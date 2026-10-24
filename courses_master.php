<?php
// courses_master.php - Academic Subject & Credit Master Management
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher', 'accounts', 'hr']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

// Check permission for modifying courses (Principal, Chairman, HOD)
$canModify = in_array($userRole, ['chairman', 'principal', 'hod'], true);

// Handle Add / Edit / Delete Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canModify) {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_course') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
        $courseName = trim($_POST['course_name'] ?? '');
        $semester = (int)($_POST['semester'] ?? 1);
        $credits = (int)($_POST['credits'] ?? 3);
        $subjectType = trim($_POST['subject_type'] ?? 'Theory');

        if (!empty($courseCode) && !empty($courseName) && $deptId > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO courses (department_id, course_code, course_name, semester, credits, subject_type)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$deptId, $courseCode, $courseName, $semester, $credits, $subjectType]);
                audit_log('Course Created', 'courses', $pdo->lastInsertId(), "Created course {$courseCode}: {$courseName}");
                set_flash('success', "Course {$courseCode} successfully registered.");
            } catch (Exception $e) {
                set_flash('danger', "Error creating course: " . $e->getMessage());
            }
        }
        header('Location: courses_master.php');
        exit;
    } elseif ($action === 'delete_course') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            audit_log('Course Deleted', 'courses', $courseId, "Deleted course ID {$courseId}");
            set_flash('success', "Course deleted.");
        }
        header('Location: courses_master.php');
        exit;
    }
}

// Filters
$deptFilter = (int)($_GET['dept_id'] ?? 0);
$semFilter = (int)($_GET['semester'] ?? 0);
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT c.*, d.dept_name, d.dept_code,
           COUNT(DISTINCT fc.teacher_id) AS faculty_count
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN faculty_courses fc ON c.id = fc.course_id
    WHERE 1=1
";
$params = [];

if ($deptFilter > 0) {
    $query .= " AND c.department_id = ?";
    $params[] = $deptFilter;
}
if ($semFilter > 0) {
    $query .= " AND c.semester = ?";
    $params[] = $semFilter;
}
if (!empty($search)) {
    $query .= " AND (c.course_code LIKE ? OR c.course_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}

$query .= " GROUP BY c.id ORDER BY d.dept_name, c.semester, c.course_code";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, dept_code, dept_name FROM departments ORDER BY dept_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course &amp; Credit Master &bull; GIMT ERP</title>
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
                        <h1 class="h3 fw-bold mb-1">Subject &amp; Credit Master</h1>
                        <p class="text-secondary mb-0">Institutional curriculum catalog, credit allocations &amp; subject types</p>
                    </div>
                    <?php if ($canModify): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCourseModal">
                            <i class="bi bi-plus-lg me-1"></i> Add New Subject
                        </button>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <!-- Filters -->
                <div class="card p-3 mb-4 border-0 shadow-sm">
                    <form method="get" action="courses_master.php" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search code or title..." value="<?= e($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="dept_id" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['dept_code']) ?> &mdash; <?= e($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="semester" class="form-select">
                                <option value="">All Semesters</option>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?= $s ?>" <?= $semFilter === $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="courses_master.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Course Table -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Course Title</th>
                                    <th>Department</th>
                                    <th>Semester</th>
                                    <th>Credits</th>
                                    <th>Type</th>
                                    <th>Assigned Faculty</th>
                                    <?php if ($canModify): ?>
                                        <th class="text-end">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courses)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No courses registered yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark-subtle text-dark border font-monospace fs-6"><?= e($c['course_code']) ?></span>
                                            </td>
                                            <td class="fw-bold"><?= e($c['course_name']) ?></td>
                                            <td><span class="badge bg-secondary"><?= e($c['dept_code']) ?></span></td>
                                            <td>Semester <?= (int) $c['semester'] ?></td>
                                            <td><span class="badge bg-primary rounded-pill px-3"><?= (int) $c['credits'] ?> Credits</span></td>
                                            <td>
                                                <span class="badge <?= $c['subject_type'] === 'Practical' ? 'bg-info-subtle text-info border border-info' : 'bg-light text-dark border' ?>">
                                                    <?= e($c['subject_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($c['faculty_count'] > 0): ?>
                                                    <a href="faculty_workload.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-person-check me-1"></i> <?= $c['faculty_count'] ?> Faculty
                                                    </a>
                                                <?php else: ?>
                                                    <a href="faculty_workload.php" class="text-danger small text-decoration-none">
                                                        <i class="bi bi-exclamation-circle me-1"></i> Unassigned
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($canModify): ?>
                                                <td class="text-end">
                                                    <form method="post" action="courses_master.php" class="d-inline" onsubmit="return confirm('Delete this course?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_course">
                                                        <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                                    </form>
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

    <!-- New Course Modal -->
    <?php if ($canModify): ?>
        <div class="modal fade" id="newCourseModal" tabindex="-1" aria-labelledby="newCourseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="courses_master.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_course">
                        <div class="modal-header">
                            <h2 class="modal-title h5 fw-bold" id="newCourseModalLabel">Register New Academic Course</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Department</label>
                                <select name="department_id" class="form-select" required>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>"><?= e($d['dept_code']) ?> &mdash; <?= e($d['dept_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label required">Course Code</label>
                                    <input type="text" name="course_code" class="form-control" placeholder="e.g. CS401" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label required">Semester</label>
                                    <select name="semester" class="form-select">
                                        <?php for ($s = 1; $s <= 8; $s++): ?>
                                            <option value="<?= $s ?>">Semester <?= $s ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Course Title</label>
                                <input type="text" name="course_name" class="form-control" placeholder="e.g. Design & Analysis of Algorithms" required>
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label required">Credit Units</label>
                                    <input type="number" name="credits" class="form-control" min="1" max="10" value="4" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label required">Subject Type</label>
                                    <select name="subject_type" class="form-select">
                                        <option value="Theory">Theory</option>
                                        <option value="Practical">Practical</option>
                                        <option value="Elective">Elective</option>
                                        <option value="Audit">Audit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Course</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
