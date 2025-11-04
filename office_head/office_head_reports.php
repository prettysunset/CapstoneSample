<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

// resolve office for this office head
$office_name = '';
$tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s = $conn->prepare("
        SELECT o.office_name
        FROM office_heads oh
        JOIN offices o ON oh.office_id = o.office_id
        WHERE oh.user_id = ? LIMIT 1
    ");
    $s->bind_param('i',$uid); $s->execute();
    $tmp = $s->get_result()->fetch_assoc(); $s->close();
    if ($tmp && !empty($tmp['office_name'])) $office_name = $tmp['office_name'];
}
if (!$office_name) {
    $s2 = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
    $s2->bind_param('i',$uid); $s2->execute();
    $tmp2 = $s2->get_result()->fetch_assoc(); $s2->close();
    if ($tmp2 && !empty($tmp2['office_name'])) $office_name = $tmp2['office_name'];
}
$office_display = preg_replace('/\s+Office\s*$/i','', trim($office_name ?: 'Unknown Office'));

// status filter from querystring — default to ongoing
$allowed = ['pending','approved','ongoing','completed','no_response','rejected'];
$statusFilter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'ongoing';
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'ongoing';

// fetch OJTs for this office with status filtering
$ojts = [];

// base SQL (we LEFT JOIN ojt_applications to evaluate application status and detect "no response")
$sql = "
  SELECT u.user_id,
         COALESCE(s.first_name, u.first_name, '') AS first_name,
         COALESCE(s.last_name,  u.last_name,  '') AS last_name,
         COALESCE(s.college,'') AS school,
         COALESCE(s.course,'') AS course,
         COALESCE(s.year_level,'') AS year_level,
         COALESCE(s.hours_rendered,0) AS hours_rendered,
         COALESCE(s.total_hours_required,500) AS total_hours_required,
         COALESCE(s.progress,0) AS progress,
         COALESCE(s.status,'') AS student_status,
         COALESCE(oa.status,'') AS application_status,
         COALESCE(lc.late_count,0) AS late_count,
         '' AS date_started,
         '' AS expected_end_date
  FROM users u
  LEFT JOIN students s ON s.user_id = u.user_id
  LEFT JOIN ojt_applications oa ON oa.student_id = s.student_id
  LEFT JOIN (
      SELECT student_id, COUNT(*) AS late_count
      FROM late_dtr
      GROUP BY student_id
  ) lc ON lc.student_id = s.student_id
  WHERE u.role = 'ojt' AND u.office_name LIKE ?
";

// append status-specific conditions
if ($statusFilter === 'pending') {
    $sql .= " AND (s.status = 'pending' OR oa.status = 'pending')";
} elseif ($statusFilter === 'approved') {
    $sql .= " AND oa.status = 'approved'";
} elseif ($statusFilter === 'ongoing') {
    $sql .= " AND s.status = 'ongoing'";
} elseif ($statusFilter === 'completed') {
    $sql .= " AND s.status = 'completed'";
} elseif ($statusFilter === 'rejected') {
    $sql .= " AND oa.status = 'rejected'";
} elseif ($statusFilter === 'no_response') {
    $sql .= " AND oa.application_id IS NULL";
}

$sql .= " ORDER BY COALESCE(s.last_name,u.last_name), COALESCE(s.first_name,u.first_name)";

