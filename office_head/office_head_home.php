<?php
session_start();
date_default_timezone_set('Asia/Manila');

// handle AJAX POST from modal (insert into office_requests) - no new file needed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    require_once __DIR__ . '/../conn.php';
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $office_id = isset($input['office_id']) ? (int)$input['office_id'] : 0;
    $new_limit = isset($input['new_limit']) ? (int)$input['new_limit'] : null;
    // reason is optional now; office head's change is auto-approved
    $reason = isset($input['reason']) ? trim($input['reason']) : '';

    if ($office_id <= 0 || $new_limit === null || $new_limit < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    // optional: verify authorization via office_heads table if exists
    $authorized = false;
    $tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $chk = $conn->prepare("SELECT 1 FROM office_heads WHERE user_id = ? AND office_id = ? LIMIT 1");
        $chk->bind_param('ii', $user_id, $office_id);
        $chk->execute();
        $r = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($r) $authorized = true;
    } else {
        // permissive fallback: allow if office exists
        $q = $conn->prepare("SELECT 1 FROM offices WHERE office_id = ? LIMIT 1");
        $q->bind_param('i', $office_id);
        $q->execute();
        $found = $q->get_result()->fetch_assoc();
        $q->close();
        if ($found) $authorized = true;
    }

    if (!$authorized) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized for this office']);
        exit;
    }

    // --- NEW: server-side check to prevent requesting less than current occupied OJTs (ongoing + approved) ---
    $officeName = '';
    $q = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ? LIMIT 1");
    if ($q) {
        $q->bind_param('i', $office_id);
        $q->execute();
        $orow = $q->get_result()->fetch_assoc();
        $q->close();
        $officeName = $orow['office_name'] ?? '';
    }

    if (!empty($officeName)) {
        // count ongoing (status = 'active') and approved (status = 'approved')
        $cntActive = 0;
        $cntApproved = 0;
 
        // count ongoing = users.status = 'ongoing' and approved = 'approved'
        $s1 = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'ojt' AND LOWER(TRIM(office_name)) LIKE ? AND status = 'ongoing'");
        if ($s1) {
            $like = '%' . mb_strtolower(trim($officeName)) . '%';
            $s1->bind_param('s', $like);
            $s1->execute();
            $cntActive = (int)($s1->get_result()->fetch_assoc()['c'] ?? 0);
            $s1->close();
        }
 
        $s2 = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'ojt' AND LOWER(TRIM(office_name)) LIKE ? AND status = 'approved'");
        if ($s2) {
            $s2->bind_param('s', $like);
            $s2->execute();
            $cntApproved = (int)($s2->get_result()->fetch_assoc()['c'] ?? 0);
            $s2->close();
        }
 
        $occupied = $cntActive + $cntApproved;
        if ($new_limit < $occupied) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Requested limit cannot be less than current number of OJTs ('.$occupied.').']);
            exit;
        }
        // Prevent no-op: reject if requested equals current office limit
        $qcur = $conn->prepare("SELECT COALESCE(current_limit, 0) AS current_limit FROM offices WHERE office_id = ? LIMIT 1");
        if ($qcur) {
            $qcur->bind_param('i', $office_id);
            $qcur->execute();
            $curRow = $qcur->get_result()->fetch_assoc();
            $qcur->close();
            $currentLimit = isset($curRow['current_limit']) ? (int)$curRow['current_limit'] : 0;
            if ($new_limit === $currentLimit) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Requested limit is the same as the current limit. No request submitted.']);
                exit;
            }
        }
    }
    // --- end server-side check ---

    // Office Head changes are applied immediately and set to 'approved'
    // Ensure we have the current limit value
    if (!isset($currentLimit)) {
      $qcur2 = $conn->prepare("SELECT COALESCE(current_limit, 0) AS current_limit FROM offices WHERE office_id = ? LIMIT 1");
      if ($qcur2) {
        $qcur2->bind_param('i', $office_id);
        $qcur2->execute();
        $curRow2 = $qcur2->get_result()->fetch_assoc();
        $qcur2->close();
        $currentLimit = isset($curRow2['current_limit']) ? (int)$curRow2['current_limit'] : 0;
      } else {
        $currentLimit = 0;
      }
    }

    $conn->begin_transaction();
    $ins = $conn->prepare("INSERT INTO office_requests (office_id, old_limit, new_limit, reason, status, date_requested, date_of_action) VALUES (?, ?, ?, ?, 'approved', NOW(), NOW())");
    if (!$ins) {
      $conn->rollback();
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'DB prepare failed (insert)']);
      exit;
    }
    $ins->bind_param('iiis', $office_id, $currentLimit, $new_limit, $reason);
    $ok1 = $ins->execute();
    $ins->close();

    // Update offices table so the approved capacity takes effect immediately
    $upd = $conn->prepare("UPDATE offices SET current_limit = ?, updated_limit = ?, requested_limit = NULL, reason = ?, status = 'Approved' WHERE office_id = ?");
    if (!$upd) {
      $conn->rollback();
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'DB prepare failed (update)']);
      exit;
    }
    $upd->bind_param('iisi', $new_limit, $new_limit, $reason, $office_id);
    $ok2 = $upd->execute();
    $upd->close();

    // Also ensure there are no lingering pending requests for this office
    // mark any pending requests as approved so the UI does not show "Request Pending"
    try {
      $clear = $conn->prepare("UPDATE office_requests SET status = 'approved', date_of_action = NOW() WHERE office_id = ? AND LOWER(status) = 'pending'");
      if ($clear) {
        $clear->bind_param('i', $office_id);
        $clear->execute();
        $clear->close();
      }
    } catch (Exception $e) {
      // non-fatal: continue and commit the successful changes
    }

    if ($ok1 && $ok2) {
      $conn->commit();
      echo json_encode(['success' => true, 'message' => 'Capacity changed']);
    } else {
      $conn->rollback();
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Failed to apply capacity change']);
    }
    exit;
}

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

