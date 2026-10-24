<?php
// fee_collect.php - Counter Collection & Formal Receipt Processing Interface
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'accounts']);

$pdo = getDb();
$studentId = (int)($_GET['student_id'] ?? 0);

// Fetch Student Profile
$student = null;
$balanceDue = 0.00;

if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.name, u.email, d.dept_name, d.dept_code
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if ($student) {
        // Calculate balance
        $stmtBalance = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type IN ('credit', 'waiver') THEN amount ELSE 0 END), 0) AS balance_due
            FROM student_ledger
            WHERE student_id = ?
        ");
        $stmtBalance->execute([$studentId]);
        $balanceDue = (float)$stmtBalance->fetchColumn();
    }
}

// Handle Payment Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $stdId = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMode = trim($_POST['payment_mode'] ?? 'Cash');
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? 'Semester Institutional Fee Payment');

    if ($stdId > 0 && $amount > 0) {
        try {
            $pdo->beginTransaction();

            // Auto-incrementing formal receipt serial number: RCP-{YEAR}-{00001}
            $year = date('Y');
            $rcpCount = $pdo->query("SELECT COUNT(*) FROM student_ledger WHERE receipt_no IS NOT NULL AND receipt_no != ''")->fetchColumn();
            $receiptNo = sprintf("RCP-%s-%05d", $year, $rcpCount + 1);

            $stmt = $pdo->prepare("
                INSERT INTO student_ledger (student_id, transaction_date, description, type, amount, payment_mode, reference_no, receipt_no, created_by)
                VALUES (?, CURDATE(), ?, 'credit', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $stdId, $description, $amount, $paymentMode,
                $referenceNo ?: 'COUNTER-CASH', $receiptNo, $_SESSION['user_id']
            ]);

            audit_log('Fee Collection', 'student_ledger', $stdId, "Receipt: {$receiptNo}, Amount: {$amount}, Mode: {$paymentMode}");

            $pdo->commit();
            set_flash('success', "Payment of " . format_inr($amount) . " successfully received! Formal Receipt: {$receiptNo}");
            header("Location: receipt_print.php?receipt_no=" . urlencode($receiptNo));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Payment collection failed: " . $e->getMessage());
            header("Location: fee_collect.php?student_id={$stdId}");
            exit;
        }
    }
}

// Student list for autocomplete/selection
$students = $pdo->query("
    SELECT s.id, s.roll_no, u.name, d.dept_code,
           (SELECT COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END), 0) FROM student_ledger WHERE student_id = s.id) AS due
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    ORDER BY s.roll_no
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Collection Counter &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .card-box { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); padding: 24px; }
        .currency { font-family: monospace; font-weight: 600; }
    </style>
</head>
<body>
    <div class="page-layout">
        <?php
        $userRole = $_SESSION['user_role'] ?? '';
        if ($userRole === 'chairman') {
            require __DIR__ . '/partials/superAdmin_sidebar.php';
        } elseif ($userRole === 'principal') {
            require __DIR__ . '/partials/principal_sidebar.php';
        } else {
            require __DIR__ . '/partials/role_sidebar.php';
        }
        ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0" style="max-width: 960px;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Fee Collection &amp; Formal Receipts Counter</h1>
                        <p class="text-secondary mb-0">Direct cashier counter, multiple payment gateways &amp; auto serial receipt generation</p>
                    </div>
                    <div>
                        <a href="fee_defaulters.php" class="btn btn-outline-danger">
                            <i class="bi bi-exclamation-octagon me-1"></i> Defaulters List
                        </a>
                    </div>
                </div>

                <?= display_flash() ?>

                <!-- Step 1: Select Student -->
                <div class="card-box mb-4">
                    <h2 class="h6 fw-bold text-uppercase text-secondary mb-3"><i class="bi bi-search me-1"></i> Locate Student Record</h2>
                    <form method="get" action="fee_collect.php" class="row g-2 align-items-center">
                        <div class="col-md-9">
                            <select name="student_id" class="form-select form-select-lg" onchange="this.form.submit()">
                                <option value="" disabled <?= $studentId === 0 ? 'selected' : '' ?>>Select Student by Roll Number or Name</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= $studentId === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['roll_no']) ?> &mdash; <?= e($s['name']) ?> (<?= e($s['dept_code']) ?>) &bull; Pending Dues: <?= format_inr((float)$s['due']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-lg w-100">Fetch Ledger</button>
                        </div>
                    </form>
                </div>

                <!-- Step 2: Payment Receipt Form -->
                <?php if ($student): ?>
                    <div class="card-box">
                        <div class="row align-items-center mb-4 pb-3 border-bottom">
                            <div class="col-sm-8">
                                <span class="badge bg-primary font-monospace fs-6 mb-1"><?= e($student['roll_no']) ?></span>
                                <h2 class="h4 fw-bold mb-1"><?= e($student['name']) ?></h2>
                                <div class="text-secondary small">
                                    <?= e($student['dept_name']) ?> &bull; Semester <?= (int)$student['current_semester'] ?> &bull; Phone: <?= e($student['phone']) ?>
                                </div>
                            </div>
                            <div class="col-sm-4 text-sm-end mt-2 mt-sm-0">
                                <span class="small text-secondary d-block text-uppercase fw-bold">Outstanding Balance Due</span>
                                <span class="h3 fw-bold <?= $balanceDue > 0 ? 'text-danger' : 'text-success' ?> currency">
                                    <?= format_inr($balanceDue) ?>
                                </span>
                            </div>
                        </div>

                        <form action="fee_collect.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold">Collection Amount (INR)</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" step="0.01" name="amount" class="form-control fw-bold" placeholder="0.00" value="<?= $balanceDue > 0 ? $balanceDue : '' ?>" required>
                                    </div>
                                    <div class="form-text">Enter full balance or installment amount.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required fw-bold">Payment Instrument / Mode</label>
                                    <select name="payment_mode" class="form-select form-select-lg" required>
                                        <option value="Cash">Cash at Counter</option>
                                        <option value="UPI">UPI (Google Pay / PhonePe / Paytm)</option>
                                        <option value="Net Banking">Net Banking / NEFT / RTGS</option>
                                        <option value="Demand Draft">Demand Draft (DD)</option>
                                        <option value="Cheque">Bank Cheque</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Payment Reference / Transaction ID / Instrument No</label>
                                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. UPI-9847291038 or DD-847291">
                                    <div class="form-text">UTR, Bank Txn Number or Cheque/DD serial number.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Fee Description</label>
                                    <input type="text" name="description" class="form-control" value="Semester <?= (int)$student['current_semester'] ?> Institutional Fee Payment">
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-success fs-4"></i>
                                    <div>
                                        <strong>Tamper-Proof Audit Logging</strong>
                                        <div class="small text-muted">Submitting will auto-generate an immutable receipt serial number and credit the student ledger.</div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">
                                <i class="bi bi-printer-fill me-2"></i> Receive Payment &amp; Generate Official Receipt
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