$like = '%' . $office_name . '%';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $ojts[] = $r;
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Office Head — Reports</title>
<style>
  /* Sidebar safety (make sure include's sidebar displays correctly) */
  .sidebar{
    width:220px;
    background:#2f3459;
    position:fixed;
    left:0;
    top:0;
    height:100vh;
    padding:28px 18px;
    box-sizing:border-box;
    color:#fff;
    z-index:1000; /* high so it stays above page content */
  }
  .sidebar .avatar{width:76px;height:76px;border-radius:50%;background:#ffffff22;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:22px}
  .sidebar h3{margin:0;text-align:center;font-size:16px}
  .sidebar p{margin:6px 0 14px;text-align:center;color:#d6d9ee;font-size:13px}
  .sidebar nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent}
  .sidebar nav a.active{background:#fff;color:#2f3459;font-weight:700}
  .sidebar .brand{position:absolute;bottom:18px;left:0;right:0;text-align:center;font-weight:800;color:#fff}

  /* Page layout (account for sidebar width + spacing) */
  body{font-family:Poppins, sans-serif;margin:0;background:#f5f6fa;color:#2f3459}
  .main{margin-left:260px; /* leave space for sidebar + gap */ padding:28px}
  .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 6px 20px rgba(0,0,0,0.04)}
  .tabs-row{display:flex;gap:12px;align-items:center;margin-bottom:18px}
  /* updated: remove underline from tab links */
  .tab-pill{
    background:#fff;
    color:#4b4f63;
    padding:10px 18px;
    border-radius:10px;
    border:1px solid #e9eaf2;
    cursor:pointer;
    font-weight:700;
    text-decoration:none;        /* remove underline */
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  .tab-pill:hover{ text-decoration:none; }
  .tab-pill:focus{ outline:none; }
  .tab-pill.active{background:#4f4aa6;color:#fff;border-color:#4f4aa6}
  .tab-pill.disabled{cursor:default;opacity:0.9;pointer-events:none}
  .controls{display:flex;gap:12px;align-items:center;margin-left:auto}
  .search{padding:10px 14px;border-radius:12px;border:1px solid #e6e9f2;width:380px}
  .export{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:#fff;border:1px solid #e6e9f2;cursor:pointer}
  .dropdown{padding:8px 12px;border-radius:8px;border:1px solid #e6e9f2;background:#fff}
  table{width:100%;border-collapse:collapse;margin-top:14px}
  thead th{background:#f5f7fb;padding:14px;text-align:left;font-weight:700;border-bottom:1px solid #eef1f6}
  td{padding:14px;border-bottom:1px solid #f0f2f7}
  .date-badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#f4f6fa;font-size:13px;color:#5b606f}
  .small-pill{background:#f0f3ff;color:#2f3850;padding:6px 10px;border-radius:12px;font-weight:700;text-align:center;display:inline-block}
  .top-icons{position:fixed;top:18px;right:28px;display:flex;gap:12px;z-index:900} /* below sidebar */
  @media(max-width:900px){ .main{padding:16px} .search{width:160px} .sidebar{display:none} .main{margin-left:16px} }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar_home.php'; ?>

<div class="top-icons">
  <a id="btnNotif" href="notifications.php" title="Notifications" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#fff;color:#2f3459;text-decoration:none">🔔</a>
  <a id="btnSettings" href="settings.php" title="Settings" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#fff;color:#2f3459;text-decoration:none">⚙️</a>
  <a id="btnLogout" href="../logout.php" title="Logout" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#fff;color:#2f3459;text-decoration:none">⤴️</a>
</div>

<div class="main">
  <div class="card">
    <div style="display:flex;align-items:center;">
      <div>
        <h3 style="margin:0">Reports</h3>
        <div style="color:#6b6f8b;font-size:13px;margin-top:6px">All OJTs under <?= htmlspecialchars($office_display) ?></div>
      </div>

      <div class="tabs-row">
        <?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
        <a class="tab-pill <?= $current === 'office_head_reports.php' ? 'active' : '' ?>" href="office_head_reports.php">ALL OJTS</a>
        <span class="tab-pill disabled" title="Open DTR page to view daily logs">DTR</span>
        <span class="tab-pill disabled" title="Open Late DTR page to view late submissions">LATE DTR</span>

        <div class="controls">
          <input id="searchInput" class="search" placeholder="Search" />
          <button id="btnExport" class="export">⬇ Export</button>

          <select id="statusFilter" class="dropdown" aria-label="Filter by status">
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>pending</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>approved</option>
            <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : '' ?>>ongoing</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>completed</option>
            <option value="no_response" <?= $statusFilter === 'no_response' ? 'selected' : '' ?>>no response</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>rejected</option>
          </select>
        </div>
      </div>
    </div>

    <div style="overflow:auto">
      <table id="tblAll">
        <thead>
          <tr>
            <th>NAME</th>
            <th>SCHOOL</th>
            <th>COURSE</th>
            <th>DATE STARTED</th>
            <th>EXPECTED END DATE</th>
            <th>HOURS RENDERED</th>
            <th>REQUIRED HOURS</th>
            <th>PROGRESS</th>
            <th>LATE DTR</th>
          </tr>
        </thead>
        <tbody id="allBody">
          <?php if (empty($ojts)): ?>
            <tr><td colspan="9" style="text-align:center;color:#8a8f9d;padding:18px">No OJTs found for your office.</td></tr>
          <?php else: foreach ($ojts as $o): ?>
            <tr data-name="<?= htmlspecialchars(strtolower(trim($o['first_name'].' '.$o['last_name']))) ?>" data-school="<?= htmlspecialchars(strtolower($o['school'])) ?>" data-course="<?= htmlspecialchars(strtolower($o['course'])) ?>">
              <td><?= htmlspecialchars(trim($o['first_name'].' '.$o['last_name'])) ?></td>
              <td><?= htmlspecialchars($o['school'] ?: '-') ?></td>
              <td><?= htmlspecialchars($o['course'] ?: '-') ?></td>
              <td><?php echo $o['date_started'] ? '<span class="date-badge">'.date('M d, Y', strtotime($o['date_started'])).'</span>' : '-'; ?></td>
              <td><?php echo $o['expected_end_date'] ? '<span class="date-badge">'.date('M d, Y', strtotime($o['expected_end_date'])).'</span>' : '-'; ?></td>
              <td style="text-align:center"><?= (int)$o['hours_rendered'] ?></td>
              <td style="text-align:center"><?= (int)$o['total_hours_required'] ?></td>
              <td style="text-align:center"><?= is_numeric($o['progress']) ? (int)$o['progress'].'%' : '-' ?></td>
              <td style="text-align:center"><span class="small-pill"><?= (int)$o['late_count'] ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
(function(){
  const tabs = document.querySelectorAll('.tab-pill');
  function switchTo(tab){
    tabs.forEach(t=>t.classList.toggle('active', t.dataset.tab===tab));
    document.querySelectorAll('.panel').forEach(p=>p.style.display = p.id === 'panel-'+tab ? 'block' : 'none');
  }
  tabs.forEach(t=>t.addEventListener('click', ()=>switchTo(t.dataset.tab)));

  // search filter for ALL OJTs table
  const search = document.getElementById('searchInput');
  search.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#allBody tr').forEach(tr=>{
      if (!tr.dataset || !tr.dataset.name) return;
      const hay = (tr.dataset.name + ' ' + tr.dataset.school + ' ' + tr.dataset.course);
      tr.style.display = q === '' || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });

  // status dropdown — redirect with query param (default handled server-side)
  document.getElementById('statusFilter').addEventListener('change', function(){
    const v = this.value;
    const url = new URL(window.location.href);
    url.searchParams.set('status', v);
    window.location.href = url.toString();
  });

  // export button placeholder
  document.getElementById('btnExport').addEventListener('click', function(){
    alert('Export not implemented — will export current table to CSV when enabled.');
  });

  // top icons actions
  document.addEventListener('click', function(e){
    if (e.target.id === 'btnLogout') {
      if (!confirm('Log out?')) return;
      window.location.replace(e.target.getAttribute('href') || '../logout.php');
      e.preventDefault();
    }
  });
})();
</script>
</body>
</html>