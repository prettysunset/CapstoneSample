<?php
$status = $_GET['status'] ?? '';
$email = $_GET['email'] ?? '';
$isSent = $status === 'sent';
$isInvalid = $status === 'invalid';
$isExpired = $status === 'expired';
$isError = $status === 'error';
$isPwMismatch = $status === 'pw_mismatch';
$isPwStrengthFail = $status === 'pw_strength_fail';
$isOtpRecent = $status === 'otp_recent';
$isOtpActive = $status === 'otp_active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="flex flex-col items-center mb-6">
                <h1 class="mt-4 text-2xl font-semibold text-slate-800">Enter OTP</h1>
                <p class="text-sm text-slate-500 text-center mt-1">We sent a 6-digit code to your email.</p>
            </div>

            <?php if ($isSent): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
                    OTP sent. Check your email.
                </div>
            <?php elseif ($isOtpRecent): ?>
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-700 text-sm">
                    A code was recently sent. Please check your email.
                </div>
            <?php elseif ($isOtpActive): ?>
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-700 text-sm">
                    There is an active code for this account. Please enter the code from your email.
                </div>
            <?php elseif ($isInvalid): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    Invalid code. Please check the code and try again.
                </div>
            <?php elseif ($isExpired): ?>
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-700 text-sm">
                    The code has expired. Please request a new one.
                </div>
            <?php elseif ($isError): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    Something went wrong. Please try again.
                </div>
            <?php endif; ?>

            <form method="POST" action="verify_otp.php" class="space-y-4" id="resetForm">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                <div>
                    <label for="otp" class="block text-sm font-medium text-slate-700 mb-1">OTP Code</label>
                    <input id="otp" name="otp" type="text" maxlength="6" required
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div style="position:relative">
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                    <div style="position:relative">
                        <input id="password" name="password" type="password" required
                               class="w-full rounded-lg border border-slate-300 px-4 py-2.5" style="padding-right:44px;">
                        <button type="button" id="togglePwd" aria-label="Show password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:6px">
                            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.65 18.65 0 0 1 4.11-5.05"></path>
                                <path d="M1 1l22 22"></path>
                                <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-2 text-sm text-slate-500">
                        <label id="lbl_len"><input type="checkbox" id="chk_len" disabled> <span id="txt_len">At least 8 characters</span></label><br>
                        <label id="lbl_upper"><input type="checkbox" id="chk_upper" disabled> <span id="txt_upper">At least 1 uppercase</span></label><br>
                        <label id="lbl_num"><input type="checkbox" id="chk_num" disabled> <span id="txt_num">At least 1 number</span></label>
                    </div>
                </div>

                <div style="position:relative">
                    <label for="confirm" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                    <div style="position:relative">
                        <input id="confirm" name="confirm" type="password" required
                               class="w-full rounded-lg border border-slate-300 px-4 py-2.5" style="padding-right:44px;">
                        <button type="button" id="toggleConfirm" aria-label="Show confirm password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:6px">
                            <svg id="eyeOpenC" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg id="eyeClosedC" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.65 18.65 0 0 1 4.11-5.05"></path>
                                <path d="M1 1l22 22"></path>
                                <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="pwMismatchMsg" class="mt-2 text-sm text-red-600" style="display:none;">Passwords do not match.</div>
                </div>

                <button type="submit" id="submitBtn" class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 transition">Reset Password</button>
            </form>

            <script>
            (function(){
                var pwd = document.getElementById('password');
                var confirm = document.getElementById('confirm');
                var chkLen = document.getElementById('chk_len');
                var chkUpper = document.getElementById('chk_upper');
                var chkNum = document.getElementById('chk_num');
                var txtLen = document.getElementById('txt_len');
                var txtUpper = document.getElementById('txt_upper');
                var txtNum = document.getElementById('txt_num');
                var lblLen = document.getElementById('lbl_len');
                var lblUpper = document.getElementById('lbl_upper');
                var lblNum = document.getElementById('lbl_num');
                var submitBtn = document.getElementById('submitBtn');
                var form = document.getElementById('resetForm');
                var pwMismatchMsg = document.getElementById('pwMismatchMsg');
                var togglePwd = document.getElementById('togglePwd');
                var toggleConfirm = document.getElementById('toggleConfirm');
                var eyeOpen = document.getElementById('eyeOpen');
                var eyeClosed = document.getElementById('eyeClosed');
                var eyeOpenC = document.getElementById('eyeOpenC');
                var eyeClosedC = document.getElementById('eyeClosedC');

                function validate(){
                    var v = pwd.value || '';
                    var okLen = v.length >= 8;
                    var okUpper = /[A-Z]/.test(v);
                    var okNum = /[0-9]/.test(v);
                    chkLen.checked = okLen;
                    chkUpper.checked = okUpper;
                    chkNum.checked = okNum;
                    // color labels green when satisfied
                    txtLen.style.color = okLen ? '#16a34a' : '#64748b';
                    txtUpper.style.color = okUpper ? '#16a34a' : '#64748b';
                    txtNum.style.color = okNum ? '#16a34a' : '#64748b';
                    // enable submit only if all conditions met and confirm matches
                    var ok = okLen && okUpper && okNum && (v === (confirm.value || '')) && v.length>0;
                    submitBtn.disabled = !ok;
                    // mismatch message
                    if (confirm.value && v !== confirm.value) {
                        pwMismatchMsg.style.display = 'block';
                    } else {
                        pwMismatchMsg.style.display = 'none';
                    }
                }

                pwd.addEventListener('input', validate);
                confirm.addEventListener('input', validate);

                // eye toggle handlers
                function toggleVisibility(inputEl, openSvg, closedSvg) {
                    if (inputEl.type === 'password') {
                        inputEl.type = 'text';
                        openSvg.style.display = 'none';
                        closedSvg.style.display = 'inline';
                    } else {
                        inputEl.type = 'password';
                        openSvg.style.display = 'inline';
                        closedSvg.style.display = 'none';
                    }
                }
                togglePwd.addEventListener('click', function(){ toggleVisibility(pwd, eyeOpen, eyeClosed); });
                toggleConfirm.addEventListener('click', function(){ toggleVisibility(confirm, eyeOpenC, eyeClosedC); });

                // initial run
                validate();
            })();
            </script>

            <script>
            // when OTP page loads with status=sent, record timestamp so forgot_password can disable resend
            (function(){
                var params = new URLSearchParams(window.location.search);
                var st = params.get('status');
                if ((st === 'sent' || st === 'otp_recent' || st === 'otp_active') && params.get('email')) {
                    try {
                        localStorage.setItem('ojtms_otp_sent_at', Math.floor(Date.now()/1000));
                    } catch(e){}
                }
            })();
            </script>

            <div class="mt-6 text-center">
                <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Back</a>
            </div>
        </div>
        
    </div>
</body>
</html>
