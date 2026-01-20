<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// handle AJAX email send from this page (do NOT change hr_actions.php)
$raw = @file_get_contents('php://input');
$inputJson = json_decode($raw, true);
if (is_array($inputJson) && (isset($inputJson['action']) && $inputJson['action'] === 'send_officehead_email')) {
    // return JSON and exit (AJAX endpoint)
    header('Content-Type: application/json; charset=utf-8');

    // Basic validation
    $to = trim($inputJson['email'] ?? '');
    $username = trim($inputJson['username'] ?? '');
    $password = trim($inputJson['password'] ?? '');
    $first = trim($inputJson['first_name'] ?? '');
    $last = trim($inputJson['last_name'] ?? '');
    $office = trim($inputJson['office'] ?? '');
    $initial_limit = (int)($inputJson['initial_limit'] ?? 0);
    $accept_courses = trim($inputJson['accept_courses'] ?? '');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !$username || !$password) {
        echo json_encode(['success'=>false,'message'=>'Invalid payload for email.']);
        exit;
    }

    // PHPMailer bootstrap (same pattern used in hr_actions.php)
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        $pmPath = __DIR__ . '/../PHPMailer/src/';
        if (file_exists($pmPath . 'PHPMailer.php')) {
            require_once $pmPath . 'Exception.php';
            require_once $pmPath . 'PHPMailer.php';
            require_once $pmPath . 'SMTP.php';
        } else {
            echo json_encode(['success'=>false,'message'=>'PHPMailer not available on server.']);
            exit;
        }
    }

    // SMTP config - use variables (const inside conditional causes parse error)
    $SMTP_HOST_LOCAL = 'smtp.gmail.com';
    $SMTP_PORT_LOCAL = 587;
    $SMTP_USER_LOCAL = 'sample.mail00000000@gmail.com';
    $SMTP_PASS_LOCAL = 'qitthwgfhtogjczq';
    $SMTP_FROM_EMAIL_LOCAL = 'sample.mail00000000@gmail.com';
    $SMTP_FROM_NAME_LOCAL  = 'OJTMS HR';

    $subject = "Your Office Head account for OJT-MS";
    $siteName = 'OJT-MS';

    $coursesListHtml = $accept_courses ? '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', array_filter(array_map('trim', explode(',', $accept_courses))))) . '</li></ul>' : '<em>None specified</em>';
    $officeLine = $office ? htmlspecialchars($office) : '<em>Not assigned</em>';
    $initialLimitLine = $initial_limit;

    $body = "
      <html><body>
      <p>Hello " . htmlspecialchars($first . ' ' . $last) . ",</p>
      <p>An Office Head account was created for you on <strong>{$siteName}</strong>. Below are your login details and office information. Please keep these credentials secure.</p>
      <table cellpadding='6' cellspacing='0' border='0'>
        <tr><td><strong>Username</strong></td><td>" . htmlspecialchars($username) . "</td></tr>
        <tr><td><strong>Password</strong></td><td>" . htmlspecialchars($password) . "</td></tr>
        <tr><td><strong>Office</strong></td><td>{$officeLine}</td></tr>
        <tr><td><strong>Initial OJT slots</strong></td><td>{$initialLimitLine}</td></tr>
        <tr><td valign='top'><strong>Accepted courses</strong></td><td>{$coursesListHtml}</td></tr>
      </table>
      <p>You can login here: <a href=\"" . (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '#') . "/\">{$siteName}</a></p>
      <p>Regards,<br>HR Department</p>
      </body></html>
    ";

    $sent = false;
    $errorInfo = '';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
         $mail->isSMTP();
         $mail->Host       = $SMTP_HOST_LOCAL;
         $mail->SMTPAuth   = true;
         $mail->Username   = $SMTP_USER_LOCAL;
         $mail->Password   = $SMTP_PASS_LOCAL;
         // use explicit 'tls' to avoid unqualified constant errors
         $mail->SMTPSecure = 'tls';
         $mail->Port       = $SMTP_PORT_LOCAL;
         $mail->CharSet    = 'UTF-8';

         $mail->setFrom($SMTP_FROM_EMAIL_LOCAL, $SMTP_FROM_NAME_LOCAL);
         $mail->addAddress($to, $first . ' ' . $last);
         $mail->isHTML(true);
         $mail->Subject = $subject;
         $mail->Body = $body;

         $mail->send();
         $sent = true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $sent = false;
        $errorInfo = $mail->ErrorInfo ?? $e->getMessage();
    }

    // fallback to PHP mail() if PHPMailer failed (best-effort)
    if (!$sent) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        // use variables (not undefined bare names)
        $headers .= "From: " . $SMTP_FROM_NAME_LOCAL . " <" . $SMTP_FROM_EMAIL_LOCAL . ">\r\n";
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent && !$errorInfo) $errorInfo = 'PHP mail() fallback failed';
    }

    echo json_encode(['success'=> (bool)$sent, 'email_sent' => (bool)$sent, 'error' => $errorInfo]);
    exit;
}

