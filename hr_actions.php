<?php
session_start();
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

    // helper: get office info (name, capacity, filled)
    $getOfficeInfo = function($conn, $officeId) {
        if (!$officeId) return null;
        $stmt = $conn->prepare("SELECT office_name, current_limit FROM offices WHERE office_id = ? LIMIT 1");
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
        $cnt = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        return [
            'office_name' => $row['office_name'],
            'capacity' => is_null($row['current_limit']) ? null : (int)$row['current_limit'],
            'filled' => (int)($cnt['filled'] ?? 0)
        ];
    };

    $pref1 = !empty($res['office_preference1']) ? (int)$res['office_preference1'] : null;
    $pref2 = !empty($res['office_preference2']) ? (int)$res['office_preference2'] : null;

    $info1 = $pref1 ? $getOfficeInfo($conn, $pref1) : null;
    $info2 = $pref2 ? $getOfficeInfo($conn, $pref2) : null;

    // determine capacity availability
    $pref1_full = false;
    $pref2_full = false;

    if ($info1) {
        if ($info1['capacity'] !== null && $info1['filled'] >= $info1['capacity']) $pref1_full = true;
    } elseif ($pref1) {
        // office not found -> treat as full to avoid assigning
        $pref1_full = true;
    }

    if ($info2) {
        if ($info2['capacity'] !== null && $info2['filled'] >= $info2['capacity']) $pref2_full = true;
    } elseif ($pref2) {
        $pref2_full = true;
    }

    // If both pref1 and pref2 are present and both full -> auto-reject and notify student
    if (($pref1 && $pref2) && $pref1_full && $pref2_full) {
        $remarks = "Auto-rejected: Full slots in preferred offices.";
        $u = $conn->prepare("UPDATE ojt_applications SET status = 'rejected', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
        $u->bind_param("si", $remarks, $app_id);
        $ok = $u->execute();
        $u->close();

        if (!$ok) respond(['success' => false, 'message' => 'Failed to update application status.']);

        // send rejection email (reuse existing rejection template)
        $mailSent = false;
        $mailError = '';
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
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
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($to, $student_name);

                $mail->isHTML(true);
                $mail->Subject = "OJT Application Update";
                $mail->Body    = "<p>Dear <strong>" . htmlspecialchars($student_name) . "</strong>,</p>"
                               . "<p>We regret to inform you that your OJT application has been <strong>rejected</strong>.</p>"
                               . "<p><strong>Reason:</strong> Full slots in preferred offices.</p>"
                               . "<p>If you have questions, please contact the HR department.</p>"
                               . "<p>— HR Department</p>";

                $mail->send();
                $mailSent = true;
            } catch (Exception $e) {
                $mailError = $mail->ErrorInfo ?? $e->getMessage();
                $mailSent = false;
            }

            if (!$mailSent) {
                // fallback to PHP mail
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=utf-8\r\n";
                $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
                $mailSent = mail($to, "OJT Application Update", "<p>Your application was rejected: Full slots in preferred offices.</p>", $headers);
            }
        }

        respond(['success' => true, 'action' => 'auto_reject', 'mail' => $mailSent ? 'sent' : 'failed', 'message' => 'Application auto-rejected due to full slots.']);
    }

    // else proceed with existing behaviour: assign pref1 if available, else pref2, else approve with office name fallback
    $assignedOfficeName = '';
    if ($info1 && ($info1['capacity'] === null || $info1['filled'] < $info1['capacity'])) {
        $assignedOfficeName = $info1['office_name'];
    } elseif ($info2 && ($info2['capacity'] === null || $info2['filled'] < $info2['capacity'])) {
        $assignedOfficeName = $info2['office_name'];
    } else {
        // fallback: if either pref exists but we couldn't compute capacity (null), prefer pref1 then pref2
        if ($info1) $assignedOfficeName = $info1['office_name'];
        elseif ($info2) $assignedOfficeName = $info2['office_name'];
    }

    // update application: status, remarks, date_updated
    $remarks = "Orientation/Start: {$orientation}";
    if ($assignedOfficeName) $remarks .= " | Assigned Office: {$assignedOfficeName}";
    $u = $conn->prepare("UPDATE ojt_applications SET status = 'approved', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
    $u->bind_param("si", $remarks, $app_id);
    $ok = $u->execute();
    $u->close();

    if (!$ok) respond(['success' => false, 'message' => 'Failed to update application.']);

    // continue with account creation, email sending etc. (keep existing logic)
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

        $createdPlainPassword = substr(bin2hex(random_bytes(5)), 0, 10);
        $passwordPlainToStore = $createdPlainPassword;
        $officeForUser = $assignedOfficeName ?: null;

        $ins = $conn->prepare("INSERT INTO users (username, password, role, office_name, date_created) VALUES (?, ?, 'ojt', ?, NOW())");
        $ins->bind_param("sss", $username, $passwordPlainToStore, $officeForUser);
        $insOk = $ins->execute();
        if ($insOk) {
            $newUserId = $ins->insert_id;
            $updS = $conn->prepare("UPDATE students SET user_id = ? WHERE student_id = ?");
            $updS->bind_param("ii", $newUserId, $student_id);
            $updS->execute();
            $updS->close();

            $createdAccount = true;
            $createdUsername = $username;
        }
        $ins->close();
    }

    // update student status to ongoing
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

    // send with PHPMailer and capture debug output (existing send logic unchanged)
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

    $remarks = null;
    if ($action === 'reject') {
        $remarks = trim($input['reason'] ?? '');
        if ($remarks === '') $remarks = 'No reason provided.';
    }

    // Get student info for email if rejecting
    $student_email = '';
    $student_name = '';
    if ($action === 'reject') {
        $stmt = $conn->prepare("SELECT s.email, s.first_name, s.last_name
                                FROM ojt_applications oa
                                JOIN students s ON oa.student_id = s.student_id
                                WHERE oa.application_id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $student_email = $row['email'];
            $student_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
    }

    // Update application status and remarks
    if ($remarks !== null) {
        $stmt = $conn->prepare("UPDATE ojt_applications SET status = ?, remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
        $stmt->bind_param("ssi", $newStatus, $remarks, $app_id);
    } else {
        $stmt = $conn->prepare("UPDATE ojt_applications SET status = ?, date_updated = CURDATE() WHERE application_id = ?");
        $stmt->bind_param("si", $newStatus, $app_id);
    }
    $ok = $stmt->execute();
    $stmt->close();

    // Send rejection email if needed
    $mailSent = null;
    $mailError = '';
    if ($action === 'reject' && $ok && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
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
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($student_email, $student_name);

            $mail->isHTML(true);
            $mail->Subject = "OJT Application Rejected";
            $mail->Body    = "<p>Dear <strong>" . htmlspecialchars($student_name) . "</strong>,</p>"
                . "<p>We regret to inform you that your OJT application has been <strong>rejected</strong>.</p>"
                . "<p><strong>Reason:</strong> " . nl2br(htmlspecialchars($remarks)) . "</p>"
                . "<p>If you have questions, please contact the HR department.</p>"
                . "<p>— HR Department</p>";

            $mail->send();
            $mailSent = true;
        } catch (Exception $e) {
            $mailError = $mail->ErrorInfo ?? $e->getMessage();
            $mailSent = false;
        }
    }

    respond([
        'success' => (bool)$ok,
        'mail' => $mailSent,
        'mail_error' => $mailError
    ]);
}

