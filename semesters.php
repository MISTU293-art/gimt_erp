<?php
require_once __DIR__ . '/middleware/isLoggedIn.php';
require_once __DIR__ . '/middleware/roleAccess.php';
require_user_role(['chairman', 'principal']);
require_once __DIR__ . '/config/db.config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function semester_escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function semester_date_label(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp === false ? $date : date('M j, Y', $timestamp);
}

$error = '';
$flash = $_SESSION['semester_flash'] ?? null;
unset($_SESSION['semester_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_batch') {
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $batchName = trim((string) ($_POST['batch_name'] ?? ''));
        $startYear = filter_var($_POST['start_year'] ?? null, FILTER_VALIDATE_INT);
        $endYear = filter_var($_POST['end_year'] ?? null, FILTER_VALIDATE_INT);

        if ($departmentId === false || $startYear === false || $endYear === false || $batchName === '') {
            $error = 'Complete all batch fields with valid values.';
        } elseif (strlen($batchName) > 100) {
            $error = 'Batch name must be 100 characters or fewer.';
        } elseif ($startYear < 2000 || $startYear > 2100 || $endYear <= $startYear || $endYear > 2101) {
            $error = 'Enter a valid academic year range between 2000 and 2101.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO academic_batches (department_id, batch_name, start_year, end_year) VALUES (?, ?, ?, ?)'
            );
            if (!$stmt) {
                $error = 'Unable to prepare the batch. Confirm the academic tables have been installed.';
            } else {
                $stmt->bind_param('isii', $departmentId, $batchName, $startYear, $endYear);
                $saved = $stmt->execute();
                $stmt->close();

                if ($saved) {
                    $_SESSION['semester_flash'] = [
                        'type' => 'success',
                        'message' => 'Batch created successfully.',
                    ];
                    header('Location: semesters.php');
                    exit;
                }
                $error = 'Unable to create this batch. Check that the department exists and the batch is not duplicated.';
            }
        }
    } elseif ($action === 'create_semester') {
        $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $semesterNumber = filter_var($_POST['semester_number'] ?? null, FILTER_VALIDATE_INT);
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $validStartDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $validEndDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

        if (
            $batchId === false ||
            $semesterNumber === false ||
            $semesterNumber < 1 ||
            $semesterNumber > 12 ||
            !$validStartDate ||
            !$validEndDate ||
            $validStartDate->format('Y-m-d') !== $startDate ||
            $validEndDate->format('Y-m-d') !== $endDate
        ) {
            $error = 'Enter a valid batch, semester number, and semester dates.';
        } elseif ($endDate < $startDate) {
            $error = 'The semester end date must be on or after its start date.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO academic_semesters (batch_id, semester_number, start_date, end_date) VALUES (?, ?, ?, ?)'
            );
            if (!$stmt) {
                $error = 'Unable to prepare the semester. Confirm the academic tables have been installed.';
            } else {
                $stmt->bind_param('iiss', $batchId, $semesterNumber, $startDate, $endDate);
                $saved = $stmt->execute();
                $stmt->close();

                if ($saved) {
                    $_SESSION['semester_flash'] = [
                        'type' => 'success',
                        'message' => 'Semester added successfully.',
                    ];
                    header('Location: semesters.php');
                    exit;
                }
                $error = 'Unable to add this semester. Check that the batch exists and this semester number is not already assigned.';
            }
        }
    } elseif ($action === 'delete_semester' || $action === 'delete_batch') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            $error = 'Select a valid record to delete.';
        } else {
            $table = $action === 'delete_batch' ? 'academic_batches' : 'academic_semesters';
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $error = 'Unable to prepare the selected record for deletion.';
            } else {
                $stmt->bind_param('i', $id);
                $deleted = $stmt->execute();
                $deletedCount = $stmt->affected_rows;
                $stmt->close();

                if ($deleted && $deletedCount === 1) {
                    $_SESSION['semester_flash'] = [
                        'type' => 'success',
                        'message' => $action === 'delete_batch'
                            ? 'Batch and its semesters deleted successfully.'
                            : 'Semester deleted successfully.',
                    ];
                    header('Location: semesters.php');
                    exit;
                }
                $error = $deleted
                    ? 'The selected record could not be found.'
                    : 'Unable to delete the selected record.';
            }
        }
    } else {
        $error = 'Invalid academic record action.';
    }
}

$departmentsResult = $conn->query('SELECT id, dept_id, dept_name FROM department ORDER BY dept_name, id');
if (!$departmentsResult) {
    throw new RuntimeException('Unable to load departments: ' . $conn->error);
}
$departments = $departmentsResult->fetch_all(MYSQLI_ASSOC);
$departmentsResult->free();

