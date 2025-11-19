<?php
session_start();
require_once __DIR__ . '/conn.php';

// Helper to send JSON
function json_resp($arr){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// detect if dtr table has office_id column (optional)
$hasOfficeCol = false;
try {
    $c = $conn->query("SHOW COLUMNS FROM `dtr` LIKE 'office_id'");
    if ($c && $c->num_rows) $hasOfficeCol = true;
} catch(Exception $e){ /* ignore */ }

// Debug endpoint: visit pc_per_office.php?debug_db=1 to verify DB connection and sample users
if (isset($_GET['debug_db'])) {
    try {
        // quick ping
        $ok = $conn->query("SELECT 1") !== false;
        // pull a few users (password preview only for debugging)
        $sample = [];
        $res = $conn->query("SELECT user_id, username, password, role FROM users LIMIT 5");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['password_preview'] = substr($r['password'] ?? '', 0, 12);
                unset($r['password']);
                $sample[] = $r;
            }
        }
        json_resp([
            'ok' => (bool)$ok,
            'mysql_client' => mysqli_get_client_info(),
            'mysql_server' => $conn->server_info ?? '',
            'users_sample' => $sample
        ]);
    } catch (Exception $ex) {
        json_resp(['ok'=>false,'error'=>$ex->getMessage()]);
    }
}

