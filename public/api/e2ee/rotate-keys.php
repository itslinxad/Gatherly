<?php
/**
 * Rotate user's key pair (generate new version)
 * Used for key rotation after compromise or periodic rotation
 * Requires PIN verification
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['publicKey', 'encryptedPrivateKey', 'salt', 'recoveryMnemonic', 'pin'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit;
    }
}

$userId = $_SESSION['user_id'];
$publicKey = $input['publicKey'];
$encryptedPrivateKey = $input['encryptedPrivateKey'];
$salt = $input['salt'];
$recoveryMnemonic = $input['recoveryMnemonic'];
$pin = $input['pin'];

try {
    // Connect to database
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Verify PIN first
    $pinStmt = $conn->prepare(
        "SELECT pin_hash, failed_attempts, locked_until 
         FROM user_pins 
         WHERE user_id = ?"
    );
    $pinStmt->bind_param("i", $userId);
    $pinStmt->execute();
    $pinResult = $pinStmt->get_result();

    if ($pinResult->num_rows === 0) {
        $pinStmt->close();
        $conn->close();
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'PIN not set up. Cannot rotate keys without PIN.'
        ]);
        exit;
    }

    $pinRow = $pinResult->fetch_assoc();
    $pinStmt->close();

    // Check if locked
    if ($pinRow['locked_until'] !== null && strtotime($pinRow['locked_until']) > time()) {
        $conn->close();
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Account is locked due to failed PIN attempts'
        ]);
        exit;
    }

    // Verify PIN
    if (!password_verify($pin, $pinRow['pin_hash'])) {
        $conn->close();
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid PIN'
        ]);
        exit;
    }

    // Get current key version
    $versionStmt = $conn->prepare(
        "SELECT MAX(key_version) as current_version 
         FROM user_keys 
         WHERE user_id = ?"
    );
    $versionStmt->bind_param("i", $userId);
    $versionStmt->execute();
    $versionResult = $versionStmt->get_result();
    $versionRow = $versionResult->fetch_assoc();
    $versionStmt->close();

    $newVersion = ($versionRow['current_version'] ?? 0) + 1;

    // Insert new key version
    $stmt = $conn->prepare(
        "INSERT INTO user_keys (user_id, public_key, encrypted_private_key, key_salt, encrypted_recovery_key, key_version, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    
    $stmt->bind_param(
        "issssi",
        $userId,
        $publicKey,
        $encryptedPrivateKey,
        $salt,
        $recoveryMnemonic,
        $newVersion
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to store new key version: ' . $stmt->error);
    }

    $stmt->close();

    // Reset PIN failed attempts after successful rotation
    $resetStmt = $conn->prepare(
        "UPDATE user_pins 
         SET failed_attempts = 0, locked_until = NULL 
         WHERE user_id = ?"
    );
    $resetStmt->bind_param("i", $userId);
    $resetStmt->execute();
    $resetStmt->close();

    $conn->close();

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Keys rotated successfully',
        'newKeyVersion' => $newVersion
    ]);

} catch (Exception $e) {
    error_log("E2EE Key Rotation Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
