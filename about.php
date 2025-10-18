<html>
 <head>
     <meta charset="UTF-8" />
     <link rel="stylesheet" href="stylenibilog.css" />
     <title>OJT-MS REQUIREMENTS</title>
     <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylenibilog.css">
<style>

      body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      padding: 0;
      background-color: #e6f2ff;
      color: #333;
    }
    .navbar {
    display: flex;
    justify-content: space-between; /* logo left, links right */
    align-items: center;           /* vertical alignment */
    margin: 22px 33px;             /* top-bottom: 22px, left-right: 33px */
}

.logo {
    font-weight: bold;
    text-decoration: none;
    color: #3a4163;
}

.logo:hover {
    background: none;
    color: #3a4163; /* no hover effect */
    padding: 0;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 15px;
    margin: 0;  /* reset default */
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

/* Hover for normal links (exclude logo & login) */
.nav-links a:hover {
    background-color: #3a4163;
    color: white;
    border-radius: 15px;
    padding: 5px 15px;
}

/* Login button */
.navbar li.login a {
    color: white;
    text-decoration: none;
    display: inline-block;
    padding: 5px 15px;
    background-color: #3a4163;
    border-radius: 15px;
        font-weight: bold;
    transition: background-color 0.3s;
}

.navbar li.login a:hover {
    background-color: #2a2f4f;
}
</style>
    </head>

    <body>
        <div class="navbar">
        <h1><a class="logo" href="about.php">OJT-MS</a></h1>

        <div class="nav-links">
        <li><a href="home.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="contacts.php">Contacts</a></li>
        <li><a href="offices.php">Offices</a></li>
        <li class="login"><a href="login.php">Login</a></li>
        </div>
    </div>
    <div style="text-align:center; margin-top:24px;">
        <h1 style="margin:0; color:#3a4163; font-size:2rem; line-height:1.2; font-weight:800;">
            Vision, Mission, and Core Values
        </h1>
        
        <h1 style="margin:8px 0 0; color:#3a4163; font-size:2rem; font-weight:800">of</h1>
        <h1 style="margin:8px 0 0; color:#4a6ff3; font-size:2rem; line-height:1.1; font-weight:800;">
            Malolos City - City Hall
        </h1>
    </div>
    

        <div class="header-container" style="background-image: url('Untitled design (7).png'); background-size: cover; background-position: center; padding: 60px 0;">
        <style>
        .vmv-grid{
            display:flex;
            flex-wrap:wrap;
            gap:20px;
            max-width:1000px;
            margin:20px auto;
            justify-content:center;
        }
        .vmv-box{
            background-color: rgba(255,255,255,0.8);
            padding:20px;
            border-radius:10px;
            color:#3a4163;
            font-size:1.1rem;
            box-sizing:border-box;
        }
        .vmv-box.two-up{
            flex: 1 1 calc(50% - 20px);
            min-width:260px;
        }
        .vmv-box.full-centre{
            flex: 1 1 50%;
            min-width:260px;
            max-width:600px;
        }
        .vmv-box h2{ text-align:center; color:#3a4163; margin-top:0;}
        .vmv-box p, .vmv-box ul{ text-align:center; margin:0;}
        .vmv-box ul{ list-style:none; padding:0; margin-top:10px;}
        .vmv-box ul li{ margin:8px 0; }
        @media (max-width:700px){
         .vmv-box.two-up, .vmv-box.full-centre{ flex-basis:100%; }
         .vmv-box.full-centre{ max-width:none; }
        }
        </style>

        <div class="vision-mission-values vmv-grid">
            <div class="vmv-box two-up">
                <h2>Vision</h2>
                <p>
                    PHILIPPINES’ PREMIER HISTORICAL CITY OF SKILLED, 
                    INTELLECTUAL, DISCIPLINED, GOD-LOVING AND 
                    EMPOWERED CITIZEN WITH BETTER QUALITY OF LIFE 
                    EMBRACING GLOBAL CHALLENGES UNDER A DYNAMIC LEADERSHIP.                
                </p>
            </div>
            <div class="vmv-box two-up">
                <h2>Mission</h2>
                <p style="text-align:center; padding-top:20px;">
                    TO UPLIFT THE LIVING CONDITION<br>
                    OF THE PEOPLE IN THE CITY OF MALOLOS                
                </p>
            </div>

            <div class="vmv-box full-centre">
                <h2>Core Values</h2>
                <ul>
                    <li>ACCOUNTABILITY</li>
                    <li>HONESTY </li>
                    <li>INTEGRITY</li>
                    <li>EXCELLENCE</ul>
            </div>
        </div>  
    </body>
</html>