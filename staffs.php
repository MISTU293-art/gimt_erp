<?php
session_start();

require_once __DIR__ . '/config/db.config.php';
include('./middleware/isLoggedIn.php');
include('./middleware/charimanPrincipalHodHrAccount.php');
/*
|--------------------------------------------------------------------------
| Request parameters
|--------------------------------------------------------------------------
*/

$allowedPageSizes = [10, 25, 50];

$perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
if (
    $perPage === false || $perPage === null ||
    !in_array($perPage, $allowedPageSizes, true)
) {
    $perPage = 10;
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($page === false || $page === null || $page < 1) {
    $page = 1;
}

// Search keyword (trimmed, limited length)
$search = trim((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 100);

/*
|--------------------------------------------------------------------------
| Build the WHERE clause (shared by listing, count and export)
|--------------------------------------------------------------------------
*/

$where       = '';
$whereTypes  = '';
$whereParams = [];

if ($search !== '') {
    // Escape LIKE wildcards typed by the user so they are treated literally
    $like = '%' . addcslashes($search, '%_\\') . '%';

    $where = " WHERE (name LIKE ? OR email LIKE ? OR role LIKE ? OR mobile LIKE ?)";
    $whereTypes  = 'ssss';
    $whereParams = [$like, $like, $like, $like];
}

/*
|--------------------------------------------------------------------------
| CSV export (all records matching the current search)
|--------------------------------------------------------------------------
*/

if (($_GET['export'] ?? '') === 'csv') {

    $exportStmt = $conn->prepare(
        "SELECT id, name, email, role, mobile
         FROM users" . $where . "
         ORDER BY id DESC"
    );

    if (!$exportStmt) {
        die("Unable to prepare the export query.");
    }

    if ($whereTypes !== '') {
        $exportStmt->bind_param($whereTypes, ...$whereParams);
    }

    $exportStmt->execute();
    $exportResult = $exportStmt->get_result();

    // Prevents spreadsheet formula injection (=, +, -, @ at start of a cell)
    $safeCell = static function ($value): string {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    };

    $filename = 'staff_list_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM so Excel reads special characters correctly
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['ID', 'Name', 'Email Address', 'Role', 'Mobile Number']);

    while ($row = $exportResult->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $safeCell($row['name']),
            $safeCell($row['email']),
            $safeCell(ucfirst((string) $row['role'])),
            $safeCell($row['mobile']),
        ]);
    }

    fclose($out);
    $exportStmt->close();
    $conn->close();
    exit;
}

/*
|--------------------------------------------------------------------------
| Count matching staff
|--------------------------------------------------------------------------
*/

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM users" . $where);

if (!$countStmt) {
    die("Unable to count staff records.");
}

if ($whereTypes !== '') {
    $countStmt->bind_param($whereTypes, ...$whereParams);
}

$countStmt->execute();
$totalStaff = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int) ceil($totalStaff / $perPage));

// Keep the current page within the available range.
$page = min($page, $totalPages);

$offset      = ($page - 1) * $perPage;
$firstRecord = $totalStaff > 0 ? $offset + 1 : 0;
$lastRecord  = min($offset + $perPage, $totalStaff);

/*
|--------------------------------------------------------------------------
| Fetch staff for the current page
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, name, email, role, mobile
     FROM users" . $where . "
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);

if (!$stmt) {
    die("Unable to prepare the staff query.");
}

$stmt->bind_param($whereTypes . 'ii', ...array_merge($whereParams, [$perPage, $offset]));
$stmt->execute();

$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| URL helper (keeps search + page size across links)
|--------------------------------------------------------------------------
*/

