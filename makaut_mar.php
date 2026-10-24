<?php
// makaut_mar.php - MAKAUT Mandatory Additional Requirements (MAR 100 Points) Portal
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$isStudent = ($userRole === 'student');

// Handle Student Submitting MAR Activity Claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isStudent) {
    verify_csrf();
    $std = get_current_student();
    $category = trim($_POST['category'] ?? '');
    $activityName = trim($_POST['activity_name'] ?? '');
    $claimedPoints = (int)($_POST['claimed_points'] ?? 10);
    $acadYear = trim($_POST['academic_year'] ?? '2026-2027');

    if ($std && !empty($category) && !empty($activityName) && $claimedPoints > 0) {
        $certificatePath = null;
        if (!empty($_FILES['certificate']['name']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['certificate']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $uploadDir = __DIR__ . '/uploads/mar/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newFile = 'mar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['certificate']['tmp_name'], $uploadDir . $newFile)) {
                    $certificatePath = 'uploads/mar/' . $newFile;
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO makaut_mar_records (student_id, academic_year, category, activity_name, claimed_points, certificate_file, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$std['id'], $acadYear, $category, $activityName, $claimedPoints, $certificatePath]);
        audit_log('MAR Activity Claimed', 'makaut_mar_records', $pdo->lastInsertId(), "Student {$std['roll_no']}: Claimed {$claimedPoints} MAR points for {$category}");
        set_flash('success', "MAR activity claim submitted for departmental review.");
        header('Location: makaut_mar.php');
        exit;
    }
}

// Handle Faculty / HOD MAR Endorsement & Awarding Points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isStudent && in_array($userRole, ['chairman', 'principal', 'hod', 'teacher'], true)) {
    verify_csrf();
    $recordId = (int)($_POST['record_id'] ?? 0);
    $statusAction = trim($_POST['status_action'] ?? '');
    $awardedPoints = (int)($_POST['awarded_points'] ?? 0);
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if ($recordId > 0 && in_array($statusAction, ['approved', 'rejected'], true)) {
        try {
            $pdo->beginTransaction();

            // Fetch record
            $recStmt = $pdo->prepare("SELECT student_id, claimed_points FROM makaut_mar_records WHERE id = ?");
            $recStmt->execute([$recordId]);
            $rec = $recStmt->fetch();

            if ($rec) {
                $finalPoints = ($statusAction === 'approved') ? ($awardedPoints > 0 ? $awardedPoints : (int)$rec['claimed_points']) : 0;

                $stmt = $pdo->prepare("
                    UPDATE makaut_mar_records
                    SET status = ?, awarded_points = ?, reviewed_by = ?, review_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$statusAction, $finalPoints, $_SESSION['user_id'], $reviewNotes, $recordId]);

                // Recalculate total MAR points for student
                $calcStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(awarded_points), 0)
                    FROM makaut_mar_records
                    WHERE student_id = ? AND status = 'approved'
                ");
                $calcStmt->execute([$rec['student_id']]);
                $newTotal = (int)$calcStmt->fetchColumn();

                $pdo->prepare("UPDATE students SET mar_points = ? WHERE id = ?")->execute([$newTotal, $rec['student_id']]);

                audit_log('MAR Claim Evaluated', 'makaut_mar_records', $recordId, "Status {$statusAction}, Awarded {$finalPoints} pts to Student {$rec['student_id']}");
                $pdo->commit();
                set_flash('success', "MAR activity claim updated. Cumulative Points: {$newTotal} / 100.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Failed to evaluate MAR record: " . $e->getMessage());
        }
        header('Location: makaut_mar.php');
        exit;
    }
}

