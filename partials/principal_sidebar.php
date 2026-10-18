<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user_name    = $_SESSION['user_name']  ?? '';
$user_email   = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr(trim($user_name), 0, 1));

$base        = '/public/';
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/*
 * PRINCIPAL MENU
 * Add or remove items here. Anything not listed is not shown.
 * Left out on purpose: staff registration, roles & permissions,
 * college admins, fee structures, scholarships, marks entry,
 * audit logs and ERP settings.
 */
$menu = [
    ['type' => 'link', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => 'principal_dashboard.php'],

    // ---------------- ACADEMIC MANAGEMENT ----------------
    ['type' => 'section', 'label' => 'ACADEMIC MANAGEMENT'],

    ['type' => 'group', 'id' => 'departmentMenu', 'label' => 'Departments', 'icon' => 'bi-building', 'items' => [
        ['label' => 'Create Department',      'href' => 'department_create.php'],
        ['label' => 'Departments & Courses',  'href' => 'departments.html'],
    ]],

    ['type' => 'group', 'id' => 'academicMenu', 'label' => 'Academic Setup', 'icon' => 'bi-mortarboard', 'items' => [
        ['label' => 'Semesters & Batches',  'href' => 'semesters.html'],
        ['label' => 'Subjects & Syllabus',  'href' => 'subjects.html'],
    ]],

    ['type' => 'group', 'id' => 'usersMenu', 'label' => 'People', 'icon' => 'bi-people', 'items' => [
        ['label' => 'Students',          'href' => 'students.html'],
        ['label' => 'Faculty & Staffs',  'href' => 'staffs.php'],
    ]],

    ['type' => 'link', 'label' => 'Admissions', 'icon' => 'bi-person-plus', 'href' => 'admissions.html'],

    ['type' => 'group', 'id' => 'examMenu', 'label' => 'Examinations', 'icon' => 'bi-journal-check', 'items' => [
        ['label' => 'Exam Schedules',  'href' => 'exam-schedules.html'],
        ['label' => 'Publish Results', 'href' => 'results.html'],
    ]],

    // ---------------- CERTIFICATES & RECORDS ----------------
    ['type' => 'section', 'label' => 'CERTIFICATES & RECORDS'],

    ['type' => 'group', 'id' => 'certificateMenu', 'label' => 'Certificates', 'icon' => 'bi-patch-check', 'items' => [
        ['label' => 'Transfer Certificate (TC)', 'href' => 'tc_requests.php'],
        ['label' => 'Bonafide Certificate',      'href' => 'bonafide_requests.php'],
        ['label' => 'Character Certificate',     'href' => 'character_certificates.php'],
        ['label' => 'Issued Certificates',       'href' => 'issued_certificates.php'],
    ]],

    // ---------------- COMMUNICATION ----------------
    ['type' => 'section', 'label' => 'COMMUNICATION'],

    ['type' => 'group', 'id' => 'noticeMenu', 'label' => 'Notices', 'icon' => 'bi-megaphone', 'items' => [
        ['label' => 'Create Notice', 'href' => 'notice_create.php'],
        ['label' => 'All Notices',   'href' => 'notices.html'],
    ]],

    ['type' => 'link', 'label' => 'Events & Calendar', 'icon' => 'bi-calendar-event', 'href' => 'events.php'],

    // ---------------- OPERATIONS ----------------
    ['type' => 'section', 'label' => 'OPERATIONS'],

    ['type' => 'link', 'label' => 'Fee Collections',     'icon' => 'bi-wallet2',        'href' => 'fee-collections.html'],
    ['type' => 'link', 'label' => 'Attendance Tracking', 'icon' => 'bi-calendar-check', 'href' => 'attendance.html'],
    ['type' => 'link', 'label' => 'Class Timetable',     'icon' => 'bi-clock-history',  'href' => 'routine.html'],
    ['type' => 'link', 'label' => 'Leave Approvals',     'icon' => 'bi-clipboard-check','href' => 'leave_requests.php'],
    ['type' => 'link', 'label' => 'Reports',             'icon' => 'bi-bar-chart-line', 'href' => 'reports.php'],
];

function sb_e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function sb_active(string $href, string $current): bool
{
    return basename($href) === $current;
}
?>

