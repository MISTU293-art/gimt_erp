<?php
// partials/role_sidebar.php - Dynamic RBAC Navigation Sidebar
$role = (string) ($_SESSION['user_role'] ?? '');

$menus = [
    'accounts' => [
        ['label' => 'Accounts Dashboard', 'href' => 'accounts_dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Fee Collection Counter', 'href' => 'fee_collect.php', 'icon' => 'bi-cash-coin'],
        ['label' => 'Student Ledgers', 'href' => 'student_ledger.php', 'icon' => 'bi-wallet2'],
        ['label' => 'Overdue Defaulters', 'href' => 'fee_defaulters.php', 'icon' => 'bi-exclamation-octagon'],
        ['label' => 'Fee Structure Builder', 'href' => 'fee_structures.php', 'icon' => 'bi-sliders'],
        ['label' => 'Admissions Verification', 'href' => 'admissions_manage.php', 'icon' => 'bi-person-check'],
        ['label' => 'Course Catalog', 'href' => 'courses_master.php', 'icon' => 'bi-journal-bookmark'],
    ],
    'hr' => [
        ['label' => 'HR Dashboard', 'href' => 'hr_dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Staff Directory', 'href' => 'staffs.php', 'icon' => 'bi-people'],
        ['label' => 'Staff Registration', 'href' => 'staff_register.php', 'icon' => 'bi-person-plus'],
        ['label' => 'Faculty Workload', 'href' => 'faculty_workload.php', 'icon' => 'bi-person-workspace'],
        ['label' => 'Departments', 'href' => 'departments.php', 'icon' => 'bi-building'],
        ['label' => 'Syllabus Master', 'href' => 'syllabus.php', 'icon' => 'bi-journal-text'],
    ],
    'hod' => [
        ['label' => 'HOD Dashboard', 'href' => 'hod_dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Course Master', 'href' => 'courses_master.php', 'icon' => 'bi-journal-bookmark'],
        ['label' => 'Faculty Workload', 'href' => 'faculty_workload.php', 'icon' => 'bi-person-workspace'],
        ['label' => 'Classroom Attendance', 'href' => 'attendance_mark.php', 'icon' => 'bi-calendar-check'],
        ['label' => 'Attendance Analytics (<75%)', 'href' => 'attendance_analytics.php', 'icon' => 'bi-graph-up'],
        ['label' => 'Leave Endorsements', 'href' => 'leave_requests.php', 'icon' => 'bi-envelope-paper'],
        ['label' => 'Coursework Assignments', 'href' => 'assignments_manage.php', 'icon' => 'bi-journal-text'],
        ['label' => 'Tabular Marks Entry', 'href' => 'marks_entry.php', 'icon' => 'bi-table'],
        ['label' => 'Exam Tabulation Engine', 'href' => 'exam_tabulation.php', 'icon' => 'bi-calculator'],
        ['label' => 'Student Grade Cards', 'href' => 'grade_card.php', 'icon' => 'bi-award'],
        ['label' => 'MAKAUT MAR (100 Pts)', 'href' => 'makaut_mar.php', 'icon' => 'bi-award-fill'],
        ['label' => 'MAKAUT MOOCs Credits', 'href' => 'makaut_moocs.php', 'icon' => 'bi-mortarboard-fill'],
        ['label' => 'Admissions Review', 'href' => 'admissions_manage.php', 'icon' => 'bi-person-check'],
        ['label' => 'Department Fee Dues', 'href' => 'fee_defaulters.php', 'icon' => 'bi-wallet2'],
    ],
    'teacher' => [
        ['label' => 'Faculty Dashboard', 'href' => 'teacher_dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Assigned Courses', 'href' => 'courses_master.php', 'icon' => 'bi-journal-bookmark'],
        ['label' => 'Mark Daily Attendance', 'href' => 'attendance_mark.php', 'icon' => 'bi-calendar-check'],
        ['label' => 'Attendance Analytics', 'href' => 'attendance_analytics.php', 'icon' => 'bi-graph-up'],
        ['label' => 'Coursework & Grading', 'href' => 'assignments_manage.php', 'icon' => 'bi-journal-text'],
        ['label' => 'Tabular Marks Entry', 'href' => 'marks_entry.php', 'icon' => 'bi-table'],
        ['label' => 'Consolidated Tabulation', 'href' => 'exam_tabulation.php', 'icon' => 'bi-calculator'],
        ['label' => 'MAKAUT MAR Endorsements', 'href' => 'makaut_mar.php', 'icon' => 'bi-award-fill'],
        ['label' => 'MOOCs Verification', 'href' => 'makaut_moocs.php', 'icon' => 'bi-mortarboard-fill'],
        ['label' => 'Student Leave Requests', 'href' => 'leave_requests.php', 'icon' => 'bi-envelope-paper'],
    ],
    'student' => [
        ['label' => 'Student Dashboard', 'href' => 'student_dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Enrolled Courses', 'href' => 'courses_master.php', 'icon' => 'bi-journal-bookmark'],
        ['label' => 'Internal & PCA Marks', 'href' => 'student_marks.php', 'icon' => 'bi-clipboard2-check'],
        ['label' => 'Financial Ledger', 'href' => 'student_ledger.php', 'icon' => 'bi-wallet2'],
        ['label' => 'Coursework & Submissions', 'href' => 'student_assignments.php', 'icon' => 'bi-journal-arrow-up'],
        ['label' => 'Apply for Leave', 'href' => 'leave_requests.php', 'icon' => 'bi-envelope-paper'],
        ['label' => 'Attendance Analytics', 'href' => 'attendance_analytics.php', 'icon' => 'bi-graph-up'],
        ['label' => 'MAKAUT MAR (100 Pts)', 'href' => 'makaut_mar.php', 'icon' => 'bi-award-fill'],
        ['label' => 'MOOCs SWAYAM/NPTEL', 'href' => 'makaut_moocs.php', 'icon' => 'bi-mortarboard-fill'],
        ['label' => 'Exam Hall Ticket', 'href' => 'hall_tickets.php', 'icon' => 'bi-person-badge'],
        ['label' => 'Digital Grade Card', 'href' => 'grade_card.php', 'icon' => 'bi-award'],
    ]
];

