<?php
// sync_push.php
// Push unsynced rows from local `dtr` to remote Hostinger DB.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// local DB
$local = @new mysqli('127.0.0.1', 'root', '', 'u389936701_capstone');
if (!$local || $local->connect_errno) {
    echo "Local DB connect failed\n"; exit(1);
}
$local->set_charset('utf8mb4');

// remote Hostinger DB (same credentials as pc_per_office.php)
$remote = @new mysqli('auth-db2090.hstgr.io', 'u389936701_user', 'CapstoneDefended1', 'u389936701_capstone', 3306);
if (!$remote || $remote->connect_errno) {
    // Remote unavailable (e.g. quota reached) — silently skip pushing for now.
    error_log('sync_push: remote DB connect failed: ' . ($remote ? $remote->connect_error : 'unknown'));
    // Close local connection and exit without reporting error to callers.
    $local->close();
    // Exit 0 to indicate no actionable push occurred (records remain local).
    exit(0);
}
$remote->set_charset('utf8mb4');

// detect if remote `dtr` has `office_id` column
$remoteHasOffice = false;
try {
    $c = $remote->query("SHOW COLUMNS FROM `dtr` LIKE 'office_id'");
    if ($c && $c->num_rows) $remoteHasOffice = true;
} catch (Exception $e) { /* ignore */ }

// ensure local dtr has lightweight sync columns (best-effort)
try {
    $c = $local->query("SHOW COLUMNS FROM `dtr` LIKE 'synced'");
    if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE dtr ADD COLUMN synced TINYINT(1) DEFAULT 0");
    $c = $local->query("SHOW COLUMNS FROM `dtr` LIKE 'buffered_at'");
    if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE dtr ADD COLUMN buffered_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    $c = $local->query("SHOW COLUMNS FROM `dtr` LIKE 'attempts'");
    if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE dtr ADD COLUMN attempts INT DEFAULT 0");
    $c = $local->query("SHOW COLUMNS FROM `dtr` LIKE 'last_error'");
    if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE dtr ADD COLUMN last_error TEXT");
} catch (Exception $e) {
    // ignore
}

// fetch unsynced local dtr rows (written by kiosk) — process oldest buffered first
$res = $local->query("SELECT * FROM dtr WHERE COALESCE(synced,0) = 0 ORDER BY COALESCE(buffered_at, log_date) LIMIT 100");
if (!$res) { echo "No buffer rows or query failed\n"; exit(0); }

$pushed = 0; $failed = 0;
while ($row = $res->fetch_assoc()) {
    $localRowId = !empty($row['dtr_id']) ? (int)$row['dtr_id'] : null;
    $student_id = (int)$row['student_id'];
    $log_date = $row['log_date'];
    $am_in = isset($row['am_in']) && $row['am_in'] !== '' ? $row['am_in'] : null;
    $am_out = isset($row['am_out']) && $row['am_out'] !== '' ? $row['am_out'] : null;
    $pm_in = isset($row['pm_in']) && $row['pm_in'] !== '' ? $row['pm_in'] : null;
    $pm_out = isset($row['pm_out']) && $row['pm_out'] !== '' ? $row['pm_out'] : null;
    $office_id = isset($row['office_id']) ? $row['office_id'] : null;

    try {
        $remote->begin_transaction();
        // check if remote row exists
        $cols = 'dtr_id, am_in,am_out,pm_in,pm_out' . ($remoteHasOffice ? ', office_id' : '');
        $chkSql = "SELECT {$cols} FROM dtr WHERE student_id = ? AND log_date = ? LIMIT 1";
        $chk = $remote->prepare($chkSql);
        if (!$chk) throw new Exception('Remote prepare failed (select): ' . $remote->error);
        $chk->bind_param('is', $student_id, $log_date);
        $chk->execute();
        $resChk = $chk->get_result();
        $rrow = $resChk ? $resChk->fetch_assoc() : null;
        $chk->close();

        if (!$rrow) {
            // insert with whatever fields available
            if ($remoteHasOffice) {
                $ins = $remote->prepare('INSERT INTO dtr (student_id, log_date, am_in, am_out, pm_in, pm_out, office_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if (!$ins) throw new Exception('Remote prepare failed (insert): ' . $remote->error);
                $ins->bind_param('isssssi', $student_id, $log_date, $am_in, $am_out, $pm_in, $pm_out, $office_id);
            } else {
                $ins = $remote->prepare('INSERT INTO dtr (student_id, log_date, am_in, am_out, pm_in, pm_out) VALUES (?, ?, ?, ?, ?, ?)');
                if (!$ins) throw new Exception('Remote prepare failed (insert): ' . $remote->error);
                $ins->bind_param('isssss', $student_id, $log_date, $am_in, $am_out, $pm_in, $pm_out);
            }
            $ok = $ins->execute();
            $ins->close();
            if (!$ok) throw new Exception('Insert failed: ' . $remote->error);
        } else {
            $dtr_id = (int)$rrow['dtr_id'];
            // build update only for fields that are present in buffer and missing in remote
            $sets = [];
            $params = [];
            if ($am_in && empty($rrow['am_in'])) { $sets[] = 'am_in = ?'; $params[] = $am_in; }
            if ($am_out && empty($rrow['am_out'])) { $sets[] = 'am_out = ?'; $params[] = $am_out; }
            if ($pm_in && empty($rrow['pm_in'])) { $sets[] = 'pm_in = ?'; $params[] = $pm_in; }
            if ($pm_out && empty($rrow['pm_out'])) { $sets[] = 'pm_out = ?'; $params[] = $pm_out; }
            if ($remoteHasOffice && !empty($office_id) && empty($rrow['office_id'] ?? null)) { $sets[] = 'office_id = ?'; $params[] = $office_id; }

            if (count($sets) > 0) {
                $sql = 'UPDATE dtr SET ' . implode(', ', $sets) . ' WHERE dtr_id = ?';
                $stmt = $remote->prepare($sql);
                if (!$stmt) throw new Exception('Remote prepare failed (update): ' . $remote->error);
                $types = str_repeat('s', count($params)) . 'i';
                $bind = [];
                $bind[] = & $types;
                foreach ($params as $k => $v) $bind[] = & $params[$k];
                $bind[] = & $dtr_id;
                call_user_func_array([$stmt, 'bind_param'], $bind);
                $ok = $stmt->execute();
                $stmt->close();
                if (!$ok) throw new Exception('Update failed: ' . $remote->error);
            }
        }
        $remote->commit();

        // mark local dtr row as synced
        if (!empty($row['dtr_id'])) {
            $localId = (int)$row['dtr_id'];
            $u = $local->prepare('UPDATE dtr SET synced = 1, attempts = attempts + 1 WHERE dtr_id = ?');
            $u->bind_param('i', $localId); $u->execute(); $u->close();
        }
        $pushed++;
    } catch (Exception $e) {
        $remote->rollback();
        $failed++;
        $err = $e->getMessage();
        // record last_error on local dtr row (if possible)
        if (!empty($row['dtr_id'])) {
            $localId = (int)$row['dtr_id'];
            $u = $local->prepare('UPDATE dtr SET last_error = ?, attempts = attempts + 1 WHERE dtr_id = ?');
            $u->bind_param('si', $err, $localId); $u->execute(); $u->close();
        }
    }
}

echo "Push complete: {$pushed} pushed, {$failed} failed\n";

$local->close();
$remote->close();

?>
