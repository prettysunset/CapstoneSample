<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>CONTACT US - OJT-MS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">

<style>
/* General Page */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #e6f2ff;
    color: #333;
}

/* Centered Modern Navbar */
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
    font-family: 'Poppins', sans-serif;
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

/* hover underline animation */
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
    background-color: #4a6ff3;
    color: white;
    border-radius: 25px;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(74, 111, 243, 0.3);
    transition: all 0.3s ease;
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

/* Contact Section */
.contacts-section {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    margin-top: 40px;
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
}

.contact-info {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
    align-items: flex-start;
}

.info-list {
    list-style: none;
    padding: 0;
    text-align: center;
}

.info-list h2 {
    color: #3a4163;
    margin-bottom: 8px;
}

/* Footer */
.footer {
    color: white;
    text-align: center;
    padding: 200px 0;
    margin-top: 0;
}
</style>
</head>

<body>
    <!-- ✅ Centered Navbar -->
    <nav class="navbar" role="navigation">
        <div class="nav-container">
            <a class="logo" href="about.php">OJT-MS</a>
            <ul class="nav-links">
                <li><a href="home.php">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contacts.php" style="color:#4a6ff3; font-weight:700;">Contacts</a></li>
                <li><a href="offices.php">Offices</a></li>
                <li class="login"><a href="login.php">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- ✅ Contact Info Section -->
    <div class="contacts-section">
        <div class="contact-info">
            <ul class="info-list">
                <li><h2>OFFICE HOURS</h2></li>
                <li>Lunes - Biyernes</li>
                <li>8:00 a.m - 5:00 p.m</li>
                <li>(except on Holidays)</li>
            </ul>
            <ul class="info-list">
                <li><h2>ADDRESS</h2></li>
                <li>New City Hall Building</li>
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
                            <a href="https://www.facebook.com/MalolosCIOPage">
                                <img src="Untitled design (9).png" alt="Facebook" style="width:40px; height:40px; object-fit:contain;">
                            </a>
                            <a href="https://www.youtube.com/channel/UCxqpRrPIYwH1-j-APh_vwAA/featured">
                                <img src="Untitled design (78).png" alt="YouTube" style="width:38px; height:38px; object-fit:contain;">
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <style>
        /* Prevent page scrolling */
        html, body {
            height: 100%;
            overflow: hidden;
        }
        /* Keep footer visible without causing scroll */
        .footer {
            margin-top: 0;
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 5;
            text-align: center;
            pointer-events: auto;
        }
    </style>

    <!-- ✅ Footer -->
    <footer class="footer" style="position:fixed; bottom:15px; left:50%; transform:translateX(-50%); z-index:10; pointer-events:auto; text-align:center; color:#fff; padding:12px 24px; border-radius:12px;">
        <h3 style="margin:0; font-weight:700; text-shadow:0 2px 4px rgba(0,0,0,0.6);">
            “DAKILA ANG BAYAN NA MAY MALASAKIT SA MAMAMAYAN”
        </h3>
        <h4 style="margin:8px 0 0 0; font-weight:600; text-shadow:0 1px 3px rgba(0,0,0,0.6);">
            IGG. ABGDO. CHRISTIAN D. NATIVIDAD<br>
            PUNONG LUNGSOD
        </h4>
    </footer>

    <!-- Background Image -->
    <footer style="position:fixed; left:0; right:0; top:0; width:100%; height:100vh; z-index:-1; pointer-events:none;" aria-hidden="true">
        <img src="OJT-MS-PROTOTYPE.png" alt="" style="display:block; width:100%; height:100%; object-fit:cover;">
    </footer>
</body>
</html>
