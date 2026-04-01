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
$avatarCol = null;

function normalize_avatar_url($rawPath) {
  $raw = trim((string)$rawPath);
  if ($raw === '') return '';
  if (preg_match('/^(https?:)?\/\//i', $raw) || strpos($raw, 'data:') === 0) return $raw;
  if (strpos($raw, '../') === 0 || strpos($raw, '/') === 0) return $raw;
  return '../' . ltrim($raw, '/\\');
}

function format_time_12_no_suffix($rawTime) {
  $s = trim((string)$rawTime);
  if ($s === '' || $s === '00:00:00') return '—';

  if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $s, $m)) {
    return $s;
  }

  $h = (int)$m[1];
  $min = $m[2];
  if ($h < 0 || $h > 23) return $s;

  $h12 = $h % 12;
  if ($h12 === 0) $h12 = 12;

  return $h12 . ':' . $min;
}

$resCols = $conn->query("SHOW COLUMNS FROM users");
if ($resCols) {
  $cols = [];
  while ($r = $resCols->fetch_assoc()) $cols[] = $r['Field'];
  foreach (['avatar', 'profile_pic', 'photo', 'picture'] as $c) {
    if (in_array($c, $cols, true)) { $avatarCol = $c; break; }
  }
}

$student_id = null; // ensure defined for later
$total_required = 500;
$hours_rendered = 0;
$percent = 0;
$expected_end_date = 'N/A';
$weeks = [];

