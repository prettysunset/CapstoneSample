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
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role, office_name, avatar FROM users WHERE user_id = ? LIMIT 1");
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
           s.reason AS reason,
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

/* MOA server-side helper removed (MOA tab removed) */

// Office requests removed from UI. Server-side helper retained if needed in future.

function fetch_evaluations($conn){
  $rows = [];

  // Some environments may have an older `evaluations` schema without `rating_desc`.
  // Check for the column and build the SELECT list accordingly.
  $hasRatingDesc = false;
  $check = $conn->query("SHOW COLUMNS FROM evaluations LIKE 'rating_desc'");
  if ($check) { $hasRatingDesc = $check->num_rows > 0; $check->free(); }

  // Check if school_eval column exists in evaluations table
  $hasSchoolEval = false;
  $check2 = $conn->query("SHOW COLUMNS FROM evaluations LIKE 'school_eval'");
  if ($check2) { $hasSchoolEval = $check2->num_rows > 0; $check2->free(); }

  $cols = "e.eval_id, e.rating, e.feedback, e.hiring, e.date_evaluated, e.cert_serial";
  if ($hasRatingDesc) $cols .= ", e.rating_desc";
  if ($hasSchoolEval) $cols .= ", e.school_eval";
  $cols .= ", s.first_name AS student_first, s.last_name AS student_last, u.first_name AS eval_first, u.last_name AS eval_last, su.office_name AS student_office";

  $sql = "
    SELECT " . $cols . "
    FROM evaluations e
    LEFT JOIN students s ON e.student_id = s.student_id
    LEFT JOIN users u ON e.user_id = u.user_id
    LEFT JOIN users su ON s.user_id = su.user_id
    ORDER BY e.date_evaluated DESC
  ";

  $res = $conn->query($sql);
  if ($res){
    while ($r = $res->fetch_assoc()) {
      if (!isset($r['rating_desc'])) $r['rating_desc'] = null;
      if (!isset($r['school_eval'])) $r['school_eval'] = null;
      $rows[] = $r;
    }
    $res->free();
  }
  return $rows;
}

function fmtDate($d){ if (!$d) return '-'; $dt = date_create($d); return $dt ? $dt->format('F j, Y') : '-'; }
// AJAX: read-only evaluation details for the View icon in Evaluations tab
if (isset($_GET['ajax']) && $_GET['ajax'] === 'view_eval') {
  header('Content-Type: application/json');

  $eval_id = isset($_GET['eval_id']) ? (int)$_GET['eval_id'] : 0;
  if ($eval_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid eval_id']);
    exit;
  }

  try {
    $evaluation = null;
    $qEval = $conn->prepare("\n            SELECT e.*,\n                   s.student_id,\n                   s.user_id AS student_user_id,\n                   COALESCE(s.college, '') AS school,\n                   COALESCE(s.course, '') AS course,\n                   COALESCE(s.total_hours_required, 0) AS hours_required,\n                   COALESCE(s.first_name, '') AS student_first,\n                   COALESCE(s.last_name, '') AS student_last,\n                   COALESCE(ev.first_name, '') AS eval_first,\n                   COALESCE(ev.last_name, '') AS eval_last\n            FROM evaluations e\n            LEFT JOIN students s ON e.student_id = s.student_id\n            LEFT JOIN users ev ON e.user_id = ev.user_id\n            WHERE e.eval_id = ?\n            LIMIT 1\n        ");
    if (!$qEval) {
      throw new Exception('Failed to prepare evaluation query');
    }
    $qEval->bind_param('i', $eval_id);
    $qEval->execute();
    $evaluation = $qEval->get_result()->fetch_assoc() ?: null;
    $qEval->close();

    if (!$evaluation) {
      echo json_encode(['success' => false, 'message' => 'Evaluation not found']);
      exit;
    }

    $evaluation['hours_rendered'] = 0.0;
    $studentUserId = (int)($evaluation['student_user_id'] ?? 0);
    if ($studentUserId > 0) {
      $qHours = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60), 0) AS total FROM dtr WHERE student_id = ?");
      if ($qHours) {
        $qHours->bind_param('i', $studentUserId);
        $qHours->execute();
        $hRow = $qHours->get_result()->fetch_assoc() ?: [];
        $evaluation['hours_rendered'] = (float)($hRow['total'] ?? 0);
        $qHours->close();
      }
    }

    $responses = [];
    $sqlRespWithQ = "\n            SELECT er.question_key, er.question_order, er.score, q.qtext, q.category\n            FROM evaluation_responses er\n            LEFT JOIN evaluation_questions q\n              ON CONVERT(q.question_key USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(er.question_key USING utf8mb4) COLLATE utf8mb4_unicode_ci\n            WHERE er.eval_id = ?\n            ORDER BY COALESCE(q.sort_order, er.question_order), er.question_order\n        ";
    $qResp = $conn->prepare($sqlRespWithQ);
    if (!$qResp) {
      $qResp = $conn->prepare("\n                SELECT er.question_key, er.question_order, er.score, NULL AS qtext, NULL AS category\n                FROM evaluation_responses er\n                WHERE er.eval_id = ?\n                ORDER BY er.question_order\n            ");
    }
    if ($qResp) {
      $qResp->bind_param('i', $eval_id);
      $qResp->execute();
      $resResp = $qResp->get_result();
      while ($row = $resResp->fetch_assoc()) {
        $responses[] = $row;
      }
      $qResp->close();
    }

    echo json_encode(['success' => true, 'evaluation' => $evaluation, 'responses' => $responses]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    error_log('[hr_head_reports view_eval] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while loading evaluation']);
    exit;
  }
}

