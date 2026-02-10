<?php
session_start();
require_once __DIR__ . '/../conn.php';

$message = '';
$messageType = '';
$avatarUpdated = false;

// detect if settings is loaded inside an overlay iframe
$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// flash messages from POST-redirect-GET
if (isset($_SESSION['settings_flash'])) {
    $message = $_SESSION['settings_flash']['message'] ?? '';
    $messageType = $_SESSION['settings_flash']['type'] ?? '';
    unset($_SESSION['settings_flash']);
}
if (isset($_SESSION['settings_avatar'])) {
    $avatarUpdated = true;
    $avatarPath = $_SESSION['settings_avatar'];
    unset($_SESSION['settings_avatar']);
}

$user_id = $_SESSION['user_id'] ?? null;
$first_name = $middle_name = $last_name = $email = '';
$avatarCol = null;
// detect avatar-like column in users table
$resCols = $conn->query("SHOW COLUMNS FROM users");
if ($resCols) {
    $cols = [];
    while ($r = $resCols->fetch_assoc()) $cols[] = $r['Field'];
    $candidates = ['avatar','profile_pic','photo','picture'];
    foreach ($candidates as $c) if (in_array($c,$cols)) { $avatarCol = $c; break; }
}
if ($user_id) {
    // include avatar column in select if available
    $selectExtra = $avatarCol ? ", `$avatarCol`" : '';
    $uStmt = $conn->prepare('SELECT first_name, middle_name, last_name, email' . $selectExtra . ' FROM users WHERE user_id = ?');
        if ($uStmt) {
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $res = $uStmt->get_result();
        $ud = $res->fetch_assoc() ?: [];
        $first_name = $ud['first_name'] ?? '';
        $middle_name = $ud['middle_name'] ?? '';
        $last_name = $ud['last_name'] ?? '';
        $email = $ud['email'] ?? '';
        // if a recent avatar was set via session (after redirect), prefer it over DB value
        if ($avatarCol) {
            if (empty($avatarPath)) {
                $avatarPath = $ud[$avatarCol] ?? '';
            }
        }
        $uStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX helper: validate current password quickly
    if (isset($_POST['action']) && $_POST['action'] === 'check_current') {
        header('Content-Type: application/json');
        $cur = trim($_POST['current_password'] ?? '');
        $ok = false;
        		if ($user_id && $cur !== '') {
        		    $s = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
        		    if ($s) {
        		        $s->bind_param('i', $user_id);
        		        $s->execute();
        		        $r = $s->get_result()->fetch_assoc();
        		        $hash = $r['password'] ?? '';
        		        $ok = ($hash !== '' && (str_starts_with($hash, '$') ? password_verify($cur, $hash) : ($hash === $cur)));
        		        error_log('settings.php AJAX check: user_id=' . $user_id . ' input_len=' . strlen($cur) . ' hash_prefix=' . substr($hash,0,60) . ' ok=' . ($ok ? '1' : '0'));
        		        $s->close();
        		    }
        		}
        // include small debug fields to help diagnose mismatches in dev
        $debug = [
            'match' => $ok,
            'user_id' => $user_id ?: null,
            'has_hash' => !empty($hash),
            'hash_prefix' => $hash ? substr($hash, 0, 10) : ''
        ];
        echo json_encode($debug);
        exit;

    }
    $form_type = $_POST['form_type'] ?? '';
    if ($form_type === 'profile') {
        // update basic name fields (safe columns)
        if (!$user_id) {
            $message = 'Not logged in.'; $messageType = 'error';
        } else {
            // handle avatar upload if present
            if (!empty($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                $f = $_FILES['avatar'];
                $mime = mime_content_type($f['tmp_name']);
                if (strpos($mime, 'image/') === 0) {
                    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                    $destDir = __DIR__ . '/../uploads/avatars/';
                    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                    $filename = 'user_' . (int)$user_id . '_' . time() . '.' . ($ext ?: 'jpg');
                    $dest = $destDir . $filename;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        // use path relative to pages in `hr_head/` so parent iframe can load it
                        $relPath = '../uploads/avatars/' . $filename;
                        $avatarPath = $relPath;
                        $avatarUpdated = true;
                        // update user avatar column if available
                        if ($avatarCol) {
                            $uup = $conn->prepare("UPDATE users SET `$avatarCol` = ? WHERE user_id = ?");
                            if ($uup) { $uup->bind_param('si', $relPath, $user_id); $uup->execute(); $uup->close(); }
                        }
                    }
                }
            }
            $fn = trim($_POST['first_name'] ?? '');
            $mn = trim($_POST['middle_name'] ?? '');
            $ln = trim($_POST['last_name'] ?? '');
            $up = $conn->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ? WHERE user_id = ?');
            if ($up) {
                $up->bind_param('sssi', $fn, $mn, $ln, $user_id);
                if ($up->execute()) {
                    // use POST-Redirect-GET so the latest values (and uploaded avatar) are reloaded from DB
                    $_SESSION['settings_flash'] = ['message' => 'Profile updated.', 'type' => 'success'];
                    if (!empty($avatarUpdated) && !empty($avatarPath)) {
                        $_SESSION['settings_avatar'] = $avatarPath;
                    }
                    header('Location: settings.php');
                    exit;
                } else { $message = 'Failed to update profile.'; $messageType = 'error'; }
                $up->close();
            } else { $message = 'Database error: ' . $conn->error; $messageType = 'error'; }
        }
    } elseif ($form_type === 'password') {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (!$user_id) {
            $message = 'You must be logged in to change password.'; $messageType = 'error';
        } elseif (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All fields are required.'; $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match.'; $messageType = 'error';
        } elseif (
            !preg_match('/[A-Z]/', $new_password) ||
            !preg_match('/[a-z]/', $new_password) ||
            !preg_match('/[0-9]/', $new_password) ||
            !preg_match('/[^A-Za-z0-9]/', $new_password) ||
            strlen($new_password) < 12
        ) {
            $message = 'Password must be at least 12 characters and include uppercase, lowercase, number, and special character.'; $messageType = 'error';
        } else {
            $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
            if (!$stmt) {
                $message = 'Database error: ' . $conn->error; $messageType = 'error';
            } else {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                if (!$user) { $message = 'User not found.'; $messageType = 'error'; }
                else {
                    $stored = $user['password'] ?? '';
                    $curOk = ($stored !== '' && (str_starts_with($stored, '$') ? password_verify($current_password, $stored) : ($stored === $current_password)));
                    error_log('settings.php password change: user_id=' . $user_id . ' hash_prefix=' . substr($stored,0,60) . ' verify=' . ($curOk ? '1' : '0'));
                    if (!$curOk) { $message = 'Current password is incorrect.'; $messageType = 'error'; }
                    else {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $update_stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                    if (!$update_stmt) { $message = 'Database error: ' . $conn->error; $messageType = 'error'; }
                    else {
                        $update_stmt->bind_param('si', $hashed_password, $user_id);
                                if ($update_stmt->execute()) {
                                    // use POST-Redirect-GET for password change as well and show centered confirmation
                                    $_SESSION['settings_flash'] = ['message' => 'Password changed successfully.', 'type' => 'success'];
                                    // clear any pending avatar session
                                    header('Location: settings.php?tab=password');
                                    exit;
                                } else { $message = 'Failed to update password: ' . $update_stmt->error; $messageType = 'error'; }
                        $update_stmt->close();
                        // if schema supports tracking force_password_change / password_changed_at, update them
                        $colsAvailable = [];
                        $resCols2 = $conn->query("SHOW COLUMNS FROM users");
                        if ($resCols2) {
                            while ($rcol = $resCols2->fetch_assoc()) $colsAvailable[] = $rcol['Field'];
                        }
                        if (in_array('force_password_change', $colsAvailable) || in_array('password_changed_at', $colsAvailable)) {
                            $setParts = [];
                            if (in_array('force_password_change', $colsAvailable)) $setParts[] = "force_password_change = 0";
                            if (in_array('password_changed_at', $colsAvailable)) $setParts[] = "password_changed_at = NOW()";
                            if (!empty($setParts)) {
                                $updSql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE user_id = ?';
                                $u2 = $conn->prepare($updSql);
                                if ($u2) { $u2->bind_param('i', $user_id); $u2->execute(); $u2->close(); }
                            }
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <style>
        :root{--bg:#f4f6fb;--card:#fff;--muted:#6b7280;--accent:#2f3850;--radius:12px;--shadow:0 18px 40px rgba(16,24,40,0.08)}
        *{box-sizing:border-box;margin:0;padding:0;font-family:Poppins,Segoe UI,Arial,sans-serif}
        html,body{height:100%}
        body{background:var(--bg);color:#111;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:1180px;background:var(--card);border-radius:16px;padding:18px;box-shadow:var(--shadow);display:grid;grid-template-columns:260px 1fr;gap:18px;position:relative}
        .left{background:var(--accent);color:#fff;border-radius:10px;padding:22px;display:flex;flex-direction:column;align-items:center;gap:16px}
        .avatar{width:96px;height:96px;border-radius:50%;background:#cfd3db;display:flex;align-items:center;justify-content:center;font-size:36px}
        .name{font-weight:700}
        .nav-tabs{display:flex;gap:8px;width:100%;background:transparent;margin-top:8px}
        .tab{flex:1;padding:10px;border-radius:10px;background:rgba(255,255,255,0.08);text-align:center;cursor:pointer;font-weight:600}
        .tab.active{background:#fff;color:var(--accent)}
        .panel{padding:6px 6px 18px 6px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
        label{display:block;font-size:13px;margin-bottom:6px;color:#333;font-weight:600}
        input[type=text], input[type=email], input[type=password], textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #e6e6e9;font-size:14px}
        textarea{min-height:56px}
        .actions{margin-top:12px;display:flex;justify-content:flex-end}
        .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer}
        .btn.primary{background:#2f3850;color:#fff}
        .btn.ghost{background:transparent;border:1px solid #eee}
        .close-btn{position:absolute;right:18px;top:18px;width:40px;height:40px;border-radius:10px;background:#fff;border:0;box-shadow:0 6px 18px rgba(0,0,0,0.06);cursor:pointer}
        .alert{padding:10px;border-radius:8px;margin-bottom:12px;font-size:14px;text-align:center;max-width:520px;margin-left:auto;margin-right:auto}
        .alert.success{background:#dcfce7;color:#14532d}
        .alert.error{background:#fee2e2;color:#991b1b}
        input.prefilled{color:#6b7280}
        /* shared card styling for profile/password tabs */
        .pw-card,.profile-card{width:100%;max-width:560px;background:#fff;border-radius:16px;box-shadow:0 12px 28px rgba(16,24,40,0.12);padding:24px}
        .pw-title,.profile-title{font-size:24px;font-weight:600;margin-bottom:18px;color:#111}
        .pw-card .field,.profile-card .field{margin-bottom:18px}
        .pw-card .label-row,.profile-card .label-row{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#111;margin-bottom:8px}
        .pw-card .status-dot{width:14px;height:14px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#dcfce7;color:#16a34a;font-size:10px}
        .pw-card .input-wrap,.profile-card .input-wrap{position:relative}
        .pw-card .input,.profile-card .input{width:100%;padding:12px 44px 12px 14px;border-radius:10px;border:1px solid #e5e7eb;outline:none;font-size:14px;transition:border 0.2s, box-shadow 0.2s;background:#fff}
        .pw-card .input:focus,.profile-card .input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.15)}
        .pw-card .input.invalid,.profile-card .input.invalid{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,0.12)}
        .profile-card .input.readonly{background:#fafafa;color:#6b7280}
        .pw-card .toggle-btn{position:absolute;top:50%;right:12px;transform:translateY(-50%);border:none;background:transparent;cursor:pointer;padding:4px;color:#6b7280}
        .pw-card .toggle-btn svg{width:18px;height:18px}
        .pw-card .helper-text{margin-top:6px;font-size:12px;color:#ef4444;min-height:14px}
        .pw-card .rules{margin-top:10px;display:grid;gap:6px;font-size:12px;color:#6b7280}
        .pw-card .rule{display:flex;align-items:center;gap:8px}
        .pw-card .rule .bullet{width:8px;height:8px;border-radius:50%;background:#d1d5db;flex-shrink:0}
        .pw-card .rule.valid{color:#16a34a}
        .pw-card .rule.valid .bullet{background:#16a34a}
        .pw-actions{margin-top:18px;display:flex;gap:10px}
        .profile-actions{margin-top:18px;display:flex;justify-content:flex-end}
        .submit-btn{flex:1;padding:12px;border-radius:12px;border:none;background:#3b82f6;color:#fff;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.2s ease}
        .profile-actions .submit-btn{flex:0 0 auto}
        .submit-btn:hover{background:#2563eb}
        .ghost-btn{padding:12px 16px;border-radius:12px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:14px;font-weight:600;cursor:pointer}
        @media (max-width:980px){.card{grid-template-columns:1fr;max-width:calc(100% - 32px)}}
    </style>
</head>
<body style="background:transparent;">
    <?php if (!empty($message) && $messageType === 'success'): ?>
    <div id="settingsFullMsg" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:9999;pointer-events:none;">
        <div style="background:rgba(0,0,0,0.75);color:#fff;padding:18px 28px;border-radius:10px;font-size:18px;pointer-events:auto;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <script>setTimeout(()=>{try{document.getElementById('settingsFullMsg')?.remove();}catch(e){}},2500);</script>
    <?php endif; ?>
    <div class="outer" style="width:100%;max-width:1180px;margin:18px auto;padding:0 18px;">
        <div class="card-profile" role="region" aria-label="Profile Settings" style="background:transparent;">
            <div class="white-card" style="background:#fff;border-radius:18px;padding:20px;max-width:1000px;width:1000px;height:640px;margin:0 auto;display:grid;grid-template-rows:auto 1fr;gap:12px;align-items:stretch;box-shadow:0 18px 40px rgba(16,24,40,0.12);position:relative;overflow:auto;">
                <button id="settings_close_btn" class="view-close" type="button" aria-label="Close" style="position:absolute;top:12px;right:12px;width:40px;height:40px;border-radius:10px;background:#fff;border:0;box-shadow:0 6px 18px rgba(0,0,0,0.06);cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center">✕</button>

                <div style="display:flex;align-items:flex-start;gap:28px">
    <?php if (!$embedded): ?>
                    <div style="width:220px;flex-shrink:0;margin-left:50px">
                        <div style="position:relative;display:flex;align-items:center;gap:14px">
                            <div style="width:96px;height:96px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,0.04);position:relative;overflow:hidden">
                                <?php $displayAvatar = $avatarPath ? $avatarPath : '';?>
                                <img id="avatarImg" src="<?php echo htmlspecialchars($displayAvatar); ?>" alt="avatar" style="width:96px;height:96px;object-fit:cover;border-radius:50%;background:#f1f5f9;" onerror="this.style.display='none'">
                                <div id="avatarSvg" style="width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-3-3.87"></path><path d="M4 21v-2a4 4 0 0 1 3-3.87"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                </div>
                                <button id="btnCamera" type="button" title="Change photo" style="position:absolute;right:6px;bottom:6px;width:34px;height:34px;border-radius:50%;background:#fff;border:0;box-shadow:0 6px 18px rgba(0,0,0,0.08);display:flex;align-items:center;justify-content:center;cursor:pointer">📷</button>
                            </div>
                        </div>
                        <div style="margin-top:12px;font-weight:700;font-size:16px;color:#111"><?php echo htmlspecialchars(trim(($first_name.' '.($middle_name ? $middle_name.' ' : '').$last_name))); ?></div>
                    </div>
    <?php else: ?>
                <div style="padding:20px;overflow:auto;height:100%;">
                    <!-- embedded mode: omit outer white-card chrome and close button -->
                    <div style="display:flex;align-items:flex-start;gap:28px">
    <?php endif; ?>

                    <div style="flex:1">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                            <div style="display:flex;gap:12px">
                                <div id="tabProfile" class="tab active" style="padding:8px 12px;border-bottom:3px solid #2f3850;cursor:pointer;font-weight:700">Edit Profile</div>
                                <div id="tabPassword" class="tab" style="padding:8px 12px;cursor:pointer;color:#6b7280">Change Password</div>
                            </div>
                        </div>
                        <?php if (!empty($message)): ?>
                            <div class="alert <?php echo ($messageType === 'success') ? 'success' : 'error'; ?>" id="settingsFlash">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <div id="panelProfile">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="form_type" value="profile">
                                <input id="avatarInput" name="avatar" type="file" accept="image/*" style="display:none">
                                <div class="profile-card">
                                    <div class="profile-title">Edit Profile</div>

                                    <div style="display:flex;gap:18px;align-items:flex-start;margin-bottom:0">
                                        <div class="field" style="flex:1">
                                            <div class="label-row"><span>First Name</span></div>
                                            <div class="input-wrap">
                                                <input id="firstName" class="input prefill-target" type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                                            </div>
                                        </div>
                                        <div class="field" style="flex:1">
                                            <div class="label-row"><span>Last Name</span></div>
                                            <div class="input-wrap">
                                                <input id="lastName" class="input prefill-target" type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="field" style="max-width:520px">
                                        <div class="label-row"><span>Email Address</span></div>
                                        <div class="input-wrap">
                                            <input id="emailAddr" class="input prefill-target readonly" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
                                        </div>
                                    </div>

                                    <!-- Contact number and Personal Address removed per request -->

                                    <div class="profile-actions">
                                        <button type="submit" class="submit-btn">SAVE CHANGES</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div id="panelPassword" style="display:none">
                            <form method="POST" id="pwForm">
                                <input type="hidden" name="form_type" value="password">
                                <div class="pw-card">
                                    <div class="pw-title">Change Password</div>

                                    <div class="field">
                                        <div class="label-row">
                                            <span>Old Password</span>
                                            <span class="status-dot">✓</span>
                                        </div>
                                        <div class="input-wrap">
                                            <input class="input" type="password" name="current_password" id="current_password" required>
                                            <button class="toggle-btn" type="button" data-target="current_password" aria-label="Show password">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="helper-text" id="currentPwMsg"></div>
                                    </div>

                                    <div class="field">
                                        <div class="label-row">
                                            <span>New Password</span>
                                        </div>
                                        <div class="input-wrap">
                                            <input class="input" type="password" name="new_password" id="new_password" required>
                                            <button class="toggle-btn" type="button" data-target="new_password" aria-label="Show password">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="helper-text" id="password_hint">Please add all necessary characters to create safe password.</div>
                                        <div class="rules">
                                            <div class="rule" id="rule-length"><span class="bullet"></span>Minimum characters 12</div>
                                            <div class="rule" id="rule-upper"><span class="bullet"></span>One uppercase character</div>
                                            <div class="rule" id="rule-lower"><span class="bullet"></span>One lowercase character</div>
                                            <div class="rule" id="rule-special"><span class="bullet"></span>One special character</div>
                                            <div class="rule" id="rule-number"><span class="bullet"></span>One number</div>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <div class="label-row">
                                            <span>Confirm New Password</span>
                                        </div>
                                        <div class="input-wrap">
                                            <input class="input" type="password" name="confirm_password" id="confirm_password" required>
                                            <button class="toggle-btn" type="button" data-target="confirm_password" aria-label="Show password">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="helper-text" id="confirmPwMsg"></div>
                                    </div>

                                    <div class="pw-actions">
                                        <button id="pwDiscard" type="button" class="ghost-btn">Discard</button>
                                        <button id="pwSubmit" type="submit" class="submit-btn" disabled>Change Password</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // transparent when inside iframe
        try{ if (window.parent && window.parent !== window) { document.documentElement.style.background='transparent'; document.body.style.background='transparent'; } }catch(e){}

        // tabs
        const tProfile = document.getElementById('tabProfile');
        const tPassword = document.getElementById('tabPassword');
        const pProfile = document.getElementById('panelProfile');
        const pPassword = document.getElementById('panelPassword');
        function showTab(name){
            // check for unsaved changes before switching tabs
            if (window.hasUnsavedChanges && window.hasUnsavedChanges()) {
                if (!confirm('You have unsaved changes. Switching tabs will discard them. Proceed?')) return;
                // discard changes
                try { if (window.__restoreSettingsOriginals) window.__restoreSettingsOriginals(); } catch(e){}
            }
            if(name==='profile'){ tProfile.classList.add('active'); tProfile.style.borderBottom='3px solid #2f3850'; tProfile.style.color=''; tPassword.classList.remove('active'); tPassword.style.borderBottom='0'; tPassword.style.color='#6b7280'; pProfile.style.display='block'; pPassword.style.display='none'; }
            else { tPassword.classList.add('active'); tPassword.style.borderBottom='3px solid #2f3850'; tPassword.style.color=''; tProfile.classList.remove('active'); tProfile.style.borderBottom='0'; tProfile.style.color='#6b7280'; pProfile.style.display='none'; pPassword.style.display='block'; }
        }
        tProfile.addEventListener('click', ()=>showTab('profile'));
        tPassword.addEventListener('click', ()=>showTab('password'));
        // open the requested tab when page loads (e.g. ?tab=password after PRG)
        (function(){ try{ const params = new URLSearchParams(location.search); const tab = params.get('tab'); if (tab==='password') showTab('password'); }catch(e){} })();

        // avatar input wiring: click camera -> open file chooser; when file chosen, preview only (file will be uploaded on Save)
        (function(){
            const btn = document.getElementById('btnCamera');
            const input = document.getElementById('avatarInput');
            const avatarImg = document.getElementById('avatarImg');
            const avatarSvg = document.getElementById('avatarSvg');
            if (!btn || !input) return;
            btn.addEventListener('click', function(){ input.click(); });
            let previewDirty = false;
            const originalAvatar = (avatarImg && avatarImg.src) ? avatarImg.src : '';
            if (avatarImg) avatarImg.dataset.original = originalAvatar;
            input.addEventListener('change', function(){
                const f = this.files && this.files[0];
                if (!f) return;
                const reader = new FileReader();
                reader.onload = function(e){
                    if (avatarImg) { avatarImg.src = e.target.result; avatarImg.style.display='block'; }
                    if (avatarSvg) avatarSvg.style.display='none';
                    previewDirty = true;
                };
                reader.readAsDataURL(f);
                // do not upload immediately; user will click SAVE CHANGES to submit the form including this file
            });
        })();

        // helper to detect unsaved changes: edited inputs or avatar preview
        window.hasUnsavedChanges = function(){
            try{
                const targets = document.querySelectorAll('.prefill-target');
                for (let i=0;i<targets.length;i++){
                    const el = targets[i];
                    if (el.dataset && el.dataset.modified === '1') return true;
                    if (typeof el.dataset.originalValue !== 'undefined' && el.value !== el.dataset.originalValue) return true;
                }
                const inp = document.getElementById('avatarInput');
                if (inp && inp.files && inp.files.length) return true;
                const aimg = document.getElementById('avatarImg');
                if (aimg && aimg.dataset && typeof aimg.dataset.original !== 'undefined' && aimg.src !== aimg.dataset.original) return true;
                // check password fields
                const pwIds = ['current_password', 'new_password', 'confirm_password'];
                for (let id of pwIds) {
                    const el = document.getElementById(id);
                    if (el && el.value.trim()) return true;
                }
            }catch(e){}
            return false;
        };

        // password strength client-side validation
        (function(){
            const newPw = document.getElementById('new_password');
            const confPw = document.getElementById('confirm_password');
            const curPw = document.getElementById('current_password');
            const submitBtn = document.getElementById('pwSubmit');
            const discardBtn = document.getElementById('pwDiscard');
            const currentMsg = document.getElementById('currentPwMsg');
            const confirmPwMsg = document.getElementById('confirmPwMsg');
            const pwHint = document.getElementById('password_hint');
            const rules = {
                length: document.getElementById('rule-length'),
                upper: document.getElementById('rule-upper'),
                lower: document.getElementById('rule-lower'),
                special: document.getElementById('rule-special'),
                number: document.getElementById('rule-number')
            };
            let currentValid = false;
            let checkTimer = null;
            const pwForm = document.getElementById('pwForm');

            function checkCurrentServer(){
                if (!curPw || !curPw.value) { currentValid = false; if(currentMsg) currentMsg.textContent=''; check(); return; }
                // debounce
                if (checkTimer) clearTimeout(checkTimer);
                    checkTimer = setTimeout(()=>{
                        const valueSent = curPw.value.trim();
                        fetch('settings.php', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'check_current', current_password: valueSent})})
                            .then(r=>r.json())
                            .then(j=>{
                                // ensure response applies to the current input (avoid race)
                                if (curPw.value !== valueSent) return; 
                                currentValid = !!j.match;
                                if (currentMsg) currentMsg.textContent = currentValid ? '' : 'Current password does not match.';
                                check();
                            }).catch(e=>{ if (curPw.value === valueSent) { currentValid = false; if (currentMsg) currentMsg.textContent=''; check(); } });
                    }, 350);
            }

            function toggleRule(element, isValid){
                if (!element) return;
                element.classList.toggle('valid', isValid);
            }

            function check(){
                const v = newPw && newPw.value ? newPw.value : '';
                const hasLength = v.length >= 12;
                const hasUpper = /[A-Z]/.test(v);
                const hasLower = /[a-z]/.test(v);
                const hasSpecial = /[^A-Za-z0-9]/.test(v);
                const hasNumber = /[0-9]/.test(v);

                toggleRule(rules.length, hasLength);
                toggleRule(rules.upper, hasUpper);
                toggleRule(rules.lower, hasLower);
                toggleRule(rules.special, hasSpecial);
                toggleRule(rules.number, hasNumber);

                const allValid = hasLength && hasUpper && hasLower && hasSpecial && hasNumber;
                if (newPw) newPw.classList.toggle('invalid', !allValid && v.length > 0);
                if (pwHint) pwHint.style.visibility = allValid || v.length === 0 ? 'hidden' : 'visible';

                const confirmMatch = confPw && confPw.value === v && v.length > 0;
                if (confirmPwMsg) confirmPwMsg.textContent = confirmMatch || !confPw.value ? '' : 'Passwords do not match.';
                if (confPw) confPw.classList.toggle('invalid', !confirmMatch && confPw.value.length > 0);

                const enable = allValid && confirmMatch && currentValid === true;
                if (submitBtn) submitBtn.disabled = !enable;
            }
            if (newPw) newPw.addEventListener('input', check);
            if (confPw) confPw.addEventListener('input', check);
            if (curPw) { curPw.addEventListener('input', function(){ currentValid = false; if(currentMsg) currentMsg.textContent=''; checkCurrentServer(); }); }
            if (discardBtn) discardBtn.addEventListener('click', function(){ if (newPw) newPw.value=''; if (confPw) confPw.value=''; if (curPw) curPw.value=''; if (confirmPwMsg) confirmPwMsg.textContent=''; if (currentMsg) currentMsg.textContent=''; check(); });
            // prevent submit if password requirements not met
            if (pwForm) pwForm.addEventListener('submit', function(e){
                const v = newPw && newPw.value ? newPw.value : '';
                const hasLength = v.length >= 12;
                const hasUpper = /[A-Z]/.test(v);
                const hasLower = /[a-z]/.test(v);
                const hasSpecial = /[^A-Za-z0-9]/.test(v);
                const hasNumber = /[0-9]/.test(v);
                const allValid = hasLength && hasUpper && hasLower && hasSpecial && hasNumber;
                if (!allValid) {
                    e.preventDefault();
                    if (pwHint) pwHint.style.visibility = 'visible';
                    return false;
                }
            });
        })();

        // password visibility toggle handlers
        (function(){
            function togglePassword(targetId, btn){
                var inp = document.getElementById(targetId);
                if (!inp) return;
                if (inp.type === 'password'){
                    inp.type = 'text';
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                    btn.setAttribute('aria-label','Hide password');
                } else {
                    inp.type = 'password';
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    btn.setAttribute('aria-label','Show password');
                }
            }
            try{
                document.querySelectorAll('.toggle-btn').forEach(function(b){
                    b.addEventListener('click', function(e){ togglePassword(b.dataset.target, b); });
                });
            }catch(e){}
        })();

        // prefill muted text behaviour: inputs show muted color until user types
        (function(){
            const targets = document.querySelectorAll('.prefill-target');
            targets.forEach(function(el){
                // save original value so we can restore if user closes without saving
                el.dataset.originalValue = el.value || '';
                // if value exists, keep muted (prefilled). if empty, ensure normal color
                if (el.value && el.value.trim() !== '') {
                    el.classList.add('prefilled');
                } else {
                    el.classList.remove('prefilled');
                }
                // when user types, remove muted styling
                el.addEventListener('input', function(){
                    el.classList.remove('prefilled');
                    el.dataset.modified = '1';
                });
            });
            // auto-hide flash after 3 seconds
            const flash = document.getElementById('settingsFlash');
            if (flash){ setTimeout(()=>{ try{ flash.style.display='none' }catch(e){} }, 3000); }

            // helper to restore original form state (used on close/unload when not saved)
            function restoreOriginals(){
                try{
                    const t = document.querySelectorAll('.prefill-target');
                    t.forEach(function(el){
                        if (el && typeof el.dataset.originalValue !== 'undefined'){
                            el.value = el.dataset.originalValue;
                            if (el.value && el.value.trim() !== '') el.classList.add('prefilled'); else el.classList.remove('prefilled');
                            delete el.dataset.modified;
                        }
                    });
                    const inp = document.getElementById('avatarInput');
                    const aimg = document.getElementById('avatarImg');
                    const asvg = document.getElementById('avatarSvg');
                    if (inp) inp.value = '';
                    if (aimg && aimg.dataset && typeof aimg.dataset.original !== 'undefined'){
                        aimg.src = aimg.dataset.original || '';
                        if (asvg) asvg.style.display = (aimg && aimg.src) ? 'none' : 'flex';
                    }
                    // clear password fields
                    const pwIds = ['current_password', 'new_password', 'confirm_password'];
                    for (let id of pwIds) {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    }
                }catch(e){}
            }

            // expose restore and set up beforeunload handler
            window.__restoreSettingsOriginals = restoreOriginals;
            // before unload, warn if there are unsaved changes; otherwise restore originals
            window.addEventListener('beforeunload', function(e){
                try{
                    if (window.__settingsSubmitting) return;
                    if (window.hasUnsavedChanges && window.hasUnsavedChanges()){
                        e.preventDefault();
                        e.returnValue = '';
                    } else {
                        restoreOriginals();
                    }
                }catch(ex){}
            });
            // mark forms as submitting so beforeunload won't prompt on legitimate saves
            try{ document.querySelectorAll('form').forEach(f=>f.addEventListener('submit', ()=>{ window.__settingsSubmitting = true; })); }catch(e){}
        })();

        // close button -> parent hook; warn about unsaved changes then optionally discard and close
        (function(){ const closeBtn = document.getElementById('settings_close_btn'); if(!closeBtn) return; closeBtn.addEventListener('click', function(){
                try{
                    // check for unsaved changes
                    if (window.hasUnsavedChanges && window.hasUnsavedChanges()){
                        var proceed = confirm('You have unsaved changes. Do you want to proceed without saving? Unsaved changes will be lost.');
                        if (!proceed) return; // user cancelled
                        // user confirmed: restore originals (discard changes)
                        try{ if (window.__restoreSettingsOriginals) window.__restoreSettingsOriginals(); }catch(e){}
                    } else {
                        // no unsaved changes: ensure clean state
                        try{ if (window.__restoreSettingsOriginals) window.__restoreSettingsOriginals(); }catch(e){}
                    }
                    if(window.parent && window.parent!==window && typeof window.parent.closeSettingsOverlay==='function'){ window.parent.closeSettingsOverlay(); return; }
                }catch(e){}
                if(history.length>1) history.back(); else window.location.href='hr_head_home.php'; });
        })();
    </script>
    <?php if (!empty($avatarUpdated) && $avatarUpdated && !empty($avatarPath)): ?>
    <script>
        try{
            if (window.parent && window.parent !== window){
                const pi = window.parent.document.querySelector('.profile img');
                if (pi) pi.src = '<?php echo htmlspecialchars($avatarPath, ENT_QUOTES); ?>';
            }
        }catch(e){}
    </script>
    <?php endif; ?>

        <!-- ADDED: confirm for logout links inside settings (redirect top window when inside iframe) -->
        <script>
            (function(){
                function attachConfirm(id){
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.addEventListener('click', function(e){
                        e.preventDefault();
                        if (confirm('Logout?')) {
                            var href = this.getAttribute('href') || '../logout.php';
                            try { window.top.location = href; } catch(ex) { window.location = href; }
                        }
                    });
                }
                attachConfirm('btnLogout');
                attachConfirm('top-logout');
            })();
        </script>
</body>
</html>