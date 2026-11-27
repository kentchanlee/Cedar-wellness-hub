<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
require_once __DIR__ . '/../includes/management.php';
$active = 'bookings'; $base = '..'; $page_title = 'Bookings'; $breadcrumb = 'Encode customer reservations (SMS / Messenger / calls)';

$hrBranchId = require_hr_branch($pdo);
$branchStmt = $pdo->prepare("SELECT * FROM branches WHERE is_deleted=0 AND id=? ORDER BY branch_name");
$branchStmt->execute([$hrBranchId]); $branches = $branchStmt->fetchAll();
$empStmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE is_deleted=0 AND branch_id=? ORDER BY first_name");
$empStmt->execute([$hrBranchId]); $employees = $empStmt->fetchAll();

$services = require __DIR__ . '/../config/services.php';
$q=$pdo->prepare("SELECT DISTINCT service FROM bookings WHERE branch_id=? AND service<>'' ORDER BY service");$q->execute([$hrBranchId]);
$services=array_values(array_unique(array_merge($services,$q->fetchAll(PDO::FETCH_COLUMN))));
sort($services);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        management_csrf();
        $action=$_POST['action'] ?? '';
        $pdo->beginTransaction();
        // Serialize booking submissions at the branch to catch concurrent duplicates.
        $q=$pdo->prepare('SELECT id FROM branches WHERE id=? AND is_deleted=0 FOR UPDATE');$q->execute([$hrBranchId]);
        if (!$q->fetch()) throw new InvalidArgumentException('Your branch is unavailable.');
        if ($action==='create') {
            $name=management_text('customer_name',100);$contact=management_text('contact_number',30);
            if (!preg_match('/^\+?[0-9]{7,15}$/D',$contact)) throw new InvalidArgumentException('Contact number must contain 7–15 digits, with an optional leading +.');
            $service=management_text('service',150);
            if (!in_array($service,$services,true)) throw new InvalidArgumentException('Select a service from the list.');
            $date=management_date('booking_date');$time=management_time('booking_time');
            if ($date<date('Y-m-d') || strtotime($date.' '.$time)<time()) throw new InvalidArgumentException('Booking date and time must be in the future.');
            $source=management_text('source',20);
            if (!in_array($source,['sms','messenger','call','walk-in','other'],true)) throw new InvalidArgumentException('Select a valid booking source.');
            $employee=null;
            if (($_POST['assigned_employee_id'] ?? '')!=='') {
                $employee=management_id('assigned_employee_id');
                $q=$pdo->prepare("SELECT id FROM employees WHERE id=? AND branch_id=? AND is_deleted=0 AND employment_status='Active' FOR UPDATE");$q->execute([$employee,$hrBranchId]);
                if (!$q->fetch()) throw new InvalidArgumentException('Select an active employee in your branch.');
                $q=$pdo->prepare("SELECT id FROM bookings WHERE assigned_employee_id=? AND booking_date=? AND booking_time=? AND status IN ('pending','confirmed')");$q->execute([$employee,$date,$time]);
                if ($q->fetch()) throw new InvalidArgumentException('Employee already has a booking at this time.');
                $q=$pdo->prepare("SELECT id FROM schedules WHERE employee_id=? AND schedule_date=? AND (is_day_off=1 OR shift_start>? OR shift_end<=? OR (break_start<=? AND break_end>?))");$q->execute([$employee,$date,$time,$time,$time,$time]);
                if ($q->fetch()) throw new InvalidArgumentException('The selected employee is off duty or on a scheduled break at this time.');
                $q=$pdo->prepare("SELECT id FROM leave_requests WHERE employee_id=? AND status='approved' AND date_from<=? AND date_to>=?");$q->execute([$employee,$date,$date]);
                if ($q->fetch()) throw new InvalidArgumentException('The selected employee is on approved leave.');
            }
            $q=$pdo->prepare("SELECT id FROM bookings WHERE branch_id=? AND contact_number=? AND service=? AND booking_date=? AND booking_time=? AND status<>'cancelled'");$q->execute([$hrBranchId,$contact,$service,$date,$time]);
            if ($q->fetch()) throw new InvalidArgumentException('This booking already exists.');
            if (time()-($_SESSION['booking_saved_at'] ?? 0)<10) throw new InvalidArgumentException('Please wait 10 seconds before creating another booking.');
            $pdo->prepare('INSERT INTO bookings (customer_name,contact_number,service,branch_id,booking_date,booking_time,source,assigned_employee_id,encoded_by) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$name,$contact,$service,$hrBranchId,$date,$time,$source,$employee,$_SESSION['user_id']]);
        } elseif ($action==='update_status') {
            $status=management_text('status',20);$id=management_id('id');
            if (!in_array($status,['pending','confirmed','completed','cancelled'],true)) throw new InvalidArgumentException('Invalid booking status.');
            $q=$pdo->prepare('SELECT id FROM bookings WHERE id=? AND branch_id=? FOR UPDATE');$q->execute([$id,$hrBranchId]);
            if (!$q->fetch()) throw new InvalidArgumentException('Booking not found in your branch.');
            $pdo->prepare('UPDATE bookings SET status=? WHERE id=? AND branch_id=?')->execute([$status,$id,$hrBranchId]);
        } else throw new InvalidArgumentException('Invalid action.');
        $pdo->commit();if ($action==='create') $_SESSION['booking_saved_at']=time();
        flash('success','Booking saved.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Bookings: '.$ex->getMessage());
        flash('error',$ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Booking could not be saved. Try again.');
        if (($_POST['action'] ?? '')==='create') $_SESSION['booking_form_old']=$_POST;
    }
    redirect(BASE_URL.'/modules/bookings.php');
}
$old=$_SESSION['booking_form_old'] ?? null;unset($_SESSION['booking_form_old']);

