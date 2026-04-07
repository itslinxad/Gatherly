<?php
/**
 * Clear user's E2EE keys - for re-setup
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    // Delete keys
    $conn->query("DELETE FROM user_keys WHERE user_id = $userId");
    $conn->query("DELETE FROM user_pins WHERE user_id = $userId");
    
    // Clear session
    unset($_SESSION['e2ee_encrypted_private_key']);
    unset($_SESSION['e2ee_public_key']);
    unset($_SESSION['e2ee_salt']);
    unset($_SESSION['e2ee_key_version']);
    unset($_SESSION['e2ee_needs_decrypt']);
    
    $conn->close();
    
    echo json_encode(['success' => true, 'message' => 'Keys cleared']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}