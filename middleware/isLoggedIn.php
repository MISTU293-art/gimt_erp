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

// Check the user's current status in the database on every request
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? LIMIT 1");
$uid  = (string) $_SESSION['user_id'];
$stmt->bind_param('s', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Deleted or blocked user: log out immediately
if (!$u || (int) $u['is_blocked'] === 1) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php?blocked=1");
    exit;
}
