<?php
// attendance_mark.php - Classroom Attendance Grid (Date & Period Wise - Strict Assigned Teacher Rule)
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Active Academic Session Context
$activeSession = get_academic_session();
$isArchivedContext = is_previous_session($activeSession);

// Determine strictly accessible courses via RBAC engine
$availableCourses = get_permitted_courses($userId, $userRole);

// Active selection with strict authorization
$reqCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
$selectedPeriod = (int)($_GET['period'] ?? 1);

// Date-based archived session verification
$isHistoricalDate = (strtotime($selectedDate) < strtotime('2026-07-01'));
$isReadOnlyRegister = ($isArchivedContext || $isHistoricalDate);

$selectedCourseId = 0;
if ($reqCourseId > 0) {
    if (can_teacher_access_course($userId, $reqCourseId, $userRole)) {
        $selectedCourseId = $reqCourseId;
    } else {
        set_flash('danger', "Access Denied: Strict regulatory rule prohibits taking attendance for Course #{$reqCourseId}. Only the mapped faculty member assigned in the course workload matrix can mark attendance.");
        $selectedCourseId = !empty($availableCourses) ? (int)$availableCourses[0]['id'] : 0;
    }
} else {
    $selectedCourseId = !empty($availableCourses) ? (int)$availableCourses[0]['id'] : 0;
}

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $attDate = trim($_POST['date'] ?? date('Y-m-d'));
    $period = (int)($_POST['period'] ?? 1);
    $statusArray = $_POST['status'] ?? []; // student_id => status

    // PREVIOUS SESSION IMMUTABILITY CHECK
    if ($isReadOnlyRegister) {
        audit_log('Archived Attendance Modification Blocked', 'attendance', $courseId, "Attempted modification on locked session {$activeSession} or past date {$attDate}");
        set_flash('danger', 'Regulatory Rule Violation: Attendance records for previous academic sessions or historical dates are permanently locked and cannot be edited, modified, or deleted.');
        header("Location: attendance_mark.php?course_id={$courseId}&date={$attDate}&period={$period}");
        exit;
    }

    // STRICT REGULATION CHECK: Only mapped teacher (or dept HOD/Principal/Chairman) can execute
    if (!can_teacher_access_course($userId, $courseId, $userRole)) {
        audit_log('Unauthorized Attendance Logging Attempt Blocked', 'attendance', $courseId, "User {$userId} ({$userRole}) denied attendance marking access");
        set_flash('danger', 'Access Denied: You are not the assigned teacher for this subject. Attendance marking blocked.');
        header("Location: attendance_mark.php");
        exit;
    }

    if ($courseId > 0 && !empty($statusArray)) {
        try {
            $pdo->beginTransaction();
            $upsertStmt = $pdo->prepare("
                INSERT INTO attendance (student_id, course_id, academic_session, attendance_date, period, status, marked_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
            ");

            $markedCount = 0;
            foreach ($statusArray as $sId => $status) {
                if (in_array($status, ['present', 'absent', 'late', 'on_duty'], true)) {
                    $upsertStmt->execute([(int)$sId, $courseId, CURRENT_OPERATING_SESSION, $attDate, $period, $status, $userId]);
                    $markedCount++;
                }
            }

            audit_log('Attendance Logged', 'attendance', $courseId, "Marked {$markedCount} students for course {$courseId} on {$attDate} P{$period} by Teacher {$userId}");
            $pdo->commit();
            set_flash('success', "Attendance saved for {$markedCount} students (Period {$period}).");
            header("Location: attendance_mark.php?course_id={$courseId}&date={$attDate}&period={$period}");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Error saving attendance: " . $e->getMessage());
        }
    }
}

// Fetch Course Info
$course = null;
$students = [];
$existingAttendance = [];

if ($selectedCourseId > 0 && can_teacher_access_course($userId, $selectedCourseId, $userRole)) {
    $cStmt = $pdo->prepare("
        SELECT c.*, d.dept_name, d.dept_code,
               (SELECT u.name FROM faculty_courses fc JOIN users u ON fc.teacher_id = u.id WHERE fc.course_id = c.id LIMIT 1) AS assigned_faculty
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $cStmt->execute([$selectedCourseId]);
    $course = $cStmt->fetch();

    if ($course) {
        // Fetch students enrolled in this department & semester
        $stdStmt = $pdo->prepare("
            SELECT s.id, s.roll_no, s.makaut_reg_no, s.makaut_roll_no, u.name, s.current_semester, d.dept_code
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.department_id = ? AND s.current_semester = ? AND s.admission_status = 'admitted'
            ORDER BY s.roll_no
        ");
        $stdStmt->execute([$course['department_id'], $course['semester']]);
        $students = $stdStmt->fetchAll();

        // Fetch any existing attendance for this course, date & period
        $attStmt = $pdo->prepare("
            SELECT student_id, status FROM attendance
            WHERE course_id = ? AND attendance_date = ? AND period = ?
        ");
        $attStmt->execute([$selectedCourseId, $selectedDate, $selectedPeriod]);
        $rows = $attStmt->fetchAll();
        foreach ($rows as $r) {
            $existingAttendance[$r['student_id']] = $r['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classroom Attendance Grid &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .btn-status { font-weight: 600; font-size: 0.85rem; padding: 6px 14px; border-radius: 6px; }
        .btn-check:checked + .btn-outline-success { background-color: #16a34a; color: white; border-color: #16a34a; }
        .btn-check:checked + .btn-outline-danger { background-color: #dc2626; color: white; border-color: #dc2626; }
        .btn-check:checked + .btn-outline-warning { background-color: #d97706; color: white; border-color: #d97706; }
        .btn-check:checked + .btn-outline-info { background-color: #0284c7; color: white; border-color: #0284c7; }
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

                <div class="mb-4">
                    <?php require __DIR__ . '/partials/makaut_header.php'; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">MAKAUT Academic Suite</span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-shield-lock me-1"></i> Strict Assigned-Teacher Rule Active</span>
                            <?php if ($isReadOnlyRegister): ?>
                                <span class="badge bg-secondary text-white"><i class="bi bi-lock-fill me-1"></i> Archived / Read-Only</span>
                            <?php endif; ?>
                        </div>
                        <h1 class="h3 fw-bold mb-1">Classroom Attendance Grid &bull; Period Stream</h1>
                        <p class="text-secondary mb-0">Period-wise roll call &bull; Only mapped course faculty can log or update attendance</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="attendance_analytics.php" class="btn btn-outline-primary">
                            <i class="bi bi-graph-up me-1"></i> Threshold Analytics (&lt;75%)
                        </a>
                        <a href="leave_requests.php" class="btn btn-outline-secondary">
                            <i class="bi bi-envelope-paper me-1"></i> Leave Requests
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <?php if ($isReadOnlyRegister): ?>
                    <div class="alert alert-warning border-warning d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-shield-lock-fill fs-4 text-warning"></i>
                        <div>
                            <strong>Archived Session / Historical Record: Read-Only</strong><br>
                            Under institutional regulations, attendance records from previous academic sessions or historical dates are permanently frozen and cannot be modified or deleted.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($availableCourses)): ?>
                    <div class="card p-5 text-center border-0 shadow-sm rounded-4">
                        <div class="text-warning mb-3">
                            <i class="bi bi-shield-exclamation" style="font-size: 3.5rem;"></i>
                        </div>
                        <h2 class="h5 fw-bold text-dark">No Teaching Subjects Assigned to Your Account</h2>
                        <p class="text-secondary mx-auto mb-4" style="max-width: 550px;">
                            Under institutional regulations, only faculty mapped to specific subjects in the Workload Allocation can mark classroom attendance.
                        </p>
                        <div>
                            <?php if (in_array($userRole, ['chairman', 'principal', 'hod'], true)): ?>
                                <a href="faculty_workload.php" class="btn btn-primary fw-bold">
                                    <i class="bi bi-person-workspace me-1"></i> Assign Course Workload
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary p-2">Contact your Department HOD or Principal to map your subjects</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Filter / Selection Desk -->
                    <div class="card p-3 mb-4 border-0 shadow-sm">
                        <form method="get" action="attendance_mark.php" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted">Permitted Course (Assigned to You)</label>
                                <select name="course_id" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($availableCourses as $ac): ?>
                                        <option value="<?= (int)$ac['id'] ?>" <?= $selectedCourseId === (int)$ac['id'] ? 'selected' : '' ?>>
                                            [<?= e($ac['dept_code']) ?> S<?= $ac['semester'] ?>] <?= e($ac['course_code']) ?> &mdash; <?= e($ac['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Attendance Date</label>
                                <input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Class Period</label>
                                <select name="period" class="form-select" onchange="this.form.submit()">
                                    <?php for ($p = 1; $p <= 6; $p++): ?>
                                        <option value="<?= $p ?>" <?= $selectedPeriod === $p ? 'selected' : '' ?>>Period <?= $p ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Load Register</button>
                            </div>
                        </form>
                    </div>

                    <!-- Attendance Register Table -->
                    <?php if ($course): ?>
                        <form action="attendance_mark.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
                            <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                            <input type="hidden" name="period" value="<?= $selectedPeriod ?>">

                            <div class="table-card">
                                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="badge bg-primary me-2 font-monospace"><?= e($course['course_code']) ?></span>
                                        <strong><?= e($course['course_name']) ?></strong>
                                        <span class="text-secondary small ms-2">&bull; Date: <strong><?= e($selectedDate) ?></strong> &bull; Period: <strong><?= $selectedPeriod ?></strong></span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-2"><i class="bi bi-person-check-fill me-1"></i> Faculty: <?= e($course['assigned_faculty'] ?: 'Assigned Teacher') ?></span>
                                    </div>
                                    <?php if (!$isReadOnlyRegister): ?>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="markAll('present')">All Present</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="markAll('absent')">All Absent</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i> Register Locked</span>
                                    <?php endif; ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 80px;">#</th>
                                                <th>Roll No &bull; Student Name</th>
                                                <th>University Roll No</th>
                                                <th>Current Status</th>
                                                <th class="text-end" style="min-width: 320px;">Mark Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($students)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">No students enrolled in this course semester.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($students as $idx => $s): ?>
                                                    <?php $curStatus = $existingAttendance[$s['id']] ?? 'present'; ?>
                                                    <tr>
                                                        <td class="text-muted fw-bold"><?= $idx + 1 ?></td>
                                                        <td>
                                                            <div class="fw-bold"><?= e($s['name']) ?></div>
                                                            <div class="small text-muted font-monospace"><?= e($s['roll_no']) ?></div>
                                                        </td>
                                                        <td>
                                                            <span class="small font-monospace text-primary"><?= e($s['makaut_roll_no'] ?: 'Pending') ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if (isset($existingAttendance[$s['id']])): ?>
                                                                <span class="badge bg-<?= $curStatus === 'present' ? 'success' : ($curStatus === 'absent' ? 'danger' : 'warning') ?> text-uppercase">
                                                                    <?= e($curStatus) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary-subtle text-secondary">Not Marked</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="btn-group" role="group" aria-label="Attendance status">
                                                                <input type="radio" class="btn-check att-status" name="status[<?= (int)$s['id'] ?>]" id="p_<?= $s['id'] ?>" value="present" <?= $curStatus === 'present' ? 'checked' : '' ?> <?= $isReadOnlyRegister ? 'disabled' : '' ?>>
                                                                <label class="btn btn-outline-success btn-status" for="p_<?= $s['id'] ?>">Present</label>

                                                                <input type="radio" class="btn-check att-status" name="status[<?= (int)$s['id'] ?>]" id="a_<?= $s['id'] ?>" value="absent" <?= $curStatus === 'absent' ? 'checked' : '' ?> <?= $isReadOnlyRegister ? 'disabled' : '' ?>>
                                                                <label class="btn btn-outline-danger btn-status" for="a_<?= $s['id'] ?>">Absent</label>

                                                                <input type="radio" class="btn-check att-status" name="status[<?= (int)$s['id'] ?>]" id="l_<?= $s['id'] ?>" value="late" <?= $curStatus === 'late' ? 'checked' : '' ?> <?= $isReadOnlyRegister ? 'disabled' : '' ?>>
                                                                <label class="btn btn-outline-warning btn-status" for="l_<?= $s['id'] ?>">Late</label>

                                                                <input type="radio" class="btn-check att-status" name="status[<?= (int)$s['id'] ?>]" id="od_<?= $s['id'] ?>" value="on_duty" <?= $curStatus === 'on_duty' ? 'checked' : '' ?> <?= $isReadOnlyRegister ? 'disabled' : '' ?>>
                                                                <label class="btn btn-outline-info btn-status" for="od_<?= $s['id'] ?>">OD</label>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (!empty($students) && !$isReadOnlyRegister): ?>
                                    <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="small text-muted">
                                            <i class="bi bi-info-circle me-1"></i> Attendance records immediately update aggregate percentages and examination hall ticket eligibility gates.
                                        </div>
                                        <button type="submit" class="btn btn-primary fw-bold px-4">
                                            <i class="bi bi-check2-circle me-1"></i> Save Attendance Register
                                        </button>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </form>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAll(status) {
            document.querySelectorAll(`input.att-status[value="${status}"]`).forEach(el => {
                el.checked = true;
            });
        }
    </script>
</body>
</html>
