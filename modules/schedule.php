<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
require_once __DIR__ . '/../includes/management.php';
$active = 'schedule'; $base = '..'; $page_title = 'Duty Scheduling'; $breadcrumb = 'Assign shifts and day-offs per employee';

$hrBranchId = require_hr_branch($pdo);
$empStmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE is_deleted=0 AND branch_id=? ORDER BY first_name");
$empStmt->execute([$hrBranchId]);
$employees = $empStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        management_csrf();
        $ids=$_POST['employee_ids'] ?? [];
        if (!is_array($ids) || !$ids || count($ids)>100) throw new InvalidArgumentException('Select 1–100 employees.');
        $ids=array_values(array_unique(array_map(function($id){$n=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$n) throw new InvalidArgumentException('Invalid employee.');return $n;},$ids)));
        sort($ids);
        $dates=$_POST['schedule_dates'] ?? [$_POST['schedule_date'] ?? ''];
        if (!is_array($dates) || !$dates || count($dates)>366) throw new InvalidArgumentException('Select 1–366 dates.');
        $dates=array_values(array_unique($dates));
        foreach ($dates as $date) {
            if (!is_string($date)) throw new InvalidArgumentException('Invalid schedule date.');
            $valid=DateTime::createFromFormat('!Y-m-d',$date);
            if (!$valid || $valid->format('Y-m-d')!==$date || $date<date('Y-m-d')) throw new InvalidArgumentException('Select valid dates today or later.');
        }
        $action=$_POST['schedule_action'] ?? '';
        if (!in_array($action,['save','cancel'],true)) throw new InvalidArgumentException('Invalid schedule action.');
        $dayoff=isset($_POST['is_day_off']) ? 1 : 0;
        $start=$end=$breakStart=$breakEnd=null;
        if ($action==='save' && !$dayoff) {
            $start=management_time('shift_start'); $end=management_time('shift_end');
            $breakStart=management_time('break_start',false);$breakEnd=management_time('break_end',false);
            if ($start >= $end) throw new InvalidArgumentException('Shift end must follow shift start.');
            if (($breakStart===null)!==($breakEnd===null) || ($breakStart!==null && ($breakStart >= $breakEnd || $breakStart<$start || $breakEnd>$end))) throw new InvalidArgumentException('Set both break times inside the shift, or clear both for a shift without a break.');
        }
        $notes=management_text('notes',255,false);
        $pdo->beginTransaction();
        $check=$pdo->prepare('SELECT id FROM employees WHERE id=? AND branch_id=? AND is_deleted=0 FOR UPDATE');
        foreach ($ids as $id) { $check->execute([$id,$hrBranchId]);if (!$check->fetch()) throw new InvalidArgumentException('Every selected employee must belong to your branch.'); }
        $leave=$pdo->prepare("SELECT id FROM leave_requests WHERE employee_id=? AND status='approved' AND date_from<=? AND date_to>=? LIMIT 1");
        $save=$pdo->prepare('INSERT INTO schedules (employee_id,schedule_date,shift_start,shift_end,break_start,break_end,is_day_off,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE shift_start=VALUES(shift_start),shift_end=VALUES(shift_end),break_start=VALUES(break_start),break_end=VALUES(break_end),is_day_off=VALUES(is_day_off),notes=VALUES(notes),created_by=VALUES(created_by)');
        $remove=$pdo->prepare('DELETE FROM schedules WHERE employee_id=? AND schedule_date=?');
        foreach ($ids as $id) foreach ($dates as $date) {
            if ($action==='cancel') $remove->execute([$id,$date]);
            else {
                if (!$dayoff) {$leave->execute([$id,$date,$date]);if ($leave->fetch()) throw new InvalidArgumentException('A selected employee has approved leave on '.$date.'. No schedules were changed.');}
                $save->execute([$id,$date,$start,$end,$breakStart,$breakEnd,$dayoff,$notes,$_SESSION['user_id']]);
            }
        }
        $pdo->commit(); flash('success','Updated '.count($ids).' employee(s) across '.count($dates).' date(s).');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Scheduling: '.$ex->getMessage());
        flash('error',$ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Schedules could not be saved. No changes were applied.');
    }
    redirect(BASE_URL.'/modules/schedule.php?employee_id='.(int)($_POST['employee_id'] ?? 0));
}

