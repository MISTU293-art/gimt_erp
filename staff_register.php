
<?php

session_start();

require_once __DIR__ . '/config/db.config.php';
include('./middleware/isLoggedIn.php');
$error = "";
$success = "";

// Display success message after redirect
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitize form data
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $mobile        = trim($_POST['mobile'] ?? '');
    $password      = $_POST['password'] ?? '';
    $address       = trim($_POST['address'] ?? '');
    $role          = $_POST['role'] ?? '';
    $qualification = trim($_POST['qualification'] ?? '');
    $bank_account  = trim($_POST['bank_account'] ?? '');
    $ifsc_code     = strtoupper(trim($_POST['ifsc_code'] ?? ''));
    $bank_name     = trim($_POST['bank_name'] ?? '');
    $micr_code     = trim($_POST['micr_code'] ?? '');
    $branch_name   = trim($_POST['branch_name'] ?? '');
    $pan_id        = strtoupper(trim($_POST['pan_id'] ?? ''));
    $aadhaar       = trim($_POST['aadhaar'] ?? '');

    // Validate required fields
    if (
        $name === '' ||
        $email === '' ||
        $mobile === '' ||
        $password === ''
    ) {
        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 12) {
        $error = "Password must contain at least 12 characters.";

    } elseif (!in_array(
        $role,
        ['chairman', 'principal', 'accounts', 'hr', 'hod', 'teacher'],
        true
    )) {
        $error = "Invalid staff role selected.";

    } else {

        // Check whether the email already exists
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $error = "Unable to process the registration.";

        } else {
            $check->bind_param("s", $email);
            $check->execute();

            $existingUser = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existingUser) {
                $error = "A staff account with this email already exists.";

            } else {

                // Hash the password before storing it
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users (
                        name, email, mobile, password, address, role,
                        qualification, bank_account, ifsc_code, bank_name,
                        micr_code, branch_name, pan_id, aadhaar
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $error = "Unable to process the registration.";

                } else {
                    $stmt->bind_param(
                        "ssssssssssssss",
                        $name,
                        $email,
                        $mobile,
                        $hashedPassword,
                        $address,
                        $role,
                        $qualification,
                        $bank_account,
                        $ifsc_code,
                        $bank_name,
                        $micr_code,
                        $branch_name,
                        $pan_id,
                        $aadhaar
                    );

                    if ($stmt->execute()) {
                        $stmt->close();

                        // Store a one-time success message
                        $_SESSION['success'] =
                            "Staff registered successfully!";

                        // Redirect to refresh the page
                        header("Location: /public/staffs.php ");
                        exit();

                    } elseif ($stmt->errno === 1062) {
                        $error =
                            "A staff account with this email already exists.";

                    } else {
                        $error = "Registration failed. Please try again.";
                    }

                    $stmt->close();
                }
            }
        }
    }
}
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Registration | GIMT ERP</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f5f7fb;
            color: #212529;
        }

        .page-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .dashboard-main {
            flex: 1;
            min-width: 0;
            padding: 28px;
        }

        .page-title {
            font-size: 1.65rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .page-description {
            color: #6c757d;
            margin-bottom: 24px;
        }

        .registration-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 28px;
        }

        .form-section+.form-section {
            margin-top: 32px;
            padding-top: 26px;
            border-top: 1px solid #e9ecef;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .section-description {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control,
        .form-select {
            min-height: 42px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 767.98px) {
            .dashboard-main {
                padding: 16px;
            }

            .registration-card {
                padding: 18px;
            }

            .page-title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>

<body>

    <div class="page-layout">

        <!-- Sidebar -->
        <?php include __DIR__ . '/partials/superAdmin_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-main">

            <div class="container-fluid p-0">

                <div class="mb-4">
                    <h1 class="page-title">Staff Registration</h1>
                    <p class="page-description mb-0">
                        Register a staff member and enter their details below.
                    </p>
                </div>
                <!-- alert -->
                 
<?php if ($error !== '' || $success !== ''): ?>
    <div class="mb-3">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show"
                 role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"></button>
            </div>

        <?php elseif ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show"
                 role="alert">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

                <div class="registration-card">

                    <form action="" method="POST" autocomplete="on">

                        <!-- Personal Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-person-vcard me-2 text-primary"></i>
                                Personal Information
                            </h2>
                            <p class="section-description">
                                Enter the staff member's basic contact details.
                            </p>

                            <div class="row g-3">

                                <div class="col-12 col-md-6">
                                    <label for="name" class="form-label">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="name"
                                        name="name"
                                        maxlength="150"
                                        placeholder="Enter full name"
                                        autocomplete="name"
                                        required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="mobile" class="form-label">
                                        Mobile Number <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        class="form-control"
                                        id="mobile"
                                        name="mobile"
                                        maxlength="20"
                                        placeholder="Enter mobile number"
                                        autocomplete="tel"
                                        required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        maxlength="150"
                                        placeholder="name@example.com"
                                        autocomplete="email"
                                        required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="qualification" class="form-label">
                                        Highest Qualification
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="qualification"
                                        name="qualification"
                                        maxlength="150"
                                        placeholder="e.g. M.Tech, M.Sc, Ph.D.">
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">
                                        Residential Address
                                    </label>
                                    <textarea
                                        class="form-control"
                                        id="address"
                                        name="address"
                                        rows="3"
                                        maxlength="255"
                                        placeholder="Enter complete address"></textarea>
                                </div>

                            </div>
                        </section>

                        <!-- Account Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-shield-lock me-2 text-primary"></i>
                                Account Information
                            </h2>
                            <p class="section-description">
                                Set the staff member's login credentials and access role.
                            </p>

                            <div class="row g-3">

                                <div class="col-12 col-md-6">
                                    <label for="password" class="form-label">
                                        Initial Password <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        minlength="12"
                                        autocomplete="new-password"
                                        placeholder="Create a strong password"
                                        required>
                                    <div class="form-text">
                                        Use at least 12 characters.
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="role" class="form-label">
                                        Staff Role <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        class="form-select"
                                        id="role"
                                        name="role"
                                        required>
                                        <option value="teacher" selected>Teacher</option>
                                        <option value="chairman">Chairman</option>
                                        <option value="principal">Principal</option>
                                        <option value="accounts">Accounts</option>
                                        <option value="hr">Human Resources (HR)</option>
                                        <option value="hod">Head of Department (HOD)</option>
                                    </select>
                                </div>

                            </div>
                        </section>

                        <!-- Bank Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-bank me-2 text-primary"></i>
                                Bank Information
                            </h2>
                            <p class="section-description">
                                Enter the bank details required for salary processing.
                            </p>

                            <div class="row g-3">

                                <div class="col-12 col-md-6">
                                    <label for="bank_name" class="form-label">
                                        Bank Name
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="bank_name"
                                        name="bank_name"
                                        maxlength="150"
                                        placeholder="Enter bank name"
                                        autocomplete="off">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="branch_name" class="form-label">
                                        Branch Name
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="branch_name"
                                        name="branch_name"
                                        maxlength="150"
                                        placeholder="Enter branch name">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="bank_account" class="form-label">
                                        Bank Account Number
                                    </label>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="bank_account"
                                        name="bank_account"
                                        maxlength="150"
                                        placeholder="Enter account number"
                                        autocomplete="off">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="ifsc_code" class="form-label">
                                        IFSC Code
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control text-uppercase"
                                        id="ifsc_code"
                                        name="ifsc_code"
                                        maxlength="20"
                                        placeholder="e.g. SBIN0001234"
                                        autocomplete="off">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="micr_code" class="form-label">
                                        MICR Code
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="micr_code"
                                        name="micr_code"
                                        maxlength="20"
                                        placeholder="Enter MICR code"
                                        autocomplete="off">
                                </div>

                            </div>
                        </section>

                        <!-- Identity Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-card-text me-2 text-primary"></i>
                                Identity Information
                            </h2>
                            <p class="section-description">
                                Enter the staff member's tax and identity details, if applicable.
                            </p>

                            <div class="row g-3">

                                <div class="col-12 col-md-6">
                                    <label for="pan_id" class="form-label">
                                        PAN Number
                                    </label>
                                    <input
                                        type="password"
                                        class="form-control text-uppercase"
                                        id="pan_id"
                                        name="pan_id"
                                        maxlength="20"
                                        placeholder="Enter PAN number"
                                        autocomplete="off">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="aadhaar" class="form-label">
                                        Aadhaar Number
                                    </label>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="aadhaar"
                                        name="aadhaar"
                                        maxlength="12"
                                        inputmode="numeric"
                                        pattern="[0-9]{12}"
                                        placeholder="Enter 12-digit Aadhaar number"
                                        autocomplete="off">
                                </div>

                            </div>
                        </section>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Reset
                            </button>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1"></i>
                                Register Staff
                            </button>
                        </div>

                    </form>

                </div>

            </div>

        </main>

    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>