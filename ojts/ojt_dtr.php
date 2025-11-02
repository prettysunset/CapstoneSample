<?php
session_start();
require_once __DIR__ . '/../conn.php';

date_default_timezone_set('Asia/Manila');

// resolve user / student for display (same logic as other pages)
$user_id = $_SESSION['user_id'] ?? null;
$student_id = null;
$name = 'User Name';
$office_display = '';

if ($user_id) {
    $su = $conn->prepare("SELECT user_id, first_name, last_name, office_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) {
        $name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? '')) ?: $name;
        if (!empty($ur['office_name'])) $office_display = preg_replace('/\s+Office\s*$/i', '', trim($ur['office_name']));
    }
    $s = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
    $s->bind_param("i", $user_id);
    $s->execute();
    $sr = $s->get_result()->fetch_assoc();
    $s->close();
    if ($sr) $student_id = (int)$sr['student_id'];
}
$role = $office_display ? "OJT - " . $office_display : "OJT";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>OJT DTR</title>
  <link rel="stylesheet" href="stylesforojt.css">
  <style>
    /* small page-specific overrides so DTR table matches layout on other pages */
    .content-wrap { position:fixed; left:260px; top:0; right:0; bottom:0; padding:32px; background:#f6f7fb; overflow:auto; }
    .card { background:#fff;padding:20px;border-radius:12px;box-shadow:0 6px 20px rgba(47,52,89,0.04); max-width:1100px; margin:0 auto; }
    table.dtr { width:100%; border-collapse:collapse; margin-top:12px; }
    table.dtr th, table.dtr td { padding:10px; text-align:center; border-bottom:1px solid #eef1f6; font-size:13px; }
    table.dtr thead th { background:#f5f7fb; color:#2f3459; }
    .date-pill { background:#f0f0f0; padding:6px 8px; border-radius:16px; display:inline-block; color:#2f3459; font-size:13px; }
    .top-actions { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
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

  <!-- Sidebar (same structure as home/profile) -->
  <div class="sidebar">
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="text-align:center; padding: 8px 12px 20px;">
          <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
            <?php
              $initials = '';
              foreach (explode(' ', trim($name)) as $p) if ($p !== '') $initials .= strtoupper($p[0]);
              $initials = substr($initials,0,2) ?: 'UN';
              echo htmlspecialchars($initials);
            ?>
          </div>
          <h3 style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($name); ?></h3>
          <p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($role); ?></p>
        </div>

        <nav style="padding: 6px 10px 12px;">
          <a href="ojt_home.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 11.5L12 4l9 7.5"></path><path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v-5h3a1 1 0 0 0 1-1v-7"></path></svg>
            <span>Home</span>
          </a>

          <a href="ojt_profile.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Profile</span>
          </a>

          <a href="ojt_dtr.php" class="active" aria-current="page" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span style="font-weight:600;">DTR</span>
          </a>

          <a href="ojt_reports.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="4" height="18"></rect><rect x="10" y="8" width="4" height="13"></rect><rect x="17" y="13" width="4" height="8"></rect></svg>
            <span>Reports</span>
          </a>
        </nav>
      </div>

      <div style="padding:14px 12px 26px;">
        <!-- sidebar logout removed — top-right logout used -->
      </div>
    </div>
  </div>

  <!-- Main content area -->
  <div class="content-wrap">
    <div class="card">
      <div class="top-actions">
        <div>
          <h2 style="margin:0;color:#2f3459;">Daily Logs</h2>
          <p style="margin:6px 0 0;color:#6b6f8b;">Review your daily time records</p>
        </div>
        <div>
          <select id="monthSelect" style="padding:8px;border-radius:8px;border:1px solid #e6e9f2;background:#fff;">
            <?php for ($m=1;$m<=12;$m++): $label = date('F', mktime(0,0,0,$m,1)); ?>
              <option value="<?php echo $m; ?>" <?php if ($m== (int)date('n')) echo 'selected'; ?>><?php echo htmlspecialchars($label . ' ' . date('Y')); ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <table class="dtr">
        <thead>
          <tr>
            <th>DATE</th>
            <th>A.M. Arrival</th>
            <th>A.M. Departure</th>
            <th>P.M. Arrival</th>
            <th>P.M. Departure</th>
            <th>HOURS</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Render last 10 logged dtr rows as example (if student_id present)
          if ($student_id) {
              $q = $conn->prepare("SELECT log_date, am_in, am_out, pm_in, pm_out, hours FROM dtr WHERE student_id = ? ORDER BY log_date DESC LIMIT 10");
              $q->bind_param('i', $student_id);
              $q->execute();
              $res = $q->get_result();
              while ($r = $res->fetch_assoc()) {
                  $d = date('M j, Y', strtotime($r['log_date']));
                  $am_in = $r['am_in'] ?: '-';
                  $am_out = $r['am_out'] ?: '-';
                  $pm_in = $r['pm_in'] ?: '-';
                  $pm_out = $r['pm_out'] ?: '-';
                  $hrs = is_numeric($r['hours']) ? (int)$r['hours'] : '-';
                  echo "<tr>
                          <td><span class='date-pill'>{$d}</span></td>
                          <td>{$am_in}</td>
                          <td>{$am_out}</td>
                          <td>{$pm_in}</td>
                          <td>{$pm_out}</td>
                          <td style='font-weight:700;color:#2f3459;'>{$hrs}</td>
                        </tr>";
              }
              $q->close();
          } else {
              echo '<tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No records — please log in as a student.</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // confirm logout (top icon)
    (function(){
      var el = document.getElementById('top-logout');
      if (!el) return;
      el.addEventListener('click', function(e){
        e.preventDefault();
        if (confirm('Log out?')) {
          window.location.href = el.getAttribute('href');
        }
      });
    })();
  </script>
</body>
</html>