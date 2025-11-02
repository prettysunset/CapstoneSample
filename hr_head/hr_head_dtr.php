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
    .table-tabs{display:flex;gap:16px;margin-bottom:12px;border-bottom:2px solid #eee}
    .table-tabs a{padding:8px 12px;text-decoration:none;color:#555;border-radius:6px}
    .table-tabs a.active{background:#2f3850;color:#fff}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px;border:1px solid #eee;text-align:left}
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
  .tbl th,.tbl td{padding:10px;border:1px solid #eee}
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
    <div style="margin-top:auto;font-weight:700">OJT-MS</div>
  </div>
 
  <main class="main" role="main">
    <div class="top-bar">
      <div style="display:flex;flex-direction:column;justify-content:center">
      <div style="font-size:22px;color:#2f3850;font-weight:700;line-height:1"><?= date('g:i A') ?></div>
      <div style="color:#6d6d6d;margin-top:4px"><?= date('F j, Y') ?></div>
      </div>
            </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px;">
      <div class="tabs" role="tablist" aria-label="DTR Tabs"
         style="display:flex;justify-content:center;align-items:flex-end;gap:24px;font-size:18px;border-bottom:2px solid #eee;padding-bottom:12px;position:relative;">
      <button class="tab active" data-tab="daily" role="tab" aria-selected="true" aria-controls="panel-daily"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
        Daily Logs
      </button>
      <button class="tab" data-tab="late" role="tab" aria-selected="false" aria-controls="panel-late"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
        Late DTR Submissions
      </button>
      <button class="tab" data-tab="reports" role="tab" aria-selected="false" aria-controls="panel-reports"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
        Attendance Reports
      </button>

      <!-- underline indicating the selected tab -->
      <div class="tab-underline" aria-hidden="true"
         style="position:absolute;bottom:0;height:3px;background:#2f3850;border-radius:3px;transition:left .18s ease,width .18s ease;left:0;width:0;"></div>
      </div>
    </div>

  <script>
  (function(){
    const tabsEl = document.querySelector('.tabs');
    if (!tabsEl) return;
    const underline = tabsEl.querySelector('.tab-underline');
    const tabs = Array.from(tabsEl.querySelectorAll('.tab'));

    function updateUnderline(){
    const active = tabsEl.querySelector('.tab.active') || tabs[0];
    if(!active || !underline) return;
    const parentRect = tabsEl.getBoundingClientRect();
    const rect = active.getBoundingClientRect();
    underline.style.left = (rect.left - parentRect.left) + 'px';
    underline.style.width = rect.width + 'px';
    }

    // ensure underline positions correctly on init, resize and load
    updateUnderline();
    window.addEventListener('resize', updateUnderline);
    window.addEventListener('load', updateUnderline);

    // keep aria-selected and active class in sync and update underline when tabs clicked
    tabs.forEach(t=>{
    t.addEventListener('click', () => {
      tabs.forEach(x => { x.classList.remove('active'); x.setAttribute('aria-selected','false'); });
      t.classList.add('active');
      t.setAttribute('aria-selected','true');
      updateUnderline();
    });
    });
  })();
  </script>

      <div id="panel-daily" class="panel" style="display:block">
        <div class="controls" style="margin-bottom:16px">
          <label for="dtrDate" style="font-weight:600">Date</label>
          <input type="date" id="dtrDate">
          <button id="btnReload" type="button" title="Load" aria-label="Load" style="border:none;background:#e6f2ff;color:#black;padding:8px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:inherit">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.13-3.36L23 10"></path>
              <path d="M20.49 15a9 9 0 0 1-14.13 3.36L1 14"></path>
            </svg>
          </button>
          <div style="flex:1"></div>
          <div style="position:relative;display:inline-block">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#9aa4b2;width:16px;height:16px">
              <circle cx="11" cy="11" r="6"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="search" placeholder="Search name / office / course"
                   style="width:280px;padding:10px 10px 10px 36px;border:1px solid #ddd;border-radius:8px">
          </div>
        </div>

        <div id="tableWrap">
          <!-- table will always render headers via JS -->
          <div class="empty">Loading...</div>
        </div>
      </div>

      <div id="panel-late" class="panel" style="display:none">
        <div class="controls" style="margin-bottom:16px">
          <label for="lateDate">Date</label>
          <input type="date" id="lateDate">
          <button id="lateLoad">Load</button>
          <div style="flex:1"></div>
          <input type="text" id="lateSearch" placeholder="Search" style="width:220px">
        </div>
        <div id="lateWrap">
          <div class="empty">Loading...</div>
        </div>
      </div>

      <div id="panel-reports" class="panel" style="display:none">
        <div class="controls" style="margin-bottom:16px">
          <label for="repDate">Filter</label>
          <input type="date" id="repDate">
          <div style="flex:1"></div>
          <input type="text" id="repSearch" placeholder="Search name / office" style="width:220px">
        </div>
        <div id="reportsWrap">
          <div class="empty">Loading...</div>
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
      document.querySelectorAll('.panel').forEach(p=>p.style.display = p.id === 'panel-'+t ? 'block' : 'none');
    });
  });

  // controls + elements
  const dateInput = document.getElementById('dtrDate');
  const btn = document.getElementById('btnReload');
  const tableWrap = document.getElementById('tableWrap');
  const search = document.getElementById('search');

  const lateDate = document.getElementById('lateDate');
  const lateLoad = document.getElementById('lateLoad');
  const lateWrap = document.getElementById('lateWrap');
  const lateSearch = document.getElementById('lateSearch');

  const repDate = document.getElementById('repDate');
  const reportsWrap = document.getElementById('reportsWrap');
  const repSearch = document.getElementById('repSearch');

  const today = new Date().toISOString().slice(0,10);
  dateInput.value = today;
  lateDate.value = today;
  repDate.value = today;

  btn.addEventListener('click', loadForDate);
  dateInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter') loadForDate(); });
  search.addEventListener('input', filterRows);

  lateLoad.addEventListener('click', ()=>fetchLate(lateDate.value));
  lateDate.addEventListener('keydown', (e)=>{ if(e.key==='Enter') fetchLate(lateDate.value); });
  lateSearch.addEventListener('input', function(e){
    const q = (e.target.value||'').toLowerCase().trim();
    const rows = lateWrap.querySelectorAll('tr[data-search]');
    rows.forEach(r=> r.style.display = (r.getAttribute('data-search')||'').indexOf(q)===-1 ? 'none' : '');
  });

  repDate.addEventListener('change', renderReports);
  repSearch.addEventListener('input', function(){
    const q = (repSearch.value||'').toLowerCase().trim();
    const rows = reportsWrap.querySelectorAll('tr[data-search]');
    rows.forEach(r=> r.style.display = (r.getAttribute('data-search')||'').indexOf(q)===-1 ? 'none' : '');
  });

  // DAILY: always render headers; fill rows if backend returns data
  async function loadForDate(){
    const dt = dateInput.value || today;
    tableWrap.innerHTML = renderDailyHeader() + '<tbody id="dtrRows"><tr><td colspan="10" style="text-align:center;padding:18px;color:#777">Loading…</td></tr></tbody></table>';
    try {
      const res = await fetch('../hr_actions.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_dtr_by_date', date: dt })
      });
      const json = await res.json();
      const rows = (json && json.success) ? (json.rows || []) : [];
      renderDailyRows(rows, dt);
    } catch (err) {
      // backend not available or failed — show empty table with message
      renderDailyRows([], dt);
    }
  }

  function renderDailyHeader(){
    return '<table class="tbl"><thead>'
       + '<tr>'
       + '<th class="left" rowspan="2" style="background:#eceff3">Date</th>'
       + '<th class="left" rowspan="2" style="background:#eceff3">Name</th>'
       + '<th class="left" rowspan="2" style="background:#eceff3">School</th>'
       + '<th class="left" rowspan="2" style="background:#eceff3">Course</th>'
       + '<th class="center" colspan="2" style="background:#eceff3">A.M.</th>'
       + '<th class="center" colspan="2" style="background:#eceff3">P.M.</th>'
       + '<th class="center" rowspan="2" style="background:#eceff3">Hours</th>'
       + '<th class="left" rowspan="2" style="background:#eceff3">Office</th>'
       + '</tr>'
       + '<tr>'
       + '<th class="center" style="background:#eceff3">Arrival</th><th class="center" style="background:#eceff3">Departure</th><th class="center" style="background:#eceff3">Arrival</th><th class="center" style="background:#eceff3">Departure</th>'
       + '</tr>'
       + '</thead>';
    }

  function renderDailyRows(rows, dt){
    let html = '';
    if (!rows || rows.length === 0) {
      html += '<tbody id="dtrRows"><tr><td colspan="10" style="text-align:center;padding:20px;color:#777">No logs for ' + escapeHtml(dt) + '.</td></tr></tbody></table>';
    } else {
      html += '<tbody id="dtrRows">';
      for (const r of rows) {
        const name = ((r.first_name||'') + ' ' + (r.last_name||'')).trim() || 'N/A';
        html += '<tr data-search="'+escapeHtml((name+' '+(r.school||'')+' '+(r.course||'')+' '+(r.office||'')).toLowerCase())+'">'
             + '<td>' + (r.log_date || '') + '</td>'
             + '<td>' + escapeHtml(name) + '</td>'
             + '<td>' + escapeHtml(r.school||'') + '</td>'
             + '<td>' + escapeHtml(r.course||'') + '</td>'
             + '<td class="center">' + escapeHtml(r.am_in||'') + '</td>'
             + '<td class="center">' + escapeHtml(r.am_out||'') + '</td>'
             + '<td class="center">' + escapeHtml(r.pm_in||'') + '</td>'
             + '<td class="center">' + escapeHtml(r.pm_out||'') + '</td>'
             + '<td class="center">' + (parseInt(r.hours||0) + (parseInt(r.minutes||0) ? 'h '+(r.minutes||0)+'m' : '')) + '</td>'
             + '<td>' + escapeHtml(r.office||'') + '</td>'
             + '</tr>';
      }
      html += '</tbody></table>';
    }
    tableWrap.innerHTML = renderDailyHeader() + html.replace(/^<tbody.*<\/tbody><\/table>$/,'').slice(renderDailyHeader().length) ? (html) : html;
    // simpler assign: ensure full table set
    if (html.indexOf('<table') === -1) {
      tableWrap.innerHTML = renderDailyHeader() + html;
    } else {
      tableWrap.innerHTML = html;
    }
  }

  // LATE: always render headers
  function renderLateHeader(){
    return '<table class="tbl"><thead>'
         + '<tr>'
         + '<th class="left" rowspan="2">Date Filed</th>'
         + '<th class="left" rowspan="2">NAME</th>'
         + '<th class="left" rowspan="2">School</th>'
         + '<th class="left" rowspan="2">Course</th>'
         + '<th class="center" colspan="2">A.M.</th>'
         + '<th class="center" colspan="2">P.M.</th>'
         + '<th class="center" rowspan="2">HOURS</th>'
         + '<th class="left" rowspan="2">Date</th>'
         + '<th class="left" rowspan="2">OFFICE</th>'
         + '<th class="left" rowspan="2">STATUS</th>'
         + '</tr>'
         + '<tr>'
         + '<th class="center">ARRIVAL</th><th class="center">DEPARTURE</th><th class="center">ARRIVAL</th><th class="center">DEPARTURE</th>'
         + '</tr>'
         + '</thead>';
  }

  function fetchLate(d){
    const dt = d || today;
    lateWrap.innerHTML = renderLateHeader() + '<tbody><tr><td colspan="12" style="text-align:center;padding:18px;color:#777">Loading…</td></tr></tbody></table>';
    fetch('../office_head/office_head_action.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'get_late_dtr', office_id:0, date:dt })
    }).then(r=>r.json()).then(j=>{
      let html = '';
      if (!j || !j.success || !j.data || j.data.length === 0) {
        html = '<tbody><tr><td colspan="12" style="text-align:center;padding:20px;color:#777">No records for selected date.</td></tr></tbody></table>';
        lateWrap.innerHTML = renderLateHeader() + html;
        return;
      }
      html = '<tbody>';
      j.data.forEach(r=>{
        const name = ((r.first_name||'')+' '+(r.last_name||'')).trim() || 'N/A';
        html += '<tr data-search="'+escapeHtml((name+' '+(r.school||'')+' '+(r.course||'')+' '+(r.office||'')).toLowerCase())+'">'
             + '<td>' + escapeHtml(r.date_filed || r.date || '') + '</td>'
             + '<td>' + escapeHtml(name) + '</td>'
             + '<td>' + escapeHtml(r.school || '') + '</td>'
             + '<td>' + escapeHtml(r.course || '') + '</td>'
             + '<td class="center">' + escapeHtml(r.am_in || '') + '</td>'
             + '<td class="center">' + escapeHtml(r.am_out || '') + '</td>'
             + '<td class="center">' + escapeHtml(r.pm_in || '') + '</td>'
             + '<td class="center">' + escapeHtml(r.pm_out || '') + '</td>'
             + '<td class="center">' + (r.hours!==null && r.hours!==undefined ? String(r.hours) : '') + '</td>'
             + '<td>' + escapeHtml(r.late_date || r.log_date || '') + '</td>'
             + '<td>' + escapeHtml(r.office || '') + '</td>'
             + '<td>' + escapeHtml(r.status || '') + '</td>'
             + '</tr>';
      });
      html += '</tbody></table>';
      lateWrap.innerHTML = renderLateHeader() + html;
    }).catch(err=>{
      lateWrap.innerHTML = renderLateHeader() + '<tbody><tr><td colspan="12" style="text-align:center;padding:20px;color:#777">No records for selected date.</td></tr></tbody></table>';
    });
  }

  // REPORTS: always show headers; attempt fetch, else show "No records"
  function renderReportsHeader(){
    return '<table class="tbl"><thead><tr>'
         + '<th class="left">NAME</th>'
         + '<th class="left">School</th>'
         + '<th class="left">Course</th>'
         + '<th class="left">OFFICE</th>'
         + '<th class="center">HOURS RENDERED</th>'
         + '<th class="center">TOTAL DAYS</th>'
         + '<th class="left">EXPECTED END DATE</th>'
         + '</tr></thead>';
  }

  async function renderReports(){
    const dt = repDate.value || today;
    reportsWrap.innerHTML = renderReportsHeader() + '<tbody><tr><td colspan="7" style="text-align:center;padding:18px;color:#777">Loading…</td></tr></tbody></table>';
    // try backend; if not available show empty header + message
    try {
      const res = await fetch('../hr_actions.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_attendance_report', date: dt })
      });
      const j = await res.json();
      if (!j || !j.success || !j.rows || j.rows.length === 0) {
        reportsWrap.innerHTML = renderReportsHeader() + '<tbody><tr><td colspan="7" style="text-align:center;padding:18px;color:#777">No records.</td></tr></tbody></table>';
        return;
      }
      let html = '<tbody>';
      j.rows.forEach(r=>{
        const name = ((r.first_name||'')+' '+(r.last_name||'')).trim() || 'N/A';
        html += '<tr data-search="'+escapeHtml((name+' '+(r.office||'')+' '+(r.course||'')).toLowerCase())+'">'
             + '<td>' + escapeHtml(name) + '</td>'
             + '<td>' + escapeHtml(r.school||'') + '</td>'
             + '<td>' + escapeHtml(r.course||'') + '</td>'
             + '<td>' + escapeHtml(r.office||'') + '</td>'
             + '<td class="center">' + (r.hours_rendered !== undefined ? String(r.hours_rendered) : '') + '</td>'
             + '<td class="center">' + (r.total_days !== undefined ? String(r.total_days) : '') + '</td>'
             + '<td>' + escapeHtml(r.expected_end_date || '') + '</td>'
             + '</tr>';
      });
      html += '</tbody></table>';
      reportsWrap.innerHTML = renderReportsHeader() + html;
    } catch (err) {
      reportsWrap.innerHTML = renderReportsHeader() + '<tbody><tr><td colspan="7" style="text-align:center;padding:18px;color:#777">No records.</td></tr></tbody></table>';
    }
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

  // initial load: render headers and attempt to fetch live data; fall back to empty rows if endpoint missing
  loadForDate();
  fetchLate(today);
  renderReports();
})();
</script>
</body>
</html>