// Fetch MAR records
if ($isStudent) {
    $std = get_current_student();
    $studentId = (int)($std['id'] ?? 0);
    $recordsStmt = $pdo->prepare("
        SELECT mar.*, u.name AS reviewer_name
        FROM makaut_mar_records mar
        LEFT JOIN users u ON mar.reviewed_by = u.id
        WHERE mar.student_id = ?
        ORDER BY mar.created_at DESC
    ");
    $recordsStmt->execute([$studentId]);
    $records = $recordsStmt->fetchAll();

    $approvedPoints = (int)($std['mar_points'] ?? 0);
} else {
    $records = $pdo->query("
        SELECT mar.*, s.roll_no, s.makaut_reg_no, u.name AS student_name, d.dept_code,
               rv.name AS reviewer_name
        FROM makaut_mar_records mar
        JOIN students s ON mar.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        LEFT JOIN users rv ON mar.reviewed_by = rv.id
        ORDER BY mar.created_at DESC
    ")->fetchAll();

    $approvedPoints = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAKAUT Mandatory Additional Requirements (MAR) &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .mar-gauge { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
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
            <div class="container-fluid p-0" style="max-width: 1050px;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <span class="badge text-bg-primary px-3 py-1 rounded-pill mb-1">MAKAUT STATUTORY DEGREE MANDATE</span>
                        <h1 class="h3 fw-bold mb-1">Mandatory Additional Requirements (MAR)</h1>
                        <p class="text-secondary mb-0">MAKAUT University 100 MAR Points Tracking, Documentation &amp; Certification Portal</p>
                    </div>
                    <?php if ($isStudent): ?>
                        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#claimMarModal">
                            <i class="bi bi-plus-lg me-1"></i> Submit Activity Claim
                        </button>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <?php if ($isStudent && $std): ?>
                    <!-- Student MAR 100-Point Progress Meter -->
                    <div class="mar-gauge mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold">MAKAUT 100 MAR Points Degree Progress</span>
                                <div class="h3 fw-bold mb-0 text-primary"><?= $approvedPoints ?> / 100 Points Earned</div>
                            </div>
                            <span class="badge <?= $approvedPoints >= 100 ? 'bg-success' : 'bg-warning text-dark' ?> fs-6 py-2 px-3">
                                <?= $approvedPoints >= 100 ? '<i class="bi bi-check-all"></i> Degree MAR Requirement Met' : (100 - $approvedPoints) . ' Points Remaining' ?>
                            </span>
                        </div>
                        <div class="progress" style="height: 14px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated <?= $approvedPoints >= 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= min(100, $approvedPoints) ?>%"></div>
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i> Under MAKAUT regulation, every B.Tech student must complete 100 verified MAR points across 8 semesters to be eligible for Degree conferment.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- MAR Table -->
                <div class="table-card">
                    <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <h2 class="h6 fw-bold mb-0"><i class="bi bi-award-fill text-warning me-1"></i> Logged MAR Activity Claims</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php if (!$isStudent): ?>
                                        <th>Student Details</th>
                                    <?php endif; ?>
                                    <th>Activity Category</th>
                                    <th>Event / Title</th>
                                    <th>Claimed</th>
                                    <th>Awarded</th>
                                    <th>Status</th>
                                    <th>Certificate</th>
                                    <?php if (!$isStudent): ?>
                                        <th class="text-end">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($records)): ?>
                                    <tr>
                                        <td colspan="<?= $isStudent ? 6 : 8 ?>" class="text-center py-4 text-muted">No MAR activity claims logged yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($records as $r): ?>
                                        <tr>
                                            <?php if (!$isStudent): ?>
                                                <td>
                                                    <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($r['roll_no']) ?></span>
                                                    <div class="fw-bold mt-1"><?= e($r['student_name']) ?></div>
                                                    <span class="badge bg-secondary"><?= e($r['dept_code']) ?></span>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($r['category']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= e($r['activity_name']) ?></div>
                                                <div class="small text-muted">Term: <?= e($r['academic_year']) ?></div>
                                            </td>
                                            <td class="font-monospace fw-bold"><?= (int)$r['claimed_points'] ?> Pts</td>
                                            <td class="font-monospace fw-bold text-success fs-6">
                                                <?= $r['awarded_points'] !== null ? (int)$r['awarded_points'] . ' Pts' : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'pending' => 'bg-warning-subtle text-dark border border-warning',
                                                    'approved' => 'bg-success text-white',
                                                    'rejected' => 'bg-danger text-white'
                                                ];
                                                ?>
                                                <span class="badge <?= $badges[$r['status']] ?? 'bg-secondary' ?> text-uppercase"><?= e($r['status']) ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($r['certificate_file'])): ?>
                                                    <a href="<?= e($r['certificate_file']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-file-earmark-check"></i> Proof
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$isStudent): ?>
                                                <td class="text-end">
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#evalModal_<?= $r['id'] ?>">
                                                            <i class="bi bi-check2"></i> Evaluate
                                                        </button>

                                                        <!-- Evaluate Modal -->
                                                        <div class="modal fade" id="evalModal_<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content text-start">
                                                                    <form action="makaut_mar.php" method="post">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                                                                        <div class="modal-header">
                                                                            <h3 class="modal-title h5 fw-bold">Evaluate MAR Claim &bull; <?= e($r['student_name']) ?></h3>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="mb-3">
                                                                                <label class="form-label required">Action</label>
                                                                                <select name="status_action" class="form-select" required>
                                                                                    <option value="approved">Approve &amp; Award MAR Points</option>
                                                                                    <option value="rejected">Reject Claim</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label class="form-label required">Awarded Points (Claimed: <?= (int)$r['claimed_points'] ?>)</label>
                                                                                <input type="number" name="awarded_points" class="form-control" value="<?= (int)$r['claimed_points'] ?>" min="0" max="50" required>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Review Remarks / Verification Note</label>
                                                                                <textarea name="review_notes" class="form-control" rows="2" placeholder="e.g. Verified official certificate issued by state organizer."></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-primary">Commit Evaluation</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">&bull;</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Student Claim Modal -->
    <?php if ($isStudent): ?>
        <div class="modal fade" id="claimMarModal" tabindex="-1" aria-labelledby="claimMarModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="makaut_mar.php" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h2 class="modal-title h5 fw-bold" id="claimMarModalLabel">Submit MAKAUT MAR Activity Claim</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required fw-bold">MAKAUT MAR Activity Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="Tree Plantation &amp; Environmental Drive">Tree Plantation &amp; Cleanliness Drive (15 Points)</option>
                                    <option value="Blood Donation / Health Outreach">Blood Donation Camp / Health Awareness Camp (10 Points)</option>
                                    <option value="Technical Fest / Competition">Participation in Tech Fest / Hackathon / Robotics (20 Points)</option>
                                    <option value="Sports / Yoga / Tournaments">Sports / Yoga / Inter-College Athletics (15 Points)</option>
                                    <option value="Technical Paper Publication">Publication of Technical Paper / Patent Application (25 Points)</option>
                                    <option value="Cultural / Debate / Quiz">Cultural Performance / Debate / Youth Parliament (10 Points)</option>
                                    <option value="Community Service / NSS / NCC">Community Social Service / NSS / NCC Volunteer (20 Points)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required fw-bold">Activity / Event Title</label>
                                <input type="text" name="activity_name" class="form-control" placeholder="e.g. State Level Hackathon 2026 or Tree Plantation Campaign" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label required fw-bold">Claimed Points</label>
                                    <input type="number" name="claimed_points" class="form-control" value="15" min="5" max="30" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label required fw-bold">Academic Term</label>
                                    <input type="text" name="academic_year" class="form-control" value="2026-2027" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required fw-bold">Proof / Certificate Upload (PDF, JPG, PNG)</label>
                                <input type="file" name="certificate" class="form-control" accept=".pdf,image/*" required>
                                <div class="form-text">Upload signed certificate or official event participation proof.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold">Submit for Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