// initialize counts (derive directly from users table — authoritative source for OJT account status)
$approved_ojts = 0;
$ongoing_ojts  = 0;
$completed_ojts = 0;

$office_name_for_query = $office['office_name'] ?? '';
if (!empty($office_name_for_query)) {
    // resolve a normalized LIKE param for office_name to avoid whitespace/case mismatches
    $officeNameForQuery = trim((string)($office['office_name'] ?? ''));
    $officeLike = '%' . mb_strtolower($officeNameForQuery) . '%';

    // Approved OJTs (users.role = 'ojt' AND users.status = 'approved')
    $approved_ojts = 0;
    $s1 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND LOWER(TRIM(office_name)) LIKE ? AND status = 'approved'");
    if ($s1) {
        $s1->bind_param('s', $officeLike);
        $s1->execute();
        $approved_ojts = (int)($s1->get_result()->fetch_assoc()['total'] ?? 0);
        $s1->close();
    }

    // Ongoing OJTs (users.role = 'ojt' AND users.status = 'ongoing')
    $ongoing_ojts = 0;
    $s2 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND LOWER(TRIM(office_name)) LIKE ? AND status = 'ongoing'");
    if ($s2) {
        $s2->bind_param('s', $officeLike);
        $s2->execute();
        $ongoing_ojts = (int)($s2->get_result()->fetch_assoc()['total'] ?? 0);
        $s2->close();
    }

    // Completed OJTs (treat 'completed' and 'inactive' as completed)
    $completed_ojts = 0;
    // ONLY count users with status = 'completed' (per your request)
    $s3 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND LOWER(TRIM(office_name)) LIKE ? AND status = 'completed'");
    if ($s3) {
        $s3->bind_param('s', $officeLike);
        $s3->execute();
        $completed_ojts = (int)($s3->get_result()->fetch_assoc()['total'] ?? 0);
        $s3->close();
    }
}

// compute available slots using offices.current_limit minus (ongoing + approved)
$curLimit = isset($office['current_limit']) ? (int)$office['current_limit'] : 0;
$available_slots = max($curLimit - ($ongoing_ojts + $approved_ojts), 0);
// --- end replacement ---

// counts (use correct role/status values from your schema)
// --- REPLACED: remove duplicate queries and use the authoritative counts computed above ---

// Use the authoritative counts calculated earlier:
// ongoing_ojts and approved_ojts were computed using case-insensitive LIKE on office_name
// expose ongoing as active_ojts for UI consistency
$active_ojts = $ongoing_ojts;
// $completed_ojts was already computed above (statuses 'completed','inactive')
 
// Pending student applications: count pending ojt_applications where this office is chosen
// Rule:
//  - Always count when this office is the 1st choice.
//  - Count when this office is the 2nd choice ONLY if the 1st-choice office is full
$pending_students = 0;
$office_id = (int)($office['office_id'] ?? 0);

