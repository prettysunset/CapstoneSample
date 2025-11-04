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

    // persist AF2 into students table (insert or update). requires conn.php to provide $conn (mysqli)
    require_once __DIR__ . '/conn.php';
    if (!empty($conn) && $conn instanceof mysqli) {
        $school = trim($_SESSION['af2']['school']);
        $school_address = trim($_SESSION['af2']['school_address']);
        $course = trim($_SESSION['af2']['course']);
        $year_level = trim($_SESSION['af2']['year_level']);
        $school_year = trim($_SESSION['af2']['school_year']);
        $semester = trim($_SESSION['af2']['semester']);
        $adviser = trim($_SESSION['af2']['adviser']);
        $adviser_contact = trim($_SESSION['af2']['adviser_contact']);

        // try to find existing student by session student_id or by email from AF1
        $student_id = $_SESSION['student_id'] ?? null;
        $email = $_SESSION['af1']['email'] ?? '';

        if (empty($student_id) && $email !== '') {
            $ps = $conn->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
            if ($ps) {
                $ps->bind_param('s', $email);
                $ps->execute();
                $res = $ps->get_result();
                if ($r = $res->fetch_assoc()) $student_id = (int)$r['student_id'];
                $ps->close();
            }
        }

        if (!empty($student_id)) {
            $upd = $conn->prepare("UPDATE students SET college = ?, course = ?, year_level = ?, school_year = ?, semester = ?, school_address = ?, ojt_adviser = ?, adviser_contact = ? WHERE student_id = ?");
            if ($upd) {
                $upd->bind_param('ssssssssi', $school, $course, $year_level, $school_year, $semester, $school_address, $adviser, $adviser_contact, $student_id);
                $upd->execute();
                $upd->close();
            }
        } else {
            // insert: include AF1 data if available
            $first = trim($_SESSION['af1']['first_name'] ?? '');
            $middle = trim($_SESSION['af1']['middle_name'] ?? '');
            $last = trim($_SESSION['af1']['last_name'] ?? '');
            $address = trim($_SESSION['af1']['address'] ?? '');
            $contact = trim($_SESSION['af1']['contact'] ?? '');
            $birthday = $_SESSION['af1']['birthday'] ?? null;
            $emg_name = trim(($_SESSION['af1']['emg_first'] ?? '') . ' ' . ($_SESSION['af1']['emg_middle'] ?? '') . ' ' . ($_SESSION['af1']['emg_last'] ?? ''));
            $emg_relation = trim($_SESSION['af1']['emg_relation'] ?? '');
            $emg_contact = trim($_SESSION['af1']['emg_contact'] ?? '');
            $email = trim($_SESSION['af1']['email'] ?? '');
            $default_hours = 500;

            $ins = $conn->prepare("INSERT INTO students (first_name, middle_name, last_name, address, contact_number, email, birthday, emergency_name, emergency_relation, emergency_contact, college, course, year_level, school_address, ojt_adviser, adviser_contact, total_hours_required) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($ins) {
                $ins->bind_param('ssssssssssssssssi', $first, $middle, $last, $address, $contact, $email, $birthday, $emg_name, $emg_relation, $emg_contact, $school, $course, $year_level, $school_address, $adviser, $adviser_contact, $default_hours);
                $ins->execute();
                $student_id = $ins->insert_id;
                $ins->close();
            }
        }

        if (!empty($student_id)) $_SESSION['student_id'] = (int)$student_id;
    }

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

        <form method="POST" novalidate id="af2Form">
          <h3>YOUR SCHOOL INFORMATION</h3>

          <input
            type="text"
            name="school"
            id="schoolInput"
            list="schoolsList"
            placeholder="School *"
            required
            value="<?= isset($af2['school']) ? htmlspecialchars($af2['school']) : '' ?>"
          >
          <datalist id="schoolsList"></datalist>

          <input type="text" name="school_address" placeholder="School Address *" required value="<?= isset($af2['school_address']) ? htmlspecialchars($af2['school_address']) : '' ?>">

          <fieldset>
            <input type="text" name="course" placeholder="Course / Program *" required value="<?= isset($af2['course']) ? htmlspecialchars($af2['course']) : '' ?>">
            <select name="year_level" required>
              <option value="" disabled <?= !isset($af2['year_level']) ? 'selected' : '' ?>>Year Level *</option>
              <option value="3" <?= (isset($af2['year_level']) && $af2['year_level'] == '3') ? 'selected' : '' ?>>3rd Year</option>
              <option value="4" <?= (isset($af2['year_level']) && $af2['year_level'] == '4') ? 'selected' : '' ?>>4th Year</option>
              <option value="5" <?= (isset($af2['year_level']) && $af2['year_level'] == '5') ? 'selected' : '' ?>>5th Year</option>
            </select>
          </fieldset>

          <fieldset>
            <input type="text" name="school_year" placeholder="School Year (e.g. 2024–2025) *" required value="<?= isset($af2['school_year']) ? htmlspecialchars($af2['school_year']) : '' ?>">
            <select name="semester" required>
              <option value="" disabled <?= !isset($af2['semester']) ? 'selected' : '' ?>>Semester *</option>
              <option value="1st Semester" <?= (isset($af2['semester']) && $af2['semester'] == '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
              <option value="2nd Semester" <?= (isset($af2['semester']) && $af2['semester'] == '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
              <option value="3rd Term (for Trimester schools)" <?= (isset($af2['semester']) && $af2['semester'] == '3rd Term (for Trimester schools)') ? 'selected' : '' ?>>3rd Term (for Trimester schools)</option>
            </select>
          </fieldset>

          <fieldset>
            <input type="text" name="adviser" placeholder="OJT Adviser *" required value="<?= isset($af2['adviser']) ? htmlspecialchars($af2['adviser']) : '' ?>">
            <input type="text" id="adviser_contact" name="adviser_contact" placeholder="Contact Number *" required maxlength="11" pattern="[0-9]{11}" value="<?= isset($af2['adviser_contact']) ? htmlspecialchars($af2['adviser_contact']) : '' ?>">
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

  // client-side required validation before submit (ensure required fields first)
  const form = document.getElementById('af2Form');
  if (form) {
    form.addEventListener('submit', function(e){
      const reqs = form.querySelectorAll('[required]');
      for (let i=0;i<reqs.length;i++){
        const el = reqs[i];
        const val = (el.value || '').toString().trim();
        if (val === '') {
          alert('Please complete all required fields.');
          el.focus();
          e.preventDefault();
          return false;
        }
      }
      // adviser contact numeric length check
      const adv = document.getElementById('adviser_contact');
      if (adv) {
        if (adv.value.replace(/\D/g,'').length !== 11) {
          alert('Adviser contact must be 11 digits.');
          adv.focus();
          e.preventDefault();
          return false;
        }
      }
    });
  }
})();
</script>
</body>
</html>
