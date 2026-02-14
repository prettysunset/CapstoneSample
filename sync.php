<?php
// sync.php
// Simple endpoint: accepts JSON payload and attempts to insert into remote `sync_log`.
// On remote failure it will store the row in local `sync_queue` (if available) or a log file.
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'invalid json']);
    exit;
}

// Remote DB credentials (match other scripts in repo)
$remoteHost = 'auth-db2090.hstgr.io';
$remoteUser = 'u389936701_user';
$remotePass = 'CapstoneDefended1';
$remoteDb   = 'u389936701_capstone';
$remotePort = 3306;

$payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $remoteHost, $remotePort, $remoteDb);
    $pdo = new PDO($dsn, $remoteUser, $remotePass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Prefer client-provided local timestamp when present so created_at reflects kiosk local time
    $created_at = null;
    if (!empty($data['client_local_ts'])) {
        try { $dt = new DateTime($data['client_local_ts']); $created_at = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $created_at = null; }
    } elseif (!empty($data['client_local_date']) && !empty($data['client_local_time'])) {
        try { $dt = new DateTime($data['client_local_date'] . ' ' . $data['client_local_time']); $created_at = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $created_at = null; }
    } elseif (!empty($data['ts'])) {
        try { $dt = new DateTime($data['ts']); $created_at = $dt->format('Y-m-d H:i:s'); } catch (Exception $e) { $created_at = null; }
    }

    if ($created_at) {
        $stmt = $pdo->prepare('INSERT INTO sync_log (payload, attempt, status, created_at) VALUES (:payload, 1, "sent", :created_at)');
        $stmt->execute([':payload' => $payloadJson, ':created_at' => $created_at]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO sync_log (payload, attempt, status, created_at) VALUES (:payload, 1, "sent", NOW())');
        $stmt->execute([':payload' => $payloadJson]);
    }

    echo json_encode(['ok'=>true]);
    exit;
} catch (PDOException $e) {
    $code = null;
    if (isset($e->errorInfo[1])) $code = (int)$e->errorInfo[1];
    else $code = (int)$e->getCode();

    $blocked_codes = [1040, 1226]; // common MySQL too many connections / resource limits
    $status = in_array($code, $blocked_codes) ? 'blocked' : 'error';
    $blocked_until = null;
    if ($status === 'blocked') {
        // conservative: suggest retry after next full hour
        $blocked_until = date('Y-m-d H:00:00', strtotime('+1 hour'));
    }

    // Attempt to persist locally to sync_queue if available
    try {
        require_once __DIR__ . '/conn.php'; // provides $conn (mysqli) for local XAMPP DB
        if (isset($conn) && $conn && !$conn->connect_errno) {
            // ensure table exists? best-effort insert — the user can create this table via provided SQL
            $ins = $conn->prepare('INSERT INTO sync_queue (payload, created_at) VALUES (?, NOW())');
            if ($ins) {
                $ins->bind_param('s', $payloadJson);
                $ins->execute();
                $ins->close();
            } else {
                // fallback to file log when prepare fails
                file_put_contents(__DIR__ . '/sync_errors.log', date('c') . " | local_queue_prepare_failed | code:$code | msg:" . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents(__DIR__ . '/sync_errors.log', date('c') . " | no_local_conn | code:$code | msg:" . $e->getMessage() . "\n", FILE_APPEND);
        }
    } catch (Exception $ex) {
        file_put_contents(__DIR__ . '/sync_errors.log', date('c') . " | local_queue_exception | code:$code | msg:" . $ex->getMessage() . "\n", FILE_APPEND);
    }

    http_response_code(500);
    echo json_encode(['ok'=>false,'status'=>$status,'code'=>$code,'msg'=>$e->getMessage(),'blocked_until'=>$blocked_until]);
    exit;
}

?>