// If this office has no available slots, do not count pending applications
if ($available_slots <= 0) {
    $pending_students = 0;
} else {
    if ($office_id > 0) {
        // 1) count pending apps where this office is first choice
        $p1 = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM ojt_applications
            WHERE status = 'pending' AND office_preference1 = ?
        ");
        if ($p1) {
            $p1->bind_param('i', $office_id);
            $p1->execute();
            $pending_students = (int)($p1->get_result()->fetch_assoc()['total'] ?? 0);
            $p1->close();
        }

        // 2) consider pending apps where this office is second choice, but only if their first choice is full
        $p2 = $conn->prepare("
            SELECT oa.application_id, oa.office_preference1
            FROM ojt_applications oa
            WHERE oa.status = 'pending' AND oa.office_preference2 = ?
        ");
        if ($p2) {
            $p2->bind_param('i', $office_id);
            $p2->execute();
            $res = $p2->get_result();
            if ($res) {
                // helper: checks if office (by id) is full (uses users counts like elsewhere)
                $isOfficeFull = function($checkOfficeId) use ($conn) {
                    $checkOfficeId = (int)$checkOfficeId;
                    if ($checkOfficeId <= 0) return false;
                    // get office row
                    $q = $conn->prepare("SELECT office_name, COALESCE(current_limit, NULL) AS capacity FROM offices WHERE office_id = ? LIMIT 1");
                    if (!$q) return false;
                    $q->bind_param('i', $checkOfficeId);
                    $q->execute();
                    $row = $q->get_result()->fetch_assoc();
                    $q->close();
                    if (!$row) return false;
                    $capacity = $row['capacity'] === null ? null : (int)$row['capacity'];
                    if (is_null($capacity)) return false; // unlimited -> not full

                    $officeName = $row['office_name'] ?? '';
                    if ($officeName === '') return false;

                    $likeParam = '%' . $officeName . '%';

                    // approved
                    $s1 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND office_name LIKE ? AND status = 'approved'");
                    if (!$s1) return false;
                    $s1->bind_param('s', $likeParam);
                    $s1->execute();
                    $approved = (int)($s1->get_result()->fetch_assoc()['total'] ?? 0);
                    $s1->close();

                    // ongoing/active
                    $s2 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND office_name LIKE ? AND status IN ('ongoing','active')");
                    if (!$s2) return false;
                    $s2->bind_param('s', $likeParam);
                    $s2->execute();
                    $ongoing = (int)($s2->get_result()->fetch_assoc()['total'] ?? 0);
                    $s2->close();

                    $available = $capacity - ($approved + $ongoing);
                    return ($available <= 0);
                };

                while ($row = $res->fetch_assoc()) {
                    $firstOfficeId = (int)($row['office_preference1'] ?? 0);
                    // If first choice is full, this pending app should count for current office
                    if ($firstOfficeId > 0 && $isOfficeFull($firstOfficeId)) {
                        $pending_students++;
                    }
                }
                $res->free();
            }
            $p2->close();
        }
    }
}

// Pending office requests for this office_id
$pending_office = 0;
$office_id = (int)($office['office_id'] ?? 0);
$s4 = $conn->prepare("SELECT COUNT(*) AS total FROM office_requests WHERE LOWER(status) = 'pending' AND office_id = ?");
if ($s4) {
    $s4->bind_param("i", $office_id);
    $s4->execute();
    $pending_office = (int)($s4->get_result()->fetch_assoc()['total'] ?? 0);
    $s4->close();
}

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
      grid-template-columns: repeat(5, 1fr);
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
    /* Overlay/modal styles for HR-style view modal */
    .overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,0.35);
      z-index: 10050;
      padding: 20px;
      box-sizing: border-box;
    }
    /* style the first child of the overlay as the modal card */
    #viewAppModal > * {
      background: #fff;
      border-radius: 10px;
      max-width: 900px;
      width: 100%;
      max-height: 90vh;
      overflow: auto;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      padding: 18px;
      box-sizing: border-box;
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

    <!-- Reports link removed per request -->

  </nav>

  <h3 style="position:absolute; bottom:20px; width:100%; text-align:center;">OJT-MS</h3>
