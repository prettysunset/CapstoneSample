<?php
// Simple JSON endpoint for OJT DTR actions used by ojts/ojt_home.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

date_default_timezone_set('Asia/Manila'); // <<< ensure correct local time

require_once __DIR__ . '/../conn.php'; // $conn (mysqli)

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
$nowTime = date('H:i:s'); // server-local time now (Asia/Manila)

/** Helpers **/
function fetch_row($conn, $student_id, $date) {
    $s = $conn->prepare("SELECT dtr_id, log_date, am_in, am_out, pm_in, pm_out, hours, minutes FROM dtr WHERE student_id = ? AND log_date = ?");
    $s->bind_param("is", $student_id, $date);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$r) return null;

    // ensure hours/minutes are integers
    $hoursStored = isset($r['hours']) ? (int)$r['hours'] : 0;
    $minutesStored = isset($r['minutes']) ? (int)$r['minutes'] : 0;

    // if stored hours/minutes are zero but we have completed time pairs, compute and persist
    $needsUpdate = false;
    $minutes_total = 0;

    if (!empty($r['am_in']) && !empty($r['am_out'])) {
        $t1 = strtotime($date . ' ' . $r['am_in']);
        $t2 = strtotime($date . ' ' . $r['am_out']);
        if ($t2 >= $t1) $minutes_total += intval(round(($t2 - $t1) / 60));
    }
    if (!empty($r['pm_in']) && !empty($r['pm_out'])) {
        $t1 = strtotime($date . ' ' . $r['pm_in']);
        $t2 = strtotime($date . ' ' . $r['pm_out']);
        if ($t2 >= $t1) $minutes_total += intval(round(($t2 - $t1) / 60));
    }

    if ($minutes_total > 0) {
        $calcHours = intdiv($minutes_total, 60);
        $calcMinutes = $minutes_total % 60;
        if ($hoursStored !== $calcHours || $minutesStored !== $calcMinutes) {
            // persist corrected values
            $u = $conn->prepare("UPDATE dtr SET hours = ?, minutes = ? WHERE dtr_id = ?");
            $u->bind_param("iii", $calcHours, $calcMinutes, $r['dtr_id']);
            $u->execute();
            $u->close();
            $hoursStored = $calcHours;
            $minutesStored = $calcMinutes;
            $needsUpdate = true;
        }
    }

    $total = ($hoursStored || $minutesStored) ? ($hoursStored + ($minutesStored / 60)) : null;

    return [
        'dtr_id' => (int)$r['dtr_id'],
        'log_date' => $r['log_date'],
        'am_in' => $r['am_in'],
        'am_out' => $r['am_out'],
        'pm_in' => $r['pm_in'],
        'pm_out' => $r['pm_out'],
        'hours' => $hoursStored,
        'minutes' => $minutesStored,
        'total_hours' => $total
    ];
}

function month_total($conn, $student_id, $year, $month) {
    $q = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ? AND YEAR(log_date)=? AND MONTH(log_date)=?");
    $q->bind_param("iii", $student_id, $year, $month);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    return (float)($row['total'] ?? 0.0);
}

function ensure_row($conn, $student_id, $date) {
    $r = fetch_row($conn, $student_id, $date);
    if ($r) return $r;
    $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date) VALUES (?, ?)");
    $ins->bind_param("is", $student_id, $date);
    $ins->execute();
    $ins->close();
    return fetch_row($conn, $student_id, $date);
}

/** Actions **/
if ($action === 'get_today') {
    $row = fetch_row($conn, $student_id, $today);
    $mTotal = month_total($conn, $student_id, (int)date('Y'), (int)date('n'));
    echo json_encode(['success'=>true,'row'=>$row,'month_total'=>$mTotal]); exit;
}

