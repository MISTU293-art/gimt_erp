<?php
// makaut_moocs.php - MAKAUT B.Tech Honours MOOCs (SWAYAM / NPTEL) Credits Management Portal
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$isStudent = ($userRole === 'student');

// Handle Student Submitting MOOCs Course Claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isStudent) {
    verify_csrf();
    $std = get_current_student();
    $platform = trim($_POST['platform'] ?? 'NPTEL');
    $courseTitle = trim($_POST['course_title'] ?? '');
    $duration = trim($_POST['course_duration'] ?? '12 Weeks');
    $creditsClaimed = (int)($_POST['credits_claimed'] ?? 3);
    $scorePct = !empty($_POST['score_percentage']) ? (float)$_POST['score_percentage'] : null;
    $acadYear = trim($_POST['academic_year'] ?? '2026-2027');

    if ($std && !empty($courseTitle) && $creditsClaimed > 0) {
        $certificatePath = null;
        if (!empty($_FILES['certificate']['name']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['certificate']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $uploadDir = __DIR__ . '/uploads/moocs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newFile = 'moocs_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['certificate']['tmp_name'], $uploadDir . $newFile)) {
                    $certificatePath = 'uploads/moocs/' . $newFile;
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO makaut_moocs_records (student_id, academic_year, platform, course_title, course_duration, credits_claimed, score_percentage, certificate_file, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$std['id'], $acadYear, $platform, $courseTitle, $duration, $creditsClaimed, $scorePct, $certificatePath]);
        audit_log('MOOCs Claim Submitted', 'makaut_moocs_records', $pdo->lastInsertId(), "Student {$std['roll_no']}: Claimed {$creditsClaimed} credits for {$courseTitle} ({$platform})");
        set_flash('success', "MOOCs certification submitted successfully for departmental faculty verification.");
        header('Location: makaut_moocs.php');
        exit;
    }
}

// Handle Faculty / HOD MOOCs Endorsement & Awarding Credits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isStudent && in_array($userRole, ['chairman', 'principal', 'hod', 'teacher'], true)) {
    verify_csrf();
    $recordId = (int)($_POST['record_id'] ?? 0);
    $statusAction = trim($_POST['status_action'] ?? '');
    $awardedCredits = (int)($_POST['credits_awarded'] ?? 0);

    if ($recordId > 0 && in_array($statusAction, ['approved', 'rejected'], true)) {
        try {
            $pdo->beginTransaction();

            $recStmt = $pdo->prepare("SELECT student_id, credits_claimed, course_title FROM makaut_moocs_records WHERE id = ?");
            $recStmt->execute([$recordId]);
            $rec = $recStmt->fetch();

            if ($rec) {
                $finalCredits = ($statusAction === 'approved') ? ($awardedCredits > 0 ? $awardedCredits : (int)$rec['credits_claimed']) : 0;

                $stmt = $pdo->prepare("
                    UPDATE makaut_moocs_records
                    SET status = ?, credits_awarded = ?, reviewed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$statusAction, $finalCredits, $_SESSION['user_id'], $recordId]);

                // Recalculate total MOOCs credits for student
                $calcStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(credits_awarded), 0)
                    FROM makaut_moocs_records
                    WHERE student_id = ? AND status = 'approved'
                ");
                $calcStmt->execute([$rec['student_id']]);
                $newTotal = (int)$calcStmt->fetchColumn();

                $pdo->prepare("UPDATE students SET moocs_credits = ? WHERE id = ?")->execute([$newTotal, $rec['student_id']]);

                audit_log('MOOCs Claim Evaluated', 'makaut_moocs_records', $recordId, "Status {$statusAction}, Awarded {$finalCredits} credits to Student {$rec['student_id']}");
                $pdo->commit();
                set_flash('success', "MOOCs credit award updated. Cumulative Student MOOCs Credits: {$newTotal} / 20.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Failed to evaluate MOOCs record: " . $e->getMessage());
        }
        header('Location: makaut_moocs.php');
        exit;
    }
}

