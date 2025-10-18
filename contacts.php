<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>CONTACT US - OJT-MS</title>
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
.contact-info{
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    
}
.info-list{
    list-style-type: none;
    padding: 0;
    margin: 0 20px;
    font-size: 25px;
}
.footer{
    color: white;
    text-align: center;
    padding: 200px 0;
    margin-top: 0;
}
</style>
</head>
<body>
 <div class="navbar">
        <h1><a class="logo" href="about.php">OJT-MS</a></h1>

        <div class="nav-links">
        <li><a href="home.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li class="home">Contacts</li>
        <li><a href="offices.php">Offices</a></li>
        <li class="login"><a href="login.php">Login</a></li>
        </div>
    </div>

    <div class="contacts-section" style="display:flex; justify-content:center; align-items:flex-start; margin-top:40px;">
        <div class="contact-info" style="display:flex; gap:10px; align-items:flex-start; justify-content:center;">
            <ul class="info-list">
                <li><h2>OFFICE HOURS</h2></li>
                <li>Lunes - Biyernes</li>
                <li>8:00 a.m - 5:00 p.m</li>
                <li>(except on Holidays)</li>
            </ul>   
            <ul class="info-list">
                <li><h2>ADDRESS</h2></li>
                <li>New City HAll Building</li>
                <li>Government Center</li>
                <li>Brgy. Bulihan</li>
                <li>City of Malolos, Bulacan</li>
                <li>Philippines, 3000</li>
            </ul> 
            <div style="display:flex; flex-direction:column; gap:10px; align-items:center;">
                <ul class="info-list">
                    <li><h2>TRUNKLINE</h2></li>
                    <li>(044) 931-8888</li>
                </ul>
                <ul class="info-list">
                    <li><h2 style="margin:0 0 6px 0;">Follow Us</h2></li>
                    <li>
                        <div style="display:flex; gap:4px; align-items:center; justify-content:center;">
                            <a href="https://www.facebook.com/MalolosCIOPage"><img src="Untitled design (9).png" alt="fblink" style="width:40px; height:40px; object-fit:contain; display:block;"></a>
                             <a href="https://www.youtube.com/channel/UCxqpRrPIYwH1-j-APh_vwAA/featured"><img src="Untitled design (78).png" alt="social-2" style="width:36px; height:36px; object-fit:contain; display:block;"></a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>


    <div class="footer" style="position:fixed; bottom:20px; left:0; right:0; z-index:1; text-align:center; color:white; margin:0; padding:0; pointer-events:auto;">
        <h3 style="margin:0; font-weight:700; text-shadow:0 2px 4px rgba(0,0,0,0.6);">
            “DAKILA ANG BAYAN NA MAY MALASAKIT SA MAMAMAYAN”
        </h3>
        <h4 style="margin:8px 0 0 0; font-weight:600; text-shadow:0 1px 3px rgba(0,0,0,0.6);">
            IGG. ABGDO. CHRISTIAN D. NATIVIDAD<br>
            PUNONG LUNGSOD
        </h4>
    </div>

    
        <footer style="position:fixed; left:0; right:0; top:0; width:100%; height:100vh; z-index:-1; pointer-events:none;" aria-hidden="true">
            <img src="OJT-MS-PROTOTYPE.png" alt="" style="display:block; width:100%; height:100%; object-fit:cover;">
        </footer>
</body>
</html>