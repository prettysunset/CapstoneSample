<?php
session_start();
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$display_name = 'User Name';
$display_role = 'Role';
$initials = 'UN';
$student_college = $student_course = $student_year = '';
$app_picture = ''; // NEW: path to picture from application (relative)
$app_picture_url = ''; // ensure defined
$user_id = $_SESSION['user_id'] ?? null;

$student_id = null; // ensure defined for later
$total_required = 500;
$hours_rendered = 0;
$percent = 0;
$expected_end_date = 'N/A';
$weeks = [];

if ($user_id) {
    // load user
    $u = $conn->prepare("SELECT username, first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ? LIMIT 1");
    $u->bind_param("i", $user_id);
    $u->execute();
    $ur = $u->get_result()->fetch_assoc();
    $u->close();

    if ($ur) {
        $nameParts = array_filter([($ur['first_name'] ?? ''), ($ur['middle_name'] ?? ''), ($ur['last_name'] ?? '')]);
        $display_name = trim(implode(' ', $nameParts)) ?: ($ur['username'] ?? $display_name);
        if (!empty($ur['office_name'])) {
            $display_role = 'OJT - ' . preg_replace('/\s+Office\s*$/i', '', trim($ur['office_name']));
        } else {
            $display_role = !empty($ur['role']) ? ucwords(str_replace('_',' ', $ur['role'])) : $display_role;
        }
    }

    // prefer student record linked to user account for profile fields (include student_id + hours)
    $s = $conn->prepare("SELECT student_id, first_name, last_name, college, course, year_level, email, total_hours_required, hours_rendered FROM students WHERE user_id = ? LIMIT 1");
    $s->bind_param("i", $user_id);
    $s->execute();
    $sr = $s->get_result()->fetch_assoc();
    $s->close();

    if ($sr) {
        $student_id = (int)($sr['student_id'] ?? 0);
        $display_name = trim(($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? '')) ?: $display_name;
        $student_college = $sr['college'] ?? '';
        $student_course = $sr['course'] ?? '';
        $student_year = $sr['year_level'] ?? '';
        $total_required = (int)($sr['total_hours_required'] ?? 500);
        $hours_rendered = (int)($sr['hours_rendered'] ?? 0);

        // get latest application picture for this student (if any)
        if ($student_id) {
            $ap = $conn->prepare("SELECT picture FROM ojt_applications WHERE student_id = ? AND COALESCE(picture,'') <> '' ORDER BY date_submitted DESC, application_id DESC LIMIT 1");
            $ap->bind_param("i", $student_id);
            $ap->execute();
            $apr = $ap->get_result()->fetch_assoc();
            $ap->close();
            if (!empty($apr['picture'])) {
                $raw = $apr['picture'];
                // candidate URL/path patterns to try (prefer project-root relative)
                $candidates = [
                  $raw,
                  ltrim($raw, "/\\"),
                  'uploads/' . basename($raw),
                  'upload/' . basename($raw),
                  'ojts/' . ltrim($raw, "/\\"),
                ];
                $found = '';
                foreach ($candidates as $c) {
                    $filePath = __DIR__ . '/../' . ltrim($c, "/\\");
                    if (is_file($filePath)) { $found = $c; break; }
                }
                if ($found !== '') {
                    // store path relative to this PHP file for browser src
                    $app_picture = $found;
                    $app_picture_url = '../' . ltrim($app_picture, "/\\");
                } else {
                    // log for debugging — do not expose to UI
                    error_log("ojt_profile: picture file not found for student_id {$student_id}. db='{$raw}' tried: ".implode(',', $candidates));
                    $app_picture = '';
                    $app_picture_url = '';
                }
            }
        }
        // initials for avatar (will be used only when no picture)
        $initials = '';
        foreach (explode(' ', $display_name) as $p) if ($p !== '') $initials .= strtoupper($p[0]);
        $initials = substr($initials ?: 'UN', 0, 2);
    } // <-- close if ($sr)

    /* REPLACE the existing progress / expected end calculation block with this */
$hours_rendered = 0.0;
$percent = 0;
$expected_end_date = 'N/A';

if (!empty($student_id)) {
    // find earliest time-in as start date (same logic as ojt_home.php)
    $startDateSql = null;
    $qf = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND ((am_in IS NOT NULL AND am_in<>'') OR (pm_in IS NOT NULL AND pm_in<>'')) ORDER BY log_date ASC LIMIT 1");
    $qf->bind_param('i', $student_id);
    $qf->execute();
    $fr = $qf->get_result()->fetch_assoc();
    $qf->close();
    if ($fr && !empty($fr['log_date'])) {
        $startDateSql = $fr['log_date'];
    } else {
        // fallback: try parse Orientation/Start from latest application remarks
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

    // sum hours + minutes/60 from dtr (optionally from startDate)
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

    $hours_rendered = isset($tr['total']) ? (float)$tr['total'] : 0.0;

    // percent and expected end date (8 hrs/day, count weekdays)
    $percent = ($total_required > 0) ? round(($hours_rendered / $total_required) * 100) : 0;
    $remaining = max(0, $total_required - $hours_rendered);

    if ($remaining <= 0) {
        $expected_end_date = 'Completed';
    } elseif ($startDateSql) {
        $daysNeeded = (int)ceil($remaining / 8); // 8 hrs/day
        $dt = new DateTime($startDateSql);
        $added = 0;
        while ($added < $daysNeeded) {
            $dt->modify('+1 day');
            $dow = (int)$dt->format('N'); // 1..7
            if ($dow < 6) $added++;
        }
        $expected_end_date = $dt->format('F j, Y');
    } else {
        // no start date known — estimate by today + daysNeeded (count weekdays)
        $daysNeeded = (int)ceil($remaining / 8);
        $dt = new DateTime(); // today
        $added = 0;
        while ($added < $daysNeeded) {
            $dt->modify('+1 day');
            $dow = (int)$dt->format('N');
            if ($dow < 6) $added++;
        }
        $expected_end_date = $dt->format('F j, Y');
    }
}

    // fetch last 3 weekly summaries (group by ISO week) if student_id present
    if ($student_id) {
        $q = "
          SELECT YEARWEEK(log_date,1) AS yw,
                 MIN(log_date) AS start_date,
                 MAX(log_date) AS end_date,
                 COUNT(DISTINCT log_date) AS days,
                 COALESCE(SUM(hours),0) AS hours
          FROM dtr
          WHERE student_id = ?
          GROUP BY yw
          ORDER BY start_date DESC
          LIMIT 3
        ";
        $s2 = $conn->prepare($q);
        $s2->bind_param("i", $student_id);
        $s2->execute();
        $res = $s2->get_result();
        while ($r = $res->fetch_assoc()) {
            $start = $r['start_date'];
            $end = $r['end_date'];
            if (date('M', strtotime($start)) === date('M', strtotime($end))) {
                $range = date('M j', strtotime($start)) . '-' . date('j, Y', strtotime($end));
            } else {
                $range = date('M j', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
            }
            $weeks[] = [
                'coverage' => $range,
                'days' => (int)$r['days'],
                'hours' => (int)$r['hours'],
                'progress' => $total_required > 0 ? round(($r['hours'] / $total_required) * 100) : 0
            ];
        }
        $s2->close();
    }
}

// fallback sample rows if none found (to match image)
if (empty($weeks)) {
    $weeks = [
        ['coverage'=>'Sep 22-27, 2025','days'=>3,'hours'=>24,'progress'=>4],
        ['coverage'=>'Sep 15-19, 2025','days'=>5,'hours'=>32,'progress'=>6],
        ['coverage'=>'Sep 8-12, 2025','days'=>4,'hours'=>28,'progress'=>5],
    ];
}
?>
<html>
<head>
    <title>OJT Reports</title>
    <link rel="stylesheet" type="text/css" href="../styles/main.css">
    <script src="../scripts/main.js"></script>
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
        /* keep reports tab header visible so user can always click back */
        .reports-card { background:#fff;border-radius:10px;padding:0;box-shadow:0 6px 18px rgba(0,0,0,0.04); overflow:hidden; }
        .reports-header { position: sticky; top: 0; background: #fff; padding:12px; display:flex; align-items:center; gap:8px; border-bottom:1px solid #eee; z-index:40; }
        .reports-body { padding:12px; max-height: calc(100vh - 260px); overflow:auto; } /* keep header visible while scrolling body */
        .tab-btn.active { background:#fff;border:1px solid #ddd;padding:8px 12px;border-radius:8px;font-weight:600; }
        .tab-btn { background:transparent;border:0;padding:8px 12px;border-radius:8px;cursor:pointer; }
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
        <a id="top-logout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
      <div class="sidebar">
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
    <div>
      <div style="text-align:center; padding: 8px 12px 20px;">
        <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
        <?php
          // derive initials (fallback to $display_name if $initials empty)
          $avatar_initials = '';
          if (!empty($initials)) {
            $avatar_initials = $initials;
          } else {
            foreach (explode(' ', trim($display_name)) as $p) if ($p !== '') $avatar_initials .= strtoupper($p[0]);
            $avatar_initials = substr($avatar_initials ?: 'UN', 0, 2);
          }
        ?>
        <?php if (!empty($app_picture_url)): ?>
          <img src="<?php echo htmlspecialchars($app_picture_url); ?>" alt="Avatar" style="width:76px;height:76px;object-fit:cover;display:block;">
        <?php else: ?>
          <?php echo htmlspecialchars($avatar_initials); ?>
        <?php endif; ?>
        </div>
        <h3 style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($display_name); ?></h3>
        <p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($display_role); ?></p>
      </div>

      <nav style="padding: 6px 10px 12px;">
        <a href="ojt_profile.php"
         style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>Profile</span>
        </a>

        

        <a href="ojt_reports.php" class="active" aria-current="page"
         style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <rect x="3" y="3" width="4" height="18"></rect>
          <rect x="10" y="8" width="4" height="13"></rect>
          <rect x="17" y="13" width="4" height="8"></rect>
        </svg>
        <span style="font-weight:600;">Reports</span>
        </a>
      </nav>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var sb = document.querySelector('.sidebar');
      if (sb) {
        sb.style.top = '0';
        sb.style.left = '0';
        sb.style.margin = '0';
      }
      if (document.documentElement) document.documentElement.style.margin = '0';
      if (document.body) document.body.style.margin = '0';
    });
    </script>

      <div style="padding:14px 12px 26px;">
        <!-- sidebar logout removed — use top-right logout icon instead -->
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT: top cards + reports (inserted so sidebar/top-icons remain unchanged) -->
  <main style="margin-left:220px;width:calc(100% - 220px);box-sizing:border-box;padding:84px 36px 48px;">
    <!-- top summary cards -->
    <div style="display:flex;gap:20px;align-items:stretch;margin-bottom:18px">
      <div style="background:#d6d8ee;border-radius:18px;padding:18px 22px;flex:0 0 220px;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="width:84px;height:84px;border-radius:50%;background:linear-gradient(180deg,#fff,#eef);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#2f3459">
          <?php echo htmlspecialchars($percent); ?>%
        </div>
        <div style="height:8px"></div>
        <div style="font-size:13px;color:#3a3f65;font-weight:600">Your Progress</div>
      </div>

      <div style="background:#d6d8ee;border-radius:18px;padding:18px 22px;flex:0 0 260px;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="font-size:13px;color:#3a3f65;font-weight:600">Hours Rendered</div>
        <div style="font-weight:800;color:#111;font-size:28px"><?php echo (int)$hours_rendered; ?></div>
      </div>

      <div style="background:#d6d8ee;border-radius:18px;padding:18px 22px;flex:1;display:flex;flex-direction:column;align-items:flex-start;justify-content:center">
        <div style="font-size:14px;color:#3a3f65">Estimated End Date:</div>
        <div style="font-weight:800;color:#111;font-size:20px"><?php echo htmlspecialchars($expected_end_date); ?></div>
      </div>
    </div>

    <!-- reports card (header is sticky) -->
    <div class="reports-card">
      <div class="reports-header" role="tablist" aria-label="Reports tabs">
        <div style="display:flex;gap:8px">
          <!-- reduced tabs: only Daily Time Record and Journals -->
          <button class="tab-btn active" data-panel="dtr" type="button">Daily Time Record</button>
          <button class="tab-btn" data-panel="journals" type="button">Journals</button>
        </div>

        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <button onclick="window.print()" style="background:#fff;border:1px solid #e0e0e0;padding:8px 10px;border-radius:8px;cursor:pointer">⤓ Export</button>
          <select style="padding:8px;border-radius:8px;border:1px solid #e6e6e6;background:#fff">
            <option>Sort by</option>
            <option value="date">Date</option>
            <option value="hours">Hours</option>
          </select>
        </div>
      </div>

      <div class="reports-body" id="panels">
        <!-- DTR (default active) -->
        <div data-panel="dtr" style="display:block">
          <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:6px;overflow:hidden">
            <thead>
              <tr style="background:#fbfbfe;font-weight:700;color:#333">
                <th style="padding:10px 14px">Date</th>
                <th style="padding:10px 14px">AM In</th>
                <th style="padding:10px 14px">AM Out</th>
                <th style="padding:10px 14px">PM In</th>
                <th style="padding:10px 14px">PM Out</th>
                <th style="padding:10px 14px">Hours</th>
              </tr>
            </thead>
            <tbody>
<?php
if (!empty($student_id)) {
    $stmt = $conn->prepare("SELECT log_date, am_in, am_out, pm_in, pm_out, hours FROM dtr WHERE student_id = ? ORDER BY log_date DESC LIMIT 30");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->num_rows) {
                while ($r = $res->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($r['log_date']).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($r['am_in'] ?: '—').'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($r['am_out'] ?: '—').'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($r['pm_in'] ?: '—').'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($r['pm_out'] ?: '—').'</td>';
                    echo '<td style="padding:10px 14px">'.((int)$r['hours']).'h</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6" style="padding:12px;color:#666">No records found.</td></tr>';
            }
        } else {
            error_log('ojt_reports: execute failed (dtr): ' . $stmt->error);
            echo '<tr><td colspan="6" style="padding:12px;color:#666">Unable to load records.</td></tr>';
        }
        $stmt->close();
    } else {
        error_log('ojt_reports: prepare failed (dtr): ' . $conn->error);
        echo '<tr><td colspan="6" style="padding:12px;color:#666">Unable to load records.</td></tr>';
    }
} else {
    echo '<tr><td colspan="6" style="padding:12px;color:#666">No records available.</td></tr>';
}
?>
            </tbody>
          </table>
        </div>
 
        <!-- Journals -->
        <div data-panel="journals" style="display:none">
          <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:6px;overflow:hidden">
            <thead>
              <tr style="background:#fbfbfe;font-weight:700;color:#333">
                <th style="padding:10px 14px">Date Uploaded</th>
                <th style="padding:10px 14px">Week Coverage</th>
                 <th style="padding:10px 14px">Attachment</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if (!empty($student_id)) {
                $qj = $conn->prepare("SELECT week_coverage, date_uploaded, attachment FROM weekly_journal WHERE user_id = ? ORDER BY date_uploaded DESC LIMIT 20");
                $qj->bind_param("i", $student_id);
                $qj->execute();
                $rj = $qj->get_result();
                if ($rj->num_rows) {
                  while ($row = $rj->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($row['date_uploaded']).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($row['week_coverage'] ?: '—').'</td>';
                    $att = $row['attachment'] ? '<a href="../'.htmlspecialchars($row['attachment']).'" target="_blank">View</a>' : '—';
                    echo '<td style="padding:10px 14px">'.$att.'</td>';
                    echo '</tr>';
                  }
                } else {
                  echo '<tr><td colspan="3" style="padding:12px;color:#666">No journals uploaded.</td></tr>';
                }
                $qj->close();
              } else {
                echo '<tr><td colspan="3" style="padding:12px;color:#666">No data.</td></tr>';
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <script>
    // simple tab switching — only treat panels inside .reports-body so header buttons are never hidden
    (function(){
      const tabs = document.querySelectorAll('.tab-btn');
      const panels = document.querySelectorAll('.reports-body [data-panel]');
      tabs.forEach(b=>{
        b.addEventListener('click', ()=>{
          tabs.forEach(x=>x.classList.remove('active'));
          b.classList.add('active');
          const target = b.getAttribute('data-panel');
          panels.forEach(p=> p.style.display = p.getAttribute('data-panel') === target ? 'block' : 'none');
        });
      });
      const logout = document.getElementById('top-logout');
      if (logout) logout.addEventListener('click', function(e){ e.preventDefault(); if (confirm('Logout?')) location.href = this.getAttribute('href'); });
    })();
  </script>

<script>
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