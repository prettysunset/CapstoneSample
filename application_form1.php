<?php
session_start();

function compute_age_php($dob) {
    if (empty($dob)) return null;
    // expect YYYY-MM-DD from <input type="date">
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$d) {
        $ts = strtotime($dob);
        if ($ts === false) return null;
        $d = (new DateTime())->setTimestamp($ts);
    }
    $now = new DateTime();
    return $now->diff($d)->y;
}

// Save AF1 data to session and redirect to AF2
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $birthday = $_POST['birthday'] ?? '';
    $age_computed = compute_age_php($birthday);

    $_SESSION['af1'] = [
        'first_name'    => $_POST['first_name'],
        'middle_name'   => $_POST['middle_name'],
        'last_name'     => $_POST['last_name'],
        'address'       => $_POST['address'],
        'age'           => $age_computed,               // computed server-side
        'email'         => $_POST['email'],
        'birthday'      => $birthday,
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

    /* birthday fake placeholder (CSS only) */
    .input-with-placeholder { position: relative; }
    .input-with-placeholder input[type="date"]{
      width:100%;
      padding:10px 12px;
      border-radius:6px;
      border:1px solid #ddd;
      background:#fff;
      appearance: none;
      /* default: hide native hint text when empty */
      color: transparent;
    }
    /* make date and gender visually match other inputs */
    .input-with-placeholder input[type="date"],
    select[name="gender"] {
      padding:10px 12px;
      border-radius:6px;
      border:1px solid #ddd;
      font-size:14px;
      height:42px;
      background:#fff;
      box-sizing:border-box;
    }
    .input-with-placeholder input[type="date"].has-value,
    .input-with-placeholder input[type="date"]:focus {
      /* show typed / selected date when focused or has value */
      color: #222;
    }
    .input-with-placeholder label.placeholder{
      position:absolute;
      left:12px;
      top:50%;
      transform:translateY(-50%);
      color:#8b8f9f;
      pointer-events:none;
      font-size:14px;
      transition:0.12s opacity;
    }
    /* hide placeholder when input has a value or is focused */
    .input-with-placeholder input[type="date"]:focus + label.placeholder,
    .input-with-placeholder input[type="date"].has-value + label.placeholder{
      opacity:0;
      visibility:hidden;
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
          <!-- removed manual age input; age will be computed from birthday -->
          <input type="email" name="email" id="email" placeholder="Email Address" required value="<?= isset($af1['email']) ? htmlspecialchars($af1['email']) : '' ?>">
          <input type="text" name="contact" id="contact" placeholder="Contact Number" maxlength="11" required value="<?= isset($af1['contact']) ? htmlspecialchars($af1['contact']) : '' ?>">
        </fieldset>

        <fieldset>
          <!-- birthday input (visible "Birthday" label over native date input) -->
          <div class="input-with-placeholder" style="margin-bottom:8px;">
            <input type="date" name="birthday" id="birthday" required value="<?= isset($af1['birthday']) ? htmlspecialchars($af1['birthday']) : '' ?>">
            <label class="placeholder">Birthday</label>
          </div>
          <select name="gender" required>
            <option value="" disabled <?= !isset($af1['gender']) ? 'selected' : '' ?>>Gender</option>
            <option value="Male" <?= (isset($af1['gender']) && $af1['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= (isset($af1['gender']) && $af1['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
            <option value="Prefer not to say" <?= (isset($af1['gender']) && $af1['gender'] == 'Prefer not to say') ? 'selected' : '' ?>>Prefer not to say</option>
          </select>

          <!-- age is removed from the form (computed server-side) -->
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
    (function(){
      // compute age from birthday
      function computeAgeFromDOB(dobStr) {
        if (!dobStr) return '';
        const b = new Date(dobStr);
        if (isNaN(b.getTime())) return '';
        const today = new Date();
        let age = today.getFullYear() - b.getFullYear();
        const m = today.getMonth() - b.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < b.getDate())) {
          age--;
        }
        return age;
      }

      // Disable future dates for birthday
      const today = new Date().toISOString().split('T')[0];
      const birthdayInput = document.getElementById('birthday');
      if (birthdayInput) birthdayInput.setAttribute('max', today);

      // Toggle visual state so native mm/dd/yyyy hint is hidden until user focuses or selects
      function refreshDateState() {
        if (!birthdayInput) return;
        if (birthdayInput.value) {
          birthdayInput.classList.add('has-value');
        } else {
          birthdayInput.classList.remove('has-value');
        }
      }
      if (birthdayInput) {
        birthdayInput.addEventListener('input', refreshDateState);
        birthdayInput.addEventListener('change', refreshDateState);
        birthdayInput.addEventListener('focus', refreshDateState);
        birthdayInput.addEventListener('blur', refreshDateState);
        // init
        refreshDateState();
      }

      // Contact number validation (only digits, 11 max)
      const contactInput = document.getElementById('contact');
      const emgContact = document.getElementById('emg_contact');
      [contactInput, emgContact].forEach(input => {
        if (!input) return;
        input.addEventListener('input', function() {
          this.value = this.value.replace(/[^0-9]/g, ''); // only numbers
          if (this.value.length > 11) this.value = this.value.slice(0, 11);
        });
      });

      // Form submit validation
      const form = document.getElementById('ojtForm');
      if (form) {
        form.addEventListener('submit', function(e) {
          const email = document.getElementById('email').value || '';
          const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
          if (!emailPattern.test(email)) {
            alert('Please enter a valid email address.');
            e.preventDefault();
            return false;
          }

          const contact = contactInput ? contactInput.value : '';
          if (contact.length !== 11) {
            alert('Contact number must be exactly 11 digits.');
            e.preventDefault();
            return false;
          }

          const ageVal = computeAgeFromDOB(birthdayInput ? birthdayInput.value : '');
          if (ageVal === '' || ageVal < 15 || ageVal > 99) {
            alert('Please ensure birthday produces an age between 15 and 99.');
            e.preventDefault();
            return false;
          }
        });
      }
    })();
  </script>
</body>
</html>