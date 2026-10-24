<?php
// attendance_analytics.php - Real-Time Attendance Percentage Engine (<75% Flagging)
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher', 'accounts', 'hr']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

$deptFilter = (int)($_GET['dept_id'] ?? 0);
$courseFilter = (int)($_GET['course_id'] ?? 0);
$semFilter = (int)($_GET['semester'] ?? 0);
$onlyDefaulters = isset($_GET['defaulters_only']) && $_GET['defaulters_only'] === '1';

// Query student attendance analytics per course
$query = "
    SELECT s.id AS student_id, s.roll_no, s.current_semester,
           u.name AS student_name, u.email AS student_email,
           d.dept_code, d.dept_name,
           c.id AS course_id, c.course_code, c.course_name,
           COUNT(a.id) AS total_sessions,
           SUM(CASE WHEN a.status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) AS attended_sessions,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_sessions,
           ROUND(
               (SUM(CASE WHEN a.status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id), 0)) * 100,
               2
           ) AS attendance_pct
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    JOIN courses c ON c.department_id = d.id AND c.semester = s.current_semester
    LEFT JOIN attendance a ON s.id = a.student_id AND c.id = a.course_id
    WHERE s.admission_status = 'admitted'
";
$params = [];

if ($deptFilter > 0) {
    $query .= " AND s.department_id = ?";
    $params[] = $deptFilter;
}
if ($courseFilter > 0) {
    $query .= " AND c.id = ?";
    $params[] = $courseFilter;
}
if ($semFilter > 0) {
    $query .= " AND s.current_semester = ?";
    $params[] = $semFilter;
}

$query .= " GROUP BY s.id, c.id";

if ($onlyDefaulters) {
    $query .= " HAVING attendance_pct < 75.00 OR total_sessions = 0";
}

$query .= " ORDER BY d.dept_code, s.roll_no, c.course_code";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$analytics = $stmt->fetchAll();

// Aggregated counts
$totalRecords = count($analytics);
$defaulterCount = 0;
foreach ($analytics as $r) {
    $pct = (float)($r['attendance_pct'] ?? 0);
    if ($pct < 75.00) $defaulterCount++;
}

$departments = $pdo->query("SELECT id, dept_code, dept_name FROM departments ORDER BY dept_name")->fetchAll();
$courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Analytics Engine &bull; GIMT ERP</title>
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
                        <h1 class="h3 fw-bold mb-1">Attendance Percentage Engine &amp; Defaulter Analytics</h1>
                        <p class="text-secondary mb-0">Automated calculation of mandatory 75% attendance threshold &amp; hall ticket blocks</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="hall_tickets.php" class="btn btn-warning fw-bold">
                            <i class="bi bi-person-badge-fill me-1"></i> Exam Hall Tickets &amp; Override
                        </a>
                        <a href="attendance_mark.php" class="btn btn-primary">
                            <i class="bi bi-pencil-square me-1"></i> Mark Attendance
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- KPI Metric Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card">
                            <div class="text-secondary small fw-bold text-uppercase">Total Student &bull; Course Pairs</div>
                            <div class="h3 fw-bold text-primary mb-0"><?= $totalRecords ?></div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card">
                            <div class="text-secondary small fw-bold text-uppercase">Students Below 75% Threshold</div>
                            <div class="h3 fw-bold text-danger mb-0"><?= $defaulterCount ?></div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="stat-card">
                            <div class="text-secondary small fw-bold text-uppercase">Statutory Regulations</div>
                            <div class="fw-semibold text-secondary mb-0">MAKAUT / AICTE 75% Minimum Floor</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-3 mb-4 border-0 shadow-sm">
                    <form method="get" action="attendance_analytics.php" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <select name="dept_id" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['dept_code']) ?> &mdash; <?= e($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="course_id" class="form-select">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= $courseFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['course_code']) ?> &mdash; <?= e($c['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="semester" class="form-select">
                                <option value="">All Semesters</option>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?= $s ?>" <?= $semFilter === $s ? 'selected' : '' ?>>Sem <?= $s ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="defaulters_only" value="1" id="defCheck" <?= $onlyDefaulters ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold text-danger" for="defCheck">
                                    Only &lt; 75% Defaulters
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="attendance_analytics.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Analytics Table -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Roll Number &bull; Student Name</th>
                                    <th>Department</th>
                                    <th>Course Code &bull; Title</th>
                                    <th class="text-center">Classes Held</th>
                                    <th class="text-center">Attended</th>
                                    <th class="text-center">Absent</th>
                                    <th class="text-center">Attendance %</th>
                                    <th>Eligibility Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($analytics)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No attendance data matching your parameters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($analytics as $row): ?>
                                        <?php
                                        $pct = (float)($row['attendance_pct'] ?? 0);
                                        $isEligible = ($pct >= 75.00);
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($row['roll_no']) ?></span>
                                                <div class="fw-bold mt-1"><?= e($row['student_name']) ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= e($row['dept_code']) ?></span></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border font-monospace"><?= e($row['course_code']) ?></span>
                                                <div class="small fw-semibold mt-1"><?= e($row['course_name']) ?></div>
                                            </td>
                                            <td class="text-center fw-semibold"><?= (int)$row['total_sessions'] ?></td>
                                            <td class="text-center text-success fw-bold"><?= (int)$row['attended_sessions'] ?></td>
                                            <td class="text-center text-danger"><?= (int)$row['absent_sessions'] ?></td>
                                            <td class="text-center">
                                                <div class="h5 mb-0 fw-bold <?= $isEligible ? 'text-success' : 'text-danger' ?>">
                                                    <?= number_format($pct, 1) ?>%
                                                </div>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar <?= $isEligible ? 'bg-success' : 'bg-danger' ?>" style="width: <?= min(100, $pct) ?>%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($isEligible): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-check-circle"></i> Eligible for Exams
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger text-white">
                                                        <i class="bi bi-slash-circle"></i> Hall Ticket Blocked (&lt;75%)
                                                    </span>
                                                <?php endif; ?>
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
