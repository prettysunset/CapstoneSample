<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// resolve office for this office head
$office_name = '';
$tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s = $conn->prepare("
        SELECT o.office_name
        FROM office_heads oh
        JOIN offices o ON oh.office_id = o.office_id
        WHERE oh.user_id = ? LIMIT 1
    ");
    $s->bind_param('i',$uid); $s->execute();
    $tmp = $s->get_result()->fetch_assoc(); $s->close();
    if ($tmp && !empty($tmp['office_name'])) $office_name = $tmp['office_name'];
}
if (!$office_name) {
    $s2 = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
    $s2->bind_param('i',$uid); $s2->execute();
    $tmp2 = $s2->get_result()->fetch_assoc(); $s2->close();
    if ($tmp2 && !empty($tmp2['office_name'])) $office_name = $tmp2['office_name'];
}
$office_display = preg_replace('/\s+Office\s*$/i','', trim($office_name ?: 'Unknown Office'));

// --- added: resolve office_id and load office_requests for this office ---
$office_id = null;
$office_requests = [];
if (!empty($office_name)) {
    $s4 = $conn->prepare("SELECT office_id FROM offices WHERE office_name = ? LIMIT 1");
    if ($s4) {
        $s4->bind_param('s', $office_name);
        $s4->execute();
        $tmp4 = $s4->get_result()->fetch_assoc();
        $s4->close();
        $office_id = isset($tmp4['office_id']) ? (int)$tmp4['office_id'] : null;
    }
}
if ($office_id) {
    $rq = $conn->prepare("SELECT request_id, old_limit, new_limit, reason, status, date_requested FROM office_requests WHERE office_id = ? ORDER BY date_requested DESC, request_id DESC");
    if ($rq) {
        $rq->bind_param('i', $office_id);
        $rq->execute();
        $resq = $rq->get_result();
        while ($r = $resq->fetch_assoc()) $office_requests[] = $r;
        $rq->close();
    }
}

// status filter from querystring — include "all" and default to all
$allowed = ['all','pending','approved','ongoing','completed','no_response','rejected'];
$statusFilter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'all';

// fetch OJTs for this office with status filtering
$ojts = [];

// base SQL (we LEFT JOIN ojt_applications to evaluate application status and detect "no response")
$sql = "
  SELECT u.user_id,
         COALESCE(s.first_name, u.first_name, '') AS first_name,
         COALESCE(s.last_name,  u.last_name,  '') AS last_name,
         COALESCE(s.college,'') AS school,
         COALESCE(s.course,'') AS course,
         COALESCE(s.year_level,'') AS year_level,
         COALESCE(s.hours_rendered,0) AS hours_rendered,
         COALESCE(s.total_hours_required,500) AS total_hours_required,
         COALESCE(s.progress,0) AS progress,
         COALESCE(s.status,'') AS student_status,
         COALESCE(oa.status,'') AS application_status,
         COALESCE(lc.late_count,0) AS late_count,
         COALESCE(u.status,'') AS user_status,
         '' AS date_started,
         '' AS expected_end_date
  FROM users u
  LEFT JOIN students s ON s.user_id = u.user_id
  LEFT JOIN ojt_applications oa ON oa.student_id = s.student_id
  LEFT JOIN (
      SELECT student_id, COUNT(*) AS late_count
      FROM late_dtr
      GROUP BY student_id
  ) lc ON lc.student_id = s.student_id
  WHERE u.role = 'ojt' AND u.office_name LIKE ?
";

/* Always include OJTs that are active in the office (approved / ongoing / completed).
   Additional status filters (from the dropdown) are applied below and will further
   narrow results when selected. */
$sql .= " AND (COALESCE(s.status, u.status, oa.status) IN ('approved','ongoing','completed'))";