// --- ADD: send email for HR Staff accounts (same pattern as office head) ---
if (is_array($inputJson) && isset($inputJson['action']) && $inputJson['action'] === 'send_hrstaff_email') {
    header('Content-Type: application/json; charset=utf-8');
    $to = trim($inputJson['email'] ?? '');
    $username = trim($inputJson['username'] ?? '');
    $password = trim($inputJson['password'] ?? '');
    $first = trim($inputJson['first_name'] ?? '');
    $last = trim($inputJson['last_name'] ?? '');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !$username || !$password) {
        echo json_encode(['success'=>false,'message'=>'Invalid payload for HR staff email.']);
        exit;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        $pmPath = __DIR__ . '/../PHPMailer/src/';
        if (file_exists($pmPath . 'PHPMailer.php')) {
            require_once $pmPath . 'Exception.php';
            require_once $pmPath . 'PHPMailer.php';
            require_once $pmPath . 'SMTP.php';
        } else {
            echo json_encode(['success'=>false,'message'=>'PHPMailer not available on server.']);
            exit;
        }
    }

    $SMTP_HOST_LOCAL = 'smtp.gmail.com';
    $SMTP_PORT_LOCAL = 587;
    $SMTP_USER_LOCAL = 'sample.mail00000000@gmail.com';
    $SMTP_PASS_LOCAL = 'qitthwgfhtogjczq';
    $SMTP_FROM_EMAIL_LOCAL = 'sample.mail00000000@gmail.com';
    $SMTP_FROM_NAME_LOCAL  = 'OJTMS HR';

    $subject = "Your HR Staff account for OJT-MS";
    $siteName = 'OJT-MS';

    $body = "
      <html><body>
      <p>Hello " . htmlspecialchars($first . ' ' . $last) . ",</p>
      <p>An HR Staff account was created for you on <strong>{$siteName}</strong>. Below are your login details. Please keep these credentials secure.</p>
      <table cellpadding='6' cellspacing='0' border='0'>
        <tr><td><strong>Username</strong></td><td>" . htmlspecialchars($username) . "</td></tr>
        <tr><td><strong>Password</strong></td><td>" . htmlspecialchars($password) . "</td></tr>
      </table>
      <p>You can login here: <a href=\"" . (isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'] : '#') . "/\">{$siteName}</a></p>
      <p>Regards,<br>HR Department</p>
      </body></html>
    ";

    $sent = false;
    $errorInfo = '';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST_LOCAL;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER_LOCAL;
        $mail->Password   = $SMTP_PASS_LOCAL;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $SMTP_PORT_LOCAL;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($SMTP_FROM_EMAIL_LOCAL, $SMTP_FROM_NAME_LOCAL);
        $mail->addAddress($to, $first . ' ' . $last);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        $sent = true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $sent = false;
        $errorInfo = $mail->ErrorInfo ?? $e->getMessage();
    }

    if (!$sent) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . $SMTP_FROM_NAME_LOCAL . " <" . $SMTP_FROM_EMAIL_LOCAL . ">\r\n";
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent && !$errorInfo) $errorInfo = 'PHP mail() fallback failed';
    }

    echo json_encode(['success'=> (bool)$sent, 'email_sent' => (bool)$sent, 'error' => $errorInfo]);
    exit;
}

// AJAX: course suggestions for modal (POST JSON { action: 'course_suggest', q: '...' })
if (is_array($inputJson) && isset($inputJson['action']) && $inputJson['action'] === 'course_suggest') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($inputJson['q'] ?? '');
    if ($q === '') { echo json_encode([]); exit; }
    $out = [];
    $like = '%' . $conn->real_escape_string($q) . '%';
    $sql = "SELECT DISTINCT course_name FROM courses WHERE course_name LIKE ? OR course_code LIKE ? ORDER BY course_name LIMIT 12";
    if ($stmt = $conn->prepare($sql)) {
        $p = $like;
        $stmt->bind_param('ss', $p, $p);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $out[] = $r['course_name'];
        $stmt->close();
    }
    echo json_encode($out);
    exit;
}

// AJAX: check if office already exists (POST JSON { action: 'check_office', office: '...' })
if (is_array($inputJson) && isset($inputJson['action']) && $inputJson['action'] === 'check_office') {
    header('Content-Type: application/json; charset=utf-8');
    $office = trim($inputJson['office'] ?? '');
    if ($office === '') { echo json_encode(['exists' => false]); exit; }
    $exists = false;

    // Check offices table
    $sql = "SELECT COUNT(*) FROM offices WHERE office_name = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $office);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();
        if ((int)$cnt > 0) $exists = true;
    }

    // Additionally check if any user already has that office_name (defensive)
    if (!$exists) {
        $sql2 = "SELECT COUNT(*) FROM users WHERE office_name = ?";
        if ($stmt2 = $conn->prepare($sql2)) {
            $stmt2->bind_param('s', $office);
            $stmt2->execute();
            $stmt2->bind_result($cnt2);
            $stmt2->fetch();
            $stmt2->close();
            if ((int)$cnt2 > 0) $exists = true;
        }
    }

    echo json_encode(['exists' => (bool)$exists]);
    exit;
}

  // AJAX: check if email already exists in users OR students (POST JSON { action: 'check_email_unique', email: '...' })
  if (is_array($inputJson) && isset($inputJson['action']) && $inputJson['action'] === 'check_email_unique') {
    header('Content-Type: application/json; charset=utf-8');
    $email = trim($inputJson['email'] ?? '');
    $exists = false;
    if ($email !== '') {
      $sql = "SELECT 1 FROM users WHERE email = ? LIMIT 1";
      if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $exists = true;
        $stmt->close();
      }
      if (!$exists) {
        $sql = "SELECT 1 FROM students WHERE email = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
          $stmt->bind_param('s', $email);
          $stmt->execute();
          $stmt->store_result();
          if ($stmt->num_rows > 0) $exists = true;
          $stmt->close();
        }
      }
    }

    echo json_encode(['exists' => (bool)$exists]);
    exit;
  }

// fetch HR user info for sidebar
$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmtU = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmtU->bind_param("i", $user_id);
$stmtU->execute();
$user = $stmtU->get_result()->fetch_assoc() ?: [];
$stmtU->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// --- NEW: datetime for top-right (match MOA/DTR layout) ---
$current_time = date("g:i A");
$current_date = date("l, F j, Y");

