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
        $stmtm = $conn->prepare("SELECT moa_file, date_signed, valid_until FROM moa WHERE school_name LIKE ? ORDER BY date_signed DESC LIMIT 1");
        if ($stmtm) {
            $like = "%{$school_search}%";
            $stmtm->bind_param('s', $like);
            $stmtm->execute();
            $rm = $stmtm->get_result()->fetch_assoc();
            $stmtm->close();
            if ($rm && !empty($rm['moa_file']) && !empty($rm['date_signed'])) {
                if (strtotime($rm['valid_until']) >= strtotime(date('Y-m-d'))) {
                    $existing_moa = $rm['moa_file'];
                }
            }
        }
    }
}

// <-- Add: load saved AF3 (if any) so we can pre-fill fields on load
$af3 = $_SESSION['af3'] ?? [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // If user clicked Previous: save AF3 fields to session and go back to AF2
    if (isset($_POST['action']) && $_POST['action'] === 'prev') {
      // Save non-file inputs so they persist when returning to AF3
      // ensure empty/blank required_hours is saved as null (not 0)
      $raw_rh = isset($_POST['required_hours']) ? trim((string)$_POST['required_hours']) : '';
      $rh_val = ($raw_rh !== '' && is_numeric($raw_rh)) ? intval($raw_rh) : null;

      // Prepare tmp upload dir for previous/save-as-draft behavior
      $tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
      if (!file_exists($tmpDir)) mkdir($tmpDir, 0777, true);

      // Helper: save uploaded file to tmp and return relative path or empty string
      function tempSaveFile($inputName, $tmpDir, array $allowedMimes, $maxBytes = 2097152) {
        if (empty($_FILES[$inputName]['name']) || !is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
          return ''; // no file provided
        }
        if ($_FILES[$inputName]['size'] > $maxBytes) {
          return '';
        }
        $finfoType = mime_content_type($_FILES[$inputName]['tmp_name']);
        if (!in_array($finfoType, $allowedMimes, true)) {
          return '';
        }
        $fileName = 'tmp_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES[$inputName]['name']));
        $targetPath = $tmpDir . $fileName;
        if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath)) {
          // return web-accessible relative path (same uploads/ prefix used elsewhere)
          $rel = 'uploads/tmp/' . $fileName;
          return $rel;
        }
        return '';
      }

      // Collect any uploaded files and save to session (cleanup previous temp if replaced)
      $sessionFiles = $_SESSION['af3']['files'] ?? [];
      $fields = [
        'formal_pic' => ['mimes'=>['image/jpeg','image/png']],
        'letter_intent' => ['mimes'=>['application/pdf']],
        'resume' => ['mimes'=>['application/pdf']],
        'endorsement' => ['mimes'=>['application/pdf']],
        'moa' => ['mimes'=>['application/pdf']]
      ];
      foreach ($fields as $fname => $meta) {
        $saved = tempSaveFile($fname, $tmpDir, $meta['mimes']);
        if ($saved !== '') {
          // remove previous temp file if it exists and differs
          if (!empty($sessionFiles[$fname]) && $sessionFiles[$fname] !== $saved) {
            $prevPath = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $sessionFiles[$fname]), DIRECTORY_SEPARATOR);
            if (file_exists($prevPath)) @unlink($prevPath);
          }
          $sessionFiles[$fname] = $saved;
        }
      }

      $_SESSION['af3'] = [
        'first_choice'    => isset($_POST['first_choice']) ? intval($_POST['first_choice']) : null,
        'second_choice'   => (isset($_POST['second_choice']) && $_POST['second_choice'] !== '') ? intval($_POST['second_choice']) : null,
        'required_hours'  => $rh_val,
        'files' => $sessionFiles
      ];

      header("Location: application_form2.php");
      exit;
    }

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

                // tmp dir and helper to promote a temp-saved file into permanent uploads/
                $tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
                if (!file_exists($tmpDir)) mkdir($tmpDir, 0777, true);

                function promoteTempFile($relPath, $uploadDir) {
                  // $relPath expected like 'uploads/tmp/tmp_xxx_filename'
                  $src = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relPath), DIRECTORY_SEPARATOR);
                  if (!file_exists($src)) return '';
                  $newName = time() . '_' . basename($src);
                  $destFS = __DIR__ . DIRECTORY_SEPARATOR . rtrim(str_replace('/', DIRECTORY_SEPARATOR, $uploadDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
                  if (rename($src, $destFS)) {
                    // return relative web path (uploads/filename)
                    return rtrim($uploadDir, '/') . $newName;
                  }
                  return '';
                }

                // Use session-saved temp files if user didn't re-upload on final submit
                $sessionFiles = $_SESSION['af3']['files'] ?? [];

                // formal_pic: only JPG/PNG ; others: PDF only
                $formal_pic      = uploadFile("formal_pic", $uploadDir, ['image/jpeg','image/png']);
                if (empty($formal_pic) && !empty($sessionFiles['formal_pic'])) {
                  $formal_pic = promoteTempFile($sessionFiles['formal_pic'], $uploadDir);
                  unset($sessionFiles['formal_pic']);
                }

                $letter_intent   = uploadFile("letter_intent", $uploadDir, ['application/pdf']);
                if (empty($letter_intent) && !empty($sessionFiles['letter_intent'])) {
                  $letter_intent = promoteTempFile($sessionFiles['letter_intent'], $uploadDir);
                  unset($sessionFiles['letter_intent']);
                }

                $resume          = uploadFile("resume", $uploadDir, ['application/pdf']);
                if (empty($resume) && !empty($sessionFiles['resume'])) {
                  $resume = promoteTempFile($sessionFiles['resume'], $uploadDir);
                  unset($sessionFiles['resume']);
                }

                $endorsement     = uploadFile("endorsement", $uploadDir, ['application/pdf']);
                if (empty($endorsement) && !empty($sessionFiles['endorsement'])) {
                  $endorsement = promoteTempFile($sessionFiles['endorsement'], $uploadDir);
                  unset($sessionFiles['endorsement']);
                }

                // if a valid MOA already exists for the applicant's school, use that file path
                if (!empty($existing_moa)) {
                  $moa = $existing_moa;
                } else {
                  $moa = uploadFile("moa", $uploadDir, ['application/pdf']);
                  if (empty($moa) && !empty($sessionFiles['moa'])) {
                    $moa = promoteTempFile($sessionFiles['moa'], $uploadDir);
                    unset($sessionFiles['moa']);
                  }
                }

                // persist any remaining sessionFiles changes
                if (!empty($sessionFiles)) {
                  $_SESSION['af3']['files'] = $sessionFiles;
                } else {
                  unset($_SESSION['af3']['files']);
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
                // Use the user-provided required hours (validated earlier). Fallback to NULL if not set.
                $total_hours = isset($required_hours) && $required_hours > 0 ? $required_hours : null;
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
                // store null as DB NULL by passing PHP null, otherwise integer
                $s_total_hours = is_null($total_hours) ? null : (int)$total_hours;
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

  /* Saved-file link style: match primary site blue and underline */
  .saved-file .file-name,
  .saved-file a {
    color: #4a6ff3;
    text-decoration: underline;
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
              <option value="" disabled <?= empty($af3['first_choice']) ? 'selected' : '' ?>>1st choice*</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= (int)$o['office_id'] ?>" <?= (isset($af3['first_choice']) && (int)$af3['first_choice'] === (int)$o['office_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['office_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <select id="second_choice" name="second_choice">
              <option value="" <?= empty($af3['second_choice']) ? 'selected' : '' ?>>2nd choice (optional)</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= (int)$o['office_id'] ?>" <?= (isset($af3['second_choice']) && (int)$af3['second_choice'] === (int)$o['office_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['office_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </fieldset>

          <input type="number" name="required_hours" placeholder="Required Hours *" required min="1" value="<?= (isset($af3['required_hours']) && is_numeric($af3['required_hours']) && intval($af3['required_hours']) > 0) ? htmlspecialchars($af3['required_hours']) : '' ?>">

          <h3>UPLOAD REQUIREMENTS</h3>

          <fieldset>
            <div style="flex:1;">
              <label>1x1 Formal Picture * (JPG / PNG only)</label>
              <?php if (!empty($af3['files']['formal_pic'])): ?>
                <?php $fp = htmlspecialchars($af3['files']['formal_pic']); ?>
                <div class="saved-file" data-field="formal_pic">
                  <a href="<?= $fp ?>" target="_blank"><?= htmlspecialchars(basename($fp)) ?></a>
                  <button type="button" class="change-file" data-field="formal_pic">Change</button>
                </div>
                <input type="hidden" name="saved_formal_pic" value="1">
                <input type="file" name="formal_pic" accept=".jpg,.jpeg,.png" style="display:none;" data-field-input="formal_pic">
              <?php else: ?>
                <div class="saved-file" data-field="formal_pic" style="display:none;">
                  <span class="file-name"></span>
                  <button type="button" class="change-file" data-field="formal_pic">Change</button>
                </div>
                <input type="file" name="formal_pic" accept=".jpg,.jpeg,.png" required data-field-input="formal_pic">
              <?php endif; ?>
            </div>
            <div style="flex:1;">
              <label>Letter of Intent * (PDF only)</label>
              <?php if (!empty($af3['files']['letter_intent'])): ?>
                <?php $fp = htmlspecialchars($af3['files']['letter_intent']); ?>
                <div class="saved-file" data-field="letter_intent">
                  <a href="<?= $fp ?>" target="_blank"><?= htmlspecialchars(basename($fp)) ?></a>
                  <button type="button" class="change-file" data-field="letter_intent">Change</button>
                </div>
                <input type="hidden" name="saved_letter_intent" value="1">
                <input type="file" name="letter_intent" accept=".pdf" style="display:none;" data-field-input="letter_intent">
              <?php else: ?>
                <div class="saved-file" data-field="letter_intent" style="display:none;">
                  <span class="file-name"></span>
                  <button type="button" class="change-file" data-field="letter_intent">Change</button>
                </div>
                <input type="file" name="letter_intent" accept=".pdf" required data-field-input="letter_intent">
              <?php endif; ?>
            </div>
          </fieldset>

          <fieldset>
            <div style="flex:1;">
              <label>Resume * (PDF only)</label>
              <?php if (!empty($af3['files']['resume'])): ?>
                <?php $fp = htmlspecialchars($af3['files']['resume']); ?>
                <div class="saved-file" data-field="resume">
                  <a href="<?= $fp ?>" target="_blank"><?= htmlspecialchars(basename($fp)) ?></a>
                  <button type="button" class="change-file" data-field="resume">Change</button>
                </div>
                <input type="hidden" name="saved_resume" value="1">
                <input type="file" name="resume" accept=".pdf" style="display:none;" data-field-input="resume">
              <?php else: ?>
                <div class="saved-file" data-field="resume" style="display:none;">
                  <span class="file-name"></span>
                  <button type="button" class="change-file" data-field="resume">Change</button>
                </div>
                <input type="file" name="resume" accept=".pdf" required data-field-input="resume">
              <?php endif; ?>
            </div>
            <div style="flex:1;">
              <label>Endorsement Letter * (PDF only)</label>
              <?php if (!empty($af3['files']['endorsement'])): ?>
                <?php $fp = htmlspecialchars($af3['files']['endorsement']); ?>
                <div class="saved-file" data-field="endorsement">
                  <a href="<?= $fp ?>" target="_blank"><?= htmlspecialchars(basename($fp)) ?></a>
                  <button type="button" class="change-file" data-field="endorsement">Change</button>
                </div>
                <input type="hidden" name="saved_endorsement" value="1">
                <input type="file" name="endorsement" accept=".pdf" style="display:none;" data-field-input="endorsement">
              <?php else: ?>
                <div class="saved-file" data-field="endorsement" style="display:none;">
                  <span class="file-name"></span>
                  <button type="button" class="change-file" data-field="endorsement">Change</button>
                </div>
                <input type="file" name="endorsement" accept=".pdf" required data-field-input="endorsement">
              <?php endif; ?>
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
            <?php if (!empty($af3['files']['moa'])): ?>
              <?php $fp = htmlspecialchars($af3['files']['moa']); ?>
              <div class="saved-file" data-field="moa">
                <a href="<?= $fp ?>" target="_blank"><?= htmlspecialchars(basename($fp)) ?></a>
                <button type="button" class="change-file" data-field="moa">Change</button>
              </div>
              <input type="hidden" name="saved_moa" value="1">
              <input type="file" name="moa" accept=".pdf" style="display:none;" data-field-input="moa">
            <?php else: ?>
              <div class="saved-file" data-field="moa" style="display:none;">
                <span class="file-name"></span>
                <button type="button" class="change-file" data-field="moa">Change</button>
              </div>
              <input type="file" name="moa" accept=".pdf" data-field-input="moa">
            <?php endif; ?>
          <?php endif; ?>

          <p class="note">
            <strong>Note:</strong><br>
            • Maximum file size for each file: <span class="highlight">2MB</span>
          </p>

          <div class="form-nav">
            <!-- submit with action=prev to save AF3 to session and go back -->
            <button type="submit" name="action" value="prev" class="secondary">← Previous</button>
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

  // compatibility: flag set when Previous button clicked (for older browsers)
  let skipValidation = false;
  const prevBtn = form.querySelector('button[name="action"][value="prev"]');
  if (prevBtn) {
    prevBtn.addEventListener('click', function(){ skipValidation = true; });
  }

  form.addEventListener('submit', function(e){
    // If Previous was clicked, skip all client-side checks
    if ((e.submitter && e.submitter.name === 'action' && e.submitter.value === 'prev') || skipValidation) {
      skipValidation = false;
      return true; // allow submit to go through to server (session save handler)
    }

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
    // file validations (accept server-saved files via hidden flags)
    const fFormal = form.querySelector('input[name="formal_pic"]');
    const savedFormal = form.querySelector('input[name="saved_formal_pic"]');
    if ((!fFormal || !fFormal.files || fFormal.files.length === 0) && !savedFormal) {
      alert('Please upload your 1x1 Formal Picture (JPG/PNG).');
      e.preventDefault();
      return false;
    }
    if (fFormal && fFormal.files && fFormal.files.length > 0) {
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
    // other required PDFs (accept saved drafts)
    const pdfFields = ['letter_intent','resume','endorsement'];
    for (let i=0;i<pdfFields.length;i++){
      const name = pdfFields[i];
      const el = form.querySelector('input[name="'+name+'"]');
      const saved = form.querySelector('input[name="saved_'+name+'"]');
      if ((!el || !el.files || el.files.length === 0) && !saved) {
        alert('Please upload ' + (el ? el.previousElementSibling.textContent.replace('*','').trim() : name) + ' (PDF).');
        e.preventDefault();
        return false;
      }
      if (el && el.files && el.files.length > 0) {
        const pf = el.files[0];
        if (pf.type !== 'application/pdf' && !/\.pdf$/i.test(pf.name)) {
          alert('Only PDF is accepted for ' + (el ? el.previousElementSibling.textContent.replace('*','').trim() : name) + '.');
          e.preventDefault();
          return false;
        }
        if (pf.size > 2 * 1024 * 1024) {
          alert((el ? el.previousElementSibling.textContent.replace('*','').trim() : name) + ' must be 2MB or smaller.');
          e.preventDefault();
          return false;
        }
      }
    }

    // FINAL CONFIRMATION: warn user to review entries before submitting
    const ok = confirm('Please review your entries. Are you sure all information and uploaded files are correct? Submitting will finalize your application.');
    if (!ok) {
      e.preventDefault();
      return false;
    }

    return true;
  });
})();
</script>

<script>
// Toggle saved-file display -> show file input when user clicks Change
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.change-file').forEach(function(btn){
    btn.addEventListener('click', function(){
      var field = btn.getAttribute('data-field');
      // remove saved flag hidden input if present
      var savedEl = document.querySelector('input[name="saved_'+field+'"]');
      if (savedEl) savedEl.parentNode.removeChild(savedEl);
      // hide saved-file display
      var disp = document.querySelector('.saved-file[data-field="'+field+'"]');
      if (disp) disp.style.display = 'none';
      // show file input
      var fin = document.querySelector('input[data-field-input="'+field+'"]');
      if (fin) {
        fin.style.display = '';
        fin.focus();
      }
    });
  });
  // If user selects a file, also remove saved flag (defensive)
  document.querySelectorAll('input[type=file][data-field-input]').forEach(function(inp){
    inp.addEventListener('change', function(){
      var field = inp.getAttribute('data-field-input');
      // If a file was selected, populate the saved-file UI to match 'previous' appearance
      var disp = document.querySelector('.saved-file[data-field="'+field+'"]');
      if (disp) {
        var nameSpan = disp.querySelector('.file-name');
        var fname = '';
        try { fname = inp.files[0].name; } catch(e) { fname = ''; }
        if (fname) nameSpan.textContent = fname;
        // show as link-like blue text (client-side only until promoted on submit)
        // visual styling handled by CSS (.saved-file .file-name)
        disp.style.display = '';
      }
      // hide the file input (but file remains selected)
      inp.style.display = 'none';
      // remove any server-saved hidden flag since this is a new selection
      var savedEl = document.querySelector('input[name="saved_'+field+'"]');
      if (savedEl) savedEl.parentNode.removeChild(savedEl);
    });
  });
});
</script>

</body>
</html>