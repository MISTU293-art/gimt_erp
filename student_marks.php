<?php
// student_marks.php - Student Continuous Internal Evaluation (CA / PCA) Marks Portal
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['student']);

$pdo = getDb();
$student = get_current_student();

if (!$student) {
    set_flash('danger', 'Student profile is not linked to your user account. Please contact the administrative office.');
    header('Location: student_dashboard.php');
    exit;
}

$studentId = (int)$student['id'];
$deptId = (int)$student['department_id'];

// Selected Semester (defaults to student's current semester)
$selectedSem = (int)($_GET['semester'] ?? $student['current_semester']);
if ($selectedSem < 1 || $selectedSem > 8) {
    $selectedSem = (int)$student['current_semester'];
}

// Fetch Courses for this semester & department with mapped faculty
$cStmt = $pdo->prepare("
    SELECT c.*, fc.section, u.name AS teacher_name, u.email AS teacher_email
    FROM courses c
    LEFT JOIN faculty_courses fc ON c.id = fc.course_id
    LEFT JOIN users u ON fc.teacher_id = u.id
    WHERE c.department_id = ? AND c.semester = ?
    ORDER BY c.course_code
");
$cStmt->execute([$deptId, $selectedSem]);
$courses = $cStmt->fetchAll();

// Fetch all internal exam marks for this student in this semester
$mStmt = $pdo->prepare("
    SELECT ie.*
    FROM internal_exams ie
    JOIN courses c ON ie.course_id = c.id
    WHERE ie.student_id = ? AND c.department_id = ? AND c.semester = ?
");
$mStmt->execute([$studentId, $deptId, $selectedSem]);
$rawMarks = $mStmt->fetchAll();

$marksByCourse = [];
foreach ($rawMarks as $m) {
    $marksByCourse[$m['course_id']][$m['exam_name']] = [
        'obtained' => (float)$m['marks_obtained'],
        'max' => (float)$m['max_marks'],
        'is_locked' => (int)$m['is_locked'],
    ];
}

// Fetch attendance percentage per course to calculate MAKAUT Attendance Award (5 marks)
$attStmt = $pdo->prepare("
    SELECT
        course_id,
        COUNT(id) AS total,
        SUM(CASE WHEN status IN ('present', 'late', 'on_duty') THEN 1 ELSE 0 END) AS attended
    FROM attendance
    WHERE student_id = ?
    GROUP BY course_id
");
$attStmt->execute([$studentId]);
$attRows = $attStmt->fetchAll();
$attPctByCourse = [];
foreach ($attRows as $ar) {
    $tot = (int)$ar['total'];
    $att = (int)$ar['attended'];
    $attPctByCourse[$ar['course_id']] = ($tot > 0) ? round(($att / $tot) * 100, 1) : 100.0;
}

// Process subject rows
$report = [];
foreach ($courses as $c) {
    $cId = (int)$c['id'];
    $cMarks = $marksByCourse[$cId] ?? [];
    $isPractical = (stripos($c['course_name'], 'lab') !== false || stripos($c['course_name'], 'practical') !== false || stripos($c['course_code'], '9') !== false || ($c['subject_type'] ?? '') === 'Practical');

    // Continuous Assessment (CA1 - CA4)
    $ca1 = $cMarks['CA-1']['obtained'] ?? ($cMarks['CIA-1']['obtained'] ?? null);
    $ca2 = $cMarks['CA-2']['obtained'] ?? ($cMarks['CIA-2']['obtained'] ?? null);
    $ca3 = $cMarks['CA-3']['obtained'] ?? null;
    $ca4 = $cMarks['CA-4']['obtained'] ?? null;

    $caValues = array_values(array_filter([$ca1, $ca2, $ca3, $ca4], fn($v) => $v !== null));
    rsort($caValues); // Best marks first
    $bestCaAvg = null;
    if (count($caValues) >= 2) {
        $bestCaAvg = round(($caValues[0] + $caValues[1]) / 2, 1);
    } elseif (count($caValues) === 1) {
        $bestCaAvg = round($caValues[0], 1);
    }

    // Attendance Marks (out of 5)
    $pct = $attPctByCourse[$cId] ?? 100.0;
    $attMarks = 0;
    if ($pct >= 90.0) $attMarks = 5;
    elseif ($pct >= 80.0) $attMarks = 4;
    elseif ($pct >= 75.0) $attMarks = 3;
    else $attMarks = 0;

    // Total Continuous Internal (out of 30)
    $totalTheoryInternal = ($bestCaAvg !== null) ? min(30.0, round($bestCaAvg + $attMarks, 1)) : null;

    // Practical Continuous Assessment (PCA1 - PCA2)
    $pca1 = $cMarks['PCA-1']['obtained'] ?? null;
    $pca2 = $cMarks['PCA-2']['obtained'] ?? null;
    $pcaAvg = null;
    if ($pca1 !== null && $pca2 !== null) {
        $pcaAvg = round(($pca1 + $pca2) / 2, 1);
    } elseif ($pca1 !== null) {
        $pcaAvg = round($pca1, 1);
    }

    // Lock status
    $isLocked = false;
    foreach ($cMarks as $item) {
        if (!empty($item['is_locked'])) {
            $isLocked = true;
            break;
        }
    }

    $report[] = [
        'course' => $c,
        'is_practical' => $isPractical,
        'ca1' => $ca1,
        'ca2' => $ca2,
        'ca3' => $ca3,
        'ca4' => $ca4,
        'best_ca_avg' => $bestCaAvg,
        'att_pct' => $pct,
        'att_marks' => $attMarks,
        'total_theory_internal' => $totalTheoryInternal,
        'pca1' => $pca1,
        'pca2' => $pca2,
        'pca_avg' => $pcaAvg,
        'is_locked' => $isLocked,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal &amp; PCA Marks Portal &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .marks-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .badge-score { font-size: 0.95rem; font-weight: 700; padding: 6px 12px; border-radius: 6px; }
        .makaut-brand-pill { background: #0b3c5d; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; letter-spacing: 0.5px; }
        @media print {
            .no-print, .role-sidebar, .sidebar-toggle, .makaut-topbar { display: none !important; }
            .dashboard-main { padding: 0 !important; }
            .marks-card { border: 1px solid #000; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="page-layout">
        <div class="no-print">
            <?php require __DIR__ . '/partials/role_sidebar.php'; ?>
        </div>

        <main class="dashboard-main">
            <div class="container-fluid p-0" style="max-width: 1150px;">

                <!-- MAKAUT University Top Header Component -->
                <div class="mb-4">
                    <?php require __DIR__ . '/partials/makaut_header.php'; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="makaut-brand-pill">MAKAUT EVALUATION</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Semester <?= $selectedSem ?> Assessment Record</span>
                        </div>
                        <h1 class="h3 fw-bold mb-0">Continuous Internal Evaluation (CA / PCA) Marks Portal</h1>
                        <p class="text-secondary small mb-0">Theory Continuous Assessment (CA 1-4) &bull; Practical Continuous Assessment (PCA 1-2)</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-outline-primary fw-bold">
                            <i class="bi bi-printer me-1"></i> Print Marks Statement
                        </button>
                        <a href="grade_card.php" class="btn btn-primary fw-bold">
                            <i class="bi bi-award me-1"></i> Final Grade Card
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- Student University Credentials Bar -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary font-monospace fs-6"><?= e($student['roll_no']) ?></span>
                                <span class="badge bg-secondary font-monospace">Univ Roll: <?= e($student['makaut_roll_no'] ?: '249001' . substr((string)$student['batch_year'], 2, 2) . sprintf('%03d', $student['id'])) ?></span>
                                <span class="badge bg-light text-dark font-monospace border">Reg No: <?= e($student['makaut_reg_no'] ?: '249' . $student['batch_year'] . sprintf('%04d', $student['id'])) ?></span>
                            </div>
                            <h2 class="h5 fw-bold mb-1"><?= e($student['name']) ?></h2>
                            <p class="small text-muted mb-0">
                                <?= e($student['dept_name']) ?> (<?= e($student['dept_code']) ?>) &bull; Batch <?= (int)$student['batch_year'] ?> &bull; MAKAUT College Code: 249
                            </p>
                        </div>
                        <div class="col-md-5 text-md-end no-print">
                            <form method="get" action="student_marks.php" class="d-inline-flex gap-2 align-items-center">
                                <label class="small fw-bold text-muted text-nowrap">Filter Semester:</label>
                                <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php for ($s = 1; $s <= 8; $s++): ?>
                                        <option value="<?= $s ?>" <?= $selectedSem === $s ? 'selected' : '' ?>>Semester <?= $s ?> <?= $s === (int)$student['current_semester'] ? '(Current)' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Theory Courses Table (Continuous Assessment CA 1 to 4) -->
                <div class="marks-card mb-4">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="fw-bold text-dark">
                            <i class="bi bi-journal-check text-primary me-2"></i>Theory Continuous Internal Assessment (CA-1 to CA-4 &bull; Max 30 Marks)
                        </div>
                        <div class="small text-muted">
                            MAKAUT Rule: <strong>Best 2 of 4 CA</strong> (Max 25) + <strong>Attendance</strong> (Max 5) = <strong>30 Marks</strong>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th class="text-start" style="width: 25%;">Subject Code &amp; Title</th>
                                    <th>Assigned Faculty</th>
                                    <th>CA-1<br><small class="text-muted">(Max 25)</small></th>
                                    <th>CA-2<br><small class="text-muted">(Max 25)</small></th>
                                    <th>CA-3<br><small class="text-muted">(Max 25)</small></th>
                                    <th>CA-4<br><small class="text-muted">(Max 25)</small></th>
                                    <th>Attendance<br><small class="text-muted">(Max 5)</small></th>
                                    <th class="bg-primary-subtle text-primary fw-bold">Total Internal<br><small>(Max 30)</small></th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $theoryFound = false;
                                foreach ($report as $r):
                                    if ($r['is_practical']) continue;
                                    $theoryFound = true;
                                ?>
                                    <tr class="text-center">
                                        <td class="text-start">
                                            <div class="font-monospace fw-bold text-primary"><?= e($r['course']['course_code']) ?></div>
                                            <div class="fw-semibold text-dark"><?= e($r['course']['course_name']) ?></div>
                                            <div class="small text-muted"><?= (int)$r['course']['credits'] ?> Credits &bull; <?= e($r['course']['subject_type']) ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($r['course']['teacher_name'] ?: 'Department Faculty') ?></div>
                                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">Sec <?= e($r['course']['section'] ?: 'A') ?></span>
                                        </td>
                                        <td>
                                            <?= $r['ca1'] !== null ? '<span class="badge bg-light text-dark border font-monospace fs-6">' . $r['ca1'] . '</span>' : '<span class="text-muted small">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <?= $r['ca2'] !== null ? '<span class="badge bg-light text-dark border font-monospace fs-6">' . $r['ca2'] . '</span>' : '<span class="text-muted small">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <?= $r['ca3'] !== null ? '<span class="badge bg-light text-dark border font-monospace fs-6">' . $r['ca3'] . '</span>' : '<span class="text-muted small">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <?= $r['ca4'] !== null ? '<span class="badge bg-light text-dark border font-monospace fs-6">' . $r['ca4'] . '</span>' : '<span class="text-muted small">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace fs-6">
                                                <?= $r['att_marks'] ?>
                                            </span>
                                            <div class="small text-muted" style="font-size: 0.7rem;"><?= $r['att_pct'] ?>% Att</div>
                                        </td>
                                        <td class="bg-primary-subtle text-primary fw-bold font-monospace fs-5">
                                            <?= $r['total_theory_internal'] !== null ? $r['total_theory_internal'] : '<span class="text-muted fs-6">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($r['is_locked']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-lock-fill"></i> Locked</span>
                                            <?php elseif ($r['best_ca_avg'] !== null): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-pencil"></i> Faculty Draft</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Awaiting Entry</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$theoryFound): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No theory subjects found for this semester.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Practical / Sessional Courses Table (PCA 1 and PCA 2) -->
                <div class="marks-card mb-4">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="fw-bold text-dark">
                            <i class="bi bi-tools text-success me-2"></i>Practical / Sessional Continuous Assessment (PCA-1 &amp; PCA-2 &bull; Max 40 Marks)
                        </div>
                        <div class="small text-muted">
                            MAKAUT Sessional Rule: <strong>PCA-1 &amp; PCA-2</strong> Evaluation across laboratory terms (Max 40 Marks)
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th class="text-start" style="width: 30%;">Laboratory / Sessional Course</th>
                                    <th>Faculty Guide</th>
                                    <th>PCA-1 Marks<br><small class="text-muted">(Max 40)</small></th>
                                    <th>PCA-2 Marks<br><small class="text-muted">(Max 40)</small></th>
                                    <th class="bg-success-subtle text-success fw-bold">Sessional Continuous Award<br><small>(Average out of 40)</small></th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $practFound = false;
                                foreach ($report as $r):
                                    if (!$r['is_practical']) continue;
                                    $practFound = true;
                                ?>
                                    <tr class="text-center">
                                        <td class="text-start">
                                            <div class="font-monospace fw-bold text-success"><?= e($r['course']['course_code']) ?></div>
                                            <div class="fw-semibold text-dark"><?= e($r['course']['course_name']) ?></div>
                                            <div class="small text-muted"><?= (int)$r['course']['credits'] ?> Credits &bull; Practical Sessional</div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($r['course']['teacher_name'] ?: 'Lab In-Charge') ?></div>
                                        </td>
                                        <td>
                                            <?= $r['pca1'] !== null ? '<span class="badge bg-light text-dark border font-monospace fs-6">' . $r['pca1'] . '</span>' : '<span class="text-muted small">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <?= $r['pca2'] !== null ? '<span class="badge bg-light text-dark border font-monospace fs-6">' . $r['pca2'] . '</span>' : '<span class="text-muted small">&mdash;</span>' ?>
                                        </td>
                                        <td class="bg-success-subtle text-success fw-bold font-monospace fs-5">
                                            <?= $r['pca_avg'] !== null ? $r['pca_avg'] : '<span class="text-muted fs-6">&mdash;</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($r['is_locked']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-lock-fill"></i> Locked</span>
                                            <?php elseif ($r['pca_avg'] !== null): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-pencil"></i> Faculty Draft</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Awaiting PCA</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$practFound): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No laboratory or sessional courses listed for Semester <?= $selectedSem ?>.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MAKAUT Regulations Note -->
                <div class="p-3 border rounded-3 bg-white shadow-sm mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong class="text-primary small d-block mb-1"><i class="bi bi-info-circle me-1"></i> Continuous Assessment (CA) Calculation:</strong>
                            <p class="small text-muted mb-0">
                                As per MAKAUT university regulations, colleges conduct four continuous evaluations (CA-1, CA-2, CA-3, CA-4). The best 2 score average is taken out of 25. Adding up to 5 attendance award points yields the 30-mark internal component.
                            </p>
                        </div>
                        <div class="col-md-6 border-start-md ps-md-3">
                            <strong class="text-success small d-block mb-1"><i class="bi bi-shield-check me-1"></i> Result Locking &amp; Security:</strong>
                            <p class="small text-muted mb-0">
                                Once marks are reviewed by the mapped course faculty and confirmed by the Department HOD, the batch is locked against tampering and transmitted for MAKAUT end-semester mark sheet consolidation.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