// fetch office head accounts (users.role = 'office_head') with email from users table
$officeHeads = [];
$q1 = $conn->prepare("
  SELECT u.user_id, u.username, u.first_name, u.last_name, u.office_name, u.status,
         u.email AS oh_email
  FROM users u
  WHERE u.role = 'office_head'
  ORDER BY u.first_name, u.last_name
");
$q1->execute();
$res1 = $q1->get_result();
while ($r = $res1->fetch_assoc()) $officeHeads[] = $r;
$q1->close();

// fetch hr staff accounts (include email for display)
$hrStaff = [];
$q2 = $conn->prepare("SELECT user_id, username, email, first_name, last_name, office_name, status FROM users WHERE role = 'hr_staff' ORDER BY first_name, last_name");
$q2->execute();
$res2 = $q2->get_result();
while ($r = $res2->fetch_assoc()) $hrStaff[] = $r;
$q2->close();

// fetch OJT accounts with non-active status (inactive, approved, completed, ongoing)
$ojts = [];
$q3 = $conn->prepare("SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.office_name, u.status, s.address, s.first_name AS s_first, s.last_name AS s_last FROM users u LEFT JOIN students s ON u.user_id = s.user_id WHERE u.role = 'ojt' AND u.status <> 'active' ORDER BY u.first_name, u.last_name");
if ($q3) {
    $q3->execute();
    $res3 = $q3->get_result();
    while ($r = $res3->fetch_assoc()) $ojts[] = $r;
    $q3->close();
}

// gather unique offices present among the OJTs (for the OJT filter dropdown)
$ojt_offices = [];
foreach ($ojts as $zz) {
    $on = trim($zz['office_name'] ?? '');
    if ($on !== '' && !in_array($on, $ojt_offices)) $ojt_offices[] = $on;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - Accounts</title>
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
   
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .tabs{display:flex;gap:18px;border-bottom:2px solid #eef1f6;padding-bottom:12px;margin-bottom:16px}
  .tabs button{background:none;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:600;color:#2f3850}
  .tabs button.active{border-bottom:3px solid #2f3850}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  input[type=text]{padding:10px;border:1px solid #ddd;border-radius:8px}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:12px;border:1px solid #eee;text-align:left}
  .tbl th{background:#f4f6fb;font-weight:700}
  .actions{display:flex;gap:8px;justify-content:center}
  .btn{padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
  .btn-add{
    background:#3a4163;
    color:#fff;
    border:1px solid #ddd;
    border-radius:16px;
    padding:12px 20px;
    font-size:16px;
    min-width:140px;
    height:44px;
    line-height:1;
    cursor:pointer;
  }
  .icon-btn{background:none;border:none;cursor:pointer;font-size:16px}
  .status-active{color:#0b7a3a;font-weight:600}
  .status-inactive{color:#a0a0a0}
  .empty{padding:18px;text-align:center;color:#777}
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} }
  /* datetime/top-icons layout (match MOA/DTR) */
  .top-section{display:flex;justify-content:space-between;gap:20px;margin-bottom:20px}
  .datetime h2{font-size:22px;color:#2f3850;margin:0}
  .datetime p{color:#6d6d6d;margin:0}
  #filter_status, #filter_office { display:none; margin-left:10px; padding:8px 10px; border:1px solid #ddd; border-radius:8px; background:#fff; color:#2f3850; font-size:14px; }
  @media(max-width:900px){
    /* ensure selects wrap nicely on small screens */
    #filter_status, #filter_office{ display:block; width:100%; margin:8px 0 0 0; }
    .controls{flex-direction:column;align-items:flex-start}
  }
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
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        MOA
      </a>
      <a href="#" class="active">
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
    <!-- top-right outline icons: notifications, calendar, settings, logout
         NOTE: same markup/placement as hr_head_moa.php so icons align across pages -->
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

    <!-- datetime block - placed exactly like hr_head_moa.php (right under icons) -->
    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?= htmlspecialchars($current_time) ?></h2>
                <p><?= htmlspecialchars($current_date) ?></p>
            </div>
        </div>
    </div>

    <div class="card" role="region" aria-label="Accounts">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px">
      <div>
        <h2 style="margin:0;color:#2f3850">Accounts</h2>
      </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <style>
          /* underline only under the text of the active tab */
          .tabs .tab span{display:inline-block;padding-bottom:6px;border-bottom:3px solid transparent;transition:border-color .15s ease;}
          .tabs .tab.active span{border-color:#2f3850;}
        </style>

        <div class="tabs" role="tablist" aria-label="Account Tabs"
         style="display:flex;justify-content:center;align-items:flex-end;gap:24px;font-size:18px;border-bottom:2px solid #eee;padding-bottom:12px;position:relative;">
          <button class="tab active" data-tab="office" role="tab" aria-selected="true" aria-controls="panel-office"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px 6px 0 0;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;transition:border-color .15s ease;">
        <span>Office Heads</span>
          </button>
          <button class="tab" data-tab="hr" role="tab" aria-selected="false" aria-controls="panel-hr"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px 6px 0 0;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;transition:border-color .15s ease;">
        <span>HR Staffs</span>
          </button>
          <button class="tab" data-tab="ojt" role="tab" aria-selected="false" aria-controls="panel-ojt"
          style="background:transparent;border:none;padding:10px 14px;border-radius:6px 6px 0 0;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;transition:border-color .15s ease;">
        <span>OJTs</span>
          </button>
        </div>
      </div>

      <div class="controls">
        <div style="position:relative;width:360px;display:flex;align-items:center">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#62718a">
            <circle cx="11" cy="11" r="7"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="search" placeholder="Search name / email / office" style="width:100%;padding:10px 10px 10px 36px;border:1px solid #ddd;border-radius:8px" />
        </div>
        <div style="flex:1"></div>
        <div style="display:flex;gap:8px;align-items:center">
          <!-- OJT-only filters moved to the right side, next to action buttons -->
          <div id="ojt_filters" style="display:flex;gap:8px;align-items:center">
            <select id="filter_status" title="Filter by status">
              <option value="">All Status</option>
              <option value="approved">Approved</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="inactive">Inactive</option>
            </select>
            <select id="filter_office" title="Filter by office">
              <option value="">All offices</option>
              <?php foreach ($ojt_offices as $of): ?>
                <option value="<?= htmlspecialchars(strtolower($of)) ?>"><?= htmlspecialchars($of) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-add" id="btnAdd">Add Account</button>
          <!-- moved here so it sits on the same row as the search (visibility toggled by JS) -->
          <button id="btnAddHr" class="btn btn-add" style="min-width:140px;padding:8px 14px;display:none">Add HR Staff</button>
        </div>
      </div>

      <div id="panel-office" class="panel" style="display:block">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblOffice">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Office</th>
                <th style="text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($officeHeads)): ?>
                <tr><td colspan="4" class="empty">No office head accounts found.</td></tr>
              <?php else: foreach ($officeHeads as $o): 
                $name = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
                $email = $o['oh_email'] ?: '';
                $officeName = $o['office_name'] ?: '';
                $status = $o['status'] === 'active' ? 'active' : 'inactive';
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name . ' ' . $email . ' ' . $officeName)) ?>">
                  <td><?= htmlspecialchars($name ?: $o['username']) ?></td>
                  <td><?= htmlspecialchars($email ?: '—') ?></td>
                  <td><?= htmlspecialchars($officeName ?: '—') ?></td>
                  <td style="text-align:center" class="actions">
                    <!-- actions temporarily inert: onclick handlers removed so clicks do nothing -->
                    <button class="icon-btn" title="Edit" data-user="<?= (int)$o['user_id'] ?>">
                       <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                         <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                         <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                       </svg>
                     </button>
                    <button class="icon-btn" title="Reset Password" data-action="reset" data-user="<?= (int)$o['user_id'] ?>">
                      <!-- lock + circular arrow (reset) -->
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="7" y="10" width="10" height="6" rx="2"></rect>
                        <path d="M9 10V8a3 3 0 0 1 6 0v2"></path>
                        <path d="M21 12a8.5 8.5 0 1 0-3.1 6.5"></path>
                        <polyline points="21 12 21 7 16 7"></polyline>
                      </svg>
                    </button>
                    <button class="icon-btn" title="Disable/Restrict" data-action="toggle" data-user="<?= (int)$o['user_id'] ?>">
                       <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                         <circle cx="12" cy="12" r="10"></circle>
                         <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                       </svg>
                     </button>
                   </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-hr" class="panel" style="display:none">
        <!-- Add HR Staff button moved to top controls row -->
        <div style="overflow-x:auto">
          <table class="tbl" id="tblHR">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th style="text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($hrStaff)): ?>
                <tr><td colspan="3" class="empty">No HR staff accounts found.</td></tr>
              <?php else: foreach ($hrStaff as $h):
                $name = trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''));
                $email = $h['email'] ?? ($h['username'] ?? '');
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name . ' ' . $email)) ?>">
                  <td><?= htmlspecialchars($name ?: $email) ?></td>
                  <td><?= htmlspecialchars($email ?: '—') ?></td>
                  <td style="text-align:center" class="actions">
                    <!-- actions inert for now: onclick removed -->
                    <button class="icon-btn" title="Edit" data-action="edit" data-user="<?= (int)$h['user_id'] ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                      </svg>
                    </button>
                    <button class="icon-btn" title="Reset Password" data-action="reset" data-user="<?= (int)$h['user_id'] ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="7" y="10" width="10" height="6" rx="2"></rect>
                        <path d="M9 10V8a3 3 0 0 1 6 0v2"></path>
                        <path d="M21 12a8.5 8.5 0 1 0-3.1 6.5"></path>
                        <polyline points="21 12 21 7 16 7"></polyline>
                      </svg>
                    </button>
                    <button class="icon-btn" title="Disable/Restrict" data-action="toggle" data-user="<?= (int)$h['user_id'] ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                      </svg>
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-ojt" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblOJTs">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Office</th>
                <th>Address</th>
                <th style="text-align:center">Status</th>
                <th style="text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ojts)): ?>
                <tr><td colspan="6" class="empty">No OJT accounts with non-active status found.</td></tr>
              <?php else: foreach ($ojts as $o):
                $name = trim(($o['s_first'] ?? '') . ' ' . ($o['s_last'] ?? '')) ?: ($o['username'] ?? '');
                $email = $o['email'] ?: ($o['username'] ?? '');
                $officeName = $o['office_name'] ?: '';
                $address = $o['address'] ?: '';
                $status = ucwords(strtolower($o['status'] ?? ''));
              ?>
                <tr
                  data-search="<?= htmlspecialchars(strtolower($name . ' ' . $email . ' ' . $officeName . ' ' . $address . ' ' . $status)) ?>"
                  data-status="<?= htmlspecialchars(strtolower($o['status'] ?? '')) ?>"
                  data-office="<?= htmlspecialchars(strtolower($officeName)) ?>"
                >
                  <td><?= htmlspecialchars($name) ?></td>
                  <td><?= htmlspecialchars($email ?: '—') ?></td>
                  <td><?= htmlspecialchars($officeName ?: '—') ?></td>
                  <td><?= htmlspecialchars($address ?: '—') ?></td>
                  <td style="text-align:center"><?= htmlspecialchars($status) ?></td>
                  <td style="text-align:center" class="actions">
                    <button class="icon-btn" title="Edit" data-user="<?= (int)$o['user_id'] ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                      </svg>
                    </button>
                    <button class="icon-btn" title="Change Password" data-user="<?= (int)$o['user_id'] ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <circle cx="12" cy="16" r="1"></circle>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        <path d="M18 8a6 6 0 0 1 0 8"></path>
                        <path d="M20 10l-2-2-2 2"></path>
                      </svg>
                    </button>
                    <button class="icon-btn" title="Disable/Restrict" data-user="<?= (int)$o['user_id'] ?>">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
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
  // tab switching (works for office, hr, ojt) + toggle Add buttons
  function updateActionButtonsForTab(tab) {
    const topAdd = document.getElementById('btnAdd');      // top Add Account (office head)
    const hrAdd  = document.getElementById('btnAddHr');    // HR panel Add HR Staff (inside HR panel)
    const statusFilter = document.getElementById('filter_status');
    const officeFilter = document.getElementById('filter_office');
    if (topAdd) topAdd.style.display = (tab === 'office') ? '' : 'none';
    if (hrAdd)  hrAdd.style.display  = (tab === 'hr') ? '' : 'none';
    // show OJT filters only on OJTs tab
    // NOTE: explicit inline display ('inline-block') is required to override the CSS rule that sets display:none
    if (statusFilter) statusFilter.style.display = (tab === 'ojt') ? 'inline-block' : 'none';
    if (officeFilter) officeFilter.style.display = (tab === 'ojt') ? 'inline-block' : 'none';
     // update search placeholder: remove "office" when HR Staffs tab is active
     const search = document.getElementById('search');
     if (search) {
       search.placeholder = (tab === 'hr') ? 'Search name / email' : 'Search name / email / office';
     }
  }

  document.querySelectorAll('.tabs button').forEach(btn=>{
    btn.addEventListener('click', function(){
      document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      const t = this.getAttribute('data-tab');
      // hide all panels then show the selected one
      document.querySelectorAll('.panel').forEach(p => p.style.display = 'none');
      const sel = document.getElementById('panel-' + t);
      if (sel) sel.style.display = 'block';
      // update Add buttons visibility and filters
      updateActionButtonsForTab(t);
      // apply filters immediately when switching tabs
      applyFilters();
    });
  });
  // initialize visibility based on active tab
  (function(){ const active = document.querySelector('.tabs button.active'); if (active) updateActionButtonsForTab(active.getAttribute('data-tab')); })();

  // unified filter function (search + status + office)
  const search = document.getElementById('search');
  const statusFilter = document.getElementById('filter_status');
  const officeFilter = document.getElementById('filter_office');

  function applyFilters(){
    const q = (search.value||'').toLowerCase().trim();
    const statusVal = statusFilter ? (statusFilter.value||'').toLowerCase() : '';
    const officeVal = officeFilter ? (officeFilter.value||'').toLowerCase() : '';
    document.querySelectorAll('tbody tr[data-search]').forEach(tr=>{
      const ds = (tr.getAttribute('data-search')||'').toLowerCase();
      const dstatus = (tr.getAttribute('data-status')||'').toLowerCase();
      const doff = (tr.getAttribute('data-office')||'').toLowerCase();

      // text match
      const textMatch = q === '' ? true : ds.indexOf(q) !== -1;
      // status match (only when status filter active)
      const statusMatch = !statusVal ? true : dstatus === statusVal;
      // office match (only when office filter active)
      const officeMatch = !officeVal ? true : doff === officeVal;

      tr.style.display = (textMatch && statusMatch && officeMatch) ? '' : 'none';
    });
  }

  // search filter binding
  if (search) search.addEventListener('input', applyFilters);
  if (statusFilter) statusFilter.addEventListener('change', applyFilters);
  if (officeFilter) officeFilter.addEventListener('change', applyFilters);

  // open modal instead of navigating
  const btnAddEl = document.getElementById('btnAdd');
  if (btnAddEl) btnAddEl.addEventListener('click', ()=> { openAdd(); });

})(); 

function editAccount(userId) {
  // navigate to edit page (implement page separately)
  window.location.href = 'account_edit.php?id=' + encodeURIComponent(userId);
}

function changePassword(userId) {
  // Placeholder: implement change password functionality
  alert('Change password functionality not yet implemented for user ID: ' + userId);
}

async function toggleStatus(userId, btn) {
  if (!confirm('Change account status?')) return;
  try {
    btn.disabled = true;
    const res = await fetch('../hr_actions.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'toggle_user_status', user_id: parseInt(userId,10) })
    });
    const j = await res.json();
    if (!j || !j.success) {
      alert('Failed: ' + (j?.message || 'Unknown error'));
      btn.disabled = false;
      return;
    }
    // reflect change in UI: swap icon
    if (j.new_status === 'active') btn.textContent = '🔓'; else btn.textContent = '🔒';
    btn.disabled = false;
  } catch (e) {
    console.error(e);
    alert('Request failed');
    btn.disabled = false;
  }
}

// modal helpers (ensure Add Account works)
function openAdd(){
  const m = document.getElementById('addModal');
  if(!m) return;
  m.style.display = 'flex';
}
function closeAdd(){
  const m = document.getElementById('addModal');
  if(!m) return;
  m.style.display = 'none';
  // optional: reset form fields
  const inputs = m.querySelectorAll('input');
  inputs.forEach(i => { if (i.type !== 'hidden') i.value = ''; });
  if (window.__hr_modal && window.__hr_modal.reset) window.__hr_modal.reset();
}

// HR Staff modal: open/close/submit (first name, last name, email only)
function openAddHr(){
  const m = document.getElementById('addHrModal');
  if(!m) return;
  m.style.display = 'flex';
}
function closeAddHr(){
  const m = document.getElementById('addHrModal');
  if(!m) return;
  m.style.display = 'none';
  ['hr_first','hr_last','hr_email'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  const st = document.getElementById('addHrModalStatus'); if (st) st.style.display = 'none';
}

async function submitAddHr(){
  const first_name = (document.getElementById('hr_first').value || '').trim();
  const last_name  = (document.getElementById('hr_last').value || '').trim();
  const email      = (document.getElementById('hr_email').value || '').trim();
  const statusEl = document.getElementById('addHrModalStatus');

  if (!first_name || !last_name || !email) { alert('Please fill First name, Last name and Email.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { alert('Please enter a valid email address.'); return; }

  // pre-check: ensure email isn't already used
  try {
    const emailChk = await fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'check_email_unique', email: email })
    });
    if (emailChk.ok) {
      const ej = await emailChk.json().catch(()=>null);
      if (ej && ej.exists) {
        alert('This email is already in use. Please use a different email.');
        return;
      }
    }
  } catch (e) {
    console.warn('Email uniqueness check failed', e);
  }

  if (statusEl) { statusEl.style.display = 'block'; statusEl.style.background = '#fffbe6'; statusEl.style.color = '#333'; statusEl.textContent = 'Creating account...'; }

  const unameBase = (first_name.charAt(0) + last_name).toLowerCase().replace(/[^a-z0-9]/g,'') || 'hr';
  const username = unameBase + Math.floor(Math.random()*900 + 100);
  const password = (typeof randomPassword === 'function') ? randomPassword(10) : Math.random().toString(36).slice(-10);

  try {
    const payload = {
      action: 'create_account',
      username: username,
      password: password,
      first_name: first_name,
      last_name: last_name,
      email: email,
      role: 'hr_staff',
      email_notify: false
    };
    const res = await fetch('../hr_actions.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json();
    if (!j || !j.success) {
      if (statusEl) { statusEl.style.background = '#fff4f4'; statusEl.style.color = '#a00'; statusEl.textContent = 'Failed: ' + (j?.message || 'Unknown error'); }
      return;
    }

    // account created — reflect in UI (stay on HR tab). attempt to send email afterwards.
    if (statusEl) { statusEl.style.background = '#fffbe6'; statusEl.style.color = '#333'; statusEl.textContent = 'Sending email to HR staff...'; }

    // try sending email via this page endpoint
    let mailJson = null;
    try {
      const mailRes = await fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          action: 'send_hrstaff_email',
          email: email,
          username: username,
          password: password,
          first_name: first_name,
          last_name: last_name
        })
      });
      mailJson = await mailRes.json();
    } catch (err) {
      console.error('mail send failed', err);
    }

    // show status to user
    if (mailJson && mailJson.success) {
      if (statusEl) { statusEl.style.background = '#e6f9ee'; statusEl.style.color = '#0b7a3a'; statusEl.textContent = 'HR staff account created and email sent.'; }
    } else {
      if (statusEl) {
        statusEl.style.background = '#fff4e5';
        statusEl.style.color = '#8a5a00';
        const mailMsg = mailJson && mailJson.error ? (' — ' + mailJson.error) : ' (email not sent)';
        statusEl.innerHTML = 'Account created, but email was not sent' + mailMsg +
          '.<br><strong>Please copy these credentials and send manually:</strong>' +
          '<div style="margin-top:8px;padding:8px;background:#fff;border-radius:6px;border:1px solid #eee">' +
          '<div><strong>Username:</strong> ' + escapeHtml(username) + '</div>' +
          '<div><strong>Password:</strong> ' + escapeHtml(password) + '</div>' +
          '</div>';
      }
    }

    // Update HR table in place and keep HR tab active (no full page reload)
    try {
      const tbody = document.querySelector('#tblHR tbody');
      if (tbody) {
        // remove empty placeholder row if present
        const emptyTd = tbody.querySelector('td.empty');
        if (emptyTd) tbody.innerHTML = '';

        const fullname = (first_name + ' ' + last_name).trim();
        const newId = j.user_id ? parseInt(j.user_id, 10) : '';

        const tr = document.createElement('tr');
        tr.setAttribute('data-search', ((fullname + ' ' + email) || '').toLowerCase());

        // action buttons HTML (matching existing buttons markup)
        const actionsHtml = `
          <button class="icon-btn" title="Edit" data-action="edit" data-user="${newId}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
          </button>
          <button class="icon-btn" title="Reset Password" data-action="reset" data-user="${newId}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <rect x="7" y="10" width="10" height="6" rx="2"></rect>
              <path d="M9 10V8a3 3 0 0 1 6 0v2"></path>
              <path d="M21 12a8.5 8.5 0 1 0-3.1 6.5"></path>
              <polyline points="21 12 21 7 16 7"></polyline>
            </svg>
          </button>
          <button class="icon-btn" title="Disable/Restrict" data-action="toggle" data-user="${newId}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
            </svg>
          </button>
        `;

        tr.innerHTML = `
          <td>${escapeHtml(fullname || username)}</td>
          <td>${escapeHtml(email || username)}</td>
          <td style="text-align:center" class="actions">${actionsHtml}</td>
        `;
        tbody.insertBefore(tr, tbody.firstChild);
      }

      // ensure HR tab remains active and visible
      document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
      const hrBtn = document.querySelector('.tabs button[data-tab="hr"]');
      if (hrBtn) hrBtn.classList.add('active');
      document.querySelectorAll('.panel').forEach(p => p.style.display = 'none');
      const panelHr = document.getElementById('panel-hr');
      if (panelHr) panelHr.style.display = 'block';
      // show/hide add buttons consistently
      const topAdd = document.getElementById('btnAdd');
      const hrAdd  = document.getElementById('btnAddHr');
      if (topAdd) topAdd.style.display = 'none';
      if (hrAdd) hrAdd.style.display = '';
    } catch (domErr) {
      console.error('Failed to update HR table DOM:', domErr);
    }

    // close modal after short delay so user sees status message
    setTimeout(()=>{ closeAddHr(); }, 900);

  } catch (err) {
    console.error(err);
    if (statusEl) { statusEl.style.background = '#fff4f4'; statusEl.style.color = '#a00'; statusEl.textContent = 'Request failed.'; }
  }

  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}

