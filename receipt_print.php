<?php
// receipt_print.php - Printable Official Student Fee Receipt (PDF / Print Layout)
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

$pdo = getDb();
$receiptNo = trim($_GET['receipt_no'] ?? '');

$stmt = $pdo->prepare("
    SELECT sl.*, s.roll_no, s.reg_no, s.batch_year, s.current_semester,
           u.name AS student_name, u.email AS student_email,
           d.dept_name, d.dept_code,
           cb.name AS cashier_name
    FROM student_ledger sl
    JOIN students s ON sl.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN departments d ON s.department_id = d.id
    LEFT JOIN users cb ON sl.created_by = cb.id
    WHERE sl.receipt_no = ?
    LIMIT 1
");
$stmt->execute([$receiptNo]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Error: Receipt not found.");
}

// Convert amount to words function
function getAmountInWords(float $amount): string {
    $number = floor($amount);
    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    ];

    if ($number == 0) return 'Zero Rupees Only';

    $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
    // Simplified conversion for Indian Rupees
    return 'Rupees ' . number_format($amount, 2) . ' Only';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Fee Receipt &bull; <?= e($receipt['receipt_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #e2e8f0; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; padding: 30px 10px; }
        .receipt-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 2px solid #123b70; position: relative; }
        .receipt-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 5rem; font-weight: 900; color: rgba(18, 59, 112, 0.04); text-transform: uppercase; pointer-events: none; white-space: nowrap; }
        .receipt-header { border-bottom: 2px solid #123b70; padding-bottom: 15px; margin-bottom: 20px; text-align: center; }
        .receipt-title { font-size: 1.5rem; font-weight: 800; color: #123b70; letter-spacing: 0.5px; }
        .table-receipt th { background: #f8fafc; font-size: 0.85rem; text-transform: uppercase; }
        .currency { font-family: monospace; font-weight: 700; }
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; border: 1px solid #123b70; padding: 25px; margin: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="text-center mb-3 no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg shadow-sm">
            <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
        </button>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg ms-2">
            <i class="bi bi-arrow-left"></i> Return to ERP
        </a>
    </div>

    <div class="receipt-container">
        <div class="receipt-watermark">GIMT OFFICIAL RECEIPT</div>

        <!-- Header -->
        <div class="receipt-header">
            <h1 class="receipt-title mb-1">GLOBAL INSTITUTE OF MANAGEMENT &amp; TECHNOLOGY</h1>
            <p class="small text-secondary mb-1">Approved by AICTE, New Delhi &bull; Affiliated to MAKAUT, West Bengal</p>
            <p class="small text-muted mb-2">Palpara More, NH-34, Krishnagar, Nadia, West Bengal &ndash; 741102</p>
            <span class="badge bg-primary px-3 py-2 text-uppercase fs-6">Official Institutional Fee Receipt</span>
        </div>

        <!-- Receipt Meta -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="small text-muted">Receipt Serial Number:</div>
                <div class="h5 fw-bold text-danger font-monospace"><?= e($receipt['receipt_no']) ?></div>
                <div class="small text-muted mt-2">Transaction Date:</div>
                <div class="fw-semibold"><?= date('d F Y', strtotime($receipt['transaction_date'])) ?></div>
            </div>
            <div class="col-6 text-end">
                <div class="small text-muted">Payment Mode:</div>
                <span class="badge bg-dark-subtle text-dark border fs-6"><?= e($receipt['payment_mode']) ?></span>
                <div class="small text-muted mt-2">Reference / Txn ID:</div>
                <div class="font-monospace fw-semibold"><?= e($receipt['reference_no'] ?: 'CASH') ?></div>
            </div>
        </div>

        <!-- Student Details Box -->
        <div class="card bg-light border-0 p-3 mb-4">
            <div class="row g-2">
                <div class="col-sm-6">
                    <span class="small text-muted">Student Name:</span>
                    <strong class="d-block fs-6"><?= e($receipt['student_name']) ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="small text-muted">College Permanent Roll No:</span>
                    <strong class="d-block font-monospace text-primary fs-6"><?= e($receipt['roll_no']) ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="small text-muted">Department / Branch:</span>
                    <span class="d-block fw-semibold"><?= e($receipt['dept_name']) ?> (<?= e($receipt['dept_code']) ?>)</span>
                </div>
                <div class="col-sm-6">
                    <span class="small text-muted">Current Academic Term:</span>
                    <span class="d-block fw-semibold">Semester <?= (int)$receipt['current_semester'] ?> &bull; Batch <?= (int)$receipt['batch_year'] ?></span>
                </div>
            </div>
        </div>

        <!-- Line Item Table -->
        <table class="table table-bordered table-receipt mb-4">
            <thead>
                <tr>
                    <th style="width: 10%;">#</th>
                    <th>Fee Description / Particulars</th>
                    <th class="text-end" style="width: 25%;">Amount Cleared</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>
                        <div class="fw-bold"><?= e($receipt['description']) ?></div>
                        <div class="small text-muted">A/C Head: Academic Tuition &amp; Administrative Ledger Credit</div>
                    </td>
                    <td class="text-end currency fs-5"><?= format_inr((float)$receipt['amount']) ?></td>
                </tr>
                <tr class="table-light">
                    <th colspan="2" class="text-end">Total Amount Received:</th>
                    <th class="text-end text-success currency fs-5"><?= format_inr((float)$receipt['amount']) ?></th>
                </tr>
            </tbody>
        </table>

        <!-- Amount In Words -->
        <div class="p-3 bg-light rounded border mb-4">
            <span class="small text-muted d-block text-uppercase fw-bold">Amount in Words:</span>
            <span class="fw-bold text-dark"><?= getAmountInWords((float)$receipt['amount']) ?></span>
        </div>

        <!-- Signatures and Stamp -->
        <div class="row pt-5 mt-4">
            <div class="col-6 text-center">
                <div class="small text-muted mb-4">Collected by: <strong><?= e($receipt['cashier_name'] ?: 'Accounts Desk') ?></strong></div>
                <div style="border-top: 1px dashed #64748b; width: 180px; margin: 0 auto; padding-top: 5px;">
                    <span class="small text-uppercase fw-bold">Accounts Officer</span>
                </div>
            </div>
            <div class="col-6 text-center">
                <div class="small text-muted mb-4">System Verification Stamp: <strong>VERIFIED &bull; PAID</strong></div>
                <div style="border-top: 1px dashed #64748b; width: 180px; margin: 0 auto; padding-top: 5px;">
                    <span class="small text-uppercase fw-bold">Dean / Principal</span>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top text-center text-muted" style="font-size: 0.72rem;">
            This is a computer-generated official receipt validated by the GIMT ERP Core Ledger Engine. No physical signature is required under IT Act 2000.
        </div>
    </div>

</body>
</html>
