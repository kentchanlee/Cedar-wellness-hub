<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
$hrBranchId = require_hr_branch($pdo);
$active = 'leave_requests'; $base = '..'; $page_title = 'Leave Requests'; $breadcrumb = 'Review and approve employee leave';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $decision = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
    $update = $pdo->prepare("UPDATE leave_requests lr JOIN employees e ON e.id=lr.employee_id SET lr.status = ?, lr.reviewed_by = ?, lr.reviewed_at = NOW() WHERE lr.id = ? AND e.branch_id = ? AND e.is_deleted=0");
    $update->execute([$decision, $_SESSION['user_id'], $id, $hrBranchId]);
    if (!$update->rowCount()) { flash('error', 'That leave request is not in your assigned branch.'); redirect(BASE_URL . '/modules/leave_requests.php'); }
    flash('success', 'Leave request ' . $decision . '.');
    redirect(BASE_URL . '/modules/leave_requests.php');
}

$filter = $_GET['status'] ?? 'pending';
$sql = "SELECT lr.*, e.first_name, e.last_name, e.employee_code FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id";
$params = [$hrBranchId];
$sql .= " WHERE e.branch_id = ? AND e.is_deleted=0";
if ($filter !== 'all') { $sql .= " AND lr.status = ?"; $params[] = $filter; }
$sql .= " ORDER BY lr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
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

      <div class="tabs">
        <a class="tab <?= $filter==='pending'?'active':'' ?>" href="?status=pending">Pending</a>
        <a class="tab <?= $filter==='approved'?'active':'' ?>" href="?status=approved">Approved</a>
        <a class="tab <?= $filter==='rejected'?'active':'' ?>" href="?status=rejected">Rejected</a>
        <a class="tab <?= $filter==='all'?'active':'' ?>" href="?status=all">All</a>
      </div>

      <div class="card">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Reason</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6"><div class="empty-state"><div class="ic"><?= ui_icon('document') ?></div>No leave requests here.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['first_name'].' '.$r['last_name']) ?> <span class="muted">(<?= e($r['employee_code']) ?>)</span></td>
              <td><?= e(ucfirst($r['leave_type'])) ?></td>
              <td><?= e($r['date_from']) ?> &rarr; <?= e($r['date_to']) ?></td>
              <td style="max-width:220px;"><?= e($r['reason']) ?: '—' ?></td>
              <td><?php $b=['pending'=>'badge-amber','approved'=>'badge-green','rejected'=>'badge-red'][$r['status']]; ?>
                <span class="badge <?= $b ?>"><?= e($r['status']) ?></span></td>
              <td class="text-right">
                <?php if ($r['status'] === 'pending'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="decision" value="approved">
                    <button class="btn btn-sm btn-brass">Approve</button>
                  </form>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="decision" value="rejected">
                    <button class="btn btn-sm btn-danger">Reject</button>
                  </form>
                <?php else: ?>—<?php endif; ?>
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
