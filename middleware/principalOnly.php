<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SESSION['user_role'] ?? '') !== 'principal') {
    header('Location: /public/index.php');
    exit;
}
