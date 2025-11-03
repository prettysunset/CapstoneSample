<?php
session_start();
require_once __DIR__ . '/../conn.php';

$display_name = 'User Name';
$display_role = 'Role';
$initials = 'UN';
$student_college = $student_course = $student_year = '';
$app_picture = ''; // NEW: path to picture from application (relative)
$app_picture_url = ''; // ensure defined
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // load user
    $u = $conn->prepare("SELECT username, first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ? LIMIT 1");
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
} // <-- close if ($user_id)
?>
<html>
<head>
    <title>OJT Profile</title>
    <link rel="stylesheet" type="text/css" href="stylesforojt.css">
    <script src="../js/ojt_profile.js"></script>
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
            <a href="ojt_home.php"
                 style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
                    <path d="M3 11.5L12 4l9 7.5"></path>
                    <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v-5h3a1 1 0 0 0 1-1v-7"></path>
                </svg>
                <span style="font-weight:600;">Home</span>
            </a>

          <a href="ojt_profile.php" class="active" aria-current="page"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.04);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span style="font-weight:600;">Profile</span>
          </a>

            <a href="#dtr" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 18px;">
              <rect x="3" y="4" width="18" height="18" rx="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>DTR</span>
            </a>

            <a href="#reports" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">
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
        <div style="width:auto; max-width:980px; align-self:flex-start; display:flex; gap:24px; align-items:center; background:#fff; padding:24px; border-radius:12px; box-shadow:0 6px 20px rgba(47,52,89,0.06);">
            <div style="width:110px;height:110px;border-radius:50%;overflow:hidden;background:#2f3459;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:36px;">
              <?php if (!empty($app_picture_url)): ?>
                <img src="<?php echo htmlspecialchars($app_picture_url); ?>" alt="Profile picture" style="width:110px;height:110px;object-fit:cover;display:block;">
              <?php else: ?>
                <?php echo htmlspecialchars($initials); ?>
              <?php endif; ?>
            </div>
            <div style="flex:0; white-space:nowrap;">
                <h1 style="margin:0 0 6px 0; font-size:26px; color:#2f3459;"><?php echo $display_name; ?></h1>
                <p style="margin:0 0 8px 0; color:#6b6f8b; font-size:16px;">Active OJT • Mayor's Office</p>
                <div style="display:flex; gap:12px; align-items:center; margin-top:6px;">
                    <button style="padding:12px 16px; border-radius:10px; border:0; background:#2f3459; color:#fff; cursor:pointer; font-size:15px;">Print DTR</button>
                    <button style="padding:12px 16px; border-radius:10px; border:1px solid #e6e9f2; background:transparent; color:#2f3459; cursor:pointer; font-size:15px;">Edit Profile</button>
                </div>
            </div>
        </div>
            <div style="max-width:980px; width:100%; display:grid; grid-template-columns:1fr; gap:20px;">
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
                              <button class="tab-btn active" data-tab="tab-info" aria-selected="true" role="tab">Information</button>
                              <button class="tab-btn" data-tab="tab-journals" aria-selected="false" role="tab">Weekly Journals</button>
                              <button class="tab-btn" data-tab="tab-attachments" aria-selected="false" role="tab">Attachments</button>
                              <button class="tab-btn" data-tab="tab-eval" aria-selected="false" role="tab">Evaluation</button>
                            </div>

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

                                    // compute progress and expected end date
                                    $hours_rendered = $hours_rendered ?? 0;
                                    $total_required = $total_required ?? 500;
                                    $percent = $total_required > 0 ? round(($hours_rendered / $total_required) * 100) : 0;
                                    $orientation_display = $orientation ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $orientation) ? date('F j, Y', strtotime($orientation)) : $orientation) : '-';
                                    // expected end (8 hrs/day, 5-day workweek) based on remaining hours
                                    $expected_end_display = '-';
                                    if (!empty($orientation) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $orientation)) {
                                        $hoursPerDay = 8;
                                        $remaining = max(0, (float)$total_required - (float)$hours_rendered);
                                        $daysNeeded = (int)ceil($remaining / $hoursPerDay);
                                        $dt = new DateTime($orientation);
                                        $added = 0;
                                        while ($added < $daysNeeded) {
                                            $dt->modify('+1 day');
                                            $dow = (int)$dt->format('N');
                                            if ($dow < 6) $added++;
                                        }
                                        $expected_end_display = $dt->format('F j, Y');
                                    } elseif (!empty($orientation) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $orientation)) {
                                        $expected_end_display = '-';
                                    }
                                    ?>

                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap;">
                                        <div style="flex:1 1 320px; min-width:220px;">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.6; font-size:16px;">
                                            Age: <?php echo htmlspecialchars($sinfo['age'] ?: '-'); ?><br>
                                            Birthday: <b><?php echo htmlspecialchars($sinfo['birthday_fmt'] ?? ($sinfo['birthday'] ?: '-')); ?></b><br>
                                            Address: <b><?php echo htmlspecialchars($sinfo['address'] ?: '-'); ?></b><br>
                                            Phone: <b><?php echo htmlspecialchars($sinfo['phone'] ?: '-'); ?></b><br>
                                            Email: <b><?php echo htmlspecialchars($sinfo['email'] ?: '-'); ?></b>
                                            </p>
                                            <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.4; font-size:15px;">
                                              <strong>College/University</strong> &nbsp; <?php echo htmlspecialchars($sinfo['college'] ?: '-'); ?><br>
                                              <strong>Course</strong> &nbsp; <?php echo htmlspecialchars($sinfo['course'] ?: '-'); ?><br>
                                              <strong>Year level</strong> &nbsp; <?php echo htmlspecialchars($sinfo['year_level'] ?: '-'); ?><br>
                                              <strong>School Address</strong> &nbsp; <?php echo htmlspecialchars($sinfo['school_address'] ?: '-'); ?>
                                            </p>
                                            <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
                                            <p style="margin:0; color:#6b6f8b; line-height:1.4; font-size:15px;">
                                              <strong>OJT Adviser</strong> &nbsp; <?php echo htmlspecialchars($sinfo['ojt_adviser'] ?: '-'); ?><br>
                                              <strong>Contact #</strong> &nbsp; <?php echo htmlspecialchars($sinfo['adviser_contact'] ?: '-'); ?>
                                            </p>
                                            <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
                                            <p style="margin:0; color:#2f3459; font-weight:700; font-size:15px;">Emergency Contact</p>
                                            <p style="margin:6px 0 0 0; color:#6b6f8b; font-size:15px;">
                                              Name: <b><?php echo htmlspecialchars($sinfo['emg_name'] ?: '-'); ?></b><br>
                                              Relationship: <b><?php echo htmlspecialchars($sinfo['emg_relation'] ?: '-'); ?></b><br>
                                              Contact Number: <b><?php echo htmlspecialchars($sinfo['emg_contact'] ?: '-'); ?></b>
                                            </p>
                                        </div>

                                        <!-- Percent / right column: circle left, details right (matches reference) -->
                                        <div id="ojt-percent" style="flex:0 0 360px; display:flex;align-items:flex-start;gap:16px;">
                                            <div class="ojt-circle" data-percent="<?php echo $percent; ?>" style="width:88px;height:88px;border-radius:50%;
                                                     display:flex;align-items:center;justify-content:center;color:#2f3459;font-weight:700;font-size:18px;
                                                     background:conic-gradient(#2f3459 0deg, #e6e9f2 0deg);flex:0 0 88px;">
                                                <?php echo $percent; ?>%
                                            </div>

                                            <div style="display:flex; flex-direction:column; align-items:flex-start; gap:6px; min-width:220px;">
                                                <div style="color:#2f3459;font-weight:700;font-size:16px;"><?php echo htmlspecialchars((int)$hours_rendered . ' out of ' . (int)$total_required . ' hours'); ?></div>
                                                <div style="color:#6b6f8b;font-size:13px;"><?php echo $percent; ?>% complete</div>
                                                <div style="color:#6b6f8b;font-size:13px; margin-top:4px;">Date Started: <b style="color:#2f3459;"><?php echo htmlspecialchars($orientation_display); ?></b></div>
                                                <div style="color:#6b6f8b;font-size:13px;">Expected End Date: <b style="color:#2f3459;"><?php echo htmlspecialchars($expected_end_display); ?></b></div>

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
                                            var deg = Math.max(0, Math.min(100, percent)) * 3.6;
                                            circle.style.background = 'conic-gradient(#2f3459 0deg ' + deg + 'deg, #e6e9f2 ' + deg + 'deg 360deg)';
                                            circle.textContent = percent + '%';
                                        }
                                     })();
                                     </script>
                             </section>

                            <section id="tab-journals" class="tab-panel" style="display:none;">
                                    <?php
                                    // fetch journals
                                    $journals = [];
                                    if (!empty($student_id)) {
                                        $qj = $conn->prepare("SELECT journal_id, date_uploaded, week_coverage, attachment, status FROM weekly_journal WHERE student_id = ? ORDER BY date_uploaded DESC, journal_id DESC");
                                        $qj->bind_param('i', $student_id);
                                        $qj->execute();
                                        $rj = $qj->get_result();
                                        while ($row = $rj->fetch_assoc()) $journals[] = $row;
                                        $qj->close();
                                    }
                                    ?>

                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                        <div style="color:#6b6f8b;font-size:16px;">Uploaded weekly journals</div>
                                        <!-- Upload button (top-right) -->
                                        <div>
                                        <button id="btn-upload-journal" type="button" style="display:inline-flex;gap:8px;align-items:center;padding:10px 14px;border-radius:8px;border:0;background:#6f6ca6;color:#fff;cursor:pointer;font-size:14px;">
                                            <span style="font-weight:700;font-size:18px;line-height:0;">+</span> Upload Journal
                                        </button>
                                    </div>
                                </div>

                                    <!-- Upload modal (hidden by default) -->
                                    <form id="frm-upload-journal" action="ojt_upload_journal.php" method="post" enctype="multipart/form-data" style="display:none;">
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id ?? ''); ?>">
                                        <div id="upload-modal-overlay" style="position:fixed;inset:0;background:rgba(15,20,40,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;">
                                            <div style="width:360px;background:#fff;border-radius:28px;padding:20px 22px;box-shadow:0 12px 40px rgba(15,20,40,0.35);font-family:Arial,Helvetica,sans-serif;">
                                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                                                    <h3 style="margin:0;font-size:18px;color:#2f3459;">Upload Journal</h3>
                                                    <button type="button" id="upload-close" style="border:0;background:transparent;font-size:18px;color:#9aa0b6;cursor:pointer;">✕</button>
                                                </div>
                                                <div style="margin-bottom:10px;">
                                                    <label style="display:block;font-size:13px;color:#6b6f8b;margin-bottom:6px;">Week</label>
                                                    <input id="modal-week" name="week_coverage" type="text" placeholder="Week 8 (September 8–12)" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6e9f2;font-size:14px;">
                                                </div>
                                                <div style="margin-bottom:8px;">
                                                    <label style="display:block;font-size:13px;color:#6b6f8b;margin-bottom:6px;">Attach file</label>
                                                    <div style="display:flex;gap:8px;">
                                                        <input id="modal-file" name="attachment" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="flex:1;">
                                                    </div>
                                                    <div style="font-size:12px;color:#8a8f9d;margin-top:8px;">
                                                        <strong>Note:</strong>
                                                        <ul style="margin:6px 0 0 18px;padding:0;color:#8a8f9d;">
                                                            <li>Supported file types: DOCX, PDF, JPG, PNG</li>
                                                            <li>Maximum file size per file: 2 MB</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                                                    <button type="button" id="modal-cancel" style="padding:10px 14px;border-radius:8px;border:1px solid #e6e9f2;background:transparent;color:#2f3459;cursor:pointer;">Cancel</button>
                                                    <button type="submit" id="modal-upload" style="padding:10px 16px;border-radius:18px;border:0;background:#2f3459;color:#fff;cursor:pointer;">Upload</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>

                                    <div style="margin-top:4px; overflow:auto;">
                                      <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #eceff5">
                                        <thead style="background:#f5f7fb;color:#2f3459">
                                          <tr>
                                            <th style="text-align:left;padding:12px;border-bottom:1px solid #eef1f6;width:18%;">DATE UPLOADED</th>
                                            <th style="text-align:left;padding:12px;border-bottom:1px solid #eef1f6;width:40%;">WEEK</th>
                                            <th style="text-align:left;padding:12px;border-bottom:1px solid #eef1f6;width:30%;">ATTACHMENT</th>
                                            <th style="text-align:center;padding:12px;border-bottom:1px solid #eef1f6;width:12%;">ACTION</th>
                                          </tr>
                                        </thead>
                                        <tbody>
                                          <?php if (count($journals) === 0): ?>
                                            <tr>
                                              <td colspan="4" style="padding:18px;text-align:center;color:#8a8f9d;">No weekly journals uploaded yet.</td>
                                            </tr>
                                          <?php else: ?>
                                            <?php foreach($journals as $j): ?>
                                              <tr>
                                                <td style="padding:12px;border-top:1px solid #f1f4f8;color:#6b6f8b;">
                                                  <?php echo !empty($j['date_uploaded']) ? date('M j, Y', strtotime($j['date_uploaded'])) : '-'; ?>
                                                </td>
                                                <td style="padding:12px;border-top:1px solid #f1f4f8;color:#2f3459;">
                                                  <?php echo htmlspecialchars($j['week_coverage'] ?: '-'); ?>
                                                </td>
                                                <td style="padding:12px;border-top:1px solid #f1f4f8;color:#2f3459;">
                                                  <?php if (!empty($j['attachment'])): ?>
                                                    <a href="<?php echo htmlspecialchars('../' . ltrim($j['attachment'],'/\\')); ?>" target="_blank" style="color:#2f3459;text-decoration:underline;"><?php echo htmlspecialchars(basename($j['attachment'])); ?></a>
                                                  <?php else: ?>
                                                    -
                                                  <?php endif; ?>
                                                </td>
                                                <td style="padding:12px;border-top:1px solid #f1f4f8;text-align:center;color:#6b6f8b;">
                                                  <?php if (!empty($j['attachment'])): ?>
                                                    <a href="<?php echo htmlspecialchars('../' . ltrim($j['attachment'],'/\\')); ?>" target="_blank" title="View" style="margin-right:8px;">🔍</a>
                                                    <a href="<?php echo htmlspecialchars('../' . ltrim($j['attachment'],'/\\')); ?>" download title="Download">⬇️</a>
                                                  <?php else: ?>
                                                    -
                                                  <?php endif; ?>
                                                </td>
                                              </tr>
                                            <?php endforeach; ?>
                                          <?php endif; ?>
                                        </tbody>
                                      </table>
                                    </div>

                                    <script>
                                    (function(){
                                        var btn = document.getElementById('btn-upload-journal');
                                        var modalForm = document.getElementById('frm-upload-journal');
                                        var overlay = document.getElementById('upload-modal-overlay');
                                        var modalWeek = document.getElementById('modal-week');
                                        var modalFile = document.getElementById('modal-file');
                                        var closeBtn = document.getElementById('upload-close');
                                        var cancelBtn = document.getElementById('modal-cancel');

                                        // open modal
                                        btn && btn.addEventListener('click', function(){
                                            if (!modalForm) return;
                                            modalForm.style.display = 'block';
                                            overlay.style.opacity = '1';
                                        });
                                        // close modal helper
                                        function closeModal(){
                                            if (!modalForm) return;
                                            modalForm.style.display = 'none';
                                            modalWeek.value = '';
                                            modalFile.value = '';
                                        }
                                        closeBtn && closeBtn.addEventListener('click', closeModal);
                                        cancelBtn && cancelBtn.addEventListener('click', closeModal);

                                        // client validation before submit
                                        modalForm.addEventListener('submit', function(e){
                                            if (!modalWeek.value.trim()){
                                                e.preventDefault();
                                                alert('Please enter week coverage.');
                                                modalWeek.focus();
                                                return false;
                                            }
                                            if (!modalFile.files || !modalFile.files.length){
                                                e.preventDefault();
                                                alert('Please attach a file.');
                                                modalFile.focus();
                                                return false;
                                            }
                                            // file size check (2 MB)
                                            var f = modalFile.files[0];
                                            if (f.size > 2 * 1024 * 1024){
                                                e.preventDefault();
                                                alert('File too large. Maximum 2 MB.');
                                                return false;
                                            }
                                            // allow submit; modal will close when page reloads
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
                                                'moa_file' => 'MOA (from application)'
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
                                            $qm = $conn->prepare("SELECT moa_file, school_name, date_uploaded FROM moa WHERE LOWER(school_name) LIKE ? ORDER BY date_uploaded DESC LIMIT 1");
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
                                                        'label' => 'MOA (' . ($moa_record['school_name'] ?? 'School') . ')',
                                                        'file' => $moa_record['moa_file'],
                                                        'date' => $moa_record['date_uploaded'] ?? null
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
                                                    $dateLabel = !empty($req['date']) ? date('M j, Y', strtotime($req['date'])) : '';
                                                ?>
                                                    <div style="display:flex;align-items:center;justify-content:space-between;background:#f7f8fb;border-radius:8px;padding:10px 12px;">
                                                        <div style="display:flex;align-items:center;gap:8px;">
                                                            <div>
                                                                <div style="font-weight:600;color:#2f3459;"><?php echo htmlspecialchars($req['label']); ?></div>
                                                                <div style="color:#6b6f8b;font-size:13px;"><?php echo $fileName; ?> <?php if ($dateLabel) echo ' • ' . $dateLabel; ?></div>
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
                                    <p style="margin:0; color:#6b6f8b; font-size:16px;">No evaluations recorded yet.</p>
                            </section>
                            </div>
                    </div>
                    </div>
            </div>

            <script>
                    (function(){
                    const tabs = document.querySelectorAll('.tab-btn');
                    const panels = document.querySelectorAll('.tab-panel');
                    function activate(targetBtn){
                            const target = targetBtn.dataset.tab;
                            tabs.forEach(btn=>{
                            const isActive = btn === targetBtn;
                            btn.classList.toggle('active', isActive);
                            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            btn.style.background = isActive ? '#2f3459' : 'transparent';
                            btn.style.color = isActive ? '#fff' : '#2f3459';
                            btn.style.border = isActive ? '0' : '1px solid #e6e9f2';
                            });
                            panels.forEach(p=>{
                            p.style.display = p.id === target ? 'block' : 'none';
                            });
                    }
                    tabs.forEach(btn => btn.addEventListener('click', ()=> activate(btn)));
                    })();
            </script>
            </div>
    </div>

    <script>
      // confirm logout (profile top icon)
      (function(){
        var el = document.getElementById('top-logout');
        if (!el) return;
        el.addEventListener('click', function(e){
          e.preventDefault();
          if (confirm('Log out?')) {
            window.location.href = el.getAttribute('href');
          }
        });
      })();
    </script>
</body>
</html>