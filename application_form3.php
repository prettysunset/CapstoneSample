<?php
session_start();
require 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required hours
    $required_hours = intval($_POST['required_hours']);
    if ($required_hours <= 0) {
        echo "<script>alert('Required hours must be a positive number.');</script>";
    } else {
        // Prepare uploads folder
        $uploadDir = "uploads/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Handle file uploads
        function uploadFile($inputName, $uploadDir) {
            if (!empty($_FILES[$inputName]['name'])) {
                $fileName = time() . '_' . basename($_FILES[$inputName]['name']);
                $targetPath = $uploadDir . $fileName;
                move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath);
                return $targetPath;
            }
            return '';
        }

        $formal_pic      = uploadFile("formal_pic", $uploadDir);
        $letter_intent   = uploadFile("letter_intent", $uploadDir);
        $resume          = uploadFile("resume", $uploadDir);
        $endorsement     = uploadFile("endorsement", $uploadDir);
        $moa             = uploadFile("moa", $uploadDir);

        // Get AF1 and AF2 data from session
        $af1 = $_SESSION['af1'];
        $af2 = $_SESSION['af2'];

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

        // Get office IDs from office names (if needed)
        function getOfficeId($conn, $office_name) {
            $stmt = $conn->prepare("SELECT office_id FROM offices WHERE office_name = ?");
            $stmt->bind_param("s", $office_name);
            $stmt->execute();
            $stmt->bind_result($office_id);
            $stmt->fetch();
            $stmt->close();
            return $office_id ?: null;
        }
        $office1 = getOfficeId($conn, $_POST['first_choice']);
        $office2 = !empty($_POST['second_choice']) ? getOfficeId($conn, $_POST['second_choice']) : null;

        // Insert into ojt_applications table (save file paths here)
        $stmt2 = $conn->prepare("INSERT INTO ojt_applications 
            (student_id, office_preference1, office_preference2, letter_of_intent, endorsement_letter, resume, moa_file, picture, status, date_submitted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
        $status2 = 'pending';
        $stmt2->bind_param("iisssssss", $student_id, $office1, $office2, $letter_intent, $endorsement, $resume, $moa, $formal_pic, $status2);
        $stmt2->execute();
        $stmt2->close();

        echo "<script>alert('Application submitted successfully!'); window.location='application_form4.php';</script>";
        exit;
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
        <div class="completed">2. School Information</div>
        <div class="active">3. Requirements</div>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <h3>OFFICE</h3>

        <fieldset>
          <select name="first_choice" required>
            <option value="" disabled selected>1st choice</option>
            <option value="City Mayor’s Office">City Mayor’s Office</option>
            <option value="City HR Department">City HR Department</option>
            <option value="City Treasurer’s Office">City Treasurer’s Office</option>
          </select>

          <select name="second_choice">
            <option value="">2nd choice (optional)</option>
            <option value="City Mayor’s Office">City Mayor’s Office</option>
            <option value="City HR Department">City HR Department</option>
            <option value="City Treasurer’s Office">City Treasurer’s Office</option>
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
