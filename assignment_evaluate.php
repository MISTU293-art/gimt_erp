<?php
// assignment_evaluate.php - Inline Assignment Evaluation & Constructive Feedback Workbench
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$assignId = (int)($_GET['assignment_id'] ?? 0);

// Fetch Assignment Details
$assignStmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_name, d.dept_name, d.dept_code, u.name AS teacher_name
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON a.teacher_id = u.id
    WHERE a.id = ?
    LIMIT 1
");
$assignStmt->execute([$assignId]);
$assignment = $assignStmt->fetch();

if (!$assignment) {
    set_flash('danger', 'Assignment not found.');
    header('Location: assignments_manage.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!can_teacher_access_course($userId, (int)$assignment['course_id'], $userRole)) {
    set_flash('danger', 'Access Denied: You are not the assigned teacher for this course. Evaluation blocked under institutional regulations.');
    header('Location: assignments_manage.php');
    exit;
}

// Handle Grading Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $marks = (float)($_POST['marks_obtained'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    if ($submissionId > 0) {
        if ($marks < 0 || $marks > (float)$assignment['max_marks']) {
            set_flash('danger', "Marks must be between 0 and {$assignment['max_marks']}.");
        } else {
            $stmt = $pdo->prepare("
                UPDATE assignment_submissions
                SET marks_obtained = ?, feedback = ?, graded_by = ?, graded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$marks, $feedback, $_SESSION['user_id'], $submissionId]);
            audit_log('Assignment Graded', 'assignment_submissions', $submissionId, "Awarded {$marks}/{$assignment['max_marks']}");
            set_flash('success', "Evaluation recorded and synced to student portal.");
        }
    }
    header("Location: assignment_evaluate.php?assignment_id={$assignId}");
    exit;
}

// Fetch Submissions for this Assignment
$subStmt = $pdo->prepare("
    SELECT s.id AS student_id, s.roll_no, u.name AS student_name, u.email AS student_email,
           sub.id AS submission_id, sub.file_path, sub.submitted_at, sub.is_late,
           sub.marks_obtained, sub.feedback, sub.graded_at,
           gr.name AS grader_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN courses c ON c.id = ?
    LEFT JOIN assignment_submissions sub ON sub.assignment_id = ? AND sub.student_id = s.id
    LEFT JOIN users gr ON sub.graded_by = gr.id
    WHERE s.department_id = c.department_id AND s.current_semester = c.semester AND s.admission_status = 'admitted'
    ORDER BY s.roll_no
");
$subStmt->execute([$assignment['course_id'], $assignId]);
$submissions = $subStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluate Coursework &bull; <?= e($assignment['title']) ?></title>
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
                        <a href="assignments_manage.php" class="text-secondary small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Assignments</a>
                        <h1 class="h3 fw-bold mb-1">Evaluate: <?= e($assignment['title']) ?></h1>
                        <p class="text-secondary mb-0">
                            [<?= e($assignment['course_code']) ?>] <?= e($assignment['course_name']) ?> &bull; Max Weightage: <strong><?= (int)$assignment['max_marks'] ?> Marks</strong> &bull; Due: <?= date('d M Y, h:i A', strtotime($assignment['due_date'])) ?>
                        </p>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Roll No &bull; Student Name</th>
                                    <th>Submission Status</th>
                                    <th>Attachment File</th>
                                    <th>Marks Awarded (Max: <?= (int)$assignment['max_marks'] ?>)</th>
                                    <th>Evaluator Feedback</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $s): ?>
                                    <?php
                                    $hasSubmitted = !empty($s['submission_id']);
                                    $isGraded = ($s['marks_obtained'] !== null);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($s['roll_no']) ?></span>
                                            <div class="fw-bold mt-1"><?= e($s['student_name']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($hasSubmitted): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2"></i> Submitted</span>
                                                <div class="small text-muted"><?= date('d M Y, h:i A', strtotime($s['submitted_at'])) ?></div>
                                                <?php if ($s['is_late']): ?>
                                                    <span class="badge bg-danger text-white"><i class="bi bi-exclamation-triangle"></i> Late Submission</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border">Not Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($hasSubmitted): ?>
                                                <a href="/public/<?= e($s['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download me-1"></i> <?= basename($s['file_path']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isGraded): ?>
                                                <span class="h5 fw-bold text-success mb-0"><?= (float)$s['marks_obtained'] ?></span>
                                                <span class="text-muted small"> / <?= (int)$assignment['max_marks'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">Not graded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small text-secondary"><?= e($s['feedback'] ?: '&mdash;') ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($hasSubmitted): ?>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal_<?= $s['submission_id'] ?>">
                                                    <i class="bi bi-pencil-square me-1"></i> <?= $isGraded ? 'Edit Grade' : 'Grade Solution' ?>
                                                </button>

                                                <!-- Grade Modal -->
                                                <div class="modal fade" id="gradeModal_<?= $s['submission_id'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content text-start">
                                                            <form action="assignment_evaluate.php?assignment_id=<?= $assignId ?>" method="post">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="submission_id" value="<?= (int)$s['submission_id'] ?>">
                                                                <div class="modal-header">
                                                                    <h2 class="modal-title h5 fw-bold">Evaluate Solution &bull; <?= e($s['student_name']) ?></h2>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label required fw-bold">Marks Obtained (Out of <?= (int)$assignment['max_marks'] ?>)</label>
                                                                        <input type="number" step="0.5" name="marks_obtained" class="form-control form-control-lg" max="<?= (int)$assignment['max_marks'] ?>" min="0" value="<?= $s['marks_obtained'] !== null ? $s['marks_obtained'] : '' ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Constructive Feedback / Annotations</label>
                                                                        <textarea name="feedback" class="form-control" rows="3" placeholder="Provide actionable feedback on code quality, logic, accuracy..."><?= e($s['feedback'] ?? '') ?></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-success fw-bold">Commit &amp; Publish Grade</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">&bull;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
