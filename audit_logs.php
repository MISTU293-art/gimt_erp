<?php
// audit_logs.php - Immutable System Audit Trail Viewer
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

$actionFilter = trim($_GET['action_filter'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT al.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($actionFilter)) {
    $query .= " AND al.action = ?";
    $params[] = $actionFilter;
}
if (!empty($search)) {
    $query .= " AND (al.details LIKE ? OR al.action LIKE ? OR u.name LIKE ? OR al.ip_address LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY al.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immutable Audit Logs &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
    </style>
</head>
<body>
    <div class="page-layout">
        <?php
        if ($userRole === 'chairman') {
            require __DIR__ . '/partials/superAdmin_sidebar.php';
        } else {
            require __DIR__ . '/partials/principal_sidebar.php';
        }
        ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Immutable Audit &amp; Accountability Logs</h1>
                        <p class="text-secondary mb-0">Cryptographic audit trail of financial collections, status transitions &amp; grade locks</p>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="card p-3 mb-4 border-0 shadow-sm">
                    <form method="get" action="audit_logs.php" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control" placeholder="Search actor, action, IP or details..." value="<?= e($search) ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="action_filter" class="form-select">
                                <option value="">All Audited Actions</option>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="audit_logs.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp &bull; IP</th>
                                    <th>Actor / User</th>
                                    <th>Audited Action</th>
                                    <th>Entity</th>
                                    <th>Transactional Audit Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No audit logs recorded matching criteria.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $l): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= date('d M Y, h:i:s A', strtotime($l['created_at'])) ?></div>
                                                <div class="small text-muted font-monospace"><i class="bi bi-geo-alt"></i> <?= e($l['ip_address']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= e($l['user_name'] ?: 'System') ?></div>
                                                <div class="small text-muted"><?= e($l['user_role'] ?: 'System Process') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">
                                                    <?= e($l['action']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary font-monospace"><?= e($l['entity_type']) ?> #<?= (int)$l['entity_id'] ?></span>
                                            </td>
                                            <td>
                                                <div class="small text-dark font-monospace"><?= e($l['details']) ?></div>
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