<!-- sidebar css (variables are scoped to the sidebar so they don't change page colors) -->
<style>
    .gimt-sidebar {
        --gimt-primary: #1f7df8;
        --gimt-primary-dark: #193458;
        --gimt-blue: #2563eb;
        --gimt-blue-light: #eff6ff;
        --sidebar-bg: #ffffff;
        --sidebar-text: #475569;
        --sidebar-muted: #94a3b8;
        --sidebar-border: #e2e8f0;
    }

    /* no ancestor of the sidebar may use overflow hidden, or sticky breaks */
    html,
    body {
        overflow-x: clip;
    }

    .page-layout,
    .dashboard-layout {
        display: flex;
        align-items: flex-start;
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
        background: #1f7df8;
        border: 0;
        border-radius: 9px;
    }

    /* Desktop: sidebar stays fixed while the page scrolls */
    @media (min-width: 992px) {
        .gimt-sidebar {
            position: sticky !important;
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

    /* Mobile */
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

<!-- Mobile sidebar toggle -->
<button class="btn sidebar-toggle d-lg-none" type="button"
    data-bs-toggle="offcanvas" data-bs-target="#gimtSidebar"
    aria-controls="gimtSidebar" aria-label="Open navigation">
    <i class="bi bi-list fs-3"></i>
</button>

<!-- GIMT ERP Principal Sidebar -->
<aside class="offcanvas-lg offcanvas-start gimt-sidebar" tabindex="-1" id="gimtSidebar"
    aria-label="GIMT ERP navigation">

    <div class="sidebar-header d-flex align-items-center justify-content-between p-3">
        <a href="<?= sb_e($base . 'principal_dashboard.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
            <img src="/public/images/logo.jpg" alt="GIMT Logo" class="brand-logo">
            <div>
                <span class="brand-title d-block">GIMT ERP</span>
                <span class="brand-subtitle">PRINCIPAL PORTAL</span>
            </div>
        </a>

        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas"
            data-bs-target="#gimtSidebar" aria-label="Close navigation"></button>
    </div>

    <nav class="sidebar-body" aria-label="Main navigation">
        <?php
        $listOpen = false;

        foreach ($menu as $item):

            if ($item['type'] === 'section'):
                if ($listOpen) { echo '</ul>'; $listOpen = false; } ?>
                <div class="menu-label"><?= sb_e($item['label']) ?></div>
            <?php
                continue;
            endif;

            if (!$listOpen) { echo '<ul class="nav flex-column mb-3">'; $listOpen = true; }

            if ($item['type'] === 'link'):
                $active = sb_active($item['href'], $currentPage); ?>
                <li class="nav-item">
                    <a class="nav-link<?= $active ? ' active' : '' ?>"
                        href="<?= sb_e($base . $item['href']) ?>"
                        <?= $active ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= sb_e($item['icon']) ?>"></i>
                        <span><?= sb_e($item['label']) ?></span>
                    </a>
                </li>
            <?php
            elseif ($item['type'] === 'group'):
                $groupActive = false;
                foreach ($item['items'] as $child) {
                    if (sb_active($child['href'], $currentPage)) { $groupActive = true; break; }
                } ?>
                <li class="nav-item">
                    <a class="nav-link<?= $groupActive ? ' active' : '' ?>"
                        data-bs-toggle="collapse" href="#<?= sb_e($item['id']) ?>" role="button"
                        aria-expanded="<?= $groupActive ? 'true' : 'false' ?>"
                        aria-controls="<?= sb_e($item['id']) ?>">
                        <i class="bi <?= sb_e($item['icon']) ?>"></i>
                        <span><?= sb_e($item['label']) ?></span>
                        <i class="bi bi-chevron-down menu-arrow"></i>
                    </a>

                    <div class="collapse<?= $groupActive ? ' show' : '' ?>" id="<?= sb_e($item['id']) ?>">
                        <ul class="nav flex-column sub-menu">
                            <?php foreach ($item['items'] as $child):
                                $childActive = sb_active($child['href'], $currentPage); ?>
                                <li class="nav-item">
                                    <a class="nav-link<?= $childActive ? ' active' : '' ?>"
                                        href="<?= sb_e($base . $child['href']) ?>"
                                        <?= $childActive ? 'aria-current="page"' : '' ?>>
                                        <?= sb_e($child['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
        <?php
            endif;
        endforeach;

        if ($listOpen) echo '</ul>';
        ?>
    </nav>

    <footer class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="admin-avatar"><?= sb_e($user_initial) ?></div>

            <div class="flex-grow-1 overflow-hidden">
                <span class="admin-name d-block text-truncate"><?= sb_e($user_name) ?></span>
                <span class="admin-email d-block text-truncate"><?= sb_e($user_email) ?></span>
            </div>

            <a href="/public/logout.php" class="logout-btn" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </footer>
</aside>

<script>
    // Close the mobile sidebar after clicking a normal link
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('gimtSidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('.nav-link:not([data-bs-toggle="collapse"])').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992 && window.bootstrap) {
                    const instance = bootstrap.Offcanvas.getInstance(sidebar);
                    if (instance) instance.hide();
                }
            });
        });
    });
</script>