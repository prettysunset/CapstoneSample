<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$su = $conn->prepare("SELECT first_name,last_name,office_name FROM users WHERE user_id=? LIMIT 1");
$su->bind_param('i',$user_id);
$su->execute();
$u=$su->get_result()->fetch_assoc();
$su->close();

$display_name = trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: 'Office Head';
$office_name = $u['office_name'] ?? '';
$office_display = preg_replace('/\s+Office\s*$/i','',$office_name ?: 'Unknown Office');

// AJAX handler (responds to POST JSON { action: 'get_daily_logs', start_date, end_date })
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if (($input['action'] ?? '') === 'get_daily_logs') {
        header('Content-Type: application/json; charset=utf-8');
        $start = $input['start_date'] ?? $input['date'] ?? '';
        $end   = $input['end_date'] ?? $start;
        if (!$start) {
            echo json_encode(['success'=>false,'error'=>'Missing start date']);
            exit;
        }
        $start = date('Y-m-d', strtotime($start));
        $end   = date('Y-m-d', strtotime($end));

        // Query: dtr.student_id -> users.user_id, join students ON students.user_id = users.user_id to get student details
        $sql = "
          SELECT d.dtr_id,
                 d.log_date,
                 d.am_in,
                 d.am_out,
                 d.pm_in,
                 d.pm_out,
                 d.hours,
                 d.minutes,
                 u.user_id,
                 COALESCE(s.first_name, u.first_name, '') AS first_name,
                 COALESCE(s.last_name,  u.last_name,  '') AS last_name,
                 COALESCE(s.college,'') AS school,
                 COALESCE(s.course,'') AS course
          FROM dtr d
          JOIN users u ON u.user_id = d.student_id
          LEFT JOIN students s ON s.user_id = u.user_id
          WHERE u.role = 'ojt'
            AND u.office_name LIKE ?
            AND d.log_date BETWEEN ? AND ?
          ORDER BY d.log_date DESC, COALESCE(s.last_name,u.last_name), COALESCE(s.first_name,u.first_name)
        ";
        $like = '%' . $office_name . '%';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success'=>false,'error'=>$conn->error]);
            exit;
        }
        $stmt->bind_param('sss', $like, $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];

        // helper: compute minutes between two times on given date (returns 0 if invalid)
        $minutes_between = function($date, $t1, $t2){
            $t1 = trim((string)$t1);
            $t2 = trim((string)$t2);
            if ($t1 === '' || $t2 === '') return 0;
            $dt1 = strtotime($date . ' ' . $t1);
            $dt2 = strtotime($date . ' ' . $t2);
            if ($dt1 === false || $dt2 === false) return 0;
            $diff = $dt2 - $dt1;
            return $diff > 0 ? (int)floor($diff / 60) : 0;
        };

        while ($r = $res->fetch_assoc()) {
            // calculate total minutes from AM and PM pairs
            $date = $r['log_date'] ?? '';
            $am_min = $minutes_between($date, $r['am_in'] ?? '', $r['am_out'] ?? '');
            $pm_min = $minutes_between($date, $r['pm_in'] ?? '', $r['pm_out'] ?? '');
            $total_min = $am_min + $pm_min;

            // cap to 8 hours (480 minutes)
            if ($total_min > 480) $total_min = 480;

            $hours = intdiv($total_min, 60);
            $minutes = $total_min % 60;

            // override/normalize fields sent to client
            $r['hours'] = $hours;
            $r['minutes'] = $minutes;

            $rows[] = $r;
        }
        $stmt->close();
        echo json_encode(['success'=>true,'data'=>$rows,'start'=>$start,'end'=>$end]);
        exit;
    }
}

