<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>OJT-MS REQUIREMENTS</title>
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

/* VMV Section */
.vmv-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    max-width: 1000px;
    margin: 0 auto;
    justify-content: center;
}

.vmv-box {
    background-color: rgba(255, 255, 255, 0.8);
    padding: 20px;
    border-radius: 10px;
    color: #3a4163;
    font-size: 1.1rem;
    box-sizing: border-box;
}

.vmv-box.two-up {
    flex: 1 1 calc(50% - 20px);
    min-width: 260px;
}

.vmv-box.full-centre {
    flex: 1 1 50%;
    min-width: 260px;
    max-width: 600px;
}

.vmv-box h2 {
    text-align: center;
    color: #3a4163;
    margin-top: 0;
}

.vmv-box p, .vmv-box ul {
    text-align: center;
    margin: 0;
}

.vmv-box ul {
    list-style: none;
    padding: 0;
    margin-top: 10px;
}

.vmv-box ul li {
    margin: 8px 0;
}

@media (max-width: 700px) {
    .vmv-box.two-up, .vmv-box.full-centre {
        flex-basis: 100%;
    }
    .vmv-box.full-centre {
        max-width: none;
    }
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
                <li><a href="about.php" style="color:#4a6ff3; font-weight:700;">About</a></li>
                <li><a href="contacts.php">Contacts</a></li>
                <li><a href="offices.php">Offices</a></li>
                <li class="login"><a href="login.php">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- ✅ Page Content -->
    <div style="text-align:center; margin-top:24px;">
        <h1 style="margin:0; color:#3a4163; font-size:2rem; line-height:1.2; font-weight:800;">
            Vision, Mission, and Core Values
        </h1>
        <h1 style="margin:8px 0 0; color:#3a4163; font-size:2rem; font-weight:800;">of</h1>
        <h1 style="margin:8px 0 0; color:#4a6ff3; font-size:2rem; line-height:1.1; font-weight:800;">
            Malolos City - City Hall
        </h1>
    </div>

    <div class="header-container" style="background-image: url('Untitled design (7).png'); background-size: cover; background-position: center; padding: 60px 0;">
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
                    <li>HONESTY</li>
                    <li>INTEGRITY</li>
                    <li>EXCELLENCE</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
