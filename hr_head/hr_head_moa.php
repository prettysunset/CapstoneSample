<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// --- MOVE: handle AJAX add MOA before any HTML output ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'add_moa')) {
    // collect and basic sanitize
    $school = trim((string)($_POST['school'] ?? ''));
    $date_signed = trim((string)($_POST['date_signed'] ?? ''));
    $valid_until = trim((string)($_POST['valid_until'] ?? ''));

    // validation: required fields
    $missing = [];
    if ($school === '') $missing[] = 'school';
    if ($date_signed === '') $missing[] = 'date_signed';
    if ($valid_until === '') $missing[] = 'valid_until';
    if (!empty($missing)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
        exit;
    }

    // validate dates
    try {
        $d1 = new DateTime($date_signed);
        $d2 = new DateTime($valid_until);
    } catch (Exception $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
        exit;
    }
    // require Valid Until to be strictly AFTER Date Signed (not earlier or same day)
    if ($d2 <= $d1) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Valid Until must be after Date Signed.']);
        exit;
    }

    // server-side: prevent adding if there's already an ACTIVE MOA for the same school
    $activeStmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM moa
        WHERE LOWER(TRIM(school_name)) = LOWER(TRIM(?))
          AND DATE_ADD(date_uploaded, INTERVAL COALESCE(validity_months,12) MONTH) >= CURDATE()
    ");
    if ($activeStmt) {
        $activeStmt->bind_param('s', $school);
        $activeStmt->execute();
        $actRow = $activeStmt->get_result()->fetch_assoc();
        $activeCount = (int)($actRow['cnt'] ?? 0);
        $activeStmt->close();
        if ($activeCount > 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'An active MOA for this school already exists.']);
            exit;
        }
    }

    // compute months difference (validity_months)
    $interval = $d1->diff($d2);
    $validity_months = (int)($interval->y * 12 + $interval->m + ($interval->d > 0 ? 1 : 0));
    if ($validity_months < 0) $validity_months = 0;

    // handle file upload (required)
    if (!isset($_FILES['moa_file']) || empty($_FILES['moa_file']['name'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please choose a file to upload.']);
        exit;
    }
    $moa_file_path = '';
    if ($_FILES['moa_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File upload error.']);
        exit;
    }
    if (isset($_FILES['moa_file']) && $_FILES['moa_file']['error'] === UPLOAD_ERR_OK) {
         $uploadDir = __DIR__ . '/../uploads/moa/';
         if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
 
         $orig = basename($_FILES['moa_file']['name']);
         $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
         $allowed = ['pdf','jpg','jpeg','png'];
         if (!in_array($ext, $allowed)) {
             http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Invalid file type. Allowed: pdf,jpg,jpeg,png']); exit;
         }
 
         // limit file size (5MB)
         if ($_FILES['moa_file']['size'] > 5 * 1024 * 1024) {
             http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'File too large (max 5MB).']); exit;
         }
 
         $safe = preg_replace('/[^a-z0-9_\-]/i','_', pathinfo($orig, PATHINFO_FILENAME));
         $newName = $safe . '_' . time() . '.' . $ext;
         $dest = $uploadDir . $newName;
         if (move_uploaded_file($_FILES['moa_file']['tmp_name'], $dest)) {
             $moa_file_path = 'uploads/moa/' . $newName;
         } else {
             http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Failed to move uploaded file.']); exit;
         }
     }

    // insert into DB
    $stmt = $conn->prepare("INSERT INTO moa (school_name, moa_file, date_uploaded, validity_months) VALUES (?,?,?,?)");
    $stmt->bind_param("sssi", $school, $moa_file_path, $date_signed, $validity_months);
    $ok = $stmt->execute();
    $insertId = $conn->insert_id;
    $err = $stmt->error;
    $stmt->close();

    if ($ok) {
        // compute students count
        $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE (college LIKE ? OR school_address LIKE ?)");
        $like = "%{$school}%";
        $cntStmt->bind_param("ss", $like, $like);
        $cntStmt->execute();
        $cntRow = $cntStmt->get_result()->fetch_assoc();
        $students = (int)($cntRow['cnt'] ?? 0);
        $cntStmt->close();

        $valid_until_calc = $date_signed ? date('Y-m-d', strtotime("+{$validity_months} months", strtotime($date_signed))) : null;
        $status = ($valid_until_calc && strtotime($valid_until_calc) >= strtotime(date('Y-m-d'))) ? 'ACTIVE' : 'EXPIRED';

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'moa' => [
            'moa_id' => (int)$insertId,
            'school_name' => $school,
            'moa_file' => $moa_file_path,
            'date_uploaded' => $date_signed,
            'valid_until' => $valid_until_calc,
            'students' => $students,
            'status' => $status
        ]]);
        exit;
    } else {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $err ?: 'Insert failed']);
        exit;
    }
}


