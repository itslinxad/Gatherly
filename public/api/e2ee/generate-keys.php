<?php
/**
 * Generate and store user's first E2EE key pair
 * Called during initial key setup
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
$requiredFields = ['publicKey', 'encryptedPrivateKey', 'salt', 'recoveryMnemonic'];
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
$keyVersion = 1; // First key version

try {
    // Connect to database
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Check if user already has keys
    $checkStmt = $conn->prepare("SELECT key_version FROM user_keys WHERE user_id = ?");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'User already has keys. Use key rotation endpoint to update keys.'
        ]);
        exit;
    }
    $checkStmt->close();

    // Insert new keys
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
        $keyVersion
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to store keys: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Keys generated and stored successfully',
        'keyVersion' => $keyVersion
    ]);

} catch (Exception $e) {
    error_log("E2EE Key Generation Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
