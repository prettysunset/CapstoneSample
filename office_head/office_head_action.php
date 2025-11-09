<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$action = $data['action'] ?? $_POST['action'] ?? '';

$user_id = (int)$_SESSION['user_id'];

// helper: resolve office_name for current user
$office_name = '';
$stmt = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($r && !empty($r['office_name'])) $office_name = $r['office_name'];

if ($action === 'get_daily_logs') {
    $date = $data['date'] ?? '';
    if (!$date) { echo json_encode(['success'=>false,'message'=>'Missing date']); exit; }

    $sql = "
      SELECT d.log_date, u.first_name, u.last_name,
             COALESCE(s.college,'') AS school, COALESCE(s.course,'') AS course,
             COALESCE(d.am_in,'') AS am_in, COALESCE(d.am_out,'') AS am_out,
             COALESCE(d.pm_in,'') AS pm_in, COALESCE(d.pm_out,'') AS pm_out,
             COALESCE(d.hours,'') AS hours, COALESCE(d.status,'') AS status
      FROM dtr d
      JOIN students s ON d.student_id = s.student_id
      JOIN users u ON s.user_id = u.user_id
      WHERE d.log_date = ? AND u.office_name LIKE ?
      ORDER BY u.last_name, u.first_name
    ";
    $like = '%' . $office_name . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $date, $like);
    if (!$stmt->execute()) {
        echo json_encode(['success'=>false,'message'=>'Query failed']);
        exit;
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit;
}

if ($action === 'create_late') {
    $student_id = (int)($data['student_id'] ?? 0);
    $late_date = $data['late_date'] ?? '';
    $am_in = $data['am_in'] ?? null;
    $am_out = $data['am_out'] ?? null;
    $pm_in = $data['pm_in'] ?? null;
    $pm_out = $data['pm_out'] ?? null;

    if (!$student_id || !$late_date) { echo json_encode(['success'=>false,'message'=>'Missing parameters']); exit; }

    $stmt = $conn->prepare("INSERT INTO late_dtr (student_id, date_filed, late_date, am_in, am_out, pm_in, pm_out, status, filed_by) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param('isssssi', $student_id, $late_date, $am_in, $am_out, $pm_in, $pm_out, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>'Late DTR created']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Insert failed']);
    }
    $stmt->close();
    exit;
}

if ($action === 'request_limit') {
    $office_id = isset($data['office_id']) ? (int)$data['office_id'] : 0;
    $requested_limit = isset($data['requested_limit']) ? (int)$data['requested_limit'] : null;
    $reason = isset($data['reason']) ? trim($data['reason']) : '';

    if ($office_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid office_id']); exit; }
    if ($requested_limit === null || !is_int($requested_limit) || $requested_limit < 0) { echo json_encode(['success'=>false,'message'=>'Invalid requested_limit']); exit; }
    if ($reason === '') { echo json_encode(['success'=>false,'message'=>'Reason is required']); exit; }

    // fetch current limit
    $stmt = $conn->prepare("SELECT current_limit FROM offices WHERE office_id = ? LIMIT 1");
    $stmt->bind_param('i', $office_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Office not found']); exit; }
    $old_limit = (int)($row['current_limit'] ?? 0);

    // insert office_requests record
    $ins = $conn->prepare("INSERT INTO office_requests (office_id, old_limit, new_limit, reason, status, date_requested) VALUES (?, ?, ?, ?, 'pending', CURDATE())");
    $ins->bind_param('iiis', $office_id, $old_limit, $requested_limit, $reason);
    $okIns = $ins->execute();
    $ins->close();

    if (!$okIns) {
        echo json_encode(['success'=>false,'message'=>'Failed to create request']); exit;
    }

    // update offices table requested_limit / reason / status
    $upd = $conn->prepare("UPDATE offices SET requested_limit = ?, reason = ?, status = 'Pending' WHERE office_id = ?");
    $upd->bind_param('isi', $requested_limit, $reason, $office_id);
    $okUpd = $upd->execute();
    $upd->close();

    if (!$okUpd) {
        echo json_encode(['success'=>false,'message'=>'Failed to update office']); exit;
    }

    echo json_encode(['success'=>true,'data'=>[
        'requested_limit' => $requested_limit,
        'reason' => $reason,
        'status' => 'Pending'
    ]]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
?>