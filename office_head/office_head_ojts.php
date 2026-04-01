<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// prevent PHP notices/warnings breaking JSON responses; buffer output
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

// --- handle AJAX JSON submission in same file (no new file) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // try to parse JSON payload
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data) && isset($data['trainee_id'])) {

        // remove any buffered output (warnings, stray whitespace) before sending JSON header
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        // session already started above — DO NOT call session_start() again
        $evaluator_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $trainee = (int)$data['trainee_id'];
        $scores = $data['scores'] ?? [];
        // collect overall assessment fields submitted from modal
        $strengths = trim((string)($data['overall_strengths'] ?? ''));
        $improvements = trim((string)($data['improvement_areas'] ?? ''));
        $other_comments = trim((string)($data['other_comments'] ?? ''));
        $hire_decision = trim((string)($data['hire_decision'] ?? ''));

        // require these fields
        if ($strengths === '' || $improvements === '' || $other_comments === '' || $hire_decision === '') {
          echo json_encode(['success' => false, 'message' => 'Please complete all Overall Assessment fields']);
          exit;
        }

        // combine into feedback text stored in evaluations.feedback
        $remarks = "Strengths: " . $strengths . "\n\nAreas for improvement: " . $improvements . "\n\nOther comments: " . $other_comments . "\n\nHire decision: " . $hire_decision;
        $school_eval_raw = isset($data['school_eval']) ? $data['school_eval'] : null;

        // resolve student_id (students.user_id = trainee)
        $student_id = null;
        $q = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
        if ($q) {
            $q->bind_param("i", $trainee);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $q->close();
            if ($r && !empty($r['student_id'])) $student_id = (int)$r['student_id'];
        }

        if (!$student_id) {
            echo json_encode(['success' => false, 'message' => 'Trainee not found']);
            exit;
        }

        // validate school_eval presence (required) and numeric
        if ($school_eval_raw === null || $school_eval_raw === '') {
          echo json_encode(['success' => false, 'message' => 'School Evaluation Grade is required']);
          exit;
        }
        if (!is_numeric($school_eval_raw)) {
          echo json_encode(['success' => false, 'message' => 'School Evaluation Grade must be numeric']);
          exit;
        }
        $school_eval = floatval($school_eval_raw);

        // compute average of numeric scores (ignore NA / null)
        $sum = 0.0; $count = 0;
        foreach ($scores as $v) {
            if ($v === null) continue;
            if (is_string($v) && strtoupper($v) === 'NA') continue;
            if ($v === '') continue;
            // allow numeric strings and numbers
            if (is_numeric($v)) {
                $n = floatval($v);
                $sum += $n;
                $count++;
            }
        }

        if ($count > 0) {
            $avg = $sum / $count;
            $avgRounded = round($avg, 2);
            $avgStr = number_format($avgRounded, 2, '.', '');

            // map to description by nearest integer
            $map = [5 => 'Outstanding', 4 => 'Very Good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'];
            $roundedInt = (int) round($avgRounded);
            if ($roundedInt < 1) $roundedInt = 1;
            if ($roundedInt > 5) $roundedInt = 5;
            $desc = $map[$roundedInt] ?? 'N/A';

            $ratingDesc = $avgStr . ' | ' . $desc;
            $ratingValue = $avgRounded;
        } else {
            // no numeric ratings provided
            $ratingDesc = 'N/A | N/A';
            $desc = 'N/A';
            $avgRounded = null;
            $ratingValue = null;
        }

        // Begin DB transaction: insert evaluation and update statuses
        $conn->begin_transaction();
        $success = false;
        $cert_serial = '';
        $serial_lock_name = '';
        $releaseSerialLock = function() use ($conn, &$serial_lock_name) {
          if ($serial_lock_name === '') return;
          $rl = $conn->prepare("SELECT RELEASE_LOCK(?)");
          if ($rl) {
            $rl->bind_param('s', $serial_lock_name);
            $rl->execute();
            $rl->close();
          }
          $serial_lock_name = '';
        };

        // Generate certificate serial as YYYY-0001 with per-year reset.
        $serial_year = (int)date('Y');
        $serial_lock_name = 'cert_serial_' . $serial_year;
        $ls = $conn->prepare("SELECT GET_LOCK(?, 10) AS got_lock");
        if (!$ls) {
          $conn->rollback();
          echo json_encode(['success' => false, 'message' => 'Failed to prepare serial lock']);
          exit;
        }
        $ls->bind_param('s', $serial_lock_name);
        $ls->execute();
        $lr = $ls->get_result()->fetch_assoc();
        $ls->close();
        if (!$lr || (int)($lr['got_lock'] ?? 0) !== 1) {
          $conn->rollback();
          echo json_encode(['success' => false, 'message' => 'Unable to lock serial generator. Please try again.']);
          exit;
        }

        $like_year = $serial_year . '-%';
        $next_seq = 1;
        $qs = $conn->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(cert_serial, '-', -1) AS UNSIGNED)), 0) + 1 AS next_seq FROM evaluations WHERE cert_serial LIKE ? FOR UPDATE");
        if (!$qs) {
          $releaseSerialLock();
          $conn->rollback();
          echo json_encode(['success' => false, 'message' => 'Failed to prepare serial query']);
          exit;
        }
        $qs->bind_param('s', $like_year);
        if (!$qs->execute()) {
          $qs->close();
          $releaseSerialLock();
          $conn->rollback();
          echo json_encode(['success' => false, 'message' => 'Failed to generate serial number']);
          exit;
        }
        $sr = $qs->get_result()->fetch_assoc();
        $qs->close();
        $next_seq = isset($sr['next_seq']) ? (int)$sr['next_seq'] : 1;
        if ($next_seq < 1) $next_seq = 1;
        $cert_serial = sprintf('%04d-%04d', $serial_year, $next_seq);

        // insert evaluation row and store overall text fields separately
        $ins = $conn->prepare("INSERT INTO evaluations (student_id, rating, rating_desc, feedback, school_eval, strengths, improvement, comments, hiring, cert_serial, date_evaluated, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        if ($ins) {
          // bind types: i (student_id), d (ratingValue), s (ratingDesc), s (feedback), d (school_eval), s (strengths), s (improvements), s (other_comments), s (hire_decision), s (cert_serial), i (evaluator_id)
          $ins->bind_param("idssdsssssi", $student_id, $ratingValue, $ratingDesc, $remarks, $school_eval, $strengths, $improvements, $other_comments, $hire_decision, $cert_serial, $evaluator_id);
          $insOk = $ins->execute();
          $eval_insert_id = $conn->insert_id;
          $ins->close();
        } else {
          $releaseSerialLock();
          echo json_encode(['success' => false, 'message' => 'DB prepare failed (evaluations insert)']);
          $conn->rollback();
          exit;
        }

        if ($insOk) {
            // insert individual question responses into evaluation_responses
            $respOk = true;
            $eval_id = isset($eval_insert_id) ? (int)$eval_insert_id : 0;
            if ($eval_id > 0) {
              $insRespScore = $conn->prepare("INSERT INTO evaluation_responses (eval_id, question_key, question_order, score) VALUES (?, ?, ?, ?)");
              $insRespNull  = $conn->prepare("INSERT INTO evaluation_responses (eval_id, question_key, question_order, score) VALUES (?, ?, ?, NULL)");
              if ($insRespScore && $insRespNull) {
                foreach ($scores as $qkey => $qval) {
                  $qorder = null;
                  if (preg_match('/(\\d+)$/', $qkey, $m)) $qorder = (int)$m[1];
                  $orderVal = $qorder ?? 0;

                  if ($qval === null || (is_string($qval) && strtoupper($qval) === 'NA') || $qval === '') {
                    $insRespNull->bind_param('isi', $eval_id, $qkey, $orderVal);
                    if (!$insRespNull->execute()) { $respOk = false; break; }
                  } else {
                    if (!is_numeric($qval)) {
                      $insRespNull->bind_param('isi', $eval_id, $qkey, $orderVal);
                      if (!$insRespNull->execute()) { $respOk = false; break; }
                    } else {
                      $scoreInt = intval($qval);
                      if ($scoreInt < 1 || $scoreInt > 5) {
                        $insRespNull->bind_param('isi', $eval_id, $qkey, $orderVal);
                        if (!$insRespNull->execute()) { $respOk = false; break; }
                      } else {
                        $insRespScore->bind_param('isii', $eval_id, $qkey, $orderVal, $scoreInt);
                        if (!$insRespScore->execute()) { $respOk = false; break; }
                      }
                    }
                  }
                }
                $insRespScore->close();
                $insRespNull->close();
              } else {
                $respOk = false;
              }
            } else {
              $respOk = false;
            }

            if (!$respOk) {
              $releaseSerialLock();
              $conn->rollback();
              echo json_encode(['success' => false, 'message' => 'Failed to save individual responses']);
              exit;
            }

            // update students.status => mark evaluated
            $u1 = $conn->prepare("UPDATE students SET status = 'evaluated' WHERE student_id = ?");
            $u1Ok = true;
            if ($u1) { $u1->bind_param("i", $student_id); $u1Ok = $u1->execute(); $u1->close(); }

            // update users.status => mark evaluated (users.user_id = trainee)
            $u2 = $conn->prepare("UPDATE users SET status = 'evaluated' WHERE user_id = ?");
            $u2Ok = true;
            if ($u2) { $u2->bind_param("i", $trainee); $u2Ok = $u2->execute(); $u2->close(); }

            // update ojt_applications.status => evaluated (if application exists)
            $u3 = $conn->prepare("UPDATE ojt_applications SET status = 'evaluated', date_updated = NOW() WHERE student_id = ?");
            $u3Ok = true;
            if ($u3) { $u3->bind_param("i", $student_id); $u3Ok = $u3->execute(); $u3->close(); }

            if ($u1Ok && $u2Ok && $u3Ok) {
              $releaseSerialLock();
                $conn->commit();
                $success = true;
            } else {
              $releaseSerialLock();
                $conn->rollback();
            }
        } else {
            $releaseSerialLock();
            $conn->rollback();
        }

        if ($success) {
          // create notifications: HR (hr_head + hr_staff) and the OJT (trainee)
          // resolve trainee display name
          $trainee_name = 'Trainee';
          $sname = $conn->prepare("SELECT COALESCE(NULLIF(u.first_name,''), NULLIF(s.first_name,''), '') AS first_name, COALESCE(NULLIF(u.last_name,''), NULLIF(s.last_name,''), '') AS last_name FROM users u LEFT JOIN students s ON s.user_id = u.user_id WHERE u.user_id = ? LIMIT 1");
          if ($sname) {
            $sname->bind_param('i', $trainee);
            $sname->execute();
            $tr = $sname->get_result()->fetch_assoc();
            $sname->close();
            if ($tr) $trainee_name = trim(($tr['first_name'] ?? '') . ' ' . ($tr['last_name'] ?? '')) ?: 'Trainee';
          }

          // notify HR head and HR staff
          $hrRecipients = [];
          $r = $conn->query("SELECT user_id FROM users WHERE role IN ('hr_head','hr_staff') AND status = 'active'");
          if ($r) {
            while ($rr = $r->fetch_assoc()) $hrRecipients[] = (int)$rr['user_id'];
            $r->free();
          }
          $hrRecipients = array_values(array_unique(array_filter($hrRecipients, function($v){ return $v > 0; })));

          if (!empty($hrRecipients)) {
            $msg_hr = 'Evaluation Submitted: The performance evaluation for ' . $trainee_name . ' has been submitted.';
            $now = date('Y-m-d H:i:s');
            $ins = $conn->prepare("INSERT INTO notifications (message, created_at) VALUES (?, ?)");
            if ($ins) {
              $ins->bind_param('ss', $msg_hr, $now);
              $ins->execute();
              $nid = $conn->insert_id;
              $ins->close();

              $ins2 = $conn->prepare("INSERT INTO notification_users (notification_id, user_id, is_read) VALUES (?, ?, 0)");
              if ($ins2) {
                foreach ($hrRecipients as $uid) {
                  $ins2->bind_param('ii', $nid, $uid);
                  $ins2->execute();
                }
                $ins2->close();
              }
            }
          }

          // notify the trainee (OJT)
          $msg_ojt = 'Evaluation Submitted: Your performance evaluation has been submitted.';
          $now = date('Y-m-d H:i:s');
          $insx = $conn->prepare("INSERT INTO notifications (message, created_at) VALUES (?, ?)");
          if ($insx) {
            $insx->bind_param('ss', $msg_ojt, $now);
            $insx->execute();
            $nid2 = $conn->insert_id;
            $insx->close();

            $insu = $conn->prepare("INSERT INTO notification_users (notification_id, user_id, is_read) VALUES (?, ?, 0)");
            if ($insu) {
              $insu->bind_param('ii', $nid2, $trainee);
              $insu->execute();
              $insu->close();
            }
          }

          echo json_encode(['success' => true, 'message' => 'Evaluation saved and statuses updated', 'rating' => $avgRounded, 'rating_text' => $ratingDesc, 'cert_serial' => $cert_serial]);
          exit;
        } else {
          echo json_encode(['success' => false, 'message' => 'DB operation failed']);
          exit;
        }
    }
}