if ($action === 'time_in' || $action === 'time_out') {
    $conn->begin_transaction();
    try {
        $row = ensure_row($conn, $student_id, $today);
        $dtr_id = $row['dtr_id'];

        // current stored values
        $sel = $conn->prepare("SELECT am_in, am_out, pm_in, pm_out FROM dtr WHERE dtr_id = ?");
        $sel->bind_param("i", $dtr_id);
        $sel->execute();
        $cur = $sel->get_result()->fetch_assoc();
        $sel->close();

        $am_in = $cur['am_in'];
        $am_out = $cur['am_out'];
        $pm_in = $cur['pm_in'];
        $pm_out = $cur['pm_out'];

        if ($action === 'time_in') {
            // If no IN at all -> first IN decides AM/PM by current hour (<12 => AM)
            if (empty($am_in) && empty($pm_in)) {
                $hour = (int)date('H'); // server local hour 0-23
                if ($hour < 12) {
                    $u = $conn->prepare("UPDATE dtr SET am_in = ? WHERE dtr_id = ?");
                } else {
                    $u = $conn->prepare("UPDATE dtr SET pm_in = ? WHERE dtr_id = ?");
                }
                $u->bind_param("si", $nowTime, $dtr_id);
                $u->execute();
                $u->close();
            } else {
                // Not first IN: do not overwrite an open IN. If AM session closed and pm_in empty, set pm_in.
                if (!empty($am_in) && !empty($am_out) && empty($pm_in)) {
                    $u = $conn->prepare("UPDATE dtr SET pm_in = ? WHERE dtr_id = ?");
                    $u->bind_param("si", $nowTime, $dtr_id);
                    $u->execute();
                    $u->close();
                }
                // otherwise ignore duplicate IN (prevents overwriting existing am_in/pm_in)
            }
        } else { // time_out
            // close the last unmatched IN: prefer pm_in->pm_out, else am_in->am_out
            if (!empty($pm_in) && empty($pm_out)) {
                $u = $conn->prepare("UPDATE dtr SET pm_out = ? WHERE dtr_id = ?");
                $u->bind_param("si", $nowTime, $dtr_id);
                $u->execute();
                $u->close();
            } elseif (!empty($am_in) && empty($am_out)) {
                $u = $conn->prepare("UPDATE dtr SET am_out = ? WHERE dtr_id = ?");
                $u->bind_param("si", $nowTime, $dtr_id);
                $u->execute();
                $u->close();
            } else {
                // fallback: set pm_out if nothing unmatched
                $u = $conn->prepare("UPDATE dtr SET pm_out = ? WHERE dtr_id = ?");
                $u->bind_param("si", $nowTime, $dtr_id);
                $u->execute();
                $u->close();
            }

            // recompute total minutes from completed sessions and store hours/minutes
            $sel2 = $conn->prepare("SELECT am_in, am_out, pm_in, pm_out FROM dtr WHERE dtr_id = ?");
            $sel2->bind_param("i", $dtr_id);
            $sel2->execute();
            $cur2 = $sel2->get_result()->fetch_assoc();
            $sel2->close();

            $minutes_total = 0;
            if (!empty($cur2['am_in']) && !empty($cur2['am_out'])) {
                $t1 = strtotime($today . ' ' . $cur2['am_in']);
                $t2 = strtotime($today . ' ' . $cur2['am_out']);
                if ($t2 >= $t1) $minutes_total += intval(round(($t2 - $t1) / 60));
            }
            if (!empty($cur2['pm_in']) && !empty($cur2['pm_out'])) {
                $t1 = strtotime($today . ' ' . $cur2['pm_in']);
                $t2 = strtotime($today . ' ' . $cur2['pm_out']);
                if ($t2 >= $t1) $minutes_total += intval(round(($t2 - $t1) / 60));
            }

            $calcHours = intdiv($minutes_total, 60);
            $calcMinutes = $minutes_total % 60;

            $upd = $conn->prepare("UPDATE dtr SET hours = ?, minutes = ? WHERE dtr_id = ?");
            $upd->bind_param("iii", $calcHours, $calcMinutes, $dtr_id);
            $upd->execute();
            $upd->close();
        }

        $conn->commit();

        $row = fetch_row($conn, $student_id, $today);
        $mTotal = month_total($conn, $student_id, (int)date('Y'), (int)date('n'));

        echo json_encode(['success'=>true,'row'=>$row,'month_total'=>$mTotal]);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;
?>