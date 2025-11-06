<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>OJT-MS Offices</title>
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

/* Page Header */
h1 {
    text-align: center;
    font-weight: 700;
    margin-top: 40px;
}

/* Table Section */
.table-section {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    margin: 30px auto;
    max-width: 1100px;
}

/* Table Style */
table {
    width: 45%;
    border-collapse: collapse;
    background-color: rgba(255, 255, 255, 0.85);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Table Headers */
th {
    background-color: #f0f6ff;
    color: #3a4163;
    text-align: left;
    padding: 15px;
    font-weight: 700;
    font-size: 16px;
    border-bottom: 2px solid #d6e4f0;
}

.floor-header th {
    background-color: #d8e8ff !important;
    color: #3a4163 !important;
    text-align: center;
    font-size: 20px !important;
    font-weight: 800;
    border-bottom: 3px solid #b8d0f0;
    height: 60px;
    vertical-align: middle;
    letter-spacing: 0.5px;
}

/* Table Cells */
td {
    padding: 14px 15px;
    border-bottom: 1px solid #d6e4f0;
    font-size: 15px;
}

/* Hover Effect */
tr:hover td {
    background-color: rgba(74,111,243,0.05);
}

/* Status Colors */
.status {
    font-weight: 600;
}
.status.open {
    color: #009900;
}
.status.full {
    color: #ff0000;
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
                <li><a href="contacts.php">Contacts</a></li>
                <li><a href="offices.php" style="color:#4a6ff3; font-weight:700;">Offices</a></li>
                <li class="login"><a href="login.php">Login</a></li>
            </ul>
        </div>
    </nav>

    <h1>OJT Slot <span style="color: #4a6ff3;">Availability</span></h1>

    <!-- ====== Table Sections (Ground + Floors) ====== -->
    <div class="table-section">
        <!-- Ground Floor -->
        <table>
            <tr class="floor-header">
                <th colspan="3">Ground Floor</th>
            </tr>
            <tr>
                <th>Office</th>
                <th>Available Slots</th>
                <th>Status</th>
            </tr>
            <tr><td>Front Desk</td><td>5</td><td class="status open">Open</td></tr>
            <tr><td>BPLO</td><td>2</td><td class="status open">Open</td></tr>
            <tr><td>LCR</td><td>0</td><td class="status full">Full</td></tr>
            <tr><td>TREASURY</td><td>3</td><td class="status open">Open</td></tr>
            <tr><td>CSWDO</td><td>3</td><td class="status open">Open</td></tr>
            <tr><td>TRAFFIC</td><td>3</td><td class="status open">Open</td></tr>
            <tr><td>CDRRMO</td><td>3</td><td class="status open">Open</td></tr>
            <tr><td>ASSESSOR</td><td>4</td><td class="status open">Open</td></tr>
            <tr><td>ACCOUNTING</td><td>5</td><td class="status open">Open</td></tr>
            <tr><td>CAD</td><td>3</td><td class="status open">Open</td></tr>
        </table>

        <!-- 2nd Floor -->
        <table>
            <tr class="floor-header">
                <th colspan="3">2nd Floor</th>
            </tr>
            <tr>
                <th>Office</th>
                <th>Available Slots</th>
                <th>Status</th>
            </tr>
            <tr><td>Mayor's Office</td><td>5</td><td class="status open">Open</td></tr>
            <tr><td>CA</td><td>2</td><td class="status open">Open</td></tr>
            <tr><td>BUDGET</td><td>0</td><td class="status full">Full</td></tr>
            <tr><td>LEGAL</td><td>3</td><td class="status full">Full</td></tr>
            <tr><td>CIO</td><td>3</
