<?php
$status = $_GET['status'] ?? '';
$isSuccess = $status === 'success';
$isError = $status === 'error';
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
                <p class="text-sm text-slate-500 text-center mt-1">Enter your email and we’ll send a reset link.</p>
            </div>

            <?php if ($isSuccess) : ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
                    Reset link sent successfully. Please check your email.
                </div>
            <?php elseif ($isError) : ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    Something went wrong. Please try again.
                </div>
            <?php endif; ?>

            <form method="POST" action="send.php" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        placeholder="you@example.com"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 transition"
                >
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="/CapstoneSample/login.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Back to Login</a>
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-slate-400">OJT Management System • Malolos City Hall</p>
    </div>
</body>
</html>
