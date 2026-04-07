<?php
/**
 * Verify user's PIN
 * Implements rate limiting (3 attempts, 15-minute lockout)
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

try {
    // Connect to database
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Get user's PIN data
    $stmt = $conn->prepare(
        "SELECT pin_hash, failed_attempts, locked_until 
         FROM user_pins 
         WHERE user_id = ?"
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
            'error' => 'PIN not set up yet'
        ]);
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // Check if account is locked
    if ($row['locked_until'] !== null) {
        $lockedUntil = strtotime($row['locked_until']);
        $now = time();
        
        if ($now < $lockedUntil) {
            $remainingMinutes = ceil(($lockedUntil - $now) / 60);
            $conn->close();
            
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Account locked due to too many failed attempts',
                'locked' => true,
                'remainingMinutes' => $remainingMinutes
            ]);
            exit;
        } else {
            // Lock expired, reset attempts
            $updateStmt = $conn->prepare(
                "UPDATE user_pins 
                 SET failed_attempts = 0, locked_until = NULL 
                 WHERE user_id = ?"
            );
            $updateStmt->bind_param("i", $userId);
            $updateStmt->execute();
            $updateStmt->close();
            $row['failed_attempts'] = 0;
        }
    }

    // Verify PIN
    $pinValid = password_verify($pin, $row['pin_hash']);

    if ($pinValid) {
        // Success - Reset failed attempts
        $updateStmt = $conn->prepare(
            "UPDATE user_pins 
             SET failed_attempts = 0, locked_until = NULL 
             WHERE user_id = ?"
        );
        $updateStmt->bind_param("i", $userId);
        $updateStmt->execute();
        $updateStmt->close();
        $conn->close();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'PIN verified successfully'
        ]);
    } else {
        // Failed attempt
        $failedAttempts = $row['failed_attempts'] + 1;
        $remainingAttempts = 3 - $failedAttempts;

        if ($failedAttempts >= 3) {
            // Lock account for 15 minutes
            $lockUntil = date('Y-m-d H:i:s', time() + (15 * 60));
            
            $updateStmt = $conn->prepare(
                "UPDATE user_pins 
                 SET failed_attempts = ?, locked_until = ? 
                 WHERE user_id = ?"
            );
            $updateStmt->bind_param("isi", $failedAttempts, $lockUntil, $userId);
            $updateStmt->execute();
            $updateStmt->close();
            $conn->close();

            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Too many failed attempts. Account locked for 15 minutes.',
                'locked' => true,
                'remainingMinutes' => 15
            ]);
        } else {
            // Increment failed attempts
            $updateStmt = $conn->prepare(
                "UPDATE user_pins 
                 SET failed_attempts = ? 
                 WHERE user_id = ?"
            );
            $updateStmt->bind_param("ii", $failedAttempts, $userId);
            $updateStmt->execute();
            $updateStmt->close();
            $conn->close();

            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid PIN',
                'remainingAttempts' => $remainingAttempts
            ]);
        }
    }

} catch (Exception $e) {
    error_log("E2EE Verify PIN Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
