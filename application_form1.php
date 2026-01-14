<?php 
session_start();
// DB connection for server-side checks
require_once 'conn.php';

// Inline AJAX responder for client-side pre-submit check
if (isset($_GET['ajax_check_email'])) {
  header('Content-Type: application/json; charset=utf-8');
  $email = trim($_GET['email'] ?? '');
  $exists = false;
  if ($email !== '' && isset($conn)) {
    $sql = "SELECT 1 FROM users WHERE email = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows > 0) $exists = true;
      $stmt->close();
    }
    if (!$exists) {
      $sql = "SELECT 1 FROM students WHERE email = ? LIMIT 1";
      if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $exists = true;
        $stmt->close();
      }
    }
  }
  echo json_encode(['exists' => $exists]);
  exit;
}

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

    // server-side age validation: must be at least 18
    if ($age_computed === null || $age_computed < 18) {
        $error_age = 'You must be at least 18 years old to apply.';
        // keep submitted values so form is repopulated
        $af1 = [
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
        // do not save to session or redirect; fall through to show form with error
    } else {
      // preserve submitted values in $af1 so we can re-render the form if needed
      $af1 = [
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

      // server-side uniqueness check: users and students
      $email_to_check = trim($_POST['email'] ?? '');
      $duplicate = false;
      if ($email_to_check !== '' && isset($conn)) {
        $sql = "SELECT 1 FROM users WHERE email = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
          $stmt->bind_param('s', $email_to_check);
          $stmt->execute();
          $stmt->store_result();
          if ($stmt->num_rows > 0) $duplicate = true;
          $stmt->close();
        }
        if (!$duplicate) {
          $sql = "SELECT 1 FROM students WHERE email = ? LIMIT 1";
          if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $email_to_check);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $duplicate = true;
            $stmt->close();
          }
        }
      }

      if ($duplicate) {
        $error_email = 'This email is already in use. Please use a different email.';
        // fall through to show form with error and preserved values
      } else {
        // no duplicate -> save and proceed
        $_SESSION['af1'] = $af1;
        header("Location: application_form2.php");
        exit;
      }
    }
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
    .navbar {
    width: 100%;
    display: flex;
    justify-content: center;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-radius: 20px;
    padding: 10px 25px;
    margin: 20px auto;
    transition: all 0.3s ease;
}

.nav-container {
    width: 100%;
    max-width: 1100px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-weight: 900;
    font-size: 1.6rem;
    letter-spacing: 1px;
    text-decoration: none;
    color: #344265;
    transition: color 0.3s ease;
}

.logo:hover {
    color: #4a6ff3;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 25px;
    margin: 0;
    padding: 0;
    align-items: center;
}

.nav-links li {
    position: relative;
}

.nav-links a {
    text-decoration: none;
    color: #3a4163;
    font-weight: 500;
    font-size: 0.95rem;
    padding: 8px 15px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

/* Hover underline animation */
.nav-links a::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%) scaleX(0);
    transform-origin: center;
    width: 60%;
    height: 2px;
    background-color: #4a6ff3;
    transition: transform 0.3s ease;
}

.nav-links a:hover::after {
    transform: translateX(-50%) scaleX(1);
}

.nav-links a:hover {
    color: #4a6ff3;
    background-color: rgba(74, 111, 243, 0.1);
}

/* Login button */
.nav-links .login a {
    background-color: #344265;
    color: white;
    border-radius: 25px;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(74, 111, 243, 0.3);
    transition: all 0.3s ease;
    padding: 8px 20px;
}

.nav-links .login a:hover {
    background-color: #344265;
    box-shadow: 0 2px 8px rgba(52, 66, 101, 0.4);
}

/* Responsive */
@media (max-width: 700px) {
    .nav-container {
        flex-direction: column;
        gap: 10px;
    }
    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }
    .navbar {
        margin: 10px auto;
        padding: 15px;
        border-radius: 15px;
    }
}

  </style>
</head>
<body>
  <nav class="navbar" role="navigation">
  <div class="nav-container">
    <a class="logo" href="about.php">OJT-MS</a>

    <ul class="nav-links">
      <li><a href="home.php">Home</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="contacts.php">Contacts</a></li>
      <li><a href="offices.php">Offices</a></li>
      <li class="login"><a href="login.php">Login</a></li>
    </ul>
  </div>
