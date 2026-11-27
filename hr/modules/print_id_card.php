<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$company = require __DIR__ . '/../config/company.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT e.*, b.branch_name FROM employees e LEFT JOIN branches b ON b.id = e.branch_id WHERE e.id = ? AND e.is_deleted = 0");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) { die('Employee record not found.'); }
if ($_SESSION['role'] === 'employee' && (int)$_SESSION['employee_id'] !== $id) {
    die('You are only allowed to view and print your own ID card.');
}

function fullname2($e) { return trim($e['first_name'] . ' ' . $e['last_name'] . ' ' . ($e['suffix'] ?? '')); }
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Employee ID Card — <?= e(fullname2($emp)) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  body { background: #F1EAE5; }
  .print-bar { max-width: 700px; margin: 20px auto; text-align: center; }
  .card-wrap { display:flex; gap: 24px; justify-content:center; flex-wrap: wrap; margin-bottom: 40px; }
  .id-card {
    width: 340px; height: 240px; border-radius: 16px; overflow:hidden; position: relative;
    background: linear-gradient(145deg, #4D2C5D 0%, #2E1939 100%); color: #FAF7F3;
    box-shadow: 0 14px 34px rgba(46,25,57,.28); font-family:var(--font-body);
  }
  .id-card::before {
    content:''; position:absolute; top:-40px; right:-40px; width:140px; height:140px; border-radius:50%;
    border: 8px solid rgba(214,154,67,.38);
  }
  .id-top { display:flex; align-items:center; gap:8px; padding: 14px 16px 8px; position:relative; z-index:1; }
  .id-top img { width: 34px; height:34px; border-radius:8px; object-fit:cover; }
  .id-top .cname { font-family:var(--font-display); font-size: 13px; font-weight:800; line-height:1.1; }
  .id-top .ctag { font-size: 8.5px; opacity:.7; text-transform:uppercase; letter-spacing:.05em; }
  .id-body { display:flex; gap: 14px; padding: 4px 16px; position:relative; z-index:1; }
  .id-photo { width: 76px; height: 92px; border-radius: 10px; object-fit: cover; background: rgba(250,247,243,.15); flex-shrink:0; border: 2px solid rgba(214,154,67,.7);}
  .id-name { font-family:var(--font-display); font-size: 16px; font-weight: 800; margin: 0 0 2px; }
  .id-role { font-size: 11px; color:#E2AF60; font-weight:700; margin-bottom: 6px; }
  .id-meta { font-size: 10px; opacity: .85; line-height: 1.5; }
  .id-bottom { display:flex; align-items:flex-end; justify-content:space-between; padding: 8px 16px 14px; position:relative; z-index:1; }
  .id-num { font-size: 11.5px; font-weight:700; letter-spacing:.03em; }
  .id-qr { background:#fff; padding:4px; border-radius:6px; }

  .id-card.back { background: #FAF7F3; color: #29242D; border:1px solid #E8DDD5; }
  .id-card.back .back-content { padding: 16px; font-size: 9.5px; line-height:1.6; position:relative; z-index:1; }
  .id-card.back .back-title { font-family:var(--font-display); color: #432752; font-size:12px; font-weight:800; margin-bottom:6px;}
  .id-card.back .sig-line { margin-top: 26px; border-top: 1px solid #333; padding-top: 4px; font-size: 9px; text-align:center; }

  @media print {
    @page { size: auto; margin: 10mm; }
    html, body {
      background: #fff !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    .print-bar { display:none; }
    .card-wrap { margin: 0; }
    .id-card {
      background: linear-gradient(145deg, #4D2C5D 0%, #2E1939 100%) !important;
      color: #FAF7F3 !important;
      box-shadow: none;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    .id-card::before { border-color: rgba(214,154,67,.38) !important; }
    .id-role { color: #E2AF60 !important; }
    .id-photo { background: rgba(250,247,243,.15) !important; border-color: rgba(214,154,67,.7) !important; }
    .id-qr { background: #fff !important; }
    .id-card.back {
      background: #FAF7F3 !important;
      color: #29242D !important;
      border-color: #E8DDD5 !important;
    }
    .id-card.back .back-title { color: #432752 !important; }
  }
</style>
</head>
<body>
  <div class="print-bar"><button class="btn btn-brass" onclick="window.print()"><?= ui_icon('print') ?> Print ID Card</button></div>
  <div class="card-wrap">
    <!-- FRONT -->
    <div class="id-card">
      <div class="id-top">
        <img src="<?= e($company['logo_web_path']) ?>" alt="logo">
        <div>
          <div class="cname"><?= e($company['name']) ?></div>
          <div class="ctag"><?= e($company['tagline']) ?></div>
        </div>
      </div>
      <div class="id-body">
        <?php if ($emp['photo']): ?>
          <img class="id-photo" src="../<?= e($emp['photo']) ?>">
        <?php else: ?>
          <div class="id-photo" style="display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:22px;">
            <?= e(strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1))) ?>
          </div>
        <?php endif; ?>
        <div>
          <p class="id-name"><?= e(fullname2($emp)) ?></p>
          <div class="id-role"><?= e($emp['position']) ?></div>
          <div class="id-meta">
            <?= e($emp['department'] ?: 'General') ?><br>
            <?= e($emp['branch_name'] ?: '—') ?>
          </div>
        </div>
      </div>
      <div class="id-bottom">
        <div class="id-num">ID: <?= e($emp['employee_code']) ?></div>
        <div class="id-qr"><div id="qrFront"></div></div>
      </div>
    </div>

    <!-- BACK -->
    <div class="id-card back">
      <div class="back-content">
        <div class="back-title">In case of emergency, please contact:</div>
        <?= e($emp['emergency_contact_name'] ?: '—') ?> &middot; <?= e($emp['emergency_contact_number'] ?: '—') ?>
        <br><br>
        <strong>Employee ID:</strong> <?= e($emp['employee_code']) ?><br>
        <strong>Date Hired:</strong> <?= e($emp['hire_date'] ? date('M j, Y', strtotime($emp['hire_date'])) : '—') ?><br>
        <strong>Blood Type / Notes:</strong> —
        <br><br>
        This card is the property of <?= e($company['name']) ?>. If found, please return to any branch or contact:
        <?= e($company['contact']) ?>.
        <div class="sig-line">Authorized Signature</div>
      </div>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrFront'), {
  text: "<?= e($emp['qr_code_value']) ?>", width: 56, height: 56, colorDark: '#432752', colorLight: '#ffffff'
});
</script>
</body></html>
