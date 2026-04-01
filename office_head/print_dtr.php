<?php
require_once __DIR__ . '/../conn.php';
date_default_timezone_set('Asia/Manila');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function format_time_12_no_suffix($rawTime){
  $s = trim((string)$rawTime);
  if ($s === '' || $s === '00:00:00') return '';
  if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $s, $m)) return $s;

  $h = (int)$m[1];
  $min = $m[2];
  if ($h < 0 || $h > 23) return $s;

  $h12 = $h % 12;
  if ($h12 === 0) $h12 = 12;
  return $h12 . ':' . $min;
}

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($app_id <= 0 && isset($_GET['application_id'])) {
  $app_id = (int)$_GET['application_id'];
}

// Accept both student_id and user_id style params from callers.
$student_param = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$user_param = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$student = null;
$office_name = '';
$office_head_name = '';

if ($app_id > 0) {
    $stmt = $conn->prepare("SELECT oa.student_id, oa.office_preference1, s.first_name, s.last_name FROM ojt_applications oa LEFT JOIN students s ON oa.student_id = s.student_id WHERE oa.application_id = ? LIMIT 1");
    $stmt->bind_param('i', $app_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $student = [ 'id' => (int)$row['student_id'], 'first_name' => $row['first_name'], 'last_name' => $row['last_name'] ];
        $office_pref = (int)($row['office_preference1'] ?? 0);
    }
}

if (!$student && $student_param > 0) {
  // First treat param as actual students.student_id.
  $stmt = $conn->prepare("SELECT student_id AS id, first_name, last_name FROM students WHERE student_id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $student_param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
      $student = [ 'id' => (int)$row['id'], 'first_name' => $row['first_name'], 'last_name' => $row['last_name'] ];
    }
  }

  // If not found, treat the same value as users.user_id (common in some callers).
  if (!$student) {
    $stmt = $conn->prepare("SELECT student_id AS id, first_name, last_name FROM students WHERE user_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('i', $student_param);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if ($row) {
        $student = [ 'id' => (int)$row['id'], 'first_name' => $row['first_name'], 'last_name' => $row['last_name'] ];
      }
    }
  }
}

if (!$student && $user_param > 0) {
  // Prefer resolving user_id -> student_id mapping.
  $stmt = $conn->prepare("SELECT student_id AS id, first_name, last_name FROM students WHERE user_id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $user_param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
      $student = [ 'id' => (int)$row['id'], 'first_name' => $row['first_name'], 'last_name' => $row['last_name'] ];
    }
  }

  // Backward compatibility: if caller passed student_id through user_id.
  if (!$student) {
    $stmt = $conn->prepare("SELECT student_id AS id, first_name, last_name FROM students WHERE student_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('i', $user_param);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if ($row) {
        $student = [ 'id' => (int)$row['id'], 'first_name' => $row['first_name'], 'last_name' => $row['last_name'] ];
      }
    }
  }
}

if (!$student) {
    echo "<p>Invalid student/application id.</p>";
    exit;
}

$student_id = (int)$student['id'];

// ALWAYS base office on the users table: use the user linked to this student
// (students.user_id -> users.office_name). Do NOT use office_preference1.
$stmt = $conn->prepare("SELECT u.office_name FROM students s LEFT JOIN users u ON s.user_id = u.user_id WHERE s.student_id = ? LIMIT 1");
if ($stmt) {
  $stmt->bind_param('i', $student_id);
  $stmt->execute();
  $rn = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!empty($rn['office_name'])) {
    $office_name = $rn['office_name'];
  }
}

// determine target month/year if not provided: use latest dtr row or current month
// Note: dtr.student_id may store either students.student_id or users.user_id.
// Query for MAX(log_date) matching either value (student_id OR linked user_id).
if (!$month || !$year) {
  $q = $conn->prepare("SELECT MAX(log_date) AS latest FROM dtr WHERE student_id = ? OR student_id = (SELECT user_id FROM students WHERE student_id = ? LIMIT 1) LIMIT 1");
  $q->bind_param('ii', $student_id, $student_id);
  $q->execute();
  $lr = $q->get_result()->fetch_assoc();
  $q->close();
  if (!empty($lr['latest'])) {
    $dt = strtotime($lr['latest']);
    $month = (int)date('n', $dt);
    $year = (int)date('Y', $dt);
  } else {
    $month = (int)date('n');
    $year = (int)date('Y');
  }
}

