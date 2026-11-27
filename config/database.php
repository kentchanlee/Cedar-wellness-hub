<?php
/**
 * Cedar Wellness Hub database connection.
 *
 * Local XAMPP defaults are retained for development. For online multi-branch
 * deployment, configure CWH_DB_HOST, CWH_DB_NAME, CWH_DB_USER and CWH_DB_PASS
 * in the hosting environment. Never commit production credentials to source control.
 */
$DB_HOST = getenv('CWH_DB_HOST') ?: 'localhost';
$DB_NAME = getenv('CWH_DB_NAME') ?: 'cedar_wellness_hub';
$DB_USER = getenv('CWH_DB_USER') ?: 'root';
$DB_PASS = getenv('CWH_DB_PASS');
if ($DB_PASS === false) {
    $DB_PASS = '';
}
$DB_PORT = getenv('CWH_DB_PORT') ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT             => 5,
        ]
    );
    // Database default timestamps must use the same clock as PHP attendance and cooldowns.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    http_response_code(503);
    error_log('CEDAR Wellness Hub database connection failed: ' . $e->getMessage());
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    echo '<div style="font-family:sans-serif;padding:40px;color:#7a2e2e">'
        . '<h2>Database connection unavailable</h2>'
        . '<p>Check that the configured MySQL server is running, the database exists, and the application credentials are correct.</p>'
        . ($isLocal ? '<p>For local XAMPP, start MySQL and import <code>database/cedar_wellness.sql</code> using phpMyAdmin.</p>' : '')
        . '<p>Please try again shortly or contact the system administrator.</p></div>';
    exit;
}
