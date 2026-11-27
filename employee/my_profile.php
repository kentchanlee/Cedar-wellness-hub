<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['employee']);
$active = 'my_profile'; $base = '..'; $page_title = 'My Profile'; $breadcrumb = 'Your HR record';

$empId = $_SESSION['employee_id'];
$stmt = $pdo->prepare("SELECT e.*, b.branch_name FROM employees e LEFT JOIN branches b ON b.id = e.branch_id WHERE e.id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();
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

      <div class="card profile-hero">
        <div class="page-profile-head">
          <?php if ($emp['photo']): ?>
            <img src="../<?= e($emp['photo']) ?>" class="profile-avatar" alt="Employee photo">
          <?php else: ?>
            <div class="avatar profile-avatar profile-avatar-fallback">
              <?= e(strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1))) ?>
            </div>
          <?php endif; ?>
          <div class="profile-summary">
            <h2 style="margin-bottom:2px;"><?= e($emp['first_name'].' '.$emp['last_name']) ?></h2>
            <p class="muted" style="margin:0 0 8px;"><?= e($emp['position']) ?> &middot; <?= e($emp['department'] ?: '—') ?></p>
            <span class="badge badge-sage">ID: <?= e($emp['employee_code']) ?></span>
            <span class="badge <?= $emp['employment_status']==='Active'?'badge-green':'badge-red' ?>"><?= e($emp['employment_status']) ?></span>
          </div>
          <div class="profile-actions">
            <a class="btn btn-outline btn-sm" href="../modules/print_profile.php?id=<?= $emp['id'] ?>" target="_blank">Print Profile</a>
            <a class="btn btn-brass btn-sm" href="../modules/print_id_card.php?id=<?= $emp['id'] ?>" target="_blank">Print ID Card</a>
          </div>
        </div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <div class="card-head"><h3>Personal Information</h3></div>
          <div class="info-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13.5px;">
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Date of Birth</div><?= e($emp['date_of_birth'] ? date('F j, Y', strtotime($emp['date_of_birth'])) : '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Gender</div><?= e($emp['gender'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Civil Status</div><?= e($emp['civil_status'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Nationality</div><?= e($emp['nationality'] ?: '—') ?></div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><h3>Contact Information</h3></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13.5px;">
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Email</div><?= e($emp['email']) ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Mobile</div><?= e($emp['contact_number'] ?: '—') ?></div>
            <div style="grid-column:1/-1;"><div class="muted" style="font-size:11px;text-transform:uppercase;">Address</div><?= e($emp['complete_address'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Emergency Contact</div><?= e($emp['emergency_contact_name'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Emergency Number</div><?= e($emp['emergency_contact_number'] ?: '—') ?></div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><h3>Employment Details</h3></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13.5px;">
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Branch</div><?= e($emp['branch_name'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Employment Type</div><?= e($emp['employment_type']) ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Date Hired</div><?= e($emp['hire_date'] ? date('F j, Y', strtotime($emp['hire_date'])) : '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Status</div><?= e($emp['employment_status']) ?></div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><h3>Government IDs</h3></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13.5px;">
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">SSS</div><?= e($emp['sss_number'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">PhilHealth</div><?= e($emp['philhealth_number'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">Pag-IBIG</div><?= e($emp['pagibig_number'] ?: '—') ?></div>
            <div><div class="muted" style="font-size:11px;text-transform:uppercase;">TIN</div><?= e($emp['tin_number'] ?: '—') ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
</body></html>
