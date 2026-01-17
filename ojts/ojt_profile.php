<?php
session_start();
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$display_name = 'User Name';
$display_role = 'Role';
$initials = 'UN';
$student_college = $student_course = $student_year = '';
$app_picture = ''; // NEW: path to picture from application (relative)
$app_picture_url = ''; // ensure defined
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // load user
    // include status so we can display the user's status from `users`
    $u = $conn->prepare("SELECT username, first_name, middle_name, last_name, role, office_name, status FROM users WHERE user_id = ? LIMIT 1");
    $u->bind_param("i", $user_id);
    $u->execute();
    $ur = $u->get_result()->fetch_assoc();
    $u->close();

    if ($ur) {
        $nameParts = array_filter([($ur['first_name'] ?? ''), ($ur['middle_name'] ?? ''), ($ur['last_name'] ?? '')]);
        $display_name = trim(implode(' ', $nameParts)) ?: ($ur['username'] ?? $display_name);
        if (!empty($ur['office_name'])) {
            $display_role = 'OJT - ' . preg_replace('/\s+Office\s*$/i', '', trim($ur['office_name']));
        } else {
            $display_role = !empty($ur['role']) ? ucwords(str_replace('_',' ', $ur['role'])) : $display_role;
        }
        // define $office_display for use in the status line
        $office_display = preg_replace('/\s+Office\s*$/i', '', trim($ur['office_name'] ?? ''));
    }

    // prefer student record linked to user account for profile fields (include student_id)
    $s = $conn->prepare("SELECT student_id, first_name, last_name, college, course, year_level, email FROM students WHERE user_id = ? LIMIT 1");
    $s->bind_param("i", $user_id);
    $s->execute();
    $sr = $s->get_result()->fetch_assoc();
    $s->close();

    if ($sr) {
        $student_id = (int)($sr['student_id'] ?? 0);
        $display_name = trim(($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? '')) ?: $display_name;
        $student_college = $sr['college'] ?? '';
        $student_course = $sr['course'] ?? '';
        $student_year = $sr['year_level'] ?? '';
        // get latest application picture for this student (if any)
        if ($student_id) {
            $ap = $conn->prepare("SELECT picture FROM ojt_applications WHERE student_id = ? AND COALESCE(picture,'') <> '' ORDER BY date_submitted DESC, application_id DESC LIMIT 1");
            $ap->bind_param("i", $student_id);
            $ap->execute();
            $apr = $ap->get_result()->fetch_assoc();
            $ap->close();
            if (!empty($apr['picture'])) {
                $raw = $apr['picture'];
                // candidate URL/path patterns to try (prefer project-root relative)
                $candidates = [
                  $raw,
                  ltrim($raw, "/\\"),
                  'uploads/' . basename($raw),
                  'upload/' . basename($raw),
                  'ojts/' . ltrim($raw, "/\\"),
                ];
                $found = '';
                foreach ($candidates as $c) {
                    $filePath = __DIR__ . '/../' . ltrim($c, "/\\");
                    if (is_file($filePath)) { $found = $c; break; }
                }
                if ($found !== '') {
                    // store path relative to this PHP file for browser src
                    $app_picture = $found;
                    $app_picture_url = '../' . ltrim($app_picture, "/\\");
                } else {
                    // log for debugging — do not expose to UI
                    error_log("ojt_profile: picture file not found for student_id {$student_id}. db='{$raw}' tried: ".implode(',', $candidates));
                    $app_picture = '';
                    $app_picture_url = '';
                }
            }
        }
        // initials for avatar (will be used only when no picture)
        $initials = '';
        foreach (explode(' ', $display_name) as $p) if ($p !== '') $initials .= strtoupper($p[0]);
        $initials = substr($initials ?: 'UN', 0, 2);
    } // <-- close if ($sr)

    // Handle weekly journal upload (POST from this page)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_journal') {
        $journal_upload_error = '';
        if (empty($student_id)) {
            $journal_upload_error = 'Student record not found.';
        } else {
            $week = trim((string)($_POST['week_coverage'] ?? ''));
            // accept optional date range from the form and include it in stored week_coverage
            $week_from = trim((string)($_POST['week_from'] ?? ''));
            $week_to = trim((string)($_POST['week_to'] ?? ''));
            if ($week === '') {
                $journal_upload_error = 'Please enter week coverage.';
            } elseif (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
                $journal_upload_error = 'Please attach a file.';
            } else {
                $f = $_FILES['attachment'];
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $journal_upload_error = 'File upload error.';
                } else {
                    $max = 2 * 1024 * 1024;
                    if ($f['size'] > $max) {
                        $journal_upload_error = 'File too large. Maximum 2 MB.';
                    } else {
                        // only allow PDF and DOCX
                        $allowed = ['pdf','docx'];
                        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed, true)) {
                            $journal_upload_error = 'Unsupported file type. Allowed: DOCX, PDF.';
                        } else {
                            $uploadDir = __DIR__ . '/../uploads/journals/';
                            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                            $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($f['name']));
                            $filename = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safe;
                            $target = $uploadDir . $filename;
                            if (move_uploaded_file($f['tmp_name'], $target)) {
                                $relpath = 'uploads/journals/' . $filename;
                                // if user provided a valid Y-m-d range, store it in ISO form inside parentheses for reliable parsing later
                                $week_to_store = $week; // default
                                $from_date = null;
                                $to_date = null;
                                if ($week_from !== '' && $week_to !== '') {
                                    // basic validation: dates must be Y-m-d
                                    $okFrom = DateTime::createFromFormat('Y-m-d', $week_from);
                                    $okTo = DateTime::createFromFormat('Y-m-d', $week_to);
                                    if ($okFrom && $okTo) {
                                        // ensure both dates are weekdays (Mon-Fri)
                                        $dowFrom = (int)$okFrom->format('N'); // 1 (Mon) .. 7 (Sun)
                                        $dowTo = (int)$okTo->format('N');
                                        if ($dowFrom <= 5 && $dowTo <= 5) {
                                            $from_date = $okFrom->format('Y-m-d');
                                            $to_date = $okTo->format('Y-m-d');
                                            $week_to_store = $week . ' (' . $from_date . '|' . $to_date . ')';
                                        } else {
                                            $journal_upload_error = 'Please choose weekdays only (Monday to Friday) for the From/To dates.';
                                        }
                                    }
                                }
                                $today = date('Y-m-d');
                                if ($from_date !== null && $to_date !== null) {
                                    $stmt = $conn->prepare("INSERT INTO weekly_journal (user_id, week_coverage, date_uploaded, attachment, from_date, to_date) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt->bind_param('isssss', $student_id, $week_to_store, $today, $relpath, $from_date, $to_date);
                                } else {
                                    $stmt = $conn->prepare("INSERT INTO weekly_journal (user_id, week_coverage, date_uploaded, attachment) VALUES (?, ?, ?, ?)");
                                    $stmt->bind_param('isss', $student_id, $week_to_store, $today, $relpath);
                                }
                                if ($stmt->execute()) {
                                    $stmt->close();
                                    // redirect to avoid form resubmission
                                    // include an uploaded=1 flag so the client-side will only activate the journals tab
                                    $redirect = $_SERVER['REQUEST_URI'];
                                    if (strpos($redirect, 'uploaded=1') === false) {
                                        $sep = (strpos($redirect, '?') === false) ? '?' : '&';
                                        $redirect .= $sep . 'uploaded=1';
                                    }
                                    $redirect .= '#tab-journals';
                                    header('Location: ' . $redirect);
                                    exit();
                                } else {
                                    $journal_upload_error = 'Database error while saving journal.';
                                    $stmt->close();
                                }
                            } else {
                                $journal_upload_error = 'Unable to move uploaded file.';
                            }
                        }
                    }
                }
            }
        }
    }
} // <-- close if ($user_id)
?>
<html>
<head>
    <title>OJT Profile</title>
    <link rel="stylesheet" type="text/css" href="stylesforojt.css">
    <script src="../js/ojt_profile.js"></script>
    <style>
        .sidebar {
        width: 220px;
        background-color: #2f3459;
        height: 100vh;
        color: white;
        position: fixed;
        padding-top: 30px;
    }
    .sidebar h3 {
        text-align: center;
        margin-bottom: 5px;
    }
    .sidebar p {
        text-align: center;
        font-size: 14px;
        margin-top: 0;
    }
    .sidebar a {
        display: block;
        padding: 10px 20px;
        margin: 10px;
        color: black;
        background: white;
        border-radius: 20px;
        text-decoration: none;
    }
    .sidebar a.active {
        background-color: #b3b7d6;
    }

    /* ADDED: make main-area fonts black, but keep sidebar text and OJT name color unchanged */
    .main-content, .main-content * {
        color: #000 !important;
    }
    /* keep OJT main name color as before */
    .main-content h1 {
        color: #2f3459 !important;
    }
    </style>
