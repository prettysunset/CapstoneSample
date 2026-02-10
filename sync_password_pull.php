<?php
// sync_password_pull.php
// Lightweight script to pull `users.password` from remote -> local
// Intended to be run frequently (e.g. cron every minute or external scheduler every 60s).
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/conn.php'; // provides $conn (local)

$remoteHost = 'auth-db2090.hstgr.io';
$remoteUser = 'u389936701_user';
$remotePass = 'CapstoneDefended1';
$remoteDb   = 'u389936701_capstone';
$remotePort = 3306;

$logFile = __DIR__ . '/sync_password_pull.log';
function logmsg($m) {
    global $logFile;
    file_put_contents($logFile, date('c') . ' ' . $m . PHP_EOL, FILE_APPEND);
}

logmsg('Starting password pull');

$remote = @new mysqli($remoteHost, $remoteUser, $remotePass, $remoteDb, $remotePort);
if (!$remote || $remote->connect_errno) {
    logmsg('Remote connect failed: ' . ($remote ? $remote->connect_error : 'unknown'));
    exit(1);
}
$remote->set_charset('utf8mb4');

// fetch only user_id and password
$res = $remote->query('SELECT user_id, password FROM users');
if (!$res) {
    logmsg('Remote query failed: ' . $remote->error);
    exit(1);
}

$updates = 0; $skipped = 0; $errors = 0;

$sel = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
$upd = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');

while ($row = $res->fetch_assoc()) {
    $uid = isset($row['user_id']) ? (int)$row['user_id'] : 0;
    if ($uid <= 0) continue;
    $remotePassHash = isset($row['password']) ? $row['password'] : null;

    // fetch local
    $localPass = null;
    if ($sel) {
        $sel->bind_param('i', $uid);
        if ($sel->execute()) {
            $r = $sel->get_result();
            $lr = $r ? $r->fetch_assoc() : null;
            $localPass = $lr ? $lr['password'] : null;
        }
    }

    // update only when different and remote has a non-empty password
    if ($remotePassHash !== null && $remotePassHash !== '' && $remotePassHash !== $localPass) {
        if ($upd) {
            $upd->bind_param('si', $remotePassHash, $uid);
            if ($upd->execute()) {
                $updates++;
            } else {
                $errors++;
                logmsg('Update failed for user_id=' . $uid . ' err=' . $upd->error);
            }
        }
    } else {
        $skipped++;
    }
}

logmsg("Password sync complete: updated={$updates}, skipped={$skipped}, errors={$errors}");
echo "Password sync complete: updated={$updates}, skipped={$skipped}, errors={$errors}\n";

if ($sel) $sel->close();
if ($upd) $upd->close();
$remote->close();

?>
