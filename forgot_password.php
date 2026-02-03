<?php
$status = $_GET['status'] ?? '';
$isSuccess = $status === 'success';
$isError = $status === 'error';
$isNotFound = $status === 'not_found';
$isNotUser = $status === 'not_user';
$isMailError = $status === 'mail_error';
$isMissingConfig = $status === 'missing_config';
$isOtpActive = $status === 'otp_active';
$isOtpRecent = $status === 'otp_recent';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="flex flex-col items-center mb-6">
              
                <h1 class="mt-4 text-2xl font-semibold text-slate-800">Forgot Password</h1>
                <p class="text-sm text-slate-500 text-center mt-1">Enter your email and we’ll send a verification code (OTP).</p>
            </div>

            <?php if ($isSuccess) : ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
                    Verification code sent successfully. Please check your email (including spam).
                </div>
            <?php elseif ($isNotFound) : ?>
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-700 text-sm">
                    Email not found. Please check the address you entered.
                </div>
            <?php elseif ($isNotUser) : ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    This email is registered as a student but not linked to a user account. Contact the administrator.
                </div>
            <?php elseif ($isMissingConfig) : ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    Email configuration missing. Copy <strong>config/email_config.php.example</strong> to <strong>config/email_config.php</strong> and fill SMTP credentials.
                </div>
            <?php elseif ($isMailError) : ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    Failed to send email. Check <strong>logs/mailer_error.log</strong> for details and verify SMTP settings.
                </div>
            <?php elseif ($isError) : ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    Something went wrong. Please try again.
                </div>
            <?php elseif ($isOtpActive) : ?>
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-700 text-sm">
                    An active reset code has already been sent to this email. Please check your inbox or wait until it expires before requesting a new code.
                </div>
            <?php elseif ($isOtpRecent) : ?>
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-700 text-sm">
                    A code was just sent. Please wait a moment before requesting another code.
                </div>
            <?php endif; ?>

            <form id="forgotForm" method="POST" action="send.php" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        placeholder="you@example.com"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <button
                    id="sendOtpBtn"
                    type="button"
                    class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 transition"
                >
                    Send OTP
                </button>
            </form>

            <div id="otpCountdown" class="mt-3 text-center text-sm text-yellow-700" style="display:none;"></div>

            <script>
            (function(){
                var btn = document.querySelector('form[action="send.php"] button[type="submit"]');
                var countdownEl = document.getElementById('otpCountdown');
                var key = 'ojtms_otp_sent_at';
                function startCountdown(sentAt){
                    var sent = parseInt(sentAt,10) || 0;
                    var now = Math.floor(Date.now()/1000);
                    var diff = 60 - (now - sent);
                    if (diff <= 0) {
                        btn.disabled = false;
                        countdownEl.style.display = 'none';
                        return;
                    }
                    btn.disabled = true;
                    countdownEl.style.display = 'block';
                    countdownEl.textContent = 'You can request a new code in ' + diff + 's.';
                    var iv = setInterval(function(){
                        diff--; if (diff <= 0) { clearInterval(iv); btn.disabled = false; countdownEl.style.display='none'; localStorage.removeItem(key); return; }
                        countdownEl.textContent = 'You can request a new code in ' + diff + 's.';
                    }, 1000);
                }
                try {
                    var s = localStorage.getItem(key);
                    if (s) startCountdown(s);
                } catch(e){}
            })();
            </script>

            <script>
            // Ensure Send OTP button actually submits the form and provides basic client-side validation/UX
            (function(){
                var btn = document.getElementById('sendOtpBtn');
                var form = document.getElementById('forgotForm');
                if (!btn || !form) return;

                function isValidEmail(e){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }

                btn.addEventListener('click', function(){
                    var emailEl = form.querySelector('input[name="email"]');
                    var email = emailEl ? (emailEl.value || '').trim() : '';
                    if (!isValidEmail(email)) {
                        alert('Please enter a valid email address.');
                        if (emailEl) emailEl.focus();
                        return;
                    }

                    // disable button to avoid double-clicks and show immediate feedback
                    btn.disabled = true;
                    var orig = btn.textContent;
                    btn.textContent = 'Sending...';

                    // set a short-lived timestamp so the UI shows countdown immediately
                    try { localStorage.setItem('ojtms_otp_sent_at', Math.floor(Date.now()/1000)); } catch(e){}

                    // submit the form normally so server-side redirects still work
                    form.submit();

                    // restore text if submission somehow prevented (safety fallback after 2s)
                    setTimeout(function(){ btn.textContent = orig; btn.disabled = false; }, 2000);
                });
            })();
            </script>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Back to Login</a>
            </div>
        </div>

        
    </div>
</body>
</html>