</head>
<body>
    <!-- top-right outline icons: notifications, settings, logout -->
    <div id="top-icons" style="position:fixed;top:18px;right:28px;display:flex;gap:14px;z-index:1200;">
        <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </a>
        <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
        </a>
        <a id="top-logout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>

         <div class="sidebar">
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="text-align:center; padding: 8px 12px 20px;">
          <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
  <?php if (!empty($app_picture_url)): ?>
    <img src="<?php echo htmlspecialchars($app_picture_url); ?>" alt="Avatar" style="width:76px;height:76px;object-fit:cover;display:block;">
  <?php else: ?>
    <?php echo htmlspecialchars($initials); ?>
  <?php endif; ?>
</div>
<h3 style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($display_name); ?></h3>
<p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($display_role); ?></p>
        </div>

      
        <nav style="padding: 6px 10px 12px;">
          <a href="ojt_profile.php" class="active" aria-current="page"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);">
             <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
               <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
               <circle cx="12" cy="7" r="4"></circle>
             </svg>
             <span style="font-weight:600;">Profile</span>
           </a>

            
            </a>

            <a href="ojt_reports.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <rect x="3" y="3" width="4" height="18"></rect>
              <rect x="10" y="8" width="4" height="13"></rect>
              <rect x="17" y="13" width="4" height="8"></rect>
            </svg>
            <span>Reports</span>
          </a>
        </nav>
      </div>

      <div style="padding:14px 12px 26px;">
        <!-- sidebar logout removed — use top-right logout icon instead -->
      </div>
    </div>
  </div>

    <div class="bottom-title">OJT-MS</div>
