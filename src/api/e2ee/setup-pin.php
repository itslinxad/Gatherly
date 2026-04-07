<?php
/**
 * Setup or update user's PIN
 * PIN is used for sensitive operations like key recovery and rotation
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
if (empty($input['pin'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required field: pin'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$pin = $input['pin'];

// Validate PIN format (alphanumeric, 6-12 characters)
if (!preg_match('/^[a-zA-Z0-9]{6,12}$/', $pin)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid PIN format. Must be 6-12 alphanumeric characters.'
    ]);
    exit;
}

try {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Hash PIN with bcrypt (cost factor 12 for good security)
    $pinHash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]);

    // Check if user already has a PIN
    $checkStmt = $conn->prepare("SELECT pin_hash FROM user_pins WHERE user_id = ?");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    $isUpdate = $result->num_rows > 0;
    $checkStmt->close();

    if ($isUpdate) {
        // Update existing PIN
        $stmt = $conn->prepare(
            "UPDATE user_pins 
             SET pin_hash = ?, 
                 failed_attempts = 0, 
                 locked_until = NULL, 
                 updated_at = NOW() 
             WHERE user_id = ?"
        );
        $stmt->bind_param("si", $pinHash, $userId);
    } else {
        // Insert new PIN
        $stmt = $conn->prepare(
            "INSERT INTO user_pins (user_id, pin_hash, failed_attempts, created_at) 
             VALUES (?, ?, 0, NOW())"
        );
        $stmt->bind_param("is", $userId, $pinHash);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to store PIN: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $isUpdate ? 'PIN updated successfully' : 'PIN created successfully'
    ]);

} catch (Exception $e) {
    error_log("E2EE Setup PIN Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
