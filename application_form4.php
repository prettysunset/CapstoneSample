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

    /* navbar */
    .navbar {
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:12px 33px;
      background:#fff;
      box-shadow: 0 2px 10px rgba(10,15,40,0.06);
      position:fixed;
      top:0;
      left:0;
      right:0;
      z-index:1000;
    }
    .logo { font-weight:bold; text-decoration:none; color:#3a4163; }
    .nav-links { display:flex; list-style:none; gap:15px; margin:0; padding:0; align-items:center; }
    .nav-links li { cursor:pointer; padding:5px 15px; }
    .nav-links a { text-decoration:none; color:#3a4163; }
    .nav-links a:hover { background-color:#3a4163; color:white; border-radius:15px; padding:5px 15px; }
    .nav-links a.login { background:#3a4163; color:white; border-radius:15px; font-weight:bold; transition:0.3s; }
    .nav-links a.login:hover { background:#2a2f4f; }

    /* confirmation card with gap from navbar */
    .confirm-card {
      max-width:900px;
      margin: 150px auto 48px; /* gap from navbar like other forms */
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
    .btn-home { background:#3a4163; color:#fff; padding:10px 26px; border-radius:20px; border:0; cursor:pointer; margin-top:20px; }
    .btn-home:hover { background:#2a2f4f; }

    @media (max-width:900px){ .confirm-card{ margin:100px 12px; } }

  </style>
</head>
<body>

  <div class="navbar">
    <a class="logo" href="about.php">OJT-MS</a>
    <ul class="nav-links">
      <li><a href="home.php">Home</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="contacts.php">Contacts</a></li>
      <li><a href="offices.php">Offices</a></li>
      <li><a class="login" href="login.php">Login</a></li>
    </ul>
  </div>

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
  window.addEventListener('load', () => {
    document.body.style.opacity = 1;
  });
</script>

</body>
</html>
