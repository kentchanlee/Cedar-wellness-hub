<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['superadmin']);
$active = 'dashboard'; $base = '..'; $page_title = 'Superadmin Dashboard'; $breadcrumb = 'HR account administration';

// HR account branch assignments are maintained by the account-management module.
$pdo->exec("CREATE TABLE IF NOT EXISTS user_branch_assignments (
    user_id INT NOT NULL PRIMARY KEY,
    branch_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_branch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_branch_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB");

$totalHr = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hr'")->fetchColumn();
$activeHr = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hr' AND status = 'active'")->fetchColumn();
$hrAccounts = $pdo->query("SELECT u.id, u.username, u.status, uba.branch_id, b.branch_name
    FROM users u LEFT JOIN user_branch_assignments uba ON uba.user_id = u.id
    LEFT JOIN branches b ON b.id = uba.branch_id
    WHERE u.role = 'hr' ORDER BY u.username")->fetchAll();

// Branch workforce overview: employee headcount and the sum of recorded attendance hours.
$branches = $pdo->query("SELECT b.id, b.branch_name, b.address,
        COUNT(DISTINCT e.id) AS employee_count
    FROM branches b
    LEFT JOIN employees e ON e.branch_id = b.id AND e.is_deleted = 0
    WHERE b.is_deleted = 0
    GROUP BY b.id, b.branch_name, b.address
    ORDER BY b.branch_name")->fetchAll();

$employeeRows = $pdo->query("SELECT e.id, e.branch_id, e.employee_code,
        CONCAT(e.first_name, ' ', COALESCE(CONCAT(e.middle_name, ' '), ''), e.last_name,
            CASE WHEN e.suffix IS NOT NULL AND e.suffix <> '' THEN CONCAT(' ', e.suffix) ELSE '' END) AS full_name,
        e.position, e.employment_status,
        COALESCE(a.total_hours, 0) AS total_hours
    FROM employees e
    LEFT JOIN (
        SELECT employee_id, SUM(COALESCE(total_hours, 0)) AS total_hours
        FROM attendance GROUP BY employee_id
    ) a ON a.employee_id = e.id
    WHERE e.is_deleted = 0
    ORDER BY e.branch_id IS NULL, e.branch_id, e.last_name, e.first_name")->fetchAll();

$employeesByBranch = [];
$unassignedEmployees = [];
$hoursByBranch = [];
foreach ($employeeRows as $employee) {
    if ($employee['branch_id'] === null) {
        $unassignedEmployees[] = $employee;
        continue;
    }
    $branchId = (int)$employee['branch_id'];
    $employeesByBranch[$branchId][] = $employee;
    $hoursByBranch[$branchId] = ($hoursByBranch[$branchId] ?? 0) + (float)$employee['total_hours'];
}
$totalEmployees = count($employeeRows);
$totalHours = array_sum($hoursByBranch) + array_sum(array_map(static fn($e) => (float)$e['total_hours'], $unassignedEmployees));
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= e($page_title) ?> — Cedar Wellness Hub</title>
<meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="../assets/css/style.css"></head>
<body><div class="app-shell">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main"><?php include __DIR__ . '/../includes/topbar.php'; ?><div class="content">
  <div class="page-heading"><div><h1>Superadmin Dashboard</h1><p class="muted">Manage HR accounts and view employee headcount and recorded work hours by branch.</p></div><a class="btn btn-primary" href="../modules/user_accounts.php">Manage HR Accounts</a></div>

  <div class="dashboard-kpis">
    <div class="card stat-card"><div class="ic-badge"><?= ui_icon('users') ?></div><div class="num"><?= $totalHr ?></div><div class="lbl">Total HR Accounts</div></div>
    <div class="card stat-card"><div class="ic-badge"><?= ui_icon('check') ?></div><div class="num"><?= $activeHr ?></div><div class="lbl">Active HR Accounts</div></div>
    <div class="card stat-card"><div class="ic-badge"><?= ui_icon('users') ?></div><div class="num"><?= $totalEmployees ?></div><div class="lbl">Total Employees</div></div>
    <div class="card stat-card"><div class="ic-badge"><?= ui_icon('clock') ?></div><div class="num"><?= number_format($totalHours, 2) ?></div><div class="lbl">Total Recorded Hours</div></div>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>Employees by Branch</h3><p class="muted">Employee headcount and total recorded attendance hours for each branch.</p></div></div>
    <?php if (!$branches): ?><div class="empty-state">No branches have been created yet.</div><?php else: ?>
      <?php foreach ($branches as $branch): $branchId = (int)$branch['id']; $branchEmployees = $employeesByBranch[$branchId] ?? []; ?>
      <section style="border:1px solid var(--cream-dim);border-radius:14px;padding:18px;margin:0 0 16px;">
        <div class="card-head" style="align-items:flex-start;flex-wrap:wrap;">
          <div><h3 style="margin-bottom:4px;"><?= e($branch['branch_name']) ?></h3><p class="muted" style="margin:0;"><?= e($branch['address']) ?></p></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <div class="card" style="padding:10px 14px;min-width:120px;"><div class="muted" style="font-size:11px;text-transform:uppercase;">Employees</div><strong style="font-size:22px;"><?= (int)$branch['employee_count'] ?></strong></div>
            <div class="card" style="padding:10px 14px;min-width:150px;"><div class="muted" style="font-size:11px;text-transform:uppercase;">Total Hours</div><strong style="font-size:22px;"><?= number_format($hoursByBranch[$branchId] ?? 0, 2) ?></strong><span class="muted"> hrs recorded</span></div>
          </div>
        </div>
        <?php if (!$branchEmployees): ?><div class="empty-state" style="padding:20px;">No employees assigned to this branch.</div><?php else: ?>
        <div class="table-wrap"><table class="tbl"><thead><tr><th>Employee ID</th><th>Employee Name</th><th>Position</th><th>Status</th><th>Total Hours</th></tr></thead><tbody>
          <?php foreach ($branchEmployees as $employee): ?><tr>
            <td><?= e($employee['employee_code']) ?></td><td><?= e($employee['full_name']) ?></td><td><?= e($employee['position']) ?></td>
            <td><?= e($employee['employment_status'] ?? 'Unknown') ?></td><td><strong><?= number_format((float)$employee['total_hours'], 2) ?></strong></td>
          </tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($unassignedEmployees): ?>
      <section style="border:1px solid var(--cream-dim);border-radius:14px;padding:18px;margin-top:18px;">
        <div class="card-head"><h3>Employees Without a Branch</h3><span class="muted"><?= count($unassignedEmployees) ?> employee(s)</span></div>
        <div class="table-wrap"><table class="tbl"><thead><tr><th>Employee ID</th><th>Employee Name</th><th>Position</th><th>Total Hours</th></tr></thead><tbody>
          <?php foreach ($unassignedEmployees as $employee): ?><tr><td><?= e($employee['employee_code']) ?></td><td><?= e($employee['full_name']) ?></td><td><?= e($employee['position']) ?></td><td><strong><?= number_format((float)$employee['total_hours'], 2) ?></strong></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </section>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:20px;"><div class="card-head"><h3>HR Accounts</h3><a class="btn btn-outline btn-sm" href="../modules/user_accounts.php">Manage</a></div>
  <?php if (!$hrAccounts): ?><div class="empty-state">No HR accounts have been created yet.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl"><thead><tr><th>Username</th><th>Assigned Branch</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($hrAccounts as $account): ?><tr><td><?= e($account['username']) ?></td><td><?= e($account['branch_name'] ?? 'Not assigned') ?></td><td><span class="badge <?= ($account['status'] ?? '') === 'active' ? 'badge-green' : 'badge-grey' ?>"><?= e(ucfirst($account['status'] ?? 'unknown')) ?></span></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></div>
</div></div></div></body></html>