// user info for sidebar
$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i",$uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'HR Head';

// --- NEW: datetime for top-right (match DTR layout) ---
$current_time = date("g:i A");
$current_date = date("l, F j, Y");

// fetch MOA rows
$moas = [];
$res = $conn->query("SELECT moa_id, school_name, moa_file, date_uploaded, COALESCE(validity_months,12) AS validity_months FROM moa ORDER BY date_uploaded DESC");
if ($res) {
    // Count OJTs by joining users -> students and matching students.college to MOA.school_name (case-insensitive, trimmed)
    $cntStmt = $conn->prepare("
        SELECT COUNT(DISTINCT u.user_id) AS cnt
        FROM users u
        JOIN students s ON s.user_id = u.user_id
        WHERE LOWER(TRIM(u.role)) = 'ojt'
          AND LOWER(TRIM(COALESCE(s.college, ''))) = LOWER(TRIM(?))
    ");
    while ($r = $res->fetch_assoc()) {
        $school = $r['school_name'] ?? '';
        $schoolParam = trim((string)$school);

        $count = 0;
        if ($cntStmt) {
            $cntStmt->bind_param('s', $schoolParam);
            $cntStmt->execute();
            $cntRow = $cntStmt->get_result()->fetch_assoc();
            $count = (int)($cntRow['cnt'] ?? 0);
        }

        $date_uploaded = $r['date_uploaded'];
        $valid_until = $date_uploaded ? date('Y-m-d', strtotime("+{$r['validity_months']} months", strtotime($date_uploaded))) : null;
        $status = ($valid_until && strtotime($valid_until) >= strtotime(date('Y-m-d'))) ? 'ACTIVE' : 'EXPIRED';

        $moas[] = [
            'moa_id' => (int)$r['moa_id'],
            'school_name' => $school,
            'moa_file' => $r['moa_file'] ?? '',
            'date_uploaded' => $date_uploaded,
            'valid_until' => $valid_until,
            'validity_months' => (int)$r['validity_months'],
            'students' => $count,
            'status' => $status
        ];
    }
    if ($cntStmt) $cntStmt->close();
    $res->free();
}

// after loading $moas, build list of distinct colleges for autocomplete
$collegeList = [];
$col_q = $conn->query("SELECT DISTINCT TRIM(college) AS college FROM students WHERE TRIM(COALESCE(college,'')) <> '' ORDER BY college");
if ($col_q) {
    while ($cr = $col_q->fetch_assoc()) {
        $c = trim((string)($cr['college'] ?? ''));
        if ($c !== '') $collegeList[] = $c;
    }
    $col_q->free();
}

function fmtDate($d){ if (!$d) return '-'; $dt = date_create($d); return $dt ? date_format($dt,'M j, Y') : '-'; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - MOA</title>
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
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  input[type=text]{padding:10px;border:1px solid #ddd;border-radius:8px}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:12px;border:1px solid #eee;text-align:left}
  .tbl thead th{background:#f4f6fb;font-weight:700}
  .badge{display:inline-block;background:#f0f2f6;padding:6px 10px;border-radius:16px;font-size:13px}
  .empty{padding:18px;text-align:center;color:#777}
  .status-active{color:#0b7a3a;font-weight:700}
  .status-expired{color:#a00;font-weight:700}
  /* modal styles for Add MOA */
  .modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,0.35); display:none; align-items:center; justify-content:center; z-index:1200; }
  .modal-backdrop.show{ display:flex; pointer-events:auto; }
  .modal{ background:#fff; width:360px; max-width:92%; border-radius:16px; padding:18px; box-shadow:0 12px 40px rgba(0,0,0,0.18); }
  .modal h3{ margin:0 0 12px 0; font-size:18px; }
  .form-row{ margin-bottom:10px; }
  .form-row label{ display:block; font-size:13px; color:#333; margin-bottom:6px; }
  .form-row input[type="text"], .form-row input[type="date"], .form-row input[type="file"] { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #ddd; }
  .modal-actions{ display:flex; justify-content:flex-end; gap:8px; margin-top:10px; }
  .btn-ghost{ background:#fff; border:1px solid #ddd; padding:8px 12px; border-radius:8px; cursor:pointer; }
  .btn-primary{ background:#2f3850; color:#fff; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; }
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} .tbl th,.tbl td{padding:8px} }
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
      <a href="#" class="active">
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
    <!-- top-right outline icons: notifications, settings, logout
         NOTE: removed position:fixed to prevent overlapping; icons now flow with page
         and stay visible. -->
    <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </a>

        <!-- calendar icon (display only) - placed to the right of Notifications to match DTR -->
        <div title="Calendar (display only)" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;pointer-events:none;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>

        <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path></svg>
        </a>
        <a id="top-logout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>

    <!-- datetime block - placed exactly like DTR page (right under icons) -->
    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?= htmlspecialchars($current_time) ?></h2>
                <p><?= htmlspecialchars($current_date) ?></p>
            </div>
        </div>
    </div>

    <div class="card" role="region" aria-label="MOA">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="margin:0;color:#2f3850">MOA</h2>
        <div style="display:flex;gap:12px;align-items:center">
          <div style="position:relative;display:inline-block;vertical-align:middle;">
            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="16" height="16" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#666;pointer-events:none" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="6"></circle>
              <path d="M21 21l-4.35-4.35A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <input type="text" id="search" placeholder="Search school" style="width:320px;padding:8px 10px 8px 36px;border:1px solid #ddd;border-radius:8px">
          </div>
          <button id="btnExport" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#e6f2ff;cursor:pointer">Export</button>
          <button id="btnAdd" style="padding:8px 12px;border-radius:8px;border:1px solid #2f3850;background:#3a4163;color:#fff;cursor:pointer">+ Add</button>
        </div>
      </div>

      <div style="overflow-x:auto">
        <table class="tbl" id="tblMoa">
          <thead>
            <tr>
              <th style="text-align:center;background:#f5f6fa">Students</th>
              <th style="background:#f5f6fa">School Name</th>
              <th style="background:#f5f6fa">MOA Status</th>
              <th style="background:#f5f6fa">Date Signed</th>
              <th style="background:#f5f6fa">Valid Until</th>
              <th style="background:#f5f6fa">Uploaded Copy</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($moas)): ?>
              <tr><td colspan="6" class="empty">No MOA records.</td></tr>
            <?php else: foreach ($moas as $m): ?>
              <tr data-search="<?= htmlspecialchars(strtolower($m['school_name'])) ?>">
                <td style="text-align:center"><?= (int)$m['students'] ?></td>
                <td><?= htmlspecialchars($m['school_name'] ?: '—') ?></td>
                <td><?= $m['status'] === 'ACTIVE' ? "<span class=\"status-active\">ACTIVE</span>" : "<span class=\"status-expired\">EXPIRED</span>" ?></td>
                <td><?= htmlspecialchars($m['date_uploaded'] ? fmtDate($m['date_uploaded']) : '-') ?></td>
                <td><?= htmlspecialchars($m['valid_until'] ? fmtDate($m['valid_until']) : '-') ?></td>
                <td>
                  <?php if (!empty($m['moa_file'])): ?>
                    <a href="<?= htmlspecialchars('../' . $m['moa_file']) ?>" target="_blank"><?= htmlspecialchars(basename($m['moa_file'])) ?></a>
                  <?php else: ?>—<?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div> <!-- end overflow-x wrapper -->

      <!-- Add MOA modal -->
      <div class="modal-backdrop" id="moaModalBackdrop" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
          <h3 id="modalTitle">Add MOA</h3>
          <form id="moaForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_moa">
            <div class="form-row">
              <label>School</label>
              <input type="text" name="school" id="schoolInput" list="collegeList" required placeholder="School name">
              <!-- datalist provides suggestions from students.college -->
              <datalist id="collegeList">
                <?php foreach ($collegeList as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="form-row">
              <label>Date Signed</label>
              <input type="date" name="date_signed" required>
            </div>
            <div class="form-row">
              <label>Valid Until</label>
              <input type="date" name="valid_until" required>
            </div>
            <div class="form-row">
              <label>Upload a copy (pdf/jpg/jpeg)</label>
              <input type="file" name="moa_file" accept=".pdf,.jpg,.jpeg">
            </div>
            <div class="modal-actions">
              <button type="button" class="btn-ghost" id="moaCancel">Cancel</button>
              <button type="submit" class="btn-primary">Add</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>

<script>
(function(){
  const search = document.getElementById('search');
  search.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#tblMoa tbody tr[data-search]').forEach(tr=>{
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q) === -1 ? 'none' : '';
    });
  });

  document.getElementById('btnExport').addEventListener('click', function(){
    const rows = Array.from(document.querySelectorAll('#tblMoa tbody tr')).filter(tr=>tr.style.display!=='none');
    if (rows.length === 0) { alert('No rows to export'); return; }
    const cols = ['Students','School Name','MOA Status','Date Signed','Valid Until','Uploaded Copy'];
    const data = [cols.join(',')];
    rows.forEach(tr=>{
      const cells = Array.from(tr.querySelectorAll('td')).map(td => '"' + td.textContent.replace(/"/g,'""').trim() + '"');
      data.push(cells.join(','));
    });
    const csv = data.join('\n');
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'moa_list.csv'; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  });

  // modal helpers (use class .show to avoid hidden element blocking clicks)
  const backdrop = document.getElementById('moaModalBackdrop');
  const btnAdd = document.getElementById('btnAdd');
  const btnCancel = document.getElementById('moaCancel');
  const form = document.getElementById('moaForm');

  // inputs for date min handling
  const dateSignedInput = form.querySelector('[name="date_signed"]');
  const validUntilInput = form.querySelector('[name="valid_until"]');

  // set Valid Until min to strictly after Date Signed (disable same-day and earlier)
  function setValidUntilMin() {
    if (!dateSignedInput || !validUntilInput) return;
    if (!dateSignedInput.value) {
      validUntilInput.removeAttribute('min');
      return;
    }
    // min = next calendar day after dateSigned
    const ds = new Date(dateSignedInput.value);
    ds.setDate(ds.getDate() + 1);
    const minISO = ds.toISOString().slice(0,10);
    validUntilInput.setAttribute('min', minISO);
  }
  if (dateSignedInput) dateSignedInput.addEventListener('change', setValidUntilMin);

  // disable future dates for Date Signed (max = today)
  (function(){
    const todayISO = new Date().toISOString().slice(0,10);
    if (dateSignedInput) {
      dateSignedInput.setAttribute('max', todayISO);
      // if current value is in future, clamp to today
      if (dateSignedInput.value && dateSignedInput.value > todayISO) dateSignedInput.value = todayISO;
    }
  })();

  function showModal(){ backdrop.classList.add('show'); backdrop.setAttribute('aria-hidden','false'); }
  function hideModal(){
    backdrop.classList.remove('show');
    backdrop.setAttribute('aria-hidden','true');
    form.reset();
    // clear min after reset
    if (validUntilInput) validUntilInput.removeAttribute('min');
  }
  btnAdd.addEventListener('click', function(e){ e.preventDefault(); showModal(); });
  btnCancel.addEventListener('click', function(e){ e.preventDefault(); hideModal(); });
  backdrop.addEventListener('click', function(e){ if (e.target === backdrop) hideModal(); });
  
  document.getElementById('moaForm').addEventListener('submit', function(e){
    e.preventDefault();
    const form = this;

    // client-side required validation (works with HTML required attributes)
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    // date validation: valid_until must be same or after date_signed
    const dateSigned = form.querySelector('[name="date_signed"]').value;
    const validUntil = form.querySelector('[name="valid_until"]').value;
    // require Valid Until to be strictly AFTER Date Signed (no same-day)
    if (dateSigned && validUntil && (new Date(validUntil) <= new Date(dateSigned))) {
      alert('Valid Until must be after Date Signed.');
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.textContent : 'SEND';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Adding...'; }

    const formData = new FormData(form);
    fetch('', { method: 'POST', body: formData })
    .then(response => response.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success && data.moa) {
          // update table (same logic you already have)
          const tblBody = document.querySelector('#tblMoa tbody');
          if (tblBody.querySelector('td.empty')) tblBody.innerHTML = '';

          const formatDate = (d) => {
            if (!d) return '-';
            const dt = new Date(d); if (isNaN(dt)) return '-';
            return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
          };

          const m = data.moa;
          const students = Number(m.students || 0);
          const school = m.school_name || '—';
          const statusClass = (m.status === 'ACTIVE') ? 'status-active' : 'status-expired';
          const dateUploaded = m.date_uploaded ? formatDate(m.date_uploaded) : '-';
          const validUntilFmt = m.valid_until ? formatDate(m.valid_until) : '-';
          const fileHtml = m.moa_file ? `<a href="../${m.moa_file}" target="_blank">${m.moa_file.split('/').pop()}</a>` : '—';

          const newRow = document.createElement('tr');
          newRow.setAttribute('data-search', (school || '').toLowerCase());
          newRow.innerHTML = `
            <td style="text-align:center">${students}</td>
            <td>${school}</td>
            <td><span class="${statusClass}">${m.status}</span></td>
            <td>${dateUploaded}</td>
            <td>${validUntilFmt}</td>
            <td>${fileHtml}</td>
          `;
          tblBody.insertBefore(newRow, tblBody.firstChild);

          // confirmation and close modal
          alert('MOA added successfully');
          hideModal();
        } else {
          alert(data.message || 'Error adding MOA');
        }
      } catch (err) {
        console.error('Server returned non-JSON:', text);
        alert('Server error. Check PHP error log or Network response.');
      }
    })
    .catch(err => { console.error(err); alert('Error processing request'); })
    .finally(() => {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalBtnText; }
    });
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