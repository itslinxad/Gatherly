<?php
/**
 * E2EE Dashboard Helper
 * Include this in dashboard pages to inject E2EE key data and scripts
 * Usage: require_once __DIR__ . '/../../src/components/e2ee-dashboard-helper.php';
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to output E2EE scripts and data attributes
function renderE2EEScripts() {
    // E2EE JavaScript files - use absolute paths from web root
    echo '<script src="/Gatherly/public/assets/js/crypto.js"></script>' . "\n";
    echo '<script src="/Gatherly/public/assets/js/key-manager.js"></script>' . "\n";
    
    // ALWAYS load login-decrypt.js to handle key decryption on page load
    // The script will check if keys are already decrypted and skip if not needed
    echo '<script src="/Gatherly/public/assets/js/login-decrypt.js"></script>' . "\n";
    
    // E2EE Chat handler (loaded after crypto/key-manager)
    echo '<script src="/Gatherly/public/assets/js/e2ee-chat.js"></script>' . "\n";
    
    // PIN modal (for sensitive operations)
    echo '<link rel="stylesheet" href="/Gatherly/public/assets/css/pin-modal.css">' . "\n";
    echo '<script src="/Gatherly/public/assets/js/pin-modal.js"></script>' . "\n";
}

// Function to inject E2EE data attributes into body tag
function getE2EEDataAttributes() {
    $attributes = '';
    
    if (isset($_SESSION['e2ee_needs_decrypt']) && $_SESSION['e2ee_needs_decrypt']) {
        $attributes .= ' data-e2ee-needs-decrypt="true"';
        
        if (isset($_SESSION['e2ee_encrypted_private_key'])) {
            $attributes .= ' data-e2ee-encrypted-private-key="' . htmlspecialchars($_SESSION['e2ee_encrypted_private_key']) . '"';
        }
        
        if (isset($_SESSION['e2ee_public_key'])) {
            $attributes .= ' data-e2ee-public-key="' . htmlspecialchars($_SESSION['e2ee_public_key']) . '"';
        }
        
        if (isset($_SESSION['e2ee_salt'])) {
            $attributes .= ' data-e2ee-salt="' . htmlspecialchars($_SESSION['e2ee_salt']) . '"';
        }
        
        if (isset($_SESSION['e2ee_key_version'])) {
            $attributes .= ' data-e2ee-key-version="' . intval($_SESSION['e2ee_key_version']) . '"';
        }
        
        if (isset($_SESSION['password'])) {
            $attributes .= ' data-e2ee-password="' . htmlspecialchars($_SESSION['password']) . '"';
        }
        
        if (isset($_SESSION['user_id'])) {
            $attributes .= ' data-e2ee-user-id="' . intval($_SESSION['user_id']) . '"';
        }
    }
    
    return $attributes;
}

// Function to check if E2EE is active for current user
function isE2EEActive() {
    return isset($_SESSION['e2ee_key_version']) && $_SESSION['e2ee_key_version'] > 0;
}

// Function to get current user's key version
function getE2EEKeyVersion() {
    return isset($_SESSION['e2ee_key_version']) ? intval($_SESSION['e2ee_key_version']) : null;
}
