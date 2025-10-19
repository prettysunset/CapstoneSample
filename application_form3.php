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

                // Insert into students table
                $stmt = $conn->prepare("INSERT INTO students 
                    (first_name, last_name, address, contact_number, email, emergency_name, emergency_relation, emergency_contact, college, course, year_level, school_address, ojt_adviser, adviser_contact, total_hours_required, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssssssssis", 
                    $af1['first_name'], $af1['last_name'], $af1['address'], $af1['contact'], $af1['email'],
                    $af1['emg_first'], $af1['emg_relation'], $af1['emg_contact'],
                    $af2['school'], $af2['course'], $af2['year_level'], $af2['school_address'],
                    $af2['adviser'], $af2['adviser_contact'], $required_hours, $status);
                $status = 'pending';
                $stmt->execute();
                $student_id = $conn->insert_id;
                $stmt->close();

                // Insert into ojt_applications table (save file paths here)
                $stmt2 = $conn->prepare("INSERT INTO ojt_applications 
                    (student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, date_submitted)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
                $status2 = 'pending';
                // bind parameters - ensure variables exist
                $office1_param = $office1;
                $office2_param = $office2;
                $stmt2->bind_param("iisssssss", $student_id, $office1_param, $office2_param, $letter_intent, $endorsement, $resume, $moa, $formal_pic, $status2);
                $stmt2->execute();
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
  <link rel="stylesheet" href="style.css">
</head>
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
      <img src="ojt_illustration.png" alt="Illustration" width="200">
    </div>

    <!-- RIGHT SIDE -->
    <div class="right">
      <div class="progress">
        <div class="completed">1. Personal Information</div>
        <div class="completed">2. School Information</div>
        <div class="active">3. Requirements</div>
      </div>

      <form method="POST" enctype="multipart/form-data">
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
          <label>1x1 Formal Picture</label>
          <input type="file" name="formal_pic" accept=".jpg,.jpeg,.png,.pdf" required>

          <label>Letter of Intent</label>
          <input type="file" name="letter_intent" accept=".jpg,.jpeg,.png,.pdf" required>
        </fieldset>

        <fieldset>
          <label>Resume</label>
          <input type="file" name="resume" accept=".jpg,.jpeg,.png,.pdf" required>

          <label>Endorsement Letter</label>
          <input type="file" name="endorsement" accept=".jpg,.jpeg,.png,.pdf" required>
        </fieldset>

        <label>Memorandum of Agreement (to follow)</label>
        <input type="file" name="moa" accept=".jpg,.jpeg,.png,.pdf">

        <p class="note">
          <strong>Note:</strong><br>
          • Supported file types: <span class="highlight">JPG, PNG, PDF</span><br>
          • Maximum file size: <span class="highlight">2MB</span>
        </p>

        <div class="form-nav">
          <button type="button" onclick="window.location='application_form2.php'">← Previous</button>
          <button type="submit">Submit →</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
