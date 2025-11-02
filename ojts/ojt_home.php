<?php
// ojt_home.php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../conn.php'; // make sure conn.php defines $conn (mysqli)

// determine logged in user -> student_id
$user_id = $_SESSION['user_id'] ?? null;
$student_id = null;
if ($user_id) {
    $s = $conn->prepare("SELECT student_id, first_name, last_name FROM students WHERE user_id = ?");
    $s->bind_param("i", $user_id);
    $s->execute();
    $sr = $s->get_result()->fetch_assoc();
    $s->close();
    if ($sr) {
        $student_id = (int)$sr['student_id'];
        $name = trim(($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? ''));
    }
}
if (empty($name)) $name = "Jasmine Santiago";

// determine office for this logged-in user and show in sidebar as "OJT - <Office>"
$office_display = '';
if (!empty($user_id)) {
    $su = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $urow = $su->get_result()->fetch_assoc();
    $su->close();
    if (!empty($urow['office_name'])) {
        // remove trailing " Office" if present (e.g., "IT Office" -> "IT")
        $office_display = preg_replace('/\s+Office\s*$/i', '', trim($urow['office_name']));
    }
}
$role = $office_display ? "OJT - " . $office_display : "OJT";

// compute accurate OJT progress: start date from latest application remarks, hours from dtr
$hours_completed = 0.0;
$total_hours = 500;
$percent = 0;
$date_started = '-';
$end_date = '-';

 // read student's required total from students table (if available)