/* new: get_application action
   Request body: { action: 'get_application', application_id: <int> }
   Response: { success: true, data: { ...application and student details... } }
*/
if ($action === 'get_application') {
    $app_id = isset($input['application_id']) ? (int)$input['application_id'] : 0;
    if ($app_id <= 0) respond(['success' => false, 'message' => 'Invalid application id.']);

    $stmt = $conn->prepare("
        SELECT oa.application_id, oa.office_preference1, oa.office_preference2,
               oa.letter_of_intent, oa.endorsement_letter, oa.resume, oa.moa_file, oa.picture,
               oa.status, oa.remarks, oa.date_submitted, oa.date_updated,
               s.student_id, s.first_name, s.last_name, s.address, s.contact_number, s.email,
               s.emergency_name, s.emergency_relation, s.emergency_contact,
               s.college, s.course, s.year_level, s.school_address, s.ojt_adviser, s.adviser_contact,
               s.birthday, s.hours_rendered, s.total_hours_required
        FROM ojt_applications oa
        LEFT JOIN students s ON oa.student_id = s.student_id
        WHERE oa.application_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) respond(['success' => false, 'message' => 'Application not found.']);

    // fetch office names
    $officeNames = [];
    foreach (['office_preference1','office_preference2'] as $col) {
        $id = isset($row[$col]) ? (int)$row[$col] : 0;
        if ($id > 0) {
            $r = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ? LIMIT 1");
            $r->bind_param("i", $id);
            $r->execute();
            $o = $r->get_result()->fetch_assoc();
            $r->close();
            $officeNames[$col] = $o ? $o['office_name'] : '';
        } else $officeNames[$col] = '';
    }

    // compute age if birthday exists (expect YYYY-MM-DD)
    $age = null;
    if (!empty($row['birthday'])) {
        try {
            $dob = new DateTime($row['birthday']);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        } catch (Exception $e) { $age = null; }
    }

    $data = [
        'application_id' => (int)$row['application_id'],
        'status' => $row['status'],
        'remarks' => $row['remarks'],
        'date_submitted' => $row['date_submitted'],
        'date_updated' => $row['date_updated'],
        // include both name and numeric ids so the modal can decide assignment
        'office1' => $officeNames['office_preference1'] ?? '',
        'office2' => $officeNames['office_preference2'] ?? '',
        'office_preference1' => (int)($row['office_preference1'] ?? 0),
        'office_preference2' => (int)($row['office_preference2'] ?? 0),
        'letter_of_intent' => $row['letter_of_intent'] ?? '',
        'endorsement_letter' => $row['endorsement_letter'] ?? '',
        'resume' => $row['resume'] ?? '',
        'moa_file' => $row['moa_file'] ?? '',
        'picture' => $row['picture'] ?? '',
        'student' => [
            'id' => (int)($row['student_id'] ?? 0),
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'address' => $row['address'] ?? '',
            'contact_number' => $row['contact_number'] ?? '',
            'email' => $row['email'] ?? '',
            'emergency_name' => $row['emergency_name'] ?? '',
            'emergency_relation' => $row['emergency_relation'] ?? '',
            'emergency_contact' => $row['emergency_contact'] ?? '',
            'college' => $row['college'] ?? '',
            'course' => $row['course'] ?? '',
            'year_level' => $row['year_level'] ?? '',
            'school_address' => $row['school_address'] ?? '',
            'ojt_adviser' => $row['ojt_adviser'] ?? '',
            'adviser_contact' => $row['adviser_contact'] ?? '',
            'birthday' => $row['birthday'] ?? '',
            'age' => $age,
            'hours_rendered' => (int)($row['hours_rendered'] ?? 0),
            'total_hours_required' => (int)($row['total_hours_required'] ?? 0),
        ]
    ];

    respond(['success' => true, 'data' => $data]);
}

