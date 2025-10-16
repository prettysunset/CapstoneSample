<?php
session_start();
include('../conn.php');

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// get user info (name, role, office_name)
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full_name = trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']);
$office_name = $user['office_name'] ?? '';

// find office_id
$office_id = null;
if ($office_name) {
    $stmt = $conn->prepare("SELECT office_id, office_name FROM offices WHERE office_name = ?");
    $stmt->bind_param("s", $office_name);
    $stmt->execute();
    $office_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($office_row) $office_id = (int)$office_row['office_id'];
}

// get counts and list of OJTs for this office (preferences)
$total_ojts = 0;
$ojt_list = [];
if ($office_id) {
    // count distinct students who listed this office as pref1 or pref2
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) AS total FROM ojt_applications WHERE office_preference1 = ? OR office_preference2 = ?");
    $stmt->bind_param("ii", $office_id, $office_id);
    $stmt->execute();
    $total_ojts = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // fetch details: student name + application status + date_submitted
    $stmt = $conn->prepare("
        SELECT s.student_id, s.first_name, s.last_name, oa.status, oa.date_submitted
        FROM ojt_applications oa
        JOIN students s ON oa.student_id = s.student_id
        WHERE oa.office_preference1 = ? OR oa.office_preference2 = ?
        ORDER BY oa.date_submitted DESC
    ");
    $stmt->bind_param("ii", $office_id, $office_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ojt_list[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Office Head - <?= htmlspecialchars($office_name ?: 'Office') ?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;padding:20px}
.header{display:flex;gap:20px;align-items:center}
.card{background:#f3f4f6;padding:16px;border-radius:8px;margin-top:12px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{border:1px solid #ddd;padding:8px;text-align:left}
</style>
</head>
<body>
<div class="header">
  <div>
    <h2><?= htmlspecialchars($full_name) ?></h2>
    <div><?= htmlspecialchars(ucwords(str_replace('_',' ',$user['role']))) ?> · <?= htmlspecialchars($office_name ?: 'No office') ?></div>
  </div>
</div>

<div class="card">
  <strong>Office:</strong> <?= htmlspecialchars($office_name ?: 'N/A') ?><br>
  <strong>Total OJTs (listed your office):</strong> <?= $total_ojts ?>
</div>

<?php if ($office_id): ?>
  <div class="card">
    <h3>OJTs (recent first)</h3>
    <?php if (count($ojt_list) === 0): ?>
      <p>No applications for this office yet.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Status</th><th>Date Submitted</th></tr></thead>
        <tbody>
        <?php foreach ($ojt_list as $i => $r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td><?= htmlspecialchars(ucfirst($r['status'] ?? 'pending')) ?></td>
            <td><?= htmlspecialchars($r['date_submitted'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <p>No office found for this account. Make sure your users.office_name matches an entry in the offices table.</p>
  </div>
<?php endif; ?>
</body>
</html>