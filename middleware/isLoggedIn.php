<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.config.php';

// Not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Check the user's current status in the database on every request.
$stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Unable to verify the account status.');
}
$uid = (int) $_SESSION['user_id'];
$stmt->bind_param('i', $uid);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit('Unable to verify the account status.');
}
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Deleted or blocked user: log out immediately
if (!$user || $user['status'] !== 'active') {
    $_SESSION = [];
    session_destroy();
    header("Location: /public/index.php?blocked=1");
    exit;
}