$students = fetch_students($conn);

// Only show students with these statuses in the Students tab
$allowed_statuses = ['evaluated','rejected','deactivated'];
$students = array_values(array_filter($students, function($s) use ($allowed_statuses) {
  $st = strtolower(trim((string)($s['student_status'] ?? '')));
  return in_array($st, $allowed_statuses, true);
}));

// NEW: override students[].hours_rendered with sum from dtr (dtr.student_id = users.user_id).
// Do not show any errors to users; log only on failure.
if (!empty($students)) {
    $stmtGetUser = $conn->prepare("SELECT user_id FROM students WHERE student_id = ? LIMIT 1");
    $stmtDtr = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ?");
    if ($stmtGetUser && $stmtDtr) {
        foreach ($students as &$st) {
            $sid = (int)($st['student_id'] ?? 0);
            $userId = null;
            $stmtGetUser->bind_param('i', $sid);
            if ($stmtGetUser->execute()) {
                $res = $stmtGetUser->get_result();
                $r = $res ? $res->fetch_assoc() : null;
                if ($r && isset($r['user_id'])) $userId = (int)$r['user_id'];
            }
            if (!empty($userId)) {
                $stmtDtr->bind_param('i', $userId);
                if ($stmtDtr->execute()) {
                    $resD = $stmtDtr->get_result();
                    $rd = $resD ? $resD->fetch_assoc() : null;
                    $total = isset($rd['total']) ? (float)$rd['total'] : 0.0;
                    // overwrite so existing table rendering uses this value
                    $st['hours_rendered'] = $total;
                }
            }
        }
        unset($st);
        $stmtGetUser->close();
        $stmtDtr->close();
    } else {
        // log only; do not show any UI message
        error_log('hr_head_reports: failed prepare for DTR override - ' . $conn->error);
    }
}