</nav>


  <div class="wrapper">
    <div class="card">
      <div class="left">
        <h1>OJT-MS</h1>
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
            <label style="display:none" for="emg_relation_select">Relationship</label>
            <select id="emg_relation_select" aria-label="Emergency relationship" required style="padding:10px;border-radius:10px;border:1px solid #e0e7ef;">
              <?php
                $relation = isset($af1['emg_relation']) ? trim($af1['emg_relation']) : '';
                $opts = [ 'Mother','Father','Grandparent','Sibling','Aunt','Uncle','Legal Guardian' ];
                $matched = false;
                foreach ($opts as $o) {
                  if (strcasecmp($o, $relation) === 0) { $matched = true; break; }
                }
              ?>
              <option value="" disabled <?= $relation === '' ? 'selected' : '' ?>>Relationship *</option>
              <?php foreach ($opts as $o): ?>
                <option value="<?= htmlspecialchars($o) ?>" <?= ($relation !== '' && strcasecmp($o,$relation)===0) ? 'selected' : '' ?>><?= htmlspecialchars($o) ?></option>
              <?php endforeach; ?>
              <option value="Other" <?= ($relation !== '' && !$matched) ? 'selected' : '' ?>>Other (specify)</option>
            </select>

            <input type="text" id="emg_relation_other" placeholder="Specify relationship" style="display:none;margin-top:8px;padding:10px;border-radius:10px;border:1px solid #e0e7ef;" />
            <button type="button" id="emg_relation_back" style="display:none;margin-left:8px;margin-top:8px;padding:8px 10px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer">Choose from list</button>
            <input type="hidden" name="emg_relation" id="emg_relation" value="<?= isset($af1['emg_relation']) ? htmlspecialchars($af1['emg_relation']) : '' ?>">

            <input type="text" name="emg_contact" id="emg_contact" placeholder="Contact Number *" maxlength="11" required value="<?= isset($af1['emg_contact']) ? htmlspecialchars($af1['emg_contact']) : '' ?>">
          </fieldset>
 
          <div class="form-nav">
            <button type="button" id="cancelBtn" class="secondary" data-href="clear_application.php">Cancel</button>
             <button type="submit">Next →</button>
           </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (!empty($error_age)): ?>
    <script>window.addEventListener('load',function(){ alert(<?= json_encode($error_age) ?>); });</script>
  <?php endif; ?>

  <script>
    window.addEventListener('load', () => { document.body.style.opacity = 1; });

    (function(){
      const today = new Date();
      // cutoff = today - 18 years
      const cutoff = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
      // build ISO date in local time (avoid toISOString timezone shift)
      const pad = (n) => (n < 10 ? '0' + n : n);
      const cutoffIso = cutoff.getFullYear() + '-' + pad(cutoff.getMonth() + 1) + '-' + pad(cutoff.getDate());

      const birthdayInput = document.getElementById('birthday');
      if (birthdayInput) {
        // set max to ensure users cannot pick a date that makes them younger than 18
        birthdayInput.setAttribute('max', cutoffIso);
        // optional: set a reasonable min (e.g. 1900-01-01)
        birthdayInput.setAttribute('min', '1900-01-01');
      }

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

      // -------------------------
      // Unsaved changes protection
      // -------------------------
      const form = document.getElementById('ojtForm');
      let initialState = '';
      let isDirty = false;

      const serialize = (formEl) => {
        if (!formEl) return '';
        const parts = [];
        Array.from(formEl.elements).forEach(el=>{
          if (!el.name) return;
          if (el.type === 'checkbox' || el.type === 'radio') {
            parts.push(`${el.name}=${el.checked}`);
          } else {
            parts.push(`${el.name}=${(el.value||'').toString()}`);
          }
        });
        return parts.join('&');
      };

      const markDirtyIfChanged = () => {
        const s = serialize(form);
        isDirty = (s !== initialState);
      };

      if (form) {
        initialState = serialize(form);
        // on any change mark dirty
        form.addEventListener('input', markDirtyIfChanged, {capture:true, passive:true});
        form.addEventListener('change', markDirtyIfChanged, {capture:true, passive:true});
        // submitting the form clears the dirty flag (we are navigating intentionally)
        form.addEventListener('submit', function(){ isDirty = false; }, {passive:true});
      }

      // intercept clicks on links and cancel button
      const confirmLeave = (href) => {
        if (!isDirty) { window.location.href = href; return; }
        if (confirm('You have unsaved changes. Leaving will discard your input. Continue?')) {
          // allow navigation
          isDirty = false;
          window.location.href = href;
        }
      };

      // cancel button
      const cancelBtn = document.getElementById('cancelBtn');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e){
          e.preventDefault();
          const href = this.dataset.href || 'clear_application.php';
          confirmLeave(href);
        }, {passive:true});
      }

      // delegate anchor clicks site-wide (only when anchor has an href)
      document.addEventListener('click', function(e){
        const a = e.target.closest('a');
        if (!a || !a.getAttribute('href')) return;
        const href = a.getAttribute('href');
        // ignore links that open in new tab or are javascript: or mailto:
        if (a.target === '_blank' || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
        // allow same-page hash navigation
        if (href.startsWith('#')) return;
        // final: confirm if dirty
        if (isDirty) {
          e.preventDefault();
          if (confirm('You have unsaved changes. Leaving will discard your input. Continue?')) {
            isDirty = false;
            window.location.href = href;
          }
        }
      }, {capture:true});

      // beforeunload native prompt
      window.addEventListener('beforeunload', function(e){
        if (!isDirty) return;
        e.preventDefault();
        e.returnValue = '';
      });
      // -------------------------

      const formEl = document.getElementById('ojtForm');
      if (formEl) {
        // emergency relation dropdown handling
        const relSelect = document.getElementById('emg_relation_select');
        const relOther = document.getElementById('emg_relation_other');
        const relHidden = document.getElementById('emg_relation');
        if (relSelect && relOther && relHidden) {
          const relBack = document.getElementById('emg_relation_back');

          const createWrapper = () => {
            const w = document.createElement('div');
            w.id = '__emg_relation_wrapper';
            w.style.display = 'inline-block';
            // ensure textbox is visible and required
            relOther.style.display = '';
            relOther.required = true;
            // move textbox into wrapper
            w.appendChild(relOther);
            if (relBack) w.appendChild(relBack);
            return w;
          };

          const replaceWithTextbox = () => {
            const parent = relSelect.parentNode;
            if (!parent) return;
            if (!document.getElementById('__emg_relation_wrapper')) {
              const w = createWrapper();
              parent.replaceChild(w, relSelect);
            }
            relHidden.value = '';
            relOther.focus();
          };

          const restoreSelect = () => {
            const wrapper = document.getElementById('__emg_relation_wrapper');
            const parent = wrapper ? wrapper.parentNode : null;
            if (wrapper && parent) {
              // move textbox back to original container (detach)
              parent.replaceChild(relSelect, wrapper);
            }
            // ensure select has no placeholder selection
            relSelect.value = '';
            relSelect.required = true;
            relOther.required = false;
            relHidden.value = '';
            if (relBack) relBack.style.display = 'none';
            relSelect.focus();
          };

          // initialize: if stored value isn't one of the options, show textbox with that value
          (function initRel() {
            const cur = (relHidden.value || '').trim();
            const isOption = cur !== '' && Array.from(relSelect.options).some(opt => opt.value.toLowerCase() === cur.toLowerCase());
            if (cur !== '' && !isOption) {
              // insert wrapper with textbox containing the current custom value
              const w = createWrapper();
              relOther.value = cur;
              relOther.required = true;
              relBack.style.display = 'inline-block';
              relSelect.parentNode.replaceChild(w, relSelect);
            } else {
              // if cur matches an option, set it; otherwise keep placeholder
              if (isOption) relSelect.value = cur;
            }
          })();

          relSelect.addEventListener('change', function(){
            if (relSelect.value === 'Other') replaceWithTextbox();
            else relHidden.value = relSelect.value;
          }, {passive:true});

          if (relBack) {
            relBack.addEventListener('click', function(){
              restoreSelect();
            }, {passive:true});
          }
        }

        formEl.addEventListener('submit', async function(e) {
          // ensure emergency relation hidden field is synchronized before validation
          if (relSelect && relHidden && relOther) {
            if (relSelect.value === 'Other') {
              relHidden.value = (relOther.value || '').trim();
            } else {
              relHidden.value = relSelect.value;
            }
          }
          e.preventDefault();
          // 1) Ensure all required fields are filled first
          const reqs = form.querySelectorAll('[required]');
          for (let i = 0; i < reqs.length; i++) {
            const el = reqs[i];
            const val = (el.value || '').toString().trim();
            if (val === '') {
              alert('Please complete all required fields.');
              el.focus();
              return false;
            }
          }

          // 2) Validate email format
          const email = document.getElementById('email').value || '';
          const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
          if (!emailPattern.test(email)) {
            alert('Please enter a valid email address.');
            return false;
          }

          // 3) Validate contact number length
          const contact = contactInput ? contactInput.value : '';
          if (contact.length !== 11) {
            alert('Contact number must be exactly 11 digits.' );
            return false;
          }

          // 4) Client-side age check (defensive): ensure birthday makes user at least 18
          if (birthdayInput && birthdayInput.value) {
            const b = new Date(birthdayInput.value);
            if (isNaN(b.getTime())) {
              alert('Please enter a valid birthday.');
              return false;
            }
            const now = new Date();
            const age = now.getFullYear() - b.getFullYear() - ((now.getMonth() < b.getMonth() || (now.getMonth() === b.getMonth() && now.getDate() < b.getDate())) ? 1 : 0);
            if (age < 18) {
              alert('You must be at least 18 years old to apply.');
              birthdayInput.focus();
              return false;
            }
          } else {
            alert('Birthday is required.');
            if (birthdayInput) birthdayInput.focus();
            return false;
          }

          // 5) Pre-submit duplicate email check via AJAX to avoid full-page refresh when duplicate
          try {
            const resp = await fetch('?ajax_check_email=1&email=' + encodeURIComponent(email), {cache: 'no-store'});
            if (resp.ok) {
              const data = await resp.json();
              if (data && data.exists) {
                alert('This email is already in use. Please use a different email.');
                const em = document.getElementById('email'); if (em) em.focus();
                return false;
              }
            }
          } catch (err) {
            // network error: allow submit so server-side defensive check can run
          }

          // no duplicate found (or AJAX failed) -> proceed with normal POST
          isDirty = false; // clear unsaved flag
          form.submit();
        });
      }
    })();
  </script>
</body>
</html>