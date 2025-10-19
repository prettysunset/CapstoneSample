<?php
// Development helpers: return JSON on any error/exception so frontend won't get "Request failed"
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// buffer output so we can always return JSON
ob_start();

set_exception_handler(function($e){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Exception: '.$e->getMessage()]);
    exit;
});

register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // clear any partial output
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . ($err['message'] ?? ''),
            'file' => $err['file'] ?? '',
            'line' => $err['line'] ?? 0
        ]);
        exit;
    }
});

require_once __DIR__ . '/conn.php';

// Load Composer autoload if present (safe) and then ensure PHPMailer classes are available.
// If Composer didn't install phpmailer, fall back to the bundled PHPMailer/src files.
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// If PHPMailer class still not available, include local PHPMailer/src files
if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    $pmPath = __DIR__ . '/PHPMailer/src/';
    if (file_exists($pmPath . 'PHPMailer.php')) {
        require_once $pmPath . 'Exception.php';
        require_once $pmPath . 'PHPMailer.php';
        require_once $pmPath . 'SMTP.php';
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PHPMailer not found. Run: composer require phpmailer/phpmailer OR add PHPMailer/src files.']);
        exit;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$action = $input['action'] ?? '';

/* new: check_capacity action
   Request body: { action: 'check_capacity', office1: <int|null>, office2: <int|null> }
   Response: { success: true, assigned: "Office Name" } or assigned: "" if none available
*/
if ($action === 'check_capacity') {
    $office1 = isset($input['office1']) ? (int)$input['office1'] : 0;
    $office2 = isset($input['office2']) ? (int)$input['office2'] : 0;

    // helper to get capacity and filled count for an office id
    $getOfficeInfo = function($conn, $officeId) {
        if (!$officeId) return null;
        // capacity column in schema is current_limit
        $stmt = $conn->prepare("SELECT office_name, current_limit FROM offices WHERE office_id = ?");
        $stmt->bind_param("i", $officeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $stmt2 = $conn->prepare("
            SELECT COUNT(DISTINCT student_id) AS filled
            FROM ojt_applications
            WHERE (office_preference1 = ? OR office_preference2 = ?) AND status = 'approved'
        ");
        $stmt2->bind_param("ii", $officeId, $officeId);
        $stmt2->execute();
        $countRow = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $filled = (int)($countRow['filled'] ?? 0);
        $capacity = is_null($row['current_limit']) ? null : (int)$row['current_limit'];
        return ['office_name' => $row['office_name'], 'capacity' => $capacity, 'filled' => $filled];
    };

    $assigned = '';
    // prefer office1 if available
    $info1 = $getOfficeInfo($conn, $office1);
    if ($info1) {
        if ($info1['capacity'] === null || $info1['filled'] < $info1['capacity']) {
            $assigned = $info1['office_name'];
        }
    }
    // else try office2
    if (empty($assigned) && $office2) {
        $info2 = $getOfficeInfo($conn, $office2);
        if ($info2 && ($info2['capacity'] === null || $info2['filled'] < $info2['capacity'])) {
            $assigned = $info2['office_name'];
        }
    }

    echo json_encode(['success' => true, 'assigned' => $assigned]);
    exit;
}

/* SMTP (use same working creds as samplegmail.php) */
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'sample.mail00000000@gmail.com';   // set your working smtp user
const SMTP_PASS = 'qitthwgfhtogjczq';                // set your working app password
const SMTP_FROM_EMAIL = 'sample.mail00000000@gmail.com';
const SMTP_FROM_NAME  = 'OJTMS HR';

function respond($data) {
    echo json_encode($data);
    exit;
}

if ($action === 'approve_send') {
    $app_id = isset($input['application_id']) ? (int)$input['application_id'] : 0;
    $orientation = trim($input['orientation_date'] ?? '');

    if ($app_id <= 0 || $orientation === '') {
        respond(['success' => false, 'message' => 'Missing application_id or orientation_date.']);
    }

    // fetch application + student (include preferences)
    $stmt = $conn->prepare("
        SELECT oa.student_id, oa.office_preference1, oa.office_preference2, s.email, s.first_name, s.last_name
        FROM ojt_applications oa
        JOIN students s ON oa.student_id = s.student_id
        WHERE oa.application_id = ?
    ");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) respond(['success' => false, 'message' => 'Application not found.']);

    $student_id = (int)$res['student_id'];
    $to = $res['email'];
    $student_name = trim(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? ''));

    // Determine assignment (prefer first, fallback to second)
    $pref1 = !empty($res['office_preference1']) ? (int)$res['office_preference1'] : null;
    $pref2 = !empty($res['office_preference2']) ? (int)$res['office_preference2'] : null;
    $assignedOfficeName = '';

    if ($pref1) {
        $r = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ?");
        $r->bind_param("i", $pref1);
        $r->execute();
        $o1 = $r->get_result()->fetch_assoc();
        $r->close();
        if ($o1) $assignedOfficeName = $o1['office_name'];
    }
    if (!$assignedOfficeName && $pref2) {
        $r = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ?");
        $r->bind_param("i", $pref2);
        $r->execute();
        $o2 = $r->get_result()->fetch_assoc();
        $r->close();
        if ($o2) $assignedOfficeName = $o2['office_name'];
    }

    // update application: status, remarks, date_updated
    $remarks = "Orientation/Start: {$orientation}";
    if ($assignedOfficeName) $remarks .= " | Assigned Office: {$assignedOfficeName}";
    $u = $conn->prepare("UPDATE ojt_applications SET status = 'approved', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
    $u->bind_param("si", $remarks, $app_id);
    $ok = $u->execute();
    $u->close();

    if (!$ok) respond(['success' => false, 'message' => 'Failed to update application.']);

    // create user account for student if not already linked
    $createdAccount = false;
    $createdUsername = '';
    $createdPlainPassword = '';

    // check existing user_id in students
    $chk = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
    $chk->bind_param("i", $student_id);
    $chk->execute();
    $rowChk = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (empty($rowChk['user_id'])) {
        // helper to build unique username base
        $emailLocal = '';
        if (!empty($to) && strpos($to, '@') !== false) $emailLocal = strtolower(explode('@', $to)[0]);
        $base = $emailLocal ?: strtolower(preg_replace('/[^a-z0-9]/', '', substr($res['first_name'],0,1) . $res['last_name']));

        if ($base === '') $base = 'student' . $student_id;

        // ensure uniqueness
        $username = $base;
        $i = 0;
        $existsStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        while (true) {
            $existsStmt->bind_param("s", $username);
            $existsStmt->execute();
            $er = $existsStmt->get_result()->fetch_assoc();
            if (!$er) break;
            $i++;
            $username = $base . $i;
        }
        $existsStmt->close();

        // generate random password (plain for email, store plain to match current login.php)
        $createdPlainPassword = substr(bin2hex(random_bytes(5)), 0, 10);
        // NOTE: storing plaintext — kept to match your current login.php. Replace with hashed value later.
        $passwordPlainToStore = $createdPlainPassword;

        // insert into users (store plaintext password to match existing login logic)
        $officeForUser = $assignedOfficeName ?: null;
        $ins = $conn->prepare("INSERT INTO users (username, password, role, office_name, date_created) VALUES (?, ?, 'ojt', ?, NOW())");
        $ins->bind_param("sss", $username, $passwordPlainToStore, $officeForUser);
        $insOk = $ins->execute();
        if ($insOk) {
            $newUserId = $ins->insert_id;
            // link to students.user_id
            $updS = $conn->prepare("UPDATE students SET user_id = ? WHERE student_id = ?");
            $updS->bind_param("ii", $newUserId, $student_id);
            $updS->execute();
            $updS->close();

            $createdAccount = true;
            $createdUsername = $username;
        }
        $ins->close();
    }

    // update student status to ongoing (optional business rule)
    $u2 = $conn->prepare("UPDATE students SET status = 'ongoing' WHERE student_id = ?");
    $u2->bind_param("i", $student_id);
    $u2->execute();
    $u2->close();

    // prepare email content (HTML)
    $subject = "OJT Application Approved";
    $html = "<p>Hi <strong>" . htmlspecialchars($student_name) . "</strong>,</p>"
          . "<p>Your OJT application has been <strong>approved</strong>.</p>"
          . "<p><strong>Orientation / Starting Date:</strong> " . htmlspecialchars($orientation) . "</p>"
          . ($assignedOfficeName ? "<p><strong>Assigned Office:</strong> " . htmlspecialchars($assignedOfficeName) . "</p>" : "");

    if ($createdAccount) {
        $html .= "<p><strong>Your student account has been created:</strong></p>"
              . "<p>Username: <code>" . htmlspecialchars($createdUsername) . "</code><br>"
              . "Password: <code>" . htmlspecialchars($createdPlainPassword) . "</code></p>"
              . "<p>Please login and change your password as soon as possible.</p>";
    } else {
        $html .= "<p>If you already have an account, use your existing credentials to login.</p>";
    }

    $html .= "<p>Please follow instructions sent by HR. Thank you.</p>"
           . "<p>— HR Department</p>";

    // send with PHPMailer and capture debug output
    $mailSent = false;
    $debugLog = '';

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // debugging - set to 0 once working
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use (&$debugLog) {
            $debugLog .= trim($str) . "\n";
        };

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) $mail->addAddress($to, $student_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        $mail->send();
        $mailSent = true;
    } catch (Exception $e) {
        $debugLog .= ($mail->ErrorInfo ?? $e->getMessage()) . "\n";
        $mailSent = false;
    }

    // fallback to PHP mail() if PHPMailer fails
    if (!$mailSent && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $mailSent = mail($to, $subject, $html, $headers);
        if (!$mailSent) $debugLog .= "PHP mail() fallback failed\n";
    }

    respond(['success' => true, 'mail' => $mailSent ? 'sent' : 'failed', 'debug' => $debugLog, 'account_created' => $createdAccount, 'username' => $createdUsername]);
}

// quick reject/approve endpoints (no email)
if ($action === 'reject' || $action === 'approve') {
    $app_id = isset($input['application_id']) ? (int)$input['application_id'] : 0;
    if ($app_id <= 0) respond(['success' => false, 'message' => 'Invalid application id.']);
    $newStatus = $action === 'reject' ? 'rejected' : 'approved';
    $stmt = $conn->prepare("UPDATE ojt_applications SET status = ?, date_updated = CURDATE() WHERE application_id = ?");
    $stmt->bind_param("si", $newStatus, $app_id);
    $ok = $stmt->execute();
    $stmt->close();
    respond(['success' => (bool)$ok]);
}

respond(['success' => false, 'message' => 'Unknown action.']);
?>