// --- NEW: toggle between Office Heads / HR Staffs panels (AJAX content)
document.getElementById('btnAddHr').addEventListener('click', function(e){
  e.preventDefault();
  const tab = 'hr';
  // switch to HR Staffs tab
  document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
  document.querySelector('.tabs button[data-tab="hr"]').classList.add('active');
  // hide all panels then show the selected one
  document.querySelectorAll('.panel').forEach(p => p.style.display = 'none');
  document.getElementById('panel-hr').style.display = 'block';
  // show Add HR Staff button, hide top Add Account button
  document.getElementById('btnAdd').style.display = 'none';
  document.getElementById('btnAddHr').style.display = '';
});
</script>

<!-- Add Account Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;z-index:9999">
  <div style="background:#fff;padding:18px;border-radius:10px;width:560px;max-width:94%;box-shadow:0 12px 30px rgba(0,0,0,0.15)">
    <h3 style="margin:0 0 12px">Create Office Head Account</h3>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <input id="m_first" placeholder="First name" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <input id="m_last" placeholder="Last name" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <input id="m_email" placeholder="Email" style="padding:10px;border:1px solid #ddd;border-radius:8px;grid-column:span 2" />
      <!-- replaced dropdown with free-text office input -->
      <input id="m_office" placeholder="Office" style="padding:10px;border:1px solid #ddd;border-radius:8px;grid-column:span 2" />
      <input id="m_initial_limit" type="number" min="0" placeholder="Initial OJT limit (e.g. 10)" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <div style="display:flex;gap:8px;align-items:center">
        <div style="position:relative;flex:1;display:flex;gap:8px;align-items:center">
          <input id="m_course_input" placeholder="Related course" autocomplete="off" style="padding:10px;border:1px solid #ddd;border-radius:8px;flex:1" />
          <button type="button" id="m_add_course_btn" class="btn" style="padding:9px 12px;border-radius:8px;background:#3a4163;color:#fff;border:none">Add</button>
          <ul id="m_course_suggestions" style="position:absolute;left:0;top:calc(100% + 8px);z-index:9999;background:#fff;border:1px solid #ddd;border-radius:8px;list-style:none;padding:6px 0;margin:0;display:none;max-height:220px;overflow:auto;box-shadow:0 8px 20px rgba(0,0,0,0.08);min-width:320px;max-width:520px;width:420px;"></ul>
        </div>
      </div>
      <div id="m_course_tags" style="grid-column:span 2;display:flex;flex-wrap:wrap;gap:8px"></div>
 
       <!-- hidden field to store CSV courses -->
       <input type="hidden" id="m_accept_courses" />
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeAdd()" class="btn" type="button">Cancel</button>
      <button id="btnCreate" class="btn btn-add" type="button">Create</button>
    </div>
    <div id="addModalStatus" style="margin-top:10px;display:none;padding:8px;border-radius:6px"></div>
  </div>
