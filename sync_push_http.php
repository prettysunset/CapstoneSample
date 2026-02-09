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
// detect remote hours/minutes
$remoteHasHours = false; $remoteHasMinutes = false;
try { $c = $remote->query("SHOW COLUMNS FROM `dtr` LIKE 'hours'"); if ($c && $c->num_rows) $remoteHasHours = true; $c2 = $remote->query("SHOW COLUMNS FROM `dtr` LIKE 'minutes'"); if ($c2 && $c2->num_rows) $remoteHasMinutes = true; } catch (Exception $e) {}

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
    $hours = isset($row['hours']) ? (int)$row['hours'] : null;
    $minutes = isset($row['minutes']) ? (int)$row['minutes'] : null;
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
            // include hours/minutes when available remotely
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
            if (!$ins) throw new Exception('remote_prepare_insert_failed: ' . $remote->error);
            $bind = [];
            $bind[] = & $types;
            foreach ($params as $k => $v) $bind[] = & $params[$k];
            call_user_func_array([$ins, 'bind_param'], $bind);
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
            if ($remoteHasHours && $hours !== null && (empty($rrow['hours']) || $rrow['hours'] === null)) { $sets[] = 'hours = ?'; $params[] = $hours; }
            if ($remoteHasMinutes && $minutes !== null && (empty($rrow['minutes']) || $rrow['minutes'] === null)) { $sets[] = 'minutes = ?'; $params[] = $minutes; }
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

// --- local -> remote users/students push (status only) -----------------
// Ensure synced columns exist on users/students (best-effort)
try {
    $c = $local->query("SHOW COLUMNS FROM `users` LIKE 'synced'"); if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE users ADD COLUMN synced TINYINT(1) DEFAULT 1");
    $c = $local->query("SHOW COLUMNS FROM `students` LIKE 'synced'"); if (!$c || $c->num_rows === 0) $local->query("ALTER TABLE students ADD COLUMN synced TINYINT(1) DEFAULT 1");
} catch (Exception $e) { /* ignore */ }

$usersPushed = 0; $usersFailed = 0;
$ru = $local->query("SELECT user_id, status FROM users WHERE COALESCE(synced,0) = 0 LIMIT 500");
if ($ru && $ru->num_rows) {
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
            $usersPushed++;
        } catch (Exception $e) {
            $remote->rollback(); $usersFailed++; error_log('sync_push_http: user push failed user_id=' . $uid . ' err=' . $e->getMessage());
        }
    }
}

$studentsPushed = 0; $studentsFailed = 0;
$rs = $local->query("SELECT student_id, status FROM students WHERE COALESCE(synced,0) = 0 LIMIT 500");
if ($rs && $rs->num_rows) {
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
            $studentsPushed++;
        } catch (Exception $e) {
            $remote->rollback(); $studentsFailed++; error_log('sync_push_http: student push failed student_id=' . $sid . ' err=' . $e->getMessage());
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
$studentsPulled = 0; $studentsCreated = 0; $studentsUpdated = 0; $studentsErrors = [];
// --- remote -> local students sync (pull full table, including total_hours_required) ---
try {
    $c = $local->query("SHOW TABLES LIKE 'students'");
    if ($c && $c->num_rows > 0) {
        // Ensure local column exists (best-effort)
        try { $local->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS total_hours_required INT DEFAULT 0"); } catch (Exception $e) {}

        // disable FK checks to avoid insert/update failures when related rows missing locally
        $local->query('SET SESSION FOREIGN_KEY_CHECKS=0');

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
                        if ($u) {
                            $u->bind_param('isssii', $uid, $fn, $ln, $status, $thr, $sid);
                            $ok = $u->execute();
                            if ($ok) $studentsUpdated++;
                            else { $err = $u->error; $msg = 'student update failed student_id=' . var_export($sid, true) . ' err=' . $err; error_log('sync_push_http: ' . $msg); $studentsErrors[] = $msg; }
                            $u->close();
                        }
                    } catch (Exception $ex) { /* ignore per-row errors */ }
                } else {
                    // insert minimal row (use provided student_id if available)
                    try {
                        if ($sid !== null) {
                            $ins = $local->prepare('INSERT INTO students (student_id, user_id, first_name, last_name, status, total_hours_required) VALUES (?, ?, ?, ?, ?, ?)');
                            if ($ins) {
                                $ins->bind_param('iisssi', $sid, $uid, $fn, $ln, $status, $thr);
                                $ok = $ins->execute();
                                if ($ok) $studentsCreated++;
                                else { $err = $ins->error; $msg = 'student insert failed student_id=' . var_export($sid, true) . ' err=' . $err; error_log('sync_push_http: ' . $msg); $studentsErrors[] = $msg; }
                                $ins->close();
                            }
                        } else {
                            $ins = $local->prepare('INSERT INTO students (user_id, first_name, last_name, status, total_hours_required) VALUES (?, ?, ?, ?, ?)');
                            if ($ins) {
                                $ins->bind_param('isssi', $uid, $fn, $ln, $status, $thr);
                                $ok = $ins->execute();
                                if ($ok) $studentsCreated++;
                                else { $err = $ins->error; $msg = 'student insert failed (no id) user_id=' . var_export($uid, true) . ' err=' . $err; error_log('sync_push_http: ' . $msg); $studentsErrors[] = $msg; }
                                $ins->close();
                            }
                        }
                    } catch (Exception $ex) { /* ignore insert errors */ }
                }
            }
            $rs->close();
        }
        // re-enable FK checks
        $local->query('SET SESSION FOREIGN_KEY_CHECKS=1');
    }
} catch (Exception $e) {
    // ignore students pull errors
}

