<?php
include('./config/db.config.php');
require_once __DIR__ . '/middleware/isLoggedIn.php';
require_once __DIR__ . '/middleware/chairmanOnly.php';

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
// total users
$totalUser = $conn->prepare("SELECT COUNT(*) AS total FROM users");
$totalUser->execute();

$result = $totalUser->get_result();
$row = $result->fetch_assoc();

$totalUsers = (int) $row['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GIMT ERP | Dashboard</title>
    <link rel="icon" type="image/jpeg" href="/public/images/logo.jpg">
    <link rel="apple-touch-icon" href="/php/images/images.jpg">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">


    <style>
        :root {
            --gimt-primary: #123b70;
            --gimt-primary-dark: #09264b;
            --gimt-blue: #2563eb;
            --gimt-blue-light: #eff6ff;

            --sidebar-bg: #ffffff;
            --sidebar-text: #475569;
            --sidebar-muted: #94a3b8;
            --sidebar-border: #e2e8f0;

            --page-bg: #f5f7fb;
            --card-bg: #ffffff;
            --text-primary: #172b4d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page-bg);
            color: var(--text-primary);
            font-family: "Segoe UI", Tahoma, sans-serif;
        }

        /* Main layout */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        .dashboard-main {
            flex: 1;
            min-width: 0;
            padding: 30px;
        }

        /* Sidebar */
        .gimt-sidebar {
            width: 265px;
            height: 100vh;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            color: var(--sidebar-text);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            z-index: 1045;
        }

        .sidebar-header {
            min-height: 78px;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            padding: 3px;
            object-fit: contain;
            border: 1px solid var(--sidebar-border);
            border-radius: 9px;
        }

        .brand-title {
            color: var(--gimt-primary-dark);
            font-size: 1.05rem;
            font-weight: 800;
        }

        .brand-subtitle {
            display: block;
            margin-top: 4px;
            color: var(--gimt-blue);
            font-size: 0.59rem;
            font-weight: 800;
            letter-spacing: 0.65px;
        }

        /* Scrollable navigation */
        .sidebar-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 18px 12px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #ffffff;
        }

        .menu-label {
            margin: 12px 10px 8px;
            color: var(--sidebar-muted);
            font-size: 0.67rem;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .gimt-sidebar .nav {
            gap: 3px;
        }

        .gimt-sidebar .nav-link {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 7px;
            color: var(--sidebar-text);
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .gimt-sidebar .nav-link>i:first-child {
            width: 19px;
            flex-shrink: 0;
            color: #64748b;
            font-size: 1.05rem;
            text-align: center;
        }

        .gimt-sidebar .nav-link:hover {
            color: var(--gimt-primary);
            background: var(--gimt-blue-light);
        }

        .gimt-sidebar .nav-link:hover>i:first-child {
            color: var(--gimt-blue);
        }

        .gimt-sidebar .nav-link.active {
            color: #ffffff;
            background: var(--gimt-primary);
        }

        .gimt-sidebar .nav-link.active>i:first-child {
            color: #ffffff;
        }

        .menu-arrow {
            margin-left: auto;
            font-size: 0.75rem !important;
            transition: transform 0.2s ease;
        }

        .gimt-sidebar .nav-link[aria-expanded="true"] .menu-arrow {
            transform: rotate(180deg);
        }

        /* Submenus */
        .sub-menu {
            margin: 4px 0 8px 13px;
            padding-left: 10px;
            border-left: 1px solid var(--sidebar-border);
        }

        .gimt-sidebar .sub-menu .nav-link {
            min-height: 36px;
            padding: 8px 10px;
            color: #64748b;
            font-size: 0.81rem;
            font-weight: 500;
        }

        .gimt-sidebar .sub-menu .nav-link:hover,
        .gimt-sidebar .sub-menu .nav-link.active {
            color: var(--gimt-blue);
            background: var(--gimt-blue-light);
        }

        /* Sidebar footer */
        .sidebar-footer {
            flex-shrink: 0;
            background: #ffffff;
            border-top: 1px solid var(--sidebar-border);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #ffffff;
            background: var(--gimt-primary);
            font-size: 0.85rem;
            font-weight: 800;
        }

        .admin-name {
            color: var(--text-primary);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .admin-email {
            color: #64748b;
            font-size: 0.70rem;
        }

        .logout-btn {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: #64748b;
            border-radius: 8px;
            text-decoration: none;
        }

        .logout-btn:hover {
            color: #dc2626;
            background: #fef2f2;
        }

        /* Mobile toggle */
        .sidebar-toggle {
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 1040;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            padding: 0;
            color: #ffffff;
            background: var(--gimt-primary);
            border: none;
            border-radius: 9px;
            box-shadow: 0 4px 12px rgb(15 23 42 / 12%);
        }

        .sidebar-toggle:hover {
            color: #ffffff;
            background: var(--gimt-blue);
        }

        /* Dashboard content components */
        .page-title {
            color: var(--text-primary);
            font-size: 1.65rem;
            font-weight: 750;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 0.9rem;
        }

        .dashboard-card {
            height: 100%;
            padding: 22px;
            background: var(--card-bg);
            border: 1px solid #e8edf4;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgb(15 23 42 / 3%);
        }

        .dashboard-card-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            color: var(--gimt-primary);
            background: var(--gimt-blue-light);
            font-size: 1.3rem;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            color: var(--gimt-primary);
            background: var(--gimt-blue-light);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .dashboard-header {
            border-color: #e5e7eb !important;
        }

        .header-icon {
            width: 54px;
            height: 54px;
            flex-shrink: 0;
            background: #eaf1ff;
            color: #1746a2;
        }

        .page-title {
            font-size: clamp(1.4rem, 3vw, 1.8rem);
            color: #172554;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            font-size: 0.95rem;
        }

        .status-badge {
            background: #eaf7ef;
            color: #166534;
            border: 1px solid #bbf7d0;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .dashboard-header .btn {
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .dashboard-header .btn:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 576px) {
            .dashboard-header {
                align-items: flex-start !important;
            }

            .dashboard-header>div:last-child {
                width: 100%;
            }

            .dashboard-header .btn {
                flex: 1;
                justify-content: center;
            }
        }


        /* Desktop */
        @media (min-width: 992px) {
            .gimt-sidebar {
                position: sticky;
                top: 0;
            }

            .sidebar-toggle {
                display: none !important;
            }
        }

        /* Mobile and tablet */
        @media (max-width: 991.98px) {
            .gimt-sidebar {
                width: 280px;
                max-width: 85vw;
                height: 100vh;
                min-height: 0;
            }

            .dashboard-main {
                width: 100%;
                padding: 76px 16px 24px;
            }

            .page-title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>

<body>

    <div class="dashboard-layout">

        <!-- Sidebar component goes here -->
        <?php include('./partials/superAdmin_sidebar.php') ?>
        <main class="dashboard-main">

            <!-- Page header -->

            <header class="dashboard-header d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 p-md-4 mb-4 rounded-4 border bg-white shadow-sm">

                <!-- Left: Dashboard information -->
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon d-flex align-items-center justify-content-center rounded-3">
                        <i class="bi bi-grid-1x2-fill fs-4"></i>
                    </div>

                    <div>
                        <h1 class="page-title fw-bold mb-1">Dashboard</h1>

                        <p class="page-subtitle text-secondary mb-2">
                            Welcome, <strong class="text-dark"><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                            to GIMT ERP Administration.
                        </p>

                        <span class="text-secondary small">
                            <i class="bi bi-calendar3 me-1"></i>
                            <?php echo date('l, d M Y'); ?>
                        </span>
                    </div>
                </div>

                <!-- Right: Role and logout -->
                <div class="d-flex align-items-center flex-wrap gap-2">

                    <span class="status-badge d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill">
                        <i class="bi bi-shield-check"></i>
                        <?php echo htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8'); ?>
                    </span>

                    <a href="logout.php"
                        class="btn btn-outline-danger d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3"
                        onclick="return confirm('Are you sure you want to log out?');">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>

                </div>

            </header>




            <!-- Dashboard statistics -->
            <section class="row g-3 mb-4">

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="dashboard-card-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <span class="text-secondary small">Users</span>
                        </div>

                        <h2 class="h3 fw-bold mb-1"><?= $totalUsers ?></h2>
                        <p class="text-secondary small mb-0">Registered users</p>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="dashboard-card-icon">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                            <span class="text-secondary small">Academic</span>
                        </div>

                        <h2 class="h3 fw-bold mb-1">0.0</h2>
                        <p class="text-secondary small mb-0">Courses and departments</p>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="dashboard-card-icon">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <span class="text-secondary small">Faculty</span>
                        </div>

                        <h2 class="h3 fw-bold mb-1">0.0</h2>
                        <p class="text-secondary small mb-0">Faculty and staff</p>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="dashboard-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="dashboard-card-icon">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <span class="text-secondary small">Finance</span>
                        </div>

                        <h2 class="h3 fw-bold mb-1">0.0</h2>
                        <p class="text-secondary small mb-0">Fee collections</p>
                    </article>
                </div>

            </section>

            <!-- Main content section -->
            <section class="dashboard-card">

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="h5 fw-bold mb-0">System Overview</h2>

                    <span class="text-secondary small">
                        <i class="bi bi-info-circle me-1"></i>
                        Connect your database to load live data
                    </span>
                </div>

                <p class="text-secondary mb-0">
                    Welcome to the GIMT ERP administration workspace.
                    Select a module from the sidebar to manage academic
                    records, students, faculty, examinations, and finance.
                </p>

            </section>

        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Update this active link on each page.
        // Dashboard is active on dashboard.html.
        document.querySelectorAll("#gimtSidebar .nav-link").forEach(link => {
            if (link.getAttribute("href") === window.location.pathname.split("/").pop()) {
                document.querySelectorAll("#gimtSidebar .nav-link").forEach(item => {
                    item.classList.remove("active");
                    item.removeAttribute("aria-current");
                });

                link.classList.add("active");
                link.setAttribute("aria-current", "page");

                const parentMenu = link.closest(".collapse");

                if (parentMenu) {
                    parentMenu.classList.add("show");

                    const toggle = document.querySelector(
                        '[href="#' + parentMenu.id + '"]'
                    );

                    if (toggle) {
                        toggle.classList.add("active");
                        toggle.setAttribute("aria-expanded", "true");
                    }
                }
            }
        });

        // Close mobile sidebar after clicking a normal navigation link.
        document.querySelectorAll(
            "#gimtSidebar .nav-link:not([data-bs-toggle='collapse'])"
        ).forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 992) {
                    const sidebar = document.getElementById("gimtSidebar");
                    const instance = bootstrap.Offcanvas.getInstance(sidebar);

                    if (instance) {
                        instance.hide();
                    }
                }
            });
        });
    </script>

</body>

</html>