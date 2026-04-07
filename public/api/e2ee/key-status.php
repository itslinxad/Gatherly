<?php
/**
 * Check if user has E2EE keys set up
 * Used to determine if user needs to go through key setup flow
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

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Connect to database
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Check if user has keys
    $keysStmt = $conn->prepare(
        "SELECT key_version, created_at 
         FROM user_keys 
         WHERE user_id = ? 
         ORDER BY key_version DESC 
         LIMIT 1"
    );
    $keysStmt->bind_param("i", $userId);
    $keysStmt->execute();
    $keysResult = $keysStmt->get_result();
    $hasKeys = $keysResult->num_rows > 0;
    $keyData = $hasKeys ? $keysResult->fetch_assoc() : null;
    $keysStmt->close();

    // Check if user has PIN
    $pinStmt = $conn->prepare(
        "SELECT created_at 
         FROM user_pins 
         WHERE user_id = ?"
    );
    $pinStmt->bind_param("i", $userId);
    $pinStmt->execute();
    $pinResult = $pinStmt->get_result();
    $hasPin = $pinResult->num_rows > 0;
    $pinData = $hasPin ? $pinResult->fetch_assoc() : null;
    $pinStmt->close();

    $conn->close();

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'hasKeys' => $hasKeys,
        'hasPin' => $hasPin,
        'needsSetup' => !$hasKeys || !$hasPin,
        'keyVersion' => $hasKeys ? $keyData['key_version'] : null,
        'setupCompletedAt' => $hasKeys ? $keyData['created_at'] : null
    ]);

} catch (Exception $e) {
    error_log("E2EE Key Status Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
