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

    // helper: get capacity and filled count for an office id
    // filled = users with role='ojt' and status IN ('approved','ongoing')
    // NOTE: normalize office_name comparison to avoid mismatches (trim + lower)
    $getOfficeInfo = function($conn, $officeId) {
        if (!$officeId) return null;
        $stmt = $conn->prepare("SELECT office_name, current_limit FROM offices WHERE office_id = ? LIMIT 1");
        $stmt->bind_param("i", $officeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $officeName = trim($row['office_name']);
        $officeNameNorm = mb_strtolower($officeName);

        // use normalized comparison on the users table to avoid case/whitespace mismatches
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) AS filled
            FROM users
            WHERE role = 'ojt' AND status IN ('approved','ongoing') AND LOWER(TRIM(office_name)) = ?
        ");
        if (!$stmt2) {
            // defensive fallback: return capacity and assume 0 filled
            $capacity = is_null($row['current_limit']) ? null : (int)$row['current_limit'];
            return ['office_name' => $officeName, 'capacity' => $capacity, 'filled' => 0];
        }
        $stmt2->bind_param("s", $officeNameNorm);
        $stmt2->execute();
        $countRow = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        $filled = (int)($countRow['filled'] ?? 0);
        $capacity = is_null($row['current_limit']) ? null : (int)$row['current_limit'];
        return ['office_name' => $officeName, 'capacity' => $capacity, 'filled' => $filled];
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
    // new fields from frontend
    $orientation_time = trim($input['orientation_time'] ?? '');
    $orientation_location = trim($input['orientation_location'] ?? '');

    if ($app_id <= 0 || $orientation === '') {
        respond(['success' => false, 'message' => 'Missing application_id or orientation_date.']);
    }

    // normalize time: accept "HH:MM" or "HH:MM:SS"; store as HH:MM:SS
    if ($orientation_time === '') {
        $orientation_time = '08:30:00';
    } else {
        // allow "H:MM" or "HH:MM"
        if (preg_match('/^\d{1,2}:\d{2}$/', $orientation_time)) {
            $parts = explode(':', $orientation_time);
            $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $orientation_time = "{$h}:{$m}:00";
        } elseif (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $orientation_time)) {
            // normalize parts
            $parts = explode(':', $orientation_time);
            $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $s = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
            $orientation_time = "{$h}:{$m}:{$s}";
        } else {
            // fallback
            $orientation_time = '08:30:00';
        }
    }

    if ($orientation_location === '') {
        $orientation_location = 'CHRMO/3rd Floor';
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
    // filled = users with role='ojt' and status IN ('approved','ongoing') whose office_name matches this office
    $getOfficeInfo = function($conn, $officeId) {
        if (!$officeId) return null;
        $stmt = $conn->prepare("SELECT office_name, current_limit FROM offices WHERE office_id = ? LIMIT 1");
        $stmt->bind_param("i", $officeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $officeName = $row['office_name'];
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) AS filled
            FROM users
            WHERE role = 'ojt' AND status IN ('approved','ongoing') AND office_name = ?
        ");
        $stmt2->bind_param("s", $officeName);
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
        $pref1_full = true;
    }

    if ($info2) {
        if ($info2['capacity'] !== null && $info2['filled'] >= $info2['capacity']) $pref2_full = true;
    } elseif ($pref2) {
        $pref2_full = true;
    }

    // --- AUTO-REJECT with re-check using users table availability ---
    // Use available = capacity - filled (filled counted from users where role='ojt' and status IN ('approved','ongoing'))
    // Case A: both choices provided and both currently full -> auto-reject
    if ($pref1 && $pref2) {
        // re-evaluate availability in case of race conditions
        $info1 = $getOfficeInfo($conn, $pref1);
        $info2 = $getOfficeInfo($conn, $pref2);
        $cap1 = $info1 ? $info1['capacity'] : null; $filled1 = $info1 ? (int)$info1['filled'] : 0;
        $cap2 = $info2 ? $info2['capacity'] : null; $filled2 = $info2 ? (int)$info2['filled'] : 0;
        $avail1 = ($cap1 === null) ? PHP_INT_MAX : max(0, $cap1 - $filled1);
        $avail2 = ($cap2 === null) ? PHP_INT_MAX : max(0, $cap2 - $filled2);

        // if both currently have zero available slots -> auto-reject
        if ($avail1 <= 0 && $avail2 <= 0) {
            $remarks = "Auto-rejected: Full slots in preferred offices.";
            $u = $conn->prepare("UPDATE ojt_applications SET status = 'rejected', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
            $u->bind_param("si", $remarks, $app_id);
            $ok = $u->execute();
            $u->close();

            if (!$ok) respond(['success' => false, 'message' => 'Failed to update application status.']);

            // send rejection email (reuse existing rejection template)
            $mailSent = false;
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
                    $mailSent = false;
                }
            }

            respond(['success' => true, 'action' => 'auto_reject', 'mail' => $mailSent ? 'sent' : 'failed', 'message' => 'Application auto-rejected due to full slots.']);
        }
        // else at least one currently has availability -> do NOT auto-reject, leave pending for HR action
    }

    // Case B: only first choice provided (pref2 empty) -> re-evaluate pref1 availability before auto-reject
    if ($pref1 && !$pref2) {
        $info1 = $getOfficeInfo($conn, $pref1);
        $cap1 = $info1 ? $info1['capacity'] : null;
        $filled1 = $info1 ? (int)$info1['filled'] : 0;
        $avail1 = ($cap1 === null) ? PHP_INT_MAX : max(0, $cap1 - $filled1);
        // Only auto-reject when no available slots
        if ($avail1 <= 0) {
            $remarks = "Auto-rejected: Preferred office has reached capacity and no second choice was provided.";
            $u = $conn->prepare("UPDATE ojt_applications SET status = 'rejected', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
            $u->bind_param("si", $remarks, $app_id);
            $ok = $u->execute();
            $u->close();
            if (!$ok) respond(['success' => false, 'message' => 'Failed to update application status.']);

            $mailSent = false;
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
                                   . "<p><strong>Reason:</strong> Your preferred office has reached capacity and no second choice was provided.</p>"
                                   . "<p>If you have questions, please contact the HR department.</p>"
                                   . "<p>— HR Department</p>";

                    $mail->send();
                    $mailSent = true;
                } catch (Exception $e) {
                    $mailSent = false;
                }
            }

            respond(['success' => true, 'action' => 'auto_reject', 'mail' => $mailSent ? 'sent' : 'failed', 'message' => 'Application auto-rejected: preferred office full and no second choice.']);
        }
        // else available -> do not auto-reject
    }

    // decide assigned office (same logic as before)
    $assignedOfficeName = '';
    if ($info1 && ($info1['capacity'] === null || $info1['filled'] < $info1['capacity'])) {
        $assignedOfficeName = $info1['office_name'];
    } elseif ($info2 && ($info2['capacity'] === null || $info2['filled'] < $info2['capacity'])) {
        $assignedOfficeName = $info2['office_name'];
    } else {
        if ($info1) $assignedOfficeName = $info1['office_name'];
        elseif ($info2) $assignedOfficeName = $info2['office_name'];
    }

    // update application: status, remarks, date_updated
    // include orientation time/location in remarks
    $remarks = "Orientation/Start: {$orientation}";
    // append time and location if provided
    if (!empty($orientation_time)) {
        $remarks .= " {$orientation_time}";
    }
    if (!empty($orientation_location)) {
        $remarks .= " | Location: {$orientation_location}";
    }
    if ($assignedOfficeName) $remarks .= " | Assigned Office: {$assignedOfficeName}";

    $u = $conn->prepare("UPDATE ojt_applications SET status = 'approved', remarks = ?, date_updated = CURDATE() WHERE application_id = ?");
    $u->bind_param("si", $remarks, $app_id);
    $ok = $u->execute();
    $u->close();

    if (!$ok) respond(['success' => false, 'message' => 'Failed to update application.']);

    // create/find orientation session and assign application to it
    try {
        // check if orientation_sessions table exists
        $tblChk = $conn->query("SHOW TABLES LIKE 'orientation_sessions'");
        if ($tblChk && $tblChk->num_rows > 0) {
            // try to find existing session for same date/time/location
            $sfind = $conn->prepare("SELECT session_id FROM orientation_sessions WHERE session_date = ? AND session_time = ? AND location = ? LIMIT 1");
            $sfind->bind_param("sss", $orientation, $orientation_time, $orientation_location);
            $sfind->execute();
            $sres = $sfind->get_result()->fetch_assoc();
            $sfind->close();

            if ($sres && !empty($sres['session_id'])) {
                $session_id = (int)$sres['session_id'];
            } else {
                // create new session
                $sins = $conn->prepare("INSERT INTO orientation_sessions (session_date, session_time, location) VALUES (?, ?, ?)");
                $sins->bind_param("sss", $orientation, $orientation_time, $orientation_location);
                $sins->execute();
                $session_id = (int)$sins->insert_id;
                $sins->close();
            }

            // insert assignment (ignore duplicates)
            if (!empty($session_id)) {
                $ass = $conn->prepare("INSERT IGNORE INTO orientation_assignments (session_id, application_id) VALUES (?, ?)");
                $ass->bind_param("ii", $session_id, $app_id);
                $ass->execute();
                $ass->close();
            }
            if ($tblChk) $tblChk->free();
        }
    } catch (Exception $e) {
        // non-fatal: log and continue
        error_log("Orientation session assign error: " . $e->getMessage());
    }

    // continue with account creation, student status update, and email send (existing logic)
    // create user account for student if not already linked
    $createdAccount = false;
    $createdUsername = '';
    $createdPlainPassword = '';

    $chk = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
    $chk->bind_param("i", $student_id);
    $chk->execute();
    $rowChk = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (empty($rowChk['user_id'])) {
        // --- existing code that creates a new users row ---
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

    // --- CHANGED: set user account status = 'approved' (do NOT mark student as ongoing yet)
    // Find the user_id for this student (either pre-existing or the one we just created)
    $targetUserId = null;
    if (!empty($rowChk['user_id'])) {
        $targetUserId = (int)$rowChk['user_id'];
    } elseif (!empty($newUserId)) {
        $targetUserId = (int)$newUserId;
    }

    if ($targetUserId) {
        $updUserStatus = $conn->prepare("UPDATE users SET status = 'approved' WHERE user_id = ?");
        $updUserStatus->bind_param("i", $targetUserId);
        $updUserStatus->execute();
        $updUserStatus->close();
    }

    // Keep student.status as 'pending' after HR approval.
    // Student will transition to 'ongoing' when the first DTR/time-in entry is created (DTR handler must perform that update).
    $updStudent = $conn->prepare("UPDATE students SET status = 'pending' WHERE student_id = ?");
    $updStudent->bind_param("i", $student_id);
    $updStudent->execute();
    $updStudent->close();

    // prepare email content (HTML) — format orientation date + time + location
    $subject = "OJT Application Approved";

    $orientation_display = $orientation;
    try {
        if (!empty($orientation)) {
            $dt = new DateTime($orientation);
            $orientation_display = $dt->format('F j, Y'); // e.g. November 11, 2024
        }
    } catch (Exception $e) {
        $orientation_display = $orientation;
    }

    // format time for display (HH:MM)
    $time_display = substr($orientation_time,0,5);

    try {
        $remarks_formatted = "Orientation/Start: {$orientation_display} {$time_display} | Location: {$orientation_location}";
        if ($assignedOfficeName) $remarks_formatted .= " | Assigned Office: {$assignedOfficeName}";
        $updRemarksStmt = $conn->prepare("UPDATE ojt_applications SET remarks = ? WHERE application_id = ?");
        if ($updRemarksStmt) {
            $updRemarksStmt->bind_param("si", $remarks_formatted, $app_id);
            $updRemarksStmt->execute();
            $updRemarksStmt->close();
        }
    } catch (Exception $e) {
        // ignore
    }

    $deadline = date('F j, Y', strtotime('+7 days'));
    $loginUrl = '/login.php';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $loginUrl = $scheme . $_SERVER['HTTP_HOST'] . '/login.php';
    }

    $html = "<p>Hi <strong>" . htmlspecialchars($student_name) . "</strong>,</p>"
          . "<p>Your OJT application has been <strong>approved</strong>.</p>"
          . "<p><strong>Orientation / Starting Date:</strong> " . htmlspecialchars($orientation_display) . " at " . htmlspecialchars($time_display) . "</p>"
          . "<p><strong>Location:</strong> " . htmlspecialchars($orientation_location) . "</p>"
          . ($assignedOfficeName ? "<p><strong>Assigned Office:</strong> " . htmlspecialchars($assignedOfficeName) . "</p>" : "");

    if ($createdAccount) {
        $html .= "<p><strong>Your student account has been created:</strong></p>"
              . "<p>Username: <code>" . htmlspecialchars($createdUsername) . "</code><br>"
              . "Password: <code>" . htmlspecialchars($createdPlainPassword) . "</code></p>"
              . "<p>Please login and change your password as soon as possible.</p>";
    } else {
        $html .= "<p>If you already have an account, use your existing credentials to login.</p>";
    }

    $html .= "<p style=\"background:#fff4f4;padding:10px;border-radius:6px;\"><strong>Important:</strong> Please log in within 7 days (by <strong>" . htmlspecialchars($deadline) . "</strong>) to secure your assigned slot. Failure to log in within 7 days from the date of this email will result in forfeiture of your assigned slot.</p>"
          . "<p>You may log in here: <a href=\"" . htmlspecialchars($loginUrl) . "\">" . htmlspecialchars($loginUrl) . "</a></p>"
          . "<p style=\"margin-top:12px;\"><strong>Bring hard copies:</strong> Please bring the original/hard copy of all required documents (Letter of Intent, Resume, Endorsement Letter, and 1x1 Formal Picture) to the Orientation/Start date listed above. Present these to the HR staff to secure your placement.</p>"
          . "<p>Please follow instructions sent by HR. Thank you.</p>"
          . "<p>— HR Department</p>";

    $hardcopy_note = "<p><strong>Hard copy requirements:</strong> Our records indicate that we have received all required soft copy documents for your application.</p>";
    $html = $hardcopy_note . $html;

    try {
        $colChk = $conn->query("SHOW COLUMNS FROM `ojt_applications` LIKE 'hard_copies_received'");
        if ($colChk && $colChk->num_rows > 0) {
            $up = $conn->prepare("UPDATE ojt_applications SET hard_copies_received = 1 WHERE application_id = ? LIMIT 1");
            if ($up) {
                $up->bind_param("i", $app_id);
                $up->execute();
                $up->close();
            }
        }
        if ($colChk) $colChk->free();
    } catch (Exception $e) {
        // ignore
    }

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

    respond([
        'success' => true,
        'mail' => $mailSent ? 'sent' : 'failed',
        'debug' => $debugLog,
        'account_created' => $createdAccount,
        'username' => $createdUsername,
        // include assigned session info for frontend if available
        'orientation' => [
            'date' => $orientation,
            'time' => $orientation_time,
            'location' => $orientation_location,
            'assigned_office' => $assignedOfficeName,
            'session_id' => isset($session_id) ? (int)$session_id : null
        ]
    ]);
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
    $student_id = 0;
    if ($action === 'reject') {
        // include student_id so we can store the reason into students table
        $stmt = $conn->prepare("SELECT s.student_id, s.email, s.first_name, s.last_name
                                FROM ojt_applications oa
                                JOIN students s ON oa.student_id = s.student_id
                                WHERE oa.application_id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $student_id = (int)($row['student_id'] ?? 0);
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

    // store reject reason into students.reason when manual reject
    if ($action === 'reject' && $ok && $student_id) {
        $up = $conn->prepare("UPDATE students SET reason = ? WHERE student_id = ?");
        if ($up) {
            $up->bind_param("si", $remarks, $student_id);
            $up->execute();
            $up->close();
        }
    }

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

    // sanitize remarks: hide auto-reject messages from the View modal
    $rawRemarks = $row['remarks'] ?? '';
    if (preg_match('/^\s*Auto-?reject(ed)?\s*:/i', $rawRemarks)) {
        $displayRemarks = ''; // do not show auto-reject text in the modal
    } else {
        $displayRemarks = $rawRemarks;
    }

    $data = [
        'application_id' => (int)$row['application_id'],
        'status' => $row['status'],
        'remarks' => $displayRemarks,
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

    // find latest pending request for this office (case-insensitive status check)
    $rq = $conn->prepare("SELECT request_id, old_limit, new_limit, reason, status FROM office_requests WHERE office_id = ? AND LOWER(status) = 'pending' ORDER BY date_requested DESC LIMIT 1");
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

        // insert a pending office_requests row (use NOW() for datetime)
        $ins = $conn->prepare("INSERT INTO office_requests (office_id, old_limit, new_limit, reason, status, date_requested) VALUES (?, ?, ?, ?, 'pending', NOW())");
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
            // update offices: set current_limit = requested, clear requested fields, set status = 'approved'
            $upd = $conn->prepare("UPDATE offices SET current_limit = ?, requested_limit = NULL, reason = NULL, status = 'approved' WHERE office_id = ?");
            $rl = (int)$requested_limit;
            $upd->bind_param("ii", $rl, $office_id);
            $upd_ok = $upd->execute();
            $upd->close();
            if (!$upd_ok) throw new Exception('Failed to update offices.');

            // set corresponding office_requests row status to approved and record date_of_action
            $u2 = $conn->prepare("UPDATE office_requests SET status = 'approved', date_of_action = NOW() WHERE request_id = ?");
            $u2->bind_param("i", $pending['request_id']);
            $u2->execute();
            $u2->close();
        } else { // decline
            // clear requested fields and mark office status as 'declined'
            $decl = $conn->prepare("UPDATE offices SET requested_limit = NULL, reason = NULL, status = 'declined' WHERE office_id = ?");
            $decl->bind_param("i", $office_id);
            $decl->execute();
            $decl->close();

            // mark office_requests row rejected/declined and set date_of_action
            $u3 = $conn->prepare("UPDATE office_requests SET status = 'rejected', date_of_action = NOW() WHERE request_id = ?");
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
               u.user_id AS u_id, u.first_name AS u_first, u.last_name AS u_last, u.role AS u_role, u.office_name AS u_office,
               su.student_id AS su_id, su.first_name AS su_first, su.last_name AS su_last, su.college AS su_college, su.course AS su_course,
               si.student_id AS si_id, si.first_name AS si_first, si.last_name AS si_last, si.college AS si_college, si.course AS si_course
        FROM dtr d
        LEFT JOIN users u ON u.user_id = d.student_id
        LEFT JOIN students su ON su.user_id = d.student_id        -- student linked to user account (preferred)
        LEFT JOIN students si ON si.student_id = d.student_id    -- student by id (fallback)
        WHERE d.log_date = ?
        ORDER BY COALESCE(su.last_name, si.last_name, u.last_name) ASC, COALESCE(su.first_name, si.first_name, u.first_name) ASC
    ");
    if (!$stmt) respond(['success' => false, 'message' => 'Prepare failed: '.$conn->error]);

    $stmt->bind_param('s', $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        // Priority for display name:
        // 1) student record linked by students.user_id = d.student_id (su_)
        // 2) student record where students.student_id = d.student_id (si_)
        // 3) fallback to users table (u_)
        $first = $last = $school = $course = '';

        if (!empty($r['su_first']) || !empty($r['su_last'])) {
            $first = $r['su_first'] ?? '';
            $last  = $r['su_last'] ?? '';
            $school = $r['su_college'] ?? '';
            $course = $r['su_course'] ?? '';
        } elseif (!empty($r['si_first']) || !empty($r['si_last'])) {
            $first = $r['si_first'] ?? '';
            $last  = $r['si_last'] ?? '';
            $school = $r['si_college'] ?? '';
            $course = $r['si_course'] ?? '';
        } elseif (!empty($r['u_first']) || !empty($r['u_last'])) {
            $first = $r['u_first'] ?? '';
            $last  = $r['u_last'] ?? '';
            // try to prefer school/course from linked student if any (already attempted), otherwise empty
        }

        // normalize times to HH:MM
        foreach (['am_in','am_out','pm_in','pm_out'] as $t) {
            if (!empty($r[$t])) $r[$t] = substr($r[$t], 0, 5);
        }

        $rows[] = [
            'dtr_id' => (int)$r['dtr_id'],
            'log_date' => $r['log_date'],
            'am_in' => $r['am_in'] ?? '',
            'am_out' => $r['am_out'] ?? '',
            'pm_in' => $r['pm_in'] ?? '',
            'pm_out' => $r['pm_out'] ?? '',
            'hours' => (int)($r['hours'] ?? 0),
            'minutes' => (int)($r['minutes'] ?? 0),
            'first_name' => $first,
            'last_name' => $last,
            'school' => $school,
            'course' => $course,
            'office' => $r['u_office'] ?? ''
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
    // allow caller to request role, but only accept these two values
    $requestedRole = trim($input['role'] ?? 'office_head');
    $role = in_array($requestedRole, ['office_head','hr_staff']) ? $requestedRole : 'office_head';
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
    // insert into users table (office_name may be null for hr_staff)
    $ins = $conn->prepare("INSERT INTO users (username, first_name, last_name, password, role, office_name, email, status, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    $ins->bind_param("sssssss", $username, $first_name, $last_name, $plain, $role, $office, $email);
    $ok = $ins->execute();
    if (!$ok) {
        $ins->close();
        respond(['success'=>false,'message'=>'DB insert failed: '.$conn->error]);
    }
    $newId = $conn->insert_id;
    $ins->close();

    // Only run office-specific logic (resolve/create office, office_heads row, courses mapping) when creating office_head
    if ($role === 'office_head') {
        // --- NEW: resolve or create office row and get office_id ---
        $office_id = null;
        if (!empty($office)) {
            // try exact case-insensitive match first
            $so = $conn->prepare("SELECT office_id FROM offices WHERE LOWER(office_name) = LOWER(?) LIMIT 1");
            $so->bind_param("s", $office);
            $so->execute();
            $or = $so->get_result()->fetch_assoc();
            $so->close();
            if ($or && !empty($or['office_id'])) {
                $office_id = (int)$or['office_id'];
            } else {
                // try LIKE fallback (handles small variations)
                $like = '%' . strtolower($office) . '%';
                $sl = $conn->prepare("SELECT office_id FROM offices WHERE LOWER(office_name) LIKE ? LIMIT 1");
                $sl->bind_param("s", $like);
                $sl->execute();
                $orl = $sl->get_result()->fetch_assoc();
                $sl->close();
                if ($orl && !empty($orl['office_id'])) {
                    $office_id = (int)$orl['office_id'];
                } else {
                    // create new office record with provided initial limit (if any)
                    $insOff = $conn->prepare("INSERT INTO offices (office_name, current_limit, status) VALUES (?, ?, 'Approved')");
                    $curLimit = (int)($input['initial_limit'] ?? 0);
                    $insOff->bind_param("si", $office, $curLimit);
                    $insOff->execute();
                    $office_id = $insOff->insert_id ?: null;
                    $insOff->close();
                }
            }
        }

        // only attempt office_heads row creation if table exists (existing behavior)
        $tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
        if ($tblCheck && $tblCheck->num_rows > 0) {
            $fullname = trim($first_name . ' ' . $last_name);
            $oh = $conn->prepare("INSERT INTO office_heads (user_id, full_name, email, office_id) VALUES (?, ?, ?, ?)");
            if ($oh) {
                $officeParam = $office_id === null ? null : $office_id;
                $oh->bind_param("issi", $newId, $fullname, $email, $officeParam);
                $oh->execute();
                $oh->close();
            }
        }

        // --- NEW: store courses and map to office ---
        $accept_courses = trim($input['accept_courses'] ?? '');
        if ($accept_courses !== '') {
            $courseNames = array_filter(array_map('trim', explode(',', $accept_courses)));
            foreach ($courseNames as $cname) {
                if ($cname === '') continue;
                // try case-insensitive match first
                $stmt = $conn->prepare("SELECT course_id FROM courses WHERE LOWER(course_name) = LOWER(?) OR (course_code IS NOT NULL AND LOWER(course_code) = LOWER(?)) LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('ss', $cname, $cname);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } else {
                    $row = null;
                }

                if ($row && !empty($row['course_id'])) {
                    $course_id = (int)$row['course_id'];
                } else {
                    $insC = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
                    if ($insC) {
                        $insC->bind_param('s', $cname);
                        $insC->execute();
                        $course_id = (int)$insC->insert_id;
                        $insC->close();
                    } else {
                        // skip on failure
                        continue;
                    }
                }

                // map to office if we resolved/created one
                if (!empty($office_id) && !empty($course_id)) {
                    $map = $conn->prepare("INSERT IGNORE INTO office_courses (office_id, course_id) VALUES (?, ?)");
                    if ($map) {
                        $map->bind_param('ii', $office_id, $course_id);
                        $map->execute();
                        $map->close();
                    }
                }
            }
        }
        // --- end new courses handling ---
    } // end office_head-only section

    // return credentials so frontend can display (do NOT expose in logs)
    respond(['success'=>true,'user_id'=>$newId,'username'=>$username,'password'=>$plain]);
}

/* later where courses are processed (keeps behavior but uses resolved $office_id) */
if (!empty($courses)) {
    foreach ($courses as $cname) {
        if ($cname === '') continue;
        // find or insert course (existing code)
        $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_name = ? OR course_code = ? LIMIT 1");
        $stmt->bind_param('ss', $cname, $cname);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['course_id'])) {
            $course_id = (int)$row['course_id'];
        } else {
            $ins = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
            $ins->bind_param('s', $cname);
            $ins->execute();
            $course_id = $ins->insert_id;
            $ins->close();
        }

        // map to office if we have an office_id
        if (!empty($office_id)) {
            $map = $conn->prepare("INSERT IGNORE INTO office_courses (office_id, course_id) VALUES (?, ?)");
            $map->bind_param('ii', $office_id, $course_id);
            $map->execute();
            $map->close();
        }
    }
}

respond(['success' => false, 'message' => 'Unknown action.']);
?>