</div>
<!-- end of existing Add Account Modal -->

<!-- Add HR Staff Modal (only first, last, email) -->
<div id="addHrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;z-index:9999">
  <div style="background:#fff;padding:18px;border-radius:10px;width:420px;max-width:94%;box-shadow:0 12px 30px rgba(0,0,0,0.15)">
    <h3 style="margin:0 0 12px">Create HR Staff Account</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <input id="hr_first" placeholder="First name" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <input id="hr_last" placeholder="Last name" style="padding:10px;border:1px solid #ddd;border-radius:8px" />
      <input id="hr_email" placeholder="Email" style="padding:10px;border:1px solid #ddd;border-radius:8px;grid-column:span 2" />
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeAddHr()" class="btn" type="button">Cancel</button>
      <button id="btnCreateHr" class="btn btn-add" type="button">Create</button>
    </div>
    <div id="addHrModalStatus" style="margin-top:10px;display:none;padding:8px;border-radius:6px"></div>
  </div>
</div>
<!-- end Add HR Staff Modal -->

<script>
// course tag helpers with DB-backed suggestions (scoped for the modal)
(function(){
  const input = document.getElementById('m_course_input');
  const addBtn = document.getElementById('m_add_course_btn');
  const suggestions = document.getElementById('m_course_suggestions');
  const tagsWrap = document.getElementById('m_course_tags');
  const hidden = document.getElementById('m_accept_courses');
  let tags = [];
  let suggItems = [];
  let sel = -1;
  let debounce = null;

  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function renderTags(){
    tagsWrap.innerHTML = '';
    tags.forEach((t, idx)=>{
      const el = document.createElement('div');
      el.style.padding = '6px 10px';
      el.style.background = '#f1f4fb';
      el.style.borderRadius = '16px';
      el.style.display = 'flex';
      el.style.gap = '8px';
      el.style.alignItems = 'center';
      el.style.fontSize = '13px';
      el.innerHTML = `<span>${escapeHtml(t)}</span><button type="button" data-idx="${idx}" style="background:transparent;border:0;cursor:pointer;color:#a00;font-weight:700">×</button>`;
      tagsWrap.appendChild(el);
      el.querySelector('button').addEventListener('click', function(){
        const i = parseInt(this.getAttribute('data-idx'),10);
        tags.splice(i,1);
        updateHidden();
        renderTags();
      });
    });
    updateHidden();
  }

  function updateHidden(){
    hidden.value = tags.join(',');
  }

  function addTagFromInput(val){
    const v = (val !== undefined ? val : (input.value || '')).trim();
    if (!v) return;
    const lc = v.toLowerCase();
    if (tags.some(t => t.toLowerCase() === lc)) {
      input.value = '';
      hideSuggestions();
      return;
    }
    tags.push(v);
    input.value = '';
    renderTags();
       input.focus();
    hideSuggestions();
  }

  function showSuggestions(items){
    suggItems = items || [];
    sel = -1;
    suggestions.innerHTML = '';
    if (!suggItems || suggItems.length === 0) { hideSuggestions(); return; }
    suggItems.forEach((s, i) => {
      const li = document.createElement('li');
      li.textContent = s;
      li.style.padding = '8px 10px';
      li.style.cursor = 'pointer';
      li.style.whiteSpace = 'nowrap';
      li.addEventListener('mousedown', function(e){
        e.preventDefault();
        addTagFromInput(s);
      });
      li.addEventListener('mouseover', () => highlight(i));
      suggestions.appendChild(li);
    });
    suggestions.style.display = 'block';
  }

  function hideSuggestions(){
    suggestions.style.display = 'none';
    suggestions.innerHTML = '';
    suggItems = [];
    sel = -1;
  }

  function highlight(i){
    const nodes = suggestions.querySelectorAll('li');
    nodes.forEach((n, idx) => n.style.background = idx === i ? '#eef6ff' : '');
    sel = i;
    if (nodes[sel]) nodes[sel].scrollIntoView({block:'nearest'});
  }

  function fetchSuggestions(q){
    if (!q || !q.trim()) { hideSuggestions(); return; }
    // debounce
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
      try {
        const res = await fetch(window.location.href, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action: 'course_suggest', q: q.trim() })
        });
        const j = await res.json();
        if (Array.isArray(j) && j.length) showSuggestions(j);
        else hideSuggestions();
      } catch (e) {
        hideSuggestions();
      }
    }, 180);
  }

  // bind events
  addBtn.addEventListener('click', function(e){ addTagFromInput(); });

  input.addEventListener('input', function(){
    fetchSuggestions(this.value);
  });

  input.addEventListener('keydown', function(e){
    const nodes = suggestions.querySelectorAll('li');
    if (e.key === 'ArrowDown') {
      if (nodes.length === 0) return;
      e.preventDefault();
      sel = Math.min(sel + 1, nodes.length - 1);
      highlight(sel);
    } else if (e.key === 'ArrowUp') {
      if (nodes.length === 0) return;
      e.preventDefault();
      sel = Math.max(sel - 1, 0);
      highlight(sel);
    } else if (e.key === 'Enter') {
      if (sel >= 0 && nodes[sel]) {
        e.preventDefault();
        addTagFromInput(nodes[sel].textContent);
      } else {
        e.preventDefault();
        addTagFromInput();
      }
    } else if (e.key === 'Escape') {
      hideSuggestions();
    }
  });

  // hide suggestions on blur (allow click selection via mousedown above)
  input.addEventListener('blur', function(){ setTimeout(hideSuggestions, 120); });

  // expose for submitAdd to read
  window.__hr_modal = { getCourses: ()=>tags, reset: ()=>{ tags = []; renderTags(); } };
  // initial render (if any)
  renderTags();
})();
</script>

