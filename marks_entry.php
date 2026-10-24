<?php
// marks_entry.php - High-Speed Tabular Continuous Internal Evaluation Entry Screen (Strict Teacher Access & Session Immutability)
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'hod', 'teacher']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$canUnlock = in_array($userRole, ['chairman', 'principal', 'hod'], true);

// Active Academic Session Context
$activeSession = get_academic_session();
$isArchivedContext = is_previous_session($activeSession);

// Fetch strictly permitted courses using RBAC engine
$courses = get_permitted_courses($userId, $userRole);

// Selection parameters
$reqCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedExamName = trim($_GET['exam_name'] ?? 'CA-1');

// Determine default max marks according to MAKAUT regulations
$defaultMaxMarks = 25.0;
if (str_starts_with($selectedExamName, 'PCA')) {
    $defaultMaxMarks = 40.0;
} elseif (str_starts_with($selectedExamName, 'CIA')) {
    $defaultMaxMarks = 30.0;
}
$maxMarks = isset($_GET['max_marks']) ? (float)$_GET['max_marks'] : $defaultMaxMarks;

$selectedCourseId = 0;
if ($reqCourseId > 0) {
    if (can_teacher_access_course($userId, $reqCourseId, $userRole)) {
        $selectedCourseId = $reqCourseId;
    } else {
        set_flash('danger', "Access Denied: Strict regulatory rule prohibits entering marks for Course #{$reqCourseId}. Only the mapped faculty member assigned in the course workload matrix can enter marks.");
        $selectedCourseId = !empty($courses) ? (int)$courses[0]['id'] : 0;
    }
} else {
    $selectedCourseId = !empty($courses) ? (int)$courses[0]['id'] : 0;
}

// Fetch course details
$currentCourse = null;
$students = [];
$existingMarks = [];
$isLockedBatch = false;

