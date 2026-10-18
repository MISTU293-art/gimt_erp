<?php
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr(trim($user_name), 0, 1));
?>

<!-- sidebar css -->
<style>
    :root {
        --gimt-primary: #1f7df8;
        --gimt-primary-dark: #193458;
        --gimt-blue: #2563eb;
        --gimt-blue-light: #eff6ff;
        --sidebar-bg: #ffffff;
        --sidebar-text: #475569;
        --sidebar-muted: #94a3b8;
        --sidebar-border: #e2e8f0;
    }

    /* IMPORTANT: no ancestor of the sidebar may have overflow set, or sticky breaks */
    html,
    body {
        overflow-x: clip;
        /* use clip, NOT hidden, so sticky keeps working */
    }

    .page-layout,
    .dashboard-layout {
        display: flex;
        align-items: flex-start;
        /* lets the sidebar keep its own height instead of stretching */
        min-height: 100vh;
    }

    .dashboard-main {
        flex: 1 1 auto;
        min-width: 0;
    }

    .gimt-sidebar {
        width: 270px;
        min-width: 270px;
        height: 100vh;
        height: 100dvh;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        color: var(--sidebar-text);
        background: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        z-index: 1045;
    }

    .sidebar-header {
        flex: 0 0 auto;
        min-height: 78px;
        background: #fff;
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
        font-size: .59rem;
        font-weight: 800;
        letter-spacing: .65px;
    }

    .sidebar-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 18px 12px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #fff;
        overscroll-behavior: contain;
    }

    .sidebar-body::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .menu-label {
        margin: 14px 10px 9px;
        color: var(--sidebar-muted);
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: 1px;
    }

    .gimt-sidebar .nav {
        gap: 3px;
    }

    .gimt-sidebar .nav-link {
        min-height: 43px;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 12px;
        border-radius: 7px;
        color: var(--sidebar-text);
        font-size: .86rem;
        font-weight: 600;
        text-decoration: none;
        transition: background .2s, color .2s;
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
        color: #fff;
        background: var(--gimt-primary);
    }

    .gimt-sidebar .nav-link.active>i:first-child {
        color: #fff;
    }

    .menu-arrow {
        margin-left: auto;
        font-size: .75rem !important;
        transition: transform .2s;
    }

    .gimt-sidebar .nav-link[aria-expanded="true"] .menu-arrow {
        transform: rotate(180deg);
    }

    .sub-menu {
        margin: 4px 0 8px 13px;
        padding-left: 10px;
        border-left: 1px solid var(--sidebar-border);
    }

    .gimt-sidebar .sub-menu .nav-link {
        min-height: 37px;
        padding: 8px 10px;
        color: #64748b;
        font-size: .81rem;
        font-weight: 500;
    }

    .gimt-sidebar .sub-menu .nav-link.active,
    .gimt-sidebar .sub-menu .nav-link:hover {
        color: var(--gimt-blue);
        background: var(--gimt-blue-light);
    }

    .sidebar-footer {
        flex: 0 0 auto;
        padding: 14px;
        background: #fff;
        border-top: 1px solid var(--sidebar-border);
    }

    .admin-avatar {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: var(--gimt-primary);
        font-size: .85rem;
        font-weight: 800;
    }

    .admin-name {
        color: #172b4d;
        font-size: .82rem;
        font-weight: 700;
    }

    .admin-email {
        color: #64748b;
        font-size: .70rem;
    }

    .logout-btn {
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        color: #64748b;
        border-radius: 8px;
        text-decoration: none;
    }

    .logout-btn:hover {
        color: #dc2626;
        background: #fef2f2;
    }

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
        color: #fff;
        background: var(--gimt-primary);
        border: 0;
        border-radius: 9px;
    }

    /* ===== Desktop: sidebar stays fixed while the page scrolls ===== */
    @media (min-width: 992px) {
        .gimt-sidebar {
            position: sticky !important;
            /* overrides Bootstrap's .offcanvas-lg { position: static } */
            top: 0 !important;
            align-self: flex-start;
            height: 100vh !important;
            height: 100dvh !important;
            transform: none !important;
            visibility: visible !important;
        }

        .sidebar-toggle {
            display: none !important;
        }
    }

    /* ===== Mobile ===== */
    @media (max-width: 991.98px) {
        .gimt-sidebar {
            width: 280px;
            min-width: 0;
            max-width: 85vw;
            height: 100vh;
            height: 100dvh;
        }

        .dashboard-main {
            width: 100%;
            min-width: 0;
            padding: 76px 16px 24px;
        }
    }
</style>

<!-- Mobile Sidebar Toggle -->
<button
    class="btn sidebar-toggle d-lg-none"
    type="button"
    data-bs-toggle="offcanvas"
    data-bs-target="#gimtSidebar"
    aria-controls="gimtSidebar"
    aria-label="Open navigation">
    <i class="bi bi-list fs-3"></i>
</button>

