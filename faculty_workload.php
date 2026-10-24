<?php
// faculty_workload.php - Faculty Workload & Course Section Mapping Engine
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher', 'hr']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$canAssign = in_array($userRole, ['chairman', 'principal', 'hod'], true);

// Handle Workload Assignment / Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canAssign) {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'assign_workload') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        $academicYear = trim($_POST['academic_year'] ?? '2026-2027');
        $section = trim($_POST['section'] ?? 'A');

        if ($teacherId > 0 && $courseId > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_courses (course_id, teacher_id, academic_year, section)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE section = VALUES(section)
                ");
                $stmt->execute([$courseId, $teacherId, $academicYear, $section]);
                audit_log('Faculty Assigned', 'faculty_courses', $courseId, "Assigned teacher {$teacherId} to course {$courseId}");
                set_flash('success', "Faculty workload mapped successfully.");
            } catch (Exception $e) {
                set_flash('danger', "Assignment failed: " . $e->getMessage());
            }
        }
        header('Location: faculty_workload.php');
        exit;
    } elseif ($action === 'unassign') {
        $mappingId = (int)($_POST['mapping_id'] ?? 0);
        if ($mappingId > 0) {
            $stmt = $pdo->prepare("DELETE FROM faculty_courses WHERE id = ?");
            $stmt->execute([$mappingId]);
            audit_log('Faculty Unassigned', 'faculty_courses', $mappingId, "Removed mapping {$mappingId}");
            set_flash('success', "Course workload mapping removed.");
        }
        header('Location: faculty_workload.php');
        exit;
    }
}

// Fetch all workload mappings
$mappings = $pdo->query("
    SELECT fc.id AS mapping_id, fc.academic_year, fc.section,
           u.id AS teacher_id, u.name AS teacher_name, u.email AS teacher_email, u.role AS teacher_role,
           c.id AS course_id, c.course_code, c.course_name, c.credits, c.semester, c.subject_type,
           d.dept_code, d.dept_name
    FROM faculty_courses fc
    JOIN users u ON fc.teacher_id = u.id
    JOIN courses c ON fc.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    ORDER BY u.name, c.semester, c.course_code
")->fetchAll();

// Aggregate faculty stats
$facultyStats = [];
foreach ($mappings as $m) {
    $tid = $m['teacher_id'];
    if (!isset($facultyStats[$tid])) {
        $facultyStats[$tid] = [
            'name' => $m['teacher_name'],
            'email' => $m['teacher_email'],
            'courses' => 0,
            'credits' => 0
        ];
    }
    $facultyStats[$tid]['courses']++;
    $facultyStats[$tid]['credits'] += (int)$m['credits'];
}

// Fetch faculties (teachers & hods) and courses for modal
$facultyList = $pdo->query("SELECT id, name, email, role FROM users WHERE role IN ('teacher', 'hod') AND status = 'active' ORDER BY name")->fetchAll();
$courseList = $pdo->query("SELECT c.id, c.course_code, c.course_name, c.semester, d.dept_code FROM courses c JOIN departments d ON c.department_id = d.id ORDER BY d.dept_code, c.semester, c.course_code")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Workload Allocation &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; }
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
                        <h1 class="h3 fw-bold mb-1">Faculty Workload Allocation</h1>
                        <p class="text-secondary mb-0">Academic term course mapping, section distribution &amp; credit loads</p>
                    </div>
                    <?php if ($canAssign): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                            <i class="bi bi-person-plus me-1"></i> Map Faculty to Course
                        </button>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <!-- Aggregated Workload Cards -->
                <div class="row g-3 mb-4">
                    <?php if (empty($facultyStats)): ?>
                        <div class="col-12"><div class="alert alert-info">No faculty workloads mapped yet.</div></div>
                    <?php else: ?>
                        <?php foreach ($facultyStats as $fs): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="stat-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar bg-primary-subtle text-primary p-3 rounded-circle fw-bold fs-5">
                                            <?= strtoupper(substr($fs['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= e($fs['name']) ?></div>
                                            <div class="small text-muted mb-1"><?= e($fs['email']) ?></div>
                                            <div class="d-flex gap-2">
                                                <span class="badge bg-secondary"><?= $fs['courses'] ?> Courses</span>
                                                <span class="badge bg-primary"><?= $fs['credits'] ?> Total Credits</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Detailed Mappings Table -->
                <div class="table-card">
                    <div class="p-3 border-bottom bg-light">
                        <h2 class="h6 fw-bold mb-0">Active Course Workload Deployments</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Faculty Member</th>
                                    <th>Course Code &bull; Title</th>
                                    <th>Department &bull; Sem</th>
                                    <th>Credits &bull; Type</th>
                                    <th>Section &bull; Term</th>
                                    <?php if ($canAssign): ?>
                                        <th class="text-end">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mappings)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No course workload mappings found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mappings as $map): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= e($map['teacher_name']) ?></div>
                                                <div class="small text-muted"><?= e($map['teacher_email']) ?> &bull; <span class="badge bg-light text-dark border text-uppercase"><?= e($map['teacher_role']) ?></span></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($map['course_code']) ?></span>
                                                <div class="fw-semibold mt-1"><?= e($map['course_name']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= e($map['dept_code']) ?></span>
                                                <div class="small text-muted">Semester <?= (int)$map['semester'] ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= (int)$map['credits'] ?> Credits</span>
                                                <span class="badge bg-light text-dark border"><?= e($map['subject_type']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info border">Section <?= e($map['section']) ?></span>
                                                <div class="small text-muted"><?= e($map['academic_year']) ?></div>
                                            </td>
                                            <?php if ($canAssign): ?>
                                                <td class="text-end">
                                                    <form method="post" action="faculty_workload.php" class="d-inline" onsubmit="return confirm('Remove workload mapping?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="unassign">
                                                        <input type="hidden" name="mapping_id" value="<?= (int)$map['mapping_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Unassign"><i class="bi bi-x-lg"></i> Unassign</button>
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

    <!-- Assign Modal -->
    <?php if ($canAssign): ?>
        <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="faculty_workload.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="assign_workload">
                        <div class="modal-header">
                            <h2 class="modal-title h5 fw-bold" id="assignModalLabel">Assign Course to Faculty</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Select Faculty / Teacher</label>
                                <select name="teacher_id" class="form-select" required>
                                    <option value="" disabled selected>Choose Faculty</option>
                                    <?php foreach ($facultyList as $fac): ?>
                                        <option value="<?= (int)$fac['id'] ?>"><?= e($fac['name']) ?> (<?= e($fac['role']) ?> &bull; <?= e($fac['email']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Select Course</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="" disabled selected>Choose Course</option>
                                    <?php foreach ($courseList as $crs): ?>
                                        <option value="<?= (int)$crs['id'] ?>">[<?= e($crs['dept_code']) ?> Sem <?= $crs['semester'] ?>] <?= e($crs['course_code']) ?> &mdash; <?= e($crs['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label required">Section / Batch</label>
                                    <input type="text" name="section" class="form-control" value="A" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label required">Academic Term</label>
                                    <input type="text" name="academic_year" class="form-control" value="2026-2027" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Confirm Allocation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
