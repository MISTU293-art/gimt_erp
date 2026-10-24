<?php
// src/bootstrap.php - Production ERP Core Kernel, Security, RBAC & MAKAUT Engine

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

require_once __DIR__ . '/../config/db.config.php';

// Set standard timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

// Initialize MAKAUT Academic Session & Semester state
if (empty($_SESSION['academic_session'])) {
    $_SESSION['academic_session'] = '2026-2027';
}
if (empty($_SESSION['academic_term'])) {
    $_SESSION['academic_term'] = 'Odd Semester (July - Dec 2026)';
}
if (empty($_SESSION['academic_semester'])) {
    $_SESSION['academic_semester'] = 3;
}

function get_academic_session(): string {
    return $_SESSION['academic_session'] ?? '2026-2027';
}

function get_academic_term(): string {
    return $_SESSION['academic_term'] ?? 'Odd Semester (July - Dec 2026)';
}

function get_academic_semester(): int {
    return (int)($_SESSION['academic_semester'] ?? 3);
}

function set_academic_context(string $session, string $term, int $semester): void {
    $_SESSION['academic_session'] = $session;
    $_SESSION['academic_term'] = $term;
    $_SESSION['academic_semester'] = max(1, min(8, $semester));
}

/**
 * Current Institutional Active Operating Session
 */
const CURRENT_OPERATING_SESSION = '2026-2027';

/**
 * Checks whether an academic session is a previous (archived) session.
 * Previous session data is permanently frozen and cannot be edited, modified, or deleted.
 */
function is_previous_session(?string $session = null): bool {
    if ($session === null) {
        $session = get_academic_session();
    }
    return ($session !== CURRENT_OPERATING_SESSION);
}

/**
 * Blocks mutation attempts on previous archived academic sessions
 */
function enforce_active_session(?string $session = null): void {
    if (is_previous_session($session)) {
        audit_log('Archived Session Mutation Blocked', 'system', null, "Attempted modification of locked session " . ($session ?? get_academic_session()));
        set_flash('danger', 'Regulatory Enforcement: Previous session data is permanently archived and cannot be edited, modified, or deleted.');
        $ref = $_SERVER['HTTP_REFERER'] ?? base_url();
        header("Location: {$ref}");
        exit;
    }
}

/**
 * Escapes HTML output safely
 */
function e(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Dynamic URL generator: works seamlessly on VPS root (/) or local subdirectory (/public/)
 */
function base_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = str_replace('\\', '/', $scriptDir);
        $base = rtrim($scriptDir, '/');
    }
    $path = ltrim($path, '/');
    if ($path === '') {
        return ($base === '' || $base === '/') ? '/' : $base . '/';
    }
    return ($base === '' || $base === '/') ? '/' . $path : $base . '/' . $path;
}

/**
 * Formats amount into INR currency
 */
function format_inr(float $amount): string {
    return '₹' . number_format($amount, 2);
}

/**
 * Generates or retrieves current CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generates hidden CSRF HTML input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validates CSRF token on POST requests
 */
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

/**
 * Set flash alert message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Render flash alert banner if present
 */
function display_flash(): string {
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $type = e($flash['type']);
        $msg = e($flash['message']);
        return "
            <div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                {$msg}
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
            </div>
        ";
    }
    return '';
}

/**
 * Retrieves current logged-in user profile
 */
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? ''
    ];
}

/**
 * Enforces authenticated session
 */
function require_auth(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . base_url('admin_login.php'));
        exit;
    }
}

/**
 * Enforces role-based permission
 */
function require_roles(array $allowedRoles): void {
    require_auth();
    $userRole = $_SESSION['user_role'] ?? '';
    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        include __DIR__ . '/../partials/403.php';
        exit;
    }
}

/**
 * Immutable audit logger for financial transactions, status changes & grade locks
 */
function audit_log(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void {
    $pdo = getDb();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failure: " . $e->getMessage());
    }
}

/**
 * Fetch linked student record for student user
 */
