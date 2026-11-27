<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['employee']);
$active = 'attendance'; $base = '..'; $page_title = 'Scan Attendance'; $breadcrumb = 'Time in / time out via QR';

$empId = $_SESSION['employee_id'];
$today = date('Y-m-d');
$att = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
$att->execute([$empId, $today]);
$att = $att->fetch() ?: [
    'time_in' => null,
    'time_out' => null,
    'break_out' => null,
    'break_in' => null
];

$emp = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$emp->execute([$empId]);
$emp = $emp->fetch() ?: [
    'employee_code' => 'N/A',
    'qr_code_value' => ''
];
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

      <div class="grid" style="grid-template-columns: minmax(280px, 520px); justify-content:center; gap:22px;">
        <div class="card">
          <div class="card-head"><h3>Your QR Badge</h3></div>
          <p class="muted" style="font-size:13px;">Present this QR badge to the authorized attendance scanner to record your Time In or Time Out. This page does not use your device camera.</p>
          <div style="display:flex;justify-content:center;padding:16px 0;">
            <div id="myQrCanvas"></div>
          </div>
          <p style="text-align:center;font-size:12.5px;" class="muted"><?= e($emp['employee_code']) ?></p>
          <div class="punch-grid" id="punchGrid">
            <div class="punch-box <?= $att['time_in'] ? 'done' : '' ?>" data-f="time_in">
              <div class="t"><?= $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '--:--' ?></div>
              <div class="l">Time In</div>
            </div>
            <div class="punch-box <?= !empty($att['time_out']) ? 'done' : '' ?>" data-f="time_out">
              <div class="t"><?= !empty($att['time_out']) ? date('h:i A', strtotime($att['time_out'])) : '--:--' ?></div>
              <div class="l">Time Out</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('myQrCanvas'), {
  text: "<?= e($emp['qr_code_value']) ?>", width: 170, height: 170, colorDark: '#432752', colorLight: '#FAF7F3'
});
</script>
</body></html>
