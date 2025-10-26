<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// fetch HR user info for sidebar
$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmtU = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmtU->bind_param("i", $user_id);
$stmtU->execute();
$user = $stmtU->get_result()->fetch_assoc() ?: [];
$stmtU->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// fetch office head accounts (users.role = 'office_head') with email from users table
$officeHeads = [];
$q1 = $conn->prepare("
  SELECT u.user_id, u.username, u.first_name, u.last_name, u.office_name, u.status,
         u.email AS oh_email
  FROM users u
  WHERE u.role = 'office_head'
  ORDER BY u.first_name, u.last_name
");
$q1->execute();
$res1 = $q1->get_result();
while ($r = $res1->fetch_assoc()) $officeHeads[] = $r;
$q1->close();

// fetch hr staff accounts
$hrStaff = [];
$q2 = $conn->prepare("SELECT user_id, username, first_name, last_name, office_name, status FROM users WHERE role = 'hr_staff' ORDER BY first_name, last_name");
$q2->execute();
$res2 = $q2->get_result();
while ($r = $res2->fetch_assoc()) $hrStaff[] = $r;
$q2->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - Accounts</title>
<style>
  *{box-sizing:border-box;font-family:'Poppins',sans-serif}
  body{margin:0;background:#f7f8fc;display:flex;min-height:100vh}
  .sidebar{width:220px;background:#2f3850;color:#fff;display:flex;flex-direction:column;align-items:center;padding:28px 12px;gap:8px}
  .profile img{width:84px;height:84px;border-radius:50%;background:#cfd3db;margin-bottom:8px}
  .profile h3{margin:0;font-size:16px}
  .profile p{margin:0;font-size:13px;color:#bfc4d1}
  .nav{display:flex;flex-direction:column;gap:10px;width:100%;margin-top:18px}
  .nav a{display:block;padding:10px 16px;color:#fff;text-decoration:none;border-radius:22px;margin:0 8px;font-weight:600}
  .nav a.active, .nav a:hover{background:#fff;color:#2f3850}
  .main{flex:1;padding:24px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .tabs{display:flex;gap:18px;border-bottom:2px solid #eef1f6;padding-bottom:12px;margin-bottom:16px}
  .tabs button{background:none;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:600;color:#2f3850}
  .tabs button.active{border-bottom:3px solid #2f3850}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  input[type=text]{padding:10px;border:1px solid #ddd;border-radius:8px}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:12px;border:1px solid #eee;text-align:left}
  .tbl th{background:#f4f6fb;font-weight:700}
  .actions{display:flex;gap:8px;justify-content:center}
  .btn{padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
  .btn-add{background:#fff;border:1px solid #ddd}
  .icon-btn{background:none;border:none;cursor:pointer;font-size:16px}
  .status-active{color:#0b7a3a;font-weight:600}
  .status-inactive{color:#a0a0a0}
  .empty{padding:18px;text-align:center;color:#777}
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} }
</style>
</head>
<body>
  <div class="sidebar" role="navigation" aria-label="Sidebar">
    <div class="profile" style="text-align:center">
      <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
      <h3><?= htmlspecialchars($full_name ?: ($_SESSION['username'] ?? 'HR Head')) ?></h3>
      <p><?= htmlspecialchars($role_label) ?></p>
    </div>
    <nav class="nav" aria-label="Main navigation">
      <a href="hr_head_home.php">🏠 Home</a>
      <a href="hr_head_ojts.php">👥 OJTs</a>
      <a href="hr_head_dtr.php">🕒 DTR</a>
      <a href="hr_head_accounts.php" class="active">👤 Accounts</a>
      <a href="hr_head_reports.php">📊 Reports</a>
    </nav>
    <div style="margin-top:auto;font-weight:700">OJT-MS</div>
  </div>

  <main class="main" role="main">
    <div class="card" role="region" aria-label="Accounts">
      <div class="tabs" role="tablist" aria-label="Account tabs">
        <button class="active" data-tab="office">Office Heads</button>
        <button data-tab="hr">HR Staffs</button>
      </div>

      <div class="controls">
        <input type="text" id="search" placeholder="Search name / email / office" style="width:360px">
        <div style="flex:1"></div>
        <button class="btn btn-add" id="btnAdd">Add Account</button>
      </div>

      <div id="panel-office" class="panel" style="display:block">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblOffice">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Office</th>
                <th style="text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($officeHeads)): ?>
                <tr><td colspan="4" class="empty">No office head accounts found.</td></tr>
              <?php else: foreach ($officeHeads as $o): 
                $name = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
                $email = $o['oh_email'] ?: '';
                $officeName = $o['office_name'] ?: '';
                $status = $o['status'] === 'active' ? 'active' : 'inactive';
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name . ' ' . $email . ' ' . $officeName)) ?>">
                  <td><?= htmlspecialchars($name ?: $o['username']) ?></td>
                  <td><?= htmlspecialchars($email ?: '—') ?></td>
                  <td><?= htmlspecialchars($officeName ?: '—') ?></td>
                  <td style="text-align:center" class="actions">
                    <button class="icon-btn" title="Edit" onclick="editAccount(<?= (int)$o['user_id'] ?>)">✏️</button>
                    <button class="icon-btn" title="<?= $status==='active' ? 'Deactivate' : 'Activate' ?>" onclick="toggleStatus(<?= (int)$o['user_id'] ?>, this)"><?= $status==='active' ? '🔓' : '🔒' ?></button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-hr" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblHR">
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Office</th>
                <th style="text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($hrStaff)): ?>
                <tr><td colspan="4" class="empty">No HR staff accounts found.</td></tr>
              <?php else: foreach ($hrStaff as $h):
                $name = trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''));
                $username = $h['username'] ?: '';
                $office = $h['office_name'] ?: '';
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name . ' ' . $username . ' ' . $office)) ?>">
                  <td><?= htmlspecialchars($name ?: $username) ?></td>
                  <td><?= htmlspecialchars($username) ?></td>
                  <td><?= htmlspecialchars($office ?: '—') ?></td>
                  <td style="text-align:center" class="actions">
                    <button class="icon-btn" title="Edit" onclick="editAccount(<?= (int)$h['user_id'] ?>)">✏️</button>
                    <button class="icon-btn" title="<?= ($h['status']==='active' ? 'Deactivate' : 'Activate') ?>" onclick="toggleStatus(<?= (int)$h['user_id'] ?>, this)"><?= ($h['status']==='active' ? '🔓' : '🔒') ?></button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

<script>
(function(){
  // tab switching
  document.querySelectorAll('.tabs button').forEach(btn=>{
    btn.addEventListener('click', function(){
      document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      const t = this.getAttribute('data-tab');
      document.getElementById('panel-office').style.display = t === 'office' ? 'block' : 'none';
      document.getElementById('panel-hr').style.display = t === 'hr' ? 'block' : 'none';
    });
  });

  // search filter
  const search = document.getElementById('search');
  search.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('tbody tr[data-search]').forEach(tr=>{
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q) === -1 ? 'none' : '';
    });
  });

  // open modal instead of navigating
  document.getElementById('btnAdd').addEventListener('click', ()=> {
    openAdd();
  });

})();

function editAccount(userId) {
  // navigate to edit page (implement page separately)
  window.location.href = 'account_edit.php?id=' + encodeURIComponent(userId);
}

async function toggleStatus(userId, btn) {
  if (!confirm('Change account status?')) return;
  try {
    btn.disabled = true;
    const res = await fetch('../hr_actions.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'toggle_user_status', user_id: parseInt(userId,10) })
    });
    const j = await res.json();
    if (!j || !j.success) {
      alert('Failed: ' + (j?.message || 'Unknown error'));
      btn.disabled = false;
      return;
    }
    // reflect change in UI: swap icon
    if (j.new_status === 'active') btn.textContent = '🔓'; else btn.textContent = '🔒';
    btn.disabled = false;
  } catch (e) {
    console.error(e);
    alert('Request failed');
    btn.disabled = false;
  }
}
</script>

<!-- Add Account Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;z-index:9999">
  <div style="background:#fff;padding:18px;border-radius:10px;width:520px;max-width:94%;box-shadow:0 12px 30px rgba(0,0,0,0.15)">
    <h3 style="margin:0 0 12px">Create Office Head Account</h3>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <input id="m_first" placeholder="First name" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <input id="m_last" placeholder="Last name" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <input id="m_email" placeholder="Email" style="padding:10px;border:1px solid #ddd;border-radius:8px;grid-column:span 2" />
      <!-- replaced dropdown with free-text office input -->
      <input id="m_office" placeholder="Office" style="padding:10px;border:1px solid #ddd;border-radius:8px;grid-column:span 2" />
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeAdd()" class="btn" type="button">Cancel</button>
      <button onclick="submitAdd()" class="btn btn-add" type="button">Create</button>
    </div>
    <div id="addModalStatus" style="margin-top:10px;display:none;padding:8px;border-radius:6px"></div>
  </div>
</div>

<script>
function openAdd(){
  document.getElementById('addModal').style.display = 'flex';
  // reset fields
  document.getElementById('m_first').value = '';
  document.getElementById('m_last').value = '';
  document.getElementById('m_email').value = '';
  document.getElementById('m_office').value = '';
  const st = document.getElementById('addModalStatus');
  st.style.display = 'none'; st.textContent = '';
}

function closeAdd(){
  document.getElementById('addModal').style.display = 'none';
}

function randomPassword(len = 10){
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let s = '';
  for (let i=0;i<len;i++) s += chars[Math.floor(Math.random()*chars.length)];
  return s;
}

async function submitAdd(){
  const first_name = (document.getElementById('m_first').value || '').trim();
  const last_name = (document.getElementById('m_last').value || '').trim();
  const email = (document.getElementById('m_email').value || '').trim();
  const office = (document.getElementById('m_office').value || '').trim();

  if (!first_name || !last_name || !email) {
    alert('Please fill first name, last name and email.');
    return;
  }

  // generate simple username + password fallback (backend should ideally generate properly)
  const unameBase = (first_name.charAt(0) + last_name).toLowerCase().replace(/[^a-z0-9]/g,'');
  const username = unameBase + Math.floor(Math.random()*900 + 100);
  const password = randomPassword(10);

  const payload = {
    action: 'create_account',
    username: username,
    password: password,
    first_name: first_name,
    last_name: last_name,
    email: email,
    role: 'office_head',
    office: office
  };

  const statusEl = document.getElementById('addModalStatus');
  statusEl.style.display = 'block';
  statusEl.style.background = '#fffbe6';
  statusEl.style.color = '#333';
  statusEl.textContent = 'Creating account...';

  try {
    const res = await fetch('../hr_actions.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json();
    if (!j || !j.success) {
      statusEl.style.background = '#fff4f4';
      statusEl.style.color = '#a00';
      statusEl.textContent = 'Failed: ' + (j?.message || 'Unknown error');
      return;
    }
    statusEl.style.background = '#e6f9ee';
    statusEl.style.color = '#0b7a3a';
    statusEl.textContent = 'Account created successfully.';
    // close after short delay and reload to show new account
    setTimeout(()=>{ closeAdd(); location.reload(); }, 900);
  } catch (err) {
    console.error(err);
    statusEl.style.background = '#fff4f4';
    statusEl.style.color = '#a00';
    statusEl.textContent = 'Request failed.';
  }
}
</script>
</body>
</html>