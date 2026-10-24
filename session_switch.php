<?php
// session_switch.php - Academic Session & Semester Context Switcher
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $session = trim($_POST['academic_session'] ?? '2026-2027');
    $term = trim($_POST['academic_term'] ?? 'Odd Semester (July - Dec 2026)');
    $sem = (int)($_POST['academic_semester'] ?? 3);
    $returnUrl = trim($_POST['return_url'] ?? 'admin_dashboard.php');

    // Clean return URL to prevent open-redirects
    $returnUrl = basename($returnUrl);
    if (empty($returnUrl) || !file_exists(__DIR__ . '/' . $returnUrl)) {
        $returnUrl = 'admin_dashboard.php';
    }

    set_academic_context($session, $term, $sem);
    set_flash('success', "Academic session context updated to: {$session} &bull; {$term} (Semester {$sem})");
    header("Location: {$returnUrl}");
    exit;
}

header('Location: ' . base_url());
exit;
