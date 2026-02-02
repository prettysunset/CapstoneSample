<?php
session_start();
require_once __DIR__ . '/conn.php';
header('Content-Type: application/json; charset=utf-8');

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
