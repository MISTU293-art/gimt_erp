<?php
require_once __DIR__ . '/config/db.config.php';
require_once __DIR__ . '/middleware/isLoggedIn.php';
require_once __DIR__ . '/middleware/principalOnly.php';

$pdo = getDb();
$user_name = $_SESSION['user_name'] ?? 'Principal';
$user_role = $_SESSION['user_role'] ?? 'principal';

// Principal Key Metrics
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE admission_status = 'admitted'")->fetchColumn();
$pendingAdmissions = (int)$pdo->query("SELECT COUNT(*) FROM admissions WHERE status IN ('submitted', 'under_review')")->fetchColumn();

// Defaulters (< 75%)
$attDefaulters = (int)$pdo->query("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN attendance a ON s.id = a.student_id
    GROUP BY s.id
    HAVING (SUM(CASE WHEN a.status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) / COUNT(a.id)) < 0.75
")->rowCount();

// Fee Defaulters
$feeDefaulters = (int)$pdo->query("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN student_ledger sl ON s.id = sl.student_id
    GROUP BY s.id
    HAVING (SUM(CASE WHEN sl.type = 'debit' THEN sl.amount ELSE 0 END) - SUM(CASE WHEN sl.type IN ('credit', 'waiver') THEN sl.amount ELSE 0 END)) > 0
")->rowCount();

$activeOverrides = (int)$pdo->query("SELECT COUNT(*) FROM hall_ticket_overrides")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalFaculty = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'hod')")->fetchColumn();

// Recent pending leave requests needing principal/faculty endorsement
$pendingLeaves = $pdo->query("
    SELECT lr.*, s.roll_no, u.name AS student_name, d.dept_code
    FROM leave_requests lr
    JOIN students s ON lr.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    WHERE lr.status = 'pending'
    ORDER BY lr.created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIMT ERP &bull; Principal Executive Suite</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --gimt-primary: #123b70;
            --gimt-primary-dark: #0a1f3d;
            --page-bg: #f5f7fb;
            --text-primary: #172b4d;
        }
        body { margin: 0; min-height: 100vh; background: var(--page-bg); color: var(--text-primary); font-family: "Segoe UI", Tahoma, sans-serif; }
        .dashboard-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; min-width: 0; padding: 28px; }
        .dashboard-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 4px 16px rgba(15,23,42,0.04); height: 100%; }
        .metric-value { font-size: 1.8rem; font-weight: 750; color: #123b70; line-height: 1.1; }
        .quick-tile { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-decoration: none; color: inherit; display: block; transition: all 0.2s; }
        .quick-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.06); border-color: var(--gimt-primary); color: var(--gimt-primary); }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include('./partials/principal_sidebar.php') ?>

        <main class="dashboard-main">
            <!-- Header -->
            <header class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-4 mb-4 rounded-4 border bg-white shadow-sm">
                <div>
                    <span class="badge text-bg-warning px-3 py-1 rounded-pill mb-1">OFFICE OF THE PRINCIPAL</span>
                    <h1 class="h3 fw-bold mb-1">Academic Governance &amp; Executive Oversight</h1>
                    <p class="text-secondary mb-0">Welcome, <strong><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></strong> &bull; <?= date('l, d F Y') ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="hall_tickets.php" class="btn btn-warning fw-bold">
                        <i class="bi bi-shield-check me-1"></i> Principal Override Desk
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger" onclick="return confirm('Log out?');">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Metrics Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Total Enrolled Students</div>
                        <div class="metric-value text-primary"><?= $totalStudents ?></div>
                        <div class="small text-muted mt-2">Active college enrolments</div>
                        <a href="student_ledger.php" class="small text-decoration-none mt-1 d-block">Student Registry &rarr;</a>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Admissions Under Review</div>
                        <div class="metric-value text-warning"><?= $pendingAdmissions ?></div>
                        <div class="small text-muted mt-2">Pending principal endorsement</div>
                        <a href="admissions_manage.php" class="small text-decoration-none mt-1 d-block">Review Pipeline &rarr;</a>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Attendance &lt; 75% Defaulters</div>
                        <div class="metric-value text-danger"><?= $attDefaulters ?></div>
                        <div class="small text-muted mt-2">Exam admit card blocked</div>
                        <a href="attendance_analytics.php?defaulters_only=1" class="small text-decoration-none mt-1 d-block">Defaulters List &rarr;</a>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="dashboard-card">
                        <div class="text-secondary small fw-bold text-uppercase">Active Condonation Overrides</div>
                        <div class="metric-value text-success"><?= $activeOverrides ?></div>
                        <div class="small text-muted mt-2">Authorized special waivers</div>
                        <a href="hall_tickets.php" class="small text-decoration-none mt-1 d-block">Manage Overrides &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Principal Operations Modules -->
            <h2 class="h5 fw-bold mb-3">Academic Leadership Workbenches</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-4 col-sm-6">
                    <a href="admissions_manage.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary-subtle text-primary p-3 rounded-circle fs-4"><i class="bi bi-person-check-fill"></i></div>
                            <div>
                                <div class="fw-bold">Admissions Approval</div>
                                <div class="small text-muted">Document audit &amp; automated roll generation</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-sm-6">
                    <a href="hall_tickets.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning-subtle text-warning p-3 rounded-circle fs-4"><i class="bi bi-person-badge-fill"></i></div>
                            <div>
                                <div class="fw-bold">Exam Hall Tickets &amp; Override</div>
                                <div class="small text-muted">Attendance condonation &amp; hall ticket authorization</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-sm-6">
                    <a href="exam_tabulation.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success-subtle text-success p-3 rounded-circle fs-4"><i class="bi bi-award-fill"></i></div>
                            <div>
                                <div class="fw-bold">Consolidated Tabulation</div>
                                <div class="small text-muted">Regulatory continuous internal evaluations</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-sm-6">
                    <a href="faculty_workload.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info-subtle text-info p-3 rounded-circle fs-4"><i class="bi bi-person-workspace"></i></div>
                            <div>
                                <div class="fw-bold">Faculty Workload Allocation</div>
                                <div class="small text-muted">Teaching assignments &amp; credit distribution</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-sm-6">
                    <a href="courses_master.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-secondary-subtle text-secondary p-3 rounded-circle fs-4"><i class="bi bi-journal-bookmark-fill"></i></div>
                            <div>
                                <div class="fw-bold">Course &amp; Credit Master</div>
                                <div class="small text-muted">Department curriculum &amp; credit catalogs</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-sm-6">
                    <a href="fee_defaulters.php" class="quick-tile">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-danger-subtle text-danger p-3 rounded-circle fs-4"><i class="bi bi-exclamation-octagon-fill"></i></div>
                            <div>
                                <div class="fw-bold">Fee Defaulter Oversight</div>
                                <div class="small text-muted">Review institutional outstanding dues</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Pending Condonations & Leaves -->
            <div class="dashboard-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 fw-bold mb-0">Pending Student Absence &amp; Condonation Requests</h2>
                    <a href="leave_requests.php" class="btn btn-sm btn-outline-primary">Review All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Roll Number &bull; Student Name</th>
                                <th>Discipline</th>
                                <th>Classification</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingLeaves)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No pending absence or condonation requests awaiting endorsement.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingLeaves as $pl): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark-subtle text-dark border font-monospace"><?= htmlspecialchars($pl['roll_no']) ?></span>
                                            <div class="fw-bold mt-1"><?= htmlspecialchars($pl['student_name']) ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($pl['dept_code']) ?></span></td>
                                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($pl['leave_type']) ?></span></td>
                                        <td class="small">
                                            <?= date('d M', strtotime($pl['start_date'])) ?> to <?= date('d M Y', strtotime($pl['end_date'])) ?>
                                        </td>
                                        <td class="small text-truncate" style="max-width: 250px;"><?= htmlspecialchars($pl['reason']) ?></td>
                                        <td class="text-end">
                                            <a href="leave_requests.php" class="btn btn-sm btn-primary">Endorse</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
