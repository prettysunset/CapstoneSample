<?php
session_start();
$conn = new mysqli("localhost", "root", "", "capstone");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role']; // hr_head, hr_staff, office_head, student

        // Redirect based on role
        switch ($row['role']) {
            case 'hr_head':
                header("Location: hr_head/hr_head_home.php");
                break;
            case 'hr_staff':
                header("Location: hr_staff/dashboard.php");
                break;
            case 'office_head':
                header("Location: office_head/dashboard.php");
                break;
            case 'student':
                header("Location: ojts/dashboard.php");
                break;
            default:
                header("Location: login.php");
                break;
        }
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | OJTMS</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
}
.login-box {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 5px 10px rgba(0,0,0,0.1);
    width: 350px;
}
h2 {
    text-align: center;
    margin-bottom: 20px;
}
input {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
}
button {
    width: 100%;
    padding: 10px;
    border: none;
    border-radius: 8px;
    background: #007bff;
    color: white;
    cursor: pointer;
}
button:hover {
    background: #0056b3;
}
.error {
    color: red;
    text-align: center;
}
</style>
</head>
<body>
<div class="login-box">
    <h2>OJTMS Login</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" id="password" placeholder="Password" required>
        <button type="button" id="togglePassword" style="margin-bottom:10px;">Show</button>
        <button type="submit">Login</button>
    </form>
</div>
<script>
document.getElementById('togglePassword').onclick = function() {
  var pwd = document.getElementById('password');
  if (pwd.type === "password") {
    pwd.type = "text";
    this.textContent = "Hide";
  } else {
    pwd.type = "password";
    this.textContent = "Show";
  }
};
</script>
</body>
</html>