$employee_id = (int)($_GET['employee_id'] ?? ($employees[0]['id'] ?? 0));
if ($employee_id && !in_array($employee_id, array_map('intval', array_column($employees, 'id')), true)) { $employee_id = (int)($employees[0]['id'] ?? 0); }
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));

if ($month<1 || $month>12 || $year<2000 || $year>2100) { $month=(int)date('n');$year=(int)date('Y'); }
$firstDay = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('w', $firstDay); // 0=Sun

$schedMap = [];
if ($employee_id) {
    $stmt = $pdo->prepare("SELECT s.* FROM schedules s JOIN employees e ON e.id=s.employee_id WHERE s.employee_id = ? AND e.branch_id = ? AND e.is_deleted=0 AND s.schedule_date BETWEEN ? AND ?");
    $from = date('Y-m-01', $firstDay);
    $to = date('Y-m-t', $firstDay);
    $stmt->execute([$employee_id, $hrBranchId, $from, $to]);
    foreach ($stmt->fetchAll() as $row) { $schedMap[$row['schedule_date']] = $row; }
}

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
    <div class="content schedule-page">

      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>

      <div class="toolbar schedule-nav">
        <form method="GET" class="flex gap-8 schedule-employee-form">
          <select name="employee_id" onchange="this.form.submit()">
            <?php foreach ($employees as $e2): ?>
              <option value="<?= $e2['id'] ?>" <?= $employee_id == $e2['id'] ? 'selected' : '' ?>><?= e($e2['first_name'].' '.$e2['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="month" value="<?= $month ?>">
          <input type="hidden" name="year" value="<?= $year ?>">
        </form>
        <div class="spacer"></div>
        <a class="btn btn-outline btn-sm" href="?employee_id=<?= $employee_id ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&larr; Prev</a>
        <span class="badge badge-sage"><?= e(date('F Y', $firstDay)) ?></span>
        <a class="btn btn-outline btn-sm" href="?employee_id=<?= $employee_id ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next &rarr;</a>
      </div>

      <div class="toolbar schedule-actions"><span class="badge badge-sage" id="selectedCount">0 dates selected</span><div class="schedule-action-buttons"><button type="button" class="btn btn-primary" id="scheduleSelectedBtn" disabled onclick="openSelectedSchedules()">Schedule Selected Dates</button><button type="button" class="btn btn-outline" id="clearSelectedBtn" onclick="clearSelectedDates()" disabled>Clear Selection</button></div></div>
      <div class="calendar">
        <div class="cal-grid">
          <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
            <div class="cal-dow"><?= $dow ?></div>
          <?php endforeach; ?>

          <?php for ($i=0; $i < $startWeekday; $i++): ?><div class="cal-cell muted"></div><?php endfor; ?>

          <?php for ($d=1; $d <= $daysInMonth; $d++):
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = $dateStr === date('Y-m-d');
            $isPast = $dateStr < date('Y-m-d');
            $s = $schedMap[$dateStr] ?? null;
          ?>
            <div class="cal-cell schedule-day <?= $isToday ? 'today' : '' ?> <?= $isPast ? 'muted' : '' ?>" data-date="<?= $dateStr ?>" data-schedule='<?= $s ? e(json_encode($s)) : '' ?>'
                 <?php if (!$isPast): ?>onclick='if(!window.didDrag) openSched("<?= $dateStr ?>", <?= $s ? e(json_encode($s)) : 'null' ?>)'<?php endif; ?>
                 style="<?= $isPast ? 'opacity:.45;cursor:not-allowed;' : 'cursor:pointer;' ?>"
                 title="<?= $isPast ? 'Past dates cannot be scheduled' : 'Click to edit this date' ?>">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:4px;"><div class="date-num"><?= $d ?></div><?php if (!$isPast): ?><input type="checkbox" class="schedule-date-checkbox" data-date="<?= $dateStr ?>" aria-label="Select <?= $dateStr ?>" style="width:auto;margin:0;cursor:pointer;" onclick="event.stopPropagation()"><?php endif; ?></div>
              <?php if ($s): ?>
                <?php if ($s['is_day_off']): ?>
                  <span class="cal-chip badge-grey"><span class="badge badge-grey" style="width:100%;box-sizing:border-box;">Day Off</span></span>
                <?php else: ?>
                  <span class="cal-chip"><span class="badge badge-sage" style="width:100%;box-sizing:border-box;white-space:normal;line-height:1.45;"><?= e(date('g:i A', strtotime($s['shift_start']))) ?>–<?= e(!empty($s['break_start']) ? date('g:i A', strtotime($s['break_start'])) : date('g:i A', strtotime($s['shift_end']))) ?><?php if (!empty($s['break_start']) && !empty($s['break_end'])): ?><br><?= e(date('g:i A', strtotime($s['break_end']))) ?>–<?= e(date('g:i A', strtotime($s['shift_end']))) ?><?php endif; ?></span></span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="modal-backdrop" id="schedModal">
  <div class="modal">
    <div class="modal-head"><h3 style="margin:0;" id="schedModalTitle">Set Schedule</h3>
      <button class="modal-close" onclick="document.getElementById('schedModal').classList.remove('open')">&times;</button></div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= e(management_token()) ?>">
      <fieldset style="margin:0 0 16px;border:1px solid var(--line);padding:12px"><legend>Employees receiving this schedule</legend>
      <label><input type="checkbox" id="allScheduleEmployees" style="width:auto"> Select all in this branch</label>
      <div style="max-height:160px;overflow:auto">
      <?php foreach ($employees as $person): ?><label style="display:block"><input type="checkbox" name="employee_ids[]" value="<?= (int)$person['id'] ?>" style="width:auto" <?= (int)$person['id']===$employee_id ? 'checked' : '' ?>> <?= e($person['first_name'].' '.$person['last_name']) ?></label><?php endforeach; ?>
      </div></fieldset>
      <input type="hidden" name="employee_id" value="<?= e($employee_id) ?>">
      <input type="hidden" name="schedule_date" id="s_date">
      <input type="hidden" name="schedule_action" id="s_action" value="save">
      <div id="selectedDateInputs"></div>
      <p id="s_range_hint" style="margin:0 0 12px;color:var(--ink-soft);"></p>
      <div class="field" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="is_day_off" id="s_dayoff" style="width:auto;" onchange="toggleShiftFields()">
        <label style="margin:0;">Mark as Day Off</label>
      </div>
      <div class="form-row" id="shiftFields">
        <div class="field"><label>Shift Start</label><input type="time" name="shift_start" id="s_start" value="08:00" required></div>
        <div class="field"><label>Break Start</label><input type="time" name="break_start" id="s_break_start" value="12:00"></div>
        <div class="field"><label>Break End</label><input type="time" name="break_end" id="s_break_end" value="13:00"></div>
        <div class="field"><label>Shift End</label><input type="time" name="shift_end" id="s_end" value="17:00" required></div>
        <p class="muted" style="grid-column:1/-1;font-size:12px;margin-top:-8px;">Set the two working periods: Shift Start → Break Start, then Break End → Shift End. Break times are optional; if used, enter both and keep them within the shift. The duty calendar and attendance log show these same scheduled periods.</p>
      </div>
      <div class="field"><label>Notes</label><input type="text" name="notes" id="s_notes" placeholder="Optional"></div>
      <div class="schedule-modal-actions"><button type="submit" class="btn btn-primary" onclick="document.getElementById('s_action').value='save'">Save Schedule</button><button type="submit" class="btn btn-outline" onclick="if(!confirm('Cancel/remove the schedule for the selected date(s)?')) return false; document.getElementById('s_action').value='cancel'; this.form.querySelectorAll('#shiftFields input').forEach(i=>i.disabled=true)">Cancel Schedule</button></div>
    </form>
  </div>
</div>

<script>
document.getElementById('allScheduleEmployees').addEventListener('change',ev=>document.querySelectorAll('[name="employee_ids[]"]').forEach(cb=>cb.checked=ev.target.checked));
document.querySelector('#schedModal form').addEventListener('submit',ev=>{
 if (!document.querySelector('[name="employee_ids[]"]:checked')) {ev.preventDefault();alert('Select at least one employee.');return;}
 ev.target.querySelectorAll('button').forEach(b=>b.disabled=true);
});
const dateCheckboxes = Array.from(document.querySelectorAll('.schedule-date-checkbox'));
const selectedDateInputs = document.getElementById('selectedDateInputs');
function selectedCheckboxDates() { return dateCheckboxes.filter(cb => cb.checked).map(cb => cb.dataset.date).sort(); }
function updateSelectedDateControls() {
  const count = selectedCheckboxDates().length;
  document.getElementById('selectedCount').textContent = count + (count === 1 ? ' date selected' : ' dates selected');
  document.getElementById('scheduleSelectedBtn').disabled = count === 0;
  document.getElementById('clearSelectedBtn').disabled = count === 0;
}
dateCheckboxes.forEach(cb => cb.addEventListener('change', updateSelectedDateControls));
function clearSelectedDates() { dateCheckboxes.forEach(cb => cb.checked = false); updateSelectedDateControls(); }
function openSelectedSchedules() {
  const dates = selectedCheckboxDates();
  if (!dates.length) return;
  selectedDateInputs.innerHTML = '';
  dates.forEach(date => {
    const input = document.createElement('input'); input.type = 'hidden'; input.name = 'schedule_dates[]'; input.value = date;
    selectedDateInputs.appendChild(input);
  });
  window.openingCheckboxSelection = true;
  openSched(dates[0], null, dates[dates.length - 1]);
  document.getElementById('s_range_hint').textContent = dates.length + ' selected dates: ' + dates.join(', ') + '. The same shift or day-off will be applied only to these dates.';
  document.getElementById('schedModalTitle').textContent = 'Schedule Selected Dates';
}
function toggleShiftFields() {
  document.querySelectorAll('#shiftFields input').forEach(i=>i.disabled=document.getElementById('s_dayoff').checked);
  document.getElementById('shiftFields').style.display = document.getElementById('s_dayoff').checked ? 'none' : 'grid';
}
function openSched(dateStr, sched, endDate) {
  endDate = endDate || dateStr;
  if (!window.openingCheckboxSelection) selectedDateInputs.innerHTML = '';
  window.openingCheckboxSelection = false;
  document.getElementById('s_action').value = 'save';
  document.getElementById('s_start').required=true;document.getElementById('s_end').required=true;
  document.getElementById('s_date').value = dateStr;
  document.getElementById('s_range_hint').textContent = dateStr === endDate ? 'Selected date: ' + dateStr : 'Selected dates: ' + dateStr + ' to ' + endDate;
  document.getElementById('schedModalTitle').textContent = 'Set Schedule — ' + dateStr;
  document.getElementById('s_dayoff').checked = sched ? Number(sched.is_day_off)===1 : false;
  document.getElementById('s_start').value = sched && sched.shift_start ? sched.shift_start.substring(0,5) : '08:00';
  document.getElementById('s_end').value = sched && sched.shift_end ? sched.shift_end.substring(0,5) : '17:00';
  document.getElementById('s_break_start').value = sched && sched.break_start ? sched.break_start.substring(0,5) : '12:00';
  document.getElementById('s_break_end').value = sched && sched.break_end ? sched.break_end.substring(0,5) : '13:00';
  document.getElementById('s_notes').value = sched ? (sched.notes || '') : '';
  toggleShiftFields();
  document.getElementById('schedModal').classList.add('open');
}
updateSelectedDateControls();
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</body></html>