// Handle AJAX POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $action = $_POST['action'];
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $office_id = isset($_POST['office_id']) ? (int)$_POST['office_id'] : 0;
    $client_ts = trim($_POST['client_ts'] ?? ''); // ISO timestamp from client, if provided
    // prefer explicit client-local date/time (YYYY-MM-DD, HH:MM:SS)
    $client_local_date = trim($_POST['client_local_date'] ?? '');
    $client_local_time = trim($_POST['client_local_time'] ?? '');

    if ($username === '' || $password === '') {
        json_resp(['success'=>false,'message'=>'Enter username and password']);
    }

    // 1) Find user in users table
    $u = $conn->prepare("SELECT user_id, password, role, status FROM users WHERE username = ? LIMIT 1");
    $u->bind_param('s', $username);
    $u->execute();
    $user = $u->get_result()->fetch_assoc();
    $u->close();

    // DEBUG: log username lookup result (remove in production)
    error_log("pc_per_office: lookup username='{$username}' => " . ($user ? "FOUND user_id={$user['user_id']} role={$user['role']} stored_preview=" . substr($user['password'],0,12) : "NOT FOUND"));
    if (!$user) json_resp(['success'=>false,'message'=>'Invalid username or password','debug'=>'user_not_found','username'=>$username]);

    // --- enforce account age: disallow time-in/out if account is less than 1 week old ---
    try {
        $dc = $conn->prepare("SELECT DATE_FORMAT(date_created, '%Y-%m-%d') AS date_created FROM users WHERE user_id = ? LIMIT 1");
        $dc->bind_param('i', $user['user_id']);
        $dc->execute();
        $dcRow = $dc->get_result()->fetch_assoc();
        $dc->close();
    } catch (Exception $e) {
        $dcRow = null;
    }
    if ($dcRow && !empty($dcRow['date_created'])) {
        $createdDate = $dcRow['date_created']; // yyyy-mm-dd
        // determine effective date: prefer client_local_date, then client_ts, then server date
        $effectiveDate = '';
        if (!empty($client_local_date)) {
            $effectiveDate = $client_local_date;
        } elseif (!empty($client_ts)) {
            try { $tmp = new DateTime($client_ts); $effectiveDate = $tmp->format('Y-m-d'); } catch (Exception $e) { /* ignore */ }
        }
        if (!$effectiveDate) {
            $row = $conn->query("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d') AS today")->fetch_assoc();
            $effectiveDate = $row['today'] ?? date('Y-m-d');
        }
        // per requirement allow logging on createdDate + 8 days (example: 2025-11-17 -> allowed 2025-11-25)
        $allowFrom = (new DateTime($createdDate))->modify('+8 days')->format('Y-m-d');
        if ($effectiveDate < $allowFrom) {
            json_resp([
                'success' => false,
                'message' => "You will be able to time in after your orientation"
            ]);
        }
    }

    // 2) Verify password - SKIPPED per request (development only)
    // Allow action when username exists and role will be checked below.
    // Keep stored value available if you want to log later.
    $stored = (string)($user['password'] ?? '');
    // no password verification performed here — proceed

    // role must be ojt
    if (($user['role'] ?? '') !== 'ojt') json_resp(['success'=>false,'message'=>'User is not an OJT']);

    // block time-in if user's status is 'completed'
    if ($action === 'time_in' && (($user['status'] ?? '') === 'completed')) {
        json_resp(['success' => false, 'message' => 'Cannot time in: user status is completed']);
    }

    // 3) Map user_id -> students.student_id and determine DTR owner (users.user_id)
    $s = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
    $s->bind_param('i', $user['user_id']);
    $s->execute();
    $st = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$st) json_resp(['success'=>false,'message'=>'No student record found for this user']);
    $student_id = (int)$st['student_id'];

    // dtr.student_id column references users.user_id in this schema — use user_id as the dtr owner
    $dtr_owner = (int)$user['user_id'];

    // Prefer explicit client-local date/time (sent by browser). Fallback to ISO client_ts, then DB server time.
    $today = null; $now = null;
    if ($client_local_date && $client_local_time) {
        // client sent local date and time strings (use these directly)
        $today = $client_local_date;
        $now = $client_local_time;
    } elseif ($client_ts) {
        try {
            // client_ts is an ISO string (UTC). Convert to server timezone only if needed.
            $cdt = new DateTime($client_ts);
            $today = $cdt->format('Y-m-d');
            $now = $cdt->format('H:i:s'); // store with seconds, 24-hour
        } catch (Exception $e) { /* ignore, fallback to DB */ }
    }
    if (!$today || !$now) {
        $dtRow = $conn->query("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d') AS today, DATE_FORMAT(NOW(), '%H:%i:%s') AS now_time")->fetch_assoc();
        $today = $dtRow['today'] ?? date('Y-m-d');
        $now = $dtRow['now_time'] ?? date('H:i:s');
    }

    try {
        $conn->begin_transaction();

        // lock existing dtr row (if any) for this user/date
        $q = $conn->prepare("SELECT dtr_id, am_in, am_out, pm_in, pm_out FROM dtr WHERE student_id = ? AND log_date = ? LIMIT 1 FOR UPDATE");
        $q->bind_param('is', $dtr_owner, $today);
        $q->execute();
        $dtr = $q->get_result()->fetch_assoc();
        $q->close();

        // check if this is the first time-in ever for this user (no prior DTR records)
        $isFirstTimeIn = false;
        $checkFirst = $conn->prepare("SELECT COUNT(*) AS cnt FROM dtr WHERE student_id = ?");
        $checkFirst->bind_param('i', $dtr_owner);
        $checkFirst->execute();
        $firstRes = $checkFirst->get_result()->fetch_assoc();
        $checkFirst->close();
        if ($firstRes && (int)$firstRes['cnt'] === 0) {
            $isFirstTimeIn = true;
        }

        // ---------- Validation rules ----------
        // parse click timestamp (flexible: "H:i:s" or "H:i")
        try {
            $clickDt = new DateTime($today . ' ' . $now);
        } catch (Exception $e) {
            json_resp(['success'=>false,'message'=>'Invalid timestamp format']);
        }
        // (future-timestamp check removed per request)

        // 6) No weekends -- validation temporarily disabled
        // $dow = (int)$clickDt->format('N'); // 1..7
        // if ($dow >= 6) {
        //     $conn->rollback();
        //     json_resp(['success'=>false,'message'=>'Logging on weekends is not allowed']);
        // }

        // helper to parse time-only strings (returns DateTime or null)
        $parseTime = function($timeStr) use ($today) {
            if (!$timeStr) return null;
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $timeStr) ?: DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $timeStr);
            return $dt ?: null;
        };

        // time range constants
        $AM_START = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 06:00:00');
        $AM_END   = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 12:30:00');
        $PM_START = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 12:30:00');
        $PM_END   = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 17:30:00');
        $LATE_PM_EARLIEST = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 12:00:00');
        $LATE_PM_LATEST   = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 16:00:00'); // allowed late punch window

        // limit logs per day: only one AM and one PM in/out -- enforce via existing columns
        // (duplicate prevention checks are applied per-action below)
        // ---------- end validation setup ----------

        // action-specific pre-checks
        if ($action === 'time_in') {
            // determine target field (existing logic)
            if ($dtr) {
                if (empty($dtr['am_in'])) {
                    $field = 'am_in';
                } elseif (empty($dtr['pm_in'])) {
                    $field = 'pm_in';
                } else {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'Already timed in for today']);
                }
            } else {
                // new row -> decide by hour
                $dtTmp = DateTime::createFromFormat('H:i:s', $now) ?: DateTime::createFromFormat('H:i', $now);
                $hour = $dtTmp ? (int)$dtTmp->format('H') : (int)date('H');
                $field = ($hour < 12) ? 'am_in' : 'pm_in';
            }

            // validate chosen field ranges & duplicates
            if ($field === 'am_in') {
                // duplicate check
                if ($dtr && !empty($dtr['am_in'])) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'AM already timed in']);
                }
                // allowed AM window
                if ($clickDt < $AM_START || $clickDt > $AM_END) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'AM Time In allowed between 06:00 and 12:30']);
                }
            } else { // pm_in
                if ($dtr && !empty($dtr['pm_in'])) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'PM already timed in']);
                }
                // if AM session exists, require AM to be completed (no overlap) and enforce standard PM window
                $amExists = $dtr && (!empty($dtr['am_in']) || !empty($dtr['am_out']));
                $amOutDt = $parseTime($dtr['am_out'] ?? null);
                if ($amExists && empty($dtr['am_out']) && !empty($dtr['am_in'])) {
                    // AM started but not finished -> disallow PM in
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'Complete AM session before PM Time In']);
                }
                // normal PM window
                if ($amExists) {
                    if ($clickDt < $PM_START || $clickDt > $PM_END) {
                        $conn->rollback();
                        json_resp(['success'=>false,'message'=>'PM Time In allowed between 12:30 and 17:30']);
                    }
                    // cross-session: if am_out exists, ensure am_out < pm_in
                    if ($amOutDt && $clickDt <= $amOutDt) {
                        $conn->rollback();
                        json_resp(['success'=>false,'message'=>'PM Time In must be after AM Time Out']);
                    }
                } else {
                    // late arrival rule (no AM session): allow 12:00 - 16:00
                    if ($clickDt < $LATE_PM_EARLIEST || $clickDt > $LATE_PM_LATEST) {
                        $conn->rollback();
                        json_resp(['success'=>false,'message'=>'Late PM Time In allowed between 12:00 and 16:00']);
                    }
                }
            }
        } elseif ($action === 'time_out') {
            // must have an existing dtr row with an unmatched IN
            if (!$dtr) {
                $conn->rollback();
                json_resp(['success'=>false,'message'=>'No time-in found for today']);
            }
            // determine which out field will be set
            $field = null;
            if (!empty($dtr['pm_in']) && empty($dtr['pm_out'])) $field = 'pm_out';
            elseif (!empty($dtr['am_in']) && empty($dtr['am_out'])) $field = 'am_out';
            if (!$field) {
                $conn->rollback();
                json_resp(['success'=>false,'message'=>'Nothing to time out or already timed out']);
            }

            // validate sequence & ranges
            if ($field === 'am_out') {
                $inDt = $parseTime($dtr['am_in']);
                if (!$inDt) { $conn->rollback(); json_resp(['success'=>false,'message'=>'Missing AM time-in']); }
                if ($clickDt <= $inDt) { $conn->rollback(); json_resp(['success'=>false,'message'=>'AM Time Out must be after AM Time In']); }
                if ($clickDt < $AM_START || $clickDt > $AM_END) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'AM Time Out must be between 06:00 and 12:30']);
                }
                // enforce minimum session length: at least 30 minutes between IN and OUT
                $diffSec = $clickDt->getTimestamp() - $inDt->getTimestamp();
                if ($diffSec < 30 * 60) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'Minimum AM session is 30 minutes before Time Out']);
                }
                // if PM already has in, ensure AM out < PM in
                if (!empty($dtr['pm_in'])) {
                    $pmInDt = $parseTime($dtr['pm_in']);
                    if ($pmInDt && $clickDt >= $pmInDt) {
                        $conn->rollback();
                        json_resp(['success'=>false,'message'=>'AM Time Out must be before PM Time In']);
                    }
                }
            } else { // pm_out
                $inDt = $parseTime($dtr['pm_in']);
                if (!$inDt) { $conn->rollback(); json_resp(['success'=>false,'message'=>'Missing PM time-in']); }
                if ($clickDt <= $inDt) { $conn->rollback(); json_resp(['success'=>false,'message'=>'PM Time Out must be after PM Time In']); }
                if ($clickDt < $PM_START || $clickDt > $PM_END) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'PM Time Out must be between 12:30 and 17:30']);
                }
                // enforce minimum session length: at least 30 minutes between IN and OUT
                $diffSec = $clickDt->getTimestamp() - $inDt->getTimestamp();
                if ($diffSec < 30 * 60) {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'Minimum PM session is 30 minutes before Time Out']);
                }
            }
        }
        // ---------- end validation checks ----------

        if ($action === 'time_in') {
            if ($dtr) {
                if (empty($dtr['am_in'])) {
                    $field = 'am_in';
                } elseif (empty($dtr['pm_in'])) {
                    $field = 'pm_in';
                } else {
                    $conn->rollback();
                    json_resp(['success'=>false,'message'=>'Already timed in for today']);
                }
                $upd = $conn->prepare("UPDATE dtr SET {$field} = ? WHERE dtr_id = ?");
                $upd->bind_param('si', $now, $dtr['dtr_id']);
                $upd->execute();
                $upd->close();
            } else {
                if ($hasOfficeCol && $office_id) {
                    $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date, am_in, office_id) VALUES (?, ?, ?, ?)");
                    $ins->bind_param('issi', $dtr_owner, $today, $now, $office_id);
                } else {
                    // determine hour using the $now value (client_ts preferred) so stored time/date
                    // matches the actual click time rather than server clock
                    $hour = null;
                    $dtTmp = DateTime::createFromFormat('H:i:s', $now) ?: DateTime::createFromFormat('H:i', $now);
                    if ($dtTmp) {
                        $hour = (int)$dtTmp->format('H');
                    } else {
                        // fallback to server hour if parsing fails
                        $hour = (int)date('H');
                    }
                    if ($hour < 12) {
                        $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date, am_in) VALUES (?, ?, ?)");
                    } else {
                        $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date, pm_in) VALUES (?, ?, ?)");
                    }
                    $ins->bind_param('iss', $dtr_owner, $today, $now);
                }
                $ins->execute();
                $ins->close();
            }

            // if this is the first time-in ever, update statuses to 'ongoing'
            if ($isFirstTimeIn) {
                $updUser = $conn->prepare("UPDATE users SET status = 'ongoing' WHERE user_id = ?");
                $updUser->bind_param('i', $user['user_id']);
                $updUser->execute();
                $updUser->close();

                $updStudent = $conn->prepare("UPDATE students SET status = 'ongoing' WHERE student_id = ?");
                $updStudent->bind_param('i', $student_id);
                $updStudent->execute();
                $updStudent->close();

                $updApp = $conn->prepare("UPDATE ojt_applications SET status = 'ongoing' WHERE student_id = ?");
                $updApp->bind_param('i', $student_id);
                $updApp->execute();
                $updApp->close();
            }

            $conn->commit();
            json_resp(['success'=>true,'message'=>'Time in recorded','time'=>$now]);
        }

        if ($action === 'time_out') {
            if (!$dtr) {
                $conn->rollback();
                json_resp(['success'=>false,'message'=>'No time-in found for today']);
            }
            $field = null;
            if (!empty($dtr['pm_in']) && empty($dtr['pm_out'])) $field = 'pm_out';
            elseif (!empty($dtr['am_in']) && empty($dtr['am_out'])) $field = 'am_out';
            else {
                $conn->rollback();
                json_resp(['success'=>false,'message'=>'Nothing to time out or already timed out']);
            }
            $upd = $conn->prepare("UPDATE dtr SET {$field} = ? WHERE dtr_id = ?");
            $upd->bind_param('si', $now, $dtr['dtr_id']);
            $upd->execute();
            $upd->close();

            // recompute total hours/minutes
            $sel = $conn->prepare("SELECT am_in,am_out,pm_in,pm_out FROM dtr WHERE dtr_id = ? LIMIT 1");
            $sel->bind_param('i', $dtr['dtr_id']);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();

            $totalMin = 0;
            // parse times using the current $today so timestamps are on the same date
            foreach ([['am_in','am_out'], ['pm_in','pm_out']] as $p) {
                if (!empty($row[$p[0]]) && !empty($row[$p[1]])) {
                    // try formats with seconds then without, always prefix with date
                    $fmt1 = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $row[$p[0]]);
                    if (!$fmt1) $fmt1 = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $row[$p[0]]);
                    $fmt2 = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $row[$p[1]]);
                    if (!$fmt2) $fmt2 = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $row[$p[1]]);
                    if ($fmt1 && $fmt2) {
                        $diff = $fmt2->getTimestamp() - $fmt1->getTimestamp();
                        if ($diff > 0) $totalMin += intval($diff / 60);
                    }
                }
            }
             // 8) Full day cap: do not exceed 480 minutes (8 hours)
             if ($totalMin > 480) $totalMin = 480;
             $hours = intdiv($totalMin, 60);
             $minutes = $totalMin % 60;

            $up2 = $conn->prepare("UPDATE dtr SET hours = ?, minutes = ? WHERE dtr_id = ?");
            $up2->bind_param('iii', $hours, $minutes, $dtr['dtr_id']);
            $up2->execute();
            $up2->close();

            $conn->commit();
            json_resp(['success'=>true,'message'=>'Time out recorded','time'=>$now,'hours'=>$hours,'minutes'=>$minutes]);
        }

        $conn->rollback();
        json_resp(['success'=>false,'message'=>'Unknown action']);
    } catch (Exception $ex) {
        $conn->rollback();
        json_resp(['success'=>false,'message'=>'Server error: '.$ex->getMessage()]);
    }
}

