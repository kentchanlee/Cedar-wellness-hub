<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['hr']);
require_once __DIR__ . '/../includes/management.php';
$active = 'account_employee'; $base = '..'; $page_title = 'Employee Accounts'; $breadcrumb = 'Manage employee accounts and records (HRIS)';

$pdo->exec("CREATE TABLE IF NOT EXISTS user_branch_assignments (
    user_id INT NOT NULL PRIMARY KEY,
    branch_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_branch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_branch_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB");
$hrBranchStmt = $pdo->prepare("SELECT uba.branch_id FROM user_branch_assignments uba JOIN branches b ON b.id=uba.branch_id WHERE uba.user_id=? AND b.is_deleted=0");
$hrBranchStmt->execute([(int)$_SESSION['user_id']]);
$hrBranchId = (int)$hrBranchStmt->fetchColumn();
if (!$hrBranchId) { http_response_code(403); exit('Your HR account has no active branch assigned. Please contact the superadmin.'); }

/* ---------------- Handle POST actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        management_csrf();
        $action = $_POST['action'] ?? '';
        if (!in_array($action,['create','edit','soft_delete','restore','toggle_field'],true)) throw new InvalidArgumentException('Invalid action.');
        if (in_array($action,['create','edit'],true)) {
            foreach (['first_name'=>60,'last_name'=>60,'position'=>80,'department'=>80,'contact_number'=>30,'complete_address'=>255,'emergency_contact_name'=>100,'emergency_contact_number'=>30] as $key=>$max) $_POST[$key]=management_text($key,$max);
            foreach (['middle_name'=>60,'suffix'=>10,'nationality'=>60,'sss_number'=>30,'philhealth_number'=>30,'pagibig_number'=>30,'tin_number'=>30] as $key=>$max) $_POST[$key]=management_text($key,$max,false);
            foreach (['contact_number','emergency_contact_number'] as $key) if (!preg_match('/^\+?[0-9 ()-]{7,30}$/D',$_POST[$key]) || strlen(preg_replace('/\D/','',$_POST[$key]))<7 || strlen(preg_replace('/\D/','',$_POST[$key]))>15) throw new InvalidArgumentException('Contact numbers must contain 7–15 digits and no letters.');
            foreach (['sss_number','philhealth_number','pagibig_number','tin_number'] as $key) if ($_POST[$key]!=='' && !preg_match('/^[0-9 -]+$/D',$_POST[$key])) throw new InvalidArgumentException('Government IDs can contain digits, spaces and hyphens only.');
            $dob=management_date('date_of_birth'); $hired=management_date('hire_date');
            if ($dob>=date('Y-m-d') || $hired<$dob) throw new InvalidArgumentException('Birth date must be before today and the hire date.');
            foreach (['gender'=>['Male','Female','Other'],'civil_status'=>['Single','Married','Widowed','Separated','Divorced'],'employment_type'=>['Regular','Probationary','Contractual','Part-Time']] as $key=>$allowed) if (!in_array($_POST[$key] ?? '',$allowed,true)) throw new InvalidArgumentException('Select a valid '.str_replace('_',' ',$key).'.');
            if ($action==='edit' && !in_array($_POST['employment_status'] ?? '',['Active','Inactive','Resigned','Terminated'],true)) throw new InvalidArgumentException('Select a valid employment status.');
            $_POST['email']=management_text('email',100);
            if (!filter_var($_POST['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email.');
        }
    } catch (InvalidArgumentException $ex) {
        $_SESSION['employee_form_old']=$_POST; flash('error',$ex->getMessage()); redirect(BASE_URL.'/modules/employees.php');
    }

    if ($action === 'create') {

        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $position = trim($_POST['position'] ?? '');
        // Employee records created from this page always receive the employee role.
        // HR accounts are created and managed only from the HR Accounts page.
        $roleInput = 'employee';
        $selectedRole = 'employee';

        $requiredFields = [
            'First name' => $first,
            'Last name' => $last,
            'Date of birth' => trim($_POST['date_of_birth'] ?? ''),
            'Gender' => trim($_POST['gender'] ?? ''),
            'Civil status' => trim($_POST['civil_status'] ?? ''),
            'Email address' => $email,
            'Mobile number' => trim($_POST['contact_number'] ?? ''),
            'Complete address' => trim($_POST['complete_address'] ?? ''),
            'Emergency contact name' => trim($_POST['emergency_contact_name'] ?? ''),
            'Emergency contact number' => trim($_POST['emergency_contact_number'] ?? ''),
            'Department' => trim($_POST['department'] ?? ''),
            'Position' => $position,
            
            'Employment type' => trim($_POST['employment_type'] ?? ''),
            'Date hired' => trim($_POST['hire_date'] ?? ''),
        ];
        $missingFields = [];
        foreach ($requiredFields as $label => $value) {
            if ($value === '') $missingFields[] = $label;
        }

        if ($missingFields) {
            flash('error', 'Please complete all required fields: ' . implode(', ', $missingFields) . '.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
        } else {

            $chkEmail = $pdo->prepare(
                "SELECT id FROM users WHERE email = ?
                 UNION
                 SELECT id FROM employees WHERE email = ?"
            );

            $chkEmail->execute([$email, $email]);


            if ($chkEmail->fetch()) {

                flash('error', 'That email address is already registered.');

            } else {

                try {

                    $pdo->beginTransaction();

                    $pdo->query('SELECT id FROM users ORDER BY id FOR UPDATE')->fetchAll();
                    $employee_code = generate_employee_id($pdo);

                    $tempPass = 'Cedar@123';

                    $hash = password_hash(
                        $tempPass,
                        PASSWORD_BCRYPT
                    );


                    $pdo->prepare(
                        "INSERT INTO users 
                        (username,email,password_hash,role,must_change_password)
                        VALUES (?,?,?,?,?)"
                    )->execute([
                        $employee_code,
                        $email,
                        $hash,
                        $selectedRole,
                        1
                    ]);


                    $userId = $pdo->lastInsertId();


                    $qr = generate_qr_value($employee_code);


                    $photoPath = !empty($_FILES['photo']['name'])
                        ? handle_photo_upload($_FILES['photo'], $employee_code)
                        : null;



                    $pdo->prepare(
                    "INSERT INTO employees
                    (
                    user_id,
                    employee_code,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    photo,
                    date_of_birth,
                    gender,
                    civil_status,
                    nationality,
                    email,
                    contact_number,
                    complete_address,
                    emergency_contact_name,
                    emergency_contact_number,
                    branch_id,
                    department,
                    position,
                    employment_type,
                    employment_status,
                    hire_date,
                    sss_number,
                    philhealth_number,
                    pagibig_number,
                    tin_number,
                    qr_code_value
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    )
                    ->execute([

                        $userId,
                        $employee_code,
                        $first,
                        trim($_POST['middle_name']),
                        $last,
                        trim($_POST['suffix']),
                        $photoPath,
                        $_POST['date_of_birth'] ?: null,
                        $_POST['gender'] ?: null,
                        $_POST['civil_status'] ?: null,
                        trim($_POST['nationality']) ?: 'Filipino',
                        $email,
                        trim($_POST['contact_number']),
                        trim($_POST['complete_address']),
                        trim($_POST['emergency_contact_name']),
                        trim($_POST['emergency_contact_number']),
                        $hrBranchId,
                        trim($_POST['department']),
                        $position,
                        $_POST['employment_type'],
                        'Active',
                        $_POST['hire_date'] ?: null,
                        trim($_POST['sss_number']),
                        trim($_POST['philhealth_number']),
                        trim($_POST['pagibig_number']),
                        trim($_POST['tin_number']),
                        $qr

                    ]);


                    $newEmpId = $pdo->lastInsertId();


                    $pdo->commit();


                    flash(
                        'success',
                        "Employee added — ID Number: $employee_code; Role: " . strtoupper($roleInput) . ". 
                        Login: $email / $employee_code 
                        Password: $tempPass"
                    );


                    // FIXED
                    redirect(
                        BASE_URL . 
                        '/modules/employees.php?highlight=' . 
                        $newEmpId
                    );


                } catch(Exception $e) {

                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Employee creation: '.$e->getMessage());
                    $_SESSION['employee_form_old']=$_POST;
                    flash('error','Employee could not be saved. Check for duplicate email or ID and try again.');

                }

            }

        }


        redirect(BASE_URL . '/modules/employees.php');

    }



    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $existingStmt = $pdo->prepare("SELECT employee_code, photo, user_id FROM employees WHERE id = ? AND branch_id = ? AND is_deleted = 0");
        $existingStmt->execute([$id, $hrBranchId]);
        $existing = $existingStmt->fetch();

        if (!$existing) {
            flash('error', 'Employee record was not found.');
        } elseif (trim($_POST['first_name'] ?? '') === '' || trim($_POST['last_name'] ?? '') === '' || trim($_POST['position'] ?? '') === '') {
            flash('error', 'First name, last name, and position are required.');
        } else {
            try {
                $pdo->beginTransaction();
                $q=$pdo->prepare('SELECT id FROM users WHERE (email=? OR username=?) AND id<>?'); $q->execute([$_POST['email'],$_POST['email'],$existing['user_id']]);
                if ($q->fetch()) throw new InvalidArgumentException('Email is already registered.');
                $q=$pdo->prepare('SELECT id FROM employees WHERE email=? AND id<>?'); $q->execute([$_POST['email'],$id]);
                if ($q->fetch()) throw new InvalidArgumentException('Email is already registered.');
                $photoPath = $existing['photo'];
                if (!empty($_FILES['photo']['name'])) {
                    $photoPath = handle_photo_upload($_FILES['photo'], $existing['employee_code']);
                    if (!$photoPath) throw new InvalidArgumentException('Upload a valid JPEG, PNG or WebP image under 4 MB.');
                }

                $pdo->prepare("UPDATE employees SET
                    first_name=?, middle_name=?, last_name=?, suffix=?, photo=?, date_of_birth=?, gender=?, civil_status=?, nationality=?,
                    contact_number=?, complete_address=?, emergency_contact_name=?, emergency_contact_number=?, branch_id=?, department=?, position=?,
                    employment_type=?, employment_status=?, hire_date=?, sss_number=?, philhealth_number=?, pagibig_number=?, tin_number=?
                    WHERE id=? AND branch_id=?")
                    ->execute([
                        trim($_POST['first_name']), trim($_POST['middle_name'] ?? ''), trim($_POST['last_name']), trim($_POST['suffix'] ?? ''),
                        $photoPath, ($_POST['date_of_birth'] ?? '') ?: null, ($_POST['gender'] ?? '') ?: null, ($_POST['civil_status'] ?? '') ?: null,
                        trim($_POST['nationality'] ?? ''), trim($_POST['contact_number'] ?? ''), trim($_POST['complete_address'] ?? ''),
                        trim($_POST['emergency_contact_name'] ?? ''), trim($_POST['emergency_contact_number'] ?? ''), $hrBranchId,
                        trim($_POST['department'] ?? ''), trim($_POST['position']), $_POST['employment_type'] ?? 'Regular',
                        $_POST['employment_status'] ?? 'Active', ($_POST['hire_date'] ?? '') ?: null, trim($_POST['sss_number'] ?? ''),
                        trim($_POST['philhealth_number'] ?? ''), trim($_POST['pagibig_number'] ?? ''), trim($_POST['tin_number'] ?? ''), $id, $hrBranchId
                    ]);
                $pdo->prepare('UPDATE employees SET email=? WHERE id=? AND branch_id=?')->execute([$_POST['email'],$id,$hrBranchId]);
                $pdo->prepare("UPDATE users SET email=? WHERE id=? AND role='employee'")->execute([$_POST['email'],$existing['user_id']]);
                $pdo->commit();
                flash('success', 'Employee profile updated.');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Employee edit: '.$e->getMessage());
                flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not update employee. Verify the fields and try again.');
            }
        }

        redirect(BASE_URL . '/modules/employees.php');

    }



    if ($action === 'soft_delete') {

        $id = (int)$_POST['id'];

        $pdo->prepare(
            "UPDATE employees 
             SET is_deleted = 1,
             deleted_at = NOW()
             WHERE id = ? AND branch_id = ?"
        )->execute([$id, $hrBranchId]);


        flash(
            'success',
            'Employee moved to archive.'
        );


        redirect(
            BASE_URL . '/modules/employees.php'
        );

    }



    if ($action === 'restore') {

        $id = (int)$_POST['id'];


        $pdo->prepare(
            "UPDATE employees
             SET is_deleted = 0,
             deleted_at = NULL
             WHERE id = ? AND branch_id = ?"
        )->execute([$id, $hrBranchId]);


        flash(
            'success',
            'Employee restored.'
        );


        redirect(
            BASE_URL . '/modules/employees.php?view=archived'
        );

    }



    if ($action === 'toggle_field') {

        $id = (int)$_POST['id'];


        $pdo->prepare(
            "UPDATE employees 
             SET on_field = NOT on_field 
             WHERE id = ? AND branch_id = ?"
        )->execute([$id, $hrBranchId]);


        redirect(
            BASE_URL . '/modules/employees.php'
        );

    }
}

$old=$_SESSION['employee_form_old'] ?? null;unset($_SESSION['employee_form_old']);
$view = $_GET['view'] ?? 'active';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$where = " WHERE e.is_deleted = " . ($view === 'archived' ? 1 : 0) . " AND e.branch_id = ?";
$params = [$hrBranchId];
if ($search !== '') {
    $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR e.position LIKE ? OR e.department LIKE ?)";
    $like = "%$search%";
    $params = [$hrBranchId, $like, $like, $like, $like, $like];
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e" . $where);
$countStmt->execute($params);
$employeeCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($employeeCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$sql = "SELECT e.*, b.branch_name FROM employees e LEFT JOIN branches b ON b.id = e.branch_id" . $where . " ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();
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

      <div class="tabs">
        <a class="tab <?= $view==='active'?'active':'' ?>" href="?view=active">Active Employees</a>
        <a class="tab <?= $view==='archived'?'active':'' ?>" href="?view=archived">Archived</a>
      </div>

      <div class="toolbar employee-toolbar">
        <form class="employee-filters" method="GET" id="employeeSearchForm">
          <input type="hidden" name="view" value="<?= e($view) ?>">
          <div class="search-box"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg></span>
            <input type="search" name="q" id="employeeLiveSearch" placeholder="Search employee, ID, role..." value="<?= e($search) ?>" autocomplete="off">
          </div>
        </form>
        <?php if ($view === 'active'): ?>
          <button class="btn btn-brass" onclick="document.getElementById('addModal').classList.add('open')">+ Add Employee</button>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            <th>Employee</th><th>Department</th><th>Position</th><th>Type</th>
            <th>Status</th><th>On Field</th><th></th>
          </tr></thead>
          <tbody id="employeeTableBody">
            <?php if (!$employees): ?>
              <tr><td colspan="7"><div class="empty-state"><div class="ic"><?= ui_icon('users') ?></div>No employees found.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($employees as $emp): ?>
            <tr>
              <td class="avatar-cell employee-cell">
                <?php if ($emp['photo']): ?>
                  <img src="../<?= e($emp['photo']) ?>" class="avatar avatar-photo" alt="">
                <?php else: ?>
                  <div class="avatar"><?= e(strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1))) ?></div>
                <?php endif; ?>
                <div><strong><?= e($emp['first_name'].' '.$emp['last_name']) ?></strong><small><?= e($emp['employee_code']) ?></small></div>
              </td>
              <td><?= e($emp['department'] ?? '—') ?></td>
              <td><?= e($emp['position']) ?></td>
              <td><span class="badge badge-grey"><?= e($emp['employment_type']) ?></span></td>
              <td><span class="badge <?= $emp['employment_status']==='Active'?'badge-green':'badge-red' ?>"><?= e($emp['employment_status']) ?></span></td>
              <td>
                <?php if ($view === 'active'): ?>
                <form method="POST" class="inline-form">
                  <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="toggle_field">
                  <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                  <button class="badge field-toggle <?= $emp['on_field'] ? 'badge-amber' : 'badge-grey' ?>">
                    <?= $emp['on_field'] ? 'On Field' : 'In-Branch' ?>
                  </button>
                </form>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-right">
                <?php if ($view === 'active'): ?>
                  <details class="action-menu"><summary class="btn btn-outline btn-icon" aria-label="Employee actions">&#8942;</summary>
                    <div class="action-menu-popover">
                      <a href="print_profile.php?id=<?= $emp['id'] ?>" target="_blank">View profile</a>
                      <button type="button" onclick='openEdit(<?= e(json_encode($emp)) ?>)'>Edit employee</button>
                      <a href="print_profile.php?id=<?= $emp['id'] ?>" target="_blank">Print profile</a>
                      <a href="print_id_card.php?id=<?= $emp['id'] ?>" target="_blank">Print ID</a>
                      <form method="POST" onsubmit="return confirm('Archive this employee? You can restore them later.');"><input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="soft_delete"><input type="hidden" name="id" value="<?= $emp['id'] ?>"><button class="danger-action">Archive employee</button></form>
                    </div>
                  </details>
                <?php else: ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="restore">
                    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                    <button class="btn btn-brass btn-sm">Restore</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
      <div id="employeePagination">
      <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Employee pages">
        <?php $query=$_GET; for($p=1;$p<=$totalPages;$p++): $query['page']=$p; ?>
          <a class="<?= $p===$page?'active':'' ?>" href="?<?= e(http_build_query($query)) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Add Employee Modal -->
<div class="modal-backdrop" id="addModal">
  <div class="modal modal-lg">
    <div class="modal-head"><h3>Add New Employee</h3>
      <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">&times;</button></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="create">

      <div class="ring-divider">Personal Information</div>
      <div class="form-row">
        <div class="field"><label>First Name</label><input type="text" name="first_name" maxlength="60" required></div>
        <div class="field"><label>Middle Name</label><input type="text" name="middle_name" maxlength="60"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Last Name</label><input type="text" name="last_name" maxlength="60" required></div>
        <div class="field"><label>Suffix</label><input type="text" name="suffix" maxlength="10" placeholder="Jr., Sr., III"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Profile / ID Picture</label><div class="photo-upload"><div class="photo-preview" id="addPhotoPreview">Image preview</div><input type="file" name="photo" accept="image/png,image/jpeg,image/webp" data-photo-preview="addPhotoPreview"></div></div>
        <div class="field"><label>Date of Birth</label><input type="date" name="date_of_birth" max="<?= date('Y-m-d',strtotime('-1 day')) ?>" required></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Gender</label>
          <select name="gender" required><option value="">— Select —</option><option>Male</option><option>Female</option><option>Other</option></select>
        </div>
        <div class="field"><label>Civil Status</label>
          <select name="civil_status" required><option value="">— Select —</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option><option>Divorced</option></select>
        </div>
      </div>
      <div class="field"><label>Nationality</label><input type="text" name="nationality" maxlength="60" value="Filipino"></div>

      <div class="ring-divider">Contact Information</div>
      <div class="form-row">
        <div class="field"><label>Email Address (login)</label><input type="email" name="email" maxlength="100" required></div>
        <div class="field"><label>Mobile Number</label><input type="tel" name="contact_number" required></div>
      </div>
      <div class="field"><label>Complete Address</label><input type="text" name="complete_address" maxlength="255" required></div>
      <div class="ring-divider">Emergency Contact</div>
      <div class="form-row">
        <div class="field"><label>Emergency Contact Name</label><input type="text" name="emergency_contact_name" maxlength="100" required></div>
        <div class="field"><label>Emergency Contact Number</label><input type="tel" name="emergency_contact_number" required></div>
      </div>

      <div class="ring-divider">Employment Details</div>
      <div class="field"><label for="department">Department / Section <span aria-hidden="true">*</span></label><input id="department" type="text" name="department" maxlength="80" placeholder="e.g. Wellness Services" required></div>
      <div class="field"><label for="position">Position / Job Title <span aria-hidden="true">*</span></label><input id="position" type="text" name="position" maxlength="80" required></div>
      <div class="form-row">
        <div class="field"><label>Employment Type</label>
          <select name="employment_type" required>
            <option>Regular</option><option>Probationary</option><option>Contractual</option><option>Part-Time</option>
          </select>
        </div>
      </div>
      <div class="field"><label>Date Hired</label><input type="date" name="hire_date" value="<?= date('Y-m-d') ?>" required></div>

      <div class="ring-divider">Government IDs (optional)</div>
      <div class="form-row">
        <div class="field"><label>SSS Number</label><input type="text" name="sss_number"></div>
        <div class="field"><label>PhilHealth Number</label><input type="text" name="philhealth_number"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Pag-IBIG Number</label><input type="text" name="pagibig_number"></div>
        <div class="field"><label>TIN Number</label><input type="text" name="tin_number"></div>
      </div>

      <p class="muted" style="font-size:12.5px;">
        Fields marked with * and required fields must be completed. Middle name, suffix, photo, and government IDs are optional. An Employee ID Number will be auto-generated (e.g. <code>CWH-<?= date('Y') ?>-00001</code>) and a QR code will be
        created automatically. The employee can log in using either their Employee ID or email, with temporary password
        <code>Cedar@123</code> — they'll be required to change it on first login.
      </p>
      <button type="submit" class="btn btn-primary">Save Employee</button>
    </form>
  </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal-backdrop" id="editModal">
  <div class="modal modal-lg">
    <div class="modal-head"><h3>Edit Employee</h3>
      <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">&times;</button></div>
    <form method="POST" id="editForm" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e_id">

      <div class="ring-divider">Personal Information</div>
      <div class="form-row">
        <div class="field"><label>First Name</label><input type="text" name="first_name" maxlength="60" id="e_first" required></div>
        <div class="field"><label>Middle Name</label><input type="text" name="middle_name" maxlength="60" id="e_middle"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Last Name</label><input type="text" name="last_name" maxlength="60" id="e_last" required></div>
        <div class="field"><label>Suffix</label><input type="text" name="suffix" maxlength="10" id="e_suffix"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Update Profile Picture</label><div class="photo-upload"><div class="photo-preview" id="editPhotoPreview">Current image</div><input type="file" name="photo" accept="image/png,image/jpeg,image/webp" data-photo-preview="editPhotoPreview"></div></div>
        <div class="field"><label>Date of Birth</label><input type="date" name="date_of_birth" max="<?= date('Y-m-d',strtotime('-1 day')) ?>" id="e_dob"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Gender</label>
          <select name="gender" id="e_gender"><option value="">— Select —</option><option>Male</option><option>Female</option><option>Other</option></select>
        </div>
        <div class="field"><label>Civil Status</label>
          <select name="civil_status" id="e_civil"><option value="">— Select —</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option><option>Divorced</option></select>
        </div>
      </div>
      <div class="field"><label>Nationality</label><input type="text" name="nationality" maxlength="60" id="e_nationality"></div>

      <div class="ring-divider">Contact Information</div>
      <div class="form-row">
        <div class="field"><label>Mobile Number</label><input type="text" name="contact_number" id="e_contact"></div>
        <div class="field"><label>Complete Address</label><input type="text" name="complete_address" maxlength="255" id="e_address"></div>
      </div>
      <div class="ring-divider">Emergency Contact</div>
      <div class="form-row">
        <div class="field"><label>Emergency Contact Name</label><input type="text" name="emergency_contact_name" maxlength="100" id="e_ec_name"></div>
        <div class="field"><label>Emergency Contact Number</label><input type="text" name="emergency_contact_number" id="e_ec_number"></div>
      </div>

      <div class="ring-divider">Employment Details</div>
      <div class="form-row">
        <div class="field"><label>Email Address (login)</label><input type="email" name="email" maxlength="100" id="e_email" required></div>
        <div class="field"><label>Employment Type</label><select name="employment_type" id="e_emp_type" required><option>Regular</option><option>Probationary</option><option>Contractual</option><option>Part-Time</option></select></div>
        <div class="field"><label>Department / Section</label><input type="text" name="department" maxlength="80" id="e_department" required></div>
        <div class="field"><label>Position / Job Title</label><input type="text" name="position" maxlength="80" id="e_position" required></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Date Hired</label><input type="date" name="hire_date" id="e_hire"></div>
        <div class="field"><label>Employment Status</label>
          <select name="employment_status" id="e_status"><option>Active</option><option>Inactive</option><option>Resigned</option><option>Terminated</option></select>
        </div>
      </div>

      <div class="ring-divider">Government IDs (optional)</div>
      <div class="form-row">
        <div class="field"><label>SSS Number</label><input type="text" name="sss_number" id="e_sss"></div>
        <div class="field"><label>PhilHealth Number</label><input type="text" name="philhealth_number" id="e_phic"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Pag-IBIG Number</label><input type="text" name="pagibig_number" id="e_hdmf"></div>
        <div class="field"><label>TIN Number</label><input type="text" name="tin_number" id="e_tin"></div>
      </div>

      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<script>
const oldEmployee=<?= json_encode($old,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
if(oldEmployee && ['create','edit'].includes(oldEmployee.action)) {
 const modal=document.getElementById(oldEmployee.action==='edit' ? 'editModal' : 'addModal');
 const f=modal.querySelector('form');for(const name of Object.keys(oldEmployee))if(f.elements[name] && name!=='csrf')f.elements[name].value=oldEmployee[name];modal.classList.add('open');
}
function openEdit(emp) {
  document.getElementById('e_email').value = emp.email || '';
  document.getElementById('e_id').value = emp.id;
  document.getElementById('e_first').value = emp.first_name;
  document.getElementById('e_middle').value = emp.middle_name || '';
  document.getElementById('e_last').value = emp.last_name;
  document.getElementById('e_suffix').value = emp.suffix || '';
  document.getElementById('e_dob').value = emp.date_of_birth || '';
  document.getElementById('e_gender').value = emp.gender || '';
  document.getElementById('e_civil').value = emp.civil_status || '';
  document.getElementById('e_nationality').value = emp.nationality || '';
  document.getElementById('e_contact').value = emp.contact_number || '';
  document.getElementById('e_address').value = emp.complete_address || '';
  document.getElementById('e_ec_name').value = emp.emergency_contact_name || '';
  document.getElementById('e_ec_number').value = emp.emergency_contact_number || '';
  document.getElementById('e_department').value = emp.department || '';
  document.getElementById('e_position').value = emp.position;
  document.getElementById('e_emp_type').value = emp.employment_type;
  document.getElementById('e_hire').value = emp.hire_date || '';
  document.getElementById('e_status').value = emp.employment_status;
  document.getElementById('e_sss').value = emp.sss_number || '';
  document.getElementById('e_phic').value = emp.philhealth_number || '';
  document.getElementById('e_hdmf').value = emp.pagibig_number || '';
  document.getElementById('e_tin').value = emp.tin_number || '';
  const photoPreview = document.getElementById('editPhotoPreview');
  if (photoPreview) {
    if (emp.photo) {
      const image = document.createElement('img');
      image.src = '../' + emp.photo;
      image.alt = 'Current employee photo';
      photoPreview.replaceChildren(image);
    } else {
      photoPreview.textContent = 'No image';
    }
  }
  document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('form[method="POST"]').forEach(f=>f.addEventListener('submit',()=>{if(f.checkValidity()) f.querySelectorAll('button').forEach(b=>b.disabled=true);}));
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
document.querySelectorAll('[data-photo-preview]').forEach(input => {
  input.addEventListener('change', () => {
    const preview = document.getElementById(input.dataset.photoPreview);
    const file = input.files && input.files[0];
    if (!preview || !file) return;
    const image = document.createElement('img');
    image.alt = 'Selected employee photo';
    image.src = URL.createObjectURL(file);
    image.onload = () => URL.revokeObjectURL(image.src);
    preview.replaceChildren(image);
  });
});
document.addEventListener('click', event => {
  document.querySelectorAll('.action-menu[open]').forEach(menu => {
    if (!menu.contains(event.target)) menu.removeAttribute('open');
  });
});

const employeeSearchForm = document.getElementById('employeeSearchForm');
const employeeLiveSearch = document.getElementById('employeeLiveSearch');
let searchTimer;
let searchRequest;

async function refreshEmployeeResults() {
  if (!employeeSearchForm || !employeeLiveSearch) return;
  if (searchRequest) searchRequest.abort();
  searchRequest = new AbortController();

  const params = new URLSearchParams(new FormData(employeeSearchForm));
  const url = `${window.location.pathname}?${params.toString()}`;
  const tableBody = document.getElementById('employeeTableBody');
  tableBody.classList.add('is-searching');

  try {
    const response = await fetch(url, { signal: searchRequest.signal });
    if (!response.ok) throw new Error('Search failed');
    const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
    const nextBody = documentResult.getElementById('employeeTableBody');
    const nextPagination = documentResult.getElementById('employeePagination');
    if (nextBody) tableBody.innerHTML = nextBody.innerHTML;
    if (nextPagination) document.getElementById('employeePagination').innerHTML = nextPagination.innerHTML;
    window.history.replaceState({}, '', url);
  } catch (error) {
    if (error.name !== 'AbortError') console.error(error);
  } finally {
    tableBody.classList.remove('is-searching');
  }
}

// Keep the employee action menu visible even inside the horizontally scrolling table.
function positionEmployeeActionMenu(details) {
  if (!details || !details.open) return;
  const trigger = details.querySelector('summary');
  const menu = details.querySelector('.action-menu-popover');
  if (!trigger || !menu) return;
  menu.classList.add('is-viewport-positioned');
  menu.style.visibility = 'hidden';
  menu.style.left = '0px';
  menu.style.top = '0px';
  const triggerRect = trigger.getBoundingClientRect();
  const menuRect = menu.getBoundingClientRect();
  const margin = 8;
  const left = Math.max(margin, Math.min(triggerRect.right - menuRect.width, window.innerWidth - menuRect.width - margin));
  const spaceBelow = window.innerHeight - triggerRect.bottom - margin;
  const top = spaceBelow >= Math.min(menuRect.height, 180) || triggerRect.top < menuRect.height + margin
    ? Math.min(triggerRect.bottom + 7, window.innerHeight - menuRect.height - margin)
    : Math.max(margin, triggerRect.top - menuRect.height - 7);
  menu.style.left = `${left}px`;
  menu.style.top = `${Math.max(margin, top)}px`;
  menu.style.visibility = 'visible';
}
document.addEventListener('click', event => {
  const summary = event.target.closest('.action-menu > summary');
  if (!summary) return;
  window.requestAnimationFrame(() => positionEmployeeActionMenu(summary.parentElement));
});
document.addEventListener('toggle', event => {
  if (event.target.matches && event.target.matches('.action-menu')) {
    if (event.target.open) window.requestAnimationFrame(() => positionEmployeeActionMenu(event.target));
    else { const menu = event.target.querySelector('.action-menu-popover'); if (menu) { menu.classList.remove('is-viewport-positioned'); menu.style.left = ''; menu.style.top = ''; menu.style.visibility = ''; } }
  }
}, true);
window.addEventListener('resize', () => document.querySelectorAll('.action-menu[open]').forEach(positionEmployeeActionMenu));
window.addEventListener('scroll', () => document.querySelectorAll('.action-menu[open]').forEach(positionEmployeeActionMenu), true);

if (employeeSearchForm && employeeLiveSearch) {
  employeeSearchForm.addEventListener('submit', event => { event.preventDefault(); refreshEmployeeResults(); });
  employeeLiveSearch.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(refreshEmployeeResults, 250);
  });
}
</script>
</body></html>
