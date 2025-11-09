<?php
session_start();

// replace the direct mysqli(...) call with environment-aware selection
if (
    (PHP_SAPI === 'cli')
    || (isset($_SERVER['HTTP_HOST']) && (stripos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1'))
    || (isset($_SERVER['SERVER_NAME']) && (stripos($_SERVER['SERVER_NAME'], 'localhost') !== false || $_SERVER['SERVER_NAME'] === '127.0.0.1'))
) {
    // Local XAMPP
    $dbHost = '127.0.0.1';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'capstone';
} else {
    // Production / hosting
    $dbHost = 'localhost';
    $dbUser = 'u389936701_user';
    $dbPass = 'CapstoneDefended1';
    $dbName = 'u389936701_capstone';
}
// suppress initial mysqli warning; fail cleanly if connection fails
$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
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
                header("Location: hr_staff/hr_staff_home.php");
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
    padding: 0;  /* removed padding-top */
    background-color: #e6f2ff;
    color: #333;
}

/* Navbar stays the same */
.navbar {
    width: 100%;
    display: flex;
    justify-content: center;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-radius: 20px;
    padding: 10px 25px;
    margin: 20px auto;  /* this sets the same spacing as other pages */
    transition: all 0.3s ease;
}


.nav-container {
    width: 100%;
    max-width: 1100px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-weight: 900;
    font-size: 1.6rem;
    letter-spacing: 1px;
    text-decoration: none;
    color: #344265;
    transition: color 0.3s ease;
}

.logo:hover {
    color: #4a6ff3;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 25px;
    margin: 0;
    padding: 0;
    align-items: center;
}

.nav-links li {
    position: relative;
}

.nav-links a {
    text-decoration: none;
    color: #3a4163;
    font-weight: 500;
    font-size: 0.95rem;
    padding: 8px 15px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

/* Hover underline animation */
.nav-links a::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%) scaleX(0);
    transform-origin: center;
    width: 60%;
    height: 2px;
    background-color: #4a6ff3;
    transition: transform 0.3s ease;
}

.nav-links a:hover::after {
    transform: translateX(-50%) scaleX(1);
}

.nav-links a:hover {
    color: #4a6ff3;
    background-color: rgba(74, 111, 243, 0.1);
}

/* Login button in navbar */
.nav-links .login a {
    background-color: #344265;
    color: white;
    border-radius: 25px;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(74, 111, 243, 0.3);
    transition: all 0.3s ease;
    padding: 8px 20px;
}

.nav-links .login a:hover {
    background-color: #344265;
    box-shadow: 0 2px 8px rgba(52, 66, 101, 0.4);
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
@media (max-width: 700px) {
    .nav-container {
        flex-direction: column;
        gap: 10px;
    }
    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }
    .navbar {
        margin: 10px auto;
        padding: 15px;
        border-radius: 15px;
    }
}

/* Additional responsive tweaks for login layout */
@media (max-width: 720px) {
    .nav-container {
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

<nav class="navbar" role="navigation">
  <div class="nav-container">
    <a class="logo" href="about.php">OJT-MS</a>

    <ul class="nav-links">
      <li><a href="home.php">Home</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="contacts.php">Contacts</a></li>
      <li><a href="offices.php">Offices</a></li>
      <li class="login"><a href="login.php">Login</a></li>
    </ul>
  </div>
</nav>

<div class="header-container" style="background-image: url('123456.png'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: calc(100vh - 90px);">
    <div class="login-box">
        <h2>OJT-MS Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
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

            <!-- ✅ Remember Me checkbox -->
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
