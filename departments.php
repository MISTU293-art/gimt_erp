<?php
require_once __DIR__ . '/middleware/isLoggedIn.php';
require_once __DIR__ . '/middleware/roleAccess.php';
require_user_role(['chairman', 'principal']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/db.config.php';

function department_escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$error = '';
$flash = $_SESSION['department_flash'] ?? null;
unset($_SESSION['department_flash']);
$formValues = [
    'dept_id' => '',
    'dept_name' => '',
    'dept_capacity' => '',
];
$editingDepartment = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            $error = 'Invalid department selected.';
        } else {
            $stmt = $conn->prepare('DELETE FROM department WHERE id = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Unable to process the department deletion.';
            } else {
                $stmt->bind_param('i', $id);
                $deleted = $stmt->execute();
                $deletedCount = $stmt->affected_rows;
                $stmt->close();

                if ($deleted && $deletedCount === 1) {
                    $_SESSION['department_flash'] = [
                        'type' => 'success',
                        'message' => 'Department deleted successfully.',
                    ];
                    header('Location: departments.php');
                    exit;
                }

                $error = $deleted
                    ? 'The department could not be found.'
                    : 'Unable to delete the department. Please try again.';
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        $formValues = [
            'dept_id' => trim((string) ($_POST['dept_id'] ?? '')),
            'dept_name' => trim((string) ($_POST['dept_name'] ?? '')),
            'dept_capacity' => trim((string) ($_POST['dept_capacity'] ?? '')),
        ];
        $id = null;

        if ($action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
        }

        if (
            $formValues['dept_id'] === '' ||
            $formValues['dept_name'] === '' ||
            $formValues['dept_capacity'] === ''
        ) {
            $error = 'Please complete all department fields.';
        } elseif (
            strlen($formValues['dept_id']) > 150 ||
            strlen($formValues['dept_name']) > 150
        ) {
            $error = 'Department ID and name must be 150 characters or fewer.';
        } elseif (
            !ctype_digit($formValues['dept_capacity']) ||
            (int) $formValues['dept_capacity'] < 1 ||
            (int) $formValues['dept_capacity'] > 2147483647
        ) {
            $error = 'Capacity must be a positive whole number.';
        } elseif ($action === 'update' && $id === false) {
            $error = 'Invalid department selected.';
        } else {
            if ($action === 'create') {
                $duplicateStmt = $conn->prepare(
                    'SELECT id FROM department WHERE dept_id = ? OR dept_name = ? LIMIT 1'
                );
                if ($duplicateStmt) {
                    $duplicateStmt->bind_param(
                        'ss',
                        $formValues['dept_id'],
                        $formValues['dept_name']
                    );
                }
            } else {
                $duplicateStmt = $conn->prepare(
                    'SELECT id FROM department WHERE (dept_id = ? OR dept_name = ?) AND id <> ? LIMIT 1'
                );
                if ($duplicateStmt) {
                    $duplicateStmt->bind_param(
                        'ssi',
                        $formValues['dept_id'],
                        $formValues['dept_name'],
                        $id
                    );
                }
            }

            if (!$duplicateStmt) {
                $error = 'Unable to check for duplicate departments.';
            } else {
                $duplicateStmt->execute();
                $duplicate = $duplicateStmt->get_result()->fetch_assoc();
                $duplicateStmt->close();

                if ($duplicate) {
                    $error = 'A department with this ID or name already exists.';
                } elseif ($action === 'create') {
                    $stmt = $conn->prepare(
                        'INSERT INTO department (dept_id, dept_name, dept_capacity) VALUES (?, ?, ?)'
                    );
                    if (!$stmt) {
                        $error = 'Unable to process the department.';
                    } else {
                        $stmt->bind_param(
                            'sss',
                            $formValues['dept_id'],
                            $formValues['dept_name'],
                            $formValues['dept_capacity']
                        );
                        $saved = $stmt->execute();
                        $stmt->close();

                        if ($saved) {
                            $_SESSION['department_flash'] = [
                                'type' => 'success',
                                'message' => 'Department created successfully.',
                            ];
                            header('Location: departments.php');
                            exit;
                        }

                        $error = 'Unable to create the department. Please try again.';
                    }
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE department SET dept_id = ?, dept_name = ?, dept_capacity = ? WHERE id = ? LIMIT 1'
                    );
                    if (!$stmt) {
                        $error = 'Unable to process the department update.';
                    } else {
                        $stmt->bind_param(
                            'sssi',
                            $formValues['dept_id'],
                            $formValues['dept_name'],
                            $formValues['dept_capacity'],
                            $id
                        );
                        $saved = $stmt->execute();
                        $stmt->close();

                        if ($saved) {
                            $_SESSION['department_flash'] = [
                                'type' => 'success',
                                'message' => 'Department updated successfully.',
                            ];
                            header('Location: departments.php');
                            exit;
                        }

                        $error = 'Unable to update the department. Please try again.';
                    }
                }
            }
        }
    } else {
        $error = 'Invalid department action.';
    }
}

if ($editId !== false && $editId !== null) {
    $stmt = $conn->prepare(
        'SELECT id, dept_id, dept_name, dept_capacity FROM department WHERE id = ? LIMIT 1'
    );

    if (!$stmt) {
        $error = 'Unable to load the selected department.';
    } else {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editingDepartment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$editingDepartment && $error === '') {
            $error = 'The selected department could not be found.';
        } elseif ($editingDepartment && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $formValues = [
                'dept_id' => (string) $editingDepartment['dept_id'],
                'dept_name' => (string) $editingDepartment['dept_name'],
                'dept_capacity' => (string) $editingDepartment['dept_capacity'],
            ];
        }
    }
}

