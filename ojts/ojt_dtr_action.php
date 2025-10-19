<?php
// Simple JSON endpoint for OJT DTR actions used by ojts/ojt_home.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// get current HH:MM (no seconds)
date_default_timezone_set('Asia/Manila');
$now_hm = date('H:i'); // e.g. "14:49"

require_once __DIR__ . '/../conn.php'; // $conn (mysqli)

// read JSON body
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']); exit;
}
$action = $input['action'] ?? '';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

// resolve student_id
$stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$student_id = $res['student_id'] ?? null;
if (!$student_id) { echo json_encode(['success'=>false,'message'=>'Student record not found for user.']); exit; }

$today = date('Y-m-d');
$nowHHMM = date('H:i'); // only hours:minutes (no seconds)

/* helper: fetch row and return trimmed times (HH:MM) */
function fetch_row($conn, $student_id, $date) {
    $s = $conn->prepare("SELECT dtr_id, log_date, am_in, am_out, pm_in, pm_out, hours, minutes FROM dtr WHERE student_id = ? AND log_date = ?");
    $s->bind_param("is", $student_id, $date);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$r) return null;

    // trim times to HH:MM (if DB stores seconds, remove them for UI)
    $trim = function($t){
        if ($t === null) return null;
        $t = trim($t);
        // Accept formats like "HH:MM:SS" or "HH:MM" or "H:M"
        $parts = explode(':', $t);
        $hh = str_pad((int)($parts[0] ?? 0), 2, '0', STR_PAD_LEFT);
        $mm = str_pad((int)($parts[1] ?? 0), 2, '0', STR_PAD_LEFT);
        return $hh . ':' . $mm;
    };

    $hoursStored = isset($r['hours']) ? (int)$r['hours'] : 0;
    $minutesStored = isset($r['minutes']) ? (int)$r['minutes'] : 0;
    $total = ($hoursStored || $minutesStored) ? ($hoursStored + ($minutesStored / 60)) : null;

    return [
        'dtr_id' => (int)$r['dtr_id'],
        'log_date' => $r['log_date'],
        'am_in' => $trim($r['am_in']),
        'am_out' => $trim($r['am_out']),
        'pm_in' => $trim($r['pm_in']),
        'pm_out' => $trim($r['pm_out']),
        'hours' => $hoursStored,
        'minutes' => $minutesStored,
        'total_hours' => $total
    ];
}

/* helper: compute total minutes from completed pairs (works with HH:MM or HH:MM:SS) */
function compute_minutes_total($date, $am_in, $am_out, $pm_in, $pm_out) {
    $minutes = 0;
    if (!empty($am_in) && !empty($am_out)) {
        $t1 = strtotime($date . ' ' . $am_in);
        $t2 = strtotime($date . ' ' . $am_out);
        if ($t2 >= $t1) $minutes += intval(round(($t2 - $t1) / 60));
    }
    if (!empty($pm_in) && !empty($pm_out)) {
        $t1 = strtotime($date . ' ' . $pm_in);
        $t2 = strtotime($date . ' ' . $pm_out);
        if ($t2 >= $t1) $minutes += intval(round(($t2 - $t1) / 60));
    }
    return $minutes;
}

function month_total($conn, $student_id, $year, $month) {
    $q = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ? AND YEAR(log_date)=? AND MONTH(log_date)=?");
    $q->bind_param("iii", $student_id, $year, $month);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    return (float)($row['total'] ?? 0.0);
}

/* Actions */
if ($action === 'get_today') {
    $row = fetch_row($conn, $student_id, $today);
    $mTotal = month_total($conn, $student_id, (int)date('Y'), (int)date('n'));
    echo json_encode(['success' => true, 'row' => $row, 'month_total' => $mTotal]); exit;
}

