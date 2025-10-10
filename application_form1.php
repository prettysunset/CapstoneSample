<?php
// ---------------------- PHP Section (Processing) ----------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "capstone");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $first = $_POST['first_name'];
    $middle = $_POST['middle_name'];
    $last = $_POST['last_name'];
    $address = $_POST['address'];
    $age = $_POST['age'];
    $email = $_POST['email'];
    $birthday = $_POST['birthday'];
    $contact = $_POST['contact'];
    $gender = $_POST['gender'];
    $emg_first = $_POST['emg_first'];
    $emg_middle = $_POST['emg_middle'];
    $emg_last = $_POST['emg_last'];
    $emg_relation = $_POST['emg_relation'];
    $emg_contact = $_POST['emg_contact'];

    $sql = "INSERT INTO students 
            (first_name, middle_name, last_name, address, age, email, birthday, contact_number, gender, emg_first, emg_middle, emg_last, emg_relation, emg_contact)
            VALUES 
            ('$first', '$middle', '$last', '$address', '$age', '$email', '$birthday', '$contact', '$gender', '$emg_first', '$emg_middle', '$emg_last', '$emg_relation', '$emg_contact')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Form submitted successfully!');</script>";
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
  <title>OJT Application Form</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="left">
      <h1>OJTMS</h1>
      <p>OJT APPLICATION FORM</p>
      <img src="ojt_illustration.png" alt="Illustration" width="200">
    </div>

    <div class="right">
      <div class="progress">
        <div class="active">1. Personal Information</div>
        <div>2. School Information</div>
        <div>3. Requirements</div>
      </div>

      <!-- HTML FORM -->
      <form id="ojtForm" method="POST">
        <fieldset>
          <input type="text" name="first_name" placeholder="First Name" required>
          <input type="text" name="middle_name" placeholder="Middle Name">
          <input type="text" name="last_name" placeholder="Last Name" required>
        </fieldset>

        <input type="text" name="address" placeholder="Complete Address" required>

        <fieldset>
          <input type="number" name="age" id="age" placeholder="Age" min="15" max="99" required>
          <input type="email" name="email" id="email" placeholder="Email Address" required>
          <input type="text" name="contact" id="contact" placeholder="09XXXXXXXXX" maxlength="11" required>
        </fieldset>

        <fieldset>
          <input type="date" name="birthday" id="birthday" required>
          <select name="gender" required>
            <option value="" disabled selected>Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </fieldset>

        <h3>Emergency Contact</h3>
        <fieldset>
          <input type="text" name="emg_first" placeholder="First Name" required>
          <input type="text" name="emg_middle" placeholder="Middle Name">
          <input type="text" name="emg_last" placeholder="Last Name" required>
        </fieldset>

        <fieldset>
          <input type="text" name="emg_relation" placeholder="Relationship" required>
          <input type="text" name="emg_contact" id="emg_contact" placeholder="Contact Number" maxlength="11" required>
        </fieldset>

        <button type="submit">Next →</button>
      </form>
    </div>
  </div>

  <!-- JavaScript Validation Section -->
  <script>
    // Disable future dates for birthday
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('birthday').setAttribute('max', today);

    // Contact number validation (only digits, 11 max)
    const contactInput = document.getElementById('contact');
    const emgContact = document.getElementById('emg_contact');

    [contactInput, emgContact].forEach(input => {
      input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, ''); // only numbers
        if (this.value.length > 11) {
          this.value = this.value.slice(0, 11); // limit 11 digits
        }
      });
    });

    // Email validation + contact + age checks before submit
    document.getElementById('ojtForm').addEventListener('submit', function(e) {
      const email = document.getElementById('email').value;
      const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
      if (!emailPattern.test(email)) {
        alert('Please enter a valid email address.');
        e.preventDefault();
        return false;
      }

      const contact = document.getElementById('contact').value;
      if (contact.length !== 11) {
        alert('Contact number must be exactly 11 digits.');
        e.preventDefault();
        return false;
      }

      const age = document.getElementById('age').value;
      if (age < 15 || age > 99) {
        alert('Please enter a valid age between 15 and 99.');
        e.preventDefault();
        return false;
      }
    });
  </script>
</body>
</html>
