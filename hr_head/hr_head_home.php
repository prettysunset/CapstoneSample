<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// fetch user info
$stmtUser = $conn->prepare("SELECT first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc() ?: [];
$stmtUser->close();

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// which tab: pending (default) or rejected
$tab = isset($_GET['tab']) && $_GET['tab'] === 'rejected' ? 'rejected' : 'pending';

// counts
$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'pending'");
$stmt->execute();
$stmt->bind_result($pending_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'rejected'");
$stmt->execute();
$stmt->bind_result($rejected_count);
$stmt->fetch();
$stmt->close();

// fetch applications for current tab
$statusFilter = $tab === 'rejected' ? 'rejected' : 'pending';
$q = "SELECT oa.application_id, oa.date_submitted, oa.status,
             s.first_name AS s_first, s.last_name AS s_last, s.address AS s_address,
             o1.office_name AS opt1, o2.office_name AS opt2
      FROM ojt_applications oa
      LEFT JOIN students s ON oa.student_id = s.student_id
      LEFT JOIN offices o1 ON oa.office_preference1 = o1.office_id
      LEFT JOIN offices o2 ON oa.office_preference2 = o2.office_id
      WHERE oa.status = ?
      ORDER BY oa.date_submitted DESC, oa.application_id DESC";
$stmtApps = $conn->prepare($q);
$stmtApps->bind_param("s", $statusFilter);
$stmtApps->execute();
$result = $stmtApps->get_result();
$apps = $result->fetch_all(MYSQLI_ASSOC);
$stmtApps->close();

// current server date/time
$current_time = date("g:i A");
$current_date = date("l, F j, Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OJT-MS | HR Head Dashboard</title>
<style>
    *{box-sizing:border-box;font-family:'Poppins',sans-serif}
    body{background:#f7f8fc;display:flex;min-height:100vh;margin:0}
    .sidebar{background:#2f3850;width:220px;color:#fff;display:flex;flex-direction:column;align-items:center;padding:30px 0}
    .profile{text-align:center;margin-bottom:20px}
    .profile img{width:90px;height:90px;border-radius:50%;background:#cfd3db;margin-bottom:10px}
    .profile h3{font-size:16px;font-weight:600}
    .profile p{font-size:13px;color:#bfc4d1}
    .nav{display:flex;flex-direction:column;gap:10px;width:100%}
    .nav a{color:#fff;text-decoration:none;padding:10px 20px;display:flex;align-items:center;gap:10px;border-radius:25px;margin:0 15px}
    .nav a:hover,.nav a.active{background:#fff;color:#2f3850;font-weight:600}
    .main{flex:1;padding:24px}
    .top-section{display:flex;justify-content:space-between;gap:20px;margin-bottom:20px}
    .datetime h2{font-size:22px;color:#2f3850;margin:0}
    .datetime p{color:#6d6d6d;margin:0}
    .table-container{background:#fff;border-radius:8px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
    .table-tabs{display:flex;gap:16px;margin-bottom:12px;border-bottom:2px solid #eee}
    .table-tabs a{padding:8px 12px;text-decoration:none;color:#555;border-radius:6px}
    .table-tabs a.active{background:#2f3850;color:#fff}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px;border:1px solid #eee;text-align:left}
    th{background:#f5f6fa}
    .actions{display:flex;gap:8px;justify-content:center}
    .actions button{border:none;background:none;cursor:pointer;font-size:16px}
    .approve{color:green} .reject{color:red} .view{color:#0b74de}
    .empty{padding:20px;text-align:center;color:#666}
</style>
</head>
<body>

<div class="sidebar">
    <div class="profile">
        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
        <h3><?php echo htmlspecialchars($full_name ?: ($_SESSION['username'] ?? '')); ?></h3>
        <p><?php echo htmlspecialchars($role_label); ?></p>
        <?php if(!empty($user['office_name'])): ?>
            <p style="font-size:12px;color:#bfc4d1"><?php echo htmlspecialchars($user['office_name']); ?></p>
        <?php endif; ?>
    </div>

    <div class="nav">
        <a href="#" class="active">🏠 Home</a>
        <a href="#">👥 OJTs</a>
        <a href="#">🕒 DTR</a>
        <a href="#">⚙️ Accounts</a>
        <a href="#">📊 Reports</a>
    </div>

    <p style="margin-top:auto;font-weight:600">OJT-MS</p>
</div>

<div class="main">
    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?php echo $current_time; ?></h2>
                <p><?php echo $current_date; ?></p>
            </div>
        </div>

        <div style="min-width:320px">
            <div style="display:flex;gap:12px;align-items:center;justify-content:flex-end">
                <div style="background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.04);text-align:center">
                    <div style="font-size:20px;font-weight:700"><?php echo (int)$pending_count; ?></div>
                    <div style="color:#666;font-size:13px">Pending</div>
                </div>
                <div style="background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.04);text-align:center">
                    <div style="font-size:20px;font-weight:700"><?php echo (int)$rejected_count; ?></div>
                    <div style="color:#666;font-size:13px">Rejected</div>
                </div>
            </div>
        </div>
    </div> <!-- end .top-section -->

<?php
// --- Office slot availability block ---
// detect capacity column name (common variants)
$capacityCol = null;
$variants = ['slot_capacity','capacity','slots','max_slots'];
foreach ($variants as $v) {
    $res = $conn->query("SHOW COLUMNS FROM offices LIKE '".$conn->real_escape_string($v)."'");
    if ($res && $res->num_rows > 0) { $capacityCol = $v; break; }
}

// fetch offices
if ($capacityCol) {
    $sql = "SELECT office_id, office_name, `$capacityCol` AS capacity FROM offices ORDER BY office_name";
} else {
    $sql = "SELECT office_id, office_name FROM offices ORDER BY office_name";
}
$offices = [];
if ($resOff = $conn->query($sql)) {
    while ($r = $resOff->fetch_assoc()) $offices[] = $r;
    $resOff->free();
}

// prepare stmt to count approved/assigned OJTs per office
$stmtCount = $conn->prepare("
    SELECT COUNT(DISTINCT student_id) AS filled
    FROM ojt_applications
    WHERE (office_preference1 = ? OR office_preference2 = ?) AND status = 'approved'
");
?>
<div class="table-container" style="margin-bottom:16px">
    <h3 style="margin:0 0 12px 0">OJT Slot Availability by Office</h3>

    <?php if (empty($offices)): ?>
        <div class="empty">No offices found.</div>
    <?php else: ?>
        <table style="margin-bottom:0">
            <thead>
                <tr>
                    <th>Office</th>
                    <th>Capacity</th>
                    <th>Filled</th>
                    <th>Available</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($offices as $o): 
                $office_id = (int)$o['office_id'];
                $capacity = isset($o['capacity']) ? (int)$o['capacity'] : null;
                $filled = 0;
                $stmtCount->bind_param("ii", $office_id, $office_id);
                $stmtCount->execute();
                $stmtCount->bind_result($filled);
                $stmtCount->fetch();
                $stmtCount->reset();
                $available = is_null($capacity) ? '-' : max(0, $capacity - $filled);
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($o['office_name']); ?></td>
                    <td><?php echo is_null($capacity) ? '—' : (int)$capacity; ?></td>
                    <td><?php echo (int)$filled; ?></td>
                    <td><?php echo $available === '-' ? '—' : (int)$available; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$stmtCount->close();
?>

    <div class="table-container">
        <div class="table-tabs">
            <a class="<?php echo $tab === 'pending' ? 'active' : ''; ?>" href="?tab=pending">Pending Approvals (<?php echo (int)$pending_count; ?>)</a>
            <a class="<?php echo $tab === 'rejected' ? 'active' : ''; ?>" href="?tab=rejected">Rejected Students (<?php echo (int)$rejected_count; ?>)</a>
        </div>

        <?php if (count($apps) === 0): ?>
            <div class="empty">No <?php echo $tab === 'rejected' ? 'rejected' : 'pending'; ?> applications found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date Submitted</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>1st Option</th>
                    <th>2nd Option</th>
                    <th style="text-align:center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apps as $row): ?>
                <tr>
                    <td><?php
                        echo $row['date_submitted'] ? htmlspecialchars(date("M j, Y", strtotime($row['date_submitted']))) : '—';
                    ?></td>
                    <td><?php echo htmlspecialchars(trim(($row['s_first'] ?? '') . ' ' . ($row['s_last'] ?? '')) ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['s_address'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['opt1'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['opt2'] ?: 'N/A'); ?></td>
                    <td class="actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="application_id" value="<?php echo (int)$row['application_id']; ?>">
                            <button type="button" class="view" title="View" onclick="window.location.href='application_view.php?id=<?php echo (int)$row['application_id']; ?>'">👁️</button>
                            <button type="button" class="approve" title="Approve" onclick="handleAction(<?php echo (int)$row['application_id']; ?>,'approve')">✔</button>
                            <button type="button" class="reject" title="Reject" onclick="handleAction(<?php echo (int)$row['application_id']; ?>,'reject')">✖</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
// handle approve/reject actions (AJAX)
function handleAction(id, action) {
    if (!confirm('Are you sure?')) return;
    fetch('hr_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({application_id: id, action: action})
    }).then(r => r.json())
      .then(res => {
          if (res.success) {
              alert('Action completed.');
              location.reload();
          } else {
              alert('Error: ' + (res.message || 'Unknown'));
          }
      }).catch(err => alert('Request failed'));
}
</script>

</body>
</html>
