<?php
session_start();
date_default_timezone_set('Asia/Manila');

// require DB connection (conn.php used elsewhere in project)
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ensure we have user's name; prefer session but fallback to users table
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($user_name === '') {
    $su = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) $user_name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? ''));
}
if ($user_name === '') $user_name = 'Office Head';

// find the office assigned to this office head via office_heads -> offices
$office = null;
$s = $conn->prepare("
    SELECT o.* 
    FROM office_heads oh
    JOIN offices o ON oh.office_id = o.office_id
    WHERE oh.user_id = ?
    LIMIT 1
");
$s->bind_param("i", $user_id);
$s->execute();
$office = $s->get_result()->fetch_assoc();
$s->close();

// fallback: try to find office by users.office_name if office_heads row missing
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
        $office = $q->get_result()->fetch_assoc();
        $q->close();
    }
}

// safe defaults if no office found
if (!$office) {
    $office = [
        'office_id' => 0,
        'office_name' => 'Unknown Office',
        'current_limit' => 0,
        'requested_limit' => 0,
        'reason' => '',
        'status' => 'open'
    ];
}

// counts (use correct role/status values from your schema)
$office_name_for_query = $office['office_name'] ?? '';

// Active OJTs - users.role = 'ojt', users.status = 'active'
$active_ojts = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND status = 'active' AND office_name = ?");
$stmt->bind_param("s", $office_name_for_query);
$stmt->execute();
$active_ojts = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Completed OJTs - if you track completed in students table use students.status = 'completed' else users.status
$completed_ojts = 0;
$s2 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND status = 'inactive' AND office_name = ?");
$s2->bind_param("s", $office_name_for_query);
$s2->execute();
$completed_ojts = (int)$s2->get_result()->fetch_assoc()['total'];
$s2->close();

// Pending student applications (if table exists)
$pending_students = 0;
// check if table exists to avoid exception on environments without that table
$tblCheck = $conn->query("SHOW TABLES LIKE 'student_applications'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s3 = $conn->prepare("SELECT COUNT(*) AS total FROM student_applications WHERE status = 'Pending' AND office_name = ?");
    $s3->bind_param("s", $office_name_for_query);
    $s3->execute();
    $pending_students = (int)$s3->get_result()->fetch_assoc()['total'];
    $s3->close();
} else {
    $pending_students = 0;
}

// Pending office requests for this office_id
$pending_office = 0;
$office_id = (int)($office['office_id'] ?? 0);
$s4 = $conn->prepare("SELECT COUNT(*) AS total FROM office_requests WHERE status = 'Pending' AND office_id = ?");
$s4->bind_param("i", $office_id);
$s4->execute();
$pending_office = (int)$s4->get_result()->fetch_assoc()['total'];
$s4->close();

// Fetch recent DTR rows for users in this office (most recent 20)
$late_dtr = $conn->prepare("
    SELECT u.first_name, u.last_name, d.am_in, d.am_out, d.pm_in, d.pm_out, d.hours
    FROM dtr d
    JOIN users u ON d.student_id = u.user_id
    WHERE u.office_name = ?
    ORDER BY d.log_date DESC
    LIMIT 20
");
$late_dtr->bind_param("s", $office_name_for_query);
$late_dtr->execute();
$late_dtr_res = $late_dtr->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Office Head | OJT-MS</title>
<style>
    body {
        font-family: 'Poppins', sans-serif;
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
    .main {
        margin-left: 240px;
        padding: 20px;
    }
    .cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    .card {
        background: #dcdff5;
        padding: 15px;
        border-radius: 15px;
        text-align: center;
    }
    .card h2 { margin: 0; }
    .table-section {
        margin-top: 30px;
        background: white;
        border-radius: 15px;
        padding: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 8px;
    }
    th {
        background-color: #f1f1f1;
    }
    .edit-section {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        margin-top: 10px;
    }
    .edit-section input {
        text-align: center;
        border: 1px solid #ccc;
        padding: 5px;
        border-radius: 5px;
    }
</style>
</head>
<body>

<div class="sidebar">
    <h3><?= htmlspecialchars($user_name) ?></h3>
    <p>Office Head</p>
    <a href="#" class="active">🏠 Home</a>
    <a href="#">👥 OJT</a>
    <a href="#">📊 Reports</a>
    <h3 style="position:absolute; bottom:20px; width:100%; text-align:center;">OJT-MS</h3>
</div>

<div class="main">
    <div class="cards">
        <div class="card">
            <p>Active OJTs</p>
            <h2><?= $active_ojts ?></h2>
        </div>
        <div class="card">
            <p>Completed OJTs</p>
            <h2><?= $completed_ojts ?></h2>
        </div>
        <div class="card">
            <p>Pending Student Applications</p>
            <h2><?= $pending_students ?></h2>
        </div>
        <div class="card">
            <p>Pending Office Request</p>
            <h2><?= $pending_office ?></h2>
        </div>
    </div>

    <div class="table-section">
        <h3>Office Information</h3>
        <div class="edit-section">
            <input type="text" value="<?= htmlspecialchars($office['current_limit']) ?>" readonly>
            <input type="text" value="<?= $active_ojts ?>" readonly>
            <input type="text" value="<?= max((int)$office['current_limit'] - $active_ojts, 0) ?>" readonly>
            <input type="text" value="<?= htmlspecialchars($office['requested_limit']) ?>" readonly>
            <input type="text" value="<?= htmlspecialchars($office['reason']) ?>" readonly>
            <input type="text" value="<?= ucfirst($office['status']) ?>" readonly>
        </div>
    </div>

    <div class="table-section">
        <h3>Late DTR Submissions</h3>
        <table>
            <tr>
                <th>NAME</th>
                <th colspan="2">A.M.</th>
                <th colspan="2">P.M.</th>
                <th>HOURS</th>
            </tr>
            <tr>
                <th></th>
                <th>ARRIVAL</th>
                <th>DEPARTURE</th>
                <th>ARRIVAL</th>
                <th>DEPARTURE</th>
                <th></th>
            </tr>

            <?php while ($row = $late_dtr_res->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= $row['am_in'] ? htmlspecialchars(date('H:i', strtotime($row['am_in']))) : '' ?></td>
                <td><?= $row['am_out'] ? htmlspecialchars(date('H:i', strtotime($row['am_out']))) : '' ?></td>
                <td><?= $row['pm_in'] ? htmlspecialchars(date('H:i', strtotime($row['pm_in']))) : '' ?></td>
                <td><?= $row['pm_out'] ? htmlspecialchars(date('H:i', strtotime($row['pm_out']))) : '' ?></td>
                <td><?= htmlspecialchars($row['hours']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>
