<?php
// student_dashboard.php - Student Self-Service Academic & Financial Portal
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['student']);

$pdo = getDb();
$student = get_current_student();

if (!$student) {
    echo "Student profile not linked to user account. Please contact administrative office.";
    exit;
}

$studentId = (int)$student['id'];

// 1. Calculate Aggregate Attendance
$attStmt = $pdo->prepare("
    SELECT
        COUNT(id) AS total,
        SUM(CASE WHEN status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) AS attended,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent
    FROM attendance
    WHERE student_id = ?
");
$attStmt->execute([$studentId]);
$attData = $attStmt->fetch();
$totalClasses = (int)($attData['total'] ?? 0);
$attendedClasses = (int)($attData['attended'] ?? 0);
$attendancePct = ($totalClasses > 0) ? round(($attendedClasses / $totalClasses) * 100, 1) : 100.0;

// 2. Calculate Financial Balance Due
$feeStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN type IN ('credit', 'waiver') THEN amount ELSE 0 END), 0) AS balance_due
    FROM student_ledger
    WHERE student_id = ?
");
$feeStmt->execute([$studentId]);
$balanceDue = (float)$feeStmt->fetchColumn();

// 3. Fetch Enrolled Courses
$cStmt = $pdo->prepare("
    SELECT c.*, fc.section, u.name AS teacher_name
    FROM courses c
    LEFT JOIN faculty_courses fc ON c.id = fc.course_id
    LEFT JOIN users u ON fc.teacher_id = u.id
    WHERE c.department_id = ? AND c.semester = ?
    ORDER BY c.course_code
");
$cStmt->execute([$student['department_id'], $student['current_semester']]);
$courses = $cStmt->fetchAll();

// 4. Fetch Pending Assignments
$aStmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_name,
           sub.id AS submission_id, sub.marks_obtained
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
    WHERE c.department_id = ? AND c.semester = ?
    ORDER BY a.due_date ASC
");
$aStmt->execute([$studentId, $student['department_id'], $student['current_semester']]);
$assignments = $aStmt->fetchAll();

$pendingAssignmentsCount = 0;
foreach ($assignments as $asg) {
    if (empty($asg['submission_id'])) {
        $pendingAssignmentsCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .kpi-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); height: 100%; }
        .kpi-val { font-size: 2rem; font-weight: 800; line-height: 1.1; margin-top: 5px; }
        .module-tile { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-decoration: none; color: inherit; display: block; transition: all 0.2s; }
        .module-tile:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.06); color: #123b70; border-color: #123b70; }
        .currency { font-family: monospace; font-weight: 700; }
    </style>
