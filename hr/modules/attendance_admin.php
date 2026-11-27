<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
require_once __DIR__ . '/../includes/management.php';
$active = 'attendance_admin'; $base = '..'; $page_title = 'Attendance Logs'; $breadcrumb = 'Kiosk scanner and daily records';

$hrBranchId = require_hr_branch($pdo);
$date = $_GET['date'] ?? date('Y-m-d');
$empFilter = $_GET['employee_id'] ?? '';

$empStmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE is_deleted=0 AND branch_id=? ORDER BY first_name");
$empStmt->execute([$hrBranchId]);
$employees = $empStmt->fetchAll();

$sql = "SELECT a.*, e.first_name, e.last_name, e.employee_code,
               s.shift_start, s.break_start, s.break_end, s.shift_end, s.is_day_off
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        LEFT JOIN schedules s ON s.employee_id = a.employee_id AND s.schedule_date = a.attendance_date
        WHERE a.attendance_date = ? AND e.branch_id = ? AND e.is_deleted = 0";
$params = [$date, $hrBranchId];
if ($empFilter !== '') { $sql .= " AND a.employee_id = ?"; $params[] = $empFilter; }
$sql .= " ORDER BY a.time_in DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
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

      <div class="grid" style="grid-template-columns: 1fr 1.4fr; gap:22px; margin-bottom:20px;">
        <div class="card">
          <div class="card-head"><h3>Kiosk Scanner</h3></div>
          <p class="muted" style="font-size:13px;">Use this station to scan any employee's QR badge for Time In or Time Out.</p>
          <div class="qr-frame" style="max-width:260px;"><div id="qr-reader"></div></div>
          <div class="qr-status-pill" id="scanStatus" role="status" aria-live="polite">Ready to scan…</div>
          <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border, #e5e7eb);">
            <h4 style="margin:0 0 8px;">Manual Attendance (QR Backup)</h4>
            <p class="muted" style="font-size:13px; margin:0 0 12px;">If an employee’s QR code does not work, enter their Employee ID or Employee Code below.</p>
            <form id="manualAttendanceForm" class="form-row">
              <div class="field" style="grid-column:1/-1;">
                <label for="employeeIdentifier">Employee ID / Code</label>
                <input id="employeeIdentifier" name="employee_identifier" type="text" autocomplete="off" required placeholder="Enter employee ID or code">
              </div>
              <div class="field" style="grid-column:1/-1;">
                <button class="btn btn-primary" type="submit" id="manualAttendanceButton">Record Attendance</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>Filter Logs</h3></div>
          <form method="GET" class="form-row">
            <div class="field"><label>Date</label><input type="date" name="date" value="<?= e($date) ?>"></div>
            <div class="field"><label>Employee</label>
              <select name="employee_id">
                <option value="">All Employees</option>
                <?php foreach ($employees as $e2): ?>
                  <option value="<?= $e2['id'] ?>" <?= $empFilter == $e2['id'] ? 'selected' : '' ?>><?= e($e2['first_name'].' '.$e2['last_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field" style="grid-column:1/-1;"><button class="btn btn-primary">Apply Filter</button></div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>Attendance — <?= e(date('F j, Y', strtotime($date))) ?></h3></div>
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Employee</th><th>Scheduled Duty</th><th>Time In</th><th>Time Out</th><th>Total Hrs</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php if (!$logs): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="ic"><?= ui_icon('clock') ?></div>No attendance records for this date.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= e($l['first_name'].' '.$l['last_name']) ?> <span class="muted">(<?= e($l['employee_code']) ?>)</span></td>
              <td><?php if (!empty($l['is_day_off'])): ?><span class="badge badge-grey">Day Off</span><?php elseif (!empty($l['shift_start']) && !empty($l['shift_end'])): ?><span style="white-space:nowrap;"><?= e(date('g:i A', strtotime($l['shift_start']))) ?>–<?= e(!empty($l['break_start']) ? date('g:i A', strtotime($l['break_start'])) : date('g:i A', strtotime($l['shift_end']))) ?></span><?php if (!empty($l['break_start']) && !empty($l['break_end'])): ?><br><span style="white-space:nowrap;"><?= e(date('g:i A', strtotime($l['break_end']))) ?>–<?= e(date('g:i A', strtotime($l['shift_end']))) ?></span><?php endif; ?><?php else: ?>—<?php endif; ?></td>
              <td><?= $l['time_in'] ? date('h:i A', strtotime($l['time_in'])) : '—' ?></td>
              <td><?= $l['time_out'] ? date('h:i A', strtotime($l['time_out'])) : '—' ?></td>
              <td><?= $l['total_hours'] !== null ? number_format($l['total_hours'],2).'h' : '—' ?></td>
              <td>
                <?php $b = ['present'=>'badge-green','late'=>'badge-amber','absent'=>'badge-red','half-day'=>'badge-sage'][$l['remarks']] ?? 'badge-grey'; ?>
                <span class="badge <?= $b ?>"><?= e($l['remarks']) ?></span>
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
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const statusEl = document.getElementById('scanStatus');
let busy = false;
function submitAttendance(payload) {
  if (busy) return;
  const identifier=payload.qr_value || payload.employee_identifier;
  const cooldownKey='cwh-attendance-'+identifier;
  try {if(Date.now()-Number(sessionStorage.getItem(cooldownKey) || 0)<60000) {statusEl.textContent='Attendance was just recorded. Wait 60 seconds before another punch.';return;}} catch(e) {}

  payload.csrf=<?= json_encode(management_token()) ?>;
  busy = true;
  document.querySelectorAll('#manualAttendanceForm button').forEach(b=>b.disabled=true);
  statusEl.textContent = 'Processing attendance…';
  fetch('../ajax/attendance_scan.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(payload).toString()
  }).then(r => r.json()).then(data => {
    statusEl.textContent = data.message || 'Unable to record attendance.';
    if (data.ok) {try{sessionStorage.setItem(cooldownKey,String(Date.now()));}catch(e){} setTimeout(() => location.reload(), 1200);}
  }).catch(() => statusEl.textContent = 'Network error — please try again.')
  .finally(() => setTimeout(() => { busy = false; document.querySelectorAll('#manualAttendanceForm button').forEach(b=>b.disabled=false); }, 2500));
}
function onScanSuccess(decodedText) {
  submitAttendance({ qr_value: decodedText });
}
document.getElementById('manualAttendanceForm').addEventListener('submit', function(event) {
  event.preventDefault();
  const input = document.getElementById('employeeIdentifier');
  const identifier = input.value.trim();
  if (!identifier) {
    statusEl.textContent = 'Enter an Employee ID or Employee Code.';
    input.focus();
    return;
  }
  submitAttendance({ employee_identifier: identifier });
});
if (typeof Html5Qrcode === 'undefined') {
 statusEl.textContent='QR scanner could not load. Use the Employee ID / Code field below.';
} else if (!window.isSecureContext) {
 statusEl.textContent='Camera scanning needs HTTPS or localhost. Use the Employee ID / Code field below.';
} else {
const html5QrCode = new Html5Qrcode("qr-reader");
Html5Qrcode.getCameras().then(cams => {
  if (cams && cams.length) {
    html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 200, height: 200 } }, onScanSuccess)
      .catch(() => html5QrCode.start(cams[0].id, {fps:10,qrbox:{width:200,height:200}},onScanSuccess))
      .catch(() => statusEl.textContent = 'Unable to start camera. Use the Employee ID / Code field below.');
  } else { statusEl.textContent = 'No camera found.'; }
}).catch(() => statusEl.textContent = 'Camera access denied. Use the Employee ID / Code field below.');
}
</script>
</body></html>
