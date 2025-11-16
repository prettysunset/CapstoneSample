<?php
session_start();

// prevent browser caching of protected pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// require login (redirect to login if not authenticated)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

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

// fetch profile picture from ojt_applications
$picture = null;
if (!empty($student_id)) {
    $p = $conn->prepare("SELECT picture FROM ojt_applications WHERE student_id = ? LIMIT 1");
    $p->bind_param("i", $student_id);
    $p->execute();
    $pr = $p->get_result()->fetch_assoc();
    $p->close();
    if ($pr && !empty($pr['picture'])) {
        $picture = $pr['picture'];
    }
}

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

// --- NEW: load personal daily logs for the logged-in student
$daily_logs = [];
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date_filter = $_GET['end_date'] ?? date('Y-m-d');
// ensure start_date <= end_date_filter
if ($start_date > $end_date_filter) {
    $temp = $start_date;
    $start_date = $end_date_filter;
    $end_date_filter = $temp;
}
if (!empty($student_id)) {
    $query = "SELECT log_date, am_in, am_out, pm_in, pm_out, hours, minutes FROM dtr WHERE student_id = ? AND log_date BETWEEN ? AND ? ORDER BY log_date DESC LIMIT 500";
    $sld = $conn->prepare($query);
    $sld->bind_param("iss", $student_id, $start_date, $end_date_filter);
    $sld->execute();
    $res_logs = $sld->get_result();
    while ($lr = $res_logs->fetch_assoc()) {
        $daily_logs[] = $lr;
    }
    $sld->close();
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
      padding: 100px 32px 28px;
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
    .progress-container { background: white; border-radius: 14px; display: flex; align-items: center; padding: 14px; }
    .progress-circle { width: 88px; height: 88px; border-radius: 50%; background: conic-gradient(#4b6cb7 <?php echo $percent; ?>%, #d6d6d6 0); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #333; font-size: 18px; margin-right: 18px; }
    .progress-details { font-size: 16px; }

    .datetime { text-align: left; margin-bottom: 0; }
    .datetime h1 { font-size: 16px; font-weight: 500; margin-bottom: 2px; }
    .datetime h2 { font-size: 20px; font-weight: 700; }

    /* Daily logs table */
    .logs-container {
      width: 100%;              /* full width of the parent (.left-content) */
      max-width: none;
      box-sizing: border-box;   /* include padding in width */
      background: #fff;
      border-radius: 12px;
      padding: 12px;
      margin-top: 18px;
      box-shadow: 0 6px 18px rgba(15,23,42,0.06);
      border: 1px solid #e6e6e6;
      overflow: auto;
    }
    .logs-container h3 { margin:0 0 8px 0; font-size:16px; color:#2f3459; font-weight:700; }
    .logs-table { width:100%; border-collapse:collapse; font-size:13px; }
    .logs-table thead th {
      background:#f1f3f6; color:#2f3459; padding:8px; text-align:center;
      border:1px solid #999; font-weight:600; font-size:14px;
    }
    .logs-table tbody td { padding:8px; border:1px solid #999; text-align:center; color:#333; }
    .logs-table tbody td.left { text-align:left; padding-left:12px; }
    .no-logs { padding:16px; text-align:center; color:#666; }
    @media (max-width:900px){
      .logs-container { width:100%; margin-left: 8px; margin-right: 8px; }
    }
  </style>
</head>
<body>

  <!-- top-right outline icons: notifications, settings, logout -->
  <div id="top-icons" style="position:fixed;top:18px;right:28px;display:flex;gap:14px;z-index:1200;">
      <a id="btnNotif" href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </a>
      <a id="btnSettings" href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
      </a>
      <a id="btnLogout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
  </div>

  <!-- top-left datetime -->
  <div class="datetime" style="position:fixed;top:28px;left:248px;z-index:1200;">
    <h1 id="clock"><?php echo date("h:i A"); ?></h1>
    <h2><?php echo date("l, F d, Y"); ?></h2>
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
            <?php if ($picture): ?>
              <img src="<?php echo htmlspecialchars('../' . ltrim($picture, '/\\')); ?>" alt="Profile Picture" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
              <?php echo htmlspecialchars($initials ?: 'JS'); ?>
            <?php endif; ?>
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
          Estimated End Date: <b><?php echo $end_date; ?></b>
        </div>
      </div>

      <!-- Daily Logs (shows only logs for the logged-in student) -->
      <div class="logs-container" aria-live="polite">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <h3 style="margin: 0;">Daily Logs</h3>
          <form method="GET" style="display: flex; gap: 8px; align-items: center;">
            <label for="start_date" style="font-size: 14px;">From:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" onchange="this.form.submit()" style="padding: 4px; border: 1px solid #ccc; border-radius: 4px;">
            <label for="end_date" style="font-size: 14px;">To:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date_filter); ?>" onchange="this.form.submit()" style="padding: 4px; border: 1px solid #ccc; border-radius: 4px;">
          </form>
        </div>
        <?php if (!empty($daily_logs)): ?>
          <table class="logs-table" role="table" aria-label="Daily logs">
            <thead>
              <tr>
                <th>Date</th>
                <th>A.M. In</th>
                <th>A.M. Out</th>
                <th>P.M. In</th>
                <th>P.M. Out</th>
                <th>Hours</th>
                <th>Minutes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daily_logs as $r): ?>
                <tr>
                  <td class="left"><?php echo htmlspecialchars(date('F j, Y', strtotime($r['log_date']))); ?></td>
                  <td><?php echo htmlspecialchars(($r['am_in'] && $r['am_in'] !== '') ? $r['am_in'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars(($r['am_out'] && $r['am_out'] !== '') ? $r['am_out'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars(($r['pm_in'] && $r['pm_in'] !== '') ? $r['pm_in'] : '-'); ?></td>
                  <td><?php echo htmlspecialchars(($r['pm_out'] && $r['pm_out'] !== '') ? $r['pm_out'] : '-'); ?></td>
                  <td><?php echo (int)$r['hours']; ?></td>
                  <td><?php echo (int)$r['minutes']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-logs">Wala pang daily log entries.</div>
        <?php endif; ?>
      </div>

    </div>
  </div>
  
  <script>
    function updateClock() {
      const now = new Date();
      document.getElementById('clock').textContent =
        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    setInterval(updateClock, 1000);

    // confirm logout (both top icon and sidebar) — use replace so back can't restore protected pages
    (function(){
      function attachConfirm(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function(e){
          e.preventDefault();
          if (confirm('Log out?')) {
            // replace history entry so back button won't return to protected page
            window.location.replace(el.getAttribute('href') || '../logout.php');
          }
        });
      }
      attachConfirm('btnLogout');
      attachConfirm('sidebar-logout');
      // keep small handlers for notif/settings
      var n = document.getElementById('btnNotif');
      if (n) n.addEventListener('click', function(e){ e.preventDefault(); alert('Walang bagong notification ngayon.'); });
      var s = document.getElementById('btnSettings');
      if (s) s.addEventListener('click', function(e){ e.preventDefault(); window.location.href = 'settings.php'; });
    })();
  </script>
</body>
</html>
