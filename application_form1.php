<?php 
session_start();

function compute_age_php($dob) {
    if (empty($dob)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$d) {
        $ts = strtotime($dob);
        if ($ts === false) return null;
        $d = (new DateTime())->setTimestamp($ts);
    }
    $now = new DateTime();
    return $now->diff($d)->y;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $birthday = $_POST['birthday'] ?? '';
    $age_computed = compute_age_php($birthday);

    $_SESSION['af1'] = [
        'first_name'    => $_POST['first_name'] ?? '',
        'middle_name'   => $_POST['middle_name'] ?? '',
        'last_name'     => $_POST['last_name'] ?? '',
        'address'       => $_POST['address'] ?? '',
        'age'           => $age_computed,
        'email'         => $_POST['email'] ?? '',
        'birthday'      => $birthday,
        'contact'       => $_POST['contact'] ?? '',
        'gender'        => $_POST['gender'] ?? '',
        'emg_first'     => $_POST['emg_first'] ?? '',
        'emg_middle'    => $_POST['emg_middle'] ?? '',
        'emg_last'      => $_POST['emg_last'] ?? '',
        'emg_relation'  => $_POST['emg_relation'] ?? '',
        'emg_contact'   => $_POST['emg_contact'] ?? ''
    ];
    header("Location: application_form2.php");
    exit;
}

