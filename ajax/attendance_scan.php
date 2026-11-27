<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr','employee']);
require_once __DIR__ . '/../includes/management.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Use POST to record attendance.']); exit; }
if (microtime(true) - ($_SESSION['attendance_last_request'] ?? 0) < 2) { http_response_code(429); echo json_encode(['ok'=>false,'message'=>'Please wait before sending another attendance request.']); exit; }
$_SESSION['attendance_last_request']=microtime(true);
if (!is_string($_POST['qr_value'] ?? '') || !is_string($_POST['employee_identifier'] ?? '') || strlen($_POST['qr_value'] ?? '')>100 || strlen($_POST['employee_identifier'] ?? '')>50) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Invalid QR or employee identifier.']); exit; }
try { management_csrf(); } catch (InvalidArgumentException $ex) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>$ex->getMessage()]);exit; }
$qr = trim($_POST['qr_value'] ?? '');
$identifier = trim($_POST['employee_identifier'] ?? '');

if ($qr === '' && $identifier === '') {
    echo json_encode(['ok' => false, 'message' => 'Scan a QR code or enter an Employee ID / Code.']);
    exit;
}

if ($qr !== '') {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE qr_code_value = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$qr]);
    $emp = $stmt->fetch();
} else {
    // Accept the employee's displayed code first, then the internal numeric ID as a fallback.
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_code = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$identifier]);
    $emp = $stmt->fetch();

    if (!$emp && ctype_digit($identifier)) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([(int)$identifier]);
        $emp = $stmt->fetch();
    }
}

if (!$emp) {
    echo json_encode(['ok' => false, 'message' => $qr !== ''
        ? 'QR code not recognized. Enter the Employee ID / Code to continue.'
        : 'Employee ID / Code not found. Check the entry and try again.']);
    exit;
}

// HR can record attendance only for employees in the branch assigned to their account.
if ($_SESSION['role'] === 'hr') {
    $hrBranchId = require_hr_branch($pdo);
    if ((int)$emp['branch_id'] !== (int)$hrBranchId) {
        echo json_encode(['ok' => false, 'message' => 'This employee is not assigned to your branch.']);
        exit;
    }
}

// Employees may only punch their own attendance; HR/Superadmin can punch for anyone (kiosk mode)
if ($_SESSION['role'] === 'employee' && (int)$_SESSION['employee_id'] !== (int)$emp['id']) {
    echo json_encode(['ok' => false, 'message' => 'This QR code does not belong to your account.']);
    exit;
}

try {
$pdo->beginTransaction();
// Serialize punches for this employee across browsers and HR sessions.
$lock=$pdo->prepare("SELECT e.* FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=? AND e.is_deleted=0 AND e.employment_status='Active' AND u.status='active' FOR UPDATE"); $lock->execute([$emp['id']]);
$locked=$lock->fetch();
if (!$locked || ($_SESSION['role']==='hr' && (int)$locked['branch_id']!==$hrBranchId)) throw new InvalidArgumentException('Employee is inactive, archived or outside your branch.');
$emp=$locked;
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? FOR UPDATE");
$stmt->execute([$emp['id'], $today]);
$row = $stmt->fetch();
if ($row) {
    $latest=$row['time_out'] ?: $row['time_in'];
    if ($latest && time()-strtotime($latest)<60) throw new InvalidArgumentException('Attendance was just recorded. Wait at least 60 seconds before another punch.');
}


if (!$row) {
    $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date) VALUES (?, ?)")->execute([$emp['id'], $today]);
    $row = ['time_in' => null, 'time_out' => null];
}

// Only two punches are used: Time In followed by Time Out.
if (empty($row['time_in'])) { $field='time_in'; $label='Time In'; }
elseif (empty($row['time_out'])) { $field='time_out'; $label='Time Out'; }
else { throw new InvalidArgumentException('Time In and Time Out are already complete for today.'); }

$pdo->prepare("UPDATE attendance SET $field = ? WHERE employee_id = ? AND attendance_date = ?")
    ->execute([$now, $emp['id'], $today]);

