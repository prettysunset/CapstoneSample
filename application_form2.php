<?php
session_start();

// load DB early so we can read courses
require_once __DIR__ . '/conn.php';

$courses = [];
$rc = $conn->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name");
if ($rc) {
    while ($r = $rc->fetch_assoc()) $courses[] = $r;
    $rc->free();
}

// load schools for datalist suggestions and offers_5th_year flag
$schools = [];
$rs = $conn->query("SELECT school_id, school_name, offers_5th_year FROM schools ORDER BY school_name");
if ($rs) {
  while ($r = $rs->fetch_assoc()) $schools[] = $r;
  $rs->free();
}

// --- NEW: compute availability per course (true if any related office has available slots) ---
$courseAvailability = [];
if (!empty($courses)) {
    // detect capacity column used in offices table (fallback to current_limit)
    $capacityCol = null;
    $variants = ['current_limit','slot_capacity','capacity','slots','max_slots','updated_limit'];
    foreach ($variants as $v) {
        $res = $conn->query("SHOW COLUMNS FROM offices LIKE '".$conn->real_escape_string($v)."'");
        if ($res && $res->num_rows > 0) { $capacityCol = $v; break; }
        if ($res) $res->free();
    }

    $ids = array_map('intval', array_column($courses, 'course_id'));
    if (!empty($ids)) {
        $in = implode(',', $ids);
        $capExpr = $capacityCol ? "`".$conn->real_escape_string($capacityCol)."`" : "o.current_limit";
        $sql = "
            SELECT oc.course_id, o.office_id, o.office_name, {$capExpr} AS capacity,
               (SELECT COUNT(*) FROM users u WHERE u.role = 'ojt' AND u.office_name = o.office_name AND u.status IN ('approved','ongoing')) AS filled
            FROM office_courses oc
            JOIN offices o ON oc.office_id = o.office_id
            WHERE oc.course_id IN ({$in})
        ";
        $res = $conn->query($sql);
        if ($res) {
            $tmp = [];
            while ($r = $res->fetch_assoc()) {
                $cid = (int)$r['course_id'];
                $capacity = is_null($r['capacity']) ? null : (int)$r['capacity'];
                $filled = isset($r['filled']) ? (int)$r['filled'] : 0;
                $available = ($capacity === null) ? true : ($capacity - $filled > 0);
                if (!isset($tmp[$cid])) $tmp[$cid] = false;
                if ($available) $tmp[$cid] = true;
            }
            $res->free();
            // populate courseAvailability: default false for courses without offices
            foreach ($ids as $cid) $courseAvailability[$cid] = !empty($tmp[$cid]);
        }
    }
}

