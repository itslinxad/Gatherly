/**
 * Gatherly E2EE PIN Modal System
 * Handles PIN setup and verification modals with rate limiting
 */

const GatherlyPINModal = (function() {
    'use strict';

    let currentModal = null;
    let callbacks = {
        onSuccess: null,
        onCancel: null
    };

    /**
     * Create modal HTML structure
     * @param {string} type - 'setup' or 'verify'
     * @param {Object} options - Modal options
     * @returns {HTMLElement}
     */
    function createModalHTML(type, options = {}) {
        const modal = document.createElement('div');
        modal.className = 'e2ee-pin-modal-overlay';
        modal.id = 'e2eePinModal';

        const isSetup = type === 'setup';
        const title = options.title || (isSetup ? 'Set Up Security PIN' : 'Verify Your PIN');
        const description = options.description || (
            isSetup 
                ? 'Create a PIN to secure sensitive operations. Must be 6-12 alphanumeric characters.' 
                : 'Enter your PIN to continue.'
        );

        modal.innerHTML = `
            <div class="e2ee-pin-modal">
                <div class="e2ee-pin-modal-header">
                    <h2>${title}</h2>
                    ${options.closeable !== false ? '<button class="e2ee-pin-close" id="pinModalClose">&times;</button>' : ''}
                </div>
                <div class="e2ee-pin-modal-body">
                    <p class="e2ee-pin-description">${description}</p>
                    
                    ${isSetup ? `
                        <div class="e2ee-pin-input-group">
                            <label for="pinInput">PIN</label>
                            <input 
                                type="password" 
                                id="pinInput" 
                                class="e2ee-pin-input" 
                                placeholder="Enter PIN (6-12 characters)"
                                maxlength="12"
                                autocomplete="off"
                            />
                            <small class="e2ee-pin-hint">Use letters and numbers only</small>
                        </div>
                        
                        <div class="e2ee-pin-input-group">
                            <label for="pinConfirmInput">Confirm PIN</label>
                            <input 
                                type="password" 
                                id="pinConfirmInput" 
                                class="e2ee-pin-input" 
                                placeholder="Re-enter PIN"
                                maxlength="12"
                                autocomplete="off"
                            />
                        </div>
                    ` : `
                        <div class="e2ee-pin-input-group">
                            <label for="pinInput">PIN</label>
                            <input 
                                type="password" 
                                id="pinInput" 
                                class="e2ee-pin-input" 
                                placeholder="Enter your PIN"
                                maxlength="12"
                                autocomplete="off"
                            />
                            <div id="pinAttemptsInfo" class="e2ee-pin-attempts" style="display: none;"></div>
                        </div>
                    `}

                    <div id="pinError" class="e2ee-pin-error" style="display: none;"></div>
                    <div id="pinSuccess" class="e2ee-pin-success" style="display: none;"></div>
                </div>
                <div class="e2ee-pin-modal-footer">
                    ${options.cancelable !== false ? '<button class="e2ee-pin-btn e2ee-pin-btn-secondary" id="pinCancelBtn">Cancel</button>' : ''}
                    <button class="e2ee-pin-btn e2ee-pin-btn-primary" id="pinSubmitBtn">
                        ${isSetup ? 'Set PIN' : 'Verify'}
                    </button>
                </div>
                <div class="e2ee-pin-loading" id="pinLoading" style="display: none;">
                    <div class="e2ee-pin-spinner"></div>
                </div>
            </div>
        `;

        return modal;
    }

    /**
     * Show error message in modal
     * @param {string} message
     */
    function showError(message) {
        const errorEl = document.getElementById('pinError');
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
            
            setTimeout(() => {
                errorEl.style.display = 'none';
            }, 5000);
        }
    }

    /**
     * Show success message in modal
     * @param {string} message
     */
    function showSuccess(message) {
        const successEl = document.getElementById('pinSuccess');
        if (successEl) {
            successEl.textContent = message;
            successEl.style.display = 'block';
        }
    }

    /**
     * Show loading state
     * @param {boolean} loading
     */
    function setLoading(loading) {
        const loadingEl = document.getElementById('pinLoading');
        const submitBtn = document.getElementById('pinSubmitBtn');
        const cancelBtn = document.getElementById('pinCancelBtn');

        if (loadingEl) loadingEl.style.display = loading ? 'flex' : 'none';
        if (submitBtn) submitBtn.disabled = loading;
        if (cancelBtn) cancelBtn.disabled = loading;
    }

    /**
     * Validate PIN format
     * @param {string} pin
     * @returns {Object} { valid: boolean, error: string }
     */
    function validatePIN(pin) {
        if (!pin || pin.length < 6) {
            return { valid: false, error: 'PIN must be at least 6 characters' };
        }
        if (pin.length > 12) {
            return { valid: false, error: 'PIN must be at most 12 characters' };
        }
        if (!/^[a-zA-Z0-9]+$/.test(pin)) {
            return { valid: false, error: 'PIN must contain only letters and numbers' };
        }
        return { valid: true };
    }

    /**
     * Handle PIN setup
     */
    async function handleSetup() {
        const pinInput = document.getElementById('pinInput');
        const pinConfirmInput = document.getElementById('pinConfirmInput');
        const pin = pinInput.value.trim();
        const pinConfirm = pinConfirmInput.value.trim();

        // Validate PIN
        const validation = validatePIN(pin);
        if (!validation.valid) {
            showError(validation.error);
            return;
        }

        // Check if PINs match
        if (pin !== pinConfirm) {
            showError('PINs do not match');
            return;
        }

        setLoading(true);

        try {
            // Absolute path from web root
            const response = await fetch('/Gatherly/public/api/e2ee/setup-pin.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ pin })
            });

            const data = await response.json();

            if (data.success) {
                showSuccess(data.message);
                
                setTimeout(() => {
                    closeModal();
                    if (callbacks.onSuccess) {
                        callbacks.onSuccess();
                    }
                }, 1000);
            } else {
                showError(data.error || 'Failed to set up PIN');
            }
        } catch (error) {
            console.error('PIN setup error:', error);
            showError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    /**
     * Handle PIN verification
     */
    async function handleVerify() {
        const pinInput = document.getElementById('pinInput');
        const pin = pinInput.value.trim();

        // Validate PIN format
        const validation = validatePIN(pin);
        if (!validation.valid) {
            showError(validation.error);
            return;
        }

        setLoading(true);

        try {
            const response = await fetch('/Gatherly/public/api/e2ee/verify-pin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ pin })
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('PIN verified successfully');
                
                setTimeout(() => {
                    closeModal();
                    if (callbacks.onSuccess) {
                        callbacks.onSuccess(pin);
                    }
                }, 500);
            } else {
                // Handle locked account
                if (data.locked) {
                    showError(data.error);
                    
                    // Disable input for locked accounts
                    pinInput.disabled = true;
                    document.getElementById('pinSubmitBtn').disabled = true;
                    
                    setTimeout(() => {
                        closeModal();
                        if (callbacks.onCancel) {
                            callbacks.onCancel({ locked: true, ...data });
                        }
                    }, 3000);
                } else {
                    // Show remaining attempts
                    showError(data.error);
                    
                    if (data.remainingAttempts !== undefined) {
                        const attemptsInfo = document.getElementById('pinAttemptsInfo');
                        if (attemptsInfo) {
                            attemptsInfo.textContent = `${data.remainingAttempts} attempt${data.remainingAttempts !== 1 ? 's' : ''} remaining`;
                            attemptsInfo.style.display = 'block';
                            attemptsInfo.className = 'e2ee-pin-attempts ' + 
                                (data.remainingAttempts === 1 ? 'e2ee-pin-attempts-warning' : '');
                        }
                    }
                    
                    // Clear input
                    pinInput.value = '';
                    pinInput.focus();
                }
            }
        } catch (error) {
            console.error('PIN verification error:', error);
            showError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    /**
     * Close modal
     */
    function closeModal() {
        if (currentModal && currentModal.parentNode) {
            currentModal.parentNode.removeChild(currentModal);
            currentModal = null;
        }
    }

    /**
     * Attach event listeners
     * @param {string} type - 'setup' or 'verify'
     */
    function attachEventListeners(type) {
        const closeBtn = document.getElementById('pinModalClose');
        const cancelBtn = document.getElementById('pinCancelBtn');
        const submitBtn = document.getElementById('pinSubmitBtn');
        const pinInput = document.getElementById('pinInput');
        const pinConfirmInput = document.getElementById('pinConfirmInput');

        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                closeModal();
                if (callbacks.onCancel) callbacks.onCancel();
            });
        }

        // Cancel button
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                closeModal();
                if (callbacks.onCancel) callbacks.onCancel();
            });
        }

        // Submit button
        if (submitBtn) {
            submitBtn.addEventListener('click', () => {
                if (type === 'setup') {
                    handleSetup();
                } else {
                    handleVerify();
                }
            });
        }

        // Enter key handling
        const handleEnter = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (type === 'setup' && e.target === pinInput) {
                    // Focus confirm input on setup
                    if (pinConfirmInput) pinConfirmInput.focus();
                } else {
                    // Submit on verify or confirm input
                    submitBtn.click();
                }
            }
        };

        if (pinInput) {
            pinInput.addEventListener('keypress', handleEnter);
            pinInput.focus();
        }
        if (pinConfirmInput) {
            pinConfirmInput.addEventListener('keypress', handleEnter);
        }

        // Click outside to close
        currentModal.addEventListener('click', (e) => {
            if (e.target === currentModal) {
                closeModal();
                if (callbacks.onCancel) callbacks.onCancel();
            }
        });
    }

    // Public API
    return {
        /**
         * Show PIN setup modal
         * @param {Object} options - { onSuccess, onCancel, title, description, closeable, cancelable }
         */
        showSetup(options = {}) {
            // Close existing modal
            if (currentModal) closeModal();

            callbacks.onSuccess = options.onSuccess || null;
            callbacks.onCancel = options.onCancel || null;

            currentModal = createModalHTML('setup', options);
            document.body.appendChild(currentModal);
            attachEventListeners('setup');
        },

        /**
         * Show PIN verification modal
         * @param {Object} options - { onSuccess, onCancel, title, description, closeable, cancelable }
         */
        showVerify(options = {}) {
            // Close existing modal
            if (currentModal) closeModal();

            callbacks.onSuccess = options.onSuccess || null;
            callbacks.onCancel = options.onCancel || null;

            currentModal = createModalHTML('verify', options);
            document.body.appendChild(currentModal);
            attachEventListeners('verify');
        },

        /**
         * Close current modal
         */
        close() {
            closeModal();
        }
    };
})();
