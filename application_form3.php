<?php 
session_start();
require 'conn.php';

// fetch offices for the select filtered by course from AF2
// only include offices that have available slots (capacity - approved - active > 0),
// treat NULL capacity as unlimited (show). When a course is selected, DO NOT
// fallback to showing all offices — show only offices related to that course.
$offices = [];
$course_id = $_SESSION['af2']['course_id'] ?? null;

// If course_id not set, try to resolve by course name saved in AF2
if (empty($course_id) && !empty($_SESSION['af2']['course'])) {
    $crsName = trim($_SESSION['af2']['course']);
    if ($crsName !== '') {
        $sr = $conn->prepare("SELECT course_id FROM courses WHERE LOWER(course_name) = LOWER(?) LIMIT 1");
        if ($sr) {
            $sr->bind_param('s', $crsName);
            $sr->execute();
            $tmp = $sr->get_result()->fetch_assoc();
            $sr->close();
            if ($tmp && !empty($tmp['course_id'])) $course_id = (int)$tmp['course_id'];
        }
    }
}

// Helper prepared stmt: count OJTs for an office using users table (robust LIKE + common active statuses)
$cntStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM users
    WHERE LOWER(role) = 'ojt' AND office_name LIKE ? AND status IN ('approved','ongoing')
");

if (!empty($course_id) && ctype_digit((string)$course_id)) {
    // Only offices related to the selected course
    $sql = "
      SELECT o.office_id, o.office_name, COALESCE(o.current_limit, NULL) AS capacity
      FROM offices o
      JOIN office_courses oc ON o.office_id = oc.office_id
      WHERE oc.course_id = ?
      ORDER BY o.office_name
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $course_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $capacity = $r['capacity'] === null ? null : (int)$r['capacity'];
            $officeName = $r['office_name'] ?? '';

            $occupied = 0;
            if ($cntStmt) {
                $like = '%' . $officeName . '%';
                $cntStmt->bind_param('s', $like);
                $cntStmt->execute();
                $rowCnt = $cntStmt->get_result()->fetch_assoc();
                $occupied = (int)($rowCnt['total'] ?? 0);
            }

            $show = true;
            if ($capacity !== null) {
                $available = $capacity - $occupied;
                if ($available <= 0) $show = false;
            }

            if ($show) $offices[] = $r;
         }
         $stmt->close();
     }
} else {
    // No course selected -> show all approved offices with available slots
    $sql = "SELECT office_id, office_name, COALESCE(current_limit, NULL) AS capacity FROM offices ORDER BY office_name";
    $resOff = $conn->query($sql);
    if ($resOff) {
        while ($r = $resOff->fetch_assoc()) {
            $capacity = $r['capacity'] === null ? null : (int)$r['capacity'];
            $officeName = $r['office_name'] ?? '';

            $occupied = 0;
            if ($cntStmt) {
                $like = '%' . $officeName . '%';
                $cntStmt->bind_param('s', $like);
                $cntStmt->execute();
                $resCnt = $cntStmt->get_result();
                $occupied = (int)($resCnt->fetch_assoc()['total'] ?? 0);
            }

            $show = true;
            if ($capacity !== null) {
                $available = $capacity - $occupied;
                if ($available <= 0) $show = false;
            }

            if ($show) $offices[] = $r;
        }
        $resOff->free();
    }
}

if ($cntStmt) $cntStmt->close();

 // detect existing valid MOA for the school entered in AF2 (if any)
