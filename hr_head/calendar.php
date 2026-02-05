<?php
// Ensure AJAX JSON POSTs are handled before any HTML is output so responses are clean
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  // capture any stray output and make responses robust for the JS client
  ob_start();
  $raw = file_get_contents('php://input');
  error_log('calendar.php POST payload: ' . $raw);
  $input = json_decode($raw, true);
  // quick counts endpoint for reschedule modal: return number of assignments per session date
  if ($input && ($input['action'] ?? '') === 'reschedule_counts') {
    try {
      $connPath = __DIR__ . '/../conn.php'; if (file_exists($connPath)) { @include_once $connPath; }
      if (!isset($conn) || !$conn instanceof mysqli) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'DB connection missing']); exit; }
      $start = trim($input['start_date'] ?? ''); $end = trim($input['end_date'] ?? '');
      if (!$start || !$end) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Missing range']); exit; }
      // detect date column name in orientation_sessions
      $cols2 = []; $resCols2 = $conn->query("SHOW COLUMNS FROM orientation_sessions"); if ($resCols2) { while ($r = $resCols2->fetch_assoc()) $cols2[] = $r['Field']; }
      $dateCandidates2 = ['session_date','date','start','session_datetime','datetime','scheduled_at']; $dateCol2=null;
      foreach ($dateCandidates2 as $c) if (in_array($c,$cols2)) { $dateCol2=$c; break; }
      if (!$dateCol2) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'No date column']); exit; }
      // query counts grouped by date
      $sql = "SELECT DATE(`" . $dateCol2 . "`) AS d, COUNT(oa.session_id) AS cnt FROM orientation_sessions s LEFT JOIN orientation_assignments oa ON s.session_id = oa.session_id WHERE DATE(s.`" . $dateCol2 . "`) BETWEEN ? AND ? GROUP BY DATE(s.`" . $dateCol2 . "`)";
      $stmt = $conn->prepare($sql);
      if (!$stmt) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Prepare failed','error'=>$conn->error]); exit; }
      $stmt->bind_param('ss',$start,$end); $stmt->execute(); $res = $stmt->get_result(); $map = [];
      if ($res) { while ($r = $res->fetch_assoc()) { $map[$r['d']] = (int)$r['cnt']; } }
      $stmt->close(); ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>true,'counts'=>$map]); exit;
    } catch (Exception $e) { $buf = ob_get_clean(); error_log('reschedule_counts error: '.$e->getMessage()."\n".$buf); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Internal']); exit; }
  }
  if ($input && ($input['action'] ?? '') === 'reschedule_all') {
    try {
      // minimal DB bootstrap (conn.php expected to set $conn)
      $connPath = __DIR__ . '/../conn.php';
      if (file_exists($connPath)) {
        @include_once $connPath;
      }
      $origSessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
      $targetDate = trim($input['target_date'] ?? '');
      $targetTime = trim($input['target_time'] ?? '');
      $targetLocation = trim($input['target_location'] ?? '');
      if (!isset($conn) || !$conn instanceof mysqli) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'DB connection missing']); exit; }
      if (!$origSessionId || !$targetDate) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Missing parameters']); exit; }

      // detect columns in orientation_sessions
      $cols2 = [];
      $resCols2 = $conn->query("SHOW COLUMNS FROM orientation_sessions");
      if ($resCols2) { while ($r = $resCols2->fetch_assoc()) $cols2[] = $r['Field']; }
      $dateCandidates2 = ['session_date','date','start','session_datetime','datetime','scheduled_at'];
      $timeCandidates2 = ['session_time','time','start_time'];
      $locCandidates2 = ['location','venue','place'];
      $dateCol2=null;$timeCol2=null;$locCol2=null;
      foreach ($dateCandidates2 as $c) if (in_array($c,$cols2)) { $dateCol2=$c; break; }
      foreach ($timeCandidates2 as $c) if (in_array($c,$cols2)) { $timeCol2=$c; break; }
      foreach ($locCandidates2 as $c) if (in_array($c,$cols2)) { $locCol2=$c; break; }
      if (!$dateCol2) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'No date column']); exit; }

      // fetch original session
      $orig=null;
      $st0=$conn->prepare("SELECT * FROM orientation_sessions WHERE session_id = ? LIMIT 1");
      $st0->bind_param('i',$origSessionId); $st0->execute(); $r0=$st0->get_result(); if ($r0) $orig=$r0->fetch_assoc(); $st0->close();
      $defaultTime = $orig[$timeCol2] ?? ($orig['time'] ?? '');
      $defaultLoc = $orig[$locCol2] ?? ($orig['location'] ?? '');
      $useTime = $targetTime !== '' ? $targetTime : $defaultTime;
      $useLoc = $targetLocation !== '' ? $targetLocation : $defaultLoc;

      // check existing session on target date
      $stmtChk = $conn->prepare("SELECT * FROM orientation_sessions WHERE DATE(`$dateCol2`) = ? LIMIT 1");
      $stmtChk->bind_param('s', $targetDate); $stmtChk->execute(); $resChk = $stmtChk->get_result(); $found = $resChk ? $resChk->fetch_assoc() : null; $stmtChk->close();
      $targetSessionId = null;
      if ($found) { $targetSessionId = (int)$found['session_id']; }
      else {
        // create new session row
        if ($timeCol2 && $dateCol2 && $timeCol2 !== $dateCol2) {
          $ins = $conn->prepare("INSERT INTO orientation_sessions (`$dateCol2`, `$timeCol2`" . ($locCol2?",`$locCol2`":"") . ") VALUES (? , ?" . ($locCol2?",?":"") . ")");
          if ($locCol2) $ins->bind_param('sss', $targetDate, $useTime, $useLoc); else $ins->bind_param('ss', $targetDate, $useTime);
        } else {
          $dtval = $targetDate . (!empty($useTime) ? ' ' . $useTime : '');
          $ins = $conn->prepare("INSERT INTO orientation_sessions (`$dateCol2`" . ($locCol2?",`$locCol2`":"") . ") VALUES (?" . ($locCol2?",?":"") . ")");
          if ($locCol2) $ins->bind_param('ss', $dtval, $useLoc); else $ins->bind_param('s', $dtval);
        }
        $ok = $ins->execute(); if (!$ok) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Create session failed','error'=>$ins->error]); exit; }
        $targetSessionId = (int)$ins->insert_id; $ins->close();
      }

      // move assignments (optionally only selected students)
      $hasAssign = $conn->query("SHOW TABLES LIKE 'orientation_assignments'")->num_rows > 0;
      if (!$hasAssign) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'assignments table missing']); exit; }
      $sA = $conn->prepare("SELECT * FROM orientation_assignments WHERE session_id = ?"); $sA->bind_param('i',$origSessionId); $sA->execute(); $resA = $sA->get_result(); $assigns=[]; while($ar=$resA->fetch_assoc()) { $assigns[]=$ar; } $sA->close();
      // previous date value for marking moved assignments (date-only)
      $prevDateVal = null;
      if (!empty($orig[$dateCol2])) { $prevDateVal = date('Y-m-d', strtotime($orig[$dateCol2])); }

      // detect whether orientation_assignments has a reschedule column (support both names)
      $assignHasRescheduled = false;
      $assignReschedCol = null;
      $resAssignCols = $conn->query("SHOW COLUMNS FROM orientation_assignments");
      if ($resAssignCols) {
        while ($ac = $resAssignCols->fetch_assoc()) {
          if (isset($ac['Field']) && ($ac['Field'] === 'rescheduled_from' || $ac['Field'] === 'resched_from')) { $assignHasRescheduled = true; $assignReschedCol = $ac['Field']; break; }
        }
      }

      // session-level reschedule column (support both names)
      $sessionReschedCol = null;
      if (!empty($cols2)) {
        if (in_array('rescheduled_from', $cols2)) $sessionReschedCol = 'rescheduled_from';
        elseif (in_array('resched_from', $cols2)) $sessionReschedCol = 'resched_from';
      }

      $movedApplicationIds = [];
      $movedStudentIds = [];
      $selectedApps = [];
      $selectedStudents = [];
      if (!empty($input['selected']) && is_array($input['selected'])) {
        foreach ($input['selected'] as $it) {
          if (isset($it['application_id']) && $it['application_id'] !== '') $selectedApps[] = (int)$it['application_id'];
          if (isset($it['student_id']) && $it['student_id'] !== '') $selectedStudents[] = (int)$it['student_id'];
        }
      } else { $selectedApps = null; $selectedStudents = null; }

      // Bulk move logic (same as original implementation)
      if (($selectedApps !== null && is_array($selectedApps) && count($selectedApps) > 0) || ($selectedStudents !== null && is_array($selectedStudents) && count($selectedStudents) > 0)) {
        // handle application_id based moves first
        if (!empty($selectedApps)) {
          $apps = array_map('intval', $selectedApps);
          $inApps = implode(',', $apps);
          $already = [];
          $q = $conn->query("SELECT application_id FROM orientation_assignments WHERE session_id = " . (int)$targetSessionId . " AND application_id IN ($inApps)");
          if ($q) { while ($r = $q->fetch_assoc()) { $already[] = (int)$r['application_id']; } $q->free(); }
          $toMove = array_values(array_diff($apps, $already));
          if (!empty($toMove)) {
            $inMove = implode(',', array_map('intval', $toMove));
            $sqlUp = "UPDATE orientation_assignments SET session_id = " . (int)$targetSessionId;
            if (!empty($prevDateVal) && $assignHasRescheduled && $assignReschedCol) { $sqlUp .= ", `" . $assignReschedCol . "` = '" . $conn->real_escape_string($prevDateVal) . "'"; }
            $sqlUp .= " WHERE session_id = " . (int)$origSessionId . " AND application_id IN ($inMove)";
            $conn->query($sqlUp);
            foreach ($toMove as $a) $movedApplicationIds[] = (int)$a;
          }
        }

        // handle student_id selections by resolving to application_ids assigned in the original session
        if (!empty($selectedStudents)) {
          $studs = array_map('intval', $selectedStudents);
          $inStuds = implode(',', $studs);
          $appList = [];
          $q2 = $conn->query("SELECT oa.application_id FROM orientation_assignments oa LEFT JOIN ojt_applications app ON oa.application_id = app.application_id WHERE oa.session_id = " . (int)$origSessionId . " AND app.student_id IN ($inStuds) AND oa.application_id IS NOT NULL");
          if ($q2) { while ($rr = $q2->fetch_assoc()) { $appList[] = (int)$rr['application_id']; } $q2->free(); }
          if (!empty($appList)) {
            $inApps2 = implode(',', $appList);
            $already2 = [];
            $q3 = $conn->query("SELECT application_id FROM orientation_assignments WHERE session_id = " . (int)$targetSessionId . " AND application_id IN ($inApps2)");
            if ($q3) { while ($r3 = $q3->fetch_assoc()) { $already2[] = (int)$r3['application_id']; } $q3->free(); }
            $toMove2 = array_values(array_diff($appList, $already2));
            if (!empty($toMove2)) {
              $inMove2 = implode(',', array_map('intval', $toMove2));
              $sqlUp2 = "UPDATE orientation_assignments SET session_id = " . (int)$targetSessionId;
              if (!empty($prevDateVal) && $assignHasRescheduled && $assignReschedCol) { $sqlUp2 .= ", `" . $assignReschedCol . "` = '" . $conn->real_escape_string($prevDateVal) . "'"; }
              $sqlUp2 .= " WHERE session_id = " . (int)$origSessionId . " AND application_id IN ($inMove2)";
              $conn->query($sqlUp2);
              foreach ($toMove2 as $a2) $movedApplicationIds[] = (int)$a2;
            }
          }
        }

        // resolve moved applications to student ids for notifications
        if (!empty($movedApplicationIds)) {
          $ids = array_map('intval', array_unique($movedApplicationIds));
          $in = implode(',', $ids);
          $rmap = $conn->query("SELECT application_id, student_id FROM ojt_applications WHERE application_id IN ($in)");
          if ($rmap) { while ($rr = $rmap->fetch_assoc()) { if (!empty($rr['student_id'])) $movedStudentIds[] = (int)$rr['student_id']; } $rmap->free(); }
        }

        // ensure unique student ids
        $movedStudentIds = array_values(array_unique(array_filter($movedStudentIds)));
      } else {
        // no explicit selection: fallback to per-assignment loop (move all assignments from original session)
        foreach($assigns as $ar) {
          $appId = $ar['application_id'] ?? ($ar['applicationid'] ?? null);
          if ($appId) {
            $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM orientation_assignments WHERE session_id = ? AND (application_id = ? OR student_id = ?) LIMIT 1");
            $chk->bind_param('iii',$targetSessionId, $appId, $appId); $chk->execute(); $cres = $chk->get_result(); $crow = $cres?$cres->fetch_assoc():null; $cnt = $crow? (int)$crow['cnt'] : 0; $chk->close();
            if ($cnt === 0) {
              if (!empty($prevDateVal)) {
                if ($assignHasRescheduled) {
                  if (!empty($assignReschedCol)) {
                    $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ?, `{$assignReschedCol}` = ? WHERE application_id = ? LIMIT 1"); $up->bind_param('isi',$targetSessionId,$prevDateVal,$appId);
                  } else {
                    $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE application_id = ? LIMIT 1"); $up->bind_param('ii',$targetSessionId,$appId);
                  }
                } else {
                  $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE application_id = ? LIMIT 1"); $up->bind_param('ii',$targetSessionId,$appId);
                }
              } else {
                $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE application_id = ? LIMIT 1"); $up->bind_param('ii',$targetSessionId,$appId);
              }
              $up->execute(); $up->close();
              if (!empty($ar['application_id'])) $movedApplicationIds[] = (int)$ar['application_id'];
              if (!empty($ar['student_id'])) $movedStudentIds[] = (int)$ar['student_id'];
            }
          } else {
            $assignId = $ar['id'] ?? $ar['assignment_id'] ?? null;
            if ($assignId) {
              if (!empty($prevDateVal)) {
                if ($assignHasRescheduled && !empty($assignReschedCol)) {
                  $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ?, `{$assignReschedCol}` = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('isii',$targetSessionId,$prevDateVal,$assignId,$assignId);
                } else {
                  $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('iii',$targetSessionId,$assignId,$assignId);
                }
              } else {
                $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('iii',$targetSessionId,$assignId,$assignId);
              }
              $up->execute(); $up->close();
            }
          }
        }
      }

      // resolve application ids to student ids
      if (!empty($movedApplicationIds)) {
        $ids = array_map('intval', $movedApplicationIds);
        $in = implode(',', $ids);
        $sqlA = "SELECT application_id, student_id FROM ojt_applications WHERE application_id IN ($in)";
        $resA2 = $conn->query($sqlA);
        $appToStud = [];
        if ($resA2) { while ($r = $resA2->fetch_assoc()) { $appToStud[(int)$r['application_id']] = (int)$r['student_id']; } $resA2->free(); }
        foreach ($movedApplicationIds as $aid) { $aidI = (int)$aid; if (isset($appToStud[$aidI])) $movedStudentIds[] = $appToStud[$aidI]; }
      }
      $movedStudentIds = array_values(array_unique(array_filter($movedStudentIds)));

      // If the original session now has no assignments, delete it
      $deletedOriginal = false;
      try {
        $remStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orientation_assignments WHERE session_id = ?");
        if ($remStmt) {
          $remStmt->bind_param('i', $origSessionId);
          $remStmt->execute();
          $remRes = $remStmt->get_result();
          $remCount = 0;
          if ($remRes && $rrem = $remRes->fetch_assoc()) { $remCount = (int)$rrem['cnt']; }
          $remStmt->close();
          if ($remCount === 0) {
            $delSess = $conn->prepare("DELETE FROM orientation_sessions WHERE session_id = ? LIMIT 1");
            if ($delSess) { $delSess->bind_param('i', $origSessionId); $delSess->execute(); if ($conn->affected_rows > 0) $deletedOriginal = true; $delSess->close(); }
          }
        }
      } catch (Exception $e) { error_log('Error checking/deleting original session: ' . $e->getMessage()); }

      // persist rescheduled_from on the target session so the UI can show it permanently
      try {
        if (!empty($targetSessionId) && !empty($orig[$dateCol2])) {
          $prevDateVal = date('Y-m-d', strtotime($orig[$dateCol2]));
          if (!empty($sessionReschedCol)) {
            $updSql = "UPDATE orientation_sessions SET `" . $sessionReschedCol . "` = ? WHERE session_id = ? AND (`" . $sessionReschedCol . "` IS NULL OR `" . $sessionReschedCol . "` = '') LIMIT 1";
            $upd = $conn->prepare($updSql);
            if ($upd) { $upd->bind_param('si', $prevDateVal, $targetSessionId); $upd->execute(); $upd->close(); }
          }
        }
      } catch (Exception $e) { error_log('Error setting rescheduled_from: ' . $e->getMessage()); }

      // (notification/email steps omitted here for brevity — keep existing code in main file if needed)

      $fromDate = $orig[$dateCol2] ?? null;
      $fromLabel = $fromDate ? date('M j, Y', strtotime($fromDate)) : null;
      // ensure moved ids are integers for JSON
      $movedApplicationIds = array_values(array_map('intval', array_values($movedApplicationIds)));
      $movedStudentIds = array_values(array_map('intval', array_values($movedStudentIds)));
      ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode([
        'success'=>true,
        'message'=>'Rescheduled',
        'target_session_id'=>$targetSessionId,
        'from_date'=>$fromLabel,
        'deleted_original'=>!empty($deletedOriginal),
        'moved_application_ids'=>$movedApplicationIds,
        'moved_student_ids'=>$movedStudentIds
      ]);
      exit;
    } catch (\Throwable $e) {
      $buf = ob_get_clean(); error_log('calendar.php reschedule exception: ' . $e->getMessage() . '\nBuffer:\n' . $buf . '\nTrace:\n' . $e->getTraceAsString()); header('Content-Type: application/json; charset=utf-8'); http_response_code(500); echo json_encode(['success'=>false,'message'=>'Internal server error','error'=>$e->getMessage(),'output'=>substr($buf,0,2000)]); exit;
    }
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Calendar</title>
  <?php
    // determine month/year from query params (defaults to current month/year)
    $m = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
    $y = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
    if ($m < 1) { $m = 1; } elseif ($m > 12) { $m = 12; }
    $first = new DateTime();
    $first->setDate($y, $m, 1);
    $monthLabel = $first->format('F Y');
    $startWeekday = (int)$first->format('w'); // 0=Sun..6=Sat
    $daysInMonth = (int)$first->format('t');
    // previous month info (for leading day numbers)
    $prev = (clone $first)->modify('-1 month');
    $daysInPrev = (int)$prev->format('t');
    // build events map (day => label) from DB `orientation_sessions`
    $events = [];
    $eventsData = []; // structured data for JS (day => array of sessions)
    // try to load real orientation sessions from DB if available
    $connPath = __DIR__ . '/../conn.php';
    if (file_exists($connPath)) {
      @include_once $connPath;
      if (isset($conn) && $conn instanceof mysqli) {
        $cols = [];
        $resCols = $conn->query("SHOW COLUMNS FROM orientation_sessions");
        if ($resCols) {
          while ($r = $resCols->fetch_assoc()) { $cols[] = $r['Field']; }
          // detect session-level reschedule column name
          $sessionReschedCol = null;
          if (!empty($cols)) {
            if (in_array('rescheduled_from', $cols)) $sessionReschedCol = 'rescheduled_from';
            elseif (in_array('resched_from', $cols)) $sessionReschedCol = 'resched_from';
          }
          $dateCandidates = ['session_date','date','start','session_datetime','datetime','scheduled_at'];
          $timeCandidates = ['session_time','time','start_time'];
          $locCandidates = ['location','venue','place'];
          $dateCol = null; $timeCol = null; $locCol = null;
          foreach ($dateCandidates as $c) if (in_array($c,$cols)) { $dateCol = $c; break; }
          foreach ($timeCandidates as $c) if (in_array($c,$cols)) { $timeCol = $c; break; }
          foreach ($locCandidates as $c) if (in_array($c,$cols)) { $locCol = $c; break; }
          if ($dateCol) {
            $startDate = $first->format('Y-m-01');
            $endDate = $first->format('Y-m-t');
            $sql = "SELECT * FROM orientation_sessions WHERE DATE(`" . $dateCol . "`) BETWEEN ? AND ?";
            // prepare a count statement for assignments if table exists
            $countStmt = null;
            $resAssign = $conn->query("SHOW TABLES LIKE 'orientation_assignments'");
            $assignCols = [];
            $assignStmt = null;
            $nameCol = null;
            $idCol = null;
            if ($resAssign && $resAssign->num_rows > 0) {
              // detect assignment table columns to extract student names/ids
              $resAssignCols = $conn->query("SHOW COLUMNS FROM orientation_assignments");
              if ($resAssignCols) {
                while ($ac = $resAssignCols->fetch_assoc()) { $assignCols[] = $ac['Field']; }
                // detect assignment-level reschedule column name
                $assignReschedCol = null;
                if (!empty($assignCols)) {
                  if (in_array('rescheduled_from', $assignCols)) $assignReschedCol = 'rescheduled_from';
                  elseif (in_array('resched_from', $assignCols)) $assignReschedCol = 'resched_from';
                }
                $nameCandidates = ['student_name','name','full_name','ojt_name','first_name','last_name'];
                $idCandidates = ['id','assignment_id','student_id','ojt_id'];
                foreach ($nameCandidates as $nc) if (in_array($nc,$assignCols)) { $nameCol = $nc; break; }
                foreach ($idCandidates as $ic) if (in_array($ic,$assignCols)) { $idCol = $ic; break; }
              }
              if (!$countStmt) {
                $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orientation_assignments WHERE session_id = ?");
              }
              // select assignment rows and join to application -> students to obtain student names when available
              $assignStmt = $conn->prepare(
                'SELECT oa.*, app.application_id, app.student_id, s.first_name, s.last_name
                 FROM orientation_assignments oa
                 LEFT JOIN ojt_applications app ON oa.application_id = app.application_id
                 LEFT JOIN students s ON app.student_id = s.student_id
                 WHERE oa.session_id = ?'
              );
            }
            if ($stmt = $conn->prepare($sql)) {
              $stmt->bind_param('ss', $startDate, $endDate);
              $stmt->execute();
              $result = $stmt->get_result();
              while ($row = $result->fetch_assoc()) {
                $dt = $row[$dateCol];
                $ts = strtotime($dt);
                if ($ts === false) continue;
                $d = (int)date('j', $ts);
                // build HTML parts: time, location, count
                $timeHtml = '';
                $timeLabel = '';
                if ($timeCol && !empty($row[$timeCol])) {
                  $timeLabel = date('g:i A', strtotime($row[$timeCol]));
                  $timeHtml = '<div class="ev-time">' . htmlspecialchars($timeLabel) . '</div>';
                } else {
                  if (strpos($dt, ' ') !== false) { $timeLabel = date('g:i A', $ts); $timeHtml = '<div class="ev-time">' . htmlspecialchars($timeLabel) . '</div>'; }
                }
                $locLabel = ($locCol && !empty($row[$locCol])) ? $row[$locCol] : 'Location';
                $locHtml = '<div class="ev-loc">' . htmlspecialchars($locLabel) . '</div>';
                $countHtml = '';
                $students = [];
                if ($assignStmt && isset($row['session_id'])) {
                  $sid = $row['session_id'];
                  $assignStmt->bind_param('i', $sid);
                  $assignStmt->execute();
                  $ares = $assignStmt->get_result();
                  if ($ares) {
                    while ($ar = $ares->fetch_assoc()) {
                      $sname = trim((string)($ar['first_name'] ?? '') . ' ' . ($ar['last_name'] ?? ''));
                      $sid = $ar['student_id'] ?? ($ar['application_id'] ?? ($ar['id'] ?? null));
                      $appId = $ar['application_id'] ?? null;
                      $studId = $ar['student_id'] ?? null;
                      // fallback: if join didn't return a name but student_id exists, try to fetch student record
                      if (empty(trim($sname)) && !empty($sid) && is_numeric($sid)) {
                        $sres = $conn->prepare("SELECT first_name, last_name FROM students WHERE student_id = ?");
                        if ($sres) {
                          $sres->bind_param('i', $sid);
                          $sres->execute();
                          $sresr = $sres->get_result();
                          if ($sresr && $sr = $sresr->fetch_assoc()) {
                            $sname = trim((string)($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? ''));
                          }
                          $sres->close();
                        }
                      }
                      if (empty(trim($sname))) {
                        if (!empty($ar['application_id'])) $sname = 'App #' . $ar['application_id'];
                        else $sname = 'Assigned OJT';
                      }
                      $students[] = ['id' => $sid, 'name' => $sname, 'application_id' => $appId, 'student_id' => $studId, 'rescheduled_from' => (isset($assignReschedCol) && $assignReschedCol && isset($ar[$assignReschedCol])) ? $ar[$assignReschedCol] : (isset($ar['rescheduled_from']) ? $ar['rescheduled_from'] : null)];
                    }
                  }
                  $cnt = count($students);
                  if ($cnt > 0) {
                    $labelCnt = ($cnt === 1) ? '1 OJT' : ($cnt . ' OJTs');
                    $countHtml = '<div class="ev-count">' . htmlspecialchars($labelCnt) . '</div>';
                  }
                } elseif ($countStmt && isset($row['session_id'])) {
                  $sid = $row['session_id'];
                  $countStmt->bind_param('i', $sid);
                  $countStmt->execute();
                  $cres = $countStmt->get_result();
                  if ($cres && $crow = $cres->fetch_assoc()) {
                      $cnt = (int)$crow['cnt'];
                      if ($cnt > 0) {
                        $labelCnt = ($cnt === 1) ? '1 OJT' : ($cnt . ' OJTs');
                        $countHtml = '<div class="ev-count">' . htmlspecialchars($labelCnt) . '</div>';
                      }
                    }
                }
                $label = trim($timeHtml . $locHtml . $countHtml);
                if (isset($events[$d])) $events[$d] .= '<br>' . $label;
                else $events[$d] = $label;
                // store structured session data for JS
                $sess = [
                  'session_id' => $row['session_id'] ?? null,
                  'time' => $timeLabel,
                  'date' => $dt,
                  'location' => $locLabel,
                  'count' => isset($cnt) ? (int)$cnt : 0,
                  'students' => $students,
                  // intentionally do not expose session-level rescheduled_from here; prefer per-assignment badges
                  'rescheduled_from' => null
                ];
                if (!isset($eventsData[$d])) $eventsData[$d] = [];
                $eventsData[$d][] = $sess;
              }
            }
            if ($countStmt) $countStmt->close();
          }
        }
      }
    }
      // Handle AJAX reschedule POST to this same script
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        // capture any stray output and make responses robust for the JS client
        ob_start();
        $raw = file_get_contents('php://input');
        error_log('calendar.php POST payload: ' . $raw);
        $input = json_decode($raw, true);
        if ($input && ($input['action'] ?? '') === 'reschedule_all') {
          try {
          $origSessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
          $targetDate = trim($input['target_date'] ?? '');
          $targetTime = trim($input['target_time'] ?? '');
          $targetLocation = trim($input['target_location'] ?? '');
          if (!$origSessionId || !$targetDate) {
            ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Missing parameters']); exit;
          }
          // detect columns
          $cols2 = [];
          $resCols2 = $conn->query("SHOW COLUMNS FROM orientation_sessions");
          if ($resCols2) { while ($r = $resCols2->fetch_assoc()) $cols2[] = $r['Field']; }
          $dateCandidates2 = ['session_date','date','start','session_datetime','datetime','scheduled_at'];
          $timeCandidates2 = ['session_time','time','start_time'];
          $locCandidates2 = ['location','venue','place'];
          $dateCol2=null;$timeCol2=null;$locCol2=null;
          foreach ($dateCandidates2 as $c) if (in_array($c,$cols2)) { $dateCol2=$c; break; }
          foreach ($timeCandidates2 as $c) if (in_array($c,$cols2)) { $timeCol2=$c; break; }
          foreach ($locCandidates2 as $c) if (in_array($c,$cols2)) { $locCol2=$c; break; }
          if (!$dateCol2) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'No date column']); exit; }

          // fetch original session
          $orig=null;
          $st0=$conn->prepare("SELECT * FROM orientation_sessions WHERE session_id = ? LIMIT 1");
          $st0->bind_param('i',$origSessionId); $st0->execute(); $r0=$st0->get_result(); if ($r0) $orig=$r0->fetch_assoc(); $st0->close();
          $defaultTime = $orig[$timeCol2] ?? ($orig['time'] ?? '');
          $defaultLoc = $orig[$locCol2] ?? ($orig['location'] ?? '');
          $useTime = $targetTime !== '' ? $targetTime : $defaultTime;
          $useLoc = $targetLocation !== '' ? $targetLocation : $defaultLoc;

          // check existing session on target date
          $stmtChk = $conn->prepare("SELECT * FROM orientation_sessions WHERE DATE(`$dateCol2`) = ? LIMIT 1");
          $stmtChk->bind_param('s', $targetDate); $stmtChk->execute(); $resChk = $stmtChk->get_result(); $found = $resChk ? $resChk->fetch_assoc() : null; $stmtChk->close();
          $targetSessionId = null;
          if ($found) { $targetSessionId = (int)$found['session_id']; }
          else {
            // create new session row
            if ($timeCol2 && $dateCol2 && $timeCol2 !== $dateCol2) {
              $ins = $conn->prepare("INSERT INTO orientation_sessions (`$dateCol2`, `$timeCol2`" . ($locCol2?",`$locCol2`":"") . ") VALUES (? , ?" . ($locCol2?",?":"") . ")");
              if ($locCol2) $ins->bind_param('sss', $targetDate, $useTime, $useLoc); else $ins->bind_param('ss', $targetDate, $useTime);
            } else {
              $dtval = $targetDate . (!empty($useTime) ? ' ' . $useTime : '');
              $ins = $conn->prepare("INSERT INTO orientation_sessions (`$dateCol2`" . ($locCol2?",`$locCol2`":"") . ") VALUES (?" . ($locCol2?",?":"") . ")");
              if ($locCol2) $ins->bind_param('ss', $dtval, $useLoc); else $ins->bind_param('s', $dtval);
            }
            $ok = $ins->execute(); if (!$ok) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Create session failed','error'=>$ins->error]); exit; }
            $targetSessionId = (int)$ins->insert_id; $ins->close();
          }

          // move assignments (optionally only selected students)
          $hasAssign = $conn->query("SHOW TABLES LIKE 'orientation_assignments'")->num_rows > 0;
          if (!$hasAssign) { ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'assignments table missing']); exit; }
          $sA = $conn->prepare("SELECT * FROM orientation_assignments WHERE session_id = ?"); $sA->bind_param('i',$origSessionId); $sA->execute(); $resA = $sA->get_result(); $assigns=[]; while($ar=$resA->fetch_assoc()) { $assigns[]=$ar; } $sA->close();
          // previous date value for marking moved assignments (date-only)
          $prevDateVal = null;
          if (!empty($orig[$dateCol2])) { $prevDateVal = date('Y-m-d', strtotime($orig[$dateCol2])); }

          // we'll collect moved students/apps to notify by email (best-effort)
          $movedApplicationIds = [];
          $movedStudentIds = [];

          // build selected lists if provided
          $selectedApps = [];
          $selectedStudents = [];
          if (!empty($input['selected']) && is_array($input['selected'])) {
            foreach ($input['selected'] as $it) {
              if (isset($it['application_id']) && $it['application_id'] !== '') $selectedApps[] = (int)$it['application_id'];
              if (isset($it['student_id']) && $it['student_id'] !== '') $selectedStudents[] = (int)$it['student_id'];
            }
          } else {
            // null means process all
            $selectedApps = null; $selectedStudents = null;
          }

          // If specific students/apps were selected, perform bulk updates for those
          if (($selectedApps !== null && is_array($selectedApps) && count($selectedApps) > 0) || ($selectedStudents !== null && is_array($selectedStudents) && count($selectedStudents) > 0)) {
            // handle application_id based moves first
            if (!empty($selectedApps)) {
              $apps = array_map('intval', $selectedApps);
              $inApps = implode(',', $apps);
              $already = [];
              $q = $conn->query("SELECT application_id FROM orientation_assignments WHERE session_id = " . (int)$targetSessionId . " AND application_id IN ($inApps)");
              if ($q) { while ($r = $q->fetch_assoc()) { $already[] = (int)$r['application_id']; } $q->free(); }
              $toMove = array_values(array_diff($apps, $already));
              if (!empty($toMove)) {
                $inMove = implode(',', array_map('intval', $toMove));
                $sqlUp = "UPDATE orientation_assignments SET session_id = " . (int)$targetSessionId;
                if (!empty($prevDateVal) && $assignHasRescheduled && $assignReschedCol) { $sqlUp .= ", `" . $assignReschedCol . "` = '" . $conn->real_escape_string($prevDateVal) . "'"; }
                $sqlUp .= " WHERE session_id = " . (int)$origSessionId . " AND application_id IN ($inMove)";
                $conn->query($sqlUp);
                foreach ($toMove as $a) $movedApplicationIds[] = (int)$a;
              }
            }

            // handle student_id selections by resolving to application_ids assigned in the original session
            if (!empty($selectedStudents)) {
              $studs = array_map('intval', $selectedStudents);
              $inStuds = implode(',', $studs);
              $appList = [];
              $q2 = $conn->query("SELECT oa.application_id FROM orientation_assignments oa LEFT JOIN ojt_applications app ON oa.application_id = app.application_id WHERE oa.session_id = " . (int)$origSessionId . " AND app.student_id IN ($inStuds) AND oa.application_id IS NOT NULL");
              if ($q2) { while ($rr = $q2->fetch_assoc()) { $appList[] = (int)$rr['application_id']; } $q2->free(); }
              if (!empty($appList)) {
                $inApps2 = implode(',', $appList);
                $already2 = [];
                $q3 = $conn->query("SELECT application_id FROM orientation_assignments WHERE session_id = " . (int)$targetSessionId . " AND application_id IN ($inApps2)");
                if ($q3) { while ($r3 = $q3->fetch_assoc()) { $already2[] = (int)$r3['application_id']; } $q3->free(); }
                $toMove2 = array_values(array_diff($appList, $already2));
                  if (!empty($toMove2)) {
                    $inMove2 = implode(',', array_map('intval', $toMove2));
                    $sqlUp2 = "UPDATE orientation_assignments SET session_id = " . (int)$targetSessionId;
                    if (!empty($prevDateVal) && $assignHasRescheduled && $assignReschedCol) { $sqlUp2 .= ", `" . $assignReschedCol . "` = '" . $conn->real_escape_string($prevDateVal) . "'"; }
                    $sqlUp2 .= " WHERE session_id = " . (int)$origSessionId . " AND application_id IN ($inMove2)";
                    $conn->query($sqlUp2);
                    foreach ($toMove2 as $a2) $movedApplicationIds[] = (int)$a2;
                  }
              }
            }

            // resolve moved applications to student ids for notifications
            if (!empty($movedApplicationIds)) {
              $ids = array_map('intval', array_unique($movedApplicationIds));
              $in = implode(',', $ids);
              $rmap = $conn->query("SELECT application_id, student_id FROM ojt_applications WHERE application_id IN ($in)");
              if ($rmap) { while ($rr = $rmap->fetch_assoc()) { if (!empty($rr['student_id'])) $movedStudentIds[] = (int)$rr['student_id']; } $rmap->free(); }
            }

            // ensure unique student ids
            $movedStudentIds = array_values(array_unique(array_filter($movedStudentIds)));
          } else {
            // no explicit selection: fallback to per-assignment loop (move all assignments from original session)
            foreach($assigns as $ar) {
              $appId = $ar['application_id'] ?? ($ar['applicationid'] ?? null);
              if ($appId) {
                $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM orientation_assignments WHERE session_id = ? AND (application_id = ? OR student_id = ?) LIMIT 1");
                $chk->bind_param('iii',$targetSessionId, $appId, $appId); $chk->execute(); $cres = $chk->get_result(); $crow = $cres?$cres->fetch_assoc():null; $cnt = $crow? (int)$crow['cnt'] : 0; $chk->close();
                if ($cnt === 0) {
                  if (!empty($prevDateVal)) {
                    if ($assignHasRescheduled && !empty($assignReschedCol)) {
                      $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ?, `{$assignReschedCol}` = ? WHERE session_id = ? AND (application_id = ? OR student_id = ?) LIMIT 1"); $up->bind_param('isiii', $targetSessionId, $prevDateVal, $origSessionId, $appId, $appId);
                    } else {
                      $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE session_id = ? AND (application_id = ? OR student_id = ?) LIMIT 1"); $up->bind_param('iiii', $targetSessionId, $origSessionId, $appId, $appId);
                    }
                  } else {
                    $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE session_id = ? AND (application_id = ? OR student_id = ?) LIMIT 1"); $up->bind_param('iiii', $targetSessionId, $origSessionId, $appId, $appId);
                  }
                  $up->execute(); $up->close();
                  if (!empty($ar['application_id'])) $movedApplicationIds[] = (int)$ar['application_id'];
                  if (!empty($ar['student_id'])) $movedStudentIds[] = (int)$ar['student_id'];
                } else {
                  $del = $conn->prepare("DELETE FROM orientation_assignments WHERE session_id = ? AND (application_id = ? OR student_id = ?) LIMIT 1"); $del->bind_param('iii', $origSessionId, $appId, $appId); $del->execute(); $del->close();
                }
              } else {
                $assignId = $ar['id'] ?? $ar['assignment_id'] ?? null;
                if ($assignId) {
                  if (!empty($prevDateVal)) {
                    if ($assignHasRescheduled) {
                      if (!empty($assignReschedCol)) {
                        $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ?, `{$assignReschedCol}` = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('isii',$targetSessionId,$prevDateVal,$assignId,$assignId);
                      } else {
                        $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('iii',$targetSessionId,$assignId,$assignId);
                      }
                    } else {
                      $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('iii',$targetSessionId,$assignId,$assignId);
                    }
                  } else {
                    $up = $conn->prepare("UPDATE orientation_assignments SET session_id = ? WHERE (id = ? OR assignment_id = ?) LIMIT 1"); $up->bind_param('iii',$targetSessionId,$assignId,$assignId);
                  }
                  $up->execute(); $up->close();
                  if (!empty($ar['student_id'])) $movedStudentIds[] = (int)$ar['student_id'];
                }
              }
            }
          }

          // prepare from-date early (used in notification body)
          $fromDate = $orig[$dateCol2] ?? null;

          // --- Send notification emails to moved students (best-effort) ---
          try {
            // resolve application ids to student ids when necessary using integer-safe IN lists
            if (!empty($movedApplicationIds)) {
              $ids = array_map('intval', $movedApplicationIds);
              $in = implode(',', $ids);
              $sqlA = "SELECT application_id, student_id FROM ojt_applications WHERE application_id IN ($in)";
              $resA2 = $conn->query($sqlA);
              $appToStud = [];
              if ($resA2) {
                while ($r = $resA2->fetch_assoc()) {
                  if (!empty($r['student_id'])) $appToStud[(int)$r['application_id']] = (int)$r['student_id'];
                }
                $resA2->free();
              }
              foreach ($movedApplicationIds as $aid) {
                $aidI = (int)$aid;
                if (isset($appToStud[$aidI])) $movedStudentIds[] = $appToStud[$aidI];
              }
            }

            // get unique student ids
            $movedStudentIds = array_values(array_unique(array_filter($movedStudentIds)));

            // If the original session now has no assignments, delete it
            $deletedOriginal = false;
            try {
              $remStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orientation_assignments WHERE session_id = ?");
              if ($remStmt) {
                $remStmt->bind_param('i', $origSessionId);
                $remStmt->execute();
                $remRes = $remStmt->get_result();
                $remCount = 0;
                if ($remRes && $rrem = $remRes->fetch_assoc()) { $remCount = (int)$rrem['cnt']; }
                $remStmt->close();
                if ($remCount === 0) {
                  $delSess = $conn->prepare("DELETE FROM orientation_sessions WHERE session_id = ? LIMIT 1");
                  if ($delSess) {
                    $delSess->bind_param('i', $origSessionId);
                    $delSess->execute();
                    if ($conn->affected_rows > 0) $deletedOriginal = true;
                    $delSess->close();
                  }
                }
              }
            } catch (Exception $e) {
              error_log('Error checking/deleting original session: ' . $e->getMessage());
            }
            $emails = [];
            if (!empty($movedStudentIds)) {
              $idsS = array_map('intval', $movedStudentIds);
              $inS = implode(',', $idsS);
              $sqlS = "SELECT student_id, email, first_name, last_name FROM students WHERE student_id IN ($inS)";
              $resS = $conn->query($sqlS);
              if ($resS) {
                while ($r = $resS->fetch_assoc()) {
                  if (!empty($r['email'])) $emails[] = ['id' => $r['student_id'], 'email' => $r['email'], 'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))];
                }
                $resS->free();
              }
            }

            if (!empty($emails)) {
              // include PHPMailer if available (pattern used in other pages)
              $autoload = __DIR__ . '/../vendor/autoload.php';
              if (file_exists($autoload)) require_once $autoload;
              if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                $pmPath = __DIR__ . '/../PHPMailer/src/';
                if (file_exists($pmPath . 'PHPMailer.php')) {
                  require_once $pmPath . 'PHPMailer.php';
                  require_once $pmPath . 'Exception.php';
                  require_once $pmPath . 'SMTP.php';
                }
              }

              // SMTP config (reuse local defaults from accounts page)
              $SMTP_HOST_LOCAL = 'smtp.gmail.com';
              $SMTP_PORT_LOCAL = 587;
              $SMTP_USER_LOCAL = 'sample.mail00000000@gmail.com';
              $SMTP_PASS_LOCAL = 'qitthwgfhtogjczq';
              $SMTP_FROM_EMAIL_LOCAL = 'sample.mail00000000@gmail.com';
              $SMTP_FROM_NAME_LOCAL  = 'OJTMS HR';

              foreach ($emails as $rcpt) {
                try {
                  if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) continue;
                  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                  $mail->isSMTP();
                  $mail->Host = $SMTP_HOST_LOCAL;
                  $mail->SMTPAuth = true;
                  $mail->Username = $SMTP_USER_LOCAL;
                  $mail->Password = $SMTP_PASS_LOCAL;
                  $mail->SMTPSecure = 'tls';
                  $mail->Port = $SMTP_PORT_LOCAL;
                  $mail->CharSet = 'UTF-8';
                  $mail->setFrom($SMTP_FROM_EMAIL_LOCAL, $SMTP_FROM_NAME_LOCAL);
                  $mail->addAddress($rcpt['email'], $rcpt['name']);
                  $mail->isHTML(true);
                  $subject = 'Orientation Rescheduled';
                  $newLabel = $targetDate . (!empty($useTime) ? ' ' . $useTime : '');
                  $body = '<p>Dear ' . htmlspecialchars($rcpt['name'] ? $rcpt['name'] : 'Student') . ',</p>';
                  $body .= '<p>Your orientation has been rescheduled to <strong>' . htmlspecialchars($newLabel) . '</strong>';
                  if (!empty($useLoc)) $body .= ' at <strong>' . htmlspecialchars($useLoc) . '</strong>';
                  if (!empty($fromDate)) $body .= '. (Previously scheduled on ' . htmlspecialchars(date('M j, Y', strtotime($fromDate))) . ')';
                  $body .= '.</p><p>Regards,<br/>HR Department</p>';
                  $mail->Subject = $subject;
                  $mail->Body = $body;
                  $mail->send();
                } catch (\Exception $e) {
                  error_log('Reschedule email error: ' . $e->getMessage());
                  // continue — don't let email errors break the main flow
                }
              }
            }
          } catch (\Throwable $e) {
            error_log('Reschedule notify error: ' . $e->getMessage());
            // swallow errors to keep reschedule successful
          }
          $fromLabel = $fromDate ? date('M j, Y', strtotime($fromDate)) : null;
          // successful response — discard any buffered output and return JSON
          ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>true,'message'=>'Rescheduled','target_session_id'=>$targetSessionId,'from_date'=>$fromLabel,'deleted_original'=>!empty($deletedOriginal)]); exit;
          } catch (\Throwable $e) {
            $buf = ob_get_clean();
            error_log('calendar.php reschedule exception: ' . $e->getMessage() . '\nBuffer:\n' . $buf . '\nTrace:\n' . $e->getTraceAsString());
            header('Content-Type: application/json; charset=utf-8'); http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'Internal server error','error'=>$e->getMessage(),'output'=>substr($buf,0,2000)]);
            exit;
          }
        }
      }
    // navigation helpers
    $prevMonth = (clone $first)->modify('-1 month');
    $nextMonth = (clone $first)->modify('+1 month');
    $prevParams = '?m=' . (int)$prevMonth->format('n') . '&y=' . (int)$prevMonth->format('Y');
    $nextParams = '?m=' . (int)$nextMonth->format('n') . '&y=' . (int)$nextMonth->format('Y');
  ?>
  <?php
  // Build compact per-day HTML for calendar cells so days with multiple
  // sessions show the first session and a small nav to view the next ones.
  $eventsHtml = [];
  if (!empty($eventsData) && is_array($eventsData)) {
    foreach ($eventsData as $dayIndex => $arr) {
      if (empty($arr) || !is_array($arr)) continue;
      if (count($arr) === 1) {
        $eventsHtml[$dayIndex] = isset($events[$dayIndex]) ? $events[$dayIndex] : '';
        continue;
      }
      $parts = '<div class="event-list">';
      foreach ($arr as $idx => $s) {
        $timeLabel = !empty($s['time']) ? htmlspecialchars($s['time']) : '';
        $timeHtml = $timeLabel ? '<div class="ev-time">' . $timeLabel . '</div>' : '';
        $locHtml = '<div class="ev-loc">' . htmlspecialchars($s['location'] ?? '') . '</div>';
        $countHtml = (!empty($s['count'])) ? ('<div class="ev-count">' . (($s['count']===1) ? '1 OJT' : ($s['count'] . ' OJTs')) . '</div>') : '';
        $label = $timeHtml . $locHtml . $countHtml;
        $parts .= '<div class="event-item">' . $label . '</div>';
      }
      $parts .= '</div>';
      $eventsHtml[$dayIndex] = $parts;
    }
  }
  ?>
  <style>
    *{box-sizing:border-box;font-family:Poppins,system-ui,Segoe UI,Arial,sans-serif}
    html,body{height:100%;margin:0;background:transparent;color:#111;overflow:hidden}
    .outer-panel{display:block;padding:0;margin:0;width:100%;height:100%}
    .outer-panel .panel{background:transparent;border-radius:0;padding:0;max-width:none;width:100%;box-shadow:none;height:100%;display:flex;align-items:center;justify-content:center}
    .card{background:#fff;border-radius:18px;padding:12px 12px;max-width:1180px;width:calc(100% - 64px);height:calc(100% - 64px);margin:0 auto;display:grid;grid-template-rows:auto 1fr;gap:8px;align-items:stretch;justify-content:stretch;box-shadow:0 18px 40px rgba(16,24,40,0.12);position:relative}
    .toolbar{display:grid;grid-template-rows:auto auto;row-gap:8px;align-items:center;margin-bottom:0}
    .toggles{display:flex;gap:8px}
    .toggle{padding:8px 12px;border-radius:12px;background:#f3f3ff;color:#5a3db0;font-weight:700;cursor:pointer;border:0}
    .toggles .muted{background:#fff;color:#667;border:1px solid #eee}
    .title{font-weight:800;color:#2f3850;text-align:center;width:100%}
    .content{display:grid;grid-template-columns:1fr 490px;gap:20px;align-items:stretch;height:100%}
    .calendar{padding:6px;border-radius:12px;background:transparent;display:flex;flex:1 1 auto;min-width:0}
    .inner-calendar{background:#fff;border-radius:12px;padding:12px;box-shadow:0 12px 30px rgba(16,24,40,0.08);max-width:none;width:100%;box-sizing:border-box;height:100%;display:flex;flex-direction:column}
    .monthLabel{font-weight:700;color:#2f3850;margin-bottom:8px}
    .inner-calendar{
      --upcoming-w:490px;
      --gap:0px;
      --inner-pad:12px;
      --avail-w: calc(100% - var(--upcoming-w) - var(--inner-pad));
      --avail-h: calc(100% - 120px);
      --cell-w: calc((var(--avail-w) - (6 * var(--gap))) / 7);
      --cell-h: calc((var(--avail-h) - (5 * var(--gap))) / 6);
      --cell-size: 85px;
    }
    .grid{display:grid;grid-template-columns:repeat(7,var(--cell-size));gap:0;justify-content:center;border-right:1px solid #f0f0f6;border-bottom:1px solid #f0f0f6}
    .dayHead{font-size:12px;color:#666;text-align:center;padding:6px 0;border-left:1px solid #f0f0f6;border-bottom:1px solid #f0f0f6}
    .grid.header{margin-bottom:0;grid-template-columns:repeat(7,var(--cell-size));}
    .cell{background:#fff;border-radius:0;border-left:1px solid #f0f0f6;border-top:1px solid #f0f0f6;padding:6px;position:relative;transition:all .12s ease;display:flex;flex-direction:column;box-sizing:border-box;width:var(--cell-size);height:var(--cell-size)}
    .cell .date{position:absolute;top:6px;right:8px;left:auto;font-size:11px;color:#9aa;transition:none;text-align:right;font-weight:700}
    .cell .content{margin-top:18px;flex:1;display:flex;align-items:flex-end;justify-content:flex-end;overflow:hidden;padding-right:0px;padding-left:12px}
    /* don't highlight day numbers purple for scheduled days; keep them muted gray */
    .cell.has-event .date{color:#9aa}
    /* remove hover animation on cells/dates */
    .cell.has-event:hover{box-shadow:none;transform:none}
    .cell.has-event:hover .date{color:#9aa}
    /* past events should look muted (no purple) */
    .cell.past-event .date{color:#9aa}
    .cell.past-event .event{background:#f5f5f7;color:#777}
    .cell.past-event:hover{box-shadow:none;transform:none}
    .cell.past-event:hover .date{color:#9aa}
    /* light purple container, strong purple text (single session) */
    .event{display:flex;flex-direction:column;align-items:flex-end;margin-left:auto;margin-top:6px;background:#efe6ff;color:#6a3db5;padding:6px 8px;border-radius:6px;font-size:11px;text-align:right}
    /* multi-session container should not show a full-width purple background behind the scrollbar */
    .event.multi{background:transparent;padding:0;margin-top:6px;margin-left:auto;align-items:stretch}
    .event .ev-time, .event .ev-loc, .event .ev-count{font-size:10px;color:#6a3db5;text-align:right}
    .event .ev-count{font-weight:600}
    /* stacked event list for days with multiple sessions */
    .event-list{width:100%;max-height:calc(var(--cell-size) - 30px);overflow-y:auto;padding-right:0;display:flex;flex-direction:column;align-items:stretch}
    .event-item{background:#efe6ff;color:#6a3db5;padding:6px 8px;border-radius:6px;margin:6px 0;font-size:11px;text-align:right;display:block}
    .event-item:first-child{margin-top:0}
    .event-item:last-child{margin-bottom:0}
    /* scrollbar padding/appearance tightened so text isn't too far from scrollbar */
    .event-list{width:100%;max-height:calc(var(--cell-size) - 30px);overflow-y:auto;padding-right:0;display:flex;flex-direction:column;align-items:stretch}
    /* slightly smaller gaps for compactness */
    .event-item{background:#efe6ff;color:#6a3db5;padding:6px 6px;border-radius:6px;margin:4px 0;font-size:11px;text-align:right;display:block}
    /* nicer thin scrollbar for WebKit */
    .event-list::-webkit-scrollbar{width:6px}
    .event-list::-webkit-scrollbar-thumb{background:rgba(106,61,181,0.22);border-radius:6px}
    .controls{text-align:center;width:100%}
    .upcoming{background:#fff;border-radius:12px;padding:16px;border:1px solid #f3f0fb;height:100%;overflow:hidden}
    .upcoming h4{margin:0 0 10px 0;color:#3a2b6a}
    .upcoming .item{display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px dashed #f2eef9}
    .upcoming .item:last-child{border-bottom:none}
    /* Orientation session layout: icon left, text right */
    #orientationContent .session{padding:8px 0;border-bottom:1px dashed #f2eef9}
    #orientationContent .session:last-child{border-bottom:none}
    #orientationContent .row{display:flex;align-items:center;gap:12px;padding:6px 0}
    /* icon: light purple circular background with purple outline-only SVG */
    #orientationContent .icon{width:36px;height:36px;border-radius:50%;background:#efe6ff;display:flex;align-items:center;justify-content:center;flex:0 0 36px;border:1px solid #6a3db5}
    #orientationContent .icon svg{width:18px;height:18px;fill:none;stroke:#6a3db5;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
    #orientationContent .text{font-size:14px;color:#111}
    /* date (small) should be black */
    #orientationContent .text.small{font-size:13px;color:#111}
    /* students list */
    #orientationContent .students{margin-top:6px}
    #orientationContent .student-row{display:flex;align-items:center;justify-content:flex-start;padding:8px 0;border-bottom:1px dashed #f2eef9}
    #orientationContent .student-row .student-name{color:#111;font-size:14px;flex:1;text-align:left;margin-left:6px}
    #orientationContent .print-icon{width:36px;height:36px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #6a3db5;background:transparent;color:#6a3db5;cursor:pointer}
    #orientationContent .print-icon svg{width:16px;height:16px;fill:none;stroke:#6a3db5;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
    #orientationContent .resched-icon{width:36px;height:36px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #6a3db5;background:transparent;color:#6a3db5;cursor:pointer}
    #orientationContent .resched-icon svg{width:16px;height:16px;fill:none;stroke:#6a3db5;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
    #orientationContent .student-actions{display:inline-flex;gap:8px;align-items:center}
    #orientationContent .print-all{display:block;margin-top:8px;padding:8px 12px;border-radius:8px;border:1px solid #6a3db5;background:transparent;color:#6a3db5;text-align:center;text-decoration:none;width:100%}
    #orientationContent .print-all.disabled{pointer-events:none;opacity:0.6}
    /* disable style for reschedule button when no students selected */
    .resched.disabled{pointer-events:none;opacity:0.6}
    .dot{width:12px;height:12px;border-radius:50%;background:#efe6ff;flex:0 0 12px;margin-top:4px}
    .dot.purple{background:#efe6ff}
    /* today's day number: filled purple circle */
    .cell.today .date{background:#6a3db5;color:#fff;width:22px;height:22px;line-height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;text-align:center;padding:0}
    /* selected day: purple outline circle */
    .cell.selected .date{background:transparent;color:#6a3db5;border:2px solid #6a3db5;width:22px;height:22px;line-height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;padding:0}
    .upcoming .meta{color:#6d6d6d;font-size:13px}
    .resched{display:block;margin-top:12px;padding:8px 12px;border-radius:8px;background:#6a3db5;color:#fff;text-align:center;text-decoration:none;width:100%}
    .resched-badge{display:inline-block;margin-top:8px;padding:4px 8px;background:#eef6ff;color:#0b3b8a;border-radius:8px;font-size:12px}
    @media (max-width:1200px){ .outer-panel .panel{width:100%;padding:18px} }
    @media (max-width:980px){ .content{grid-template-columns:1fr 260px} }
    @media (max-width:820px){
      .inner-calendar{--upcoming-w:200px;--inner-pad:40px}
      .content{grid-template-columns:1fr 200px}
    }
    @media (max-width:640px){
      .content{grid-template-columns:1fr}
      .inner-calendar{--upcoming-w:0;--inner-pad:32px}
      .grid{grid-template-columns:repeat(7, calc((100% - (6*var(--gap))) / 7));justify-content:stretch}
      .cell{width:auto;height:auto;aspect-ratio:1/1}
    }
    .outer-panel, .panel, .card { overflow: visible; }
    .inner-calendar { overflow: visible; }
    body { background: transparent; }
    .grid[aria-hidden="false"]{display:grid;grid-template-rows:repeat(6,var(--cell-size));align-content:start}
    /* close button inside the white card */
    .view-close { position: absolute; right: 18px; top: 18px; width:36px;height:36px;border-radius:50%;background:#fff;border:0;box-shadow:0 6px 18px rgba(16,24,40,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px; z-index:10010; }
  </style>
  <!-- flatpickr (lightweight datepicker) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
  
  <div class="outer-panel">
    <div class="panel">
      <div class="card" role="region" aria-label="Calendar">
        <button class="view-close" aria-label="Close calendar" type="button" onclick="(parent && parent.closeCalendarOverlay) ? parent.closeCalendarOverlay() : window.close()">✕</button>
      <div class="toolbar">
        <!-- toolbar top left intentionally kept minimal; toggles placed in calendar header -->
            <!-- month label & nav moved inside the white calendar header (see below) -->

          <div class="content">
          <div class="calendar">
            <div class="inner-calendar">
            <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:8px">
              <a href="<?php echo $prevParams; ?>" class="toggle muted" style="background:#fff;border:1px solid #eee;padding:6px 10px;border-radius:8px;text-decoration:none;color:#2f3850" aria-label="Previous month">&laquo;</a>
              <div class="title" style="font-weight:700;color:#2f3850;text-align:center"><?php echo htmlspecialchars($monthLabel); ?></div>
              <a href="<?php echo $nextParams; ?>" class="toggle muted" style="background:#fff;border:1px solid #eee;padding:6px 10px;border-radius:8px;text-decoration:none;color:#2f3850" aria-label="Next month">&raquo;</a>
            </div>

            <div class="grid header">
              <div class="dayHead">Sun</div>
              <div class="dayHead">Mon</div>
              <div class="dayHead">Tue</div>
              <div class="dayHead">Wed</div>
              <div class="dayHead">Thu</div>
              <div class="dayHead">Fri</div>
              <div class="dayHead">Sat</div>
            </div>

            <div class="grid" aria-hidden="false">
              <?php
                // render 6 weeks (6*7 = 42 cells)
                $cell = 0;
                // current server date parts for marking today
                $serverY = (int)date('Y');
                $serverM = (int)date('n');
                $serverD = (int)date('j');
                // leading prev-month days
                $lead = $startWeekday; // number of empty cells before 1st
                for ($i = 0; $i < 42; $i++, $cell++) {
                  // reset dataAttr each iteration so it doesn't leak between cells
                  $dataAttr = '';
                  if ($i < $lead) {
                    // previous month day
                    $num = $daysInPrev - ($lead - 1 - $i);
                    $class = 'other-month';
                    $label = $num;
                    $eventHtml = '';
                  } elseif ($i < $lead + $daysInMonth) {
                    // current month day
                    $d = $i - $lead + 1;
                    $num = $d;
                    $hasEvent = isset($events[$d]);
                    $label = $d;
                    // determine if this cell's date is in the past (server timezone)
                    $cellIso = $first->format('Y-m-') . sprintf('%02d', $d);
                    $isPast = strtotime($cellIso) < strtotime(date('Y-m-d'));
                    $classes = [];
                    if ($hasEvent) $classes[] = 'has-event';
                    if ($hasEvent && $isPast) $classes[] = 'past-event';
                    // mark today (server local date) when calendar is showing current month
                    if ($serverY === $y && $serverM === $m && $d === $serverD) {
                      $classes[] = 'today';
                    }
                    $class = implode(' ', $classes);
                    $eventHtml = '';
                    if ($hasEvent) {
                      $inner = isset($eventsHtml[$d]) ? $eventsHtml[$d] : (isset($events[$d]) ? $events[$d] : '');
                      $isMulti = isset($eventsData[$d]) && count($eventsData[$d]) > 1;
                      $cls = $isMulti ? 'event multi' : 'event';
                      $eventHtml = '<div class="' . $cls . '">' . $inner . '</div>';
                    }
                    // include robust inline handlers so clicks always work even if delegation fails
                    $dataAttr = ' data-day="' . $d . '" onclick="(function(el){try{var d=el.getAttribute(\'data-day\');var prev=document.querySelector(\'.cell.selected\');if(prev)prev.classList.remove(\'selected\');if(d){el.classList.add(\'selected\');if(window.renderOrientation)window.renderOrientation(d);} }catch(e){} })(this)" onkeydown="if(event.key==\'Enter\' || event.key==\' \'){ (function(el){try{var d=el.getAttribute(\'data-day\');var prev=document.querySelector(\'.cell.selected\');if(prev)prev.classList.remove(\'selected\');if(d){el.classList.add(\'selected\');if(window.renderOrientation)window.renderOrientation(d);} }catch(e){} })(this); event.preventDefault(); }"';
                  } else {
                    // next month filler
                    $num = $i - ($lead + $daysInMonth) + 1;
                    $class = 'other-month';
                    $label = $num;
                    $eventHtml = '';
                  }
                  ?>
                  <div class="cell <?php echo $class; ?>"<?php echo ($dataAttr ?? ''); ?> role="button" tabindex="0">
                    <div class="date"><?php echo $label; ?></div>
                    <div class="content"><?php echo $eventHtml; ?></div>
                  </div>
              <?php } ?>
            </div>
          </div>
        </div>

        <aside id="orientationPanel" class="upcoming" aria-label="Orientation">
          <h4>Orientation</h4>
          <div id="orientationContent">
            <div class="meta">Click a day to see orientation details.</div>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <script>
    // expose events data from PHP
    var eventsData = <?php echo json_encode($eventsData ?? []); ?>;

    function renderOrientation(day){
      var container = document.getElementById('orientationContent');
      container.innerHTML = '';
      if (!day || !eventsData[day]){
        container.innerHTML = '<div class="meta">No orientation scheduled.</div>';
        return;
      }
      var sessions = eventsData[day];
      // render sessions with left icons and rows
      sessions.forEach(function(s){
        var sess = document.createElement('div'); sess.className = 'session';
        sess.dataset.sessionId = s.session_id || '';

        // time row
        var row1 = document.createElement('div'); row1.className = 'row';
        var icon1 = document.createElement('div'); icon1.className = 'icon';
        icon1.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1a11 11 0 1 0 11 11A11.012 11.012 0 0 0 12 1zm1 12.59V7h-2v6.59l5 3 1-1.66z"></path></svg>';
        var txt1 = document.createElement('div'); txt1.className='text'; txt1.textContent = s.time || '';
        row1.appendChild(icon1); row1.appendChild(txt1);
        sess.appendChild(row1);

        // date row
        var row2 = document.createElement('div'); row2.className = 'row';
        var icon2 = document.createElement('div'); icon2.className = 'icon';
        icon2.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM5 20V9h14l.002 11H5z"></path></svg>';
        var txt2 = document.createElement('div'); txt2.className='text small'; txt2.textContent = (new Date(s.date)).toLocaleString(undefined, {weekday:'short', year:'numeric', month:'long', day:'numeric'});
        row2.appendChild(icon2); row2.appendChild(txt2);
        sess.appendChild(row2);

        // location row
        var row3 = document.createElement('div'); row3.className = 'row';
        var icon3 = document.createElement('div'); icon3.className = 'icon';
        icon3.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"></path></svg>';
        var txt3 = document.createElement('div'); txt3.className='text'; txt3.textContent = s.location || '';
        row3.appendChild(icon3); row3.appendChild(txt3);
        sess.appendChild(row3);

        // students list with label and print actions
        var studentsWrap = document.createElement('div'); studentsWrap.className = 'students';
        if (s.students && s.students.length > 0) {
          // header: Students label + Select All checkbox
          var header = document.createElement('div'); header.style.display = 'flex'; header.style.justifyContent = 'space-between'; header.style.alignItems = 'center';
          var label = document.createElement('div'); label.className = 'students-label'; label.textContent = 'Students';
          header.appendChild(label);
          var saWrap = document.createElement('div'); saWrap.style.display='flex'; saWrap.style.alignItems='center';
          var sa = document.createElement('input'); sa.type = 'checkbox'; sa.className = 'select-all'; sa.id = 'select_all_' + (s.session_id || Math.random().toString(36).slice(2,7));
          var saLabel = document.createElement('label'); saLabel.setAttribute('for', sa.id); saLabel.style.marginLeft='6px'; saLabel.style.fontSize='12px'; saLabel.style.color='#444'; saLabel.textContent = 'Select all';
          saLabel.style.cursor = 'pointer';
          saWrap.appendChild(sa); saWrap.appendChild(saLabel);
          header.appendChild(saWrap);
          studentsWrap.appendChild(header);

          s.students.forEach(function(st){
            var r = document.createElement('div'); r.className = 'student-row';
            // checkbox for selecting which students to reschedule
            var cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'resched-checkbox';
            cb.style.marginRight = '8px';
            if (st.application_id) cb.dataset.applicationId = st.application_id;
            if (st.student_id) cb.dataset.studentId = st.student_id;
            // name
            var name = document.createElement('div'); name.className = 'student-name'; name.textContent = st.name || '';
            // assemble row: checkbox + name
            r.appendChild(name);
            // prepend checkbox before name for easy selection
            r.insertBefore(cb, r.firstChild);
            // per-student persistent rescheduled badge (if this assignment was rescheduled)
            try {
              if (st.rescheduled_from) {
                var sd = new Date(st.rescheduled_from);
                if (!isNaN(sd)) {
                  var sfl = sd.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
                  var sb = document.createElement('span'); sb.className = 'resched-badge'; sb.textContent = 'Rescheduled from ' + sfl; sb.style.marginLeft = '8px';
                  r.appendChild(sb);
                }
              }
            } catch(e){}
            studentsWrap.appendChild(r);
          });
          // select-all behavior
          sa.addEventListener('change', function(){
            var sessParent = this.closest('.students');
            if (!sessParent) return;
            var checks = sessParent.querySelectorAll('.resched-checkbox');
            // set checked state for all checkboxes
            checks.forEach(function(ch){ ch.checked = sa.checked; });
            // trigger a single change event to let delegated handlers update UI once
            if (checks.length > 0) checks[0].dispatchEvent(new Event('change', { bubbles: true }));
          });
          // Print button (prints selected students) — use anchor to preserve original layout
          var printAll = document.createElement('a'); printAll.className = 'print-all'; printAll.href = '#'; printAll.textContent = 'Print Endorsement Letter';
          // start disabled until at least one student is selected (use disabled class)
          printAll.classList.add('disabled'); printAll.setAttribute('aria-disabled','true');
          // attach session id for Print All
          printAll.dataset.sessionId = s.session_id || '';
          studentsWrap.appendChild(printAll);

          // enable/disable print button based on checkbox selections (toggle class)
          function updatePrintButtonState() {
            try {
              var anyChecked = Array.from(studentsWrap.querySelectorAll('.resched-checkbox')).some(function(ch){ return ch.checked; });
              if (anyChecked) { printAll.classList.remove('disabled'); printAll.removeAttribute('aria-disabled'); }
              else { printAll.classList.add('disabled'); printAll.setAttribute('aria-disabled','true'); }
            } catch(e){}
          }
          // wire checkbox change events to update button state
          var allChecks = studentsWrap.querySelectorAll('.resched-checkbox');
          allChecks.forEach(function(ch){ ch.addEventListener('change', updatePrintButtonState); });
        } else {
          var none = document.createElement('div'); none.className = 'meta'; none.textContent = 'No students assigned.';
          studentsWrap.appendChild(none);
        }
        sess.appendChild(studentsWrap);

        // show persistent rescheduled badge when present on the session
        try {
          if (s.rescheduled_from) {
            var fd = new Date(s.rescheduled_from);
            if (!isNaN(fd)) {
              var fl = fd.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
              var badge = document.createElement('span'); badge.className = 'resched-badge'; badge.textContent = 'Rescheduled from ' + fl;
              sess.appendChild(badge);
            }
          }
        } catch(e) { /* ignore */ }

        // session-level reschedule button (Reschedule selected students)
        var sessionBtn = document.createElement('a'); sessionBtn.className = 'resched'; sessionBtn.href = '#'; sessionBtn.textContent = 'Reschedule';
        // start disabled until at least one student is selected (match print-all behavior)
        sessionBtn.classList.add('disabled'); sessionBtn.setAttribute('aria-disabled','true');
        sessionBtn.style.marginTop = '10px';
        sessionBtn.dataset.sessionId = s.session_id || '';
        sessionBtn.addEventListener('click', function(ev){
          // don't act when button is disabled
          if (this.classList && this.classList.contains('disabled')) return;
          ev.preventDefault();
          // collect checked students inside this session element
          var sessEl = this.closest('.session');
          var checks = sessEl ? sessEl.querySelectorAll('.resched-checkbox:checked') : [];
          if (!checks || checks.length === 0) { alert('Please select one or more students to reschedule.'); return; }
          // build selected list and store globally for modal
          var sel = [];
          checks.forEach(function(c){ sel.push({ application_id: c.dataset.applicationId || null, student_id: c.dataset.studentId || null }); });
          // attach to currentReschedSelected
          currentReschedSelected = sel;
          openRescheduleModalForSession(this.dataset.sessionId);
        });
        sess.appendChild(sessionBtn);

        container.appendChild(sess);
      });
    }

    // small keyboard-friendly focus for cells and click handling to populate Orientation panel
    // handle selection outline + rendering using event delegation so clicks work
    // even if calendar DOM is re-rendered
    function clearSelected(){
      var prev = document.querySelector('.cell.selected');
      if (prev) prev.classList.remove('selected');
    }

    // delegated click handler: activate day when a .cell is clicked
    document.addEventListener('click', function(e){
      var cell = e.target.closest && e.target.closest('.cell');
      if (!cell) return;
      var d = cell.dataset.day;
      clearSelected();
      if (d) cell.classList.add('selected');
      renderOrientation(d);
    }, true);

    // keyboard support: when a focused .cell receives Enter/Space, open it
    document.addEventListener('keydown', function(e){
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var el = document.activeElement;
      if (!el || !(el.classList && el.classList.contains('cell'))) return;
      e.preventDefault();
      var d = el.dataset.day;
      clearSelected();
      if (d) el.classList.add('selected');
      renderOrientation(d);
    });

    // fallback handler used by inline cell attributes
    function handleDayClick(el){
      try {
        var d = el && el.dataset ? el.dataset.day : null;
        clearSelected();
        if (d) el.classList.add('selected');
        renderOrientation(d);
      } catch (e) { console.error('handleDayClick error', e); }
    }

    // Delegated selection handlers for students and select-all
    (function(){
      var oc = document.getElementById('orientationContent');
      if (!oc) return;

      function updateButtonsForWrap(studentsWrap){
        if (!studentsWrap) return;
        var checks = Array.from(studentsWrap.querySelectorAll('.resched-checkbox'));
        var checkedCount = checks.filter(function(ch){ return ch.checked; }).length;
        var printAllBtn = studentsWrap.querySelector('.print-all');
        if (printAllBtn) {
          if (checkedCount > 0) { printAllBtn.classList.remove('disabled'); printAllBtn.removeAttribute('aria-disabled'); }
          else { printAllBtn.classList.add('disabled'); printAllBtn.setAttribute('aria-disabled','true'); }
        }
        var sessEl = studentsWrap.closest && studentsWrap.closest('.session');
        var reschedBtn = sessEl ? sessEl.querySelector('.resched') : null;
        if (reschedBtn) {
          if (checkedCount > 0) { reschedBtn.classList.remove('disabled'); reschedBtn.removeAttribute('aria-disabled'); }
          else { reschedBtn.classList.add('disabled'); reschedBtn.setAttribute('aria-disabled','true'); }
        }
        // update select-all checkbox state
        var sa = studentsWrap.querySelector('.select-all');
        if (sa) {
          if (checks.length === 0) { sa.checked = false; sa.indeterminate = false; }
          else if (checkedCount === 0) { sa.checked = false; sa.indeterminate = false; }
          else if (checkedCount === checks.length) { sa.checked = true; sa.indeterminate = false; }
          else { sa.checked = false; sa.indeterminate = true; }
        }
        // reflect row selected class
        checks.forEach(function(ch){ var row = ch.closest && ch.closest('.student-row'); if (row) { if (ch.checked) row.classList.add('selected'); else row.classList.remove('selected'); } });
      }

      oc.addEventListener('click', function(e){
        var t = e.target;
        // select-all clicked (support clicks on the checkbox OR its label)
        var sa = (t.closest && t.closest('.select-all')) || null;
        if (!sa && t && t.tagName === 'LABEL' && t.getAttribute('for')) {
          var ref = document.getElementById(t.getAttribute('for'));
          if (ref && ref.classList && ref.classList.contains('select-all')) sa = ref;
        }
        if (sa) {
          var wrap = sa.closest && sa.closest('.students');
          if (!wrap) return;
          var checks = wrap.querySelectorAll('.resched-checkbox');
          checks.forEach(function(ch){ ch.checked = sa.checked; });
          if (checks.length > 0) checks[0].dispatchEvent(new Event('change',{bubbles:true}));
          return;
        }
        // row click toggles checkbox (ignore clicks on inputs/links)
        var row = t.closest && t.closest('.student-row');
        if (row && !t.matches('input, a, button, label')) {
          var cb = row.querySelector('.resched-checkbox');
          if (cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change',{bubbles:true})); updateButtonsForWrap(row.closest('.students')); }
          return;
        }
      }, true);

      // listen for checkbox changes to update buttons
      oc.addEventListener('change', function(e){
        var target = e.target;
        if (target && target.classList && target.classList.contains('resched-checkbox')){
          updateButtonsForWrap(target.closest('.students'));
        }
      });
    })();

    // try to show today's sessions when loading if in current month
    (function(){
      var today = new Date();
      var curMonth = <?php echo (int)$m; ?>;
      var curYear = <?php echo (int)$y; ?>;
      if (today.getMonth()+1 === curMonth && today.getFullYear() === curYear){
        renderOrientation(today.getDate());
      }
    })();

    // delegate print button clicks (single and batch)
    document.addEventListener('click', function(e){
      var p = e.target.closest && e.target.closest('.print-icon');
      if (p) {
        var appId = p.dataset.applicationId || '';
        var studId = p.dataset.studentId || '';
        var url = 'print_endorsement.php?';
        if (appId) url += 'application_id=' + encodeURIComponent(appId);
        else if (studId) url += 'student_id=' + encodeURIComponent(studId);
        else { alert('No application or student id available'); return; }
        // open via temporary anchor click to improve multi-tab reliability
        try {
          var a = document.createElement('a'); a.href = url; a.target = '_blank'; a.rel = 'noopener'; document.body.appendChild(a); a.click(); a.remove();
        } catch(e) { window.open(url, '_blank'); }
        return;
      }
      var r = e.target.closest && e.target.closest('.resched-icon');
      if (r) {
        var appIdR = r.dataset.applicationId || '';
        var studIdR = r.dataset.studentId || '';
        var urlR = 'reschedule.php?';
        if (appIdR) urlR += 'application_id=' + encodeURIComponent(appIdR);
        else if (studIdR) urlR += 'student_id=' + encodeURIComponent(studIdR);
        else { alert('No application or student id available'); return; }
        window.open(urlR, '_blank');
        return;
      }
      var pa = e.target.closest && e.target.closest('.print-all');
      if (pa) {
        e.preventDefault();
        // print only selected students in this session — combine into one helper tab
        var sessEl = pa.closest('.session');
        if (!sessEl) { alert('Session element not found'); return; }
        var checks = Array.from(sessEl.querySelectorAll('.resched-checkbox:checked'));
        if (!checks || checks.length === 0) { alert('Please select one or more students to print.'); return; }

        var urls = checks.map(function(c){
          var appId = c.dataset.applicationId || '';
          var studId = c.dataset.studentId || '';
          var url = 'print_endorsement.php?';
          if (appId) url += 'application_id=' + encodeURIComponent(appId);
          else if (studId) url += 'student_id=' + encodeURIComponent(studId);
          else return null;
          return url;
        }).filter(Boolean);
        if (urls.length === 0) return;

        // open helper window (user gesture) — fallback to opening separate tabs if blocked
        var helperWin = null;
        try { helperWin = window.open('', '_blank'); } catch (err) { helperWin = null; }
        if (!helperWin) {
          urls.forEach(function(u){ try { var a = document.createElement('a'); a.href = u; a.target = '_blank'; a.rel = 'noopener'; document.body.appendChild(a); a.click(); a.remove(); } catch(e){ window.open(u,'_blank'); } });
          return;
        }

        // Prepare a basic helper window document, then fetch pages from the opener
        helperWin.document.open();
        helperWin.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Print Queue</title>' +
          '<style>body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:12px} .page{page-break-after:always;margin:0;padding:0}</style></head><body>' +
          '<div id="pages"></div></body></html>');
        helperWin.document.close();

        // Fetch all endorsement pages in parallel, append them, then print once ready.
        (function(){
          try {
            var container = helperWin.document.getElementById('pages');
            var fetches = urls.map(function(u){
              return fetch(u, { credentials: 'same-origin' }).then(function(r){
                if (!r.ok) throw new Error('Fetch failed: ' + u);
                return r.text();
              }).catch(function(){
                return '<div class="page">Failed to load: ' + u + '</div>';
              });
            });

            Promise.all(fetches).then(function(results){
              try {
                results.forEach(function(txt){
                  var parsed = new DOMParser().parseFromString(txt, 'text/html');
                  var bodyHtml = (parsed && parsed.body) ? parsed.body.innerHTML : txt;
                  var wrap = helperWin.document.createElement('div');
                  wrap.className = 'page';
                  wrap.innerHTML = bodyHtml;
                  container.appendChild(wrap);
                });
              } catch (e) { console.error('Error appending pages', e); }
              // short delay to let the helper window render, then print
              setTimeout(function(){ try { helperWin.focus(); helperWin.print(); } catch(e){ console.error(e); } }, 80);
            }).catch(function(e){
              console.error('Some fetches failed', e);
              // still attempt to print whatever loaded
              setTimeout(function(){ try { helperWin.focus(); helperWin.print(); } catch(e){} }, 400);
            });
          } catch (e) { console.error('Error building print helper', e); }
        })();
        return;
      }
      var ra = e.target.closest && e.target.closest('.resched');
      if (ra) {
        // ignore clicks on disabled resched buttons
        if (ra.classList && ra.classList.contains('disabled')) return;
        if (ra.getAttribute && ra.getAttribute('aria-disabled') === 'true') return;
        e.preventDefault();
        var sid2 = ra.dataset.sessionId || '';
        if (!sid2) { alert('Session id missing'); return; }
        // open inline reschedule modal
        openRescheduleModalForSession(sid2);
        return;
      }
    });
  </script>
  <!-- Reschedule modal -->
  <div id="reschedModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;z-index:10020;background:rgba(0,0,0,0.35);">
    <div style="background:#fff;border-radius:10px;padding:16px;width:420px;max-width:calc(100% - 32px);box-shadow:0 12px 40px rgba(0,0,0,0.2);">
      <h3 style="margin:0 0 8px 0;color:#2f3850">Reschedule</h3>
      <div style="font-size:13px;color:#444;margin-bottom:8px">Choose new date for the selected students in this session.</div>
      <div id="resched_selected" style="margin-bottom:8px;font-size:13px;color:#333;max-height:120px;overflow:auto;border:1px dashed #eee;padding:8px;border-radius:6px;display:none"></div>
      <div style="margin-bottom:8px">
        <label style="font-weight:600;display:block;margin-bottom:4px">Date</label>
        <input id="resched_date" type="text" placeholder="YYYY-MM-DD" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc" />
        <div id="resched_counts" style="font-size:13px;color:#666;margin-top:6px;display:none"></div>
      </div>
      <div style="margin-bottom:8px">
        <label style="font-weight:600;display:block;margin-bottom:4px">Time</label>
        <input id="resched_time" type="time" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc" />
      </div>
      <div style="margin-bottom:8px">
        <label style="font-weight:600;display:block;margin-bottom:4px">Location</label>
        <input id="resched_location" type="text" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc" />
      </div>
      <div id="resched_msg" style="display:none;margin-bottom:8px;padding:8px;border-radius:6px"></div>
      <div id="resched_controls" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;"></div>
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <button type="button" id="resched_cancel" style="padding:8px 12px;border-radius:6px;border:1px solid #ddd;background:#fff">Cancel</button>
        <button type="button" id="resched_confirm" style="padding:8px 12px;border-radius:6px;background:#6a3db5;color:#fff;border:0">Confirm</button>
      </div>
    </div>
  </div>

  <script>
    // helper: find session object by session_id in eventsData
    function findSessionById(id) {
      if (!id) return null;
      for (var day in eventsData) {
        if (!eventsData.hasOwnProperty(day)) continue;
        var arr = eventsData[day];
        for (var i = 0; i < arr.length; i++) {
          if (String(arr[i].session_id) === String(id)) return { session: arr[i], day: day };
        }
      }
      return null;
    }

    var currentReschedSession = null;
    var currentReschedSelected = null; // array of selected {application_id, student_id}
    function openRescheduleModalForSession(sessionId) {
      currentReschedSession = sessionId;
      var found = findSessionById(sessionId);
      var modal = document.getElementById('reschedModal');
      var dateIn = document.getElementById('resched_date');
      var timeIn = document.getElementById('resched_time');
      var locIn = document.getElementById('resched_location');
      var selContainer = document.getElementById('resched_selected');
      var msg = document.getElementById('resched_msg');
      msg.style.display = 'none'; msg.textContent = '';
      if (found && found.session) {
        // default date: session.date (may be datetime)
        var dt = new Date(found.session.date);
        var yyyy = dt.getFullYear();
        var mm = String(dt.getMonth()+1).padStart(2,'0');
        var dd = String(dt.getDate()).padStart(2,'0');
        dateIn.value = yyyy + '-' + mm + '-' + dd;
        // default time if available
        if (found.session.time) {
          // parse time like "8:30 AM" -> 08:30
          var t = found.session.time;
          var dtmp = new Date('1970-01-01 ' + t);
          if (!isNaN(dtmp)) {
            var th = String(dtmp.getHours()).padStart(2,'0');
            var tm = String(dtmp.getMinutes()).padStart(2,'0');
            timeIn.value = th + ':' + tm;
          } else {
            timeIn.value = '';
          }
        } else {
          timeIn.value = '';
        }
        locIn.value = found.session.location || '';
      } else {
        // fallback: set date to today
        var d = new Date();
        dateIn.value = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        timeIn.value = '';
        locIn.value = '';
      }
      // if no selected list provided, try to collect checked boxes from the session DOM
      try {
        if (!currentReschedSelected || !Array.isArray(currentReschedSelected) || currentReschedSelected.length === 0) {
          var sessEl = document.querySelector('[data-session-id="' + sessionId + '"]');
          var checks = sessEl ? Array.from(sessEl.querySelectorAll('.resched-checkbox:checked')) : [];
          var sel = [];
          checks.forEach(function(c){ sel.push({ application_id: c.dataset.applicationId || null, student_id: c.dataset.studentId || null }); });
          if (sel.length > 0) currentReschedSelected = sel;
        }
      } catch (e) { console.error(e); }

      // populate selected students list in the modal
      try {
        if (selContainer) {
          selContainer.innerHTML = '';
          if (currentReschedSelected && Array.isArray(currentReschedSelected) && currentReschedSelected.length > 0) {
            var list = document.createElement('div');
            list.style.display = 'flex'; list.style.flexDirection = 'column'; list.style.gap = '6px';
            currentReschedSelected.forEach(function(it){
              var name = '(unknown)';
              if (found && found.session && Array.isArray(found.session.students)) {
                for (var i=0;i<found.session.students.length;i++){
                  var ss = found.session.students[i];
                  if ((it.application_id && String(ss.application_id) === String(it.application_id)) || (it.student_id && String(ss.student_id) === String(it.student_id))) { name = ss.name || name; break; }
                }
              }
              var row = document.createElement('div'); row.style.display='flex'; row.style.justifyContent='space-between'; row.style.alignItems='center';
              var left = document.createElement('div'); left.textContent = name; left.style.flex='1';
              var right = document.createElement('div'); right.style.fontSize='12px'; right.style.color='#666';
              right.textContent = '';
              row.appendChild(left); row.appendChild(right);
              list.appendChild(row);
            });
            selContainer.appendChild(list);
            selContainer.style.display = 'block';
          } else {
            selContainer.style.display = 'none';
          }
        }
      } catch (e) { console.error('populate selected failed', e); }
      // disable today/past and initialize a datepicker with counts
      try {
        // compute forbidden original session date (ISO) when available
        var forbiddenIso = null;
        try {
          if (found && found.session && found.session.date) {
            var od = new Date(found.session.date);
            if (!isNaN(od)) {
              var oy = od.getFullYear();
              var om = String(od.getMonth()+1).padStart(2,'0');
              var oday = String(od.getDate()).padStart(2,'0');
              forbiddenIso = oy + '-' + om + '-' + oday;
              dateIn.dataset.forbiddenDate = forbiddenIso;
            }
          }
        } catch(e) { /* ignore */ }

        // compute min (tomorrow) but skip Saturday/Sunday so min never falls on weekend
        var minD = new Date();
        minD.setDate(minD.getDate() + 1);
        while (minD.getDay() === 0 || minD.getDay() === 6) { minD.setDate(minD.getDate() + 1); }
        var minY = minD.getFullYear();
        var minM = String(minD.getMonth()+1).padStart(2,'0');
        var minDay = String(minD.getDate()).padStart(2,'0');
        var minIso = minY + '-' + minM + '-' + minDay;
        dateIn.min = minIso;
        if (dateIn.value < minIso) dateIn.value = minIso;
        if (forbiddenIso && dateIn.value === forbiddenIso) dateIn.value = minIso;

        // helper: compute next non-weekend (Mon-Fri) ISO date starting at iso
        function nextNonWeekendIso(iso){ try { var d = new Date(iso); if (isNaN(d)) return minIso; while (d.getDay() === 0 || d.getDay() === 6) d.setDate(d.getDate() + 1); var y = d.getFullYear(); var m = String(d.getMonth()+1).padStart(2,'0'); var dd = String(d.getDate()).padStart(2,'0'); return y + '-' + m + '-' + dd; } catch(e){ return minIso; } }

        // helper: format a Date to local YYYY-MM-DD (avoid timezone shifts from toISOString)
        function toLocalIso(d){ try { if (!d || !(d instanceof Date)) return ''; var y = d.getFullYear(); var m = String(d.getMonth()+1).padStart(2,'0'); var dd = String(d.getDate()).padStart(2,'0'); return y + '-' + m + '-' + dd; } catch(e){ return ''; } }
        // function: count OJTs scheduled on an ISO date and show message
        function updateReschedCounts(iso){
          try {
            var el = document.getElementById('resched_counts');
            if (!el) return;
            if (!iso) { el.style.display = 'none'; el.textContent = ''; return; }
            var total = 0;
            // prefer server-provided counts when available
            try {
              if (window.reschedCounts && typeof window.reschedCounts === 'object') {
                total = parseInt(window.reschedCounts[iso] || 0, 10) || 0;
              } else {
                for (var dd in eventsData) {
                  if (!eventsData.hasOwnProperty(dd)) continue;
                  var arr = eventsData[dd];
                  for (var i=0;i<arr.length;i++){ try { var s = arr[i]; if (!s || !s.date) continue; var dstr = toLocalIso(new Date(s.date)); if (dstr === iso) total += (s.count || 0); } catch(e){} }
                }
              }
            } catch(e) { console.warn('reschedCounts parse error', e); }
            if (total > 0) {
              el.style.display = 'block';
              el.textContent = total + (total === 1 ? ' OJT already scheduled on this date.' : ' OJTs already scheduled on this date.');
            } else {
              // hide the helper when there are no scheduled OJTs; day counts are shown inline in the datepicker
              el.style.display = 'none';
              el.textContent = '';
            }
          } catch(e){ console.warn('updateReschedCounts failed', e); }
        }

        // fetch counts for a date range from server and cache in window.reschedCounts
        function fetchReschedCountsForRange(startIso, endIso){
          try {
            if (!startIso || !endIso) return Promise.resolve(null);
            return fetch('calendar.php', {
              method: 'POST', headers: {'Content-Type':'application/json'},
              body: JSON.stringify({ action: 'reschedule_counts', start_date: startIso, end_date: endIso })
            }).then(function(r){ return r.text().then(function(t){ try { return JSON.parse(t); } catch(e){ return { success: r.ok, message: t }; } }); })
            .then(function(res){ console.log('resched_counts response', res); if (res && res.success && res.counts) { window.reschedCounts = res.counts; } return res; })
            .catch(function(err){ console.warn('fetchReschedCounts failed', err); return null; });
          } catch(e){ return Promise.resolve(null); }
        }

        // prefetch counts for the session's month so flatpickr can render day counts
        try {
          var sessBase = (found && found.session && found.session.date) ? new Date(found.session.date) : new Date();
          var startMonth = new Date(sessBase.getFullYear(), sessBase.getMonth(), 1);
          var endMonth = new Date(sessBase.getFullYear(), sessBase.getMonth()+1, 0);
          var startIso = toLocalIso(startMonth);
          var endIso = toLocalIso(endMonth);
          fetchReschedCountsForRange(startIso, endIso).then(function(){ try { if (dateIn._flatpickr) { dateIn._flatpickr.redraw(); } } catch(e){} });
        } catch(e) { /* ignore prefetch errors */ }

        // initialize flatpickr when available, otherwise fallback to native oninput
        if (window.flatpickr && typeof window.flatpickr === 'function') {
          try {
            // reusable renderer that mirrors the approve modal style
            function renderReschedCounts(fpInstance) {
              try {
                var days = fpInstance && fpInstance.calendarContainer ? fpInstance.calendarContainer.querySelectorAll('.flatpickr-day') : [];
                days.forEach(function(dayElem){
                  try {
                    // remove old badge(s)
                    var old = dayElem.querySelector && (dayElem.querySelector('.orient-count') || dayElem.querySelector('.fp-day-count'));
                    if (old) old.remove();

                    // determine date for this cell
                    var dateObj = dayElem.dateObj || null;
                    if (!dateObj) {
                      // try to parse day number and use current view month/year
                      var txt = (dayElem.textContent || '').trim();
                      var m = txt.match(/^(\d{1,2})/);
                      if (!m) return;
                      var dayNum = parseInt(m[1], 10);
                      dateObj = new Date(fpInstance.currentYear, fpInstance.currentMonth, dayNum);
                    }
                    if (!dateObj) return;
                    var iso = toLocalIso(dateObj);
                    // prefer server-provided counts
                    var cnt = 0;
                    try {
                      if (window.reschedCounts && typeof window.reschedCounts === 'object') cnt = parseInt(window.reschedCounts[iso] || 0, 10) || 0;
                      else {
                        for (var dd in eventsData) {
                          if (!eventsData.hasOwnProperty(dd)) continue;
                          var arr = eventsData[dd] || [];
                          for (var i=0;i<arr.length;i++){ try { var s = arr[i]; if (!s || !s.date) continue; var dstr = toLocalIso(new Date(s.date)); if (dstr === iso) cnt += (s.count || 0); } catch(e){} }
                        }
                      }
                    } catch(e){}
                    if (cnt && cnt > 0) {
                      var span = document.createElement('span');
                      span.className = 'orient-count';
                      span.textContent = '(' + String(cnt) + ')';
                      span.style.cssText = 'color:#0b7a3a;font-weight:inherit;font-size:inherit;margin-left:0;vertical-align:baseline;line-height:1;';
                      // remove stray whitespace text nodes so span sits immediately after number
                      Array.from(dayElem.childNodes).forEach(function(n){ if (n.nodeType === Node.TEXT_NODE && n.textContent.trim() === '') n.remove(); });
                      var numSpan = dayElem.querySelector('.flat-day-num');
                      if (numSpan && numSpan.parentNode) numSpan.parentNode.insertBefore(span, numSpan.nextSibling);
                      else try { dayElem.appendChild(span); } catch(e){}
                    }
                  } catch(e) { /* per-day ignore */ }
                });
              } catch(e) { console.warn('renderReschedCounts failed', e); }
            }

            if (dateIn._flatpickr) dateIn._flatpickr.destroy();
            window.flatpickr(dateIn, {
              dateFormat: 'Y-m-d',
              defaultDate: dateIn.value || null,
              // allow navigating to earlier months; explicitly disable dates before minIso instead
              minDate: null,
              disable: [
                function(date){
                  try {
                    // block dates strictly before minIso (computed as next non-weekend)
                    if (minIso) {
                      var y = date.getFullYear(); var m = String(date.getMonth()+1).padStart(2,'0'); var d = String(date.getDate()).padStart(2,'0');
                      var iso = y + '-' + m + '-' + d;
                      if (iso < minIso) return true;
                    }
                    // disable weekends
                    if (date.getDay && (date.getDay() === 0 || date.getDay() === 6)) return true;
                    // disable original session date
                    if (forbiddenIso) {
                      var dd = toLocalIso(date);
                      if (dd === forbiddenIso) return true;
                    }
                  } catch(e) { /* ignore and allow date */ }
                  return false;
                }
              ],
              onReady: function(selectedDates, dateStr, fp){ try{ renderReschedCounts(fp); }catch(e){} },
              onOpen: function(selectedDates, dateStr, fp){ try{ renderReschedCounts(fp); }catch(e){} },
              onChange: function(selectedDates, dateStr){ try { if (!dateStr) return; updateReschedCounts(dateStr); } catch(e){} },
              onMonthChange: function(selectedDates, dateStr, fpInstance){
                try {
                  var y = fpInstance.currentYear; var m = fpInstance.currentMonth; // 0-based month
                  var s = toLocalIso(new Date(y, m, 1));
                  var e = toLocalIso(new Date(y, m+1, 0));
                  fetchReschedCountsForRange(s, e).then(function(){ try { renderReschedCounts(fpInstance); } catch(e){} });
                } catch(e) {}
              },
              onYearChange: function(selectedDates, dateStr, fpInstance){
                try {
                  var y = fpInstance.currentYear; var m = fpInstance.currentMonth;
                  var s = toLocalIso(new Date(y, m, 1));
                  var e = toLocalIso(new Date(y, m+1, 0));
                  fetchReschedCountsForRange(s, e).then(function(){ try { renderReschedCounts(fpInstance); } catch(e){} });
                } catch(e) {}
              },
              onDayCreate: function(dObj, dStr, dayElem){
                try {
                  if (!dayElem) return;
                  var dateObj = dayElem.dateObj || null;
                  if (!dateObj) return;
                  var iso = toLocalIso(dateObj);
                  var cnt = 0;
                  try {
                    if (window.reschedCounts && typeof window.reschedCounts === 'object') cnt = parseInt(window.reschedCounts[iso] || 0, 10) || 0;
                    else {
                      for (var dd in eventsData) {
                        if (!eventsData.hasOwnProperty(dd)) continue;
                        var arr = eventsData[dd] || [];
                        for (var i=0;i<arr.length;i++){ try { var s = arr[i]; if (!s || !s.date) continue; var dstr = toLocalIso(new Date(s.date)); if (dstr === iso) cnt += (s.count || 0); } catch(e){} }
                      }
                    }
                  } catch(e){}
                  // remove any existing count element to avoid duplicates
                  var existing = dayElem.querySelector && (dayElem.querySelector('.orient-count') || dayElem.querySelector('.fp-day-count'));
                  if (existing) existing.remove();
                  if (cnt > 0) {
                    var sp = document.createElement('span');
                    sp.className = 'orient-count';
                    sp.textContent = '(' + String(cnt) + ')';
                    sp.style.cssText = 'color:#0b7a3a;font-weight:inherit;font-size:inherit;margin-left:0;vertical-align:baseline;line-height:1';
                    Array.from(dayElem.childNodes).forEach(function(n){ if (n.nodeType === Node.TEXT_NODE && n.textContent.trim() === '') n.remove(); });
                    var numSpan = dayElem.querySelector('.flat-day-num');
                    if (numSpan && numSpan.parentNode) numSpan.parentNode.insertBefore(sp, numSpan.nextSibling);
                    else try { dayElem.appendChild(sp); } catch(e){}
                  }
                } catch(e) { /* ignore */ }
              }
            });
            // update counts for initial value
            if (dateIn.value) updateReschedCounts(dateIn.value);
          } catch(e) { /* ignore flatpickr init errors */ }
        } else {
          // fallback: native input handler
          dateIn.oninput = function(){
            try {
              var val = this.value;
              if (!val) return;
              if (this.dataset && this.dataset.forbiddenDate && val === this.dataset.forbiddenDate) {
                try { msg.style.display = 'block'; msg.style.background = '#fff4f4'; msg.style.color = '#a00'; msg.style.whiteSpace = 'pre-wrap'; msg.style.userSelect = 'text'; msg.textContent = 'Cannot select the same date as the current session.'; } catch(e){}
                this.value = minIso;
                setTimeout(function(){ try{ msg.style.display = 'none'; }catch(e){} }, 2400);
                updateReschedCounts(this.value);
                return;
              }
              var sel = new Date(val);
              if (!isNaN(sel) && sel.getDay && (sel.getDay() === 0 || sel.getDay() === 6)) {
                try { msg.style.display = 'block'; msg.style.background = '#fff4f4'; msg.style.color = '#a00'; msg.style.whiteSpace = 'pre-wrap'; msg.style.userSelect = 'text'; msg.textContent = 'Cannot select weekend (Saturday or Sunday). Please pick another date.'; } catch(e){}
                this.value = nextNonWeekendIso(val);
                setTimeout(function(){ try{ msg.style.display = 'none'; }catch(e){} }, 2400);
                updateReschedCounts(this.value);
                return;
              }
              // update counts for the selected value
              updateReschedCounts(val);
            } catch(e) {}
          };
          // initial counts
          if (dateIn.value) updateReschedCounts(dateIn.value);
        }
      } catch (e) {
        // ignore if date input unsupported
      }
      modal.style.display = 'flex';
      // focus date
      dateIn.focus();
    }

    // Refresh helper: reload the calendar to reflect server-side changes
    function refreshCalendar(){
      try { window.location.reload(); } catch(e) { /* ignore */ }
    }

    document.getElementById('resched_cancel').addEventListener('click', function(){
      document.getElementById('reschedModal').style.display = 'none';
      currentReschedSelected = null;
    });

    document.getElementById('resched_confirm').addEventListener('click', function(){
      var sid = currentReschedSession;
      if (!sid) return alert('Session id missing');
      var dateVal = document.getElementById('resched_date').value;
      if (!dateVal) return alert('Please select a date');
      var timeVal = document.getElementById('resched_time').value || '';
      var locVal = document.getElementById('resched_location').value || '';
      var selected = currentReschedSelected || null;
      var msg = document.getElementById('resched_msg');
      var btn = document.getElementById('resched_confirm');
      btn.disabled = true; btn.textContent = 'Working...';
      fetch('calendar.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'reschedule_all', session_id: sid, target_date: dateVal, target_time: timeVal, target_location: locVal, selected: selected })
      }).then(function(r){
        return r.text().then(function(txt){
          try { return JSON.parse(txt); }
          catch(e) { console.warn('calendar.php non-JSON response:', txt); return { success: r.ok, message: txt }; }
        });
      }).then(function(res){
        btn.disabled = false; btn.textContent = 'Confirm';
        var rawArea = document.getElementById('resched_raw');
        var copyBtn = document.getElementById('resched_copy');
        if (res && res.success) {
          msg.style.display = 'block'; msg.style.background = '#e6f9ee'; msg.style.color = '#0b7a3a'; msg.style.whiteSpace = 'pre-wrap'; msg.style.userSelect = 'text'; msg.textContent = (res.message || 'Rescheduled.');
          // update badges: prefer per-student badges when only some students were moved
          try {
            var sessEl = document.querySelector('[data-session-id="' + sid + '"]');
            var studentRows = sessEl ? sessEl.querySelectorAll('.student-row') : [];
            var totalStudents = studentRows.length || 0;
            var movedStudents = Array.isArray(res.moved_student_ids) ? res.moved_student_ids.map(function(x){ return String(x); }) : [];
            // always add per-student badges for moved students (do not show session-level rescheduled_from)
            if (movedStudents.length > 0) {
              movedStudents.forEach(function(sidMoved){
                try {
                  var cb = sessEl ? sessEl.querySelector('.resched-checkbox[data-student-id="' + sidMoved + '"]') : null;
                  if (!cb) cb = sessEl ? sessEl.querySelector('.resched-checkbox[data-application-id="' + sidMoved + '"]') : null;
                  if (cb) {
                    var row = cb.closest('.student-row');
                    if (row) {
                      // don't duplicate badge
                      if (!row.querySelector('.resched-badge')) {
                        var sd = res.from_date ? new Date(res.from_date) : null;
                        var label = res.from_date ? ('Rescheduled from ' + res.from_date) : 'Rescheduled';
                        var sb = document.createElement('span'); sb.className = 'resched-badge'; sb.textContent = label; sb.style.marginLeft = '8px';
                        row.appendChild(sb);
                      }
                    }
                  }
                } catch (e) { /* ignore per-row failures */ }
              });
            }
          } catch (e) { console.warn('Badge update failed', e); }
          // if server deleted the original session because no assignments remain, remove it from the DOM
          try {
            if (res && res.deleted_original) {
              var toRem = document.querySelectorAll('[data-session-id="' + sid + '"]');
              toRem.forEach(function(n){ n.remove(); });
            }
          } catch (e) { }
          // keep modal visible briefly then close (5s so user can copy any message)
          setTimeout(function(){ document.getElementById('reschedModal').style.display = 'none'; }, 5000);
          // refresh calendar display shortly after success so UI reflects server changes
          setTimeout(function(){ try{ refreshCalendar(); }catch(e){}; }, 1200);
          // clear selection after reschedule
          try { currentReschedSelected = null; var sessEl2 = document.querySelector('[data-session-id="' + sid + '"]'); if (sessEl2) { var checksAll = sessEl2.querySelectorAll('.resched-checkbox:checked'); checksAll.forEach(function(c){ c.checked = false; }); } } catch(e){}
        } else {
          // show error in modal and make it selectable so user can copy manually
          msg.style.display = 'block'; msg.style.background = '#fff4f4'; msg.style.color = '#a00'; msg.style.whiteSpace = 'pre-wrap'; msg.style.userSelect = 'text';
          var displayText = (res && res.message) ? res.message : 'Error';
          msg.textContent = displayText;
        }
      }).catch(function(err){
        btn.disabled = false; btn.textContent = 'Confirm';
        // show a request-failed message inside the modal so the user can copy it manually
        try {
          msg.style.display = 'block'; msg.style.background = '#fff4f4'; msg.style.color = '#a00'; msg.style.whiteSpace = 'pre-wrap'; msg.style.userSelect = 'text';
          var txt = 'Request failed' + (err && err.message ? (': ' + err.message) : '');
          msg.textContent = txt;
          try { msg.focus(); } catch(e){}
        } catch(e){}
        console.error(err);
      });
    });

    // copy button handler
    (function(){
      var btn = document.getElementById('resched_copy');
      var raw = document.getElementById('resched_raw');
      if (btn && raw) {
        btn.addEventListener('click', function(){
          var txt = raw.textContent || '';
          if (!txt) return;
          // try navigator.clipboard, fallback to textarea execCommand
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(function(){ btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = 'Copy'; }, 2000); }).catch(function(){ fallbackCopy(txt); });
          } else {
            fallbackCopy(txt);
          }
        });
      }
      function fallbackCopy(text){
        try {
          var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
          btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = 'Copy'; }, 2000);
        } catch(e){ console.error('Copy failed', e); }
      }
    })();
  </script>
</body>
</html>
