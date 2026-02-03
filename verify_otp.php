<?php
require_once __DIR__ . '/conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$otp = trim($_POST['otp'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm'] ?? '';

// basic checks
if (!$email || !$otp) {
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=error');
    exit;
}

// password match
if ($password !== $confirm) {
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=pw_mismatch');
    exit;
}

// password strength: at least 8 chars, 1 uppercase, 1 number
$errors = [];
if (strlen($password) < 8) $errors[] = 'length';
if (!preg_match('/[A-Z]/', $password)) $errors[] = 'uppercase';
if (!preg_match('/[0-9]/', $password)) $errors[] = 'number';
if (!empty($errors)) {
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=pw_strength_fail');
    exit;
}

// fetch latest unused OTP for this email
$stmt = $conn->prepare('SELECT id, user_id, otp, expires_at, used FROM password_resets WHERE email = ? AND otp = ? AND used = 0 ORDER BY id DESC LIMIT 1');
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=invalid');
    exit;
}
$row = $res->fetch_assoc();

// check expiry
if (strtotime($row['expires_at']) < time()) {
    header('Location: otp_verify.php?email=' . urlencode($email) . '&status=expired');
    exit;
}

// update user password: store a secure hash instead of the plain password
$hashed = password_hash($password, PASSWORD_DEFAULT);
$upd = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
$upd->bind_param('si', $hashed, $row['user_id']);
$upd->execute();

// mark OTP as used
$mark = $conn->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
$mark->bind_param('i', $row['id']);
$mark->execute();

// redirect to login with success
header('Location: login.php?reset=success');
exit;
?>
