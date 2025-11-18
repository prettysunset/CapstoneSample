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
           s.hours_rendered, s.total_hours_required,
           -- STATUS logic changed: prefer users.status; if missing use latest application status; fallback to students.status
           COALESCE(NULLIF(u.status,''), NULLIF(oa.status,''), s.status) AS student_status,
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
            GROUP BY student_id
        ) mx ON oa1.student_id = mx.student_id AND oa1.date_submitted = mx.max_date
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
        // prepare once: count ojts by status from users table (role = 'ojt')
        $stmtUser = $conn->prepare("
            SELECT 
              SUM(status = 'approved') AS approved_count,
              SUM(status = 'ongoing')  AS ongoing_count,
              SUM(status = 'completed') AS completed_count
            FROM users
            WHERE role = 'ojt' AND office_name = ?
        ");
        while ($r = $res->fetch_assoc()){
            $officeName = $r['office_name'] ?? '';
            $stmtUser->bind_param("s", $officeName);
            $stmtUser->execute();
            $cnt = $stmtUser->get_result()->fetch_assoc() ?: [];
            $approved = (int)($cnt['approved_count'] ?? 0);
            $ongoing  = (int)($cnt['ongoing_count'] ?? 0);
            $completed = (int)($cnt['completed_count'] ?? 0);

            $cap = is_null($r['current_limit']) ? null : (int)$r['current_limit'];
            // Available slot = current_limit - (ongoing + approved)
            $available = is_null($cap) ? '—' : max(0, $cap - ($ongoing + $approved));

            $rows[] = array_merge($r, [
                'capacity' => $cap,
                'available' => $available,
                'approved' => $approved,
                'ongoing' => $ongoing,
                'completed' => $completed
            ]);
        }
        $stmtUser->close();
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

// new: load office_requests
function fetch_office_requests($conn){
    // return only non-pending office requests (approved/rejected)
    $rows = [];
    $sql = "
      SELECT r.request_id, r.office_id, r.old_limit, r.new_limit, r.reason, r.status, r.date_requested, r.date_of_action,
             o.office_name
      FROM office_requests r
      LEFT JOIN offices o ON o.office_id = r.office_id
      WHERE r.status <> 'pending'
      ORDER BY r.date_requested DESC, r.request_id DESC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    return $rows;
}

function fetch_evaluations($conn){
    $rows = [];
    $sql = "
      SELECT e.eval_id, e.rating, e.feedback, e.date_evaluated, e.rating_desc,
             s.first_name AS student_first, s.last_name AS student_last,
             u.first_name AS eval_first, u.last_name AS eval_last
      FROM evaluations e
      LEFT JOIN students s ON e.student_id = s.student_id
      LEFT JOIN users u ON e.user_id = u.user_id
      ORDER BY e.date_evaluated DESC
    ";
    $res = $conn->query($sql);
    if ($res){
        while ($r = $res->fetch_assoc()) {
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
$office_requests = fetch_office_requests($conn);
$evaluations = fetch_evaluations($conn);
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

/* MOA status colors */
.moa-status.active{ color: #178a34; font-weight:600; } /* green */
.moa-status.expired{ color: #d32f2f; font-weight:600; } /* red */
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
        <!-- header: Reports left, export top-right -->
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">
          <h2 style="margin:0;color:#2f3850">Reports</h2>

          <div style="display:flex;align-items:center;gap:12px;flex:0 0 auto;">
            <button id="exportBtn" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#3a4163;color:#fff;cursor:pointer">Export</button>
          </div>
        </div>

        <!-- Tabs centered below the title (single grey line) -->
        <div style="display:flex;flex-direction:column;gap:12px;">
          <style>
            .tabs { display:flex; justify-content:center; align-items:flex-end; gap:18px; font-size:18px; border-bottom:2px solid #eee; padding-bottom:12px; }
            .tabs button { background:transparent; border:none; padding:10px 14px; border-radius:6px 6px 0 0; cursor:pointer; color:#2f3850; font-weight:600; outline:none; font-size:18px; }
            .tabs button span{ display:inline-block; padding-bottom:6px; border-bottom:3px solid transparent; transition:border-color .15s ease; }
            .tabs button.active span{ border-color: #2f3850; }
          </style>

          <div class="tabs" role="tablist" aria-label="OJT Tabs">
            <button class="tab active" data-tab="students" role="tab" aria-selected="true" aria-controls="panel-students">
              <span>Students (<?= count($students) ?>)</span>
            </button>
            <button class="tab" data-tab="offices" role="tab" aria-selected="false" aria-controls="panel-offices">
              <span>Offices (<?= count($offices) ?>)</span>
            </button>
            <button class="tab" data-tab="moa" role="tab" aria-selected="false" aria-controls="panel-moa">
              <span>MOA (<?= count($moa) ?>)</span>
            </button>
            <button class="tab" data-tab="requests" role="tab" aria-selected="false" aria-controls="panel-requests">
              <span>Office Requests (<?= count($office_requests) ?>)</span>
            </button>
            <button class="tab" data-tab="evaluations" role="tab" aria-selected="false" aria-controls="panel-evaluations">
              <span>Evaluations (<?= count($evaluations) ?>)</span>
            </button>
          </div>
        </div>

        <!-- Search bar placed under the grey tab line, aligned left -->
        <div class="card-search" style="padding-top:12px;margin-top:8px;margin-bottom:14px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:center;">
          <!-- left: search -->
          <div id="globalSearchWrap" style="position:relative;flex:1 1 320px;max-width:60%;">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#6d6d6d;pointer-events:none">
              <circle cx="11" cy="11" r="7"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="globalSearch" placeholder="Search" aria-label="Search" style="width:100%;padding:8px 12px 8px 36px;border-radius:8px;border:1px solid #ddd;background:#fff;outline:none">
          </div>

          <!-- right: Students-only filters (office & status) -->
          <div id="studentsFilters" style="display:flex;gap:8px;align-items:center;flex:0 0 auto;">
            <select id="officeFilter" style="padding:8px;border-radius:8px;border:1px solid #ddd;background:#fff;min-width:180px;">
              <option value="">All offices</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= htmlspecialchars(strtolower($o['office_name'] ?? '')) ?>"><?= htmlspecialchars($o['office_name'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>

            <select id="statusFilter" style="padding:8px;border-radius:8px;border:1px solid #ddd;background:#fff;min-width:160px;">
              <option value="">All status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="evaluated">Evaluated</option>
              <option value="rejected">Rejected</option>
              <option value="deactivated">Deactivated</option>
            </select>
          </div>

          <!-- right (offices) filters - shown only when Offices tab active -->
          <div id="officesFilters" style="display:none;gap:8px;align-items:center;flex:0 0 auto;">
            <select id="officesSortColumn" style="padding:8px;border-radius:8px;border:1px solid #ddd;background:#fff;min-width:220px;">
              <option value="">None</option>
              <option value="capacity">Capacity</option>
              <option value="available">Available Slot</option>
              <option value="approved">Approved OJTs</option>
              <option value="ongoing">Ongoing OJTs</option>
              <option value="completed">Completed OJTs</option>
            </select>
            <button id="officesSortDirBtn" data-dir="asc" title="Toggle sort direction" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer">Asc</button>
          </div>

          <!-- right (moa) filters - shown only when MOA tab active -->
          <div id="moaFilters" style="display:none;gap:8px;align-items:center;flex:0 0 auto;">
            <select id="moaStatusFilter" style="padding:8px;border-radius:8px;border:1px solid #ddd;background:#fff;min-width:160px;">
              <option value="">All status</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
            </select>
          </div>
        </div>

      <div id="panel-students" class="panel" style="display:block">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblStudents">
            <thead>
                <tr style="background:#2f3850;color:#black;font-weight:600">
                <th>Name</th><th>Office</th><th>School</th><th>Course</th>
                <th style="text-align:center">Hours Rendered</th><th style="text-align:center">Required Hours</th><th>Status</th>
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
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name.' '.$office.' '.$school.' '.$course)) ?>">
                  <td><?= htmlspecialchars($name ?: 'N/A') ?></td>
                  <td><?= htmlspecialchars($office) ?></td>
                  <td><?= htmlspecialchars($school) ?></td>
                  <td><?= htmlspecialchars($course) ?></td>
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
              <tr>
                <th>Office</th>
                <th style="text-align:center">Capacity</th>
                <th style="text-align:center">Available Slot</th>
                <th style="text-align:center">Approved OJTs</th>
                <th style="text-align:center">Ongoing OJTs</th>
                <th style="text-align:center">Completed OJTs</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($offices)): ?>
                <tr><td colspan="6" class="empty">No offices found.</td></tr>
              <?php else: foreach ($offices as $o): ?>
                <tr data-search="<?= htmlspecialchars(strtolower($o['office_name'])) ?>">
                  <td><?= htmlspecialchars($o['office_name']) ?></td>
                  <td style="text-align:center"><?= is_null($o['capacity']) ? '—' : (int)$o['capacity'] ?></td>
                  <td style="text-align:center"><?= is_string($o['available']) ? htmlspecialchars($o['available']) : (int)$o['available'] ?></td>
                  <td style="text-align:center"><?= (int)($o['approved'] ?? 0) ?></td>
                  <td style="text-align:center"><?= (int)($o['ongoing'] ?? 0) ?></td>
                  <td style="text-align:center"><?= (int)($o['completed'] ?? 0) ?></td>
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
              <tr><th>School</th><th style="text-align:center">Students</th><th>MOA File</th><th>Date Signed</th><th style="text-align:center">Valid Until</th><th style="text-align:center">Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($moa)): ?>
                <tr><td colspan="6" class="empty">No MOA records.</td></tr>
              <?php else: foreach ($moa as $m): ?>
                 <tr data-search="<?= htmlspecialchars(strtolower($m['school_name'])) ?>">
                   <td><?= htmlspecialchars($m['school_name']) ?></td>
                   <td style="text-align:center"><?= (int)($m['student_count'] ?? 0) ?></td>
                   <td>
                     <?php if (!empty($m['moa_file'])): ?>
                       <a href="<?= htmlspecialchars('../' . $m['moa_file']) ?>" target="_blank">View</a>
                     <?php else: ?>—<?php endif; ?>
                   </td>
                  <?php
                    // Date Signed = date_uploaded (formatted) and compute valid until & status
                    $date_signed = !empty($m['date_uploaded']) ? date('M j, Y', strtotime($m['date_uploaded'])) : '-';
                    $valid_until = '-';
                    $status_label = 'Expired';
                    if (!empty($m['date_uploaded']) && isset($m['validity_months'])) {
                      $months = (int)$m['validity_months'];
                      if ($months > 0) {
                        $valid_until_ts = strtotime("+{$months} months", strtotime($m['date_uploaded']));
                        $valid_until = date('M j, Y', $valid_until_ts);
                        // Active if valid_until is today or in the future
                        $today_ts = strtotime(date('Y-m-d'));
                        $status_label = ($valid_until_ts >= $today_ts) ? 'Active' : 'Expired';
                      } else {
                        $valid_until = '-';
                        $status_label = 'Expired';
                      }
                    } else {
                      // no date uploaded => treat as Expired / unknown
                      $status_label = 'Expired';
                    }
                    // css class suffix (lowercase) for client-side filtering
                    $status_class = strtolower($status_label);
                  ?>
                  <td><?= htmlspecialchars($date_signed) ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($valid_until) ?></td>
                  <td style="text-align:center"><span class="moa-status <?= htmlspecialchars($status_class) ?>"><?= htmlspecialchars($status_label) ?></span></td>
                 </tr>
               <?php endforeach; endif; ?>
             </tbody>
           </table>
         </div>
       </div>

      <!-- NEW: Office Requests panel -->
      <div id="panel-requests" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblRequests">
            <thead>
              <tr>
                <th style="text-align:center">Date Requested</th>
                <th>Office</th>
                <th style="text-align:center">New Limit</th>
                <th>Reason</th>
                <th style="text-align:center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($office_requests)): ?>
                <tr><td colspan="5" class="empty">No office requests.</td></tr>
              <?php else: foreach ($office_requests as $req): ?>
                <tr data-search="<?= htmlspecialchars(strtolower(($req['office_name'] ?? '') . ' ' . ($req['reason'] ?? '') . ' ' . ($req['status'] ?? '')) ) ?>">
                  <td style="text-align:center"><?= htmlspecialchars(fmtDate($req['date_requested'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($req['office_name'] ?? '-') ?></td>
                  <td style="text-align:center"><?= is_null($req['new_limit']) ? '—' : (int)$req['new_limit'] ?></td>
                  <td><?= htmlspecialchars($req['reason'] ?? '-') ?></td>
                  <td style="text-align:center"><?= htmlspecialchars(ucfirst($req['status'] ?? '')) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- NEW: Evaluations panel -->
      <div id="panel-evaluations" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblEvaluations">
            <thead>
              <tr>
                <th style="text-align:center">Date Evaluated</th>
                <th style="text-align:center">Student Name</th>
                <th style="text-align:center">Rating</th>
                <th style="text-align:center">Feedback</th>
                <th style="text-align:center">Evaluator</th>
                <th style="text-align:center">View</th>
                <th style="text-align:center">Print Certificate</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($evaluations)): ?>
                <tr><td colspan="7" class="empty">No evaluations found.</td></tr>
              <?php else: foreach ($evaluations as $e): ?>
                <tr data-search="<?= htmlspecialchars(strtolower(($e['student_first'] ?? '') . ' ' . ($e['student_last'] ?? '') . ' ' . ($e['eval_first'] ?? '') . ' ' . ($e['eval_last'] ?? '') . ' ' . ($e['feedback'] ?? ''))) ?>">
                  <td style="text-align:center"><?= htmlspecialchars(fmtDate($e['date_evaluated'] ?? '')) ?></td>
                  <td style="text-align:center"><?= htmlspecialchars(trim(($e['student_first'] ?? '') . ' ' . ($e['student_last'] ?? ''))) ?: 'N/A' ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($e['rating_desc'] ?? '') ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($e['feedback'] ?? '') ?></td>
                  <td style="text-align:center"><?= htmlspecialchars(trim(($e['eval_first'] ?? '') . ' ' . ($e['eval_last'] ?? ''))) ?: 'N/A' ?></td>
                  <td style="text-align:center">
                    <button class="view-btn" data-eval-id="<?= htmlspecialchars($e['eval_id'] ?? '') ?>" title="View Evaluation" style="background:none;border:none;cursor:pointer;color:#0b74de;">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </button>
                  </td>
                  <td style="text-align:center">
                    <button class="print-btn" data-eval-id="<?= htmlspecialchars($e['eval_id'] ?? '') ?>" title="Print Certificate of Completion" style="background:none;border:none;cursor:pointer;color:#0b74de;">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                      </svg>
                    </button>
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
  // helper: apply current search + filters to all panels (so search reflects immediately even when tab not active)
  function applyFilters() {
    const q = (document.getElementById('globalSearch')?.value || '').toLowerCase().trim();
    const officeVal = (document.getElementById('officeFilter')?.value || '').toLowerCase().trim();
    const statusVal = (document.getElementById('statusFilter')?.value || '').toLowerCase().trim();
    const moaStatusVal = (document.getElementById('moaStatusFilter')?.value || '').toLowerCase().trim();

    const norm = txt => (txt || '').toString().toLowerCase().trim();

    // handle every panel so filtering is reflected immediately across tabs
    document.querySelectorAll('.panel').forEach(visible => {
      const isStudents = visible.id === 'panel-students';
      const isOffices  = visible.id === 'panel-offices';
      const isMoa      = visible.id === 'panel-moa';
      const isRequests = visible.id === 'panel-requests';
      const isEvaluations = visible.id === 'panel-evaluations';

      visible.querySelectorAll('tbody tr').forEach(tr=>{
        // placeholder rows have no data-search attribute
        const ds = norm(tr.getAttribute('data-search'));
        const visibleBySearch = q === '' ? true : ds.indexOf(q) !== -1;

        let visibleByOffice = true;
        let visibleByStatus = true;

        if (isStudents) {
          const tds = tr.querySelectorAll('td');
          const officeText = norm(tds[1]?.textContent || '');
          const statusText = norm(tds[6]?.textContent || '');

          if (officeVal) visibleByOffice = officeText.indexOf(officeVal) !== -1;
          if (statusVal) visibleByStatus = statusText.indexOf(statusVal) !== -1;
        } else if (isOffices) {
          // offices: filter by search (data-search is office name). Also allow globalSearch to match other columns if needed.
          visibleByOffice = true;
          visibleByStatus = true;
        } else if (isMoa) {
          const tds = tr.querySelectorAll('td');
          // status cell is the last td (index 5)
          const statusText = norm(tds[5]?.textContent || '');
          if (moaStatusVal) visibleByStatus = statusText.indexOf(moaStatusVal) !== -1;
        } else if (isRequests) {
          // office requests: rely on data-search (office, reason, status)
          visibleByOffice = true;
          visibleByStatus = true;
        } else if (isEvaluations) {
          // evaluations: rely on data-search (student name, evaluator name, feedback)
          visibleByOffice = true;
          visibleByStatus = true;
        }

        tr.style.display = (visibleBySearch && visibleByOffice && visibleByStatus) ? '' : 'none';
      });
    });
  }

 // tab switching (simple CSS-based underline on active tab)
   document.querySelectorAll('.tabs button').forEach(btn=>{
     btn.addEventListener('click', function(){
       document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
       this.classList.add('active');

       const tab = this.getAttribute('data-tab');
       document.getElementById('panel-students').style.display = tab==='students' ? 'block' : 'none';
       document.getElementById('panel-offices').style.display = tab==='offices' ? 'block' : 'none';
       document.getElementById('panel-moa').style.display = tab==='moa' ? 'block' : 'none';
       document.getElementById('panel-requests').style.display = tab==='requests' ? 'block' : 'none';
       document.getElementById('panel-evaluations').style.display = tab==='evaluations' ? 'block' : 'none';

       // show/hide students-only filters
       const sf = document.getElementById('studentsFilters');
       if (sf) sf.style.display = tab==='students' ? 'flex' : 'none';
       // show/hide offices-only filters
       const of = document.getElementById('officesFilters');
       if (of) of.style.display = tab==='offices' ? 'flex' : 'none';
       // show/hide moa-only filters
       const mf = document.getElementById('moaFilters');
       if (mf) mf.style.display = tab==='moa' ? 'flex' : 'none';

       // preserve globalSearch value so typed query still filters other tabs
       // reset only the per-tab helper filters if you want; currently keep them as-is
       const ofilter = document.getElementById('officeFilter');
       if (ofilter) ofilter.value = ofilter.value; // no-op to preserve selection
       const st = document.getElementById('statusFilter');
       if (st) st.value = st.value;
       const moaSt = document.getElementById('moaStatusFilter');
       if (moaSt) moaSt.value = moaSt.value;

       // apply filters to refresh visibility (for all panels)
       applyFilters();
     });
   });

   // wire search + filter inputs
   const globalSearchEl = document.getElementById('globalSearch');
   if (globalSearchEl) globalSearchEl.addEventListener('input', applyFilters);
   const officeFilterEl = document.getElementById('officeFilter');
   if (officeFilterEl) officeFilterEl.addEventListener('change', applyFilters);
   const statusFilterEl = document.getElementById('statusFilter');
   if (statusFilterEl) statusFilterEl.addEventListener('change', applyFilters);
   const moaStatusFilterEl = document.getElementById('moaStatusFilter');
   if (moaStatusFilterEl) moaStatusFilterEl.addEventListener('change', applyFilters);
  // offices sort control (single dropdown + direction button)
  const officesSortCol = document.getElementById('officesSortColumn');
  const officesSortDirBtn = document.getElementById('officesSortDirBtn');

  function sortOfficesTable(key, dir){
    const tbl = document.getElementById('tblOffices');
    if (!tbl) return;
    const tbody = tbl.tBodies[0];
    if (!tbody) return;
    const idxMap = { capacity:1, available:2, approved:3, ongoing:4, completed:5 };
    const idx = idxMap[key];
    if (!idx) return;
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(r=>r.querySelectorAll('td').length > 0);
    const getNumeric = (row) => {
      const txt = (row.cells[idx]?.textContent || '').trim();
      if (txt === '—') return null;
      const n = parseFloat(txt.replace(/[^0-9.-]/g,'')); return isNaN(n) ? null : n;
    };
    rows.sort((a,b)=>{
      const A = getNumeric(a), B = getNumeric(b);
      if (A === null && B === null) return 0;
      if (A === null) return 1;
      if (B === null) return -1;
      return dir === 'asc' ? A - B : B - A;
    });
    rows.forEach(r=>tbody.appendChild(r));
  }

  if (officesSortCol) officesSortCol.addEventListener('change', function(){
    const key = this.value;
    const dir = officesSortDirBtn?.dataset.dir || 'asc';
    if (!key) return;
    sortOfficesTable(key, dir);
  });
  if (officesSortDirBtn) officesSortDirBtn.addEventListener('click', function(){
    const newDir = this.dataset.dir === 'asc' ? 'desc' : 'asc';
    this.dataset.dir = newDir;
    this.textContent = newDir === 'asc' ? 'Asc' : 'Desc';
    const key = officesSortCol?.value || '';
    if (!key) return;
    sortOfficesTable(key, newDir);
  });

   // initialize students filters visibility based on default active tab
   (function initFilterVisibility(){
     const active = document.querySelector('.tabs button.active');
     const sf = document.getElementById('studentsFilters');
     if (!sf) return;
     sf.style.display = active && active.getAttribute('data-tab') === 'students' ? 'flex' : 'none';
     const of = document.getElementById('officesFilters');
     if (of) of.style.display = active && active.getAttribute('data-tab') === 'offices' ? 'flex' : 'none';
     const mf = document.getElementById('moaFilters');
     if (mf) mf.style.display = active && active.getAttribute('data-tab') === 'moa' ? 'flex' : 'none';
   })();
 
   // initial filter pass so table reflects any default selection / search
   applyFilters();
 
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