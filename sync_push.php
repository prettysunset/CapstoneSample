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
// detect if remote `dtr` has `hours` and `minutes` columns
$remoteHasHours = false; $remoteHasMinutes = false;
try {
    $c = $remote->query("SHOW COLUMNS FROM `dtr` LIKE 'hours'"); if ($c && $c->num_rows) $remoteHasHours = true;
    $c2 = $remote->query("SHOW COLUMNS FROM `dtr` LIKE 'minutes'"); if ($c2 && $c2->num_rows) $remoteHasMinutes = true;
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
    $hours = isset($row['hours']) ? (int)$row['hours'] : null;
    $minutes = isset($row['minutes']) ? (int)$row['minutes'] : null;

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
            // insert with whatever fields available (include hours/minutes when remote supports them)
            if ($remoteHasOffice) {
                $cols = 'student_id, log_date, am_in, am_out, pm_in, pm_out, office_id';
                $placeholders = '?, ?, ?, ?, ?, ?, ?';
                $types = 'isssssi';
                $params = [$student_id, $log_date, $am_in, $am_out, $pm_in, $pm_out, $office_id];
            } else {
                $cols = 'student_id, log_date, am_in, am_out, pm_in, pm_out';
                $placeholders = '?, ?, ?, ?, ?, ?';
                $types = 'isssss';
                $params = [$student_id, $log_date, $am_in, $am_out, $pm_in, $pm_out];
            }
            if ($remoteHasHours) { $cols .= ', hours'; $placeholders .= ', ?'; $types .= 'i'; $params[] = $hours ?? 0; }
            if ($remoteHasMinutes) { $cols .= ', minutes'; $placeholders .= ', ?'; $types .= 'i'; $params[] = $minutes ?? 0; }
            $sql = 'INSERT INTO dtr (' . $cols . ') VALUES (' . $placeholders . ')';
            $ins = $remote->prepare($sql);
            if (!$ins) throw new Exception('Remote prepare failed (insert): ' . $remote->error);
            // bind params dynamically
            $bind = [];
            $bind[] = & $types;
            foreach ($params as $k => $v) $bind[] = & $params[$k];
            call_user_func_array([$ins, 'bind_param'], $bind);
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
            if ($remoteHasHours && $hours !== null && (empty($rrow['hours']) || $rrow['hours'] === null)) { $sets[] = 'hours = ?'; $params[] = $hours; }
            if ($remoteHasMinutes && $minutes !== null && (empty($rrow['minutes']) || $rrow['minutes'] === null)) { $sets[] = 'minutes = ?'; $params[] = $minutes; }

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

// --- Push unsynced users and students using the same connections (run together) ---
try {
    // ensure synced columns exist on users/students (best-effort)
    try {
        $c = $local->query("SHOW COLUMNS FROM `users` LIKE 'synced'"); if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE users ADD COLUMN synced TINYINT(1) DEFAULT 1");
        $c = $local->query("SHOW COLUMNS FROM `students` LIKE 'synced'"); if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE students ADD COLUMN synced TINYINT(1) DEFAULT 1");
    } catch (Exception $e) { /* ignore */ }

    // push users (status only)
    $pushedUsers = 0; $failedUsers = 0;
    $ru = $local->query("SELECT user_id, status FROM users WHERE COALESCE(synced,0) = 0 LIMIT 500");
    if ($ru && $ru->num_rows) {
        error_log('sync_push: found unsynced users: ' . $ru->num_rows);
        while ($rowU = $ru->fetch_assoc()) {
            $uid = (int)$rowU['user_id'];
            try {
                $remote->begin_transaction();
                $chk = $remote->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1');
                $chk->bind_param('i', $uid); $chk->execute(); $rchk = $chk->get_result()->fetch_assoc(); $chk->close();
                if ($rchk) {
                    $stmt = $remote->prepare('UPDATE users SET status = ? WHERE user_id = ?');
                    $stmt->bind_param('si', $rowU['status'], $uid); $stmt->execute(); $stmt->close();
                } else {
                    $stmt = $remote->prepare('INSERT INTO users (user_id, status) VALUES (?, ?)');
                    $stmt->bind_param('is', $uid, $rowU['status']); $stmt->execute(); $stmt->close();
                }
                $remote->commit();
                $u = $local->prepare('UPDATE users SET synced = 1 WHERE user_id = ?'); $u->bind_param('i', $uid); $u->execute(); $u->close();
                $pushedUsers++;
            } catch (Exception $e) {
                $remote->rollback(); $failedUsers++; error_log('sync_push: user push failed user_id=' . $uid . ' err=' . $e->getMessage());
            }
        }
    }
    error_log('sync_push: users pushed=' . $pushedUsers . ' failed=' . $failedUsers);

    // push students (status only)
    $pushedStudents = 0; $failedStudents = 0;
    $rs = $local->query("SELECT student_id, status FROM students WHERE COALESCE(synced,0) = 0 LIMIT 500");
    if ($rs && $rs->num_rows) {
        error_log('sync_push: found unsynced students: ' . $rs->num_rows);
        while ($rowS = $rs->fetch_assoc()) {
            $sid = (int)$rowS['student_id'];
            try {
                $remote->begin_transaction();
                $chk = $remote->prepare('SELECT student_id FROM students WHERE student_id = ? LIMIT 1');
                $chk->bind_param('i', $sid); $chk->execute(); $rchk = $chk->get_result()->fetch_assoc(); $chk->close();
                if ($rchk) {
                    $stmt = $remote->prepare('UPDATE students SET status = ? WHERE student_id = ?');
                    $stmt->bind_param('si', $rowS['status'], $sid); $stmt->execute(); $stmt->close();
                } else {
                    $stmt = $remote->prepare('INSERT INTO students (student_id, status) VALUES (?, ?)');
                    $stmt->bind_param('is', $sid, $rowS['status']); $stmt->execute(); $stmt->close();
                }
                $remote->commit();
                $u = $local->prepare('UPDATE students SET synced = 1 WHERE student_id = ?'); $u->bind_param('i', $sid); $u->execute(); $u->close();
                $pushedStudents++;
            } catch (Exception $e) {
                $remote->rollback(); $failedStudents++; error_log('sync_push: student push failed student_id=' . $sid . ' err=' . $e->getMessage());
            }
        }
    }
    error_log('sync_push: students pushed=' . $pushedStudents . ' failed=' . $failedStudents);
} catch (Exception $e) { error_log('sync_push: users/students push error: ' . $e->getMessage()); }

$local->close();
$remote->close();

?>