// Determine which months to print.
// Default: ignore passed month/year and print all months between first and last DTR log for this student
// (matching either student_id or linked user_id). If caller explicitly provides single_month=1,
// then only the provided month/year will be printed (if present), otherwise current month.
$months = [];
$single = isset($_GET['single_month']) && ($_GET['single_month']=='1' || strtolower($_GET['single_month']) === 'true');
if ($single && $month && $year) {
  $months[] = ['year' => $year, 'month' => $month];
} else {
  $q = $conn->prepare("SELECT MIN(log_date) AS first_log, MAX(log_date) AS last_log FROM dtr WHERE student_id = ? OR student_id = (SELECT user_id FROM students WHERE student_id = ? LIMIT 1) LIMIT 1");
  $q->bind_param('ii', $student_id, $student_id);
  $q->execute();
  $lr = $q->get_result()->fetch_assoc();
  $q->close();
  if (!empty($lr['first_log']) && !empty($lr['last_log'])) {
    $start = strtotime($lr['first_log']);
    $end = strtotime($lr['last_log']);
    $sy = (int)date('Y', $start);
    $sm = (int)date('n', $start);
    $ey = (int)date('Y', $end);
    $em = (int)date('n', $end);
    $y = $sy; $m = $sm;
    while ($y < $ey || ($y == $ey && $m <= $em)) {
      $months[] = ['year' => $y, 'month' => $m];
      $m++;
      if ($m > 12) { $m = 1; $y++; }
    }
  } else {
    $months[] = ['year' => (int)date('Y'), 'month' => (int)date('n')];
  }
}

// try to find office head full name
if (!empty($office_name)) {
    $oh = $conn->prepare("SELECT first_name,middle_name,last_name FROM users WHERE role = 'office_head' AND LOWER(TRIM(office_name)) = LOWER(TRIM(?)) LIMIT 1");
    $oh->bind_param('s', $office_name);
    $oh->execute();
    $ohr = $oh->get_result()->fetch_assoc();
    $oh->close();
    if ($ohr) $office_head_name = trim(($ohr['first_name'] ?? '') . ' ' . ($ohr['middle_name'] ?? '') . ' ' . ($ohr['last_name'] ?? ''));
}

// No fallback to office_preference1: do not use office_pref to determine office head.
// If an office head cannot be found via users.office_name, leave $office_head_name empty.

// final name
$student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: 'N/A';