<!-- GIMT ERP Sidebar -->
<aside
    class="offcanvas-lg offcanvas-start gimt-sidebar"
    tabindex="-1"
    id="gimtSidebar"
    aria-label="GIMT ERP navigation">

    <!-- Brand Header -->
    <div class="sidebar-header d-flex align-items-center justify-content-between p-3">
        <a href="/public/admin_dashboard.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <img
                src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS6fl6HJwnR00yvYnEkcHN40chI3gK2pcMsmjf4GaiePt7T-xTC0Kac4Tg&s=10"
                alt="GIMT Logo"
                class="brand-logo">

            <div>
                <span class="brand-title d-block">GIMT ERP</span>
                <span class="brand-subtitle">ADMINISTRATION PORTAL</span>
            </div>
        </a>

        <button
            type="button"
            class="btn-close d-lg-none"
            data-bs-dismiss="offcanvas"
            data-bs-target="#gimtSidebar"
            aria-label="Close navigation"></button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-body" aria-label="Main navigation">

        <!-- Main Menu -->


        <ul class="nav flex-column mb-4">
            <li class="nav-item">
                <a class="nav-link" href="/public/admin_dashboard.php">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Academic Management -->
        <div class="menu-label">ACADEMIC MANAGEMENT</div>

        <ul class="nav flex-column mb-4">
            <li class="nav-item">
                <a
                    class="nav-link"
                    data-bs-toggle="collapse"
                    href="#academicMenu"
                    role="button"
                    aria-expanded="false"
                    aria-controls="academicMenu">
                    <i class="bi bi-mortarboard"></i>
                    <span>Academic Setup</span>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <div class="collapse" id="academicMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="departments.html">Departments &amp; Courses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="semesters.html">Semesters &amp; Batches</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="subjects.html">Subjects &amp; Syllabus</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a
                    class="nav-link"
                    data-bs-toggle="collapse"
                    href="#usersMenu"
                    role="button"
                    aria-expanded="false"
                    aria-controls="usersMenu">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <div class="collapse" id="usersMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="students.html">Students</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="staff_register.php">Faculty &amp; Staffs Registration</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="staffs.php">Faculty &amp; Staffs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admins.html">College Admins</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="roles-permissions.html">Roles &amp; Permissions</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="admissions.html">
                    <i class="bi bi-person-plus"></i>
                    <span>Admissions</span>
                </a>
            </li>

            <li class="nav-item">
                <a
                    class="nav-link"
                    data-bs-toggle="collapse"
                    href="#examMenu"
                    role="button"
                    aria-expanded="false"
                    aria-controls="examMenu">
                    <i class="bi bi-journal-check"></i>
                    <span>Examinations</span>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <div class="collapse" id="examMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="exam-schedules.html">Exam Schedules</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="marks-entry.html">Marks &amp; Grades</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="results.html">Publish Results</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

        <!-- Operations and Finance -->
        <div class="menu-label">OPERATIONS &amp; FINANCE</div>

        <ul class="nav flex-column mb-4">
            <li class="nav-item">
                <a
                    class="nav-link"
                    data-bs-toggle="collapse"
                    href="#financeMenu"
                    role="button"
                    aria-expanded="false"
                    aria-controls="financeMenu">
                    <i class="bi bi-wallet2"></i>
                    <span>Fee &amp; Finance</span>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <div class="collapse" id="financeMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link" href="fee-structures.html">Fee Structures</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fee-collections.html">Fee Collections</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="scholarships.html">Scholarships</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="attendance.html">
                    <i class="bi bi-calendar-check"></i>
                    <span>Attendance Tracking</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="routine.html">
                    <i class="bi bi-clock-history"></i>
                    <span>Class Timetable</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="notices.html">
                    <i class="bi bi-megaphone"></i>
                    <span>Notices &amp; Announcements</span>
                </a>
            </li>
        </ul>

        <!-- System Settings -->
        <div class="menu-label">SYSTEM SETTINGS</div>

        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link" href="system-logs.html">
                    <i class="bi bi-shield-check"></i>
                    <span>Audit Logs</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="settings.html">
                    <i class="bi bi-gear"></i>
                    <span>ERP Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Footer -->
    <footer class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="admin-avatar">
                <?= htmlspecialchars($user_initial, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="flex-grow-1 overflow-hidden">
                <span class="admin-name d-block text-truncate">
                    <?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>
                </span>

                <span class="admin-email d-block text-truncate">
                    <?= htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <a href="/public/logout.php"
                class="logout-btn"
                title="Logout"
                aria-label="Logout">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </footer>
</aside>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('gimtSidebar');
        if (!sidebar) return;

        // Highlight the active link and open its parent submenu
        sidebar.querySelectorAll('.nav-link[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#')) return;

            const url = new URL(link.href, window.location.href);

            if (url.origin === window.location.origin &&
                url.pathname === window.location.pathname) {

                link.classList.add('active');
                link.setAttribute('aria-current', 'page');

                const parentMenu = link.closest('.collapse');
                if (parentMenu) {
                    parentMenu.classList.add('show');

                    const toggle = sidebar.querySelector('[href="#' + parentMenu.id + '"]');
                    if (toggle) {
                        toggle.classList.add('active');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                }
            }
        });

        // Close the mobile sidebar after clicking a normal navigation link
        sidebar.querySelectorAll('.nav-link:not([data-bs-toggle="collapse"])').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    const instance = bootstrap.Offcanvas.getInstance(sidebar);
                    if (instance) instance.hide();
                }
            });
        });
    });
</script>