// Save AF2 data to session and redirect to AF3
if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // resolve course input: may be course_id (from dropdown) or free text (fallback)
        $rawCourse = $_POST['course'] ?? '';
        $courseResolved = trim($rawCourse);
        $courseId = null;
        if ($courseResolved !== '' && ctype_digit((string)$courseResolved)) {
            $cid = (int)$courseResolved;
            $s = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ? LIMIT 1");
            if ($s) {
                $s->bind_param('i', $cid);
                $s->execute();
                $cr = $s->get_result()->fetch_assoc();
                $s->close();
                if ($cr && !empty($cr['course_name'])) {
                    $courseResolved = $cr['course_name'];
                    $courseId = $cid;
                }
            }
        }
        // prepare posted AF2 values (we only persist to session when all checks pass)
        $posted_af2 = [
          'school'         => $_POST['school'] ?? '',
          'school_address' => $_POST['school_address'] ?? '',
          'course'         => $courseResolved,
          'course_id'      => $courseId,
          'year_level'     => $_POST['year_level'] ?? '',
          // school year is fixed (not provided by user)
          'school_year'    => '2025-2026',
          'semester'       => $_POST['semester'] ?? '',
          'adviser'        => $_POST['adviser'] ?? '',
          'adviser_contact'=> $_POST['adviser_contact'] ?? ''
        ];

        // server-side: if school matches a known school and it does NOT offer 5th year,
        // prevent selecting year_level = 5
        $school_offers_5th = null;
        if (trim($posted_af2['school']) !== '') {
          $s2 = $conn->prepare("SELECT offers_5th_year FROM schools WHERE LOWER(school_name) = LOWER(?) LIMIT 1");
          if ($s2) {
            $s2->bind_param('s', $posted_af2['school']);
            $s2->execute();
            $s2->store_result();
            if ($s2->num_rows > 0) {
              $s2->bind_result($school_offers_5th);
              $s2->fetch();
              $school_offers_5th = (int)$school_offers_5th;
            }
            $s2->close();
          }
        }

        // If user clicked Previous: persist posted AF2 to session and go back to AF1
        if (isset($_POST['action']) && $_POST['action'] === 'prev') {
          $_SESSION['af2'] = $posted_af2;
          header("Location: application_form1.php");
          exit;
        }

        // server-side: ensure adviser_contact does not match AF1 contact or emergency contact
        $af1 = $_SESSION['af1'] ?? [];
        $af1_contact = preg_replace('/[^0-9]/', '', $af1['contact'] ?? '');
        $af1_emg = preg_replace('/[^0-9]/', '', $af1['emg_contact'] ?? '');
        $adviser_clean = preg_replace('/[^0-9]/', '', $posted_af2['adviser_contact'] ?? '');

        if ($adviser_clean !== '' && ($adviser_clean === $af1_contact || $adviser_clean === $af1_emg)) {
          $error_adviser_conflict = 'The adviser’s contact number must be different from your personal and emergency contact numbers.';
          // preserve posted values in local $af2 for re-rendering the form below
          $af2 = $posted_af2;
        } else {
          // if matched school does not offer 5th year, block selection of 5th year
          if ($school_offers_5th !== null && $school_offers_5th === 0 && isset($posted_af2['year_level']) && (string)$posted_af2['year_level'] === '5') {
            $error_no_5th = 'The selected school does not offer 5th Year. Please choose another year level.';
            $af2 = $posted_af2;
          } else {
          // persist any selected office/course coming from hidden inputs
          if (isset($_POST['selected_office_id']) && $_POST['selected_office_id'] !== '') {
            $_SESSION['selected_office_id'] = intval($_POST['selected_office_id']);
          }
          if (isset($_POST['selected_course']) && $_POST['selected_course'] !== '') {
            $_SESSION['selected_course'] = trim($_POST['selected_course']);
          }
          // all checks passed -> persist and continue
          $_SESSION['af2'] = $posted_af2;
          header("Location: application_form3.php");
          exit;
          }
        }
}

