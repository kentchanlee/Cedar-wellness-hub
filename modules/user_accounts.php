<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['superadmin']);
$active = 'account_hr'; $base = '..'; $page_title = 'HR Accounts'; $breadcrumb = 'Manage HR accounts';

// Keep HR branch assignments in a separate table so existing installations can upgrade safely.
$pdo->exec("CREATE TABLE IF NOT EXISTS user_branch_assignments (
    user_id INT NOT NULL PRIMARY KEY,
    branch_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_branch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_branch_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB");


require_once __DIR__ . '/../includes/management.php';
management_schema($pdo);
$branches = $pdo->query("SELECT b.id,b.branch_name,b.hr_capacity,(SELECT COUNT(*) FROM user_branch_assignments a JOIN users u ON u.id=a.user_id WHERE a.branch_id=b.id AND u.role='hr' AND u.status='active') AS active_hr_count FROM branches b WHERE b.is_deleted=0 ORDER BY b.branch_name")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        management_csrf();
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['create_hr','edit_hr','toggle_status','reset_password'], true)) throw new InvalidArgumentException('Invalid action.');
        $pdo->beginTransaction();
        // Serialize staffing changes, including branch reassignment and activation.
        $pdo->query('SELECT id FROM branches ORDER BY id FOR UPDATE')->fetchAll();
        $id = $action === 'create_hr' ? 0 : management_id('id');
        $user = null;
        if ($id) {
            $q=$pdo->prepare("SELECT u.*,a.branch_id FROM users u LEFT JOIN user_branch_assignments a ON a.user_id=u.id WHERE u.id=? AND u.role='hr' FOR UPDATE");
            $q->execute([$id]); $user=$q->fetch();
            if (!$user) throw new InvalidArgumentException('HR account not found.');
        }
        if (in_array($action,['create_hr','edit_hr'],true)) {
            $first=management_text('first_name',60); $last=management_text('last_name',60);
            $username=management_text('username',50);
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/D',$username)) throw new InvalidArgumentException('Username must be 3–50 letters, numbers, dots, underscores or hyphens.');
            $email=management_text('email',120,false);
            if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
            $branchId=management_id('branch_id');
            $q=$pdo->prepare('SELECT id FROM users WHERE id<>? AND (username=? OR email=? OR username=? OR email=?)');
            $q->execute([$id,$username,$email ?: null,$email ?: null,$username]);
            if ($q->fetch()) throw new InvalidArgumentException('Username or email already belongs to another account.');
            if (!$user || $user['status']==='active') management_capacity($pdo,$branchId,$id);
            else {
                $q=$pdo->prepare('SELECT id FROM branches WHERE id=? AND is_deleted=0'); $q->execute([$branchId]);
                if (!$q->fetch()) throw new InvalidArgumentException('Select an active branch.');
            }
            if (!$id) {
                $pdo->prepare("INSERT INTO users (username,email,first_name,last_name,password_hash,role,must_change_password) VALUES (?,?,?,?,?,'hr',1)")->execute([$username,$email ?: null,$first,$last,password_hash('Cedar@123',PASSWORD_DEFAULT)]);
                $id=(int)$pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE users SET username=?,email=?,first_name=?,last_name=? WHERE id=? AND role='hr'")->execute([$username,$email ?: null,$first,$last,$id]);
            }
            $pdo->prepare('INSERT INTO user_branch_assignments (user_id,branch_id) VALUES (?,?) ON DUPLICATE KEY UPDATE branch_id=VALUES(branch_id)')->execute([$id,$branchId]);
        } elseif ($action==='toggle_status') {
            if ($user['status']!=='active') management_capacity($pdo,(int)$user['branch_id'],$id);
            $pdo->prepare("UPDATE users SET status=IF(status='active','disabled','active') WHERE id=? AND role='hr'")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE users SET password_hash=?,must_change_password=1 WHERE id=? AND role='hr'")->execute([password_hash('Cedar@123',PASSWORD_DEFAULT),$id]);
        }
        $pdo->commit();
        flash('success',in_array($action,['create_hr','reset_password'],true) ? 'HR account saved. Temporary password: Cedar@123. A password change is required at next login.' : 'HR account updated.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('HR management: '.$ex->getMessage());
        flash('error',$ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Could not save the HR account. Check the database migration and try again.');
        $_SESSION['hr_form_old']=$_POST;
    }
    redirect(BASE_URL . '/modules/user_accounts.php');
}
$users = $pdo->query("SELECT u.*,br.branch_name,uba.branch_id FROM users u LEFT JOIN user_branch_assignments uba ON uba.user_id=u.id LEFT JOIN branches br ON br.id=uba.branch_id WHERE u.role='hr' ORDER BY u.username")->fetchAll();
$old = $_SESSION['hr_form_old'] ?? null; unset($_SESSION['hr_form_old']);

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

      <div class="toolbar">
        <div class="spacer"></div>
        <button class="btn btn-brass" onclick="editHR()">+ Add HR Account</button>
      </div>

      <div class="card">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Branch</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= e($u['username']) ?></td>
              <td><?= $u['first_name'] ? e($u['first_name'].' '.$u['last_name']) : '—' ?></td>
              <td><span class="badge badge-sage"><?= e($u['role']) ?></span></td>
              <td><?= $u['role'] === 'hr' ? e($u['branch_name'] ?? 'Not assigned') : '—' ?></td>
              <td><span class="badge <?= $u['status']==='active'?'badge-green':'badge-red' ?>"><?= e($u['status']) ?></span></td>
              <td class="text-right">
                <button type="button" class="btn btn-outline btn-sm" onclick='editHR(<?= e(json_encode(array_intersect_key($u,array_flip(['id','username','email','first_name','last_name','branch_id'])))) ?>)'>Edit</button>
                <?php if ($u['role'] !== 'superadmin'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button class="btn btn-outline btn-sm" onclick="return confirm('Reset password to Cedar@123?');">Reset Password</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button class="btn btn-sm <?= $u['status']==='active'?'btn-danger':'btn-brass' ?>">
                    <?= $u['status']==='active' ? 'Disable' : 'Enable' ?>
                  </button>
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

<div class="modal-backdrop" id="hrModal">
  <div class="modal" role="dialog" aria-modal="true" tabindex="-1">
    <div class="modal-head"><h3 id="hrTitle" style="margin:0;">Add HR Account</h3>
      <button class="modal-close" onclick="document.getElementById('hrModal').classList.remove('open')">&times;</button></div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="id" value=""><input type="hidden" name="action" value="create_hr">
      <div class="form-row"><div class="field"><label>First Name<input name="first_name" maxlength="60" required></label></div><div class="field"><label>Last Name<input name="last_name" maxlength="60" required></label></div></div>
      <div class="field"><label>Email (optional)<input type="email" name="email" maxlength="120"></label></div>
      <div class="field"><label>Username</label><input type="text" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,49}" required></div>
      <div class="field"><label for="hr_branch_id">Assigned Branch</label>
        <select id="hr_branch_id" name="branch_id" required>
          <option value="">— Select branch —</option>
          <?php foreach ($branches as $branch): ?><option value="<?= (int)$branch['id'] ?>"><?= e($branch['branch_name']) ?> (<?= (int)$branch['active_hr_count'] ?>/<?= (int)$branch['hr_capacity'] ?> active HR)</option><?php endforeach; ?>
        </select>
      </div>
      <p class="muted" style="font-size:12.5px;">Temporary password will be <code>Cedar@123</code> — the account must change it on first login.</p>
      <button class="btn btn-primary" type="submit" id="hrSave">Create HR Account</button>
    </form>
  </div>
</div>
<script>
function editHR(data={}) {
 const form=document.querySelector('#hrModal form'); form.reset();
 for(const name of ['id','username','email','first_name','last_name','branch_id']) form.elements[name].value=data[name] || '';
 form.elements.action.value=data.id ? 'edit_hr' : 'create_hr';
 document.getElementById('hrTitle').textContent=data.id ? 'Edit HR Account' : 'Add HR Account';
 document.getElementById('hrSave').textContent=data.id ? 'Save Changes' : 'Create HR Account';
 document.getElementById('hrModal').classList.add('open');
}
const oldHR=<?= json_encode($old, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if(oldHR && ['create_hr','edit_hr'].includes(oldHR.action)) editHR(oldHR);
document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{if(f.checkValidity()) f.querySelectorAll('button').forEach(b=>b.disabled=true);}));

document.addEventListener('keydown',ev=>{if(ev.key==='Escape')document.querySelectorAll('.modal-backdrop.open').forEach(m=>m.classList.remove('open'));});
document.querySelectorAll('.modal-backdrop').forEach(m => { m.addEventListener('click', e => { if (e.target===m) m.classList.remove('open'); }); });
</script>
</body></html>