$existing_moa = null;
if (!empty($_SESSION['af2']['school'])) {
    $school_search = trim($_SESSION['af2']['school']);
    if ($school_search !== '') {
        $stmtm = $conn->prepare("SELECT moa_file, date_uploaded, COALESCE(validity_months,12) AS validity_months FROM moa WHERE school_name LIKE ? ORDER BY date_uploaded DESC LIMIT 1");
        if ($stmtm) {
            $like = "%{$school_search}%";
            $stmtm->bind_param('s', $like);
            $stmtm->execute();
            $rm = $stmtm->get_result()->fetch_assoc();
            $stmtm->close();
            if ($rm && !empty($rm['moa_file']) && !empty($rm['date_uploaded'])) {
                $valid_until = date('Y-m-d', strtotime("+{$rm['validity_months']} months", strtotime($rm['date_uploaded'])));
                if (strtotime($valid_until) >= strtotime(date('Y-m-d'))) {
                    $existing_moa = $rm['moa_file'];
                }
            }
        }
    }
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
                function uploadFile($inputName, $uploadDir, array $allowedMimes, $maxBytes = 2097152) {
                    if (empty($_FILES[$inputName]['name']) || !is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
                        return ''; // no file provided
                    }
                    if ($_FILES[$inputName]['size'] > $maxBytes) {
                        return ''; // too large
                    }
                    $finfoType = mime_content_type($_FILES[$inputName]['tmp_name']);
                    if (!in_array($finfoType, $allowedMimes, true)) {
                        return ''; // wrong mime
                    }
                    $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES[$inputName]['name']));
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath)) {
                        return $targetPath;
                    }
                    return '';
                }

                // formal_pic: only JPG/PNG ; others: PDF only
                $formal_pic      = uploadFile("formal_pic", $uploadDir, ['image/jpeg','image/png']);
                $letter_intent   = uploadFile("letter_intent", $uploadDir, ['application/pdf']);
                $resume          = uploadFile("resume", $uploadDir, ['application/pdf']);
                $endorsement     = uploadFile("endorsement", $uploadDir, ['application/pdf']);
                // if a valid MOA already exists for the applicant's school, use that file path
                if (!empty($existing_moa)) {
                    $moa = $existing_moa;
                } else {
                    $moa = uploadFile("moa", $uploadDir, ['application/pdf']);
                }

                // Server-side required file/type checks
                if (empty($formal_pic)) {
                    echo "<script>alert('Formal picture is required and must be JPG or PNG (max 2MB).'); window.history.back();</script>";
                    exit;
                }
                // letter_intent, resume, endorsement are required per form; if any empty -> error
                if (empty($letter_intent) || empty($resume) || empty($endorsement)) {
                    echo "<script>alert('Letter of Intent, Resume, and Endorsement Letter are required and must be PDF (max 2MB).'); window.history.back();</script>";
                    exit;
                }

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

                // clear saved application data so forms no longer pre-fill
                unset($_SESSION['af1'], $_SESSION['af2'], $_SESSION['student_id']);
                // server-side redirect (no reliance on JS)
                header("Location: application_form4.php");
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
  <style>
/* Modern Centered Navbar - same as why.html */
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
            <select id="first_choice" name="first_choice" required>
              <option value="" disabled selected>1st choice*</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= (int)$o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
              <?php endforeach; ?>
            </select>

            <select id="second_choice" name="second_choice">
              <option value="">2nd choice (optional)</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= (int)$o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </fieldset>

          <input type="number" name="required_hours" placeholder="Required Hours *" required min="1">

          <h3>UPLOAD REQUIREMENTS</h3>

          <fieldset>
            <div style="flex:1;">
              <label>1x1 Formal Picture * (JPG / PNG only)</label>
              <input type="file" name="formal_pic" accept=".jpg,.jpeg,.png" required>
            </div>
            <div style="flex:1;">
              <label>Letter of Intent * (PDF only)</label>
              <input type="file" name="letter_intent" accept=".pdf" required>
            </div>
          </fieldset>

          <fieldset>
            <div style="flex:1;">
              <label>Resume * (PDF only)</label>
              <input type="file" name="resume" accept=".pdf" required>
            </div>
            <div style="flex:1;">
              <label>Endorsement Letter * (PDF only)</label>
              <input type="file" name="endorsement" accept=".pdf" required>
            </div>
          </fieldset>

          <?php if (!empty($existing_moa)): ?>
<?php
  // Try resolve filesystem path and public URL safely
  $fsCandidate = $existing_moa;
  // if stored relative (e.g. "uploads/xxx.pdf"), try relative to script
  if (!file_exists($fsCandidate)) {
      $fsCandidate = __DIR__ . DIRECTORY_SEPARATOR . ltrim($existing_moa, '/\\');
  }
  // If still not found, try DOCUMENT_ROOT prefix
  if (!file_exists($fsCandidate) && !empty($_SERVER['DOCUMENT_ROOT'])) {
      $fsCandidate = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . ltrim($existing_moa, '/\\');
  }

  if (file_exists($fsCandidate) && is_readable($fsCandidate)) {
      // build public URL from realpath -> remove DOCUMENT_ROOT
      $real = realpath($fsCandidate);
      $docroot = realpath($_SERVER['DOCUMENT_ROOT']) ?: '';
      if ($docroot !== '' && strpos($real, $docroot) === 0) {
          $publicUrl = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($docroot)));
          $publicUrl = '/' . ltrim($publicUrl, '/');
      } else {
          // fallback: if existing_moa already looks like a web path, use it; else use uploads basename
          $publicUrl = htmlspecialchars($existing_moa);
      }
      $base = htmlspecialchars(basename($real));
      echo "<label>Memorandum of Agreement</label>";
      echo "<p><a href=\"{$publicUrl}\" target=\"_blank\">{$base}</a> — MOA on file for your school; no upload required.</p>";
  } else {
      echo "<label>Memorandum of Agreement</label>";
      echo "<p><strong>MOA file is recorded but cannot be accessed.</strong> Possible reasons: file missing or permission issue. Please contact the administrator.</p>";
  }
