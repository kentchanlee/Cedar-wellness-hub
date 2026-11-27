<?php
/**
 * Cedar Wellness Hub - Shared helper functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Keep attendance timestamps aligned with the user's local Philippines time.
date_default_timezone_set('Asia/Manila');


/** Base URL */
// Override CWH_BASE_URL when deployed under a different URL path. Use an empty
// value when the app is installed at the web root.
$baseUrl = getenv('CWH_BASE_URL');
if ($baseUrl === false) { $baseUrl = '/cedar-wellness-hub'; }
define('BASE_URL', rtrim($baseUrl, '/'));


/** Redirect helper */
function redirect(string $path): void {
    header("Location: $path");
    exit;
}


/** Require an active session; otherwise send to login */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        redirect(BASE_URL . '/index.php');
    }
}


/** Require one of the given roles; otherwise send to their own dashboard */
function require_role(array $roles): void {
    require_login();

    if (!in_array($_SESSION['role'], $roles, true)) {
        redirect(dashboard_url_for($_SESSION['role']));
    }
}


/** Where should a role land after login */
function dashboard_url_for(string $role): string {

    switch ($role) {

        case 'superadmin':
            return BASE_URL . '/superadmin/dashboard.php';

        case 'hr':
            return BASE_URL . '/hr/dashboard.php';

        case 'employee':
            return BASE_URL . '/employee/dashboard.php';

        default:
            return BASE_URL . '/index.php';
    }
}



/** Return the active branch assigned to the logged-in HR account, or null. */
function current_hr_branch_id(PDO $pdo): ?int {
    if (($_SESSION['role'] ?? '') !== 'hr') return null;
    $stmt = $pdo->prepare("SELECT uba.branch_id FROM user_branch_assignments uba JOIN branches b ON b.id = uba.branch_id WHERE uba.user_id = ? AND b.is_deleted = 0 LIMIT 1");
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** Enforce that HR pages cannot operate without an assigned branch. */
function require_hr_branch(PDO $pdo): int {
    $branchId = current_hr_branch_id($pdo);
    if (!$branchId) { http_response_code(403); exit('Your HR account has no active branch assigned. Please contact the superadmin.'); }
    return $branchId;
}

/** Escape output */
function e(?string $val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}


/** Generate a unique QR code value */
function generate_qr_value(string $employee_code): string {
    return 'CWH-QR-' . strtoupper(bin2hex(random_bytes(4)));
}


/** Generate the next sequential Employee ID */
function generate_employee_id(PDO $pdo): string {

    $year = date('Y');

    $stmt = $pdo->prepare(
        "SELECT employee_code 
         FROM employees 
         WHERE employee_code LIKE ?
         ORDER BY id DESC 
         LIMIT 1"
    );

    $stmt->execute(["CWH-$year-%"]);

    $last = $stmt->fetch();

    $next = 1;

    if ($last) {
        $parts = explode('-', $last['employee_code']);
        $next = (int) end($parts) + 1;
    }

    return sprintf(
        'CWH-%s-%05d',
        $year,
        $next
    );
}


/** Upload employee photo */
function handle_photo_upload(array $file, string $employeeCode): ?string {

    if (
        empty($file['name']) ||
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }


    if (
        !isset($file['tmp_name']) ||
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        return null;
    }


    $allowed = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp'
    ];


    $ext = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );


    if (!array_key_exists($ext, $allowed)) {
        return null;
    }


    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || ($imageInfo['mime'] ?? '') !== $allowed[$ext]) return null;

    if ($file['size'] > 4 * 1024 * 1024) {
        return null;
    }


    $dir = __DIR__ . '/../uploads/employees';


    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }


    $filename =
        strtolower(str_replace(['-', ' '], '_', $employeeCode))
        . '_' . time()
        . '.' . $ext;


    $dest = $dir . '/' . $filename;


    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }


    return 'uploads/employees/' . $filename;
}


/** Compute worked hours */
function compute_total_hours(
    ?string $time_in,
    ?string $break_out,
    ?string $break_in,
    ?string $time_out
): ?float {
    if (!$time_in || !$time_out) {
        return null;
    }
    return round(max(strtotime($time_out) - strtotime($time_in), 0) / 3600, 2);
}

/** Current logged-in user's display name */
function current_user_label(): string {

    return $_SESSION['display_name']
        ?? $_SESSION['username']
        ?? 'User';
}


/** Flash message system */
function flash(string $key, ?string $msg = null) {

    if ($msg !== null) {

        $_SESSION['flash'][$key] = $msg;
        return null;

    }


    $val = $_SESSION['flash'][$key] ?? null;


    unset($_SESSION['flash'][$key]);


    return $val;
}

/** Small, dependency-free SVG icons for shared dashboard components. */
function ui_icon(string $name): string {
    $icons = [
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 11a4 4 0 0 0 0-8M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'x' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
        'chart' => '<path d="M3 3v18h18M7 16l4-5 3 3 5-7"/>',
        'field' => '<path d="M4 19h16M6 19V9l6-5 6 5v10M9 19v-6h6v6"/>',
        'print' => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>'
    ];
    $paths = $icons[$name] ?? $icons['chart'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $paths . '</svg>';
}
