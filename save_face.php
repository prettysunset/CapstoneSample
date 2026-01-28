<?php
session_start();
// Connect directly to Hostinger DB for face registration (do not include conn.php)
header('Content-Type: application/json; charset=utf-8');
$h_host = 'auth-db2090.hstgr.io';
$h_user = 'u389936701_user';
$h_pass = 'CapstoneDefended1';
$h_db   = 'u389936701_capstone';
$h_port = 3306;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = @new mysqli($h_host, $h_user, $h_pass, $h_db, $h_port);
    if ($conn && !$conn->connect_errno) {
        $conn->set_charset('utf8mb4');
        error_log('save_face: connected to Hostinger DB ' . $h_host);
    } else {
        error_log('save_face: Hostinger DB connect failed: ' . ($conn ? $conn->connect_error : 'unknown'));
        echo json_encode(['success'=>false,'message'=>'Cannot connect to Hostinger DB']);
        exit;
    }
} catch (Exception $ex) {
    error_log('save_face: exception connecting Hostinger DB: ' . $ex->getMessage());
    echo json_encode(['success'=>false,'message'=>'DB connection exception']);
    exit;
}

// Expect POST: username, password, image (dataURL)
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$image = $_POST['image'] ?? '';
$descriptor = $_POST['descriptor'] ?? '';

if ($username === '' || $password === '' ) {
    echo json_encode(['success'=>false,'message'=>'Missing parameters']); exit;
}

// find user
$s = $conn->prepare("SELECT user_id, password FROM users WHERE username = ? LIMIT 1");
$s->bind_param('s', $username);
$s->execute();
$user = $s->get_result()->fetch_assoc();
$s->close();
if (!$user) { echo json_encode(['success'=>false,'message'=>'User not found']); exit; }

// verify password: try password_verify then fallback
$ok = false;
$stored = (string)($user['password'] ?? '');
if ($stored !== '' && password_verify($password, $stored)) $ok = true;
if (!$ok && $stored === $password) $ok = true; // fallback for plaintext (development)
if (!$ok) { echo json_encode(['success'=>false,'message'=>'Invalid credentials']); exit; }

// optionally decode and save image if provided
$rel = null;
if ($image && preg_match('#^data:image/(jpeg|png);base64,#i', $image, $m)) {
    $matches = $m;
    $data = substr($image, strpos($image, ',') + 1);
    $bin = base64_decode($data);
    if ($bin !== false) {
        $ext = strtolower($matches[1]) === 'png' ? 'png' : 'jpg';
        $dir = __DIR__ . '/uploads/faces';
        if (!is_dir($dir)) {@mkdir($dir, 0755, true);} 
        $filename = sprintf('%s_%s.%s', $user['user_id'], time(), $ext);
        $path = $dir . '/' . $filename;
        if (file_put_contents($path, $bin) !== false) {
            $rel = 'uploads/faces/' . $filename;
        }
    }
}

// store record in DB: face_templates (descriptor optional)
try {
    $ins = $conn->prepare("INSERT INTO face_templates (user_id, file_path, descriptor) VALUES (?, ?, ?)");
    $descJson = $descriptor ? $descriptor : null;
    $ins->bind_param('iss', $user['user_id'], $rel, $descJson);
    $ins->execute();
    $ins->close();
    echo json_encode(['success'=>true,'message'=>'Saved','file'=>$rel]);
} catch (Exception $e) {
    // cleanup file on DB error
    if (!empty($path)) @unlink($path);
    echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