$batchesResult = $conn->query(
    'SELECT b.id, b.batch_name, b.start_year, b.end_year, b.created_at, d.dept_id, d.dept_name
     FROM academic_batches b
     INNER JOIN department d ON d.id = b.department_id
     ORDER BY b.start_year DESC, d.dept_name, b.batch_name'
);
if (!$batchesResult) {
    throw new RuntimeException('Unable to load batches. Apply the academic table definitions from db.sql first.');
}
$batches = $batchesResult->fetch_all(MYSQLI_ASSOC);
$batchesResult->free();

$semestersResult = $conn->query(
    'SELECT s.id, s.batch_id, s.semester_number, s.start_date, s.end_date, s.created_at
     FROM academic_semesters s
     ORDER BY s.batch_id, s.semester_number'
);
if (!$semestersResult) {
    throw new RuntimeException('Unable to load semesters. Apply the academic table definitions from db.sql first.');
}
$semesters = [];
while ($semester = $semestersResult->fetch_assoc()) {
    $semesters[$semester['batch_id']][] = $semester;
}
$semestersResult->free();

$today = date('Y-m-d');
$semesterCount = array_sum(array_map('count', $semesters));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semesters &amp; Batches | GIMT ERP</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #f5f7fb;
            color: #172b4d;
            font-family: "Segoe UI", Tahoma, sans-serif;
        }

        .dashboard-main {
            flex: 1 1 auto;
            min-width: 0;
            padding: 28px;
        }

        .academic-card,
        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 16px rgb(15 23 42 / 4%);
        }

        .stat-card {
            padding: 18px;
        }

        .stat-value {
            color: #193458;
            font-size: 1.6rem;
            font-weight: 750;
        }

        .academic-card .form-control,
        .academic-card .form-select {
            min-height: 42px;
        }

        .semester-table th,
        .semester-table td {
            vertical-align: middle;
        }

        @media (max-width: 991.98px) {
            .dashboard-main {
                width: 100%;
                padding: 76px 16px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="page-layout">
        <?php if (($_SESSION['user_role'] ?? '') === 'principal'): ?>
            <?php include __DIR__ . '/partials/principal_sidebar.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/partials/superAdmin_sidebar.php'; ?>
        <?php endif; ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0">
                <header class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Semesters &amp; Batches</h1>
                        <p class="text-secondary mb-0">Create academic batches and manage each batch's semester schedule.</p>
                    </div>
                    <span class="badge rounded-pill text-bg-primary px-3 py-2">
                        <?= count($batches) ?> batches
                    </span>
                </header>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= semester_escape($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                        <?= semester_escape($flash['message'] ?? '') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= semester_escape($error) ?></div>
                <?php endif; ?>

                <section class="row g-3 mb-4" aria-label="Academic totals">
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="stat-card h-100">
                            <div class="text-secondary small">Departments</div>
                            <div class="stat-value"><?= count($departments) ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="stat-card h-100">
                            <div class="text-secondary small">Academic batches</div>
                            <div class="stat-value"><?= count($batches) ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="stat-card h-100">
                            <div class="text-secondary small">Semesters scheduled</div>
                            <div class="stat-value"><?= $semesterCount ?></div>
                        </div>
                    </div>
                </section>

                <section class="academic-card p-4 mb-4" aria-labelledby="batch-form-title">
                    <h2 class="h5 fw-bold mb-1" id="batch-form-title">Create an academic batch</h2>
                    <p class="small text-secondary mb-3">Associate a batch name and year range with a department.</p>
                    <?php if (!$departments): ?>
                        <div class="alert alert-info mb-0">
                            Add a department before creating a batch.
                            <a href="departments.php" class="alert-link">Manage departments</a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="semesters.php" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= semester_escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="create_batch">
                            <div class="col-12 col-md-5">
                                <label for="department_id" class="form-label">Department</label>
                                <select class="form-select" name="department_id" id="department_id" required>
                                    <option value="">Choose a department</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= (int) $department['id'] ?>">
                                            <?= semester_escape($department['dept_name']) ?><?= $department['dept_id'] !== '' ? ' (' . semester_escape($department['dept_id']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="batch_name" class="form-label">Batch name</label>
                                <input class="form-control" type="text" name="batch_name" id="batch_name" maxlength="100" placeholder="e.g. 2025 Intake" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="start_year" class="form-label">Start year</label>
                                <input class="form-control" type="number" name="start_year" id="start_year" min="2000" max="2100" value="<?= (int) date('Y') ?>" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="end_year" class="form-label">End year</label>
                                <input class="form-control" type="number" name="end_year" id="end_year" min="2001" max="2101" value="<?= (int) date('Y') + 4 ?>" required>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create batch
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="academic-card p-4 mb-4" aria-labelledby="semester-form-title">
                    <h2 class="h5 fw-bold mb-1" id="semester-form-title">Add a semester</h2>
                    <p class="small text-secondary mb-3">Set semester dates within an existing academic batch.</p>
                    <?php if (!$batches): ?>
                        <div class="alert alert-info mb-0">Create a batch before adding semesters.</div>
                    <?php else: ?>
                        <form method="post" action="semesters.php" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= semester_escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="create_semester">
                            <div class="col-12 col-md-5">
                                <label for="batch_id" class="form-label">Batch</label>
                                <select class="form-select" name="batch_id" id="batch_id" required>
                                    <option value="">Choose a batch</option>
                                    <?php foreach ($batches as $batch): ?>
                                        <option value="<?= (int) $batch['id'] ?>">
                                            <?= semester_escape($batch['dept_name']) ?> — <?= semester_escape($batch['batch_name']) ?> (<?= (int) $batch['start_year'] ?>–<?= (int) $batch['end_year'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label for="semester_number" class="form-label">Semester</label>
                                <select class="form-select" name="semester_number" id="semester_number" required>
                                    <option value="">Choose</option>
                                    <?php for ($number = 1; $number <= 12; $number++): ?>
                                        <option value="<?= $number ?>">Semester <?= $number ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="start_date" class="form-label">Start date</label>
                                <input class="form-control" type="date" name="start_date" id="start_date" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="end_date" class="form-label">End date</label>
                                <input class="form-control" type="date" name="end_date" id="end_date" required>
                            </div>
                            <div class="col-12 col-md-1 d-flex align-items-end">
                                <button class="btn btn-primary w-100" type="submit" aria-label="Add semester">
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="academic-card overflow-hidden" aria-labelledby="batch-list-title">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-4 pb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1" id="batch-list-title">Batch and semester details</h2>
                            <p class="small text-secondary mb-0">All academic batches, departments and scheduled semesters.</p>
                        </div>
                    </div>
                    <?php if (!$batches): ?>
                        <div class="text-center text-secondary py-5">No batches have been created yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover semester-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Department</th>
                                        <th scope="col">Batch</th>
                                        <th scope="col">Years</th>
                                        <th scope="col">Semester schedule</th>
                                        <th scope="col">Created</th>
                                        <th scope="col" class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold"><?= semester_escape($batch['dept_name']) ?></span>
                                                <?php if ($batch['dept_id'] !== ''): ?>
                                                    <span class="d-block small text-secondary"><?= semester_escape($batch['dept_id']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= semester_escape($batch['batch_name']) ?></td>
                                            <td><?= (int) $batch['start_year'] ?>–<?= (int) $batch['end_year'] ?></td>
                                            <td>
                                                <?php if (empty($semesters[$batch['id']])): ?>
                                                    <span class="text-secondary">No semesters added</span>
                                                <?php else: ?>
                                                    <div class="d-flex flex-column gap-2">
                                                        <?php foreach ($semesters[$batch['id']] as $semester): ?>
                                                            <?php
                                                            $status = $today < $semester['start_date']
                                                                ? 'Upcoming'
                                                                : ($today > $semester['end_date'] ? 'Completed' : 'In progress');
                                                            $badgeClass = $status === 'In progress'
                                                                ? 'text-bg-success'
                                                                : ($status === 'Upcoming' ? 'text-bg-primary' : 'text-bg-secondary');
                                                            ?>
                                                            <div class="d-flex align-items-center flex-wrap gap-2">
                                                                <span class="fw-semibold">Semester <?= (int) $semester['semester_number'] ?></span>
                                                                <span><?= semester_date_label($semester['start_date']) ?> – <?= semester_date_label($semester['end_date']) ?></span>
                                                                <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                                                                <form method="post" action="semesters.php" class="d-inline" onsubmit="return confirm('Delete this semester?');">
                                                                    <input type="hidden" name="csrf_token" value="<?= semester_escape($csrfToken) ?>">
                                                                    <input type="hidden" name="action" value="delete_semester">
                                                                    <input type="hidden" name="id" value="<?= (int) $semester['id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete semester <?= (int) $semester['semester_number'] ?>">
                                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= semester_date_label((string) $batch['created_at']) ?></td>
                                            <td class="text-end">
                                                <form method="post" action="semesters.php" onsubmit="return confirm('Delete this batch and all of its semesters?');">
                                                    <input type="hidden" name="csrf_token" value="<?= semester_escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_batch">
                                                    <input type="hidden" name="id" value="<?= (int) $batch['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete batch
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>