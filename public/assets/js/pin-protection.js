/**
 * Gatherly PIN Protection Handler
 * Show PIN modal before allowing access to protected pages (chats)
 * Similar to Facebook Messenger's lock feature
 * Requires PIN on every visit after logout
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'gatherly_pin_verified';

    // Check if this page needs PIN protection
    function needsPinProtection() {
        const path = window.location.pathname;
        return path.includes('/chats.php') || path.includes('/messages');
    }

    // Check if PIN is verified (simple flag in localStorage)
    function isPinVerified() {
        return localStorage.getItem(STORAGE_KEY) === 'true';
    }

    // Mark PIN as verified
    function setPinVerified() {
        localStorage.setItem(STORAGE_KEY, 'true');
    }

    // Clear PIN verification (call on logout to require PIN again)
    function clearPinVerified() {
        localStorage.removeItem(STORAGE_KEY);
    }

    // Show PIN verification modal
    async function showPinModal() {
        return new Promise((resolve, reject) => {
            if (typeof GatherlyPINModal === 'undefined') {
                reject(new Error('PIN Modal not loaded'));
                return;
            }

            GatherlyPINModal.showVerify({
                title: 'Enter PIN',
                description: 'Enter your security PIN to access chats',
                closeable: false,
                cancelable: false,
                onSuccess: () => {
                    setPinVerified();
                    resolve(true);
                },
                onCancel: () => {
                    resolve(false);
                }
            });
        });
    }

    // Check if user has PIN set up via API
    async function checkPinStatus() {
        try {
            const response = await fetch('/Gatherly/public/api/e2ee/key-status.php');
            const data = await response.json();
            return data.hasPin || false;
        } catch (error) {
            console.error('[PIN Protection] Error checking PIN status:', error);
            return false;
        }
    }

    // Main init function
    async function init() {
        if (!needsPinProtection()) {
            return;
        }

        console.log('[PIN Protection] Checking PIN status...');

        // Check if PIN is already verified
        if (isPinVerified()) {
            console.log('[PIN Protection] Already verified');
            return;
        }

        // Check if user has a PIN set up
        const hasPin = await checkPinStatus();
        
        if (hasPin) {
            console.log('[PIN Protection] PIN required, showing modal...');
            
            // Hide chat interface temporarily
            const chatContainer = document.querySelector('.lg\\:ml-64');
            if (chatContainer) {
                chatContainer.style.display = 'none';
            }

            // Show PIN modal
            const verified = await showPinModal();

            if (verified) {
                console.log('[PIN Protection] PIN verified, showing chat');
                if (chatContainer) {
                    chatContainer.style.display = '';
                }
            } else {
                console.log('[PIN Protection] PIN verification failed');
                // Show access denied message
                document.body.innerHTML = `
                    <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f3f4f6;font-family:Poppins,sans-serif;">
                        <div style="text-align:center;padding:40px;background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                            <i class="fas fa-lock" style="font-size:48px;color:#6b7280;margin-bottom:16px;"></i>
                            <h2 style="font-size:20px;font-weight:600;color:#111827;margin-bottom:8px;">Access Denied</h2>
                            <p style="color:#6b7280;margin-bottom:20px;">Please enter your PIN to access chats.</p>
                            <button onclick="window.location.reload()" style="padding:12px 24px;background:#4f46e5;color:white;border:none;border-radius:8px;cursor:pointer;">
                                Try Again
                            </button>
                        </div>
                    </div>
                `;
            }
        } else {
            console.log('[PIN Protection] No PIN set up, allowing access');
        }
    }

    // Export for use in logout handlers
    window.PinProtection = {
        clearVerified: clearPinVerified
    };

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();