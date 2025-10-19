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
$role = "OJT";

// sample hours values — ideally read from DB students.hours_rendered / total_hours_required
$hours_completed = 180;
$total_hours = 500;
$percent = round(($hours_completed / $total_hours) * 100);
$date_started = "July 21, 2025";
$end_date = "November 13, 2025";

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
        $hours = isset($r['hours']) ? (int)$r['hours'] : 0;
        $minutes = isset($r['minutes']) ? (int)$r['minutes'] : 0;
        $total_hours = $hours + ($minutes / 60);
        $dtrMap[$d] = [
            'dtr_id' => (int)$r['dtr_id'],
            'log_date' => $r['log_date'],
            'am_in' => $r['am_in'],
            'am_out' => $r['am_out'],
            'pm_in' => $r['pm_in'],
            'pm_out' => $r['pm_out'],
            'hours' => $hours,
            'minutes' => $minutes,
            'total_hours' => $total_hours
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
    body { display: flex; background: #f9f9fb; color: #222; min-height: 100vh; }

    /* Sidebar */
    .sidebar {
      width: 220px;
      background: #3a3f63;
      color: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24px 14px;
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
    }
    .sidebar img { width: 72px; height: 72px; border-radius: 50%; background: #ccc; margin-bottom: 10px; }
    .sidebar h2 { font-size: 16px; margin-bottom: 3px; }
    .sidebar p { font-size: 13px; color: #c7c9d6; margin-bottom: 22px; }

    .menu-btn { display: flex; align-items: center; width: 100%; padding: 8px 12px; background: none; border: none; color: white; text-align: left; font-size: 14px; cursor: pointer; border-radius: 8px; transition: 0.2s; }
    .menu-btn:hover, .menu-btn.active { background: #5b5f89; }
    .bottom-title { margin-top: auto; font-size: 13px; color: #d6d6e0; padding-bottom: 6px; }

    /* Main Content */
    .main {
      margin-left: 220px;
      flex: 1;
      display: flex;
      flex-direction: row;
      justify-content: space-between;
      padding: 28px 32px;
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
      height: calc(100vh - 56px);
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
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 12px;
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

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="" alt="Profile Picture">
    <h2><?php echo htmlspecialchars($name); ?></h2>
    <p><?php echo $role; ?></p>

    <button class="menu-btn active">🏠 Home</button>
    <button class="menu-btn">👤 Profile</button>
    <button class="menu-btn">🗓️ DTR</button>
    <button class="menu-btn">📊 Reports</button>

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
          for ($d = 1; $d <= $daysInMonth; $d++) {
            // make sure $row is an array to avoid "offset on null" warnings
            $row = isset($dtrMap[$d]) && is_array($dtrMap[$d]) ? $dtrMap[$d] : [];

            $amArrival = !empty($row['am_in']) ? date('h:i A', strtotime($row['am_in'])) : '';
            $amDepart  = !empty($row['am_out']) ? date('h:i A', strtotime($row['am_out'])) : '';
            $pmArrival = !empty($row['pm_in']) ? date('h:i A', strtotime($row['pm_in'])) : '';
            $pmDepart  = !empty($row['pm_out']) ? date('h:i A', strtotime($row['pm_out'])) : '';

            $hours = (isset($row['hours']) && $row['hours'] !== 0) ? (int)$row['hours'] : '';
            $minutes = (isset($row['minutes']) && $row['minutes'] !== 0) ? (int)$row['minutes'] : '';

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

          if (typeof row.hours !== 'undefined' && typeof row.minutes !== 'undefined') {
            tr.querySelector('.hours').textContent = row.hours ? row.hours : '';
            tr.querySelector('.minutes').textContent = row.minutes ? row.minutes : '';
          } else if (typeof row.total_hours !== 'undefined') {
            const h = Math.floor(row.total_hours);
            const m = Math.round((row.total_hours - h) * 60);
            tr.querySelector('.hours').textContent = h || '';
            tr.querySelector('.minutes').textContent = m || '';
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

    function formatTime(timeStr) {
      if (!timeStr) return '';
      // accept "HH:MM" or "HH:MM:SS"
      const p = timeStr.split(':');
      if (p.length < 2) return timeStr;
      let hh = parseInt(p[0],10);
      const mm = (p[1] || '00').padStart(2,'0');
      const ampm = hh >= 12 ? 'PM' : 'AM';
      hh = ((hh + 11) % 12) + 1;
      return hh + ':' + mm + ' ' + ampm;
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
  </script>
</body>
</html>