if ($action === 'time_in' || $action === 'time_out') {
    $conn->begin_transaction();
    try {
        // ensure row exists
        $s = $conn->prepare("SELECT dtr_id FROM dtr WHERE student_id = ? AND log_date = ?");
        $s->bind_param("is", $student_id, $today);
        $s->execute();
        $exists = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$exists) {
            $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date) VALUES (?, ?)");
            $ins->bind_param("is", $student_id, $today);
            $ins->execute();
            $ins->close();
        }

        // load current fields
        $sel = $conn->prepare("SELECT dtr_id, am_in, am_out, pm_in, pm_out FROM dtr WHERE student_id = ? AND log_date = ?");
        $sel->bind_param("is", $student_id, $today);
        $sel->execute();
        $cur = $sel->get_result()->fetch_assoc();
        $sel->close();

        $dtr_id = (int)$cur['dtr_id'];
        $am_in = $cur['am_in'] ?? null;
        $am_out = $cur['am_out'] ?? null;
        $pm_in = $cur['pm_in'] ?? null;
        $pm_out = $cur['pm_out'] ?? null;

        if ($action === 'time_in') {
            // first IN decides AM/PM by hour of first IN
            if (empty($am_in) && empty($pm_in)) {
                $hour = (int)date('H');
                if ($hour < 12) {
                    $u = $conn->prepare("UPDATE dtr SET am_in = ? WHERE dtr_id = ?");
                } else {
                    $u = $conn->prepare("UPDATE dtr SET pm_in = ? WHERE dtr_id = ?");
                }
                $u->bind_param("si", $nowHHMM, $dtr_id);
                $u->execute(); $u->close();
            } else {
                // if AM session closed and pm_in empty -> set pm_in
                if (!empty($am_in) && !empty($am_out) && empty($pm_in)) {
                    $u = $conn->prepare("UPDATE dtr SET pm_in = ? WHERE dtr_id = ?");
                    $u->bind_param("si", $nowHHMM, $dtr_id);
                    $u->execute(); $u->close();
                }
                // otherwise ignore duplicate IN
            }
        } else { // time_out
            // close last unmatched IN: prefer pm_in->pm_out, else am_in->am_out
            if (!empty($pm_in) && empty($pm_out)) {
                $u = $conn->prepare("UPDATE dtr SET pm_out = ? WHERE dtr_id = ?");
                $u->bind_param("si", $nowHHMM, $dtr_id);
                $u->execute(); $u->close();
                $pm_out = $nowHHMM;
            } elseif (!empty($am_in) && empty($am_out)) {
                $u = $conn->prepare("UPDATE dtr SET am_out = ? WHERE dtr_id = ?");
                $u->bind_param("si", $nowHHMM, $dtr_id);
                $u->execute(); $u->close();
                $am_out = $nowHHMM;
            } else {
                // fallback
                $u = $conn->prepare("UPDATE dtr SET pm_out = ? WHERE dtr_id = ?");
                $u->bind_param("si", $nowHHMM, $dtr_id);
                $u->execute(); $u->close();
                $pm_out = $nowHHMM;
            }

            // recompute minutes total from completed pairs and persist hours/minutes
            $sel2 = $conn->prepare("SELECT am_in, am_out, pm_in, pm_out FROM dtr WHERE dtr_id = ?");
            $sel2->bind_param("i", $dtr_id);
            $sel2->execute();
            $cur2 = $sel2->get_result()->fetch_assoc();
            $sel2->close();

            $minutes_total = compute_minutes_total($today, $cur2['am_in'] ?? null, $cur2['am_out'] ?? null, $cur2['pm_in'] ?? null, $cur2['pm_out'] ?? null);

            $calcHours = intdiv($minutes_total, 60);
            $calcMinutes = $minutes_total % 60;

            $upd = $conn->prepare("UPDATE dtr SET hours = ?, minutes = ? WHERE dtr_id = ?");
            $upd->bind_param("iii", $calcHours, $calcMinutes, $dtr_id);
            $upd->execute();
            $upd->close();
        }

        $conn->commit();

        // return updated row (trimmed) and month total
        $row = fetch_row($conn, $student_id, $today);
        $mTotal = month_total($conn, $student_id, (int)date('Y'), (int)date('n'));
        echo json_encode(['success' => true, 'row' => $row, 'month_total' => $mTotal]);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;
?>