if ($selectedCourseId > 0 && can_teacher_access_course($userId, $selectedCourseId, $userRole)) {
    $stmtCur = $pdo->prepare("
        SELECT c.*, d.dept_name, d.dept_code
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmtCur->execute([$selectedCourseId]);
    $currentCourse = $stmtCur->fetch();

    if ($currentCourse) {
        // Fetch enrolled students
        $stdStmt = $pdo->prepare("
            SELECT s.id, s.roll_no, s.makaut_reg_no, s.makaut_roll_no, u.name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.department_id = ? AND s.current_semester = ? AND s.admission_status = 'admitted'
            ORDER BY s.roll_no
        ");
        $stdStmt->execute([$currentCourse['department_id'], $currentCourse['semester']]);
        $students = $stdStmt->fetchAll();

        // Fetch existing marks
        $mStmt = $pdo->prepare("
            SELECT student_id, marks_obtained, is_locked
            FROM internal_exams
            WHERE course_id = ? AND exam_name = ?
        ");
        $mStmt->execute([$selectedCourseId, $selectedExamName]);
        $rows = $mStmt->fetchAll();
        foreach ($rows as $r) {
            $existingMarks[$r['student_id']] = (float)$r['marks_obtained'];
            if ($r['is_locked'] == 1) {
                $isLockedBatch = true;
            }
        }
    }
}

// Determine if entire sheet is read-only
$isReadOnlySheet = ($isLockedBatch || $isArchivedContext);

// Handle Form Submission (Save Draft or Final Lock)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');
    $targetCourseId = (int)($_POST['course_id'] ?? $selectedCourseId);

    // PREVIOUS SESSION IMMUTABILITY CHECK
    if ($isArchivedContext) {
        audit_log('Archived Marks Mutation Attempt Blocked', 'internal_exams', $targetCourseId, "User {$userId} attempted modifying locked session {$activeSession}");
        set_flash('danger', 'Regulatory Enforcement: Previous session data is permanently archived and cannot be edited, modified, or deleted.');
        header("Location: marks_entry.php?course_id={$targetCourseId}&exam_name=" . urlencode($selectedExamName));
        exit;
    }

    // STRICT REGULATION CHECK: Only mapped teacher (or dept HOD/Principal/Chairman) can execute
    if (!can_teacher_access_course($userId, $targetCourseId, $userRole)) {
        audit_log('Unauthorized Marks Submission Attempt Blocked', 'internal_exams', $targetCourseId, "User {$userId} ({$userRole}) denied marks entry access");
        set_flash('danger', 'Access Denied: You are not the assigned teacher for this subject. Operation blocked.');
        header("Location: marks_entry.php");
        exit;
    }

    if ($action === 'save_marks' || $action === 'lock_marks') {
        if ($isLockedBatch && !$canUnlock) {
            set_flash('danger', 'These assessment marks are locked and cannot be altered without Principal authorization.');
            header("Location: marks_entry.php?course_id={$targetCourseId}&exam_name=" . urlencode($selectedExamName));
            exit;
        }

        $marksInput = $_POST['marks'] ?? [];
        $isLocked = ($action === 'lock_marks') ? 1 : 0;
        $maxM = (float)($_POST['max_marks'] ?? $maxMarks);

        try {
            $pdo->beginTransaction();
            $stmtUpsert = $pdo->prepare("
                INSERT INTO internal_exams (course_id, academic_session, student_id, exam_name, marks_obtained, max_marks, is_locked, entered_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), max_marks = VALUES(max_marks), is_locked = VALUES(is_locked), entered_by = VALUES(entered_by)
            ");

            $savedCount = 0;
            foreach ($marksInput as $sId => $val) {
                if ($val !== '' && is_numeric($val)) {
                    $mVal = (float)$val;
                    if ($mVal >= 0 && $mVal <= $maxM) {
                        $stmtUpsert->execute([$targetCourseId, CURRENT_OPERATING_SESSION, (int)$sId, $selectedExamName, $mVal, $maxM, $isLocked, $userId]);
                        $savedCount++;
                    }
                }
            }

            audit_log(
                $isLocked ? 'Exam Marks Locked' : 'Exam Marks Draft Saved',
                'internal_exams',
                $targetCourseId,
                "{$savedCount} marks entries for {$selectedExamName} (Lock: {$isLocked}) by Teacher {$userId}"
            );

            $pdo->commit();
            set_flash('success', "Marks saved for {$savedCount} students. " . ($isLocked ? "Assessment is now FINAL LOCKED." : "Saved as editable draft."));
            header("Location: marks_entry.php?course_id={$targetCourseId}&exam_name=" . urlencode($selectedExamName) . "&max_marks={$maxM}");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Failed to record marks: " . $e->getMessage());
        }
    } elseif ($action === 'unlock_batch' && $canUnlock) {
        $pdo->prepare("UPDATE internal_exams SET is_locked = 0 WHERE course_id = ? AND exam_name = ?")
            ->execute([$targetCourseId, $selectedExamName]);
        audit_log('Exam Marks Unlocked', 'internal_exams', $targetCourseId, "Unlocked by {$_SESSION['user_name']}");
        set_flash('info', "Marks unlocked for faculty editing.");
        header("Location: marks_entry.php?course_id={$targetCourseId}&exam_name=" . urlencode($selectedExamName) . "&max_marks={$maxMarks}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabular Marks Entry &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .marks-input { max-width: 130px; font-weight: 700; text-align: center; }
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

                <div class="mb-4">
                    <?php require __DIR__ . '/partials/makaut_header.php'; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">MAKAUT Academic Suite</span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-shield-lock me-1"></i> Strict Assigned-Teacher Rule Active</span>
                            <?php if ($isArchivedContext): ?>
                                <span class="badge bg-secondary text-white"><i class="bi bi-lock-fill me-1"></i> Archived Session (Read-Only)</span>
                            <?php endif; ?>
                        </div>
                        <h1 class="h3 fw-bold mb-1">Continuous Internal Evaluation (CA / PCA) Marks Entry</h1>
                        <p class="text-secondary mb-0">High-speed tabular entry desk &bull; Only mapped course faculty can enter or lock continuous marks</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="exam_tabulation.php" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Consolidated Tabulation
                        </a>
                        <a href="grade_card.php" class="btn btn-outline-success">
                            <i class="bi bi-award me-1"></i> Digital Grade Cards
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <?php if ($isArchivedContext): ?>
                    <div class="alert alert-warning border-warning d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-shield-lock-fill fs-4 text-warning"></i>
                        <div>
                            <strong>Archived Academic Session Selected (<?= e($activeSession) ?>): Read-Only</strong><br>
                            Under MAKAUT and institute bylaws, marks for archived academic sessions are permanently frozen and cannot be edited, modified, or deleted.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($courses)): ?>
                    <div class="card p-5 text-center border-0 shadow-sm rounded-4">
                        <div class="text-warning mb-3">
                            <i class="bi bi-shield-exclamation" style="font-size: 3.5rem;"></i>
                        </div>
                        <h2 class="h5 fw-bold text-dark">No Teaching Subjects Assigned in Workload Matrix</h2>
                        <p class="text-secondary mx-auto mb-4" style="max-width: 550px;">
                            Under institutional regulations, faculty members can only access and enter marks for subjects mapped to them in the Faculty Workload Allocation master.
                        </p>
                        <div>
                            <?php if (in_array($userRole, ['chairman', 'principal', 'hod'], true)): ?>
                                <a href="faculty_workload.php" class="btn btn-primary fw-bold">
                                    <i class="bi bi-person-workspace me-1"></i> Assign Course Workload
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary p-2">Contact your Department HOD or Principal to map your subjects</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Selection Bar -->
                    <div class="card p-3 mb-4 border-0 shadow-sm">
                        <form method="get" action="marks_entry.php" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted">Permitted Course / Subject (Assigned to You)</label>
                                <select name="course_id" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                                            [<?= e($c['dept_code']) ?> Sem <?= $c['semester'] ?>] <?= e($c['course_code']) ?> &mdash; <?= e($c['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">MAKAUT Assessment Series</label>
                                <select name="exam_name" class="form-select" onchange="this.form.submit()">
                                    <optgroup label="MAKAUT Continuous Assessment (Theory)">
                                        <option value="CA-1" <?= $selectedExamName === 'CA-1' ? 'selected' : '' ?>>Continuous Assessment 1 (CA-1 &bull; Max 25)</option>
                                        <option value="CA-2" <?= $selectedExamName === 'CA-2' ? 'selected' : '' ?>>Continuous Assessment 2 (CA-2 &bull; Max 25)</option>
                                        <option value="CA-3" <?= $selectedExamName === 'CA-3' ? 'selected' : '' ?>>Continuous Assessment 3 (CA-3 &bull; Max 25)</option>
                                        <option value="CA-4" <?= $selectedExamName === 'CA-4' ? 'selected' : '' ?>>Continuous Assessment 4 (CA-4 &bull; Max 25)</option>
                                    </optgroup>
                                    <optgroup label="MAKAUT Practical Assessment (Labs / Sessional)">
                                        <option value="PCA-1" <?= $selectedExamName === 'PCA-1' ? 'selected' : '' ?>>Practical Assessment 1 (PCA-1 &bull; Max 40)</option>
                                        <option value="PCA-2" <?= $selectedExamName === 'PCA-2' ? 'selected' : '' ?>>Practical Assessment 2 (PCA-2 &bull; Max 40)</option>
                                    </optgroup>
                                    <optgroup label="Legacy Institutional Series">
                                        <option value="CIA-1" <?= $selectedExamName === 'CIA-1' ? 'selected' : '' ?>>CIA-1 (Max 30)</option>
                                        <option value="CIA-2" <?= $selectedExamName === 'CIA-2' ? 'selected' : '' ?>>CIA-2 (Max 30)</option>
                                        <option value="Practical Pre-Final" <?= $selectedExamName === 'Practical Pre-Final' ? 'selected' : '' ?>>Practical Pre-Final</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Max Marks</label>
                                <input type="number" step="0.5" name="max_marks" class="form-control" value="<?= $maxMarks ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Load Sheet</button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabular Sheet -->
                    <?php if ($currentCourse): ?>
                        <form action="marks_entry.php?course_id=<?= $selectedCourseId ?>&exam_name=<?= urlencode($selectedExamName) ?>&max_marks=<?= $maxMarks ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
                            <input type="hidden" name="max_marks" value="<?= $maxMarks ?>">

                            <div class="table-card">
                                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="badge bg-primary me-2 font-monospace"><?= e($currentCourse['course_code']) ?></span>
                                        <strong><?= e($currentCourse['course_name']) ?></strong>
                                        <span class="text-secondary small ms-2">&bull; Assessment: <strong><?= e($selectedExamName) ?></strong> &bull; Max Marks: <strong><?= $maxMarks ?></strong></span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-2"><i class="bi bi-check-circle"></i> Assigned Subject</span>
                                    </div>
                                    <div>
                                        <?php if ($isLockedBatch): ?>
                                            <span class="badge bg-danger py-2 px-3 fs-6"><i class="bi bi-lock-fill me-1"></i> RESULT LOCKED</span>
                                            <?php if ($canUnlock && !$isArchivedContext): ?>
                                                <button type="submit" name="action" value="unlock_batch" class="btn btn-sm btn-outline-warning ms-2" onclick="return confirm('Unlock these marks for faculty alteration?');">
                                                    <i class="bi bi-unlock"></i> Unlock
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($isArchivedContext): ?>
                                            <span class="badge bg-secondary py-2 px-3 fs-6"><i class="bi bi-lock-fill me-1"></i> ARCHIVED SESSION</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3"><i class="bi bi-pencil"></i> DRAFT EDITABLE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 80px;">#</th>
                                                <th>Roll Number &bull; Student Name</th>
                                                <th>MAKAUT University Identifiers</th>
                                                <th class="text-center" style="width: 250px;">Marks Obtained (Out of <?= $maxMarks ?>)</th>
                                                <th>Performance Indicator</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($students)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">No students enrolled in this course semester.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($students as $idx => $s): ?>
                                                    <?php
                                                    $val = isset($existingMarks[$s['id']]) ? $existingMarks[$s['id']] : '';
                                                    $pct = ($val !== '' && $maxMarks > 0) ? round(($val / $maxMarks) * 100, 1) : null;
                                                    ?>
                                                    <tr>
                                                        <td class="text-muted fw-bold"><?= $idx + 1 ?></td>
                                                        <td>
                                                            <div class="fw-bold"><?= e($s['name']) ?></div>
                                                            <div class="small text-muted font-monospace"><?= e($s['roll_no']) ?></div>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($s['makaut_roll_no'])): ?>
                                                                <span class="small font-monospace text-primary d-block">Roll: <?= e($s['makaut_roll_no']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($s['makaut_reg_no'])): ?>
                                                                <span class="small font-monospace text-secondary d-block">Reg: <?= e($s['makaut_reg_no']) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="number"
                                                                   step="0.5"
                                                                   name="marks[<?= (int)$s['id'] ?>]"
                                                                   value="<?= $val !== '' ? $val : '' ?>"
                                                                   min="0"
                                                                   max="<?= $maxMarks ?>"
                                                                   class="form-control marks-input mx-auto"
                                                                   placeholder="&mdash;"
                                                                   <?= $isReadOnlySheet ? 'disabled' : '' ?>>
                                                        </td>
                                                        <td>
                                                            <?php if ($pct !== null): ?>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 100px;">
                                                                        <div class="progress-bar <?= $pct >= 40 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= min(100, $pct) ?>%"></div>
                                                                    </div>
                                                                    <span class="small fw-bold <?= $pct >= 40 ? 'text-success' : 'text-danger' ?>"><?= $pct ?>%</span>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="small text-muted">Unentered</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (!empty($students) && !$isReadOnlySheet): ?>
                                    <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="small text-muted">
                                            <i class="bi bi-info-circle me-1"></i> Saving draft allows ongoing modifications. <strong>Final Result Lock</strong> cryptographically secures the score sheet.
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="action" value="save_marks" class="btn btn-outline-primary fw-bold">
                                                <i class="bi bi-save me-1"></i> Save Editable Draft
                                            </button>
                                            <button type="submit" name="action" value="lock_marks" class="btn btn-success fw-bold" onclick="return confirm('Lock this assessment sheet permanently? Once locked, alterations require Principal condonation.');">
                                                <i class="bi bi-lock-fill me-1"></i> Finalize &amp; Lock Assessment
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </form>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
