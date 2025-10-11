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
</head>
<body>
  <div class="container">
    <!-- LEFT SIDE -->
    <div class="left">
      <h1>OJTMS</h1>
      <p>OJT APPLICATION FORM</p>
      <img src="ojt_illustration.png" alt="Illustration" width="200">
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

        <select name="school" required>
          <option value="" disabled <?= !isset($af2['school']) ? 'selected' : '' ?>>School</option>
          <option value="Bulacan Polytechnic College" <?= (isset($af2['school']) && $af2['school'] == 'Bulacan Polytechnic College') ? 'selected' : '' ?>>Bulacan Polytechnic College</option>
          <option value="Bulacan State University" <?= (isset($af2['school']) && $af2['school'] == 'Bulacan State University') ? 'selected' : '' ?>>Bulacan State University</option>
          <option value="La Consolacion University" <?= (isset($af2['school']) && $af2['school'] == 'La Consolacion University') ? 'selected' : '' ?>>La Consolacion University</option>
        </select>

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
</body>
</html>