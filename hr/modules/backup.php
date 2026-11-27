<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'hr') { redirect(dashboard_url_for($_SESSION['role'] ?? 'employee')); }
$active = 'backup'; $base = '..'; $page_title = 'Database Backup'; $breadcrumb = 'Automatic & manual backups';

$backupDir = __DIR__ . '/../backups';

/** Dump the whole database to a .sql file using pure PHP (no mysqldump needed) */
function run_backup(PDO $pdo, string $backupDir, string $triggeredBy, ?int $userId): string {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $filename = 'cedar_backup_' . date('Ymd_His') . '.sql';
    $path = $backupDir . '/' . $filename;
    $sql = "-- Cedar Wellness Hub backup generated " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        if ($table === 'backup_logs') continue; // avoid recursive bloat
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_map(fn($c) => "`$c`", array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($row));
            $sql .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($path, $sql);

    $pdo->prepare("INSERT INTO backup_logs (filename, triggered_by, created_by) VALUES (?, ?, ?)")
        ->execute([$filename, $triggeredBy, $userId]);

    return $filename;
}

// Manual trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup_now') {
    run_backup($pdo, $backupDir, 'manual', $_SESSION['user_id']);
    flash('success', 'Backup created successfully.');
    redirect('/modules/backup.php');
}

// Auto backup: if last backup is older than 24 hours, run one automatically
$last = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 1")->fetch();
$autoRan = false;
if (!$last || strtotime($last['created_at']) < strtotime('-24 hours')) {
    run_backup($pdo, $backupDir, 'auto', $_SESSION['user_id']);
    $autoRan = true;
}

$logs = $pdo->query("SELECT bl.*, u.username FROM backup_logs bl LEFT JOIN users u ON u.id = bl.created_by ORDER BY bl.created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= e($page_title) ?> — Cedar Wellness Hub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="content">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($autoRan): ?><div class="alert alert-success">Auto-backup ran because it had been more than 24 hours since the last one.</div><?php endif; ?>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-head"><h3>Backup Now</h3></div>
        <p class="muted" style="font-size:13px;">
          The system automatically creates a backup every 24 hours whenever a superadmin visits this page.
          You can also trigger one manually below.
        </p>
        <form method="POST"><input type="hidden" name="action" value="backup_now">
          <button class="btn btn-brass" type="submit">Backup Now</button>
        </form>
      </div>

      <div class="card">
        <div class="card-head"><h3>Backup History</h3></div>
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>File</th><th>Trigger</th><th>By</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state"><div class="ic"><?= ui_icon('document') ?></div>No backups yet.</div></td></tr><?php endif; ?>
            <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= e($l['filename']) ?></td>
              <td><span class="badge <?= $l['triggered_by']==='auto'?'badge-sage':'badge-brass' ?>"><?= e($l['triggered_by']) ?></span></td>
              <td><?= e($l['username'] ?? '—') ?></td>
              <td><?= e(date('M j, Y h:i A', strtotime($l['created_at']))) ?></td>
              <td class="text-right">
                <?php if (file_exists($backupDir . '/' . $l['filename'])): ?>
                  <a class="btn btn-outline btn-sm" href="../modules/download_backup.php?file=<?= urlencode($l['filename']) ?>">Download</a>
                <?php else: ?><span class="muted">file removed</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body></html>