$currentRoleMenu = $menus[$role] ?? [];
$currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$sidebarName = (string) ($_SESSION['user_name'] ?? 'User');
?>
<style>
    .role-sidebar { width: 270px; min-width: 270px; height: 100vh; height: 100dvh; flex-shrink: 0; display: flex; flex-direction: column; background: #fff; border-right: 1px solid #e2e8f0; }
    .role-sidebar .sidebar-links { flex: 1; overflow-y: auto; padding: 16px 12px; }
    .role-sidebar .nav-link { display: flex; align-items: center; gap: 10px; min-height: 40px; padding: 8px 12px; border-radius: 8px; color: #475569; font-weight: 600; text-decoration: none; font-size: 0.9rem; }
    .role-sidebar .nav-link:hover, .role-sidebar .nav-link.active { color: #123b70; background: #eff6ff; }
    .role-sidebar .sidebar-toggle { position: fixed; top: 14px; left: 14px; z-index: 1040; width: 44px; height: 44px; color: white; background: #123b70; border-radius: 8px; border: 0; display: grid; place-items: center; }
    @media (min-width: 992px) { .role-sidebar { position: sticky; top: 0; } .role-sidebar .sidebar-toggle { display: none; } }
    @media (max-width: 991.98px) { .role-sidebar { width: 280px; min-width: 0; max-width: 85vw; height: 100vh; height: 100dvh; } }
</style>

<button class="btn sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#roleSidebar" aria-controls="roleSidebar" aria-label="Open navigation">
    <i class="bi bi-list fs-3"></i>
</button>

<aside class="offcanvas-lg offcanvas-start role-sidebar" id="roleSidebar" tabindex="-1" aria-label="<?= e(ucfirst($role)) ?> navigation">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <div>
            <span class="fw-bold text-primary d-block">GIMT ERP &bull; <?= strtoupper(e($role)) ?></span>
            <span class="text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">ACADEMIC MANAGEMENT</span>
        </div>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
    </div>
    <nav class="sidebar-links" aria-label="Main navigation">
        <ul class="nav flex-column gap-1">
            <?php foreach ($currentRoleMenu as $item): ?>
                <?php $isActive = ($currentScript === $item['href']); ?>
                <li class="nav-item">
                    <a class="nav-link<?= $isActive ? ' active fw-bold text-primary' : '' ?>" href="<?= e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                        <i class="bi <?= e($item['icon']) ?> fs-5" aria-hidden="true"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="border-top p-3 bg-light">
        <div class="small fw-bold text-truncate text-dark"><?= e($sidebarName) ?></div>
        <div class="small text-muted text-uppercase mb-2" style="font-size: 0.7rem;"><?= e($role) ?> Account</div>
        <a class="small text-danger text-decoration-none fw-semibold" href="logout.php">
            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Sign out
        </a>
    </div>
</aside>
