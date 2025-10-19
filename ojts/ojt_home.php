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
    $q = $conn->prepare("SELECT log_date, time_in, time_out, total_hours FROM dtr WHERE student_id = ? AND MONTH(log_date)=? AND YEAR(log_date)=? ORDER BY log_date");
    $q->bind_param("iii", $student_id, $month, $year);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $d = (int)date('j', strtotime($r['log_date']));
        $dtrMap[$d] = $r;
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
    .buttons button { width: 140px; padding: 12px 0; border: none; border-radius: 20px; font-size: 15px; cursor: pointer; color: white; }
    .timein { background: #5b5f89; } .timeout { background: #c3c3c3; color: #333; }

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
            $row = $dtrMap[$d] ?? null;
            $time_in = $row['time_in'] ?? '';
            $time_out = $row['time_out'] ?? '';
            $total = $row['total_hours'] ?? '';
            // map single time_in/time_out into AM arrival and PM departure (if you later track AM/PM separately adjust)
            $amArrival = $time_in ? date('h:i A', strtotime($time_in)) : '';
            $pmDeparture = $time_out ? date('h:i A', strtotime($time_out)) : '';
            $hours = $total ? floor((float)$total) : '';
            $minutes = $total ? round(((float)$total - floor((float)$total)) * 60) : '';
            if ($total) $totalHours += (float)$total;
            echo "<tr data-day=\"$d\">
                    <td>{$d}</td>
                    <td class='am-arrival'>".htmlspecialchars($amArrival)."</td>
                    <td class='am-depart'></td>
                    <td class='pm-arrival'></td>
                    <td class='pm-depart'>".htmlspecialchars($pmDeparture)."</td>
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

    // set initial button state based on today's row
    function refreshButtonsState(todayRow) {
      if (!todayRow) {
        btnIn.disabled = false;
        btnOut.disabled = true;
        return;
      }
      const hasIn = !!todayRow.time_in;
      const hasOut = !!todayRow.time_out;
      btnIn.disabled = !!hasIn;
      btnOut.disabled = !hasIn || !!hasOut;
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
        }
      } catch (e) {
        console.error(e);
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
          return;
        }
        const row = j.row; // { log_date, time_in, time_out, total_hours }
        // update table row for the day
        const d = new Date(row.log_date);
        const day = d.getDate();
        const tr = document.querySelector('#dtrTable tbody tr[data-day="'+day+'"]');
        if (tr) {
          tr.querySelector('.am-arrival').textContent = row.time_in ? formatTime(row.time_in) : '';
          tr.querySelector('.pm-depart').textContent = row.time_out ? formatTime(row.time_out) : '';
          if (row.total_hours !== null) {
            const h = Math.floor(row.total_hours);
            const m = Math.round((row.total_hours - h) * 60);
            tr.querySelector('.hours').textContent = h;
            tr.querySelector('.minutes').textContent = m;
          }
        }
        // update total
        document.getElementById('totalHours').textContent = j.month_total || document.getElementById('totalHours').textContent;
        // update button states
        refreshButtonsState(row);
      } catch (e) {
        console.error(e);
        alert('Request failed');
      }
    }

    function formatTime(timeStr) {
      // timeStr is "HH:MM:SS" — convert to h:i A
      const p = timeStr.split(':');
      if (p.length < 2) return timeStr;
      let hh = parseInt(p[0],10);
      const mm = p[1].padStart(2,'0');
      const ampm = hh >= 12 ? 'PM' : 'AM';
      hh = ((hh + 11) % 12) + 1;
      return hh + ':' + mm + ' ' + ampm;
    }

    btnIn.onclick = () => handleAction('time_in');
    btnOut.onclick = () => handleAction('time_out');

    // init
    fetchTodayRow();
  </script>
</body>
</html>
