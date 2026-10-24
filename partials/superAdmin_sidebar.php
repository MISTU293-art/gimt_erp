<?php
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr(trim($user_name), 0, 1));
$currentScript = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
?>

<!-- sidebar css -->
<style>
    :root {
        --gimt-primary: #123b70;
        --gimt-primary-dark: #0a1f3d;
        --gimt-blue: #123b70;
        --gimt-blue-light: #eff6ff;
        --sidebar-bg: #ffffff;
        --sidebar-text: #475569;
        --sidebar-muted: #94a3b8;
        --sidebar-border: #e2e8f0;
    }

    html, body {
        overflow-x: clip;
    }

    .page-layout, .dashboard-layout {
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
        min-height: 70px;
        background: #fff;
        border-bottom: 1px solid var(--sidebar-border);
    }

    .brand-title {
        color: #172b4d;
        font-size: 1.05rem;
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: .5px;
    }

    .brand-subtitle {
        color: var(--sidebar-muted);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .sidebar-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 14px 10px;
    }

    .menu-label {
        padding: 8px 12px 4px;
        color: var(--sidebar-muted);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: 1.1px;
    }

    .gimt-sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 40px;
        padding: 8px 12px;
        color: var(--sidebar-text);
        font-size: .85rem;
        font-weight: 600;
        border-radius: 8px;
        text-decoration: none;
    }

    .gimt-sidebar .nav-link:hover {
        color: var(--gimt-primary);
        background: var(--gimt-blue-light);
    }

    .gimt-sidebar .nav-link.active {
        color: #fff;
        background: var(--gimt-primary);
    }

    .sub-menu {
        margin: 4px 0 8px 13px;
        padding-left: 10px;
        border-left: 1px solid var(--sidebar-border);
    }

    .gimt-sidebar .sub-menu .nav-link {
        min-height: 35px;
        padding: 6px 10px;
        color: #64748b;
        font-size: .81rem;
        font-weight: 500;
    }

    .gimt-sidebar .sub-menu .nav-link.active,
    .gimt-sidebar .sub-menu .nav-link:hover {
        color: var(--gimt-primary);
        background: var(--gimt-blue-light);
    }

    .sidebar-footer {
        flex: 0 0 auto;
        padding: 14px;
        background: #f8fafc;
        border-top: 1px solid var(--sidebar-border);
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

    @media (min-width: 992px) {
        .gimt-sidebar {
            position: sticky !important;
            top: 0 !important;
            align-self: flex-start;
        }
        .sidebar-toggle {
            display: none !important;
        }
    }

    @media (max-width: 991.98px) {
        .gimt-sidebar {
            width: 280px;
            min-width: 0;
            max-width: 85vw;
        }
    }
</style>

<!-- Mobile Sidebar Toggle -->
<button class="btn sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#gimtSidebar" aria-controls="gimtSidebar" aria-label="Open navigation">
    <i class="bi bi-list fs-3"></i>
</button>

<!-- GIMT ERP Chairman / SuperAdmin Sidebar -->
<aside class="offcanvas-lg offcanvas-start gimt-sidebar" tabindex="-1" id="gimtSidebar" aria-label="GIMT ERP navigation">
    <!-- Brand Header -->
    <div class="sidebar-header d-flex align-items-center justify-content-between p-3">
        <a href="admin_dashboard.php" class="d-flex align-items-center gap-2 text-decoration-none">
            <div>
                <span class="brand-title d-block">GIMT ERP</span>
                <span class="brand-subtitle">CHAIRMAN &bull; GOVERNING BODY</span>
            </div>
        </a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#gimtSidebar" aria-label="Close navigation"></button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-body" aria-label="Main navigation">
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link<?= $currentScript === 'admin_dashboard.php' ? ' active' : '' ?>" href="admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Chairman Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Admissions Pipeline -->
        <div class="menu-label">ADMISSIONS &amp; ONBOARDING</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link<?= $currentScript === 'admissions_manage.php' ? ' active' : '' ?>" href="admissions_manage.php">
                    <i class="bi bi-person-check-fill"></i>
                    <span>Admissions Pipeline</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admission_apply.php" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Applicant Portal (Public)</span>
                </a>
            </li>
        </ul>

        <!-- Academic Setup -->
        <div class="menu-label">ACADEMIC MANAGEMENT</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#academicMenu" role="button" aria-expanded="false">
                    <i class="bi bi-mortarboard"></i>
                    <span>Curriculum &amp; Setup</span>
                    <i class="bi bi-chevron-down ms-auto" style="font-size: .75rem;"></i>
                </a>
                <div class="collapse" id="academicMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a class="nav-link" href="departments.php">Departments Master</a></li>
                        <li class="nav-item"><a class="nav-link" href="courses_master.php">Subject &amp; Credits Master</a></li>
                        <li class="nav-item"><a class="nav-link" href="faculty_workload.php">Faculty Workload Allocation</a></li>
                        <li class="nav-item"><a class="nav-link" href="hod_assign.php">HOD Department Assign</a></li>
                        <li class="nav-item"><a class="nav-link" href="semesters.php">Semesters &amp; Batches</a></li>
                        <li class="nav-item"><a class="nav-link" href="syllabus.php">Syllabus Master</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#usersMenu" role="button" aria-expanded="false">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                    <i class="bi bi-chevron-down ms-auto" style="font-size: .75rem;"></i>
                </a>
                <div class="collapse" id="usersMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a class="nav-link" href="staff_register.php">Staff Registration</a></li>
                        <li class="nav-item"><a class="nav-link" href="staffs.php">Faculty &amp; Staff Directory</a></li>
                    </ul>
                </div>
            </li>
        </ul>

        <!-- Examinations & Tabulation -->
        <div class="menu-label">EXAMINATIONS &amp; EVALUATION</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#examMenu" role="button" aria-expanded="false">
                    <i class="bi bi-journal-check"></i>
                    <span>Examinations</span>
                    <i class="bi bi-chevron-down ms-auto" style="font-size: .75rem;"></i>
                </a>
                <div class="collapse" id="examMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a class="nav-link" href="exam_series.php">Assessment Series Setup</a></li>
                        <li class="nav-item"><a class="nav-link" href="marks_entry.php">Tabular Marks Entry</a></li>
                        <li class="nav-item"><a class="nav-link" href="exam_tabulation.php">Consolidated Tabulation</a></li>
                        <li class="nav-item"><a class="nav-link" href="grade_card.php">Locked Grade Cards</a></li>
                        <li class="nav-item"><a class="nav-link" href="hall_tickets.php">Hall Tickets &amp; Override</a></li>
                    </ul>
                </div>
            </li>
        </ul>

        <!-- Financial Operations -->
        <div class="menu-label">FINANCE &amp; ACCOUNTS</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#financeMenu" role="button" aria-expanded="false">
                    <i class="bi bi-wallet2"></i>
                    <span>Fee Accounting</span>
                    <i class="bi bi-chevron-down ms-auto" style="font-size: .75rem;"></i>
                </a>
                <div class="collapse" id="financeMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item"><a class="nav-link" href="fee_structures.php">Fee Structure Builder</a></li>
                        <li class="nav-item"><a class="nav-link" href="fee_collect.php">Counter Fee Collection</a></li>
                        <li class="nav-item"><a class="nav-link" href="student_ledger.php">Student Ledgers</a></li>
                        <li class="nav-item"><a class="nav-link" href="fee_defaulters.php">Overdue Defaulters</a></li>
                    </ul>
                </div>
            </li>
        </ul>

        <!-- Operations -->
        <div class="menu-label">OPERATIONS &amp; ATTENDANCE</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item"><a class="nav-link<?= $currentScript === 'attendance_mark.php' ? ' active' : '' ?>" href="attendance_mark.php"><i class="bi bi-calendar-check"></i><span>Classroom Attendance</span></a></li>
            <li class="nav-item"><a class="nav-link<?= $currentScript === 'attendance_analytics.php' ? ' active' : '' ?>" href="attendance_analytics.php"><i class="bi bi-graph-up"></i><span>Attendance &lt;75% Engine</span></a></li>
            <li class="nav-item"><a class="nav-link<?= $currentScript === 'leave_requests.php' ? ' active' : '' ?>" href="leave_requests.php"><i class="bi bi-clipboard-check"></i><span>Leave &amp; Condonations</span></a></li>
            <li class="nav-item"><a class="nav-link<?= $currentScript === 'assignments_manage.php' ? ' active' : '' ?>" href="assignments_manage.php"><i class="bi bi-journal-text"></i><span>Coursework Assignments</span></a></li>
        </ul>

        <!-- MAKAUT Compliance Matrix -->
        <div class="menu-label">MAKAUT COMPLIANCE</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item"><a class="nav-link<?= $currentScript === 'makaut_mar.php' ? ' active' : '' ?>" href="makaut_mar.php"><i class="bi bi-award-fill"></i><span>MAR (100 Points) Portal</span></a></li>
            <li class="nav-item"><a class="nav-link<?= $currentScript === 'makaut_moocs.php' ? ' active' : '' ?>" href="makaut_moocs.php"><i class="bi bi-mortarboard-fill"></i><span>MOOCs / SWAYAM / NPTEL</span></a></li>
        </ul>

        <!-- System Governance & Audit -->
        <div class="menu-label">GOVERNANCE &amp; AUDIT</div>
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link<?= $currentScript === 'audit_logs.php' ? ' active' : '' ?>" href="audit_logs.php">
                    <i class="bi bi-shield-check"></i>
                    <span>Immutable Audit Logs</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Footer -->
    <footer class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary text-white rounded-circle p-2 fw-bold text-center" style="width: 36px; height: 36px; line-height: 20px;">
                <?= htmlspecialchars($user_initial, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <span class="d-block text-truncate fw-bold small text-dark"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="d-block text-truncate text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <a href="logout.php" class="text-danger p-2 text-decoration-none" title="Logout">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </footer>
</aside>
