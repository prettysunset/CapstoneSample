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
    body{background:#f7f8fc;display:flex;min-height:100vh;margin:0}
    .sidebar{background:#2f3850;width:220px;color:#fff;display:flex;flex-direction:column;align-items:center;padding:30px 0}
    .profile{text-align:center;margin-bottom:20px}
    .profile img{width:90px;height:90px;border-radius:50%;background:#cfd3db;margin-bottom:10px}
    .profile h3{font-size:16px;font-weight:600}
    .profile p{font-size:13px;color:#bfc4d1}
    .nav{display:flex;flex-direction:column;gap:10px;width:100%}
    .nav a{color:#fff;text-decoration:none;padding:10px 20px;display:flex;align-items:center;gap:10px;border-radius:25px;margin:0 15px}
    .nav a:hover,.nav a.active{background:#fff;color:#2f3850;font-weight:600}
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
  .btn-add{
    background:#3a4163;
    color:#fff;
    border:1px solid #ddd;
    border-radius:16px;
    padding:12px 20px;
    font-size:16px;
    min-width:140px;
    height:44px;
    line-height:1;
    cursor:pointer;
  }
  .icon-btn{background:none;border:none;cursor:pointer;font-size:16px}
  .status-active{color:#0b7a3a;font-weight:600}
  .status-inactive{color:#a0a0a0}
  .empty{padding:18px;text-align:center;color:#777}
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} }
</style>
</head>
<body>
  <div class="sidebar">
    <div class="profile">
        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
        <h3><?php echo htmlspecialchars($full_name ?: ($_SESSION['username'] ?? '')); ?></h3>
        <p><?php echo htmlspecialchars($role_label); ?></p>
        <?php if(!empty($user['office_name'])): ?>
            <p style="font-size:12px;color:#bfc4d1"><?php echo htmlspecialchars($user['office_name']); ?></p>
        <?php endif; ?>
    </div>

    
    <div class="nav">
      <a href="hr_head_home.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <path d="M3 11.5L12 4l9 7.5"></path>
          <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
        </svg>
        Home
      </a>
      <a href="hr_head_ojts.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="8" r="3"></circle>
          <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
        </svg>
        OJTs
      </a>
      <a href="hr_head_dtr.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 8v5l3 2"></path>
        </svg>
        DTR
      </a>
      <a href="hr_head_moa.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 8v5l3 2"></path>
        </svg>
        MOA
      </a>
      <a href="#" class="active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
        Accounts
      </a>
      <a href="hr_head_reports.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <rect x="3" y="10" width="4" height="10"></rect>
          <rect x="10" y="6" width="4" height="14"></rect>
          <rect x="17" y="2" width="4" height="18"></rect>
        </svg>
        Reports
      </a>
    </div>
    <div style="margin-top:auto;font-weight:700">OJT-MS</div>
  </div>
 

  <main class="main" role="main">
    <!-- top-right outline icons: notifications, settings, logout
         NOTE: removed position:fixed to prevent overlapping; icons now flow with page
         and stay visible. -->
    <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </a>
        <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
        </a>
        <a id="top-logout" href="/logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
    <div class="card" role="region" aria-label="Accounts">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px">
      <div>
        <h2 style="margin:0 0 6px;font-size:20px">Accounts</h2>
      </div>
      <div style="text-align:right;color:#62718a;font-size:13px">
        <div><?php echo date('F j, Y'); ?></div>
        <div style="font-weight:700;margin-top:6px"><?php echo htmlspecialchars($full_name ?: ($_SESSION['username'] ?? '')); ?></div>
      </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <style>
          /* underline only under the text of the active tab */
          .tabs .tab span{display:inline-block;padding-bottom:6px;border-bottom:3px solid transparent;transition:border-color .15s ease;}
          .tabs .tab.active span{border-color:#2f3850;}
        </style>

        <div class="tabs" role="tablist" aria-label="Account Tabs"
         style="display:flex;justify-content:center;align-items:flex-end;gap:24px;font-size:18px;border-bottom:2px solid #eee;padding-bottom:12px;position:relative;">
          <button class="tab active" data-tab="office" role="tab" aria-selected="true" aria-controls="panel-office"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px 6px 0 0;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;transition:border-color .15s ease;">
        <span>Office Heads</span>
          </button>
          <button class="tab" data-tab="hr" role="tab" aria-selected="false" aria-controls="panel-hr"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px 6px 0 0;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;transition:border-color .15s ease;">
        <span>HR Staffs</span>
          </button>
        </div>
      </div>

      <div class="controls">
        <div style="position:relative;width:360px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#62718a">
            <circle cx="11" cy="11" r="7"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="search" placeholder="Search name / email / office" style="width:100%;padding:10px 10px 10px 36px;border:1px solid #ddd;border-radius:8px" />
        </div>
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