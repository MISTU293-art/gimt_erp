<?php
// student_ledger.php - Double-Entry Student Financial Ledger & Accounts Management Portal
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';
$isStudent = ($userRole === 'student');
$canManageAccounts = in_array($userRole, ['chairman', 'principal', 'accounts'], true);

// Active Academic Session Context
$activeSession = get_academic_session();
$isArchivedContext = is_previous_session($activeSession);

$studentId = 0;
if ($isStudent) {
    $std = get_current_student();
    if (!$std) {
        set_flash('danger', 'Student record not linked.');
        header('Location: student_dashboard.php');
        exit;
    }
    $studentId = (int)$std['id'];
} else {
    $studentId = (int)($_GET['student_id'] ?? 0);
    if ($studentId === 0) {
        $studentId = (int)$pdo->query("SELECT id FROM students ORDER BY roll_no LIMIT 1")->fetchColumn();
    }
}

// Handle Manual Debit or Waiver Posting (Accounts Desk / Principal / Chairman)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManageAccounts) {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    // PREVIOUS SESSION IMMUTABILITY: Cannot post or modify entries if session is archived
    if ($isArchivedContext) {
        audit_log('Ledger Posting Blocked on Archived Session', 'student_ledger', $studentId, "Attempted posting on locked session {$activeSession}");
        set_flash('danger', "Regulatory Enforcement: Previous session ({$activeSession}) is permanently archived. Ledger adjustments can only be posted to the active session (" . CURRENT_OPERATING_SESSION . ").");
        header("Location: student_ledger.php?student_id={$studentId}");
        exit;
    }

    if ($action === 'post_entry') {
        $type = trim($_POST['type'] ?? 'debit');
        $head = trim($_POST['fee_head'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $ref = trim($_POST['reference_no'] ?? '');

        if (!empty($head) && empty($desc)) {
            $desc = $head;
        } elseif (!empty($head) && !empty($desc)) {
            $desc = "[{$head}] " . $desc;
        }

        if ($studentId > 0 && $amount > 0 && !empty($desc) && in_array($type, ['debit', 'waiver'], true)) {
            $stmt = $pdo->prepare("
                INSERT INTO student_ledger (student_id, academic_session, transaction_date, description, type, amount, payment_mode, reference_no, created_by)
                VALUES (?, ?, CURDATE(), ?, ?, ?, 'Adjustment', ?, ?)
            ");
            $stmt->execute([$studentId, CURRENT_OPERATING_SESSION, $desc, $type, $amount, $ref ?: 'ADJ-' . time(), $_SESSION['user_id']]);
            audit_log('Ledger Entry Posted', 'student_ledger', $studentId, "Posted {$type} of ₹{$amount} ({$desc}) for Student ID {$studentId}");
            set_flash('success', "Ledger {$type} entry of ₹" . number_format($amount, 2) . " posted successfully under session " . CURRENT_OPERATING_SESSION . ".");
        } else {
            set_flash('danger', "Please provide a valid amount and description for the ledger adjustment.");
        }
        header("Location: student_ledger.php?student_id={$studentId}");
        exit;
    }
}

// Fetch Student Profile
$student = null;
if ($studentId > 0) {
    $stdStmt = $pdo->prepare("
        SELECT s.*, u.name, u.email, d.dept_name, d.dept_code
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stdStmt->execute([$studentId]);
    $student = $stdStmt->fetch();
}

// Session & Type Filters
$filterSession = trim($_GET['session'] ?? 'all');
$filterType = trim($_GET['type'] ?? 'all');

// Build Ledger Query
$query = "
    SELECT sl.*, u.name AS creator_name
    FROM student_ledger sl
    LEFT JOIN users u ON sl.created_by = u.id
    WHERE sl.student_id = ?
";
$params = [$studentId];

if ($filterSession !== 'all' && !empty($filterSession)) {
    $query .= " AND sl.academic_session = ?";
    $params[] = $filterSession;
}
if ($filterType !== 'all' && in_array($filterType, ['debit', 'credit', 'waiver'], true)) {
    $query .= " AND sl.type = ?";
    $params[] = $filterType;
}

$query .= " ORDER BY sl.transaction_date ASC, sl.id ASC";
$txStmt = $pdo->prepare($query);
$txStmt->execute($params);
$transactions = $txStmt->fetchAll();

// Calculate Running Balances
$totalDebits = 0.00;
$totalCredits = 0.00;
$totalWaivers = 0.00;

$runningBalance = 0.00;
$ledgerTimeline = [];

foreach ($transactions as $tx) {
    $amt = (float)$tx['amount'];
    if ($tx['type'] === 'debit') {
        $totalDebits += $amt;
        $runningBalance += $amt;
    } elseif ($tx['type'] === 'credit') {
        $totalCredits += $amt;
        $runningBalance -= $amt;
    } elseif ($tx['type'] === 'waiver') {
        $totalWaivers += $amt;
        $runningBalance -= $amt;
    }
    $tx['balance_after'] = $runningBalance;
    $tx['is_archived'] = ($tx['academic_session'] !== CURRENT_OPERATING_SESSION);
    $ledgerTimeline[] = $tx;
}

$balanceDue = $totalDebits - $totalCredits - $totalWaivers;

// For Accounts/Staff: list of students
$allStudents = [];
if (!$isStudent) {
    $allStudents = $pdo->query("SELECT s.id, s.roll_no, s.makaut_roll_no, u.name, d.dept_code FROM students s JOIN users u ON s.user_id = u.id JOIN departments d ON s.department_id = d.id ORDER BY s.roll_no")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Financial Ledger &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .ledger-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .summary-box { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; }
        .stat-val { font-size: 1.6rem; font-weight: 750; }
        .currency { font-family: monospace; font-weight: 600; }
        @media print {
            .no-print, .role-sidebar, .sidebar-toggle, .gimt-sidebar, .makaut-topbar { display: none !important; }
            .dashboard-main { padding: 0 !important; }
            .ledger-card { border: 1px solid #000; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="page-layout">
        <div class="no-print">
            <?php
            if ($userRole === 'chairman') {
                require __DIR__ . '/partials/superAdmin_sidebar.php';
            } elseif ($userRole === 'principal') {
                require __DIR__ . '/partials/principal_sidebar.php';
            } else {
                require __DIR__ . '/partials/role_sidebar.php';
            }
            ?>
        </div>

        <main class="dashboard-main">
            <div class="container-fluid p-0">

                <div class="mb-4 no-print">
                    <?php require __DIR__ . '/partials/makaut_header.php'; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">MAKAUT Accounts &amp; Audit Suite</span>
                            <?php if ($isArchivedContext): ?>
                                <span class="badge bg-secondary text-white"><i class="bi bi-lock-fill me-1"></i> Archived Session (Read-Only)</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-pencil-square me-1"></i> Active Session (Editable)</span>
                            <?php endif; ?>
                        </div>
                        <h1 class="h3 fw-bold mb-1">Double-Entry Financial Ledger &amp; Accounts Desk</h1>
                        <p class="text-secondary mb-0">Audited debits, itemized fee invoices, collection receipts &amp; approved waivers</p>
                    </div>
                    <?php if (!$isStudent && $canManageAccounts): ?>
                        <div class="d-flex gap-2">
                            <a href="fee_collect.php?student_id=<?= $studentId ?>" class="btn btn-success fw-bold">
                                <i class="bi bi-cash-coin me-1"></i> Collect Fee / Issue Receipt
                            </a>
                            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#postEntryModal" <?= $isArchivedContext ? 'disabled title="Cannot post entries in archived session"' : '' ?>>
                                <i class="bi bi-plus-circle me-1"></i> Post Debit / Waiver
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?= display_flash() ?>

                <?php if ($isArchivedContext): ?>
                    <div class="alert alert-warning border-warning d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-shield-lock-fill fs-4 text-warning"></i>
                        <div>
                            <strong>Regulatory Notice: Previous Session Selected (<?= e($activeSession) ?>)</strong><br>
                            Under institutional and MAKAUT audit bylaws, historical financial records from previous sessions are strictly read-only and permanently locked against modification, edit, or deletion.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Student Selector for Accounts / Staff -->
                <?php if (!$isStudent): ?>
                    <div class="card p-3 mb-4 border-0 shadow-sm no-print">
                        <form method="get" action="student_ledger.php" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted">Select Student Account</label>
                                <select name="student_id" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($allStudents as $st): ?>
                                        <option value="<?= (int)$st['id'] ?>" <?= $studentId === (int)$st['id'] ? 'selected' : '' ?>>
                                            <?= e($st['roll_no']) ?> &mdash; <?= e($st['name']) ?> (<?= e($st['dept_code']) ?>) <?= !empty($st['makaut_roll_no']) ? '[Univ: ' . e($st['makaut_roll_no']) . ']' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Academic Session</label>
                                <select name="session" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $filterSession === 'all' ? 'selected' : '' ?>>All Sessions</option>
                                    <option value="2026-2027" <?= $filterSession === '2026-2027' ? 'selected' : '' ?>>2026-2027 (Active Session)</option>
                                    <option value="2025-2026" <?= $filterSession === '2025-2026' ? 'selected' : '' ?>>2025-2026 (Archived)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Transaction Type</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Types</option>
                                    <option value="debit" <?= $filterType === 'debit' ? 'selected' : '' ?>>Debits (Invoices)</option>
                                    <option value="credit" <?= $filterType === 'credit' ? 'selected' : '' ?>>Credits (Payments)</option>
                                    <option value="waiver" <?= $filterType === 'waiver' ? 'selected' : '' ?>>Waivers</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="fee_defaulters.php" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-exclamation-octagon me-1"></i> Defaulters
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Student Profile Banner -->
                <?php if ($student): ?>
                    <div class="card p-4 mb-4 border-0 shadow-sm bg-primary text-white rounded-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-white text-primary font-monospace fs-6"><?= e($student['roll_no']) ?></span>
                                    <span class="badge bg-white-50 text-white font-monospace">Univ Roll: <?= e($student['makaut_roll_no'] ?: '249001' . substr((string)$student['batch_year'], 2, 2) . sprintf('%03d', $student['id'])) ?></span>
                                    <span class="badge bg-warning text-dark font-monospace">Reg: <?= e($student['makaut_reg_no'] ?: '249' . $student['batch_year'] . sprintf('%04d', $student['id'])) ?></span>
                                </div>
                                <h2 class="h4 fw-bold mb-1"><?= e($student['name']) ?></h2>
                                <div class="small text-white-50">
                                    <?= e($student['dept_name']) ?> (<?= e($student['dept_code']) ?>) &bull; Semester <?= (int)$student['current_semester'] ?> &bull; Batch <?= (int)$student['batch_year'] ?> &bull; Phone: <?= e($student['phone']) ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-white-50 text-uppercase fw-bold">Current Net Balance Due</div>
                                <div class="h2 fw-bold mb-0 <?= $balanceDue > 0 ? 'text-warning' : 'text-white' ?> currency">
                                    <?= format_inr($balanceDue) ?>
                                </div>
                                <span class="badge <?= $balanceDue <= 0 ? 'bg-success' : 'bg-danger' ?> mt-1 fs-6">
                                    <?= $balanceDue <= 0 ? '<i class="bi bi-check-circle me-1"></i> All Dues Cleared' : '<i class="bi bi-exclamation-circle me-1"></i> Overdue Balance Pending' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Double-Entry Metrics -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="summary-box">
                                <div class="text-secondary small text-uppercase fw-bold">Total Invoiced Debits</div>
                                <div class="stat-val text-danger currency"><?= format_inr($totalDebits) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="summary-box">
                                <div class="text-secondary small text-uppercase fw-bold">Total Payments Received</div>
                                <div class="stat-val text-success currency"><?= format_inr($totalCredits) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="summary-box">
                                <div class="text-secondary small text-uppercase fw-bold">Approved Waivers</div>
                                <div class="stat-val text-info currency"><?= format_inr($totalWaivers) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="summary-box">
                                <div class="text-secondary small text-uppercase fw-bold">Current Balance Due</div>
                                <div class="stat-val <?= $balanceDue > 0 ? 'text-danger' : 'text-success' ?> currency"><?= format_inr($balanceDue) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Ledger Table -->
                    <div class="ledger-card mb-4">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h3 class="h6 fw-bold mb-0"><i class="bi bi-clock-history me-1"></i> Transaction Audit Ledger</h3>
                            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print Formal Account Statement</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date &bull; Session</th>
                                        <th>Description</th>
                                        <th>Reference / Receipt</th>
                                        <th>Mode</th>
                                        <th class="text-end">Debit (Dr)</th>
                                        <th class="text-end">Credit (Cr)</th>
                                        <th class="text-end">Balance Due</th>
                                        <th class="text-end no-print">Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ledgerTimeline)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No financial ledger transactions recorded.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ledgerTimeline as $entry): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($entry['transaction_date'])) ?></div>
                                                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.68rem;">
                                                        <?= e($entry['academic_session'] ?? '2026-2027') ?>
                                                    </span>
                                                    <?php if (!empty($entry['is_archived'])): ?>
                                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;"><i class="bi bi-lock-fill"></i> Archived</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?= e($entry['description']) ?></div>
                                                    <div class="small text-muted">Recorded by <?= e($entry['creator_name'] ?: 'System') ?></div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($entry['receipt_no'])): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace fs-6">
                                                            <i class="bi bi-receipt"></i> <?= e($entry['receipt_no']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted font-monospace small"><?= e($entry['reference_no'] ?: '-') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= e($entry['payment_mode']) ?></span></td>
                                                <td class="text-end currency text-danger">
                                                    <?= $entry['type'] === 'debit' ? format_inr((float)$entry['amount']) : '-' ?>
                                                </td>
                                                <td class="text-end currency text-success">
                                                    <?= in_array($entry['type'], ['credit', 'waiver'], true) ? format_inr((float)$entry['amount']) : '-' ?>
                                                </td>
                                                <td class="text-end currency fw-bold">
                                                    <?= format_inr($entry['balance_after']) ?>
                                                </td>
                                                <td class="text-end no-print">
                                                    <?php if (!empty($entry['receipt_no'])): ?>
                                                        <a href="receipt_print.php?receipt_no=<?= urlencode($entry['receipt_no']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Formal Receipt">
                                                            <i class="bi bi-printer"></i> Receipt
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">&bull;</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Post Entry Modal for Accounts -->
    <?php if (!$isStudent && $canManageAccounts): ?>
        <div class="modal fade" id="postEntryModal" tabindex="-1" aria-labelledby="postEntryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="student_ledger.php?student_id=<?= $studentId ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="post_entry">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="postEntryModalLabel">
                                <i class="bi bi-wallet2 text-primary me-2"></i>Post Ledger Adjustment Entry
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Transaction Type</label>
                                <select name="type" class="form-select" required>
                                    <option value="debit">Debit (Invoice / Fine / Additional Fee)</option>
                                    <option value="waiver">Waiver (Merit Scholarship / Principal Concession)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Fee Head / Classification</label>
                                <select name="fee_head" class="form-select">
                                    <option value="Tuition Fee Adjustment">Tuition Fee Adjustment</option>
                                    <option value="Examination & Tabulation Fee">Examination &amp; Tabulation Fee</option>
                                    <option value="Laboratory & Workshop Fee">Laboratory &amp; Workshop Fee</option>
                                    <option value="Library Overdue Penalty">Library Overdue Penalty</option>
                                    <option value="Late Registration Fine">Late Registration Fine</option>
                                    <option value="Hostel & Utility Debit">Hostel &amp; Utility Debit</option>
                                    <option value="Merit Scholarship Concession">Merit Scholarship Concession (Waiver)</option>
                                    <option value="Principal Condonation Waiver">Principal Condonation Waiver</option>
                                    <option value="Other Institutional Adjustment">Other Institutional Adjustment</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Amount (INR &bull; ₹)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 5000.00" min="1" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Specific Description / Reason</label>
                                <input type="text" name="description" class="form-control" placeholder="e.g. Semester 3 Special Merit Concession" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Reference Number / Sanction Order (Optional)</label>
                                <input type="text" name="reference_no" class="form-control" placeholder="e.g. SANCTION-2026-08">
                            </div>

                            <div class="p-2 bg-light rounded small text-muted">
                                <i class="bi bi-info-circle me-1"></i> Will be recorded under current active operating session: <strong><?= CURRENT_OPERATING_SESSION ?></strong>.
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold">Commit Ledger Entry</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
