<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// prevent PHP notices/warnings breaking JSON responses; buffer output
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

// --- handle AJAX JSON submission in same file (no new file) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // try to parse JSON payload
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data) && isset($data['trainee_id'])) {

        // remove any buffered output (warnings, stray whitespace) before sending JSON header
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        // session already started above — DO NOT call session_start() again
        $evaluator_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $trainee = (int)$data['trainee_id'];
        $scores = $data['scores'] ?? [];
        $remarks = trim((string)($data['remarks'] ?? ''));
        $school_eval_raw = isset($data['school_eval']) ? $data['school_eval'] : null;

        // resolve student_id (students.user_id = trainee)
        $student_id = null;
        $q = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
        if ($q) {
            $q->bind_param("i", $trainee);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $q->close();
            if ($r && !empty($r['student_id'])) $student_id = (int)$r['student_id'];
        }

        if (!$student_id) {
            echo json_encode(['success' => false, 'message' => 'Trainee not found']);
            exit;
        }

        // validate school_eval presence (required) and numeric
        if ($school_eval_raw === null || $school_eval_raw === '') {
          echo json_encode(['success' => false, 'message' => 'School Evaluation Grade is required']);
          exit;
        }
        if (!is_numeric($school_eval_raw)) {
          echo json_encode(['success' => false, 'message' => 'School Evaluation Grade must be numeric']);
          exit;
        }
        $school_eval = floatval($school_eval_raw);

        // compute average of numeric scores (ignore NA / null)
        $sum = 0.0; $count = 0;
        foreach ($scores as $v) {
            if ($v === null) continue;
            if (is_string($v) && strtoupper($v) === 'NA') continue;
            if ($v === '') continue;
            // allow numeric strings and numbers
            if (is_numeric($v)) {
                $n = floatval($v);
                $sum += $n;
                $count++;
            }
        }

        if ($count > 0) {
            $avg = $sum / $count;
            $avgRounded = round($avg, 2);
            $avgStr = number_format($avgRounded, 2, '.', '');

            // map to description by nearest integer
            $map = [5 => 'Outstanding', 4 => 'Very Good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'];
            $roundedInt = (int) round($avgRounded);
            if ($roundedInt < 1) $roundedInt = 1;
            if ($roundedInt > 5) $roundedInt = 5;
            $desc = $map[$roundedInt] ?? 'N/A';

            $ratingDesc = $avgStr . ' | ' . $desc;
            $ratingValue = $avgRounded;
        } else {
            // no numeric ratings provided
            $ratingDesc = 'N/A | N/A';
            $desc = 'N/A';
            $avgRounded = null;
            $ratingValue = null;
        }

        // Begin DB transaction: insert evaluation and update statuses
        $conn->begin_transaction();
        $success = false;

        $ins = $conn->prepare("INSERT INTO evaluations (student_id, rating, rating_desc, feedback, school_eval, date_evaluated, user_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        if ($ins) {
            // bind: int, double (nullable), string, string, int
          $ins->bind_param("idssdi", $student_id, $ratingValue, $ratingDesc, $remarks, $school_eval, $evaluator_id);
            $insOk = $ins->execute();
            $ins->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'DB prepare failed (evaluations insert)']);
            $conn->rollback();
            exit;
        }

        if ($insOk) {
            // update students.status => mark evaluated
            $u1 = $conn->prepare("UPDATE students SET status = 'evaluated' WHERE student_id = ?");
            $u1Ok = true;
            if ($u1) { $u1->bind_param("i", $student_id); $u1Ok = $u1->execute(); $u1->close(); }

            // update users.status => mark evaluated (users.user_id = trainee)
            $u2 = $conn->prepare("UPDATE users SET status = 'evaluated' WHERE user_id = ?");
            $u2Ok = true;
            if ($u2) { $u2->bind_param("i", $trainee); $u2Ok = $u2->execute(); $u2->close(); }

            // update ojt_applications.status => evaluated (if application exists)
            $u3 = $conn->prepare("UPDATE ojt_applications SET status = 'evaluated', date_updated = NOW() WHERE student_id = ?");
            $u3Ok = true;
            if ($u3) { $u3->bind_param("i", $student_id); $u3Ok = $u3->execute(); $u3->close(); }

            if ($u1Ok && $u2Ok && $u3Ok) {
                $conn->commit();
                $success = true;
            } else {
                $conn->rollback();
            }
        } else {
            $conn->rollback();
        }

        if ($success) {
          // create notifications: HR (hr_head + hr_staff) and the OJT (trainee)
          // resolve trainee display name
          $trainee_name = 'Trainee';
          $sname = $conn->prepare("SELECT COALESCE(NULLIF(u.first_name,''), NULLIF(s.first_name,''), '') AS first_name, COALESCE(NULLIF(u.last_name,''), NULLIF(s.last_name,''), '') AS last_name FROM users u LEFT JOIN students s ON s.user_id = u.user_id WHERE u.user_id = ? LIMIT 1");
          if ($sname) {
            $sname->bind_param('i', $trainee);
            $sname->execute();
            $tr = $sname->get_result()->fetch_assoc();
            $sname->close();
            if ($tr) $trainee_name = trim(($tr['first_name'] ?? '') . ' ' . ($tr['last_name'] ?? '')) ?: 'Trainee';
          }

          // notify HR head and HR staff
          $hrRecipients = [];
          $r = $conn->query("SELECT user_id FROM users WHERE role IN ('hr_head','hr_staff') AND status = 'active'");
          if ($r) {
            while ($rr = $r->fetch_assoc()) $hrRecipients[] = (int)$rr['user_id'];
            $r->free();
          }
          $hrRecipients = array_values(array_unique(array_filter($hrRecipients, function($v){ return $v > 0; })));

          if (!empty($hrRecipients)) {
            $msg_hr = 'Evaluation Submitted: The performance evaluation for ' . $trainee_name . ' has been submitted.';
            $ins = $conn->prepare("INSERT INTO notifications (message) VALUES (?)");
            if ($ins) {
              $ins->bind_param('s', $msg_hr);
              $ins->execute();
              $nid = $conn->insert_id;
              $ins->close();

              $ins2 = $conn->prepare("INSERT INTO notification_users (notification_id, user_id, is_read) VALUES (?, ?, 0)");
              if ($ins2) {
                foreach ($hrRecipients as $uid) {
                  $ins2->bind_param('ii', $nid, $uid);
                  $ins2->execute();
                }
                $ins2->close();
              }
            }
          }

          // notify the trainee (OJT)
          $msg_ojt = 'Evaluation Submitted: Your performance evaluation has been submitted.';
          $insx = $conn->prepare("INSERT INTO notifications (message) VALUES (?)");
          if ($insx) {
            $insx->bind_param('s', $msg_ojt);
            $insx->execute();
            $nid2 = $conn->insert_id;
            $insx->close();

            $insu = $conn->prepare("INSERT INTO notification_users (notification_id, user_id, is_read) VALUES (?, ?, 0)");
            if ($insu) {
              $insu->bind_param('ii', $nid2, $trainee);
              $insu->execute();
              $insu->close();
            }
          }

          echo json_encode(['success' => true, 'message' => 'Evaluation saved and statuses updated', 'rating' => $avgRounded, 'rating_text' => $ratingDesc]);
          exit;
        } else {
          echo json_encode(['success' => false, 'message' => 'DB operation failed']);
          exit;
        }
    }
}

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id = (int)$_SESSION['user_id'];

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// resolve display name and office
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($user_name === '') {
    $su = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) $user_name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? ''));
}
if ($user_name === '') $user_name = 'Office Head';

// find office
$office = null;
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
    $s->execute();
    $office = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
}
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
        $office = $q->get_result()->fetch_assoc() ?: null;
        $q->close();
    }
}
if (!$office) {
    $office = ['office_id'=>0,'office_name'=>'Unknown Office'];
}
$office_display = preg_replace('/\s+Office\s*$/i', '', trim($office['office_name'] ?? 'Unknown Office'));

