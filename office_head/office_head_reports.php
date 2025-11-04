<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$uid = (int)$_SESSION['user_id'];
$su = $conn->prepare("SELECT first_name,last_name,office_name FROM users WHERE user_id=? LIMIT 1");
$su->bind_param('i',$uid); $su->execute(); $u=$su->get_result()->fetch_assoc(); $su->close();
$display_name = trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: 'Office Head';
$office_display = $u['office_name'] ?? '';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Office Head — Reports</title><style>body{font-family:Poppins;margin:0;background:#f5f6fa}.sidebar{width:220px;background:#2f3459;position:fixed;height:100vh;padding-top:30px;color:#fff}.main{margin-left:240px;padding:20px}</style></head>
<body>
<div class="sidebar">
  <div style="text-align:center;padding:18px 12px 8px;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fff;color:#2f3459;display:inline-flex;align-items:center;justify-content:center;font-weight:700;margin:6px auto;font-size:20px;">
      <?= htmlspecialchars(mb_strtoupper(substr(trim($display_name),0,1) ?: 'O')) ?>
    </div>
    <h3 style="margin:8px 0 4px;font-size:16px;"><?= htmlspecialchars($display_name) ?></h3>
    <p style="margin:0;font-size:13px;opacity:0.9">Office Head — <?= htmlspecialchars(preg_replace('/\s+Office\s*$/i','',$office_display)) ?></p>
  </div>
  <nav style="margin-top:14px;display:flex;flex-direction:column;gap:8px;padding:0 12px;">
    <a href="office_head_home.php">Home</a>
    <a href="office_head_ojts.php">OJTs</a>
    <a href="office_head_dtr.php">DTR</a>
    <a href="office_head_reports.php" style="background:#fff;color:#2f3459;padding:8px;border-radius:10px">Reports</a>
  </nav>
  <h3 style="position:absolute; bottom:20px; width:100%; text-align:center;">OJT-MS</h3>
</div>
<div class="main">
  <h1>Reports</h1>
  <p>Generate reports for <?= htmlspecialchars(preg_replace('/\s+Office\s*$/i','',$office_display)) ?>.</p>
  <!-- Add report generation UI here -->
</div>
</body>
</html>