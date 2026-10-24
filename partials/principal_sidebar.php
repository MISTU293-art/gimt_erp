<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user_name    = $_SESSION['user_name']  ?? '';
$user_email   = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr(trim($user_name), 0, 1));

$base        = '';
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

$menu = [
    ['type' => 'link', 'label' => 'Principal Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'principal_dashboard.php'],

    // ---------------- ADMISSIONS & ENROLLMENT ----------------
    ['type' => 'section', 'label' => 'ADMISSIONS & ONBOARDING'],
    ['type' => 'link', 'label' => 'Admissions Pipeline', 'icon' => 'bi-person-check-fill', 'href' => 'admissions_manage.php'],

    // ---------------- ACADEMIC MANAGEMENT ----------------
    ['type' => 'section', 'label' => 'ACADEMIC MANAGEMENT'],
    ['type' => 'group', 'id' => 'departmentMenu', 'label' => 'Curriculum & Setup', 'icon' => 'bi-building', 'items' => [
        ['label' => 'Departments & HODs',  'href' => 'departments.php'],
        ['label' => 'Courses & Credits Master', 'href' => 'courses_master.php'],
        ['label' => 'Faculty Workload Mapping', 'href' => 'faculty_workload.php'],
        ['label' => 'Semesters & Batches',  'href' => 'semesters.php'],
        ['label' => 'Syllabus Master',      'href' => 'syllabus.php'],
    ]],

    ['type' => 'group', 'id' => 'usersMenu', 'label' => 'Staff & People', 'icon' => 'bi-people', 'items' => [
        ['label' => 'Faculty & Staff Directory', 'href' => 'staffs.php'],
        ['label' => 'HOD Department Assign',    'href' => 'hod_assign.php'],
    ]],

    // ---------------- EXAMINATIONS & EVALUATION ----------------
    ['type' => 'section', 'label' => 'EXAMINATIONS & EVALUATION'],
    ['type' => 'group', 'id' => 'examMenu', 'label' => 'Internal Exams & CIA', 'icon' => 'bi-journal-check', 'items' => [
        ['label' => 'Assessment Series Setup', 'href' => 'exam_series.php'],
        ['label' => 'Tabular Marks Entry',     'href' => 'marks_entry.php'],
        ['label' => 'Consolidated Tabulation', 'href' => 'exam_tabulation.php'],
        ['label' => 'Digital Grade Cards',     'href' => 'grade_card.php'],
        ['label' => 'Hall Tickets & Override', 'href' => 'hall_tickets.php'],
    ]],

    // ---------------- ATTENDANCE & COURSEWORK ----------------
    ['type' => 'section', 'label' => 'ATTENDANCE & COURSEWORK'],
    ['type' => 'link', 'label' => 'Classroom Attendance',    'icon' => 'bi-calendar-check', 'href' => 'attendance_mark.php'],
    ['type' => 'link', 'label' => 'Attendance Analytics (<75%)', 'icon' => 'bi-graph-up', 'href' => 'attendance_analytics.php'],
    ['type' => 'link', 'label' => 'Leave & Condonations',   'icon' => 'bi-clipboard-check', 'href' => 'leave_requests.php'],
    ['type' => 'link', 'label' => 'Coursework Assignments', 'icon' => 'bi-journal-text',   'href' => 'assignments_manage.php'],

    // ---------------- MAKAUT COMPLIANCE ----------------
    ['type' => 'section', 'label' => 'MAKAUT COMPLIANCE'],
    ['type' => 'link', 'label' => 'MAR (100 Points) Portal', 'icon' => 'bi-award-fill', 'href' => 'makaut_mar.php'],
    ['type' => 'link', 'label' => 'MOOCs / SWAYAM / NPTEL', 'icon' => 'bi-mortarboard-fill', 'href' => 'makaut_moocs.php'],

    // ---------------- FINANCE & LEDGERS ----------------
    ['type' => 'section', 'label' => 'FINANCIAL OVERSIGHT'],
    ['type' => 'group', 'id' => 'financeMenu', 'label' => 'Fee & Ledgers', 'icon' => 'bi-wallet2', 'items' => [
        ['label' => 'Fee Structure Builder', 'href' => 'fee_structures.php'],
        ['label' => 'Fee Collections Desk',  'href' => 'fee_collect.php'],
        ['label' => 'Student Ledgers',        'href' => 'student_ledger.php'],
        ['label' => 'Overdue Defaulters',    'href' => 'fee_defaulters.php'],
    ]],
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

<!-- sidebar css -->
<style>
    .gimt-sidebar {
        --gimt-primary: #123b70;
        --gimt-primary-dark: #0a1f3d;
        --gimt-blue: #2563eb;
        --gimt-blue-light: #eff6ff;
        --sidebar-bg: #ffffff;
        --sidebar-text: #475569;
        --sidebar-muted: #94a3b8;
        --sidebar-border: #e2e8f0;
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

    .sidebar-header { flex: 0 0 auto; min-height: 70px; background: #fff; border-bottom: 1px solid var(--sidebar-border); }
    .brand-title { color: #172b4d; font-size: 1.05rem; font-weight: 800; line-height: 1.2; letter-spacing: .5px; }
    .brand-subtitle { color: var(--sidebar-muted); font-size: .65rem; font-weight: 700; letter-spacing: 1px; }
    .sidebar-body { flex: 1 1 auto; overflow-y: auto; padding: 14px 10px; }
    .menu-label { padding: 8px 12px 4px; color: var(--sidebar-muted); font-size: .68rem; font-weight: 700; letter-spacing: 1.1px; }
    .gimt-sidebar .nav-link { display: flex; align-items: center; gap: 10px; min-height: 40px; padding: 8px 12px; color: var(--sidebar-text); font-size: .85rem; font-weight: 600; border-radius: 8px; text-decoration: none; }
    .gimt-sidebar .nav-link:hover { color: var(--gimt-primary); background: var(--gimt-blue-light); }
    .gimt-sidebar .nav-link.active { color: #fff; background: var(--gimt-primary); }
    .sub-menu { margin: 4px 0 8px 13px; padding-left: 10px; border-left: 1px solid var(--sidebar-border); }
    .gimt-sidebar .sub-menu .nav-link { min-height: 35px; padding: 6px 10px; color: #64748b; font-size: .81rem; font-weight: 500; }
    .gimt-sidebar .sub-menu .nav-link.active, .gimt-sidebar .sub-menu .nav-link:hover { color: var(--gimt-primary); background: var(--gimt-blue-light); }
    .sidebar-footer { flex: 0 0 auto; padding: 14px; background: #f8fafc; border-top: 1px solid var(--sidebar-border); }
    .sidebar-toggle { position: fixed; top: 14px; left: 14px; z-index: 1040; width: 44px; height: 44px; display: grid; place-items: center; padding: 0; color: #fff; background: var(--gimt-primary); border: 0; border-radius: 9px; }
    @media (min-width: 992px) { .gimt-sidebar { position: sticky !important; top: 0 !important; align-self: flex-start; } .sidebar-toggle { display: none !important; } }
    @media (max-width: 991.98px) { .gimt-sidebar { width: 280px; min-width: 0; max-width: 85vw; } }
</style>

<button class="btn sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#principalSidebar" aria-controls="principalSidebar" aria-label="Open navigation">
    <i class="bi bi-list fs-3"></i>
</button>

<aside class="offcanvas-lg offcanvas-start gimt-sidebar" tabindex="-1" id="principalSidebar" aria-label="Principal Navigation">
    <div class="sidebar-header d-flex align-items-center justify-content-between p-3">
        <a href="principal_dashboard.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <div>
                <span class="brand-title d-block">GIMT ERP</span>
                <span class="brand-subtitle">PRINCIPAL'S EXECUTIVE SUITE</span>
            </div>
        </a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <nav class="sidebar-body" aria-label="Principal Menu">
        <?php foreach ($menu as $entry): ?>
            <?php if ($entry['type'] === 'section'): ?>
                <div class="menu-label mt-2"><?= sb_e($entry['label']) ?></div>
            <?php elseif ($entry['type'] === 'link'): ?>
                <?php $isActive = sb_active($entry['href'], $currentPage); ?>
                <ul class="nav flex-column mb-1">
                    <li class="nav-item">
                        <a class="nav-link<?= $isActive ? ' active' : '' ?>" href="<?= sb_e($entry['href']) ?>">
                            <i class="bi <?= sb_e($entry['icon']) ?>"></i>
                            <span><?= sb_e($entry['label']) ?></span>
                        </a>
                    </li>
                </ul>
            <?php elseif ($entry['type'] === 'group'): ?>
                <?php
                $anyActive = false;
                foreach ($entry['items'] as $it) {
                    if (sb_active($it['href'], $currentPage)) {
                        $anyActive = true;
                        break;
                    }
                }
                ?>
                <ul class="nav flex-column mb-1">
                    <li class="nav-item">
                        <a class="nav-link<?= $anyActive ? ' active' : '' ?>" data-bs-toggle="collapse" href="#<?= sb_e($entry['id']) ?>" role="button" aria-expanded="<?= $anyActive ? 'true' : 'false' ?>">
                            <i class="bi <?= sb_e($entry['icon']) ?>"></i>
                            <span><?= sb_e($entry['label']) ?></span>
                            <i class="bi bi-chevron-down ms-auto" style="font-size: .75rem;"></i>
                        </a>
                        <div class="collapse<?= $anyActive ? ' show' : '' ?>" id="<?= sb_e($entry['id']) ?>">
                            <ul class="nav flex-column sub-menu">
                                <?php foreach ($entry['items'] as $it): ?>
                                    <?php $itActive = sb_active($it['href'], $currentPage); ?>
                                    <li class="nav-item">
                                        <a class="nav-link<?= $itActive ? ' active' : '' ?>" href="<?= sb_e($it['href']) ?>">
                                            <?= sb_e($it['label']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <footer class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary text-white rounded-circle p-2 fw-bold text-center" style="width: 36px; height: 36px; line-height: 20px;">
                <?= sb_e($user_initial) ?>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <span class="d-block text-truncate fw-bold small text-dark"><?= sb_e($user_name) ?></span>
                <span class="d-block text-truncate text-muted" style="font-size: 0.72rem;"><?= sb_e($user_email) ?></span>
            </div>
            <a href="logout.php" class="text-danger p-2 text-decoration-none" title="Sign out">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </footer>
</aside>
