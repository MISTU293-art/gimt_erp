<?php
// leave_requests.php - Student Leave & Medical Condonation Workflow
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$isStudent = ($userRole === 'student');

// Handle Student Submitting Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isStudent) {
    verify_csrf();
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $leaveType = trim($_POST['leave_type'] ?? 'Medical');
    $reason = trim($_POST['reason'] ?? '');

    $std = get_current_student();
    if ($std && !empty($startDate) && !empty($endDate) && !empty($reason)) {
        // Handle attachment upload
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'docx'], true)) {
                $uploadDir = __DIR__ . '/uploads/leaves/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = 'leave_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
                    $attachmentPath = 'uploads/leaves/' . $fileName;
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO leave_requests (student_id, start_date, end_date, leave_type, reason, attachment, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$std['id'], $startDate, $endDate, $leaveType, $reason, $attachmentPath]);
        audit_log('Leave Request Submitted', 'leave_requests', $pdo->lastInsertId(), "Student {$std['roll_no']}: {$leaveType} leave");
        set_flash('success', "Leave request submitted for departmental endorsement.");
        header('Location: leave_requests.php');
        exit;
    }
}

// Handle Faculty / HOD Endorsement (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isStudent && in_array($userRole, ['chairman', 'principal', 'hod', 'teacher'], true)) {
    verify_csrf();
    $leaveId = (int)($_POST['leave_id'] ?? 0);
    $statusAction = trim($_POST['status_action'] ?? '');
    $remarks = trim($_POST['review_remarks'] ?? '');

    if ($leaveId > 0 && in_array($statusAction, ['approved', 'rejected'], true)) {
        $stmt = $pdo->prepare("
            UPDATE leave_requests
            SET status = ?, reviewed_by = ?, review_remarks = ?
            WHERE id = ?
        ");
        $stmt->execute([$statusAction, $_SESSION['user_id'], $remarks, $leaveId]);
        audit_log('Leave Endorsement Action', 'leave_requests', $leaveId, "Status {$statusAction} by {$_SESSION['user_id']}");
        set_flash('success', "Leave request status updated to " . ucfirst($statusAction) . ".");
        header('Location: leave_requests.php');
        exit;
    }
}

// Fetch Leave Requests
if ($isStudent) {
    $std = get_current_student();
    $stmt = $pdo->prepare("
        SELECT lr.*, u.name AS reviewer_name
        FROM leave_requests lr
        LEFT JOIN users u ON lr.reviewed_by = u.id
        WHERE lr.student_id = ?
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute([$std['id'] ?? 0]);
    $requests = $stmt->fetchAll();
} else {
    $requests = $pdo->query("
        SELECT lr.*, s.roll_no, u.name AS student_name, d.dept_code,
               rv.name AS reviewer_name
        FROM leave_requests lr
        JOIN students s ON lr.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        LEFT JOIN users rv ON lr.reviewed_by = rv.id
        ORDER BY lr.created_at DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave &amp; Condonation Requests &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
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
            <div class="container-fluid p-0">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Student Leave &amp; Medical Condonation</h1>
                        <p class="text-secondary mb-0">Official absence regularization, medical certificates &amp; HOD endorsements</p>
                    </div>
                    <?php if ($isStudent): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                            <i class="bi bi-plus-lg me-1"></i> Apply for Leave
                        </button>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php if (!$isStudent): ?>
                                        <th>Student Details</th>
                                    <?php endif; ?>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Reason &bull; Supporting Proof</th>
                                    <th>Status</th>
                                    <th>Endorsement &bull; Remarks</th>
                                    <?php if (!$isStudent): ?>
                                        <th class="text-end">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No leave requests logged.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $req): ?>
                                        <tr>
                                            <?php if (!$isStudent): ?>
                                                <td>
                                                    <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($req['roll_no']) ?></span>
                                                    <div class="fw-bold mt-1"><?= e($req['student_name']) ?></div>
                                                    <span class="badge bg-secondary"><?= e($req['dept_code']) ?></span>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($req['leave_type']) ?></span>
                                            </td>
                                            <td>
                                                <div><strong>From:</strong> <?= date('d M Y', strtotime($req['start_date'])) ?></div>
                                                <div><strong>To:</strong> <?= date('d M Y', strtotime($req['end_date'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="small mb-1"><?= nl2br(e($req['reason'])) ?></div>
                                                <?php if (!empty($req['attachment'])): ?>
                                                    <a href="/public/<?= e($req['attachment']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">
                                                        <i class="bi bi-paperclip"></i> Certificate Proof
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'pending' => 'bg-warning-subtle text-dark border border-warning',
                                                    'approved' => 'bg-success text-white',
                                                    'rejected' => 'bg-danger text-white'
                                                ];
                                                $bClass = $badges[$req['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $bClass ?> text-uppercase"><?= e($req['status']) ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($req['reviewer_name'])): ?>
                                                    <div class="small fw-semibold"><?= e($req['reviewer_name']) ?></div>
                                                    <div class="small text-muted"><?= e($req['review_remarks'] ?: 'No notes') ?></div>
                                                <?php else: ?>
                                                    <span class="small text-muted">Awaiting review</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$isStudent): ?>
                                                <td class="text-end">
                                                    <?php if ($req['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#actionModal_<?= $req['id'] ?>">
                                                            <i class="bi bi-check2"></i> Review
                                                        </button>

                                                        <!-- Action Modal -->
                                                        <div class="modal fade" id="actionModal_<?= $req['id'] ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content text-start">
                                                                    <form action="leave_requests.php" method="post">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="leave_id" value="<?= $req['id'] ?>">
                                                                        <div class="modal-header">
                                                                            <h2 class="modal-title h5 fw-bold">Endorse Leave Request</h2>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p class="small text-secondary mb-3">Applicant: <strong><?= e($req['student_name']) ?></strong> (<?= e($req['roll_no']) ?>)</p>
                                                                            <div class="mb-3">
                                                                                <label class="form-label required">Action</label>
                                                                                <select name="status_action" class="form-select" required>
                                                                                    <option value="approved">Approve &amp; Endorse Leave</option>
                                                                                    <option value="rejected">Reject Leave Request</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Review Remarks / Condonation Notes</label>
                                                                                <textarea name="review_remarks" class="form-control" rows="2" placeholder="e.g. Endorsed upon verification of medical fitness report."></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-primary">Save Endorsement</button>
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

    <!-- Apply Leave Modal for Student -->
    <?php if ($isStudent): ?>
        <div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="leave_requests.php" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h2 class="modal-title h5 fw-bold" id="applyLeaveModalLabel">Submit Leave / Condonation Application</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Leave Classification</label>
                                <select name="leave_type" class="form-select" required>
                                    <option value="Medical">Medical Illness / Hospitalization</option>
                                    <option value="Personal">Personal / Family Emergency</option>
                                    <option value="Official Duty">Institute Representation / Tech Fest / Sports</option>
                                    <option value="Condonation">Attendance Shortage Condonation Appeal</option>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label required">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label required">End Date</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Detailed Reason</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Provide factual details..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Supporting Proof (Medical Prescriptions / Certificate / PDF / Image)</label>
                                <input type="file" name="attachment" class="form-control" accept=".pdf,image/*,.docx">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Submit for Endorsement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
