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

echo json_encode(['success'=>false,'message'=>'Unknown action']);
?>