<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// fetch user info
$stmtUser = $conn->prepare("SELECT first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc() ?: [];
$stmtUser->close();

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// which tab: pending (default) or rejected
$tab = isset($_GET['tab']) && $_GET['tab'] === 'rejected' ? 'rejected' : 'pending';

// --- LEFT-COLUMN COUNTS: use users table (role = 'ojt') for approved / completed / ongoing ---
// (Exclude records where status = 'active' as requested)
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'ojt' AND status = 'approved'");
$stmt->execute();
$stmt->bind_result($users_approved_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'ojt' AND status = 'completed'");
$stmt->execute();
$stmt->bind_result($users_completed_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'ojt' AND status = 'evaluated'");
$stmt->execute();
$stmt->bind_result($users_evaluated_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'ojt' AND status = 'ongoing'");
$stmt->execute();
$stmt->bind_result($users_ongoing_count);
$stmt->fetch();
$stmt->close();

// --- ADDED: pending/rejected counts used by the tabs ---
$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'pending'");
$stmt->execute();
$stmt->bind_result($pending_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'rejected'");
$stmt->execute();
$stmt->bind_result($rejected_count);
$stmt->fetch();
$stmt->close();

// fetch applications for current tab (include student email and remarks for rejected)
$statusFilter = $tab === 'rejected' ? 'rejected' : 'pending';
$q = "SELECT oa.application_id, oa.date_submitted, oa.status, oa.remarks,
             s.student_id, s.first_name AS s_first, s.last_name AS s_last, s.address AS s_address, s.email AS s_email,
             oa.office_preference1, oa.office_preference2,
             o1.office_name AS opt1, o2.office_name AS opt2
      FROM ojt_applications oa
       LEFT JOIN students s ON oa.student_id = s.student_id
       LEFT JOIN offices o1 ON oa.office_preference1 = o1.office_id
       LEFT JOIN offices o2 ON oa.office_preference2 = o2.office_id
       WHERE oa.status = ?
       ORDER BY oa.date_submitted ASC, oa.application_id ASC";
$stmtApps = $conn->prepare($q);
$stmtApps->bind_param("s", $statusFilter);
$stmtApps->execute();
$result = $stmtApps->get_result();
$apps = $result->fetch_all(MYSQLI_ASSOC);
$stmtApps->close();

// current server date/time
$current_time = date("g:i A");
$current_date = date("l, F j, Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OJT-MS | HR Staff Dashboard</title>
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
    .table-container{background:#fff;border-radius:8px;padding:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
    .table-tabs{display:flex;gap:16px;margin-bottom:12px;border-bottom:2px solid #eee}
    .table-tabs a{padding:8px 12px;text-decoration:none;color:#555;border-radius:6px}
    .table-tabs a.active{background:#2f3850;color:#fff}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:8px;border:1px solid #eee;text-align:left}
    th{background:#f5f6fa}
    .actions{display:flex;gap:8px;justify-content:center}
    .actions button{border:none;background:none;cursor:pointer;font-size:16px}
    .approve{color:green} .reject{color:red} .view{color:#0b74de}
    .empty{padding:20px;text-align:center;color:#666}

    /* top-right icons */
    .top-icons{display:flex;gap:10px;align-items:center}
    .top-icons button{background:none;border:none;cursor:pointer;font-size:18px;padding:8px;border-radius:8px}
    .top-icons button:hover{background:#f0f0f0}

    /* Modal / overlay */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(102, 51, 153, 0.18); /* light purple translucent blur look */
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      backdrop-filter: none;
    }
    .modal {
      background: #fff;
      border-radius: 10px;
      padding: 18px;
      width: 420px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      max-width: calc(100% - 40px);
    }
    .modal h3 { margin:0 0 12px 0; color:#3a2b6a }
    .modal .row { margin-bottom:8px; font-size:14px }
    .modal label { font-weight:600; font-size:13px; display:block; margin-bottom:4px }
    .modal input[type="date"] { width:100%; padding:8px; border-radius:6px; border:1px solid #ccc }
    .modal .values { padding:8px 10px; background:#faf7ff; border-radius:6px; color:#333; }
    .modal .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px }
    .modal button { padding:8px 12px; border-radius:6px; border:none; cursor:pointer }
    .btn-cancel { background:#eee; color:#333 }
    .btn-send { background:#6a3db5; }

    /* View Application Modal specific styles */
    #view_name { font-weight:700; font-size:18px; }
    #view_status { color:#666; font-size:13px; }
    #view_avatar {
      width:120px;
      height:120px;
      border-radius:50%;
      background:#e9e9e9;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:44px;
      color:#777;
      margin:0 auto 12px auto;
    }
    #view_approve_btn, #view_reject_btn {
      background:#fff;
      border:2px solid;
      padding:8px 14px;
      border-radius:24px;
      cursor:pointer;
      font-weight:500;
      display:inline-flex;
      align-items:center;
      gap:6px;
    }
    #view_approve_btn {
      color:#28a745;
      border-color:#28a745;
    }
    #view_reject_btn {
      color:#dc3545;
      border-color:#dc3545;
    }
    #view_attachments {
      display:flex;
      flex-direction:column;
      gap:8px;
    }
    .status-open{ color:#0b7a3a; font-weight:700; background:#e6f9ee; padding:6px 10px; border-radius:12px; display:inline-block; }
    .status-full{ color:#b22222; font-weight:700; background:#fff4f4; padding:6px 10px; border-radius:12px; display:inline-block; }

    /* make the left time/counters container smaller so slots table gets more horizontal space */
    .time-card { min-width:220px; max-width:260px; padding:12px; }
    .time-card .current-time { font-size:24px; color:#2f3850; margin:0; }
    .time-card .current-date { color:#6d6d6d; font-size:14px; }

    /* slightly smaller vertical footprint */
    .time-card { padding:12px; }
    .time-card .current-time { font-size:28px; } /* slightly smaller */
    .time-card .current-date { font-size:14px; } /* slightly smaller */
    .counter { padding:10px; }
    .counter h3 { margin:0; font-size:20px; color:#2f3850; }
    .counter p { margin:6px 0 0 0; color:#666; font-size:12px; }

    /* ensure slots card can expand */
    .slots-card { width:100%; }

    /* top bar / header styles */
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 24px;
    }
    .topbar .card {
      background: #fff;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      flex: 1;
      margin-right: 18px;
    }
    .topbar .card:last-child {
      margin-right: 0;
    }
    .topbar h2 {
      font-size: 28px;
      margin: 0 0 12px 0;
      color: #2f3850;
    }
    .topbar p {
      margin: 0;
      color: #6d6d6d;
      font-size: 14px;
    }

    /* Office availability: allow table to grow while keeping a scrollbar
       when content exceeds a reasonable max height. This shows ALL offices
       (no hard limit to 5 rows) but still constrains very tall lists. */

    .table-container.office-availability { 
      padding:8px; 
      box-sizing: border-box; 
      height: auto;            /* allow container to size to content */
      max-height: none; 
      overflow: visible; 
    }

    /* Do not force fixed row heights; allow natural row height */
    #officeBodyTable tbody tr { height: auto; }

    /* Keep head table layout consistent */
    #officeHeadTable thead th { line-height: normal; padding:6px; box-sizing:border-box; }

    /* The body wrapper will scroll only when content is taller than max-height.
       Use a viewport-relative max so it fits various screen sizes. */
    #officeBodyWrap {
      height: auto;
      max-height: 60vh; /* adjust as needed (e.g. 50vh/70vh) */
      overflow-y: auto;
      overflow-x: hidden;
      box-sizing: border-box;
    }

    /* ensure inner tables do not add extra margins */
    #officeHeadTable, #officeBodyTable { border-collapse: collapse; width:100%; box-sizing:border-box; }

    /* scrollbar visuals */
    #officeBodyWrap::-webkit-scrollbar { width:8px; }
    #officeBodyWrap::-webkit-scrollbar-thumb { background:#e0e0e0; border-radius:8px; }

    /* status badges */
    .status-open{ color:#0b7a3a; font-weight:700; background:#e6f9ee; padding:6px 10px; border-radius:12px; display:inline-block; }
    .status-full{ color:#b22222; font-weight:700; background:#fff4f4; padding:6px 10px; border-radius:12px; display:inline-block; }
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
      <a href="#" class="active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <path d="M3 11.5L12 4l9 7.5"></path>
          <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
        </svg>
        Home
      </a>
      <a href="hr_staff_ojts.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="8" r="3"></circle>
          <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
        </svg>
        OJTs
      </a>
      <a href="hr_staff_dtr.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 8v5l3 2"></path>
        </svg>
        DTR
      </a>
      <a href="hr_staff_moa.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 8v5l3 2"></path>
        </svg>
        MOA
      </a>
      <a href="hr_staff_accounts.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
        Accounts
      </a>
      <a href="hr_staff_reports.php">
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

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout
       NOTE: removed position:fixed to prevent overlapping; icons now flow with page
       and stay visible. -->
  <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
      <a id="btnNotif" href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </a>
      <!-- calendar icon (display only, non-clickable) -->
      <div title="Calendar (display only)" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;pointer-events:none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
       <a id="btnSettings" href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
           <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
       </a>
       <a id="btnLogout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
   </div>
   <div style="display:flex;gap:18px;align-items:stretch;margin-bottom:12px;">
    <!-- Left column: date on top, counters below (slightly narrower & lower height) -->
    <div style="flex:0 0 240px;min-width:200px;display:flex;flex-direction:column;">
      <div style="background:#fff;border-radius:8px;padding:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);flex:1;display:flex;flex-direction:column;justify-content:space-between;min-height:200px;">
      <div class="datetime" style="margin-bottom:10px">
        <h2 style="margin:0;font-size:20px;line-height:1"><?php echo $current_time; ?></h2>
        <p style="margin:0;color:#6d6d6d;font-size:12px"><?php echo $current_date; ?></p>
      </div>

      <!-- Counters: two rows
         Row 1: Evaluated and Completed (full width, prominent)
         Row 2: Approved Applicants + Active (side-by-side) -->
      <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">

        <!-- Row 1a: Evaluated (full width) -->
        <div style="background:#f5f7ff;border-radius:8px;padding:12px;border:1px solid #e6e9fb;box-shadow:0 2px 6px rgba(0,0,0,0.02);display:flex;align-items:center;justify-content:space-between;min-height:64px;">
        <div style="display:flex;flex-direction:column;gap:2px;">
          <div style="font-size:13px;color:#6d6d6d;font-weight:600">Evaluated</div>
        </div>
        <div style="font-size:28px;font-weight:700;color:#2f3850"><?php echo (int)($users_evaluated_count ?? 0); ?></div>
        </div>

        <!-- Row 1b: Completed (full width) -->
        <div style="background:#f5f7ff;border-radius:8px;padding:12px;border:1px solid #e6e9fb;box-shadow:0 2px 6px rgba(0,0,0,0.02);display:flex;align-items:center;justify-content:space-between;min-height:64px;">
        <div style="display:flex;flex-direction:column;gap:2px;">
          <div style="font-size:13px;color:#6d6d6d;font-weight:600">Completed</div>
        </div>
        <div style="font-size:28px;font-weight:700;color:#2f3850"><?php echo (int)($users_completed_count ?? 0); ?></div>
        </div>

        <!-- Row 2: Approved Applicants and Ongoing -->
        <div style="display:flex;gap:8px;">
        <!-- Approved Applicants -->
        <div style="background:#fff;border-radius:8px;padding:10px;border:1px solid #eef2f7;box-shadow:0 2px 6px rgba(0,0,0,0.03);flex:1;display:flex;flex-direction:column;justify-content:center;min-width:0;text-align:center;">
           <div style="font-size:18px;font-weight:700;color:#2f3850;line-height:1"><?php echo (int)($users_approved_count ?? 0); ?></div>
           <div style="color:#666;font-size:12px;margin-top:6px">Approved Applicants</div>
         </div>

        <!-- Ongoing -->
        <div style="background:#fff;border-radius:8px;padding:10px;border:1px solid #eef2f7;box-shadow:0 2px 6px rgba(0,0,0,0.03);flex:1;display:flex;flex-direction:column;justify-content:center;min-width:0;text-align:center;">
           <div style="font-size:18px;font-weight:700;color:#2f3850"><?php echo (int)($users_ongoing_count ?? 0); ?></div>
           <div style="color:#666;font-size:12px;margin-top:6px">Ongoing</div>
        </div>
        </div>
      </div>
      </div>
    </div>

<?php
// --- Office slot availability block (prepare data) ---
$capacityCol = null;
$variants = ['current_limit','slot_capacity','capacity','slots','max_slots'];
foreach ($variants as $v) {
    $res = $conn->query("SHOW COLUMNS FROM offices LIKE '".$conn->real_escape_string($v)."'");
    if ($res && $res->num_rows > 0) { $capacityCol = $v; break; }
}

// fetch offices
if ($capacityCol) {
    $sql = "SELECT office_id, office_name, `$capacityCol` AS capacity FROM offices ORDER BY office_name";
} else {
    $sql = "SELECT office_id, office_name FROM offices ORDER BY office_name";
}
$offices = [];
if ($resOff = $conn->query($sql)) {
    while ($r = $resOff->fetch_assoc()) $offices[] = $r;
    $resOff->free();
}

// prepare stmts to count approved and active OJTs per office
// approved => status = 'approved'
// active => common labels for ongoing/active assignments (best-effort; adjust list to your schema)
$stmtApproved = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM users
    WHERE role = 'ojt' AND office_name = ? AND status = 'approved'
");
$stmtActive = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM users
    WHERE role = 'ojt' AND office_name = ? AND status = 'ongoing'
");
$stmtCompleted = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM users
    WHERE role = 'ojt' AND office_name = ? AND status = 'completed'
");
?>

    <!-- Right column: slot availability (wider, slightly reduced vertical spacing) -->
    <div style="flex:1 1 0%;min-width:420px;">
      <div class="table-container office-availability" style="padding:8px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px">
          <h3 style="margin:0; background:#3a4163;padding:8px;border-radius:8px; color:#fff">OJT Slot Availability by Office</h3>
          <div style="display:flex;gap:8px;align-items:center">
            <input id="officeSearch" type="text" placeholder="Search office..." style="padding:8px 10px;border:1px solid #ddd;border-radius:8px;width:220px" />
            <select id="officeStatusFilter" style="padding:8px;border:1px solid #ddd;border-radius:8px;background:#fff">
              <option value="active">Open</option>
              <option value="full">Full</option>
              <option value="all" selected>All</option>
            </select>

            <!-- added sort control (same line) -->
            <label for="officeSort" style="margin:0 6px 0 6px;color:#444;font-weight:600;font-size:13px">Sort by</label>
            <select id="officeSort" style="padding:8px;border:1px solid #ddd;border-radius:8px;background:#fff">
              <option value="">None</option>
              <option value="capacity">Capacity</option>
              <option value="active">Ongoing OJTs</option>
              <option value="approved">Approved</option>
              <option value="completed">Completed OJTs</option>
              <option value="available">Available Slot</option>
            </select>
            <button id="officeSortDir" title="Toggle sort direction" style="padding:8px 10px;border:1px solid #ddd;border-radius:8px;background:#fff;cursor:pointer">Desc</button>
          </div>
        </div>

        <?php if (empty($offices)): ?>
            <div class="empty">No offices found.</div>
        <?php else: ?>
            <!-- Header table (keeps header fixed/aligned) -->
            <table id="officeHeadTable" style="width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;margin:0 0 0 0;">
              <colgroup>
                <col style="width:34%">
                <col style="width:8%">
                <col style="width:10%">
                <col style="width:10%">
                <col style="width:10%">
                <col style="width:10%">
                <col style="width:18%">
              </colgroup>
              <thead>
                <tr>
                  <th style="text-align:left;padding:6px;border:1px solid #eee;background:#e6e9fb">Office</th>
                  <th style="padding:6px;border:1px solid #eee;background:#e6e9fb;text-align:center">Capacity</th>
                  <th style="padding:6px;border:1px solid #eee;background:#e6e9fb;text-align:center">Ongoing OJTs</th>
                  <th style="padding:6px;border:1px solid #eee;background:#e6e9fb;text-align:center">Approved</th>
                  <th style="padding:6px;border:1px solid #eee;background:#e6e9fb;text-align:center">Completed OJTs</th>
                  <th style="padding:6px;border:1px solid #eee;background:#e6e9fb;text-align:center">Available Slot</th>
                  <th style="padding:6px;border:1px solid #eee;background:#e6e9fb;text-align:center">Status</th>
                </tr>
              </thead>
            </table>

            <!-- Scrollable tbody container with a matching table -->
            <!-- body height = exactly 5 rows (th NOT counted) -->
            <div id="officeBodyWrap" style="height:calc(48px * 5);min-height:calc(48px * 5);overflow:auto;">
              <table id="officeBodyTable" style="width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;margin:0;">
                <colgroup>
                  <col style="width:34%">
                  <col style="width:8%">
                  <col style="width:10%">
                  <col style="width:10%">
                  <col style="width:10%">
                  <col style="width:10%">
                  <col style="width:18%">
                </colgroup>
                <tbody id="officesBody">
                <?php foreach ($offices as $o):
                    // approved count per office
                    $approved = 0;
                    $active = 0;
                    // use office_name from offices table to count users assigned to that office
                    $officeName = $o['office_name'] ?? '';
                    if ($stmtApproved) {
                        $stmtApproved->bind_param('s', $officeName);
                        $stmtApproved->execute();
                        $stmtApproved->bind_result($approvedTemp);
                        $stmtApproved->fetch();
                        $approved = (int)($approvedTemp ?? 0);
                        $stmtApproved->free_result();
                    }
                    if ($stmtActive) {
                        $stmtActive->bind_param('s', $officeName);
                        $stmtActive->execute();
                        $stmtActive->bind_result($activeTemp);
                        $stmtActive->fetch();
                        $active = (int)($activeTemp ?? 0);
                        $stmtActive->free_result();
                    }
                    // completed count per office
                    $completed = 0;
                    if ($stmtCompleted) {
                        $stmtCompleted->bind_param('s', $officeName);
                        $stmtCompleted->execute();
                        $stmtCompleted->bind_result($completedTemp);
                        $stmtCompleted->fetch();
                        $completed = (int)($completedTemp ?? 0);
                        $stmtCompleted->free_result();
                    }

                    $cap = isset($o['capacity']) ? (int)$o['capacity'] : null;

                    // compute available slots: capacity - (active + approved)
                    if ($cap === null) {
                        $availableDisplay = '—';
                        $statusLabel = 'Open';
                        $statusClass = 'status-open';
                    } else {
                        $availableNum = max(0, $cap - ($active + $approved));
                        $availableDisplay = $availableNum;
                        if ($availableNum === 0) {
                            $statusLabel = 'Full';
                            $statusClass = 'status-full';
                        } else {
                            $statusLabel = 'Open';
                            $statusClass = 'status-open';
                        }
                    }
                ?>
                  <tr data-office="<?= htmlspecialchars(strtolower($o['office_name'] ?? '')) ?>">
                    <td style="padding:6px;border:1px solid #eee;"><?= htmlspecialchars($o['office_name'] ?? '—') ?></td>
                    <td style="text-align:center;padding:6px;border:1px solid #eee"><?= $cap === null ? '—' : $cap ?></td>
                    <td style="text-align:center;padding:6px;border:1px solid #eee"><?= $active ?></td>
                    <td style="text-align:center;padding:6px;border:1px solid #eee"><?= $approved ?></td>
                    <td style="text-align:center;padding:6px;border:1px solid #eee"><?= $completed ?></td>
                    <td style="text-align:center;padding:6px;border:1px solid #eee"><?= $availableDisplay ?></td>
                    <td style="text-align:center;padding:6px;border:1px solid #eee"><span class="<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
         <?php endif; ?>
       </div>
     </div>
   </div> <!-- end second row -->
<?php
$stmtApproved->close();
$stmtActive->close();
$stmtCompleted->close();
?>
<style>
/* Office availability — show all office rows; allow tbody to scroll when very tall */
.table-container.office-availability { 
  padding:8px; 
  box-sizing: border-box; 
  height: auto;            /* allow container to size to content */
  max-height: none; 
  overflow: visible;
}

/* Allow natural row height (do not force fixed row height) */
#officeBodyTable tbody tr { height: auto; }

/* Keep header layout stable */
#officeHeadTable thead th { line-height: normal; padding:6px; box-sizing:border-box; }

/* Body wrapper scrolls only when content exceeds max-height */
#officeBodyWrap {
  height: auto;
  max-height: 60vh; /* adjust if you want more/less vertical space */
  overflow-y: auto;
  overflow-x: hidden;
  box-sizing: border-box;
}

/* Ensure inner tables don't add extra margins */
#officeHeadTable, #officeBodyTable { border-collapse: collapse; width:100%; box-sizing:border-box; }

/* scrollbar visuals */
#officeBodyWrap::-webkit-scrollbar { width:8px; }
#officeBodyWrap::-webkit-scrollbar-thumb { background:#e0e0e0; border-radius:8px; }

/* status badges (kept same) */
.status-open{ color:#0b7a3a; font-weight:700; background:#e6f9ee; padding:6px 10px; border-radius:12px; display:inline-block; }
.status-full{ color:#b22222; font-weight:700; background:#fff4f4; padding:6px 10px; border-radius:12px; display:inline-block; }
</style>

<script>
/* Office availability filtering (search + status) with sorting on same line
   sorts by Capacity (col 1), Active OJTs (col 2), Approved (col 3), Available Slot (col 4) */
(function(){
  const searchInput = document.getElementById('officeSearch');
  const statusSel = document.getElementById('officeStatusFilter');
  const sortSel = document.getElementById('officeSort');
  const sortDirBtn = document.getElementById('officeSortDir');
  const tbody = document.getElementById('officesBody');

  // column indices in the table body (0-based)
  // updated to include Completed OJTs column (new index)
  const COL = { capacity:1, active:2, approved:3, completed:4, available:5 };

  function parseNumCell(text){
    if (!text) return null;
    text = text.toString().trim();
    if (text === '—' || text === '') return null;
    const n = parseInt(text.replace(/[^\d-]/g,''), 10);
    return isNaN(n) ? null : n;
  }

  function filterAndSort(){
    const q = (searchInput.value || '').toLowerCase().trim();
    const status = (statusSel.value || 'active');
    const sortBy = (sortSel.value || '');
    const dir = (sortDirBtn.dataset.dir || 'desc'); // 'asc' or 'desc'

    const rows = Array.from(tbody.querySelectorAll('tr'));

    // First: filter rows (set display)
    rows.forEach(r => {
      const name = (r.getAttribute('data-office') || '').toLowerCase();
      const statusText = (r.querySelector('td:last-child').textContent || '').toLowerCase();
      const matchesQuery = q === '' || name.indexOf(q) !== -1;
      let matchesStatus = true;
      if (status === 'active') {
        matchesStatus = statusText !== 'full';
      } else if (status === 'full') {
        matchesStatus = statusText === 'full';
      } else {
        matchesStatus = true;
      }
      r.style.display = (matchesQuery && matchesStatus) ? '' : 'none';
    });

    // Then: sort visible rows if requested
    if (sortBy && COL.hasOwnProperty(sortBy)) {
      const visibleRows = rows.filter(r => r.style.display !== 'none');
      // build sortable array
      visibleRows.sort((a,b) => {
        const aRaw = a.cells[COL[sortBy]] ? a.cells[COL[sortBy]].textContent : '';
        const bRaw = b.cells[COL[sortBy]] ? b.cells[COL[sortBy]].textContent : '';
        const aVal = parseNumCell(aRaw);
        const bVal = parseNumCell(bRaw);

        // handle nulls: push nulls to end
        if (aVal === null && bVal === null) return 0;
        if (aVal === null) return dir === 'asc' ? 1 : -1;
        if (bVal === null) return dir === 'asc' ? -1 : 1;

        if (aVal === bVal) return 0;
        return (aVal < bVal ? -1 : 1) * (dir === 'asc' ? 1 : -1);
      });

      // re-append visible rows in sorted order at the end of tbody (keeps hidden rows in place)
      // approach: move each visible row in order
      visibleRows.forEach(r => tbody.appendChild(r));
    }
  }

  // toggle direction button
  sortDirBtn.dataset.dir = 'desc';
  sortDirBtn.addEventListener('click', function(){
    this.dataset.dir = this.dataset.dir === 'asc' ? 'desc' : 'asc';
    this.textContent = this.dataset.dir === 'asc' ? 'Asc' : 'Desc';
    filterAndSort();
  });

  // wire inputs
  searchInput.addEventListener('input', filterAndSort);
  statusSel.addEventListener('change', filterAndSort);
  sortSel.addEventListener('change', filterAndSort);

  // initial run
  filterAndSort();
})();
</script>

<!-- place search next to pending/rejected tabs -->
<style>
.table-tabs-wrap { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
.table-tabs-inline { display:flex; gap:8px; align-items:center; }
</style>
<div style="display:none"></div>
<script>
  // move existing tabs into a wrapper and inject search (runs once)
  (function(){
    const tabs = document.querySelector('.table-tabs');
    if (!tabs) return;
    const parent = tabs.parentElement;
    const wrapper = document.createElement('div');
    wrapper.className = 'table-tabs-wrap';
    const left = document.createElement('div');
    left.className = 'table-tabs-inline';
    left.appendChild(tabs);
    const right = document.createElement('div');
    right.innerHTML = '<input id=\"tabSearch\" type=\"text\" placeholder=\"Search pending/rejected...\" style=\"padding:6px 10px;border:1px solid #ddd;border-radius:8px;min-width:220px\" />';
    wrapper.appendChild(left);
    wrapper.appendChild(right);
    parent.insertBefore(wrapper, parent.firstChild);
    // wire simple client-side filter for the applications table
    const tabSearch = document.getElementById('tabSearch');
    tabSearch.addEventListener('input', function(){
      const q = (this.value||'').toLowerCase().trim();
      document.querySelectorAll('.table-container table tbody tr').forEach(tr=>{
        if (!q) { tr.style.display=''; return; }
        const txt = (tr.textContent||'').toLowerCase();
        tr.style.display = txt.indexOf(q) === -1 ? 'none' : '';
      });
    });
  })();
</script>

<!-- Next row: the list of pending / rejected (keeps the existing table-container after placeholder) -->

    <div class="table-container">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px">
            <div class="table-tabs" style="display:flex;gap:12px">
                <a class="<?php echo $tab === 'pending' ? 'active' : ''; ?>" href="?tab=pending" <?php if ($tab === 'pending') echo 'style="background:#3a4163;color:#fff"'; ?>>Pending Approvals (<?php echo (int)$pending_count; ?>)</a>
                <a class="<?php echo $tab === 'rejected' ? 'active' : ''; ?>" href="?tab=rejected" <?php if ($tab === 'rejected') echo 'style="background:#3a4163;color:#fff"'; ?>>Rejected Students (<?php echo (int)$rejected_count; ?>)</a>
            </div>

            <!-- keep search + office dropdown inside the white .table-container -->
            <div style="display:flex;gap:8px;align-items:center;">
                <div style="position:relative;display:inline-flex;align-items:center;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:6px;pointer-events:none;">
                    <circle cx="11" cy="11" r="6"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                  </svg>
                  <input id="tabSearch" aria-label="Search pending or rejected" type="text" placeholder="Search name / address / office..." style="padding:6px 10px 6px 34px;border:1px solid #ddd;border-radius:8px;min-width:220px" />
                </div>
                <select id="tabOfficeFilter" style="padding:6px;border:1px solid #ddd;border-radius:8px;">
                    <option value="all">All offices</option>
                    <?php foreach($offices as $o): ?>
                        <?php $on = is_array($o) ? ($o['office_name'] ?? '') : (string)$o; ?>
                        <option value="<?php echo htmlspecialchars(strtolower($on)); ?>"><?php echo htmlspecialchars($on); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (count($apps) === 0): ?>
            <div class="empty">No <?php echo $tab === 'rejected' ? 'rejected' : 'pending'; ?> applications found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date Submitted</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>1st Option</th>
                    <th>2nd Option</th>
                    <?php if ($tab === 'rejected'): ?>
                        <th>Reason</th>
                    <?php endif; ?>
                    <?php if ($tab !== 'rejected'): ?>
                    <th style="text-align:center">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apps as $row): ?>
                <tr>
                    <td><?php echo $row['date_submitted'] ? htmlspecialchars(date("M j, Y", strtotime($row['date_submitted']))) : '—'; ?></td>
                    <td><?php echo htmlspecialchars(trim(($row['s_first'] ?? '') . ' ' . ($row['s_last'] ?? '')) ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['s_address'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['opt1'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['opt2'] ?: 'N/A'); ?></td>
                    <?php if ($tab === 'rejected'): ?>
                        <td><?php echo htmlspecialchars(trim($row['remarks'] ?? '') ?: '—'); ?></td>
                    <?php endif; ?>
                    <?php if ($tab !== 'rejected'): ?>
                    <td class="actions">
                        <button type="button" class="view" title="View" onclick="openViewModal(<?= (int)$row['application_id'] ?>)">👁</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal overlay for approval -->
<div id="overlay" class="overlay" role="dialog" aria-hidden="true">
  <div class="modal" role="document" aria-labelledby="approveTitle">
    <h3 id="approveTitle">Approve Application</h3>

    <div class="row">
      <label>Student Name</label>
      <div class="values" id="modal_name">—</div>
    </div>

    <div class="row">
      <label>Email</label>
      <div class="values" id="modal_email">—</div>
    </div>

    <div class="row">
      <label>Assigned Office (from applicant)</label>
      <div class="values" id="modal_office">—</div>
    </div>

    <div class="row">
      <label>Orientation / Starting Date</label>
      <input type="date" id="modal_date">
    </div>
    <div class="row">
      <label>Location</label>
      <input type="text" id="modal_location" value="CHRMO/3rd Floor" placeholder="CHRMO/3rd Floor">
    </div>
    <div class="row">
      <label>Time</label>
      <input type="time" id="modal_time" value="08:30">
    </div>

    <!-- status message area (hidden until send result) -->
    <div id="modal_status" class="values" style="display:none;margin-top:10px;"></div>

    <div class="actions">
      <button class="btn-cancel" onclick="closeModal()" type="button">Cancel</button>
      <button id="btnSend" class="btn-send" type="button" onclick="sendApproval(); setTimeout(function(){ location.reload(); }, 1200);" aria-disabled="true" disabled>Send</button>
    </div>
  </div>
</div>

<!-- Reject Modal overlay -->
<div id="rejectOverlay" class="overlay" role="dialog" aria-hidden="true">
  <div class="modal" role="document" aria-labelledby="rejectTitle">
    <h3 id="rejectTitle">Reject Application</h3>
    <div class="row">
      <label>Student Name</label>
      <div class="values" id="reject_name">—</div>
    </div>
    <div class="row">
      <label>Email</label>
      <div class="values" id="reject_email">—</div>
    </div>
    <div class="row">
      <label>Reason for Rejection <span style="color:red">*</span></label>
      <textarea id="reject_reason" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc" required></textarea>
    </div>
    <div id="reject_status" class="values" style="display:none;margin-top:10px;"></div>
    <div class="actions">
      <button class="btn-cancel" onclick="closeRejectModal()" type="button">Cancel</button>
      <button id="btnRejectSend" class="btn-send" type="button" onclick="sendReject(); setTimeout(function(){ location.reload(); }, 1200);" aria-disabled="true" disabled>Reject</button>
    </div>
  </div>
</div>
<!-- View Application Modal -->
<div id="viewOverlay" class="overlay" role="dialog" aria-hidden="true" style="display:none;align-items:center;justify-content:center;">
  <div class="modal" style="width:760px;max-width:calc(100% - 40px);max-height:80vh;overflow:auto;padding:16px;">
    <!-- Top: avatar, name, status and action buttons -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding-bottom:8px;border-bottom:1px solid #eee">
      <div style="width:120px;height:120px;border-radius:50%;background:#e9e9e9;display:flex;align-items:center;justify-content:center;font-size:44px;color:#777" id="view_avatar">👤</div>
      <div style="text-align:center">
        <div id="view_name" style="font-weight:700;font-size:18px">Name</div>
        <div id="view_status" style="color:#666;font-size:13px">Status | hours</div>
      </div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <!-- Approve/Reject removed for HR Staff view — view-only access -->
      </div>
    </div>

    <!-- Below name: info and attachments shown as flex -->
    <div style="display:flex;gap:18px;margin-top:12px;flex-wrap:wrap;">
      <div style="flex:1;min-width:280px;border-right:1px solid #eee;padding-right:12px">
        <table style="width:100%;border-collapse:collapse">
          <tbody style="font-size:14px">
            <tr><td style="width:140px;font-weight:700">Age</td><td id="view_age"></td></tr>
            <tr><td style="font-weight:700">Birthday</td><td id="view_birthday"></td></tr>
            <tr><td style="font-weight:700">Address</td><td id="view_address"></td></tr>
            <tr><td style="font-weight:700">Phone</td><td id="view_phone"></td></tr>
            <tr><td style="font-weight:700">Email</td><td id="view_email"></td></tr>

            <tr><td style="height:8px"></td><td></td></tr>

            <tr><td style="font-weight:700">College/University</td><td id="view_college"></td></tr>
            <tr><td style="font-weight:700">Course</td><td id="view_course"></td></tr>
            <tr><td style="font-weight:700">Year level</td><td id="view_year"></td></tr>
            <tr><td style="font-weight:700">School Address</td><td id="view_school_address"></td></tr>
            <tr><td style="font-weight:700">OJT Adviser</td><td id="view_adviser"></td></tr>

            <tr><td style="height:8px"></td><td></td></tr>

            <tr><td style="font-weight:700">Emergency Contact</td><td id="view_emg_name"></td></tr>
            <tr><td style="font-weight:700">Relationship</td><td id="view_emg_relation"></td></tr>
            <tr><td style="font-weight:700">Contact Number</td><td id="view_emg_contact"></td></tr>
          </tbody>
        </table>
      </div>

      <div style="width:320px;padding-left:12px;min-width:220px">
        <div style="font-weight:700;margin-bottom:8px">Attachments</div>
        <div id="view_attachments" style="display:flex;flex-direction:column;gap:8px"></div>
      </div>
    </div>
    <style>
      /* make modal a positioned container so the button can be placed at its top-right */
      .modal { position: relative; }
    </style>

    <!-- top-right Close button inside the modal -->
    <div style="position:absolute; top:12px; right:12px;">
      <button class="btn-cancel"
              onclick="closeViewModal()"
              type="button"
              style="padding:8px 12px; border-radius:8px; border:none; background:#eee; color:#333; cursor:pointer;">
        Close
      </button>
    </div>
  </div>
</div>

<script>
// hold currently approving application id
let currentAppId = null;

async function openApproveModal(btn) {
    const el = btn;
    currentAppId = el.getAttribute('data-appid');
    const name = el.getAttribute('data-name') || '';
    const email = el.getAttribute('data-email') || '';
    const opt1Name = el.getAttribute('data-opt1') || '';
    const opt2Name = el.getAttribute('data-opt2') || '';
    const opt1Id = parseInt(el.getAttribute('data-opt1-id') || '0', 10) || null;
    const opt2Id = parseInt(el.getAttribute('data-opt2-id') || '0', 10) || null;

    document.getElementById('modal_name').textContent = name;
    document.getElementById('modal_email').textContent = email;

    // determine assigned office by asking server (pref1 if has capacity, else pref2, else show N/A)
    let assigned = '';
    try {
        const payload = { action: 'check_capacity', office1: opt1Id, office2: opt2Id };
        const res = await fetch('../hr_actions.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json && json.success) {
            assigned = json.assigned || '';
        } else {
            // fallback display: prefer opt1 name then opt2
            assigned = opt1Name || opt2Name || 'N/A';
        }
    } catch (e) {
        assigned = opt1Name || opt2Name || 'N/A';
    }

    document.getElementById('modal_office').textContent = assigned || 'N/A';

    const dateInput = document.getElementById('modal_date');

    // --- ADDED: prevent selecting past dates and disable next 7 days starting tomorrow ---
    const today = new Date();

    // block range: tomorrow .. tomorrow + 6 (7 days total: days 1..7)
    const blockedStart = new Date(today);
    blockedStart.setDate(blockedStart.getDate() + 1);
    const blockedEnd = new Date(today);
    blockedEnd.setDate(blockedEnd.getDate() + 7);

    // earliest allowed date is the day after the blockedEnd
    const allowedMin = new Date(today);
    allowedMin.setDate(allowedMin.getDate() + 8);

    const toIsoDate = d => d.toISOString().split('T')[0];

    dateInput.min = toIsoDate(allowedMin);
    // optionally prefill with the first allowed date
    dateInput.value = toIsoDate(allowedMin);

    // add user hint (shows on hover) about the disabled range
    dateInput.title = `Unavailable: ${toIsoDate(blockedStart)} — ${toIsoDate(blockedEnd)}. Earliest selectable: ${toIsoDate(allowedMin)}.`;
    // --- end added ---

    // disable send until date chosen
    const btnSend = document.getElementById('btnSend');
    btnSend.disabled = false;
    btnSend.setAttribute('aria-disabled', 'false');

    // enable when date selected (also re-check validity)
    dateInput.oninput = function() {
        const val = dateInput.value;
        if (val && val >= dateInput.min) {
            btnSend.disabled = false;
            btnSend.setAttribute('aria-disabled', 'false');
        } else {
            btnSend.disabled = true;
            btnSend.setAttribute('aria-disabled', 'true');
        }
    };

    // show overlay
    const ov = document.getElementById('overlay');
    ov.style.display = 'flex';
    ov.setAttribute('aria-hidden', 'false');

    dateInput.focus();
}

function closeModal(){
    const ov = document.getElementById('overlay');
    ov.style.display = 'none';
    ov.setAttribute('aria-hidden', 'true');
    currentAppId = null;

    // cleanup date onchange
    const dateInput = document.getElementById('modal_date');
    dateInput.oninput = null;
}

function sendApproval(){
    if (!currentAppId) return alert('Invalid application.');

    const date = document.getElementById('modal_date').value;
    const statusEl = document.getElementById('modal_status');

    if (!date) {
        return alert('Please select the orientation / starting date.');
    }

    // disable button to prevent double submit
    const btnSend = document.getElementById('btnSend');
    btnSend.disabled = true;
    btnSend.setAttribute('aria-disabled', 'true');

    // clear/prepare status area
    statusEl.style.display = 'none';
    statusEl.textContent = '';
    statusEl.style.background = '';
    statusEl.style.color = '';

    const payload = {
        action: 'approve_send',
        application_id: parseInt(currentAppId,10),
        orientation_date: date
    };

    fetch('../hr_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    }).then(r => r.json())
      .then(res => {
          if (res.success) {
              // show inline status based on mail result
              if (res.mail && res.mail === 'sent') {
                  statusEl.style.display = 'block';
                  statusEl.style.background = '#e6f9ee';
                  statusEl.style.color = '#0b7a3a';
                  statusEl.textContent = 'Email sent.';
              } else {
                  statusEl.style.display = 'block';
                  statusEl.style.background = '#fff4f4';
                  statusEl.style.color = '#a00';
                  statusEl.textContent = 'Email not sent (' + (res.mail || 'failed') + ').';
                  if (res.debug) statusEl.textContent += ' See debug.';
                  console.warn(res.debug || res.error || '');
              }

              // give user a short moment to see the message, then close modal and reload to reflect DB status change
              setTimeout(() => {
                  closeModal();
                  location.reload();
              }, 900);
          } else {
              // server returned success=false
              alert('Error: ' + (res.message || 'Unknown') + (res.error ? '\n' + res.error : ''));
              // re-enable send on failure
              btnSend.disabled = false;
              btnSend.setAttribute('aria-disabled', 'false');
          }
      }).catch(err => {
          alert('Request failed.');
          console.error(err);
          btnSend.disabled = false;
          btnSend.setAttribute('aria-disabled', 'false');
      });
}

// Reject modal functions
let currentRejectId = null;

function openRejectModal(btn) {
    currentRejectId = btn.getAttribute('data-appid');
    document.getElementById('reject_name').textContent = btn.getAttribute('data-name') || '';
    document.getElementById('reject_email').textContent = btn.getAttribute('data-email') || '';
    document.getElementById('reject_reason').value = '';
    document.getElementById('reject_status').style.display = 'none';
    document.getElementById('btnRejectSend').disabled = true;
    document.getElementById('btnRejectSend').setAttribute('aria-disabled', 'true');
    document.getElementById('rejectOverlay').style.display = 'flex';
    document.getElementById('rejectOverlay').setAttribute('aria-hidden', 'false');
    document.getElementById('reject_reason').focus();
}

// Enable Reject button only if reason is filled
document.addEventListener('input', function(e){
    if (e.target && e.target.id === 'reject_reason') {
        const btn = document.getElementById('btnRejectSend');
        if (e.target.value.trim().length > 0) {
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'false');
        } else {
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
        }
    }
});

function closeRejectModal(){
    document.getElementById('rejectOverlay').style.display = 'none';
    document.getElementById('rejectOverlay').setAttribute('aria-hidden', 'true');
    currentRejectId = null;
}

function sendReject(){
    const reason = document.getElementById('reject_reason').value.trim();
    if (!currentRejectId || !reason) {
        alert('Please provide a reason for rejection.');
        return;
    }
    const statusEl = document.getElementById('reject_status');
    statusEl.style.display = 'none';
    statusEl.textContent = '';
    statusEl.style.background = '';
    statusEl.style.color = '';

    const btn = document.getElementById('btnRejectSend');
    btn.disabled = true;
    btn.setAttribute('aria-disabled', 'true');

    fetch('../hr_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            application_id: parseInt(currentRejectId,10),
            action: 'reject',
            reason: reason
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            statusEl.style.display = 'block';
            statusEl.style.background = '#fff4f4';
            statusEl.style.color = '#a00';
            statusEl.textContent = 'Application rejected.';
            setTimeout(() => {
                closeRejectModal();
                location.reload();
            }, 900);
        } else {
            alert('Error: ' + (res.message || 'Unknown'));
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'false');
        }
    })
    .catch(err => {
        alert('Request failed');
        btn.disabled = false;
        btn.setAttribute('aria-disabled', 'false');
    });
}

// existing handleAction for reject (uses hr_actions.php)
function handleAction(id, action) {
  if (!confirm('Are you sure?')) return;
  fetch('../hr_actions.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ application_id: id, action: action })
  })
  .then(response => response.text())
  .then(text => {
    // try parse JSON safely
    let json = null;
    try { json = JSON.parse(text); } catch(e) { console.error('Non-JSON response:', text); }
    if (json && json.success) {
      alert('Action completed.');
      location.reload();
    } else {
      console.error('Action failed:', json || text);
      alert('Error: ' + (json && json.message ? json.message : 'Request failed or invalid response'));
    }
  })
  .catch(err => {
    console.error('Request error:', err);
    alert('Request failed');
  });
}

/* View Application Modal scripts */
async function openViewModal(appId) {
  const overlay = document.getElementById('viewOverlay');
  // clear
  ['view_name','view_status','view_age','view_birthday','view_address','view_phone','view_email',
   'view_college','view_course','view_year','view_school_address','view_adviser',
   'view_emg_name','view_emg_relation','view_emg_contact','view_attachments'].forEach(id=> {
     const el = document.getElementById(id);
     if (el) el.textContent = '';
   });
  const avatarEl0 = document.getElementById('view_avatar');
  if (avatarEl0) avatarEl0.innerHTML = '👤';

  try {
    const payload = { action: 'get_application', application_id: parseInt(appId,10) };
    const res = await fetch('../hr_actions.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const json = await res.json();

    if (!json || !json.success) {
      alert('Application not found or server error.');
      return;
    }

    const d = json.data || {};
    const st = d.student || {};

    const studentName = ((st.first_name||'') + ' ' + (st.last_name||'')).trim() || 'N/A';
    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || 'N/A'; };

    setText('view_name', studentName);
    setText('view_status', (d.status || '').toUpperCase() + (d.remarks ? ' | ' + d.remarks : ''));
    setText('view_age', st.age ? (st.age + ' years old') : 'N/A');
    setText('view_birthday', st.birthday ? new Date(st.birthday).toLocaleDateString('en-PH') : 'N/A');
    setText('view_address', st.address);
    setText('view_phone', st.contact_number);
    setText('view_email', st.email);
    setText('view_college', st.college);
    setText('view_course', st.course);
    setText('view_year', st.year_level);
    setText('view_school_address', st.school_address);
    setText('view_adviser', st.ojt_adviser);
    setText('view_emg_name', st.emergency_name);
    setText('view_emg_relation', st.emergency_relation);
    setText('view_emg_contact', st.emergency_contact);

    // avatar
    const avatarEl = document.getElementById('view_avatar');
    if (avatarEl) {
      avatarEl.style.background = '#e9e9e9';
      avatarEl.style.color = '#777';
      avatarEl.style.fontSize = '44px';
      // prefer applicant picture (d.picture) if provided
      const picRaw = (d.picture || '').trim();
      if (picRaw) {
        let picHref;
        if (/^https?:\/\//i.test(picRaw) || picRaw.startsWith('/')) {
          picHref = picRaw;
        } else if (/^uploads[\/\\]/i.test(picRaw)) {
          picHref = '../' + picRaw.replace(/^\/+/, '');
        } else {
          picHref = '../uploads/' + picRaw.replace(/^\/+/, '');
        }
        // create image element and insert
        avatarEl.innerHTML = '';
        const img = document.createElement('img');
        img.src = picHref;
        img.alt = studentName || 'Applicant';
        img.style.width = '120px';
        img.style.height = '120px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '50%';
        // on error fallback to initial
        img.onerror = function() {
          avatarEl.innerHTML = (studentName && studentName !== 'N/A') ? studentName.trim().charAt(0).toUpperCase() : '👤';
        };
        avatarEl.appendChild(img);
      } else {
        avatarEl.innerHTML = (studentName && studentName !== 'N/A') ? studentName.trim().charAt(0).toUpperCase() : '👤';
      }
    }

    // attachments: use any file fields returned by the endpoint
    const attachmentsEl = document.getElementById('view_attachments');
    if (attachmentsEl) {
      attachmentsEl.innerHTML = '';
      const fileKeys = ['letter_of_intent','endorsement_letter','resume','moa_file','picture'];
      const files = [];
      fileKeys.forEach(k => { if (d[k]) files.push({ filepath: d[k], original_name: k.replace(/_/g,' ') }); });
      if (files.length) {
        files.forEach(file => {
          let raw = (file.filepath || '').trim();
          if (!raw) return; // skip empty

          // Resolve href relative to this script (hr_head/ -> project root is ../)
          let href;
          if (/^https?:\/\//i.test(raw) || raw.startsWith('/')) {
            // already a full URL or absolute path -> use as-is
            href = raw;
          } else if (/^uploads[\/\\]/i.test(raw)) {
            // stored like "uploads/xxx" or "uploads\xxx" -> from hr_head file, prefix one level up
            href = '../' + raw.replace(/^\/+/, '');
          } else {
            // stored as bare filename or relative path without uploads/ -> assume uploads/
            href = '../uploads/' + raw.replace(/^\/+/, '');
          }

          const a = document.createElement('a');
          a.href = href;
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          const label = (file.original_name && file.original_name !== '') ? file.original_name : (href.split('/').pop() || 'Attachment');
          a.textContent = label;
          a.style.color = '#0b74de';
          a.style.textDecoration = 'underline';
          a.style.marginTop = '4px';
          a.style.cursor = 'pointer';

          // DO NOT set download attribute — allow browser to open/view inline (server decides rendering)
          attachmentsEl.appendChild(a);
        });
      } else {
       
        const noAttach = document.createElement('div');
        noAttach.textContent = 'No attachments found.';
        noAttach.style.color = '#666';
        noAttach.style.fontSize = '13px';
        noAttach.style.marginTop = '4px';
        attachmentsEl.appendChild(noAttach);
      }
    }

    const isOpen = (d.status === 'approved' || d.status === 'pending');
    const approveBtn = document.getElementById('view_approve_btn');
    const rejectBtn = document.getElementById('view_reject_btn');
    if (approveBtn) {
      approveBtn.style.display = isOpen ? 'inline-flex' : 'none';
      // wire same approve flow as the table action icons:
      approveBtn.onclick = function(e){
        // close view modal then open approve modal with same data
        closeViewModal();
        const fakeBtn = {
          getAttribute: (k) => {
            switch(k) {
              case 'data-appid': return String(appId);
              case 'data-name': return studentName;
              case 'data-email': return st.email || '';
              case 'data-opt1': return d.office1 || '';
              case 'data-opt2': return d.office2 || '';
              case 'data-opt1-id': return String(d.office_preference1 || 0);
              case 'data-opt2-id': return String(d.office_preference2 || 0);
            }
            return null;
          }
        };
        openApproveModal(fakeBtn);
      };
    }
    if (rejectBtn) {
      rejectBtn.style.display = isOpen ? 'inline-flex' : 'none';
      // wire same reject flow as the table action icons:
      rejectBtn.onclick = function(e){
        closeViewModal();
        const fakeBtn = {
          getAttribute: (k) => {
            switch(k) {
              case 'data-appid': return String(appId);
              case 'data-name': return studentName;
              case 'data-email': return st.email || '';
            }
            return null;
          }
        };
        openRejectModal(fakeBtn);
      };
    }

    // show modal
    if (overlay) {
      overlay.style.display = 'flex';
      overlay.setAttribute('aria-hidden', 'false');
      overlay.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  } catch (err) {
    console.error(err);
    alert('Request failed.');
  }
}

// close view modal
function closeViewModal() {
  const overlay = document.getElementById('viewOverlay');
  overlay.style.display = 'none';
  overlay.setAttribute('aria-hidden', 'true');
}

// notifications temporarily disabled (no action)
const notifBtn = document.getElementById('btnNotif');
if (notifBtn) {
  notifBtn.addEventListener('click', function(e){
    e.preventDefault();
    // intentionally left blank — notifications disabled for now
  });
}

// confirm before logout
const logoutBtn = document.getElementById('btnLogout');
if (logoutBtn) {
  logoutBtn.addEventListener('click', function(e){
    e.preventDefault();
    if (confirm('Are you sure you want to logout?')) {
      window.location.href = this.getAttribute('href');
    }
  });
}
</script>
<?php
// --- AFTER you load $offices (right after the block that builds $offices) ---
/* AUTO-REJECT: move pending -> rejected when pref2 is NULL and pref1 office is Full.
   This runs only when viewing the pending tab to avoid unexpected updates elsewhere. */
if ($tab === 'pending' && !empty($offices)) {
    $capacityCol = $capacityCol ?? null;

    // helper: get office capacity + filled (use users table: role='ojt' status IN ('approved','ongoing'))
    $getOfficeInfo = function($conn, $officeId) {
        if (!$officeId) return null;
        $s = $conn->prepare("SELECT office_name, current_limit FROM offices WHERE office_id = ? LIMIT 1");
        if (!$s) return null;
        $s->bind_param("i", $officeId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$r) return null;
        $officeName = $r['office_name'];
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) AS filled
            FROM users
            WHERE role = 'ojt' AND status IN ('approved','ongoing') AND office_name = ?
        ");
        if (!$stmt2) return ['office_name'=>$officeName,'capacity'=>is_null($r['current_limit'])?null:(int)$r['current_limit'],'filled'=>0];
        $stmt2->bind_param("s", $officeName);
        $stmt2->execute();
        $cnt = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        return [
            'office_name' => $officeName,
            'capacity' => is_null($r['current_limit']) ? null : (int)$r['current_limit'],
            'filled' => (int)($cnt['filled'] ?? 0)
        ];
    };

    // build list of office ids that are "full" (capacity !== null && filled >= capacity)
    $fullOfficeIds = [];
    foreach ($offices as $o) {
        $officeId = (int)($o['office_id'] ?? 0);
        $cap = isset($o['capacity']) ? (int)$o['capacity'] : null;
        if ($capacityCol === null || $cap === null) continue; // unlimited -> not full
        $info = $getOfficeInfo($conn, $officeId);
        $filled = $info ? (int)$info['filled'] : 0;
        if ($filled >= $cap) $fullOfficeIds[] = $officeId;
    }

    if (!empty($fullOfficeIds)) {
        $n = count($fullOfficeIds);
        $placeholders = implode(',', array_fill(0, $n, '?'));

        // fetch pending applications that are candidates for auto-reject:
        // A) pref2 NULL/0 and pref1 in full list
        // B) both pref1 AND pref2 in full list (both provided)
        $sql = "SELECT oa.application_id, oa.student_id, s.email, oa.office_preference1, oa.office_preference2
                FROM ojt_applications oa
                JOIN students s ON oa.student_id = s.student_id
                WHERE oa.status = 'pending'
                  AND (
                     ((oa.office_preference2 IS NULL OR oa.office_preference2 = 0) AND oa.office_preference1 IN ($placeholders))
                     OR
                     (oa.office_preference1 IN ($placeholders) AND oa.office_preference2 IN ($placeholders))
                  )";

        $stmtFind = $conn->prepare($sql);
        if ($stmtFind) {
            // bind types for 3 groups of $n integers
            $bindTypes = str_repeat('i', $n * 3);
            $bindParams = [];
            $bindParams[] = &$bindTypes;
            // first IN
            for ($i = 0; $i < $n; $i++) $bindParams[] = &$fullOfficeIds[$i];
            // second IN
            for ($i = 0; $i < $n; $i++) $bindParams[] = &$fullOfficeIds[$i];
            // third IN
            for ($i = 0; $i < $n; $i++) $bindParams[] = &$fullOfficeIds[$i];
            call_user_func_array([$stmtFind, 'bind_param'], $bindParams);
            $stmtFind->execute();
            $resPending = $stmtFind->get_result();
            $candidates = $resPending->fetch_all(MYSQLI_ASSOC);
            $stmtFind->close();

            if (!empty($candidates)) {
                $u = $conn->prepare("UPDATE ojt_applications SET status = 'rejected', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
                $mailHeaders = "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: OJTMS HR <no-reply@localhost>\r\n";
                $toActuallyReject = [];
                // prepare student update stmt once
                $updStudentStmt = $conn->prepare("UPDATE students SET reason = ? WHERE student_id = ?");

                foreach ($candidates as $rowCandidate) {
                    $appId = (int)$rowCandidate['application_id'];
                    $studentEmail = trim($rowCandidate['email'] ?? '');
                    $pref1 = isset($rowCandidate['office_preference1']) ? (int)$rowCandidate['office_preference1'] : 0;
                    $pref2 = isset($rowCandidate['office_preference2']) ? (int)$rowCandidate['office_preference2'] : 0;

                    // CASE: pref2 empty/null => before rejecting, re-check availability of pref1 using users/office capacity
                    if (($pref2 === 0 || $pref2 === null) && $pref1) {
                        $info1 = $getOfficeInfo($conn, $pref1);
                        $cap1 = $info1 ? $info1['capacity'] : null;
                        $filled1 = $info1 ? (int)$info1['filled'] : 0;
                        $available1 = ($cap1 === null) ? PHP_INT_MAX : max(0, $cap1 - $filled1);
                        // if available >= 1 -> do NOT auto-reject this row (skip)
                        if ($available1 >= 1) {
                          // skip auto-reject for this candidate
                          continue;
                        }
                        // else fallthrough to reject (no available slot)
                    }

                    // CASE: both pref1 and pref2 provided and both full -> reject (we already selected candidates with both in full list)
                    // For safety: if pref1/pref2 present but one has a slot now, skip rejecting accordingly.
                    if ($pref1 && $pref2) {
                        $info1 = $getOfficeInfo($conn, $pref1);
                        $info2 = $getOfficeInfo($conn, $pref2);
                        $cap1 = $info1 ? $info1['capacity'] : null; $filled1 = $info1 ? (int)$info1['filled'] : 0;
                        $cap2 = $info2 ? $info2['capacity'] : null; $filled2 = $info2 ? (int)$info2['filled'] : 0;
                        $avail1 = ($cap1 === null) ? PHP_INT_MAX : max(0, $cap1 - $filled1);
                        $avail2 = ($cap2 === null) ? PHP_INT_MAX : max(0, $cap2 - $filled2);
                        // if either now has available slot, skip reject (prefer to leave pending so HR can approve)
                        if ($avail1 >= 1 || $avail2 >= 1) continue;
                    }

                    // If reached here, we will auto-reject
                    // provide contextual remark and store into both application and student.reason
                    if (($pref2 === 0 || $pref2 === null) && $pref1) {
                        $remarks = "Auto-rejected: Preferred office has reached capacity and no second choice provided.";
                    } else {
                        $remarks = "Auto-rejected: Preferred office(s) have reached capacity.";
                    }
                    if ($u) {
                        $u->bind_param('si', $remarks, $appId);
                        $u->execute();
                    }

                    // send notification email to student (basic PHP mail)
                    if (!empty($studentEmail)) {
                        $subject = "OJT Application Update";
                        $message = "
                          <p>Dear student,</p>
                          <p>Your OJT application (ID: {$appId}) has been rejected.</p>
                          <p><strong>Reason:</strong> " . htmlspecialchars($remarks) . "</p>
                          <p>Regards,<br/>OJT-MS HR</p>
                        ";
                        // correct header string (no extra backslashes)
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        $headers .= "From: OJTMS HR <no-reply@localhost>\r\n";

                        $mailOk = @mail($studentEmail, $subject, $message, $headers);
                        if (!$mailOk) {
                            error_log("Auto-reject mail failed for app {$appId} to {$studentEmail}");
                        }
                    }
                } // end foreach candidates
                if ($updStudentStmt) $updStudentStmt->close();
                if ($u) $u->close();

                // refresh $apps so UI reflects the moved rows
                $stmtApps = $conn->prepare($q);
                if ($stmtApps) {
                    $stmtApps->bind_param("s", $statusFilter);
                    $stmtApps->execute();
                    $result = $stmtApps->get_result();
                    $apps = $result->fetch_all(MYSQLI_ASSOC);
                    $stmtApps->close();
                }
            } // end if (!empty($candidates))
        } // end if ($stmtFind)
    } // end if (!empty($fullOfficeIds))
} // end if ($tab === 'pending' && !empty($offices))
?>