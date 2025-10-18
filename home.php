<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>OJT-MS HOME</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">
</head>

<body>    
    <div class="navbar">
        <h1><a class="logo" href="about.php">OJT-MS</a></h1>

        <div class="nav-links">
            <li class="home">Home</li>
            <li><a href="about.php">About</a></li>
            <li><a href="contacts.php">Contacts</a></li>
            <li><a href="offices.php">Offices</a></li>
            <li class="login"><a href="login.php">Login</a></li>
        </div>
    </div>

    <div class="header-container" 
         style="background: linear-gradient(rgba(255,255,255,0.3), rgba(255,255,255,0.6)), url('Untitled design (7).png'); 
                background-size: cover; 
                background-position: center; 
                padding: 35px 0 45px 0; /* reduced padding to make header shorter */">

        <p style="font-size: 58px; margin: 10px 0 8px 0; text-shadow: 4px 4px white;" class="header">
            Welcome to the<br> Malolos City - OJT <br>Management <br>System
        </p>

        <p class="subheader" style="margin-top: 4px;">
            Your Pathway to Growth and <br>Service starts here
        </p>

        <div class="buttons" 
             style="display:flex; flex-direction:column; align-items:center; gap:6px; margin-top:4px;">

            <!-- Apply Now Button -->
            <div style="width:100%; display:flex; justify-content:center;">
                <button class="mainbtn">
                    <a style="color:white; text-decoration:none; display:inline-block; padding:10px 20px;" href="why.html">
                        Apply Now
                    </a>
                </button>
            </div>

            <!-- Requirements + Date -->
            <div style="display:flex; align-items:center; width:100%; margin-top:-2px;">
                <div style="flex:1;"></div>

                <div style="flex:0;">
                    <button class="secondbtn">
                        <a style="color:#3a4163; text-decoration:none;" href="requirements.html">
                            Requirements
                        </a>
                    </button>
                </div>

                <div style="flex:1; display:flex; justify-content:flex-end;">
                    <p class="date" 
                       style="margin:0 4px 0 0; font-weight:bolder; color:#3a4163; font-size:15px; padding:6px 10px; background-color:#e6f2ff; display:inline-block;">
                       October 12, 2025
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