// Render minimal page
$office_id = isset($_GET['office_id']) ? (int)$_GET['office_id'] : 0;
$office_name = '';
if ($office_id) {
    $s = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ? LIMIT 1");
    $s->bind_param('i',$office_id);
    $s->execute();
    $of = $s->get_result()->fetch_assoc();
    $s->close();
    $office_name = $of['office_name'] ?? '';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>PC — Time In / Time Out</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{
    --bg1:#e6f2ff;
    --card-bg: rgba(255,255,255,0.95);
    --accent:#3a4163;
    --btn-in:#3d44a8;
    --btn-out:#355e4a;
  }
  /* make page area full viewport and hide scrollbar on desktop */
  html,body{
    height:100%;
    margin:0;
    font-family:'Poppins',sans-serif;
    background:var(--bg1);
    overflow:hidden; /* hides scrollbar — see note below */
  }

  /* background like login */
  .page-bg{
    min-height:100vh;
    background-image:url('123456.png');
    background-size:cover;
    background-position:center;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px;
    box-sizing:border-box;
    width:100%;
  }

  /* larger card, responsive max-width so it never forces a horizontal scrollbar */
  .card{
    width:640px;           /* increased container width */
    max-width:calc(100% - 48px); /* leave breathing room to avoid overflow */
    background:linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,255,255,0.90));
    border-radius:20px;
    padding:32px;
    box-shadow: 8px 14px 40px rgba(58,65,99,0.12);
    position:relative;
    overflow:visible;
  }

  .logo{font-size:14px;color:var(--accent);text-align:center;font-weight:700;margin-bottom:8px}
  .time-big{font-weight:700;font-size:20px;color:var(--accent);text-align:center}
  .date-sub{color:#6b7280;text-align:center;margin-bottom:16px}
  .sub-desc{color:#5b6477;text-align:center;margin-bottom:18px;font-size:13px}
  .form-row{display:flex;gap:10px}
  .input{
    width:100%;
    background:white;
    border-radius:10px;
    border:1px solid rgba(58,65,99,0.06);
    padding:12px 14px;
    box-sizing:border-box;
    font-size:14px;
    color:#222;
    margin-bottom:10px;
  }
  .password-container{position:relative}
  .password-container button{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:0;cursor:pointer;padding:4px;color:var(--accent)
  }
  .actions{display:flex;gap:12px;justify-content:center;margin-top:6px}
  .btn{
    flex:1;padding:12px;border-radius:12px;border:0;color:white;font-weight:700;cursor:pointer;box-shadow:0 6px 18px rgba(58,65,99,0.08)
  }
  .btn.in{background:var(--btn-in)}
  .btn.out{background:var(--btn-out)}
  .btn:disabled{background:#c7c7c7;cursor:not-allowed;color:#444}
  .msg{display:none;text-align:center;margin-top:12px;padding:10px;border-radius:8px;font-size:14px}
  .office-name{font-size:13px;color:#4b5563;text-align:center;margin-bottom:8px}

  /* hide native scrollbar visuals in WebKit/Firefox (keeps ability to scroll if overflow occurs) */
  ::-webkit-scrollbar { width:0; height:0; }
  html { -ms-overflow-style: none; scrollbar-width: none; }

  /* responsive: allow scrolling on small screens and reduce card size */
  @media (max-width:760px){
    html,body{ overflow:auto; } /* enable scroll on narrow devices */
    .card{ width:94%; padding:20px; border-radius:14px; }
    .time-big{font-size:16px}
  }
</style>
</head>
<body>
  <div class="page-bg">
    <div class="card" role="region" aria-label="PC Time Log">
      <div class="logo">OJT-MS</div>
      <div class="time-big" id="now">--:--:--</div>
      <div class="date-sub" id="date"><?php echo date('F j, Y'); ?></div>
      <?php if ($office_name): ?>
        <div class="office-name"><?php echo htmlspecialchars($office_name); ?></div>
      <?php endif; ?>

      <form id="pcForm" onsubmit="return false;" style="margin-top:6px">
        <input type="hidden" id="office_id" value="<?php echo (int)$office_id; ?>">
        <input id="username" class="input" type="text" placeholder="Username" autocomplete="username">
        <div class="password-container">
          <input id="password" class="input" type="password" placeholder="Password" autocomplete="current-password">
          <button type="button" id="togglePassword" aria-label="Show password">
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3a4163" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3a4163" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
              <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.65 18.65 0 0 1 4.11-5.05"></path>
              <path d="M1 1l22 22"></path>
              <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
            </svg>
          </button>
        </div>

        <div class="actions">
          <button id="btnIn" class="btn in" type="button">Time In</button>
          <button id="btnOut" class="btn out" type="button">Time Out</button>
        </div>

        <div id="msg" class="msg" role="status" aria-live="polite"></div>
      </form>
    </div>
  </div>

<script>
(function(){
  const nowEl = document.getElementById('now');
  const dateEl = document.getElementById('date');
  function tick(){
    const d = new Date();
    nowEl.textContent = d.toLocaleTimeString('en-US',{hour12:true});
    dateEl.textContent = d.toLocaleDateString(undefined,{month:'long',day:'numeric',year:'numeric'});
  }
  tick();
  setInterval(tick,1000);

  const btnIn = document.getElementById('btnIn');
  const btnOut = document.getElementById('btnOut');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const officeId = document.getElementById('office_id').value;
  const msg = document.getElementById('msg');

  function showMsg(text, ok=true){
    msg.style.display = 'block';
    msg.style.background = ok ? '#e6f9ee' : '#fff4f4';
    msg.style.color = ok ? '#0b7a3a' : '#a00';
    msg.textContent = text;
    setTimeout(()=> msg.style.display = 'none', 3000);
  }

  // toggle eye
  (function(){
    var btn = document.getElementById('togglePassword');
    var pwd = document.getElementById('password');
    var openEye = document.getElementById('eyeOpen');
    var closedEye = document.getElementById('eyeClosed');
    btn.addEventListener('click', function(e){
        e.preventDefault();
        if (pwd.type === 'password') {
            pwd.type = 'text';
            openEye.style.display = 'none';
            closedEye.style.display = 'inline';
            btn.setAttribute('aria-label', 'Hide password');
        } else {
            pwd.type = 'password';
            openEye.style.display = 'inline';
            closedEye.style.display = 'none';
            btn.setAttribute('aria-label', 'Show password');
        }
    }, true);
  })();

  async function send(action){
      const u = username.value.trim();
      const p = password.value;
      if (!u || !p) { showMsg('Enter username and password', false); return; }
      btnIn.disabled = true; btnOut.disabled = true;

      // clear fields immediately for the next user (keep u/p in local variables for the request)
      username.value = '';
      password.value = '';
      username.focus();

      try {
        const form = new FormData();
        form.append('action', action);
        form.append('username', u);
        form.append('password', p);
        // send exact client click time:
        // 1) ISO UTC timestamp (still useful)
        form.append('client_ts', new Date().toISOString());
        // 2) explicit client-local date and time so server stores the user's local date/time
        const dNow = new Date();
        const localDate = dNow.getFullYear() + '-' + String(dNow.getMonth()+1).padStart(2,'0') + '-' + String(dNow.getDate()).padStart(2,'0');
        const localTime = String(dNow.getHours()).padStart(2,'0') + ':' + String(dNow.getMinutes()).padStart(2,'0') + ':' + String(dNow.getSeconds()).padStart(2,'0');
        form.append('client_local_date', localDate);
        form.append('client_local_time', localTime);
        if (officeId && Number(officeId) !== 0) form.append('office_id', officeId);
        const res = await fetch(window.location.href, { method:'POST', body: form });
        const j = await res.json();
        console.log('pc_per_office response:', j); // DEBUG: open browser console
        if (j.success) {
          showMsg(j.message || (action==='time_in'?'Time in recorded':'Time out recorded'), true);
        } else {
          const extra = j.debug ? (' — ' + j.debug + (j.stored_preview ? ' ('+j.stored_preview+')' : '')) : '';
          showMsg((j.message || 'Action failed') + extra, false);
        }
      } catch (e) {
        showMsg('Request failed', false);
      } finally {
        // re-enable after short delay to avoid accidental double clicks
        setTimeout(()=>{ btnIn.disabled = false; btnOut.disabled = false; }, 600);
      }
    }

  btnIn.addEventListener('click', ()=>send('time_in'));
  btnOut.addEventListener('click', ()=>{ if (!confirm('Confirm Time Out?')) return; send('time_out'); });
})();
</script>
</body>
</html>