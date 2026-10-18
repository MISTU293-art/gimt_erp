<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$allowedRoles = ['chairman', 'principal', 'hr', 'accounts'];
$role = strtolower(trim((string) ($_SESSION['user_role'] ?? '')));

if (!in_array($role, $allowedRoles, true)) {
    header("Location: index.php");
    exit;
}