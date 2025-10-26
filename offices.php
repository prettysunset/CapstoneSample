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

/* Page Header */
h1 {
  text-align: center;
  font-weight: 700;
  margin-top: 40px;
}

/* Table Container for Two Tables per Section */
.table-section {
  display: flex;
  justify-content: center;
  gap: 30px;
  flex-wrap: wrap;
  margin: 30px auto;
}

/* Table Style */
table {
  width: 45%;
  border-collapse: collapse;
  background-color: #fff;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Table Header Row (consistent for all) */
th {
  background-color: #f0f6ff;
  color: #3a4163;
  text-align: left;
  padding: 15px;
  font-weight: 700;
  font-size: 16px;
  border-bottom: 2px solid #d6e4f0;
}

/* Floor Header — same height, centered text, bold and clean */
.floor-header th {
  background-color: #d8e8ff !important;
  color: #3a4163 !important;
  text-align: center;
  font-size: 20px !important;
  font-weight: 800;
  border-bottom: 3px solid #b8d0f0;
  height: 60px; /* makes all floor headers same height */
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
  background-color: #f8fbff;
}

/* Status Colors */
.status {
  font-weight: 600;
}
.status.open {
  color: #009900; /* Green */
}
.status.full {
  color: #ff0000; /* Red */
}
</style>

</head>    

<body>
  <div class="navbar">
    <h1><a class="logo" href="about.php">OJT-MS</a></h1>
    <div class="nav-links">
      <li><a href="home.php">Home<a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="contacts.php">Contacts</a></li>
      <li class="home">Offices</li>
      <li class="login"><a href="login.php">Login</a></li>
    </div>
  </div>

<h1>OJT Slot <span style="color: #4a6ff3;">Availability</span></h1>

<!-- ====== SECTION 1 (Ground + 2nd Floor) ====== -->
<div class="table-section">
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
    <tr><td>CIO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>HEALTH</td><td>3</td><td class="status open">Open</td></tr>
  </table>
</div>

<!-- ====== SECTION 2 (3rd + 4th Floor) ====== -->
<div class="table-section">
  <table>
    <tr class="floor-header">
      <th colspan="3">3rd Floor</th>
    </tr>
    <tr>
      <th>Office</th>
      <th>Available Slots</th>
      <th>Status</th>
    </tr>
    <tr><td>CEO</td><td>5</td><td class="status open">Open</td></tr>
    <tr><td>GSO</td><td>2</td><td class="status open">Open</td></tr>
    <tr><td>CHRMO</td><td>0</td><td class="status full">Full</td></tr>
    <tr><td>CTECO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>CPDO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>CEEDO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>CENRO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>AGRI</td><td>4</td><td class="status open">Open</td></tr>
    <tr><td>VET</td><td>5</td><td class="status open">Open</td></tr>
  </table>

  <table>
    <tr class="floor-header">
      <th colspan="3">4th Floor</th>
    </tr>
    <tr>
      <th>Office</th>
      <th>Available Slots</th>
      <th>Status</th>
    </tr>
    <tr><td>VM. GILBERT GATCHALIAN</td><td>5</td><td class="status open">Open</td></tr>
    <tr><td>COUN. JV VITUG</td><td>2</td><td class="status open">Open</td></tr>
    <tr><td>COUN. DENNIS SAN DIEGO</td><td>0</td><td class="status full">Full</td></tr>
    <tr><td>COUN. GELI BULAONG</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>COUN. EDGARDO DOMINGO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>COUN. TROI ALDABA</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>COUN. PONCHO ARCEGA</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>COUN. MIEL AGUSTIN</td><td>4</td><td class="status open">Open</td></tr>
    <tr><td>COUN. MIKKI SOTTO</td><td>5</td><td class="status open">Open</td></tr>
    <tr><td>COUN. NOEL PINEDA</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>COUN. TOOTS BAUTISTA</td><td>1</td><td class="status open">Open</td></tr>
    <tr><td>ABC CESAR BARTOLOME</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>SK RIAN MACLYN DELA CRUZ</td><td>1</td><td class="status open">Open</td></tr>
    <tr><td>OSSP</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>DILG</td><td>2</td><td class="status open">Open</td></tr>
  </table>
</div>

<!-- ====== SECTION 3 (5th Floor Alone) ====== -->
<div class="table-section">
  <table>
    <tr class="floor-header">
      <th colspan="3">5th Floor</th>
    </tr>
    <tr>
      <th>Office</th>
      <th>Available Slots</th>
      <th>Status</th>
    </tr>
    <tr><td>CSWDO EXT.</td><td>5</td><td class="status open">Open</td></tr>
    <tr><td>SPORTS</td><td>2</td><td class="status open">Open</td></tr>
    <tr><td>TOURISM</td><td>0</td><td class="status full">Full</td></tr>
    <tr><td>ARCHITECT</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>PAO</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>IT</td><td>3</td><td class="status open">Open</td></tr>
    <tr><td>COA</td><td>3</td><td class="status open">Open</td></tr>
  </table>
</div>

</body>
</html>
