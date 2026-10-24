<?php
require_once __DIR__ . '/middleware/isLoggedIn.php';
require_once __DIR__ . '/middleware/roleAccess.php';
require_once __DIR__ . '/config/db.config.php';

function syllabus_escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$role = (string) ($_SESSION['user_role'] ?? '');
require_user_role(['chairman', 'principal', 'accounts', 'hr', 'hod', 'teacher']);
$canEdit = in_array($role, ['hod', 'teacher'], true);

$schemaCheck = $conn->query("SHOW TABLES LIKE 'academic_syllabi'");
if (!$schemaCheck) {
    throw new RuntimeException('Unable to verify the syllabus database schema: ' . $conn->error);
}
if ($schemaCheck->num_rows === 0) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Syllabus setup required | GIMT ERP</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <main class="container py-5">
            <div class="alert alert-warning">
                <h1 class="h4">Syllabus setup required</h1>
                <p class="mb-0">The academic tables are not installed in the database yet. Apply the academic table definitions from <code>db.sql</code>, then reload this page.</p>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
$schemaCheck->free();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$error = '';
$flash = $_SESSION['syllabus_flash'] ?? null;
unset($_SESSION['syllabus_flash']);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$editing = null;
$form = [
    'batch_id' => '',
    'semester_number' => '',
    'subject_code' => '',
    'subject_name' => '',
    'topics' => '',
    'learning_outcomes' => '',
    'references_text' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }
    require_user_role(['hod', 'teacher']);

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false) {
            $error = 'Select a valid syllabus record.';
        } else {
            $stmt = $conn->prepare('DELETE FROM academic_syllabi WHERE id = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Unable to prepare the syllabus deletion.';
            } else {
                $stmt->bind_param('i', $id);
                $deleted = $stmt->execute();
                $affectedRows = $stmt->affected_rows;
                $stmt->close();

                if ($deleted && $affectedRows === 1) {
                    $_SESSION['syllabus_flash'] = ['type' => 'success', 'message' => 'Syllabus entry deleted.'];
                    header('Location: syllabus.php');
                    exit;
                }
                $error = $deleted ? 'The syllabus entry was not found.' : 'Unable to delete the syllabus entry.';
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        $form = [
            'batch_id' => trim((string) ($_POST['batch_id'] ?? '')),
            'semester_number' => trim((string) ($_POST['semester_number'] ?? '')),
            'subject_code' => trim((string) ($_POST['subject_code'] ?? '')),
            'subject_name' => trim((string) ($_POST['subject_name'] ?? '')),
            'topics' => trim((string) ($_POST['topics'] ?? '')),
            'learning_outcomes' => trim((string) ($_POST['learning_outcomes'] ?? '')),
            'references_text' => trim((string) ($_POST['references_text'] ?? '')),
        ];
        $id = $action === 'update'
            ? filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : null;
        $batchId = filter_var($form['batch_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $semesterNumber = filter_var($form['semester_number'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);

        if (
            $batchId === false ||
            $semesterNumber === false ||
            $form['subject_code'] === '' ||
            $form['subject_name'] === '' ||
            $form['topics'] === ''
        ) {
            $error = 'Complete the batch, semester, subject code, subject name, and topics fields.';
        } elseif (
            strlen($form['subject_code']) > 50 ||
            strlen($form['subject_name']) > 150 ||
            strlen($form['topics']) > 60000 ||
            strlen($form['learning_outcomes']) > 60000 ||
            strlen($form['references_text']) > 60000
        ) {
            $error = 'A syllabus field exceeds the maximum permitted length.';
        } elseif ($action === 'update' && $id === false) {
            $error = 'Select a valid syllabus entry to update.';
        } else {
            $semesterCheck = $conn->prepare(
                'SELECT id FROM academic_semesters WHERE batch_id = ? AND semester_number = ? LIMIT 1'
            );
            if (!$semesterCheck) {
                $error = 'Unable to verify the selected semester.';
            } else {
                $semesterCheck->bind_param('ii', $batchId, $semesterNumber);
                $semesterCheck->execute();
                $semesterExists = (bool) $semesterCheck->get_result()->fetch_assoc();
                $semesterCheck->close();

                if (!$semesterExists) {
                    $error = 'Choose a semester that has been scheduled for the selected batch.';
                } else {
                    if ($action === 'create') {
                        $stmt = $conn->prepare(
                            'INSERT INTO academic_syllabi
                                (batch_id, semester_number, subject_code, subject_name, topics, learning_outcomes, references_text, created_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        if ($stmt) {
                            $userId = (int) $_SESSION['user_id'];
                            $stmt->bind_param(
                                'iisssssi',
                                $batchId,
                                $semesterNumber,
                                $form['subject_code'],
                                $form['subject_name'],
                                $form['topics'],
                                $form['learning_outcomes'],
                                $form['references_text'],
                                $userId
                            );
                        }
                    } else {
                        $stmt = $conn->prepare(
                            'UPDATE academic_syllabi
                             SET batch_id = ?, semester_number = ?, subject_code = ?, subject_name = ?,
                                 topics = ?, learning_outcomes = ?, references_text = ?
                             WHERE id = ? LIMIT 1'
                        );
                        if ($stmt) {
                            $stmt->bind_param(
                                'iisssssi',
                                $batchId,
                                $semesterNumber,
                                $form['subject_code'],
                                $form['subject_name'],
                                $form['topics'],
                                $form['learning_outcomes'],
                                $form['references_text'],
                                $id
                            );
                        }
                    }

                    if (!$stmt) {
                        $error = 'Unable to prepare the syllabus entry. Apply the syllabus table definition from db.sql first.';
                    } else {
                        $saved = $stmt->execute();
                        $duplicate = $stmt->errno === 1062;
                        $stmt->close();

                        if ($saved) {
                            $_SESSION['syllabus_flash'] = [
                                'type' => 'success',
                                'message' => $action === 'create' ? 'Syllabus entry created.' : 'Syllabus entry updated.',
                            ];
                            header('Location: syllabus.php');
                            exit;
                        }
                        $error = $duplicate
                            ? 'That subject code already exists for this batch and semester.'
                            : 'Unable to save the syllabus entry. Please try again.';
                    }
                }
            }
        }
    } else {
        $error = 'Invalid syllabus action.';
    }
}

$batchesResult = $conn->query(
    'SELECT b.id, b.batch_name, b.start_year, b.end_year, d.dept_name
     FROM academic_batches b
     INNER JOIN department d ON d.id = b.department_id
     ORDER BY b.start_year DESC, d.dept_name, b.batch_name'
);
if (!$batchesResult) {
    throw new RuntimeException('Unable to load academic batches. Apply the academic schema from db.sql first.');
}
$batches = $batchesResult->fetch_all(MYSQLI_ASSOC);
$batchesResult->free();

$semestersResult = $conn->query(
    'SELECT s.batch_id, s.semester_number
     FROM academic_semesters s
     ORDER BY s.batch_id, s.semester_number'
);
if (!$semestersResult) {
    throw new RuntimeException('Unable to load scheduled semesters: ' . $conn->error);
}
$scheduledSemesters = [];
while ($semester = $semestersResult->fetch_assoc()) {
    $scheduledSemesters[$semester['batch_id']][] = (int) $semester['semester_number'];
}
$semestersResult->free();

if ($editId !== false && $editId !== null) {
    if (!$canEdit) {
        http_response_code(403);
        exit('You do not have permission to edit syllabus entries.');
    }
    $stmt = $conn->prepare(
        'SELECT id, batch_id, semester_number, subject_code, subject_name, topics, learning_outcomes, references_text
         FROM academic_syllabi WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to load the selected syllabus entry.');
    }
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$editing) {
        http_response_code(404);
        exit('Syllabus entry not found.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        foreach ($form as $key => $_) {
            $form[$key] = (string) ($editing[$key] ?? '');
        }
    }
}

$syllabiResult = $conn->query(
    'SELECT s.id, s.batch_id, s.semester_number, s.subject_code, s.subject_name,
            s.topics, s.learning_outcomes, s.references_text, s.updated_at,
            b.batch_name, b.start_year, b.end_year, d.dept_name
     FROM academic_syllabi s
     INNER JOIN academic_batches b ON b.id = s.batch_id
     INNER JOIN department d ON d.id = b.department_id
     ORDER BY d.dept_name, b.start_year DESC, s.semester_number, s.subject_code'
);
if (!$syllabiResult) {
    throw new RuntimeException('Unable to load syllabus records: ' . $conn->error);
}
$syllabi = $syllabiResult->fetch_all(MYSQLI_ASSOC);
$syllabiResult->free();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Syllabus | GIMT ERP</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #f5f7fb; color: #172b4d; font-family: "Segoe UI", Tahoma, sans-serif; }
        .dashboard-main { flex: 1 1 auto; min-width: 0; padding: 28px; }
        .syllabus-card { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 4px 16px rgb(15 23 42 / 4%); }
        .syllabus-content { max-width: 420px; white-space: pre-line; }
        @media (max-width: 991.98px) { .dashboard-main { width: 100%; padding: 76px 16px 24px; } }
    </style>
</head>
<body>
    <?php if (in_array($role, ['accounts', 'hr', 'hod', 'teacher'], true)): ?>
        <div class="page-layout">
            <?php require __DIR__ . '/partials/role_sidebar.php'; ?>
    <?php elseif ($role === 'principal'): ?>
        <div class="page-layout">
            <?php require __DIR__ . '/partials/principal_sidebar.php'; ?>
    <?php else: ?>
        <div class="page-layout">
            <?php require __DIR__ . '/partials/superAdmin_sidebar.php'; ?>
    <?php endif; ?>
        <main class="dashboard-main">
            <div class="container-fluid p-0">
                <header class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Syllabus</h1>
                        <p class="text-secondary mb-0">Browse subjects, topics and learning outcomes by department, batch and semester.</p>
                    </div>
                    <span class="badge rounded-pill text-bg-primary px-3 py-2"><?= count($syllabi) ?> subjects</span>
                </header>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= syllabus_escape($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                        <?= syllabus_escape($flash['message'] ?? '') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= syllabus_escape($error) ?></div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                    <section class="syllabus-card p-4 mb-4" aria-labelledby="syllabus-form-title">
                        <h2 class="h5 fw-bold mb-1" id="syllabus-form-title"><?= $editing ? 'Edit syllabus entry' : 'Add syllabus entry' ?></h2>
                        <p class="small text-secondary mb-3">Only HOD and Teacher accounts can add or change syllabus content.</p>
                        <?php if (!$batches): ?>
                            <div class="alert alert-info mb-0">Create a batch and schedule at least one semester before adding a syllabus.</div>
                        <?php else: ?>
                            <form method="post" action="syllabus.php<?= $editing ? '?edit=' . (int) $editing['id'] : '' ?>" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?= syllabus_escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                                <?php if ($editing): ?>
                                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                                <?php endif; ?>
                                <div class="col-12 col-md-5">
                                    <label for="batch_id" class="form-label">Department and batch</label>
                                    <select name="batch_id" id="batch_id" class="form-select" required>
                                        <option value="">Choose a batch</option>
                                        <?php foreach ($batches as $batch): ?>
                                            <option value="<?= (int) $batch['id'] ?>"<?= (string) $batch['id'] === $form['batch_id'] ? ' selected' : '' ?>>
                                                <?= syllabus_escape($batch['dept_name']) ?> — <?= syllabus_escape($batch['batch_name']) ?> (<?= (int) $batch['start_year'] ?>–<?= (int) $batch['end_year'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label for="semester_number" class="form-label">Semester</label>
                                    <select name="semester_number" id="semester_number" class="form-select" required>
                                        <option value="">Choose a semester</option>
                                        <?php foreach ($scheduledSemesters as $batchId => $numbers): ?>
                                            <?php foreach ($numbers as $number): ?>
                                                <option value="<?= $number ?>" data-batch-id="<?= (int) $batchId ?>"<?= (string) $number === $form['semester_number'] && (string) $batchId === $form['batch_id'] ? ' selected' : '' ?>>
                                                    Semester <?= $number ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label for="subject_code" class="form-label">Subject code</label>
                                    <input type="text" name="subject_code" id="subject_code" class="form-control" maxlength="50" value="<?= syllabus_escape($form['subject_code']) ?>" required>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label for="subject_name" class="form-label">Subject name</label>
                                    <input type="text" name="subject_name" id="subject_name" class="form-control" maxlength="150" value="<?= syllabus_escape($form['subject_name']) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label for="topics" class="form-label">Topics / syllabus</label>
                                    <textarea name="topics" id="topics" class="form-control" rows="5" maxlength="60000" required><?= syllabus_escape($form['topics']) ?></textarea>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label for="learning_outcomes" class="form-label">Learning outcomes <span class="text-secondary">(optional)</span></label>
                                    <textarea name="learning_outcomes" id="learning_outcomes" class="form-control" rows="3" maxlength="60000"><?= syllabus_escape($form['learning_outcomes']) ?></textarea>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label for="references_text" class="form-label">References <span class="text-secondary">(optional)</span></label>
                                    <textarea name="references_text" id="references_text" class="form-control" rows="3" maxlength="60000"><?= syllabus_escape($form['references_text']) ?></textarea>
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-<?= $editing ? 'check-lg' : 'plus-lg' ?> me-1" aria-hidden="true"></i><?= $editing ? 'Save changes' : 'Add syllabus' ?>
                                    </button>
                                    <?php if ($editing): ?><a href="syllabus.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="syllabus-card overflow-hidden" aria-labelledby="syllabus-list-title">
                    <div class="p-4 pb-3">
                        <h2 class="h5 fw-bold mb-1" id="syllabus-list-title">Syllabus catalogue</h2>
                        <p class="small text-secondary mb-0"><?= $canEdit ? 'Manage entries you are authorized to edit.' : 'Read-only access for your role.' ?></p>
                    </div>
                    <?php if (!$syllabi): ?>
                        <div class="text-center text-secondary py-5">No syllabus entries are available yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Department / batch</th>
                                        <th scope="col">Semester</th>
                                        <th scope="col">Subject</th>
                                        <th scope="col">Topics</th>
                                        <th scope="col">Learning outcomes</th>
                                        <th scope="col">References</th>
                                        <?php if ($canEdit): ?><th scope="col" class="text-end">Actions</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($syllabi as $entry): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold"><?= syllabus_escape($entry['dept_name']) ?></span>
                                                <span class="d-block small text-secondary"><?= syllabus_escape($entry['batch_name']) ?> · <?= (int) $entry['start_year'] ?>–<?= (int) $entry['end_year'] ?></span>
                                            </td>
                                            <td>Semester <?= (int) $entry['semester_number'] ?></td>
                                            <td><span class="fw-semibold"><?= syllabus_escape($entry['subject_name']) ?></span><span class="d-block small text-secondary"><?= syllabus_escape($entry['subject_code']) ?></span></td>
                                            <td><div class="syllabus-content"><?= syllabus_escape($entry['topics']) ?></div></td>
                                            <td><div class="syllabus-content"><?= syllabus_escape($entry['learning_outcomes'] ?: '—') ?></div></td>
                                            <td><div class="syllabus-content"><?= syllabus_escape($entry['references_text'] ?: '—') ?></div></td>
                                            <?php if ($canEdit): ?>
                                                <td class="text-end text-nowrap">
                                                    <a class="btn btn-sm btn-outline-primary" href="syllabus.php?edit=<?= (int) $entry['id'] ?>" aria-label="Edit <?= syllabus_escape($entry['subject_name']) ?>">
                                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                                    </a>
                                                    <form method="post" action="syllabus.php" class="d-inline" onsubmit="return confirm('Delete this syllabus entry?');">
                                                        <input type="hidden" name="csrf_token" value="<?= syllabus_escape($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete <?= syllabus_escape($entry['subject_name']) ?>">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
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
    <script>
        const batchSelect = document.getElementById('batch_id');
        const semesterSelect = document.getElementById('semester_number');
        if (batchSelect && semesterSelect) {
            const filterSemesters = () => {
                const batchId = batchSelect.value;
                let selectedVisible = false;
                Array.from(semesterSelect.options).forEach(option => {
                    if (!option.value) return;
                    const visible = option.dataset.batchId === batchId;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible && option.selected) selectedVisible = true;
                });
                if (!selectedVisible) semesterSelect.value = '';
            };
            batchSelect.addEventListener('change', filterSemesters);
            filterSemesters();
        }
    </script>
</body>
</html>
