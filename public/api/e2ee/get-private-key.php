<?php
/**
 * Get user's own encrypted private key
 * Called during login to decrypt on client-side
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

    // Get user's encrypted private key and salt
    $stmt = $conn->prepare(
        "SELECT encrypted_private_key, public_key, key_salt, key_version, created_at 
         FROM user_keys 
         WHERE user_id = ? 
         ORDER BY key_version DESC 
         LIMIT 1"
    );
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'User has not set up E2EE keys yet',
            'needsSetup' => true
        ]);
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'encryptedPrivateKey' => $row['encrypted_private_key'],
        'publicKey' => $row['public_key'],
        'salt' => $row['salt'],
        'keyVersion' => $row['key_version'],
        'createdAt' => $row['created_at']
    ]);

} catch (Exception $e) {
    error_log("E2EE Get Private Key Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