?>
          <?php else: ?>
            <label>Memorandum of Agreement (to follow) (PDF preferred)</label>
            <input type="file" name="moa" accept=".pdf">
          <?php endif; ?>

          <p class="note">
            <strong>Note:</strong><br>
            • Maximum file size for each file: <span class="highlight">2MB</span>
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
<script>
(function(){
  const first = document.getElementById('first_choice');
  const second = document.getElementById('second_choice');
  if (!first || !second) return;

  // capture original options
  const original = Array.from(second.options).map(o=>({v:o.value,t:o.text}));
  const placeholder = original.find(o=>o.v === '') || null;

  function refreshSecond(){
    const sel = String(first.value || '');
    // rebuild second: always keep placeholder first, then options excluding selected first
    second.innerHTML = '';
    if (placeholder) {
      const ph = document.createElement('option');
      ph.value = placeholder.v;
      ph.text = placeholder.t;
      second.appendChild(ph);
    }
    original.forEach(o=>{
      if (o.v === '') return; // skip placeholder (already added)
      if (o.v === sel) return; // skip office chosen as first choice
      const opt = document.createElement('option');
      opt.value = o.v;
      opt.text = o.t;
      second.appendChild(opt);
    });

    // ensure no automatic selection when first choice is empty
    if (sel === '') {
      second.value = ''; // show placeholder
    } else {
      // if second currently equals the first selection, reset to placeholder
      if (second.value === sel) second.value = '';
    }
  }

  first.addEventListener('change', refreshSecond);
  window.addEventListener('load', refreshSecond);
})();
</script>

<script>
// client-side validation: required fields + file types
+(function(){
  const form = document.querySelector('form[method="POST"][enctype="multipart/form-data"]');
  if (!form) return;
  form.id = form.id || 'af3Form';
  form.addEventListener('submit', function(e){
    // required selects/inputs
    const reqs = form.querySelectorAll('[required]');
    for (let i=0;i<reqs.length;i++){
      const el = reqs[i];
      // skip hidden elements
      if (el.offsetParent === null && el.type !== 'file') continue;
      const val = (el.value || '').toString().trim();
      if (val === '') {
        alert('Please complete all required fields.');
        el.focus();
        e.preventDefault();
        return false;
      }
    }
    // required_hours positive
    const rh = form.querySelector('input[name="required_hours"]');
    if (rh && Number(rh.value) <= 0) {
      alert('Required hours must be a positive number.');
      rh.focus();
      e.preventDefault();
      return false;
    }
    // file validations
    const fFormal = form.querySelector('input[name="formal_pic"]');
    if (!fFormal || !fFormal.files || fFormal.files.length === 0) {
      alert('Please upload your 1x1 Formal Picture (JPG/PNG).');
      e.preventDefault();
      return false;
    } else {
      const f = fFormal.files[0];
      if (!/image\/(jpeg|png)/.test(f.type)) {
        alert('Formal Picture must be JPG or PNG.');
        e.preventDefault();
        return false;
      }
      if (f.size > 2 * 1024 * 1024) {
        alert('Formal Picture must be 2MB or smaller.');
        e.preventDefault();
        return false;
      }
    }
    // other required PDFs
    const pdfFields = ['letter_intent','resume','endorsement'];
    for (let i=0;i<pdfFields.length;i++){
      const el = form.querySelector('input[name="'+pdfFields[i]+'"]');
      if (!el || !el.files || el.files.length === 0) {
        alert('Please upload ' + el.previousElementSibling.textContent.replace('*','').trim() + ' (PDF).');
        e.preventDefault();
        return false;
      }
      const pf = el.files[0];
      if (pf.type !== 'application/pdf' && !/\.pdf$/i.test(pf.name)) {
        alert('Only PDF is accepted for ' + el.previousElementSibling.textContent.replace('*','').trim() + '.');
        e.preventDefault();
        return false;
      }
      if (pf.size > 2 * 1024 * 1024) {
        alert(el.previousElementSibling.textContent.replace('*','').trim() + ' must be 2MB or smaller.');
        e.preventDefault();
        return false;
      }
    }
    return true;
  });
})();
</script>

</body>
</html>
