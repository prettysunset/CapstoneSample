<?php
// sync_push_http.php
// Lightweight HTTP endpoint to push unsynced local `dtr` rows to remote Hostinger DB.
// Designed to be called from the kiosk page periodically (every 60s).
header('Content-Type: application/json; charset=utf-8');
// minimal output — kiosk will ignore details

// local DB
$local = @new mysqli('127.0.0.1', 'root', '', 'u389936701_capstone');
if (!$local || $local->connect_errno) {
    echo json_encode(['ok' => false, 'reason' => 'local_connect_failed']);
    exit;
}
$local->set_charset('utf8mb4');

// remote Hostinger DB
$remote = @new mysqli('auth-db2090.hstgr.io', 'u389936701_user', 'CapstoneDefended1', 'u389936701_capstone', 3306);
if (!$remote || $remote->connect_errno) {
    // silent skip
    error_log('sync_push_http: remote connect failed: ' . ($remote ? $remote->connect_error : 'unknown'));
    echo json_encode(['ok' => true, 'pushed' => 0, 'skipped_remote_unavailable' => true]);
    $local->close();
    exit;
}
$remote->set_charset('utf8mb4');

// detect remote office_id
$remoteHasOffice = false;
try { $c = $remote->query("SHOW COLUMNS FROM `dtr` LIKE 'office_id'"); if ($c && $c->num_rows) $remoteHasOffice = true; } catch (Exception $e) {}

