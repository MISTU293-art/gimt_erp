<?php
// student_assignments.php - Student Assignment Submission & Feedback Portal
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['student', 'teacher', 'hod', 'principal', 'chairman']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$isStudent = ($userRole === 'student');

// If student, get their student profile
$studentId = 0;
if ($isStudent) {
    $std = get_current_student();
    if (!$std) {
        set_flash('danger', 'Student account not linked.');
        header('Location: student_dashboard.php');
        exit;
    }
    $studentId = (int)$std['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 1);
}

// Handle Student File Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isStudent) {
    verify_csrf();
    $assignId = (int)($_POST['assignment_id'] ?? 0);

    // Fetch assignment due date
    $assignStmt = $pdo->prepare("SELECT due_date FROM assignments WHERE id = ?");
    $assignStmt->execute([$assignId]);
    $dueDate = $assignStmt->fetchColumn();

    if ($assignId > 0 && $dueDate) {
        if (!empty($_FILES['submission_file']['name']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'docx', 'doc', 'zip', 'rar', 'cpp', 'java', 'py', 'txt'];

            if (in_array($ext, $allowed, true)) {
                $uploadDir = __DIR__ . '/uploads/assignments/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $fileName = 'sub_' . $assignId . '_' . $studentId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $uploadDir . $fileName)) {
                    $filePath = 'uploads/assignments/' . $fileName;

                    // Late submission check: compare current timestamp with due date
                    $isLate = (time() > strtotime($dueDate)) ? 1 : 0;

                    $stmt = $pdo->prepare("
                        INSERT INTO assignment_submissions (assignment_id, student_id, file_path, submitted_at, is_late)
                        VALUES (?, ?, ?, NOW(), ?)
                        ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), submitted_at = NOW(), is_late = VALUES(is_late)
                    ");
                    $stmt->execute([$assignId, $studentId, $filePath, $isLate]);

                    audit_log('Assignment Submitted', 'assignment_submissions', $assignId, "Student {$studentId} submitted file {$fileName}. Late: {$isLate}");
                    set_flash('success', "Assignment solution submitted successfully!" . ($isLate ? " (Note: Flagged as Late Submission)" : ""));
                } else {
                    set_flash('danger', "Failed to upload file.");
                }
            } else {
                set_flash('danger', "Invalid file format. Allowed: " . implode(', ', $allowed));
            }
        } else {
            set_flash('danger', "Please select a file to upload.");
        }
    }
    header('Location: student_assignments.php');
    exit;
}

// Fetch assignments for the student's current department and semester
$stmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_name, u.name AS teacher_name,
           sub.id AS submission_id, sub.file_path, sub.submitted_at, sub.is_late,
           sub.marks_obtained, sub.feedback,
           gr.name AS grader_name
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON a.teacher_id = u.id
    JOIN students s ON s.department_id = c.department_id AND s.current_semester = c.semester
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = s.id
    LEFT JOIN users gr ON sub.graded_by = gr.id
    WHERE s.id = ?
    ORDER BY a.due_date DESC
");
$stmt->execute([$studentId]);
$assignments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Coursework &amp; Submissions &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .assign-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
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
            <div class="container-fluid p-0" style="max-width: 960px;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Coursework &amp; Digital Submissions</h1>
                        <p class="text-secondary mb-0">Enforce deadline compliance, upload solutions &amp; view teacher feedback</p>
                    </div>
                </div>

                <?= display_flash() ?>

                <?php if (empty($assignments)): ?>
                    <div class="card p-5 text-center text-muted border-0 shadow-sm">
                        <i class="bi bi-journal-x fs-1 mb-2"></i>
                        <h5>No assignments scheduled</h5>
                        <p class="small mb-0">No active assignments posted for your enrolled courses yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <?php
                        $isSubmitted = !empty($a['submission_id']);
                        $isGraded = ($a['marks_obtained'] !== null);
                        $isPastDue = (time() > strtotime($a['due_date']));
                        ?>
                        <div class="assign-card">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <span class="badge bg-primary-subtle text-primary border font-monospace me-2"><?= e($a['course_code']) ?></span>
                                    <span class="text-muted small">&bull; <?= e($a['course_name']) ?></span>
                                    <h2 class="h5 fw-bold mb-1 mt-1"><?= e($a['title']) ?></h2>
                                    <div class="small text-muted">
                                        Faculty: <strong><?= e($a['teacher_name']) ?></strong> &bull; Weightage: <strong class="text-primary"><?= (int)$a['max_marks'] ?> Marks</strong>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted text-uppercase fw-bold">Due Date</div>
                                    <div class="fw-bold <?= $isPastDue ? 'text-danger' : 'text-dark' ?>">
                                        <?= date('d M Y, h:i A', strtotime($a['due_date'])) ?>
                                    </div>
                                    <?php if ($isSubmitted): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle mt-1"><i class="bi bi-check2-circle"></i> Submitted</span>
                                        <?php if ($a['is_late']): ?>
                                            <span class="badge bg-warning-subtle text-danger border border-warning mt-1"><i class="bi bi-exclamation-triangle"></i> Late</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge <?= $isPastDue ? 'bg-danger' : 'bg-warning text-dark' ?> mt-1">
                                            <?= $isPastDue ? 'Overdue' : 'Pending Submission' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="text-secondary small mb-3"><?= nl2br(e($a['description'])) ?></p>

                            <?php if (!empty($a['attachment'])): ?>
                                <div class="mb-3">
                                    <a href="/public/<?= e($a['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-download me-1"></i> Download Problem Sheet
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Submission or Feedback Section -->
                            <?php if ($isSubmitted): ?>
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="small text-muted">Your Submission File:</div>
                                            <a href="/public/<?= e($a['file_path']) ?>" target="_blank" class="fw-bold text-decoration-none">
                                                <i class="bi bi-file-earmark-check text-success"></i> <?= basename($a['file_path']) ?>
                                            </a>
                                            <div class="small text-muted mt-1">Submitted at: <?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></div>
                                        </div>
                                        <div class="col-md-5 text-md-end mt-2 mt-md-0">
                                            <?php if ($isGraded): ?>
                                                <div class="small text-muted text-uppercase fw-bold">Grade &bull; Feedback</div>
                                                <div class="h4 fw-bold text-success mb-1">
                                                    <?= (float)$a['marks_obtained'] ?> / <?= (int)$a['max_marks'] ?>
                                                </div>
                                                <div class="small text-secondary italic">"<?= e($a['feedback']) ?>"</div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary p-2"><i class="bi bi-hourglass-split"></i> Awaiting Faculty Evaluation</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($isStudent): ?>
                                <div class="p-3 bg-light rounded-3 border">
                                    <form action="student_assignments.php" method="post" enctype="multipart/form-data">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-8">
                                                <input type="file" name="submission_file" class="form-control" required>
                                                <div class="form-text small">Accepted formats: PDF, DOCX, ZIP, C++, Java, Python (Max 25MB)</div>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-primary w-100 fw-bold">
                                                    <i class="bi bi-upload me-1"></i> Upload Solution
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