// fetch OJTs for this office (include students.status and hours columns)
$ojts = [];
$stmt = $conn->prepare("
    SELECT u.user_id,
           COALESCE(NULLIF(u.first_name, ''), NULLIF(s.first_name, ''), '') AS first_name,
           COALESCE(NULLIF(u.last_name, ''), NULLIF(s.last_name, ''), '') AS last_name,
           COALESCE(s.college, '') AS school,
           COALESCE(s.course, '') AS course,
           COALESCE(s.year_level, '') AS year_level,
           COALESCE(s.hours_rendered, 0) AS hours_completed,
           COALESCE(s.total_hours_required, 500) AS hours_required,
           COALESCE(s.status, '') AS student_status,
           COALESCE(u.status, '') AS user_status
    FROM users u
    LEFT JOIN students s ON s.user_id = u.user_id
    WHERE u.role = 'ojt' AND u.office_name LIKE ?
    ORDER BY u.last_name, u.first_name
    LIMIT 200
");
$like = '%' . ($office['office_name'] ?? '') . '%';
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $ojts[] = $r;
$stmt->close();

// Override hours_completed with accurate sum from dtr (hours + minutes)
if (!empty($ojts)) {
  $qDtr = $conn->prepare("SELECT IFNULL(SUM(hours),0) AS th, IFNULL(SUM(minutes),0) AS tm FROM dtr WHERE student_id = ?");
  if ($qDtr) {
    foreach ($ojts as &$row) {
      $sid = (int)($row['user_id'] ?? 0);
      $qDtr->bind_param('i', $sid);
      $qDtr->execute();
      $dres = $qDtr->get_result();
      $d = $dres ? $dres->fetch_assoc() : null;
      $th = isset($d['th']) ? (int)$d['th'] : 0;
      $tm = isset($d['tm']) ? (int)$d['tm'] : 0;
      // normalize minutes into hours
      $th += intdiv($tm, 60);
      $rem = $tm % 60;
      // numeric value used for comparisons (hours + fraction)
      $row['hours_completed'] = $th + ($rem / 60);
      // display as decimal-style minutes per request (e.g. 21 hours 4 minutes -> 21.4)
      $row['hours_display'] = $th . '.' . $rem;
      // keep raw parts if needed
      $row['hours_part_h'] = $th;
      $row['hours_part_m'] = $rem;
    }
    unset($row);
    $qDtr->close();
  }
}

// split into tabs:
// - Completed: explicitly marked 'completed' (prefer this)
// - For Evaluation: reached or surpassed required hours but not yet marked completed
// - Active: everything else
$for_eval = []; $active = []; $completedArr = [];
foreach ($ojts as $r) {
    $hc = floatval($r['hours_completed'] ?? 0);
    $hr = floatval($r['hours_required'] ?? 0);
    $student_status = strtolower(trim((string)($r['student_status'] ?? '')));
    $user_status = strtolower(trim((string)($r['user_status'] ?? '')));

    // Evaluated: primarily based on users.status; fallback to students.status
    if ($user_status === 'evaluated' || $student_status === 'evaluated') {
      $completedArr[] = $r;
      continue;
    }

    // For Evaluation: users with users.status = 'completed' OR those who reached required hours
    if ($user_status === 'completed' || ($hr > 0 && $hc >= $hr)) {
      $for_eval[] = $r;
      continue;
    }

    // Active/Ongoing: show only if users.status is exactly 'ongoing'
    if ($user_status === 'ongoing') {
      $active[] = $r;
      continue;
    }
    // otherwise do not include in any tab
}

// Load evaluated OJTs with latest evaluation remarks (override completedArr with richer rows)
$completedArr = [];
$q = $conn->prepare("
        SELECT u.user_id,
          COALESCE(NULLIF(u.first_name, ''), NULLIF(s.first_name, '')) AS first_name,
          COALESCE(NULLIF(u.last_name, ''), NULLIF(s.last_name, '')) AS last_name,
          COALESCE(s.college, '') AS school,
          COALESCE(s.course, '') AS course,
          COALESCE(s.year_level, '') AS year_level,
          COALESCE(s.hours_rendered, 0) AS hours_completed,
          COALESCE(s.total_hours_required, 500) AS hours_required,
          (SELECT rating_desc FROM evaluations ev2 WHERE ev2.student_id = s.student_id ORDER BY date_evaluated DESC, eval_id DESC LIMIT 1) AS remarks,
          (SELECT school_eval FROM evaluations ev3 WHERE ev3.student_id = s.student_id ORDER BY date_evaluated DESC, eval_id DESC LIMIT 1) AS school_eval
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.status = 'evaluated'
    ORDER BY u.last_name, u.first_name
");
if ($q) {
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) $completedArr[] = $row;
    $q->close();
}

// override completedArr hours with DTR sums (same decimal-style minutes display)
if (!empty($completedArr)) {
  $qDtr2 = $conn->prepare("SELECT IFNULL(SUM(hours),0) AS th, IFNULL(SUM(minutes),0) AS tm FROM dtr WHERE student_id = ?");
  if ($qDtr2) {
    foreach ($completedArr as &$c) {
      $sid = (int)($c['user_id'] ?? 0);
      $qDtr2->bind_param('i', $sid);
      $qDtr2->execute();
      $dres = $qDtr2->get_result();
      $d = $dres ? $dres->fetch_assoc() : null;
      $th = isset($d['th']) ? (int)$d['th'] : 0;
      $tm = isset($d['tm']) ? (int)$d['tm'] : 0;
      $th += intdiv($tm, 60);
      $rem = $tm % 60;
      $c['hours_completed'] = $th + ($rem / 60);
      $c['hours_display'] = $th . '.' . $rem;
      $c['hours_part_h'] = $th;
      $c['hours_part_m'] = $rem;
    }
    unset($c);
    $qDtr2->close();
  }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Office Head — OJT List</title>
<style>
  body{font-family:'Poppins',sans-serif;margin:0;background:#f5f6fa}
  .sidebar{width:220px;background:#2f3459;height:100vh;position:fixed;color:#fff;padding-top:30px}
  .main{margin-left:240px;padding:20px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(47,52,89,0.04)}
  .tabs{display:flex;gap:18px;border-bottom:1px solid #e6e9f2;padding-bottom:12px;margin-bottom:16px}
  .tab{padding:10px 18px;border-radius:8px;cursor:pointer;color:#6b6f8b}
  .tab.active{border-bottom:3px solid #4f4aa6;color:#111}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  .search{flex:1;padding:12px;border-radius:10px;border:1px solid #e6e9f2;background:#fff}
  .btn{padding:10px 14px;border-radius:20px;border:0;background:#4f4aa6;color:#fff;cursor:pointer}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:14px;text-align:left;border-bottom:1px solid #eef1f6;font-size:14px}
  thead th{background:#f5f7fb;color:#2f3459}
  .view-btn{background:transparent;border:0;cursor:pointer;color:#2f3459}
  .pill{background:#f0f0f0;padding:6px 10px;border-radius:16px;display:inline-block}
  .tab-panel{display:none}
  .tab-panel.active{display:block}
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
  /* match office_head_home.php top icons positioning & spacing */
  #top-icons { display:flex; justify-content:flex-end; gap:14px; align-items:center; margin:8px 0 12px 0; z-index:50; }

  /* icon style that matches top-right icons: no border, transparent background, same color */
  .icon-btn {
    background: transparent;
    border: 0;
    color: #2f3459; /* same as sidebar color */
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0; /* avoid emoji font sizing — use SVG inside */
    padding: 0;
    line-height: 1;
  }
  .icon-btn:active { transform: translateY(1px); }
  .icon-btn.small { width: 32px; height: 32px; font-size:0 }
  .icon-btn svg { width:18px; height:18px; stroke:currentColor; fill:none; }
  .evaluate-btn { margin-left: 8px; } /* keep spacing */
  /* make school-grade divider more visible */
  .eval-divider {
    border-top: 2px solid #c8cfe8; /* darker, more visible */
    padding-top: 14px;
    margin-top: 12px;
  }

  /* hide spinner arrows on number input for eval grade */
  #evalSchoolGrade::-webkit-outer-spin-button,
  #evalSchoolGrade::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  #evalSchoolGrade {
    -moz-appearance: textfield;
    appearance: textfield;
  }
  /* View modal styles (from HR head) */
  .view-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(16,24,40,0.28); z-index: 9999; padding: 18px; }
  /* lock page when modal open */
  body.modal-open { overflow: hidden; height: 100%; }

  .view-card {
    width: 880px;
    max-width: 94vw;
    border-radius: 20px;
    background: transparent;
    box-shadow: 0 22px 60px rgba(16,24,40,0.28);
    overflow: visible;
    position: relative;
    padding: 18px;
    font-family: 'Poppins', sans-serif;
    max-height: 80vh;
    display:flex;
    flex-direction:column;
  }

  .view-inner {
    background:#fff;
    border-radius:14px;
    padding:18px;
    box-shadow: none;
    border: 1px solid rgba(231,235,241,0.9);
    min-height: 460px;
    max-height: calc(80vh - 36px);
    display:flex;
    flex-direction:column;
    overflow:hidden; /* panel will scroll, not whole page */
  }
  .view-panel { flex:1 1 auto; min-height:360px; box-sizing:border-box; overflow:auto; padding-top:8px; }
  .view-close { position: absolute; right: 18px; top: 18px; width:36px;height:36px;border-radius:50%;background:#fff;border:0;box-shadow:0 6px 18px rgba(16,24,40,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px; z-index:10010; }
  .view-header { display:flex; gap:18px; align-items:center; margin-bottom:6px; }
  .view-avatar { width:96px;height:96px;border-radius:50%;background:#eceff3;flex:0 0 96px; display:flex;align-items:center;justify-content:center; overflow:hidden; }
  .view-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
  .view-name { font-size:20px; font-weight:800; color:#222e50; margin:0 0 6px 0; letter-spacing:0.2px; }
  .view-submeta { font-size:13px; color:#6b7280; display:flex; gap:12px; align-items:center; }
  .view-tools { display:flex; gap:12px; align-items:center; margin-top:8px; }
  .view-tabs { display:flex; gap:20px; align-items:center; margin-top:10px; padding-bottom:10px; }
  .view-tab { padding:6px 10px; cursor:pointer; border-radius:6px; color:#6b7280; font-weight:700; font-size:13px; }
  .view-tab.active { color:#1f2937; border-bottom:3px solid #344154; }
  .view-body { display:flex; gap:18px; margin-top:8px; align-items:flex-start; }
  .view-left { flex:1; padding:14px; border-radius:10px; min-width:320px; }
  .view-right { width:340px; min-width:260px; padding:14px; }
  .info-row{ display:flex; gap:10px; padding:6px 0; align-items:flex-start; }
  .info-label{ width:110px; font-weight:700; color:#222e50; font-size:13px; }
  .info-value{ color:#111827; font-weight:800; font-size:13px; line-height:1.1; }
  .emergency{ margin-top:12px; padding-top:8px; border-top:1px solid #eef2f6; }
  .donut { width:100px; height:100px; display:grid; place-items:center; }
  .donut svg { transform:rotate(-90deg); }
  /* lightweight on-page debug banner (temporary) */
  #oh-debug { position: fixed; left: 12px; bottom: 12px; background: rgba(16,24,40,0.9); color: #fff; padding:8px 10px; border-radius:8px; font-size:12px; z-index:12000; max-width:44vw; max-height:30vh; overflow:auto; box-shadow:0 6px 18px rgba(0,0,0,0.3); }
  #oh-debug .ln { margin:2px 0; }
</style>
</head>
<body>
<script>
// Safe stub so click handlers don't fail if the full implementation hasn't loaded
if (!window.openViewModal) {
  window.openViewModal = function(appId, userId, preRendered, preRequired) {
    try { console.log('stub openViewModal', { appId: appId, userId: userId }); } catch (e) {}
    try { if (window.ohDebugLog) window.ohDebugLog('stub openViewModal uid='+userId); } catch (e) {}
    var overlay = document.getElementById('viewOverlay');
    if (overlay) { overlay.style.display = 'flex'; overlay.setAttribute('aria-hidden','false'); }
    // ensure default tab visible
    try{
      if (overlay){
        // deactivate tabs
        Array.from(overlay.querySelectorAll('.view-tab')).forEach(t=>t.classList.remove('active'));
        const first = overlay.querySelector('.view-tab[data-tab="info"]') || overlay.querySelector('.view-tab');
        if (first) first.classList.add('active');
        Array.from(overlay.querySelectorAll('.view-panel')).forEach(p=>p.style.display='none');
        const panel = overlay.querySelector('#panel-info'); if (panel) panel.style.display='block';
      }
    }catch(e){}
    // best-effort: try to fetch basic user info if backend endpoint is reachable
    try {
      if (typeof fetch === 'function' && parseInt(userId,10) > 0) {
        fetch('../hr_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'get_user', user_id: parseInt(userId,10) }) })
          .then(function(r){ return r.json(); })
          .then(function(j){ if (j && j.success && j.data && j.data.student) {
            var s = j.data.student; var n = ((s.first_name||'') + ' ' + (s.last_name||'')).trim(); var nameEl = document.getElementById('view_name'); if (nameEl) nameEl.textContent = n || nameEl.textContent;
          }}).catch(function(){/* ignore */});
      }
    } catch (e) {}
  };
}
</script>

<div class="sidebar">
  <div style="text-align:center;padding:18px 12px 8px;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fff;color:#2f3459;display:inline-flex;align-items:center;justify-content:center;font-weight:700;margin:6px auto;font-size:20px;">
      <?= htmlspecialchars(mb_strtoupper(substr(trim($user_name),0,1) ?: 'O')) ?>
    </div>
    <h3 style="margin:8px 0 4px;font-size:16px;"><?= htmlspecialchars($user_name) ?></h3>
    <p style="margin:0;font-size:13px;opacity:0.9">Office Head — <?= htmlspecialchars($office_display) ?></p>
  </div>

  <nav class="nav" style="margin-top:14px;display:flex;flex-direction:column;gap:8px;padding:0 12px;">
    <a href="office_head_home.php" title="Home" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 11.5L12 4l9 7.5"></path>
        <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
      </svg>
      <span>Home</span>
    </a>

    <a href="office_head_ojts.php" class="active" title="OJTs" style="display:flex;align-items:center;gap:8px;color:#2f3459;">
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


  <div style="position:absolute;bottom:20px;width:100%;text-align:center;font-weight:700;padding-bottom:6px">OJT-MS</div>
</div>

<div id="oh-debug" aria-hidden="false" style="display:none"></div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout — moved inside .main to match office_head_home.php -->
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

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div>
        <div class="tabs" role="tablist" aria-label="OJTs tabs">
          <div class="tab active" data-target="panel-active">Ongoing</div>
          <div class="tab" data-target="panel-eval">For Evaluation</div>
          <div class="tab" data-target="panel-evaluated">Evaluated</div>
        </div>
        </div>
      <div style="display:flex;gap:12px;align-items:center">
        <!-- Create OJT removed per request -->
      </div>
    </div>

    <div class="controls">
      <input class="search" placeholder="Search" id="searchInput" />
      <select id="sortSelect" style="padding:10px;border-radius:10px;border:1px solid #e6e9f2;background:#fff">
        <option value="">Sort by</option>
        <option value="name">Name</option>
        <option value="hours">Hours</option>
      </select>
    </div>

    <div id="panel-active" class="tab-panel active">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>View</th></tr>
          </thead>
          <tbody>
            <?php if (empty($active)): ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No ongoing OJTs.</td></tr>
            <?php else: foreach ($active as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php
                  $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed'];
                  echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs');
                ?></td>
                <td>
                  <button class="view-btn icon-btn" data-id="<?php echo (int)$o['user_id']; ?>" title="View" onclick="(window.openViewModal||function(){}) (0, <?php echo (int)$o['user_id']; ?>)">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-eval" class="tab-panel">
      <h4 style="margin:10px 0 6px">For Evaluation</h4>
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (empty($for_eval)): ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No OJTs ready for evaluation.</td></tr>
            <?php else: foreach ($for_eval as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php
                  $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed'];
                  echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs');
                ?></td>
                <td style="white-space:nowrap">
                  <button class="view-btn icon-btn" data-id="<?php echo (int)$o['user_id']; ?>" title="View" onclick="(window.openViewModal||function(){}) (0, <?php echo (int)$o['user_id']; ?>)">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                  <button class="evaluate-btn icon-btn"
                    data-id="<?php echo (int)$o['user_id']; ?>"
                    data-name="<?php echo htmlspecialchars(trim($o['first_name'].' '.$o['last_name'])); ?>"
                    data-school="<?php echo htmlspecialchars($o['school'] ?: '-'); ?>"
                    data-course="<?php echo htmlspecialchars($o['course'] ?: '-'); ?>"
                    data-hours="<?php $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed']; echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs'); ?>"
                    title="Evaluate">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-evaluated" class="tab-panel">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>Remarks</th><th>School Grade</th><th>View</th></tr>
          </thead>
          <tbody>
            <?php if (empty($completedArr)): ?>
              <tr><td colspan="8" style="text-align:center;color:#8a8f9d;padding:18px;">No evaluated OJTs.</td></tr>
            <?php else: foreach ($completedArr as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php
                  $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed'];
                  echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs');
                ?></td>
                <td><?php echo htmlspecialchars($o['remarks'] ?? '-'); ?></td>
                <td><?php
                  if (isset($o['school_eval']) && $o['school_eval'] !== null && $o['school_eval'] !== '') {
                    echo htmlspecialchars(number_format((float)$o['school_eval'], 2, '.', ''));
                  } else {
                    echo '-';
                  }
                ?></td>
                <td>
                  <button class="view-btn icon-btn" data-id="<?php echo (int)$o['user_id']; ?>" title="View" onclick="(window.openViewModal||function(){}) (0, <?php echo (int)$o['user_id']; ?>)">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
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
</div>

<div id="evalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:9999;">
  <div style="background:#fff;width:820px;max-width:95%;border-radius:8px;padding:18px;box-shadow:0 8px 30px rgba(0,0,0,0.15);height:80vh;max-height:80vh;overflow-y:auto;">
    <!-- OJT info shown above the evaluation scale -->
    <div id="evalInfo" style="margin-bottom:12px;padding:10px;border-radius:6px;background:#f7f8fb;border:1px solid #eef1f6;color:#000;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <!-- left: name (bold) and course underneath -->
        <div style="font-size:15px;color:#000;min-width:220px;">
          <div>
            <span style="color:#000;">Name:</span>
            <span id="evalNameText" style="font-weight:700;margin-left:6px;">—</span>
          </div>
          <div id="evalCourse" style="margin-top:6px;color:#000;opacity:0.9;">Course: —</div>
        </div>
        <!-- right: school and hours stacked and right-aligned -->
        <div style="text-align:right;color:#000;opacity:0.85;min-width:200px;">
          <div id="evalSchool">School: —</div>
          <div id="evalHours" style="margin-top:6px;">Hours: —</div>
        </div>
      </div>
    </div>
     <h3 style="margin:0 0 8px 0;">Evaluation Scale</h3>
    <p style="margin:6px 0 12px 0;line-height:1.4;">
      Please rate the trainee's performance using the following scale.<br><br>
      <strong>5 - Outstanding:</strong> Consistently exceeds expectations. Performance is exceptional.<br>
      <strong>4 - Very Good:</strong> Consistently meets all expectations. Performance is of high quality.<br>
      <strong>3 - Good:</strong> Meets expectations most of the time. Performance is satisfactory.<br>
      <strong>2 - Fair:</strong> Sometimes fails to meet expectations. Requires improvement and supervision.<br>
      <strong>1 - Poor:</strong> Consistently fails to meet expectations. Performance is unacceptable.<br>
      <strong>N/A:</strong> Not Applicable. The trainee did not have the opportunity to demonstrate this skill.
    </p>

    <!-- Interactive evaluation grid (no image) -->
    <div style="overflow:auto;margin-top:6px;">
      <table id="evalGrid" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
          <tr style="background:#2f3459;color:#fff;">
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:left">Competency</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">5</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">4</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">3</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">2</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">1</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:60px">N/A</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $competencies = [
              'Application of Knowledge: Applies academic theories to practical work.',
              'Quality of Work: Produces accurate, thorough, and neat work.',
              'Job-Specific Skills: Performs tasks specific to the role effectively.',
              'Quantity of Work: Completes satisfactory volume of work on time.',
              'Learning & Adaptability: Learns new tasks, procedures, and systems quickly.'
            ];
            foreach ($competencies as $idx => $text):
              $key = 'c' . ($idx+1);
          ?>
          <tr data-key="<?= $key ?>">
            <td style="padding:10px;border:1px solid #eef1f6;vertical-align:middle;"><?= htmlspecialchars($text) ?></td>
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
                <!-- numeric columns show no text by default; data-score holds value -->
                <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="<?= $s ?>"
                  aria-label="Score <?= $s ?>"
                  style="width:36px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                </button>
              </td>
            <?php endfor; ?>
            <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
              <!-- keep N/A visible by default -->
              <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="NA"
                aria-label="Not applicable"
                style="width:44px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                N/A
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:10px;">
      <label style="display:block;margin-bottom:6px;font-weight:600">Feedback (optional)</label>
      <textarea id="evalRemarks" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></textarea>
    </div>

    <div class="eval-divider">
      <label style="display:block;margin-bottom:6px;font-weight:700">School Evaluation Grade</label>
      <input id="evalSchoolGrade" type="number" step="0.01" min="0" max="999" style="width:180px;padding:8px;border-radius:6px;border:1px solid #ddd" aria-label="School Evaluation Grade">
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
      <button id="evalCancel" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancel</button>
      <button id="evalSubmit" style="padding:8px 12px;border-radius:6px;border:0;background:#4f4aa6;color:#fff;cursor:pointer">Submit Evaluation</button>
    </div>
  </div>
</div>
<script>
  // store selections per modal open
  const _evalStore = {};

  // delegated handler for score cells
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.score-cell');
    if (!btn) return;

    const key = btn.getAttribute('data-key');
    const score = btn.getAttribute('data-score');

    // save selection
    _evalStore[key] = score;

    // visually mark row: clear all then set selected
    const row = btn.closest('tr[data-key]');
    if (!row) return;
    row.querySelectorAll('.score-cell').forEach(b => {
      b.style.background = '#fff';
      b.style.borderColor = '#cfd6ea';
      b.style.color = '#000';
      b.textContent = ''; // keep ALL buttons empty by default (including N/A)
    });

    // mark clicked
    btn.style.background = '#4f4aa6';
    btn.style.color = '#fff';
    btn.style.borderColor = '#4f4aa6';
    btn.textContent = '✓';
  });

  // open modal handler
  document.addEventListener('click', function (e) {
    const btn = e.target.closest && e.target.closest('.evaluate-btn');
    if (!btn) return;
    const traineeId = btn.getAttribute('data-id');
    const modal = document.getElementById('evalModal');
    modal.dataset.traineeId = traineeId;

    // populate info
    document.getElementById('evalNameText').textContent = (btn.getAttribute('data-name') || '—');
    document.getElementById('evalSchool').textContent = 'School: ' + (btn.getAttribute('data-school') || '—');
    document.getElementById('evalCourse').textContent = 'Course: ' + (btn.getAttribute('data-course') || '—');
    document.getElementById('evalHours').textContent = 'Hours: ' + (btn.getAttribute('data-hours') || '—');

    // reset previous selections/remarks
    Object.keys(_evalStore).forEach(k => delete _evalStore[k]);
    modal.querySelectorAll('.score-cell').forEach(b => {
      b.style.background = '#fff';
      b.style.borderColor = '#cfd6ea';
      b.style.color = '#000';
      b.textContent = ''; // empty by default (including N/A)
    });
    document.getElementById('evalRemarks').value = '';
    // reset school grade
    const gradeEl = document.getElementById('evalSchoolGrade'); if (gradeEl) gradeEl.value = '';

    modal.style.display = 'flex';
  });

  // close modal
  document.getElementById('evalCancel').addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('evalModal').style.display = 'none';
  });

  // submit evaluation — enforce all competencies selected
  document.getElementById('evalSubmit').addEventListener('click', function (e) {
    e.preventDefault();
    const modal = document.getElementById('evalModal');
    const traineeId = modal.dataset.traineeId;
    if (!traineeId) { alert('Trainee ID not set'); return; }

    // build payload
    const payload = { trainee_id: traineeId, remarks: (document.getElementById('evalRemarks').value || '').trim(), scores: {} };
    // include school evaluation grade
    const gradeEl = document.getElementById('evalSchoolGrade');
    const gradeValRaw = gradeEl ? (gradeEl.value || '').toString().trim() : '';
    payload.school_eval = gradeValRaw;
    const rows = Array.from(modal.querySelectorAll('tr[data-key]'));
    rows.forEach(row => {
      const key = row.getAttribute('data-key');
      payload.scores[key] = (typeof _evalStore[key] !== 'undefined') ? _evalStore[key] : null;
    });

    // REQUIRE: every competency must have a selection (numeric or NA)
    const allRated = rows.every(row => {
      const k = row.getAttribute('data-key');
      return typeof payload.scores[k] !== 'undefined' && payload.scores[k] !== null;
    });
    if (!allRated) {
      alert('Please rate all competencies (choose 5/4/3/2/1 or N/A) before submitting.');
      return;
    }

    // REQUIRE: School Evaluation Grade must be provided and numeric
    if (!gradeValRaw) {
      alert('Please enter the School Evaluation Grade.');
      return;
    }
    const gradeVal = parseFloat(gradeValRaw);
    if (isNaN(gradeVal)) {
      alert('School Evaluation Grade must be a number (decimals allowed).');
      return;
    }
    // normalize payload value to number
    payload.school_eval = gradeVal;

    // disable submit to avoid duplicates
    this.disabled = true;

    fetch('office_head_ojts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json().catch(() => ({ success: false, message: 'Invalid JSON response' })))
      .then(resp => {
        this.disabled = false;
        if (resp && resp.success) {
          // go to evaluated tab so the evaluated list is shown
          // use query param so server-rendered evaluated rows appear
          window.location.href = 'office_head_ojts.php?tab=evaluated';
        } else {
          alert('Submit failed: ' + (resp && resp.message ? resp.message : 'Unknown error'));
        }
      })
      .catch(err => {
        this.disabled = false;
        console.error(err);
        alert('Submit failed. Check console.');
      });
  });

  // restrict input for School Evaluation Grade: only digits and single dot,
  // and limit integer part to max 3 digits (allow decimals after dot)
  (function () {
    const g = document.getElementById('evalSchoolGrade');
    if (!g) return;

    function sanitize(val) {
      if (!val) return '';
      // remove non-digit/dot
      val = val.replace(/[^0-9.]/g, '');
      // collapse multiple dots into first
      const parts = val.split('.');
      const intPart = (parts[0] || '').slice(0, 3); // limit to 3 digits
      let decPart = parts.slice(1).join('');
      if (decPart.length) decPart = '.' + decPart;
      return intPart + decPart;
    }

    g.addEventListener('keydown', function (e) {
      const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
      if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
      if (e.key >= '0' && e.key <= '9') {
        // determine prospective value after key
        const selStart = this.selectionStart || 0;
        const selEnd = this.selectionEnd || 0;
        const cur = this.value || '';
        const next = cur.slice(0, selStart) + e.key + cur.slice(selEnd);
        const int = next.split('.')[0] || '';
        if (int.replace(/^0+/, '').length > 3 && int.length > 3) {
          // more than 3 digits in integer part -> prevent
          e.preventDefault();
        }
        return;
      }
      if (e.key === '.') {
        // allow dot only if not already present and integer part has at least 1 or up to 3 digits
        if ((this.value || '').includes('.')) e.preventDefault();
        return;
      }
      e.preventDefault();
    });

    g.addEventListener('paste', function (e) {
      e.preventDefault();
      const txt = (e.clipboardData || window.clipboardData).getData('text') || '';
      this.value = sanitize(txt);
    });

    g.addEventListener('input', function () {
      const v = sanitize(this.value);
      if (this.value !== v) this.value = v;
    });
  })();

  // tabs wiring
  (function () {
    function activateTabEl(tab) {
      if (!tab) return;
      const tabs = Array.from(document.querySelectorAll('.tabs .tab'));
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const target = tab.getAttribute('data-target');
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      const panel = document.getElementById(target);
      if (panel) panel.classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', function () {
      const container = document.querySelector('.tabs');
      if (!container) return;
      // try to honour ?tab=... or #panel-... so redirects show correct tab
      const params = new URLSearchParams(location.search);
      let tabParam = params.get('tab') || '';
      if (!tabParam && location.hash) {
        tabParam = location.hash.replace(/^#/, '');
      }
      if (tabParam) {
        let panelId = tabParam.startsWith('panel-') ? tabParam : ('panel-' + tabParam);
        const predefined = container.querySelector(`.tab[data-target="${panelId}"]`);
        if (predefined) activateTabEl(predefined);
      }

      // delegated click handler...
      container.addEventListener('click', function (e) {
        const tab = e.target.closest('.tab');
        if (!tab) return;
        activateTabEl(tab);
      });

      const tabEls = Array.from(container.querySelectorAll('.tab'));
      tabEls.forEach((t, idx, arr) => {
        t.setAttribute('role', 'tab');
        t.tabIndex = 0;
        t.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            activateTabEl(t);
            return;
          }
          if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') {
            ev.preventDefault();
            const next = arr[(idx + 1) % arr.length];
            next && next.focus();
          }
          if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            const prev = arr[(idx - 1 + arr.length) % arr.length];
            prev && prev.focus();
          }
        });
      });

      // activate initially marked tab or first tab
      const initially = container.querySelector('.tab.active') || container.querySelector('.tab');
      if (initially) activateTabEl(initially);
    });
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
</script>
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
<!-- View Application Modal (copied/adapted from hr_head to provide same view behavior) -->
<?php
// load MOA rows for client-side matching
$moa_rows = [];
$moa_q = $conn->query("SELECT school_name, moa_file FROM moa");
if ($moa_q) {
    while ($r = $moa_q->fetch_assoc()) {
        $sn = trim($r['school_name'] ?? '');
        $mf = trim($r['moa_file'] ?? '');
        if ($sn === '' || $mf === '') continue;
        $moa_rows[] = ['school_name' => $sn, 'moa_file' => $mf];
    }
    $moa_q->free();
}

// build officeHeads map from users table for role 'office_head'
$officeHeads = array();
$ohStmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, office_name FROM users WHERE role = 'office_head'");
if ($ohStmt) {
  $ohStmt->execute();
  $ohRes = $ohStmt->get_result();
  if ($ohRes) {
    while ($ohRow = $ohRes->fetch_assoc()) {
      $on = isset($ohRow['office_name']) ? trim($ohRow['office_name']) : '';
      if ($on === '') continue;
      $fn = (isset($ohRow['first_name']) ? $ohRow['first_name'] : '');
      $mn = (isset($ohRow['middle_name']) ? $ohRow['middle_name'] : '');
      $ln = (isset($ohRow['last_name']) ? $ohRow['last_name'] : '');
      $fullname = trim($fn . ' ' . $mn . ' ' . $ln);
      $email = isset($ohRow['email']) ? $ohRow['email'] : '';
      $officeHeads[$on] = array('name' => $fullname, 'email' => $email);
    }
    $ohRes->free();
  }
  $ohStmt->close();
}

// build user maps from the loaded OJT arrays so client can prefer users.status and office_name
$allUsers = array_merge($ojts, $for_eval, $completedArr);
$userStatusMap = [];
$userOfficeMap = [];
foreach ($allUsers as $u) {
    if (isset($u['user_id'])) {
        $uid = (int)$u['user_id'];
        $userStatusMap[$uid] = $u['user_status'] ?? $u['student_status'] ?? '';
        $userOfficeMap[$uid] = $u['office_name'] ?? '';
    }
}
?>
<div id="viewOverlay" class="view-overlay" aria-hidden="true" style="display:none;">
  <div class="view-card" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
    <button class="view-close" aria-label="Close modal" onclick="window.closeViewModal && window.closeViewModal()">✕</button>

    <!-- inner white container -->
    <div class="view-inner">
      <div class="view-header">
        <div class="view-avatar" id="view_avatar"> <!-- image inserted via JS --> </div>
        <div class="view-meta">
          <h2 class="view-name" id="view_name">Name Surname</h2>
          <div class="view-submeta" id="view_statusline">
            <span id="view_status_badge" style="display:inline-flex;align-items:center;gap:8px;font-weight:700;color:inherit">—</span>
            <span id="view_department" style="display:flex;align-items:center;gap=6px;color:#6b7280">Office</span>
          </div>

          <div class="view-tools" aria-hidden="true">
            <button class="tool-link" id="printDTR">Print DTR</button>
          </div>
        </div>
      </div>

      <div class="view-tabs" role="tablist" aria-label="View tabs">
        <div class="view-tab active" data-tab="info" onclick="switchViewTab(event)">Information</div>
        <div class="view-tab" data-tab="late" onclick="switchViewTab(event)">DTR</div>
        <div class="view-tab" data-tab="atts" onclick="switchViewTab(event)">Attachments</div>
        <div class="view-tab" data-tab="eval" onclick="switchViewTab(event)">Evaluation</div>
      </div>

      <div id="panel-info" class="view-panel" style="display:block;">
        <div class="view-body">
          <div class="view-left">
            <div style="display:flex;gap:12px;">
              <div style="flex:1">
                <div class="info-row"><div class="info-label">Age</div><div class="info-value" id="view_age">—</div></div>
                <div class="info-row"><div class="info-label">Birthday</div><div class="info-value" id="view_birthday">—</div></div>
                <div class="info-row"><div class="info-label">Address</div><div class="info-value" id="view_address">—</div></div>
                <div class="info-row"><div class="info-label">Phone</div><div class="info-value" id="view_phone">—</div></div>
                <div class="info-row"><div class="info-label">Email</div><div class="info-value" id="view_email">—</div></div>
              </div>
            </div>

            <div style="height:14px"></div>

            <div style="border-top:1px solid #f1f5f9;padding-top:12px;">
              <div class="info-row"><div class="info-label">College/University</div><div class="info-value" id="view_college">—</div></div>
              <div class="info-row"><div class="info-label">Course</div><div class="info-value" id="view_course">—</div></div>
              <div class="info-row"><div class="info-label">Year level</div><div class="info-value" id="view_year">—</div></div>
              <div class="info-row"><div class="info-label">School Address</div><div class="info-value" id="view_school_address">—</div></div>
              <div class="info-row"><div class="info-label">OJT Adviser</div><div class="info-value" id="view_adviser">—</div></div>
            </div>

            <div class="emergency">
              <div style="font-weight:700;margin-bottom:8px">Emergency Contact</div>
              <div class="info-row"><div class="info-label" style="width:120px">Name</div><div class="info-value" id="view_emg_name">—</div></div>
              <div class="info-row"><div class="info-label">Relationship</div><div class="info-value" id="view_emg_rel">—</div></div>
              <div class="info-row"><div class="info-label">Contact Number</div><div class="info-value" id="view_emg_contact">—</div></div>
            </div>
          </div>

            <div class="view-right">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <div style="font-weight:700">Progress</div>
            </div>

            <div class="progress-wrap" style="display:flex;flex-direction:row;gap:16px;align-items:center;justify-content:flex-start;margin-top:14px;">
              <div class="donut" id="view_donut" style="position:relative;flex:0 0 auto;">
              <svg width="120" height="120" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="48" stroke="#eef2f6" stroke-width="18" fill="none"></circle>
                <circle id="donut_fore" cx="60" cy="60" r="48" stroke="#10b981" stroke-width="18" stroke-linecap="round" fill="none" stroke-dasharray="302" stroke-dashoffset="302"></circle>
              </svg>
              <div id="view_percent" style="position:absolute;inset:0;display:grid;place-items:center;font-weight:800;color:#111827;font-size:16px;pointer-events:none">0%</div>
              </div>

              <div style="flex:1;min-width:0;max-width:320px;margin-left:12px;">
              <div style="font-size:14px;font-weight:700" id="view_hours_text">0 out of — hours</div>
              <div style="font-size:12px;color:#6b7280;margin-top:6px;white-space:pre-line" id="view_dates">Date Started: — 
              Expected End Date: —</div>
              </div>
            </div>

            <div class="assigned" id="view_assigned" style="margin-top:18px;display:flex;flex-direction:column;gap:8px;text-align:left;">
              <div style="font-weight:700">Assigned Office:</div>
              <div id="view_assigned_office">—</div>

              <div style="margin-top:6px;font-weight:700">Office Head:</div>
              <div id="view_office_head">—</div>

              <div style="margin-top:6px;font-weight:700">Email:</div>
              <div id="view_office_contact">—</div>
            </div>

            </div>
        </div> <!-- .view-body -->
      </div> <!-- #panel-info -->

      <div id="panel-late" class="view-panel" style="display:none;padding:12px 6px;">
        <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;">
          <div style="overflow:auto">
            <table aria-label="DTR" style="width:100%;border-collapse:collapse;font-size:14px">
              <thead>
                <tr style="background:#f3f4f6;color:#111">
                  <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center;font-weight:700;text-transform:uppercase">Date</th>
                  <th colspan="2" style="padding:10px;border:1px solid #eee;text-align:center">A.M.</th>
                  <th colspan="2" style="padding:10px;border:1px solid #eee;text-align:center">P.M.</th>
                  <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center">HOURS</th>
                    <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center">MINUTES</th>
                </tr>
                <tr style="background:#f3f4f6;color:#111">
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">ARRIVAL</th>
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">DEPARTURE</th>
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">ARRIVAL</th>
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">DEPARTURE</th>
                </tr>
              </thead>
              <tbody id="late_dtr_tbody">
                <tr class="empty">
                  <td colspan="7" style="padding:18px;text-align:center;color:#6b7280"></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div id="panel-atts" class="view-panel" style="display:none;padding:12px 6px;">
        <div id="attachments_full" style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;min-height:160px;">
          <div id="view_attachments_list" style="display:flex;flex-direction:column;gap:8px;"></div>
        </div>
      </div>

      <div id="panel-eval" class="view-panel" style="display:none;padding:12px 6px;">
        <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;min-height:160px;color:#6b7280" id="eval_full">
          Evaluation content here.
        </div>
      </div>
     </div> <!-- .view-inner -->
   </div> <!-- .view-card -->
 </div> <!-- #viewOverlay -->

    <script>
      (function(){
        const overlay = document.getElementById('viewOverlay');
        if (!overlay) return;
        // local tab wiring for the modal to ensure tabs switch even if other scripts fail
        const tabs = Array.from(overlay.querySelectorAll('.view-tab'));
        const panels = Array.from(overlay.querySelectorAll('.view-panel'));
        tabs.forEach(t => {
          t.addEventListener('click', function(ev){
            const name = t.getAttribute('data-tab');
            if (!name) return;
            tabs.forEach(x=>x.classList.remove('active'));
            t.classList.add('active');
            panels.forEach(p=>p.style.display = 'none');
            const panel = overlay.querySelector('#panel-' + name);
            if (panel) panel.style.display = 'block';
          });
        });
        // close button wiring
        const closeBtn = overlay.querySelector('.view-close');
        if (closeBtn) closeBtn.addEventListener('click', function(){ overlay.style.display='none'; overlay.setAttribute('aria-hidden','true'); });
      })();
    </script>

<script>
  window.moaBySchool = <?php echo json_encode($moa_rows, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
</script>
<script>
  window.userStatusMap = <?= json_encode($userStatusMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?> || {};
  window.userOfficeMap = <?= json_encode($userOfficeMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?> || {};
  window.officeHeadMap = <?= json_encode($officeHeads, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?> || {};
  (function(){ try { window.normalizedOfficeHeadMap = {}; for (const k in window.officeHeadMap) { if (!k) continue; const nk = k.toString().trim().toLowerCase().replace(/\s+/g,' '); if (!nk) continue; window.normalizedOfficeHeadMap[nk] = window.officeHeadMap[k]; } } catch (e) { window.normalizedOfficeHeadMap = {}; } })();
</script>

<script>
// reproduce the openViewModal + helpers from hr_head so Office Head view behaves the same
(function(){
  function positionNoop(){}
  // switchViewTab reused
  function switchViewTab(e){
    // support being called with an Event or a tab name string
    const tab = (typeof e === 'string') ? e : (e.currentTarget ? e.currentTarget.getAttribute('data-tab') : (e.target ? (e.target.getAttribute && e.target.getAttribute('data-tab')) || (e.target.closest ? (e.target.closest('.view-tab') ? e.target.closest('.view-tab').getAttribute('data-tab') : null) : null) : null));
    if (!tab) return;
    // update active tab button
    document.querySelectorAll('.view-tab').forEach(t=>t.classList.remove('active'));
    const btn = document.querySelector('.view-tab[data-tab="'+tab+'"]'); if (btn) btn.classList.add('active');
    // hide all panels and show the selected one
    document.querySelectorAll('.view-panel').forEach(p=>p.style.display='none');
    const panel = document.getElementById('panel-' + tab); if (panel) panel.style.display = 'block';
  }
  window.switchViewTab = switchViewTab;
  // attach robust listeners and remove inline onclicks to avoid duplicate handlers
  try {
    document.querySelectorAll('.view-tab').forEach(t=>{
      try{ t.removeAttribute && t.removeAttribute('onclick'); }catch(e){}
      t.addEventListener && t.addEventListener('click', switchViewTab);
    });
  } catch(e) {}

  window.showViewOverlay = function(){ const o=document.getElementById('viewOverlay'); if(o){ o.style.display='flex'; o.setAttribute('aria-hidden','false'); } };
  window.closeViewModal = function(){ const o=document.getElementById('viewOverlay'); if(o){ o.style.display='none'; o.setAttribute('aria-hidden','true'); } };

  // on-page debug logger
  window.ohDebugLog = function(msg){ try{ const el = document.getElementById('oh-debug'); if (!el) return; el.style.display='block'; const ln = document.createElement('div'); ln.className = 'ln'; ln.textContent = typeof msg === 'string' ? msg : JSON.stringify(msg); el.appendChild(ln); if (el.childNodes.length > 40) el.removeChild(el.childNodes[0]); }catch(e){} };

  (function(){ const overlay = document.getElementById('viewOverlay'); if (!overlay) return; overlay.addEventListener('click', function(e){ if (e.target === overlay) window.closeViewModal(); }); document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') window.closeViewModal(); }); })();

  function setDonut(percent){ percent = Math.max(0, Math.min(100, Number(percent) || 0)); const circle = document.getElementById('donut_fore'); const radius = 48; const circumference = 2 * Math.PI * radius; const offset = circumference - (percent / 100) * circumference; try{ circle.style.strokeDasharray = circumference; circle.style.strokeDashoffset = offset; }catch(e){} document.getElementById('view_percent').textContent = Math.round(percent) + '%'; }

  window.openViewModal = async function(appId, userId, preRendered, preRequired){
    try{ console.log('openViewModal called', { appId: appId, userId: userId, time: new Date().toISOString() }); if (window.ohDebugLog) window.ohDebugLog('openViewModal called uid='+userId); }catch(e){}
    showViewOverlay();
    ['view_name','view_age','view_birthday','view_address','view_phone','view_email','view_college','view_course','view_year','view_school_address','view_adviser','view_emg_name','view_emg_rel','view_emg_contact','view_hours_text','view_dates','view_assigned_office','view_office_head','view_office_contact','view_attachments_list'].forEach(id=>{ const el=document.getElementById(id); if(el) el.textContent='—'; });
    const avatarEl = document.getElementById('view_avatar'); if (avatarEl) avatarEl.innerHTML='👤';

    try{
      console.log('openViewModal: fetching details for', { appId: appId, userId: userId });
      let payload;
      if (parseInt(appId,10) > 0) payload = { action:'get_application', application_id: parseInt(appId,10) };
      else if (parseInt(userId,10) > 0) payload = { action:'get_user', user_id: parseInt(userId,10) };
      else { alert('No application or user id available.'); closeViewModal(); return; }

      const res = await fetch('../hr_actions.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      const json = await res.json(); if (!json.success) { alert('Failed to load details'); closeViewModal(); return; }
      const d = json.data; const s = d.student || {};

      document.getElementById('view_name').textContent = ((s.first_name||'') + ' ' + (s.last_name||'')).trim() || 'N/A';
      // status badge: prefer embedded userStatusMap
      (function(){ const statusBadgeEl = document.getElementById('view_status_badge'); let stRaw = (d.status || ''); try{ if (window.userStatusMap && userId && (window.userStatusMap[userId] !== undefined && window.userStatusMap[userId] !== null)) stRaw = window.userStatusMap[userId]; }catch(e){} const st = (stRaw||'').toString().trim().toLowerCase(); if (!st) statusBadgeEl.style.display='none'; else { let label = st.charAt(0).toUpperCase()+st.slice(1); statusBadgeEl.style.display='inline-flex'; statusBadgeEl.style.color=''; statusBadgeEl.textContent = label; }})();

      let assignedOffice = null; try{ if (window.userOfficeMap && userId && window.userOfficeMap[userId]) assignedOffice = window.userOfficeMap[userId]; }catch(e){ assignedOffice = null; }
      assignedOffice = assignedOffice || d.office1 || d.office || '';
      document.getElementById('view_department').textContent = assignedOffice || '—';

      if (d.picture){ avatarEl.innerHTML=''; const img = document.createElement('img'); img.src = '../' + d.picture; img.alt = 'avatar'; avatarEl.appendChild(img); }

      document.getElementById('view_age').textContent = s.age || '—';
      document.getElementById('view_birthday').textContent = s.birthday || (s.birthdate||'—');
      document.getElementById('view_address').textContent = s.address || s.school_address || '—';
      document.getElementById('view_phone').textContent = s.contact_number || '—';
      document.getElementById('view_email').textContent = s.email || '—';

      document.getElementById('view_college').textContent = s.college || '—';
      document.getElementById('view_course').textContent = s.course || '—';
      document.getElementById('view_year').textContent = s.year_level || '—';
      document.getElementById('view_school_address').textContent = s.school_address || '—';
      document.getElementById('view_adviser').textContent = (s.ojt_adviser || '') + (s.adviser_contact ? ' | ' + s.adviser_contact : '');

      if (s.emg_name || s.emg_contact || s.emergency_name || s.emergency_contact) {
        document.getElementById('view_emg_name').textContent = s.emg_name || s.emergency_name || '—';
        document.getElementById('view_emg_rel').textContent = s.emg_relation || s.emergency_relation || '—';
        document.getElementById('view_emg_contact').textContent = s.emg_contact || s.emergency_contact || '—';
      }

      const rendered = Number(s.hours_rendered || d.hours_rendered || 0);
      const requiredRaw = (s.total_hours_required !== undefined && s.total_hours_required !== null) ? s.total_hours_required : (d.total_hours_required !== undefined && d.total_hours_required !== null ? d.total_hours_required : null);
      const required = Number(requiredRaw || 0);
      const requiredDisplay = requiredRaw === null ? '—' : String(required);
      if (typeof preRendered !== 'undefined' && preRendered !== null) {
        const hoursElImmediate = document.getElementById('view_hours_text');
        const reqDispImmediate = (preRequired === null || preRequired === undefined) ? requiredDisplay : String(preRequired);
        if (hoursElImmediate) hoursElImmediate.textContent = `${preRendered} out of ${reqDispImmediate} hours`;
        const pctImmediate = (preRequired !== null && preRequired !== undefined && preRequired > 0) ? (Number(preRendered) / Number(preRequired) * 100) : 0;
        setDonut(pctImmediate);
      } else {
        document.getElementById('view_hours_text').textContent = `${rendered} out of ${requiredDisplay} hours`;
        document.getElementById('view_dates').textContent = `Date Started: —\nExpected End Date: —`;
        const pct = (requiredRaw !== null && required > 0) ? (rendered / required * 100) : 0;
        setDonut(pct);
      }

      document.getElementById('view_assigned_office').textContent = assignedOffice || '—';
      let officeHeadName = '';
      let officeHeadEmail = '';
      if (assignedOffice) {
        try{
          const key = assignedOffice.toString().trim(); const norm = key.toLowerCase().replace(/\s+/g,' ');
          if (window.officeHeadMap && window.officeHeadMap[key]) { officeHeadName = window.officeHeadMap[key].name || ''; officeHeadEmail = window.officeHeadMap[key].email || ''; }
          else if (window.normalizedOfficeHeadMap && window.normalizedOfficeHeadMap[norm]) { officeHeadName = window.normalizedOfficeHeadMap[norm].name || ''; officeHeadEmail = window.normalizedOfficeHeadMap[norm].email || ''; }
          else { for (const k in (window.officeHeadMap || {})) { if (!k) continue; if (k.toString().trim().toLowerCase() === key.toLowerCase()) { officeHeadName = window.officeHeadMap[k].name || ''; officeHeadEmail = window.officeHeadMap[k].email || ''; break; } } }
        }catch(e){}
      }
      officeHeadName = officeHeadName || d.office_head || d.office_head_name || '';
      officeHeadEmail = officeHeadEmail || d.office_head_email || d.office_contact || '';
      document.getElementById('view_office_head').textContent = officeHeadName || '—';
      document.getElementById('view_office_contact').textContent = officeHeadEmail || d.office_contact || '—';

      const attRoot = document.getElementById('view_attachments_list'); if (attRoot) attRoot.innerHTML = '';
      const attachments = [ {label:'Letter of Intent', file:d.letter_of_intent}, {label:'Endorsement', file:d.endorsement_letter}, {label:'Resume', file:d.resume}, {label:'MOA (application)', file:d.moa_file}, {label:'Picture', file:d.picture} ].filter(a=>a && a.file);
      if (d.school_moa && !attachments.some(a=>a.file===d.school_moa)) attachments.push({label:'MOA', file:d.school_moa});

      (function(){ try{ const schoolRaw = (s.college || s.school_name || s.school || s.school_address || '').toString().trim(); if (!schoolRaw || !Array.isArray(window.moaBySchool) || !window.moaBySchool.length) return; const normalize = txt => (txt||'').toString().toLowerCase().replace(/[^\w\s]/g,' ').replace(/\s+/g,' ').trim(); const sNorm = normalize(schoolRaw); for(const entry of window.moaBySchool){ if(!entry||!entry.school_name) continue; const eNorm = normalize(entry.school_name); if (!eNorm) continue; if (eNorm === sNorm || eNorm.includes(sNorm) || sNorm.includes(eNorm)) { if (!attachments.some(a=>a.file===entry.moa_file)) attachments.push({ label: 'MOA', file: entry.moa_file }); break; } } }catch(ex){} }());

      attachments.forEach(a=>{ const filePath = (a.file||'').toString().trim(); if(!filePath) return; let href; if (/^https?:\/\//i.test(filePath) || filePath.startsWith('/')) href = filePath; else if (/^uploads[\/\\]/i.test(filePath)) href = '../' + filePath.replace(/^\/+/, ''); else href = '../uploads/' + filePath.replace(/^\/+/, ''); const row = document.createElement('div'); row.style.display='flex'; row.style.justifyContent='space-between'; row.style.alignItems='center'; row.style.padding='6px 0'; const lbl = document.createElement('div'); lbl.style.fontSize='14px'; lbl.style.fontWeight='600'; lbl.textContent = a.label || (href.split('/').pop() || 'Attachment'); const actions = document.createElement('div'); actions.style.display='flex'; actions.style.gap='8px'; const viewBtn = document.createElement('a'); viewBtn.href = href; viewBtn.target = '_blank'; viewBtn.rel = 'noopener noreferrer'; viewBtn.className = 'tool-link'; viewBtn.textContent = 'View'; const dlBtn = document.createElement('a'); dlBtn.href = href; dlBtn.download = ''; dlBtn.className = 'tool-link'; dlBtn.textContent = 'Download'; actions.appendChild(viewBtn); actions.appendChild(dlBtn); row.appendChild(lbl); row.appendChild(actions); if (attRoot) attRoot.appendChild(row); });

      (async function(){ try{ const tbody = document.getElementById('late_dtr_tbody'); if (!tbody) return; tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">Loading...</td></tr>'; const today = new Date().toISOString().slice(0,10); const resp = await fetch('../hr_actions.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action: 'get_dtr_by_range', from: '1900-01-01', to: today, user_id: parseInt(userId,10) }) }); const jr = await resp.json(); if (!jr || !jr.success) { tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">Unable to load DTR.</td></tr>'; return; } const rows = Array.isArray(jr.rows) ? jr.rows : []; const nameNorm = ((s.first_name||'') + ' ' + (s.last_name||'')).toLowerCase().trim(); const officeNorm = (d.office1 || d.office || '').toString().toLowerCase().trim(); const matched = rows.filter(r => { try { const rids = [r.student_id, r.user_id, r.userid, r.userId, r.userIdRaw]; for (const candidate of rids) { if (candidate !== undefined && candidate !== null && String(candidate) === String(userId)) return true; } } catch (err) {} const rName = ((r.first_name||'') + ' ' + (r.last_name||'')).toLowerCase().trim(); const rOffice = (r.office || '').toString().toLowerCase().trim(); if (nameNorm && rName) { if (rName === nameNorm) return true; if (rName.includes(nameNorm) || nameNorm.includes(rName)) return true; } if (officeNorm && rOffice) { if (rOffice === officeNorm) return true; if (rOffice.includes(officeNorm) || officeNorm.includes(rOffice)) return true; } return false; }); if (!matched.length) { tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">No DTR records found.</td></tr>'; computeAndUpdateDates([]); return; } tbody.innerHTML = ''; const formatDateCell = (raw) => { if (!raw) return ''; try { const d = raw.toString().split('T')[0]; const parts = d.split('-'); if (parts.length === 3) return `${parts[1]}-${parts[2]}-${parts[0]}`; return raw; } catch (e) { return raw; } }; matched.forEach(r => { const tr = document.createElement('tr'); tr.innerHTML = ` <td style="padding:8px;text-align:center">${formatDateCell(r.log_date) || ''}</td> <td style="padding:8px;text-align:center">${r.am_in || ''}</td> <td style="padding:8px;text-align:center">${r.am_out || ''}</td> <td style="padding:8px;text-align:center">${r.pm_in || ''}</td> <td style="padding:8px;text-align:center">${r.pm_out || ''}</td> <td style="padding:8px;text-align:center">${r.hours != null ? r.hours : ''}</td> <td style="padding:8px;text-align:center">${r.minutes != null ? r.minutes : ''}</td> `; tbody.appendChild(tr); }); (function computeAndUpdateDates(rowsMatched){ function fmt(dstr){ if (!dstr) return '—'; const dt = new Date(dstr + 'T00:00:00'); if (isNaN(dt.getTime())) return '—'; const months = ['January','February','March','April','May','June','July','August','September','October','November','December']; return months[dt.getMonth()] + ' ' + dt.getDate() + ', ' + dt.getFullYear(); } let orientation = ''; try{ const rem = (d.remarks || '').toString(); const m = rem.match(/Orientation\/Start:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i); if (m && m[1]) orientation = m[1]; else { const m2 = rem.match(/Orientation\/Start:\s*([^|]+)/i); if (m2 && m2[1]) orientation = m2[1].trim(); } }catch(e){ orientation = ''; } let _userStatusRaw = (d.status || ''); try { if (window.userStatusMap && userId && (window.userStatusMap[userId] !== undefined && window.userStatusMap[userId] !== null)) { _userStatusRaw = window.userStatusMap[userId]; } } catch (e) { } const userStatus = (_userStatusRaw || '').toString().trim().toLowerCase(); const totalRequired = (s.total_hours_required !== undefined && s.total_hours_required !== null) ? Number(s.total_hours_required) : ((d.total_hours_required !== undefined && d.total_hours_required !== null) ? Number(d.total_hours_required) : null); let hrsRendered = Number(s.hours_rendered || d.hours_rendered || 0); if (rowsMatched && rowsMatched.length) { let sum = 0; rowsMatched.forEach(rr => { sum += (Number(rr.hours || 0) + (Number(rr.minutes || 0)/60)); }); hrsRendered = sum; } try { const requiredDisplayLocal = (totalRequired === null || totalRequired === undefined) ? '—' : String(totalRequired); const rounded = Math.round(hrsRendered * 100) / 100; const hoursEl = document.getElementById('view_hours_text'); if (hoursEl) hoursEl.textContent = `${rounded} out of ${requiredDisplayLocal} hours`; const pctLocal = (totalRequired !== null && totalRequired > 0) ? (hrsRendered / totalRequired * 100) : 0; setDonut(pctLocal); } catch (e) { console.warn('Failed to update hours/progress in view modal', e); } function addBusinessDays(startDateStr, daysNeeded){ let dt = new Date(startDateStr + 'T00:00:00'); if (isNaN(dt.getTime())) return null; let counted = 0; while (counted < daysNeeded){ const dow = dt.getDay(); if (dow >=1 && dow <=5) counted++; if (counted >= daysNeeded) break; dt.setDate(dt.getDate() + 1); } return dt; } let orientation_display = '-'; let expected_display = '-'; if (userStatus === 'approved') { orientation_display = '-'; expected_display = '-'; } else if (userStatus === 'ongoing') { let earliest = null; if (rowsMatched && rowsMatched.length){ rowsMatched.forEach(rr => { if (rr.log_date){ if (earliest === null || rr.log_date < earliest) earliest = rr.log_date; } }); } if (earliest) { orientation_display = fmt(earliest); const remaining = Math.max(0, (Number(totalRequired || 0) - hrsRendered)); const hoursPerDay = 8; const daysNeeded = Math.ceil(remaining / hoursPerDay); if (daysNeeded <= 0) { expected_display = orientation_display; } else { const dt = addBusinessDays(earliest, daysNeeded); expected_display = dt ? fmt(dt.toISOString().slice(0,10)) : '—'; } } else { if (orientation && /^\d{4}-\d{2}-\d{2}$/.test(orientation)){ orientation_display = fmt(orientation); const remaining = Math.max(0, (Number(totalRequired || 0) - hrsRendered)); const hoursPerDay = 8; const daysNeeded = Math.ceil(remaining / hoursPerDay); if (daysNeeded <= 0) expected_display = orientation_display; else { const dt = addBusinessDays(orientation, daysNeeded); expected_display = dt ? fmt(dt.toISOString().slice(0,10)) : '—'; } } else { orientation_display = '-'; expected_display = '-'; } } } else { if (userStatus === 'completed') { let firstDate = null, lastDate = null; try { const candidateRows = (Array.isArray(rows) ? rows.filter(r => { try { const ids = [r.student_id, r.user_id, r.userid, r.userId, r.userIdRaw]; for (const candidate of ids) { if (candidate !== undefined && candidate !== null && String(candidate) === String(userId)) return true; } } catch (e) {} return false; }) : []); const searchRows = (candidateRows.length ? candidateRows : (rowsMatched || [])); function toDate(dstr){ if (!dstr) return null; try { const s = dstr.toString().split('T')[0]; const dt = new Date(s + 'T00:00:00'); return isNaN(dt.getTime()) ? null : dt; } catch(e){ return null; } } if (searchRows && searchRows.length) { searchRows.forEach(rr => { const dt = toDate(rr.log_date); if (!dt) return; if (!firstDate || dt < firstDate) firstDate = dt; if (!lastDate || dt > lastDate) lastDate = dt; }); } } catch (e) { console.warn('completed date calc error', e); } if (firstDate) orientation_display = fmt(firstDate.toISOString().slice(0,10)); if (lastDate) expected_display = fmt(lastDate.toISOString().slice(0,10)); try { console.log('computeAndUpdateDates: completed debug', { userId: userId, first: firstDate ? firstDate.toISOString().slice(0,10) : null, last: lastDate ? lastDate.toISOString().slice(0,10) : null, matchedCount: (rowsMatched||[]).length }); } catch(e){} } else if (totalRequired && hrsRendered >= totalRequired) { let first = null, last = null; if (rowsMatched && rowsMatched.length){ rowsMatched.forEach(rr => { if (rr.log_date){ if (!first || rr.log_date < first) first = rr.log_date; if (!last || rr.log_date > last) last = rr.log_date; } }); } if (first) orientation_display = fmt(first); if (last) expected_display = fmt(last); } else { if (orientation && /^\d{4}-\d{2}-\d{2}$/.test(orientation)){ orientation_display = fmt(orientation); const remaining = Math.max(0, (Number(totalRequired || 0) - hrsRendered)); const hoursPerDay = 8; const daysNeeded = Math.ceil(remaining / hoursPerDay); if (daysNeeded <= 0) expected_display = orientation_display; else { const dt = addBusinessDays(orientation, daysNeeded); expected_display = dt ? fmt(dt.toISOString().slice(0,10)) : '—'; } } else { orientation_display = (orientation ? orientation : '-'); expected_display = '-'; } } document.getElementById('view_dates').textContent = `Date Started: ${orientation_display}\nExpected End Date: ${expected_display}`; })(matched);
        } catch (ex) { console.warn('View modal DTR fetch error', ex); const tbody = document.getElementById('late_dtr_tbody'); if (tbody) tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">Failed to load DTR.</td></tr>'; }
      })();

      document.getElementById('printDTR').onclick = function(){ window.open('print_dtr.php?id=' + encodeURIComponent(appId),'_blank'); };

    }catch(err){ console.error('openViewModal error', err); try{ alert('Failed to load details — check console for error.'); }catch(e){}; closeViewModal(); }
  };
})();
</script>
<script>
  // wire existing view buttons (delegated)
  document.addEventListener('click', function(e){
    try {
      const btn = e.target && e.target.closest && e.target.closest('.view-btn');
      if (!btn) return;
      const uid = btn.getAttribute('data-id');
      console.log('view-btn clicked', { uid: uid, timestamp: new Date().toISOString() }); if (window.ohDebugLog) window.ohDebugLog('view-btn clicked uid='+uid);
      if (!uid) return;
      if (window.openViewModal) {
        try { window.openViewModal(0, uid, null, null); }
        catch (err) { console.error('openViewModal threw', err); }
      } else {
        console.warn('openViewModal is not defined at click time');
      }
    } catch (ex) { console.error('delegated view-btn handler error', ex); }
  });
</script>
</body>
</html>