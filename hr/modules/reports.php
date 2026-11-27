<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
$hrBranchId = require_hr_branch($pdo);
$active = 'reports'; $base = '..'; $page_title = 'Reports & Analytics'; $breadcrumb = 'Attendance monitoring and total hours';

$view = $_GET['view'] ?? 'month'; // day | week | days_1_5 | days_15_30 | month | year
$allowedViews = ['day', 'week', 'days_1_5', 'days_15_30', 'month', 'year'];
if (!in_array($view, $allowedViews, true)) $view = 'month';

$day  = $_GET['day']  ?? date('Y-m-d');
$week = $_GET['week'] ?? date('o-\WW');
$mon  = $_GET['mon']  ?? date('Y-m');
$yr   = $_GET['yr']   ?? date('Y');

$monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $mon . '-01');
if (!$monthStart || $monthStart->format('Y-m') !== $mon) {
    $mon = date('Y-m');
    $monthStart = new DateTimeImmutable($mon . '-01');
}

if (!preg_match('/^(\d{4})-W(\d{2})$/', $week, $weekParts)) {
    $week = date('o-\WW');
    preg_match('/^(\d{4})-W(\d{2})$/', $week, $weekParts);
}
$weekStart = (new DateTimeImmutable())->setISODate((int)$weekParts[1], (int)$weekParts[2]);
$weekEnd = $weekStart->modify('+6 days');

$labels = []; $presentData = []; $lateData = []; $absentData = [];
$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM employees WHERE is_deleted=0 AND branch_id=?");
$countStmt->execute([$hrBranchId]); $totalEmployees = (int)$countStmt->fetch()['c'];

