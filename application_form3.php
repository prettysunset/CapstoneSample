<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "capstone");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $first_choice = $_POST['first_choice'];
    $second_choice = $_POST['second_choice'];
    $required_hours = $_POST['required_hours'];

    // File uploads
    $uploadDir = "uploads/";

    $formal_pic = $uploadDir . basename($_FILES["formal_pic"]["name"]);
    $letter_intent = $uploadDir . basename($_FILES["letter_intent"]["name"]);
    $resume = $uploadDir . basename($_FILES["resume"]["name"]);
    $endorsement = $uploadDir . basename($_FILES["endorsement"]["name"]);
    $moa = $uploadDir . basename($_FILES["moa"]["name"]);

    move_uploaded_file($_FILES["formal_pic"]["tmp_name"], $formal_pic);
    move_uploaded_file($_FILES["letter_intent"]["tmp_name"], $letter_intent);
    move_uploaded_file($_FILES["resume"]["tmp_name"], $resume);
    move_uploaded_file($_FILES["endorsement"]["tmp_name"], $endorsement);
    move_uploaded_file($_FILES["moa"]["tmp_name"], $moa);

    $sql = "INSERT INTO requirements (first_choice, second_choice, required_hours, formal_pic, letter_intent, resume, endorsement, moa)
            VALUES ('$first_choice', '$second_choice', '$required_hours', '$formal_pic', '$letter_intent', '$resume', '$endorsement', '$moa')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Application submitted successfully!'); window.location='application_success.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }

    $conn->close();
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

        <input type="number" name="required_hours" placeholder="Required Hours" required>

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
