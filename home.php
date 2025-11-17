<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>OJT-MS HOME</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">



  </head>

<body style="background-color:#e6f2ff;">
<nav class="navbar" role="navigation">
  <div class="nav-container">
    <a class="logo" href="about.php">OJT-MS</a>

   <ul class="nav-links">
  <li><a href="home.php" style="color:#4a6ff3; font-weight:700;">Home</a></li>
  <li><a href="about.php">About</a></li>
  <li><a href="contacts.php">Contacts</a></li>
  <li><a href="offices.php">Offices</a></li>
  <li class="login"><a href="login.php">Login</a></li>
</ul>

  </div>
</nav>

<style>
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

/* Login button */
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
</style>


    <div class="header-container" 
         style="background: linear-gradient(rgba(255,255,255,0.3), rgba(255,255,255,0.6)), url('Untitled design (7).png'); 
                background-size: cover; 
                background-position: center; 
                padding: 35px 0 45px 0; /* reduced padding to make header shorter */">

        <p style="font-size: 50px; margin: 10px 0 8px 0; text-shadow: 2px 2px white;" class="header">
            Welcome to the<br> Malolos City - OJT <br>Management <br>System
        </p>

        <p class="subheader" style="margin-top: 4px; text-shadow: 1px 1px white;">
            Your Pathway to Growth and <br>Service starts here
        </p>

        <div class="buttons" 
             style="display:flex; flex-direction:column; align-items:center; gap:6px; margin-top:4px; width:100%;">

            <!-- Apply Now Button -->
            <div style="width:100%; display:flex; justify-content:center;">
                <button class="mainbtn">
                    <a style="color:white; text-decoration:none; display:inline-block; padding:10px 10px;" href="application_form1.php">
                        Apply Now
                    </a>
                </button>
            </div>

            <!-- Requirements + Date -->
            <div style="display:flex; justify-content:center; width:100%; margin-top:-2px;">
              <button class="secondbtn">
                <a style="color:#3a4163; text-decoration:none;" href="requirements.html">
                  Requirements
                </a>
              </button>
            </div>
        </div>
    </div>
</body>
</html>
