<?php
// exam_series.php - Internal Assessment Series Configuration
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

// Handle Create / Toggle Series
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_series') {
        $seriesName = trim($_POST['series_name'] ?? '');
        $academicYear = trim($_POST['academic_year'] ?? '2026-2027');
        $semester = (int)($_POST['semester'] ?? 1);
        $maxMarks = (float)($_POST['max_marks'] ?? 50.00);
        $passingFloor = (float)($_POST['passing_floor'] ?? 20.00);

        if (!empty($seriesName)) {
            $stmt = $pdo->prepare("
                INSERT INTO exam_series (series_name, academic_year, semester, max_marks, passing_floor, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE max_marks = VALUES(max_marks), passing_floor = VALUES(passing_floor)
            ");
            $stmt->execute([$seriesName, $academicYear, $semester, $maxMarks, $passingFloor]);
            audit_log('Exam Series Configured', 'exam_series', $pdo->lastInsertId(), "Created series {$seriesName}");
            set_flash('success', "Exam assessment series configured successfully.");
        }
        header('Location: exam_series.php');
        exit;
    }
}

$seriesList = $pdo->query("SELECT * FROM exam_series ORDER BY semester, series_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Assessment Schedules &bull; GIMT ERP</title>
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
                        <h1 class="h3 fw-bold mb-1">Internal Exam Series Setup</h1>
                        <p class="text-secondary mb-0">CIA assessment schedules, maximum weightages &amp; statutory passing floors</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="marks_entry.php" class="btn btn-outline-primary">
                            <i class="bi bi-table me-1"></i> Tabular Marks Entry
                        </a>
                        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#newSeriesModal">
                            <i class="bi bi-plus-lg me-1"></i> Add Assessment Series
                        </button>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Assessment Series Title</th>
                                    <th>Semester</th>
                                    <th>Academic Term</th>
                                    <th>Max Scale Marks</th>
                                    <th>Passing Floor</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seriesList as $ser): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold fs-6"><?= e($ser['series_name']) ?></span>
                                        </td>
                                        <td><span class="badge bg-secondary">Semester <?= (int)$ser['semester'] ?></span></td>
                                        <td><?= e($ser['academic_year']) ?></td>
                                        <td><strong class="text-primary"><?= (float)$ser['max_marks'] ?> Marks</strong></td>
                                        <td><strong class="text-danger"><?= (float)$ser['passing_floor'] ?> Marks</strong></td>
                                        <td><span class="badge bg-success">Active &bull; Open for Entry</span></td>
                                        <td class="text-end">
                                            <a href="marks_entry.php?exam_name=<?= urlencode($ser['series_name']) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil-square me-1"></i> Enter Marks
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="newSeriesModal" tabindex="-1" aria-labelledby="newSeriesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="exam_series.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_series">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="newSeriesModalLabel">Configure Internal Exam Series</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Assessment Series Name</label>
                            <input type="text" name="series_name" class="form-control" placeholder="e.g. CIA-1 or Practical Pre-Final" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label required">Semester</label>
                                <select name="semester" class="form-select">
                                    <?php for ($s = 1; $s <= 8; $s++): ?>
                                        <option value="<?= $s ?>">Semester <?= $s ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label required">Academic Term</label>
                                <input type="text" name="academic_year" class="form-control" value="2026-2027" required>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label required">Max Marks</label>
                                <input type="number" step="0.5" name="max_marks" class="form-control" value="30.00" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label required">Passing Floor</label>
                                <input type="number" step="0.5" name="passing_floor" class="form-control" value="12.00" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Series</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