</div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout
       NOTE: removed position:fixed to prevent overlapping; icons now flow with page
       and stay visible. -->
  <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a id="btnNotif" href="#" title="Notifications" aria-haspopup="dialog" aria-expanded="false" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;position:relative;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
          <span class="notif-count" aria-hidden="true" style="position:absolute;top:-4px;right:-4px;width:18px;height:18px;border-radius:999px;background:#ef4444;color:#fff;font-size:11px;line-height:1;font-weight:700;text-align:center;display:none;align-items:center;justify-content:center;">0</span>
      </a>
      <button id="btnSettings" type="button" title="Settings" aria-label="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;border:0;box-shadow:none;cursor:pointer;">
           <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
      </button>
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
        <p style="margin:0 0 6px 0">Approved OJTs</p>
        <h2 style="margin:0"><?= $approved_ojts ?></h2>
      </div>

      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Completed OJTs</p>
        <h2 style="margin:0"><?= $completed_ojts ?></h2>
      </div>
      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Available Slots</p>
        <h2 style="margin:0"><?= isset($available_slots) ? $available_slots : 0 ?></h2>
      </div>
      <div class="card" style="height:110px;min-height:90px;max-height:140px;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;overflow:hidden;">
        <p style="margin:0 0 6px 0">Capacity</p>
        <h2 style="margin:0"><?= isset($curLimit) ? $curLimit : (int)($office['current_limit'] ?? 0) ?></h2>
      </div>

    </div>

    <div class="table-section">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <!-- Edit button (always enabled) -->
            <div></div>
            <button id="btnEditOffice" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Change Capacity</button>
        </div> 

        <!-- Office info table removed; only Request button remains -->

        <!-- Edit Modal (updated: include editable Requested Limit + Reason) -->
        <div id="officeModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;">
                <div style="background:#fff;padding:18px;border-radius:8px;width:420px;box-shadow:0 8px 30px rgba(0,0,0,0.12);box-sizing:border-box;">
                <h4 style="margin:0 0 8px 0">Change Capacity</h4>
                <div style="display:grid;gap:8px;margin-top:8px">
                    <div style="display:block;margin:0">
                      <input id="m_requested_limit" type="number" min="0" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd;box-sizing:border-box;" required aria-label="New capacity" placeholder="">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px">
                        <button id="m_cancel" style="padding:8px 10px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancel</button>
                        <button id="m_request" style="padding:8px 12px;border-radius:6px;border:none;background:#5b5f89;color:#fff;cursor:pointer">Change Capacity</button>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="oh_office_id" value="<?= (int)$office['office_id'] ?>">
    </div>

    <!-- Applications: Pending / Approved tabs -->
    <div class="table-section">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:8px;align-items:center">
          <button id="tabPending" style="padding:8px 12px;border-radius:6px;border:1px solid #d0d0d0;background:#fff;cursor:pointer">Pending Applications</button>
          <button id="tabApproved" style="padding:8px 12px;border-radius:6px;border:1px solid #d0d0d0;background:transparent;cursor:pointer;color:#666">Approved OJTs</button>
        </div>
        <div></div>
      </div>

      <div style="margin-top:12px; overflow-x:auto;">
        <?php
        $officeId = (int)($office['office_id'] ?? 0);
        $apps = [];
        $approved_list = [];
        if ($officeId > 0) {
          // include student details and attachments to populate HR-style view modal
          $qr = $conn->prepare("SELECT oa.application_id, oa.date_submitted, oa.status,
            s.student_id, s.first_name, s.last_name, s.address, s.contact_number, s.email AS s_email, s.birthday,
            s.college, s.course, s.year_level, s.school_address, s.ojt_adviser, s.emergency_name, s.emergency_relation, s.emergency_contact,
            oa.office_preference1, oa.office_preference2, o1.office_name AS opt1, o2.office_name AS opt2,
            oa.letter_of_intent, oa.endorsement_letter, oa.resume, oa.picture
            FROM ojt_applications oa
            LEFT JOIN students s ON oa.student_id = s.student_id
            LEFT JOIN offices o1 ON oa.office_preference1 = o1.office_id
            LEFT JOIN offices o2 ON oa.office_preference2 = o2.office_id
            WHERE oa.status = 'pending' AND (oa.office_preference1 = ? OR oa.office_preference2 = ?) ORDER BY oa.date_submitted DESC, oa.application_id DESC LIMIT 200");
          if ($qr) {
            $qr->bind_param('ii', $officeId, $officeId);
            $qr->execute();
            $resr = $qr->get_result();
            while ($row = $resr->fetch_assoc()) $apps[] = $row;
            $qr->close();
          }

          // Approved OJTs: derive from `users` table where role='ojt' and status='approved'
          // match office by case-insensitive LIKE on office_name (consistent with above)
          $likeOffice = '%' . mb_strtolower(trim((string)($office['office_name'] ?? ''))) . '%';
          // join students to get course/year_level and other student details
          // Use student names from `students` table for display (prefer `students` first/last)
          $qa = $conn->prepare("SELECT u.user_id, s.first_name AS first_name, s.last_name AS last_name, u.email, u.office_name, u.status,
            s.student_id, s.address, s.contact_number, s.birthday, s.college, s.course, s.year_level, s.school_address, s.ojt_adviser,
            s.emergency_name, s.emergency_relation, s.emergency_contact
            FROM users u
            LEFT JOIN students s ON u.user_id = s.user_id
            WHERE u.role = 'ojt' AND LOWER(TRIM(u.office_name)) LIKE ? AND u.status = 'approved'
            ORDER BY s.last_name, s.first_name LIMIT 200");
          if ($qa) {
            $qa->bind_param('s', $likeOffice);
            $qa->execute();
            $ra = $qa->get_result();
            while ($r = $ra->fetch_assoc()) $approved_list[] = $r;
            $qa->close();
          }
        }
        ?>

        <table id="pendingAppsTable" style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
              <tr>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Date Submitted</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Name</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Address</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">1st Option</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">2nd Option</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($apps)): ?>
                <tr><td colspan="6" style="padding:12px;color:#666;text-align:center">No pending applications for this office.</td></tr>
              <?php else: ?>
                <?php foreach ($apps as $a):
                    $studentName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                ?>
                  <tr>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo !empty($a['date_submitted']) ? htmlspecialchars(date('M j, Y', strtotime($a['date_submitted']))) : '-'; ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($studentName); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($a['address'] ?? '-'); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($a['opt1'] ?? '-'); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($a['opt2'] ?? '-'); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;text-align:center;">
                      <button class="view-app" 
                        data-appid="<?= (int)$a['application_id'] ?>" 
                        data-name="<?= htmlspecialchars($studentName) ?>"
                        data-email="<?= htmlspecialchars($a['s_email'] ?? '') ?>"
                        data-date="<?= htmlspecialchars($a['date_submitted'] ?? '') ?>"
                        data-opt1="<?= htmlspecialchars($a['opt1'] ?? '') ?>"
                        data-opt2="<?= htmlspecialchars($a['opt2'] ?? '') ?>"
                        data-address="<?= htmlspecialchars($a['address'] ?? '') ?>"
                        data-phone="<?= htmlspecialchars($a['contact_number'] ?? '') ?>"
                        data-birthday="<?= htmlspecialchars($a['birthday'] ?? '') ?>"
                        data-college="<?= htmlspecialchars($a['college'] ?? '') ?>"
                        data-course="<?= htmlspecialchars($a['course'] ?? '') ?>"
                        data-year="<?= htmlspecialchars($a['year_level'] ?? '') ?>"
                        data-school_address="<?= htmlspecialchars($a['school_address'] ?? '') ?>"
                        data-adviser="<?= htmlspecialchars($a['ojt_adviser'] ?? '') ?>"
                        data-emg_name="<?= htmlspecialchars($a['emergency_name'] ?? '') ?>"
                        data-emg_relation="<?= htmlspecialchars($a['emergency_relation'] ?? '') ?>"
                        data-emg_contact="<?= htmlspecialchars($a['emergency_contact'] ?? '') ?>"
                        data-loi="<?= htmlspecialchars($a['letter_of_intent'] ?? '') ?>"
                        data-endorse="<?= htmlspecialchars($a['endorsement_letter'] ?? '') ?>"
                        data-resume="<?= htmlspecialchars($a['resume'] ?? '') ?>"
                        data-picture="<?= htmlspecialchars($a['picture'] ?? '') ?>"
                        style="background:transparent;border:0;color:#0b74de;cursor:pointer;font-size:18px" title="View">
                        👁️
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <!-- Approved OJTs table (hidden by default) -->
          <table id="approvedOjtsTable" style="width:100%; border-collapse:collapse; text-align:left; display:none; margin-top:12px;">
            <thead>
              <tr>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Name</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">School</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Course</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Year Level</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">View</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($approved_list)): ?>
                <tr><td colspan="5" style="padding:12px;color:#666;text-align:center">No approved OJTs for this office.</td></tr>
              <?php else: ?>
                <?php foreach ($approved_list as $ap):
                    $apName = trim(($ap['first_name'] ?? '') . ' ' . ($ap['last_name'] ?? ''));
                ?>
                  <tr>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($apName); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($ap['college'] ?? '-'); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($ap['course'] ?? '-'); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;"><?php echo htmlspecialchars($ap['year_level'] ?? '-'); ?></td>
                    <td style="padding:8px; border:1px solid #e0e0e0;text-align:center;">
                      <button class="view-app" 
                        data-appid="" 
                        data-name="<?= htmlspecialchars($apName) ?>"
                        data-email="<?= htmlspecialchars($ap['email'] ?? '') ?>"
                        data-date=""
                        data-opt1=""
                        data-opt2=""
                        data-address="<?= htmlspecialchars($ap['address'] ?? '') ?>"
                        data-phone="<?= htmlspecialchars($ap['contact_number'] ?? '') ?>"
                        data-birthday="<?= htmlspecialchars($ap['birthday'] ?? '') ?>"
                        data-college="<?= htmlspecialchars($ap['college'] ?? '') ?>"
                        data-course="<?= htmlspecialchars($ap['course'] ?? '') ?>"
                        data-year="<?= htmlspecialchars($ap['year_level'] ?? '') ?>"
                        data-school_address="<?= htmlspecialchars($ap['school_address'] ?? '') ?>"
                        data-adviser="<?= htmlspecialchars($ap['ojt_adviser'] ?? '') ?>"
                        data-emg_name="<?= htmlspecialchars($ap['emergency_name'] ?? '') ?>"
                        data-emg_relation="<?= htmlspecialchars($ap['emergency_relation'] ?? '') ?>"
                        data-emg_contact="<?= htmlspecialchars($ap['emergency_contact'] ?? '') ?>"
                        data-loi=""
                        data-endorse=""
                        data-resume=""
                        data-picture=""
                        style="background:transparent;border:0;color:#0b74de;cursor:pointer;font-size:18px" title="View">
                        👁️
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <script>
        (function(){
          var tabP = document.getElementById('tabPending');
          var tabA = document.getElementById('tabApproved');
          var tPending = document.getElementById('pendingAppsTable');
          var tApproved = document.getElementById('approvedOjtsTable');
          if (!tabP || !tabA || !tPending || !tApproved) return;
          function showPending(){
            tPending.style.display = '';
            tApproved.style.display = 'none';
            tabP.style.background = '#fff'; tabP.style.color='';
            tabA.style.background = 'transparent'; tabA.style.color='#666';
          }
          function showApproved(){
            tPending.style.display = 'none';
            tApproved.style.display = '';
            tabA.style.background = '#fff'; tabA.style.color='';
            tabP.style.background = 'transparent'; tabP.style.color='#666';
          }
          tabP.addEventListener('click', showPending);
          tabA.addEventListener('click', showApproved);
        })();
        </script>

        <!-- View Application Modal (HR-style) -->
        <div id="viewAppModal" class="overlay" style="display:none;align-items:center;justify-content:center;" role="dialog" aria-hidden="true">
          <div class="modal" style="width:760px;max-width:calc(100% - 40px);max-height:80vh;overflow:auto;padding:16px;">
            <div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding-bottom:8px;border-bottom:1px solid #eee">
              <div id="view_avatar" style="width:120px;height:120px;border-radius:50%;background:#e9e9e9;display:flex;align-items:center;justify-content:center;font-size:44px;color:#777">👤</div>
              <div style="text-align:center">
                <div id="view_name" style="font-weight:700;font-size:18px">Name</div>
                <div id="view_status" style="color:#666;font-size:13px">Status</div>
              </div>
            </div>

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
              .modal { position: relative; }
            </style>

            <div style="position:absolute; top:12px; right:12px;">
              <button class="btn-cancel" id="v_close" type="button" style="padding:8px 12px; border-radius:8px; border:none; background:#eee; color:#333; cursor:pointer;">Close</button>
            </div>
          </div>
        </div>

    </div>
    <script>
    (function(){
      function clearAttachments() {
        const c = document.getElementById('view_attachments');
        if (!c) return;
        c.innerHTML = '';
      }
      function addAttachment(label, url) {
        if (!url) return;
        const c = document.getElementById('view_attachments');
        if (!c) return;
        const a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = label;
        a.style.color = '#0b74de';
        c.appendChild(a);
      }

      function openModal(btn){
        const get = k => btn.getAttribute(k) || '';
        const name = get('data-name');
        const email = get('data-email');
        const address = get('data-address');
        const phone = get('data-phone');
        const birthday = get('data-birthday');
        const college = get('data-college');
        const course = get('data-course');
        const year = get('data-year');
        const school_address = get('data-school_address');
        const adviser = get('data-adviser');
        const emg_name = get('data-emg_name');
        const emg_relation = get('data-emg_relation');
        const emg_contact = get('data-emg_contact');
        const opt1 = get('data-opt1');
        const opt2 = get('data-opt2');
        const date = get('data-date');
        const loi = get('data-loi');
        const endorse = get('data-endorse');
        const resume = get('data-resume');
        const picture = get('data-picture');

        document.getElementById('view_name').textContent = name;
        document.getElementById('view_status').textContent = (opt1 ? ('1st: ' + opt1) : '') + (opt2 ? (' | 2nd: ' + opt2) : '');
        document.getElementById('view_address').textContent = address;
        document.getElementById('view_phone').textContent = phone;
        document.getElementById('view_email').textContent = email;
        document.getElementById('view_birthday').textContent = birthday;
        document.getElementById('view_college').textContent = college;
        document.getElementById('view_course').textContent = course;
        document.getElementById('view_year').textContent = year;
        document.getElementById('view_school_address').textContent = school_address;
        document.getElementById('view_adviser').textContent = adviser;
        document.getElementById('view_emg_name').textContent = emg_name;
        document.getElementById('view_emg_relation').textContent = emg_relation;
        document.getElementById('view_emg_contact').textContent = emg_contact;
        document.getElementById('view_age').textContent = '';

        // avatar placeholder
        const avatar = document.getElementById('view_avatar');
        if (avatar) avatar.innerHTML = '👤';

        clearAttachments();
        addAttachment('Letter of Intent', loi);
        addAttachment('Endorsement Letter', endorse);
        addAttachment('Resume', resume);
        if (picture) addAttachment('Picture', picture);

        const modal = document.getElementById('viewAppModal');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden','false');
      }

      function closeModal(){
        const modal = document.getElementById('viewAppModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden','true');
      }

      document.querySelectorAll('.view-app').forEach(btn => {
        btn.addEventListener('click', function(){ openModal(this); });
      });

      const vclose = document.getElementById('v_close');
      if (vclose) vclose.addEventListener('click', closeModal);
      const vmodal = document.getElementById('viewAppModal');
      if (vmodal) vmodal.addEventListener('click', function(e){ if (e.target === vmodal) closeModal(); });
    })();
    </script>
