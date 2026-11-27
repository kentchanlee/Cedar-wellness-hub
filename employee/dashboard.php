<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['employee']);
$active = 'dashboard'; $base = '..'; $page_title = 'My Dashboard'; $breadcrumb = 'Welcome back';

$empId = $_SESSION['employee_id'];
$today = date('Y-m-d');

$att = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
$att->execute([$empId, $today]);
$att = $att->fetch() ?: [
    'time_in' => null,
    'time_out' => null,
];

$sched = $pdo->prepare("SELECT * FROM schedules WHERE employee_id = ? AND schedule_date >= ? ORDER BY schedule_date LIMIT 5");
$sched->execute([$empId, $today]);
$sched = $sched->fetchAll();

$leave = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC LIMIT 3");
$leave->execute([$empId]);
$leave = $leave->fetchAll();

$emp = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$emp->execute([$empId]);
$emp = $emp->fetch() ?: [
    'on_field' => 0
];

// Month-to-date total hours
$mtd = $pdo->prepare("SELECT COALESCE(SUM(total_hours),0) h FROM attendance WHERE employee_id = ? AND attendance_date >= DATE_FORMAT(NOW(),'%Y-%m-01')");
$mtd->execute([$empId]);
$mtdHours = $mtd->fetch()['h'];
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

      <div class="dashboard-kpis">
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('clock') ?></div>
          <div class="num"><?= $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '—' ?></div>
          <div class="lbl">Time In Today</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('sun') ?></div>
          <div class="num"><?= $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '—' ?></div>
          <div class="lbl">Time Out Today</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('chart') ?></div>
          <div class="num"><?= number_format((float)$mtdHours, 1) ?>h</div>
          <div class="lbl">Hours This Month</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('field') ?></div>
          <div class="num" style="font-size:18px;"><?= $emp['on_field'] ? 'On Field' : 'In-Branch' ?></div>
          <div class="lbl">Current Status</div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-head"><h3>Today's Attendance</h3></div>
        <div class="punch-grid">
          <div class="punch-box <?= $att['time_in'] ? 'done' : '' ?>">
            <div class="t"><?= $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '--:--' ?></div>
            <div class="l">Time In</div>
          </div>
          <div class="punch-box <?= !empty($att['time_out']) ? 'done' : '' ?>">
            <div class="t"><?= !empty($att['time_out']) ? date('h:i A', strtotime($att['time_out'])) : '--:--' ?></div>
            <div class="l">Time Out</div>
          </div>
        </div>
        <div style="text-align:center;margin-top:18px;">
          <a href="attendance.php" class="btn btn-brass">Scan QR to Punch In/Out</a>
        </div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <div class="card-head">
            <h3>Upcoming Schedule</h3>
            <a href="my_schedule.php" class="btn btn-outline btn-sm">Full Calendar</a>
          </div>
          <?php if (!$sched): ?>
            <div class="empty-state"><div class="ic"><?= ui_icon('clock') ?></div>No upcoming shifts posted yet.</div>
          <?php else: ?>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Date</th><th>Shift</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($sched as $s): ?>
                <tr>
                  <td><?= e(date('M j, Y (D)', strtotime($s['schedule_date']))) ?></td>
                  <td><?= $s['is_day_off'] ? '—' : e(substr($s['shift_start'],0,5).' - '.substr($s['shift_end'],0,5)) ?></td>
                  <td><?= $s['is_day_off'] ? '<span class="badge badge-grey">Day Off</span>' : '<span class="badge badge-sage">On Duty</span>' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-head">
            <h3>My Leave Requests</h3>
            <a href="my_leave.php" class="btn btn-outline btn-sm">File / View All</a>
          </div>
          <?php if (!$leave): ?>
            <div class="empty-state"><div class="ic"><?= ui_icon('document') ?></div>You haven't filed any leave requests.</div>
          <?php else: ?>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Type</th><th>Dates</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($leave as $lr): ?>
                <tr>
                  <td><?= e(ucfirst($lr['leave_type'])) ?></td>
                  <td><?= e($lr['date_from']) ?> &rarr; <?= e($lr['date_to']) ?></td>
                  <td><?php $b=['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'][$lr['status']]; ?>
                    <span class="badge <?= $b ?>"><?= e($lr['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>
</body></html>
