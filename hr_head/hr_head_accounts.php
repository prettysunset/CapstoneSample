<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

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

// fetch HR user info for sidebar
$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmtU = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmtU->bind_param("i", $user_id);
$stmtU->execute();
$user = $stmtU->get_result()->fetch_assoc() ?: [];
$stmtU->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

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
    <!-- top-right outline icons: notifications, settings, logout
         NOTE: removed position:fixed to prevent overlapping; icons now flow with page
         and stay visible. -->
    <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </a>
        <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
        </a>
        <a id="top-logout" href="/logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
    <div class="card" role="region" aria-label="Accounts">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px">
      <div>
        <h2 style="margin:0 0 6px;font-size:20px">Accounts</h2>
      </div>
      <div style="text-align:right;color:#62718a;font-size:13px">
        <div><?php echo date('F j, Y'); ?></div>
        <div style="font-weight:700;margin-top:6px"><?php echo htmlspecialchars($full_name ?: ($_SESSION['username'] ?? '')); ?></div>
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
        </div>
      </div>

      <div class="controls">
        <div style="position:relative;width:360px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#62718a">
            <circle cx="11" cy="11" r="7"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="search" placeholder="Search name / email / office" style="width:100%;padding:10px 10px 10px 36px;border:1px solid #ddd;border-radius:8px" />
        </div>
        <div style="flex:1"></div>
        <button class="btn btn-add" id="btnAdd">Add Account</button>
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
                    <button class="icon-btn" title="Edit" onclick="editAccount(<?= (int)$o['user_id'] ?>)">✏️</button>
                    <button class="icon-btn" title="<?= $status==='active' ? 'Deactivate' : 'Activate' ?>" onclick="toggleStatus(<?= (int)$o['user_id'] ?>, this)"><?= $status==='active' ? '🔓' : '🔒' ?></button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-hr" class="panel" style="display:none">
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
                    <button class="icon-btn" title="Edit" onclick="editAccount(<?= (int)$h['user_id'] ?>)">✏️</button>
                    <button class="icon-btn" title="<?= ($h['status']==='active' ? 'Deactivate' : 'Activate') ?>" onclick="toggleStatus(<?= (int)$h['user_id'] ?>, this)"><?= ($h['status']==='active' ? '🔓' : '🔒') ?></button>
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
  // tab switching
  document.querySelectorAll('.tabs button').forEach(btn=>{
    btn.addEventListener('click', function(){
      document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      const t = this.getAttribute('data-tab');
      document.getElementById('panel-office').style.display = t === 'office' ? 'block' : 'none';
      document.getElementById('panel-hr').style.display = t === 'hr' ? 'block' : 'none';
    });
  });

  // search filter
  const search = document.getElementById('search');
  search.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('tbody tr[data-search]').forEach(tr=>{
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q) === -1 ? 'none' : '';
    });
  });

  // open modal instead of navigating
  document.getElementById('btnAdd').addEventListener('click', ()=> {
    openAdd();
  });

})();

function editAccount(userId) {
  // navigate to edit page (implement page separately)
  window.location.href = 'account_edit.php?id=' + encodeURIComponent(userId);
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

// simple random password generator used by submitAdd()
function randomPassword(len = 10){
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let out = '';
  for (let i = 0; i < len; i++) out += chars.charAt(Math.floor(Math.random() * chars.length));
  return out;
}

// close modal when clicking backdrop
document.addEventListener('click', function(e){
  const m = document.getElementById('addModal');
  if (!m || m.style.display !== 'flex') return;
  // if clicked directly on backdrop (not the modal card)
  if (e.target === m) closeAdd();
});

// allow ESC to close modal
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeAdd();
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
    // server-side check: office existence
    const chk = await fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'check_office', office: office })
    });
    const chkJson = await chk.json();
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
    const j = await res.json();
    if (!j || !j.success) {
      statusEl.style.background = '#fff4f4';
      statusEl.style.color = '#a00';
      statusEl.textContent = 'Failed: ' + (j?.message || 'Unknown error');
      return;
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
    const mailJson = await mailRes.json();

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
    console.error(err);
    statusEl.style.background = '#fff4f4';
    statusEl.style.color = '#a00';
    statusEl.textContent = 'Request failed.';
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
</body>
</html>