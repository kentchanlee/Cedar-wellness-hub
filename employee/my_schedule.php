<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['employee']);
$active = 'my_schedule'; $base = '..'; $page_title = 'My Schedule'; $breadcrumb = 'Your duty calendar';

$empId = $_SESSION['employee_id'];
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));

if ($month<1 || $month>12 || $year<2000 || $year>2100) { $month=(int)date('n');$year=(int)date('Y'); }
$firstDay = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('w', $firstDay);

$stmt = $pdo->prepare("SELECT * FROM schedules WHERE employee_id = ? AND schedule_date BETWEEN ? AND ?");
$stmt->execute([$empId, date('Y-m-01', $firstDay), date('Y-m-t', $firstDay)]);
$schedMap = [];
foreach ($stmt->fetchAll() as $row) { $schedMap[$row['schedule_date']] = $row; }

$prevMonth = $month - 1; $prevYear = $year; if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year; if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
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
        <div class="spacer"></div>
        <a class="btn btn-outline btn-sm" href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&larr; Prev</a>
        <span class="badge badge-sage"><?= e(date('F Y', $firstDay)) ?></span>
        <a class="btn btn-outline btn-sm" href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next &rarr;</a>
      </div>

      <div class="calendar">
        <div class="cal-grid">
          <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?><div class="cal-dow"><?= $dow ?></div><?php endforeach; ?>
          <?php for ($i=0; $i < $startWeekday; $i++): ?><div class="cal-cell muted"></div><?php endfor; ?>
          <?php for ($d=1; $d <= $daysInMonth; $d++):
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = $dateStr === date('Y-m-d');
            $s = $schedMap[$dateStr] ?? null;
          ?>
            <div class="cal-cell <?= $isToday ? 'today' : '' ?>">
              <div class="date-num"><?= $d ?></div>
              <?php if ($s): ?>
                <?php if ($s['is_day_off']): ?>
                  <span class="badge badge-grey" style="width:100%;box-sizing:border-box;margin-top:4px;">Day Off</span>
                <?php else: ?>
                  <span class="badge badge-sage" style="width:100%;box-sizing:border-box;margin-top:4px;white-space:normal;line-height:1.45;"><?= e(date('g:i A', strtotime($s['shift_start']))) ?>–<?= e(!empty($s['break_start']) ? date('g:i A', strtotime($s['break_start'])) : date('g:i A', strtotime($s['shift_end']))) ?><?php if (!empty($s['break_start']) && !empty($s['break_end'])): ?><br><?= e(date('g:i A', strtotime($s['break_end']))) ?>–<?= e(date('g:i A', strtotime($s['shift_end']))) ?><?php endif; ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body></html>
