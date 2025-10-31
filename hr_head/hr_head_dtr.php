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
  body{margin:0;background:#f7f8fc;display:flex;min-height:100vh}
  .sidebar{width:220px;background:#2f3850;color:#fff;display:flex;flex-direction:column;align-items:center;padding:28px 12px;gap:8px}
  .profile img{width:84px;height:84px;border-radius:50%;background:#cfd3db;margin-bottom:8px}
  .profile h3{margin:0;font-size:16px}
  .profile p{margin:0;font-size:13px;color:#bfc4d1}
  .nav{display:flex;flex-direction:column;gap:10px;width:100%;margin-top:18px}
  .nav a{display:block;padding:10px 16px;color:#fff;text-decoration:none;border-radius:22px;margin:0 8px;font-weight:600}
  .nav a.active, .nav a:hover{background:#fff;color:#2f3850}
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

    
    <nav class="nav" aria-label="Main navigation">
      <a href="hr_head_home.php">🏠 Home</a>
      <a href="hr_head_ojts.php">👥 OJTs</a>
      <a href="hr_head_dtr.php" class="active">🕒 DTR</a>
      <a href="hr_head_moa.php">🕒 MOA</a>
      <a href="hr_head_accounts.php">⚙️ Accounts</a>
      <a href="hr_head_reports.php">📊 Reports</a>
    </nav>
    <div style="margin-top:auto;font-weight:700">OJT-MS</div>
  </div>

  <main class="main" role="main">
    <div class="top-bar">
      <div>
        <h2 style="margin:0;color:#2f3850">Daily Logs</h2>
        <p style="margin:4px 0 0;color:#6d6d6d">View logs by date</p>
      </div>
      <div style="display:flex;gap:12px;align-items:center">
        <div style="background:#fff;padding:8px 12px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04)"><?=$current_date = date("F j, Y")?></div>
      </div>
    </div>

    <div class="card" role="region" aria-label="Daily Logs content">
      <div class="tabs" role="tablist" aria-label="DTR tabs">
        <button class="active" data-tab="daily">Daily Logs</button>
        <button data-tab="late">Late DTR Submissions</button>
        <button data-tab="reports">Attendance Reports</button>
      </div>

      <div id="panel-daily" class="panel" style="display:block">
        <div class="controls" style="margin-bottom:16px">
          <label for="dtrDate" style="font-weight:600">Date</label>
          <input type="date" id="dtrDate">
          <button id="btnReload" type="button">Load</button>
          <div style="flex:1"></div>
          <input type="text" id="search" placeholder="Search name / office / course" style="width:280px">
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
         + '<th class="left" rowspan="2">Date</th>'
         + '<th class="left" rowspan="2">Name</th>'
         + '<th class="left" rowspan="2">School</th>'
         + '<th class="left" rowspan="2">Course</th>'
         + '<th class="center" colspan="2">A.M.</th>'
         + '<th class="center" colspan="2">P.M.</th>'
         + '<th class="center" rowspan="2">Hours</th>'
         + '<th class="left" rowspan="2">Office</th>'
         + '</tr>'
         + '<tr>'
         + '<th class="center">Arrival</th><th class="center">Departure</th><th class="center">Arrival</th><th class="center">Departure</th>'
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