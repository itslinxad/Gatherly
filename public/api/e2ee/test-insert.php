<?php
/**
 * Debug: Check why keys not saving
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

// Simulate what generate-keys.php does
$userId = $_GET['user_id'] ?? 12;
$publicKey = "test_key_" . time();
$encryptedPrivateKey = "encrypted_" . time() . "::iv_" . time();
$salt = "salt_" . time();
$recoveryMnemonic = "mnemonic_test";
$keyVersion = 1;

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);

$stmt = $conn->prepare(
    "INSERT INTO user_keys (user_id, public_key, encrypted_private_key, key_salt, encrypted_recovery_key, key_version, created_at) 
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);

$stmt->bind_param("issssi", $userId, $publicKey, $encryptedPrivateKey, $salt, $recoveryMnemonic, $keyVersion);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Key inserted for user ' . $userId]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();