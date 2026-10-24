<?php
// role_dashboard.php - Multi-Tenant Role-Specific Dashboard Engine (Accounts, HR, HOD, Teacher)
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/middleware/isLoggedIn.php';

$dashboardRoles = [
    'accounts_dashboard.php' => 'accounts',
    'hr_dashboard.php' => 'hr',
    'hod_dashboard.php' => 'hod',
    'teacher_dashboard.php' => 'teacher',
];
$requestedDashboard = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$requiredRole = $dashboardRoles[$requestedDashboard] ?? null;

if ($requiredRole === null || ($_SESSION['user_role'] ?? '') !== $requiredRole) {
    http_response_code(403);
    include __DIR__ . '/partials/403.php';
    exit;
}

$pdo = getDb();
$userId = (int)($_SESSION['user_id'] ?? 0);
$displayName = (string) ($_SESSION['user_name'] ?? ucfirst($requiredRole));

$roleTitles = [
    'accounts' => 'Accounts & Finance Department',
    'hr' => 'Human Resources Administration',
    'hod' => 'Head of Department (Academic Oversight)',
    'teacher' => 'Faculty Teaching & Evaluation Workbench',
];

// Fetch role-specific metrics
$metrics = [];
$quickLinks = [];

if ($requiredRole === 'accounts') {
    $totalDebits = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM student_ledger WHERE type = 'debit'")->fetchColumn();
    $totalCredits = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM student_ledger WHERE type IN ('credit', 'waiver')")->fetchColumn();
    $totalDues = $totalDebits - $totalCredits;
    $defaultersCount = (int)$pdo->query("
        SELECT COUNT(DISTINCT student_id) FROM student_ledger
        GROUP BY student_id HAVING (SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END)) > 0
    ")->rowCount();

    $metrics = [
        ['label' => 'Total Fee Collected', 'val' => format_inr($totalCredits), 'color' => 'text-success'],
        ['label' => 'Outstanding Balance Due', 'val' => format_inr($totalDues), 'color' => 'text-danger'],
        ['label' => 'Overdue Defaulters', 'val' => $defaultersCount, 'color' => 'text-warning'],
        ['label' => 'Total Invoiced Debits', 'val' => format_inr($totalDebits), 'color' => 'text-primary'],
    ];

    $quickLinks = [
        ['label' => 'Fee Collection Counter', 'desc' => 'Issue official serial receipts', 'icon' => 'bi-cash-coin', 'href' => 'fee_collect.php', 'color' => 'text-success'],
        ['label' => 'Student Ledgers', 'desc' => 'Double-entry transactional ledger', 'icon' => 'bi-wallet2', 'href' => 'student_ledger.php', 'color' => 'text-primary'],
        ['label' => 'Overdue Defaulters', 'desc' => 'Audit unpaid student balances', 'icon' => 'bi-exclamation-octagon', 'href' => 'fee_defaulters.php', 'color' => 'text-danger'],
        ['label' => 'Fee Structure Builder', 'desc' => 'Configure itemized heads & bulk invoice', 'icon' => 'bi-sliders', 'href' => 'fee_structures.php', 'color' => 'text-info'],
        ['label' => 'Admissions Verification', 'desc' => 'Verify applicant prospectus fees', 'icon' => 'bi-person-check', 'href' => 'admissions_manage.php', 'color' => 'text-secondary'],
    ];

} elseif ($requiredRole === 'hr') {
    $totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'hod', 'accounts', 'hr')")->fetchColumn();
    $totalFaculty = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'hod')")->fetchColumn();
    $totalDepts = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $metrics = [
        ['label' => 'Registered Staff', 'val' => $totalStaff, 'color' => 'text-primary'],
        ['label' => 'Teaching Faculty', 'val' => $totalFaculty, 'color' => 'text-success'],
        ['label' => 'Academic Departments', 'val' => $totalDepts, 'color' => 'text-info'],
        ['label' => 'Total ERP Users', 'val' => $totalUsers, 'color' => 'text-dark'],
    ];

    $quickLinks = [
        ['label' => 'Staff Directory', 'desc' => 'View all employee profiles & credentials', 'icon' => 'bi-people', 'href' => 'staffs.php', 'color' => 'text-primary'],
        ['label' => 'Register New Staff', 'desc' => 'Onboard faculty & institutional staff', 'icon' => 'bi-person-plus', 'href' => 'staff_register.php', 'color' => 'text-success'],
        ['label' => 'Faculty Workload', 'desc' => 'Review credit distributions & sections', 'icon' => 'bi-person-workspace', 'href' => 'faculty_workload.php', 'color' => 'text-info'],
        ['label' => 'Departments Master', 'desc' => 'Configure department capacities & HODs', 'icon' => 'bi-building', 'href' => 'departments.php', 'color' => 'text-secondary'],
    ];

} elseif ($requiredRole === 'hod') {
    // Get HOD's department
    $deptStmt = $pdo->prepare("SELECT id, dept_name, dept_code FROM departments WHERE hod_user_id = ? LIMIT 1");
    $deptStmt->execute([$userId]);
    $myDept = $deptStmt->fetch();
    $myDeptId = $myDept ? (int)$myDept['id'] : 1;

    $deptStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE department_id = {$myDeptId} AND admission_status = 'admitted'")->fetchColumn();
    $deptCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE department_id = {$myDeptId}")->fetchColumn();
    $deptFaculty = (int)$pdo->query("SELECT COUNT(DISTINCT teacher_id) FROM faculty_courses fc JOIN courses c ON fc.course_id = c.id WHERE c.department_id = {$myDeptId}")->fetchColumn();
    $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests lr JOIN students s ON lr.student_id = s.id WHERE s.department_id = {$myDeptId} AND lr.status = 'pending'")->fetchColumn();

    $metrics = [
        ['label' => 'Department Students', 'val' => $deptStudents, 'color' => 'text-primary'],
        ['label' => 'Active Courses', 'val' => $deptCourses, 'color' => 'text-success'],
        ['label' => 'Assigned Faculty', 'val' => $deptFaculty, 'color' => 'text-info'],
        ['label' => 'Pending Leave Appeals', 'val' => $pendingLeaves, 'color' => 'text-warning'],
    ];

    $quickLinks = [
        ['label' => 'Curriculum & Courses', 'desc' => 'Manage courses, credits & syllabus', 'icon' => 'bi-journal-bookmark', 'href' => 'courses_master.php', 'color' => 'text-primary'],
        ['label' => 'Faculty Workload Mapping', 'desc' => 'Allocate classes, terms & sections', 'icon' => 'bi-person-workspace', 'href' => 'faculty_workload.php', 'color' => 'text-success'],
        ['label' => 'Classroom Attendance Grid', 'desc' => 'Real-time roll call & logs', 'icon' => 'bi-calendar-check', 'href' => 'attendance_mark.php', 'color' => 'text-info'],
        ['label' => 'Attendance Analytics (<75%)', 'desc' => 'Threshold defaulters & exam gate', 'icon' => 'bi-graph-up', 'href' => 'attendance_analytics.php', 'color' => 'text-danger'],
        ['label' => 'Leave Endorsements', 'desc' => 'Approve absence condonations', 'icon' => 'bi-envelope-paper', 'href' => 'leave_requests.php', 'color' => 'text-warning'],
        ['label' => 'Tabular Marks Entry', 'desc' => 'Enter CIA-1, CIA-2 marks & lock', 'icon' => 'bi-table', 'href' => 'marks_entry.php', 'color' => 'text-primary'],
        ['label' => 'Consolidated Tabulation', 'desc' => 'Continuous internal calculations', 'icon' => 'bi-calculator', 'href' => 'exam_tabulation.php', 'color' => 'text-success'],
        ['label' => 'Locked Grade Cards', 'desc' => 'Student progress reports & SGPA', 'icon' => 'bi-award', 'href' => 'grade_card.php', 'color' => 'text-dark'],
    ];

} elseif ($requiredRole === 'teacher') {
    $myCourses = (int)$pdo->query("SELECT COUNT(DISTINCT course_id) FROM faculty_courses WHERE teacher_id = {$userId}")->fetchColumn();
    $myAssignments = (int)$pdo->query("SELECT COUNT(*) FROM assignments WHERE teacher_id = {$userId}")->fetchColumn();
    $submissionsToGrade = (int)$pdo->query("
        SELECT COUNT(asub.id) FROM assignment_submissions asub
        JOIN assignments a ON asub.assignment_id = a.id
        WHERE a.teacher_id = {$userId} AND asub.marks_obtained IS NULL
    ")->fetchColumn();
    $classesMarked = (int)$pdo->query("SELECT COUNT(DISTINCT attendance_date, course_id, period) FROM attendance WHERE marked_by = {$userId}")->fetchColumn();

    $metrics = [
        ['label' => 'My Assigned Courses', 'val' => $myCourses, 'color' => 'text-primary'],
        ['label' => 'Published Assignments', 'val' => $myAssignments, 'color' => 'text-info'],
        ['label' => 'Submissions to Grade', 'val' => $submissionsToGrade, 'color' => 'text-warning'],
        ['label' => 'Lecture Sessions Logged', 'val' => $classesMarked, 'color' => 'text-success'],
    ];

    $quickLinks = [
        ['label' => 'Assigned Courses', 'desc' => 'View teaching courses & syllabus', 'icon' => 'bi-journal-bookmark', 'href' => 'courses_master.php', 'color' => 'text-primary'],
        ['label' => 'Mark Daily Attendance', 'desc' => 'Period-wise roll call grid', 'icon' => 'bi-calendar-check', 'href' => 'attendance_mark.php', 'color' => 'text-success'],
        ['label' => 'Coursework & Grading', 'desc' => 'Create assignments & evaluate files', 'icon' => 'bi-journal-text', 'href' => 'assignments_manage.php', 'color' => 'text-info'],
        ['label' => 'Tabular Marks Entry', 'desc' => 'Internal assessments CIA-1 & CIA-2', 'icon' => 'bi-table', 'href' => 'marks_entry.php', 'color' => 'text-warning'],
        ['label' => 'Consolidated Tabulation', 'desc' => 'View weighted internal marks', 'icon' => 'bi-calculator', 'href' => 'exam_tabulation.php', 'color' => 'text-dark'],
        ['label' => 'Attendance Analytics', 'desc' => 'Identify students below 75%', 'icon' => 'bi-graph-up', 'href' => 'attendance_analytics.php', 'color' => 'text-danger'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($roleTitles[$requiredRole]) ?> | GIMT ERP</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #f8fafc; color: #172b4d; font-family: "Segoe UI", Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1 1 auto; min-width: 0; padding: 28px; }
        .role-card { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 4px 16px rgba(15,23,42,0.03); }
        .role-stat { font-size: 1.8rem; font-weight: 750; line-height: 1.1; margin-top: 5px; }
        .module-tile { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-decoration: none; color: inherit; display: block; transition: all 0.2s; }
        .module-tile:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,0.06); border-color: #123b70; color: #123b70; }
        .currency { font-family: monospace; font-weight: 700; }
    </style>
</head>
<body>
    <div class="page-layout">
        <?php require __DIR__ . '/partials/role_sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0" style="max-width: 1100px;">

                <!-- Header -->
                <header class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 p-4 rounded-4 border bg-white shadow-sm">
                    <div>
                        <span class="badge text-bg-primary px-3 py-1 rounded-pill mb-1 text-uppercase"><?= e($requiredRole) ?> WORKBENCH</span>
                        <h1 class="h3 fw-bold mb-1"><?= e($roleTitles[$requiredRole]) ?></h1>
                        <p class="text-secondary mb-0">Welcome, <strong><?= e($displayName) ?></strong> &bull; <?= date('l, d F Y') ?></p>
                    </div>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2 fw-semibold">Session 2026-2027</span>
                </header>

                <?= display_flash() ?>

                <!-- Role Key Metrics -->
                <section class="row g-3 mb-4" aria-label="System totals">
                    <?php foreach ($metrics as $m): ?>
                        <div class="col-12 col-sm-6 col-md-3">
                            <article class="role-card p-4 h-100">
                                <div class="text-secondary small fw-bold text-uppercase"><?= e($m['label']) ?></div>
                                <div class="role-stat <?= e($m['color']) ?> currency"><?= $m['val'] ?></div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </section>

                <!-- Fast Module Navigation -->
                <h2 class="h5 fw-bold mb-3">Operational Workbenches &amp; Tools</h2>
                <div class="row g-3 mb-4">
                    <?php foreach ($quickLinks as $ql): ?>
                        <div class="col-md-4 col-sm-6">
                            <a href="<?= e($ql['href']) ?>" class="module-tile">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-3 rounded-circle fs-4 bg-light <?= e($ql['color']) ?>">
                                        <i class="bi <?= e($ql['icon']) ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="h6 fw-bold mb-1"><?= e($ql['label']) ?></h3>
                                        <div class="small text-muted"><?= e($ql['desc']) ?></div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
