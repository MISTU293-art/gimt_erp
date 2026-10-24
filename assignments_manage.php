<?php
// assignments_manage.php - Faculty Assignment Creation & Evaluation Workbench
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Fetch courses assigned to this user (or all if admin/principal/hod)
$query = "
    SELECT a.*, c.course_code, c.course_name, c.semester, d.dept_code,
           u.name AS teacher_name,
           COUNT(asub.id) AS submission_count,
           SUM(CASE WHEN asub.marks_obtained IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON a.teacher_id = u.id
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id
";

$params = [];
if ($userRole === 'teacher') {
    $query .= " WHERE a.teacher_id = ?";
    $params[] = $userId;
}

$query .= " GROUP BY a.id ORDER BY a.due_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Assignments Master &bull; GIMT ERP</title>
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
                        <h1 class="h3 fw-bold mb-1">Digital Coursework &amp; Assignments</h1>
                        <p class="text-secondary mb-0">Publish problem sheets, track late submissions &amp; inline grading workbench</p>
                    </div>
                    <div>
                        <a href="assignment_create.php" class="btn btn-primary fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> Create New Assignment
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course &bull; Department</th>
                                    <th>Assignment Title</th>
                                    <th>Weightage &bull; Max Marks</th>
                                    <th>Strict Due Date</th>
                                    <th>Submission Progress</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No assignments published yet. Click 'Create New Assignment' to post one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assignments as $a): ?>
                                        <?php
                                        $isPastDue = (strtotime($a['due_date']) < time());
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border font-monospace"><?= e($a['course_code']) ?></span>
                                                <div class="small text-muted mt-1"><?= e($a['dept_code']) ?> Sem <?= (int)$a['semester'] ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= e($a['title']) ?></div>
                                                <div class="small text-muted">Posted by <?= e($a['teacher_name']) ?></div>
                                                <?php if (!empty($a['attachment'])): ?>
                                                    <a href="/public/<?= e($a['attachment']) ?>" target="_blank" class="small text-decoration-none">
                                                        <i class="bi bi-paperclip"></i> Problem Sheet Attachment
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary font-monospace fs-6"><?= (int)$a['max_marks'] ?> Marks</span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold <?= $isPastDue ? 'text-danger' : 'text-success' ?>">
                                                    <?= date('d M Y, h:i A', strtotime($a['due_date'])) ?>
                                                </div>
                                                <div class="small text-muted"><?= $isPastDue ? '<span class="badge bg-danger-subtle text-danger">Closed</span>' : '<span class="badge bg-success-subtle text-success">Active</span>' ?></div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary rounded-pill"><?= (int)$a['submission_count'] ?> Submitted</span>
                                                    <span class="badge bg-success rounded-pill"><?= (int)$a['graded_count'] ?> Graded</span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <a href="assignment_evaluate.php?assignment_id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                                    <i class="bi bi-check2-square me-1"></i> Grade Submissions
                                                </a>
                                            </td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
