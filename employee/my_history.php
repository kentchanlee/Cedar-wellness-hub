<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['employee']);
$active = 'my_history'; $base = '..'; $page_title = 'Attendance History'; $breadcrumb = 'Your past time records';
$empId = $_SESSION['employee_id'];

$month = $_GET['month'] ?? date('Y-m');
if (!is_string($month) || !preg_match('/^(20[0-9]{2}|2100)-(0[1-9]|1[0-2])$/D',$month)) $month=date('Y-m');
$rows = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date LIKE ? ORDER BY attendance_date DESC");
$rows->execute([$empId, $month . '%']);
$rows = $rows->fetchAll();

$totalHours = array_sum(array_column($rows, 'total_hours'));
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
      <div class="toolbar">
        <form method="GET"><input type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"></form>
        <div class="spacer"></div>
        <span class="badge badge-sage">Total: <?= number_format($totalHours,2) ?>h this month</span>
      </div>
      <div class="card">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state"><div class="ic"><?= ui_icon('clock') ?></div>No records for this month.</div></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e(date('M j, Y (D)', strtotime($r['attendance_date']))) ?></td>
              <td><?= $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '—' ?></td>
              <td><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—' ?></td>
              <td><?= $r['total_hours'] !== null ? number_format($r['total_hours'],2).'h' : '—' ?></td>
              <td><?php $b=['present'=>'badge-green','late'=>'badge-amber','absent'=>'badge-red','half-day'=>'badge-sage'][$r['remarks']] ?? 'badge-grey'; ?>
                <span class="badge <?= $b ?>"><?= e($r['remarks']) ?></span></td>
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