<script>
async function submitAdd(){
  const first_name = (document.getElementById('m_first').value || '').trim();
  const last_name = (document.getElementById('m_last').value || '').trim();
  const email = (document.getElementById('m_email').value || '').trim();
  const office = (document.getElementById('m_office').value || '').trim();
  const initial_limit = parseInt(document.getElementById('m_initial_limit').value || '0', 10) || 0;
  const courses = (window.__hr_modal && window.__hr_modal.getCourses()) ? window.__hr_modal.getCourses() : [];
  const accept_courses = courses.join(',');

  // client-side validation: all required
  if (!first_name || !last_name || !email || !office) {
    alert('Please fill First name, Last name, Email and Office.');
    return;
  }

  // at least one course required
  if (!Array.isArray(courses) || courses.length < 1) {
    alert('Please add at least one related course.');
    return;
  }

  // basic email format check
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    alert('Please enter a valid email address.');
    return;
  }

  const statusEl = document.getElementById('addModalStatus');
  statusEl.style.display = 'block';
  statusEl.style.background = '#fffbe6';
  statusEl.style.color = '#333';
  statusEl.textContent = 'Validating...';

  try {
    // pre-check: ensure email isn't already used in users or students
    try {
      const emailChk = await fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'check_email_unique', email: email })
      });
      if (emailChk.ok) {
        const ej = await emailChk.json().catch(()=>null);
        if (ej && ej.exists) {
          statusEl.style.display = 'block';
          statusEl.style.background = '#fff4f4';
          statusEl.style.color = '#a00';
          statusEl.textContent = 'This email is already in use. Please use a different email.';
          return;
        }
      }
    } catch (e) {
      // if check fails, continue and let server-side endpoint handle duplicates
      console.warn('Email uniqueness check failed', e);
    }
    // server-side check: office existence
    const chkRes = await fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'check_office', office: office })
    });

    if (!chkRes.ok) {
      const txt = await chkRes.text().catch(()=>chkRes.statusText);
      throw new Error('Office-check failed: ' + chkRes.status + ' ' + txt);
    }

    let chkJson;
    try { chkJson = await chkRes.json(); } catch (e) {
      const txt = await chkRes.text().catch(()=>null);
      throw new Error('Office-check returned invalid JSON: ' + (txt || e.message));
    }

    if (chkJson && chkJson.exists) {
      statusEl.style.background = '#fff4f4';
      statusEl.style.color = '#a00';
      statusEl.textContent = 'Cannot create account: the office "' + office + '" already exists.';
      return;
    }

    // generate simple username + password fallback
    const unameBase = (first_name.charAt(0) + last_name).toLowerCase().replace(/[^a-z0-9]/g,'');
    const username = unameBase + Math.floor(Math.random()*900 + 100);
    const password = randomPassword(10);

    const payload = {
      action: 'create_account',
      username: username,
      password: password,
      first_name: first_name,
      last_name: last_name,
      email: email,
      role: 'office_head',
      office: office,
      initial_limit: initial_limit,
      accept_courses: accept_courses,
      email_notify: false
    };

    statusEl.textContent = 'Creating account...';

    // 1) create account via existing endpoint (do NOT edit hr_actions.php)
    const res = await fetch('../hr_actions.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });

    if (!res.ok) {
      const txt = await res.text().catch(()=>res.statusText);
      throw new Error('Create account failed: ' + res.status + ' ' + txt);
    }

    let j;
    try { j = await res.json(); } catch (e) {
      const txt = await res.text().catch(()=>null);
      throw new Error('Create account returned invalid JSON: ' + (txt || e.message));
    }

    if (!j || !j.success) {
      throw new Error(j?.message || 'Server returned failure creating account.');
    }

    // 2) now request this page to send the actual email (server-side send implemented above)
    statusEl.textContent = 'Sending email to the office head...';
    const mailRes = await fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        action: 'send_officehead_email',
        email: email,
        username: username,
        password: password,
        first_name: first_name,
        last_name: last_name,
        office: office,
        initial_limit: initial_limit,
        accept_courses: accept_courses
      })
    });

    let mailJson = null;
    if (mailRes.ok) {
      try { mailJson = await mailRes.json(); } catch(e){ mailJson = null; }
    } else {
      const txt = await mailRes.text().catch(()=>mailRes.statusText);
      console.warn('send_officehead_email responded non-OK:', mailRes.status, txt);
    }

    if (mailJson && mailJson.success) {
      statusEl.style.background = '#e6f9ee';
      statusEl.style.color = '#0b7a3a';
      statusEl.innerHTML = 'Account created and email has been sent to the office head.';
    } else {
      statusEl.style.background = '#fff4e5';
      statusEl.style.color = '#8a5a00';
      const mailMsg = mailJson && mailJson.error ? (' — ' + mailJson.error) : ' (email not sent)';
      statusEl.innerHTML = 'Account created, but email was not sent' + mailMsg +
        '.<br><strong>Please copy these credentials and send to the office head manually:</strong>' +
        '<div style="margin-top:8px;padding:8px;background:#fff;border-radius:6px;border:1px solid #eee">' +
        '<div><strong>Username:</strong> ' + escapeHtml(username) + '</div>' +
        '<div><strong>Password:</strong> ' + escapeHtml(password) + '</div>' +
        '<div style="margin-top:6px"><strong>Office:</strong> ' + escapeHtml(office || 'N/A') + '</div>' +
        '<div><strong>Initial OJT slots:</strong> ' + (initial_limit || 0) + '</div>' +
        '<div><strong>Accepted courses:</strong> ' + (accept_courses ? escapeHtml(accept_courses) : 'None') + '</div>' +
        '</div>';
    }

    if (window.__hr_modal) window.__hr_modal.reset();
    setTimeout(()=>{ closeAdd(); location.reload(); }, 1400);
  } catch (err) {
    console.error('submitAdd error:', err);
    statusEl.style.background = '#fff4f4';
    statusEl.style.color = '#a00';
    statusEl.textContent = 'Request failed: ' + (err.message || 'Unknown error');
  }

  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}
