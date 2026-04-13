<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Check if user already has keys
require_once __DIR__ . '/../../config/database.php';
$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT key_version FROM user_keys WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User already has keys, redirect to dashboard
    $role = $_SESSION['role'] ?? 'organizer';
    header("Location: $role/$role-dashboard.php");
    exit();
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Encryption | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo.png">
    <?php
    $cssPath = __DIR__ . '/../../src/output.css';
if (file_exists($cssPath)): ?>
    <link rel="stylesheet" href="../../src/output.css?v=<?php echo filemtime($cssPath); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/pin-modal.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .setup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            text-align: center;
            color: white;
        }

        .setup-header i {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .setup-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin: 0 0 8px;
        }

        .setup-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .setup-body {
            padding: 40px;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
            gap: 8px;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e5e7eb;
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: #667eea;
            width: 24px;
            border-radius: 5px;
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }

        .info-box.warning {
            background: #fef3c7;
            border-color: #fde68a;
        }

        .info-box i {
            color: #0369a1;
            margin-right: 8px;
        }

        .info-box.warning i {
            color: #92400e;
        }

        .recovery-phrase {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .recovery-words {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 16px;
        }

        .recovery-word {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px;
            font-family: monospace;
            font-size: 14px;
            text-align: center;
        }

        .recovery-word span {
            color: #9ca3af;
            font-size: 11px;
            margin-right: 4px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            width: 100%;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #5568d3;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 14px;
            color: #374151;
            cursor: pointer;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .loading-spinner.active {
            display: block;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon i {
            font-size: 40px;
            color: white;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        @media (max-width: 640px) {
            .setup-header {
                padding: 30px 20px;
            }

            .setup-body {
                padding: 30px 20px;
            }

            .recovery-words {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="setup-container">
        <div class="setup-header">
            <i class="fas fa-shield-alt"></i>
            <h1>Set Up End-to-End Encryption</h1>
            <p>Secure your conversations with military-grade encryption</p>
        </div>

        <div class="setup-body">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-dot active" data-step="1"></div>
                <div class="step-dot" data-step="2"></div>
                <div class="step-dot" data-step="3"></div>
                <div class="step-dot" data-step="4"></div>
            </div>

            <!-- Step 1: Introduction -->
            <div class="step active" id="step1">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">What is End-to-End Encryption?</h2>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    End-to-end encryption (E2EE) ensures that only you and your conversation partners can read your messages. 
                    Not even Gatherly servers can access your message content.
                </p>

                <div class="info-box">
                    <i class="fas fa-lock"></i>
                    <strong>Your messages are encrypted with:</strong>
                    <ul style="margin: 12px 0 0 28px; color: #374151;">
                        <li>RSA-2048 key exchange</li>
                        <li>AES-256-GCM encryption</li>
                        <li>Password-derived master key (PBKDF2)</li>
                    </ul>
                </div>

                <div class="info-box warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Important:</strong> If you forget your password, you cannot recover your messages without your recovery phrase.
                </div>

                <button class="btn btn-primary" onclick="nextStep(2)">Continue</button>
            </div>

            <!-- Step 2: Generate Keys -->
            <div class="step" id="step2">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">Generate Your Encryption Keys</h2>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    We'll now generate your unique encryption keys. This process happens entirely on your device.
                </p>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>This will take a few seconds...</strong>
                    <p style="margin: 8px 0 0; color: #374151; font-size: 14px;">
                        Your device is generating a 2048-bit RSA key pair and deriving your master encryption key from your password.
                    </p>
                </div>

                <button class="btn btn-primary" id="generateKeysBtn" onclick="generateKeys()">Generate Keys</button>
                
                <div class="loading-spinner" id="keyGenLoading">
                    <div class="spinner"></div>
                    <p style="color: #6b7280;">Generating your encryption keys...</p>
                </div>
            </div>

            <!-- Step 3: Recovery Phrase -->
            <div class="step" id="step3">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">Save Your Recovery Phrase</h2>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Write down these 24 words in order. You'll need them to recover your account if you forget your password.
                </p>

                <div class="recovery-phrase">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <strong style="color: #111827;">Your Recovery Phrase</strong>
                        <button onclick="copyRecoveryPhrase()" style="background: none; border: none; color: #667eea; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <div class="recovery-words" id="recoveryWords"></div>
                </div>

                <div class="info-box warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Store this securely!</strong> Anyone with this phrase can access your messages.
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="recoveryConfirm" onchange="toggleContinueBtn()">
                    <label for="recoveryConfirm">I have saved my recovery phrase securely</label>
                </div>

                <button class="btn btn-primary" id="continueBtn" disabled onclick="nextStep(4)">Continue</button>
            </div>

            <!-- Step 4: Set PIN -->
            <div class="step" id="step4">
                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">Set Up Security PIN</h2>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Create a PIN to protect sensitive operations like key rotation and recovery.
                </p>

                <div class="info-box">
                    <i class="fas fa-key"></i>
                    <strong>PIN Requirements:</strong>
                    <ul style="margin: 12px 0 0 28px; color: #374151;">
                        <li>6-12 alphanumeric characters (letters and numbers)</li>
                        <li>Different from your password</li>
                        <li>3 attempts before 15-minute lockout</li>
                    </ul>
                </div>

                <button class="btn btn-primary" onclick="setupPIN()">Set Up PIN</button>
            </div>

            <!-- Success -->
            <div class="step" id="stepSuccess">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2 style="font-size: 24px; font-weight: 600; margin-bottom: 12px; text-align: center;">All Set!</h2>
                <p style="color: #6b7280; margin-bottom: 32px; text-align: center;">
                    Your encryption keys have been generated and your account is now secured with end-to-end encryption.
                </p>
                <button class="btn btn-primary" onclick="redirectToDashboard()">Go to Dashboard</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/crypto.js"></script>
    <script>
        console.log('Crypto loaded, GatherlyE2EE:', typeof GatherlyE2EE);
    </script>
    <script src="../assets/js/key-manager.js"></script>
    <script>
        console.log('Key manager loaded, GatherlyKeyManager:', typeof GatherlyKeyManager);
    </script>
    <script src="../assets/js/pin-modal.js"></script>
    <script>
        let currentStep = 1;
        let generatedKeys = null;
        let recoveryMnemonic = null;

        function nextStep(step) {
            // Hide current step
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.step-dot').forEach(d => d.classList.remove('active'));

            // Show new step
            document.getElementById('step' + step).classList.add('active');
            document.querySelector(`.step-dot[data-step="${step}"]`)?.classList.add('active');
            
            currentStep = step;
        }

        async function generateKeys() {
            console.log('GatherlyE2EE:', GatherlyE2EE);
            console.log('generateKeyPair:', GatherlyE2EE?.generateKeyPair);
            
            const btn = document.getElementById('generateKeysBtn');
            const loading = document.getElementById('keyGenLoading');

            btn.style.display = 'none';
            loading.classList.add('active');

            try {
                // Get password from session (we'll pass it through)
                const password = "<?php echo $_SESSION['password'] ?? ''; ?>";
                
                if (!password) {
                    throw new Error('Password not found in session');
                }

                // Generate salt
                const salt = await GatherlyE2EE.generateSalt();
                const saltBase64 = btoa(String.fromCharCode(...salt));

                // Derive master key from password
                const masterKey = await GatherlyE2EE.deriveMasterKey(password, salt);

                // Generate RSA key pair
                const keyPair = await GatherlyE2EE.generateKeyPair();

                // Export public key
                const publicKeyPem = await GatherlyE2EE.exportPublicKey(keyPair.publicKey);

                // Encrypt private key with master key
                const encryptedPrivateKeyResult = await GatherlyE2EE.encryptPrivateKey(keyPair.privateKey, masterKey);
                // Store both ciphertext and IV (API expects "encryptedPrivateKey_iv" format)
                const encryptedPrivateKey = encryptedPrivateKeyResult.ciphertext + '::' + encryptedPrivateKeyResult.iv;

                // Generate recovery mnemonic (returns space-separated string)
                const mnemonicString = await GatherlyE2EE.generateRecoveryMnemonic();
                // Store as array for display later
                recoveryMnemonic = mnemonicString.split(' ');

                // Encrypt recovery mnemonic with master key
                const encryptedMnemonic = await GatherlyE2EE.encrypt(mnemonicString, masterKey);

                // Store for later use
                generatedKeys = {
                    publicKey: publicKeyPem,
                    encryptedPrivateKey: encryptedPrivateKey,
                    salt: saltBase64,
                    recoveryMnemonic: encryptedMnemonic.ciphertext,
                    recoveryIV: encryptedMnemonic.iv,
                    recoveryAuthTag: encryptedMnemonic.authTag
                };

                // Show recovery phrase (use array from split)
                displayRecoveryPhrase(recoveryMnemonic);

                // Move to next step
                nextStep(3);

            } catch (error) {
                console.error('Key generation error:', error);
                alert('Failed to generate keys. Please try again.');
                btn.style.display = 'block';
                loading.classList.remove('active');
            }
        }

        function displayRecoveryPhrase(words) {
            const container = document.getElementById('recoveryWords');
            container.innerHTML = '';

            words.forEach((word, index) => {
                const div = document.createElement('div');
                div.className = 'recovery-word';
                div.innerHTML = `${word}`;
                container.appendChild(div);
            });
        }

        function copyRecoveryPhrase() {
            if (!recoveryMnemonic) return;

            const phrase = recoveryMnemonic.join(' ');
            navigator.clipboard.writeText(phrase).then(() => {
                alert('Recovery phrase copied to clipboard!');
            });
        }

        function toggleContinueBtn() {
            const checkbox = document.getElementById('recoveryConfirm');
            const btn = document.getElementById('continueBtn');
            btn.disabled = !checkbox.checked;
        }

        function setupPIN() {
            GatherlyPINModal.showSetup({
                closeable: false,
                cancelable: false,
                onSuccess: () => {
                    // PIN setup complete, now save keys to server
                    saveKeysToServer();
                }
            });
        }

        async function saveKeysToServer() {
            try {
                // Combine encrypted mnemonic parts
                const fullRecoveryMnemonic = JSON.stringify({
                    ciphertext: generatedKeys.recoveryMnemonic,
                    iv: generatedKeys.recoveryIV,
                    authTag: generatedKeys.recoveryAuthTag
                });

                const response = await fetch('/Gatherly/public/api/e2ee/generate-keys.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        publicKey: generatedKeys.publicKey,
                        encryptedPrivateKey: generatedKeys.encryptedPrivateKey,
                        salt: generatedKeys.salt,
                        recoveryMnemonic: fullRecoveryMnemonic
                    })
                });

                const data = await response.json();

                if (data.success) {
                    nextStep('Success');
                } else {
                    throw new Error(data.error || 'Failed to save keys');
                }
            } catch (error) {
                console.error('Save keys error:', error);
                alert('Failed to save encryption keys. Please try again.');
            }
        }

        function redirectToDashboard() {
            const role = "<?php echo $_SESSION['role'] ?? 'organizer'; ?>";
            window.location.href = `${role}/${role}-dashboard.php`;
        }
    </script>
</body>

</html>