if ($user_id) {
    // load user
  $avatarSelect = $avatarCol ? ", `$avatarCol` AS user_avatar" : '';
  $u = $conn->prepare("SELECT username, first_name, middle_name, last_name, role, office_name, status" . $avatarSelect . " FROM users WHERE user_id = ? LIMIT 1");
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
        if (!empty($ur['user_avatar'])) {
          $app_picture_url = normalize_avatar_url($ur['user_avatar']);
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

        // fallback: get latest application picture only when no users avatar is available
        if ($student_id && empty($app_picture_url)) {
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
                  $app_picture_url = normalize_avatar_url($app_picture);
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

// choose id used in DTR/journals: prefer users.user_id (DTR stores users.user_id) but fall back to students.student_id
$dtrUserId = null;
if (!empty($user_id)) {
    $dtrUserId = (int)$user_id;
} elseif (!empty($student_id)) {
    $dtrUserId = (int)$student_id;
}

// Always compute actual hours from dtr (hours + minutes/60). Use optional startDate extraction only for expected-end logic.
$startDateSql = null;
if (!empty($dtrUserId)) {
    // earliest recorded time-in (if any)
    $qf = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND ((am_in IS NOT NULL AND am_in<>'') OR (pm_in IS NOT NULL AND pm_in<>'')) ORDER BY log_date ASC LIMIT 1");
    $qf->bind_param('i', $dtrUserId);
    $qf->execute();
    $fr = $qf->get_result()->fetch_assoc();
    $qf->close();
    if ($fr && !empty($fr['log_date'])) {
        $startDateSql = $fr['log_date'];
    } else {
        // fallback: parse Orientation/Start from latest application remarks (optional)
        $qa = $conn->prepare("SELECT remarks FROM ojt_applications WHERE student_id = ? ORDER BY date_updated DESC, application_id DESC LIMIT 1");
        $qa->bind_param('i', $dtrUserId);
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

    // Sum hours from DTR: hours + minutes/60
    $qSum = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ?");
    $qSum->bind_param("i", $dtrUserId);
    $qSum->execute();
    $trSum = $qSum->get_result()->fetch_assoc();
    $qSum->close();
    $hours_rendered = isset($trSum['total']) ? (float)$trSum['total'] : 0.0;

    // percent (cap at 100)
    if ($total_required > 0) {
        $pct = ($hours_rendered / (float)$total_required) * 100.0;
        $percent = (int) round(min(100, max(0, $pct)));
    } else {
        $percent = 0;
    }
    $remaining = max(0, $total_required - $hours_rendered);

    // NEW: respect users.status rules:
    // - approved => expected end date = '-'
    // - ongoing  => use earliest DTR log_date (startDateSql) as Date Started and estimate end date from that (Mon-Fri, 8hrs/day)
    // - otherwise => existing behavior (completed => last dtr; fallback estimate from orientation)
    $user_status = strtolower($ur['status'] ?? '');

    if ($user_status === 'approved') {
        $expected_end_date = '-';
    } elseif ($user_status === 'ongoing') {
        if ($remaining <= 0) {
            // already finished — show last DTR as completed
            $lastDateSql = null;
            $qld = $conn->prepare("SELECT MAX(log_date) AS last_date FROM dtr WHERE student_id = ?");
            $qld->bind_param("i", $dtrUserId);
            if ($qld->execute()) {
                $ld = $qld->get_result()->fetch_assoc();
                if ($ld && !empty($ld['last_date'])) $lastDateSql = $ld['last_date'];
            }
            $qld->close();
            if ($lastDateSql) {
                $expected_end_date = (new DateTime($lastDateSql))->format('F j, Y');
            } else {
                $expected_end_date = 'Completed';
            }
        } else {
            // need start date from DTR — if none, leave '-' (per request: use earliest dtr.log_date)
            if (!empty($startDateSql)) {
                $daysNeeded = (int)ceil($remaining / 8); // 8 hrs/day
                $dt = new DateTime($startDateSql);
                $counted = 0;
                // count the start day if it's a weekday
                while ($counted < $daysNeeded) {
                    $dow = (int)$dt->format('N'); // 1..7
                    if ($dow <= 5) $counted++;
                    if ($counted >= $daysNeeded) break;
                    $dt->modify('+1 day');
                }
                $expected_end_date = $dt->format('F j, Y');
            } else {
                // no DTR start -> cannot compute reliably per requirement
                $expected_end_date = '-';
            }
        }
    } else {
        // other statuses (including completed) — preserve previous behavior
        if ($remaining <= 0) {
            $lastDateSql = null;
            $qld = $conn->prepare("SELECT MAX(log_date) AS last_date FROM dtr WHERE student_id = ?");
            $qld->bind_param("i", $dtrUserId);
            if ($qld->execute()) {
                $ld = $qld->get_result()->fetch_assoc();
                if ($ld && !empty($ld['last_date'])) $lastDateSql = $ld['last_date'];
            }
            $qld->close();
            if ($lastDateSql) {
                $expected_end_date = (new DateTime($lastDateSql))->format('F j, Y');
            } else {
                $expected_end_date = 'Completed';
            }
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

    // fetch last 3 weekly summaries (group by ISO week) using same dtrUserId
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
    $s2->bind_param("i", $dtrUserId);
    $s2->execute();
    $res = $s2->get_result();
    $weeks = [];
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
} // end if (!empty($dtrUserId))

// if no dtrUserId then $hours_rendered/$percent remain 0 and weeks falls back later

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

} // end if ($user_id)

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
    <title>DTR</title>
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
        <a id="btnNotif" href="#" title="Notifications" aria-haspopup="dialog" aria-expanded="false" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;position:relative;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
          <span class="notif-count" aria-hidden="true" style="position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#ef4444;color:#fff;font-size:11px;line-height:18px;text-align:center;display:none;">0</span>
        </a>
        <button id="btnSettings" type="button" title="Settings" aria-label="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;background:transparent;border:0;box-shadow:none;cursor:pointer;">
           <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
      </button>
        <a id="top-logout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
      <div class="sidebar">
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
    <div>
      <div style="text-align:center; padding: 8px 12px 20px;">
        <div id="sidebarAvatarWrap" style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
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
          <img id="sidebarAvatarImg" src="<?php echo htmlspecialchars($app_picture_url . (strpos($app_picture_url, '?') === false ? '?' : '&') . 'v=' . time()); ?>" alt="Avatar" style="width:76px;height:76px;object-fit:cover;display:block;">
        <?php else: ?>
          <?php echo htmlspecialchars($avatar_initials); ?>
        <?php endif; ?>
        </div>
        <h3 id="sidebarDisplayName" style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($display_name); ?></h3>
        <p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($display_role); ?></p>
      </div>

      <?php
          $__curr = basename($_SERVER['PHP_SELF'] ?? '');
          $__active_reports = $__curr === 'ojt_reports.php';
          $__active_profile = $__curr === 'ojt_profile.php';
          $__link_base = 'display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;';
      ?>
      <nav style="padding: 6px 10px 12px;">
        <a href="ojt_reports.php" <?php if ($__active_reports) echo 'class="active" aria-current="page"'; ?>
           style="<?php echo $__link_base; ?><?php echo $__active_reports ? 'color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);' : 'color:#fff;background:transparent;'; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <rect x="3" y="3" width="4" height="18"></rect>
          <rect x="10" y="8" width="4" height="13"></rect>
          <rect x="17" y="13" width="4" height="8"></rect>
        </svg>
        <span style="font-weight:600;">DTR</span>
        </a>

        <a href="ojt_profile.php" <?php if ($__active_profile) echo 'class="active" aria-current="page"'; ?>
           style="<?php echo $__link_base; ?><?php echo $__active_profile ? 'color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);' : 'color:#fff;background:transparent;'; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>Profile</span>
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

    <!-- ADDED: confirm for top-right logout icon (matches ojt_profile.php behavior) -->
    <script>
      (function(){
        var topLogout = document.getElementById('top-logout');
        if (topLogout) {
          topLogout.addEventListener('click', function(e){
            e.preventDefault();
            if (confirm('Logout?')) {
              window.location = this.getAttribute('href') || '../logout.php';
            }
          });
        }
      })();
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
          <!-- single tab: Daily Time Record -->
          <button class="tab-btn active" data-panel="dtr" type="button">Daily Time Record</button>
        </div>

        <!-- DTR controls (same line as tabs). Sort dropdown removed per request -->
        <div id="dtrControls" style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <label for="dtr_from" style="font-weight:600;margin-right:6px">From</label>
          <input id="dtr_from" type="date" style="padding:8px;border-radius:8px;border:1px solid #e6e6e6;background:#fff">
          <label for="dtr_to" style="font-weight:600;margin-left:8px;margin-right:6px">To</label>
          <input id="dtr_to" type="date" style="padding:8px;border-radius:8px;border:1px solid #e6e6e6;background:#fff">
          <button id="btnExport" onclick="window.print()" style="background:#fff;border:1px solid #e0e0e0;padding:8px 10px;border-radius:8px;cursor:pointer">⤓ Export</button>
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
                <th style="padding:10px 14px">Minutes</th>
              </tr>
            </thead>
            <tbody>
<?php
if (!empty($dtrUserId)) {
    // include minutes column from DB
    $stmt = $conn->prepare("SELECT log_date, am_in, am_out, pm_in, pm_out, hours, minutes FROM dtr WHERE student_id = ? ORDER BY log_date DESC LIMIT 30");
    if ($stmt) {
        $stmt->bind_param("i", $dtrUserId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->num_rows) {
                while ($r = $res->fetch_assoc()) {
                    // expose raw ISO log_date for client-side filtering
                    $iso = htmlspecialchars($r['log_date'] ?: '');
                    echo '<tr data-log-date="'.$iso.'">';
                    // format log_date as "November 17, 2025"
                    $logDate = (!empty($r['log_date']) && strtotime($r['log_date'])) ? date('F j, Y', strtotime($r['log_date'])) : ($r['log_date'] ?: '—');
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($logDate).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars(format_time_12_no_suffix($r['am_in'] ?? '')).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars(format_time_12_no_suffix($r['am_out'] ?? '')).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars(format_time_12_no_suffix($r['pm_in'] ?? '')).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars(format_time_12_no_suffix($r['pm_out'] ?? '')).'</td>';
                    echo '<td style="padding:10px 14px">'.((int)$r['hours']).'h</td>';
                    echo '<td style="padding:10px 14px">'.(isset($r['minutes']) ? ((int)$r['minutes']).'m' : '—').'</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7" style="padding:12px;color:#666">No records found.</td></tr>';
            }
        } else {
            error_log('ojt_reports: execute failed (dtr): ' . $stmt->error);
            echo '<tr><td colspan="7" style="padding:12px;color:#666">Unable to load records.</td></tr>';
        }
        $stmt->close();
    } else {
        error_log('ojt_reports: prepare failed (dtr): ' . $conn->error);
        echo '<tr><td colspan="7" style="padding:12px;color:#666">Unable to load records.</td></tr>';
    }
} else {
    echo '<tr><td colspan="7" style="padding:12px;color:#666">No records available.</td></tr>';
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
              if (!empty($student_id) || !empty($user_id)) {
                // weekly_journal.user_id normally references students.student_id. prefer that, but allow fallback.
                $journalUserId = !empty($student_id) ? (int)$student_id : (int)$user_id;
                $qj = $conn->prepare("SELECT week_coverage, date_uploaded, attachment FROM weekly_journal WHERE user_id = ? ORDER BY date_uploaded DESC LIMIT 20");
                $qj->bind_param("i", $journalUserId);
                $qj->execute();
                $rj = $qj->get_result();
                if ($rj && $rj->num_rows) {
                  while ($row = $rj->fetch_assoc()) {
                    echo '<tr>';
                    $dUploaded = (!empty($row['date_uploaded']) && strtotime($row['date_uploaded'])) ? date('F j, Y', strtotime($row['date_uploaded'])) : ($row['date_uploaded'] ?: '—');
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($dUploaded).'</td>';
                    echo '<td style="padding:10px 14px">'.htmlspecialchars($row['week_coverage'] ?: '—').'</td>';

                    // show view icon only (open in new tab, no download)
                    if (!empty($row['attachment'])) {
                        $url = '../' . ltrim($row['attachment'], "/\\");
                        $eye = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                        $att = '<a href="'.htmlspecialchars($url).'" target="_blank" rel="noopener noreferrer" title="Open attachment" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#f0f0f5;color:#2f3459;text-decoration:none;margin-left:8px">'.$eye.'</a>';
                    } else {
                        $att = '—';
                    }
                    echo '<td style="padding:10px 14px;text-align:center">'.htmlspecialchars($att).'</td>';
                    echo '</tr>';
                  }
                } else {
                  echo '<tr><td colspan="3" style="padding:12px;color:#666">No records found.</td></tr>';
                }
              } else {
                echo '<tr><td colspan="3" style="padding:12px;color:#666">No records available.</td></tr>';
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
    <!-- Settings overlay: match OJT profile — full-screen iframe, inner page provides white-card chrome -->
    <div id="settings-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,20,40,0.6);align-items:center;justify-content:center;z-index:12000;">
      <div class="modal" style="width:100%;height:100vh;max-width:100%;max-height:100vh;padding:0;background:transparent;display:flex;align-items:center;justify-content:center;position:relative;">
        <iframe id="settings-iframe" src="" title="Settings" style="width:100%;height:100%;border:0;display:block;"></iframe>
      </div>
    </div>

    <script>
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
          var wrap = document.getElementById('sidebarAvatarWrap');
          var img = document.getElementById('sidebarAvatarImg');
          if (wrap && !img) {
            wrap.innerHTML = '';
            img = document.createElement('img');
            img.id = 'sidebarAvatarImg';
            img.alt = 'Avatar';
            img.style.width = '76px';
            img.style.height = '76px';
            img.style.objectFit = 'cover';
            img.style.display = 'block';
            wrap.appendChild(img);
          }
          if (img) img.src = d.avatar + (d.avatar.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
        }
        if (typeof d.name !== 'undefined') {
          var h = document.getElementById('sidebarDisplayName');
          if (h) h.textContent = d.name;
        }
      }catch(err){}
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
</body>
</html>