// ensure local columns exist (best-effort)
try {
    $c = $local->query("SHOW COLUMNS FROM `dtr` LIKE 'synced'"); if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE dtr ADD COLUMN synced TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

$res = $local->query("SELECT * FROM dtr WHERE COALESCE(synced,0)=0 ORDER BY COALESCE(buffered_at, log_date) LIMIT 100");
if (!$res) { echo json_encode(['ok'=>true,'pushed'=>0,'reason'=>'no_rows_or_query_failed']); $local->close(); $remote->close(); exit; }

$pushed = 0; $failed = 0;
while ($row = $res->fetch_assoc()) {
    $student_id = (int)$row['student_id'];
    $log_date = $row['log_date'];
    $am_in = isset($row['am_in']) && $row['am_in'] !== '' ? $row['am_in'] : null;
    $am_out = isset($row['am_out']) && $row['am_out'] !== '' ? $row['am_out'] : null;
    $pm_in = isset($row['pm_in']) && $row['pm_in'] !== '' ? $row['pm_in'] : null;
    $pm_out = isset($row['pm_out']) && $row['pm_out'] !== '' ? $row['pm_out'] : null;
    $office_id = isset($row['office_id']) ? $row['office_id'] : null;
    try {
        $remote->begin_transaction();
        $cols = 'dtr_id, am_in,am_out,pm_in,pm_out' . ($remoteHasOffice ? ', office_id' : '');
        $chkSql = "SELECT {$cols} FROM dtr WHERE student_id = ? AND log_date = ? LIMIT 1";
        $chk = $remote->prepare($chkSql);
        if (!$chk) throw new Exception('remote_prepare_select_failed: ' . $remote->error);
        $chk->bind_param('is', $student_id, $log_date);
        $chk->execute();
        $resChk = $chk->get_result();
        $rrow = $resChk ? $resChk->fetch_assoc() : null;
        $chk->close();

        if (!$rrow) {
            if ($remoteHasOffice) {
                $ins = $remote->prepare('INSERT INTO dtr (student_id, log_date, am_in, am_out, pm_in, pm_out, office_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->bind_param('isssssi', $student_id, $log_date, $am_in, $am_out, $pm_in, $pm_out, $office_id);
            } else {
                $ins = $remote->prepare('INSERT INTO dtr (student_id, log_date, am_in, am_out, pm_in, pm_out) VALUES (?, ?, ?, ?, ?, ?)');
                $ins->bind_param('isssss', $student_id, $log_date, $am_in, $am_out, $pm_in, $pm_out);
            }
            if (!$ins) throw new Exception('remote_prepare_insert_failed: ' . $remote->error);
            $ok = $ins->execute(); $ins->close();
            if (!$ok) throw new Exception('remote_insert_failed: ' . $remote->error);
        } else {
            $dtr_id = (int)$rrow['dtr_id'];
            $sets = []; $params = [];
            if ($am_in && empty($rrow['am_in'])) { $sets[] = 'am_in = ?'; $params[] = $am_in; }
            if ($am_out && empty($rrow['am_out'])) { $sets[] = 'am_out = ?'; $params[] = $am_out; }
            if ($pm_in && empty($rrow['pm_in'])) { $sets[] = 'pm_in = ?'; $params[] = $pm_in; }
            if ($pm_out && empty($rrow['pm_out'])) { $sets[] = 'pm_out = ?'; $params[] = $pm_out; }
            if ($remoteHasOffice && !empty($office_id) && empty($rrow['office_id'] ?? null)) { $sets[] = 'office_id = ?'; $params[] = $office_id; }
            if (count($sets) > 0) {
                $sql = 'UPDATE dtr SET ' . implode(', ', $sets) . ' WHERE dtr_id = ?';
                $stmt = $remote->prepare($sql);
                if (!$stmt) throw new Exception('remote_prepare_update_failed: ' . $remote->error);
                $types = str_repeat('s', count($params)) . 'i';
                $bind = [];
                $bind[] = & $types;
                foreach ($params as $k => $v) $bind[] = & $params[$k];
                $bind[] = & $dtr_id;
                call_user_func_array([$stmt, 'bind_param'], $bind);
                $ok = $stmt->execute(); $stmt->close();
                if (!$ok) throw new Exception('remote_update_failed: ' . $remote->error);
            }
        }
        $remote->commit();
        // mark local as synced
        if (!empty($row['dtr_id'])) {
            $localId = (int)$row['dtr_id'];
            $u = $local->prepare('UPDATE dtr SET synced = 1, attempts = attempts + 1 WHERE dtr_id = ?');
            if ($u) { $u->bind_param('i', $localId); $u->execute(); $u->close(); }
        }
        $pushed++;
    } catch (Exception $e) {
        $remote->rollback(); $failed++;
        if (!empty($row['dtr_id'])) {
            $localId = (int)$row['dtr_id'];
            $u = $local->prepare('UPDATE dtr SET last_error = ?, attempts = attempts + 1 WHERE dtr_id = ?');
            if ($u) { $err = $e->getMessage(); $u->bind_param('si', $err, $localId); $u->execute(); $u->close(); }
        }
    }
}

// --- remote -> local users sync (pull) -------------------------------
// Purpose: copy new users and endorsement_printed flag from remote to local
// Best-effort: add endorsement_printed column locally if missing, insert new users,
// and update endorsement_printed when remote has it set.
$usersPulled = 0; $usersCreated = 0; $usersUpdated = 0;
try {
    // ensure local users table exists before trying
    $c = $local->query("SHOW TABLES LIKE 'users'");
    if ($c && $c->num_rows > 0) {
        // ensure endorsement_printed column exists locally (best-effort)
        try { $local->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS endorsement_printed TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}

        $ru = $remote->query("SELECT user_id, username, password, first_name, last_name, role, status, date_created, COALESCE(endorsement_printed,0) AS endorsement_printed FROM users");
        if ($ru) {
            while ($ruRow = $ru->fetch_assoc()) {
                $usersPulled++;
                $ruid = isset($ruRow['user_id']) ? (int)$ruRow['user_id'] : null;
                // check local existence
                $st = $local->prepare('SELECT endorsement_printed FROM users WHERE user_id = ? LIMIT 1');
                if ($st) {
                    $st->bind_param('i', $ruid);
                    $st->execute();
                    $lr = $st->get_result()->fetch_assoc();
                    $st->close();
                } else {
                    $lr = null;
                }

                if ($lr) {
                    // exists locally: update endorsement_printed if remote has it and local doesn't
                    $remoteFlag = (int)($ruRow['endorsement_printed'] ?? 0);
                    $localFlag = (int)($lr['endorsement_printed'] ?? 0);
                    if ($remoteFlag && !$localFlag) {
                        $u = $local->prepare('UPDATE users SET endorsement_printed = 1 WHERE user_id = ? LIMIT 1');
                        if ($u) { $u->bind_param('i', $ruid); $u->execute(); $u->close(); $usersUpdated++; }
                    }
                } else {
                    // not found locally: insert minimal user row (best-effort)
                    try {
                        $ins = $local->prepare('INSERT INTO users (user_id, username, password, first_name, last_name, role, status, date_created, endorsement_printed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        if ($ins) {
                            $uid = $ruid;
                            $uname = $ruRow['username'] ?? '';
                            $pwd = $ruRow['password'] ?? '';
                            $fn = $ruRow['first_name'] ?? '';
                            $ln = $ruRow['last_name'] ?? '';
                            $role = $ruRow['role'] ?? '';
                            $status = $ruRow['status'] ?? '';
                            $dc = $ruRow['date_created'] ?? null;
                            $ep = (int)($ruRow['endorsement_printed'] ?? 0);
                            $ins->bind_param('isssssssi', $uid, $uname, $pwd, $fn, $ln, $role, $status, $dc, $ep);
                            $ok = $ins->execute();
                            $ins->close();
                            if ($ok) $usersCreated++;
                        }
                    } catch (Exception $ex) {
                        // ignore insert errors, continue
                    }
                }
            }
            $ru->close();
        }
    }
} catch (Exception $e) {
    // ignore pull errors
}
$studentsPulled = 0; $studentsCreated = 0; $studentsUpdated = 0;
// --- remote -> local students sync (pull full table, including total_hours_required) ---
try {
    $c = $local->query("SHOW TABLES LIKE 'students'");
    if ($c && $c->num_rows > 0) {
        // Ensure local column exists (best-effort)
        try { $local->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS total_hours_required INT DEFAULT 0"); } catch (Exception $e) {}

        $rs = $remote->query("SELECT student_id, user_id, first_name, last_name, status, COALESCE(total_hours_required,0) AS total_hours_required FROM students");
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                $studentsPulled++;
                $sid = isset($r['student_id']) ? (int)$r['student_id'] : null;
                // check local existence by student_id
                $st = null;
                if ($sid !== null) {
                    $chk = $local->prepare('SELECT student_id FROM students WHERE student_id = ? LIMIT 1');
                    if ($chk) {
                        $chk->bind_param('i', $sid);
                        $chk->execute();
                        $st = $chk->get_result()->fetch_assoc();
                        $chk->close();
                    }
                }

                $fn = $r['first_name'] ?? '';
                $ln = $r['last_name'] ?? '';
                $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
                $status = $r['status'] ?? '';
                $thr = isset($r['total_hours_required']) ? (int)$r['total_hours_required'] : 0;

                if ($st) {
                    // update local row
                    try {
                        $u = $local->prepare('UPDATE students SET user_id = ?, first_name = ?, last_name = ?, status = ?, total_hours_required = ? WHERE student_id = ?');
                        if ($u) { $u->bind_param('isssii', $uid, $fn, $ln, $status, $thr, $sid); $u->execute(); $u->close(); $studentsUpdated++; }
                    } catch (Exception $ex) { /* ignore per-row errors */ }
                } else {
                    // insert minimal row (use provided student_id if available)
                    try {
                        if ($sid !== null) {
                            $ins = $local->prepare('INSERT INTO students (student_id, user_id, first_name, last_name, status, total_hours_required) VALUES (?, ?, ?, ?, ?, ?)');
                            if ($ins) { $ins->bind_param('iisssi', $sid, $uid, $fn, $ln, $status, $thr); $ok = $ins->execute(); $ins->close(); if ($ok) $studentsCreated++; }
                        } else {
                            $ins = $local->prepare('INSERT INTO students (user_id, first_name, last_name, status, total_hours_required) VALUES (?, ?, ?, ?, ?)');
                            if ($ins) { $ins->bind_param('isssi', $uid, $fn, $ln, $status, $thr); $ok = $ins->execute(); $ins->close(); if ($ok) $studentsCreated++; }
                        }
                    } catch (Exception $ex) { /* ignore insert errors */ }
                }
            }
            $rs->close();
        }
    }
} catch (Exception $e) {
    // ignore students pull errors
}

echo json_encode(['ok'=>true,'pushed'=>$pushed,'failed'=>$failed,'users_pulled'=>$usersPulled,'users_created'=>$usersCreated,'users_updated'=>$usersUpdated,'students_pulled'=>$studentsPulled,'students_created'=>$studentsCreated,'students_updated'=>$studentsUpdated]);
$local->close(); $remote->close();
exit;

?>
