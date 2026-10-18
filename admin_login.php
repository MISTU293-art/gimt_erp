<?php
session_start();

require_once __DIR__ . '/config/db.config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $allowedRoles = [
        'chairman',
        'principal',
        'accounts',
        'hr',
        'hod',
        'teacher'
    ];

    if ($email === '' || $password === '' || $role === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Please select a valid designation.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role, status
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
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                //  cookie set
                setcookie("Username", $email, time() + (86400 * 30), "/");
                // Redirect based on role
                $dashboards = [
                    'chairman' => 'admin_dashboard.php',
                    'principal' => 'principal_dashboard.php',
                    'accounts' => 'accounts_dashboard.php',
                    'hr' => 'hr_dashboard.php',
                    'hod' => 'hod_dashboard.php',
                    'teacher' => 'teacher_dashboard.php'
                ];

                header('Location: ' . $dashboards[$role]);
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
    <meta name="theme-color" content="#e8731f">
    <meta name="description" content="Global Institute of Management and Technology (GIMT), Krishnagar, Nadia. B.Tech and Diploma engineering programs affiliated to MAKAUT and approved by AICTE.">

    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link rel="apple-touch-icon" href="/php/images/images.jpg">
    <title>GIMT ERP | Login</title>

    <link rel="icon" type="image/jpeg" href="/images/logo.jpg">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <style>
        :root {
            --orange: #e8731f;
            --orange-dark: #c2410c;
            --page-bg: #f2f5fa;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 80vh;
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, #e0eaff, transparent 40%),
                var(--page-bg);
        }

        .login-wrapper {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 16px;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            border: 1px solid #e7edf5;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 20px 60px rgba(18, 59, 112, 0.12);
        }

        .login-header {
            padding: 30px 28px 27px;
            color: #ffffff;
            text-align: center;
            background: linear-gradient(135deg,
                    var(--orange-dark),
                    var(--orange));
        }

        .brand-icon {
            width: 68px;
            height: 68px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 18px;
            color: var(--accent);
            font-size: 34px;
        }

        .login-header h1 {
            margin-bottom: 6px;
            font-size: 28px;
            font-weight: 750;
            letter-spacing: 0.5px;
        }

        .login-header p {
            margin: 0;
            color: #d5e2f5;
            font-size: 14px;
        }

        .login-body {
            padding: 30px;
        }

        .welcome-title {
            color: #18283d;
            font-size: 21px;
            font-weight: 750;
        }

        .form-label {
            margin-bottom: 8px;
            color: #26374c;
            font-size: 14px;
            font-weight: 650;
        }

        .input-group-text {
            min-width: 46px;
            justify-content: center;
            color: #64748b;
            background: #f8fafc;
            border-color: #dce3ed;
        }

        .form-control,
        .form-select {
            min-height: 48px;
            border-color: #dce3ed;
            font-size: 14px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(18, 59, 112, 0.10);
        }

        .password-toggle {
            border-color: #dce3ed;
            color: #64748b;
        }

        .password-toggle:hover {
            color: var(--primary);
            background: #f8fafc;
        }

        .login-btn {
            min-height: 49px;
            border: 0;
            border-radius: 9px;
            color: #ffffff;
            background: var(--primary);
            font-size: 15px;
            font-weight: 650;
            transition: background 0.2s, transform 0.2s;
        }

        .login-btn:hover {
            color: #ffffff;
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .security-note {
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        .login-footer {
            padding: 15px;
            border-top: 1px solid #edf0f5;
            color: #718096;
            background: #fbfcfe;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 480px) {
            .login-body {
                padding: 24px 20px;
            }

            .login-header {
                padding: 28px 20px 23px;
            }
        }
    </style>
</head>

<body>

    <main class="login-wrapper">

        <section class="login-card">

            <!-- Login Header -->
            <header class="login-header">

                <div class="brand-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>

                <h1>GIMT ERP</h1>

                <p>Institutional Management Portal</p>

            </header>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="login-body">

                <div class="mb-4">
                    <h2 class="welcome-title mb-1">Welcome Back</h2>

                    <p class="text-secondary small mb-0">
                        Sign in to access your institutional dashboard.
                    </p>
                </div>

                <form id="loginForm" action="" method="post">

                    <!-- Email -->
                    <div class="mb-3">

                        <label for="email" class="form-label">
                            Email Address
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                placeholder="name@gimt.edu.in"
                                autocomplete="username"
                                required>

                        </div>

                    </div>

                    <!-- Password -->
                    <div class="mb-3">

                        <label for="password" class="form-label">
                            Password
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required>

                            <button
                                class="btn password-toggle"
                                type="button"
                                id="togglePassword"
                                aria-label="Show password">

                                <i class="bi bi-eye" id="passwordIcon"></i>

                            </button>

                        </div>

                    </div>

                    <!-- Role Selection -->
                    <div class="mb-4">

                        <label for="role" class="form-label">
                            Login As
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-person-badge"></i>
                            </span>

                            <select
                                name="role"
                                id="role"
                                class="form-select"
                                required>

                                <option value="" selected disabled>
                                    Select your designation
                                </option>

                                <option value="chairman">
                                    Chairman
                                </option>

                                <option value="principal">
                                    Principal
                                </option>

                                <option value="accounts">
                                    Accounts Department
                                </option>

                                <option value="hr">
                                    HR Department
                                </option>

                                <option value="hod">
                                    Head of Department (HOD)
                                </option>

                                <option value="teacher">
                                    Teacher
                                </option>

                            </select>

                        </div>

                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn  w-100 btn-primary">

                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Sign In

                    </button>
                    <!-- back home button -->


                    <a href="index.php" class="btn btn-secondary w-100 mt-1">Back Home </a>



                </form>

                <!-- Demo Message -->
                <div id="loginMessage" class="mt-3" aria-live="polite"></div>

                <!-- Security Information -->
                <div class="security-note mt-4">

                    <i class="bi bi-shield-lock-fill me-1"></i>
                    Authorized institutional access only

                </div>

            </div>

            <!-- Footer -->
            <footer class="login-footer">

                &copy; <span id="currentYear"></span> GIMT.
                All rights reserved.

            </footer>

        </section>

    </main>

    <script>
        // Show or hide password
        const togglePassword = document.getElementById("togglePassword");
        const passwordInput = document.getElementById("password");
        const passwordIcon = document.getElementById("passwordIcon");

        togglePassword.addEventListener("click", function() {

            const isPassword = passwordInput.type === "password";

            passwordInput.type = isPassword ? "text" : "password";

            passwordIcon.classList.toggle("bi-eye", !isPassword);
            passwordIcon.classList.toggle("bi-eye-slash", isPassword);

            togglePassword.setAttribute(
                "aria-label",
                isPassword ? "Hide password" : "Show password"
            );

        });

        // Automatically update copyright year
        document.getElementById("currentYear").textContent =
            new Date().getFullYear();
    </script>

</body>

</html>