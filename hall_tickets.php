<?php
// hall_tickets.php - Exam Hall Ticket Eligibility, Defaulter Blocking & Principal Override
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$isStudent = ($userRole === 'student');
$isPrincipalOrChairman = in_array($userRole, ['chairman', 'principal'], true);

$studentId = 0;
if ($isStudent) {
    $std = get_current_student();
    if (!$std) {
        set_flash('danger', 'Student profile not linked.');
        header('Location: student_dashboard.php');
        exit;
    }
    $studentId = (int)$std['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if ($studentId === 0) {
        $studentId = (int)$pdo->query("SELECT id FROM students ORDER BY roll_no LIMIT 1")->fetchColumn();
    }
}

$selectedExamSeries = trim($_GET['exam_series'] ?? 'Continuous Internal Assessment 1 (CIA-1)');

// Handle Principal / Chairman Override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isPrincipalOrChairman) {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'grant_override') {
        $sId = (int)($_POST['student_id'] ?? 0);
        $series = trim($_POST['exam_series'] ?? '');
        $sem = (int)($_POST['semester'] ?? 1);
        $reason = trim($_POST['reason'] ?? '');
        $overrideType = trim($_POST['override_type'] ?? 'attendance');

        if ($sId > 0 && !empty($series) && !empty($reason)) {
            $stmt = $pdo->prepare("
                INSERT INTO hall_ticket_overrides (student_id, exam_series, semester, override_type, reason, approved_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), approved_by = VALUES(approved_by)
            ");
            $stmt->execute([$sId, $series, $sem, $overrideType, $reason, $_SESSION['user_id']]);
            audit_log('Hall Ticket Override Granted', 'hall_ticket_overrides', $sId, "Override for {$series} by {$_SESSION['user_name']}: {$reason}");
            set_flash('success', "Principal special condonation granted! Hall ticket unlocked for student.");
            header("Location: hall_tickets.php?student_id={$sId}&exam_series=" . urlencode($series));
            exit;
        }
    } elseif ($action === 'revoke_override') {
        $overrideId = (int)($_POST['override_id'] ?? 0);
        if ($overrideId > 0) {
            $pdo->prepare("DELETE FROM hall_ticket_overrides WHERE id = ?")->execute([$overrideId]);
            audit_log('Hall Ticket Override Revoked', 'hall_ticket_overrides', $overrideId, "Revoked by {$_SESSION['user_name']}");
            set_flash('info', "Override revoked.");
            header("Location: hall_tickets.php?student_id={$studentId}&exam_series=" . urlencode($selectedExamSeries));
            exit;
        }
    }
}

// Fetch Student Profile
$student = null;
$courses = [];
$attendancePct = 0.00;
$feeBalanceDue = 0.00;
$isOverridden = false;
$overrideRecord = null;

