<?php
require_once __DIR__ . '/src/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $allowedRoles = [
        'chairman',
        'principal',
        'accounts',
        'hr',
        'hod',
        'teacher',
        'student'
    ];

    if ($email === '' || $password === '' || $role === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Please select a valid designation.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, full_name, email, password, role, status
             FROM users
             WHERE email = ? AND role = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = 'Unable to process your login. Please try again.';
        } else {
            $stmt->bind_param('ss', $email, $role);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                !$user ||
                !password_verify($password, $user['password'])
            ) {
                $error = 'Invalid email, password, or designation.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Your account is blocked. Please contact the administrator.';
            } else {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Audit login
                audit_log('User Login', 'users', (int) $user['id'], "User logged in with role {$role}");

                // Redirect based on role
                $dashboards = [
                    'chairman' => 'admin_dashboard.php',
                    'principal' => 'principal_dashboard.php',
                    'accounts' => 'accounts_dashboard.php',
                    'hr' => 'hr_dashboard.php',
                    'hod' => 'hod_dashboard.php',
                    'teacher' => 'teacher_dashboard.php',
                    'student' => 'student_dashboard.php'
                ];

                header('Location: ' . ($dashboards[$role] ?? 'admin_dashboard.php'));
                exit;
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
    <meta name="theme-color" content="#123b70">
    <title>GIMT Enterprise ERP | Institutional Login</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #123b70;
            --primary-dark: #0c274b;
            --secondary: #e8731f;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --bg-light: #f8fafc;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            --card-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --transition: all 0.2s ease-in-out;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-container {
            width: 100%;
            max-width: 1050px;
        }

        .auth-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .auth-card:hover {
            box-shadow: var(--card-shadow-lg);
        }

        .brand-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .brand-section::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04' fill-rule='evenodd'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.7;
            pointer-events: none;
        }

        .form-section {
            padding: 3rem 2.5rem;
        }

        .brand-badge {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand-title {
            font-weight: 700;
            font-size: 1.75rem;
            line-height: 1.25;
            margin-bottom: 1rem;
        }

        .brand-desc {
            font-size: 0.95rem;
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            color: #e2e8f0;
        }

        .feature-icon {
            font-size: 1.15rem;
            color: var(--secondary);
            margin-right: 0.75rem;
            line-height: 1;
            margin-top: 2px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group-text {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            color: #64748b;
        }

        .form-control, .form-select {
            border-color: #cbd5e1;
            padding: 0.7rem 0.95rem;
            font-size: 0.95rem;
            color: var(--text-dark);
            border-radius: 8px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(18, 59, 112, 0.12);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .demo-chip {
            cursor: pointer;
            padding: 5px 10px;
            font-size: 0.75rem;
            border-radius: 6px;
            border: 1px dashed #94a3b8;
            background: #f1f5f9;
            transition: all 0.15s;
        }

        .demo-chip:hover {
            background: #e2e8f0;
            border-color: var(--primary);
            color: var(--primary);
        }
    </style>
</head>

<body>

    <main class="auth-container">
        <div class="auth-card">
            <div class="row g-0">

                <!-- Left: Branding & Overview -->
                <div class="col-lg-5 d-none d-lg-flex brand-section">
                    <div>
                        <span class="brand-badge">
                            <i class="bi bi-shield-lock-fill me-1"></i> ENTERPRISE SUITE v3.0
                        </span>
                        <h1 class="brand-title">Global Institute of Management &amp; Technology</h1>
                        <p class="brand-desc">
                            Integrated Academic &amp; Administrative ERP automating Admissions, Ledger Accounting, Smart Attendance, Continuous Internal Evaluation &amp; Grade Tabulation.
                        </p>

                        <div class="feature-item">
                            <i class="bi bi-person-check-fill feature-icon"></i>
                            <div>
                                <strong>Admissions to Enrolment</strong>
                                <div class="text-white-50 small">Automated roll IDs &amp; ledger creation</div>
                            </div>
                        </div>

                        <div class="feature-item">
                            <i class="bi bi-cash-stack feature-icon"></i>
                            <div>
                                <strong>Double-Entry Student Ledgers</strong>
                                <div class="text-white-50 small">Formal receipts &amp; defaulter tracking</div>
                            </div>
                        </div>

                        <div class="feature-item">
                            <i class="bi bi-calendar2-check-fill feature-icon"></i>
                            <div>
                                <strong>Smart Attendance &amp; Hall Tickets</strong>
                                <div class="text-white-50 small">Real-time &lt; 75% blocking with Principal override</div>
                            </div>
                        </div>

                        <div class="feature-item">
                            <i class="bi bi-award-fill feature-icon"></i>
                            <div>
                                <strong>CIA &amp; Digital Grade Cards</strong>
                                <div class="text-white-50 small">Locked tabular marks entry with SGPA calculation</div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-top border-white-50 border-opacity-25 d-flex justify-content-between align-items-center">
                        <small class="text-white-50">&copy; <?= date('Y') ?> GIMT. AICTE &amp; MAKAUT Affiliated.</small>
                        <a href="index.php" class="text-white text-decoration-none small"><i class="bi bi-house me-1"></i> Public Home</a>
                    </div>
                </div>

                <!-- Right: Secure Login Form -->
                <div class="col-lg-7 form-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="h4 fw-bold mb-1">Institutional ERP Access</h2>
                            <p class="text-secondary small mb-0">Sign in to your role-authorized workspace</p>
                        </div>
                        <a href="admission_apply.php" class="btn btn-outline-warning btn-sm fw-semibold">
                            <i class="bi bi-pencil-square me-1"></i> Apply Admission
                        </a>
                    </div>

                    <?= display_flash() ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form id="loginForm" action="admin_login.php" method="post">
                        <?= csrf_field() ?>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Institutional Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="email" class="form-control" placeholder="user@gimt.ac.in" autocomplete="username" required>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••••••" autocomplete="current-password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div class="mb-4">
                            <label for="role" class="form-label">Designation / Role</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                <select name="role" id="role" class="form-select" required>
                                    <option value="" selected disabled>Select your institutional role</option>
                                    <option value="chairman">Chairman / Governing Body</option>
                                    <option value="principal">Principal</option>
                                    <option value="accounts">Accounts &amp; Finance</option>
                                    <option value="hr">HR Administrator</option>
                                    <option value="hod">Head of Department (HOD)</option>
                                    <option value="teacher">Faculty / Teacher</option>
                                    <option value="student">Enrolled Student</option>
                                </select>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary w-100 mb-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Secure Sign In
                        </button>
                    </form>

                    <!-- Quick Demo Role Switcher (One-Click Testing) -->
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-secondary text-uppercase"><i class="bi bi-key-fill text-warning me-1"></i> Quick Demo Logins (Password: Password@123)</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="demo-chip" onclick="fillCreds('chairman@gimt.ac.in', 'chairman')">👑 Chairman</span>
                            <span class="demo-chip" onclick="fillCreds('principal@gimt.ac.in', 'principal')">🎓 Principal</span>
                            <span class="demo-chip" onclick="fillCreds('accounts@gimt.ac.in', 'accounts')">💰 Accounts</span>
                            <span class="demo-chip" onclick="fillCreds('hr@gimt.ac.in', 'hr')">👥 HR Admin</span>
                            <span class="demo-chip" onclick="fillCreds('hod.cse@gimt.ac.in', 'hod')">🏛️ HOD (CSE)</span>
                            <span class="demo-chip" onclick="fillCreds('teacher.cse@gimt.ac.in', 'teacher')">👨‍🏫 Faculty</span>
                            <span class="demo-chip" onclick="fillCreds('student.cse@gimt.ac.in', 'student')">🎒 Student (Rahul)</span>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');
        const toggleIcon = document.querySelector('#toggleIcon');

        togglePassword.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });

        function fillCreds(email, role) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'Password@123';
            document.getElementById('role').value = role;
        }
    </script>
</body>
</html>
