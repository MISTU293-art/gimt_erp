<?php
// admissions_manage.php - Institutional Admissions & Onboarding Management Pipeline
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'accounts', 'hr', 'hod']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

// Filter handling
$statusFilter = trim($_GET['status'] ?? '');
$deptFilter = (int) ($_GET['dept_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT a.*, d.dept_name, d.dept_code,
           s.roll_no AS enrolled_roll_no
    FROM admissions a
    JOIN departments d ON a.department_id = d.id
    LEFT JOIN students s ON a.enrolled_student_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}
if (!empty($deptFilter)) {
    $query .= " AND a.department_id = ?";
    $params[] = $deptFilter;
}
if (!empty($search)) {
    $query .= " AND (a.full_name LIKE ? OR a.application_no LIKE ? OR a.phone LIKE ? OR a.email LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, dept_code, dept_name FROM departments ORDER BY dept_name")->fetchAll();

// Count pipeline metrics
$metrics = [
    'total' => $pdo->query("SELECT COUNT(*) FROM admissions")->fetchColumn(),
    'submitted' => $pdo->query("SELECT COUNT(*) FROM admissions WHERE status = 'submitted'")->fetchColumn(),
    'verified' => $pdo->query("SELECT COUNT(*) FROM admissions WHERE status = 'document_verified'")->fetchColumn(),
    'cleared' => $pdo->query("SELECT COUNT(*) FROM admissions WHERE status = 'fee_cleared'")->fetchColumn(),
    'enrolled' => $pdo->query("SELECT COUNT(*) FROM admissions WHERE status = 'enrolled'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admissions Management &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; }
        .stat-number { font-size: 1.8rem; font-weight: 750; color: #123b70; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .badge-stage { text-transform: capitalize; font-weight: 600; font-size: 0.78rem; padding: 6px 10px; }
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
                        <h1 class="h3 fw-bold mb-1">Student Admissions &amp; Onboarding</h1>
                        <p class="text-secondary mb-0">Multi-stage applicant workflow, document audit &amp; automated enrollment</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="admission_apply.php" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Public Applicant Wizard
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- Pipeline Stage Metrics -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-2">
                        <div class="stat-card">
                            <div class="text-secondary small">Total Applications</div>
                            <div class="stat-number"><?= (int)$metrics['total'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="stat-card">
                            <div class="text-secondary small">New Submitted</div>
                            <div class="stat-number text-primary"><?= (int)$metrics['submitted'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="stat-card">
                            <div class="text-secondary small">Doc Verified</div>
                            <div class="stat-number text-info"><?= (int)$metrics['verified'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="text-secondary small">Fee Cleared</div>
                            <div class="stat-number text-warning"><?= (int)$metrics['cleared'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="text-secondary small">Enrolled Students</div>
                            <div class="stat-number text-success"><?= (int)$metrics['enrolled'] ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-3 mb-4 border-0 shadow-sm">
                    <form method="get" action="admissions_manage.php" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, app no, phone..." value="<?= e($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Workflow Stages</option>
                                <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="under_review" <?= $statusFilter === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                                <option value="document_verified" <?= $statusFilter === 'document_verified' ? 'selected' : '' ?>>Document Verified</option>
                                <option value="fee_cleared" <?= $statusFilter === 'fee_cleared' ? 'selected' : '' ?>>Fee Cleared</option>
                                <option value="enrolled" <?= $statusFilter === 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="dept_id" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['dept_code']) ?> - <?= e($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="admissions_manage.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Table Card -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>App No &bull; Date</th>
                                    <th>Applicant Details</th>
                                    <th>Department</th>
                                    <th>Prior Academics</th>
                                    <th>Fee Status</th>
                                    <th>Workflow Stage</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applications)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No admission applications found matching your criteria.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-primary"><?= e($app['application_no']) ?></div>
                                                <div class="small text-muted"><?= date('d M Y, h:i A', strtotime($app['created_at'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= e($app['full_name']) ?></div>
                                                <div class="small text-muted"><?= e($app['phone']) ?> &bull; <?= e($app['email']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= e($app['dept_code']) ?></span>
                                                <div class="small text-muted text-uppercase"><?= e($app['quota']) ?> Quota</div>
                                            </td>
                                            <td>
                                                <div class="small">10th: <strong><?= e($app['tenth_percentage']) ?>%</strong></div>
                                                <div class="small">12th: <strong><?= e($app['twelfth_percentage']) ?>%</strong></div>
                                            </td>
                                            <td>
                                                <?php if ($app['payment_status'] === 'success'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i> Paid ₹1,000</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= e($app['payment_status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'submitted' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                    'under_review' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                                    'document_verified' => 'bg-info-subtle text-info border border-info-subtle',
                                                    'fee_cleared' => 'bg-warning-subtle text-dark border border-warning-subtle',
                                                    'enrolled' => 'bg-success text-white',
                                                    'rejected' => 'bg-danger text-white'
                                                ];
                                                $badgeClass = $badges[$app['status']] ?? 'bg-light text-dark';
                                                ?>
                                                <span class="badge badge-stage <?= $badgeClass ?>">
                                                    <?= str_replace('_', ' ', $app['status']) ?>
                                                </span>
                                                <?php if (!empty($app['enrolled_roll_no'])): ?>
                                                    <div class="small text-success fw-bold mt-1"><i class="bi bi-person-badge"></i> <?= e($app['enrolled_roll_no']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="admission_view.php?id=<?= (int)$app['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i> Review &bull; Enroll
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