if (!empty($student_id)) {
    $s = $conn->prepare("SELECT total_hours_required FROM students WHERE student_id = ? LIMIT 1");
    $s->bind_param("i", $student_id);
    $s->execute();
    $sr = $s->get_result()->fetch_assoc();
    $s->close();
    if ($sr && !empty($sr['total_hours_required'])) $total_hours = (float)$sr['total_hours_required'];

    // determine start date: use first recorded time-in (am_in or pm_in) in dtr; fallback to application remarks
    $startDateSql = null;
    $qf = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND (am_in IS NOT NULL AND am_in<>'' OR pm_in IS NOT NULL AND pm_in<>'') ORDER BY log_date ASC LIMIT 1");
    $qf->bind_param('i', $student_id);
    $qf->execute();
    $fr = $qf->get_result()->fetch_assoc();
    $qf->close();
    if ($fr && !empty($fr['log_date'])) {
        $startDateSql = $fr['log_date'];
    } else {
        // fallback: try to parse Orientation/Start from latest application remarks
        $qa = $conn->prepare("SELECT remarks FROM ojt_applications WHERE student_id = ? ORDER BY date_updated DESC, application_id DESC LIMIT 1");
        $qa->bind_param('i', $student_id);
        $qa->execute();
        $ar = $qa->get_result()->fetch_assoc();
        $qa->close();
        if ($ar && !empty($ar['remarks'])) {
            $r = $ar['remarks'];
            if (preg_match('/Orientation\/Start:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $r, $m)) {
                $startDateSql = $m[1];
            } elseif (preg_match('/Orientation\/Start:\s*([0-9]{4}\/[0-9]{2}\/[0-9]{2})/i', $r, $m2)) {
                $startDateSql = str_replace('/', '-', $m2[1]);
            } elseif (preg_match('/Orientation\/Start:\s*([A-Za-z0-9\-\s,]+)/i', $r, $m3)) {
                $try = trim($m3[1]);
                $ts = strtotime($try);
                if ($ts !== false) $startDateSql = date('Y-m-d', $ts);
            }
        }
    }

    // compute hours completed from dtr (restrict to start date if known)
    if ($startDateSql) {
        $q = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ? AND log_date >= ?");
        $q->bind_param("is", $student_id, $startDateSql);
    } else {
        $q = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ?");
        $q->bind_param("i", $student_id);
    }
    $q->execute();
    $tr = $q->get_result()->fetch_assoc();
    $q->close();
    $hours_completed = isset($tr['total']) ? (float)$tr['total'] : 0.0;

    // percentage (use students.total_hours_required already loaded into $total_hours)
    $percent = $total_hours > 0 ? round(($hours_completed / $total_hours) * 100) : 0;

    // formatted start / expected end (8 hrs/day, 5-day work week). Use remaining hours.
    if ($startDateSql) {
        $date_started = date('F j, Y', strtotime($startDateSql));
        $hoursPerDay = 8;
        $remaining = max(0, $total_hours - $hours_completed);
        $daysNeeded = (int)ceil($remaining / $hoursPerDay);
        // advance counting only weekdays (Mon-Fri)
        $dt = new DateTime($startDateSql);
        $added = 0;
        while ($added < $daysNeeded) {
            $dt->modify('+1 day');
            $dow = (int)$dt->format('N'); // 1 (Mon) .. 7 (Sun)
            if ($dow < 6) $added++;
        }
        // if daysNeeded == 0 the end date is the start date
        $end_date = $dt->format('F j, Y');
    } else {
        $date_started = '-';
        $end_date = '-';
    }
}

// load DTR rows for current month
$year = (int)date('Y');
$month = (int)date('n');
$daysInMonth = (int)date('t', strtotime("$year-$month-01"));

$dtrMap = []; // day => row
if ($student_id) {
    // select actual columns including am/pm in/out and hours/minutes
    $q = $conn->prepare("SELECT dtr_id, log_date, am_in, am_out, pm_in, pm_out, hours, minutes FROM dtr WHERE student_id = ? AND MONTH(log_date)=? AND YEAR(log_date)=? ORDER BY log_date");
    $q->bind_param("iii", $student_id, $month, $year);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $d = (int)date('j', strtotime($r['log_date']));

        // Trim times to HH:MM (remove seconds) and do NOT recalculate/persist on page load.
        $am_in = !empty($r['am_in']) ? substr($r['am_in'], 0, 5) : null;
        $am_out = !empty($r['am_out']) ? substr($r['am_out'], 0, 5) : null;
        $pm_in = !empty($r['pm_in']) ? substr($r['pm_in'], 0, 5) : null;
        $pm_out = !empty($r['pm_out']) ? substr($r['pm_out'], 0, 5) : null;

        // read stored hours/minutes (persisted only by time_out endpoint)
        $hoursStored = isset($r['hours']) ? (int)$r['hours'] : 0;
        $minutesStored = isset($r['minutes']) ? (int)$r['minutes'] : 0;

        // use a distinct local variable so we DON'T overwrite $total_hours (students.total_hours_required)
        $row_total_hours = ($hoursStored || $minutesStored) ? ($hoursStored + ($minutesStored / 60)) : 0.0;

        $dtrMap[$d] = [
            'dtr_id' => (int)$r['dtr_id'],
            'log_date' => $r['log_date'],
            'am_in' => $am_in,
            'am_out' => $am_out,
            'pm_in' => $pm_in,
            'pm_out' => $pm_out,
            'hours' => $hoursStored,
            'minutes' => $minutesStored,
            'total_hours' => $row_total_hours
        ];
    }
    $q->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OJT Home - OJT-MS</title>
  <style>
    * { box-sizing: border-box; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body { display: flex; background: #f9f9fb; color: #222; min-height: 100vh;         font-family: 'Poppins', sans-serif;
        margin: 0;
        background-color: #f5f6fa;
    }
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
        background: white;
        border-radius: 20px;
        text-decoration: none;
    }
    .sidebar a.active {
        background-color: #b3b7d6;
    }
    /* Main Content */
    .main {
      margin-left: 220px;
      flex: 1;
      display: flex;
      flex-direction: row;
      justify-content: space-between;
      /* increased top padding so main content (including DTR) sits below the top icons */
      padding: 56px 32px 28px;
      height: 100vh;
      align-items: stretch;
      gap: 20px;
      overflow: hidden; /* prevent page scrollbars from main area */
    }

    /* Left side */
    .left-content {
      flex: 1 1 60%;
      min-width: 260px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      overflow: hidden;
    }
    .progress-container { background: #e8e9ef; border-radius: 14px; display: flex; align-items: center; padding: 14px; }
    .progress-circle { width: 88px; height: 88px; border-radius: 50%; background: conic-gradient(#4b6cb7 <?php echo $percent; ?>%, #d6d6d6 0); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #333; font-size: 18px; margin-right: 18px; }
    .progress-details { font-size: 14px; }

    .datetime { text-align: center; margin-bottom: 6px; }
    .datetime h1 { font-size: 20px; font-weight: 500; margin-bottom: 6px; }
    .datetime h2 { font-size: 28px; font-weight: 700; }

    .buttons { display: flex; justify-content: center; gap: 16px; }
    .buttons button { width: 140px; padding: 12px 0; border: none; border-radius: 20px; font-size: 15px; cursor: pointer; color: white; transition: opacity .12s ease, transform .06s ease, background .12s ease; }
    /* enabled appearance (both buttons look the same when enabled) */
    .timein, .timeout { background: #5b5f89; color: #fff; box-shadow: 0 4px 10px rgba(90,95,140,0.12); }

    /* disabled visual state - uniform for both buttons */
    .buttons button[disabled], .buttons button.btn-disabled {
      background: #c3c3c3 !important;
      color: #333 !important;
      opacity: 0.9;
      filter: none;
      cursor: not-allowed;
      transform: none;
      pointer-events: none;
      box-shadow: none;
    }
    /* small active feedback for enabled buttons */
    .buttons button:not([disabled]):active { transform: translateY(1px); }

    /* DTR Section */
    .dtr-section {
      flex: 0 0 36%;
      max-width: 36%;
      min-width: 300px;
      background: white;
      padding: 12px 14px;
      border-radius: 8px;
      border: 1px solid #ddd;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: stretch;
      overflow: auto; /* allow internal scroll when needed */
      /* account for increased top padding so DTR doesn't overlap top icons */
      height: calc(100vh - 84px);
    }
    .dtr-section h3 { text-align: center; font-size: 13px; margin-bottom: 6px; }
    .dtr-section p { font-size: 12px; margin-bottom: 8px; text-align: center; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      table-layout: fixed;
    }
    th, td {
      border: 1px solid #000;
      padding: 6px 4px;
      text-align: center;
      font-size: 12px;
      /* allow the full HH:MM / text to show — do not ellipsize time columns */
    }
    th { background: #f0f0f0; font-weight: 600; font-size: 12px; }
    tfoot td { font-weight: bold; text-align: right; padding:8px; }

    @media (max-width: 1100px) {
      .dtr-section { max-width: 40%; min-width: 260px; }
      th, td { font-size: 11px; padding:4px 3px; }
    }
  </style>
</head>
<body>

  <!-- top-right outline icons: notifications, settings, logout -->
  <div id="top-icons" style="position:fixed;top:18px;right:28px;display:flex;gap:14px;z-index:1200;">
      <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </a>
      <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
      </a>
      <a id="top-logout" href="/logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
  </div>

  <!-- Sidebar -->
  <div class="sidebar">
    <?php
      // initials for avatar
      $initials = '';
      foreach (explode(' ', trim($name)) as $p) {
        if ($p !== '') $initials .= strtoupper($p[0]);
      }
      $initials = substr($initials, 0, 2);
    ?>
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="text-align:center; padding: 8px 12px 20px;">
          <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
            <?php echo htmlspecialchars($initials ?: 'JS'); ?>
          </div>
          <h3 style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($name); ?></h3>
          <p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($role); ?></p>
        </div>

        <nav style="padding: 6px 10px 12px;">
          <a href="#" class="active" aria-current="page"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <path d="M3 11.5L12 4l9 7.5"></path>
              <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
            </svg>
            <span style="font-weight:600;">Home</span>
          </a>

            <a href="ojt_profile.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Profile</span>
            </a>

            <a href="ojt_dtr.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <rect x="3" y="4" width="18" height="18" rx="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>DTR</span>
            </a>

            <a href="ojt_reports.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <rect x="3" y="3" width="4" height="18"></rect>
              <rect x="10" y="8" width="4" height="13"></rect>
              <rect x="17" y="13" width="4" height="8"></rect>
            </svg>
            <span>Reports</span>
          </a>
        </nav>
      </div>

      <div style="padding:14px 12px 26px;">
        <!-- sidebar logout removed — use top-right logout icon instead -->
      </div>
    </div>
  </div>

    <div class="bottom-title">OJT-MS</div>
  </div>

  <!-- Main Content -->
  <div class="main">
    <div class="left-content">
      <div class="progress-container">
        <div class="progress-circle"><?php echo $percent; ?>%</div>
        <div class="progress-details">
          <b><?php echo "$hours_completed out of $total_hours hours"; ?></b><br>
          Date Started: <b><?php echo $date_started; ?></b><br>
          Expected End Date: <b><?php echo $end_date; ?></b>
        </div>
      </div>

      <div class="datetime">
        <h1><?php echo date("F d, Y"); ?></h1>
        <!-- render initial clock with seconds so client update won't visibly jump -->
        <h2 id="clock"><?php echo date("h:i:s A"); ?></h2>
      </div>

      <div class="buttons">
        <button id="btnTimeIn" class="timein">TIME IN</button>
        <button id="btnTimeOut" class="timeout" disabled>TIME OUT</button>
      </div>
    </div>

    <div class="dtr-section">
      <h3>DAILY TIME RECORD</h3>
      <p><b><?php echo htmlspecialchars($name); ?></b><br><?php echo date('F Y'); ?></p>

      <table id="dtrTable">
        <thead>
          <tr>
            <th>DAY</th>
            <th>AM Arrival</th>
            <th>AM Departure</th>
            <th>PM Arrival</th>
            <th>PM Departure</th>
            <th>Hours</th>
            <th>Minutes</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $totalHours = 0;
          // helper inline: format "HH:MM" -> "h:i A" safely
          function fmt_hm($hm) {
              if (empty($hm)) return '';
              $hm = trim($hm);
              // normalize to "HH:MM" even if input is "HH:MM:SS"
              if (strpos($hm, ':') !== false) {
                  $parts = explode(':', $hm);
                  // take first two segments (hours and minutes)
                  $hm = sprintf('%02d:%02d', intval($parts[0] ?? 0), intval($parts[1] ?? 0));
              }
              $dt = DateTime::createFromFormat('H:i', $hm);
              // show hour:minute only (no AM/PM)
              return $dt ? $dt->format('h:i') : htmlspecialchars($hm);
          }

          for ($d = 1; $d <= $daysInMonth; $d++) {
            // make sure $row is an array to avoid "offset on null" warnings
            $row = isset($dtrMap[$d]) && is_array($dtrMap[$d]) ? $dtrMap[$d] : [];

            // use trimmed HH:MM values already stored in $dtrMap
            $amArrival = fmt_hm($row['am_in'] ?? null);
            $amDepart  = fmt_hm($row['am_out'] ?? null);
            $pmArrival = fmt_hm($row['pm_in'] ?? null);
            $pmDepart  = fmt_hm($row['pm_out'] ?? null);

            $hasRow = !empty($row['dtr_id']);
            // show persisted hours/minutes (including 0) only when there is a row (time_out should have set these)
            $hours = $hasRow ? (int)($row['hours'] ?? 0) : '';
            $minutes = $hasRow ? (int)($row['minutes'] ?? 0) : '';

            // add to total safely
            $totalHours += isset($row['total_hours']) ? (float)$row['total_hours'] : 0.0;

            echo "<tr data-day=\"$d\">
                    <td>{$d}</td>
                    <td class='am-arrival'>".htmlspecialchars($amArrival)."</td>
                    <td class='am-depart'>".htmlspecialchars($amDepart)."</td>
                    <td class='pm-arrival'>".htmlspecialchars($pmArrival)."</td>
                    <td class='pm-depart'>".htmlspecialchars($pmDepart)."</td>
                    <td class='hours'>".($hours !== '' ? $hours : '')."</td>
                    <td class='minutes'>".($minutes !== '' ? $minutes : '')."</td>
                  </tr>";
          }
          ?>
        </tbody>
        <tfoot>
          <tr><td colspan="7">TOTAL: <span id="totalHours"><?php echo $totalHours; ?></span> hrs</td></tr>
        </tfoot>
      </table>
    </div>
  </div>

  <script>
    function updateClock() {
      const now = new Date();
      document.getElementById('clock').textContent =
        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    setInterval(updateClock, 1000);

    const studentId = <?php echo json_encode($student_id ?: null); ?>;
    const btnIn = document.getElementById('btnTimeIn');
    const btnOut = document.getElementById('btnTimeOut');

    function setButtonState(inDisabled, outDisabled) {
      btnIn.disabled = inDisabled;
      btnOut.disabled = outDisabled;
      btnIn.setAttribute('aria-disabled', inDisabled ? 'true' : 'false');
      btnOut.setAttribute('aria-disabled', outDisabled ? 'true' : 'false');
    }

    // determine if there is an unmatched IN (am_in without am_out or pm_in without pm_out)
    function hasUnmatchedIn(row) {
      if (!row) return false;
      return (row.am_in && !row.am_out) || (row.pm_in && !row.pm_out);
    }

    // set initial button state based on today's row
    function refreshButtonsState(todayRow) {
      const unmatched = hasUnmatchedIn(todayRow);
      // when unmatched=true -> TIME IN disabled, TIME OUT enabled
      setButtonState(unmatched, !unmatched);
    }

    // fetch today's dtr row to init
    async function fetchTodayRow() {
      try {
        const res = await fetch('./ojt_dtr_action.php', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action: 'get_today' })
        });
        const j = await res.json();
        if (j.success) {
          refreshButtonsState(j.row || null);
        } else {
          // default: allow TIME IN, disable TIME OUT
          setButtonState(false, true);
        }
      } catch (e) {
        console.error(e);
        // network error: be conservative
        setButtonState(false, true);
      }
    }

    async function handleAction(action) {
      try {
        const res = await fetch('./ojt_dtr_action.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action })
        });
        const j = await res.json();
        if (!j.success) {
          alert('Error: ' + (j.message || 'Unknown'));
          // refresh state from server after failure
          fetchTodayRow();
          return;
        }
        const row = j.row; // { log_date, am_in, am_out, pm_in, pm_out, hours, minutes, total_hours }
        if (!row) {
          // nothing to update
          fetchTodayRow();
          return;
        }

        // update table row for the day
        const d = new Date(row.log_date);
        const day = d.getDate();
        const tr = document.querySelector('#dtrTable tbody tr[data-day="'+day+'"]');
        if (tr) {
          tr.querySelector('.am-arrival').textContent = row.am_in ? formatTime(row.am_in) : '';
          tr.querySelector('.am-depart').textContent  = row.am_out ? formatTime(row.am_out) : '';
          tr.querySelector('.pm-arrival').textContent = row.pm_in ? formatTime(row.pm_in) : '';
          tr.querySelector('.pm-depart').textContent  = row.pm_out ? formatTime(row.pm_out) : '';

          // ALWAYS show hours/minutes when server returned numeric values (including 0)
          if (row.hours !== null && row.hours !== undefined) {
            tr.querySelector('.hours').textContent = String(row.hours);
          } else {
            tr.querySelector('.hours').textContent = '';
          }
          if (row.minutes !== null && row.minutes !== undefined) {
            tr.querySelector('.minutes').textContent = String(row.minutes);
          } else {
            tr.querySelector('.minutes').textContent = '';
          }
        }

        // update total
        document.getElementById('totalHours').textContent = j.month_total || document.getElementById('totalHours').textContent;

        // update button states according to server row
        refreshButtonsState(row);
      } catch (e) {
        console.error(e);
        alert('Request failed');
        // on error, re-fetch to restore correct state
        fetchTodayRow();
      }
    }

    // TIME IN: disable inputs immediately to avoid double clicks, call action, then final state will be set from server response
    btnIn.onclick = async () => {
      // prevent clicking when already disabled
      if (btnIn.disabled) return;
      setButtonState(true, true);
      await handleAction('time_in');
    };

    // TIME OUT: require confirmation, then proceed; disable immediately to avoid double clicks
    btnOut.onclick = async () => {
      if (btnOut.disabled) return;
      const ok = confirm('Are you sure you want to TIME OUT now?');
      if (!ok) return;
      setButtonState(true, true);
      await handleAction('time_out');
    };

    // init
    fetchTodayRow();

    // replace formatTime with:
    function formatTime(timeStr) {
      if (!timeStr) return '';
      // accept "HH:MM" or "HH:MM:SS" and use only HH:MM
      const parts = timeStr.split(':');
      let hh = parseInt(parts[0], 10) || 0;
      const mm = (parts[1] || '00').padStart(2, '0');
      // convert to 12-hour display but without AM/PM
      hh = ((hh + 11) % 12) + 1;
      return String(hh).padStart(2,'0') + ':' + mm;
    }

    // confirm logout (both top icon and sidebar)
    (function(){
      function attachConfirm(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function(e){
          e.preventDefault();
          if (confirm('Log out?')) {
            window.location.href = el.getAttribute('href');
          }
        });
      }
      attachConfirm('top-logout');
      attachConfirm('sidebar-logout');
    })();
  </script>
</body>
</html>
