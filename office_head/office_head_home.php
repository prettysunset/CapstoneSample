<?php
session_start();
date_default_timezone_set('Asia/Manila');

// require DB connection (conn.php used elsewhere in project)
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ensure we have user's name; prefer session but fallback to users table
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($user_name === '') {
    $su = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) $user_name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? ''));
}
if ($user_name === '') $user_name = 'Office Head';

// find the office assigned to this office head via office_heads -> offices
$office = null;

// if office_heads table exists, use it; otherwise fallback to users.office_name
$tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s = $conn->prepare("
        SELECT o.* 
        FROM office_heads oh
        JOIN offices o ON oh.office_id = o.office_id
        WHERE oh.user_id = ?
        LIMIT 1
    ");
    $s->bind_param("i", $user_id);
    if ($s->execute()) {
        $office = $s->get_result()->fetch_assoc();
    }
    $s->close();
}

// fallback: try to find office by users.office_name if office_heads row missing or table absent
if (!$office) {
    $su = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $urow = $su->get_result()->fetch_assoc();
    $su->close();
    if (!empty($urow['office_name'])) {
        $office_name = $urow['office_name'];
        $q = $conn->prepare("SELECT * FROM offices WHERE office_name LIKE ? LIMIT 1");
        $like = "%{$office_name}%";
        $q->bind_param("s", $like);
        $q->execute();
        $office = $q->get_result()->fetch_assoc();
        $q->close();
    }
}

// safe defaults if no office found
if (!$office) {
    $office = [
        'office_id' => 0,
        'office_name' => 'Unknown Office',
        'current_limit' => 0,
        'requested_limit' => 0,
        'reason' => '',
        'status' => 'open'
    ];
}

// helper: return short display name (remove trailing " Office")
function short_office_name($name) {
    if (empty($name)) return '';
    // remove trailing " Office" (case-insensitive) and trim
    return preg_replace('/\s+Office\s*$/i', '', trim($name));
}

// display-only name
$office_display = short_office_name($office['office_name'] ?? 'Unknown Office');


// --- REPLACE: fetch office request/office info and compute counts using users -> students -> ojt_applications ---
// ensure office_id
$office_id = (int)($office['office_id'] ?? 0);

// prefer any pending office_requests for display (most recent)
$display_requested_limit = $office['requested_limit'] ?? null;
$display_reason = $office['reason'] ?? '';
$display_status = $office['status'] ?? '';

if ($office_id > 0) {
    $req = $conn->prepare("SELECT new_limit, reason, status, date_requested FROM office_requests WHERE office_id = ? AND status = 'pending' ORDER BY date_requested DESC LIMIT 1");
    $req->bind_param('i', $office_id);
    if ($req->execute()) {
        $rrow = $req->get_result()->fetch_assoc();
        if ($rrow) {
            // use pending request values for display
            $display_requested_limit = $rrow['new_limit'];
            $display_reason = $rrow['reason'];
            $display_status = $rrow['status']; // 'pending'
        } else {
            // fallback to offices table values already in $office
            $display_requested_limit = $office['requested_limit'] ?? $display_requested_limit;
            $display_reason = $office['reason'] ?? $display_reason;
            $display_status = $office['status'] ?? $display_status;
        }
    }
    $req->close();
}

// initialize counts
$approved_ojts = 0;
$ongoing_ojts = 0;
$completed_ojts = 0;

// 1) get OJT users that belong to this office (use exact office_name match)
$office_name_for_query = $office['office_name'] ?? '';
$user_ids = [];
$user_status_map = [];

if (!empty($office_name_for_query)) {
    $uStmt = $conn->prepare("SELECT user_id, status FROM users WHERE role = 'ojt' AND office_name = ?");
    $uStmt->bind_param('s', $office_name_for_query);
    if ($uStmt->execute()) {
        $ures = $uStmt->get_result();
        while ($u = $ures->fetch_assoc()) {
            $uid = (int)($u['user_id'] ?? 0);
            if ($uid > 0) {
                $user_ids[] = $uid;
                $user_status_map[$uid] = $u['status'] ?? '';
            }
        }
        $ures->free();
    }
    $uStmt->close();
}