$departments = [];
$result = $conn->query(
    'SELECT id, dept_id, dept_name, dept_capacity, created_at FROM department ORDER BY dept_name, id'
);
if ($result) {
    $departments = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} else {
    $error = $error !== ''
        ? $error
        : 'Unable to load departments. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments | GIMT ERP</title>
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

        .page-title {
            margin-bottom: 6px;
            font-size: 1.65rem;
            font-weight: 700;
        }

        .department-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 16px rgb(15 23 42 / 4%);
        }

        .department-card .form-control {
            min-height: 44px;
        }

        .table > :not(caption) > * > * {
            padding: 0.85rem 1rem;
            vertical-align: middle;
        }

        @media (max-width: 767.98px) {
            .dashboard-main {
                padding: 76px 14px 24px;
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
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="page-title">Departments</h1>
                        <p class="text-secondary mb-0">Create and manage academic departments.</p>
                    </div>
                    <span class="badge rounded-pill text-bg-primary px-3 py-2">
                        <?= count($departments) ?> departments
                    </span>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= department_escape($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                        <?= department_escape($flash['message'] ?? '') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= department_escape($error) ?></div>
                <?php endif; ?>

                <section class="department-card p-4 mb-4" aria-labelledby="department-form-title">
                    <div class="mb-3">
                        <h2 class="h5 mb-1" id="department-form-title">
                            <?= $editingDepartment ? 'Edit department' : 'Add a department' ?>
                        </h2>
                        <p class="text-secondary small mb-0">
                            <?= $editingDepartment ? 'Update the department details below.' : 'Enter a unique ID, name and student intake capacity.' ?>
                        </p>
                    </div>
                    <form method="post" action="departments.php<?= $editingDepartment ? '?edit=' . (int) $editingDepartment['id'] : '' ?>">
                        <input type="hidden" name="csrf_token" value="<?= department_escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="<?= $editingDepartment ? 'update' : 'create' ?>">
                        <?php if ($editingDepartment): ?>
                            <input type="hidden" name="id" value="<?= (int) $editingDepartment['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="dept_id" class="form-label">Department ID</label>
                                <input
                                    type="text"
                                    name="dept_id"
                                    id="dept_id"
                                    class="form-control"
                                    maxlength="150"
                                    required
                                    value="<?= department_escape($formValues['dept_id']) ?>"
                                    placeholder="e.g. CSE">
                            </div>
                            <div class="col-md-5">
                                <label for="dept_name" class="form-label">Department name</label>
                                <input
                                    type="text"
                                    name="dept_name"
                                    id="dept_name"
                                    class="form-control"
                                    maxlength="150"
                                    required
                                    value="<?= department_escape($formValues['dept_name']) ?>"
                                    placeholder="e.g. Computer Science &amp; Engineering">
                            </div>
                            <div class="col-md-3">
                                <label for="dept_capacity" class="form-label">Student intake capacity</label>
                                <input
                                    type="number"
                                    name="dept_capacity"
                                    id="dept_capacity"
                                    class="form-control"
                                    min="1"
                                    max="2147483647"
                                    step="1"
                                    required
                                    value="<?= department_escape($formValues['dept_capacity']) ?>"
                                    placeholder="e.g. 60">
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?= $editingDepartment ? 'check-lg' : 'plus-lg' ?> me-1" aria-hidden="true"></i>
                                    <?= $editingDepartment ? 'Save changes' : 'Create department' ?>
                                </button>
                                <?php if ($editingDepartment): ?>
                                    <a href="departments.php" class="btn btn-outline-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="department-card overflow-hidden" aria-labelledby="department-list-title">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-4 pb-3">
                        <h2 class="h5 mb-0" id="department-list-title">Department list</h2>
                        <span class="text-secondary small"><?= count($departments) ?> total</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Department ID</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Intake capacity</th>
                                    <th scope="col">Created</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($departments): ?>
                                    <?php foreach ($departments as $index => $department): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= department_escape($department['dept_id']) ?></td>
                                            <td class="fw-semibold"><?= department_escape($department['dept_name']) ?></td>
                                            <td><?= department_escape($department['dept_capacity']) ?></td>
                                            <td>
                                                <?php
                                                $createdAt = strtotime((string) $department['created_at']);
                                                echo $createdAt === false
                                                    ? department_escape($department['created_at'])
                                                    : department_escape(date('M j, Y', $createdAt));
                                                ?>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="departments.php?edit=<?= (int) $department['id'] ?>"
                                                    aria-label="Edit <?= department_escape($department['dept_name']) ?>">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                    <span class="d-none d-md-inline">Edit</span>
                                                </a>
                                                <form method="post" action="departments.php" class="d-inline" onsubmit="return confirm('Delete this department? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?= department_escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $department['id'] ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        aria-label="Delete <?= department_escape($department['dept_name']) ?>">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                        <span class="d-none d-md-inline">Delete</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-5 text-center text-secondary">
                                            <i class="bi bi-building d-block fs-3 mb-2" aria-hidden="true"></i>
                                            No departments yet. Use the form above to create one.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>