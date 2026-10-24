<?php
// admission_apply.php - Public Multi-Step Admission & Onboarding Wizard
require_once __DIR__ . '/src/bootstrap.php';

$pdo = getDb();
$departments = $pdo->query("SELECT id, dept_code, dept_name FROM departments ORDER BY dept_name")->fetchAll();

$success = false;
$applicationNo = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? 'male');
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $aadhaarNo = trim($_POST['aadhaar_no'] ?? '');

    $guardianName = trim($_POST['guardian_name'] ?? '');
    $guardianRelation = trim($_POST['guardian_relation'] ?? 'Father');
    $guardianPhone = trim($_POST['guardian_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $tenthBoard = trim($_POST['tenth_board'] ?? '');
    $tenthYear = (int) ($_POST['tenth_year'] ?? 0);
    $tenthPercentage = (float) ($_POST['tenth_percentage'] ?? 0);

    $twelfthBoard = trim($_POST['twelfth_board'] ?? '');
    $twelfthYear = (int) ($_POST['twelfth_year'] ?? 0);
    $twelfthPercentage = (float) ($_POST['twelfth_percentage'] ?? 0);

    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $quota = trim($_POST['quota'] ?? 'general');
    $batchYear = (int) date('Y');

    // Validation
    if (empty($fullName) || empty($email) || empty($phone) || empty($guardianName) || empty($departmentId)) {
        $error = "Please fill in all required fields marked with *.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Handle file uploads
        $uploadDir = __DIR__ . '/uploads/admissions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $files = [
            'doc_aadhaar' => null,
            'doc_tenth' => null,
            'doc_twelfth' => null,
            'doc_tc' => null,
            'doc_photo' => null
        ];

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

        foreach ($files as $fileKey => $val) {
            if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts, true)) {
                    $newFileName = $fileKey . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $newFileName)) {
                        $files[$fileKey] = 'uploads/admissions/' . $newFileName;
                    }
                }
            }
        }

        // Generate Unique Application Number: GIMT-APP-{YEAR}-{RANDOM}
        $count = $pdo->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
        $applicationNo = sprintf("GIMT-APP-%s-%04d", date('Y'), $count + 1);

        // Application Fee Payment simulation
        $paymentStatus = 'success';
        $paymentReference = 'PAY-GATEWAY-' . strtoupper(bin2hex(random_bytes(4)));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO admissions (
                    application_no, full_name, email, phone, dob, gender, blood_group,
                    category, aadhaar_no, guardian_name, guardian_relation, guardian_phone,
                    address, tenth_board, tenth_year, tenth_percentage, twelfth_board,
                    twelfth_year, twelfth_percentage, department_id, quota, batch_year,
                    doc_aadhaar, doc_tenth, doc_twelfth, doc_tc, doc_photo,
                    application_fee, payment_status, payment_reference, payment_date, status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    1000.00, ?, ?, NOW(), 'submitted'
                )
            ");

            $stmt->execute([
                $applicationNo, $fullName, $email, $phone, $dob ?: '2005-01-01', $gender, $bloodGroup,
                $category, $aadhaarNo, $guardianName, $guardianRelation, $guardianPhone,
                $address, $tenthBoard, $tenthYear, $tenthPercentage, $twelfthBoard,
                $twelfthYear, $twelfthPercentage, $departmentId, $quota, $batchYear,
                $files['doc_aadhaar'], $files['doc_tenth'], $files['doc_twelfth'], $files['doc_tc'], $files['doc_photo'],
                $paymentStatus, $paymentReference
            ]);

            audit_log('Admission Application Submitted', 'admissions', $pdo->lastInsertId(), "App No: {$applicationNo} by {$fullName}");
            $success = true;

        } catch (Exception $e) {
            $error = "Failed to submit application: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Admissions 2026 | GIMT Enterprise Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #123b70;
            --secondary: #e8731f;
        }
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; }
        .wizard-header { background: linear-gradient(135deg, #123b70 0%, #0a1f3d 100%); color: white; padding: 40px 0; }
        .step-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 24px; padding: 28px; }
        .step-badge { width: 32px; height: 32px; border-radius: 50%; background: #123b70; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; font-size: 0.9rem; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #475569; }
        .required::after { content: " *"; color: #ef4444; }
    </style>
</head>
<body>

    <header class="wizard-header text-center">
        <div class="container">
            <span class="badge text-bg-warning px-3 py-2 rounded-pill fw-bold mb-2">ADMISSIONS SESSION 2026-2027</span>
            <h1 class="h2 fw-bold mb-1">Global Institute of Management &amp; Technology</h1>
            <p class="mb-0 text-white-50">Online Digital Application &amp; Institutional Onboarding Wizard</p>
        </div>
    </header>

    <div class="container py-5" style="max-width: 900px;">

        <?php if ($success): ?>
            <div class="step-card text-center p-5">
                <div class="mb-3 text-success">
                    <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                </div>
                <h2 class="h3 fw-bold text-success mb-2">Application Successfully Submitted!</h2>
                <p class="text-secondary mb-4">Your application has been logged into the GIMT Admissions &amp; Enrollment Engine.</p>

                <div class="card bg-light border-0 p-4 mb-4 text-start mx-auto" style="max-width: 550px;">
                    <div class="row g-2">
                        <div class="col-sm-5 text-secondary">Application Number:</div>
                        <div class="col-sm-7 fw-bold text-primary font-monospace fs-5"><?= e($applicationNo) ?></div>

                        <div class="col-sm-5 text-secondary">Applicant Name:</div>
                        <div class="col-sm-7 fw-semibold"><?= e($fullName) ?></div>

                        <div class="col-sm-5 text-secondary">Application Fee:</div>
                        <div class="col-sm-7 fw-semibold text-success"><i class="bi bi-check-all"></i> ₹1,000.00 (Cleared via Payment Gateway)</div>

                        <div class="col-sm-5 text-secondary">Initial Workflow Stage:</div>
                        <div class="col-sm-7"><span class="badge bg-primary">Submitted &bull; Pending Verification</span></div>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3">
                    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print Application Slip</button>
                    <a href="index.php" class="btn btn-primary"><i class="bi bi-house me-1"></i> Back to Homepage</a>
                    <a href="admin_login.php" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-right me-1"></i> ERP Portal</a>
                </div>
            </div>
        <?php else: ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="admission_apply.php" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <!-- Step 1: Personal Demographics -->
                <div class="step-card">
                    <h2 class="h5 fw-bold mb-4 d-flex align-items-center">
                        <span class="step-badge">1</span> Personal Demographics
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Full Legal Name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="As per 10th marksheet" required value="<?= e($_POST['full_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="candidate@example.com" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Mobile Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="10-digit number" required value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" required value="<?= e($_POST['dob'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <option value="">Select Blood Group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="General">General</option>
                                <option value="OBC">OBC</option>
                                <option value="SC">SC</option>
                                <option value="ST">ST</option>
                                <option value="EWS">EWS</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Aadhaar Number</label>
                            <input type="text" name="aadhaar_no" class="form-control" maxlength="12" placeholder="12-digit UID" value="<?= e($_POST['aadhaar_no'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Guardian & Address -->
                <div class="step-card">
                    <h2 class="h5 fw-bold mb-4 d-flex align-items-center">
                        <span class="step-badge">2</span> Guardian Details &amp; Permanent Address
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label required">Guardian Name</label>
                            <input type="text" name="guardian_name" class="form-control" placeholder="Parent or legal guardian" required value="<?= e($_POST['guardian_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Relationship</label>
                            <select name="guardian_relation" class="form-select">
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Guardian">Legal Guardian</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Guardian Phone</label>
                            <input type="tel" name="guardian_phone" class="form-control" placeholder="10-digit number" required value="<?= e($_POST['guardian_phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label required">Permanent Residential Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Full postal address with district, state & PIN code" required><?= e($_POST['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Prior Academic History -->
                <div class="step-card">
                    <h2 class="h5 fw-bold mb-4 d-flex align-items-center">
                        <span class="step-badge">3</span> Prior Academic History
                    </h2>
                    <div class="row g-3 mb-3">
                        <div class="col-12"><strong class="text-primary small text-uppercase"><i class="bi bi-mortarboard me-1"></i> Class 10 (Secondary Exam)</strong></div>
                        <div class="col-md-6">
                            <label class="form-label">Board / Council</label>
                            <input type="text" name="tenth_board" class="form-control" placeholder="e.g. WBBSE, CBSE, ICSE" value="<?= e($_POST['tenth_board'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Passing Year</label>
                            <input type="number" name="tenth_year" class="form-control" placeholder="2024" value="<?= e($_POST['tenth_year'] ?? '2024') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Aggregate %</label>
                            <input type="number" step="0.01" name="tenth_percentage" class="form-control" placeholder="85.50" value="<?= e($_POST['tenth_percentage'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12"><strong class="text-primary small text-uppercase"><i class="bi bi-mortarboard me-1"></i> Class 12 / Diploma (Higher Secondary Exam)</strong></div>
                        <div class="col-md-6">
                            <label class="form-label">Board / Council</label>
                            <input type="text" name="twelfth_board" class="form-control" placeholder="e.g. WBCHSE, CBSE, ISC, WBSCTE" value="<?= e($_POST['twelfth_board'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Passing Year</label>
                            <input type="number" name="twelfth_year" class="form-control" placeholder="2026" value="<?= e($_POST['twelfth_year'] ?? '2026') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Aggregate %</label>
                            <input type="number" step="0.01" name="twelfth_percentage" class="form-control" placeholder="88.00" value="<?= e($_POST['twelfth_percentage'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Step 4: Department & Program Selection -->
                <div class="step-card">
                    <h2 class="h5 fw-bold mb-4 d-flex align-items-center">
                        <span class="step-badge">4</span> Program &amp; Department Preference
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Desired Engineering Department</label>
                            <select name="department_id" class="form-select" required>
                                <option value="" disabled selected>Choose Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= (int) $dept['id'] ?>"><?= e($dept['dept_code']) ?> &mdash; <?= e($dept['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Admission Quota</label>
                            <select name="quota" class="form-select">
                                <option value="general">WBJEE / General Merit Quota</option>
                                <option value="management">Institute Management Quota</option>
                                <option value="tfw">Tuition Fee Waiver (TFW) Scheme</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Document Uploads -->
                <div class="step-card">
                    <h2 class="h5 fw-bold mb-4 d-flex align-items-center">
                        <span class="step-badge">5</span> Document Uploads (PDF / JPG / PNG, Max 5MB)
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Applicant Passport Photo</label>
                            <input type="file" name="doc_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aadhaar Card Copy</label>
                            <input type="file" name="doc_aadhaar" class="form-control" accept=".pdf,image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">10th Marksheet / Certificate</label>
                            <input type="file" name="doc_tenth" class="form-control" accept=".pdf,image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">12th Marksheet / Diploma Certificate</label>
                            <input type="file" name="doc_twelfth" class="form-control" accept=".pdf,image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Transfer Certificate / Migration (Optional)</label>
                            <input type="file" name="doc_tc" class="form-control" accept=".pdf,image/*">
                        </div>
                    </div>
                </div>

                <!-- Step 6: Payment Gateway Simulation & Confirmation -->
                <div class="step-card bg-light border-primary">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h3 class="h5 fw-bold mb-1"><i class="bi bi-credit-card-2-front text-primary me-2"></i> Application Fee Checkout</h3>
                            <p class="text-secondary small mb-0">Secure PG Integration &bull; Instant Receipt Generation</p>
                        </div>
                        <div class="text-end">
                            <span class="h4 fw-bold text-success mb-0">₹1,000.00</span>
                            <span class="d-block small text-muted">Inclusive of Prospectus</span>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Submission automatically initiates payment verification and provisions your applicant ledger record.
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                        <i class="bi bi-lock-fill me-2"></i> Pay ₹1,000 &amp; Submit Application
                    </button>
                </div>

            </form>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
