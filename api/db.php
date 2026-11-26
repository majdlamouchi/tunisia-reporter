<?php
declare(strict_types=1);

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) {
        json_response(['success' => false, 'error' => 'Server not configured: copy api/config.example.php to api/config.php'], 500);
    }
    $cfg = require $configFile;

    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4",
            $cfg['db_user'],
            $cfg['db_pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Database connection failed'], 500);
    }
    return $pdo;
}
