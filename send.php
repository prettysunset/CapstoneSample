<?php
// send.php - handles forgot-password OTP sending
require_once __DIR__ . '/conn.php';

// load email config (create config/email_config.php with real creds)
$emailCfg = __DIR__ . '/config/email_config.php';
if (!file_exists($emailCfg)) {
    // config missing: redirect with explicit status to help debugging
    header('Location: forgot_password.php?status=missing_config');
    exit;
}
require_once $emailCfg;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Prefer composer's autoloader if available, otherwise include PHPMailer sources like other pages
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    $pmPath = __DIR__ . '/PHPMailer/src/';
    if (file_exists($pmPath . 'PHPMailer.php')) {
        require_once $pmPath . 'Exception.php';
        require_once $pmPath . 'PHPMailer.php';
        require_once $pmPath . 'SMTP.php';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    // log invalid email attempt
    @file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] Invalid email input\n", FILE_APPEND | LOCK_EX);
    header('Location: forgot_password.php?status=error');
    exit;
}

// find user by email in `users`
$stmt = $conn->prepare('SELECT user_id, email, first_name FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = null;
if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
} else {
    // not found in users => check students table
    $stmt2 = $conn->prepare('SELECT student_id, user_id, email FROM students WHERE email = ? LIMIT 1');
    $stmt2->bind_param('s', $email);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2->num_rows > 0) {
        $student = $res2->fetch_assoc();
        // ensure student is linked to a user account
        if (!empty($student['user_id'])) {
            $stmt3 = $conn->prepare('SELECT user_id, email, first_name FROM users WHERE user_id = ? LIMIT 1');
            $stmt3->bind_param('i', $student['user_id']);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            if ($res3->num_rows > 0) {
                $user = $res3->fetch_assoc();
                // ensure email variable is the provided email (student may have email)
            } else {
                // student linked user_id not present in users table
                header('Location: forgot_password.php?status=not_user');
                exit;
            }
        } else {
            // student exists but not linked to user account
            header('Location: forgot_password.php?status=not_user');
            exit;
        }
    } else {
        // email not found in users or students
        @file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] Email not found: {$email}\n", FILE_APPEND | LOCK_EX);
        header('Location: forgot_password.php?status=not_found');
        exit;
    }
}

@file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] Found user lookup result for {$email}: user_id=".($user['user_id']??'')."\n", FILE_APPEND | LOCK_EX);

// create password_resets table if not exists
$createSql = "CREATE TABLE IF NOT EXISTS password_resets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Prevent resending if there's an active (unused & not expired) OTP for this email
$chk = $conn->prepare('SELECT id, expires_at FROM password_resets WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
$chk->bind_param('s', $email);
$chk->execute();
$resChk = $chk->get_result();
if ($resChk && $resChk->num_rows > 0) {
    // Active OTP exists — do not resend; redirect to OTP page so user sees code input
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=otp_active');
    exit;
}

// NOTE: 60-second resend limit removed — server will permit immediate resend.

// generate 6-digit OTP and expiry (10 minutes)
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiryMinutes = 3; // OTP expiry in minutes (was 10)
$expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

$ins = $conn->prepare('INSERT INTO password_resets (user_id, email, otp, expires_at) VALUES (?, ?, ?, ?)');
$ins->bind_param('isss', $user['user_id'], $email, $otp, $expiresAt);
$ins->execute();

@file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] Inserted OTP for {$email}, otp={$otp}, expires_at={$expiresAt}\n", FILE_APPEND | LOCK_EX);

// send OTP via PHPMailer using config values from config/email_config.php
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = EMAIL_SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = EMAIL_SMTP_USER;
    $mail->Password = EMAIL_SMTP_PASS;
    $mail->SMTPSecure = EMAIL_SMTP_SECURE ?: 'tls'; // 'tls' or 'ssl'
    $mail->Port = EMAIL_SMTP_PORT;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
    $mail->addAddress($email, $user['first_name'] ?? '');

    $mail->isHTML(true);
    $mail->Subject = 'Your OJT-MS password reset code';
    $body = "<p>Hi " . htmlspecialchars($user['first_name'] ?? '') . ",</p>" .
        "<p>Your password reset code (OTP) is: <strong>" . htmlspecialchars($otp) . "</strong></p>" .
        "<p>This code expires in {$expiryMinutes} minutes.</p>" .
        "<p>If you didn't request this, you can safely ignore this message.</p>";
    $mail->Body = $body;

    $mail->send();
    // redirect to OTP verify page
    @file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] PHPMailer send() succeeded for {$email}\n", FILE_APPEND | LOCK_EX);
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=sent');
    exit;
} catch (Exception $e) {
    // attempt PHP mail() fallback (best-effort), like hr_head_accounts.php
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . (defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : '') . " <" . (defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : '') . ">\r\n";
    $fallbackSent = mail($email, 'Your OJT-MS password reset code', $body, $headers);
    @file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] PHP mail() fallback returned: " . ($fallbackSent ? '1' : '0') . " for {$email}\n", FILE_APPEND | LOCK_EX);
    if ($fallbackSent) {
        @file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] Fallback mail() succeeded for {$email}\n", FILE_APPEND | LOCK_EX);
        header('Location: otp_verify.php?email=' . urlencode($email) . '&status=sent');
        exit;
    }

    // ensure logs directory exists and record full error
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/mailer_error.log';
    $errMsg = $mail->ErrorInfo ?? $e->getMessage();
    $msg = '[' . date('Y-m-d H:i:s') . '] Mail error: ' . $errMsg . "\n";
    @file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
    @file_put_contents(__DIR__ . '/logs/send_debug.log', '['.date('Y-m-d H:i:s')."] PHPMailer exception: {$errMsg}\n", FILE_APPEND | LOCK_EX);

    // redirect with a specific mail error status
    header('Location: forgot_password.php?status=mail_error');
    exit;
}
?>