function get_current_student(): ?array {
    if (($_SESSION['user_role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
        return null;
    }
    $pdo = getDb();
    $stmt = $pdo->prepare("
        SELECT s.*, d.dept_name, d.dept_code, u.name, u.email
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE s.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// ==============================================================================
// MAKAUT (MAULANA ABUL KALAM AZAD UNIVERSITY OF TECHNOLOGY) REGULATORY ENGINE
// ==============================================================================

/**
 * Computes MAKAUT 10-Point Letter Grade and Grade Point from Marks Percentage
 * O: 90-100 (10), E: 80-89 (9), A: 70-79 (8), B: 60-69 (7), C: 50-59 (6), D: 40-49 (5), F: <40 (0)
 */
function makaut_grade_point(float $percentage): array {
    if ($percentage >= 90.0) return ['grade' => 'O', 'point' => 10, 'desc' => 'Outstanding'];
    if ($percentage >= 80.0) return ['grade' => 'E', 'point' => 9,  'desc' => 'Excellent'];
    if ($percentage >= 70.0) return ['grade' => 'A', 'point' => 8,  'desc' => 'Very Good'];
    if ($percentage >= 60.0) return ['grade' => 'B', 'point' => 7,  'desc' => 'Good'];
    if ($percentage >= 50.0) return ['grade' => 'C', 'point' => 6,  'desc' => 'Fair'];
    if ($percentage >= 40.0) return ['grade' => 'D', 'point' => 5,  'desc' => 'Pass'];
    return ['grade' => 'F', 'point' => 0, 'desc' => 'Fail'];
}

/**
 * Computes MAKAUT SGPA (Semester Grade Point Average)
 * SGPA = sum(Credits * GradePoint) / sum(Credits)
 */
function makaut_calculate_sgpa(array $courses): float {
    $totalCredits = 0;
    $totalWeightedPoints = 0.0;
    foreach ($courses as $c) {
        $credits = (int)($c['credits'] ?? 3);
        $gp = (int)($c['grade_point'] ?? 0);
        $totalCredits += $credits;
        $totalWeightedPoints += ($credits * $gp);
    }
    return ($totalCredits > 0) ? round($totalWeightedPoints / $totalCredits, 2) : 0.00;
}

/**
 * Computes MAKAUT YGPA (Yearly Grade Point Average)
 * YGPA = (SGPA_odd * C_odd + SGPA_even * C_even) / (C_odd + C_even)
 */
function makaut_calculate_ygpa(float $oddSgpa, int $oddCredits, float $evenSgpa, int $evenCredits): float {
    $tot = $oddCredits + $evenCredits;
    return ($tot > 0) ? round(($oddSgpa * $oddCredits + $evenSgpa * $evenCredits) / $tot, 2) : 0.00;
}

/**
 * Computes MAKAUT DGPA (Divided Grade Point Average for 4-year B.Tech)
 * DGPA = (YGPA_1 + YGPA_2 + 1.5 * YGPA_3 + 1.5 * YGPA_4) / 5
 */
function makaut_calculate_dgpa(float $y1, float $y2, float $y3, float $y4): float {
    return round(($y1 + $y2 + 1.5 * $y3 + 1.5 * $y4) / 5.0, 2);
}

/**
 * Strict Teacher & Course Access Validation Engine
 * Ensures ONLY the teacher assigned in `faculty_courses` can enter marks or take attendance for a course.
 * HODs can access courses within their department.
 * Principal and Chairman have institutional oversight access.
 */
function can_teacher_access_course(int $userId, int $courseId, ?string $userRole = null): bool {
    if ($courseId <= 0 || $userId <= 0) return false;
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? '';
    }

    // Chairman & Principal have full institutional oversight
    if (in_array($userRole, ['chairman', 'principal'], true)) {
        return true;
    }

    $pdo = getDb();

    // HODs can access any course in their department
    if ($userRole === 'hod') {
        $stmt = $pdo->prepare("
            SELECT 1 FROM courses c
            JOIN departments d ON c.department_id = d.id
            WHERE c.id = ? AND d.hod_user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$courseId, $userId]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    // Assigned Teachers: MUST have an active record in faculty_courses
    $stmt = $pdo->prepare("
        SELECT 1 FROM faculty_courses
        WHERE teacher_id = ? AND course_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $courseId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Returns list of courses that the current user is strictly permitted to manage
 */
function get_permitted_courses(int $userId, string $userRole): array {
    $pdo = getDb();
    if (in_array($userRole, ['chairman', 'principal'], true)) {
        return $pdo->query("
            SELECT c.*, d.dept_code, d.dept_name,
                   GROUP_CONCAT(u.name SEPARATOR ', ') AS assigned_teachers
            FROM courses c
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN faculty_courses fc ON c.id = fc.course_id
            LEFT JOIN users u ON fc.teacher_id = u.id
            GROUP BY c.id
            ORDER BY c.semester, c.course_code
        ")->fetchAll();
    } elseif ($userRole === 'hod') {
        $stmt = $pdo->prepare("
            SELECT c.*, d.dept_code, d.dept_name,
                   GROUP_CONCAT(u.name SEPARATOR ', ') AS assigned_teachers
            FROM courses c
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN faculty_courses fc ON c.id = fc.course_id
            LEFT JOIN users u ON fc.teacher_id = u.id
            WHERE d.hod_user_id = ?
            GROUP BY c.id
            ORDER BY c.semester, c.course_code
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } elseif ($userRole === 'teacher') {
        $stmt = $pdo->prepare("
            SELECT c.*, d.dept_code, d.dept_name, fc.section
            FROM courses c
            JOIN departments d ON c.department_id = d.id
            JOIN faculty_courses fc ON c.id = fc.course_id
            WHERE fc.teacher_id = ?
            ORDER BY c.semester, c.course_code
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    return [];
}