</div>
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

<script>
(function(){
  const btnEdit = document.getElementById('btnEditOffice');
  const modal = document.getElementById('officeModal');
  if (!btnEdit || !modal) return;

  // modal inputs
  const mCurrent = document.getElementById('m_current_limit');
  const mAvailable = document.getElementById('m_available_slots');
  const mRequested = document.getElementById('m_requested_limit');
  const mCancel = document.getElementById('m_cancel');
  const mRequest = document.getElementById('m_request');

  // server-provided constants for validation
  const OFFICE_ID = Number(document.getElementById('oh_office_id').value || 0);
  const CURRENT_LIMIT = <?= $curLimit ?>;
  const OCCUPIED = <?= ($active_ojts + $approved_ojts) ?>;

  // open modal
  btnEdit.addEventListener('click', function(e){
    e.preventDefault();
    if (btnEdit.disabled) return;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
    mRequested.focus();
  });

  // cancel/hide modal
  mCancel && mCancel.addEventListener('click', function(e){
    e.preventDefault();
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
  });

  // submit change (no reason required)
  mRequest && mRequest.addEventListener('click', function(e){
    e.preventDefault();
    const requestedRaw = (mRequested.value || '').trim();
    if (requestedRaw === ''){
      alert('Please enter the new capacity.');
      mRequested.focus();
      return;
    }
    const requested = Number(requestedRaw);
    if (isNaN(requested) || requested < 0){
      alert('Please enter a valid requested capacity (0 or greater).');
      mRequested.focus();
      return;
    }
    if (requested < OCCUPIED){
      alert('New capacity cannot be less than current number of OJTs (' + OCCUPIED + ').');
      mRequested.focus();
      return;
    }
    if (requested === CURRENT_LIMIT){
      alert('New capacity is the same as the current capacity. No change submitted.');
      mRequested.focus();
      return;
    }

    mRequest.disabled = true;
    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ office_id: OFFICE_ID, new_limit: requested })
    })
    .then(r => r.json())
    .then(j => {
      if (j && j.success){
        alert('Capacity changed.');
        location.reload();
      } else {
        alert('Change failed: ' + (j && j.message ? j.message : 'Unknown error'));
        mRequest.disabled = false;
      }
    })
    .catch(err => { console.error(err); alert('Change failed.'); mRequest.disabled = false; });
  });

  // close modal when clicking outside content
  modal.addEventListener('click', function(ev){ if (ev.target === modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); } });
})();