/* new: respond_office_request action
   Request body: { action: 'respond_office_request', office_id: <int>, response: 'approve'|'decline' }
   Response: { success: true, message: 'Request processed.', ... }
*/
if ($action === 'respond_office_request') {
    $office_id = isset($input['office_id']) ? (int)$input['office_id'] : 0;
    $response  = isset($input['response']) ? strtolower(trim($input['response'])) : '';

    if ($office_id <= 0 || !in_array($response, ['approve','decline'])) {
        respond(['success'=>false,'message'=>'Invalid payload']);
    }

    // find latest pending request for this office
    $rq = $conn->prepare("SELECT request_id, old_limit, new_limit, reason, status FROM office_requests WHERE office_id = ? AND status = 'pending' ORDER BY date_requested DESC LIMIT 1");
    $rq->bind_param("i", $office_id);
    $rq->execute();
    $pending = $rq->get_result()->fetch_assoc();
    $rq->close();

    // If no explicit office_requests row exists, but offices.requested_limit is set,
    // create a request row automatically so HR can approve/decline it.
    if (!$pending) {
        $o2 = $conn->prepare("SELECT current_limit, requested_limit, reason FROM offices WHERE office_id = ? LIMIT 1");
        $o2->bind_param("i", $office_id);
        $o2->execute();
        $offRow2 = $o2->get_result()->fetch_assoc();
        $o2->close();

        $requested_limit = $offRow2['requested_limit'] ?? null;
        $reason_from_office = $offRow2['reason'] ?? '';
        $old_limit_val = isset($offRow2['current_limit']) ? (int)$offRow2['current_limit'] : 0;

        if ($requested_limit === null || $requested_limit === '') {
            respond(['success'=>false,'message'=>'No pending office request found for this office.']);
        }

        // insert a pending office_requests row
        $ins = $conn->prepare("INSERT INTO office_requests (office_id, old_limit, new_limit, reason, status, date_requested) VALUES (?, ?, ?, ?, 'pending', CURDATE())");
        if (!$ins) {
            respond(['success'=>false,'message'=>'DB prepare failed for inserting request: '.$conn->error]);
        }
        $new_limit_val = (int)$requested_limit;
        $ins->bind_param("iiis", $office_id, $old_limit_val, $new_limit_val, $reason_from_office);
        $ins_ok = $ins->execute();
        if (!$ins_ok) {
            $ins->close();
            respond(['success'=>false,'message'=>'Failed to create office request: '.$ins->error]);
        }
        $newReqId = $conn->insert_id;
        $ins->close();

        // set $pending to the newly created row so processing continues below
        $pending = [
            'request_id' => (int)$newReqId,
            'old_limit'  => $old_limit_val,
            'new_limit'  => $new_limit_val,
            'reason'     => $reason_from_office,
            'status'     => 'pending'
        ];
    }

    // get current offices.requested_limit as fallback
    $o = $conn->prepare("SELECT requested_limit FROM offices WHERE office_id = ? LIMIT 1");
    $o->bind_param("i", $office_id);
    $o->execute();
    $offRow = $o->get_result()->fetch_assoc();
    $o->close();

    $requested_limit = $pending['new_limit'] ?? $offRow['requested_limit'] ?? null;
    if ($response === 'approve' && ($requested_limit === null || $requested_limit === '')) {
        respond(['success'=>false,'message'=>'Requested limit not found.']);
    }

    // perform DB updates in transaction
    $conn->begin_transaction();
    try {
        if ($response === 'approve') {
            // update offices: set current_limit= requested, updated_limit, clear requested_limit, set status Approved
            $upd = $conn->prepare("UPDATE offices SET current_limit = ?, updated_limit = ?, requested_limit = NULL, reason = NULL, status = 'Approved' WHERE office_id = ?");
            $rl = (int)$requested_limit;
            $upd->bind_param("iii", $rl, $rl, $office_id);
            $upd_ok = $upd->execute();
            $upd->close();
            if (!$upd_ok) throw new Exception('Failed to update offices.');
            // set corresponding office_requests row status to approved
            $u2 = $conn->prepare("UPDATE office_requests SET status = 'approved' WHERE request_id = ?");
            $u2->bind_param("i", $pending['request_id']);
            $u2->execute();
            $u2->close();
        } else { // decline
            // set offices.status = 'Declined' and keep requested_limit (optional) or clear it - we'll clear requested_limit but keep reason
            $decl = $conn->prepare("UPDATE offices SET status = 'Declined' WHERE office_id = ?");
            $decl->bind_param("i", $office_id);
            $decl->execute();
            $decl->close();
            // mark office_requests row rejected
            $u3 = $conn->prepare("UPDATE office_requests SET status = 'rejected' WHERE request_id = ?");
            $u3->bind_param("i", $pending['request_id']);
            $u3->execute();
            $u3->close();
        }

        $conn->commit();
        respond(['success'=>true,'message'=>'Request processed.','action'=>$response,'office_id'=>$office_id,'new_limit'=> $response==='approve' ? (int)$requested_limit : null]);
    } catch (Exception $e) {
        $conn->rollback();
        respond(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
    }
}

/* new: get_dtr_by_date action
   Request body: { action: 'get_dtr_by_date', date: 'YYYY-MM-DD' }
   Response: { success: true, date: 'YYYY-MM-DD', rows: [ { ...dtr details... } ] }
*/
if ($action === 'get_dtr_by_date') {
    $date = trim($input['date'] ?? '');
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        respond(['success' => false, 'message' => 'Invalid date. Use YYYY-MM-DD']);
    }

    $stmt = $conn->prepare("
        SELECT d.dtr_id, d.log_date, d.am_in, d.am_out, d.pm_in, d.pm_out, d.hours, d.minutes,
               COALESCE(st.first_name, u.first_name, '') AS first_name,
               COALESCE(st.last_name, u.last_name, '') AS last_name,
               COALESCE(st.college, '') AS school,
               COALESCE(st.course, '') AS course,
               COALESCE(st.year_level, '') AS year_level,
               COALESCE(u.office_name, '') AS office
        FROM dtr d
        LEFT JOIN students st ON st.user_id = d.student_id
        LEFT JOIN users u ON u.user_id = d.student_id
        WHERE d.log_date = ?
        ORDER BY u.office_name ASC, COALESCE(st.last_name, u.username) ASC, COALESCE(st.first_name, '') ASC
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'dtr_id' => (int)$r['dtr_id'],
            'log_date' => $r['log_date'],
            'am_in' => $r['am_in'],
            'am_out' => $r['am_out'],
            'pm_in' => $r['pm_in'],
            'pm_out' => $r['pm_out'],
            'hours' => (int)($r['hours'] ?? 0),
            'minutes' => (int)($r['minutes'] ?? 0),
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'school' => $r['school'],
            'course' => $r['course'],
            'year_level' => $r['year_level'],
            'office' => $r['office']
        ];
    }
    $stmt->close();

    respond(['success' => true, 'date' => $date, 'rows' => $rows]);
}