$af1 = isset($_SESSION['af1']) ? $_SESSION['af1'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OJT Form - Personal Information</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Floating placeholder fix for date input */
    .input-with-placeholder {
      position: relative;
      width: 100%;
    }

    /* hide the browser's built-in date hint (e.g. "mm/dd/yyyy") when the field is empty.
       the script toggles the 'has-value' class when a value exists so we can restore text color. */
    .input-with-placeholder input[type="date"] {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #e0e7ef;
      font-size: 14px;
      color: transparent; /* hide the browser placeholder text */
      -webkit-text-fill-color: transparent; /* WebKit (Chrome/Safari) */
      caret-color: #333;
      background-clip: padding-box;
    }

    /* show the selected date text when value exists (script adds .has-value) */
    .input-with-placeholder input[type="date"].has-value {
      color: #333;
      -webkit-text-fill-color: #333;
    }

    .input-with-placeholder label.placeholder {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #8b8f9f;
      pointer-events: none;
      transition: 0.2s ease all;
      font-size: 14px;
      background: white;
      padding: 0 4px;
    }

    .input-with-placeholder input[type="date"].has-value + label,
    .input-with-placeholder input[type="date"]:focus + label {
      top: -8px;
      font-size: 12px;
      color: #3a4163;
    }

    /* layout for Birthday + Gender in the same row */
    fieldset.field-date-gender {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    fieldset.field-date-gender .input-with-placeholder {
      flex: 1;
      min-width: 0; /* allow flex children to shrink on small screens */
    }
    fieldset.field-date-gender select {
      flex: 1;
      min-width: 0;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #e0e7ef;
      font-size: 14px;
      background: #fff;
      appearance: none;
    }
  </style>
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
        <p>Application Form</p>
        <img src="O.png" alt="Illustration">
      </div>

      <div class="right">
        <div class="progress" aria-hidden="true">
          <div class="step active"><span class="label">1. Personal Information</span></div>
          <div class="step"><span class="label">2. School Information</span></div>
          <div class="step"><span class="label">3. Requirements</span></div>
        </div>

        <form id="ojtForm" method="POST" novalidate>
          <fieldset>
            <input type="text" name="first_name" placeholder="First Name *" required value="<?= isset($af1['first_name']) ? htmlspecialchars($af1['first_name']) : '' ?>">
            <input type="text" name="middle_name" placeholder="Middle Name" value="<?= isset($af1['middle_name']) ? htmlspecialchars($af1['middle_name']) : '' ?>">
            <input type="text" name="last_name" placeholder="Last Name *" required value="<?= isset($af1['last_name']) ? htmlspecialchars($af1['last_name']) : '' ?>">
          </fieldset>
 
          <input type="text" name="address" placeholder="Complete Address *" required value="<?= isset($af1['address']) ? htmlspecialchars($af1['address']) : '' ?>">
 
           <fieldset>
            <input type="email" name="email" id="email" placeholder="Email Address *" required value="<?= isset($af1['email']) ? htmlspecialchars($af1['email']) : '' ?>">
            <input type="text" name="contact" id="contact" placeholder="Contact Number *" maxlength="11" required value="<?= isset($af1['contact']) ? htmlspecialchars($af1['contact']) : '' ?>">
           </fieldset>
 
           <fieldset class="field-date-gender">
             <div class="input-with-placeholder">
              <input type="date" name="birthday" id="birthday" required value="<?= isset($af1['birthday']) ? htmlspecialchars($af1['birthday']) : '' ?>">
              <label class="placeholder">Birthday *</label>
             </div>
 
            <select name="gender" required>
              <option value="" disabled <?= !isset($af1['gender']) ? 'selected' : '' ?>>Gender *</option>
              <option value="Male" <?= (isset($af1['gender']) && $af1['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (isset($af1['gender']) && $af1['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
              <option value="Prefer not to say" <?= (isset($af1['gender']) && $af1['gender'] == 'Prefer not to say') ? 'selected' : '' ?>>Prefer not to say</option>
            </select>
           </fieldset>
 
           <h3>Emergency Contact</h3>
           <fieldset>
            <input type="text" name="emg_first" placeholder="First Name *" required value="<?= isset($af1['emg_first']) ? htmlspecialchars($af1['emg_first']) : '' ?>">
            <input type="text" name="emg_middle" placeholder="Middle Name" value="<?= isset($af1['emg_middle']) ? htmlspecialchars($af1['emg_middle']) : '' ?>">
            <input type="text" name="emg_last" placeholder="Last Name *" required value="<?= isset($af1['emg_last']) ? htmlspecialchars($af1['emg_last']) : '' ?>">
          </fieldset>
 
           <fieldset>
            <input type="text" name="emg_relation" placeholder="Relationship *" required value="<?= isset($af1['emg_relation']) ? htmlspecialchars($af1['emg_relation']) : '' ?>">
            <input type="text" name="emg_contact" id="emg_contact" placeholder="Contact Number *" maxlength="11" required value="<?= isset($af1['emg_contact']) ? htmlspecialchars($af1['emg_contact']) : '' ?>">
          </fieldset>
 
          <div class="form-nav">
            <button type="button" class="secondary" onclick="window.location='home.php'">Cancel</button>
            <button type="submit">Next →</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.addEventListener('load', () => { document.body.style.opacity = 1; });

    (function(){
      const today = new Date().toISOString().split('T')[0];
      const birthdayInput = document.getElementById('birthday');
      if (birthdayInput) birthdayInput.setAttribute('max', today);

      function refreshDateState() {
        if (!birthdayInput) return;
        if (birthdayInput.value) birthdayInput.classList.add('has-value');
        else birthdayInput.classList.remove('has-value');
      }
      if (birthdayInput) {
        ['input','change','focus','blur'].forEach(ev => birthdayInput.addEventListener(ev, refreshDateState));
        refreshDateState();
      }

      const contactInput = document.getElementById('contact');
      const emgContact = document.getElementById('emg_contact');
      [contactInput, emgContact].forEach(input => {
        if (!input) return;
        input.addEventListener('input', function() {
          this.value = this.value.replace(/[^0-9]/g, '');
          if (this.value.length > 11) this.value = this.value.slice(0, 11);
        });
      });

      const form = document.getElementById('ojtForm');
      if (form) {
        form.addEventListener('submit', function(e) {
          // 1) Ensure all required fields are filled first
          const reqs = form.querySelectorAll('[required]');
          for (let i = 0; i < reqs.length; i++) {
            const el = reqs[i];
            const val = (el.value || '').toString().trim();
            if (val === '') {
              alert('Please complete all required fields.');
              el.focus();
              e.preventDefault();
              return false;
            }
          }

          // 2) Validate email format
          const email = document.getElementById('email').value || '';
          const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
          if (!emailPattern.test(email)) {
            alert('Please enter a valid email address.');
            e.preventDefault();
            return false;
          }

          // 3) Validate contact number length
          const contact = contactInput ? contactInput.value : '';
          if (contact.length !== 11) {
            alert('Contact number must be exactly 11 digits.');
            e.preventDefault();
            return false;
          }
 
        });
      }
    })();
  </script>
</body>
</html>