// Fetch Records
if ($isStudent) {
    $std = get_current_student();
    $studentId = (int)($std['id'] ?? 0);
    $recordsStmt = $pdo->prepare("
        SELECT m.*, u.name AS reviewer_name
        FROM makaut_moocs_records m
        LEFT JOIN users u ON m.reviewed_by = u.id
        WHERE m.student_id = ?
        ORDER BY m.created_at DESC
    ");
    $recordsStmt->execute([$studentId]);
    $moocsRecords = $recordsStmt->fetchAll();

    $totalCredits = (int)$pdo->query("SELECT moocs_credits FROM students WHERE id = {$studentId}")->fetchColumn();
} else {
    $filterStatus = trim($_GET['status'] ?? 'all');
    $query = "
        SELECT m.*, s.roll_no, s.makaut_reg_no, s.makaut_roll_no, u.name AS student_name, d.dept_code, rev.name AS reviewer_name
        FROM makaut_moocs_records m
        JOIN students s ON m.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        LEFT JOIN users rev ON m.reviewed_by = rev.id
    ";
    if ($filterStatus === 'pending') {
        $query .= " WHERE m.status = 'pending'";
    } elseif ($filterStatus === 'approved') {
        $query .= " WHERE m.status = 'approved'";
    }
    $query .= " ORDER BY m.created_at DESC";
    $moocsRecords = $pdo->query($query)->fetchAll();
    $totalCredits = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAKAUT MOOCs (SWAYAM / NPTEL) Portal &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .progress-bar-moocs { background: linear-gradient(90deg, #10b981 0%, #059669 100%); }
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
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">MAKAUT B.Tech Honours</span>
                            <span class="badge bg-secondary-subtle text-secondary font-monospace">AICTE / SWAYAM / NPTEL</span>
                        </div>
                        <h1 class="h3 fw-bold mb-0">Massive Open Online Courses (MOOCs) Portal</h1>
                        <p class="text-secondary small mb-0">E-Learning Credits Tracker for B.Tech with Honours Degree Eligibility (Target: 20 Credits)</p>
                    </div>
                    <?php if ($isStudent): ?>
                        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#claimMoocsModal">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Submit MOOCs Certificate
                        </button>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <!-- Progress Header for Student -->
                <?php if ($isStudent): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                        <div class="row align-items-center g-3">
                            <div class="col-md-7">
                                <span class="small text-muted text-uppercase fw-bold">MAKAUT Honours Degree Qualification Status</span>
                                <div class="d-flex align-items-baseline gap-2 mt-1">
                                    <span class="display-6 fw-bold text-success font-monospace"><?= $totalCredits ?></span>
                                    <span class="text-secondary fs-5">/ 20 Target MOOCs Credits</span>
                                </div>
                                <div class="progress mt-3" style="height: 10px;">
                                    <div class="progress-bar progress-bar-moocs" style="width: <?= min(100, round(($totalCredits / 20) * 100)) ?>%"></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <?php if ($totalCredits >= 20): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i> Congratulations! Mandatory 20 MOOCs credits completed for B.Tech with Honours degree.
                                    <?php else: ?>
                                        <i class="bi bi-info-circle text-primary"></i> <?= 20 - $totalCredits ?> more verified credits needed from approved SWAYAM / NPTEL / Coursera courses.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-5 border-start-md ps-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <div class="fw-bold small mb-1"><i class="bi bi-mortarboard-fill text-primary me-1"></i> MAKAUT Honours Regulation:</div>
                                    <p class="small text-secondary mb-0">
                                        Students obtaining an aggregate DGPA &ge; 7.50 with an additional 20 credits through MOOCs from 2nd to 4th year are awarded <strong>B.Tech (Honours)</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter for Faculty -->
                <?php if (!$isStudent): ?>
                    <div class="card p-3 mb-4 border-0 shadow-sm bg-white">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="fw-bold text-secondary">
                                <i class="bi bi-funnel me-1"></i> Filter Submissions
                            </div>
                            <div class="btn-group">
                                <a href="makaut_moocs.php?status=all" class="btn btn-sm <?= ($filterStatus ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All Records</a>
                                <a href="makaut_moocs.php?status=pending" class="btn btn-sm <?= ($filterStatus ?? '') === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">Pending Verification</a>
                                <a href="makaut_moocs.php?status=approved" class="btn btn-sm <?= ($filterStatus ?? '') === 'approved' ? 'btn-primary' : 'btn-outline-secondary' ?>">Approved</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- MOOCs Records List -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php if (!$isStudent): ?>
                                        <th>Student / Roll No</th>
                                    <?php endif; ?>
                                    <th>Platform &amp; Duration</th>
                                    <th>Course Title</th>
                                    <th>Credits Claimed</th>
                                    <th>Score</th>
                                    <th>Certificate</th>
                                    <th>Status</th>
                                    <?php if (!$isStudent): ?>
                                        <th class="text-end">Verification Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($moocsRecords)): ?>
                                    <tr>
                                        <td colspan="<?= $isStudent ? 6 : 8 ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-mortarboard fs-1 d-block mb-2"></i>
                                            No MOOCs course records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($moocsRecords as $r): ?>
                                        <tr>
                                            <?php if (!$isStudent): ?>
                                                <td>
                                                    <div class="fw-bold"><?= e($r['student_name'] ?? 'N/A') ?></div>
                                                    <div class="small font-monospace text-primary"><?= e($r['roll_no'] ?? '') ?> (<?= e($r['dept_code'] ?? '') ?>)</div>
                                                    <?php if (!empty($r['makaut_roll_no'])): ?>
                                                        <div class="small text-muted font-monospace">Univ: <?= e($r['makaut_roll_no']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge bg-dark-subtle text-dark border px-2 py-1"><?= e($r['platform']) ?></span>
                                                <div class="small text-muted mt-1"><?= e($r['course_duration']) ?> &bull; <?= e($r['academic_year']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= e($r['course_title']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-primary border font-monospace fs-6">
                                                    <?= (int)$r['credits_claimed'] ?> Credits
                                                </span>
                                                <?php if ($r['status'] === 'approved'): ?>
                                                    <div class="small text-success fw-bold mt-1">Awarded: <?= (int)$r['credits_awarded'] ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $r['score_percentage'] !== null ? number_format((float)$r['score_percentage'], 1) . '%' : '&mdash;' ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($r['certificate_file'])): ?>
                                                    <a href="<?= e($r['certificate_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-file-earmark-pdf"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">No file</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($r['status'] === 'approved'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                        <i class="bi bi-check-circle"></i> Approved
                                                    </span>
                                                <?php elseif ($r['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                        <i class="bi bi-x-circle"></i> Rejected
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                        <i class="bi bi-clock"></i> Under Review
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$isStudent): ?>
                                                <td class="text-end">
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                        <form method="post" action="makaut_moocs.php" class="d-inline-flex gap-1 align-items-center">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                                                            <input type="number" name="credits_awarded" value="<?= (int)$r['credits_claimed'] ?>" min="1" max="6" class="form-control form-control-sm text-center" style="width: 65px;" title="Approved Credits">
                                                            <button type="submit" name="status_action" value="approved" class="btn btn-sm btn-success fw-bold" title="Approve Credits">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button type="submit" name="status_action" value="rejected" class="btn btn-sm btn-danger" title="Reject Submission">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Reviewed</span>
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

    <!-- Student Claim MOOCs Modal -->
    <?php if ($isStudent): ?>
        <div class="modal fade" id="claimMoocsModal" tabindex="-1" aria-labelledby="claimMoocsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="post" action="makaut_moocs.php" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="claimMoocsModalLabel"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Submit MOOCs Certification</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Online Platform</label>
                                <select name="platform" class="form-select" required>
                                    <option value="NPTEL">NPTEL (IIT/IISc)</option>
                                    <option value="SWAYAM">SWAYAM (MHRD / AICTE)</option>
                                    <option value="Coursera">Coursera</option>
                                    <option value="edX">edX</option>
                                    <option value="Other">Other Approved Platform</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Course Title</label>
                                <input type="text" name="course_title" class="form-control" placeholder="e.g. Programming in Java / Cloud Computing" required>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Course Duration</label>
                                    <select name="course_duration" class="form-select">
                                        <option value="4 Weeks">4 Weeks (1 Credit)</option>
                                        <option value="8 Weeks">8 Weeks (2 Credits)</option>
                                        <option value="12 Weeks" selected>12 Weeks (3 Credits)</option>
                                        <option value="16 Weeks">16 Weeks (4 Credits)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Credits Claimed</label>
                                    <input type="number" name="credits_claimed" class="form-control" value="3" min="1" max="4" required>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Score Percentage (%)</label>
                                    <input type="number" step="0.1" name="score_percentage" class="form-control" placeholder="e.g. 84.5" min="40" max="100">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Academic Year</label>
                                    <input type="text" name="academic_year" class="form-control" value="2026-2027">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Verified Certificate File (PDF / Image)</label>
                                <input type="file" name="certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text">Upload official completion certificate showing final marks/grade.</div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold">Submit for Verification</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
