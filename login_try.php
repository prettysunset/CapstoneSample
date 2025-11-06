<?php
session_start();

// Hostinger DB credentials
define('DB_HOST','localhost');
define('DB_USER','u389936701_user');
define('DB_PASS','CapstoneDefended1');
define('DB_NAME','u389936701_capstone');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Enter username and password.";
    } else {
        // Prepared statement to avoid injection
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ? LIMIT 1");
        $stmt->bind_param('ss', $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();

            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Redirect based on role
            switch ($row['role']) {
                case 'hr_head':
                    header("Location: hr_head/hr_head_home.php");
                    break;
                case 'hr_staff':
                    header("Location: hr_staff/dashboard.php");
                    break;
                case 'office_head':
                    header("Location: office_head/office_head_home.php");
                    break;
                case 'student':
                case 'ojt':
                    header("Location: ojts/ojt_home.php");
                    break;
                default:
                    header("Location: login.php");
                    break;
            }
            exit();
        } else {
            $error = "Invalid username or password!";
        }
        $stmt->close();
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
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #e6f2ff;
    color: #333;
    padding-top: 90px;
}

/* Navbar */
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background-color: rgba(230,242,255,0.98);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    z-index: 9999;
}
.navbar .container {
    max-width: 1100px;
    margin: 0 auto;
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 20px;
}
.logo {
    font-weight: bold;
    text-decoration: none;
    color: #3a4163;
}
.logo:hover {
    background: none;
    color: #3a4163;
}
.nav-links {
    display: flex;
    list-style: none;
    gap: 15px;
    margin: 0;
    padding: 0;
    align-items: center;
}
.nav-links li {
    cursor: pointer;
    padding: 5px 15px;
}
.nav-links a {
    text-decoration: none;
    color: #3a4163;
}
.nav-links a:hover {
    background-color: #3a4163;
    color: white;
    border-radius: 15px;
    padding: 5px 15px;
}

/* Login section */
.header-container {
    background-size: cover;
    background-position: center;
    padding: 60px 0 40px;
}
.login-box {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
    width: 350px;
    margin: 24px auto;
    border: 1px solid rgba(255,255,255,0.2);
}
h2 {
    text-align: center;
    margin-bottom: 20px;
}
input[type="text"],
input[type="password"] {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 14px;
}

/* Eye icon styling */
.password-container {
    position: relative;
    width: 100%;
}
.password-container input {
    width: 100%;
    padding-right: 40px; /* space for the eye icon */
}
.password-container button {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

/* Checkbox */
.checkbox-container {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}
.checkbox-container input {
    margin-right: 8px;
}
.checkbox-container label {
    font-size: 14px;
    color: #3a4163;
}

/* Login button */
button[type="submit"] {
    width: 100%;
    padding: 10px 15px;
    border: none;
    border-radius: 15px;
    background-color: #3a4163;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
}
button[type="submit"]:hover {
    background-color: #2a2f4f;
}

.error {
    color: red;
    text-align: center;
}

/* Responsive */
@media (max-width: 720px) {
    .navbar .container {
        padding: 12px;
    }
    .nav-links {
        gap: 8px;
    }
    .login-box {
        width: 90%;
    }
}
</style>
</head>
<body>

<div class="navbar">
    <div class="container">
        <h1><a class="logo" href="about.php">OJT-MS</a></h1>
        <div class="nav-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="contacts.php">Contacts</a></li>
            <li><a href="offices.php">Offices</a></li>
        </div>
    </div>
</div>

<div class="header-container" style="background-image: url('123456.png'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: calc(100vh - 90px);">
    <div class="login-box">
        <h2>OJT-MS Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>".htmlspecialchars($error, ENT_QUOTES,'UTF-8')."</p>"; ?>
        <form method="POST" autocomplete="off">
            <input type="text" name="username" placeholder="Username" required>

            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" id="togglePassword" aria-label="Show password">
                    <!-- Eye open -->
                    <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3a4163" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <!-- Eye closed -->
                    <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3a4163" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.65 18.65 0 0 1 4.11-5.05"></path>
                        <path d="M1 1l22 22"></path>
                        <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
                    </svg>
                </button>
            </div>

            <div class="checkbox-container">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember Me</label>
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('togglePassword');
    var pwd = document.getElementById('password');
    var openEye = document.getElementById('eyeOpen');
    var closedEye = document.getElementById('eyeClosed');

    btn.addEventListener('click', function(e){
        e.stopImmediatePropagation();
        if (pwd.type === 'password') {
            pwd.type = 'text';
            openEye.style.display = 'none';
            closedEye.style.display = 'inline';
            btn.setAttribute('aria-label', 'Hide password');
        } else {
            pwd.type = 'password';
            openEye.style.display = 'inline';
            closedEye.style.display = 'none';
            btn.setAttribute('aria-label', 'Show password');
        }
    }, true);
})();
</script>

</body>
</html>
