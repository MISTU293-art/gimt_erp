<?php
require_once __DIR__ . '/middleware/isLoggedIn.php';
include('./middleware/charimanPrincipalHodHrAccount.php');
require_once __DIR__ . '/config/db.config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$ip=$_SERVER['REMOTE_ADDR'];
// ---------- Helpers ----------
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ---------- Validate ID (numeric or text IDs) ----------
$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,50}$/', $id)) {
    http_response_code(400);
    exit('Invalid staff ID.');
}

// ---------- CSRF token ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$selfUrl = basename($_SERVER['PHP_SELF']) . '?id=' . rawurlencode($id);

// Logged-in user's ID: checks the common session keys
$currentId = (string) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['admin_id'] ?? '');

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = $_POST['action'] ?? '';

    // ----- Block / Unblock -----
    if ($action === 'block' || $action === 'unblock') {

        if ($currentId !== '' && $id === $currentId) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'You cannot block your own account.'];
            header('Location: ' . $selfUrl);
            exit;
        }

        if ($action === 'block') {
            $stmt = $conn->prepare(
                "UPDATE users SET status = 'blocked' WHERE id = ? LIMIT 1"
            );
            $stmt->bind_param('s', $id);
            $okMsg = 'User has been blocked.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET status = 'active' WHERE id = ? LIMIT 1"
            );
            $stmt->bind_param('s', $id);
            $okMsg = 'User has been unblocked.';
        }

        $ok = $stmt->execute();
        $stmt->close();

        $_SESSION['flash'] = $ok
            ? ['type' => 'success', 'msg' => $okMsg]
            : ['type' => 'danger',  'msg' => 'Could not update the user status.'];

        header('Location: ' . $selfUrl);
        exit;
    }

    // ----- Update details -----
    if ($action === 'update') {
        $f = [];
        foreach (
            [
                'name',
                'mobile',
                'email',
                'qualification',
                'address',
                'bank_name',
                'branch_name',
                'bank_account',
                'ifsc_code',
                'micr_code',
                'pan_id',
                'aadhaar'
            ] as $field
        ) {
            $f[$field] = trim($_POST[$field] ?? '');
        }
        $f['ifsc_code'] = strtoupper($f['ifsc_code']);
        $f['pan_id']    = strtoupper($f['pan_id']);

        $errors = [];
        if ($f['name'] === '' || mb_strlen($f['name']) > 150) {
            $errors[] = 'Enter a valid name.';
        }
        if (!preg_match('/^[0-9+\-\s]{7,20}$/', $f['mobile'])) {
            $errors[] = 'Enter a valid mobile number.';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($f['aadhaar'] !== '' && !preg_match('/^\d{12}$/', $f['aadhaar'])) {
            $errors[] = 'Aadhaar must be exactly 12 digits.';
        }
        if ($f['pan_id'] !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $f['pan_id'])) {
            $errors[] = 'PAN must look like ABCDE1234F.';
        }

        // Email must not belong to another user
        if (!$errors) {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            $chk->bind_param('ss', $f['email'], $id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $errors[] = 'This email is already used by another user.';
            }
            $chk->close();
        }

        if ($errors) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => implode(' ', $errors)];
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET name=?, mobile=?, email=?, qualification=?, address=?,
                        bank_name=?, branch_name=?, bank_account=?, ifsc_code=?, micr_code=?,
                        pan_id=?, aadhaar=?
                 WHERE id=? LIMIT 1"
            );
            $stmt->bind_param(
                'sssssssssssss',
                $f['name'],
                $f['mobile'],
                $f['email'],
                $f['qualification'],
                $f['address'],
                $f['bank_name'],
                $f['branch_name'],
                $f['bank_account'],
                $f['ifsc_code'],
                $f['micr_code'],
                $f['pan_id'],
                $f['aadhaar'],
                $id
            );
            $ok = $stmt->execute();
            $stmt->close();

            $_SESSION['flash'] = $ok
                ? ['type' => 'success', 'msg' => 'Staff details updated successfully.']
                : ['type' => 'danger',  'msg' => 'Could not update the staff details.'];
        }

        header('Location: ' . $selfUrl);
        exit;
    }
}

// ---------- Flash message ----------
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---------- Fetch staff (blocked users included) ----------
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('s', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    http_response_code(404);
    exit('Staff member not found.');
}

