<?php
require_once __DIR__ . '/config/db.config.php';
require_once __DIR__ . '/middleware/isLoggedIn.php';
require_once __DIR__ . '/middleware/chairmanOnly.php';

$pdo = getDb();
$user_name = $_SESSION['user_name'] ?? 'Administrator';
$user_role = $_SESSION['user_role'] ?? '';

$roleLabels = [
    'chairman' => 'Chairman',
    'principal' => 'Principal',
    'accounts' => 'Accounts',
    'hr' => 'HR',
    'hod' => 'Head of Department',
    'teacher' => 'Teacher',
    'student' => 'Student'
];
$userCounts = array_fill_keys(array_keys($roleLabels), 0);

$roleResult = $conn->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
if ($roleResult) {
    while ($row = $roleResult->fetch_assoc()) {
        if (isset($userCounts[$row['role']])) {
            $userCounts[$row['role']] = (int) $row['total'];
        }
    }
    $roleResult->free();
}

$departments = $pdo->query("SELECT id, dept_code AS dept_id, dept_name, dept_capacity, created_at FROM departments ORDER BY dept_name")->fetchAll();

$recentUsers = $pdo->query("SELECT name, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 6")->fetchAll();

// Executive Institutional Metrics
$totalDebits = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM student_ledger WHERE type = 'debit'")->fetchColumn();
$totalCredits = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM student_ledger WHERE type IN ('credit', 'waiver')")->fetchColumn();
$totalDues = $totalDebits - $totalCredits;

$totalAdmissions = (int)$pdo->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
$totalEnrolledStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE admission_status = 'admitted'")->fetchColumn();

$avgAttendance = (float)$pdo->query("
    SELECT ROUND(COALESCE(SUM(CASE WHEN status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) / NULLIF(COUNT(id), 0) * 100, 100), 1)
    FROM attendance
")->fetchColumn();

$recentLogs = $pdo->query("
    SELECT al.*, u.name AS user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 5
")->fetchAll();

$totalUsers = array_sum($userCounts);
$totalDepartments = count($departments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIMT ERP &bull; Chairman Executive Suite</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --gimt-primary: #123b70;
            --gimt-primary-dark: #0a1f3d;
            --gimt-blue: #123b70;
            --gimt-blue-light: #eff6ff;
            --page-bg: #f5f7fb;
            --card-bg: #ffffff;
            --text-primary: #172b4d;
        }
        body { margin: 0; min-height: 100vh; background: var(--page-bg); color: var(--text-primary); font-family: "Segoe UI", Tahoma, sans-serif; }
        .dashboard-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; min-width: 0; padding: 28px; }
        .dashboard-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 4px 16px rgba(15,23,42,0.04); height: 100%; }
        .metric-value { font-size: 1.8rem; font-weight: 750; color: #123b70; line-height: 1.1; }
        .currency { font-family: monospace; font-weight: 700; }
        .quick-tile { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-decoration: none; color: inherit; display: block; transition: all 0.2s; }
        .quick-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.06); border-color: var(--gimt-primary); color: var(--gimt-primary); }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include('./partials/superAdmin_sidebar.php') ?>

        <main class="dashboard-main">
            <!-- Header -->
            <header class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-4 mb-4 rounded-4 border bg-white shadow-sm">
                <div>
                    <span class="badge text-bg-primary px-3 py-1 rounded-pill mb-1">CHAIRMAN EXECUTIVE DESK</span>
                    <h1 class="h3 fw-bold mb-1">GIMT Enterprise ERP Operations</h1>
                    <p class="text-secondary mb-0">Welcome, <strong><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></strong> &bull; <?= date('l, d F Y') ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="audit_logs.php" class="btn btn-outline-primary">
                        <i class="bi bi-shield-check me-1"></i> Audit Trail
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger" onclick="return confirm('Log out?');">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Executive Institutional Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Total Enrolled Students</div>
                        <div class="metric-value text-primary"><?= $totalEnrolledStudents ?></div>
                        <div class="small text-muted mt-2">Active academic enrolments</div>
                        <a href="student_ledger.php" class="small text-decoration-none mt-1 d-block">Student Registry &rarr;</a>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Admissions Pipeline</div>
                        <div class="metric-value text-info"><?= $totalAdmissions ?></div>
                        <div class="small text-muted mt-2">Applications this term</div>
                        <a href="admissions_manage.php" class="small text-decoration-none mt-1 d-block">Manage Pipeline &rarr;</a>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Total Fee Receipts Cleared</div>
                        <div class="metric-value text-success currency">₹<?= number_format($totalCredits, 2) ?></div>
                        <div class="small text-muted mt-2">Accounts counter &amp; online</div>
                        <a href="fee_collect.php" class="small text-decoration-none mt-1 d-block">Collection Counter &rarr;</a>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Outstanding Ledger Dues</div>
                        <div class="metric-value text-danger currency">₹<?= number_format($totalDues, 2) ?></div>
                        <div class="small text-muted mt-2">Overdue student balances</div>
                        <a href="fee_defaulters.php" class="small text-decoration-none mt-1 d-block">Defaulters Tracker &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Quick Action Portals -->
            <h2 class="h5 fw-bold mb-3">Enterprise Core Modules</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <a href="admissions_manage.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary-subtle text-primary p-3 rounded-circle fs-4"><i class="bi bi-person-check"></i></div>
                            <div>
                                <div class="fw-bold">Admissions</div>
                                <div class="small text-muted">Review, verify &amp; enroll</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="fee_collect.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success-subtle text-success p-3 rounded-circle fs-4"><i class="bi bi-cash-coin"></i></div>
                            <div>
                                <div class="fw-bold">Counter Fee Desk</div>
                                <div class="small text-muted">Print formal receipts</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="attendance_analytics.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning-subtle text-warning p-3 rounded-circle fs-4"><i class="bi bi-graph-up"></i></div>
                            <div>
                                <div class="fw-bold">Attendance &lt;75%</div>
                                <div class="small text-muted">Hall ticket eligibility gate</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="exam_tabulation.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info-subtle text-info p-3 rounded-circle fs-4"><i class="bi bi-calculator"></i></div>
                            <div>
                                <div class="fw-bold">Exam Tabulation</div>
                                <div class="small text-muted">Consolidated continuous marks</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Data Breakdown Rows -->
            <div class="row g-3 mb-4">
                <div class="col-lg-5">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 fw-bold mb-0">System Users by Role</h2>
                            <a href="staffs.php" class="btn btn-sm btn-outline-primary">View Directory</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Role / Designation</th>
                                        <th class="text-end">Accounts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roleLabels as $rKey => $rLabel): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rLabel) ?></td>
                                            <td class="text-end fw-bold"><?= $userCounts[$rKey] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <th>Total Active Accounts</th>
                                        <td class="text-end fw-bold text-primary"><?= $totalUsers ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 fw-bold mb-0">Recent Immutable Audit Trail</h2>
                            <a href="audit_logs.php" class="btn btn-sm btn-outline-primary">Full Audit Log</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Action</th>
                                        <th>Actor</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLogs as $rl): ?>
                                        <tr>
                                            <td class="small text-muted"><?= date('d M, h:i A', strtotime($rl['created_at'])) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($rl['action']) ?></span></td>
                                            <td class="small fw-semibold"><?= htmlspecialchars($rl['user_name'] ?: 'System') ?></td>
                                            <td class="small font-monospace text-truncate" style="max-width: 250px;"><?= htmlspecialchars($rl['details'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