if ($studentId > 0) {
    $stdStmt = $pdo->prepare("
        SELECT s.*, u.name, u.email, d.dept_name, d.dept_code
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stdStmt->execute([$studentId]);
    $student = $stdStmt->fetch();

    if ($student) {
        // 1. Calculate Aggregate Attendance
        $attStmt = $pdo->prepare("
            SELECT
                COUNT(id) AS total,
                SUM(CASE WHEN status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) AS attended
            FROM attendance
            WHERE student_id = ?
        ");
        $attStmt->execute([$studentId]);
        $attData = $attStmt->fetch();
        $totalClasses = (int)($attData['total'] ?? 0);
        $attendedClasses = (int)($attData['attended'] ?? 0);
        $attendancePct = ($totalClasses > 0) ? round(($attendedClasses / $totalClasses) * 100, 2) : 100.00;

        // 2. Calculate Outstanding Fee Balance
        $feeStmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type IN ('credit', 'waiver') THEN amount ELSE 0 END), 0) AS balance_due
            FROM student_ledger
            WHERE student_id = ?
        ");
        $feeStmt->execute([$studentId]);
        $feeBalanceDue = (float)$feeStmt->fetchColumn();

        // 3. Check for Principal Override
        $ovrStmt = $pdo->prepare("
            SELECT hto.*, u.name AS approver_name
            FROM hall_ticket_overrides hto
            JOIN users u ON hto.approved_by = u.id
            WHERE hto.student_id = ? AND hto.exam_series = ? AND hto.semester = ?
            LIMIT 1
        ");
        $ovrStmt->execute([$studentId, $selectedExamSeries, $student['current_semester']]);
        $overrideRecord = $ovrStmt->fetch();
        $isOverridden = ($overrideRecord !== false && !empty($overrideRecord));

        // 4. Fetch Enrolled Courses for this Semester
        $cStmt = $pdo->prepare("
            SELECT c.* FROM courses c
            WHERE c.department_id = ? AND c.semester = ?
            ORDER BY c.course_code
        ");
        $cStmt->execute([$student['department_id'], $student['current_semester']]);
        $courses = $cStmt->fetchAll();
    }
}

// Eligibility Rule: Attendance >= 75% AND Fee <= 0 (OR has Principal Override)
$attendanceEligible = ($attendancePct >= 75.00);
$feeEligible = ($feeBalanceDue <= 0.00);
$isEligible = ($attendanceEligible && $feeEligible) || $isOverridden;

// List all students for staff selector
$allStudents = [];
if (!$isStudent) {
    $allStudents = $pdo->query("SELECT s.id, s.roll_no, u.name, d.dept_code FROM students s JOIN users u ON s.user_id = u.id JOIN departments d ON s.department_id = d.id ORDER BY s.roll_no")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Hall Ticket &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .hall-ticket-card { background: white; border: 2px solid #123b70; border-radius: 12px; padding: 35px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); position: relative; }
        .block-card { background: #fef2f2; border: 2px solid #ef4444; border-radius: 12px; padding: 35px; }
        .qr-placeholder { width: 90px; height: 90px; border: 2px dashed #123b70; display: grid; place-content: center; font-size: 0.75rem; text-align: center; }
        @media print {
            body { background: white; padding: 0; }
            .no-print, .role-sidebar, .sidebar-toggle, .gimt-sidebar { display: none !important; }
            .dashboard-main { padding: 0; }
            .hall-ticket-card { border: 1.5px solid #000; box-shadow: none; padding: 20px; }
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
            <div class="container-fluid p-0" style="max-width: 900px;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Examination Hall Ticket Portal</h1>
                        <p class="text-secondary mb-0">Automated eligibility gate, attendance defaulter blocking &amp; Principal condonation</p>
                    </div>
                    <?php if ($isEligible): ?>
                        <button onclick="window.print()" class="btn btn-primary fw-bold">
                            <i class="bi bi-printer me-1"></i> Print Hall Ticket
                        </button>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <!-- Staff Student Selector -->
                <?php if (!$isStudent): ?>
                    <div class="card p-3 mb-4 border-0 shadow-sm no-print">
                        <form method="get" action="hall_tickets.php" class="row g-2 align-items-center">
                            <div class="col-md-7">
                                <select name="student_id" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($allStudents as $st): ?>
                                        <option value="<?= (int)$st['id'] ?>" <?= $studentId === (int)$st['id'] ? 'selected' : '' ?>>
                                            <?= e($st['roll_no']) ?> &mdash; <?= e($st['name']) ?> (<?= e($st['dept_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <select name="exam_series" class="form-select" onchange="this.form.submit()">
                                    <option value="Continuous Internal Assessment 1 (CIA-1)" <?= $selectedExamSeries === 'Continuous Internal Assessment 1 (CIA-1)' ? 'selected' : '' ?>>CIA-1 Examination</option>
                                    <option value="Continuous Internal Assessment 2 (CIA-2)" <?= $selectedExamSeries === 'Continuous Internal Assessment 2 (CIA-2)' ? 'selected' : '' ?>>CIA-2 Examination</option>
                                    <option value="Practical Pre-Final Examination" <?= $selectedExamSeries === 'Practical Pre-Final Examination' ? 'selected' : '' ?>>Practical Pre-Final</option>
                                </select>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($student): ?>

                    <!-- ELIGIBILITY GATE STATUS -->
                    <div class="card p-3 mb-4 border-0 shadow-sm no-print">
                        <h2 class="h6 fw-bold text-uppercase text-secondary mb-2">Automated Eligibility Gate</h2>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Attendance Threshold (&ge;75%)</div>
                                    <div class="h5 mb-0 fw-bold <?= $attendanceEligible ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($attendancePct, 1) ?>% &bull; <?= $attendanceEligible ? '<span class="badge bg-success">Passed</span>' : '<span class="badge bg-danger">Shortage</span>' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Financial Dues Clearance</div>
                                    <div class="h5 mb-0 fw-bold <?= $feeEligible ? 'text-success' : 'text-danger' ?>">
                                        <?= format_inr($feeBalanceDue) ?> &bull; <?= $feeEligible ? '<span class="badge bg-success">Cleared</span>' : '<span class="badge bg-danger">Unpaid</span>' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light">
                                    <div class="small text-muted">Executive Override Status</div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php if ($isOverridden): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-patch-check"></i> Principal Override Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No Override</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!$isEligible): ?>
                        <!-- BLOCKED HALL TICKET NOTICE -->
                        <div class="block-card mb-4 text-center">
                            <div class="text-danger mb-3">
                                <i class="bi bi-shield-slash-fill" style="font-size: 4rem;"></i>
                            </div>
                            <h2 class="h4 fw-bold text-danger mb-2">Exam Hall Ticket Restricted</h2>
                            <p class="text-secondary mb-4 mx-auto" style="max-width: 600px;">
                                Hall ticket generation is blocked under institutional academic bylaws because this candidate does not meet the statutory eligibility requirements:
                            </p>
                            <div class="d-flex justify-content-center gap-3 mb-4 flex-wrap">
                                <?php if (!$attendanceEligible): ?>
                                    <span class="badge bg-danger p-2 fs-6"><i class="bi bi-x-circle me-1"></i> Attendance Below 75% (Current: <?= number_format($attendancePct, 1) ?>%)</span>
                                <?php endif; ?>
                                <?php if (!$feeEligible): ?>
                                    <span class="badge bg-danger p-2 fs-6"><i class="bi bi-cash-stack me-1"></i> Overdue Fee Due: <?= format_inr($feeBalanceDue) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($isPrincipalOrChairman): ?>
                                <!-- PRINCIPAL OVERRIDE CONTROL -->
                                <div class="card bg-white p-4 border-warning text-start mx-auto" style="max-width: 620px;">
                                    <h3 class="h6 fw-bold text-dark text-uppercase mb-2">
                                        <i class="bi bi-award-fill text-warning me-1"></i> Principal / Dean Special Condonation Workbench
                                    </h3>
                                    <p class="small text-muted mb-3">
                                        As Principal or Chairman, you have the regulatory authority to grant an attendance condonation waiver or fee extension to unblock this student's exam hall ticket.
                                    </p>
                                    <form action="hall_tickets.php" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="grant_override">
                                        <input type="hidden" name="student_id" value="<?= $studentId ?>">
                                        <input type="hidden" name="exam_series" value="<?= e($selectedExamSeries) ?>">
                                        <input type="hidden" name="semester" value="<?= (int)$student['current_semester'] ?>">

                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Override Classification</label>
                                            <select name="override_type" class="form-select">
                                                <option value="attendance">Attendance Shortage Medical Condonation</option>
                                                <option value="fee">Fee Payment Grace Period Extension</option>
                                                <option value="both">Both Attendance &amp; Fee Exemption</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold required">Regulatory Reason / Official Note</label>
                                            <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Approved condonation on account of hospital admission & verified medical certificates." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                                            <i class="bi bi-unlock-fill me-1"></i> Authorize Condonation &amp; Unlock Hall Ticket
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted">
                                    Please contact your Department HOD or the Principal's Office to file a condonation appeal with medical documentation.
                                </p>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>

                        <!-- PRINTABLE OFFICIAL HALL TICKET -->
                        <?php if ($isOverridden && $isPrincipalOrChairman): ?>
                            <div class="alert alert-warning d-flex justify-content-between align-items-center mb-3 no-print">
                                <div>
                                    <i class="bi bi-patch-check-fill me-1"></i> Unlocked via Principal Condonation:
                                    <strong><?= e($overrideRecord['reason']) ?></strong> (by <?= e($overrideRecord['approver_name']) ?>)
                                </div>
                                <form action="hall_tickets.php" method="post" class="d-inline" onsubmit="return confirm('Revoke this condonation?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="revoke_override">
                                    <input type="hidden" name="override_id" value="<?= $overrideRecord['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="hall-ticket-card">
                            <!-- Institution Header -->
                            <div class="text-center border-bottom pb-3 mb-4">
                                <h1 class="h4 fw-bold text-primary mb-1">GLOBAL INSTITUTE OF MANAGEMENT &amp; TECHNOLOGY</h1>
                                <p class="small text-muted mb-1">Approved by AICTE &bull; Affiliated to MAKAUT, West Bengal &bull; College Code: <strong>249</strong></p>
                                <p class="small text-muted mb-2">Palpara More, NH-34, Krishnagar, Nadia, WB - 741102</p>
                                <span class="badge bg-dark px-3 py-1 text-uppercase">Admit Card / Exam Hall Ticket &bull; <?= e($selectedExamSeries) ?></span>
                            </div>

                            <!-- Student Info & Photo Row -->
                            <div class="row align-items-center mb-4">
                                <div class="col-8">
                                    <table class="table table-borderless table-sm mb-0">
                                        <tr>
                                            <td class="text-muted" style="width: 160px;">Candidate Name:</td>
                                            <td class="fw-bold fs-6"><?= e($student['name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">MAKAUT Univ Roll No:</td>
                                            <td class="fw-bold font-monospace text-primary fs-6"><?= e($student['makaut_roll_no'] ?: '249001' . substr((string)$student['batch_year'], 2, 2) . sprintf('%03d', $student['id'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">MAKAUT Reg No:</td>
                                            <td class="font-monospace fw-semibold"><?= e($student['makaut_reg_no'] ?: '249' . $student['batch_year'] . sprintf('%04d', $student['id'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">College Roll No:</td>
                                            <td class="fw-bold font-monospace text-secondary"><?= e($student['roll_no']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Department:</td>
                                            <td class="fw-semibold"><?= e($student['dept_name']) ?> (<?= e($student['dept_code']) ?>)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Semester / Batch:</td>
                                            <td>Semester <?= (int)$student['current_semester'] ?> &bull; Batch <?= (int)$student['batch_year'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">MAKAUT MAR Status:</td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle font-monospace">
                                                    MAR: <?= (int)($student['mar_points'] ?? 0) ?> / 100 Pts
                                                </span>
                                                <span class="badge bg-secondary-subtle text-secondary font-monospace ms-1">
                                                    MOOCs: <?= (int)($student['moocs_credits'] ?? 0) ?> Credits
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Eligibility Status:</td>
                                            <td><span class="badge bg-success">Admit Card Valid &bull; Verified For Examination</span></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="qr-placeholder ms-auto mb-1">
                                        <span>MAKAUT 249<br>GIMT EXAM SEAL</span>
                                    </div>
                                    <span class="small text-muted font-monospace"><?= e($student['roll_no']) ?></span>
                                </div>
                            </div>

                            <!-- Course Schedules Table -->
                            <h2 class="h6 fw-bold text-uppercase text-secondary mb-2">Permitted Courses for Examination</h2>
                            <table class="table table-bordered mb-4">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 15%;">Course Code</th>
                                        <th>Subject Name</th>
                                        <th style="width: 15%;">Credits</th>
                                        <th style="width: 25%;">Invigilator Signature</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold"><?= e($c['course_code']) ?></td>
                                            <td><?= e($c['course_name']) ?> (<?= e($c['subject_type']) ?>)</td>
                                            <td><?= (int)$c['credits'] ?> Units</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Instructions -->
                            <div class="small text-muted mb-5">
                                <strong>Candidate Instructions:</strong>
                                <ol class="ps-3 mb-0">
                                    <li>Candidate must bring this physical printed Hall Ticket along with valid Institute Photo ID Card.</li>
                                    <li>Entry into the examination hall is prohibited after 15 minutes of commencement.</li>
                                    <li>Electronic gadgets, smart watches, and programmable calculators are strictly prohibited.</li>
                                </ol>
                            </div>

                            <!-- Signature Desk -->
                            <div class="row pt-4 border-top text-center">
                                <div class="col-6">
                                    <div style="border-top: 1px dashed #64748b; width: 180px; margin: 30px auto 5px;"></div>
                                    <span class="small fw-bold">Candidate Signature</span>
                                </div>
                                <div class="col-6">
                                    <div style="border-top: 1px dashed #64748b; width: 180px; margin: 30px auto 5px;"></div>
                                    <span class="small fw-bold">Controller of Examinations / Principal</span>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