// render printable HTML (one sheet per month in $months)
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Daily Time Record - <?php echo h($student_name); ?></title>
  <style>
    /* Paper size: width 2.5in, height 8.5in (portrait narrow card) */
    @page { size: 2.5in 8.5in; margin: 0.15in; }
    html,body{height:100%;margin:0;padding:0}
    body{font-family: 'Times New Roman', Times, serif; font-size:10px;color:#111;}
    /* sheet should wrap content height (not force full-page box) so border stops after content */
    .sheet{width:3in; max-height:8.5in; margin:0 auto; box-sizing:border-box; padding:3px; border:2px solid #000; display:flex; flex-direction:column; page-break-after:always; page-break-inside:avoid; overflow:visible}
    .muted{color:#333;font-size:10px}
    .center{ text-align:center }
    .small{font-size:10px}
    .table-dtr{width:100%;border-collapse:collapse;font-size:9px; page-break-inside:auto; table-layout:fixed}
    .table-dtr th, .table-dtr td{border:1px solid #000;padding:1px 2px;text-align:center;overflow:hidden;white-space:nowrap}
    /* ensure background colors are honored when printing */
    .sheet, .table-dtr, .table-dtr td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .table-dtr thead th{background:transparent;font-weight:700;font-size:7px;padding:0px 1px}
    .table-dtr tbody tr.weekend td:first-child{background-color:#bfbfbf}
    .table-dtr thead tr:first-child th{font-size:8px}
    .table-dtr thead th.small{font-size:7px}
    /* equal widths for DAY, A.M. Arrival/Departure, P.M. Arrival/Departure, Hours, Minutes */
    .col-day,
    .col-am-arr,
    .col-am-dep,
    .col-pm-arr,
    .col-pm-dep,
    .col-hours,
    .col-min { width:14.2857%; padding:0 2px; box-sizing:border-box }
    .table-dtr thead th.col-day{font-size:7px;padding:0 1px}
    /* remove vertical padding for body cells and tighten rows */
    .table-dtr tbody td{padding:0 2px}
    /* smaller font for day numbers (table body first column) */
    .table-dtr tbody td:first-child{font-size:8px;padding:0;text-align:center;vertical-align:middle}
    .row-fixed{height:14px}
    .no-border{border:0}
    .signature{margin-top:6px;text-align:center}
    .sig-name{font-weight:700;display:block}
    .sig-role{font-size:10px}
    /* ensure no breaks inside the card */
    .sheet, .sheet * { box-sizing: border-box; }
    @media print {
      body{margin:0}
      .sheet{padding:0; page-break-after:always; page-break-inside:avoid}
    }
  </style>
  <script>function doPrint(){ window.print(); }</script>
</head>
<body onload="doPrint()">
  <?php
  // render one sheet per month
  foreach ($months as $mi) {
      $m = (int)$mi['month'];
      $y = (int)$mi['year'];
      $monthName = date('F', strtotime(sprintf('%04d-%02d-01', $y, $m)));
      $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));

      // fetch DTR rows for this month (match either student id or linked user_id)
      $stmt = $conn->prepare("SELECT log_date, am_in, am_out, pm_in, pm_out FROM dtr WHERE (student_id = ? OR student_id = (SELECT user_id FROM students WHERE student_id = ? LIMIT 1)) AND YEAR(log_date)=? AND MONTH(log_date)=? ORDER BY log_date ASC");
      $stmt->bind_param('iiii', $student_id, $student_id, $y, $m);
      $stmt->execute();
      $res = $stmt->get_result();
      $rows = [];
      while ($r = $res->fetch_assoc()) {
          $d = (int)date('j', strtotime($r['log_date']));
          $rows[$d] = $r;
      }
      $stmt->close();

      // compute totals for this month
      $totalMinutes = 0;
      for ($d=1;$d<=$daysInMonth;$d++){
          if (!isset($rows[$d])) continue;
          $r = $rows[$d];
          $mins = 0;
          if (!empty($r['am_in']) && !empty($r['am_out'])) {
              $t1 = strtotime($r['log_date'].' '.$r['am_in']);
              $t2 = strtotime($r['log_date'].' '.$r['am_out']);
              if ($t2 > $t1) $mins += floor(($t2-$t1)/60);
          }
          if (!empty($r['pm_in']) && !empty($r['pm_out'])) {
              $t1 = strtotime($r['log_date'].' '.$r['pm_in']);
              $t2 = strtotime($r['log_date'].' '.$r['pm_out']);
              if ($t2 > $t1) $mins += floor(($t2-$t1)/60);
          }
          $totalMinutes += $mins;
          $rows[$d]['minutes_total'] = $mins;
      }
  ?>

  <div class="sheet">
    <div class="center" style="margin-bottom:4px">
      <div class="muted small" style="font-size:8px;text-align:left;width:100%;margin-bottom:10px">Civil Service Form. 48</div>
      <div style="font-weight:800;letter-spacing:1px;font-size:12px;margin-top:2px">DAILY TIME RECORD</div>
      <div style="margin-top:6px;font-weight:800;font-size:11px;letter-spacing:0.6px"><?php echo h(strtoupper($student_name)); ?></div>
      <div class="small" style="margin-top:2px">(Name)</div>
      <div style="margin-top:6px;font-size:9px;display:flex;align-items:center;gap:6px">
        <div>For the month of</div>
        <div style="font-weight:700;border-bottom:1px solid #000;padding:0 6px;min-width:80px;text-align:center;">
          <?php echo h($monthName . ' ' . $y); ?>
        </div>
      </div>
      <!-- Removed official hours / regular days / departure / Saturdays fields as requested -->
    </div>

    <table class="table-dtr" aria-label="Daily Time Record">
      <thead>
        <tr>
          <th class="col-day" rowspan="2">DAY</th>
          <th colspan="2">A.M.</th>
          <th colspan="2">P.M.</th>
          <th colspan="2">Undertime</th>
        </tr>
        <tr>
          <th class="col-am-arr small">Arrival</th>
          <th class="col-am-dep small">Departure</th>
          <th class="col-pm-arr small">Arrival</th>
          <th class="col-pm-dep small">Departure</th>
          <th class="col-hours small">Hours</th>
          <th class="col-min small">Minutes</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($d=1;$d<=$daysInMonth;$d++):
            $row = $rows[$d] ?? null;
            $am_in = $row['am_in'] ?? '';
            $am_out = $row['am_out'] ?? '';
            $pm_in = $row['pm_in'] ?? '';
            $pm_out = $row['pm_out'] ?? '';
            $am_in_disp = format_time_12_no_suffix($am_in);
            $am_out_disp = format_time_12_no_suffix($am_out);
            $pm_in_disp = format_time_12_no_suffix($pm_in);
            $pm_out_disp = format_time_12_no_suffix($pm_out);
            $mins = isset($row['minutes_total']) ? (int)$row['minutes_total'] : 0;
            $hval = floor($mins/60);
            $mval = $mins % 60;
            $isBlank = $d > $daysInMonth;
            // determine weekend shading if date exists
            $isWeekend = false;
            if (!$isBlank) {
                $dow = (int)date('N', strtotime(sprintf('%04d-%02d-%02d', $y, $m, $d))); // 6=Sat,7=Sun
                if ($dow >= 6) $isWeekend = true;
            }
        ?>
        <tr class="row-fixed<?php echo $isWeekend ? ' weekend' : ''; ?>">
          <td style="text-align:center;padding:0"><?php echo $d; ?></td>
          <td><?php echo $isBlank ? '' : h($am_in_disp); ?></td>
          <td><?php echo $isBlank ? '' : h($am_out_disp); ?></td>
          <td><?php echo $isBlank ? '' : h($pm_in_disp); ?></td>
          <td><?php echo $isBlank ? '' : h($pm_out_disp); ?></td>
          <td><?php echo ($hval>0 && !$isBlank) ? $hval : ''; ?></td>
          <td><?php echo ($mval>0 && !$isBlank) ? $mval : ''; ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td colspan="5" style="text-align:right;font-weight:700;border-top:2px solid #000">TOTAL</td>
          <td style="font-weight:700;border-top:2px solid #000"><?php echo floor($totalMinutes/60); ?></td>
          <td style="font-weight:700;border-top:2px solid #000"><?php echo $totalMinutes%60; ?></td>
        </tr>
      </tfoot>
    </table>
    <div style="margin-top:6px; font-size:9px; text-align:center">
      <div style="margin-bottom:6px">I certify on my honor that the above is a true and correct report of the</div>
      <div style="margin-bottom:6px">hours of work performed, record of which was made daily at the time</div>
      <div style="margin-bottom:8px">of arrival and departure from office.</div>

      <div style="height:10px"></div>

      <!-- OJT signature line (above the VERIFIED text) -->
      <div style="width:100%;text-align:center;margin-top:8px;margin-bottom:6px">
        <div style="width:60%;margin:0 auto;border-bottom:1px solid #000;height:1px"></div>
        <div></div>
      </div>

      <div style="font-style:italic;font-size:10px;margin-bottom:20px">VERIFIED as to the prescribed office hours</div>

      <!-- Office head signature line (above the printed name) -->
      <div style="width:100%;text-align:center;margin-top:8px">
        <div style="width:60%;margin:0 auto;border-bottom:1px solid #000;height:1px"></div>
        <div style="text-align:center;font-weight:700;letter-spacing:0.6px;margin-top:6px"><?php echo h(strtoupper($office_head_name ?: '')); ?></div>
        <div style="text-align:center;font-size:10px;margin-top:4px">In Charge</div>
      </div>

      <div style="font-size:9px;margin-top:10px;text-align:center">(SEE INSTRUCTIONS ON BACK)</div>
    </div>
  </div>
  <?php } // end foreach months ?>
</body>
</html>