if (!empty($user_ids)) {
    // 2) map users -> students
    $inUsers = implode(',', array_map('intval', $user_ids)); // safe ints from DB
    $student_map = []; // user_id => student row
    $student_ids = [];

    $q = "SELECT student_id, user_id, status FROM students WHERE user_id IN ($inUsers)";
    $res = $conn->query($q);
    if ($res) {
        while ($s = $res->fetch_assoc()) {
            $uid = (int)($s['user_id'] ?? 0);
            $sid = (int)($s['student_id'] ?? 0);
            if ($uid > 0 && $sid > 0) {
                $student_map[$uid] = $s;
                $student_ids[] = $sid;
            }
        }
        $res->free();
    }

    // 3) counts from ojt_applications for those student_ids
    if (!empty($student_ids)) {
        $inStudents = implode(',', array_map('intval', $student_ids));

        // approved (distinct students with approved application)
        $r = $conn->query("SELECT COUNT(DISTINCT student_id) AS total FROM ojt_applications WHERE student_id IN ($inStudents) AND status = 'approved'");
        $approved_ojts = (int)($r ? $r->fetch_assoc()['total'] : 0);
        if ($r) $r->free();

        // ongoing: approved applications whose student record shows ongoing
        $r2 = $conn->query("
            SELECT COUNT(DISTINCT oa.student_id) AS total
            FROM ojt_applications oa
            JOIN students s ON oa.student_id = s.student_id
            WHERE oa.student_id IN ($inStudents) AND oa.status = 'approved' AND s.status = 'ongoing'
        ");
        $ongoing_ojts = (int)($r2 ? $r2->fetch_assoc()['total'] : 0);
        if ($r2) $r2->free();
    } else {
        // no student rows found -> approved and ongoing remain 0
        $approved_ojts = 0;
        $ongoing_ojts = 0;
    }

    // 4) completed: prefer students.status = 'completed' else fallback to users.status = 'inactive'
    $completed_set = [];
    foreach ($user_ids as $uid) {
        if (isset($student_map[$uid]) && !empty($student_map[$uid]['status']) && strtolower(trim($student_map[$uid]['status'])) === 'completed') {
            $completed_set['s' . (int)$student_map[$uid]['student_id']] = true;
            continue;
        }
        // fallback: if user.status = 'inactive' treat as completed
        if (!empty($user_status_map[$uid]) && strtolower(trim($user_status_map[$uid])) === 'inactive') {
            $completed_set['u' . $uid] = true;
        }
    }
    $completed_ojts = count($completed_set);
}

// compute available slots using offices.current_limit (from $office) minus ongoing + approved
$curLimit = isset($office['current_limit']) ? (int)$office['current_limit'] : 0;
$available_slots = max($curLimit - ($ongoing_ojts + $approved_ojts), 0);
// --- end replacement ---

// counts (use correct role/status values from your schema)
$office_name_for_query = $office['office_name'] ?? '';

// Active OJTs - users.role = 'ojt', users.status = 'active'
$active_ojts = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND status = 'active' AND office_name = ?");
$stmt->bind_param("s", $office_name_for_query);
$stmt->execute();
$active_ojts = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Completed OJTs - if you track completed in students table use students.status = 'completed' else users.status
$completed_ojts = 0;
$s2 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND status = 'inactive' AND office_name = ?");
$s2->bind_param("s", $office_name_for_query);
$s2->execute();
$completed_ojts = (int)$s2->get_result()->fetch_assoc()['total'];
$s2->close();

// Pending student applications (if table exists)
$pending_students = 0;
// check if table exists to avoid exception on environments without that table
$tblCheck = $conn->query("SHOW TABLES LIKE 'student_applications'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s3 = $conn->prepare("SELECT COUNT(*) AS total FROM student_applications WHERE status = 'Pending' AND office_name = ?");
    $s3->bind_param("s", $office_name_for_query);
    $s3->execute();
    $pending_students = (int)$s3->get_result()->fetch_assoc()['total'];
    $s3->close();
} else {
    $pending_students = 0;
}

// Pending office requests for this office_id
$pending_office = 0;
$office_id = (int)($office['office_id'] ?? 0);
$s4 = $conn->prepare("SELECT COUNT(*) AS total FROM office_requests WHERE status = 'Pending' AND office_id = ?");
$s4->bind_param("i", $office_id);
$s4->execute();
$pending_office = (int)$s4->get_result()->fetch_assoc()['total'];
$s4->close();

// Fetch recent DTR rows for users in this office (most recent 20)
$late_dtr = $conn->prepare("
    SELECT u.first_name, u.last_name, d.am_in, d.am_out, d.pm_in, d.pm_out, d.hours
    FROM dtr d
    JOIN users u ON d.student_id = u.user_id
    WHERE u.office_name = ?
    ORDER BY d.log_date DESC
    LIMIT 20
");
$late_dtr->bind_param("s", $office_name_for_query);
$late_dtr->execute();
$late_dtr_res = $late_dtr->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Office Head | OJT-MS</title>
<style>
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        background-color: #f5f6fa;
    }
    .sidebar {
        width: 220px;
        background-color: #2f3459;
        height: 100vh;
        color: white;
        position: fixed;
        padding-top: 30px;
    }
    .sidebar h3 {
        text-align: center;
        margin-bottom: 5px;
    }
    .sidebar p {
        text-align: center;
        font-size: 14px;
        margin-top: 0;
    }
    .sidebar a {
        display: block;
        padding: 10px 20px;
        margin: 10px;
        color: black;
        border-radius: 20px;
        text-decoration: none;
    }
    .sidebar a.active {
        background-color: #fff;
    }
    .main {
        margin-left: 240px;
        padding: 20px;
    }
    .cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    .card {
        background: #dcdff5;
        padding: 15px;
        border-radius: 15px;
        text-align: center;
    }
    .card h2 { margin: 0; }
    .table-section {
        margin-top: 30px;
        background: white;
        border-radius: 15px;
        padding: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 8px;
    }
    th {
        background-color: #f1f1f1;
    }
    .edit-section {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        margin-top: 10px;
    }
    .edit-section input {
        text-align: center;
        border: 1px solid #ccc;
        padding: 5px;
        border-radius: 5px;
    }
</style>
</head>
<body>

<div class="sidebar">
  <div style="text-align:center;padding:18px 12px 8px;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fff;color:#2f3459;display:inline-flex;align-items:center;justify-content:center;font-weight:700;margin:6px auto;font-size:20px;">
      <?= htmlspecialchars(mb_strtoupper(substr(trim($user_name),0,1) ?: 'O')) ?>
    </div>
    <h3 style="margin:8px 0 4px;font-size:16px;"><?= htmlspecialchars($user_name) ?></h3>
    <p style="margin:0;font-size:13px;opacity:0.9">Office Head — <?= htmlspecialchars($office_display) ?></p>
  </div>

  <nav class="nav" style="margin-top:14px;display:flex;flex-direction:column;gap:8px;padding:0 12px;">
    <a href="office_head_home.php" class="active" title="Home" style="display:flex;align-items:center;gap:8px;color:#2f3459;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 11.5L12 4l9 7.5"></path>
        <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
      </svg>
      <span>Home</span>
    </a>

    <a href="office_head_ojts.php" title="OJTs" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="3"></circle>
        <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
      </svg>
      <span>OJTs</span>
    </a>

    <a href="office_head_dtr.php" title="DTR" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>DTR</span>
    </a>

    <a href="office_head_reports.php" title="Reports" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="10" width="4" height="10"></rect>
        <rect x="10" y="6" width="4" height="14"></rect>
        <rect x="17" y="2" width="4" height="18"></rect>
      </svg>
      <span>Reports</span>
    </a>

  </nav>

  <h3 style="position:absolute; bottom:20px; width:100%; text-align:center;">OJT-MS</h3>
</div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout
       NOTE: removed position:fixed to prevent overlapping; icons now flow with page
       and stay visible. -->
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

    <div class="cards">
      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Ongoing OJTs</p>
        <h2 style="margin:0"><?= $active_ojts ?></h2>
      </div>

      <!-- NEW: Approved card placed to the right of Ongoing -->
      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Approved</p>
        <h2 style="margin:0"><?= $approved_ojts ?></h2>
      </div>

      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Completed OJTs</p>
        <h2 style="margin:0"><?= $completed_ojts ?></h2>
      </div>
      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Pending Student Applications</p>
        <h2 style="margin:0"><?= $pending_students ?></h2>
      </div>

    </div>

    <div class="table-section">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <!-- keep only the Edit button (no heading text) -->
            <div></div>
            <?php if ((int)$pending_office > 0): ?>
              <button id="btnEditOffice" disabled style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;background:#f0f0f0;color:#666;cursor:not-allowed">Request Pending</button>
            <?php else: ?>
              <button id="btnEditOffice" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Edit</button>
            <?php endif; ?>
        </div>
        <input type="hidden" id="oh_has_pending" value="<?= (int)$pending_office ?>">

        <!-- Office Information table with headers -->
        <div style="margin-top:12px; overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse; text-align:center;">
            <thead>
              <tr>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Current Limit</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Ongoing OJTs</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Approved</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Available Slots</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Requested Limit</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Reason</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_current_limit" type="text" value="<?= htmlspecialchars($office['current_limit']) ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_active_ojts" type="text" value="<?= $active_ojts ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_approved_ojts" type="text" value="<?= $approved_ojts ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <?php
                    $curLimit = isset($office['current_limit']) ? (int)$office['current_limit'] : 0;
                    $available = max($curLimit - ($active_ojts + $approved_ojts), 0);
                  ?>
                  <input id="ci_available_slots" type="text" value="<?= $available ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_requested_limit" type="text" value="<?= htmlspecialchars($office['requested_limit'] ?? '') ?>" readonly style="width:90px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0; max-width:300px;">
                  <input id="ci_reason" type="text" value="<?= htmlspecialchars($office['reason'] ?? '') ?>" readonly style="width:100%;border:0;background:transparent;text-align:left;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_status" type="text" value="<?= htmlspecialchars(ucfirst($office['status'] ?? '')) ?>" readonly style="width:90px;border:0;background:transparent;text-align:center;">
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Edit Modal (unchanged) -->
        <div id="officeModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;">
            <div style="background:#fff;padding:18px;border-radius:8px;width:420px;box-shadow:0 8px 30px rgba(0,0,0,0.12);">
                <h4 style="margin:0 0 8px 0">Request Change - <?= htmlspecialchars($office_display) ?></h4>
                <div style="display:grid;gap:8px;margin-top:8px">
                    <label>Current Limit <input id="m_current_limit" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Ongoing OJTs <input id="m_active_ojts" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Approved <input id="m_approved_ojts" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Available Slots <input id="m_available_slots" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Requested Limit <input id="m_requested_limit" type="number" min="0" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Reason <textarea id="m_reason" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></textarea></label>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px">
                        <button id="m_cancel" style="padding:8px 10px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancel</button>
                        <button id="m_request" style="padding:8px 12px;border-radius:6px;border:none;background:#5b5f89;color:#fff;cursor:pointer">Request</button>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="oh_office_id" value="<?= (int)$office['office_id'] ?>">
    </div>

    <div class="table-section">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <h3>Late DTR Submissions</h3>
            <div style="display:flex;align-items:center;gap:8px">
                <!-- date picker (native calendar icon used by browser) -->
                <input id="lateDate" type="date" value="<?= date('Y-m-d') ?>" style="padding:6px;border-radius:6px;border:1px solid #ddd;">
            </div>
        </div>

        <!-- add explicit id's so JS updates only this table -->
        <table id="lateDtrTable">
            <thead>
              <tr>
                <th>NAME</th>
                <th colspan="2">A.M.</th>
                <th colspan="2">P.M.</th>
                <th>HOURS</th>
                <th>STATUS</th>
              </tr>
              <tr>
                <th></th>
                <th>ARRIVAL</th>
                <th>DEPARTURE</th>
                <th>ARRIVAL</th>
                <th>DEPARTURE</th>
                <th></th>
                <th></th>
              </tr>
            </thead>
            <tbody id="lateDtrTbody">
            <?php while ($row = $late_dtr_res->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= $row['am_in'] ? htmlspecialchars(date('H:i', strtotime($row['am_in']))) : '' ?></td>
                <td><?= $row['am_out'] ? htmlspecialchars(date('H:i', strtotime($row['am_out']))) : '' ?></td>
                <td><?= $row['pm_in'] ? htmlspecialchars(date('H:i', strtotime($row['pm_in']))) : '' ?></td>
                <td><?= $row['pm_out'] ? htmlspecialchars(date('H:i', strtotime($row['pm_out']))) : '' ?></td>
                <td><?= htmlspecialchars($row['hours']) ?></td>
                <td><?= (!empty($row['hours']) ? 'Validated' : '') ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
    (function(){
      const btn = document.getElementById('btnEditOffice');
      const modal = document.getElementById('officeModal');
      const cancel = document.getElementById('m_cancel');
      const requestBtn = document.getElementById('m_request');
      const officeId = document.getElementById('oh_office_id').value;

      function openModal(){
        document.getElementById('m_current_limit').value = document.getElementById('ci_current_limit').value;
        document.getElementById('m_active_ojts').value = document.getElementById('ci_active_ojts').value;
        document.getElementById('m_approved_ojts').value = document.getElementById('ci_approved_ojts').value;
        document.getElementById('m_available_slots').value = document.getElementById('ci_available_slots').value;
        document.getElementById('m_requested_limit').value = document.getElementById('ci_requested_limit').value || '';
        document.getElementById('m_reason').value = document.getElementById('ci_reason').value || '';
        modal.style.display = 'flex';
      }
      function closeModal(){ modal.style.display = 'none'; }

      btn.addEventListener('click', openModal);
      cancel.addEventListener('click', closeModal);

      requestBtn.addEventListener('click', function(){
        const reqLimitEl = document.getElementById('m_requested_limit');
        const reasonEl = document.getElementById('m_reason');
        const reqLimitRaw = (reqLimitEl.value || '').toString().trim();
        const reason = (reasonEl.value || '').toString().trim();

        // validation: required fields
        if (reqLimitRaw === '') {
          alert('Requested Limit is required.');
          reqLimitEl.focus();
          return;
        }
        const reqLimitNum = Number(reqLimitRaw);
        if (!Number.isFinite(reqLimitNum) || isNaN(reqLimitNum) || reqLimitNum < 0) {
          alert('Requested Limit must be a valid non-negative number.');
          reqLimitEl.focus();
          return;
        }
        if (reason === '') {
          alert('Reason is required.');
          reasonEl.focus();
          return;
        }

        // NEW: ensure requested_limit is not less than ongoing + approved
        // read values from modal inputs (fallback to current display inputs)
        const ongoingVal = Number(document.getElementById('m_active_ojts')?.value || document.getElementById('ci_active_ojts')?.value || 0);
        const approvedVal = Number(document.getElementById('m_approved_ojts')?.value || document.getElementById('ci_approved_ojts')?.value || 0);
        const minAllowed = ongoingVal + approvedVal;
        if (reqLimitNum < minAllowed) {
          alert('Requested Limit cannot be less than the sum of Ongoing + Approved OJTs (' + minAllowed + ').');
          reqLimitEl.focus();
          return;
        }

        // disable button during request
        requestBtn.disabled = true;
        requestBtn.textContent = 'Sending...';

        fetch('office_head_action.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({
            action: 'request_limit',
            office_id: parseInt(officeId,10),
            requested_limit: Math.floor(reqLimitNum),
            reason
          })
        }).then(r => r.json().catch(()=>null))
          .then(j => {
            if (!j) {
              alert('Request failed: invalid server response.');
              return;
            }
            if (!j.success) {
              // clearer handling for unknown action
              if (j.message && j.message.toLowerCase().indexOf('unknown action') !== -1) {
                alert('Server error: Unknown action. Please ensure office_head_action.php implements "request_limit".');
              } else {
                alert('Request failed: ' + (j.message || 'unknown error'));
              }
              return;
            }
            // update UI fields on success
            if (j.data) {
              document.getElementById('ci_requested_limit').value = j.data.requested_limit ?? document.getElementById('ci_requested_limit').value;
              document.getElementById('ci_reason').value = j.data.reason ?? document.getElementById('ci_reason').value;
              if (j.data.status) {
                document.getElementById('ci_status').value = (j.data.status.charAt(0).toUpperCase() + j.data.status.slice(1));
              }
            }
            closeModal();
          }).catch(e=>{
            console.error(e);
            alert('Request failed (network or server error).');
          }).finally(()=>{
            requestBtn.disabled = false;
            requestBtn.textContent = 'Request';
          });
       });
    })();
    </script>

    <script>
    (function(){
      const dateInput = document.getElementById('lateDate');
      const tbody = document.getElementById('lateDtrTbody'); // target the second table explicitly
      const officeId = document.getElementById('oh_office_id').value;

      function renderRows(rows) {
        tbody.innerHTML = '';
        if (!rows || rows.length === 0) {
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 7;
          td.textContent = 'No records found for selected date.';
          tr.appendChild(td);
          tbody.appendChild(tr);
          return;
        }
        rows.forEach(r=>{
          const tr = document.createElement('tr');
          function td(text){ const el = document.createElement('td'); el.textContent = text || ''; return el; }
          tr.appendChild(td((r.first_name || '') + ' ' + (r.last_name || '')));
          tr.appendChild(td(r.am_in ? r.am_in : ''));
          tr.appendChild(td(r.am_out ? r.am_out : ''));
          tr.appendChild(td(r.pm_in ? r.pm_in : ''));
          tr.appendChild(td(r.pm_out ? r.pm_out : ''));
          tr.appendChild(td(r.hours !== null && r.hours !== undefined ? String(r.hours) : ''));
          tr.appendChild(td(r.status || (r.hours ? 'Validated' : '')));
          tbody.appendChild(tr);
        });
      }

      function fetchForDate(d) {
        fetch('office_head_action.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action: 'get_late_dtr', office_id: parseInt(officeId,10), date: d })
        }).then(r=>r.json()).then(j=>{
          if (!j || !j.success) {
            console.error('Fetch late dtr failed', j);
            renderRows([]);
            return;
          }
          renderRows(j.data || []);
        }).catch(err=>{
          console.error(err);
          renderRows([]);
        });
      }

      dateInput.addEventListener('change', function(){ fetchForDate(this.value); });

      // initial load for today's date
      fetchForDate(dateInput.value);
    })();
    </script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const notifBtn = document.getElementById('btnNotif');
  const settingsBtn = document.getElementById('btnSettings');
  const logoutBtn = document.getElementById('btnLogout');

  if (notifBtn) {
    notifBtn.addEventListener('click', function(e){
      e.preventDefault();
      alert('Walang bagong notification ngayon.');
    });
  }

  if (settingsBtn) {
    settingsBtn.addEventListener('click', function(e){
      e.preventDefault();
      window.location.href = 'settings.php';
    });
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (!confirm('Log out?')) return;
      // replace history entry so back button won't return to protected pages
      window.location.replace(logoutBtn.getAttribute('href') || '../logout.php');
    }, { passive: true });
  }
});
</script>
</div>

</body>
</html>
