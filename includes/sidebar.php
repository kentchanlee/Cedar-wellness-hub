<?php
/**
 * Expects $active to be set by the including page, e.g. $active = 'employees';
 * Expects $base to be the relative path prefix to project root, e.g. '..'
 */
$role = $_SESSION['role'] ?? 'employee';
$active = $active ?? '';
$base = $base ?? '.';

function nav_icon(string $key): string {
    $icons = [
        'dashboard' => '<path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>',
        'employees' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'attendance_admin' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'attendance' => '<path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'schedule' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'my_schedule' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'leave_requests' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'my_leave' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'bookings' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z"/>',
        'branches' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'reports' => '<path d="M3 3v18h18M7 16l4-5 3 3 5-7"/>',
        'accounts' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'backup' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>',
        'my_profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'my_history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>'
    ];
    $paths = $icons[$key] ?? $icons['dashboard'];
    return '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">' . $paths . '</svg>';
}

function nav_item(string $key, string $active, string $href, string $icon, string $label): void {
    $cls = $key === $active ? 'nav-link active' : 'nav-link';
    echo '<a class="' . $cls . '" href="' . e($href) . '">' . nav_icon($key) . '<span class="nav-text">' . e($label) . '</span></a>';
}
?>
<div class="sidebar-scrim" id="sidebarScrim" aria-hidden="true"></div>
<aside class="sidebar" id="appSidebar" aria-label="Primary navigation">
  <div class="sidebar-brand">
    <img class="brand-mark" src="<?= e($base) ?>/assets/img/logo.png" alt="Cedar Wellness Hub logo">
    <div>
      <strong>Cedar Wellness</strong>
      <span>Management Platform</span>
    </div>
    <button class="sidebar-close" type="button" aria-label="Close navigation" data-sidebar-close>&times;</button>
  </div>

  <div class="nav-label">Overview</div>
  <nav class="nav-group">
    <?php nav_item('dashboard', $active, "$base/" . ($role === 'superadmin' ? 'superadmin' : ($role === 'hr' ? 'hr' : 'employee')) . "/dashboard.php", '&#9679;', 'Dashboard'); ?>
  </nav>

  <?php if ($role === 'superadmin'): ?>
  <div class="nav-group">
    <div class="nav-label">Administration</div>
    <?php
    nav_item('account_hr', $active, "$base/modules/user_accounts.php", 'accounts', 'HR Manage');
    nav_item('branches', $active, "$base/modules/branches.php", 'branches', 'Branches / Map');
    ?>
  </div>
  <?php elseif ($role === 'hr'): ?>
  <div class="nav-group">
    <div class="nav-label">Workforce</div>
    <?php
    nav_item('account_employee', $active, "$base/modules/employees.php", 'employees', 'Employees');
    nav_item('attendance_admin', $active, "$base/modules/attendance_admin.php", 'attendance_admin', 'Attendance Logs');
    nav_item('schedule', $active, "$base/modules/schedule.php", 'schedule', 'Scheduling');
    nav_item('leave_requests', $active, "$base/modules/leave_requests.php", 'leave_requests', 'Leave Requests');
    ?>
  </div>
  <div class="nav-group">
    <div class="nav-label">Operations</div>
    <?php
    nav_item('bookings', $active, "$base/modules/bookings.php", 'bookings', 'Bookings');
    ?>
  </div>
  <div class="nav-group">
    <div class="nav-label">Insights</div>
    <?php nav_item('reports', $active, "$base/modules/reports.php", 'reports', 'Reports & Analytics'); ?>
  </div>
  <?php else: ?>
  <div class="nav-group">
    <div class="nav-label">My Work</div>
    <?php
    nav_item('my_profile', $active, "$base/employee/my_profile.php", '&#128100;', 'My Profile / ID');
    nav_item('attendance', $active, "$base/employee/attendance.php", '&#9989;', 'Scan Attendance');
    nav_item('my_schedule', $active, "$base/employee/my_schedule.php", '&#128197;', 'My Schedule');
    nav_item('my_leave', $active, "$base/employee/my_leave.php", '&#128203;', 'Leave Requests');
    nav_item('my_history', $active, "$base/employee/my_history.php", '&#128340;', 'Attendance History');
    ?>
  </div>
  <?php endif; ?>

  <div class="sidebar-foot">
    <div class="user-chip">
      <div class="av"><?= e(strtoupper(substr(current_user_label(), 0, 1))) ?></div>
      <div>
        <div><?= e(current_user_label()) ?></div>
        <div class="role-tag"><?= e($role) ?></div>
      </div>
    </div>
    <a class="btn btn-outline btn-sm btn-block sidebar-password" href="<?= e($base) ?>/change_password.php">Change Password</a>
    <a class="btn btn-danger btn-sm btn-block"
       href="<?= e($base) ?>/logout.php"
       onclick="return confirm('Are you sure you want to logout?');">Sign Out</a>
  </div>
</aside>
<script>
function toggleAccountMenu(button) {
  const menu = document.getElementById('accountSubmenu');
  const isOpen = !menu.hidden;
  menu.hidden = isOpen;
  button.setAttribute('aria-expanded', String(!isOpen));
}
</script>
