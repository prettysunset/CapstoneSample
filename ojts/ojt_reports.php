<?php
session_start();
require_once __DIR__ . '/../conn.php';

$display_name = 'User Name';
$display_role = 'Role';
$initials = 'UN';
$student_college = $student_course = $student_year = '';
$app_picture = ''; // NEW: path to picture from application (relative)
$app_picture_url = ''; // ensure defined
$user_id = $_SESSION['user_id'] ?? null;

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

    // prefer student record linked to user account for profile fields (include student_id)
    $s = $conn->prepare("SELECT student_id, first_name, last_name, college, course, year_level, email FROM students WHERE user_id = ? LIMIT 1");
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
} // <-- close if ($user_id)
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
        <a href="ojt_home.php"
         style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <path d="M3 11.5L12 4l9 7.5"></path>
          <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
        </svg>
        <span style="font-weight:600;">Home</span>
        </a>

        <a href="ojt_profile.php"
         style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span style="font-weight:600;">Profile</span>
        </a>

        <a href="ojt_dtr.php"
         style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
          <rect x="3" y="4" width="18" height="18" rx="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <span style="font-weight:600;">DTR</span>
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

    <div class="bottom-title">OJT-MS</div>
</body>
</html>