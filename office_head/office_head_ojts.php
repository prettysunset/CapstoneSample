<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id = (int)$_SESSION['user_id'];

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// resolve display name and office
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($user_name === '') {
    $su = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) $user_name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? ''));
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
           COALESCE(NULLIF(u.first_name, ''), NULLIF(s.first_name, ''), '') AS first_name,
           COALESCE(NULLIF(u.last_name, ''), NULLIF(s.last_name, ''), '') AS last_name,
           COALESCE(s.college, '') AS school,
           COALESCE(s.course, '') AS course,
           COALESCE(s.year_level, '') AS year_level,
           COALESCE(s.hours_rendered, 0) AS hours_completed,
           COALESCE(s.total_hours_required, 500) AS hours_required,
           COALESCE(s.status, '') AS student_status
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

// split into tabs:
// - Completed: explicitly marked 'completed' (prefer this)
// - For Evaluation: reached or surpassed required hours but not yet marked completed
// - Active: everything else
$for_eval = []; $active = []; $completedArr = [];
foreach ($ojts as $r) {
    $hc = (int)($r['hours_completed'] ?? 0);
    $hr = (int)($r['hours_required'] ?? 0);
    $status = strtolower(trim((string)($r['student_status'] ?? '')));

    if ($status === 'completed') {
        // already completed — show under Completed tab
        $completedArr[] = $r;
    } elseif ($hr > 0 && $hc >= $hr) {
        // reached required hours but not yet marked completed — show for evaluation
        $for_eval[] = $r;
    } else {
        $active[] = $r;
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

    <a href="office_head_reports.php" title="Reports" style="display:flex;align-items:center;gap:8px;color:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="10" width="4" height="10"></rect>
        <rect x="10" y="6" width="4" height="14"></rect>
        <rect x="17" y="2" width="4" height="18"></rect>
      </svg>
      <span>Reports</span>
    </a>

  </nav>


  <div style="position:absolute;bottom:20px;width:100%;text-align:center;font-weight:700;padding-bottom:6px">OJT-MS</div>
</div>

<div class="main">
  <!-- top-right outline icons: notifications, settings, logout — moved inside .main to match office_head_home.php -->
  <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
      <a id="btnNotif" href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </a>
      <a id="btnSettings" href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
      </a>
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
          <div class="tab" data-target="panel-completed">Completed</div>
        </div>
        </div>
      <div style="display:flex;gap:12px;align-items:center">
        <!-- Create OJT removed per request -->
      </div>
    </div>

    <div class="controls">
      <input class="search" placeholder="Search" id="searchInput" />
      <select id="sortSelect" style="padding:10px;border-radius:10px;border:1px solid #e6e9f2;background:#fff">
        <option value="">Sort by</option>
        <option value="name">Name</option>
        <option value="hours">Hours</option>
      </select>
    </div>

    <div id="panel-active" class="tab-panel active">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>View</th></tr>
          </thead>
          <tbody>
            <?php if (empty($active)): ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No active OJTs.</td></tr>
            <?php else: foreach ($active as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars((int)$o['hours_completed'] . ' / ' . (int)$o['hours_required'] . ' hrs'); ?></td>
                <td><button class="view-btn" data-id="<?php echo (int)$o['user_id']; ?>">👁️</button></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-eval" class="tab-panel">
      <h4 style="margin:10px 0 6px">For Evaluation</h4>
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
                <td><?php echo htmlspecialchars((int)$o['hours_completed'] . ' / ' . (int)$o['hours_required'] . ' hrs'); ?></td>
                <td style="white-space:nowrap">
                  <button class="view-btn" data-id="<?php echo (int)$o['user_id']; ?>">👁️</button>
                  <button class="view-btn" data-id="<?php echo (int)$o['user_id']; ?>" title="Evaluate" style="margin-left:8px">📄</button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-completed" class="tab-panel">
      <div style="overflow:auto">
        <table>
          <thead>
            <tr><th>Name</th><th>School</th><th>Course</th><th>Year Level</th><th>Hours</th><th>Remarks</th><th>View</th></tr>
          </thead>
          <tbody>
            <?php if (empty($completedArr)): ?>
              <tr><td colspan="7" style="text-align:center;color:#8a8f9d;padding:18px;">No completed OJTs.</td></tr>
            <?php else: foreach ($completedArr as $o): ?>
              <tr>
                <td><?php echo htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])); ?></td>
                <td><?php echo htmlspecialchars($o['school'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['course'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($o['year_level'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars((int)$o['hours_completed'] . ' / ' . (int)$o['hours_required'] . ' hrs'); ?></td>
                <td><?php echo htmlspecialchars($o['remarks'] ?? '-'); ?></td>
                <td><button class="view-btn" data-id="<?php echo (int)$o['user_id']; ?>">👁️</button></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('click', function(e){
  if (e.target.matches('.view-btn')) {
    const id = e.target.getAttribute('data-id');
    if (id) {
      // open user profile in a new tab
      window.open('user_profile.php?id=' + encodeURIComponent(id), '_blank');
    }
  }
});

// tabs switching
document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', function() {
    const target = this.getAttribute('data-target');

    // hide all tab panels
    document.querySelectorAll('.tab-panel').forEach(panel => {
      panel.classList.remove('active');
    });

    // show the selected tab panel
    document.getElementById(target).classList.add('active');

    // update tab active state
    document.querySelectorAll('.tab').forEach(t => {
      t.classList.remove('active');
    });
    this.classList.add('active');
  });
});

// search and sort functionality
document.getElementById('searchInput').addEventListener('input', function() {
  const query = this.value.toLowerCase();
  filterTable(query);
});

document.getElementById('sortSelect').addEventListener('change', function() {
  const sortBy = this.value;
  sortTable(sortBy);
});

function filterTable(query) {
  document.querySelectorAll('.tab-panel.active table tbody tr').forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}

function sortTable(sortBy) {
  const table = document.querySelector('.tab-panel.active table');
  const rows = Array.from(table.querySelectorAll('tbody tr'));

  rows.sort((a, b) => {
    let aValue, bValue;
    if (sortBy === 'name') {
      aValue = a.querySelector('td').innerText.toLowerCase();
      bValue = b.querySelector('td').innerText.toLowerCase();
    } else if (sortBy === 'hours') {
      aValue = parseInt(a.querySelector('td:nth-child(5)').innerText);
      bValue = parseInt(b.querySelector('td:nth-child(5)').innerText);
    }
    return aValue > bValue ? 1 : -1;
  });

  rows.forEach(row => {
    table.querySelector('tbody').appendChild(row);
  });
}
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
</script>
</body>
</html>