if ($view === 'day') {
    // Per-employee status for that single day
    $stmt = $pdo->prepare("SELECT e.first_name, e.last_name, a.remarks, a.time_in FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = ?
        WHERE e.is_deleted = 0 AND e.branch_id = ? ORDER BY e.first_name");
    $stmt->execute([$day, $hrBranchId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $labels[] = $r['first_name'];
        $presentData[] = ($r['time_in'] && $r['remarks'] === 'present') ? 1 : 0;
        $lateData[]    = ($r['time_in'] && $r['remarks'] === 'late') ? 1 : 0;
        $absentData[]  = (!$r['time_in']) ? 1 : 0;
    }
    $chartTitle = "Attendance — " . date('F j, Y', strtotime($day));
} elseif ($view === 'week') {
    for ($cursor = $weekStart; $cursor <= $weekEnd; $cursor = $cursor->modify('+1 day')) {
        $dateStr = $cursor->format('Y-m-d');
        $labels[] = $cursor->format('D M j');
        $stmt = $pdo->prepare("SELECT a.remarks, COUNT(*) c FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date = ? AND e.branch_id = ? AND e.is_deleted=0 GROUP BY a.remarks");
        $stmt->execute([$dateStr, $hrBranchId]);
        $counts = ['present'=>0,'late'=>0,'absent'=>0];
        foreach ($stmt->fetchAll() as $row) { if (isset($counts[$row['remarks']])) $counts[$row['remarks']] = (int)$row['c']; }
        $presentData[] = $counts['present'];
        $lateData[] = $counts['late'];
        $absentData[] = 0;
    }
    $chartTitle = 'Weekly Attendance - ' . $weekStart->format('M j') . ' to ' . $weekEnd->format('M j, Y');
} elseif ($view === 'days_1_5' || $view === 'days_15_30') {
    $startDay = $view === 'days_1_5' ? 1 : 15;
    $endDay = $view === 'days_1_5' ? 5 : min(30, (int)$monthStart->format('t'));
    for ($d = $startDay; $d <= $endDay; $d++) {
        $dateStr = $monthStart->format('Y-m-') . sprintf('%02d', $d);
        $labels[] = $d;
        $stmt = $pdo->prepare("SELECT a.remarks, COUNT(*) c FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date = ? AND e.branch_id = ? AND e.is_deleted=0 GROUP BY a.remarks");
        $stmt->execute([$dateStr, $hrBranchId]);
        $counts = ['present'=>0,'late'=>0,'absent'=>0];
        foreach ($stmt->fetchAll() as $row) { if (isset($counts[$row['remarks']])) $counts[$row['remarks']] = (int)$row['c']; }
        $presentData[] = $counts['present'];
        $lateData[] = $counts['late'];
        $absentData[] = 0;
    }
    $chartTitle = 'Attendance Days ' . $startDay . '-' . $endDay . ' - ' . $monthStart->format('F Y');
} elseif ($view === 'year') {
    for ($m = 1; $m <= 12; $m++) {
        $mm = sprintf('%04d-%02d', $yr, $m);
        $labels[] = date('M', mktime(0,0,0,$m,1));
        $stmt = $pdo->prepare("SELECT a.remarks, COUNT(*) c FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date LIKE ? AND e.branch_id = ? AND e.is_deleted=0 GROUP BY a.remarks");
        $stmt->execute([$mm . '%', $hrBranchId]);
        $counts = ['present'=>0,'late'=>0,'absent'=>0];
        foreach ($stmt->fetchAll() as $row) { if (isset($counts[$row['remarks']])) $counts[$row['remarks']] = (int)$row['c']; }
        $presentData[] = $counts['present'];
        $lateData[] = $counts['late'];
        $absentData[] = $counts['absent'];
    }
    $chartTitle = "Attendance Trend — $yr";
} else { // month
    $firstDay = strtotime($mon . '-01');
    $daysInMonth = (int)date('t', $firstDay);
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = date('Y-m-', $firstDay) . sprintf('%02d', $d);
        $labels[] = $d;
        $stmt = $pdo->prepare("SELECT a.remarks, COUNT(*) c FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date = ? AND e.branch_id = ? AND e.is_deleted=0 GROUP BY a.remarks");
        $stmt->execute([$dateStr, $hrBranchId]);
        $counts = ['present'=>0,'late'=>0,'absent'=>0];
        foreach ($stmt->fetchAll() as $row) { if (isset($counts[$row['remarks']])) $counts[$row['remarks']] = (int)$row['c']; }
        $presentData[] = $counts['present'];
        $lateData[] = $counts['late'];
        $absentData[] = 0; // absentees not logged unless explicitly marked
    }
    $chartTitle = "Daily Attendance — " . date('F Y', $firstDay);
}

// Total hours per employee for the selected period
if ($view === 'day') { $rangeStart = $day; $rangeEnd = $day; }
elseif ($view === 'week') { $rangeStart = $weekStart->format('Y-m-d'); $rangeEnd = $weekEnd->format('Y-m-d'); }
elseif ($view === 'days_1_5') { $rangeStart = $mon . '-01'; $rangeEnd = $mon . '-05'; }
elseif ($view === 'days_15_30') { $rangeStart = $mon . '-15'; $rangeEnd = $mon . '-' . sprintf('%02d', min(30, (int)$monthStart->format('t'))); }
elseif ($view === 'year') { $rangeStart = "$yr-01-01"; $rangeEnd = "$yr-12-31"; }
else { $rangeStart = $mon . '-01'; $rangeEnd = date('Y-m-t', strtotime($mon . '-01')); }

$hoursStmt = $pdo->prepare("SELECT e.first_name, e.last_name, e.employee_code,
    COALESCE(SUM(a.total_hours),0) total_hours, COUNT(a.id) days_logged
    FROM employees e LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ?
    WHERE e.is_deleted = 0 AND e.branch_id = ? GROUP BY e.id ORDER BY total_hours DESC");
$hoursStmt->execute([$rangeStart, $rangeEnd, $hrBranchId]);
$hoursRows = $hoursStmt->fetchAll();

$presentTotal = array_sum($presentData);
$lateTotal = array_sum($lateData);
$absentTotal = array_sum($absentData);
$attendanceTotal = $presentTotal + $lateTotal + $absentTotal;
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= e($page_title) ?> — Cedar Wellness Hub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .report-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
  .report-kpi { position:relative; overflow:hidden; min-height:112px; }
  .report-kpi::after { content:''; position:absolute; width:80px; height:80px; right:-28px; bottom:-38px; border:12px solid currentColor; border-radius:50%; opacity:.08; }
  .report-kpi .kpi-label { color:var(--ink-soft); font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
  .report-kpi .kpi-value { margin-top:8px; font:800 30px/1 var(--font-display); letter-spacing:-.04em; }
  .report-kpi.present { color:#5E7D68; }
  .report-kpi.late { color:#C18431; }
  .report-kpi.absent { color:#A8525B; }
  .report-chart-grid { display:grid; grid-template-columns:minmax(0,2fr) minmax(260px,.75fr); gap:18px; margin-bottom:20px; }
  .chart-card { min-width:0; }
  .chart-subtitle { margin:2px 0 0; color:var(--ink-soft); font-size:12px; }
  .chart-wrap { position:relative; height:340px; }
  .donut-wrap { position:relative; height:280px; max-width:300px; margin:10px auto 0; }
  .donut-center { position:absolute; inset:50% auto auto 50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none; }
  .donut-center strong { display:block; color:var(--cedar-deep); font:800 28px/1 var(--font-display); }
  .donut-center span { color:var(--ink-soft); font-size:10px; text-transform:uppercase; letter-spacing:.08em; }
  @media (max-width:900px) { .report-chart-grid { grid-template-columns:1fr; } }
  @media (max-width:600px) { .report-kpis { grid-template-columns:1fr; } .chart-wrap { height:300px; } }
</style></head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="content">

      <div class="toolbar">
        <form method="GET" class="flex gap-8">
          <select name="view" onchange="this.form.submit()">
            <option value="day" <?= $view==='day'?'selected':'' ?>>Day</option>
            <option value="week" <?= $view==='week'?'selected':'' ?>>Weekly</option>
            <option value="days_1_5" <?= $view==='days_1_5'?'selected':'' ?>>Days 1-5</option>
            <option value="days_15_30" <?= $view==='days_15_30'?'selected':'' ?>>Days 15-30</option>
            <option value="month" <?= $view==='month'?'selected':'' ?>>Month</option>
            <option value="year" <?= $view==='year'?'selected':'' ?>>Year</option>
          </select>
          <?php if ($view === 'day'): ?>
            <input type="date" name="day" value="<?= e($day) ?>" onchange="this.form.submit()">
          <?php elseif ($view === 'week'): ?>
            <input type="week" name="week" value="<?= e($week) ?>" onchange="this.form.submit()">
          <?php elseif (in_array($view, ['month', 'days_1_5', 'days_15_30'], true)): ?>
            <input type="month" name="mon" value="<?= e($mon) ?>" onchange="this.form.submit()">
          <?php else: ?>
            <input type="number" name="yr" value="<?= e($yr) ?>" style="width:100px;" onchange="this.form.submit()">
          <?php endif; ?>
        </form>
      </div>

      <div class="report-kpis">
        <div class="card report-kpi present"><div class="kpi-label">Present</div><div class="kpi-value"><?= number_format($presentTotal) ?></div></div>
        <div class="card report-kpi late"><div class="kpi-label">Late arrivals</div><div class="kpi-value"><?= number_format($lateTotal) ?></div></div>
        <div class="card report-kpi absent"><div class="kpi-label">Absent</div><div class="kpi-value"><?= number_format($absentTotal) ?></div></div>
      </div>

      <div class="report-chart-grid">
        <div class="card chart-card">
          <div class="card-head">
            <div><h3><?= e($chartTitle) ?></h3><p class="chart-subtitle">Attendance activity across the selected period</p></div>
          </div>
          <div class="chart-wrap"><canvas id="attChart"></canvas></div>
        </div>
        <div class="card chart-card">
          <div class="card-head">
            <div><h3>Distribution</h3><p class="chart-subtitle">Status breakdown</p></div>
          </div>
          <div class="donut-wrap">
            <canvas id="distributionChart"></canvas>
            <div class="donut-center"><strong><?= number_format($attendanceTotal) ?></strong><span>Total records</span></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Total Hours per Employee</h3><span class="muted" style="font-size:12.5px;"><?= e($rangeStart) ?> to <?= e($rangeEnd) ?></span></div>
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Employee</th><th>Code</th><th>Days Logged</th><th>Total Hours</th></tr></thead>
          <tbody>
            <?php foreach ($hoursRows as $h): ?>
            <tr>
              <td><?= e($h['first_name'].' '.$h['last_name']) ?></td>
              <td><?= e($h['employee_code']) ?></td>
              <td><?= (int)$h['days_logged'] ?></td>
              <td><strong><?= number_format((float)$h['total_hours'], 2) ?>h</strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('attChart');
const themeStyles = getComputedStyle(document.documentElement);
const chartText = themeStyles.getPropertyValue('--ink-soft').trim() || '#706875';
const chartSurface = themeStyles.getPropertyValue('--surface-raised').trim() || '#FFFFFF';
Chart.defaults.font.family = "Inter, system-ui, sans-serif";
Chart.defaults.color = chartText;

const chartArea = ctx.getContext('2d');
const presentGradient = chartArea.createLinearGradient(0, 0, 0, 340);
presentGradient.addColorStop(0, '#75518A');
presentGradient.addColorStop(1, '#A68CAF');

new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { label: 'Present', data: <?= json_encode($presentData) ?>, backgroundColor: presentGradient, borderRadius: 7, borderSkipped: false, maxBarThickness: 32 },
      { label: 'Late', data: <?= json_encode($lateData) ?>, backgroundColor: '#D69A43', borderRadius: 7, borderSkipped: false, maxBarThickness: 32 },
      { label: 'Absent', data: <?= json_encode($absentData) ?>, backgroundColor: '#B96570', borderRadius: 7, borderSkipped: false, maxBarThickness: 32 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    animation: { duration: 700, easing: 'easeOutQuart' },
    plugins: {
      legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 22, boxWidth: 8, boxHeight: 8 } },
      tooltip: { backgroundColor: '#2E1939', padding: 12, cornerRadius: 10, displayColors: true, boxPadding: 5 }
    },
    scales: {
      x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 16 } },
      y: { stacked: true, beginAtZero: true, border: { display: false }, grid: { color: 'rgba(67,39,82,.08)' }, ticks: { precision: 0, padding: 8 } }
    }
  }
});

new Chart(document.getElementById('distributionChart'), {
  type: 'doughnut',
  data: {
    labels: ['Present', 'Late', 'Absent'],
    datasets: [{
      data: [<?= $presentTotal ?>, <?= $lateTotal ?>, <?= $absentTotal ?>],
      backgroundColor: ['#75518A', '#D69A43', '#B96570'],
      borderColor: chartSurface, borderWidth: 4, hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '72%',
    plugins: {
      legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, boxWidth: 8, boxHeight: 8 } },
      tooltip: { backgroundColor: '#2E1939', padding: 12, cornerRadius: 10 }
    }
  }
});
</script>
</body></html>