// Notification overlay (iframe to notif.php)
(function(){
  const notifBtn = document.getElementById('btnNotif');
  if (!notifBtn) return;
  const badge = notifBtn.querySelector('.notif-count');

  let overlay = document.getElementById('notifOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'notifOverlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.display = 'none';
    overlay.style.alignItems = 'flex-start';
    overlay.style.justifyContent = 'flex-end';
    overlay.style.padding = '18px';
    overlay.style.background = 'rgba(15, 23, 42, 0.25)';
    overlay.style.zIndex = '10050';
    overlay.innerHTML =
      '<div style="width:360px;max-width:calc(100% - 32px);height:600px;max-height:calc(100vh - 36px);background:#fff;border-radius:16px;box-shadow:0 18px 45px rgba(15, 23, 42, 0.18);overflow:hidden;">' +
      '<iframe src="notif.php?embed=1" title="Notifications" style="width:100%;height:100%;border:0;"></iframe>' +
      '</div>';
    document.body.appendChild(overlay);
  }

  notifBtn.setAttribute('aria-haspopup', 'dialog');
  notifBtn.setAttribute('aria-expanded', 'false');

  function setBadge(count) {
    if (!badge) return;
    const num = parseInt(count || 0, 10) || 0;
    if (num > 0) {
      badge.textContent = num;
      badge.style.display = 'inline-flex';
    } else {
      badge.textContent = '0';
      badge.style.display = 'none';
    }
  }

  try {
    const saved = localStorage.getItem('notifUnread');
    if (saved !== null) setBadge(saved);
  } catch (e) {
    // ignore storage errors
  }

  window.addEventListener('message', function(e){
    if (e && e.data && e.data.type === 'notif-count') {
      setBadge(e.data.unread);
    }
  });

  function openPanel() {
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    notifBtn.setAttribute('aria-expanded', 'true');
  }

  function closePanel() {
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
    notifBtn.setAttribute('aria-expanded', 'false');
  }

  window.closeNotifOverlay = closePanel;

  notifBtn.addEventListener('click', function(e){
    e.preventDefault();
    if (overlay.style.display === 'flex') {
      closePanel();
    } else {
      openPanel();
    }
  });

  overlay.addEventListener('click', function(e){
    if (e.target === overlay) closePanel();
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closePanel();
  });
})();

