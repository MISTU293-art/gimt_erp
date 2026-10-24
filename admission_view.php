<?php
// admission_view.php - Application Review & Automated Enrollment Engine
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'accounts', 'hr', 'hod']);

$pdo = getDb();
$appId = (int) ($_GET['id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? '';

$stmt = $pdo->prepare("
    SELECT a.*, d.dept_name, d.dept_code,
           s.roll_no AS enrolled_roll_no, s.id AS student_id
    FROM admissions a
    JOIN departments d ON a.department_id = d.id
    LEFT JOIN students s ON a.enrolled_student_id = s.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
    set_flash('danger', 'Admission application not found.');
    header('Location: admissions_manage.php');
    exit;
}

// Handle Status Change or Enrollment Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $newStatus = trim($_POST['new_status'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');
        $allowedStatuses = ['submitted', 'under_review', 'document_verified', 'fee_cleared', 'rejected'];

        if (in_array($newStatus, $allowedStatuses, true)) {
            $stmt = $pdo->prepare("UPDATE admissions SET status = ?, admin_remarks = ?, reviewed_by = ? WHERE id = ?");
            $stmt->execute([$newStatus, $remarks, $_SESSION['user_id'], $appId]);
            audit_log('Admission Status Update', 'admissions', $appId, "Changed status to {$newStatus}");
            set_flash('success', "Application status updated to " . ucfirst(str_replace('_', ' ', $newStatus)));
            header("Location: admission_view.php?id={$appId}");
            exit;
        }
    } elseif ($action === 'enroll_student') {
        // ENROLLMENT ENGINE: Automated Roll Number, User Account, Student Record, Ledger Setup
        if ($app['status'] === 'enrolled' || !empty($app['enrolled_student_id'])) {
            set_flash('warning', 'Applicant is already enrolled.');
            header("Location: admission_view.php?id={$appId}");
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. Generate Configurable Roll Number: GIMT-{BATCH_YEAR}-{DEPT_CODE}-{COUNT+1}
            $batchYear = (int)$app['batch_year'];
            $deptCode = strtoupper(trim($app['dept_code']));
            
            // Find existing student count for this dept & batch
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department_id = ? AND batch_year = ?");
            $countStmt->execute([$app['department_id'], $batchYear]);
            $seqNo = (int)$countStmt->fetchColumn() + 1;
            $rollNo = sprintf("GIMT-%d-%s-%03d", $batchYear, $deptCode, $seqNo);

            // 2. Provision User Login Account (Role: student)
            $studentEmail = !empty($app['email']) ? $app['email'] : strtolower("{$rollNo}@gimt.ac.in");
            // Check if user with this email already exists
            $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkUser->execute([$studentEmail]);
            $existingUserId = $checkUser->fetchColumn();

            $defaultPassword = password_hash('Password@123', PASSWORD_BCRYPT);
            if ($existingUserId) {
                $userId = (int)$existingUserId;
                $pdo->prepare("UPDATE users SET role = 'student', status = 'active' WHERE id = ?")->execute([$userId]);
            } else {
                $userStmt = $pdo->prepare("
                    INSERT INTO users (name, full_name, email, mobile, password, role, status)
                    VALUES (?, ?, ?, ?, ?, 'student', 'active')
                ");
                $userStmt->execute([$app['full_name'], $app['full_name'], $studentEmail, $app['phone'], $defaultPassword]);
                $userId = (int)$pdo->lastInsertId();
            }

            // 3. Provision Student Master Record
            $stdStmt = $pdo->prepare("
                INSERT INTO students (
                    user_id, roll_no, reg_no, department_id, batch_year, current_semester,
                    phone, guardian_name, guardian_phone, address, dob, gender,
                    blood_group, quota, admission_status, admission_id
                ) VALUES (
                    ?, ?, ?, ?, ?, 1,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, 'admitted', ?
                )
            ");
            $regNo = sprintf("REG-%d-%04d", $batchYear, $seqNo);
            $stdStmt->execute([
                $userId, $rollNo, $regNo, $app['department_id'], $batchYear,
                $app['phone'], $app['guardian_name'], $app['guardian_phone'], $app['address'],
                $app['dob'], $app['gender'], $app['blood_group'], $app['quota'], $appId
            ]);
            $studentId = (int)$pdo->lastInsertId();

            // 4. Provision Initial Fee Ledger Debit
            // Look up fee structure for this dept, quota & batch_year semester 1
            $feeStmt = $pdo->prepare("
                SELECT total_amount FROM fee_structures
                WHERE department_id = ? AND quota = ? AND batch_year = ? AND semester = 1
                LIMIT 1
            ");
            $feeStmt->execute([$app['department_id'], $app['quota'], $batchYear]);
            $initialFee = (float)$feeStmt->fetchColumn();
            if ($initialFee <= 0) {
                $initialFee = 65000.00; // default semester fee
            }

            $ledgerStmt = $pdo->prepare("
                INSERT INTO student_ledger (student_id, transaction_date, description, type, amount, payment_mode, reference_no, created_by)
                VALUES (?, CURDATE(), 'Semester 1 Institutional Tuition & Academic Debit', 'debit', ?, 'System', ?, ?)
            ");
            $ledgerRef = sprintf("INV-%d-S1-%03d", $batchYear, $seqNo);
            $ledgerStmt->execute([$studentId, $initialFee, $ledgerRef, $_SESSION['user_id']]);

            // 5. Update Application to Enrolled
            $updApp = $pdo->prepare("
                UPDATE admissions
                SET status = 'enrolled', enrolled_student_id = ?, reviewed_by = ?, admin_remarks = ?
                WHERE id = ?
            ");
            $updApp->execute([$studentId, $_SESSION['user_id'], "Enrolled with Roll No: {$rollNo}", $appId]);

            // 6. Audit Log
            audit_log('Student Enrolled', 'students', $studentId, "Enrolled {$app['full_name']} with Roll: {$rollNo}, User ID: {$userId}");

            $pdo->commit();
            set_flash('success', "Student successfully enrolled! Roll Number: {$rollNo}. User credentials and fee ledger generated.");
            header("Location: admission_view.php?id={$appId}");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Enrollment failed: ' . $e->getMessage());
            header("Location: admission_view.php?id={$appId}");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Review &bull; <?= e($app['application_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .section-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .data-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 2px; }
        .data-value { font-size: 1rem; color: #1e293b; font-weight: 500; }
        .doc-chip { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="page-layout">
        <?php
        if ($userRole === 'chairman') {
            require __DIR__ . '/partials/superAdmin_sidebar.php';
        } elseif ($userRole === 'principal') {
            require __DIR__ . '/partials/principal_sidebar.php';
        } else {
            require __DIR__ . '/partials/role_sidebar.php';
        }
        ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0" style="max-width: 1100px;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <a href="admissions_manage.php" class="text-decoration-none small text-secondary"><i class="bi bi-arrow-left"></i> Back to Admissions Pipeline</a>
                        <h1 class="h3 fw-bold mb-0">Application Dossier: <?= e($app['application_no']) ?></h1>
                    </div>
                    <div>
                        <?php if ($app['status'] === 'enrolled'): ?>
                            <span class="badge bg-success fs-6 py-2 px-3"><i class="bi bi-check-circle-fill me-1"></i> Enrolled: <?= e($app['enrolled_roll_no']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-primary fs-6 py-2 px-3 text-capitalize"><?= str_replace('_', ' ', $app['status']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="row g-4">
                    <div class="col-lg-8">

                        <!-- Personal & Guardian Info -->
                        <div class="section-card">
                            <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-person-lines-fill me-2"></i> Candidate Demographics</h2>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="data-label">Full Legal Name</div>
                                    <div class="data-value"><?= e($app['full_name']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Email Address</div>
                                    <div class="data-value"><?= e($app['email']) ?></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="data-label">Mobile Phone</div>
                                    <div class="data-value"><?= e($app['phone']) ?></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="data-label">Date of Birth</div>
                                    <div class="data-value"><?= date('d M Y', strtotime($app['dob'])) ?></div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="data-label">Gender &bull; Blood Group</div>
                                    <div class="data-value text-capitalize"><?= e($app['gender']) ?> &bull; <?= e($app['blood_group'] ?: 'N/A') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Aadhaar Number</div>
                                    <div class="data-value font-monospace"><?= e($app['aadhaar_no'] ?: 'Not Provided') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Category</div>
                                    <div class="data-value"><?= e($app['category']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Guardian Name (<?= e($app['guardian_relation']) ?>)</div>
                                    <div class="data-value"><?= e($app['guardian_name']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Guardian Contact</div>
                                    <div class="data-value"><?= e($app['guardian_phone']) ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="data-label">Permanent Address</div>
                                    <div class="data-value"><?= nl2br(e($app['address'])) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Prior Academic History -->
                        <div class="section-card">
                            <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-mortarboard me-2"></i> Prior Academic History</h2>
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Examination</th>
                                            <th>Board / Council</th>
                                            <th>Passing Year</th>
                                            <th>Aggregate %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Class 10 (Secondary)</td>
                                            <td><?= e($app['tenth_board'] ?: 'N/A') ?></td>
                                            <td><?= e($app['tenth_year'] ?: 'N/A') ?></td>
                                            <td class="fw-bold text-success"><?= e($app['tenth_percentage']) ?>%</td>
                                        </tr>
                                        <tr>
                                            <td>Class 12 / Diploma (HS)</td>
                                            <td><?= e($app['twelfth_board'] ?: 'N/A') ?></td>
                                            <td><?= e($app['twelfth_year'] ?: 'N/A') ?></td>
                                            <td class="fw-bold text-success"><?= e($app['twelfth_percentage']) ?>%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Document Verification Desk -->
                        <div class="section-card">
                            <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-folder-check me-2"></i> Uploaded Documents &amp; Audit</h2>
                            <div class="row g-2">
                                <?php
                                $docs = [
                                    ['Aadhaar Card Copy', $app['doc_aadhaar']],
                                    ['10th Marksheet', $app['doc_tenth']],
                                    ['12th Marksheet', $app['doc_twelfth']],
                                    ['Transfer / Migration Certificate', $app['doc_tc']],
                                    ['Passport Photo', $app['doc_photo']],
                                ];
                                foreach ($docs as $d):
                                ?>
                                    <div class="col-sm-6">
                                        <div class="doc-chip">
                                            <div>
                                                <div class="fw-semibold small"><?= e($d[0]) ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?= !empty($d[1]) ? '<i class="bi bi-file-earmark-check text-success"></i> Uploaded' : '<span class="text-danger"><i class="bi bi-x-circle"></i> Not uploaded</span>' ?>
                                                </div>
                                            </div>
                                            <?php if (!empty($d[1])): ?>
                                                <a href="/public/<?= e($d[1]) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Program Details & Workflow Actions -->
                    <div class="col-lg-4">

                        <!-- Program Card -->
                        <div class="section-card">
                            <h2 class="h6 fw-bold text-uppercase text-secondary mb-3">Enrolling Program</h2>
                            <div class="mb-3">
                                <div class="data-label">Selected Department</div>
                                <div class="fw-bold text-primary fs-5"><?= e($app['dept_code']) ?></div>
                                <div class="text-secondary small"><?= e($app['dept_name']) ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="data-label">Admission Quota</div>
                                <span class="badge bg-secondary text-uppercase"><?= e($app['quota']) ?> Quota</span>
                            </div>
                            <div class="mb-3">
                                <div class="data-label">Batch Academic Year</div>
                                <div class="fw-bold"><?= e($app['batch_year']) ?> &ndash; <?= (int)$app['batch_year'] + 4 ?></div>
                            </div>
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small text-muted">Application Fee:</span>
                                    <span class="small fw-bold">₹1,000.00</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="small text-muted">Status:</span>
                                    <span class="badge bg-success-subtle text-success">Paid (Gateway)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Workflow Action Card -->
                        <div class="section-card">
                            <h2 class="h6 fw-bold text-uppercase text-secondary mb-3">Workflow Validation Engine</h2>

                            <?php if ($app['status'] !== 'enrolled'): ?>
                                <form action="admission_view.php?id=<?= $appId ?>" method="post" class="mb-4">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <label class="form-label small fw-bold">Change Workflow Stage</label>
                                    <select name="new_status" class="form-select mb-2">
                                        <option value="submitted" <?= $app['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                        <option value="under_review" <?= $app['status'] === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                                        <option value="document_verified" <?= $app['status'] === 'document_verified' ? 'selected' : '' ?>>Document Verified</option>
                                        <option value="fee_cleared" <?= $app['status'] === 'fee_cleared' ? 'selected' : '' ?>>Fee Cleared</option>
                                        <option value="rejected" <?= $app['status'] === 'rejected' ? 'selected' : '' ?>>Reject Application</option>
                                    </select>
                                    <textarea name="admin_remarks" class="form-control mb-2" rows="2" placeholder="Audit remarks..."><?= e($app['admin_remarks'] ?? '') ?></textarea>
                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Update Stage</button>
                                </form>

                                <hr>

                                <!-- Automated 1-Click Enrollment Engine -->
                                <div class="bg-success-subtle p-3 rounded-3 border border-success">
                                    <h3 class="h6 fw-bold text-success mb-1"><i class="bi bi-lightning-charge-fill me-1"></i> 1-Click Final Admission</h3>
                                    <p class="small text-secondary mb-3">
                                        Automatically generates unique Roll ID (e.g. GIMT-<?= date('Y') ?>-<?= e($app['dept_code']) ?>-001), student login account, and initial ledger debit.
                                    </p>
                                    <form action="admission_view.php?id=<?= $appId ?>" method="post" onsubmit="return confirm('Confirm final institutional enrollment for <?= e(addslashes($app['full_name'])) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="enroll_student">
                                        <button type="submit" class="btn btn-success w-100 fw-bold">
                                            <i class="bi bi-person-check-fill me-1"></i> Generate Roll &amp; Enroll
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mb-3">
                                    <div class="fw-bold"><i class="bi bi-patch-check-fill"></i> Admission Finalized</div>
                                    <div class="small mt-1">Roll Number: <strong><?= e($app['enrolled_roll_no']) ?></strong></div>
                                </div>
                                <a href="student_ledger.php?student_id=<?= (int)$app['student_id'] ?>" class="btn btn-primary w-100 mb-2">
                                    <i class="bi bi-wallet2 me-1"></i> Open Student Ledger
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
