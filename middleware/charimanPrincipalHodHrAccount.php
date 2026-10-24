<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$allowedRoles = ['chairman', 'principal', 'hr'];
$role = strtolower(trim((string) ($_SESSION['user_role'] ?? '')));

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    exit('You do not have permission to access staff management.');
}