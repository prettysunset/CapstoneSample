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

// fetch office head accounts (users.role = 'office_head') with email from office_heads when available
$officeHeads = [];
$q1 = $conn->prepare("
  SELECT u.user_id, u.username, u.first_name, u.last_name, u.office_name, u.status,
         oh.email AS oh_email
  FROM users u
  LEFT JOIN office_heads oh ON oh.user_id = u.user_id
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
      <a href="#">📊 Reports</a>
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

  // add account (navigate to generic add page)
  document.getElementById('btnAdd').addEventListener('click', ()=> {
    window.location.href = 'account_create.php';
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
</body>
</html>