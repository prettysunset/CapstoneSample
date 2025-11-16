<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// fetch user for sidebar display
$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmtUser = $conn->prepare("SELECT first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc() ?: [];
$stmtUser->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// add same datetime variables as OJTs
$current_time = date("g:i A");
$current_date = date("l, F j, Y");

// fetch distinct offices for dropdown
$stmtOff = $conn->prepare("SELECT DISTINCT office_name FROM users WHERE office_name IS NOT NULL AND office_name <> '' ORDER BY office_name");
$stmtOff->execute();
$resOff = $stmtOff->get_result();
$offices = [];
while ($r = $resOff->fetch_assoc()) $offices[] = $r['office_name'];
$stmtOff->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - Daily Logs (DTR)</title>
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
    .top-section{display:flex;justify-content:space-between;gap:20px;margin-bottom:20px}
    .datetime h2{font-size:22px;color:#2f3850;margin:0}
    .datetime p{color:#6d6d6d;margin:0}
    .table-container{background:#fff;border-radius:8px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
    .table-tabs{display:flex;gap:16px;margin-bottom:12px;border-bottom:2px solid #999}
    .table-tabs a{padding:8px 12px;text-decoration:none;color:#555;border-radius:6px}
    .table-tabs a.active{background:#2f3850;color:#fff}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px;border:1px solid #999;text-align:left}
    th{background:#f5f6fa}
    .actions{display:flex;gap:8px;justify-content:center}
    .actions button{border:none;background:none;cursor:pointer;font-size:16px}
    .approve{color:green} .reject{color:red} .view{color:#0b74de}
    .empty{padding:20px;text-align:center;color:#666}
     
 .main{flex:1;padding:24px}
  .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .tabs{display:flex;gap:18px;border-bottom:2px solid #eef1f6;padding-bottom:12px;margin-bottom:16px}
  .tabs button{background:none;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:600;color:#2f3850}
  .tabs button.active{border-bottom:3px solid #2f3850}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  input[type=date], input[type=text]{padding:10px;border:1px solid #ddd;border-radius:8px}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:10px;border:1px solid #999}
  .tbl thead th{background:#f4f6fb;font-weight:700;color:#333}
  .tbl thead th.left{ text-align:left }
  .tbl thead th.center{ text-align:center }
  .tbl td{ text-align:left }
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
      <a href="#" class="active">
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
      <a href="hr_head_accounts.php">
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
    <div style="margin-top:auto;padding:18px 0;width:100%;text-align:center;">
      <p style="margin:0;font-weight:600">OJT-MS</p>
    </div>
  </div>
 
  <main class="main" role="main">
    <!-- top-right outline icons: notifications, calendar (display only), settings, logout -->
    <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </a>

        <!-- calendar icon (display only, non-clickable) - placed to match OJTs -->
        <div title="Calendar (display only)" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;pointer-events:none;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>

        <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path></svg>
        </a>
        <a id="top-logout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>

    <!-- place datetime in same location/markup as OJTs -->
    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?= htmlspecialchars($current_time) ?></h2>
                <p><?= htmlspecialchars($current_date) ?></p>
            </div>
        </div>
    </div>

    <div class="table-container">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="tabs" role="tablist" aria-label="DTR Tabs"
           style="display:flex;justify-content:center;align-items:flex-end;gap:24px;font-size:18px;border-bottom:2px solid #eee;padding-bottom:12px;position:relative;">
          <!-- Only Daily Logs retained -->
          <div style="font-size:18px;font-weight:700;color:#2f3850">Daily Logs</div>
        </div>
      </div>

      <div id="panel-daily" class="panel" style="display:block">
        <div class="controls" style="margin-bottom:16px">
          <label for="dtrDate" style="font-weight:600">Date</label>
          <input type="date" id="dtrDate">

          <!-- office filter dropdown -->
          <label for="officeFilter" style="font-weight:600;margin-left:12px">Office</label>
          <select id="officeFilter" style="padding:10px;border:1px solid #ddd;border-radius:8px">
            <option value="all">All offices</option>
            <?php foreach($offices as $o): ?>
              <option value="<?php echo htmlspecialchars($o); ?>"><?php echo htmlspecialchars($o); ?></option>
            <?php endforeach; ?>
          </select>

          <!-- sort controls -->
          <label for="sortBy" style="font-weight:600;margin-left:12px">Sort by</label>
          <select id="sortBy" style="padding:10px;border:1px solid #ddd;border-radius:8px">
            <option value="none">None</option>
            <option value="am_in">A.M. Arrival</option>
            <option value="am_out">A.M. Departure</option>
            <option value="pm_in">P.M. Arrival</option>
            <option value="pm_out">P.M. Departure</option>
          </select>

          <select id="sortDir" style="padding:10px;border:1px solid #ddd;border-radius:8px;margin-left:6px">
            <option value="asc">Asc</option>
            <option value="desc">Desc</option>
          </select>

          <!-- reload button removed: table updates automatically when controls change -->
 
          <div style="flex:1"></div>
          <div style="position:relative;display:inline-block">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#9aa4b2;width:16px;height:16px">
              <circle cx="11" cy="11" r="6"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="search" placeholder="Search name / course"
                   style="width:280px;padding:10px 10px 10px 36px;border:1px solid #ddd;border-radius:8px">
          </div>
        </div>

        <div id="tableWrap">
          <!-- table will always render headers via JS -->
          <div class="empty">Loading...</div>
        </div>
      </div>

      <!-- (Late / Reports UI removed — single Daily Logs view) -->
    </div> <!-- /.table-container -->
  </main>

<script>
(function(){
  // tab switching
  document.querySelectorAll('.tabs button').forEach(btn=>{
    btn.addEventListener('click', function(){
      document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      const t = this.getAttribute('data-tab');
      document.querySelectorAll('.panel').forEach(p=>p.style.display = p.id === 'panel-'+t ? 'block' : 'none');
    });
  });

  // controls + elements
  const dateInput = document.getElementById('dtrDate');
  const tableWrap = document.getElementById('tableWrap');
  const search = document.getElementById('search');
  const officeFilter = document.getElementById('officeFilter');
  const sortBy = document.getElementById('sortBy');
  const sortDir = document.getElementById('sortDir');

  const today = new Date().toISOString().slice(0,10);
  dateInput.value = today;

  // automatically load when the date is changed via the calendar
  dateInput.addEventListener('change', loadForDate);
  // support Enter key as well
  dateInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter') loadForDate(); });
  search.addEventListener('input', filterRows);
  officeFilter.addEventListener('change', loadForDate);
  sortBy.addEventListener('change', loadForDate);
  sortDir.addEventListener('change', loadForDate);

  // DAILY: always render headers; fill rows if backend returns data
  async function loadForDate(){
    const dt = dateInput.value || today;
    const office = (officeFilter && officeFilter.value) ? officeFilter.value : 'all';
    const sortKey = (sortBy && sortBy.value) ? sortBy.value : 'none';
    const dir = (sortDir && sortDir.value === 'desc') ? -1 : 1;

    // render header immediately with empty tbody
    tableWrap.innerHTML = renderDailyHeader() + '<tbody id="dtrRows"></tbody></table>';

    try {
      // request only by date; apply office filter & sort on client
      const res = await fetch('../hr_actions.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_dtr_by_date', date: dt })
      });
      const json = await res.json();
      let rows = (json && json.success) ? (json.rows || []) : [];

      // client-side office filter (exact match, case-insensitive)
      if (office && office !== 'all') {
        const offLower = String(office).toLowerCase();
        rows = rows.filter(r => String(r.office || '').toLowerCase() === offLower);
      }

      // client-side sort by selected time column (am_in, am_out, pm_in, pm_out)
      if (sortKey && sortKey !== 'none') {
        rows.sort((a,b)=>{
          const va = timeToMinutes(a[sortKey] || '');
          const vb = timeToMinutes(b[sortKey] || '');
          return (va - vb) * dir;
        });
      }

      renderDailyRows(rows, dt);
      // re-apply current search filter after new rows are rendered
      filterRows();
    } catch (err) {
      // backend failed — show empty table (renderDailyRows handles empty)
      renderDailyRows([], dt);
      filterRows();
    }
  }

  // parse "hh:mm AM/PM" (or "HH:MM") into minutes since midnight; empty/invalid -> large value
  function timeToMinutes(t){
    if (!t) return 24*60 + 1;
    const s = String(t).trim();
    const m = s.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i);
    if (!m) return 24*60 + 1;
    let hh = parseInt(m[1],10), mm = parseInt(m[2],10);
    const ap = (m[3]||'').toUpperCase();
    if (ap === 'PM' && hh < 12) hh += 12;
    if (ap === 'AM' && hh === 12) hh = 0;
    return hh*60 + mm;
  }

  function renderDailyHeader(){
    // Title case headers and center-align TH cells
    return '<table class="tbl"><thead>'
        + '<tr>'
        + '<th class="center" rowspan="2" style="background:#eceff3">Date</th>'
        + '<th class="center" rowspan="2" style="background:#eceff3">Name</th>'
        + '<th class="center" rowspan="2" style="background:#eceff3">School</th>'
        + '<th class="center" rowspan="2" style="background:#eceff3">Course</th>'
        + '<th class="center" colspan="2" style="background:#eceff3">A.M.</th>'
        + '<th class="center" colspan="2" style="background:#eceff3">P.M.</th>'
        + '<th class="center" rowspan="2" style="background:#eceff3">Hours</th>'
        + '<th class="center" rowspan="2" style="background:#eceff3">Minutes</th>'
        + '<th class="center" rowspan="2" style="background:#eceff3">Office</th>'
        + '</tr>'
        + '<tr>'
        + '<th class="center" style="background:#eceff3">Arrival</th><th class="center" style="background:#eceff3">Departure</th><th class="center" style="background:#eceff3">Arrival</th><th class="center" style="background:#eceff3">Departure</th>'
        + '</tr>'
        + '</thead>';
     }
 
   function renderDailyRows(rows, dt){
     // build tbody based on rows; keep header rendered separately
     let tbody = '';
     if (!rows || rows.length === 0) {
       tbody = '<tbody id="dtrRows"><tr><td colspan="11" style="text-align:center;padding:20px;color:#777">No logs for ' + escapeHtml(dt) + '.</td></tr></tbody></table>';
       tableWrap.innerHTML = renderDailyHeader() + tbody;
       return;
     }
     tbody = '<tbody id="dtrRows">';
     for (const r of rows) {
       const name = ((r.first_name||'') + ' ' + (r.last_name||'')).trim() || 'N/A';
       tbody += '<tr data-search="'+escapeHtml((name+' '+(r.school||'')+' '+(r.course||'')+' '+(r.office||'')).toLowerCase())+'">'
           // date column now formatted as MM-DD-YYYY
           + '<td>' + escapeHtml(formatDateToMMDDYYYY(r.log_date || '')) + '</td>'
           + '<td>' + escapeHtml(name) + '</td>'
           + '<td>' + escapeHtml(r.school||'') + '</td>'
           + '<td>' + escapeHtml(r.course||'') + '</td>'
           + '<td class="center">' + escapeHtml(r.am_in||'') + '</td>'
           + '<td class="center">' + escapeHtml(r.am_out||'') + '</td>'
           + '<td class="center">' + escapeHtml(r.pm_in||'') + '</td>'
           + '<td class="center">' + escapeHtml(r.pm_out||'') + '</td>'
           + '<td class="center">' + ((r.hours !== undefined && r.hours !== null) ? String(parseInt(r.hours)) : ((r.minutes !== undefined && r.minutes !== null) ? '0' : '')) + '</td>'
           + '<td class="center">' + (r.minutes !== undefined && r.minutes !== null ? String(parseInt(r.minutes)) : '') + '</td>'
           + '<td>' + escapeHtml(r.office||'') + '</td>'
           + '</tr>';
     }
     tbody += '</tbody></table>';
     tableWrap.innerHTML = renderDailyHeader() + tbody;
    }
 
   function filterRows(){
    const q = (search.value || '').toLowerCase().trim();
    const tbody = document.getElementById('dtrRows');
    if (!tbody) return;
    for (const tr of Array.from(tbody.children)) {
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q) === -1 ? 'none' : '';
    }
  }

  function escapeHtml(s){ return (s===null||s===undefined)?'': String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

  // format 'YYYY-MM-DD' or similar into 'MM-DD-YYYY'
  function formatDateToMMDDYYYY(v){
    if (!v) return '';
    // if already in YYYY-MM-DD
    const m = String(v).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return m[2] + '-' + m[3] + '-' + m[1];
    // fallback: try Date parse and format
    const d = new Date(v);
    if (isNaN(d.getTime())) return String(v);
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const dd = String(d.getDate()).padStart(2,'0');
    const yyyy = d.getFullYear();
    return mm + '-' + dd + '-' + yyyy;
  }

  // initial load: render headers and load daily data only
  loadForDate();
})();
</script>

<script>
  // attach confirm to top logout like hr_head_ojts.php
  (function(){
    const logoutBtn = document.getElementById('top-logout');
    if (!logoutBtn) return;
    logoutBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = this.getAttribute('href');
      }
    });
  })();
</script>
</body>
</html>