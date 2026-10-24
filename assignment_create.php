<?php
// assignment_create.php - Assignment Creation Workbench
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Fetch assigned courses
$courses = get_permitted_courses($userId, $userRole);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $courseId = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $maxMarks = (int)($_POST['max_marks'] ?? 100);
    $dueDate = trim($_POST['due_date'] ?? '');

    // Strict regulatory rule: Only assigned teacher or HOD/Principal can publish assignments
    if (!can_teacher_access_course($userId, $courseId, $userRole)) {
        audit_log('Unauthorized Assignment Creation Blocked', 'assignments', $courseId, "User {$userId} ({$userRole}) denied assignment creation");
        set_flash('danger', 'Access Denied: You are not the assigned teacher for this course. Assignment publishing blocked.');
        header('Location: assignments_manage.php');
        exit;
    }

    if ($courseId > 0 && !empty($title) && !empty($dueDate)) {
        // Handle file attachment
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'docx', 'doc', 'zip', 'txt'], true)) {
                $uploadDir = __DIR__ . '/uploads/assignments/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newFileName = 'assign_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newFileName)) {
                    $attachmentPath = 'uploads/assignments/' . $newFileName;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO assignments (course_id, teacher_id, title, description, attachment, max_marks, due_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseId, $userId, $title, $description, $attachmentPath, $maxMarks, $dueDate]);
            $assignId = $pdo->lastInsertId();

            audit_log('Assignment Published', 'assignments', $assignId, "Published '{$title}' for course {$courseId}");
            set_flash('success', "Assignment published successfully.");
            header('Location: assignments_manage.php');
            exit;

        } catch (Exception $e) {
            set_flash('danger', "Error creating assignment: " . $e->getMessage());
        }
    } else {
        set_flash('danger', "Please complete all required fields.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .form-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); padding: 30px; }
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
            <div class="container-fluid p-0" style="max-width: 800px;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <a href="assignments_manage.php" class="text-secondary small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Assignments Master</a>
                        <h1 class="h3 fw-bold mb-0">Create Assignment Workbench</h1>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="form-card">
                    <form action="assignment_create.php" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label required fw-bold">Select Academic Course</label>
                            <select name="course_id" class="form-select form-select-lg" required>
                                <option value="" disabled selected>Choose Course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>">[<?= e($c['dept_code']) ?> Sem <?= $c['semester'] ?>] <?= e($c['course_code']) ?> &mdash; <?= e($c['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required fw-bold">Assignment Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Implementation of Graph Shortest Path Algorithms" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label required fw-bold">Maximum Weightage Marks</label>
                                <input type="number" name="max_marks" class="form-control" value="25" min="5" max="100" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label required fw-bold">Strict Submission Deadline</label>
                                <input type="datetime-local" name="due_date" class="form-control" required>
                                <div class="form-text text-danger">Submissions past this timestamp are auto-flagged as late.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Detailed Instructions / Problem Description</label>
                            <textarea name="description" class="form-control" rows="5" placeholder="Specify instructions, deliverables, programming languages, format requirements..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Problem Sheet Attachment (PDF, DOCX, ZIP)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.zip,.txt">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                            <i class="bi bi-cloud-arrow-up-fill me-2"></i> Publish Assignment to Students
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
