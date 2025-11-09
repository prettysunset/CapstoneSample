<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Application Submitted | OJTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="styles.css">
  <style>
    body { 
      opacity: 0; 
      transition: opacity 0.45s ease; 
      font-family: 'Poppins', sans-serif;
      background-color: #e6f2ff;
      margin:0;
      padding:0;
    }

    /* ✅ MATCHED NAVBAR DESIGN (same as form3) */
    .navbar {
      width: 100%;
      display: flex;
      justify-content: center;
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      border-radius: 20px;
      padding: 10px 25px;
      margin: 20px auto;
      transition: all 0.3s ease;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
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

    /* confirmation card */
    .confirm-card {
      max-width:900px;
      margin: 150px auto 48px; /* gap from fixed navbar */
      padding:28px;
      background:white;
      border-radius:16px;
      box-shadow:0 10px 30px rgba(10,15,40,0.06);
      display:flex;
      gap:24px;
      align-items:center;
      flex-direction:column;
      text-align:center;
    }

    .confirm-right img { width:90px; margin-bottom:12px; }
    .confirm-right h2 { color:#3a4163; margin:4px 0 8px; }
    .btn-home {
      background:#3a4163;
      color:#fff;
      padding:10px 26px;
      border-radius:20px;
      border:0;
      cursor:pointer;
      margin-top:20px;
      transition:background 0.3s ease;
    }
    .btn-home:hover {
      background:#2a2f4f;
    }
  </style>
</head>
<body>
  <!-- ✅ SAME NAVBAR STRUCTURE -->
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

  <div class="confirm-card" role="status">
    <div class="confirm-right">
      <img src="https://cdn-icons-png.flaticon.com/512/845/845646.png" alt="Check">
      <h2>Application Submitted</h2>
      <p>Thank you for submitting your OJT application.<br>
      Your request has been successfully received.<br>
      Please wait for an email notification from the HR Head regarding approval status.</p>
      <button class="btn-home" onclick="window.location.href='home.php'">Back to Home Page</button>
    </div>
  </div>


  <script>
    window.addEventListener('load', () => { document.body.style.opacity = 1; });
  </script>
</body>
</html>
