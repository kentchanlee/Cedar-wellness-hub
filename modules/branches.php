<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['superadmin']);
$active = 'branches'; $base = '..'; $page_title = 'Branches'; $breadcrumb = 'All company locations';

require_once __DIR__ . '/../includes/management.php';
management_schema($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        management_csrf();
        $action=$_POST['action'] ?? '';
        if (!in_array($action,['create','edit','delete'],true)) throw new InvalidArgumentException('Invalid action.');
        $pdo->beginTransaction();
        $pdo->query('SELECT id FROM branches ORDER BY id FOR UPDATE')->fetchAll();
        $id=$action==='create' ? 0 : management_id('id');
        if ($id) {
            $q=$pdo->prepare('SELECT id FROM branches WHERE id=? AND is_deleted=0 FOR UPDATE'); $q->execute([$id]);
            if (!$q->fetch()) throw new InvalidArgumentException('Branch not found.');
        }
        if ($action==='delete') {
            foreach (['employees','user_branch_assignments','bookings'] as $table) {
                $q=$pdo->prepare("SELECT COUNT(*) FROM $table WHERE branch_id=?" . ($table==='employees' ? ' AND is_deleted=0' : ''));
                $q->execute([$id]);
                if ($q->fetchColumn()) throw new InvalidArgumentException('This branch has employees, HR assignments or bookings. Reassign them before removal.');
            }
            $pdo->prepare('UPDATE branches SET is_deleted=1 WHERE id=?')->execute([$id]);
        } else {
            $name=management_text('branch_name',100);
            $line=management_text('address_line',100); $barangay=management_text('barangay',80);
            $city=management_text('city',80); $province=management_text('province',80);
            $postal=management_text('postal_code',4);
            if (!preg_match('/^[0-9]{4}$/D',$postal)) throw new InvalidArgumentException('Philippine postal code must contain four digits.');
            $address=implode(', ',[$line,$barangay,$city,$province,$postal]);
            if (strlen($address)>255) throw new InvalidArgumentException('Combined address must not exceed 255 bytes.');
            $contact=management_text('contact_number',30,false);
            if ($contact!=='' && (!preg_match('/^\+?[0-9 ()-]+$/D',$contact) || strlen(preg_replace('/\D/','',$contact))<7 || strlen(preg_replace('/\D/','',$contact))>15)) throw new InvalidArgumentException('Contact number must contain 7–15 digits, with optional +, spaces, brackets or hyphens.');
            $lat=filter_var($_POST['latitude'] ?? null,FILTER_VALIDATE_FLOAT);
            $lng=filter_var($_POST['longitude'] ?? null,FILTER_VALIDATE_FLOAT);
            if ($lat===false || $lng===false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new InvalidArgumentException('Select a valid map location or enter valid latitude and longitude.');
            $capacity=management_id('hr_capacity');
            if ($capacity>100) throw new InvalidArgumentException('HR capacity must be between 1 and 100.');
            $q=$pdo->prepare('SELECT id FROM branches WHERE branch_name=? AND is_deleted=0 AND id<>?'); $q->execute([$name,$id]);
            if ($q->fetch()) throw new InvalidArgumentException('An active branch already uses this name.');
            if ($id) {
                $q=$pdo->prepare("SELECT COUNT(*) FROM user_branch_assignments a JOIN users u ON u.id=a.user_id WHERE a.branch_id=? AND u.role='hr' AND u.status='active'"); $q->execute([$id]);
                if ($q->fetchColumn()>$capacity) throw new InvalidArgumentException('Capacity cannot be lower than this branch’s current active HR count.');
            }
            $values=[$name,$address,$lat,$lng,$contact,$capacity,$line,$barangay,$city,$province,$postal];
            if ($id) {
                $values[]=$id;
                $pdo->prepare('UPDATE branches SET branch_name=?,address=?,latitude=?,longitude=?,contact_number=?,hr_capacity=?,address_line=?,barangay=?,city=?,province=?,postal_code=? WHERE id=?')->execute($values);
            } else {
                $pdo->prepare('INSERT INTO branches (branch_name,address,latitude,longitude,contact_number,hr_capacity,address_line,barangay,city,province,postal_code) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            }
        }
        $pdo->commit(); flash('success',$action==='delete' ? 'Branch removed.' : 'Branch saved.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Branch management: '.$ex->getMessage());
        flash('error',$ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Could not save the branch. Check the database migration and try again.');
        $_SESSION['branch_form_old']=$_POST;
    }
    redirect(BASE_URL . '/modules/branches.php');
}
$old=$_SESSION['branch_form_old'] ?? null; unset($_SESSION['branch_form_old']);

$branches = $pdo->query("SELECT * FROM branches WHERE is_deleted = 0 ORDER BY branch_name")->fetchAll();
// Load reservations by branch so the map can show which customers are booking each location.
$bookingStmt = $pdo->query("SELECT branch_id, customer_name, contact_number, service, booking_date, booking_time, status
    FROM bookings WHERE branch_id IS NOT NULL ORDER BY booking_date DESC, booking_time DESC");
$branchBookings = [];
foreach ($bookingStmt->fetchAll() as $booking) {
    $branchBookings[(string)$booking['branch_id']][] = $booking;
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= e($page_title) ?> — Cedar Wellness Hub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  /* Keep Leaflet's panes and controls below the Add Branch dialog. */
  #branch-map,
  #branch-map .leaflet-pane,
  #branch-map .leaflet-control { z-index: 0 !important; }

  #brModal {
    position: fixed !important;
    inset: 0 !important;
    z-index: 99999 !important;
    isolation: isolate;
  }
  #brModal .modal { max-height:90vh; overflow-y:auto; width:min(680px,95vw); }
  #brModal.open { display: flex !important; }
  #brModal .modal { position: relative; z-index: 1; }

</style></head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="content">
      <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-head"><h3>Branch Map</h3>
          <button class="btn btn-brass btn-sm" onclick="editBranch()">+ Add Branch</button>
        </div>
        <div id="branch-map"></div>
      </div>

      <div class="card">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Branch</th><th>Address</th><th>Contact</th><th>Active HR limit</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($branches as $b): ?>
            <tr>
              <td><?= e($b['branch_name']) ?></td>
              <td><?= e($b['address']) ?></td>
              <td><?= e($b['contact_number']) ?></td>
              <td><?= (int)$b['hr_capacity'] ?></td><td class="text-right">
                <button type="button" class="btn btn-outline btn-sm" onclick='editBranch(<?= e(json_encode($b)) ?>)'>Edit / Move Pin</button>
                <form method="POST" onsubmit="return confirm('Remove this branch?');" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button class="btn btn-danger btn-sm">Remove</button>
                </form>
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

<div class="modal-backdrop" id="brModal">
  <div class="modal" role="dialog" aria-modal="true" tabindex="-1">
    <div class="modal-head"><h3 id="branchTitle" style="margin:0;">Add Branch</h3>
      <button class="modal-close" onclick="document.getElementById('brModal').classList.remove('open')">&times;</button></div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= e(management_token()) ?>"><input type="hidden" name="id"><input type="hidden" name="action" value="create">
      <div class="field"><label>Branch Name</label><input type="text" name="branch_name" maxlength="100" required></div>
      <p id="legacyAddress" class="muted"></p>
      <div class="field"><label>Building / Street<input name="address_line" maxlength="100" required></label></div>
      <div class="form-row"><div class="field"><label>Barangay<input name="barangay" maxlength="80" required></label></div><div class="field"><label>City / Municipality<input name="city" maxlength="80" required></label></div></div>
      <div class="form-row"><div class="field"><label>Province<input name="province" maxlength="80" required></label></div><div class="field"><label>Postal Code<input name="postal_code" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required></label></div></div>
      <p class="muted" id="pickerHelp">Click the map to place the branch pin, or drag the pin to move it. Coordinates can also be entered below.</p>
      <div id="locationPicker" style="height:260px;isolation:isolate" aria-label="Branch location picker"></div>
      <div class="form-row">
        <div class="field"><label>Latitude</label><input type="number" step="any" min="-90" max="90" name="latitude" placeholder="14.1962" required></div>
        <div class="field"><label>Longitude</label><input type="number" step="any" min="-180" max="180" name="longitude" placeholder="120.8781" required></div>
      </div>
      <div class="field"><label>Contact Number</label><input type="text" name="contact_number" maxlength="30" inputmode="tel"></div>
      <div class="field"><label>Maximum Active HR Accounts<input type="number" name="hr_capacity" min="1" max="100" value="1" required></label></div>
      <p class="muted">New branches allow one active HR by default. Disabled HR accounts do not consume capacity.</p>
      <button class="btn btn-primary" type="submit" id="branchSave">Save Branch</button>
    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const branches = <?= json_encode($branches, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const branchBookings = <?= json_encode($branchBookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const map = typeof L !== 'undefined' ? L.map('branch-map').setView(branches.length ? [branches[0].latitude, branches[0].longitude] : [14.1962,120.8781], 10) : null;
if(map) {
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const bounds = [];
branches.forEach(b => {
  if(b.latitude===null || b.longitude===null || !Number.isFinite(Number(b.latitude)) || !Number.isFinite(Number(b.longitude))) return;
  const marker = L.marker([Number(b.latitude), Number(b.longitude)]).addTo(map);
  const customers = branchBookings[String(b.id)] || [];

  // Hovering over a branch marker previews its customer bookings.
  const previewNames = customers.slice(0, 4).map(item => item.customer_name).filter(Boolean);
  const extraCount = Math.max(0, customers.length - previewNames.length);
  const hoverText = customers.length
    ? `${customers.length} booking(s): ${previewNames.join(', ')}${extraCount ? ` +${extraCount} more` : ''}`
    : 'No customer bookings yet';
  const tooltipContent = document.createElement('span');
  tooltipContent.textContent = `${b.branch_name || 'Branch'} — ${hoverText}`;
  marker.bindTooltip(tooltipContent, { direction: 'top', sticky: true });

  // Click to view complete booking details for this branch. Build DOM nodes and use
  // textContent so customer-entered names/details cannot inject HTML into the map.
  const popup = document.createElement('div');
  const heading = document.createElement('strong');
  heading.textContent = b.branch_name || 'Branch';
  popup.appendChild(heading);
  const address = document.createElement('div');
  address.textContent = b.address || '';
  popup.appendChild(address);
  if (b.contact_number) {
    const contact = document.createElement('div');
    contact.textContent = `Contact: ${b.contact_number}`;
    popup.appendChild(contact);
  }
  const bookingHeading = document.createElement('p');
  bookingHeading.style.margin = '8px 0 4px';
  bookingHeading.textContent = `Customer bookings (${customers.length})`;
  popup.appendChild(bookingHeading);
  if (!customers.length) {
    const empty = document.createElement('div');
    empty.textContent = 'No customer bookings for this branch yet.';
    popup.appendChild(empty);
  } else {
    const list = document.createElement('ul');
    list.style.cssText = 'margin:0;padding-left:18px;max-height:180px;overflow:auto;';
    customers.forEach(item => {
      const li = document.createElement('li');
      const date = item.booking_date ? new Date(`${item.booking_date}T00:00:00`).toLocaleDateString() : '';
      const time = item.booking_time ? String(item.booking_time).slice(0, 5) : '';
      li.textContent = `${item.customer_name || 'Unknown customer'}${item.contact_number ? ` (${item.contact_number})` : ''} — ${item.service || 'Service not specified'} — ${date} ${time} — ${item.status || 'pending'}`;
      list.appendChild(li);
    });
    popup.appendChild(list);
  }
  const edit=document.createElement('button'); edit.type='button'; edit.className='btn btn-outline btn-sm'; edit.textContent='Edit / Move Pin'; edit.onclick=()=>editBranch(b); popup.appendChild(edit);
  marker.bindPopup(popup, { maxWidth: 360 });
  bounds.push([Number(b.latitude), Number(b.longitude)]);
});
if (bounds.length > 1) map.fitBounds(bounds, { padding: [30,30] });

}

let picker=null, pin=null;
function editBranch(data={}) {
 const form=document.querySelector('#brModal form'); form.reset();
 for(const name of ['id','branch_name','contact_number','address_line','barangay','city','province','postal_code','latitude','longitude']) form.elements[name].value=data[name] ?? '';
 form.elements.hr_capacity.value=data.hr_capacity || 1;
 form.elements.action.value=data.id ? 'edit' : 'create';
 document.getElementById('branchTitle').textContent=data.id ? 'Edit Branch' : 'Add Branch';
 document.getElementById('legacyAddress').textContent=data.id && !data.city ? 'Existing address: '+data.address+'. Complete the address fields below to standardize it.' : '';
 document.getElementById('brModal').classList.add('open');
 if(typeof L==='undefined') { document.getElementById('pickerHelp').textContent='Map unavailable. Enter coordinates manually, or restore your internet connection and reload.'; return; }
 if(!picker) {
   picker=L.map('locationPicker').setView([8.4542,124.6319],12);
   L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors'}).addTo(picker);
   picker.on('click',ev=>setPin(ev.latlng.lat,ev.latlng.lng));
 }
 if(pin) {picker.removeLayer(pin);pin=null;}
 const lat=Number(data.latitude),lng=Number(data.longitude);
 if(data.latitude!=null && data.longitude!=null && Number.isFinite(lat) && Number.isFinite(lng)) setPin(lat,lng);
 requestAnimationFrame(()=>{picker.invalidateSize();if(pin)picker.setView(pin.getLatLng(),15);});
}
function setPin(lat,lng) {
 if(!pin) {pin=L.marker([lat,lng],{draggable:true}).addTo(picker);pin.on('dragend',()=>{const pt=pin.getLatLng();setPin(pt.lat,pt.lng);});}
 pin.setLatLng([lat,lng]);
 const form=document.querySelector('#brModal form');form.elements.latitude.value=lat.toFixed(7);form.elements.longitude.value=lng.toFixed(7);
}
for(const name of ['latitude','longitude']) document.querySelector('#brModal form').elements[name].addEventListener('change',()=>{
 const f=document.querySelector('#brModal form');if(picker && f.elements.latitude.value!=='' && f.elements.longitude.value!=='' && f.elements.latitude.checkValidity() && f.elements.longitude.checkValidity()) {setPin(Number(f.elements.latitude.value),Number(f.elements.longitude.value));picker.panTo(pin.getLatLng());}
});
const oldBranch=<?= json_encode($old,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
if(oldBranch && oldBranch.action!=='delete') editBranch(oldBranch);
document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{if(f.checkValidity()) f.querySelectorAll('button').forEach(b=>b.disabled=true);}));
document.addEventListener('keydown',ev=>{if(ev.key==='Escape')document.querySelectorAll('.modal-backdrop.open').forEach(m=>m.classList.remove('open'));});
document.querySelectorAll('.modal-backdrop').forEach(m => { m.addEventListener('click', e => { if (e.target===m) m.classList.remove('open'); }); });
</script>
</body></html>
