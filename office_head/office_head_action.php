<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/../conn.php';

// read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'request_limit') {
    $office_id = isset($input['office_id']) ? (int)$input['office_id'] : 0;
    $new_limit  = isset($input['requested_limit']) ? (int)$input['requested_limit'] : null;
    $reason     = trim($input['reason'] ?? '');

    if ($office_id <= 0 || $new_limit === null || $new_limit < 0 || $reason === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    // Prevent duplicate pending requests
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM office_requests WHERE office_id = ? AND status = 'Pending'");
    $chk->bind_param("i", $office_id);
    $chk->execute();
    $cnt = (int)$chk->get_result()->fetch_assoc()['cnt'];
    $chk->close();

    if ($cnt > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have a pending request. Please wait for HR to process it.']);
        exit;
    }

    // fetch current limit (old_limit)
    $old_limit = 0;
    $stmt = $conn->prepare("SELECT current_limit FROM offices WHERE office_id = ? LIMIT 1");
    $stmt->bind_param("i", $office_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $old_limit = (int)$row['current_limit'];

    // begin transaction
    $conn->begin_transaction();
    try {
        // insert history row (new request)
        $ins = $conn->prepare("INSERT INTO office_requests (office_id, old_limit, new_limit, reason, status, date_requested) VALUES (?, ?, ?, ?, 'Pending', NOW())");
        $ins->bind_param("iiis", $office_id, $old_limit, $new_limit, $reason);
        $ins->execute();
        $request_id = $ins->insert_id;
        $ins->close();

        // update offices to reflect latest requested values (optional)
        $upd = $conn->prepare("UPDATE offices SET requested_limit = ?, reason = ?, status = 'Pending' WHERE office_id = ?");
        $upd->bind_param("isi", $new_limit, $reason, $office_id);
        $upd->execute();
        $upd->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Request submitted',
            'data' => [
                'request_id' => $request_id,
                'office_id' => $office_id,
                'old_limit' => $old_limit,
                'requested_limit' => $new_limit,
                'reason' => $reason,
                'status' => 'Pending',
                'date_requested' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('request_limit error: '.$e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

// other actions...
echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;
?>