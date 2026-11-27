<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
$active = 'dashboard'; $base = '..'; $page_title = 'HR Dashboard'; $breadcrumb = 'Workforce overview';

// Scope every HR dashboard metric and list to employees in the HR account's assigned branches.
$branchStmt = $pdo->prepare("SELECT uba.branch_id FROM user_branch_assignments uba JOIN branches b ON b.id = uba.branch_id WHERE uba.user_id = ? AND b.is_deleted = 0");
$branchStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
$hrBranchIds = array_values(array_unique(array_map('intval', $branchStmt->fetchAll(PDO::FETCH_COLUMN))));
$hasBranches = count($hrBranchIds) > 0;
$branchWhere = $hasBranches ? 'e.branch_id IN (' . implode(',', array_fill(0, count($hrBranchIds), '?')) . ')' : '1 = 0';
$branchParams = $hrBranchIds;

$totalEmployeesStmt = $pdo->prepare("SELECT COUNT(*) c FROM employees e WHERE e.is_deleted = 0 AND $branchWhere");
$totalEmployeesStmt->execute($branchParams);
$totalEmployees = (int)$totalEmployeesStmt->fetch()['c'];

$pendingLeaveStmt = $pdo->prepare("SELECT COUNT(*) c FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id WHERE lr.status = 'pending' AND e.is_deleted = 0 AND $branchWhere");
$pendingLeaveStmt->execute($branchParams);
$pendingLeave = (int)$pendingLeaveStmt->fetch()['c'];

$today = date('Y-m-d');
$presentStmt = $pdo->prepare("SELECT COUNT(DISTINCT a.employee_id) c FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.attendance_date = ? AND a.time_in IS NOT NULL AND e.is_deleted = 0 AND $branchWhere");
$presentStmt->execute(array_merge([$today], $branchParams));
$presentToday = (int)$presentStmt->fetch()['c'];
$absentToday = max(0, $totalEmployees - $presentToday);

$bookingBranchWhere = $hasBranches ? 'bk.branch_id IN (' . implode(',', array_fill(0, count($hrBranchIds), '?')) . ')' : '1 = 0';
$pendingBookingsStmt = $pdo->prepare("SELECT COUNT(*) c FROM bookings bk WHERE bk.status = 'pending' AND $bookingBranchWhere");
$pendingBookingsStmt->execute($branchParams);
$pendingBookings = (int)$pendingBookingsStmt->fetch()['c'];

$pendingLeaveRowsStmt = $pdo->prepare("SELECT lr.*, e.first_name, e.last_name FROM leave_requests lr
    JOIN employees e ON e.id = lr.employee_id WHERE lr.status='pending' AND e.is_deleted = 0 AND $branchWhere ORDER BY lr.created_at DESC LIMIT 6");
$pendingLeaveRowsStmt->execute($branchParams);
$pendingLeaveRows = $pendingLeaveRowsStmt->fetchAll();

$todayScheduleStmt = $pdo->prepare("SELECT s.*, e.first_name, e.last_name FROM schedules s
    JOIN employees e ON e.id = s.employee_id WHERE s.schedule_date = ? AND e.is_deleted = 0 AND $branchWhere ORDER BY s.shift_start LIMIT 8");
$todayScheduleStmt->execute(array_merge([$today], $branchParams));
$todaySchedule = $todayScheduleStmt->fetchAll();
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
          <div class="ic-badge"><?= ui_icon('users') ?></div>
          <div class="num"><?= (int)$totalEmployees ?></div>
          <div class="lbl">Active Employees</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('check') ?></div>
          <div class="num"><?= (int)$presentToday ?></div>
          <div class="lbl">Present Today</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('x') ?></div>
          <div class="num"><?= (int)$absentToday ?></div>
          <div class="lbl">Absent / Not Yet In</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('document') ?></div>
          <div class="num"><?= (int)$pendingLeave ?></div>
          <div class="lbl">Pending Leave Requests</div>
        </div>
        <div class="card stat-card">
          <div class="ic-badge"><?= ui_icon('clock') ?></div>
          <div class="num"><?= (int)$pendingBookings ?></div>
          <div class="lbl">Pending Bookings</div>
        </div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <div class="card-head">
            <h3>Leave Requests Awaiting Approval</h3>
            <a href="../modules/leave_requests.php" class="btn btn-outline btn-sm">Review</a>
          </div>
          <?php if (!$pendingLeaveRows): ?>
            <div class="empty-state"><div class="ic">&#9989;</div>All caught up — no pending requests.</div>
          <?php else: ?>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($pendingLeaveRows as $lr): ?>
                <tr>
                  <td><?= e($lr['first_name'].' '.$lr['last_name']) ?></td>
                  <td><?= e(ucfirst($lr['leave_type'])) ?></td>
                  <td><?= e($lr['date_from']) ?> &rarr; <?= e($lr['date_to']) ?></td>
                  <td><a href="../modules/leave_requests.php" class="btn btn-sm btn-brass">Review</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-head">
            <h3>Today's Duty Schedule</h3>
            <a href="../modules/schedule.php" class="btn btn-outline btn-sm">Full Calendar</a>
          </div>
          <?php if (!$todaySchedule): ?>
            <div class="empty-state"><div class="ic"><?= ui_icon('clock') ?></div>No shifts scheduled for today yet.</div>
          <?php else: ?>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Employee</th><th>Shift</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($todaySchedule as $s): ?>
                <tr>
                  <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
                  <td><?= $s['is_day_off'] ? '—' : e(substr($s['shift_start'],0,5).' - '.substr($s['shift_end'],0,5)) ?></td>
                  <td><?= $s['is_day_off'] ? '<span class="badge badge-grey">Day Off</span>' : '<span class="badge badge-sage">On Duty</span>' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="ring-divider"><span class="ring-mark"></span></div>

      <div class="grid grid-3">
        <div class="card">
          <div class="card-head"><h3>Pending Bookings</h3></div>
          <p class="num" style="font-size:26px;"><?= (int)$pendingBookings ?></p>
          <a class="btn btn-outline btn-sm" href="../modules/bookings.php">Manage Bookings</a>
        </div>
        <div class="card">
          <div class="card-head"><h3>Manage Employees</h3></div>
          <p class="muted" style="font-size:13px;">Add, edit, or update employee profiles and branch assignment.</p>
          <a class="btn btn-outline btn-sm" href="../modules/employees.php">Go to Employees</a>
        </div>
        <div class="card">
          <div class="card-head"><h3>Attendance Reports</h3></div>
          <p class="muted" style="font-size:13px;">View charts filtered by day, month, or year.</p>
          <a class="btn btn-outline btn-sm" href="../modules/reports.php">View Reports</a>
        </div>
      </div>

    </div>
  </div>
</div>
</body></html>
