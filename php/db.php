<?php
require_once __DIR__ . '/env.php';

function nexusDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
    $dbname = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: ($_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'db_tolltech'));
    $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
    $pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

    $baseConnection = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $baseConnection->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function nexusJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function nexusString(?string $value): string
{
    return trim((string) $value ?? '');
}