function staffUrl(array $params = []): string
{
    global $search, $perPage;

    $query = array_merge([
        'q'        => $search,
        'per_page' => $perPage,
    ], $params);

    // Drop empty values to keep URLs clean
    $query = array_filter($query, static fn($v) => $v !== '' && $v !== null);

    return '?' . http_build_query($query);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff List | GIMT ERP</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f5f7fb;
            color: #172b4d;
            font-family: "Segoe UI", Tahoma, sans-serif;
        }

        .page-layout {
            display: flex;
            align-items: flex-start;
            min-height: 100vh;
            width: 100%;
        }

        .dashboard-main {
            flex: 1;
            min-width: 0;
            padding: 28px;
        }

        .page-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .page-description {
            color: #64748b;
            margin-bottom: 0;
            font-size: .9rem;
        }

        .staff-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 3px 12px rgb(15 23 42 / 3%);
        }

        .staff-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 18px;
        }

        /* Search + export row */
        .staff-tools {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .search-form {
            flex: 1 1 320px;
            max-width: 460px;
        }

        .search-box {
            position: relative;
        }

        .search-box .search-icon {
            position: absolute;
            top: 50%;
            left: 13px;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .search-box input {
            padding-left: 38px;
            padding-right: 38px;
            border-color: #e2e8f0;
            background: #f8fafc;
        }

        .search-box input:focus {
            background: #fff;
            border-color: #2563eb;
            box-shadow: 0 0 0 .2rem rgb(37 99 235 / 12%);
        }

        .search-clear {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            color: #64748b;
            border-radius: 50%;
            text-decoration: none;
        }

        .search-clear:hover {
            color: #dc2626;
            background: #fef2f2;
        }

        .btn-gimt {
            color: #fff;
            background: #123b70;
            border-color: #123b70;
        }

        .btn-gimt:hover {
            color: #fff;
            background: #09264b;
            border-color: #09264b;
        }

        .btn-export {
            color: #123b70;
            background: #fff;
            border: 1px solid #cbd5e1;
            font-weight: 600;
        }

        .btn-export:hover {
            color: #123b70;
            background: #eff6ff;
            border-color: #2563eb;
        }

        .staff-table {
            margin-bottom: 0;
            white-space: nowrap;
        }

        .staff-table thead th {
            padding: 14px 16px;
            color: #475569;
            background: #f1f5f9;
            border-bottom: 1px solid #dbe3ed;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .staff-table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: .9rem;
        }

        .staff-table tbody tr:hover td {
            background: #f8fbff;
        }

        .staff-id {
            color: #64748b;
            font-weight: 600;
        }

        .staff-name {
            color: #172b4d;
            font-weight: 650;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            color: #1749a8;
            background: #e8f0ff;
            font-size: .77rem;
            font-weight: 650;
        }

        .staff-count {
            color: #64748b;
            font-size: .85rem;
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            min-width: 38px;
            text-align: center;
            color: #123b70;
            border-color: #e2e8f0;
        }

        .pagination .page-item.active .page-link {
            color: #fff;
            background: #123b70;
            border-color: #123b70;
        }

        .pagination .page-item.disabled .page-link {
            color: #94a3b8;
        }

        .page-size-select {
            width: 85px;
        }

        .empty-state {
            padding: 48px 16px !important;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 767.98px) {
            .dashboard-main {
                padding: 76px 14px 24px;
            }

            .staff-card {
                padding: 16px;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .search-form {
                max-width: 100%;
            }

            .pagination .page-link {
                min-width: 33px;
                padding: 7px 9px;
                font-size: .85rem;
            }
        }

        /* Print: hide sidebar, buttons and pagination */
        @media print {

            .gimt-sidebar,
            .sidebar-toggle,
            .no-print,
            .pagination {
                display: none !important;
            }

            body {
                background: #fff;
            }

            .page-layout {
                display: block;
            }

            .dashboard-main {
                padding: 0;
            }

            .staff-card {
                border: 0;
                box-shadow: none;
                padding: 0;
            }

            .staff-table {
                white-space: normal;
            }
        }
    </style>
</head>

<body>

    <div class="page-layout">

        <?php include __DIR__ . '/partials/superAdmin_sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0">

                <!-- Page heading -->
                <div class="d-flex justify-content-between align-items-center
                        flex-wrap gap-3 mb-4">

                    <div>
                        <h1 class="page-title">Staff List</h1>
                        <p class="page-description">
                            View and manage registered faculty and staff.
                        </p>
                    </div>

                    <a href="/public/staff_register.php"
                        class="btn btn-primary no-print">
                        <i class="bi bi-person-plus me-1"></i>
                        Add Staff
                    </a>

                </div>

                <!-- Staff table card -->
                <section class="staff-card">

                    <div class="staff-toolbar">

                        <div>
                            <h2 class="h5 fw-bold mb-1">All Staff Members</h2>

                            <p class="staff-count mb-0">
                                Showing
                                <strong><?= $firstRecord ?></strong>–<strong><?= $lastRecord ?></strong>
                                of <strong><?= $totalStaff ?></strong>
                                <?= $search !== '' ? 'matching ' : '' ?>records
                                <?php if ($search !== ''): ?>
                                    for “<strong><?= e($search) ?></strong>”
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Rows per page (keeps the search keyword) -->
                        <form method="GET" action=""
                            class="d-flex align-items-center gap-2 no-print">

                            <?php if ($search !== ''): ?>
                                <input type="hidden" name="q" value="<?= e($search) ?>">
                            <?php endif; ?>

                            <label for="per_page"
                                class="small text-secondary text-nowrap">
                                Rows per page
                            </label>

                            <select name="per_page"
                                id="per_page"
                                class="form-select form-select-sm page-size-select"
                                onchange="this.form.submit()">

                                <?php foreach ($allowedPageSizes as $size): ?>
                                    <option value="<?= $size ?>"
                                        <?= $perPage === $size ? 'selected' : '' ?>>
                                        <?= $size ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </form>

                    </div>

                    <!-- Search + export -->
                    <div class="staff-tools no-print">

                        <form method="GET" action="" class="search-form" role="search">
                            <input type="hidden" name="per_page" value="<?= $perPage ?>">

                            <div class="d-flex gap-2">
                                <div class="search-box flex-grow-1">
                                    <i class="bi bi-search search-icon"></i>

                                    <input type="search"
                                        name="q"
                                        class="form-control"
                                        value="<?= e($search) ?>"
                                        maxlength="100"
                                        placeholder="Search name, email, role or mobile…"
                                        aria-label="Search staff">

                                    <?php if ($search !== ''): ?>
                                        <a href="<?= e(staffUrl(['q' => '', 'page' => 1])) ?>"
                                            class="search-clear"
                                            title="Clear search"
                                            aria-label="Clear search">
                                            <i class="bi bi-x-lg small"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-gimt">
                                    Search
                                </button>
                            </div>
                        </form>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= e(staffUrl(['export' => 'csv'])) ?>"
                                class="btn btn-export btn-sm d-flex align-items-center gap-1
                                  <?= $totalStaff === 0 ? 'disabled' : '' ?>"
                                title="Download all <?= $search !== '' ? 'matching ' : '' ?>records as CSV (opens in Excel)">
                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                Export CSV
                            </a>

                            <button type="button"
                                class="btn btn-export btn-sm d-flex align-items-center gap-1"
                                onclick="window.print()"
                                title="Print or save this page as PDF">
                                <i class="bi bi-printer"></i>
                                Print / PDF
                            </button>
                        </div>

                    </div>

                    <!-- Responsive table -->
                    <div class="table-responsive">
                        <table class="table table-hover staff-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email Address</th>
                                    <th>Role</th>
                                    <th>Mobile Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($result->num_rows > 0): ?>

                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="staff-id">
                                                #<?= (int) $row['id'] ?>
                                            </td>

                                            <td class="staff-name">
                                                <?= e($row['name']) ?>
                                            </td>

                                            <td>
                                                <?= e($row['email']) ?>
                                            </td>

                                            <td>
                                                <span class="role-badge">
                                                    <?= e(ucfirst((string) $row['role'])) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?= e($row['mobile']) ?>
                                            </td>
                                            <td>
                                                <a href="/public/staff_profile.php?id=<?= urlencode($row['id']) ?>"
                                                    class="btn btn-sm btn-primary">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z" />
                                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0" />
                                                    </svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <?php if ($search !== ''): ?>
                                                <i class="bi bi-search fs-1 d-block mb-3"></i>
                                                <h3 class="h6 fw-bold">No matching staff found</h3>
                                                <p class="small mb-3">
                                                    Nothing matched “<?= e($search) ?>”.
                                                    Try a different keyword.
                                                </p>
                                                <a href="<?= e(staffUrl(['q' => '', 'page' => 1])) ?>"
                                                    class="btn btn-export btn-sm no-print">
                                                    Clear search
                                                </a>
                                            <?php else: ?>
                                                <i class="bi bi-people fs-1 d-block mb-3"></i>
                                                <h3 class="h6 fw-bold">No staff records found</h3>
                                                <p class="small mb-0">
                                                    Register a staff member to display
                                                    their details here.
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination footer -->
                    <div class="d-flex justify-content-between align-items-center
                            flex-wrap gap-3 mt-4">

                        <p class="small text-secondary mb-0">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </p>

                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Staff table pagination" class="no-print">
                                <ul class="pagination pagination-sm flex-wrap">

                                    <!-- Previous -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <?php if ($page > 1): ?>
                                            <a class="page-link"
                                                href="<?= e(staffUrl(['page' => $page - 1])) ?>"
                                                aria-label="Previous page">
                                                <i class="bi bi-chevron-left"></i>
                                                <span class="d-none d-sm-inline">Previous</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="page-link">
                                                <i class="bi bi-chevron-left"></i>
                                                <span class="d-none d-sm-inline">Previous</span>
                                            </span>
                                        <?php endif; ?>
                                    </li>

                                    <!-- Numbered pages -->
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage   = min($totalPages, $page + 2);
                                    ?>

                                    <?php if ($startPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="<?= e(staffUrl(['page' => 1])) ?>">1</a>
                                        </li>

                                        <?php if ($startPage > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">…</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="<?= e(staffUrl(['page' => $i])) ?>"
                                                <?= $i === $page ? 'aria-current="page"' : '' ?>>
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">…</span>
                                            </li>
                                        <?php endif; ?>

                                        <li class="page-item">
                                            <a class="page-link"
                                                href="<?= e(staffUrl(['page' => $totalPages])) ?>">
                                                <?= $totalPages ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Next -->
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <?php if ($page < $totalPages): ?>
                                            <a class="page-link"
                                                href="<?= e(staffUrl(['page' => $page + 1])) ?>"
                                                aria-label="Next page">
                                                <span class="d-none d-sm-inline">Next</span>
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="page-link">
                                                <span class="d-none d-sm-inline">Next</span>
                                                <i class="bi bi-chevron-right"></i>
                                            </span>
                                        <?php endif; ?>
                                    </li>

                                </ul>
                            </nav>
                        <?php endif; ?>

                    </div>

                </section>

            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>

<?php
$stmt->close();
$conn->close();
?>