// defaults: last 7 days
$default_end = date('Y-m-d');
$default_start = date('Y-m-d', strtotime('-6 days', strtotime($default_end)));
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Office Head — DTR</title>
<link rel="stylesheet" href="../ojts/stylesforojt.css">
<style>
  body{font-family:Poppins, sans-serif;margin:0;background:#f5f6fa}
  /* allow .main to use full available horizontal space (override external limits) */
  .main{
    margin-left:240px;
    padding:20px;
    width:auto;
    max-width:none;
    box-sizing:border-box;
  }
  /* make .card span the available workspace (viewport minus sidebar + padding) */
  .card{
    background:#fff;
    border-radius:12px;
    padding:18px;
    box-shadow:0 6px 20px rgba(0,0,0,0.05);
    width: calc(100vw - 240px - 40px); /* viewport width minus sidebar (240) and main horizontal padding (20*2) */
    max-width:none;
    box-sizing:border-box;
    margin:0;
  }
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  .btn{padding:10px 14px;border-radius:20px;border:0;background:#4f4aa6;color:#fff;cursor:pointer}
  .tabs{display:flex;gap:24px;border-bottom:2px solid #eee;padding-bottom:12px;margin-bottom:16px;position:relative}
  .tabs .tab{background:transparent;border:none;padding:10px 12px;border-radius:6px;cursor:pointer;font-weight:600;color:#2f3850}
  .tabs .tab.active{color:#2f3850}
  /* tab underline removed (no purple line) */
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px;text-align:left;border-bottom:1px solid #eef1f6;font-size:14px}
  thead th{background:#f5f7fb;color:#2f3459}
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} }

  /* make top-icons full width of .main so icons align to the same right edge as home */
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

  /* table small adjustments used by render */
  .center{text-align:center}
  input[type="date"]{padding:8px;border:1px solid #e6e9f2;border-radius:8px}
  .small-note{color:#6b7180;font-size:13px}
</style>
</head>
<body>
<?php
$user_name = $display_name ?? 'Office Head';
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
    <a href="office_head_home.php" title="Home" style="display:flex;align-items:center;gap:8px;color:#fff;background:transparent;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 11.5L12 4l9 7.5"></path>
        <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
      </svg>
      <span>Home</span>
    </a>

    <a href="office_head_ojts.php" title="OJTs" style="display:flex;align-items:center;gap:8px;color:#fff;background:transparent;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="3"></circle>
        <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
      </svg>
      <span>OJTs</span>
    </a>

    <a href="office_head_dtr.php" class="active" title="DTR" style="display:flex;align-items:center;gap:8px;color:#2f3859;background:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2"></rect>
      <line x1="16" y1="2" x2="16" y2="6"></line>
      <line x1="8" y1="2" x2="8" y2="6"></line>
      <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>DTR</span>
    </a>

    <a href="office_head_reports.php" title="Reports" style="display:flex;align-items:center;gap:8px;color:#fff;background:transparent;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="10" width="4" height="10"></rect>
        <rect x="10" y="6" width="4" height="14"></rect>
        <rect x="17" y="2" width="4" height="18"></rect>
      </svg>
      <span>Reports</span>
    </a>

  </nav>
</div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout -->
  <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
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
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div style="display:flex;gap:12px;align-items:center">
      </div>
    </div>

    <div class="tabs" role="tablist" aria-label="DTR Tabs">
      <div class="tab active" data-tab="daily" aria-selected="true" style="font-weight:600;color:#2f3850">Daily Logs</div>
    </div>

    <div id="panel-daily" class="panel" style="display:block">
      <div class="controls" style="margin-bottom:8px">
        <label for="startDate" class="small-note" style="margin-right:6px">Start</label>
        <input id="startDate" type="date" value="<?= htmlspecialchars($default_start) ?>" />
        <label for="endDate" class="small-note" style="margin-left:8px;margin-right:6px">End</label>
        <input id="endDate" type="date" value="<?= htmlspecialchars($default_end) ?>" />

        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <input id="searchDaily" type="text" placeholder="Search name / school / course" style="padding:10px;border-radius:8px;border:1px solid #ddd" />
        </div>
      </div>
      <div style="overflow:auto">
        <table id="dailyTable">
          <thead>
            <tr>
              <th class="center">Date</th>
              <th>Name</th>
              <th>School</th>
              <th>Course</th>
              <th class="center">A.M. Arrival</th>
              <th class="center">A.M. Departure</th>
              <th class="center">P.M. Arrival</th>
              <th class="center">P.M. Departure</th>
              <th class="center">Hours</th>
              <th class="center">Minutes</th>
            </tr>
          </thead>
          <tbody id="dtrBody">
            <tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  const dtrBody = document.getElementById('dtrBody');
  const searchDaily = document.getElementById('searchDaily');
  const startDateInp = document.getElementById('startDate');
  const endDateInp = document.getElementById('endDate');

  function renderDaily(rows){
    dtrBody.innerHTML = '';
    if (!rows || rows.length === 0) {
      dtrBody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">No records found.</td></tr>';
      return;
    }
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      const name = ((r.first_name||'')+' '+(r.last_name||'')).trim();
      tr.setAttribute('data-search', ((name)+' '+(r.school||'')+' '+(r.course||'')).toLowerCase());
      tr.innerHTML = '<td class="center">'+ (r.log_date||'') +'</td>'
                   + '<td>'+ (name||'') +'</td>'
                   + '<td>'+ (r.school||'-') +'</td>'
                   + '<td>'+ (r.course||'-') +'</td>'
                   + '<td class="center">'+ (r.am_in||'-') +'</td>'
                   + '<td class="center">'+ (r.am_out||'-') +'</td>'
                   + '<td class="center">'+ (r.pm_in||'-') +'</td>'
                   + '<td class="center">'+ (r.pm_out||'-') +'</td>'
                   + '<td class="center">'+ (r.hours !== undefined ? r.hours : '-') +'</td>'
                   + '<td class="center">'+ (r.minutes !== undefined ? r.minutes : '-') +'</td>';
      dtrBody.appendChild(tr);
    });
  }

  async function fetchDailyRange(start, end){
    dtrBody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>';
    try {
      const res = await fetch('office_head_dtr.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_daily_logs', start_date: start, end_date: end })
      });
      const j = await res.json();
      if (j && j.success) renderDaily(j.data || []); else renderDaily([]);
    } catch(e){ console.error(e); renderDaily([]); }
  }

  // helper to validate dates and load automatically
  function loadIfValid() {
    const s = startDateInp.value;
    const e = endDateInp.value;
    if (!s || !e) return;
    if (s > e) {
      // show a small client-side alert and do not request
      alert('Start date must be before or equal to end date');
      return;
    }
    fetchDailyRange(s, e);
  }

  // auto-load when either date changes (no Load button)
  startDateInp.addEventListener('change', loadIfValid);
  endDateInp.addEventListener('change', loadIfValid);

  // search filter (client-side)
  searchDaily.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#dtrBody tr').forEach(r=> {
      const hay = (r.getAttribute('data-search')||'');
      r.style.display = q === '' || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });

  // initial fetch using defaults rendered by PHP
  (function init(){
    const s = startDateInp.value;
    const e = endDateInp.value;
    if (s && e) fetchDailyRange(s, e);
  })();

  // confirm logout (top-right)
  (function(){
    const logout = document.getElementById('btnLogout');
    if (!logout) return;
    logout.addEventListener('click', function(e){
      if (!confirm('Are you sure you want to logout?')) e.preventDefault();
    });
  })();

})();
</script>
</body>
</html>