$isBlocked = ($data['status'] ?? '') !== 'active';
$isSelf    = ($currentId !== '' && $id === $currentId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($data['name']) ?> </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f5f7fb;
            color: #172b4d;
            font-family: "Segoe UI", Tahoma, sans-serif;
        }

        .page-layout {
            display: flex;
            align-items: flex-start;
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
            font-weight: 700;
            margin-bottom: 6px;
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
            margin-bottom: 18px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control,
        .form-select {
            min-height: 42px;
        }

        .form-control[readonly] {
            background: #f8fafc;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid #e9ecef;
        }

        .state-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 700;
        }

        .state-active {
            color: #166534;
            background: #eaf7ef;
            border: 1px solid #bbf7d0;
        }

        .state-blocked {
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .blocked-banner {
            border-left: 4px solid #ef4444;
        }

        @media (max-width: 767.98px) {
            .dashboard-main {
                padding: 76px 14px 24px;
            }

            .registration-card {
                padding: 18px;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .form-actions .btn {
                flex: 1;
            }
        }

        @media print {

            .gimt-sidebar,
            .sidebar-toggle,
            .no-print {
                display: none !important;
            }

            body {
                background: #fff;
            }

            .page-layout {
                display: block;
            }

            .dashboard-main {
                padding: 0;
            }

            .registration-card {
                border: 0;
                padding: 0;
            }
        }

        .print-header {
            display: none;
        }

        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-header {
                display: block;
                margin-bottom: 18px;
                padding-bottom: 10px;
                border-bottom: 2px solid #123b70;
            }

            .print-header h2 {
                margin: 0 0 6px;
                font-size: 1.3rem;
                color: #123b70;
            }

            .print-header p {
                margin: 0;
                font-size: .85rem;
            }

            /* hide things not needed on paper */
            .page-title,
            .state-badge,
            .blocked-banner,
            .alert,
            .form-actions {
                display: none !important;
            }

            /* show fields as plain text lines */
            .form-control {
                border: 0 !important;
                border-bottom: 1px solid #cbd5e1 !important;
                border-radius: 0 !important;
                background: transparent !important;
                padding-left: 0;
                min-height: 0;
                resize: none;
            }

            .form-section {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <div class="page-layout">

        <?php include __DIR__ . '/partials/superAdmin_sidebar.php'; ?>

        <main class="dashboard-main">
            <div class="container-fluid p-0">

                <!-- Page heading -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div class="mt-4">
                        <h1 class="page-title"><?= e($data['name']) ?></h1>
                        <?php if ($isBlocked): ?>
                            <span class="state-badge state-blocked"><i class="bi bi-slash-circle"></i> Blocked</span>
                        <?php else: ?>
                            <span class="state-badge state-active"><i class="bi bi-check-circle"></i> Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 no-print mt-4">
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Staff Data
                        </button>
                        <a href="/public/staffs.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Back Home
                        </a>
                    </div>
                </div>

                <!-- Flash message -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show no-print" role="alert">
                        <?= e($flash['msg']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($isBlocked): ?>
                    <div class="alert alert-danger blocked-banner">
                        <i class="bi bi-slash-circle me-1"></i>
                        This user is blocked and cannot log in.
                    </div>
                <?php endif; ?>

                <div class="registration-card">
                    <div class="print-header">
                        <h2>GIMT - Staff Details</h2>
                        <p>
                            <strong><?= e($data['name']) ?></strong><br>
                            Printed on <?= e(date('d M Y, h:i A')) ?>
                            <br>
                            <small>Ip:<?php echo $ip ?></small>
                        </p>
                    </div>
                    <form action="<?= e($selfUrl) ?>" method="POST" id="staffForm" autocomplete="on" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="update">

                        <!-- Personal Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-person-vcard me-2 text-primary"></i>Personal Information
                            </h2>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control editable" id="name" name="name" maxlength="150"
                                        placeholder="Enter full name" autocomplete="name"
                                        value="<?= e($data['name']) ?>" readonly required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control editable" id="mobile" name="mobile" maxlength="20"
                                        autocomplete="tel" value="<?= e($data['mobile']) ?>" readonly required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control editable" id="email" name="email" maxlength="150"
                                        placeholder="name@example.com" autocomplete="email"
                                        value="<?= e($data['email']) ?>" readonly required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="qualification" class="form-label">Highest Qualification</label>
                                    <input type="text" class="form-control editable" id="qualification" name="qualification"
                                        maxlength="150" placeholder="e.g. M.Tech, M.Sc, Ph.D."
                                        value="<?= e($data['qualification'] ?? '') ?>" readonly>
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">Residential Address</label>
                                    <textarea class="form-control editable" id="address" name="address" rows="3"
                                        maxlength="255" placeholder="No address available" readonly><?= e($data['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </section>

                        <!-- Bank Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-bank me-2 text-primary"></i>Bank Information
                            </h2>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <input type="text" class="form-control editable" id="bank_name" name="bank_name"
                                        maxlength="150" placeholder="Enter bank name" autocomplete="off"
                                        value="<?= e($data['bank_name'] ?? '') ?>" readonly>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="branch_name" class="form-label">Branch Name</label>
                                    <input type="text" class="form-control editable" id="branch_name" name="branch_name"
                                        maxlength="150" placeholder="Enter branch name"
                                        value="<?= e($data['branch_name'] ?? '') ?>" readonly>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="bank_account" class="form-label">Bank Account Number</label>
                                    <input type="text" class="form-control editable" id="bank_account" name="bank_account"
                                        maxlength="150" placeholder="Enter account number" autocomplete="off"
                                        value="<?= e($data['bank_account'] ?? '') ?>" readonly>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="ifsc_code" class="form-label">IFSC Code</label>
                                    <input type="text" class="form-control editable text-uppercase" id="ifsc_code" name="ifsc_code"
                                        maxlength="20" placeholder="e.g. SBIN0001234" autocomplete="off"
                                        value="<?= e($data['ifsc_code'] ?? '') ?>" readonly>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="micr_code" class="form-label">MICR Code</label>
                                    <input type="text" class="form-control editable" id="micr_code" name="micr_code"
                                        maxlength="20" placeholder="Enter MICR code" autocomplete="off"
                                        value="<?= e($data['micr_code'] ?? '') ?>" readonly>
                                </div>
                            </div>
                        </section>

                        <!-- Identity Information -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-card-text me-2 text-primary"></i>Identity Information
                            </h2>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="pan_id" class="form-label">PAN Number</label>
                                    <input type="text" class="form-control editable text-uppercase" id="pan_id" name="pan_id"
                                        maxlength="10" placeholder="Enter PAN number" autocomplete="off"
                                        value="<?= e($data['pan_id'] ?? '') ?>" readonly>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="aadhaar" class="form-label">Aadhaar Number</label>
                                    <input type="text" class="form-control editable" id="aadhaar" name="aadhaar"
                                        maxlength="12" inputmode="numeric" pattern="[0-9]{12}"
                                        placeholder="Enter 12-digit Aadhaar number" autocomplete="off"
                                        value="<?= e($data['aadhaar'] ?? '') ?>" readonly>
                                </div>
                            </div>
                        </section>

                        <!-- Account Status -->
                        <section class="form-section">
                            <h2 class="section-title">
                                <i class="bi bi-shield-lock me-2 text-primary"></i>Account Status
                            </h2>

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <?php if ($isBlocked): ?>
                                        <span class="state-badge state-blocked"><i class="bi bi-slash-circle"></i> Blocked</span>
                                        <p class="text-secondary small mb-0 mt-2">This user cannot log in.</p>
                                    <?php else: ?>
                                        <span class="state-badge state-active"><i class="bi bi-check-circle"></i> Active</span>
                                        <p class="text-secondary small mb-0 mt-2">This user can log in normally.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="no-print text-md-end">
                                    <?php if ($isSelf): ?>
                                        <button type="button" class="btn btn-danger" disabled>
                                            <i class="bi bi-slash-circle"></i> Block User
                                        </button>
                                        <p class="text-secondary small mb-0 mt-2">You cannot block your own account.</p>
                                    <?php elseif ($isBlocked): ?>
                                        <button type="submit" form="blockForm" class="btn btn-success">
                                            <i class="bi bi-unlock"></i> Unblock User
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" form="blockForm" class="btn btn-danger">
                                            <i class="bi bi-slash-circle"></i> Block User
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <!-- Form actions -->
                        <div class="form-actions no-print">
                            <!-- View mode -->
                            <div class="d-flex flex-wrap gap-2" id="viewActions">
                                <button type="button" class="btn btn-primary" id="editBtn">
                                    <i class="bi bi-pencil-square"></i> Update
                                </button>
                            </div>

                            <!-- Edit mode -->
                            <div class="d-none flex-wrap gap-2" id="editActions">
                                <button type="button" class="btn btn-outline-secondary" id="cancelBtn">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-cloud-upload"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Separate block / unblock form (kept outside the update form) -->
                    <?php if (!$isSelf): ?>
                        <form action="<?= e($selfUrl) ?>" method="POST" id="blockForm"
                            onsubmit="return confirm('<?= $isBlocked ? 'Unblock' : 'Block' ?> this user?');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="<?= $isBlocked ? 'unblock' : 'block' ?>">
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const form = document.getElementById('staffForm');
        const fields = form.querySelectorAll('.editable');
        const viewActions = document.getElementById('viewActions');
        const editActions = document.getElementById('editActions');
        const original = {};

        fields.forEach(f => original[f.id] = f.value);

        function setEditMode(on) {
            fields.forEach(f => f.readOnly = !on);
            viewActions.classList.toggle('d-none', on);
            editActions.classList.toggle('d-none', !on);
            editActions.classList.toggle('d-flex', on);
            if (on) fields[0].focus();
        }

        document.getElementById('editBtn').addEventListener('click', () => setEditMode(true));

        document.getElementById('cancelBtn').addEventListener('click', () => {
            fields.forEach(f => f.value = original[f.id]);
            setEditMode(false);
        });
    </script>

</body>

</html>