// Determine lateness from the employee's assigned shift, falling back to 9:00 AM if no schedule exists.
if ($field === 'time_in') {
    $schedStmt = $pdo->prepare("SELECT shift_start, is_day_off FROM schedules WHERE employee_id = ? AND schedule_date = ? LIMIT 1");
    $schedStmt->execute([$emp['id'], $today]);
    $assignedSchedule = $schedStmt->fetch();
    if ($assignedSchedule && !empty($assignedSchedule['is_day_off'])) {
        throw new InvalidArgumentException('You are marked Day Off today. Please contact HR if this is incorrect.');
    }
    $shiftStart = !empty($assignedSchedule['shift_start']) ? $assignedSchedule['shift_start'] : '09:00:00';
    $cutoff = strtotime($today . ' ' . $shiftStart);
    $remarks = strtotime($now) > $cutoff ? 'late' : 'present';
    $pdo->prepare("UPDATE attendance SET remarks = ? WHERE employee_id = ? AND attendance_date = ?")
        ->execute([$remarks, $emp['id'], $today]);
}

if ($field === 'time_out') {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? FOR UPDATE");
    $stmt->execute([$emp['id'], $today]);
    $fresh = $stmt->fetch();
    $hours = compute_total_hours($fresh['time_in'], null, null, $fresh['time_out']);
    // Deduct only the portion of the scheduled break that overlaps the employee's actual attendance interval.
    $scheduleStmt = $pdo->prepare("SELECT break_start, break_end FROM schedules WHERE employee_id = ? AND schedule_date = ? LIMIT 1");
    $scheduleStmt->execute([$emp['id'], $today]);
    $break = $scheduleStmt->fetch();
    if ($hours !== null && $break && !empty($break['break_start']) && !empty($break['break_end'])) {
        $actualStart = strtotime($fresh['time_in']);
        $actualEnd = strtotime($fresh['time_out']);
        $breakStart = strtotime($today . ' ' . $break['break_start']);
        $breakEnd = strtotime($today . ' ' . $break['break_end']);
        $overlapSeconds = max(0, min($actualEnd, $breakEnd) - max($actualStart, $breakStart));
        $hours = round(max(0, $hours - ($overlapSeconds / 3600)), 2);
    }
    // Compare actual worked hours with the assigned schedule. A completed scan is
    // marked half-day when the employee worked less than half of the scheduled
    // net shift (scheduled duration minus the planned break).
    $schedStmt = $pdo->prepare("SELECT shift_start, shift_end, break_start, break_end, is_day_off FROM schedules WHERE employee_id = ? AND schedule_date = ? LIMIT 1");
    $schedStmt->execute([$emp['id'], $today]);
    $schedule = $schedStmt->fetch();

    $remarksStmt = $pdo->prepare("SELECT remarks FROM attendance WHERE employee_id = ? AND attendance_date = ? FOR UPDATE");
    $remarksStmt->execute([$emp['id'], $today]);
    $currentRemarks = $remarksStmt->fetchColumn() ?: 'present';
    $finalRemarks = $currentRemarks;

    if ($schedule && empty($schedule['is_day_off']) && !empty($schedule['shift_start']) && !empty($schedule['shift_end']) && $hours !== null) {
        $scheduledSeconds = max(0, strtotime($today . ' ' . $schedule['shift_end']) - strtotime($today . ' ' . $schedule['shift_start']));
        if (!empty($schedule['break_start']) && !empty($schedule['break_end'])) {
            $scheduledBreakSeconds = max(0, strtotime($today . ' ' . $schedule['break_end']) - strtotime($today . ' ' . $schedule['break_start']));
            $scheduledSeconds = max(0, $scheduledSeconds - $scheduledBreakSeconds);
        }
        $scheduledHours = $scheduledSeconds / 3600;
        if ($scheduledHours > 0 && $hours < ($scheduledHours / 2)) {
            $finalRemarks = 'half-day';
        }
    }

    $pdo->prepare("UPDATE attendance SET total_hours = ?, remarks = ? WHERE employee_id = ? AND attendance_date = ?")
        ->execute([$hours, $finalRemarks, $emp['id'], $today]);
    if ($finalRemarks === 'half-day') {
        $attendanceNote = ' Attendance status: Half Day because recorded hours are below half of the scheduled working hours.';
    } else {
        $attendanceNote = '';
    }
} else {
    $attendanceNote = '';
}

$pdo->commit();
echo json_encode([
    'ok' => true,
    'employee_name' => $emp['first_name'] . ' ' . $emp['last_name'],
    'punch_type' => $label,
    'time' => date('h:i A'),
    'message' => $label . ' recorded for ' . $emp['first_name'] . ' at ' . date('h:i A') . ($attendanceNote ?? ''),
]);

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!($ex instanceof InvalidArgumentException)) error_log('Attendance scan: '.$ex->getMessage());
    echo json_encode(['ok'=>false,'message'=>$ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Attendance could not be saved. Please try again.']);
}