// AJAX: view weekly journals for a student user (read-only)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'view_journals') {
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json');

  try {
    $student_user_id = isset($_GET['student_user_id']) ? (int)$_GET['student_user_id'] : 0;
    if ($student_user_id <= 0) {
      echo json_encode(['success' => false, 'message' => 'Invalid student_user_id']);
      exit;
    }

    $student_id = 0;
    $qStudent = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
    if ($qStudent) {
      $qStudent->bind_param('i', $student_user_id);
      $qStudent->execute();
      $rowStudent = $qStudent->get_result()->fetch_assoc();
      $qStudent->close();
      if ($rowStudent && !empty($rowStudent['student_id'])) {
        $student_id = (int)$rowStudent['student_id'];
      }
    }

    $rows = [];
    $targetJournalUserId = $student_id > 0 ? $student_id : $student_user_id;

    $qj = $conn->prepare("SELECT journal_id, date_uploaded, week_coverage, attachment FROM weekly_journal WHERE user_id = ? ORDER BY date_uploaded DESC, journal_id DESC LIMIT 50");
    if ($qj) {
      $qj->bind_param('i', $targetJournalUserId);
      $qj->execute();
      $rj = $qj->get_result();
      while ($r = $rj->fetch_assoc()) $rows[] = $r;
      $qj->close();
    }

    // Fallback: some deployments may store weekly_journal.user_id as users.user_id.
    if (empty($rows) && $targetJournalUserId !== $student_user_id) {
      $qj2 = $conn->prepare("SELECT journal_id, date_uploaded, week_coverage, attachment FROM weekly_journal WHERE user_id = ? ORDER BY date_uploaded DESC, journal_id DESC LIMIT 50");
      if ($qj2) {
        $qj2->bind_param('i', $student_user_id);
        $qj2->execute();
        $rj2 = $qj2->get_result();
        while ($r2 = $rj2->fetch_assoc()) $rows[] = $r2;
        $qj2->close();
      }
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
  } catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while fetching journals', 'error' => $e->getMessage()]);
    exit;
  }
}

// AJAX: resolve MOA file by school name (priority source for attachments tab)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'resolve_moa') {
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json');

  try {
    $college = trim((string)($_GET['college'] ?? ''));
    if ($college === '') {
      echo json_encode(['success' => true, 'moa_file' => '']);
      exit;
    }

    $moaFile = '';
    $qm = $conn->prepare("SELECT moa_file FROM moa WHERE LOWER(TRIM(school_name)) = LOWER(TRIM(?)) ORDER BY date_uploaded DESC, moa_id DESC LIMIT 1");
    if ($qm) {
      $qm->bind_param('s', $college);
      $qm->execute();
      $rm = $qm->get_result()->fetch_assoc();
      $qm->close();
      if ($rm && !empty($rm['moa_file'])) {
        $moaFile = (string)$rm['moa_file'];
      }
    }

    echo json_encode(['success' => true, 'moa_file' => $moaFile]);
    exit;
  } catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while resolving MOA']);
    exit;
  }
}

// AJAX: view evaluation details (read-only)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'view_eval') {
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json');

  // convert warnings/notices into exceptions inside this block so we can return JSON
  set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
  });

  // helper: fetch all rows from a prepared statement, compatible with environments
  // that don't have mysqli_stmt::get_result (mysqlnd absent)
  function stmt_fetch_all_rows($stmt) {
    if (method_exists($stmt, 'get_result')) {
      $res = $stmt->get_result();
      $rows = [];
      while ($r = $res->fetch_assoc()) $rows[] = $r;
      return $rows;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) return [];
    $fields = [];
    while ($f = $meta->fetch_field()) $fields[] = $f->name;
    $meta->free();
    $row = array_fill_keys($fields, null);
    $bindRefs = [];
    foreach ($fields as $name) $bindRefs[] = &$row[$name];
    call_user_func_array([$stmt, 'bind_result'], $bindRefs);
    $rows = [];
    while ($stmt->fetch()) {
      $copy = [];
      foreach ($row as $k => $v) $copy[$k] = $v;
      $rows[] = $copy;
    }
    return $rows;
  }

  function stmt_fetch_one($stmt) {
    $rows = stmt_fetch_all_rows($stmt);
    return count($rows) ? $rows[0] : null;
  }

  try {
    $eval_id = isset($_GET['eval_id']) ? (int)$_GET['eval_id'] : 0;
    // fallback: accept either direct student_id (preferred) or student_user_id
    if ($eval_id <= 0) {
      if (isset($_GET['student_id'])) {
        $sid = (int)$_GET['student_id'];
        if ($sid > 0) {
            $qev = $conn->prepare("SELECT eval_id FROM evaluations WHERE student_id = ? ORDER BY date_evaluated DESC, eval_id DESC LIMIT 1");
            if (!$qev) {
              throw new Exception('DB prepare failed (fallback eval lookup): ' . $conn->error);
            }
            $qev->bind_param('i', $sid);
            if (!$qev->execute()) {
              throw new Exception('DB execute failed (fallback eval lookup): ' . $qev->error);
            }
            $rev = stmt_fetch_one($qev);
            $qev->close();
            if ($rev && !empty($rev['eval_id'])) $eval_id = (int)$rev['eval_id'];
        }
      } elseif (isset($_GET['student_user_id'])) {
        $stu_user = (int)$_GET['student_user_id'];
        if ($stu_user > 0) {
          $qsv = $conn->prepare("SELECT s.student_id FROM students s WHERE s.user_id = ? LIMIT 1");
          if ($qsv) {
            $qsv->bind_param('i', $stu_user);
            $qsv->execute();
            $rsv = $qsv->get_result()->fetch_assoc();
            $qsv->close();
            if ($rsv && !empty($rsv['student_id'])) {
              $sid = (int)$rsv['student_id'];
              $qev = $conn->prepare("SELECT eval_id FROM evaluations WHERE student_id = ? ORDER BY date_evaluated DESC, eval_id DESC LIMIT 1");
              if ($qev) {
                $qev->bind_param('i', $sid);
                $qev->execute();
                $rev = $qev->get_result()->fetch_assoc();
                $qev->close();
                if ($rev && !empty($rev['eval_id'])) $eval_id = (int)$rev['eval_id'];
              }
            }
          }
        }
      }
    }

    if ($eval_id <= 0) {
      echo json_encode(['success' => false, 'message' => 'Invalid eval_id']);
      restore_error_handler();
      exit;
    }

    // fetch evaluation row
    $qe = $conn->prepare("SELECT e.*, COALESCE(u.first_name,'') AS ev_first, COALESCE(u.last_name,'') AS ev_last FROM evaluations e LEFT JOIN users u ON e.user_id = u.user_id WHERE e.eval_id = ? LIMIT 1");
    if (!$qe) {
      throw new Exception('DB prepare failed (evaluation select): ' . $conn->error);
    }
    $evaluation = null;
    $qe->bind_param('i', $eval_id);
    if (!$qe->execute()) {
      throw new Exception('DB execute failed (evaluation select): ' . $qe->error);
    }
    $evaluation = stmt_fetch_one($qe);
    $qe->close();
    if (!$evaluation) {
      echo json_encode(['success' => false, 'message' => 'Evaluation not found']);
      restore_error_handler();
      exit;
    }

    // fetch responses with question text if available
    // Do not fail the whole request if responses query fails; evaluation row is still useful.
    $rows = [];
    $responses_warning = null;
    $sqlWithQuestions = "SELECT er.question_key, er.question_order, er.score, q.qtext, q.category FROM evaluation_responses er LEFT JOIN evaluation_questions q ON CONVERT(q.question_key USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(er.question_key USING utf8mb4) COLLATE utf8mb4_unicode_ci WHERE er.eval_id = ? ORDER BY COALESCE(q.sort_order, er.question_order), er.question_order";
    $sqlFallback = "SELECT er.question_key, er.question_order, er.score, NULL AS qtext, NULL AS category FROM evaluation_responses er WHERE er.eval_id = ? ORDER BY er.question_order";

    $qr = $conn->prepare($sqlWithQuestions);
    if (!$qr) {
      // fallback when evaluation_questions table/columns are unavailable
      $qr = $conn->prepare($sqlFallback);
    }
    if ($qr) {
      $qr->bind_param('i', $eval_id);
      if ($qr->execute()) {
        $rows = stmt_fetch_all_rows($qr);
      } else {
        $responses_warning = 'responses execute failed: ' . $qr->error;
      }
      $qr->close();
    } else {
      $responses_warning = 'responses prepare failed: ' . $conn->error;
    }

    echo json_encode(['success' => true, 'evaluation' => $evaluation, 'responses' => $rows, 'responses_warning' => $responses_warning]);
    restore_error_handler();
    exit;
  } catch (Throwable $e) {
    // ensure any buffered output is cleared so JSON is valid
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    $payload = ['success' => false, 'message' => 'Server error while fetching evaluation', 'error' => $e->getMessage()];
    // attempt to include trace for debugging (can be removed later)
    try { $payload['trace'] = $e->getTraceAsString(); } catch (Throwable $_) {}
    echo json_encode($payload);
    // also write to PHP error log for server-side inspection
    error_log('[view_eval error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    restore_error_handler();
    exit;
  }
}

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id = (int)$_SESSION['user_id'];

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// resolve display name and office
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$user_email = trim((string)($_SESSION['email'] ?? ''));
if ($user_name === '') {
    $su = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) {
      $user_name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? ''));
      if ($user_email === '') $user_email = trim((string)($ur['email'] ?? ''));
    }
}
if ($user_name === '') $user_name = 'Office Head';

// find office
$office = null;
$tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s = $conn->prepare("
        SELECT o.* 
        FROM office_heads oh
        JOIN offices o ON oh.office_id = o.office_id
        WHERE oh.user_id = ?
        LIMIT 1
    ");
    $s->bind_param("i", $user_id);
    $s->execute();
    $office = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
}
if (!$office) {
    $su = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $urow = $su->get_result()->fetch_assoc();
    $su->close();
    if (!empty($urow['office_name'])) {
        $office_name = $urow['office_name'];
        $q = $conn->prepare("SELECT * FROM offices WHERE office_name LIKE ? LIMIT 1");
        $like = "%{$office_name}%";
        $q->bind_param("s", $like);
        $q->execute();
        $office = $q->get_result()->fetch_assoc() ?: null;
        $q->close();
    }
}
if (!$office) {
    $office = ['office_id'=>0,'office_name'=>'Unknown Office'];
}
$office_display = preg_replace('/\s+Office\s*$/i', '', trim($office['office_name'] ?? 'Unknown Office'));

