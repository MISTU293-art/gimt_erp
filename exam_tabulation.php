<?php
// exam_tabulation.php - MAKAUT Consolidated Continuous Evaluation Tabulation Engine
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$canFinalize = in_array($userRole, ['chairman', 'principal', 'hod'], true);

// Fetch permitted courses
$courses = get_permitted_courses($userId, $userRole);

$reqCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedCourseId = 0;
if ($reqCourseId > 0 && can_teacher_access_course($userId, $reqCourseId, $userRole)) {
    $selectedCourseId = $reqCourseId;
} else {
    $selectedCourseId = !empty($courses) ? (int)$courses[0]['id'] : 0;
}

// Fetch Course
$currentCourse = null;
$tabulation = [];

if ($selectedCourseId > 0 && can_teacher_access_course($userId, $selectedCourseId, $userRole)) {
    $cStmt = $pdo->prepare("SELECT c.*, d.dept_name, d.dept_code FROM courses c JOIN departments d ON c.department_id = d.id WHERE c.id = ?");
    $cStmt->execute([$selectedCourseId]);
    $currentCourse = $cStmt->fetch();

    if ($currentCourse) {
        $isPractical = (stripos($currentCourse['course_name'], 'lab') !== false || stripos($currentCourse['course_name'], 'practical') !== false || ($currentCourse['subject_type'] ?? '') === 'Practical');

        // Fetch students and aggregate assessments
        $query = "
            SELECT s.id AS student_id, s.roll_no, s.makaut_roll_no, s.makaut_reg_no, u.name AS student_name,
                   MAX(CASE WHEN ie.exam_name = 'CA-1' THEN ie.marks_obtained ELSE (CASE WHEN ie.exam_name = 'CIA-1' THEN ie.marks_obtained ELSE NULL END) END) AS ca1_marks,
                   MAX(CASE WHEN ie.exam_name = 'CA-2' THEN ie.marks_obtained ELSE (CASE WHEN ie.exam_name = 'CIA-2' THEN ie.marks_obtained ELSE NULL END) END) AS ca2_marks,
                   MAX(CASE WHEN ie.exam_name = 'CA-3' THEN ie.marks_obtained ELSE NULL END) AS ca3_marks,
                   MAX(CASE WHEN ie.exam_name = 'CA-4' THEN ie.marks_obtained ELSE NULL END) AS ca4_marks,
                   MAX(CASE WHEN ie.exam_name = 'PCA-1' THEN ie.marks_obtained ELSE NULL END) AS pca1_marks,
                   MAX(CASE WHEN ie.exam_name = 'PCA-2' THEN ie.marks_obtained ELSE NULL END) AS pca2_marks,
                   COALESCE(AVG(asub.marks_obtained), 0) AS assignment_avg,
                   MAX(ie.is_locked) AS has_locked_marks
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN courses c ON c.id = ?
            LEFT JOIN internal_exams ie ON s.id = ie.student_id AND ie.course_id = c.id
            LEFT JOIN assignments a ON a.course_id = c.id
            LEFT JOIN assignment_submissions asub ON asub.assignment_id = a.id AND asub.student_id = s.id
            WHERE s.department_id = c.department_id AND s.current_semester = c.semester AND s.admission_status = 'admitted'
            GROUP BY s.id
            ORDER BY s.roll_no
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$selectedCourseId]);
        $rows = $stmt->fetchAll();

        // Fetch attendance for attendance marks calculation
        $attStmt = $pdo->prepare("
            SELECT student_id,
                   COUNT(id) AS total,
                   SUM(CASE WHEN status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) AS attended
            FROM attendance
            WHERE course_id = ?
            GROUP BY student_id
        ");
        $attStmt->execute([$selectedCourseId]);
        $attMap = [];
        foreach ($attStmt->fetchAll() as $at) {
            $tot = (int)$at['total'];
            $att = (int)$at['attended'];
            $attMap[$at['student_id']] = ($tot > 0) ? round(($att / $tot) * 100, 1) : 100.0;
        }

        foreach ($rows as $r) {
            $sId = $r['student_id'];
            $ca1 = $r['ca1_marks'] !== null ? (float)$r['ca1_marks'] : null;
            $ca2 = $r['ca2_marks'] !== null ? (float)$r['ca2_marks'] : null;
            $ca3 = $r['ca3_marks'] !== null ? (float)$r['ca3_marks'] : null;
            $ca4 = $r['ca4_marks'] !== null ? (float)$r['ca4_marks'] : null;

            $caArr = array_values(array_filter([$ca1, $ca2, $ca3, $ca4], fn($v) => $v !== null));
            rsort($caArr);
            $bestCa = 0.0;
            if (count($caArr) >= 2) {
                $bestCa = ($caArr[0] + $caArr[1]) / 2.0;
            } elseif (count($caArr) === 1) {
                $bestCa = $caArr[0];
            } else {
                $bestCa = 18.0; // Baseline default if not entered yet
            }

            // Attendance marks (out of 5)
            $pct = $attMap[$sId] ?? 100.0;
            $attMarks = ($pct >= 90.0) ? 5 : (($pct >= 80.0) ? 4 : (($pct >= 75.0) ? 3 : 0));

            // Total Theory Internal (out of 30)
            $consolidatedTotal = round($bestCa + $attMarks, 1);
            if ($consolidatedTotal > 30.0) $consolidatedTotal = 30.0;

            // Equivalent letter grade out of 30
            $normPct = ($consolidatedTotal / 30.0) * 100.0;
            $gradeData = makaut_grade_point($normPct);

            $r['best_ca'] = round($bestCa, 1);
            $r['att_pct'] = $pct;
            $r['att_marks'] = $attMarks;
            $r['consolidated_total'] = $consolidatedTotal;
            $r['letter_grade'] = $gradeData['grade'];
            $r['grade_point'] = $gradeData['point'];
            $r['is_practical'] = $isPractical;

            $tabulation[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidated Tabulation Engine &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        @media print {
            body { background: white; padding: 0; }
            .no-print, .role-sidebar, .sidebar-toggle, .gimt-sidebar, .makaut-topbar { display: none !important; }
            .dashboard-main { padding: 0; }
            .table-card { border: 1px solid #000; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="page-layout">
        <div class="no-print">
            <?php
            if ($userRole === 'chairman') {
                require __DIR__ . '/partials/superAdmin_sidebar.php';
            } elseif ($userRole === 'principal') {
                require __DIR__ . '/partials/principal_sidebar.php';
            } else {
                require __DIR__ . '/partials/role_sidebar.php';
            }
            ?>
        </div>

        <main class="dashboard-main">
            <div class="container-fluid p-0">

                <div class="mb-4 no-print">
                    <?php require __DIR__ . '/partials/makaut_header.php'; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">MAKAUT Examination System</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-shield-check me-1"></i> Continuous Evaluation</span>
                        </div>
                        <h1 class="h3 fw-bold mb-1">Consolidated Continuous Assessment Tabulation Engine</h1>
                        <p class="text-secondary mb-0">Best 2 of 4 CA marks (25M) + Attendance marks (5M) &bull; MAKAUT 30-Mark Continuous Assessment Tabulation</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="bi bi-printer me-1"></i> Print Tabulation Sheet
                        </button>
                        <a href="grade_card.php" class="btn btn-primary fw-bold">
                            <i class="bi bi-award me-1"></i> Student Grade Cards
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- Course Selector -->
                <div class="card p-3 mb-4 border-0 shadow-sm no-print">
                    <form method="get" action="exam_tabulation.php" class="row g-2 align-items-center">
                        <div class="col-md-9">
                            <label class="form-label small fw-bold text-muted">Assigned / Permitted Course</label>
                            <select name="course_id" class="form-select form-select-lg" onchange="this.form.submit()">
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                                        [<?= e($c['dept_code']) ?> Sem <?= $c['semester'] ?>] <?= e($c['course_code']) ?> &mdash; <?= e($c['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-lg w-100">Calculate Tabulation</button>
                        </div>
                    </form>
                </div>

                <!-- Tabulation Sheet -->
                <?php if ($currentCourse): ?>
                    <div class="table-card">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="badge bg-primary font-monospace fs-6 me-2"><?= e($currentCourse['course_code']) ?></span>
                                <strong class="fs-6"><?= e($currentCourse['course_name']) ?></strong>
                                <span class="text-secondary small ms-2">&bull; <?= e($currentCourse['dept_name']) ?> &bull; Semester <?= (int)$currentCourse['semester'] ?></span>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                <i class="bi bi-calculator"></i> MAKAUT 30-Mark Tabulation
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th class="text-start">Roll No &bull; Student Name</th>
                                        <th>CA-1<br><small class="text-muted">(25M)</small></th>
                                        <th>CA-2<br><small class="text-muted">(25M)</small></th>
                                        <th>CA-3<br><small class="text-muted">(25M)</small></th>
                                        <th>CA-4<br><small class="text-muted">(25M)</small></th>
                                        <th>Best CA<br><small class="text-muted">(25M)</small></th>
                                        <th>Att Marks<br><small class="text-muted">(5M)</small></th>
                                        <th class="bg-primary-subtle text-primary fw-bold">Internal Total<br><small>(30M)</small></th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tabulation)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">No students enrolled in this course semester.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tabulation as $idx => $row): ?>
                                            <?php $isPass = ($row['letter_grade'] !== 'F'); ?>
                                            <tr class="text-center">
                                                <td class="text-muted"><?= $idx + 1 ?></td>
                                                <td class="text-start">
                                                    <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($row['roll_no']) ?></span>
                                                    <div class="fw-bold d-inline-block ms-2"><?= e($row['student_name']) ?></div>
                                                    <?php if (!empty($row['makaut_roll_no'])): ?>
                                                        <div class="small text-muted font-monospace ms-2">Univ: <?= e($row['makaut_roll_no']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $row['ca1_marks'] !== null ? $row['ca1_marks'] : '<span class="text-muted">&mdash;</span>' ?></td>
                                                <td><?= $row['ca2_marks'] !== null ? $row['ca2_marks'] : '<span class="text-muted">&mdash;</span>' ?></td>
                                                <td><?= $row['ca3_marks'] !== null ? $row['ca3_marks'] : '<span class="text-muted">&mdash;</span>' ?></td>
                                                <td><?= $row['ca4_marks'] !== null ? $row['ca4_marks'] : '<span class="text-muted">&mdash;</span>' ?></td>
                                                <td class="fw-bold font-monospace"><?= $row['best_ca'] ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= $row['att_marks'] ?></span>
                                                    <div class="small text-muted" style="font-size: 0.68rem;"><?= $row['att_pct'] ?>%</div>
                                                </td>
                                                <td class="bg-primary-subtle text-primary fw-bold font-monospace fs-5"><?= $row['consolidated_total'] ?></td>
                                                <td>
                                                    <span class="badge <?= $isPass ? 'bg-primary' : 'bg-danger' ?> font-monospace fs-6">
                                                        <?= e($row['letter_grade']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small text-muted">
                                <strong>MAKAUT Regulation Clause:</strong> Theory internal continuous evaluation aggregates Best 2 CA components + class attendance bonus.
                            </div>
                            <div class="small fw-semibold text-secondary">
                                Evaluated Students: <strong><?= count($tabulation) ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