// apply status filter only when not "all"
if ($statusFilter !== 'all') {
    if ($statusFilter === 'pending') {
        $sql .= " AND (COALESCE(s.status,'') = 'pending' OR COALESCE(oa.status,'') = 'pending')";
    } elseif ($statusFilter === 'approved') {
        $sql .= " AND (COALESCE(oa.status,'') = 'approved' OR COALESCE(s.status,'') = 'approved' OR COALESCE(u.status,'') = 'approved')";
    } elseif ($statusFilter === 'ongoing') {
        $sql .= " AND (COALESCE(oa.status,'') = 'ongoing' OR COALESCE(s.status,'') = 'ongoing' OR COALESCE(u.status,'') = 'ongoing')";
    } elseif ($statusFilter === 'completed') {
        $sql .= " AND (COALESCE(s.status,'') = 'completed' OR COALESCE(u.status,'') = 'completed')";
    } elseif ($statusFilter === 'rejected') {
        $sql .= " AND COALESCE(oa.status,'') = 'rejected'";
    } elseif ($statusFilter === 'no_response') {
        $sql .= " AND oa.application_id IS NULL";
    }
}

$sql .= " ORDER BY COALESCE(s.last_name,u.last_name), COALESCE(s.first_name,u.first_name)";

$like = '%' . $office_name . '%';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $ojts[] = $r;
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Office Head — Reports</title>
<style>
  body{font-family:'Poppins',sans-serif;margin:0;background:#f5f6fa;color:#2f3459}
  .sidebar{width:220px;background:#2f3459;height:100vh;position:fixed;color:#fff;padding-top:30px}
  .main{margin-left:240px;padding:28px}
  .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 6px 20px rgba(47,52,89,0.04)}
  .top-icons{position:fixed;top:18px;right:28px;display:flex;gap:12px;z-index:1200}
  .sidebar h3{text-align:center;margin-bottom:5px}
  .sidebar p{text-align:center;font-size:14px;margin-top:0}
  .sidebar a{display:flex;align-items:center;gap:8px;padding:10px 20px;margin:10px;color:#fff;border-radius:20px;text-decoration:none}
  .sidebar a.active{background:#fff;color:#2f3459}

  /* Page layout (account for sidebar width + spacing) */
  body{font-family:Poppins, sans-serif;margin:0;background:#f5f6fa;color:#2f3459}
  .main{margin-left:260px; /* leave space for sidebar + gap */ padding:28px}
  .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 6px 20px rgba(0,0,0,0.04)}
  .tabs-row{display:flex;gap:12px;align-items:center;margin-bottom:18px}
  /* updated: remove underline from tab links */
  .tab-pill{
    background:#fff;
    color:#4b4f63;
    padding:10px 18px;
    border-radius:10px;
    border:1px solid #e9eaf2;
    cursor:pointer;
    font-weight:700;
    text-decoration:none;        /* remove underline */
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
   .controls{display:flex;gap:12px;align-items:center;margin-left:auto}
  .search{padding:10px 14px;border-radius:12px;border:1px solid #e6e9f2;width:380px}
  .export{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:#fff;border:1px solid #e6e9f2;cursor:pointer}
  .dropdown{padding:8px 12px;border-radius:8px;border:1px solid #e6e9f2;background:#fff}
  table{width:100%;border-collapse:collapse;margin-top:14px}
  thead th{background:#f5f7fb;padding:14px;text-align:left;font-weight:700;border-bottom:1px solid #eef1f6}
  td{padding:14px;border-bottom:1px solid #f0f2f7}
  .date-badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#f4f6fa;font-size:13px;color:#5b606f}
  .small-pill{background:#f0f3ff;color:#2f3850;padding:6px 10px;border-radius:12px;font-weight:700;text-align:center;display:inline-block}
  /* match DTR top icons placement (inside .main, aligned to its right edge) */
  #top-icons {
    display: flex;
    justify-content: flex-end;
    gap: 14px;
    align-items: center;
    margin: 8px 0 12px 0;
    z-index: 50;
    width: 100%;
    box-sizing: border-box;
  }
  @media(max-width:900px){ .main{padding:16px} .search{width:160px} .sidebar{display:none} .main{margin-left:16px} }

  /* header: raise title a bit and put tabs/controls on next line */
  .card-header{ display:flex; flex-direction:column; gap:8px; align-items:stretch; }
  .card-header .title{ align-self:flex-start; transform: translateY(-6px); }
  .card-header .row{ display:flex; gap:12px; align-items:center; width:100%; }
  .card-header .row .tabs-row{ flex:1; margin:0; }
  @media(max-width:700px){
    .card-header .row{ flex-direction:column; align-items:stretch; }
    .card-header .title{ transform:none; }
  }
</style>
</head>
<body>
<?php
// ensure $user_name is available (fallback to DB or session)
$user_name = '';
if (!empty($_SESSION['user_name'])) {
  $user_name = $_SESSION['user_name'];
} else {
  $s3 = $conn->prepare("SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS name FROM users WHERE user_id = ? LIMIT 1");
  $s3->bind_param('i', $uid);
  $s3->execute();
  $tmp3 = $s3->get_result()->fetch_assoc();
  $s3->close();
  $user_name = $tmp3['name'] ?? '';
}
$user_name = trim($user_name) ?: 'Office Head';
$current = basename($_SERVER['SCRIPT_NAME']);
?>

  <div class="sidebar">
  <div style="text-align:center;padding:18px 12px 8px;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fff;color:#2f3459;display:inline-flex;align-items:center;justify-content:center;font-weight:700;margin:6px auto;font-size:20px;">
      <?= htmlspecialchars(mb_strtoupper(substr(trim($user_name),0,1) ?: 'O')) ?>
    </div>
    <h3 style="margin:8px 0 4px;font-size:16px;"><?= htmlspecialchars($user_name) ?></h3>
    <p style="margin:0;font-size:13px;opacity:0.9">Office Head — <?= htmlspecialchars($office_display) ?></p>
  </div>

  <nav class="nav" style="margin-top:14px;display:flex;flex-direction:column;gap:8px;padding:0 12px;">
    <a href="office_head_home.php" title="Home">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 11.5L12 4l9 7.5"></path>
        <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
      </svg>
      <span>Home</span>
    </a>

    <a href="office_head_ojts.php" title="OJTs">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="3"></circle>
        <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
      </svg>
      <span>OJTs</span>
    </a>

    <a href="office_head_dtr.php" title="DTR">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>DTR</span>
    </a>

    <a href="office_head_reports.php" class="active" title="Reports">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="10" width="4" height="10"></rect>
        <rect x="10" y="6" width="4" height="14"></rect>
        <rect x="17" y="2" width="4" height="18"></rect>
      </svg>
      <span>Reports</span>
    </a>
  </nav>

  <div style="position:absolute;bottom:20px;width:100%;text-align:center;font-weight:700;padding-bottom:6px">OJT-MS</div>
</div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout (matched to office_head_dtr.php) -->
  <div id="top-icons">
      <a id="btnNotif" href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </a>
      <a id="btnSettings" href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
      </a>
      <a id="btnLogout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="title"><h3 style="margin:0">Reports</h3></div>
      <div class="row">
        <div class="tabs-row" style="flex:1">
          <style>
            .tabs{display:flex;gap:18px;border-bottom:1px solid #e6e9f2;padding-bottom:12px;margin-bottom:16px}
            .tab{padding:10px 18px;border-radius:8px;cursor:pointer;color:#6b6f8b;background:transparent;border:none;font-weight:700}
            .tab.active{border-bottom:3px solid #4f4aa6;color:#111}
            .tab:focus{outline:none}
          </style>

          <div class="tabs">
            <button class="tab-pill tab active" data-tab="ojts" type="button">OJTs</button>
            <button class="tab-pill tab" data-tab="dtr" type="button">DTR</button>
            <button class="tab-pill tab" data-tab="requests" type="button">Office Requests</button>
          </div></div>

        <div class="controls">
          <input id="searchInput" class="search" placeholder="Search" />
          <button id="btnExport" class="export">⬇ Export</button>

          <select id="statusFilter" class="dropdown" aria-label="Filter by status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>all</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>approved</option>
            <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : '' ?>>ongoing</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>completed</option>
          </select>
        </div>
      </div>
    </div>

    <!-- PANEL: OJTs (default) -->
    <div id="panel-ojts" class="panel" style="display:block">
      <div style="overflow:auto">
        <table id="tblAll">
          <thead>
            <tr>
              <th>Name</th>
              <th>School</th>
              <th>Course</th>
              <th>Progress</th>
              <th>Estimated End Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="allBody">
            <?php if (empty($ojts)): ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px">No OJTs found for your office.</td></tr>
            <?php else: foreach ($ojts as $o): ?>
              <tr
                data-name="<?= htmlspecialchars(strtolower(trim($o['first_name'].' '.$o['last_name']))) ?>"
                data-school="<?= htmlspecialchars(strtolower($o['school'])) ?>"
                data-course="<?= htmlspecialchars(strtolower($o['course'])) ?>"
                data-status="<?= htmlspecialchars(strtolower($o['user_status'] ?? '')) ?>"
              >
                <td><?= htmlspecialchars(trim($o['first_name'].' '.$o['last_name'])) ?></td>
                <td><?= htmlspecialchars($o['school'] ?: '-') ?></td>
                <td><?= htmlspecialchars($o['course'] ?: '-') ?></td>
                <td style="text-align:center"><?= is_numeric($o['progress']) ? (int)$o['progress'].'%' : '-' ?></td>
                <td><?php echo $o['expected_end_date'] ? '<span class="date-badge">'.date('M d, Y', strtotime($o['expected_end_date'])).'</span>' : '-'; ?></td>
                <td><?= htmlspecialchars($o['user_status'] ?: '-') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PANEL: DTR -->
    <div id="panel-dtr" class="panel" style="display:none">
      <div style="overflow:auto">
        <table id="dailyTable">
          <thead>
            <tr>
              <th>Date</th>
              <th>Name</th>
              <th>School</th>
              <th>Course</th>
              <th>A.M. Arrival</th>
              <th>A.M. Departure</th>
              <th>P.M. Arrival</th>
              <th>P.M. Departure</th>
              <th>Hours</th>
              <th>Minutes</th>
            </tr>
          </thead>
          <tbody id="dtrBody">
            <tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">No logs loaded.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PANEL: Office Requests -->
    <div id="panel-requests" class="panel" style="display:none">
      <div style="overflow:auto">
        <table id="requestsTable">
          <thead>
            <tr>
              <th>Date Requested</th>
              <th>Requested Limit</th>
              <th>Reason</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="requestsBody">
            <?php if (empty($office_requests)): ?>
              <tr><td colspan="4" style="text-align:center;color:#8a8f9d;padding:18px">No requests found.</td></tr>
            <?php else: foreach ($office_requests as $req): ?>
              <tr data-req-id="<?= (int)($req['request_id'] ?? 0) ?>" data-processed-at="<?= htmlspecialchars($req['date_of_action'] ?? '') ?>">
                <td><?= htmlspecialchars($req['date_requested'] ?? '-') ?></td>
                <td style="text-align:center"><?= htmlspecialchars($req['new_limit'] ?? '-') ?></td>
                <td><?= htmlspecialchars($req['reason'] ?? '-') ?></td>
                <td class="req-status"><?= htmlspecialchars($req['status'] ?? '-') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // client-side tabs (no navigation)
  const tabs = document.querySelectorAll('.tab-pill');
  const panels = {
    ojts: document.getElementById('panel-ojts'),
    dtr: document.getElementById('panel-dtr'),
    requests: document.getElementById('panel-requests')
  };
  function switchTab(name){
    tabs.forEach(t=>t.classList.toggle('active', t.dataset.tab===name));
    Object.keys(panels).forEach(k=>{
      panels[k].style.display = k === name ? 'block' : 'none';
    });
  }
  tabs.forEach(t=> t.addEventListener('click', ()=> switchTab(t.dataset.tab)));

  // search filter for ALL OJTs table
  const search = document.getElementById('searchInput');
  search.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#allBody tr').forEach(tr=>{
      if (!tr.dataset || !tr.dataset.name) return;
      const hay = (tr.dataset.name + ' ' + tr.dataset.school + ' ' + tr.dataset.course + ' ' + (tr.dataset.status||''));
      tr.style.display = q === '' || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });

  // status dropdown — redirect with query param (default handled server-side)
  document.getElementById('statusFilter').addEventListener('change', function(){
    const v = this.value;
    const url = new URL(window.location.href);
    url.searchParams.set('status', v);
    window.location.href = url.toString();
  });

  // DTR search removed (search input was removed from the DTR panel)
 
  // export button placeholder
  document.getElementById('btnExport').addEventListener('click', function(){
    alert('Export not implemented — will export current table to CSV when enabled.');
  });

  // top icons actions
  document.addEventListener('click', function(e){
    if (e.target.id === 'btnLogout') {
      if (!confirm('Log out?')) return;
      window.location.replace(e.target.getAttribute('href') || '../logout.php');
      e.preventDefault();
    }
  });

  // --- ADD: load DTR rows from server (office_head_dtr.php -> action 'get_daily_logs') ---
  function fmtDate(d){ return d.toISOString().slice(0,10); }
  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  async function loadDtr(startDate, endDate){
    const tbody = document.getElementById('dtrBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading...</td></tr>';
    try {
      const payload = { action: 'get_daily_logs', start_date: startDate, end_date: endDate };
      const resp = await fetch('office_head_dtr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      // server may return { success: true, data: [...] } or { success: true, rows: [...] }
      const data = await resp.json().catch(()=>({success:false}));
      const rows = Array.isArray(data.data) ? data.data
                 : Array.isArray(data.rows) ? data.rows
                 : Array.isArray(data) ? data : [];
      if (!data.success || rows.length === 0) {
         tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">No logs found for selected range.</td></tr>';
         return;
       }
       tbody.innerHTML = '';
       rows.forEach(r=>{
         const tr = document.createElement('tr');
         tr.innerHTML = [
           `<td>${esc(r.log_date||'')}</td>`,
           `<td>${esc((r.first_name||'') + ' ' + (r.last_name||''))}</td>`,
           `<td>${esc(r.school || '-')}</td>`,
           `<td>${esc(r.course || '-')}</td>`,
           `<td>${esc(r.am_in || '-')}</td>`,
           `<td>${esc(r.am_out || '-')}</td>`,
           `<td>${esc(r.pm_in || '-')}</td>`,
           `<td>${esc(r.pm_out || '-')}</td>`,
           `<td style="text-align:center">${esc(r.hours ?? 0)}</td>`,
           `<td style="text-align:center">${esc(r.minutes ?? 0)}</td>`
         ].join('');
         tbody.appendChild(tr);
       });
    } catch (err) {
      console.error('loadDtr error', err);
      tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#a00;padding:18px">Unable to load logs.</td></tr>';
    }
  }

  // load when switching to the DTR tab (default range: last 14 days)
  document.querySelectorAll('.tab-pill').forEach(btn=>{
    btn.addEventListener('click', function(){
      if (this.dataset.tab === 'dtr') {
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - 14);
        loadDtr(fmtDate(start), fmtDate(end));
      }
    });
  });

  // If DTR tab is active on page load, auto-load
  (function initDtrIfActive(){
    const active = document.querySelector('.tab-pill.active');
    if (active && active.dataset.tab === 'dtr') {
      const end = new Date();
      const start = new Date(); start.setDate(end.getDate() - 14);
      loadDtr(fmtDate(start), fmtDate(end));
    }
  })();
  // --- END ADD ---
})();
</script>
<script>
  // attach confirm to top logout like hr_head_ojts.php
  (function(){
    const logoutBtn = document.getElementById('btnLogout') || document.querySelector('a[href$="logout.php"]');
    if (!logoutBtn) return;
    logoutBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = this.getAttribute('href') || '../logout.php';
      }
    });
  })();
</script>
</body>
</html>