// Pre-fill form fields if session data exists
$af2 = isset($_SESSION['af2']) ? $_SESSION['af2'] : [];
// If the user clicked a course on offices.php, use it to prefill AF2 when AF2 not provided yet
if (empty($af2['course']) && !empty($_SESSION['selected_course'])) {
  $af2['course'] = $_SESSION['selected_course'];
  // try to resolve course_id from loaded $courses
  foreach ($courses as $c) {
    if (strcasecmp(trim($c['course_name']), trim($af2['course'])) === 0) {
      $af2['course_id'] = (int)$c['course_id'];
      break;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OJT Application Form - School Information</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="styles.css">
  <style>
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

#courseAvailabilityMsg { display:none !important; }
/* layout for course + year level: 75% / 25% */
.field-course-year{display:flex;gap:12px;align-items:center}
.field-course-year .course-select{flex:3}
.field-course-year .year-select{flex:1;max-width:220px}
@media(max-width:700px){.field-course-year{flex-direction:column}.field-course-year .year-select{max-width:none}}
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
          <div class="step active"><span class="label">2. School Information</span></div>
          <div class="step"><span class="label">3. Requirements</span></div>
        </div>

        <form method="POST" novalidate id="af2Form">
          <!-- carried selections from offices.php -->
          <input type="hidden" name="selected_office_id" value="<?= isset($_SESSION['selected_office_id']) ? htmlspecialchars((string)$_SESSION['selected_office_id']) : '' ?>">
          <input type="hidden" name="selected_course" value="<?= isset($_SESSION['selected_course']) ? htmlspecialchars($_SESSION['selected_course']) : '' ?>">
          <h3>YOUR SCHOOL INFORMATION</h3>

          <input
            type="text"
            name="school"
            id="schoolInput"
            list="schoolsList"
            placeholder="School *"
            required
            value="<?= isset($af2['school']) ? htmlspecialchars($af2['school']) : '' ?>"
          >
          <datalist id="schoolsList"></datalist>

          <input type="text" name="school_address" placeholder="School Address *" required value="<?= isset($af2['school_address']) ? htmlspecialchars($af2['school_address']) : '' ?>">

          <fieldset class="field-course-year">
              <?php $locked_course = !empty($_SESSION['selected_course']); ?>
              <?php if ($locked_course): 
                  $locked_name = htmlspecialchars($_SESSION['selected_course']);
                  $locked_id = isset($af2['course_id']) ? (int)$af2['course_id'] : '';
              ?>
                <input type="hidden" name="course" value="<?= $locked_id !== '' ? $locked_id : $locked_name ?>">
                <div style="padding:10px;border-radius:8px;background:#f8fafc;border:1px solid #e6eef8;"><?= $locked_name ?></div>
              <?php else: ?>
                <select name="course" id="courseSelect" class="course-select" required>
                  <option value="" disabled <?= !isset($af2['course_id']) ? 'selected' : '' ?>>Select Course *</option>
                  <?php foreach ($courses as $c): 
                    $sel = '';
                    if (isset($af2['course_id']) && $af2['course_id'] !== null) {
                      if ((int)$af2['course_id'] === (int)$c['course_id']) $sel = 'selected';
                    } else {
                      if (isset($af2['course']) && $af2['course'] !== '' && $af2['course'] === $c['course_name']) $sel = 'selected';
                    }
                  ?>
                    <option value="<?= (int)$c['course_id'] ?>" <?= $sel ?>><?= htmlspecialchars($c['course_name'] . ($c['course_code'] ? " ({$c['course_code']})" : '')) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            <div id="courseAvailabilityMsg" style="color:#b91c1c;display:none;margin-top:6px;font-size:0.95rem;">No available office for the selected course.</div>
            <select name="year_level" class="year-select" required>
              <option value="" disabled <?= !isset($af2['year_level']) ? 'selected' : '' ?>>Year Level *</option>
              <option value="3" <?= (isset($af2['year_level']) && $af2['year_level'] == '3') ? 'selected' : '' ?>>2nd Year</option>
              <option value="3" <?= (isset($af2['year_level']) && $af2['year_level'] == '3') ? 'selected' : '' ?>>3rd Year</option>
              <option value="4" <?= (isset($af2['year_level']) && $af2['year_level'] == '4') ? 'selected' : '' ?>>4th Year</option>
              <option value="5" <?= (isset($af2['year_level']) && $af2['year_level'] == '5') ? 'selected' : '' ?>>5th Year</option>
            </select>
          </fieldset>

          <!-- Semester input removed per request -->

          <fieldset>
            <input type="text" name="adviser" placeholder="OJT Adviser *" required value="<?= isset($af2['adviser']) ? htmlspecialchars($af2['adviser']) : '' ?>">
            <input type="text" id="adviser_contact" name="adviser_contact" placeholder="Contact Number *" required maxlength="11" pattern="[0-9]{11}" value="<?= isset($af2['adviser_contact']) ? htmlspecialchars($af2['adviser_contact']) : '' ?>">
          </fieldset>

          <!-- add this above the form -->
<div id="courseWarning" style="display:none;padding:10px;border-radius:8px;background:#fff4e5;color:#8a5a00;margin-bottom:12px;border:1px solid #ffd7a8;font-weight:600">
  No available office for the selected course.
</div>

          <div class="form-nav">
            <button type="submit" name="action" value="prev" class="secondary" id="prevBtn">← Previous</button>
            <button type="submit" id="nextBtn">Next →</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
window.addEventListener('load', () => { document.body.style.opacity = 1; });

// AF1 contacts (clean digits) for client-side validation
const AF1_CONTACT = <?= json_encode(preg_replace('/[^0-9]/', '', (isset($_SESSION['af1']['contact']) ? $_SESSION['af1']['contact'] : ''))) ?> || '';
const AF1_EMG_CONTACT = <?= json_encode(preg_replace('/[^0-9]/', '', (isset($_SESSION['af1']['emg_contact']) ? $_SESSION['af1']['emg_contact'] : ''))) ?> || '';

(function(){
  // server-provided schools list and offers map
  const schoolsData = <?= json_encode($schools, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?> || [];
  const schools = schoolsData.map(s => s.school_name);
  const schoolOffers = {};
  schoolsData.forEach(s => { schoolOffers[String(s.school_name).toLowerCase()] = Number(s.offers_5th_year); });

  const input = document.getElementById('schoolInput');
  const datalist = document.getElementById('schoolsList');

  // expose function for other scripts / server-triggered calls
  window.update5thYearVisibility = function(schoolName) {
    const ysel = document.querySelector('.year-select');
    if (!ysel) return;
    const opt5 = Array.from(ysel.options).find(o => String(o.value) === '5');
    const key = (schoolName || '').trim().toLowerCase();
    const offers = key && (schoolOffers[key] !== undefined) ? schoolOffers[key] : null;
    if (offers === 0) {
      if (opt5) { opt5.hidden = true; opt5.disabled = true; }
      if (ysel.value === '5') {
        ysel.value = '';
      }
    } else {
      if (opt5) { opt5.hidden = false; opt5.disabled = false; }
    }
  };

  function populateList(filter) {
    datalist.innerHTML = '';
    const q = (filter || '').trim().toLowerCase();
    const matches = q === '' ? schools.slice(0,50) : schools.filter(s => s.toLowerCase().includes(q)).slice(0,50);
    matches.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s;
      datalist.appendChild(opt);
    });
  }

  function clearList(){ datalist.innerHTML = ''; }
  let openedByArrow = false;

  if (!input) return;
  input.addEventListener('mousedown', function(e){
    const rect = input.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    if (clickX >= rect.width - 28) {
      openedByArrow = true;
      populateList('');
    } else {
      openedByArrow = false;
      clearList();
    }
  });

  input.addEventListener('focus', function(){
    if (openedByArrow) populateList('');
    else clearList();
  });

  input.addEventListener('input', function(e){
    const q = e.target.value || '';
    if (q.trim() === '') {
      if (openedByArrow) populateList('');
      else clearList();
    } else {
      populateList(q);
      // adjust 5th Year visibility as user types
      window.update5thYearVisibility(q);
      openedByArrow = false;
    }
  });

  input.addEventListener('blur', function(){
    setTimeout(clearList, 120);
    // check the typed school against known schools
    window.update5thYearVisibility(input.value || '');
    openedByArrow = false;
  });

  if (input.value && input.value.trim() !== '') {
    populateList(input.value);
    window.update5thYearVisibility(input.value || '');
  }

  // -------------------------
  // Draft autosave (localStorage) for AF2 so answers persist when navigating back
  // -------------------------
  const DRAFT_KEY = 'af2_draft';
  const formEl = document.getElementById('af2Form');
  function saveDraft() {
    if (!formEl) return;
    const data = {};
    Array.from(formEl.elements).forEach(el => {
      if (!el.name) return;
      if (el.type === 'checkbox' || el.type === 'radio') data[el.name] = el.checked;
      else data[el.name] = el.value;
    });
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(data)); } catch (e) { /* ignore storage errors */ }
  }

  // populate from draft if server didn't provide values
  try {
    const draftRaw = localStorage.getItem(DRAFT_KEY);
    if (draftRaw) {
      const draft = JSON.parse(draftRaw);
      // only set fields that are currently empty so we don't override server-side values
      Object.keys(draft).forEach(k => {
        try {
          const el = formEl.elements.namedItem(k);
          if (!el) return;
          // for selects and inputs
          if ((el.type === 'select-one' || el.tagName === 'SELECT') && (!el.value || String(el.value).trim() === '')) el.value = draft[k];
          else if ((el.type === 'text' || el.type === 'tel' || el.type === 'email' || el.type === 'hidden') && (!el.value || String(el.value).trim() === '')) el.value = draft[k];
          else if (el.type === 'checkbox' || el.type === 'radio') el.checked = !!draft[k];
        } catch (e) { /* ignore per-field errors */ }
      });
    }
  } catch (e) { /* ignore parse errors */ }

  // attach listeners to save on change/input
  if (formEl) {
    Array.from(formEl.elements).forEach(el => {
      el.addEventListener('input', saveDraft, {passive:true});
      el.addEventListener('change', saveDraft, {passive:true});
    });
    // clear draft on successful submit (we let server redirect)
    formEl.addEventListener('submit', function(){ try { localStorage.removeItem(DRAFT_KEY); } catch(e){} });
  }

  // client-side required validation before submit (ensure required fields first)
  const form = document.getElementById('af2Form');
  let skipValidation = false;
  const prevBtn = document.querySelector('button[name="action"][value="prev"]');
  if (prevBtn) prevBtn.addEventListener('click', function(){ skipValidation = true; });
      if (form) {
    form.addEventListener('submit', function(e){
      if (skipValidation) { skipValidation = false; return true; }
      const reqs = form.querySelectorAll('[required]');
      for (let i=0;i<reqs.length;i++){
        const el = reqs[i];
        const val = (el.value || '').toString().trim();
        if (val === '') {
          alert('Please complete all required fields.');
          el.focus();
          e.preventDefault();
          return false;
        }
      }
      // adviser contact numeric length check
      const adv = document.getElementById('adviser_contact');
      if (adv) {
        if (adv.value.replace(/\D/g,'').length !== 11) {
          alert('Adviser contact must be 11 digits.');
          adv.focus();
          e.preventDefault();
          return false;
        }
        // ensure adviser_contact does not match AF1 contact or AF1 emergency contact
        const advDigits = adv.value.replace(/\D/g,'');
        if (advDigits !== '' && (advDigits === AF1_CONTACT || advDigits === AF1_EMG_CONTACT)) {
          alert('The adviser’s contact number must be different from your personal and emergency contact numbers.');
          adv.focus();
          e.preventDefault();
          return false;
        }
      }
    });
  }
})();
</script>
<script>
// server-provided availability map
const courseAvailability = <?= json_encode($courseAvailability, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?> || {};

(function(){
  const courseSelect = document.getElementById('courseSelect');
  const warning = document.getElementById('courseWarning');
  const nextBtn = document.getElementById('nextBtn');
  const form = document.getElementById('af2Form');

  function updateAvailabilityUI() {
    const val = courseSelect && courseSelect.value ? String(courseSelect.value) : '';
    if (!val) {
      if (warning) warning.style.display = 'none';
      if (nextBtn) nextBtn.disabled = false;
      if (courseSelect) courseSelect.setCustomValidity('');
      return;
    }
    const ok = !!courseAvailability[val];
    if (!ok) {
      if (warning) {
        warning.textContent = 'No available office for the selected course.';
        warning.style.display = 'block';
      }
      if (nextBtn) nextBtn.disabled = true;
      if (courseSelect) courseSelect.setCustomValidity('No available office for selected course');
    } else {
      if (warning) warning.style.display = 'none';
      if (nextBtn) nextBtn.disabled = false;
      if (courseSelect) courseSelect.setCustomValidity('');
    }
  }

  if (courseSelect) {
    courseSelect.addEventListener('change', updateAvailabilityUI);
    // initial check if preselected
    updateAvailabilityUI();
  }

  // final safety on submit: re-check availability before allowing submit
  if (form) {
    form.addEventListener('submit', function(e){
      const val = courseSelect && courseSelect.value ? String(courseSelect.value) : '';
      if (val && !courseAvailability[val]) {
        // ensure top banner visible and keep user on page
        if (warning) { warning.textContent = 'No available office for the selected course.'; warning.style.display = 'block'; }
        e.preventDefault();
        courseSelect.focus();
        return false;
      }
      return true;
    }, {passive:false});
  }
})();
</script>
<?php if (!empty($error_adviser_conflict)): ?>
  <script>window.addEventListener('load',function(){ alert(<?= json_encode($error_adviser_conflict) ?>); });</script>
<?php endif; ?>
<?php if (!empty($error_no_5th)): ?>
  <script>window.addEventListener('load',function(){ alert(<?= json_encode($error_no_5th) ?>); try{ window.update5thYearVisibility(document.getElementById('schoolInput').value||''); }catch(e){} });</script>
<?php endif; ?>
</body>
</html>