</div>
    <div class="main-content" style="position:fixed; left:260px; top:0; bottom:0; padding:32px 32px 32px 0; display:flex; flex-direction:column; align-items:flex-start; gap:20px; width:calc(100% - 260px); background:#f6f7fb; overflow:auto; font-size:18px;">
        <div style="width:100%; max-width:none; align-self:flex-start; display:flex; gap:24px; align-items:center; background:#fff; padding:24px; border-radius:12px; box-shadow:0 6px 20px rgba(47,52,89,0.06);">
            <div style="width:110px;height:110px;border-radius:50%;overflow:hidden;background:#2f3459;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:36px;">
              <?php if (!empty($app_picture_url)): ?>
                <img src="<?php echo htmlspecialchars($app_picture_url); ?>" alt="Profile picture" style="width:110px;height:110px;object-fit:cover;display:block;">
              <?php else: ?>
                <?php echo htmlspecialchars($initials); ?>
              <?php endif; ?>
            </div>
            <div style="flex:0; white-space:nowrap;">
                <h1 style="margin:0 0 6px 0; font-size:26px; color:#2f3459;"><?php echo htmlspecialchars($display_name); ?></h1>
                <p style="margin:0 0 8px 0; color:#6b6f8b; font-size:16px;">
                    <?php
                    // Fetch the status from the `users` table and format it in title case
                    $status_display = ucwords(strtolower($ur['status'] ?? 'active'));
                    ?>
                    <?php echo htmlspecialchars($status_display . ' OJT • ' . $office_display); ?>
                </p>
                <?php
                  // prefer status from users table (title-cased). fallback to 'active'.
                  $status_display = ucwords(strtolower($ur['status'] ?? 'active'));
                ?>
                <div style="display:flex; gap:12px; align-items:center; margin-top:6px;">
                    <button style="padding:12px 16px; border-radius:10px; border:1px solid #e6e9f2; background:transparent; color:#2f3459; cursor:pointer; font-size:15px;">Edit Profile</button>
                </div>
            </div>
        </div>
            <div style="width:100%; max-width:none; display:grid; grid-template-columns:1fr; gap:20px;">
                    <div style="background:#fff; padding:20px; border-radius:12px; box-shadow:0 6px 20px rgba(47,52,89,0.04);">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                            <style>
                              .tab-btn {
                                padding:10px 14px;
                                border-radius:8px;
                                background:transparent !important;
                                color:#2f3459 !important;
                                border:1px solid #e6e9f2 !important;
                                cursor:pointer;
                                font-size:15px;
                                text-decoration:none;
                                line-height:1;
                              }
                              .tab-btn.active {
                                background:transparent !important;
                                color:#2f3459 !important;
                                border:none !important;
                                text-decoration:underline !important;
                                text-underline-offset:6px;
                                font-weight:600;
                              }
                              .tab-btn:focus { outline: none; box-shadow: none; }
                            </style>

                            <!-- Tabs -->
                            <div role="tablist" aria-label="Profile tabs" style="display:flex; gap:8px; flex-wrap:wrap;">
                              <button id="tabbtn-info" type="button" class="tab-btn active" data-tab="tab-info" aria-selected="true" role="tab" aria-controls="tab-info">Information</button>
                              <button id="tabbtn-journals" type="button" class="tab-btn" data-tab="tab-journals" aria-selected="false" role="tab" aria-controls="tab-journals">Weekly Journals</button>
                              <button id="tabbtn-attachments" type="button" class="tab-btn" data-tab="tab-attachments" aria-selected="false" role="tab" aria-controls="tab-attachments">Attachments</button>
                              <button id="tabbtn-eval" type="button" class="tab-btn" data-tab="tab-eval" aria-selected="false" role="tab" aria-controls="tab-eval">Evaluation</button>
                            </div>

                            <script>
                            (function(){
                              // Delegated click handler so tabs work regardless of DOM order.
                              document.addEventListener('click', function(e){
                              var btn = e.target.closest('.tab-btn');
                              if (!btn || !btn.dataset.tab) return;
                              e.preventDefault();
                              var tabs = Array.from(document.querySelectorAll('.tab-btn'));
                              var panels = Array.from(document.querySelectorAll('.tab-panel'));
                              tabs.forEach(function(t){
                                var active = (t === btn);
                                t.classList.toggle('active', active);
                                t.setAttribute('aria-selected', active ? 'true' : 'false');
                                t.style.background = active ? '#2f3459' : 'transparent';
                                t.style.color = active ? '#fff' : '#2f3459';
                                t.style.border = active ? '0' : '1px solid #e6e9f2';
                              });
                              panels.forEach(function(p){
                                p.style.display = (p.id === btn.dataset.tab) ? 'block' : 'none';
                              });
                              btn.focus();
                              });
                            })();
                            </script>

                            <!-- Tab panels -->
                            <div style="border-radius:8px; padding:14px; background:#fbfcff; min-height:220px;">
                            <section id="tab-info" class="tab-panel active" style="display:block;">
                                    <h4 style="margin:0 0 10px 0; color:#2f3459; font-size:20px;">Information</h4>

                                    <?php
                                    // load detailed student info (fallbacks if missing)
                                    $sinfo = [
                                      'age' => '', 'birthday' => '', 'birthday_fmt' => '', 'address' => '', 'phone' => '', 'email' => '',
                                      'college' => '', 'course' => '', 'year_level' => '', 'school_address' => '',
                                      'ojt_adviser' => '', 'adviser_contact' => '',
                                      'emg_name' => '', 'emg_relation' => '', 'emg_contact' => ''
                                    ];
                                    // ensure $student_id exists: prefer the student linked to the current user
                                    if (empty($student_id) && !empty($_SESSION['user_id'])) {
                                        $tmp = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
                                        $tmp->bind_param('i', $_SESSION['user_id']);
                                        $tmp->execute();
                                        $tmpR = $tmp->get_result()->fetch_assoc();
                                        $tmp->close();
                                        if ($tmpR) $student_id = (int)$tmpR['student_id'];
                                    }

                                    $hours_rendered = 0;
                                    $total_required = 500;
                                    $orientation = '';
                                    $assignedOffice = '';
                                    $office_head_name = '';
                                    $office_head_email = '';

                                    if (!empty($student_id)) {
                                        $qs = $conn->prepare("SELECT birthday,address,contact_number,email,college,course,year_level,school_address,ojt_adviser,adviser_contact,emergency_name,emergency_relation,emergency_contact,hours_rendered,total_hours_required FROM students WHERE student_id = ? LIMIT 1");
                                        $qs->bind_param('i', $student_id);
                                        $qs->execute();
                                        $srow = $qs->get_result()->fetch_assoc();
                                        $qs->close();
                                        if ($srow) {
                                            $sinfo['birthday'] = $srow['birthday'];
                                            if (!empty($srow['birthday'])) {
                                                $dob = DateTime::createFromFormat('Y-m-d', $srow['birthday']);
                                                if ($dob) {
                                                    $today = new DateTime('now');
                                                    $sinfo['age'] = $dob->diff($today)->y;
                                                    $sinfo['birthday_fmt'] = $dob->format('m/d/Y');
                                                }
                                            }
                                            $sinfo['address'] = $srow['address'] ?? '';
                                            $sinfo['phone'] = $srow['contact_number'] ?? '';
                                            $sinfo['email'] = $srow['email'] ?? '';
                                            $sinfo['college'] = $srow['college'] ?? '';
                                            $sinfo['course'] = $srow['course'] ?? '';
                                            $sinfo['year_level'] = $srow['year_level'] ?? '';
                                            $sinfo['school_address'] = $srow['school_address'] ?? '';
                                            $sinfo['ojt_adviser'] = $srow['ojt_adviser'] ?? '';
                                            $sinfo['adviser_contact'] = $srow['adviser_contact'] ?? '';
                                            $sinfo['emg_name'] = $srow['emergency_name'] ?? '';
                                            $sinfo['emg_relation'] = $srow['emergency_relation'] ?? '';
                                            $sinfo['emg_contact'] = $srow['emergency_contact'] ?? '';
                                            $hours_rendered = (float)($srow['hours_rendered'] ?? 0);
                                            $total_required = (float)($srow['total_hours_required'] ?? 500);
                                        }

                                        // latest application (remarks for orientation/assigned office) — any status
                                        $qa = $conn->prepare("SELECT remarks FROM ojt_applications WHERE student_id = ? ORDER BY date_updated DESC, application_id DESC LIMIT 1");
                                        $qa->bind_param('i', $student_id);
                                        $qa->execute();
                                        $arow = $qa->get_result()->fetch_assoc();
                                        $qa->close();
                                        if ($arow && !empty($arow['remarks'])) {
                                            $r = $arow['remarks'];
                                            if (preg_match('/Orientation\/Start:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $r, $m)) {
                                                $orientation = $m[1];
                                            } elseif (preg_match('/Orientation\/Start:\s*([^|]+)/i', $r, $m2)) {
                                                $orientation = trim($m2[1]);
                                            }
                                            if (preg_match('/Assigned Office:\s*([^|]+)/i', $r, $m3)) $assignedOffice = trim($m3[1]);
                                        }

                                        // resolve office head name + email robustly (prefer users.email)
                                        if (!empty($assignedOffice)) {
                                            // normalize assignedOffice
                                            $ao = preg_replace('/\bOffice\b/i', '', $assignedOffice);
                                            $ao = trim($ao);
                                            $aoLower = strtolower($ao);
                                            $like = '%' . $aoLower . '%';

                                            // 1) try users table (office_head) with users.email
                                            $oh = $conn->prepare("SELECT first_name,last_name,user_id,office_name,COALESCE(email,'') AS email FROM users WHERE role = 'office_head' AND (LOWER(office_name) LIKE ? OR LOWER(office_name)=?) LIMIT 1");
                                            $oh->bind_param('ss', $like, $aoLower);
                                            $oh->execute();
                                            $ohr = $oh->get_result()->fetch_assoc();
                                            $oh->close();
                                            if ($ohr) {
                                                $office_head_name = trim(($ohr['first_name'] ?? '') . ' ' . ($ohr['last_name'] ?? ''));
                                                $office_head_email = !empty($ohr['email']) ? $ohr['email'] : '';
                                            }

                                            // 2) try offices -> office_heads_backup for email
                                            if (empty($office_head_email)) {
                                                $of = $conn->prepare("SELECT office_id FROM offices WHERE LOWER(office_name) LIKE ? OR LOWER(office_name)=? LIMIT 1");
                                                $of->bind_param('ss', $like, $aoLower);
                                                $of->execute();
                                                $ofr = $of->get_result()->fetch_assoc();
                                                $of->close();
                                                if ($ofr) {
                                                    $ohb = $conn->prepare("SELECT full_name, contact_number, email FROM office_heads_backup WHERE office_id = ? LIMIT 1");
                                                    $ohb->bind_param('i', $ofr['office_id']);
                                                    $ohb->execute();
                                                    $ohbr = $ohb->get_result()->fetch_assoc();
                                                    $ohb->close();
                                                    if ($ohbr) {
                                                        if (empty($office_head_name) && !empty($ohbr['full_name'])) $office_head_name = $ohbr['full_name'];
                                                        if (!empty($ohbr['email'])) $office_head_email = $ohbr['email'];
                                                    }
                                                }
                                            }

                                            // 3) fallback: any office_head user
                                            if (empty($office_head_email)) {
                                                $ohf = $conn->query("SELECT first_name,last_name,user_id,COALESCE(email,'') AS email FROM users WHERE role='office_head' LIMIT 1");
                                                if ($ohf && ($r = $ohf->fetch_assoc())) {
                                                    if (empty($office_head_name)) $office_head_name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                                                    if (!empty($r['email'])) {
                                                        $office_head_email = $r['email'];
                                                    } else {
                                                        $ohc2 = $conn->prepare("SELECT email FROM office_heads_backup WHERE user_id = ? LIMIT 1");
                                                        $ohc2->bind_param('i', $r['user_id']);
                                                        $ohc2->execute();
                                                        $ohcr2 = $ohc2->get_result()->fetch_assoc();
                                                        $ohc2->close();
                                                        if ($ohcr2 && !empty($ohcr2['email'])) $office_head_email = $ohcr2['email'];
                                                    }
                                                }
                                            }

                                            if (empty($office_head_email)) {
                                                error_log("ojt_profile: office head email not found for AssignedOffice='{$assignedOffice}' resolvedName='{$office_head_name}'");
                                            }
                                        }
                                    }

                                    // compute progress from DTR (hours + minutes/60) and expected end date
                                    $hours_rendered = 0.0;
                                    $percent = 0;
                                    $expected_end_display = '-';
                                    $total_required = $total_required ?? 500;

                                    // determine DTR id: prefer users.user_id (dtr.student_id stores users.user_id) then fallback to students.student_id
                                    $dtrUserId = null;
                                    if (!empty($user_id)) {
                                        $dtrUserId = (int)$user_id;
                                    } elseif (!empty($student_id)) {
                                        $dtrUserId = (int)$student_id;
                                    }

                                    if (!empty($dtrUserId)) {
                                        $qSum = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ?");
                                        if ($qSum) {
                                            $qSum->bind_param('i', $dtrUserId);
                                            $qSum->execute();
                                            $trSum = $qSum->get_result()->fetch_assoc();
                                            $qSum->close();
                                            $hours_rendered = isset($trSum['total']) ? (float)$trSum['total'] : 0.0;
                                        } else {
                                            error_log('ojt_profile: failed prepare SUM(dtr): ' . $conn->error);
                                        }
                                    }

                                    // compute percent (cap at 100)
                                    if ($total_required > 0) {
                                        $pct = ($hours_rendered / (float)$total_required) * 100.0;
                                        $percent = (int) round(min(100, max(0, $pct)));
                                    } else {
                                        $percent = 0;
                                    }
                                    $remaining = max(0, $total_required - $hours_rendered);

                                    // Default displays
                                    $orientation_display = '-';
                                    $expected_end_display = '-';

                                    // Behavior:
                                    // - users.status = 'approved' => leave both fields as '-'
                                    // - users.status = 'ongoing'  => Date Started = earliest dtr.log_date (dtr.student_id stores users.user_id)
                                    //                                Estimated End = start + required weekdays (Mon-Fri) assuming 8 hrs/day
                                    // - otherwise => keep existing fallback: completed uses DTR first/last; else use orientation estimate
                                    $user_status = strtolower($ur['status'] ?? '');

                                    if ($user_status === 'approved') {
                                        // intentionally leave dashes
                                        $orientation_display = '-';
                                        $expected_end_display = '-';
                                    } elseif ($user_status === 'ongoing') {
                                        // find earliest DTR log_date for this user (dtr.student_id references users.user_id)
                                        $dtrFirst = null;
                                        $dtrUserId = !empty($user_id) ? (int)$user_id : null;
                                        if (!$dtrUserId && !empty($student_id)) $dtrUserId = (int)$student_id;
                                        if (!empty($dtrUserId)) {
                                            $qf = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND COALESCE(log_date,'') <> '' ORDER BY log_date ASC LIMIT 1");
                                            if ($qf) {
                                                $qf->bind_param('i', $dtrUserId);
                                                $qf->execute();
                                                $r1 = $qf->get_result()->fetch_assoc();
                                                $qf->close();
                                                if ($r1 && !empty($r1['log_date'])) $dtrFirst = $r1['log_date'];
                                            }
                                        }

                                        if (!empty($dtrFirst)) {
                                            $orientation_display = date('F j, Y', strtotime($dtrFirst));

                                            // estimate end date inclusive of start day:
                                            $remaining = max(0, (float)$total_required - (float)$hours_rendered);
                                            $hoursPerDay = 8;
                                            $daysNeeded = (int)ceil($remaining / $hoursPerDay);

                                            if ($daysNeeded <= 0) {
                                                $expected_end_display = $orientation_display;
                                            } else {
                                                // count start day as day 1 if it's a weekday
                                                $dt = new DateTime($dtrFirst);
                                                $counted = 0;
                                                // advance day-by-day and count only Mon-Fri; stop when counted == daysNeeded
                                                while ($counted < $daysNeeded) {
                                                    $dow = (int)$dt->format('N'); // 1..7
                                                    if ($dow <= 5) $counted++;
                                                    if ($counted >= $daysNeeded) break;
                                                    $dt->modify('+1 day');
                                                }
                                                $expected_end_display = $dt->format('F j, Y');
                                            }
                                        } else {
                                            // no DTR yet: fallback to orientation from application (if valid date) and estimate from that
                                            if (!empty($orientation) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $orientation)) {
                                                $orientation_display = date('F j, Y', strtotime($orientation));
                                                $remaining = max(0, (float)$total_required - (float)$hours_rendered);
                                                $hoursPerDay = 8;
                                                $daysNeeded = (int)ceil($remaining / $hoursPerDay);
                                                if ($daysNeeded <= 0) {
                                                    $expected_end_display = $orientation_display;
                                                } else {
                                                    $dt = new DateTime($orientation);
                                                    $counted = 0;
                                                    while ($counted < $daysNeeded) {
                                                        $dow = (int)$dt->format('N');
                                                        if ($dow <= 5) $counted++;
                                                        if ($counted >= $daysNeeded) break;
                                                        $dt->modify('+1 day');
                                                    }
                                                    $expected_end_display = $dt->format('F j, Y');
                                                }
                                            } else {
                                                $orientation_display = '-';
                                                $expected_end_display = '-';
                                            }
                                        }
                                    } else {
                                        // completed or other statuses: keep prior behavior
                                        if (!empty($hours_rendered) && !empty($total_required) && $hours_rendered >= $total_required) {
                                            // use DTR first/last for start/end
                                            $dtrFirst = null;
                                            $dtrLast = null;
                                            $dtrUserId = !empty($user_id) ? (int)$user_id : null;
                                            if (!$dtrUserId && !empty($student_id)) $dtrUserId = (int)$student_id;
                                            if (!empty($dtrUserId)) {
                                                $qf = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND COALESCE(log_date,'') <> '' ORDER BY log_date ASC LIMIT 1");
                                                if ($qf) { $qf->bind_param('i', $dtrUserId); $qf->execute(); $r1 = $qf->get_result()->fetch_assoc(); $qf->close(); if ($r1 && !empty($r1['log_date'])) $dtrFirst = $r1['log_date']; }
                                                $ql = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND COALESCE(log_date,'') <> '' ORDER BY log_date DESC LIMIT 1");
                                                if ($ql) { $ql->bind_param('i', $dtrUserId); $ql->execute(); $r2 = $ql->get_result()->fetch_assoc(); $ql->close(); if ($r2 && !empty($r2['log_date'])) $dtrLast = $r2['log_date']; }
                                            }
                                            if (!empty($dtrFirst)) $orientation_display = date('F j, Y', strtotime($dtrFirst));
                                            if (!empty($dtrLast)) $expected_end_display = date('F j, Y', strtotime($dtrLast));
                                            else $expected_end_display = '-';
                                        } else {
                                            // fallback: use orientation from application and estimate end date from it (Mon-Fri, 8hrs/day)
                                            if (!empty($orientation) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $orientation)) {
                                                $orientation_display = date('F j, Y', strtotime($orientation));
                                                $remaining = max(0, (float)$total_required - (float)$hours_rendered);
                                                $hoursPerDay = 8;
                                                $daysNeeded = (int)ceil($remaining / $hoursPerDay);
                                                if ($daysNeeded <= 0) {
                                                    $expected_end_display = $orientation_display;
                                                } else {
                                                    $dt = new DateTime($orientation);
                                                    $counted = 0;
                                                    while ($counted < $daysNeeded) {
                                                        $dow = (int)$dt->format('N');
                                                        if ($dow <= 5) $counted++;
                                                        if ($counted >= $daysNeeded) break;
                                                        $dt->modify('+1 day');
                                                    }
                                                    $expected_end_display = $dt->format('F j, Y');
                                                }
                                            } else {
                                                $orientation_display = $orientation ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $orientation) ? date('F j, Y', strtotime($orientation)) : $orientation) : '-';
                                                $expected_end_display = '-';
                                            }
                                        }
                                    }
                                    ?>

                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap;">
                                        <div style="flex:1 1 320px; min-width:220px;">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.6; font-size:16px;">
                                            Age: <b><?php echo htmlspecialchars($sinfo['age'] ?: '-'); ?></b><br>
                                            Birthday: <b><?php echo htmlspecialchars($sinfo['birthday_fmt'] ?? ($sinfo['birthday'] ?: '-')); ?></b><br>
                                            Address: <b><?php echo htmlspecialchars($sinfo['address'] ?: '-'); ?></b><br>
                                            Phone: <b><?php echo htmlspecialchars($sinfo['phone'] ?: '-'); ?></b><br>
                                            Email: <b><?php echo htmlspecialchars($sinfo['email'] ?: '-'); ?></b>
                                            </p>
                                            <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.4; font-size:15px;">
                                              College/University: &nbsp; <b><?php echo htmlspecialchars($sinfo['college'] ?: '-'); ?></b><br>
                                              Course: &nbsp; <b><?php echo htmlspecialchars($sinfo['course'] ?: '-'); ?></b><br>
                                              Year level: &nbsp; <b><?php echo htmlspecialchars($sinfo['year_level'] ?: '-'); ?></b><br>
                                              School Address: &nbsp; <b><?php echo htmlspecialchars($sinfo['school_address'] ?: '-'); ?></b>
                                            </p>
                                            <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.4; font-size:15px;">
                                              OJT Adviser: &nbsp; <b><?php echo htmlspecialchars($sinfo['ojt_adviser'] ?: '-'); ?></b><br>
                                              Contact #: &nbsp; <b><?php echo htmlspecialchars($sinfo['adviser_contact'] ?: '-'); ?></b>
                                            </p>
                                            <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
                                            <p style="margin:0; color:#2f3459; font-weight:700; font-size:15px;">Emergency Contact</p>
                                            <p style="margin:6px 0 0 0; color:#6b6f8b; font-size:15px;">
                                              Name: <b><?php echo htmlspecialchars($sinfo['emg_name'] ?: '-'); ?></b><br>
                                              Relationship: <b><?php echo htmlspecialchars($sinfo['emg_relation'] ?: '-'); ?></b><br>
                                              Contact Number: <b><?php echo htmlspecialchars($sinfo['emg_contact'] ?: '-'); ?></b>
                                            </div>
                                            <div style="width:1px;background:#eef1f6;align-self:stretch;margin:0 45px;"></div>
                                        <!-- Percent / right column: circle above details -->
                                        <div id="ojt-percent" style="flex:0 0 360px; display:flex;flex-direction:column;align-items:flex-start;gap:12px;">
                                          <div style="width:100%;display:flex;justify-content:flex-start;">
                                            <div class="ojt-circle" data-percent="<?php echo $percent; ?>" style="width:88px;height:88px;border-radius:50%;
                                                 display:flex;align-items:center;justify-content:center;color:#2f3459;font-weight:700;font-size:18px;
                                                 background:conic-gradient(rgba(47,52,89,0.22) 0deg, #e6e9f2 0deg);">
                                              <?php echo $percent; ?>%
                                            </div>
                                          </div>

                                          <div style="display:flex; flex-direction:column; align-items:flex-start; gap:6px; min-width:220px;">
                                            <div style="color:#2f3459;font-weight:700;font-size:16px;"><?php echo htmlspecialchars((int)$hours_rendered . ' out of ' . (int)$total_required . ' hours'); ?></div>
                                            
                                            <div style="color:#6b6f8b;font-size:13px; margin-top:4px;">Date Started: <b style="color:#2f3459;"><?php echo htmlspecialchars($orientation_display); ?></b></div>
                                            <div style="color:#6b6f8b;font-size:13px;">Estimated End Date: <b style="color:#2f3459;"><?php echo htmlspecialchars($expected_end_display); ?></b></div>

                                                <div style="width:100%;height:8px"></div>

                                                <div style="display:flex;gap:12px;width:100%;align-items:flex-start;">
                                                    <div style="min-width:140px;">
                                                        <div style="color:#6b6f8b;font-size:13px;">Assigned Office</div>
                                                        <div style="font-weight:700;color:#2f3459;"><?php echo htmlspecialchars($assignedOffice ?: '-'); ?></div>
                                                    </div>
                                                    <div style="min-width:140px;">
                                                        <div style="color:#6b6f8b;font-size:13px;">Office Head</div>
                                                        <div style="font-weight:700;color:#2f3459;"><?php echo htmlspecialchars($office_head_name ?: '-'); ?></div>
                                                        <div style="color:#6b6f8b;font-size:13px;margin-top:6px;">Email: <b style="color:#2f3459;"><?php echo htmlspecialchars($office_head_email ?? '-'); ?></b></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                     <script>
                                     (function(){
                                        var percent = <?php echo json_encode($percent); ?>;
                                        var circle = document.querySelector('#tab-info .ojt-circle');
                                        if (circle) {
                                            var p = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
                                            var deg = p * 3.6;
                                            // lighter / semi-transparent progress arc so dark percentage text is readable
                                            circle.style.background = 'conic-gradient(rgba(47,52,89,0.22) 0deg ' + deg + 'deg, #e6e9f2 ' + deg + 'deg 360deg)';
                                            circle.textContent = p + '%';
                                            // use dark text for contrast against the lighter arc
                                            circle.style.color = '#2f3459';
                                        }
                                     })();
                                     </script>
                             </section>

                            <section id="tab-journals" class="tab-panel" style="display:none;">
                                <?php
                                // Show weekly_journal rows from DB (user_id = student_id)
                                $journals = [];
                                if (!empty($student_id)) {
                                    $qj = $conn->prepare("SELECT journal_id, date_uploaded, week_coverage, attachment FROM weekly_journal WHERE user_id = ? ORDER BY date_uploaded DESC, journal_id DESC");
                                    $qj->bind_param('i', $student_id);
                                    $qj->execute();
                                    $rj = $qj->get_result();
                                    while ($row = $rj->fetch_assoc()) $journals[] = $row;
                                    $qj->close();
                                }

                                // Compute next week number and default Mon-Fri date range for the upload modal
                                $nextWeekNumber = count($journals) + 1;
                                // Determine base date for next week: prefer the latest journal's stored week range (if it contains ISO dates),
                                // otherwise fall back to using the latest date_uploaded.
                                $nextMonday = null;
                                if (!empty($journals) && !empty($journals[0]['week_coverage'])) {
                                    $latestLabel = $journals[0]['week_coverage'];
                                    // look for stored ISO pattern we save on upload: (YYYY-MM-DD|YYYY-MM-DD)
                                    if (preg_match('/\((\d{4}-\d{2}-\d{2})\|(\d{4}-\d{2}-\d{2})\)$/', $latestLabel, $m)) {
                                        try {
                                            $lastFrom = new DateTime($m[1]);
                                            // next week's Monday is lastFrom + 7 days
                                            $nextMonday = clone $lastFrom;
                                            $nextMonday->modify('+7 days');
                                        } catch (Exception $e) {
                                            $nextMonday = null;
                                        }
                                    }
                                }
                                if ($nextMonday === null) {
                                    if (!empty($journals) && !empty($journals[0]['date_uploaded'])) {
                                        try {
                                            $last = new DateTime($journals[0]['date_uploaded']);
                                        } catch (Exception $e) {
                                            $last = new DateTime();
                                        }
                                        // move to the Monday of that week, then advance one week to get the next week's Monday
                                        $lastMonday = clone $last;
                                        $lastMonday->modify('this week monday');
                                        $nextMonday = clone $lastMonday;
                                        $nextMonday->modify('+7 days');
                                    } else {
                                        $today = new DateTime();
                                        $nextMonday = clone $today;
                                        $nextMonday->modify('this week monday');
                                    }
                                }
                                $fromDate = $nextMonday->format('Y-m-d');
                                $toDateObj = (clone $nextMonday)->modify('+4 days');
                                $toDate = $toDateObj->format('Y-m-d');
                                // human-friendly week label like: Week 8 (September 8–12) or Week 1 (Sep 28–Oct 2)
                                $fromLabel = $nextMonday->format('F j');
                                $toLabel = $toDateObj->format('F j');
                                if ($nextMonday->format('F') === $toDateObj->format('F')) {
                                    // same month: "September 8–12"
                                    $toLabelShort = $toDateObj->format('j');
                                    $weekLabel = sprintf('Week %d (%s–%s)', $nextWeekNumber, $fromLabel, $toLabelShort);
                                } else {
                                    // different months: include both months
                                    $weekLabel = sprintf('Week %d (%s–%s)', $nextWeekNumber, $fromLabel, $toLabel);
                                }
                                // Short label that shows only the week number (used for display and posting)
                                $weekNumberLabel = 'Week ' . $nextWeekNumber;
                                // When there are existing journals, restrict selectable dates to start no earlier than the
                                // computed next week's "from" date. If no prior journals, allow any date (no min).
                                $minSelectable = (count($journals) > 0) ? $fromDate : '';
                                ?>

                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                    <div style="color:#6b6f8b;font-size:16px;">Weekly Journals (<?php echo count($journals); ?>)</div>
                                    <?php if (!empty($student_id)): ?>
                                        <button id="btn-upload-journal" type="button" style="display:inline-flex;gap:8px;align-items:center;padding:8px 12px;border-radius:8px;border:0;background:#2f3459;color:#fff !important;cursor:pointer;font-size:14px;">
                                            <span style="color:#fff !important;font-weight:700;font-size:18px;line-height:0;">+</span> Upload Journal
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Upload modal -->
                                <form id="frm-upload-journal" action="" method="post" enctype="multipart/form-data" style="display:none;">
                                    <input type="hidden" name="action" value="upload_journal">
                                    <div id="upload-modal-overlay" style="position:fixed;inset:0;background:rgba(15,20,40,0.5);display:none;align-items:center;justify-content:center;z-index:9999;">
                                        <div id="upload-modal-content" style="width:360px;background:#fff;border-radius:12px;padding:18px 18px;box-shadow:0 12px 40px rgba(15,20,40,0.35);font-family:Arial,Helvetica,sans-serif;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                                                <h3 style="margin:0;font-size:18px;color:#2f3459;">Upload Journal</h3>
                                                <button type="button" id="upload-close" style="border:0;background:transparent;font-size:18px;color:#9aa0b6;cursor:pointer;">✕</button>
                                            </div>
                                            <?php if (!empty($journal_upload_error)): ?>
                                                <div style="color:#d32f2f;margin-bottom:8px;"><?php echo htmlspecialchars($journal_upload_error); ?></div>
                                            <?php endif; ?>
                                            <div style="margin-bottom:10px;">
                                                <label style="display:block;font-size:13px;color:#6b6f8b;margin-bottom:6px;">Week</label>
                                                <!-- Display-only Week label (no editable textbox) -->
                                                <div id="modal-week-display" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6e9f2;font-size:14px;background:#f7f8fb;color:#2f3459;">
                                                    <?php echo htmlspecialchars($weekNumberLabel); ?>
                                                </div>
                                                <!-- Hidden field preserves server contract for week_coverage (only Week N) -->
                                                <input type="hidden" id="modal-week-hidden" name="week_coverage" value="<?php echo htmlspecialchars($weekNumberLabel); ?>">
                                                <div style="display:flex;gap:8px;margin-top:8px;">
                                                    <div style="flex:1;">
                                                        <label style="display:block;font-size:12px;color:#6b6f8b;margin-bottom:6px;">From</label>
                                                        <input id="modal-from" name="week_from" type="date" placeholder="mm/dd/yyyy" <?php if(!empty($minSelectable)) echo 'min="'.htmlspecialchars($minSelectable).'"'; ?> max="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:8px;border-radius:8px;border:1px solid #e6e9f2;font-size:13px;">
                                                    </div>
                                                    <div style="flex:1;">
                                                        <label style="display:block;font-size:12px;color:#6b6f8b;margin-bottom:6px;">To</label>
                                                        <input id="modal-to" name="week_to" type="date" placeholder="mm/dd/yyyy" <?php if(!empty($minSelectable)) echo 'min="'.htmlspecialchars($minSelectable).'"'; ?> max="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:8px;border-radius:8px;border:1px solid #e6e9f2;font-size:13px;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="margin-bottom:8px;">
                                                <label style="display:block;font-size:13px;color:#6b6f8b;margin-bottom:6px;">Attach file</label>
                                                <div style="display:flex;gap:8px;">
                                                    <input id="modal-file" name="attachment" type="file" accept=".docx,.pdf" style="flex:1;" required>
                                                </div>
                                                <div style="font-size:12px;color:#8a8f9d;margin-top:8px;">
                                                    <strong>Note:</strong>
                                                    <ul style="margin:6px 0 0 18px;padding:0;color:#8a8f9d;">
                                                        <li>Supported file types: DOCX, PDF</li>
                                                        <li>Maximum file size per file: 2 MB</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                                                <button type="button" id="modal-cancel" style="padding:8px 14px;border-radius:18px;border:1px solid #e6e9f2;background:transparent;color:#2f3459;cursor:pointer;">Cancel</button>
                                                <button type="submit" id="modal-upload" style="padding:8px 14px;border-radius:18px;border:0;background:#2f3459;color:#fff !important;cursor:pointer;">Upload</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <div style="margin-top:4px; overflow:auto;">
                                  <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #eceff5">
                                    <thead style="background:#f5f7fb;color:#2f3459">
                                      <tr>
                                        <th style="text-align:left;padding:12px;border-bottom:1px solid #eef1f6;width:25%;">DATE UPLOADED</th>
                                        <th style="text-align:left;padding:12px;border-bottom:1px solid #eef1f6;width:45%;">WEEK</th>
                                        <th style="text-align:left;padding:12px;border-bottom:1px solid #eef1f6;width:20%;">ATTACHMENT</th>
                                        <th style="text-align:center;padding:12px;border-bottom:1px solid #eef1f6;width:10%;">ACTION</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php if (empty($journals)): ?>
                                        <tr><td colspan="4" style="padding:18px;text-align:center;color:#8a8f9d;">No weekly journals found.</td></tr>
                                      <?php else: foreach($journals as $j): ?>
                                        <tr>
                                          <td style="padding:12px;border-top:1px solid #f1f4f8;color:#6b6f8b;"><?php echo !empty($j['date_uploaded']) ? date('M j, Y', strtotime($j['date_uploaded'])) : '-'; ?></td>
                                              <td style="padding:12px;border-top:1px solid #f1f4f8;color:#2f3459;">
                                                <?php
                                                // Display friendly week label. If we stored an ISO range like "Week N (YYYY-MM-DD|YYYY-MM-DD)", format it
                                                $rawWeek = $j['week_coverage'] ?? '';
                                                $displayWeek = '-';
                                                if (!empty($rawWeek)) {
                                                    if (preg_match('/^(Week\s*\d+)\s*\((\d{4}-\d{2}-\d{2})\|(\d{4}-\d{2}-\d{2})\)$/', $rawWeek, $wm)) {
                                                            try {
                                                                $d1 = new DateTime($wm[2]);
                                                                $d2 = new DateTime($wm[3]);
                                                                // collect only weekdays (Mon-Fri) between the two dates
                                                                $days = [];
                                                                $tmp = clone $d1;
                                                                while ($tmp <= $d2) {
                                                                    $n = (int)$tmp->format('N');
                                                                    if ($n <= 5) $days[] = clone $tmp;
                                                                    $tmp->modify('+1 day');
                                                                }
                                                                if (empty($days)) {
                                                                    $displayWeek = htmlspecialchars($rawWeek);
                                                                } else {
                                                                    // group by year -> month -> day numbers
                                                                    $groups = [];
                                                                    foreach ($days as $dt) {
                                                                        $mon = $dt->format('F');
                                                                        $yr = $dt->format('Y');
                                                                        $groups[$yr][$mon][] = (int)$dt->format('j');
                                                                    }
                                                                    $monthParts = [];
                                                                    foreach ($groups as $yr => $months) {
                                                                        foreach ($months as $mon => $nums) {
                                                                            sort($nums);
                                                                            $ranges = [];
                                                                            $start = $prev = null;
                                                                            foreach ($nums as $n) {
                                                                                if ($start === null) { $start = $prev = $n; continue; }
                                                                                if ($n === $prev + 1) { $prev = $n; continue; }
                                                                                if ($start === $prev) $ranges[] = (string)$start; else $ranges[] = $start . '–' . $prev;
                                                                                $start = $prev = $n;
                                                                            }
                                                                            if ($start !== null) { if ($start === $prev) $ranges[] = (string)$start; else $ranges[] = $start . '–' . $prev; }
                                                                            $monthParts[] = ['month'=>$mon, 'year'=>$yr, 'ranges'=>implode(', ', $ranges)];
                                                                        }
                                                                    }
                                                                    // build display
                                                                    $years = array_unique(array_map(function($p){ return $p['year']; }, $monthParts));
                                                                    if (count($monthParts) === 1) {
                                                                        $p = $monthParts[0];
                                                                        $displayWeek = sprintf('%s (%s %s, %s)', $wm[1], $p['month'], $p['ranges'], $p['year']);
                                                                    } else {
                                                                        if (count($years) === 1) {
                                                                            $pieces = array_map(function($p){ return $p['month'] . ' ' . $p['ranges']; }, $monthParts);
                                                                            $displayWeek = sprintf('%s (%s, %s)', $wm[1], implode(', ', $pieces), $years[0]);
                                                                        } else {
                                                                            $pieces = array_map(function($p){ return $p['month'] . ' ' . $p['ranges'] . ' ' . $p['year']; }, $monthParts);
                                                                            $displayWeek = sprintf('%s (%s)', $wm[1], implode(', ', $pieces));
                                                                        }
                                                                    }
                                                                }
                                                            } catch (Exception $e) {
                                                                $displayWeek = htmlspecialchars($rawWeek);
                                                            }
                                                    } else {
                                                        // no ISO range stored. try to infer the week's scope from date_uploaded (if present)
                                                        $displayWeek = htmlspecialchars($rawWeek);
                                                        if (!empty($j['date_uploaded'])) {
                                                            try {
                                                                $du = new DateTime($j['date_uploaded']);
                                                                // get the Monday of that week
                                                                $monday = clone $du;
                                                                $monday->modify('this week monday');
                                                                $fri = (clone $monday)->modify('+4 days');
                                                                $fromLabel = $monday->format('F j');
                                                                $toLabel = $fri->format('F j');
                                                                if ($monday->format('F') === $fri->format('F')) {
                                                                    $displayWeek = sprintf('%s (%s–%s, %s)', preg_replace('/\s*\(.*$/','',$rawWeek), $fromLabel, $fri->format('j'), $fri->format('Y'));
                                                                } else {
                                                                    $displayWeek = sprintf('%s (%s–%s, %s)', preg_replace('/\s*\(.*$/','',$rawWeek), $fromLabel, $toLabel, $fri->format('Y'));
                                                                }
                                                            } catch (Exception $e) {
                                                                // keep fallback
                                                            }
                                                        }
                                                    }
                                                }
                                                echo $displayWeek;
                                                ?>
                                              </td>
                                          <td style="padding:12px;border-top:1px solid #f1f4f8;color:#2f3459;"><?php echo !empty($j['attachment']) ? htmlspecialchars(basename($j['attachment'])) : '-'; ?></td>
                                          <td style="padding:12px;border-top:1px solid #f1f4f8;text-align:center;color:#6b6f8b;">
                                                                                        <?php if (!empty($j['attachment'])):
                                                                                                $path = '../' . ltrim($j['attachment'],'/\\');
                                                                                                // mimic Attachments tab: open file directly in new tab (browser decides whether to preview or download)
                                                                                                $viewHref = $path;
                                                                                        ?>
                                                                                            <a href="<?php echo htmlspecialchars($viewHref); ?>" target="_blank" rel="noopener noreferrer" title="View" style="margin-right:8px;display:inline-flex;align-items:center;">
                                                                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                                                            </a>
                                                                                            <a href="<?php echo htmlspecialchars($path); ?>" download title="Download" style="display:inline-flex;align-items:center;">
                                                                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                                                                            </a>
                                                                                        <?php else: ?>-<?php endif; ?>
                                          </td>
                                        </tr>
                                      <?php endforeach; endif; ?>
                                    </tbody>
                                  </table>
                                </div>
                                <script>
                                (function(){
                                    var btn = document.getElementById('btn-upload-journal');
                                    var modalForm = document.getElementById('frm-upload-journal');
                                    var overlay = document.getElementById('upload-modal-overlay');
                                    var modalContent = document.getElementById('upload-modal-content');
                                    var modalWeekHidden = document.getElementById('modal-week-hidden');
                                    var modalFile = document.getElementById('modal-file');
                                    var closeBtn = document.getElementById('upload-close');
                                    var cancelBtn = document.getElementById('modal-cancel');

                                    function openModal(){
                                        if (!overlay) return;
                                        // ensure overlay lives at document body level so it stacks above other contexts
                                        try {
                                            // append the whole form to body so the submit button remains inside the form
                                            if (modalForm && modalForm.parentNode !== document.body) document.body.appendChild(modalForm);
                                            if (overlay.parentNode !== null) overlay.style.zIndex = '99999';
                                        } catch(e) {}
                                        overlay.style.display = 'flex';
                                        if (modalForm) modalForm.style.display = 'block';
                                        // prevent background scrolling while modal is open
                                        try { document.body.style.overflow = 'hidden'; } catch(e) {}
                                        // focus file input as the primary actionable field
                                        setTimeout(function(){ try { modalFile && modalFile.focus(); } catch(e){} }, 80);
                                    }
                                    function closeModal(){
                                        if (!overlay) return;
                                        overlay.style.display = 'none';
                                        if (modalForm) modalForm.style.display = 'none';
                                        // keep the hidden week value intact; clear date inputs and file input
                                        try { var mf = document.getElementById('modal-from'); if (mf) mf.value = ''; } catch(e) {}
                                        try { var mt = document.getElementById('modal-to'); if (mt) mt.value = ''; } catch(e) {}
                                        if (modalFile) modalFile.value = '';
                                        try { document.body.style.overflow = ''; } catch(e) {}
                                    }

                                    // prevent clicks inside modal content from closing
                                    if (modalContent) modalContent.addEventListener('click', function(e){ e.stopPropagation(); });
                                    // clicking overlay closes the modal
                                    if (overlay) overlay.addEventListener('click', closeModal);

                                    btn && btn.addEventListener('click', openModal);
                                    closeBtn && closeBtn.addEventListener('click', closeModal);
                                    cancelBtn && cancelBtn.addEventListener('click', closeModal);

                                    // helper: return true if dateStr (YYYY-MM-DD) is weekend (Sat/Sun)
                                    function isWeekend(dateStr){
                                        if (!dateStr) return false;
                                        var d = new Date(dateStr + 'T00:00:00');
                                        var day = d.getDay(); // 0 Sun, 6 Sat
                                        return day === 0 || day === 6;
                                    }
                                    // prevent selecting weekend dates interactively
                                    try {
                                        var mfInput = document.getElementById('modal-from');
                                        var mtInput = document.getElementById('modal-to');
                                        if (mfInput) mfInput.addEventListener('change', function(){ if (isWeekend(this.value)){ alert('Please select a weekday (Mon–Fri).'); this.value = ''; this.focus(); } });
                                        if (mtInput) mtInput.addEventListener('change', function(){ if (isWeekend(this.value)){ alert('Please select a weekday (Mon–Fri).'); this.value = ''; this.focus(); } });
                                    } catch(e) {}

                                    // client-side validation before submit
                                    modalForm && modalForm.addEventListener('submit', function(e){
                                        // basic required checks: only file is required on client-side (week is provided by server-hidden field)
                                        if (!modalFile.files || !modalFile.files.length){
                                            e.preventDefault();
                                            alert('Please attach a file.');
                                            modalFile && modalFile.focus();
                                            return false;
                                        }
                                        var f = modalFile.files[0];
                                        // size check (2 MB)
                                        if (f.size > 2 * 1024 * 1024){
                                            e.preventDefault();
                                            alert('File too large. Maximum 2 MB.');
                                            return false;
                                        }
                                        // extension check (client-side; server enforces as well)
                                        var allowed = ['pdf','docx'];
                                        var name = f.name || '';
                                        var ext = (name.split('.').pop() || '').toLowerCase();
                                        if (allowed.indexOf(ext) === -1){
                                            e.preventDefault();
                                            alert('Unsupported file type. Allowed: DOCX, PDF.');
                                            modalFile && modalFile.focus();
                                            return false;
                                        }
                                        // validate date constraints (if user provided dates)
                                        try {
                                            var mf = document.getElementById('modal-from');
                                            var mt = document.getElementById('modal-to');
                                            if (mf) {
                                                if (mf.min && mf.value && mf.value < mf.min) {
                                                    e.preventDefault();
                                                    alert('Invalid "From" date. Please choose a date on or after ' + mf.min + '.');
                                                    mf.focus();
                                                    return false;
                                                }
                                            }
                                            // disallow weekends on submit as well
                                            if (mf && mf.value && isWeekend(mf.value)) { e.preventDefault(); alert('Please choose a weekday (Mon–Fri) for From date.'); mf.focus(); return false; }
                                            if (mt && mt.value && isWeekend(mt.value)) { e.preventDefault(); alert('Please choose a weekday (Mon–Fri) for To date.'); mt.focus(); return false; }
                                            if (mf && mt && mf.value && mt.value) {
                                                if (mt.value < mf.value) {
                                                    e.preventDefault();
                                                    alert('Invalid dates: "To" must be the same or after "From".');
                                                    mt.focus();
                                                    return false;
                                                }
                                            }
                                        } catch (ex) {}
                                        // if all checks pass, allow native submit (server will validate again)
                                    });
                                 })();
                                </script>
                            </section>

                            <section id="tab-attachments" class="tab-panel" style="display:none;">
              
                                    <?php
                                    $requirements = [];
                                    $moa_record = null;
                                    if (!empty($student_id)) {
                                        // get latest application for this student
                                        $qapp = $conn->prepare("SELECT letter_of_intent, endorsement_letter, resume, moa_file, picture, date_submitted FROM ojt_applications WHERE student_id = ? ORDER BY date_updated DESC, application_id DESC LIMIT 1");
                                        $qapp->bind_param('i', $student_id);
                                        $qapp->execute();
                                        $appRow = $qapp->get_result()->fetch_assoc();
                                        $qapp->close();
                                        if ($appRow) {
                                            // map known fields to labels (show only non-empty)
                                            $map = [
                                                'letter_of_intent' => 'Letter of Intent',
                                                'endorsement_letter' => 'Endorsement Letter',
                                                'resume' => 'Resume',
                                                'picture' => 'Profile Picture',
                                                'moa_file' => 'MOA'
                                            ];
                                            foreach ($map as $col => $label) {
                                                if (!empty($appRow[$col])) {
                                                    $requirements[] = [
                                                        'label' => $label,
                                                        'file' => $appRow[$col],
                                                        'date' => $appRow['date_submitted'] ?? null
                                                    ];
                                                }
                                            }
                                        }

                                        // if student's college exists, check moa table for matching school_name
                                        if (!empty($sinfo['college'])) {
                                            $colLower = '%' . strtolower($sinfo['college']) . '%';
                                            $qm = $conn->prepare("SELECT moa_file, school_name, date_signed FROM moa WHERE LOWER(school_name) LIKE ? ORDER BY date_signed DESC LIMIT 1");
                                            $qm->bind_param('s', $colLower);
                                            $qm->execute();
                                            $moa_record = $qm->get_result()->fetch_assoc();
                                            $qm->close();
                                            if ($moa_record && !empty($moa_record['moa_file'])) {
                                                // include MOA (school) at top if not already in requirements
                                                $found = false;
                                                foreach ($requirements as $r) if (basename($r['file']) === basename($moa_record['moa_file'])) $found = true;
                                                if (!$found) {
                                                    array_unshift($requirements, [
                                                        'label' => 'MOA',
                                                        'file' => $moa_record['moa_file'],
                                                        // hide date for MOA display per request
                                                        'date' => null
                                                    ]);
                                                }
                                            }
                                        }
                                    }
                                    ?>

                                    <div style="margin-top:12px; background:#fff; border:1px solid #eceff5; padding:18px; border-radius:6px;">
                                        <?php if (count($requirements) === 0): ?>
                                            <div style="color:#8a8f9d;">No attachments submitted yet.</div>
                                        <?php else: ?>
                                            <div style="display:flex;flex-direction:column;gap:12px;max-width:720px;">
                                                <?php foreach ($requirements as $req): 
                                                    $filePath = !empty($req['file']) ? '../' . ltrim($req['file'],'/\\') : '';
                                                    $fileName = $req['file'] ? htmlspecialchars(basename($req['file'])) : '-';
                                                    // dates removed for all attachments per request
                                                    $dateLabel = '';
                                                ?>
                                                    <div style="display:flex;align-items:center;justify-content:space-between;background:#f7f8fb;border-radius:8px;padding:10px 12px;">
                                                        <div style="display:flex;align-items:center;gap:8px;">
                                                            <div>
                                                                <div style="font-weight:600;color:#2f3459;"><?php echo htmlspecialchars($req['label']); ?></div>
                                                                <div style="color:#6b6f8b;font-size:13px;"><?php echo $fileName; ?></div>
                                                            </div>
                                                        </div>
                                                         <div style="display:flex;align-items:center;gap:10px;">
                                                             <?php if (!empty($filePath) && is_file(__DIR__ . '/../' . ltrim($req['file'],'/\\'))): ?>
                                                                 <a href="<?php echo htmlspecialchars($filePath); ?>" target="_blank" title="View" style="color:#6b6f8b;text-decoration:none;font-size:18px;">👁️</a>
                                                                 <a href="<?php echo htmlspecialchars($filePath); ?>" download title="Download" style="color:#6b6f8b;text-decoration:none;font-size:18px;">⬇️</a>
                                                             <?php else: ?>
                                                                 <span style="color:#c1c5d4;font-size:14px;">—</span>
                                                             <?php endif; ?>
                                                         </div>
                                                     </div>
                                                 <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                            </section>

                            <section id="tab-eval" class="tab-panel" style="display:none;">
                                    <h4 style="margin:0 0 10px 0; color:#2f3459; font-size:20px;">Evaluation</h4>
                                    <?php
                                    $evaluations = [];
                                    if (!empty($student_id)) {
                                        $qe = $conn->prepare("
                                            SELECT e.eval_id, e.rating, e.rating_desc, e.feedback, e.date_evaluated,
                                                   COALESCE(u.first_name,'') AS ev_first, COALESCE(u.last_name,'') AS ev_last
                                            FROM evaluations e
                                            LEFT JOIN users u ON e.user_id = u.user_id
                                            WHERE e.student_id = ?
                                            ORDER BY e.date_evaluated DESC, e.eval_id DESC
                                        ");
                                        if ($qe) {
                                            $qe->bind_param('i', $student_id);
                                            $qe->execute();
                                            $resE = $qe->get_result();
                                            while ($r = $resE->fetch_assoc()) $evaluations[] = $r;
                                            $qe->close();
                                        }
                                    }
                                    ?>
                                    <?php if (empty($evaluations)): ?>
                                        <p style="margin:0; color:#6b6f8b; font-size:16px;">No evaluation recorded yet.</p>
                                    <?php else: ?>
                                        <div style="margin-top:8px; overflow:auto; background:#fff; border:1px solid #eceff5; padding:12px; border-radius:8px;">
                                            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                                                <thead style="background:#f5f7fb;color:#2f3459">
                                                    <tr>
                                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #eef1f6;">Date</th>
                                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #eef1f6;">Rating</th>
                                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #eef1f6;">Rating Description</th>
                                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #eef1f6;">Feedback</th>
                                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #eef1f6;">Evaluated By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($evaluations as $ev): ?>
                                                    <tr>
                                                        <td style="padding:8px;border-top:1px solid #f1f4f8;color:#6b6f8b;"><?php echo !empty($ev['date_evaluated']) ? date('M j, Y', strtotime($ev['date_evaluated'])) : '-'; ?></td>
                                                        <td style="padding:8px;border-top:1px solid #f1f4f8;color:#2f3459;"><?php echo $ev['rating'] !== null ? htmlspecialchars($ev['rating']) : '-'; ?></td>
                                                        <td style="padding:8px;border-top:1px solid #f1f4f8;color:#2f3459;"><?php echo htmlspecialchars($ev['rating_desc'] ?? '-'); ?></td>
                                                        <td style="padding:8px;border-top:1px solid #f1f4f8;color:#2f3459;"><?php echo !empty($ev['feedback']) ? nl2br(htmlspecialchars($ev['feedback'])) : '-'; ?></td>
                                                        <td style="padding:8px;border-top:1px solid #f1f4f8;color:#6b6f8b;">
                                                            <?php
                                                            $ename = trim(($ev['ev_first'] ?? '') . ' ' . ($ev['ev_last'] ?? ''));
                                                            echo $ename !== '' ? htmlspecialchars($ename) : '-';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
      (function(){
        function attachConfirm(id){
          var el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('click', function(e){
            e.preventDefault();
            if (confirm('Log out?')) {
              // replace history entry so back button won't return to protected page
              window.location.replace(el.getAttribute('href') || '../logout.php');
            }
          });
        }
        attachConfirm('btnLogout');
        attachConfirm('sidebar-logout');
        // keep small handlers for notif/settings
        var n = document.getElementById('btnNotif');
        if (n) n.addEventListener('click', function(e){ e.preventDefault(); alert('Walang bagong notification ngayon.'); });
        var s = document.getElementById('btnSettings');
        if (s) s.addEventListener('click', function(e){ e.preventDefault(); window.location.href = 'settings.php'; });
      })();
    </script>

    <!-- ADDED: confirm for top-right logout icon (matches ojt_reports.php behavior) -->
    <script>
      (function(){
        var topLogout = document.getElementById('top-logout');
        if (topLogout) {
          topLogout.addEventListener('click', function(e){
            e.preventDefault();
            if (confirm('Logout?')) {
              window.location.replace(this.getAttribute('href') || '../logout.php');
            }
          });
        }
      })();
    </script>
        <script>
            // Only activate the Weekly Journals tab when we were redirected after an upload
            (function(){
                function activateIfUploadRedirect(){
                    try {
                        if (location.hash !== '#tab-journals') return;
                        if (location.search.indexOf('uploaded=1') === -1) return;
                        var btn = document.querySelector('.tab-btn[data-tab="tab-journals"]');
                        if (btn) try { btn.click(); } catch(e) {}
                        // remove uploaded=1 from URL so subsequent refreshes won't re-activate the tab
                        var newSearch = location.search.replace(/([?&])uploaded=1(&?)/, '$1').replace(/[?&]$/,'');
                        var newUrl = location.pathname + (newSearch ? newSearch : '') + location.hash;
                        history.replaceState(null, '', newUrl);
                    } catch(e) {}
                }
                window.addEventListener('hashchange', activateIfUploadRedirect);
                document.addEventListener('DOMContentLoaded', function(){ setTimeout(activateIfUploadRedirect, 20); });
            })();
        </script>
</body>
</html>