$offices = fetch_offices($conn);
$evaluations = fetch_evaluations($conn);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - Records</title>
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
        <?php $profileImg = !empty($user['avatar']) ? $user['avatar'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>
        <img src="<?php echo htmlspecialchars($profileImg); ?>" alt="Profile">
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
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
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
        Records
      </a>
      </div>
    <p style="margin-top:auto;font-weight:600">OJT-MS</p>
</div>
 

  <main class="main" role="main">
    <!-- top-right outline icons: notifications, calendar, settings, logout
         NOTE: same markup/placement as hr_head_accounts.php so icons align across pages -->
    <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a id="btnNotif" href="#" title="Notifications" aria-haspopup="dialog" aria-expanded="false" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;position:relative;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span class="notif-count" aria-hidden="true" style="position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#ef4444;color:#fff;font-size:11px;line-height:18px;text-align:center;display:none;">0</span>
      </a>
        <!-- calendar icon (clickable: opens calendar overlay) -->
        <button id="openCalendarBtn" title="Calendar" aria-label="Open calendar" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;border:0;cursor:pointer;padding:0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </button>
        <button id="btnSettings" type="button" title="Settings" aria-label="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;border:0;box-shadow:none;cursor:pointer;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path></svg>
        </button>
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

    <div class="card" role="region" aria-label="Records">
        <!-- header: Reports left, export top-right -->
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">
          <h2 style="margin:0;color:#2f3850">Records</h2>

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
            <!-- Offices and MOA tabs removed -->
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
              <option value="">Status</option>
              <option value="evaluated">Evaluated</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <!-- right: Evaluations-only filter (office) -->
          <div id="evaluationsFilters" style="display:none;gap:8px;align-items:center;flex:0 0 auto;">
            <select id="evalOfficeFilter" style="padding:8px;border-radius:8px;border:1px solid #ddd;background:#fff;min-width:180px;">
              <option value="">All offices</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= htmlspecialchars(strtolower($o['office_name'] ?? '')) ?>"><?= htmlspecialchars($o['office_name'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Offices and MOA specific filters removed -->
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
                  <td><?php
                    $st = strtolower(trim((string)($s['student_status'] ?? '')));
                    if ($st === 'rejected') {
                      // Do not display the stored reason in the Students tab; show only the status
                      echo htmlspecialchars('Rejected');
                    } else {
                      echo htmlspecialchars(ucfirst($s['student_status'] ?: ''));
                    }
                  ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Offices and MOA panels removed -->

      <!-- Office Requests panel removed -->

      <!-- NEW: Evaluations panel -->
      <div id="panel-evaluations" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblEvaluations">
            <thead>
              <tr>
                      <th style="text-align:center">Date Evaluated</th>
                      <th style="text-align:center">Serial No.</th>
                      <th style="text-align:center">Student Name</th>
                      <th style="text-align:center">Rating</th>
                      <th style="text-align:center">School Grade</th>
                      <th style="text-align:center">Hiring Decision</th>
                      <th style="text-align:center">Evaluator</th>
                      <th style="text-align:center">Office</th>
                      <th style="text-align:center">View</th>
                      <th style="text-align:center">Print Certificate</th>
                    </tr>
            </thead>
            <tbody>
              <?php if (empty($evaluations)): ?>
                <tr><td colspan="8" class="empty">No evaluations found.</td></tr>
              <?php else: foreach ($evaluations as $e): ?>
                <tr data-search="<?= htmlspecialchars(strtolower(($e['student_first'] ?? '') . ' ' . ($e['student_last'] ?? '') . ' ' . ($e['eval_first'] ?? '') . ' ' . ($e['eval_last'] ?? '') . ' ' . ($e['hiring'] ?? '') . ' ' . ($e['cert_serial'] ?? ''))) ?>">
                  <td style="text-align:center"><?= htmlspecialchars(fmtDate($e['date_evaluated'] ?? '')) ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($e['cert_serial'] ?? '-') ?></td>
                  <td style="text-align:center"><?= htmlspecialchars(trim(($e['student_first'] ?? '') . ' ' . ($e['student_last'] ?? ''))) ?: 'N/A' ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($e['rating_desc'] ?? '') ?></td>
                  <td style="text-align:center"><?php
                      if (isset($e['school_eval']) && $e['school_eval'] !== null && $e['school_eval'] !== '') {
                        echo htmlspecialchars(number_format((float)$e['school_eval'], 2, '.', ''));
                      } else {
                        echo '-';
                      }
                  ?></td>
                  <td style="text-align:center"><?php
                      $h = isset($e['hiring']) ? $e['hiring'] : null;
                      echo $h ? htmlspecialchars($h) : '-';
                  ?></td>
                  <td style="text-align:center"><?= htmlspecialchars(trim(($e['eval_first'] ?? '') . ' ' . ($e['eval_last'] ?? ''))) ?: 'N/A' ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($e['student_office'] ?? '-') ?></td>
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
<!-- Read-only Evaluation View Modal (same behavior as office_head_ojts.php) -->
<div id="viewEvalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;z-index:12000;">
  <div style="background:#fff;width:860px;max-width:95%;border-radius:8px;padding:18px;box-shadow:0 12px 40px rgba(0,0,0,0.2);max-height:88vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3 style="margin:0;font-size:16px;color:#111827;">Evaluation</h3>
      <button id="viewEvalClose" aria-label="Close" title="Close" style="width:32px;height:32px;border-radius:6px;border:0;background:#e6e9f2;cursor:pointer;font-size:22px;line-height:1;padding:0;">&times;</button>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;gap:12px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;flex-wrap:wrap;">
      <div>
        <div><strong>Name:</strong> <span id="viewEvalName">-</span></div>
        <div><strong>Course:</strong> <span id="viewEvalCourse">-</span></div>
      </div>
      <div style="text-align:right;">
        <div><strong>School:</strong> <span id="viewEvalSchool">-</span></div>
        <div><strong>Hours:</strong> <span id="viewEvalHours">-</span></div>
      </div>
    </div>

    <div id="viewEvalBody" style="min-height:120px;"></div>
  </div>
</div>

<script>
(function(){
  // helper: apply current search + filters to all panels (so search reflects immediately even when tab not active)
  function applyFilters() {
    const q = (document.getElementById('globalSearch')?.value || '').toLowerCase().trim();
    const officeVal = (document.getElementById('officeFilter')?.value || '').toLowerCase().trim();
    const evalOfficeVal = (document.getElementById('evalOfficeFilter')?.value || '').toLowerCase().trim();
    const statusVal = (document.getElementById('statusFilter')?.value || '').toLowerCase().trim();
    const moaStatusVal = (document.getElementById('moaStatusFilter')?.value || '').toLowerCase().trim();

    const norm = txt => (txt || '').toString().toLowerCase().trim();

    // handle every panel so filtering is reflected immediately across tabs
    document.querySelectorAll('.panel').forEach(visible => {
      const isStudents = visible.id === 'panel-students';
      const isOffices  = visible.id === 'panel-offices';
      const isMoa      = visible.id === 'panel-moa';
      const isRequests = false; // Office Requests removed
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
        } else if (isEvaluations) {
          const tds = tr.querySelectorAll('td');
          const officeText = norm(tds[6]?.textContent || '');
          if (evalOfficeVal) visibleByOffice = officeText.indexOf(evalOfficeVal) !== -1;
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
      const ps = document.getElementById('panel-students'); if (ps) ps.style.display = tab==='students' ? 'block' : 'none';
      const po = document.getElementById('panel-offices');  if (po) po.style.display = tab==='offices' ? 'block' : 'none';
      const pm = document.getElementById('panel-moa');      if (pm) pm.style.display = tab==='moa' ? 'block' : 'none';
      const pe = document.getElementById('panel-evaluations'); if (pe) pe.style.display = tab==='evaluations' ? 'block' : 'none';

       // show/hide students-only filters
       const sf = document.getElementById('studentsFilters');
       if (sf) sf.style.display = tab==='students' ? 'flex' : 'none';
      const ef = document.getElementById('evaluationsFilters');
      if (ef) ef.style.display = tab==='evaluations' ? 'flex' : 'none';
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
      const eofilter = document.getElementById('evalOfficeFilter');
      if (eofilter) eofilter.value = eofilter.value;
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
  const evalOfficeFilterEl = document.getElementById('evalOfficeFilter');
  if (evalOfficeFilterEl) evalOfficeFilterEl.addEventListener('change', applyFilters);
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
     const ef = document.getElementById('evaluationsFilters');
     if (ef) ef.style.display = active && active.getAttribute('data-tab') === 'evaluations' ? 'flex' : 'none';
     const of = document.getElementById('officesFilters');
     if (of) of.style.display = active && active.getAttribute('data-tab') === 'offices' ? 'flex' : 'none';
     const mf = document.getElementById('moaFilters');
     if (mf) mf.style.display = active && active.getAttribute('data-tab') === 'moa' ? 'flex' : 'none';
   })();
 
   // initial filter pass so table reflects any default selection / search
   applyFilters();
 
  // export active-tab table to CSV
   document.getElementById('exportBtn').addEventListener('click', function(){
     const activeTab = document.querySelector('.tabs button.active')?.getAttribute('data-tab') || 'students';

     let tableId = '';
     let excludeIdx = [];
     let fileName = 'reports_export.csv';

     if (activeTab === 'evaluations') {
       tableId = 'tblEvaluations';
       // Remove View and Print Certificate columns on export.
       excludeIdx = [7, 8];
       fileName = 'reports_evaluations.csv';
     } else {
       tableId = 'tblStudents';
       fileName = 'reports_students.csv';
     }

     const table = document.getElementById(tableId);
     if (!table) return alert('No data to export.');

     const rows = Array.from(table.querySelectorAll('tbody tr')).filter(tr => {
       if (tr.style.display === 'none') return false;
       const tds = tr.querySelectorAll('td');
       if (!tds.length) return false;
       if (tds.length === 1 && tds[0].classList.contains('empty')) return false;
       return true;
     });

     if (rows.length === 0) return alert('No rows to export.');

     const headers = Array.from(table.querySelectorAll('thead th'))
       .filter((_, idx) => excludeIdx.indexOf(idx) === -1)
       .map(th => th.textContent.trim());

     const data = [headers.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')];

     rows.forEach(tr => {
       const cells = Array.from(tr.querySelectorAll('td'))
         .filter((_, idx) => excludeIdx.indexOf(idx) === -1)
         .map(td => '"' + td.textContent.replace(/"/g, '""').trim() + '"');
       data.push(cells.join(','));
     });

     const csv = data.join('\n');
     const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
     const url = URL.createObjectURL(blob);
     const a = document.createElement('a');
     a.href = url;
     a.download = fileName;
     document.body.appendChild(a);
     a.click();
     a.remove();
     URL.revokeObjectURL(url);
   });
 })();
</script>

<script>
  // View Evaluation modal behavior for Evaluations table
  (function(){
    const modal = document.getElementById('viewEvalModal');
    if (!modal) return;

    const bodyEl = document.getElementById('viewEvalBody');
    const nameEl = document.getElementById('viewEvalName');
    const courseEl = document.getElementById('viewEvalCourse');
    const schoolEl = document.getElementById('viewEvalSchool');
    const hoursEl = document.getElementById('viewEvalHours');
    const closeBtn = document.getElementById('viewEvalClose');

    function escapeHtml(v) {
      return (v == null ? '' : String(v))
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function formatHours(v) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '-';
      const formatted = n.toFixed(2);
      return formatted.replace(/\.00$/, '') + ' hrs';
    }

    function openModal() {
      modal.style.display = 'flex';
    }

    function closeModal() {
      modal.style.display = 'none';
    }

    function splitFeedback(evaluation) {
      const out = {
        strengths: (evaluation.strengths || '').trim(),
        improvement: (evaluation.improvement || '').trim(),
        comments: (evaluation.comments || '').trim(),
        hiring: (evaluation.hiring || '').trim()
      };

      if (out.strengths || out.improvement || out.comments || out.hiring) {
        return out;
      }

      const fb = (evaluation.feedback || '').toString();
      const get = (label, nextLabel) => {
        const next = nextLabel ? '(?:\\n\\n' + nextLabel + ':|$)' : '$';
        const re = new RegExp(label + ':(.*?)' + next, 'is');
        const m = fb.match(re);
        return m ? m[1].trim() : '';
      };

      out.strengths = get('Strengths', 'Areas for improvement|Other comments|Hire decision');
      out.improvement = get('Areas for improvement', 'Other comments|Hire decision');
      out.comments = get('Other comments', 'Hire decision');
      out.hiring = get('Hire decision', null);
      return out;
    }

    function renderRows(title, label, rows) {
      if (!rows || rows.length === 0) return '';
      let html = '';
      html += '<div style="overflow:auto;margin-top:10px;">';
      html += '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
      html += '<thead><tr><th style="text-align:left;padding:10px;border:1px solid #e5e7eb;background:#f3f4f6;">' + escapeHtml(title) + '</th><th style="text-align:center;padding:10px;border:1px solid #e5e7eb;background:#f3f4f6;width:130px;">Score</th></tr></thead>';
      html += '<tbody>';
      rows.forEach(r => {
        const qText = r.qtext || r.question_key || label;
        const score = (r.score == null || r.score === '') ? '-' : r.score;
        html += '<tr>' +
          '<td style="padding:10px;border:1px solid #e5e7eb;">' + escapeHtml(qText) + '</td>' +
          '<td style="padding:10px;border:1px solid #e5e7eb;text-align:center;">' + escapeHtml(score) + '</td>' +
          '</tr>';
      });
      html += '</tbody></table></div>';
      return html;
    }

    async function loadEvaluation(evalId) {
      bodyEl.innerHTML = '<div style="padding:12px;color:#4b5563;">Loading evaluation...</div>';
      nameEl.textContent = '-';
      courseEl.textContent = '-';
      schoolEl.textContent = '-';
      hoursEl.textContent = '-';
      openModal();

      try {
        const res = await fetch('hr_head_reports.php?ajax=view_eval&eval_id=' + encodeURIComponent(evalId), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const payload = await res.json();
        if (!payload || !payload.success) {
          throw new Error((payload && payload.message) ? payload.message : 'Failed to load evaluation');
        }

        const evaluation = payload.evaluation || {};
        const responses = Array.isArray(payload.responses) ? payload.responses : [];
        const details = splitFeedback(evaluation);

        const studentName = (String(evaluation.student_first || '') + ' ' + String(evaluation.student_last || '')).trim() || 'N/A';
        const school = String(evaluation.school || '').trim() || 'N/A';
        const course = String(evaluation.course || '').trim() || 'N/A';

        nameEl.textContent = studentName;
        courseEl.textContent = course;
        schoolEl.textContent = school;
        const rendered = formatHours(evaluation.hours_rendered);
        const required = Number(evaluation.hours_required || 0);
        hoursEl.textContent = rendered === '-' ? '-' : (rendered + ' / ' + required + ' hrs');

        const competencyRows = [];
        const skillRows = [];
        const traitRows = [];
        const otherRows = [];

        responses.forEach(r => {
          const cat = String(r.category || '').toLowerCase();
          if (cat.indexOf('compet') !== -1) competencyRows.push(r);
          else if (cat.indexOf('skill') !== -1) skillRows.push(r);
          else if (cat.indexOf('trait') !== -1) traitRows.push(r);
          else otherRows.push(r);
        });

        let html = '';
        html += '<div style="margin-bottom:8px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;line-height:1.7;">';
        html += '<div><strong>Strengths:</strong> ' + escapeHtml(details.strengths || '-') + '</div>';
        html += '<div><strong>Areas for Improvement:</strong> ' + escapeHtml(details.improvement || '-') + '</div>';
        html += '<div><strong>Overall Performance Comments:</strong> ' + escapeHtml(details.comments || '-') + '</div>';
        html += '<div><strong>Hiring Consideration:</strong> ' + escapeHtml(details.hiring || '-') + '</div>';
        html += '</div>';

        if (responses.length === 0) {
          html += '<div style="padding:12px;color:#4b5563;">No evaluation response rows found.</div>';
        } else {
          html += renderRows('Competency', 'Competency', competencyRows);
          html += renderRows('Skill', 'Skill', skillRows);
          html += renderRows('Trait', 'Trait', traitRows);
          html += renderRows('Other', 'Question', otherRows);
        }

        bodyEl.innerHTML = html;
      } catch (err) {
        bodyEl.innerHTML = '<div style="padding:12px;color:#b91c1c;background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;">Unable to load evaluation details.</div>';
      }
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display === 'flex') closeModal(); });

    document.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.view-btn');
      if (!btn) return;
      const evalId = btn.getAttribute('data-eval-id');
      if (!evalId) {
        alert('Missing evaluation id');
        return;
      }
      loadEvaluation(evalId);
    });
  })();
</script>
<script>
  // Calendar modal open/close handlers (inline-styled overlay for Reports page)
  (function(){
    const openBtn = document.getElementById('openCalendarBtn');
    if (!openBtn) return;
    const calendarOverlay = document.createElement('div');
    calendarOverlay.id = 'calendarOverlay';
    calendarOverlay.style.position = 'fixed';
    calendarOverlay.style.top = '0';
    calendarOverlay.style.left = '0';
    calendarOverlay.style.right = '0';
    calendarOverlay.style.bottom = '0';
    calendarOverlay.style.display = 'none';
    calendarOverlay.style.alignItems = 'center';
    calendarOverlay.style.justifyContent = 'center';
    calendarOverlay.style.background = 'rgba(102, 51, 153, 0.18)';
    calendarOverlay.style.zIndex = '9999';
    calendarOverlay.setAttribute('role','dialog');
    calendarOverlay.setAttribute('aria-hidden','true');
    calendarOverlay.innerHTML = `
      <div style="width:100%;height:100vh;max-width:100%;max-height:100vh;padding:0;background:transparent;display:flex;align-items:center;justify-content:center;position:relative;">
        <iframe src="calendar.php" title="Calendar" style="width:100%;height:100%;border:0;display:block;"></iframe>
      </div>`;
    document.body.appendChild(calendarOverlay);
    function showCalendar(){ calendarOverlay.style.display = 'flex'; calendarOverlay.setAttribute('aria-hidden','false'); }
    function hideCalendar(){ calendarOverlay.style.display = 'none'; calendarOverlay.setAttribute('aria-hidden','true'); }
    window.closeCalendarOverlay = hideCalendar;
    openBtn.addEventListener('click', function(){ showCalendar(); });
    calendarOverlay.addEventListener('click', function(e){ if (e.target === calendarOverlay) hideCalendar(); });
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
    // handle print certificate button clicks (open printable certificate in new tab)
    document.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.print-btn');
      if (!btn) return;
      const evalId = btn.getAttribute('data-eval-id');
      if (!evalId) return alert('Missing evaluation id');
      const url = 'print_certificate.php?eval_id=' + encodeURIComponent(evalId);
      window.open(url, '_blank');
    });
  })();
</script>

<script>
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
<script>
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
</script>
</body>
</html>
