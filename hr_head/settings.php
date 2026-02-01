<?php
session_start();
require_once __DIR__ . '/../conn.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $message = 'You must be logged in to change password.';
        $messageType = 'error';
    } elseif (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All fields are required.';
        $messageType = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($new_password) < 12) {
        $message = 'Password must be at least 12 characters long.';
        $messageType = 'error';
    } else {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
        if (!$stmt) {
            $message = 'Database error: ' . $conn->error;
            $messageType = 'error';
        } else {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                $message = 'User not found.';
                $messageType = 'error';
            } elseif (!password_verify($current_password, $user['password'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                if (!$update_stmt) {
                    $message = 'Database error: ' . $conn->error;
                    $messageType = 'error';
                } else {
                    $update_stmt->bind_param('sh', $hashed_password, $user_id);

                    if ($update_stmt->execute()) {
                        $message = 'Password changed successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update password: ' . $update_stmt->error;
                        $messageType = 'error';
                    }
                    $update_stmt->close();
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --danger: #ef4444;
            --success: #16a34a;
            --border: #e5e7eb;
            --shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            --radius: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 560px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 32px 28px 28px;
        }

        .title {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .field {
            margin-bottom: 18px;
        }

        .label-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dcfce7;
            color: var(--success);
            font-size: 10px;
        }

        .input-wrap {
            position: relative;
        }

        .input {
            width: 100%;
            padding: 12px 44px 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            outline: none;
            font-size: 14px;
            transition: border 0.2s, box-shadow 0.2s;
            background: #fff;
        }

        .input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .input.invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        }

        .toggle-btn {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            padding: 4px;
            color: var(--muted);
        }

        .toggle-btn svg {
            width: 18px;
            height: 18px;
        }

        .helper-text {
            margin-top: 6px;
            font-size: 12px;
            color: var(--danger);
        }

        .rules {
            margin-top: 10px;
            display: grid;
            gap: 6px;
            font-size: 12px;
            color: var(--muted);
        }

        .rule {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rule .bullet {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d1d5db;
            flex-shrink: 0;
        }

        .rule.valid {
            color: var(--success);
        }

        .rule.valid .bullet {
            background: var(--success);
        }

        .submit-btn {
            margin-top: 24px;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            background: var(--primary);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert.success {
            background: #dcfce7;
            color: #14532d;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Change Password</div>

        <?php if (!empty($message)) : ?>
            <div class="alert <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
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
                    <input class="input" type="password" name="confirm_password" id="confirm_password"  required>
                    <button class="toggle-btn" type="button" data-target="confirm_password" aria-label="Show password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <button class="submit-btn" type="submit">Change Password</button>
        </form>
    </div>

    <script>
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordHint = document.getElementById('password_hint');
        const rules = {
            length: document.getElementById('rule-length'),
            upper: document.getElementById('rule-upper'),
            lower: document.getElementById('rule-lower'),
            special: document.getElementById('rule-special'),
            number: document.getElementById('rule-number')
        };

        function toggleRule(element, isValid) {
            element.classList.toggle('valid', isValid);
        }

        function validatePassword() {
            const value = newPasswordInput.value;
            const hasLength = value.length >= 12;
            const hasUpper = /[A-Z]/.test(value);
            const hasLower = /[a-z]/.test(value);
            const hasSpecial = /[^A-Za-z0-9]/.test(value);
            const hasNumber = /[0-9]/.test(value);

            toggleRule(rules.length, hasLength);
            toggleRule(rules.upper, hasUpper);
            toggleRule(rules.lower, hasLower);
            toggleRule(rules.special, hasSpecial);
            toggleRule(rules.number, hasNumber);

            const allValid = hasLength && hasUpper && hasLower && hasSpecial && hasNumber;
            newPasswordInput.classList.toggle('invalid', !allValid && value.length > 0);
            passwordHint.style.visibility = allValid || value.length === 0 ? 'hidden' : 'visible';
        }

        newPasswordInput.addEventListener('input', validatePassword);
        validatePassword();

        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                target.type = target.type === 'password' ? 'text' : 'password';
            });
        });

        confirmPasswordInput.addEventListener('input', () => {
            const isMatch = confirmPasswordInput.value.length > 0 && confirmPasswordInput.value === newPasswordInput.value;
            confirmPasswordInput.classList.toggle('invalid', !isMatch && confirmPasswordInput.value.length > 0);
        });

        // Redirect to login page after successful password change
        <?php if ($messageType === 'success') : ?>
            setTimeout(() => {
                window.location.href = '/CapstoneSample/login.php';
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>