</script>

<script>
// ensure Create button is bound even if inline handlers fail
(function(){
  try {
    const b = document.getElementById('btnCreate');
    if (b) {
      b.addEventListener('click', function(e){
        e.preventDefault();
        // safe-guard: disable while processing
        if (b.disabled) return;
        b.disabled = true;
        Promise.resolve().then(() => submitAdd()).finally(()=> b.disabled = false);
      });
    }
  } catch (err){
    console.error('binding btnCreate:', err);
  }
})();
</script>
<script>
/* Replace the old immediate-invoked binder with DOMContentLoaded to ensure elements exist */
document.addEventListener('DOMContentLoaded', function(){
  try {
    const addHrBtn = document.getElementById('btnAddHr');
    if (addHrBtn) addHrBtn.addEventListener('click', (e)=> { e.preventDefault(); openAddHr(); });

    const createHrBtn = document.getElementById('btnCreateHr');
    if (createHrBtn) {
      createHrBtn.addEventListener('click', function(e){
        e.preventDefault();
        if (createHrBtn.disabled) return;
        createHrBtn.disabled = true;
        Promise.resolve().then(()=> submitAddHr()).finally(()=> createHrBtn.disabled = false);
      });
    }

    // allow Esc to close HR modal and backdrop click
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeAddHr(); });
    document.addEventListener('click', function(e){ const m = document.getElementById('addHrModal'); if (!m || m.style.display !== 'flex') return; if (e.target === m) closeAddHr(); });
  } catch (err) { console.error('binding btnAddHr:', err); }
});
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

<script>
/* Utility: generate a readable random password used by submitAdd/submitAddHr */
function randomPassword(len){
  len = parseInt(len || 10, 10);
  const upper = "ABCDEFGHJKLMNPQRSTUVWXYZ"; // avoid confusing I/O
  const lower = "abcdefghijkmnpqrstuvwxyz"; // avoid confusing l
  const digits = "23456789"; // avoid 0/1
  const specials = "!@#$%&*?";
  const all = upper + lower + digits + specials;

  // ensure at least one of each required category
  let pwd = '';
  pwd += upper.charAt(Math.floor(Math.random()*upper.length));
  pwd += lower.charAt(Math.floor(Math.random()*lower.length));
  pwd += digits.charAt(Math.floor(Math.random()*digits.length));
  pwd += specials.charAt(Math.floor(Math.random()*specials.length));

  for (let i = pwd.length; i < len; i++){
    pwd += all.charAt(Math.floor(Math.random()*all.length));
  }

  // shuffle characters
  return pwd.split('').sort(()=>0.5 - Math.random()).join('');
}
</script>

</body>
</html>