<?php 
session_start();
require 'conn.php';

// fetch offices for the select (add this)
$offices = [];
$roff = $conn->query("SELECT office_id, office_name FROM offices ORDER BY office_name");
if ($roff) {
    while ($r = $roff->fetch_assoc()) $offices[] = $r;
    $roff->free();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required hours
    $required_hours = intval($_POST['required_hours']);
    if ($required_hours <= 0) {
        echo "<script>alert('Required hours must be a positive number.');</script>";
    } else {
        // validate office preference (first choice must be provided and valid)
        $office1 = isset($_POST['first_choice']) ? intval($_POST['first_choice']) : 0;
        $office2 = isset($_POST['second_choice']) && $_POST['second_choice'] !== '' ? intval($_POST['second_choice']) : null;

        if ($office1 <= 0) {
            echo "<script>alert('Please select a valid 1st office choice.');</script>";
        } else {
            // check office1 exists
            $validOffice1 = false;
            $s = $conn->prepare("SELECT office_id FROM offices WHERE office_id = ?");
            $s->bind_param("i", $office1);
            $s->execute();
            $s->bind_result($foundOffice1);
            if ($s->fetch()) $validOffice1 = true;
            $s->close();

            if (!$validOffice1) {
                echo "<script>alert('Selected 1st office not found. Please select again.');</script>";
            } else {
                // check office2 exists (optional)
                $validOffice2 = false;
                if ($office2) {
                    $s2 = $conn->prepare("SELECT office_id FROM offices WHERE office_id = ?");
                    $s2->bind_param("i", $office2);
                    $s2->execute();
                    $s2->bind_result($foundOffice2);
                    if ($s2->fetch()) $validOffice2 = true;
                    $s2->close();
                    if (!$validOffice2) $office2 = null; // ignore invalid second choice
                }

                // Prepare uploads folder
                $uploadDir = "uploads/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Handle file uploads
                function uploadFile($inputName, $uploadDir) {
                    if (!empty($_FILES[$inputName]['name']) && is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
                        // basic validation: size <= 2MB and allowed types
                        $maxBytes = 2 * 1024 * 1024;
                        $allowed = ['image/jpeg','image/png','application/pdf'];
                        if ($_FILES[$inputName]['size'] > $maxBytes) return '';
                        if (!in_array(mime_content_type($_FILES[$inputName]['tmp_name']), $allowed)) return '';

                        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES[$inputName]['name']));
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath)) {
                            return $targetPath;
                        }
                    }
                    return '';
                }

                $formal_pic      = uploadFile("formal_pic", $uploadDir);
                $letter_intent   = uploadFile("letter_intent", $uploadDir);
                $resume          = uploadFile("resume", $uploadDir);
                $endorsement     = uploadFile("endorsement", $uploadDir);
                $moa             = uploadFile("moa", $uploadDir);

                // Get AF1 and AF2 data from session
                $af1 = $_SESSION['af1'] ?? [];
                $af2 = $_SESSION['af2'] ?? [];

                $emergency_name = trim(($af1['emg_first'] ?? '') . ' ' . ($af1['emg_last'] ?? ''));
                $total_hours = 500;
                $hours_rendered = 0;
                $status = 'pending';

                // Prepare variables for binding (mysqli requires variables, not expressions)
                $s_first = $af1['first_name'] ?? '';
                $s_last = $af1['last_name'] ?? '';
                $s_address = $af1['address'] ?? '';
                $s_contact = $af1['contact'] ?? '';
                $s_email = $af1['email'] ?? '';
                // birthday from AF1 (YYYY-MM-DD or null)
                $s_birthday = !empty($af1['birthday']) ? $af1['birthday'] : null;
                $s_emergency_name = $emergency_name;
                $s_emergency_relation = $af1['emg_relation'] ?? '';
                $s_emergency_contact = $af1['emg_contact'] ?? '';
                $s_college = $af2['school'] ?? $af2['college'] ?? '';
                $s_course = $af2['course'] ?? '';
                $s_year_level = $af2['year_level'] ?? '';
                $s_school_address = $af2['school_address'] ?? '';
                $s_ojt_adviser = $af2['ojt_adviser'] ?? $af2['adviser'] ?? '';
                $s_adviser_contact = $af2['adviser_contact'] ?? '';
                $s_total_hours = (int)$total_hours;
                $s_hours_rendered = (int)$hours_rendered;
                $s_status = $status;

                // Insert into students table (include birthday)
                $stmt = $conn->prepare("
                  INSERT INTO students (
                    first_name, last_name,
                    address, contact_number, email, birthday,
                    emergency_name, emergency_relation, emergency_contact,
                    college, course, year_level, school_address,
                    ojt_adviser, adviser_contact,
                    total_hours_required, hours_rendered, status
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    echo "<script>alert('Database error (students prepare): " . addslashes($conn->error) . "');</script>";
                    exit;
                }

                // 15 strings (including birthday), 2 ints, 1 string -> total 18 params
                $types = str_repeat('s', 15) . 'iis';

                $bind_ok = $stmt->bind_param(
                  $types,
                  $s_first,
                  $s_last,
                  $s_address,
                  $s_contact,
                  $s_email,
                  $s_birthday,
                  $s_emergency_name,
                  $s_emergency_relation,
                  $s_emergency_contact,
                  $s_college,
                  $s_course,
                  $s_year_level,
                  $s_school_address,
                  $s_ojt_adviser,
                  $s_adviser_contact,
                  $s_total_hours,
                  $s_hours_rendered,
                  $s_status
                );

                if (!$bind_ok) {
                    echo "<script>alert('Database bind error (students): " . addslashes($stmt->error) . "');</script>";
                    exit;
                }

                $exec_ok = $stmt->execute();
                if (!$exec_ok) {
                    echo "<script>alert('Database execute error (students): " . addslashes($stmt->error) . "');</script>";
                    $stmt->close();
                    exit;
                }

                $student_id = $conn->insert_id; // ensure we have student_id for application
                $stmt->close();

                // Insert into ojt_applications table (save file paths here)
                $status2 = 'pending';

                if (is_null($office2)) {
                    // office2 NULL path
                    $stmt2 = $conn->prepare("INSERT INTO ojt_applications 
                        (student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, date_submitted)
                        VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, CURDATE())");
                    if (!$stmt2) {
                        echo "<script>alert('Database error (applications prepare NULL): " . addslashes($conn->error) . "');</script>";
                        exit;
                    }

                    $bind_ok2 = $stmt2->bind_param(
                        "iissssss",
                        $student_id,
                        $office1,
                        $letter_intent,
                        $endorsement,
                        $resume,
                        $moa,
                        $formal_pic,
                        $status2
                    );

                    if (!$bind_ok2) {
                        echo "<script>alert('Database bind error (applications NULL): " . addslashes($stmt2->error) . "');</script>";
                        $stmt2->close();
                        exit;
                    }

                } else {
                    // office2 provided
                    $stmt2 = $conn->prepare("INSERT INTO ojt_applications 
                        (student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, date_submitted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
                    if (!$stmt2) {
                        echo "<script>alert('Database error (applications prepare): " . addslashes($conn->error) . "');</script>";
                        exit;
                    }

                    $bind_ok2 = $stmt2->bind_param(
                        "iiissssss",
                        $student_id,
                        $office1,
                        $office2,
                        $letter_intent,
                        $endorsement,
                        $resume,
                        $moa,
                        $formal_pic,
                        $status2
                    );

                    if (!$bind_ok2) {
                        echo "<script>alert('Database bind error (applications): " . addslashes($stmt2->error) . "');</script>";
                        $stmt2->close();
                        exit;
                    }
                }

                $exec2 = $stmt2->execute();
                if (!$exec2) {
                    echo "<script>alert('Database execute error (applications): " . addslashes($stmt2->error) . "');</script>";
                    $stmt2->close();
                    exit;
                }

                $stmt2->close();

                echo "<script>alert('Application submitted successfully!'); window.location='application_form4.php';</script>";
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OJT Application Form - Requirements</title>
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
          <div class="step completed"><span class="label">2. School Information</span></div>
          <div class="step active"><span class="label">3. Requirements</span></div>
        </div>

        <form method="POST" enctype="multipart/form-data" novalidate>
          <h3>OFFICE</h3>

          <fieldset>
            <select name="first_choice" required>
              <option value="" disabled selected>1st choice</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= (int)$o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
              <?php endforeach; ?>
            </select>

            <select name="second_choice">
              <option value="">2nd choice (optional)</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= (int)$o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </fieldset>

          <input type="number" name="required_hours" placeholder="Required Hours" required min="1">

          <h3>UPLOAD REQUIREMENTS</h3>

          <fieldset>
            <div style="flex:1;">
              <label>1x1 Formal Picture</label>
              <input type="file" name="formal_pic" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>
            <div style="flex:1;">
              <label>Letter of Intent</label>
              <input type="file" name="letter_intent" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>
          </fieldset>

          <fieldset>
            <div style="flex:1;">
              <label>Resume</label>
              <input type="file" name="resume" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>
            <div style="flex:1;">
              <label>Endorsement Letter</label>
              <input type="file" name="endorsement" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>
          </fieldset>

          <label>Memorandum of Agreement (to follow)</label>
          <input type="file" name="moa" accept=".jpg,.jpeg,.png,.pdf">

          <p class="note">
            <strong>Note:</strong><br>
            • Supported file types: <span class="highlight">JPG, PNG, PDF</span><br>
            • Maximum file size: <span class="highlight">2MB</span>
          </p>

          <div class="form-nav">
            <button type="button" class="secondary" onclick="window.location='application_form2.php'">← Previous</button>
            <button type="submit">Submit →</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>window.addEventListener('load', () => { document.body.style.opacity = 1; });</script>


</body>
</html>
