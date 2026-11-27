<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['employee']);
require_once __DIR__ . '/../includes/management.php';
$active = 'my_leave'; $base = '..'; $page_title = 'Leave Requests'; $breadcrumb = 'File and track your leave';
$empId = $_SESSION['employee_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        management_csrf();
        $type=management_text('leave_type',20);$from=management_date('date_from');$to=management_date('date_to');
        $reason=management_text('reason',2000,false);
        if (!in_array($type,['sick','vacation','emergency','unpaid','other'],true)) throw new InvalidArgumentException('Select a valid leave type.');
        if ($from<date('Y-m-d') || $to<$from) throw new InvalidArgumentException('Choose dates from today onward, with the end date on or after the start date.');
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT id FROM employees WHERE id=? AND is_deleted=0 FOR UPDATE');$q->execute([$empId]);
        if (!$q->fetch()) throw new InvalidArgumentException('Employee record is unavailable.');
        $q=$pdo->prepare("SELECT id FROM leave_requests WHERE employee_id=? AND status IN ('pending','approved') AND date_from<=? AND date_to>=? LIMIT 1");$q->execute([$empId,$to,$from]);
        if ($q->fetch()) throw new InvalidArgumentException('You already have a pending or approved leave request overlapping these dates.');
        $q=$pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status='pending'");$q->execute([$empId]);
        if ($q->fetchColumn()>=3) throw new InvalidArgumentException('You may have up to three pending leave requests. Wait for HR to review them.');
        $q=$pdo->prepare('SELECT created_at FROM leave_requests WHERE employee_id=? ORDER BY created_at DESC LIMIT 1');$q->execute([$empId]);$latest=$q->fetchColumn();
        if ($latest && time()-strtotime($latest)<60) throw new InvalidArgumentException('Wait 60 seconds before submitting another leave request.');
        $pdo->prepare('INSERT INTO leave_requests (employee_id,leave_type,date_from,date_to,reason) VALUES (?,?,?,?,?)')->execute([$empId,$type,$from,$to,$reason]);
        $pdo->commit();flash('success','Leave request submitted for HR review.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Leave submission: '.$ex->getMessage());
        flash('error',$ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Leave could not be submitted. Try again.');
        $_SESSION['leave_form_old']=$_POST;
    }
    redirect(BASE_URL.'/employee/my_leave.php');
}
$old=$_SESSION['leave_form_old'] ?? null;unset($_SESSION['leave_form_old']);

$rows = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
$rows->execute([$empId]);
$rows = $rows->fetchAll();
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
      <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>

      <div class="grid" style="grid-template-columns: 1fr 1.4fr; gap: 22px;">
        <div class="card">
          <div class="card-head"><h3>File a Leave Request</h3></div>
          <form method="POST" id="leaveForm"><input type="hidden" name="csrf" value="<?= e(management_token()) ?>">
            <div class="field"><label>Leave Type</label>
              <select name="leave_type" required>
                <option value="sick">Sick Leave</option>
                <option value="vacation">Vacation Leave</option>
                <option value="emergency">Emergency Leave</option>
                <option value="unpaid">Unpaid Leave</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-row">
              <div class="field"><label>From</label><input type="date" name="date_from" min="<?= date('Y-m-d') ?>" required></div>
              <div class="field"><label>To</label><input type="date" name="date_to" min="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="field"><label>Reason</label><textarea name="reason" maxlength="2000" rows="3" placeholder="Optional details"></textarea></div>
            <p class="muted">Up to 3 pending requests. Dates cannot overlap an existing pending or approved request. Wait 60 seconds between submissions.</p><button class="btn btn-primary" type="submit">Submit Request</button>
          </form>
        </div>

        <div class="card">
          <div class="card-head"><h3>Your Requests</h3></div>
          <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Type</th><th>Dates</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (!$rows): ?><tr><td colspan="3"><div class="empty-state"><div class="ic"><?= ui_icon('document') ?></div>No requests filed yet.</div></td></tr><?php endif; ?>
              <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e(ucfirst($r['leave_type'])) ?></td>
                <td><?= e($r['date_from']) ?> &rarr; <?= e($r['date_to']) ?></td>
                <td><?php $b=['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'][$r['status']]; ?>
                  <span class="badge <?= $b ?>"><?= e($r['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const form=document.getElementById('leaveForm');
const oldLeave=<?= json_encode($old,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
if(oldLeave)for(const name of ['leave_type','date_from','date_to','reason'])form.elements[name].value=oldLeave[name] || '';
form.elements.date_from.addEventListener('change',()=>{form.elements.date_to.min=form.elements.date_from.value || form.elements.date_from.min;});
form.addEventListener('submit',()=>{if(form.checkValidity())form.querySelector('button').disabled=true;});
</script></body></html>
