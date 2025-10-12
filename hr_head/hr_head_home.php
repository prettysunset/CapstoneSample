<?php
session_start();
include('../conn.php');

// Only check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info from users table
$query = "SELECT first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Compose full name
$full_name = $user['first_name'];
if (!empty($user['middle_name'])) $full_name .= ' ' . $user['middle_name'];
$full_name .= ' ' . $user['last_name'];

// Compose role label
$role_label = '';
switch ($user['role']) {
    case 'hr_head': $role_label = 'HR Head'; break;
    case 'hr_staff': $role_label = 'HR Staff'; break;
    case 'office_head': $role_label = 'Office Head'; break;
    case 'ojt': $role_label = 'OJT'; break;
    default: $role_label = ucfirst($user['role']);
}

// Compose office label (only for office_head and ojt)
$office_label = '';
if ($user['role'] == 'office_head' || $user['role'] == 'ojt') {
    $office_label = $user['office_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HR Head Dashboard | OJT-MS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      display: flex;
      background: #f5f6fa;
    }

    /* Sidebar */
    .sidebar {
      width: 230px;
      height: 100vh;
      background-color: #1f2a44;
      color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 20px;
    }

    .profile {
      text-align: center;
      margin-bottom: 30px;
    }

    .profile img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background-color: #ccc;
      margin-bottom: 10px;
    }

    .profile h3 {
      font-size: 16px;
      margin-bottom: 4px;
    }

    .profile p {
      font-size: 13px;
      opacity: 0.8;
    }

    .menu {
      width: 100%;
    }

    .menu a {
      display: block;
      color: #fff;
      padding: 12px 25px;
      text-decoration: none;
      transition: 0.3s;
    }

    .menu a:hover {
      background: #273556;
    }

    .menu i {
      margin-right: 10px;
    }

    .logo {
      margin-top: auto;
      padding: 20px;
      text-align: center;
      font-weight: 600;
      letter-spacing: 1px;
    }

    /* Main content */
    .main {
      flex: 1;
      padding: 20px 40px;
    }

    .time-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .time {
      font-size: 18px;
      color: #333;
    }

    .date {
      color: #666;
      font-size: 15px;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="profile">
      <img src="profile_placeholder.png" alt="Profile">
      <h3><?php echo htmlspecialchars($full_name); ?></h3>
      <p><?php echo htmlspecialchars($role_label); ?></p>
      <?php if ($office_label): ?>
        <p><?php echo htmlspecialchars($office_label); ?></p>
      <?php endif; ?>
    </div>

    <div class="menu">
      <a href="#"><i>🏠</i> Home</a>
      <a href="#"><i>👥</i> OJTs</a>
      <a href="#"><i>🕒</i> DTR</a>
      <a href="#"><i>👤</i> Accounts</a>
      <a href="#"><i>📊</i> Reports</a>
    </div>

    <div class="logo">
      OJT-MS
    </div>
  </div>

  <!-- Main content -->
  <div class="main">
    <div class="time-section">
      <div class="time"><?php echo date("g:i A"); ?></div>
      <div class="date"><?php echo date("l, F j, Y"); ?></div>
    </div>
    <h2>Welcome, <?php echo htmlspecialchars($full_name); ?>!</h2>
    <p>This is your <?php echo htmlspecialchars($role_label); ?> dashboard.</p>
  </div>
</body>
</html>
