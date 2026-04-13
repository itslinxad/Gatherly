/**
 * Gatherly E2EE Login Handler
 * Handles key decryption after successful login
 * Include this script on dashboard pages to decrypt and cache user keys
 */

async function runKeyDecryption() {
    'use strict';

    // Wait for GatherlyKeyManager to be available
    if (typeof GatherlyKeyManager === 'undefined') {
        console.warn('[E2EE] Key manager not loaded yet, retrying...');
        setTimeout(runKeyDecryption, 100);
        return;
    }

    const body = document.body;
    if (!body) {
        console.warn('[E2EE] Body not ready, will retry...');
        setTimeout(runKeyDecryption, 100);
        return;
    }

    const needsDecrypt = body.dataset.e2eeNeedsDecrypt === 'true';

    if (!needsDecrypt) {
        if (window.GatherlyKeyManager && window.GatherlyKeyManager.hasActiveSession()) {
            console.log('[E2EE] Keys already loaded in session');
        }
        return;
    }

    console.log('[E2EE] Starting key decryption process...');

    try {
        const encryptedPrivateKeyBase64 = body.dataset.e2eeEncryptedPrivateKey;
        const publicKeyPem = body.dataset.e2eePublicKey;
        const saltBase64 = body.dataset.e2eeSalt;
        const keyVersion = parseInt(body.dataset.e2eeKeyVersion, 10);
        const password = body.dataset.e2eePassword;
        const userId = parseInt(body.dataset.e2eeUserId, 10);

        if (!encryptedPrivateKeyBase64 || !publicKeyPem || !saltBase64 || !password || !userId) {
            console.error('[E2EE] Missing required key data');
            showKeyError('Missing encryption key data. Please log in again.');
            return;
        }

        const saltBytes = Uint8Array.from(atob(saltBase64), c => c.charCodeAt(0));

        console.log('[E2EE] Deriving master key...');
        const masterKey = await window.GatherlyE2EE.deriveMasterKey(password, saltBytes);

        console.log('[E2EE] Decrypting private key...');
        const privateKey = await window.GatherlyE2EE.decryptPrivateKey(encryptedPrivateKeyBase64, masterKey);

        console.log('[E2EE] Storing keys in session...');
        await GatherlyKeyManager.setupUserKeys({
            userId: userId,
            masterKey: masterKey,
            privateKey: privateKey,
            publicKeyPem: publicKeyPem,
            keyVersion: keyVersion,
            salt: saltBase64
        });

        console.log('[E2EE] Keys successfully decrypted and cached!');

        await fetch('/Gatherly/public/api/e2ee/clear-login-data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('[E2EE] Login complete - E2EE is active');

    } catch (error) {
        console.error('[E2EE] Key decryption failed:', error);
        showKeyError('Failed to decrypt encryption keys. Redirecting to key setup...');
        
        // Delete invalid keys and redirect to setup
        setTimeout(async () => {
            try {
                await fetch('/Gatherly/public/api/e2ee/clear-keys.php', { method: 'POST' });
            } catch (e) {}
            window.location.href = '/Gatherly/public/pages/setup-keys.php';
        }, 2000);
    }
}

function clearSensitiveData() {
    if (!document.body) return;
    delete document.body.dataset.e2eeEncryptedPrivateKey;
    delete document.body.dataset.e2eePassword;
    delete document.body.dataset.e2eeSalt;
    delete document.body.dataset.e2eeNeedsDecrypt;
    console.log('[E2EE] Sensitive data cleared from DOM');
}

function showKeyError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.style.cssText = 'position:fixed;top:20px;right:20px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:10000;max-width:400px;font-family:Poppins,sans-serif;font-size:14px';
    errorDiv.innerHTML = '<strong style="display:block;margin-bottom:4px;">Encryption Error</strong>' + message +
        '<button onclick="this.parentElement.remove()" style="position:absolute;top:8px;right:8px;background:none;border:none;font-size:20px;color:#991b1b;cursor:pointer;">&times;</button>';
    document.body.appendChild(errorDiv);
    setTimeout(() => { if (errorDiv.parentElement) errorDiv.remove(); }, 10000);
    setTimeout(() => { window.location.href = '/Gatherly/public/pages/signin.php'; }, 3000);
}

// Run on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runKeyDecryption);
} else {
    runKeyDecryption();
}
