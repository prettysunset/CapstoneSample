<?php
session_start();
// Use local DB connection (conn.php) so uploaded face templates are stored locally
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require_once __DIR__ . '/conn.php';
    if (!isset($conn) || !$conn || $conn->connect_errno) {
        throw new Exception('Local DB connection not available');
    }
    $conn->set_charset('utf8mb4');
    error_log('save_face: using local DB via conn.php');
} catch (Exception $ex) {
    error_log('save_face: local DB connect failed: ' . $ex->getMessage());
    echo json_encode(['success'=>false,'message'=>'Cannot connect to local DB']);
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
    // If a descriptor was provided, check for duplicates first (use strict threshold)
    $descThreshold = 0.40; // stricter than probe threshold used elsewhere
    if ($descriptor) {
        $probe = json_decode($descriptor, true);
        if (is_array($probe) && count($probe) > 0) {
            $q = $conn->query("SELECT descriptor FROM face_templates WHERE descriptor IS NOT NULL");
            $best = ['dist' => INF];
            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    $djson = $r['descriptor'] ?? null;
                    if (!$djson) continue;
                    $darr = json_decode($djson, true);
                    if (!is_array($darr) || count($darr) !== count($probe)) continue;
                    $sum = 0.0;
                    for ($i=0,$n=count($probe); $i<$n; $i++) { $diff = (floatval($darr[$i]) - floatval($probe[$i])); $sum += $diff * $diff; }
                    $dist = sqrt($sum);
                    if ($dist < $best['dist']) { $best['dist'] = $dist; }
                }
                $q->close();
            }
            if ($best['dist'] !== INF && $best['dist'] <= $descThreshold) {
                // duplicate found — do not save
                if (!empty($path)) @unlink($path);
                echo json_encode(['success'=>false,'message'=>'Duplicate face detected (similar template exists)','best_distance'=>$best['dist']]);
                exit;
            }
        }
    }
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
