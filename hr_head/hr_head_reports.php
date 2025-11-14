<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i",$uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// --- NEW: datetime for top-right (match MOA/DTR layout) ---
$current_time = date("g:i A");
$current_date = date("l, F j, Y");

function fetch_students($conn){
    $q = "
    SELECT s.student_id, s.first_name, s.last_name, s.college, s.course,
           s.hours_rendered, s.total_hours_required, s.status AS student_status,
           u.office_name AS office_name,
           oa.remarks AS app_remarks, oa.date_submitted
    FROM students s
    LEFT JOIN users u ON u.user_id = s.user_id
    LEFT JOIN (
        SELECT oa1.*
        FROM ojt_applications oa1
        JOIN (
            SELECT student_id, MAX(date_submitted) AS max_date
            FROM ojt_applications
            WHERE status = 'approved'
            GROUP BY student_id
        ) mx ON oa1.student_id = mx.student_id AND oa1.date_submitted = mx.max_date
        WHERE oa1.status = 'approved'
    ) oa ON oa.student_id = s.student_id
    ORDER BY s.last_name, s.first_name
    ";
    $res = $conn->query($q);
    $rows = [];
    if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
    return $rows;
}

function fetch_offices($conn){
    $rows = [];
    $res = $conn->query("SELECT office_id, office_name, current_limit, requested_limit, reason, status FROM offices ORDER BY office_name");
    if ($res) {
        $stmtCount = $conn->prepare("
            SELECT COUNT(DISTINCT oa.student_id) AS filled FROM ojt_applications oa
            WHERE (oa.office_preference1 = ? OR oa.office_preference2 = ?) AND oa.status = 'approved'
        ");
        while ($r = $res->fetch_assoc()){
            $id = (int)$r['office_id'];
            $stmtCount->bind_param("ii",$id,$id);
            $stmtCount->execute();
            $cnt = $stmtCount->get_result()->fetch_assoc();
            $filled = (int)($cnt['filled'] ?? 0);
            $cap = is_null($r['current_limit']) ? null : (int)$r['current_limit'];
            $available = is_null($cap) ? '—' : max(0, $cap - $filled);
            $rows[] = array_merge($r, ['filled'=>$filled, 'available'=>$available]);
        }
        $stmtCount->close();
        $res->free();
    }
    return $rows;
}

function fetch_moa($conn){
    $rows = [];
    $sql = "
      SELECT m.moa_id, m.school_name, m.moa_file, m.date_uploaded, m.validity_months,
             (SELECT COUNT(*) FROM students s WHERE LOWER(TRIM(s.college)) = LOWER(TRIM(m.school_name))) AS student_count
      FROM moa m
      ORDER BY m.date_uploaded DESC
    ";
    $res = $conn->query($sql);
    if ($res){
        while ($r = $res->fetch_assoc()) {
            $r['student_count'] = (int)($r['student_count'] ?? 0);
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

function fmtDate($d){ if (!$d) return '-'; $dt = date_create($d); return $dt ? $dt->format('M j, Y') : '-'; }

$students = fetch_students($conn);
$offices = fetch_offices($conn);
$moa = fetch_moa($conn);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - Reports</title>
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
    /* ensure consistency with Accounts page: white card background and deeper shadow */
    .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
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
      <a href="hr_head_accounts.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
        Accounts
      </a>
      <a href="#" class="active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <rect x="3" y="10" width="4" height="10"></rect>
          <rect x="10" y="6" width="4" height="14"></rect>
          <rect x="17" y="2" width="4" height="18"></rect>
        </svg>
        Reports
      </a>
      </div>
    <p style="margin-top:auto;font-weight:600">OJT-MS</p>
</div>
 

  <main class="main" role="main">
    <!-- top-right outline icons: notifications, calendar, settings, logout
         NOTE: same markup/placement as hr_head_accounts.php so icons align across pages -->
    <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </a>
        <!-- calendar icon (display only) - placed to the right of Notifications to match DTR -->
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

    <!-- datetime block - placed exactly like hr_head_accounts.php (right under icons) -->
    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?= htmlspecialchars($current_time) ?></h2>
                <p><?= htmlspecialchars($current_date) ?></p>
            </div>
        </div>
    </div>

    <div class="card" role="region" aria-label="Reports">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h2 style="margin:0;color:#2f3850">Reports</h2>
        <div style="display:flex;gap:12px;align-items:center">
          <div id="globalSearchWrap" style="position:relative">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#6d6d6d;pointer-events:none">
              <circle cx="11" cy="11" r="7"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="globalSearch" placeholder="Search" aria-label="Search" style="width:320px;padding:8px 12px 8px 36px;border-radius:8px;border:1px solid #ddd;background:#fff;outline:none">
          </div>
          <button id="exportBtn" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#3a4163;color:#fff;cursor:pointer">Export</button>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="tabs" role="tablist" aria-label="OJT Tabs" style="display:flex;justify-content:center;align-items:flex-end;gap:24px;font-size:18px;border-bottom:2px solid #eee;padding-bottom:12px;position:relative;">
          <button class="tab active" data-tab="students" role="tab" aria-selected="true" aria-controls="panel-students" style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
        Students (<?= count($students) ?>)
          </button>
          <button class="tab" data-tab="offices" role="tab" aria-selected="false" aria-controls="panel-offices" style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
        Offices (<?= count($offices) ?>)
          </button>
          <button class="tab" data-tab="moa" role="tab" aria-selected="false" aria-controls="panel-moa" style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
        MOA (<?= count($moa) ?>)
          </button>

          <!-- underline indicating the selected tab -->
          <div class="tab-underline" aria-hidden="true" style="position:absolute;bottom:0;height:3px;background:#2f3850;border-radius:3px;transition:left .18s ease,width .18s ease;left:0;width:0;"></div>
        </div>
      </div>

    <script>
    (function(){
      // helper to position the underline under the active tab
      function updateTabUnderline(){
        const tabs = document.querySelector('.tabs');
        if (!tabs) return;
        const active = tabs.querySelector('button.active');
        const line = tabs.querySelector('.tab-underline');
        if (!active || !line) return;
        const aRect = active.getBoundingClientRect();
        const tRect = tabs.getBoundingClientRect();
        line.style.left = (aRect.left - tRect.left) + 'px';
        line.style.width = aRect.width + 'px';
      }
      // initialize and bind events
      window.addEventListener('load', updateTabUnderline);
      window.addEventListener('resize', updateTabUnderline);
      document.querySelectorAll('.tabs button').forEach(btn=>{
        btn.addEventListener('click', function(){
      // small delay to allow other click handlers (existing script) to toggle active class first
      setTimeout(updateTabUnderline, 30);
        });
      });
      // in case the page already has an active tab
      setTimeout(updateTabUnderline,50);
    })();
    </script>

      <div id="panel-students" class="panel" style="display:block">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblStudents">
            <thead>
                <tr style="background:#2f3850;color:#black;font-weight:600">
                <th>Name</th><th>Office</th><th>School</th><th>Course</th><th>Start Date</th><th>End Date</th><th style="text-align:center">Hours Rendered</th><th style="text-align:center">Required Hours</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
              <?php if (empty($students)): ?>
                <tr><td colspan="9" class="empty">No students found.</td></tr>
              <?php else: foreach ($students as $s):
                $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                $office = $s['office_name'] ?: '-';
                $school = $s['college'] ?: '-';
                $course = $s['course'] ?: '-';
                $hours = (int)($s['hours_rendered'] ?? 0);
                $req = (int)($s['total_hours_required'] ?? 0);
                // try extract dates from remarks (Orientation/Start: YYYY-MM-DD | Assigned Office: ...)
                $start = $end = '';
                if (!empty($s['app_remarks']) && preg_match('/Orientation\/Start\s*:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i',$s['app_remarks'],$m)) $start = $m[1];
                if (!empty($s['app_remarks']) && preg_match('/(End Date|Expected End Date)\s*:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i',$s['app_remarks'],$m2)) $end = $m2[2];
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name.' '.$office.' '.$school.' '.$course)) ?>">
                  <td><?= htmlspecialchars($name ?: 'N/A') ?></td>
                  <td><?= htmlspecialchars($office) ?></td>
                  <td><?= htmlspecialchars($school) ?></td>
                  <td><?= htmlspecialchars($course) ?></td>
                  <td><?= htmlspecialchars($start ? fmtDate($start) : '-') ?></td>
                  <td><?= htmlspecialchars($end ? fmtDate($end) : '-') ?></td>
                  <td style="text-align:center"><?= $hours ?></td>
                  <td style="text-align:center"><?= $req ?></td>
                  <td><?= htmlspecialchars(ucfirst($s['student_status'] ?: '')) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-offices" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblOffices">
            <thead>
              <tr><th>Office</th><th style="text-align:center">Capacity</th><th style="text-align:center">Active OJTs</th><th style="text-align:center">Available</th><th>Requested Limit</th><th>Reason</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($offices)): ?>
                <tr><td colspan="7" class="empty">No offices found.</td></tr>
              <?php else: foreach ($offices as $o): ?>
                <tr data-search="<?= htmlspecialchars(strtolower($o['office_name'])) ?>">
                  <td><?= htmlspecialchars($o['office_name']) ?></td>
                  <td style="text-align:center"><?= is_null($o['current_limit']) ? '—' : (int)$o['current_limit'] ?></td>
                  <td style="text-align:center"><?= (int)$o['filled'] ?></td>
                  <td style="text-align:center"><?= htmlspecialchars((string)$o['available']) ?></td>
                  <td style="text-align:center"><?= $o['requested_limit'] === null ? '—' : (int)$o['requested_limit'] ?></td>
                  <td><?= htmlspecialchars($o['reason'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($o['status'] ?: '—') ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-moa" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblMoa">
            <thead>
              <tr><th>School</th><th style="text-align:center">Students</th><th>MOA File</th><th>Uploaded</th><th>Validity (months)</th></tr>
            </thead>
            <tbody>
              <?php if (empty($moa)): ?>
                <tr><td colspan="4" class="empty">No MOA records.</td></tr>
              <?php else: foreach ($moa as $m): ?>
                <tr data-search="<?= htmlspecialchars(strtolower($m['school_name'])) ?>">
                  <td><?= htmlspecialchars($m['school_name']) ?></td>
                  <td style="text-align:center"><?= (int)($m['student_count'] ?? 0) ?></td>
                  <td>
                    <?php if (!empty($m['moa_file'])): ?>
                      <a href="<?= htmlspecialchars('../' . $m['moa_file']) ?>" target="_blank">View</a>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($m['date_uploaded'] ? date('M j, Y', strtotime($m['date_uploaded'])) : '-') ?></td>
                  <td style="text-align:center"><?= (int)($m['validity_months'] ?? 0) ?></td>
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
      const tab = this.getAttribute('data-tab');
      document.getElementById('panel-students').style.display = tab==='students' ? 'block' : 'none';
      document.getElementById('panel-offices').style.display = tab==='offices' ? 'block' : 'none';
      document.getElementById('panel-moa').style.display = tab==='moa' ? 'block' : 'none';
      document.getElementById('globalSearch').value = '';
    });
  });

  // global search applies to visible panel
  document.getElementById('globalSearch').addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    const visible = document.querySelector('.panel[style*="display:block"]');
    if (!visible) return;
    visible.querySelectorAll('tbody tr[data-search]').forEach(tr=>{
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q)===-1 ? 'none' : '';
    });
  });

  // export visible table to CSV
  document.getElementById('exportBtn').addEventListener('click', function(){
    const visiblePanel = document.querySelector('.panel[style*="display:block"]');
    if (!visiblePanel) return alert('No data to export.');
    const rows = Array.from(visiblePanel.querySelectorAll('tbody tr')).filter(r=>r.style.display!=='none');
    if (rows.length === 0) return alert('No rows to export.');
    const cols = Array.from(visiblePanel.querySelectorAll('thead th')).map(th=>th.textContent.trim());
    const data = [cols.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')];
    rows.forEach(tr=>{
      const cells = Array.from(tr.querySelectorAll('td')).map(td => '"' + td.textContent.replace(/"/g,'""').trim() + '"');
      data.push(cells.join(','));
    });
    const csv = data.join('\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'reports_export.csv'; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  });
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