<?php
/**
 * Forgot Password Handler
 * Uses recovery phrase to verify identity and reset password + regenerate E2EE keys
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'check_email':
        checkEmail();
        break;
    case 'verify_recovery':
        verifyRecoveryPhrase();
        break;
    case 'reset_password':
        resetPassword();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function checkEmail() {
    global $conn;
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        return;
    }
    
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No account found with this email']);
        return;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Check if user has recovery phrase stored
    $keyStmt = $conn->prepare("SELECT encrypted_recovery_key FROM user_keys WHERE user_id = ? ORDER BY key_version DESC LIMIT 1");
    $keyStmt->bind_param("i", $user['user_id']);
    $keyStmt->execute();
    $keyResult = $keyStmt->get_result();
    
    if ($keyResult->num_rows === 0) {
        $keyStmt->close();
        $conn->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'This account does not have a recovery phrase set up. Please contact support.']);
        return;
    }
    
    $keyStmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'user_id' => $user['user_id'],
        'name' => $user['first_name'] . ' ' . $user['last_name']
    ]);
}

function verifyRecoveryPhrase() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? 0;
    $recoveryPhrase = trim($_POST['recovery_phrase'] ?? '');
    
    if (empty($userId) || empty($recoveryPhrase)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID and recovery phrase are required']);
        return;
    }
    
    // Validate recovery phrase format (24 words)
    $words = preg_split('/\s+/', $recoveryPhrase);
    if (count($words) !== 24) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Recovery phrase must be exactly 24 words']);
        return;
    }
    
    // Create hash and compare with stored hash
    $recoveryHash = hash('sha256', strtolower(trim($recoveryPhrase)));
    
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    $stmt = $conn->prepare("SELECT recovery_phrase_hash FROM user_keys WHERE user_id = ? ORDER BY key_version DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Compare hashes
    if ($row['recovery_phrase_hash'] !== $recoveryHash) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid recovery phrase. Please check your words and try again.']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Recovery phrase validated',
        'user_id' => $userId
    ]);
}

function resetPassword() {
    global $conn;
    
    $userId = $_POST['user_id'] ?? 0;
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($userId) || empty($newPassword) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
        return;
    }
    
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
        return;
    }
    
    // Hash and update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update password']);
        return;
    }
    
    $stmt->close();
    
    // Delete old E2EE keys - user will need to set up again with new password
    $conn->query("DELETE FROM user_keys WHERE user_id = $userId");
    $conn->query("DELETE FROM user_pins WHERE user_id = $userId");
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successful. You will need to set up your encryption keys again.'
    ]);
}