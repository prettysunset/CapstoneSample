<?php
// sync_users_pull.php
// Run every 60 minutes to pull `users` from remote Hostinger -> local DB
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load local DB connection (provides $conn)
require_once __DIR__ . '/conn.php';

// Remote Hostinger credentials (match pc_per_office.php)
$remoteHost = 'auth-db2090.hstgr.io';
$remoteUser = 'u389936701_user';
$remotePass = 'CapstoneDefended1';
$remoteDb   = 'u389936701_capstone';
$remotePort = 3306;

$logFile = __DIR__ . '/sync_users_pull.log';
function logmsg($m) {
    global $logFile;
    file_put_contents($logFile, date('c') . ' ' . $m . PHP_EOL, FILE_APPEND);
}

logmsg('Starting users pull sync');

// Connect to remote
$remote = @new mysqli($remoteHost, $remoteUser, $remotePass, $remoteDb, $remotePort);
if (!$remote || $remote->connect_errno) {
    logmsg('Remote connect failed: ' . ($remote ? $remote->connect_error : 'unknown'));
    exit(1);
}
$remote->set_charset('utf8mb4');

// fetch remote users
$res = $remote->query('SELECT * FROM users');
if (!$res) {
    logmsg('Remote query failed: ' . $remote->error);
    exit(1);
}

// find local users columns (only sync columns that exist locally)
$colsRes = $conn->query('SHOW COLUMNS FROM users');
$localCols = [];
while ($c = $colsRes->fetch_assoc()) $localCols[] = $c['Field'];
if (count($localCols) === 0) {
    logmsg('Local users table not found or has no columns');
    exit(1);
}

$cols = $localCols; // preserve order
$colList = implode(',', array_map(function($c){ return "`$c`"; }, $cols));
$placeholders = implode(',', array_fill(0, count($cols), '?'));
$updateCols = array_filter($cols, function($c){ return $c !== 'user_id'; });
if (count($updateCols) === 0) {
    logmsg('No updatable columns found');
    exit(1);
}
$updateList = implode(',', array_map(function($c){ return "`$c`=VALUES(`$c`)"; }, $updateCols));

$sql = "INSERT INTO users ({$colList}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updateList}";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logmsg('Prepare failed: ' . $conn->error);
    exit(1);
}

$count = 0; $errors = 0;
$conn->begin_transaction();
while ($row = $res->fetch_assoc()) {
    // build values in same column order
    $vals = [];
    foreach ($cols as $c) {
        $vals[] = array_key_exists($c, $row) ? $row[$c] : null;
    }

    // bind params dynamically (all as string)
    $types = str_repeat('s', count($vals));
    $bindParams = [];
    $bindParams[] = & $types;
    for ($i = 0; $i < count($vals); $i++) {
        $bindParams[] = & $vals[$i];
    }
    // call bind_param by reference
    if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
        $errors++;
        logmsg('bind_param failed for row user_id=' . ($row['user_id'] ?? '(null)'));
        continue;
    }

    if (!$stmt->execute()) {
        $errors++;
        logmsg('Execute failed for user_id=' . ($row['user_id'] ?? '(null)') . ' err=' . $stmt->error);
        continue;
    }
    $count++;
}
$conn->commit();

logmsg("Sync complete: {$count} rows upserted, {$errors} errors");
echo "Sync complete: {$count} rows upserted, {$errors} errors\n";

$stmt->close();
$remote->close();

?>
