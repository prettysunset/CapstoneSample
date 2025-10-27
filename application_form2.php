<?php
session_start();

// Save AF2 data to session and redirect to AF3
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['af2'] = [
        'school'         => $_POST['school'] ?? '',
        'school_address' => $_POST['school_address'] ?? '',
        'course'         => $_POST['course'] ?? '',
        'year_level'     => $_POST['year_level'] ?? '',
        'school_year'    => $_POST['school_year'] ?? '',
        'semester'       => $_POST['semester'] ?? '',
        'adviser'        => $_POST['adviser'] ?? '',
        'adviser_contact'=> $_POST['adviser_contact'] ?? ''
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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="styles.css">
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

  <div class="wrapper">
    <div class="card">
      <div class="left">
        <h1>OJTMS</h1>
        <p>OJT APPLICATION FORM</p>
        <img src="O.png" alt="Illustration">
      </div>

      <div class="right">
        <div class="progress" aria-hidden="true">
          <div class="step completed"><span class="label">1. Personal Information</span></div>
          <div class="step active"><span class="label">2. School Information</span></div>
          <div class="step"><span class="label">3. Requirements</span></div>
        </div>

        <form method="POST" novalidate>
          <h3>YOUR SCHOOL INFORMATION</h3>

          <input
            type="text"
            name="school"
            id="schoolInput"
            list="schoolsList"
            placeholder="School"
            required
            value="<?= isset($af2['school']) ? htmlspecialchars($af2['school']) : '' ?>"
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
            <button type="button" class="secondary" onclick="window.location='application_form1.php'">← Previous</button>
            <button type="submit">Next →</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
window.addEventListener('load', () => { document.body.style.opacity = 1; });

(function(){
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

  function clearList(){ datalist.innerHTML = ''; }
  let openedByArrow = false;

  if (!input) return;
  input.addEventListener('mousedown', function(e){
    const rect = input.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    if (clickX >= rect.width - 28) {
      openedByArrow = true;
      populateList('');
    } else {
      openedByArrow = false;
      clearList();
    }
  });

  input.addEventListener('focus', function(){
    if (openedByArrow) populateList('');
    else clearList();
  });

  input.addEventListener('input', function(e){
    const q = e.target.value || '';
    if (q.trim() === '') {
      if (openedByArrow) populateList('');
      else clearList();
    } else {
      populateList(q);
      openedByArrow = false;
    }
  });

  input.addEventListener('blur', function(){
    setTimeout(clearList, 120);
    openedByArrow = false;
  });

  if (input.value && input.value.trim() !== '') populateList(input.value);
})();
</script>


</body>
</html>