</head>
<body>
    <div class="page-layout">
        <?php require __DIR__ . '/partials/role_sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0" style="max-width: 1100px;">

                <!-- MAKAUT Enterprise Header -->
                <div class="mb-4">
                    <?php require __DIR__ . '/partials/makaut_header.php'; ?>
                </div>

                <!-- Welcome Header -->
                <div class="card bg-primary text-white border-0 shadow-sm p-4 rounded-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-white text-primary font-monospace fs-6"><?= e($student['roll_no']) ?></span>
                                <span class="badge bg-white-50 text-white font-monospace">Univ Roll: <?= e($student['makaut_roll_no'] ?: '249001' . substr((string)$student['batch_year'], 2, 2) . sprintf('%03d', $student['id'])) ?></span>
                            </div>
                            <h1 class="h3 fw-bold mb-1">Welcome, <?= e($student['name'] ?? $_SESSION['user_name']) ?></h1>
                            <p class="text-white-50 mb-0">
                                <?= e($student['dept_name']) ?> &bull; Semester <?= (int)$student['current_semester'] ?> &bull; Batch <?= (int)$student['batch_year'] ?> &bull; Affiliated to MAKAUT (Code 249)
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                <i class="bi bi-shield-check me-1"></i> Active Enrolment
                            </span>
                            <div class="small text-white-50 mt-1 font-monospace">MAKAUT Reg: <?= e($student['makaut_reg_no'] ?: '249' . $student['batch_year'] . sprintf('%04d', $student['id'])) ?></div>
                        </div>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- KPI Metric Grid -->
                <div class="row g-3 mb-4">
                    <!-- Attendance -->
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="text-secondary small fw-bold text-uppercase">Attendance Rate</div>
                            <div class="kpi-val <?= $attendancePct >= 75 ? 'text-success' : 'text-danger' ?>">
                                <?= $attendancePct ?>%
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar <?= $attendancePct >= 75 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= min(100, $attendancePct) ?>%"></div>
                            </div>
                            <div class="small text-muted mt-2">
                                <?= $attendancePct >= 75 ? '<i class="bi bi-check-circle-fill text-success"></i> Exam Hall Ticket Eligible' : '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Below 75% Floor' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Balance -->
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="text-secondary small fw-bold text-uppercase">Account Balance Due</div>
                            <div class="kpi-val <?= $balanceDue > 0 ? 'text-danger' : 'text-success' ?> currency">
                                <?= format_inr($balanceDue) ?>
                            </div>
                            <div class="small mt-2">
                                <?php if ($balanceDue > 0): ?>
                                    <span class="badge bg-danger-subtle text-danger">Payment Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">No Dues Cleared</span>
                                <?php endif; ?>
                            </div>
                            <a href="student_ledger.php" class="small text-decoration-none mt-2 d-block">View Full Ledger &rarr;</a>
                        </div>
                    </div>

                    <!-- Enrolled Courses -->
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="text-secondary small fw-bold text-uppercase">Enrolled Subjects</div>
                            <div class="kpi-val text-primary"><?= count($courses) ?></div>
                            <div class="small text-muted mt-2">Semester <?= (int)$student['current_semester'] ?> Curriculum</div>
                            <a href="courses_master.php" class="small text-decoration-none mt-2 d-block">Course Details &rarr;</a>
                        </div>
                    </div>

                    <!-- Coursework Deadlines -->
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="text-secondary small fw-bold text-uppercase">Pending Coursework</div>
                            <div class="kpi-val <?= $pendingAssignmentsCount > 0 ? 'text-warning' : 'text-success' ?>">
                                <?= $pendingAssignmentsCount ?>
                            </div>
                            <div class="small text-muted mt-2">Deadlines awaiting solution</div>
                            <a href="student_assignments.php" class="small text-decoration-none mt-2 d-block">Submissions Portal &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Fast Navigation Modules -->
                <h2 class="h5 fw-bold mb-3">Institutional Student Modules</h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-sm-6">
                        <a href="student_ledger.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary-subtle text-primary p-3 rounded-circle fs-4">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Financial Ledger</h3>
                                    <div class="small text-muted">Itemized debits, online fee receipts &amp; payments</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="hall_tickets.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-warning-subtle text-warning p-3 rounded-circle fs-4">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Exam Hall Ticket</h3>
                                    <div class="small text-muted">Admit card generation &amp; eligibility gate verification</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="grade_card.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-success-subtle text-success p-3 rounded-circle fs-4">
                                    <i class="bi bi-award"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Digital Grade Card</h3>
                                    <div class="small text-muted">Locked term transcript, subject GPA &amp; SGPA summary</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="student_marks.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary-subtle text-primary p-3 rounded-circle fs-4">
                                    <i class="bi bi-clipboard2-check"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Continuous Internal &amp; PCA Marks</h3>
                                    <div class="small text-muted">View subject CA-1 to CA-4, Lab PCA &amp; Faculty status</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="student_assignments.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info-subtle text-info p-3 rounded-circle fs-4">
                                    <i class="bi bi-journal-arrow-up"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Coursework &amp; Assignments</h3>
                                    <div class="small text-muted">Upload solutions &amp; view teacher evaluation feedback</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="leave_requests.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-danger-subtle text-danger p-3 rounded-circle fs-4">
                                    <i class="bi bi-envelope-paper"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Leave &amp; Condonation</h3>
                                    <div class="small text-muted">Submit medical absence appeals for HOD endorsement</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="attendance_analytics.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-secondary-subtle text-secondary p-3 rounded-circle fs-4">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Attendance Analytics</h3>
                                    <div class="small text-muted">Subject-wise lecture attendance &amp; 75% monitoring</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="makaut_mar.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary-subtle text-primary p-3 rounded-circle fs-4">
                                    <i class="bi bi-award-fill"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">MAKAUT MAR Portal</h3>
                                    <div class="small text-muted">Claim 100 Mandatory Points (Curricular &amp; NSS)</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="makaut_moocs.php" class="module-tile">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-success-subtle text-success p-3 rounded-circle fs-4">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </div>
                                <div>
                                    <h3 class="h6 fw-bold mb-1">MOOCs / SWAYAM / NPTEL</h3>
                                    <div class="small text-muted">20 Honours Credits certification tracking</div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Enrolled Course Catalog Table -->
                <div class="kpi-card mb-4">
                    <h2 class="h6 fw-bold text-uppercase text-secondary mb-3"><i class="bi bi-book me-1"></i> Current Semester Enrolled Courses</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Code</th>
                                    <th>Subject Name</th>
                                    <th>Credits</th>
                                    <th>Type</th>
                                    <th>Faculty Guide</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                    <tr>
                                        <td><span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($c['course_code']) ?></span></td>
                                        <td class="fw-bold"><?= e($c['course_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= (int)$c['credits'] ?> Units</span></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($c['subject_type']) ?></span></td>
                                        <td class="small text-muted"><?= e($c['teacher_name'] ?: 'Department Faculty') ?></td>
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
