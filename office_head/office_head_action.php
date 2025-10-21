<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']); exit;
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
$action = $in['action'] ?? '';

if ($action === 'request_limit') {
    $office_id = (int)($in['office_id'] ?? 0);
    $requested_limit = isset($in['requested_limit']) ? (int)$in['requested_limit'] : null;
    $reason = trim($in['reason'] ?? '');
    if ($office_id <= 0 || $requested_limit === null) {
        echo json_encode(['success'=>false,'message'=>'Invalid payload']); exit;
    }

    // ensure office exists
    $r = $conn->prepare("SELECT current_limit FROM offices WHERE office_id = ? LIMIT 1");
    $r->bind_param("i", $office_id);
    $r->execute();
    $off = $r->get_result()->fetch_assoc();
    $r->close();
    if (!$off) {
        echo json_encode(['success'=>false,'message'=>'Office not found']); exit;
    }

    $old = (int)($off['current_limit'] ?? 0);

    // update offices: set requested_limit, reason, status = 'pending'
    $u = $conn->prepare("UPDATE offices SET requested_limit = ?, reason = ?, status = 'pending' WHERE office_id = ?");
    $u->bind_param("isi", $requested_limit, $reason, $office_id);
    $ok = $u->execute();
    $u->close();
    if (!$ok) {
        echo json_encode(['success'=>false,'message'=>'DB update failed']); exit;
    }

    // insert a request record
    $ins = $conn->prepare("INSERT INTO office_requests (office_id, old_limit, new_limit, reason, status, date_requested) VALUES (?, ?, ?, ?, 'pending', CURDATE())");
    $ins->bind_param("iiis", $office_id, $old, $requested_limit, $reason);
    $ins->execute();
    $ins->close();

    echo json_encode(['success'=>true,'data'=>['requested_limit'=>$requested_limit,'reason'=>$reason,'status'=>'pending']]);
    exit;
}

// New: return late DTR rows for a given office_id + date
if ($action === 'get_late_dtr') {
    $office_id = (int)($in['office_id'] ?? 0);
    $date = trim($in['date'] ?? '');
    if ($office_id <= 0 || $date === '') {
        echo json_encode(['success'=>false,'message'=>'Invalid payload']); exit;
    }
    // get office_name
    $q = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ? LIMIT 1");
    $q->bind_param("i", $office_id);
    $q->execute();
    $off = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$off) {
        echo json_encode(['success'=>false,'message'=>'Office not found']); exit;
    }
    $like = '%' . $off['office_name'] . '%';
    $s = $conn->prepare("
      SELECT u.first_name, u.last_name, d.am_in, d.am_out, d.pm_in, d.pm_out, d.hours
      FROM dtr d
      JOIN users u ON d.student_id = u.user_id
      WHERE u.office_name LIKE ? AND d.log_date = ?
      ORDER BY u.last_name, u.first_name
    ");
    $s->bind_param("ss", $like, $date);
    $s->execute();
    $res = $s->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        // ensure time strings are HH:MM (remove any seconds)
        $trim = function($t){ return $t === null ? null : substr(trim($t),0,5); };
        $rows[] = [
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'am_in' => $trim($r['am_in']),
            'am_out' => $trim($r['am_out']),
            'pm_in' => $trim($r['pm_in']),
            'pm_out' => $trim($r['pm_out']),
            'hours' => isset($r['hours']) ? (int)$r['hours'] : null,
            // optional status for UI
            'status' => (!empty($r['hours']) ? 'Validated' : '')
        ];
    }
    $s->close();
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
exit;
?>