/* new: create_account action
   Request body: { action: 'create_account', username: <string>, password: <string>, first_name: <string>, last_name: <string>, email: <string|null>, role: <string>, office: <string|null> }
   Response: { success: true, user_id: <int> } or error details
*/
if ($action === 'create_account') {
    $callerId = (int)($_SESSION['user_id'] ?? 0);
    // permission check
    $st = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
    $st->bind_param("i", $callerId);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$r || !in_array($r['role'], ['hr_head','hr_staff'])) {
        respond(['success'=>false,'message'=>'Permission denied.']);
    }

    $username   = trim($input['username'] ?? '');
    $password   = trim($input['password'] ?? '');
    $first_name = trim($input['first_name'] ?? '');
    $last_name  = trim($input['last_name'] ?? '');
    $email      = trim($input['email'] ?? '') ?: null;
    // force role to office_head for this endpoint (ignore any role passed)
    $role       = 'office_head';
    $office     = trim($input['office'] ?? '') ?: null;

    if ($username === '' || $password === '') {
        respond(['success'=>false,'message'=>'Missing required fields (username, password).']);
    }

    // ensure username unique
    $chk = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $chk->bind_param("s", $username);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        respond(['success'=>false,'message'=>'Username already exists.']);
    }
    $chk->close();

    // NOTE: storing plain password per request (INSECURE) - per your request
    $plain = $password;
    $ins = $conn->prepare("INSERT INTO users (username, first_name, last_name, password, role, office_name, email, status, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    $ins->bind_param("sssssss", $username, $first_name, $last_name, $plain, $role, $office, $email);
    $ok = $ins->execute();
    if (!$ok) {
        $ins->close();
        respond(['success'=>false,'message'=>'DB insert failed: '.$conn->error]);
    }
    $newId = $conn->insert_id;
    $ins->close();

    // only attempt to create office_heads row if table exists
    $tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $fullname = trim($first_name . ' ' . $last_name);
        $oh = $conn->prepare("INSERT INTO office_heads (user_id, full_name, email) VALUES (?, ?, ?)");
        $oh->bind_param("iss", $newId, $fullname, $email);
        $oh->execute();
        $oh->close();
    }

    // return credentials so frontend can display (do NOT expose in logs)
    respond(['success'=>true,'user_id'=>$newId,'username'=>$username,'password'=>$plain]);
}

respond(['success' => false, 'message' => 'Unknown action.']);
?>