// Settings modal open/close handlers (iframe overlay)
    (function(){
      const openBtn = document.getElementById('btnSettings');
      if (!openBtn) return;
      const settingsOverlay = document.createElement('div');
      settingsOverlay.id = 'settingsOverlay';
      settingsOverlay.style.position = 'fixed';
      settingsOverlay.style.top = '0';
      settingsOverlay.style.left = '0';
      settingsOverlay.style.right = '0';
      settingsOverlay.style.bottom = '0';
      settingsOverlay.style.display = 'none';
      settingsOverlay.style.alignItems = 'center';
      settingsOverlay.style.justifyContent = 'center';
      settingsOverlay.style.background = 'rgba(102, 51, 153, 0.18)';
      settingsOverlay.style.zIndex = '9999';
      settingsOverlay.setAttribute('role','dialog');
      settingsOverlay.setAttribute('aria-hidden','true');

      settingsOverlay.innerHTML = `
        <div style="width:100%;height:100vh;max-width:100%;max-height:100vh;padding:0;background:transparent;display:flex;align-items:center;justify-content:center;position:relative;">
          <iframe src="settings.php" title="Settings" style="width:100%;height:100%;border:0;display:block;"></iframe>
        </div>`;

      document.body.appendChild(settingsOverlay);

      function showSettings(){ settingsOverlay.style.display = 'flex'; settingsOverlay.setAttribute('aria-hidden','false'); try{ openBtn.style.background = '#fff'; openBtn.style.boxShadow = '0 6px 18px rgba(0,0,0,0.06)'; }catch(e){} }
      function hideSettings(){ settingsOverlay.style.display = 'none'; settingsOverlay.setAttribute('aria-hidden','true'); try{ openBtn.style.background = 'transparent'; openBtn.style.boxShadow = 'none'; }catch(e){} }
      window.closeSettingsOverlay = hideSettings;

      openBtn.addEventListener('click', function(ev){ ev.preventDefault(); showSettings(); });
      settingsOverlay.addEventListener('click', function(e){ if (e.target === settingsOverlay) hideSettings(); });
    })();
    // listen for updates from the settings iframe and patch the sidebar/profile in-place
  (function(){
    window.addEventListener('message', function(e){
      try{
        var d = e && e.data ? e.data : null;
        if (!d || d.type !== 'profile-updated') return;
        if (typeof d.avatar !== 'undefined' && d.avatar) {
          var img = document.querySelector('.profile img');
          if (img) img.src = d.avatar;
        }
        if (typeof d.name !== 'undefined') {
          var h = document.querySelector('.profile h3');
          if (h) h.textContent = d.name;
        }
      }catch(err){}
    });
  })();
</script>
</body>
</html>