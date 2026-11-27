<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$company = require __DIR__ . '/../config/company.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT e.*, b.branch_name FROM employees e LEFT JOIN branches b ON b.id = e.branch_id WHERE e.id = ? AND e.is_deleted = 0");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) { die('Employee record not found.'); }

// Access control: HR/Superadmin can view anyone; employees may only view their own profile
if ($_SESSION['role'] === 'employee' && (int)$_SESSION['employee_id'] !== $id) {
    die('You are only allowed to view and print your own profile.');
}

function fullname($e) {
    return trim($e['first_name'] . ' ' . ($e['middle_name'] ? $e['middle_name'][0].'. ' : '') . $e['last_name'] . ' ' . ($e['suffix'] ?? ''));
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Employee Profile — <?= e(fullname($emp)) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  body { background: #fff; }
  .sheet { max-width: 780px; margin: 30px auto; padding: 40px; }
  .sheet-head { display:flex; align-items:center; gap:16px; border-bottom: 3px solid var(--cedar-deep); padding-bottom: 18px; margin-bottom: 24px; }
  .sheet-head img { width: 56px; height: 56px; }
  .sheet-head h1 { font-size: 22px; margin: 0; }
  .sheet-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--ink-soft); }
  .profile-top { display:flex; gap: 24px; align-items:flex-start; margin-bottom: 26px; }
  .profile-photo { width: 120px; height: 120px; border-radius: 10px; object-fit: cover; background: var(--sage-soft); border: 1px solid #eee; }
  .profile-id { font-family: var(--font-display); font-size: 24px; font-weight:800; color: var(--cedar-deep); margin: 0; }
  .section-title { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: var(--brass); font-weight: 700; margin: 22px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 24px; font-size: 13.5px; }
  .info-grid .lbl { color: var(--ink-soft); font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
  .print-bar { max-width: 780px; margin: 16px auto 0; text-align: right; }
  @media print {
    .print-bar { display: none; }
    .sheet { margin: 0; padding: 0; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
  <div class="print-bar"><button class="btn btn-brass" onclick="window.print()"><?= ui_icon('print') ?> Print This Profile</button></div>
  <div class="sheet">
    <div class="sheet-head">
      <img src="<?= e($company['logo_web_path']) ?>" alt="logo">
      <div>
        <h1><?= e($company['name']) ?></h1>
        <p><?= e($company['tagline']) ?> &middot; <?= e($company['address']) ?></p>
        <p><?= e($company['contact']) ?></p>
      </div>
    </div>

    <div class="profile-top">
      <?php if ($emp['photo']): ?>
        <img class="profile-photo" src="../<?= e($emp['photo']) ?>">
      <?php else: ?>
        <div class="profile-photo" style="display:flex;align-items:center;justify-content:center;font-size:34px;color:var(--cedar-deep);font-family:var(--font-display);">
          <?= e(strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1))) ?>
        </div>
      <?php endif; ?>
      <div>
        <p class="profile-id"><?= e(fullname($emp)) ?></p>
        <p class="muted" style="margin:2px 0 8px;"><?= e($emp['position']) ?> &middot; <?= e($emp['department'] ?: '—') ?></p>
        <span class="badge badge-sage">ID: <?= e($emp['employee_code']) ?></span>
        <span class="badge <?= $emp['employment_status']==='Active'?'badge-green':'badge-red' ?>"><?= e($emp['employment_status']) ?></span>
        <span class="badge badge-grey"><?= e($emp['employment_type']) ?></span>
      </div>
    </div>

    <div class="section-title">Personal Information</div>
    <div class="info-grid">
      <div><div class="lbl">Date of Birth</div><?= e($emp['date_of_birth'] ? date('F j, Y', strtotime($emp['date_of_birth'])) : '—') ?></div>
      <div><div class="lbl">Gender</div><?= e($emp['gender'] ?: '—') ?></div>
      <div><div class="lbl">Civil Status</div><?= e($emp['civil_status'] ?: '—') ?></div>
      <div><div class="lbl">Nationality</div><?= e($emp['nationality'] ?: '—') ?></div>
    </div>

    <div class="section-title">Contact Information</div>
    <div class="info-grid">
      <div><div class="lbl">Email</div><?= e($emp['email']) ?></div>
      <div><div class="lbl">Mobile Number</div><?= e($emp['contact_number'] ?: '—') ?></div>
      <div style="grid-column:1/-1;"><div class="lbl">Complete Address</div><?= e($emp['complete_address'] ?: '—') ?></div>
      <div><div class="lbl">Emergency Contact</div><?= e($emp['emergency_contact_name'] ?: '—') ?></div>
      <div><div class="lbl">Emergency Contact No.</div><?= e($emp['emergency_contact_number'] ?: '—') ?></div>
    </div>

    <div class="section-title">Employment Details</div>
    <div class="info-grid">
      <div><div class="lbl">Branch</div><?= e($emp['branch_name'] ?: '—') ?></div>
      <div><div class="lbl">Department / Section</div><?= e($emp['department'] ?: '—') ?></div>
      <div><div class="lbl">Position</div><?= e($emp['position']) ?></div>
      <div><div class="lbl">Employment Type</div><?= e($emp['employment_type']) ?></div>
      <div><div class="lbl">Date Hired</div><?= e($emp['hire_date'] ? date('F j, Y', strtotime($emp['hire_date'])) : '—') ?></div>
      <div><div class="lbl">Employment Status</div><?= e($emp['employment_status']) ?></div>
    </div>

    <div class="section-title">Government IDs</div>
    <div class="info-grid">
      <div><div class="lbl">SSS Number</div><?= e($emp['sss_number'] ?: '—') ?></div>
      <div><div class="lbl">PhilHealth Number</div><?= e($emp['philhealth_number'] ?: '—') ?></div>
      <div><div class="lbl">Pag-IBIG Number</div><?= e($emp['pagibig_number'] ?: '—') ?></div>
      <div><div class="lbl">TIN Number</div><?= e($emp['tin_number'] ?: '—') ?></div>
    </div>

    <p class="muted" style="margin-top:30px;font-size:11px;">Generated <?= date('F j, Y h:i A') ?> · Cedar Wellness Hub HRIS</p>
  </div>
</body></html>
