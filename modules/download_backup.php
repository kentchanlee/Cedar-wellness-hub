<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'hr') { redirect(dashboard_url_for($_SESSION['role'] ?? 'employee')); }

$file = basename($_GET['file'] ?? ''); // basename() prevents path traversal
$path = __DIR__ . '/../backups/' . $file;

// Only allow files that are actually logged as backups
$stmt = $pdo->prepare("SELECT id FROM backup_logs WHERE filename = ?");
$stmt->execute([$file]);
if (!$stmt->fetch() || !file_exists($path)) {
    http_response_code(404);
    die('Backup file not found.');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
