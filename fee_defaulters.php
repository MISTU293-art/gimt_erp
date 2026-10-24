<?php
// fee_defaulters.php - Automated Student Fee Defaulter Tracking Engine
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'accounts', 'hod']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

$deptFilter = (int)($_GET['dept_id'] ?? 0);
$semFilter = (int)($_GET['semester'] ?? 0);
$minDue = (float)($_GET['min_due'] ?? 1000.00);

$query = "
    SELECT s.id AS student_id, s.roll_no, s.batch_year, s.current_semester, s.phone, s.guardian_phone,
           u.name AS student_name, u.email AS student_email,
           d.dept_code, d.dept_name,
           COALESCE(SUM(CASE WHEN sl.type = 'debit' THEN sl.amount ELSE 0 END), 0) AS total_debits,
           COALESCE(SUM(CASE WHEN sl.type IN ('credit', 'waiver') THEN sl.amount ELSE 0 END), 0) AS total_credits,
           (COALESCE(SUM(CASE WHEN sl.type = 'debit' THEN sl.amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN sl.type IN ('credit', 'waiver') THEN sl.amount ELSE 0 END), 0)) AS balance_due,
           MAX(CASE WHEN sl.type = 'credit' THEN sl.transaction_date ELSE NULL END) AS last_payment_date
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    LEFT JOIN student_ledger sl ON s.id = sl.student_id
    WHERE s.admission_status = 'admitted'
";
$params = [];

if ($deptFilter > 0) {
    $query .= " AND s.department_id = ?";
    $params[] = $deptFilter;
}
if ($semFilter > 0) {
    $query .= " AND s.current_semester = ?";
    $params[] = $semFilter;
}

$query .= " GROUP BY s.id HAVING balance_due >= ? ORDER BY balance_due DESC";
$params[] = $minDue;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$defaulters = $stmt->fetchAll();

// Aggregates
$totalOutstanding = 0.00;
foreach ($defaulters as $def) {
    $totalOutstanding += (float)$def['balance_due'];
}

$departments = $pdo->query("SELECT id, dept_code, dept_name FROM departments ORDER BY dept_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Defaulter Tracking &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; }
        .currency { font-family: monospace; font-weight: 600; }
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
                        <h1 class="h3 fw-bold mb-1">Overdue Fee Defaulter Tracker</h1>
                        <p class="text-secondary mb-0">Automated extraction of institutional accounts with unpaid debit balances</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print Roster</button>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- Metrics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card">
                            <div class="text-secondary small fw-bold text-uppercase">Total Defaulter Accounts</div>
                            <div class="h3 fw-bold text-danger mb-0"><?= count($defaulters) ?></div>
                        </div>
                    </div>
                    <div class="col-md-5 col-sm-6">
                        <div class="stat-card">
                            <div class="text-secondary small fw-bold text-uppercase">Total Overdue Dues Balance</div>
                            <div class="h3 fw-bold text-danger mb-0 currency"><?= format_inr($totalOutstanding) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="stat-card">
                            <div class="text-secondary small fw-bold text-uppercase">Default Threshold</div>
                            <div class="h3 fw-bold text-primary mb-0 currency">&ge; <?= format_inr($minDue) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-3 mb-4 border-0 shadow-sm">
                    <form method="get" action="fee_defaulters.php" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <select name="dept_id" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['dept_code']) ?> &mdash; <?= e($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="semester" class="form-select">
                                <option value="">All Semesters</option>
                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                    <option value="<?= $s ?>" <?= $semFilter === $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text">Min Due ₹</span>
                                <input type="number" step="100" name="min_due" class="form-control" value="<?= $minDue ?>">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="fee_defaulters.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Defaulters Table -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Roll Number &bull; Student</th>
                                    <th>Department &bull; Sem</th>
                                    <th>Guardian Phone</th>
                                    <th class="text-end">Total Invoiced</th>
                                    <th class="text-end">Paid So Far</th>
                                    <th class="text-end">Outstanding Due</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($defaulters)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-success fw-bold">
                                            <i class="bi bi-check-circle-fill me-1"></i> No fee defaulters found matching criteria! All accounts within policy limits.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($defaulters as $def): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace fs-6"><?= e($def['roll_no']) ?></span>
                                                <div class="fw-bold mt-1"><?= e($def['student_name']) ?></div>
                                                <div class="small text-muted"><?= e($def['student_email']) ?> &bull; <?= e($def['phone']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= e($def['dept_code']) ?></span>
                                                <div class="small text-muted">Semester <?= (int)$def['current_semester'] ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-danger"><i class="bi bi-telephone"></i> <?= e($def['guardian_phone'] ?: 'N/A') ?></div>
                                                <div class="small text-muted">Last Payment: <?= $def['last_payment_date'] ? date('d M Y', strtotime($def['last_payment_date'])) : 'Never' ?></div>
                                            </td>
                                            <td class="text-end currency text-muted"><?= format_inr((float)$def['total_debits']) ?></td>
                                            <td class="text-end currency text-success"><?= format_inr((float)$def['total_credits']) ?></td>
                                            <td class="text-end currency fw-bold text-danger fs-6"><?= format_inr((float)$def['balance_due']) ?></td>
                                            <td class="text-end">
                                                <a href="fee_collect.php?student_id=<?= (int)$def['student_id'] ?>" class="btn btn-sm btn-success fw-semibold">
                                                    <i class="bi bi-cash"></i> Collect
                                                </a>
                                                <a href="student_ledger.php?student_id=<?= (int)$def['student_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-wallet2"></i> Ledger
                                                </a>
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
