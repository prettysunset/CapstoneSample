<?php
session_start();

// Save AF1 data to session and redirect to AF2
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['af1'] = [
        'first_name'    => $_POST['first_name'],
        'middle_name'   => $_POST['middle_name'],
        'last_name'     => $_POST['last_name'],
        'address'       => $_POST['address'],
        'age'           => $_POST['age'],
        'email'         => $_POST['email'],
        'birthday'      => $_POST['birthday'],
        'contact'       => $_POST['contact'],
        'gender'        => $_POST['gender'],
        'emg_first'     => $_POST['emg_first'],
        'emg_middle'    => $_POST['emg_middle'],
        'emg_last'      => $_POST['emg_last'],
        'emg_relation'  => $_POST['emg_relation'],
        'emg_contact'   => $_POST['emg_contact']
    ];
    header("Location: application_form2.php");
    exit;
}

// Pre-fill form fields if session data exists
$af1 = isset($_SESSION['af1']) ? $_SESSION['af1'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OJT Form</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="left">
      <h1>OJTMS</h1>
      <p>  FORM</p>
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
          <input type="text" name="first_name" placeholder="First Name" required value="<?= isset($af1['first_name']) ? htmlspecialchars($af1['first_name']) : '' ?>">
          <input type="text" name="middle_name" placeholder="Middle Name" value="<?= isset($af1['middle_name']) ? htmlspecialchars($af1['middle_name']) : '' ?>">
          <input type="text" name="last_name" placeholder="Last Name" required value="<?= isset($af1['last_name']) ? htmlspecialchars($af1['last_name']) : '' ?>">
        </fieldset>

        <input type="text" name="address" placeholder="Complete Address" required value="<?= isset($af1['address']) ? htmlspecialchars($af1['address']) : '' ?>">

        <fieldset>
          <input type="number" name="age" id="age" placeholder="Age" min="15" max="99" required value="<?= isset($af1['age']) ? htmlspecialchars($af1['age']) : '' ?>">
          <input type="email" name="email" id="email" placeholder="Email Address" required value="<?= isset($af1['email']) ? htmlspecialchars($af1['email']) : '' ?>">
          <input type="text" name="contact" id="contact" placeholder="Contact Number" maxlength="11" required value="<?= isset($af1['contact']) ? htmlspecialchars($af1['contact']) : '' ?>">
        </fieldset>

        <fieldset>
          <input type="date" name="birthday" id="birthday" required value="<?= isset($af1['birthday']) ? htmlspecialchars($af1['birthday']) : '' ?>">
          <select name="gender" required>
            <option value="" disabled <?= !isset($af1['gender']) ? 'selected' : '' ?>>Gender</option>
            <option value="Male" <?= (isset($af1['gender']) && $af1['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= (isset($af1['gender']) && $af1['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
            <option value="Prefer not to say" <?= (isset($af1['gender']) && $af1['gender'] == 'Prefer not to say') ? 'selected' : '' ?>>Prefer not to say</option>
          </select>
        </fieldset>

        <h3>Emergency Contact</h3>
        <fieldset>
          <input type="text" name="emg_first" placeholder="First Name" required value="<?= isset($af1['emg_first']) ? htmlspecialchars($af1['emg_first']) : '' ?>">
          <input type="text" name="emg_middle" placeholder="Middle Name" value="<?= isset($af1['emg_middle']) ? htmlspecialchars($af1['emg_middle']) : '' ?>">
          <input type="text" name="emg_last" placeholder="Last Name" required value="<?= isset($af1['emg_last']) ? htmlspecialchars($af1['emg_last']) : '' ?>">
        </fieldset>

        <fieldset>
          <input type="text" name="emg_relation" placeholder="Relationship" required value="<?= isset($af1['emg_relation']) ? htmlspecialchars($af1['emg_relation']) : '' ?>">
          <input type="text" name="emg_contact" id="emg_contact" placeholder="Contact Number" maxlength="11" required value="<?= isset($af1['emg_contact']) ? htmlspecialchars($af1['emg_contact']) : '' ?>">
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