<?php
/**
 * Clear sensitive E2EE login data from session
 * Called after keys are successfully decrypted on client-side
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Please login first'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Clear sensitive E2EE data from session
    $clearedItems = [];

    if (isset($_SESSION['password'])) {
        unset($_SESSION['password']);
        $clearedItems[] = 'password';
    }

    if (isset($_SESSION['e2ee_encrypted_private_key'])) {
        unset($_SESSION['e2ee_encrypted_private_key']);
        $clearedItems[] = 'encrypted_private_key';
    }

    if (isset($_SESSION['e2ee_salt'])) {
        unset($_SESSION['e2ee_salt']);
        $clearedItems[] = 'salt';
    }

    if (isset($_SESSION['e2ee_needs_decrypt'])) {
        unset($_SESSION['e2ee_needs_decrypt']);
        $clearedItems[] = 'needs_decrypt_flag';
    }

    // Keep public_key and key_version as they're not sensitive
    // and may be useful for the application

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Sensitive session data cleared',
        'cleared' => $clearedItems
    ]);

} catch (Exception $e) {
    error_log("E2EE Clear Login Data Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