// --- remote -> local ojt_applications sync (pull new rows only) ---------
$appsPulled = 0; $appsCreated = 0; $appsErrors = [];
try {
    $c = $local->query("SHOW TABLES LIKE 'ojt_applications'");
    if ($c && $c->num_rows > 0) {
        $ars = $remote->query("SELECT application_id, student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, remarks, date_submitted, date_updated FROM ojt_applications");
        if ($ars) {
            while ($ar = $ars->fetch_assoc()) {
                // disable FK checks to avoid insert failures when related rows (e.g. offices)
                // are not yet present locally; we'll re-enable after attempting inserts
                $local->query('SET SESSION FOREIGN_KEY_CHECKS=0');
                $appsPulled++;
                $aid = isset($ar['application_id']) ? (int)$ar['application_id'] : null;
                $sid = isset($ar['student_id']) ? (int)$ar['student_id'] : null;

                $exists = null;
                if ($aid !== null) {
                    $chk = $local->prepare('SELECT application_id FROM ojt_applications WHERE application_id = ? LIMIT 1');
                    if ($chk) { $chk->bind_param('i', $aid); $chk->execute(); $exists = $chk->get_result()->fetch_assoc(); $chk->close(); }
                }

                if (!$exists) {
                    try {
                        // insert minimal/full row using remote application_id if available
                        if ($aid !== null) {
                            $ins = $local->prepare('INSERT INTO ojt_applications (application_id, student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, remarks, date_submitted, date_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                            if ($ins) {
                                $ap_id = $aid;
                                $op1 = isset($ar['office_preference1']) ? $ar['office_preference1'] : null;
                                $op2 = isset($ar['office_preference2']) ? $ar['office_preference2'] : null;
                                $loi = $ar['letter_of_intent'] ?? '';
                                $el = $ar['endorsement_letter'] ?? '';
                                $resu = $ar['resume'] ?? '';
                                $moa = $ar['moa_file'] ?? '';
                                $pic = $ar['picture'] ?? '';
                                $st = $ar['status'] ?? '';
                                $rem = $ar['remarks'] ?? '';
                                $ds = $ar['date_submitted'] ?? null;
                                $du = $ar['date_updated'] ?? null;
                                $ins->bind_param('iiissssssssss', $ap_id, $sid, $op1, $op2, $loi, $el, $resu, $moa, $pic, $st, $rem, $ds, $du);
                                $ok = $ins->execute();
                                if ($ok) {
                                    $appsCreated++;
                                } else {
                                    $err = $ins->error;
                                    $msg = 'ojt insert failed application_id=' . var_export($aid, true) . ' err=' . $err;
                                    error_log('sync_push_http: ' . $msg);
                                    $appsErrors[] = $msg;
                                }
                                $ins->close();
                            }
                        } else {
                            // no remote id provided — insert without application_id
                            $ins = $local->prepare('INSERT INTO ojt_applications (student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, remarks, date_submitted, date_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                            if ($ins) {
                                $op1 = isset($ar['office_preference1']) ? $ar['office_preference1'] : null;
                                $op2 = isset($ar['office_preference2']) ? $ar['office_preference2'] : null;
                                $loi = $ar['letter_of_intent'] ?? '';
                                $el = $ar['endorsement_letter'] ?? '';
                                $resu = $ar['resume'] ?? '';
                                $moa = $ar['moa_file'] ?? '';
                                $pic = $ar['picture'] ?? '';
                                $st = $ar['status'] ?? '';
                                $rem = $ar['remarks'] ?? '';
                                $ds = $ar['date_submitted'] ?? null;
                                $du = $ar['date_updated'] ?? null;
                                $ins->bind_param('iiisssssssss', $sid, $op1, $op2, $loi, $el, $resu, $moa, $pic, $st, $rem, $ds, $du);
                                $ok = $ins->execute();
                                if ($ok) {
                                    $appsCreated++;
                                } else {
                                    $err = $ins->error;
                                    $msg = 'ojt insert failed (no id) student_id=' . var_export($sid, true) . ' err=' . $err;
                                    error_log('sync_push_http: ' . $msg);
                                    $appsErrors[] = $msg;
                                }
                                $ins->close();
                            }
                        }
                    } catch (Exception $ex) {
                        $msg = 'ojt insert exception application_id=' . var_export($aid, true) . ' err=' . $ex->getMessage();
                        error_log('sync_push_http: ' . $msg);
                        $appsErrors[] = $msg;
                        // ignore insert errors for individual rows
                    }
                }
            }
            $ars->close();
            // re-enable FK checks
            $local->query('SET SESSION FOREIGN_KEY_CHECKS=1');
        }
    }
} catch (Exception $e) {
    // ignore ojt_applications pull errors
}

echo json_encode(['ok'=>true,'pushed'=>$pushed,'failed'=>$failed,'users_pulled'=>$usersPulled,'users_created'=>$usersCreated,'users_updated'=>$usersUpdated,'students_pulled'=>$studentsPulled,'students_created'=>$studentsCreated,'students_updated'=>$studentsUpdated,'students_errors'=>$studentsErrors,'apps_pulled'=>$appsPulled,'apps_created'=>$appsCreated,'apps_errors'=>$appsErrors]);
$local->close(); $remote->close();
exit;

?>