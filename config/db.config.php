<?php
// config/db.config.php - Enterprise Database Connection & Environment Loader

// Simple zero-dependency .env loader
if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Strip outer quotes if present
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $val;
                    putenv("{$key}={$val}");
                }
            }
        }
    }
}

// Load .env from project root if present
load_env_file(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: "localhost";
$port = getenv('DB_PORT') ?: "3306";
$user = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$db_Name = getenv('DB_NAME') ?: "gimt_erp";

// 1. MySQLi connection for backwards compatibility
$conn = new mysqli($host, $user, $password, $db_Name, (int)$port);
if ($conn->connect_error) {
    http_response_code(500);
    include __DIR__ . '/../partials/500.php';
    exit;
}
$conn->set_charset("utf8mb4");

// 2. PDO Connection for Production-Grade Prepared Statements & Transactions
$pdo = null;
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db_Name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    include __DIR__ . '/../partials/500.php';
    exit;
}

if (!function_exists('getDb')) {
    function getDb(): PDO {
        global $pdo;
        return $pdo;
    }
}