$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT b.*, br.branch_name, e.first_name, e.last_name FROM bookings b
        LEFT JOIN branches br ON br.id = b.branch_id
        LEFT JOIN employees e ON e.id = b.assigned_employee_id";
$params = [$hrBranchId];
$sql .= " WHERE b.branch_id = ?";
if ($statusFilter !== 'all') { $sql .= " AND b.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();
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
      <?php if ($msg=flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

      <div class="toolbar">
        <div class="tabs" style="border:none;margin:0;">
          <a class="tab <?= $statusFilter==='all'?'active':'' ?>" href="?status=all">All</a>
          <a class="tab <?= $statusFilter==='pending'?'active':'' ?>" href="?status=pending">Pending</a>
          <a class="tab <?= $statusFilter==='confirmed'?'active':'' ?>" href="?status=confirmed">Confirmed</a>
          <a class="tab <?= $statusFilter==='completed'?'active':'' ?>" href="?status=completed">Completed</a>
        </div>
        <div class="spacer"></div>
        <button class="btn btn-brass" onclick="document.getElementById('bkModal').classList.add('open')">+ Encode Booking</button>
      </div>

      <div class="card">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Customer</th><th>Service</th><th>Branch</th><th>Date/Time</th><th>Source</th><th>Assigned To</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if (!$bookings): ?><tr><td colspan="8"><div class="empty-state"><div class="ic"><?= ui_icon('clock') ?></div>No bookings yet.</div></td></tr><?php endif; ?>
            <?php foreach ($bookings as $bk): ?>
            <tr>
              <td><?= e($bk['customer_name']) ?><br><span class="muted" style="font-size:12px;"><?= e($bk['contact_number']) ?></span></td>
              <td><?= e($bk['service']) ?></td>
              <td><?= e($bk['branch_name'] ?? '—') ?></td>
              <td><?= e(date('M j, Y', strtotime($bk['booking_date']))) ?> <?= e(substr($bk['booking_time'],0,5)) ?></td>
              <td><span class="badge badge-grey"><?= e($bk['source']) ?></span></td>
              <td><?= $bk['first_name'] ? e($bk['first_name'].' '.$bk['last_name']) : '—' ?></td>
              <td>
                <?php $b=['pending'=>'badge-amber','confirmed'=>'badge-sage','completed'=>'badge-green','cancelled'=>'badge-red'][$bk['status']]; ?>
                <span class="badge <?= $b ?>"><?= e($bk['status']) ?></span>
              </td>
              <td class="text-right">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="id" value="<?= $bk['id'] ?>">
                  <select name="status" onchange="this.form.submit()" class="btn-sm" style="padding:5px 8px;border-radius:6px;border:1px solid #ddd;">
                    <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $bk['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
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

<div class="modal-backdrop" id="bkModal">
  <div class="modal">
    <div class="modal-head"><h3 style="margin:0;">Encode Customer Booking</h3>
      <button class="modal-close" onclick="document.getElementById('bkModal').classList.remove('open')">&times;</button></div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="field"><label>Customer Name</label><input type="text" name="customer_name" maxlength="100" required></div>
        <div class="field"><label>Contact Number</label><input type="text" name="contact_number" inputmode="tel" pattern="[+]?[0-9]{7,15}" maxlength="16" required></div>
      </div>
      <div class="field"><label>Service Requested</label><select name="service" required><option value="">— Select service —</option><?php foreach ($services as $service): ?><option value="<?= e($service) ?>"><?= e($service) ?></option><?php endforeach; ?></select></div>
      <div class="form-row">
        <div class="field"><label>Branch</label>
          <select name="branch_id" required>
            <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['branch_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Received Via</label>
          <select name="source">
            <option value="sms">SMS</option><option value="messenger">Messenger</option>
            <option value="call">Phone Call</option><option value="walk-in">Walk-in</option><option value="other">Other</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="field"><label>Booking Date</label><input type="date" name="booking_date" min="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label>Booking Time</label><input type="time" name="booking_time" required></div>
      </div>
      <div class="field"><label>Assign To Employee (optional)</label>
        <select name="assigned_employee_id"><option value="">— Unassigned —</option>
          <?php foreach ($employees as $e2): ?><option value="<?= $e2['id'] ?>"><?= e($e2['first_name'].' '.$e2['last_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Save Booking</button>
    </form>
  </div>
</div>
<script>
const oldBooking=<?= json_encode($old,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
if(oldBooking) {const f=document.querySelector('#bkModal form');for(const name of ['customer_name','contact_number','service','booking_date','booking_time','source','assigned_employee_id'])f.elements[name].value=oldBooking[name] || '';document.getElementById('bkModal').classList.add('open');}
document.querySelectorAll('form[method="POST"]').forEach(f=>f.addEventListener('submit',()=>{if(f.checkValidity())f.querySelectorAll('button').forEach(b=>b.disabled=true);}));

document.querySelectorAll('.modal-backdrop').forEach(m => { m.addEventListener('click', e => { if (e.target===m) m.classList.remove('open'); }); });
</script>
</body></html>
