<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


if ($_SESSION['user_role'] != "chairman") {
    header("Location:index.php");
    exit;
}
