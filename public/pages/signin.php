<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to Gatherly | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="/Gatherly/public/assets/js/crypto.js"></script>
    <link rel="stylesheet" href="/Gatherly/public/assets/css/pin-modal.css">
    <script src="/Gatherly/public/assets/js/pin-modal.js"></script>
    <style>
        .recovery-word {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            text-align: center;
            background: #f9fafb;
        }
    </style>
</head>

<body>
    <?php
    session_start();

    // Redirect to the right dashboard if already logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'administrator':
                header("Location: admin/admin-dashboard.php");
                exit();
            case 'organizer':
                header("Location: organizer/organizer-dashboard.php");
                exit();
            case 'manager':
                header("Location: manager/manager-dashboard.php");
                exit();
            case 'supplier':
                header("Location: supplier/supplier-dashboard.php");
                exit();
            default:
                header("Location: ../../index.php");
                exit();
        }
    }

    $error = null;
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    ?>

    <div class="min-h-screen bg-linear-to-br from-indigo-50 via-white to-purple-50 font-['Montserrat'] flex flex-col">
        <div class="flex flex-col items-center justify-center flex-1 w-full px-4 py-8 sm:py-12">
            <div class="flex flex-col items-center mb-6 text-xl font-bold sm:text-2xl sm:mb-8 ">
                <a href="../../index.php" class="flex flex-col items-center group">
                    <img class="w-12 mb-4 transition-transform sm:w-16 sm:mb-8 drop-shadow-lg group-hover:scale-110"
                        src="../assets/images/logo.png" alt="Logo">
                    <span class="text-gray-800">Sign in to your account</span>
                </a>
            </div>
            <div class="flex flex-col items-center w-full mt-4 sm:mt-8">
                <form action="../../src/services/signin-handler.php" method="POST" class="w-full max-w-md sm:max-w-lg">
                    <div
                        class="flex flex-col w-full p-6 bg-white border border-gray-200 shadow-xl sm:p-8 md:p-12 rounded-2xl">
                        <?php if (!empty($error)): ?>
                            <div
                                class="flex items-start gap-2 p-3 mb-4 text-sm text-red-700 border border-red-200 rounded-lg sm:p-4 sm:mb-5 bg-red-50">
                                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>
                        <label for="email" class="mb-2 text-sm font-semibold text-gray-700">Email address</label>
                        <input type="email" id="email" name="email" required
                            class="w-full px-4 py-2.5 mb-5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="your@email.com">
                        <label for="password" class="mb-2 text-sm font-semibold text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-2.5 mb-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="Enter your password">
                        <div class="flex justify-end mb-6">
                            <button type="button" onclick="showForgotPassword()" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">
                                Forgot password?
                            </button>
                        </div>
                        <!-- <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center">
                                <input type="checkbox" id="remember" name="remember"
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                                <label for="remember" class="ml-2 text-xs font-medium text-gray-600 sm:text-sm">Remember
                                    me</label>
                            </div>
                            <a href="#forgotPass">
                                <span
                                    class="text-xs font-semibold text-indigo-600 transition-colors sm:text-sm hover:text-indigo-700 hover:underline">Forgot
                                    password?</span>
                            </a>
                        </div> -->
                        <button type="submit"
                            class="cursor-pointer w-full px-4 py-3 font-semibold text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0">Sign
                            in</button>
                    </div>
                </form>
            </div>
            <div class="flex items-center mt-6 sm:mt-12">
                <p class="text-xs text-gray-600 sm:text-sm">Don't have an account?
                    <a href="signup.php">
                        <span
                            class="font-semibold text-indigo-600 transition-colors hover:text-indigo-700 hover:underline">Sign
                            up</span>
                    </a>
                </p>
            </div>
        </div>
        <?php include '../../src/components/Footer.php'; ?>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 text-white">
                <h2 class="text-xl font-bold">Reset Password</h2>
                <p class="text-sm text-indigo-100 mt-1">Use your recovery phrase to verify your identity</p>
            </div>
            
            <div class="p-6">
                <!-- Step 1: Enter Email -->
                <div id="forgotStep1">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Enter your email</label>
                        <input type="email" id="forgotEmail" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="your@email.com">
                    </div>
                    <div class="flex gap-3">
                        <button onclick="closeForgotPassword()" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button onclick="checkEmail()" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Continue
                        </button>
                    </div>
                </div>

                <!-- Step 2: Enter Recovery Phrase -->
                <div id="forgotStep2" class="hidden">
                    <p class="text-sm text-gray-600 mb-4">Enter your 24-word recovery phrase to verify your identity:</p>
                    <textarea id="recoveryPhraseInput" rows="3" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                        placeholder="word1 word2 word3 ... word24"></textarea>
                    <p class="text-xs text-gray-500 mt-2">Enter all 24 words separated by spaces</p>
                    <div class="flex gap-3 mt-4">
                        <button onclick="showForgotStep(1)" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Back
                        </button>
                        <button onclick="verifyRecoveryPhrase()" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Verify
                        </button>
                    </div>
                </div>

                <!-- Step 3: Enter New Password -->
                <div id="forgotStep3" class="hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                        <input type="password" id="newPassword" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="At least 8 characters">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" id="confirmPassword" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Re-enter password">
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-yellow-800"><strong>Note:</strong> Your encryption keys will be reset. You'll need to set up new E2EE keys after logging in.</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="showForgotStep(2)" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Back
                        </button>
                        <button onclick="resetPassword()" class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Reset Password
                        </button>
                    </div>
                </div>

                <!-- Success -->
                <div id="forgotSuccess" class="hidden text-center py-4">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Password Reset Successful!</h3>
                    <p class="text-sm text-gray-600 mb-4">You can now log in with your new password.</p>
                    <button onclick="closeForgotPassword()" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Go to Login
                    </button>
                </div>

                <!-- Error Message -->
                <div id="forgotError" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600" id="forgotErrorText"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let forgotUserId = null;

        function showForgotPassword() {
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
            showForgotStep(1);
        }

        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').classList.add('hidden');
            forgotUserId = null;
            document.getElementById('recoveryPhraseInput').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            hideForgotError();
        }

        function showForgotStep(step) {
            document.getElementById('forgotStep1').classList.add('hidden');
            document.getElementById('forgotStep2').classList.add('hidden');
            document.getElementById('forgotStep3').classList.add('hidden');
            document.getElementById('forgotSuccess').classList.add('hidden');
            
            if (step === 1) document.getElementById('forgotStep1').classList.remove('hidden');
            if (step === 2) document.getElementById('forgotStep2').classList.remove('hidden');
            if (step === 3) document.getElementById('forgotStep3').classList.remove('hidden');
            
            hideForgotError();
        }

        function showForgotError(msg) {
            document.getElementById('forgotError').classList.remove('hidden');
            document.getElementById('forgotErrorText').textContent = msg;
        }

        function hideForgotError() {
            document.getElementById('forgotError').classList.add('hidden');
        }

        async function checkEmail() {
            const email = document.getElementById('forgotEmail').value.trim();
            if (!email) {
                showForgotError('Please enter your email');
                return;
            }

            try {
                const response = await fetch('/Gatherly/public/api/e2ee/forgot-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=check_email&email=' + encodeURIComponent(email)
                });
                const data = await response.json();
                
                if (data.success) {
                    forgotUserId = data.user_id;
                    showForgotStep(2);
                } else {
                    showForgotError(data.error);
                }
            } catch (e) {
                showForgotError('An error occurred. Please try again.');
            }
        }

        async function verifyRecoveryPhrase() {
            const phrase = document.getElementById('recoveryPhraseInput').value.trim();
            if (!phrase) {
                showForgotError('Please enter your recovery phrase');
                return;
            }

            const words = phrase.split(/\s+/);
            if (words.length < 24) {
                showForgotError('Recovery phrase must be 24 words');
                return;
            }

            try {
                const response = await fetch('/Gatherly/public/api/e2ee/forgot-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=verify_recovery&user_id=' + forgotUserId + '&recovery_phrase=' + encodeURIComponent(phrase)
                });
                const data = await response.json();
                
                if (data.success) {
                    showForgotStep(3);
                } else {
                    showForgotError(data.error);
                }
            } catch (e) {
                showForgotError('An error occurred. Please try again.');
            }
        }

        async function resetPassword() {
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;

            if (!newPass || !confirmPass) {
                showForgotError('Please fill in all fields');
                return;
            }

            if (newPass.length < 8) {
                showForgotError('Password must be at least 8 characters');
                return;
            }

            if (newPass !== confirmPass) {
                showForgotError('Passwords do not match');
                return;
            }

            try {
                const response = await fetch('/Gatherly/public/api/e2ee/forgot-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=reset_password&user_id=' + forgotUserId + '&new_password=' + encodeURIComponent(newPass) + '&confirm_password=' + encodeURIComponent(confirmPass)
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('forgotStep3').classList.add('hidden');
                    document.getElementById('forgotSuccess').classList.remove('hidden');
                } else {
                    showForgotError(data.error);
                }
            } catch (e) {
                showForgotError('An error occurred. Please try again.');
            }
        }
    </script>
</body>

</html>
