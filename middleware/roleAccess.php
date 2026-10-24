<?php
if (!function_exists('require_user_role')) {
    function require_user_role(array $allowedRoles): void
    {
        $role = (string) ($_SESSION['user_role'] ?? '');
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            exit('You do not have permission to access this page.');
        }
    }
}