// fetch OJTs for this office (include students.status and hours columns)
$ojts = [];
$stmt = $conn->prepare("
    SELECT u.user_id,
           COALESCE(s.student_id, 0) AS student_id,
           COALESCE(NULLIF(u.first_name, ''), NULLIF(s.first_name, ''), '') AS first_name,
           COALESCE(NULLIF(u.last_name, ''), NULLIF(s.last_name, ''), '') AS last_name,
           COALESCE(s.college, '') AS school,
           COALESCE(s.course, '') AS course,
           COALESCE(s.year_level, '') AS year_level,
           COALESCE(s.hours_rendered, 0) AS hours_completed,
           COALESCE(s.total_hours_required, 500) AS hours_required,
           COALESCE(s.status, '') AS student_status,
           COALESCE(u.status, '') AS user_status,
           (
             SELECT oa.application_id
             FROM ojt_applications oa
             WHERE oa.student_id = s.student_id
             ORDER BY oa.date_submitted DESC, oa.application_id DESC
             LIMIT 1
           ) AS application_id
    FROM users u
    LEFT JOIN students s ON s.user_id = u.user_id
    WHERE u.role = 'ojt' AND u.office_name LIKE ?
    ORDER BY u.last_name, u.first_name
    LIMIT 200
");
$like = '%' . ($office['office_name'] ?? '') . '%';
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $ojts[] = $r;
$stmt->close();

// Override hours_completed with accurate sum from dtr (hours + minutes)
if (!empty($ojts)) {
  $qDtr = $conn->prepare("SELECT IFNULL(SUM(hours),0) AS th, IFNULL(SUM(minutes),0) AS tm FROM dtr WHERE student_id = ?");
  if ($qDtr) {
    foreach ($ojts as &$row) {
      $sid = (int)($row['user_id'] ?? 0);
      $qDtr->bind_param('i', $sid);
      $qDtr->execute();
      $dres = $qDtr->get_result();
      $d = $dres ? $dres->fetch_assoc() : null;
      $th = isset($d['th']) ? (int)$d['th'] : 0;
      $tm = isset($d['tm']) ? (int)$d['tm'] : 0;
      // normalize minutes into hours
      $th += intdiv($tm, 60);
      $rem = $tm % 60;
      // numeric value used for comparisons (hours + fraction)
      $row['hours_completed'] = $th + ($rem / 60);
      // display as decimal-style minutes per request (e.g. 21 hours 4 minutes -> 21.4)
      $row['hours_display'] = $th . '.' . $rem;
      // keep raw parts if needed
      $row['hours_part_h'] = $th;
      $row['hours_part_m'] = $rem;
    }
    unset($row);
    $qDtr->close();
  }
}

// split into tabs:
// - Completed: explicitly marked 'completed' (prefer this)
// - For Evaluation: reached or surpassed required hours but not yet marked completed
// - Active: everything else
$for_eval = []; $active = []; $completedArr = [];
foreach ($ojts as $r) {
    $hc = floatval($r['hours_completed'] ?? 0);
    $hr = floatval($r['hours_required'] ?? 0);
    $student_status = strtolower(trim((string)($r['student_status'] ?? '')));
    $user_status = strtolower(trim((string)($r['user_status'] ?? '')));

    // Evaluated: primarily based on users.status; fallback to students.status
    if ($user_status === 'evaluated' || $student_status === 'evaluated') {
      $completedArr[] = $r;
      continue;
    }

    // For Evaluation: users with users.status = 'completed' OR those who reached required hours
    if ($user_status === 'completed' || ($hr > 0 && $hc >= $hr)) {
      $for_eval[] = $r;
      continue;
    }

    // Active/Ongoing: show only if users.status is exactly 'ongoing'
    if ($user_status === 'ongoing') {
      $active[] = $r;
      continue;
    }
    // otherwise do not include in any tab
}

// Load evaluated OJTs with latest evaluation remarks (override completedArr with richer rows)
$completedArr = [];
$q = $conn->prepare("
        SELECT u.user_id,
          s.student_id,
          COALESCE(NULLIF(u.first_name, ''), NULLIF(s.first_name, '')) AS first_name,
          COALESCE(NULLIF(u.last_name, ''), NULLIF(s.last_name, '')) AS last_name,
          COALESCE(s.college, '') AS school,
          COALESCE(s.course, '') AS course,
          COALESCE(s.year_level, '') AS year_level,
          COALESCE(s.hours_rendered, 0) AS hours_completed,
          COALESCE(s.total_hours_required, 500) AS hours_required,
          (SELECT ev2.rating_desc FROM evaluations ev2 WHERE ev2.student_id = s.student_id ORDER BY ev2.date_evaluated DESC, ev2.eval_id DESC LIMIT 1) AS remarks,
          (SELECT ev3.school_eval FROM evaluations ev3 WHERE ev3.student_id = s.student_id ORDER BY ev3.date_evaluated DESC, ev3.eval_id DESC LIMIT 1) AS school_eval,
          (SELECT ev4.eval_id FROM evaluations ev4 WHERE ev4.student_id = s.student_id ORDER BY ev4.date_evaluated DESC, ev4.eval_id DESC LIMIT 1) AS eval_id
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.status = 'evaluated'
    ORDER BY u.last_name, u.first_name
");
if ($q) {
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) $completedArr[] = $row;
    $q->close();
}

// override completedArr hours with DTR sums (same decimal-style minutes display)
if (!empty($completedArr)) {
  $qDtr2 = $conn->prepare("SELECT IFNULL(SUM(hours),0) AS th, IFNULL(SUM(minutes),0) AS tm FROM dtr WHERE student_id = ?");
  if ($qDtr2) {
    foreach ($completedArr as &$c) {
      $sid = (int)($c['user_id'] ?? 0);
      $qDtr2->bind_param('i', $sid);
      $qDtr2->execute();
      $dres = $qDtr2->get_result();
      $d = $dres ? $dres->fetch_assoc() : null;
      $th = isset($d['th']) ? (int)$d['th'] : 0;
      $tm = isset($d['tm']) ? (int)$d['tm'] : 0;
      $th += intdiv($tm, 60);
      $rem = $tm % 60;
      $c['hours_completed'] = $th + ($rem / 60);
      $c['hours_display'] = $th . '.' . $rem;
      $c['hours_part_h'] = $th;
      $c['hours_part_m'] = $rem;
    }
    unset($c);
    $qDtr2->close();
  }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Office Head — OJT List</title>
<style>
  body{font-family:'Poppins',sans-serif;margin:0;background:#f5f6fa}
  .sidebar{width:220px;background:#2f3459;height:100vh;position:fixed;color:#fff;padding-top:30px}
  .main{margin-left:240px;padding:20px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(47,52,89,0.04)}
  .tabs{display:flex;gap:18px;border-bottom:1px solid #e6e9f2;padding-bottom:12px;margin-bottom:16px}
  .tab{padding:10px 18px;border-radius:8px;cursor:pointer;color:#6b6f8b}
  .tab.active{border-bottom:3px solid #4f4aa6;color:#111}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  .search{flex:1;padding:12px;border-radius:10px;border:1px solid #e6e9f2;background:#fff}
  .btn{padding:10px 14px;border-radius:20px;border:0;background:#4f4aa6;color:#fff;cursor:pointer}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:14px;text-align:left;border-bottom:1px solid #eef1f6;font-size:14px}
  thead th{background:#f5f7fb;color:#2f3459}
  .view-btn{background:transparent;border:0;cursor:pointer;color:#2f3459}
  .pill{background:#f0f0f0;padding:6px 10px;border-radius:16px;display:inline-block}
  .tab-panel{display:none}
  .tab-panel.active{display:block}
  .sidebar {
        width: 220px;
        background-color: #2f3459;
        height: 100vh;
        color: white;
        position: fixed;
        padding-top: 30px;
    }
    .sidebar h3 {
        text-align: center;
        margin-bottom: 5px;
    }
    .sidebar p {
        text-align: center;
        font-size: 14px;
        margin-top: 0;
    }
    .sidebar a {
        display: block;
        padding: 10px 20px;
        margin: 10px;
        color: black;
        border-radius: 20px;
        text-decoration: none;
    }
    .sidebar a.active {
        background-color: #fff;
    }
  /* match office_head_home.php top icons positioning & spacing */
  #top-icons { display:flex; justify-content:flex-end; gap:14px; align-items:center; margin:8px 0 12px 0; z-index:50; }

  /* icon style that matches top-right icons: no border, transparent background, same color */
  .icon-btn {
    background: transparent;
    border: 0;
    color: #2f3459; /* same as sidebar color */
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0; /* avoid emoji font sizing — use SVG inside */
    padding: 0;
    line-height: 1;
  }
  .icon-btn:active { transform: translateY(1px); }
  .icon-btn.small { width: 32px; height: 32px; font-size:0 }
  .icon-btn svg { width:18px; height:18px; stroke:currentColor; fill:none; }
  .evaluate-btn { margin-left: 8px; } /* keep spacing */
  /* make school-grade divider more visible */
  .eval-divider {
    border-top: 2px solid #c8cfe8; /* darker, more visible */
    padding-top: 14px;
    margin-top: 12px;
  }

  /* hide spinner arrows on number input for eval grade */
  #evalSchoolGrade::-webkit-outer-spin-button,
  #evalSchoolGrade::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  #evalSchoolGrade {
    -moz-appearance: textfield;
    appearance: textfield;
  }

  .tool-link{background:#eef2ff;border:1px solid #dbe4ff;padding:6px 10px;border-radius:8px;font-size:12px;color:#1f2a56;text-decoration:none;cursor:pointer}
  .tool-link:hover{background:#e2e8ff}
  .view-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(16,24,40,0.28); z-index: 9999; padding: 18px; }
  body.modal-open { overflow: hidden; height: 100%; }
  .view-card {
    width: 880px;
    max-width: 94vw;
    border-radius: 20px;
    background: transparent;
    box-shadow: 0 22px 60px rgba(16,24,40,0.28);
    overflow: visible;
    position: relative;
    padding: 18px;
    max-height: 80vh;
    display:flex;
    flex-direction:column;
  }
  .view-inner {
    background:#fff;
    border-radius:14px;
    padding:18px;
    border: 1px solid rgba(231,235,241,0.9);
    min-height: 460px;
    max-height: calc(80vh - 36px);
    display:flex;
    flex-direction:column;
    overflow:hidden;
  }
  .view-panel { flex:1 1 auto; min-height:360px; box-sizing:border-box; overflow:auto; padding-top:8px; }
  .view-close { position: absolute; right: 18px; top: 18px; width:36px;height:36px;border-radius:50%;background:#fff;border:0;box-shadow:0 6px 18px rgba(16,24,40,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px; z-index:10010; }
  .view-header { display:flex; gap:18px; align-items:center; margin-bottom:6px; }
  .view-avatar { width:96px;height:96px;border-radius:50%;background:#eceff3;flex:0 0 96px; display:flex;align-items:center;justify-content:center; overflow:hidden; }
  .view-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
  .view-name { font-size:20px; font-weight:800; color:#222e50; margin:0 0 6px 0; letter-spacing:0.2px; }
  .view-submeta { font-size:13px; color:#6b7280; display:flex; gap:12px; align-items:center; }
  .view-tools { display:flex; gap:12px; align-items:center; margin-top:8px; }
  .view-tabs { display:flex; gap:20px; align-items:center; margin-top:10px; padding-bottom:10px; }
  .view-tab { padding:6px 10px; cursor:pointer; border-radius:6px; color:#6b7280; font-weight:700; font-size:13px; }
  .view-tab.active { color:#1f2937; border-bottom:3px solid #344154; }
  .view-body { display:flex; gap:18px; margin-top:8px; align-items:flex-start; }
  .view-left { flex:1; padding:14px; border-radius:10px; min-width:320px; }
  .view-right { width:340px; min-width:260px; padding:14px; }
  .info-row{ display:flex; gap:10px; padding:6px 0; align-items:flex-start; }
  .info-label{ width:110px; font-weight:700; color:#222e50; font-size:13px; }
  .info-value{ color:#111827; font-weight:800; font-size:13px; line-height:1.1; }
  .emergency{ margin-top:12px; padding-top:8px; border-top:1px solid #eef2f6; }
  .donut { width:100px; height:100px; display:grid; place-items:center; }
  .donut svg { transform:rotate(-90deg); }
  @media (max-width:980px){
    .view-card{ width:calc(100% - 32px); padding:12px; }
    .view-inner{ padding:12px; }
    .view-body{ flex-direction:column; }
    .view-avatar { width:72px;height:72px; flex:0 0 72px; }
    .view-right{ width:100%; min-width:0; }
  }
</style>
</head>
<body>

<div class="sidebar">
  <div style="text-align:center;padding:18px 12px 8px;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fff;color:#2f3459;display:inline-flex;align-items:center;justify-content:center;font-weight:700;margin:6px auto;font-size:20px;">
      <?= htmlspecialchars(mb_strtoupper(substr(trim($user_name),0,1) ?: 'O')) ?>
    </div>
    <h3 style="margin:8px 0 4px;font-size:16px;"><?= htmlspecialchars($user_name) ?></h3>
    <p style="margin:0;font-size:13px;opacity:0.9">Office Head — <?= htmlspecialchars($office_display) ?></p>
  </div>

  <nav class="nav" style="margin-top:14px;display:flex;flex-direction:column;gap:8px;padding:0 12px;">
    <a href="office_head_home.php" title="Home" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 11.5L12 4l9 7.5"></path>
        <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
      </svg>
      <span>Home</span>
    </a>

    <a href="office_head_ojts.php" class="active" title="OJTs" style="display:flex;align-items:center;gap:8px;color:#2f3459;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="3"></circle>
        <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
      </svg>
      <span>OJTs</span>
    </a>

    <a href="office_head_dtr.php" title="DTR" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>DTR</span>
    </a>

    <!-- Reports link removed per request -->

  </nav>


  <div style="position:absolute;bottom:20px;width:100%;text-align:center;font-weight:700;padding-bottom:6px">OJT-MS</div>
</div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout — moved inside .main to match office_head_home.php -->
  <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
        <a id="btnNotif" href="#" title="Notifications" aria-haspopup="dialog" aria-expanded="false" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;position:relative;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
          <span class="notif-count" aria-hidden="true" style="position:absolute;top:-4px;right:-4px;width:18px;height:18px;border-radius:999px;background:#ef4444;color:#fff;font-size:11px;line-height:1;font-weight:700;text-align:center;display:none;align-items:center;justify-content:center;">0</span>
      </a>
      <button id="btnSettings" type="button" title="Settings" aria-label="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;border:0;box-shadow:none;cursor:pointer;">
           <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
      </button>
      <a id="btnLogout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div>
        <div class="tabs" role="tablist" aria-label="OJTs tabs">
          <div class="tab active" data-target="panel-active">Ongoing</div>
          <div class="tab" data-target="panel-eval">For Evaluation</div>
          <div class="tab" data-target="panel-evaluated">Evaluated</div>
        </div>
        </div>
      <div style="display:flex;gap:12px;align-items:center">
        <!-- Create OJT removed per request -->
      </div>
    </div>

    <div class="controls">
      <input class="search" placeholder="Search" id="searchInput" />
    </div>

    <div id="panel-active" class="tab-panel active">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>View</th></tr>
          </thead>
          <tbody>
            <?php if (empty($active)): ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No ongoing OJTs.</td></tr>
            <?php else: foreach ($active as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php
                  $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed'];
                  echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs');
                ?></td>
                <td>
                  <button type="button" class="view-btn icon-btn"
                    data-view-context="ongoing"
                    data-user-status="<?php echo htmlspecialchars(strtolower((string)($o['user_status'] ?? 'ongoing'))); ?>"
                    data-app-id="<?php echo (int)($o['application_id'] ?? 0); ?>"
                    data-id="<?php echo (int)$o['user_id']; ?>"
                    data-name="<?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?>"
                    data-school="<?php echo htmlspecialchars($o['school'] ?: '-'); ?>"
                    data-course="<?php echo htmlspecialchars($o['course'] ?: '-'); ?>"
                    data-hours="<?php $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed']; echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs'); ?>"
                    data-rendered="<?php echo json_encode((float)($o['hours_completed'] ?? 0)); ?>"
                    data-required="<?php echo json_encode((int)($o['hours_required'] ?? 0)); ?>"
                    onclick="if(window.openOjtProfileFromButton){ window.openOjtProfileFromButton(this); return false; }"
                    title="View">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-eval" class="tab-panel">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (empty($for_eval)): ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No OJTs ready for evaluation.</td></tr>
            <?php else: foreach ($for_eval as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php
                  $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed'];
                  echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs');
                ?></td>
                <td style="white-space:nowrap">
                  <button type="button" class="view-btn icon-btn"
                    data-view-context="for-eval"
                    data-user-status="<?php echo htmlspecialchars(strtolower((string)($o['user_status'] ?? ''))); ?>"
                    data-app-id="<?php echo (int)($o['application_id'] ?? 0); ?>"
                    data-id="<?php echo (int)$o['user_id']; ?>"
                    data-name="<?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?>"
                    data-school="<?php echo htmlspecialchars($o['school'] ?: '-'); ?>"
                    data-course="<?php echo htmlspecialchars($o['course'] ?: '-'); ?>"
                    data-hours="<?php $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed']; echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs'); ?>"
                    data-rendered="<?php echo json_encode((float)($o['hours_completed'] ?? 0)); ?>"
                    data-required="<?php echo json_encode((int)($o['hours_required'] ?? 0)); ?>"
                    onclick="if(window.openOjtProfileFromButton){ window.openOjtProfileFromButton(this); return false; }"
                    title="View">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                  <button class="evaluate-btn icon-btn"
                    data-id="<?php echo (int)$o['user_id']; ?>"
                    data-name="<?php echo htmlspecialchars(trim($o['first_name'].' '.$o['last_name'])); ?>"
                    data-school="<?php echo htmlspecialchars($o['school'] ?: '-'); ?>"
                    data-course="<?php echo htmlspecialchars($o['course'] ?: '-'); ?>"
                    data-hours="<?php $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed']; echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs'); ?>"
                    title="Evaluate">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-evaluated" class="tab-panel">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>Remarks</th><th>School Grade</th><th>View</th></tr>
          </thead>
          <tbody>
            <?php if (empty($completedArr)): ?>
              <tr><td colspan="8" style="text-align:center;color:#8a8f9d;padding:18px;">No evaluated OJTs.</td></tr>
            <?php else: foreach ($completedArr as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php
                  $hc_display = isset($o['hours_display']) ? $o['hours_display'] : (int)$o['hours_completed'];
                  echo htmlspecialchars($hc_display . ' / ' . (int)$o['hours_required'] . ' hrs');
                ?></td>
                <td><?php echo htmlspecialchars($o['remarks'] ?? '-'); ?></td>
                <td><?php
                  if (isset($o['school_eval']) && $o['school_eval'] !== null && $o['school_eval'] !== '') {
                    echo htmlspecialchars(number_format((float)$o['school_eval'], 2, '.', ''));
                  } else {
                    echo '-';
                  }
                ?></td>
                <td>
                  <button class="view-eval-btn icon-btn" data-eval-id="<?php echo isset($o['eval_id']) ? (int)$o['eval_id'] : 0; ?>" data-id="<?php echo (int)$o['user_id']; ?>" data-student-id="<?php echo isset($o['student_id']) ? (int)$o['student_id'] : 0; ?>" title="View Evaluation">
                    <svg viewBox="0 0 24 24" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

  <div id="viewOverlay" class="view-overlay" aria-hidden="true" style="display:none;">
    <div class="view-card" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
      <button class="view-close" aria-label="Close modal" id="viewCloseBtn">X</button>
      <div class="view-inner">
        <div class="view-header">
          <div class="view-avatar" id="view_avatar">👤</div>
          <div class="view-meta">
            <h2 class="view-name" id="view_name">Name</h2>
            <div class="view-submeta" id="view_statusline">
              <span id="view_status_badge" style="display:inline-flex;align-items:center;gap:8px;font-weight:700;color:inherit">—</span>
              <span id="view_department" style="color:#6b7280">—</span>
            </div>
            <div class="view-tools" aria-hidden="true">
              <button class="tool-link" id="printDTR" type="button">Print DTR</button>
            </div>
          </div>
        </div>

        <div class="view-tabs" role="tablist" aria-label="View tabs">
          <div class="view-tab active" data-view-tab="info">Information</div>
          <div class="view-tab" data-view-tab="late">DTR</div>
          <div class="view-tab" data-view-tab="atts">Attachments</div>
          <div class="view-tab" data-view-tab="journals">Weekly Journals</div>
        </div>

        <div id="view-panel-info" class="view-panel" style="display:block;">
          <div class="view-body">
            <div class="view-left">
              <div class="info-row"><div class="info-label">Age</div><div class="info-value" id="view_age">—</div></div>
              <div class="info-row"><div class="info-label">Birthday</div><div class="info-value" id="view_birthday">—</div></div>
              <div class="info-row"><div class="info-label">Address</div><div class="info-value" id="view_address">—</div></div>
              <div class="info-row"><div class="info-label">Phone</div><div class="info-value" id="view_phone">—</div></div>
              <div class="info-row"><div class="info-label">Email</div><div class="info-value" id="view_email">—</div></div>

              <div style="height:14px"></div>
              <div style="border-top:1px solid #f1f5f9;padding-top:12px;">
                <div class="info-row"><div class="info-label">College/University</div><div class="info-value" id="view_college">—</div></div>
                <div class="info-row"><div class="info-label">Course</div><div class="info-value" id="view_course">—</div></div>
                <div class="info-row"><div class="info-label">Year level</div><div class="info-value" id="view_year">—</div></div>
                <div class="info-row"><div class="info-label">School Address</div><div class="info-value" id="view_school_address">—</div></div>
                <div class="info-row"><div class="info-label">OJT Adviser</div><div class="info-value" id="view_adviser">—</div></div>
              </div>

              <div class="emergency">
                <div style="font-weight:700;margin-bottom:8px">Emergency Contact</div>
                <div class="info-row"><div class="info-label" style="width:120px">Name</div><div class="info-value" id="view_emg_name">—</div></div>
                <div class="info-row"><div class="info-label">Relationship</div><div class="info-value" id="view_emg_rel">—</div></div>
                <div class="info-row"><div class="info-label">Contact Number</div><div class="info-value" id="view_emg_contact">—</div></div>
              </div>
            </div>

            <div class="view-right">
              <div style="font-weight:700">Progress</div>
              <div class="progress-wrap" style="display:flex;flex-direction:row;gap:16px;align-items:center;justify-content:flex-start;margin-top:14px;">
                <div class="donut" style="position:relative;flex:0 0 auto;">
                  <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="48" stroke="#eef2f6" stroke-width="18" fill="none"></circle>
                    <circle id="donut_fore" cx="60" cy="60" r="48" stroke="#10b981" stroke-width="18" stroke-linecap="round" fill="none" stroke-dasharray="302" stroke-dashoffset="302"></circle>
                  </svg>
                  <div id="view_percent" style="position:absolute;inset:0;display:grid;place-items:center;font-weight:800;color:#111827;font-size:16px;pointer-events:none">0%</div>
                </div>
                <div style="flex:1;min-width:0;max-width:320px;margin-left:12px;">
                  <div style="font-size:14px;font-weight:700" id="view_hours_text">0 out of — hours</div>
                  <div style="font-size:12px;color:#6b7280;margin-top:6px;white-space:pre-line" id="view_dates">Date Started: —
  Expected End Date: —</div>
                </div>
              </div>

              <div style="margin-top:18px;display:flex;flex-direction:column;gap:8px;text-align:left;">
                <div style="font-weight:700">Assigned Office:</div>
                <div id="view_assigned_office">—</div>
                <div style="margin-top:6px;font-weight:700">Office Head:</div>
                <div id="view_office_head">—</div>
                <div style="margin-top:6px;font-weight:700">Email:</div>
                <div id="view_office_contact">—</div>
              </div>
            </div>
          </div>
        </div>

        <div id="view-panel-late" class="view-panel" style="display:none;padding:12px 6px;">
          <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;">
            <div style="overflow:auto">
              <table aria-label="DTR" style="width:100%;border-collapse:collapse;font-size:14px">
                <thead>
                  <tr style="background:#f3f4f6;color:#111">
                    <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center;font-weight:700;text-transform:uppercase">Date</th>
                    <th colspan="2" style="padding:10px;border:1px solid #eee;text-align:center">A.M.</th>
                    <th colspan="2" style="padding:10px;border:1px solid #eee;text-align:center">P.M.</th>
                    <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center">HOURS</th>
                    <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center">MINUTES</th>
                  </tr>
                  <tr style="background:#f3f4f6;color:#111">
                    <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">ARRIVAL</th>
                    <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">DEPARTURE</th>
                    <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">ARRIVAL</th>
                    <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">DEPARTURE</th>
                  </tr>
                </thead>
                <tbody id="late_dtr_tbody">
                  <tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">No DTR records yet.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div id="view-panel-atts" class="view-panel" style="display:none;padding:12px 6px;">
          <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;min-height:160px;">
            <div id="view_attachments_list" style="display:flex;flex-direction:column;gap:8px;"></div>
          </div>
        </div>

        <div id="view-panel-journals" class="view-panel" style="display:none;padding:12px 6px;">
          <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;min-height:160px;">
            <div style="overflow:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                  <tr style="background:#f3f4f6;color:#111;">
                    <th style="padding:10px;border:1px solid #eee;text-align:left;">DATE UPLOADED</th>
                    <th style="padding:10px;border:1px solid #eee;text-align:left;">WEEK</th>
                    <th style="padding:10px;border:1px solid #eee;text-align:left;">ATTACHMENT</th>
                  </tr>
                </thead>
                <tbody id="view_journals_tbody">
                  <tr class="empty"><td colspan="3" style="padding:18px;text-align:center;color:#6b7280">No weekly journals found.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

<div id="evalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:9999;">
  <div style="background:#fff;width:820px;max-width:95%;border-radius:8px;padding:18px;box-shadow:0 8px 30px rgba(0,0,0,0.15);height:80vh;max-height:80vh;overflow-y:auto;">
    <!-- OJT info shown above the evaluation scale -->
    <div id="evalInfo" style="margin-bottom:12px;padding:10px;border-radius:6px;background:#f7f8fb;border:1px solid #eef1f6;color:#000;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <!-- left: name (bold) and course underneath -->
        <div style="font-size:15px;color:#000;min-width:220px;">
          <div>
            <span style="color:#000;">Name:</span>
            <span id="evalNameText" style="font-weight:700;margin-left:6px;">—</span>
          </div>
          <div id="evalCourse" style="margin-top:6px;color:#000;opacity:0.9;">Course: —</div>
        </div>
        <!-- right: school and hours stacked and right-aligned -->
        <div style="text-align:right;color:#000;opacity:0.85;min-width:200px;">
          <div id="evalSchool">School: —</div>
          <div id="evalHours" style="margin-top:6px;">Hours: —</div>
        </div>
      </div>
    </div>
     <h3 style="margin:0 0 8px 0;">Evaluation Scale</h3>
    <p style="margin:6px 0 12px 0;line-height:1.4;">
      Please rate the trainee's performance using the following scale.<br><br>
      <strong>5 - Outstanding:</strong> Consistently exceeds expectations. Performance is exceptional.<br>
      <strong>4 - Very Good:</strong> Consistently meets all expectations. Performance is of high quality.<br>
      <strong>3 - Good:</strong> Meets expectations most of the time. Performance is satisfactory.<br>
      <strong>2 - Fair:</strong> Sometimes fails to meet expectations. Requires improvement and supervision.<br>
      <strong>1 - Poor:</strong> Consistently fails to meet expectations. Performance is unacceptable.<br>
      <strong>N/A:</strong> Not Applicable. The trainee did not have the opportunity to demonstrate this skill.
    </p>

    <!-- Interactive evaluation grid (no image) -->
    <div style="overflow:auto;margin-top:6px;">
      <table id="evalGrid" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
          <tr style="background:#2f3459;color:#fff;">
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:left">Competency</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">5</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">4</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">3</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">2</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">1</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:60px">N/A</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $competencies = [
              'Application of Knowledge: Applies academic theories to practical work.',
              'Quality of Work: Produces accurate, thorough, and neat work.',
              'Job-Specific Skills: Performs tasks specific to the role effectively.',
              'Quantity of Work: Completes satisfactory volume of work on time.',
              'Learning & Adaptability: Learns new tasks, procedures, and systems quickly.'
            ];
            foreach ($competencies as $idx => $text):
              $key = 'c' . ($idx+1);
          ?>
          <tr data-key="<?= $key ?>">
            <td style="padding:10px;border:1px solid #eef1f6;vertical-align:middle;"><?= htmlspecialchars($text) ?></td>
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
                <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="<?= $s ?>"
                  aria-label="Score <?= $s ?>"
                  style="width:36px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                </button>
              </td>
            <?php endfor; ?>
            <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
              <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="NA"
                aria-label="Not applicable"
                style="width:44px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                N/A
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Additional Skill table -->
    <div style="overflow:auto;margin-top:6px;">
      <table id="skillGrid" style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:8px;">
        <thead>
          <tr style="background:#2f3459;color:#fff;">
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:left">Skill</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">5</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">4</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">3</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">2</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">1</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:60px">N/A</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $skills = [
              'Communication (Oral & Written): Expresses ideas clearly, professionally, and actively listens to others.',
              'Teamwork & Collaboration: Works cooperatively with supervisors and colleagues; contributes positively to the team.',
              'Problem-Solving: Analyzes situations, identifies problems, and suggests logical solutions.',
              'Critical Thinking: Gathers and evaluates information to make sound judgments.',
              'Initiative & Resourcefulness: Seeks new responsibilities, asks relevant questions, and works independently when appropriate.'
            ];
            foreach ($skills as $idx => $text):
              $key = 's' . ($idx+1);
          ?>
          <tr data-key="<?= $key ?>">
            <td style="padding:10px;border:1px solid #eef1f6;vertical-align:middle;"><?= htmlspecialchars($text) ?></td>
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
                <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="<?= $s ?>"
                  aria-label="Score <?= $s ?>"
                  style="width:36px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                </button>
              </td>
            <?php endfor; ?>
            <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
              <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="NA"
                aria-label="Not applicable"
                style="width:44px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                N/A
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Additional Trait table -->
    <div style="overflow:auto;margin-top:6px;">
      <table id="traitGrid" style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:8px;">
        <thead>
          <tr style="background:#2f3459;color:#fff;">
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:left">Trait</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">5</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">4</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">3</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">2</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:48px">1</th>
            <th style="padding:8px;border:1px solid #e6e9f2;text-align:center;width:60px">N/A</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $traits = [
              'Punctuality & Attendance: Adheres to the agreed-upon work schedule and informs the supervisor of any absences.',
              'Professional Conduct: Observes company policies, follows instructions, and maintains confidentiality.',
              'Attitude & Receptiveness: Maintains a positive attitude and accepts constructive feedback gracefully.',
              'Time Management: Prioritizes tasks effectively to manage workload.',
              'Professional Appearance: Adheres to the company\'s dress code and grooming standards.'
            ];
            foreach ($traits as $idx => $text):
              $key = 't' . ($idx+1);
          ?>
          <tr data-key="<?= $key ?>">
            <td style="padding:10px;border:1px solid #eef1f6;vertical-align:middle;"><?= htmlspecialchars($text) ?></td>
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
                <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="<?= $s ?>"
                  aria-label="Score <?= $s ?>"
                  style="width:36px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                </button>
              </td>
            <?php endfor; ?>
            <td style="padding:6px;border:1px solid #eef1f6;text-align:center;">
              <button type="button" class="score-cell" data-key="<?= $key ?>" data-score="NA"
                aria-label="Not applicable"
                style="width:44px;height:30px;border-radius:4px;border:1px solid #cfd6ea;background:#fff;cursor:pointer">
                N/A
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:10px;">
      <h3 style="margin:0 0 6px 0;">Overall Assessment & Recommendations</h3>

      <label style="display:block;margin-top:8px;margin-bottom:6px;font-weight:700">What are the trainee's most significant strengths?</label>
      <textarea id="overallStrengths" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd" required></textarea>

      <label style="display:block;margin-top:8px;margin-bottom:6px;font-weight:700">What specific areas require improvement?</label>
      <textarea id="improvementAreas" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd" required></textarea>

      <label style="display:block;margin-top:8px;margin-bottom:6px;font-weight:700">Do you have other comments on the trainee's overall performance?</label>
      <textarea id="otherComments" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd" required></textarea>

      <label style="display:block;margin-top:8px;margin-bottom:6px;font-weight:700">Would you consider hiring this trainee for a full-time position (if one were available)?</label>
      <div style="display:flex;gap:12px;align-items:center;margin-bottom:6px">
        <label style="display:inline-flex;align-items:center;gap:6px"><input type="radio" name="hireDecision" value="Yes"> Yes</label>
        <label style="display:inline-flex;align-items:center;gap:6px"><input type="radio" name="hireDecision" value="Maybe"> Maybe</label>
        <label style="display:inline-flex;align-items:center;gap:6px"><input type="radio" name="hireDecision" value="No"> No</label>
      </div>
    </div>


    <div class="eval-divider">
      <label style="display:block;margin-bottom:6px;font-weight:700">School Evaluation Grade</label>
      <input id="evalSchoolGrade" type="number" step="0.01" min="0" max="999" style="width:180px;padding:8px;border-radius:6px;border:1px solid #ddd" aria-label="School Evaluation Grade">
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
      <button id="evalCancel" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancel</button>
      <button id="evalSubmit" style="padding:8px 12px;border-radius:6px;border:0;background:#4f4aa6;color:#fff;cursor:pointer">Submit Evaluation</button>
    </div>
  </div>
</div>
<script>
(function(){
  // Fallback opener: if the main profile script fails to initialize, keep View buttons functional.
  if (typeof window.openOjtProfileFromButton === 'function') return;
  window.openOjtProfileFromButton = function(btn){
    const viewContext = (btn.getAttribute('data-view-context') || 'ongoing').toLowerCase();
    const isForEval = viewContext === 'for-eval';
    const statusLabel = isForEval ? 'For Evaluation' : 'Ongoing';
    const ov = document.getElementById('viewOverlay');
    if (!ov) {
      alert('View popup error: #viewOverlay not found.');
      return false;
    }

    const setText = function(id, value){
      const el = document.getElementById(id);
      if (el) el.textContent = value || '—';
    };

    setText('view_name', btn.getAttribute('data-name') || '—');
    setText('view_college', btn.getAttribute('data-school') || '—');
    setText('view_course', btn.getAttribute('data-course') || '—');
    setText('view_hours_text', btn.getAttribute('data-hours') || '—');
    setText('view_status_badge', statusLabel);

    const printBtn = document.getElementById('printDTR');
    if (printBtn) printBtn.style.display = isForEval ? 'inline-flex' : 'none';

    ov.style.display = 'flex';
    ov.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    return false;
  };
})();
</script>
<script>
(function(){
  const OFFICE_HEAD_NAME = <?php echo json_encode($user_name); ?>;
  const OFFICE_HEAD_EMAIL = <?php echo json_encode($user_email); ?>;
  const OFFICE_NAME = <?php echo json_encode($office['office_name'] ?? ''); ?>;
  let currentViewUserId = 0;

  function reportViewError(context, err){
    const message = (err && err.message) ? err.message : String(err || 'Unknown error');
    console.error('[OJT View Error] ' + context + ':', err);
    alert('View popup error (' + context + '): ' + message);
  }

  function qs(id){ return document.getElementById(id); }
  function getViewContext(btn){
    return ((btn && btn.getAttribute('data-view-context')) || 'ongoing').toLowerCase();
  }
  function setPrintVisibility(isForEval){
    const printBtn = qs('printDTR');
    if (printBtn) printBtn.style.display = isForEval ? 'inline-flex' : 'none';
  }
  function formatStatusLabel(rawStatus){
    const s = (rawStatus || '').toString().trim().toLowerCase();
    if (!s) return '—';
    return s
      .replace(/_/g, ' ')
      .replace(/\b\w/g, function(ch){ return ch.toUpperCase(); });
  }
  function formatHoursMax2(value){
    const n = Number(value);
    if (!isFinite(n)) return '—';
    return n.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
  }
  function esc(v){
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function normalizeFile(filePath){
    const v = (filePath || '').toString().trim();
    if (!v) return '';
    if (/^(https?:)?\/\//i.test(v) || v.startsWith('data:')) return v;
    if (v.startsWith('../') || v.startsWith('./') || v.startsWith('/')) return v;
    return '../' + v.replace(/^\/+/, '');
  }
  function formatDateMMDDYYYY(value){
    const s = (value || '').toString().trim();
    if (!s) return '-';

    // Handles YYYY-MM-DD or YYYY/MM/DD
    const m = s.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/);
    if (m) {
      const mm = m[2].padStart(2, '0');
      const dd = m[3].padStart(2, '0');
      const yyyy = m[1];
      return mm + '/' + dd + '/' + yyyy;
    }

    const d = new Date(s);
    if (isNaN(d.getTime())) return s;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const yyyy = d.getFullYear();
    return mm + '/' + dd + '/' + yyyy;
  }
  function setDonut(percent){
    const p = Math.max(0, Math.min(100, Number(percent) || 0));
    const circle = qs('donut_fore');
    if (!circle) return;
    const radius = 48;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (p / 100) * circumference;
    circle.style.strokeDasharray = circumference;
    circle.style.strokeDashoffset = offset;
    if (qs('view_percent')) qs('view_percent').textContent = Math.round(p) + '%';
  }
  function calcAge(birthday){
    if (!birthday) return '';
    const d = new Date(birthday);
    if (isNaN(d.getTime())) return '';
    const now = new Date();
    let age = now.getFullYear() - d.getFullYear();
    const m = now.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
    return age >= 0 ? String(age) : '';
  }
  function showOverlay(){
    const ov = qs('viewOverlay');
    if (!ov) {
      throw new Error('View overlay element #viewOverlay was not found in DOM.');
    }
    ov.style.display = 'flex';
    ov.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
  }
  function hideOverlay(){
    const ov = qs('viewOverlay');
    if (!ov) return;
    ov.style.display = 'none';
    ov.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }
  function switchTab(tab){
    document.querySelectorAll('[data-view-tab]').forEach(function(el){ el.classList.remove('active'); });
    const targetBtn = document.querySelector('[data-view-tab="' + tab + '"]');
    if (targetBtn) targetBtn.classList.add('active');
    ['info','late','atts','journals'].forEach(function(name){
      const panel = qs('view-panel-' + name);
      if (panel) panel.style.display = (name === tab) ? 'block' : 'none';
    });
  }
  function resetModal(){
    [
      'view_name','view_age','view_birthday','view_address','view_phone','view_email','view_college','view_course','view_year',
      'view_school_address','view_adviser','view_emg_name','view_emg_rel','view_emg_contact','view_hours_text','view_dates',
      'view_assigned_office','view_office_head','view_office_contact','view_status_badge','view_department'
    ].forEach(function(id){ const el = qs(id); if (el) el.textContent = '—'; });
    const av = qs('view_avatar'); if (av) av.innerHTML = '👤';
    const list = qs('view_attachments_list'); if (list) list.innerHTML = '';
    const dtrBody = qs('late_dtr_tbody'); if (dtrBody) dtrBody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">No DTR records yet.</td></tr>';
    const journalsBody = qs('view_journals_tbody');
    if (journalsBody) journalsBody.innerHTML = '<tr class="empty"><td colspan="3" style="padding:18px;text-align:center;color:#6b7280">No weekly journals found.</td></tr>';
    setPrintVisibility(false);
    setDonut(0);
    switchTab('info');
  }
  function formatDateLong(value){
    const s = (value || '').toString().trim();
    if (!s) return '—';
    const d = new Date(s);
    if (isNaN(d.getTime())) return s;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }
  function renderJournals(rows){
    const tbody = qs('view_journals_tbody');
    if (!tbody) return;
    const data = Array.isArray(rows) ? rows : [];
    if (!data.length) {
      tbody.innerHTML = '<tr class="empty"><td colspan="3" style="padding:18px;text-align:center;color:#6b7280">No weekly journals found.</td></tr>';
      return;
    }

    tbody.innerHTML = data.map(function(r){
      const uploaded = formatDateLong(r.date_uploaded || '');
      const week = r.week_coverage || '—';
      const attRaw = (r.attachment || '').toString().trim();
      let attHtml = '—';
      if (attRaw) {
        const href = normalizeFile(attRaw);
        const parts = attRaw.replace(/\\/g, '/').split('/');
        const fileName = parts.length ? parts[parts.length - 1] : attRaw;
        // Open attachment in a new page/tab.
        attHtml = '<a href="' + esc(href) + '" target="_blank" rel="noopener noreferrer" style="color:#1d4ed8;text-decoration:underline;">' + esc(fileName) + '</a>';
      }

      return '<tr>' +
        '<td style="padding:10px;border:1px solid #eee;">' + esc(uploaded) + '</td>' +
        '<td style="padding:10px;border:1px solid #eee;">' + esc(week) + '</td>' +
        '<td style="padding:10px;border:1px solid #eee;">' + attHtml + '</td>' +
      '</tr>';
    }).join('');
  }
  function loadJournals(userId){
    const tbody = qs('view_journals_tbody');
    if (!tbody || !userId) return;
    tbody.innerHTML = '<tr class="empty"><td colspan="3" style="padding:18px;text-align:center;color:#6b7280">Loading...</td></tr>';

    fetch('office_head_ojts.php?ajax=view_journals&student_user_id=' + encodeURIComponent(userId) + '&_ts=' + Date.now(), { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.success) {
          renderJournals([]);
          return;
        }
        renderJournals(j.rows || []);
      })
      .catch(function(){
        tbody.innerHTML = '<tr class="empty"><td colspan="3" style="padding:18px;text-align:center;color:#6b7280">Unable to load weekly journals.</td></tr>';
      });
  }
  function resolveMoaByCollege(college){
    const school = (college || '').toString().trim();
    if (!school) return Promise.resolve('');
    const url = 'office_head_ojts.php?ajax=resolve_moa&college=' + encodeURIComponent(school) + '&_ts=' + Date.now();
    return fetch(url, { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.success) return '';
        return (j.moa_file || '').toString().trim();
      })
      .catch(function(){ return ''; });
  }
  function renderAttachments(d){
    const list = qs('view_attachments_list');
    if (!list) return;
    list.innerHTML = '';
    const atts = [
      { label: 'Letter of Intent', file: d.letter_of_intent },
      { label: 'Endorsement Letter', file: d.endorsement_letter },
      { label: 'Resume', file: d.resume },
      { label: 'MOA', file: d.moa_file || d.moa },
      { label: 'Picture', file: d.picture }
    ].filter(function(a){ return !!(a.file && String(a.file).trim()); });

    if (!atts.length) {
      list.innerHTML = '<div style="color:#6b7280">No attachments available.</div>';
      return;
    }

    atts.forEach(function(a){
      const href = normalizeFile(a.file);
      const row = document.createElement('div');
      row.style.display = 'flex';
      row.style.justifyContent = 'space-between';
      row.style.alignItems = 'center';
      row.style.padding = '6px 0';

      const left = document.createElement('div');
      left.style.fontSize = '14px';
      left.style.fontWeight = '600';
      left.textContent = a.label;

      const right = document.createElement('div');
      right.style.display = 'flex';
      right.style.gap = '8px';

      const view = document.createElement('a');
      view.href = href;
      view.target = '_blank';
      view.rel = 'noopener noreferrer';
      view.className = 'tool-link';
      view.textContent = 'View';

      const dl = document.createElement('a');
      dl.href = href;
      dl.className = 'tool-link';
      dl.download = '';
      dl.textContent = 'Download';

      right.appendChild(view);
      right.appendChild(dl);
      row.appendChild(left);
      row.appendChild(right);
      list.appendChild(row);
    });
  }
  function renderAvatar(picture){
    const av = qs('view_avatar');
    if (!av) return;
    const src = normalizeFile(picture);
    if (!src) { av.innerHTML = '👤'; return; }
    av.innerHTML = '';
    const img = document.createElement('img');
    img.src = src;
    img.alt = 'Profile';
    img.onerror = function(){ av.innerHTML = '👤'; };
    av.appendChild(img);
  }
  function loadDtr(userId, requiredHours, userStatus){
    function normalizeDateOnly(value){
      const s = (value || '').toString().trim();
      if (!s) return '';
      const d = new Date(s);
      if (isNaN(d.getTime())) return '';
      return d.toISOString().slice(0, 10);
    }

    function addBusinessDaysFromNext(startDateStr, businessDays){
      let dt = new Date(startDateStr + 'T00:00:00');
      if (isNaN(dt.getTime())) return null;
      let counted = 0;
      while (counted < businessDays) {
        dt.setDate(dt.getDate() + 1);
        const dow = dt.getDay();
        if (dow >= 1 && dow <= 5) counted++;
      }
      return dt;
    }

    const tbody = qs('late_dtr_tbody');
    if (!tbody || !userId) return;
    tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">Loading...</td></tr>';
    const today = new Date().toISOString().slice(0,10);
    fetch('../hr_actions.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action:'get_dtr_by_range', from:'1900-01-01', to: today, user_id: Number(userId) })
    })
    .then(function(r){ return r.json(); })
    .then(function(j){
      const rows = (j && j.success && Array.isArray(j.rows)) ? j.rows : [];
      if (!rows.length) {
        tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">No DTR records.</td></tr>';
        if (qs('view_dates')) qs('view_dates').textContent = 'Date Started: —\nExpected End Date: —';
        return;
      }

      let totalH = 0;
      let totalM = 0;
      rows.forEach(function(r){
        totalH += Number(r.hours || 0);
        totalM += Number(r.minutes || 0);
      });
      totalH += Math.floor(totalM / 60);
      totalM = totalM % 60;
      const rendered = totalH + (totalM / 60);
      const req = Number(requiredHours || 0);
      if (qs('view_hours_text')) qs('view_hours_text').textContent = formatHoursMax2(rendered) + ' out of ' + formatHoursMax2(req) + ' hours';
      setDonut(req > 0 ? (rendered / req) * 100 : 0);

      const dates = rows.map(function(r){ return normalizeDateOnly(r.log_date); }).filter(Boolean).sort();
      const started = dates.length ? dates[0] : '';
      const latest = dates.length ? dates[dates.length - 1] : '';
      const normalizedStatus = (userStatus || '').toString().trim().toLowerCase();

      let expected = '';
      if (latest) {
        const isCompleted = normalizedStatus === 'completed';
        const metRequiredHours = req > 0 && rendered >= req;

        if (isCompleted || metRequiredHours) {
          // If completed/already reached requirement, expected end is latest logged DTR date.
          expected = latest;
        } else if (req > 0) {
          const remainingHours = Math.max(0, req - rendered);
          const businessDaysNeeded = Math.ceil(remainingHours / 8);
          if (businessDaysNeeded <= 0) {
            expected = latest;
          } else {
            const projected = addBusinessDaysFromNext(latest, businessDaysNeeded);
            expected = projected ? projected.toISOString().slice(0, 10) : '';
          }
        }
      }

      if (qs('view_dates')) {
        qs('view_dates').textContent =
          'Date Started: ' + (started ? formatDateMMDDYYYY(started) : '—') +
          '\nExpected End Date: ' + (expected ? formatDateMMDDYYYY(expected) : '—');
      }

      tbody.innerHTML = rows.map(function(r){
        return '<tr>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(formatDateMMDDYYYY(r.log_date || '')) + '</td>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(r.am_in || '-') + '</td>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(r.am_out || '-') + '</td>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(r.pm_in || '-') + '</td>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(r.pm_out || '-') + '</td>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(r.hours || '0') + '</td>' +
          '<td style="padding:8px;border:1px solid #eee;text-align:center">' + esc(r.minutes || '0') + '</td>' +
        '</tr>';
      }).join('');
    })
    .catch(function(){
      tbody.innerHTML = '<tr class="empty"><td colspan="7" style="padding:18px;text-align:center;color:#6b7280">Unable to load DTR.</td></tr>';
    });
  }
  function openFromButton(btn){
    try {
      const appId = Number(btn.getAttribute('data-app-id') || 0);
      const userId = Number(btn.getAttribute('data-id') || 0);
      const viewContext = getViewContext(btn);
      const isForEval = viewContext === 'for-eval';
      const rowUserStatus = (btn.getAttribute('data-user-status') || '').toLowerCase();
      const statusLabel = formatStatusLabel(rowUserStatus) || (isForEval ? 'For Evaluation' : 'Ongoing');

      if (!userId) {
        throw new Error('Missing user id on view button (data-id).');
      }

      currentViewUserId = userId;
      resetModal();
      showOverlay();

      if (qs('view_name')) qs('view_name').textContent = btn.getAttribute('data-name') || '—';
      if (qs('view_college')) qs('view_college').textContent = btn.getAttribute('data-school') || '—';
      if (qs('view_course')) qs('view_course').textContent = btn.getAttribute('data-course') || '—';
      if (qs('view_hours_text')) {
        const renderedInit = Number(btn.getAttribute('data-rendered') || 0);
        const requiredInit = Number(btn.getAttribute('data-required') || 0);
        qs('view_hours_text').textContent = formatHoursMax2(renderedInit) + ' out of ' + formatHoursMax2(requiredInit) + ' hours';
      }
      if (qs('view_status_badge')) qs('view_status_badge').textContent = statusLabel;
      setPrintVisibility(isForEval);

      const req = Number(btn.getAttribute('data-required') || 0);
      const userStatus = (btn.getAttribute('data-user-status') || '').toLowerCase();
      loadDtr(userId, req, userStatus);
      loadJournals(userId);

      if (!appId) {
        if (qs('view_status_badge')) qs('view_status_badge').textContent = statusLabel;
        if (qs('view_department')) qs('view_department').textContent = OFFICE_NAME || '—';
        if (qs('view_assigned_office')) qs('view_assigned_office').textContent = OFFICE_NAME || '—';
        if (qs('view_office_head')) qs('view_office_head').textContent = OFFICE_HEAD_NAME || '—';
        if (qs('view_office_contact')) qs('view_office_contact').textContent = OFFICE_HEAD_EMAIL || '—';
        if (qs('view_dates')) qs('view_dates').textContent = 'Date Started: —\nExpected End Date: —';
        const renderedFallback = Number(btn.getAttribute('data-rendered') || 0);
        if (qs('view_hours_text')) qs('view_hours_text').textContent = formatHoursMax2(renderedFallback) + ' out of ' + formatHoursMax2(req) + ' hours';
        setDonut(req > 0 ? (renderedFallback / req) * 100 : 0);
        renderAttachments({});
        return;
      }

      fetch('../hr_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_application', application_id: appId })
      })
      .then(function(r){
        return r.text().then(function(txt){
          let parsed = null;
          try {
            parsed = JSON.parse(txt);
          } catch (e) {
            throw new Error('Invalid JSON from hr_actions.php. Response starts with: ' + (txt || '').slice(0, 120));
          }
          if (!r.ok) {
            throw new Error((parsed && parsed.message) ? parsed.message : ('HTTP ' + r.status));
          }
          return parsed;
        });
      })
      .then(function(j){
        if (!j || !j.success || !j.data) {
          throw new Error((j && j.message) ? j.message : 'Failed to load profile data.');
        }
        const d = j.data;
        const s = d.student || {};

        if (qs('view_name')) qs('view_name').textContent = ((s.first_name || '') + ' ' + (s.last_name || '')).trim() || (btn.getAttribute('data-name') || '—');
        if (qs('view_status_badge')) {
          const apiUserStatus = (d.user_status || '').toString().toLowerCase();
          qs('view_status_badge').textContent = formatStatusLabel(apiUserStatus || rowUserStatus || d.status || 'ongoing');
        }
        if (qs('view_department')) qs('view_department').textContent = d.office1 || d.office2 || OFFICE_NAME || '—';

        renderAvatar(d.picture || '');

        if (qs('view_age')) qs('view_age').textContent = (s.age != null && s.age !== '') ? s.age : calcAge(s.birthday || '');
        if (qs('view_birthday')) qs('view_birthday').textContent = s.birthday || '—';
        if (qs('view_address')) qs('view_address').textContent = s.address || '—';
        if (qs('view_phone')) qs('view_phone').textContent = s.contact_number || '—';
        if (qs('view_email')) qs('view_email').textContent = s.email || '—';

        if (qs('view_college')) qs('view_college').textContent = s.college || '—';
        if (qs('view_course')) qs('view_course').textContent = s.course || '—';
        if (qs('view_year')) qs('view_year').textContent = s.year_level || '—';
        if (qs('view_school_address')) qs('view_school_address').textContent = s.school_address || '—';
        if (qs('view_adviser')) qs('view_adviser').textContent = (s.ojt_adviser || '') + (s.adviser_contact ? ' | ' + s.adviser_contact : '') || '—';
        if (qs('view_emg_name')) qs('view_emg_name').textContent = s.emergency_name || '—';
        if (qs('view_emg_rel')) qs('view_emg_rel').textContent = s.emergency_relation || '—';
        if (qs('view_emg_contact')) qs('view_emg_contact').textContent = s.emergency_contact || '—';

        const req = Number(s.total_hours_required || btn.getAttribute('data-required') || 0);
        const rendered = Number(s.hours_rendered || btn.getAttribute('data-rendered') || 0);
        if (qs('view_hours_text')) qs('view_hours_text').textContent = formatHoursMax2(rendered) + ' out of ' + formatHoursMax2(req) + ' hours';
        setDonut(req > 0 ? (rendered / req) * 100 : 0);

        if (qs('view_assigned_office')) qs('view_assigned_office').textContent = d.office1 || d.office2 || OFFICE_NAME || '—';
        if (qs('view_office_head')) qs('view_office_head').textContent = OFFICE_HEAD_NAME || '—';
        if (qs('view_office_contact')) qs('view_office_contact').textContent = OFFICE_HEAD_EMAIL || '—';

        resolveMoaByCollege(s.college || '').then(function(moaFromSchool){
          // Priority: MOA matched by school from moa table, fallback to application moa_file.
          d.moa_file = moaFromSchool || d.moa_file || '';
          renderAttachments(d);
        });
      })
      .catch(function(err){
        reportViewError('profile-fetch', err);
        hideOverlay();
      });
    } catch (err) {
      reportViewError('openFromButton', err);
      hideOverlay();
    }
  }

  window.openOjtProfileFromButton = openFromButton;

  document.addEventListener('click', function(e){
    try {
      const btn = e.target.closest && e.target.closest('.view-btn');
      if (!btn) return;
      e.preventDefault();
      openFromButton(btn);
    } catch (err) {
      reportViewError('view-click', err);
    }
  });

  document.querySelectorAll('[data-view-tab]').forEach(function(tab){
    tab.addEventListener('click', function(){
      switchTab(this.getAttribute('data-view-tab'));
    });
  });

  const printBtn = qs('printDTR');
  if (printBtn) {
    printBtn.addEventListener('click', function(){
      if (!currentViewUserId) return;
      window.open('print_dtr.php?student_id=' + encodeURIComponent(currentViewUserId), '_blank');
    });
  }

  const closeBtn = qs('viewCloseBtn');
  if (closeBtn) closeBtn.addEventListener('click', hideOverlay);
  const overlay = qs('viewOverlay');
  if (overlay) overlay.addEventListener('click', function(e){ if (e.target === overlay) hideOverlay(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hideOverlay(); });
})();
</script>
<script>
  // store selections per modal open
  const _evalStore = {};

  // delegated handler for score cells
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.score-cell');
    if (!btn) return;

    const key = btn.getAttribute('data-key');
    const score = btn.getAttribute('data-score');

    // save selection
    _evalStore[key] = score;

    // visually mark row: clear all then set selected
    const row = btn.closest('tr[data-key]');
    if (!row) return;
    row.querySelectorAll('.score-cell').forEach(b => {
      b.style.background = '#fff';
      b.style.borderColor = '#cfd6ea';
      b.style.color = '#000';
      b.textContent = ''; // keep ALL buttons empty by default (including N/A)
    });

    // mark clicked
    btn.style.background = '#4f4aa6';
    btn.style.color = '#fff';
    btn.style.borderColor = '#4f4aa6';
    btn.textContent = '✓';
  });

  // open modal handler
  document.addEventListener('click', function (e) {
    const btn = e.target.closest && e.target.closest('.evaluate-btn');
    if (!btn) return;
    const traineeId = btn.getAttribute('data-id');
    const modal = document.getElementById('evalModal');
    modal.dataset.traineeId = traineeId;

    // populate info
    document.getElementById('evalNameText').textContent = (btn.getAttribute('data-name') || '—');
    document.getElementById('evalSchool').textContent = 'School: ' + (btn.getAttribute('data-school') || '—');
    document.getElementById('evalCourse').textContent = 'Course: ' + (btn.getAttribute('data-course') || '—');
    document.getElementById('evalHours').textContent = 'Hours: ' + (btn.getAttribute('data-hours') || '—');

    // reset previous selections/remarks
    Object.keys(_evalStore).forEach(k => delete _evalStore[k]);
    modal.querySelectorAll('.score-cell').forEach(b => {
      b.style.background = '#fff';
      b.style.borderColor = '#cfd6ea';
      b.style.color = '#000';
      b.textContent = ''; // empty by default (including N/A)
    });
    // reset overall assessment fields
    const osEl = document.getElementById('overallStrengths'); if (osEl) osEl.value = '';
    const iaEl = document.getElementById('improvementAreas'); if (iaEl) iaEl.value = '';
    const ocEl = document.getElementById('otherComments'); if (ocEl) ocEl.value = '';
    document.querySelectorAll('input[name="hireDecision"]').forEach(r => r.checked = false);
    // reset school grade
    const gradeEl = document.getElementById('evalSchoolGrade'); if (gradeEl) gradeEl.value = '';

    modal.style.display = 'flex';
  });

  // close modal
  document.getElementById('evalCancel').addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('evalModal').style.display = 'none';
  });

  // submit evaluation — enforce all competencies selected
  document.getElementById('evalSubmit').addEventListener('click', function (e) {
    e.preventDefault();
    const modal = document.getElementById('evalModal');
    const traineeId = modal.dataset.traineeId;
    if (!traineeId) { alert('Trainee ID not set'); return; }

    // build payload
    const payload = { trainee_id: traineeId, scores: {} };
    payload.overall_strengths = (document.getElementById('overallStrengths').value || '').trim();
    payload.improvement_areas = (document.getElementById('improvementAreas').value || '').trim();
    payload.other_comments = (document.getElementById('otherComments').value || '').trim();
    const hireSel = document.querySelector('input[name="hireDecision"]:checked');
    payload.hire_decision = hireSel ? hireSel.value : '';
    // include school evaluation grade
    const gradeEl = document.getElementById('evalSchoolGrade');
    const gradeValRaw = gradeEl ? (gradeEl.value || '').toString().trim() : '';
    payload.school_eval = gradeValRaw;
    const rows = Array.from(modal.querySelectorAll('tr[data-key]'));
    rows.forEach(row => {
      const key = row.getAttribute('data-key');
      payload.scores[key] = (typeof _evalStore[key] !== 'undefined') ? _evalStore[key] : null;
    });

    // REQUIRE: every competency must have a selection (numeric or NA)
    const allRated = rows.every(row => {
      const k = row.getAttribute('data-key');
      return typeof payload.scores[k] !== 'undefined' && payload.scores[k] !== null;
    });
    if (!allRated) {
      alert('Please rate all competencies (choose 5/4/3/2/1 or N/A) before submitting.');
      return;
    }

    // REQUIRE: overall assessment fields must be provided
    if (!payload.overall_strengths || !payload.improvement_areas || !payload.other_comments) {
      alert('Please complete the Overall Assessment textboxes.');
      return;
    }
    if (!payload.hire_decision) {
      alert('Please select a hire decision (Yes / Maybe / No).');
      return;
    }

    // REQUIRE: School Evaluation Grade must be provided and numeric
    if (!gradeValRaw) {
      alert('Please enter the School Evaluation Grade.');
      return;
    }
    const gradeVal = parseFloat(gradeValRaw);
    if (isNaN(gradeVal)) {
      alert('School Evaluation Grade must be a number (decimals allowed).');
      return;
    }
    // normalize payload value to number
    payload.school_eval = gradeVal;

    if (!confirm('Are you sure you want to submit this evaluation?')) {
      return;
    }

    // disable submit to avoid duplicates
    this.disabled = true;

    fetch('office_head_ojts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json().catch(() => ({ success: false, message: 'Invalid JSON response' })))
      .then(resp => {
        this.disabled = false;
        if (resp && resp.success) {
          // go to evaluated tab so the evaluated list is shown
          // use query param so server-rendered evaluated rows appear
          window.location.href = 'office_head_ojts.php?tab=evaluated';
        } else {
          alert('Submit failed: ' + (resp && resp.message ? resp.message : 'Unknown error'));
        }
      })
      .catch(err => {
        this.disabled = false;
        console.error(err);
        alert('Submit failed. Check console.');
      });
  });

  // restrict input for School Evaluation Grade: only digits and single dot,
  // and limit integer part to max 3 digits (allow decimals after dot)
  (function () {
    const g = document.getElementById('evalSchoolGrade');
    if (!g) return;

    function sanitize(val) {
      if (!val) return '';
      // remove non-digit/dot
      val = val.replace(/[^0-9.]/g, '');
      // collapse multiple dots into first
      const parts = val.split('.');
      const intPart = (parts[0] || '').slice(0, 3); // limit to 3 digits
      let decPart = parts.slice(1).join('');
      if (decPart.length) decPart = '.' + decPart;
      return intPart + decPart;
    }

    g.addEventListener('keydown', function (e) {
      const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
      if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
      if (e.key >= '0' && e.key <= '9') {
        // determine prospective value after key
        const selStart = this.selectionStart || 0;
        const selEnd = this.selectionEnd || 0;
        const cur = this.value || '';
        const next = cur.slice(0, selStart) + e.key + cur.slice(selEnd);
        const int = next.split('.')[0] || '';
        if (int.replace(/^0+/, '').length > 3 && int.length > 3) {
          // more than 3 digits in integer part -> prevent
          e.preventDefault();
        }
        return;
      }
      if (e.key === '.') {
        // allow dot only if not already present and integer part has at least 1 or up to 3 digits
        if ((this.value || '').includes('.')) e.preventDefault();
        return;
      }
      e.preventDefault();
    });

    g.addEventListener('paste', function (e) {
      e.preventDefault();
      const txt = (e.clipboardData || window.clipboardData).getData('text') || '';
      this.value = sanitize(txt);
    });

    g.addEventListener('input', function () {
      const v = sanitize(this.value);
      if (this.value !== v) this.value = v;
    });
  })();

  // tabs wiring
  (function () {
    function activateTabEl(tab) {
      if (!tab) return;
      const tabs = Array.from(document.querySelectorAll('.tabs .tab'));
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const target = tab.getAttribute('data-target');
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      const panel = document.getElementById(target);
      if (panel) panel.classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', function () {
      const container = document.querySelector('.tabs');
      if (!container) return;
      // try to honour ?tab=... or #panel-... so redirects show correct tab
      const params = new URLSearchParams(location.search);
      let tabParam = params.get('tab') || '';
      if (!tabParam && location.hash) {
        tabParam = location.hash.replace(/^#/, '');
      }
      if (tabParam) {
        let panelId = tabParam.startsWith('panel-') ? tabParam : ('panel-' + tabParam);
        const predefined = container.querySelector(`.tab[data-target="${panelId}"]`);
        if (predefined) activateTabEl(predefined);
      }

      // delegated click handler...
      container.addEventListener('click', function (e) {
        const tab = e.target.closest('.tab');
        if (!tab) return;
        activateTabEl(tab);
      });

      const tabEls = Array.from(container.querySelectorAll('.tab'));
      tabEls.forEach((t, idx, arr) => {
        t.setAttribute('role', 'tab');
        t.tabIndex = 0;
        t.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            activateTabEl(t);
            return;
          }
          if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') {
            ev.preventDefault();
            const next = arr[(idx + 1) % arr.length];
            next && next.focus();
          }
          if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            const prev = arr[(idx - 1 + arr.length) % arr.length];
            prev && prev.focus();
          }
        });
      });

      // activate initially marked tab or first tab
      const initially = container.querySelector('.tab.active') || container.querySelector('.tab');
      if (initially) activateTabEl(initially);
    });
  })();

  // Notification overlay (iframe to notif.php)
  (function(){
    const notifBtn = document.getElementById('btnNotif');
    if (!notifBtn) return;
    const badge = notifBtn.querySelector('.notif-count');

    let overlay = document.getElementById('notifOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'notifOverlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-hidden', 'true');
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.display = 'none';
      overlay.style.alignItems = 'flex-start';
      overlay.style.justifyContent = 'flex-end';
      overlay.style.padding = '18px';
      overlay.style.background = 'rgba(15, 23, 42, 0.25)';
      overlay.style.zIndex = '10050';
      overlay.innerHTML =
        '<div style="width:360px;max-width:calc(100% - 32px);height:600px;max-height:calc(100vh - 36px);background:#fff;border-radius:16px;box-shadow:0 18px 45px rgba(15, 23, 42, 0.18);overflow:hidden;">' +
        '<iframe src="notif.php?embed=1" title="Notifications" style="width:100%;height:100%;border:0;"></iframe>' +
        '</div>';
      document.body.appendChild(overlay);
    }

    notifBtn.setAttribute('aria-haspopup', 'dialog');
    notifBtn.setAttribute('aria-expanded', 'false');

    function setBadge(count) {
      if (!badge) return;
      const num = parseInt(count || 0, 10) || 0;
      if (num > 0) {
        badge.textContent = num;
        badge.style.display = 'inline-flex';
      } else {
        badge.textContent = '0';
        badge.style.display = 'none';
      }
    }

    try {
      const saved = localStorage.getItem('notifUnread');
      if (saved !== null) setBadge(saved);
    } catch (e) {
      // ignore storage errors
    }

    window.addEventListener('message', function(e){
      if (e && e.data && e.data.type === 'notif-count') {
        setBadge(e.data.unread);
      }
    });

    function openPanel() {
      overlay.style.display = 'flex';
      overlay.setAttribute('aria-hidden', 'false');
      notifBtn.setAttribute('aria-expanded', 'true');
    }

    function closePanel() {
      overlay.style.display = 'none';
      overlay.setAttribute('aria-hidden', 'true');
      notifBtn.setAttribute('aria-expanded', 'false');
    }

    window.closeNotifOverlay = closePanel;

    notifBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (overlay.style.display === 'flex') {
        closePanel();
      } else {
        openPanel();
      }
    });

    overlay.addEventListener('click', function(e){
      if (e.target === overlay) closePanel();
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closePanel();
    });
  })();
</script>
<!-- Read-only Evaluation View Modal -->
<div id="viewEvalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;z-index:12000;"> 
  <div style="background:#fff;width:820px;max-width:95%;border-radius:8px;padding:18px;box-shadow:0 12px 40px rgba(0,0,0,0.2);max-height:88vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3 style="margin:0;font-size:16px;color:#111827;">Evaluation</h3>
      <button id="viewEvalClose" aria-label="Close" title="Close" style="width:32px;height:32px;border-radius:6px;border:0;background:#e6e9f2;cursor:pointer;font-size:22px;line-height:1;padding:0;">&times;</button>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:12px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;">
      <div style="font-size:15px;line-height:1.45;color:#111827;">
        <div><strong>Name:</strong> <span id="viewHeadName">-</span></div>
        <div>Course: <span id="viewHeadCourse">-</span></div>
      </div>
      <div style="font-size:15px;line-height:1.45;color:#111827;text-align:right;">
        <div>School: <span id="viewHeadSchool">-</span></div>
        <div>Hours: <span id="viewHeadHours">-</span></div>
      </div>
    </div>

    <div id="viewEvalBody" style="min-height:120px;"></div>
  </div>
</div>

<script>
  (function(){
    function escapeHtml(v) {
      return String(v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function showViewModal(){
      const m = document.getElementById('viewEvalModal'); if (!m) return; m.style.display = 'flex'; document.body.style.overflow = 'hidden';
    }
    function hideViewModal(){
      const m = document.getElementById('viewEvalModal'); if (!m) return; m.style.display = 'none'; document.body.style.overflow = ''; 
    }
    document.getElementById('viewEvalClose').addEventListener('click', hideViewModal);
    document.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.view-eval-btn');
      if (!btn) return;
      e.preventDefault();
      // capture eval id (if present) and store on modal for later use
      const evalId = btn.getAttribute('data-eval-id') || '';
      const modal = document.getElementById('viewEvalModal');
      if (modal) modal.dataset.evalId = evalId || '';

      const bodyEl = document.getElementById('viewEvalBody');
      if (bodyEl) bodyEl.innerHTML = '';

      const tr = btn.closest('tr');
      const tds = tr ? tr.querySelectorAll('td') : [];
      const rowName = (tds[0] && tds[0].textContent) ? tds[0].textContent.trim() : '-';
      const rowSchool = (tds[1] && tds[1].textContent) ? tds[1].textContent.trim() : '-';
      const rowCourse = (tds[2] && tds[2].textContent) ? tds[2].textContent.trim() : '-';
      const rowHours = (tds[4] && tds[4].textContent) ? tds[4].textContent.trim() : '-';

      const headName = document.getElementById('viewHeadName');
      const headCourse = document.getElementById('viewHeadCourse');
      const headSchool = document.getElementById('viewHeadSchool');
      const headHours = document.getElementById('viewHeadHours');
      if (headName) headName.textContent = rowName;
      if (headCourse) headCourse.textContent = rowCourse;
      if (headSchool) headSchool.textContent = rowSchool;
      if (headHours) headHours.textContent = rowHours;

      if (evalId) {
        const url = 'office_head_ojts.php?ajax=view_eval&eval_id=' + encodeURIComponent(evalId) + '&_ts=' + Date.now();
        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
          .then(r => r.json().catch(() => null))
          .then(data => {
            if (!data || data.success === false) {
              throw new Error((data && data.message) ? data.message : 'Failed to load evaluation details');
            }
            const ev = (data && data.evaluation) ? data.evaluation : {};
            if (bodyEl) {
              let html = '';

              const strengths = (ev && ev.strengths) ? String(ev.strengths).trim() : '';
              const improvement = (ev && ev.improvement) ? String(ev.improvement).trim() : '';
              const comments = (ev && ev.comments) ? String(ev.comments).trim() : '';
              const hiring = (ev && ev.hiring) ? String(ev.hiring).trim() : '';

              html += '<div style="margin-top:2px;margin-bottom:10px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;color:#111827;font-size:14px;line-height:1.5;">' +
                '<div><strong>Strengths:</strong> ' + escapeHtml(strengths || '-') + '</div>' +
                '<div style="margin-top:6px;"><strong>Areas for Improvement:</strong> ' + escapeHtml(improvement || '-') + '</div>' +
                '<div style="margin-top:6px;"><strong>Overall Performance Comments:</strong> ' + escapeHtml(comments || '-') + '</div>' +
                '<div style="margin-top:6px;"><strong>Hiring Consideration:</strong> ' + escapeHtml(hiring || '-') + '</div>' +
              '</div>';

              const responses = Array.isArray(data.responses) ? data.responses : [];
              if (responses.length > 0) {
                const orderedResponses = responses.slice().sort((a, b) => {
                  const ak = String((a && a.question_key) ? a.question_key : '').toLowerCase();
                  const bk = String((b && b.question_key) ? b.question_key : '').toLowerCase();

                  function rank(k) {
                    if (k.startsWith('c')) return 1;
                    if (k.startsWith('s')) return 2;
                    if (k.startsWith('t')) return 3;
                    return 9;
                  }

                  function indexNum(k) {
                    const m = k.match(/(\d+)$/);
                    return m ? parseInt(m[1], 10) : 999;
                  }

                  const ar = rank(ak);
                  const br = rank(bk);
                  if (ar !== br) return ar - br;

                  const ai = indexNum(ak);
                  const bi = indexNum(bk);
                  if (ai !== bi) return ai - bi;

                  const ao = parseInt((a && a.question_order) ? a.question_order : 0, 10) || 0;
                  const bo = parseInt((b && b.question_order) ? b.question_order : 0, 10) || 0;
                  return ao - bo;
                });

                const competencyRows = [];
                const skillRows = [];
                const traitRows = [];

                for (let i = 0; i < orderedResponses.length; i++) {
                  const row = orderedResponses[i] || {};
                  const key = String((row && row.question_key) ? row.question_key : '').toLowerCase();
                  if (key.startsWith('c')) {
                    competencyRows.push(row);
                  } else if (key.startsWith('s')) {
                    skillRows.push(row);
                  } else if (key.startsWith('t')) {
                    traitRows.push(row);
                  }
                }

                function buildSectionTable(sectionTitle, sectionRows) {
                  if (!sectionRows.length) return '';

                  let rows = '';
                  for (let i = 0; i < sectionRows.length; i++) {
                    const row = sectionRows[i] || {};
                    const qtext = row.qtext ? String(row.qtext) : (row.question_key ? String(row.question_key) : ('Item ' + (i + 1)));
                    const score = (row.score === null || typeof row.score === 'undefined' || row.score === '') ? 'N/A' : String(row.score);
                    rows += '<tr>' +
                      '<td style="padding:10px;border:1px solid #eef1f6;">' + escapeHtml(qtext) + '</td>' +
                      '<td style="padding:10px;border:1px solid #eef1f6;text-align:center;width:90px;">' + escapeHtml(score) + '</td>' +
                    '</tr>';
                  }

                  return '<div style="overflow:auto;margin-top:8px;">' +
                    '<table style="width:100%;border-collapse:collapse;font-size:14px;">' +
                      '<thead><tr style="background:#2f3459;color:#fff;">' +
                        '<th style="padding:9px;border:1px solid #e6e9f2;text-align:left;">' + escapeHtml(sectionTitle) + '</th>' +
                        '<th style="padding:9px;border:1px solid #e6e9f2;text-align:center;width:90px;">Score</th>' +
                      '</tr></thead>' +
                      '<tbody>' + rows + '</tbody>' +
                    '</table>' +
                  '</div>';
                }

                html += buildSectionTable('Competency', competencyRows);
                html += buildSectionTable('Skill', skillRows);
                html += buildSectionTable('Trait', traitRows);
              } else {
                html += '<div style="padding:10px;border:1px solid #e5e7eb;border-radius:6px;background:#f8fafc;color:#334155;">No per-question responses found for this evaluation.</div>';
              }

              bodyEl.innerHTML = html;
            }
            showViewModal();
          })
          .catch((err) => {
            if (bodyEl) {
              const msg = (err && err.message) ? err.message : 'Unknown fetch error';
              bodyEl.innerHTML = '<div style="margin-top:10px;color:#8a1538;font-size:12px;"><strong>Debug:</strong> fetch failed for Eval ID ' + escapeHtml(evalId) + '.</div>' +
                '<pre style="margin-top:6px;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;white-space:pre-wrap;word-break:break-word;font-size:12px;line-height:1.35;">' + escapeHtml(msg) + '</pre>';
            }
            showViewModal();
          });
        return;
      }
      showViewModal();
    });
    // close on overlay click
    document.getElementById('viewEvalModal').addEventListener('click', function(e){ if (e.target === this) hideViewModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { const m = document.getElementById('viewEvalModal'); if (m && m.style.display === 'flex') hideViewModal(); } });
  })();
</script>
<script>
  // attach confirm to top logout like hr_head_ojts.php
  (function(){
    const logoutBtn = document.getElementById('btnLogout') || document.querySelector('a[href$="logout.php"]');
    if (!logoutBtn) return;
    logoutBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = this.getAttribute('href') || '../logout.php';
      }
    });
  })();

  // Settings modal open/close handlers (iframe overlay)
    (function(){
      const openBtn = document.getElementById('btnSettings');
      if (!openBtn) return;
      const settingsOverlay = document.createElement('div');
      settingsOverlay.id = 'settingsOverlay';
      settingsOverlay.style.position = 'fixed';
      settingsOverlay.style.top = '0';
      settingsOverlay.style.left = '0';
      settingsOverlay.style.right = '0';
      settingsOverlay.style.bottom = '0';
      settingsOverlay.style.display = 'none';
      settingsOverlay.style.alignItems = 'center';
      settingsOverlay.style.justifyContent = 'center';
      settingsOverlay.style.background = 'rgba(102, 51, 153, 0.18)';
      settingsOverlay.style.zIndex = '9999';
      settingsOverlay.setAttribute('role','dialog');
      settingsOverlay.setAttribute('aria-hidden','true');

      settingsOverlay.innerHTML = `
        <div style="width:100%;height:100vh;max-width:100%;max-height:100vh;padding:0;background:transparent;display:flex;align-items:center;justify-content:center;position:relative;">
          <iframe src="settings.php" title="Settings" style="width:100%;height:100%;border:0;display:block;"></iframe>
        </div>`;

      document.body.appendChild(settingsOverlay);

      function showSettings(){ settingsOverlay.style.display = 'flex'; settingsOverlay.setAttribute('aria-hidden','false'); try{ openBtn.style.background = '#fff'; openBtn.style.boxShadow = '0 6px 18px rgba(0,0,0,0.06)'; }catch(e){} }
      function hideSettings(){ settingsOverlay.style.display = 'none'; settingsOverlay.setAttribute('aria-hidden','true'); try{ openBtn.style.background = 'transparent'; openBtn.style.boxShadow = 'none'; }catch(e){} }
      window.closeSettingsOverlay = hideSettings;

      openBtn.addEventListener('click', function(ev){ ev.preventDefault(); showSettings(); });
      settingsOverlay.addEventListener('click', function(e){ if (e.target === settingsOverlay) hideSettings(); });
    })();
    // listen for updates from the settings iframe and patch the sidebar/profile in-place
  (function(){
    window.addEventListener('message', function(e){
      try{
        var d = e && e.data ? e.data : null;
        if (!d || d.type !== 'profile-updated') return;
        if (typeof d.avatar !== 'undefined' && d.avatar) {
          var img = document.querySelector('.profile img');
          if (img) img.src = d.avatar;
        }
        if (typeof d.name !== 'undefined') {
          var h = document.querySelector('.profile h3');
          if (h) h.textContent = d.name;
        }
      }catch(err){}
    });
  })();
</script>
</body>
</html>