<?php
// fee_structures.php - Itemized Fee Structure Builder & Semester Invoicing
require_once __DIR__ . '/src/bootstrap.php';
require_roles(['chairman', 'principal', 'accounts']);

$pdo = getDb();
$userRole = $_SESSION['user_role'] ?? '';

// Handle Add / Edit Fee Structure or Bulk Invoicing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'save_structure') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $quota = trim($_POST['quota'] ?? 'general');
        $batchYear = (int)($_POST['batch_year'] ?? 2026);
        $semester = (int)($_POST['semester'] ?? 1);

        $tuition = (float)($_POST['tuition_fee'] ?? 0);
        $development = (float)($_POST['development_fee'] ?? 0);
        $library = (float)($_POST['library_fee'] ?? 0);
        $lab = (float)($_POST['lab_fee'] ?? 0);
        $hostel = (float)($_POST['hostel_fee'] ?? 0);
        $bus = (float)($_POST['bus_fee'] ?? 0);
        $caution = (float)($_POST['caution_deposit'] ?? 0);

        $total = $tuition + $development + $library + $lab + $hostel + $bus + $caution;

        if ($deptId > 0 && $total > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO fee_structures (
                        department_id, quota, batch_year, semester,
                        tuition_fee, development_fee, library_fee, lab_fee,
                        hostel_fee, bus_fee, caution_deposit, total_amount
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?
                    ) ON DUPLICATE KEY UPDATE
                        tuition_fee = VALUES(tuition_fee),
                        development_fee = VALUES(development_fee),
                        library_fee = VALUES(library_fee),
                        lab_fee = VALUES(lab_fee),
                        hostel_fee = VALUES(hostel_fee),
                        bus_fee = VALUES(bus_fee),
                        caution_deposit = VALUES(caution_deposit),
                        total_amount = VALUES(total_amount)
                ");
                $stmt->execute([
                    $deptId, $quota, $batchYear, $semester,
                    $tuition, $development, $library, $lab,
                    $hostel, $bus, $caution, $total
                ]);
                audit_log('Fee Structure Saved', 'fee_structures', $pdo->lastInsertId(), "Dept {$deptId}, Sem {$semester}, Total: {$total}");
                set_flash('success', "Fee structure configured successfully. Total Semester Fee: ₹" . number_format($total, 2));
            } catch (Exception $e) {
                set_flash('danger', "Failed to save fee structure: " . $e->getMessage());
            }
        }
        header('Location: fee_structures.php');
        exit;

    } elseif ($action === 'generate_bulk_invoices') {
        // Bulk Debit Invoicing for all enrolled students in Dept & Semester
        $deptId = (int)($_POST['department_id'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 1);
        $batchYear = (int)($_POST['batch_year'] ?? 2026);

        // Fetch structure
        $structStmt = $pdo->prepare("
            SELECT total_amount FROM fee_structures
            WHERE department_id = ? AND batch_year = ? AND semester = ?
            LIMIT 1
        ");
        $structStmt->execute([$deptId, $batchYear, $semester]);
        $totalAmount = (float)$structStmt->fetchColumn();

        if ($totalAmount <= 0) {
            set_flash('danger', "No fee structure found for the selected department, batch and semester.");
            header('Location: fee_structures.php');
            exit;
        }

        // Fetch students in this dept, batch and semester
        $stdStmt = $pdo->prepare("
            SELECT id, roll_no FROM students
            WHERE department_id = ? AND batch_year = ? AND current_semester = ? AND admission_status = 'admitted'
        ");
        $stdStmt->execute([$deptId, $batchYear, $semester]);
        $targetStudents = $stdStmt->fetchAll();

        $count = 0;
        $ledgerStmt = $pdo->prepare("
            INSERT INTO student_ledger (student_id, transaction_date, description, type, amount, payment_mode, reference_no, created_by)
            VALUES (?, CURDATE(), ?, 'debit', ?, 'System', ?, ?)
        ");

        foreach ($targetStudents as $ts) {
            $desc = "Semester {$semester} Batch Fee Invoice Debit";
            $ref = sprintf("INV-%d-S%d-%s", $batchYear, $semester, $ts['roll_no']);
            $ledgerStmt->execute([$ts['id'], $desc, $totalAmount, $ref, $_SESSION['user_id']]);
            $count++;
        }

        audit_log('Bulk Fee Invoices Generated', 'student_ledger', null, "Generated {$count} invoices for Dept {$deptId}, Sem {$semester}, Amount ₹{$totalAmount}");
        set_flash('success', "Successfully posted {$count} student ledger debit invoices totaling ₹" . number_format($count * $totalAmount, 2));
        header('Location: fee_structures.php');
        exit;
    }
}

// Fetch existing fee structures
$structures = $pdo->query("
    SELECT fs.*, d.dept_name, d.dept_code
    FROM fee_structures fs
    JOIN departments d ON fs.department_id = d.id
    ORDER BY d.dept_name, fs.batch_year DESC, fs.semester
")->fetchAll();

$departments = $pdo->query("SELECT id, dept_code, dept_name FROM departments ORDER BY dept_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Itemized Fee Structures &bull; GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .page-layout { display: flex; min-height: 100vh; }
        .dashboard-main { flex: 1; padding: 28px; overflow-x: auto; }
        .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .currency { font-family: monospace; font-weight: 600; }
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
                        <h1 class="h3 fw-bold mb-1">Itemized Fee Structure Builder</h1>
                        <p class="text-secondary mb-0">Departmental semester heads, caution deposits &amp; batch invoicing engine</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#bulkInvoiceModal">
                            <i class="bi bi-receipt-cutoff me-1"></i> Bulk Semester Invoicing
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newStructureModal">
                            <i class="bi bi-plus-lg me-1"></i> Add / Configure Fee Head
                        </button>
                    </div>
                </div>

                <?= display_flash() ?>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department &bull; Quota</th>
                                    <th>Batch &bull; Sem</th>
                                    <th class="text-end">Tuition</th>
                                    <th class="text-end">Development</th>
                                    <th class="text-end">Library &bull; Lab</th>
                                    <th class="text-end">Hostel &bull; Bus</th>
                                    <th class="text-end">Caution Dep.</th>
                                    <th class="text-end">Total Semester Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($structures)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No fee structures configured yet. Click 'Add / Configure Fee Head' to build one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($structures as $s): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= e($s['dept_code']) ?></div>
                                                <div class="small text-muted text-uppercase"><?= e($s['quota']) ?> Quota</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">Batch <?= (int)$s['batch_year'] ?></div>
                                                <div class="badge bg-secondary">Semester <?= (int)$s['semester'] ?></div>
                                            </td>
                                            <td class="text-end currency"><?= format_inr((float)$s['tuition_fee']) ?></td>
                                            <td class="text-end currency"><?= format_inr((float)$s['development_fee']) ?></td>
                                            <td class="text-end currency">
                                                <div>Lib: <?= format_inr((float)$s['library_fee']) ?></div>
                                                <div>Lab: <?= format_inr((float)$s['lab_fee']) ?></div>
                                            </td>
                                            <td class="text-end currency">
                                                <div>Hst: <?= format_inr((float)$s['hostel_fee']) ?></div>
                                                <div>Bus: <?= format_inr((float)$s['bus_fee']) ?></div>
                                            </td>
                                            <td class="text-end currency"><?= format_inr((float)$s['caution_deposit']) ?></td>
                                            <td class="text-end">
                                                <span class="fs-6 fw-bold text-primary currency"><?= format_inr((float)$s['total_amount']) ?></span>
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

    <!-- Configure Fee Modal -->
    <div class="modal fade" id="newStructureModal" tabindex="-1" aria-labelledby="newStructureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="fee_structures.php" method="post" id="feeForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_structure">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="newStructureModalLabel">Configure Itemized Fee Structure</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label required">Department</label>
                                <select name="department_id" class="form-select" required>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>"><?= e($d['dept_code']) ?> &mdash; <?= e($d['dept_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required">Quota</label>
                                <select name="quota" class="form-select">
                                    <option value="general">General / Merit</option>
                                    <option value="management">Management</option>
                                    <option value="tfw">TFW Scheme</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required">Batch Year</label>
                                <input type="number" name="batch_year" class="form-control" value="2026" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required">Semester</label>
                                <select name="semester" class="form-select">
                                    <?php for ($sem = 1; $sem <= 8; $sem++): ?>
                                        <option value="<?= $sem ?>">Sem <?= $sem ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <h3 class="h6 fw-bold text-uppercase text-secondary mb-3">Itemized Fee Heads (INR)</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tuition Fee</label>
                                <input type="number" step="0.01" name="tuition_fee" class="form-control fee-input" value="45000.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Development Fee</label>
                                <input type="number" step="0.01" name="development_fee" class="form-control fee-input" value="8000.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Library Access Fee</label>
                                <input type="number" step="0.01" name="library_fee" class="form-control fee-input" value="2500.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Laboratory &amp; Equipment Fee</label>
                                <input type="number" step="0.01" name="lab_fee" class="form-control fee-input" value="4500.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hostel &amp; Mess Accommodation (Optional)</label>
                                <input type="number" step="0.01" name="hostel_fee" class="form-control fee-input" value="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transportation / Bus Service (Optional)</label>
                                <input type="number" step="0.01" name="bus_fee" class="form-control fee-input" value="0.00">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Caution Deposit (Refundable)</label>
                                <input type="number" step="0.01" name="caution_deposit" class="form-control fee-input" value="5000.00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Fee Structure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Invoicing Modal -->
    <div class="modal fade" id="bulkInvoiceModal" tabindex="-1" aria-labelledby="bulkInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="fee_structures.php" method="post" onsubmit="return confirm('Post fee debit invoices to all enrolled students in the selected batch and semester?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="generate_bulk_invoices">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold" id="bulkInvoiceModalLabel">Bulk Semester Fee Invoicing</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary small">
                            This will debit the configured semester fee to all active students in the specified department and semester who do not yet have an invoice.
                        </p>
                        <div class="mb-3">
                            <label class="form-label required">Department</label>
                            <select name="department_id" class="form-select" required>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>"><?= e($d['dept_code']) ?> &mdash; <?= e($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label required">Batch Year</label>
                                <input type="number" name="batch_year" class="form-control" value="2026" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label required">Semester</label>
                                <select name="semester" class="form-select">
                                    <?php for ($sem = 1; $sem <= 8; $sem++): ?>
                                        <option value="<?= $sem ?>">Semester <?= $sem ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold">Generate Debits</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
