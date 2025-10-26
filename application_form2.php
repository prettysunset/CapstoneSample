<?php
session_start();

// Save AF2 data to session and redirect to AF3
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['af2'] = [
        'school'         => $_POST['school'],
        'school_address' => $_POST['school_address'],
        'course'         => $_POST['course'],
        'year_level'     => $_POST['year_level'],
        'school_year'    => $_POST['school_year'],
        'semester'       => $_POST['semester'],
        'adviser'        => $_POST['adviser'],
        'adviser_contact'=> $_POST['adviser_contact']
    ];
    header("Location: application_form3.php");
    exit;
}

// Pre-fill form fields if session data exists
$af2 = isset($_SESSION['af2']) ? $_SESSION['af2'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OJT Application Form - School Information</title>
  <link rel="stylesheet" href="style.css">

<style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      padding: 0;
      background-color: #e6f2ff;
      color: #333;
      /* reserve space for fixed navbar so content isn't hidden */
      padding-top: 80px;
      box-sizing: border-box;
    }

    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      display: flex;
      justify-content: space-between; /* logo left, links right */
      align-items: center;           /* vertical alignment */
      height: 64px;
      padding: 12px 33px;
      background: #ffffff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }

    .logo {
      font-weight: bold;
      text-decoration: none;
      color: #3a4163;
      font-size: 20px;
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
      list-style: none;
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

    /* Ensure main layout isn't crowded under the navbar */
    .container {
      max-width: 1100px;
      margin: 20px auto;
      padding: 20px;
    }

    @media (max-width: 600px) {
      .navbar {
        padding: 10px 16px;
        height: auto;
      }
      body {
        padding-top: 72px;
      }
    }
  </style>
</head>
<body>

          <div class="navbar">
        <h1><a class="logo" href="about.php">OJT-MS</a></h1>

        <div class="nav-links">
        <li><a href="home.php">Home</a></li>
        <li class="home">About</li>
        <li><a href="contacts.php">Contacts</a></li>
        <li><a href="offices.php">Offices</a></li>
        <li class="login"><a href="login.php">Login</a></li>
        </div>
    </div>


  <div class="container">
    <!-- LEFT SIDE -->
    <div class="left">
      <h1>OJTMS</h1>
      <p>OJT APPLICATION FORM</p>
      <img src="O.png" alt="Illustration" width="400" height="300">
    </div>

    <!-- RIGHT SIDE -->
    <div class="right">
      <div class="progress">
        <div class="completed">1. Personal Information</div>
        <div class="active">2. School Information</div>
        <div>3. Requirements</div>
      </div>

      <form method="POST">
        <h3>YOUR SCHOOL INFORMATION</h3>

        <!-- replace the <select name="school"> with this searchable input + datalist -->
<input
  type="text"
  name="school"
  id="schoolInput"
  list="schoolsList"
  placeholder="School"
  required
  value="<?= isset($af2['school']) ? htmlspecialchars($af2['school']) : '' ?>"
  style="width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;margin-bottom:12px;"
>
<datalist id="schoolsList"></datalist>

        <input type="text" name="school_address" placeholder="School Address" required value="<?= isset($af2['school_address']) ? htmlspecialchars($af2['school_address']) : '' ?>">

        <fieldset>
          <input type="text" name="course" placeholder="Course / Program" required value="<?= isset($af2['course']) ? htmlspecialchars($af2['course']) : '' ?>">
          <input type="text" name="year_level" placeholder="Year Level" required value="<?= isset($af2['year_level']) ? htmlspecialchars($af2['year_level']) : '' ?>">
        </fieldset>

        <fieldset>
          <input type="text" name="school_year" placeholder="School Year (e.g. 2024–2025)" required value="<?= isset($af2['school_year']) ? htmlspecialchars($af2['school_year']) : '' ?>">
          <input type="text" name="semester" placeholder="Semester (e.g. 1st, 2nd)" required value="<?= isset($af2['semester']) ? htmlspecialchars($af2['semester']) : '' ?>">
        </fieldset>

        <fieldset>
          <input type="text" name="adviser" placeholder="OJT Adviser" required value="<?= isset($af2['adviser']) ? htmlspecialchars($af2['adviser']) : '' ?>">
          <input type="text" name="adviser_contact" placeholder="Contact Number" required maxlength="11" pattern="[0-9]{11}" value="<?= isset($af2['adviser_contact']) ? htmlspecialchars($af2['adviser_contact']) : '' ?>">
        </fieldset>

        <div class="form-nav">
          <button type="button" onclick="window.location='application_form1.php'">← Previous</button>
          <button type="submit">Next →</button>
        </div>
      </form>
    </div>
  </div>

  <script>
(function(){
  // PH schools list you provided
  const schools = [
    "Bulacan Polytechnic College",
    "Bulacan State University",
    "La Consolacion University Philippines",
    "Centro Escolar University – Malolos Campus",
    "ABE International Business College – Malolos",
    "STI College – Malolos",
    "Baliuag University",
    "College of Our Lady of Mercy of Pulilan Foundation",
    "Meycauayan College",
    "St. Mary’s College of Meycauayan",
    "Immaculate Conception International College of Arts and Technology",
    "Asian Institute of Computer Studies – Malolos",
    "La Verdad Christian College – Apalit",
    "AMA Computer College – Malolos",
    "Philippine College of Science and Technology – Bulacan Branch"
  ];

  const input = document.getElementById('schoolInput');
  const datalist = document.getElementById('schoolsList');

  function populateList(filter) {
    datalist.innerHTML = '';
    const q = (filter || '').trim().toLowerCase();
    const matches = q === '' ? schools.slice(0,50) : schools.filter(s => s.toLowerCase().includes(q)).slice(0,50);
    matches.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s;
      datalist.appendChild(opt);
    });
  }

  function clearList(){
    datalist.innerHTML = '';
  }

  // State: whether last mousedown was on arrow area (right side)
  let openedByArrow = false;

  // When user presses mouse down on input, detect if it's the arrow area.
  input.addEventListener('mousedown', function(e){
    const rect = input.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    // approximate arrow area: last 28px of control (adjust if needed)
    if (clickX >= rect.width - 28) {
      openedByArrow = true;
      // populate full list so browser will show it on arrow click
      populateList('');
      // let browser open the datalist dropdown naturally
    } else {
      // clicking the textbox itself — do not show full list
      openedByArrow = false;
      // clear datalist to avoid showing the full list on focus
      clearList();
    }
  });

  // On focus: only show list if it was opened by arrow mousedown
  input.addEventListener('focus', function(){
    if (openedByArrow) {
      populateList('');
    } else {
      clearList();
    }
  });

  // Typing: always populate with filtered matches (but hide full list if empty input and not openedByArrow)
  input.addEventListener('input', function(e){
    const q = e.target.value || '';
    if (q.trim() === '') {
      // if user cleared input and didn't use arrow, keep it hidden
      if (openedByArrow) populateList('');
      else clearList();
    } else {
      populateList(q);
      // typing means user didn't click arrow to open full list
      openedByArrow = false;
    }
  });

  // On blur hide list
  input.addEventListener('blur', function(){
    // small timeout so option click registers before clear
    setTimeout(clearList, 120);
    openedByArrow = false;
  });

  // initial state: prefill filtered options if there's a value
  if (input.value && input.value.trim() !== '') populateList(input.value);
})();
</script>
</body>
</html>