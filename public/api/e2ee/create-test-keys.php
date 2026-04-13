<?php
/**
 * Create test keys with proper format
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: text/plain');

$userId = $_GET['user_id'] ?? 11;

// Generate proper base64 test keys
$publicKey = base64_encode("public_key_data_" . time());
$encryptedPrivateKey = base64_encode("encrypted_pk_" . time()) . "::" . base64_encode("iv_" . time());
$salt = base64_encode("salt_" . time());
$recoveryMnemonic = base64_encode("mnemonic_" . time());
$keyVersion = 1;

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);

$stmt = $conn->prepare(
    "INSERT INTO user_keys (user_id, public_key, encrypted_private_key, key_salt, encrypted_recovery_key, key_version, created_at) 
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);

$stmt->bind_param("issssi", $userId, $publicKey, $encryptedPrivateKey, $salt, $recoveryMnemonic, $keyVersion);

if ($stmt->execute()) {
    echo "Created keys for user $userId\n";
    echo "Public key: " . substr($publicKey, 0, 30) . "...\n";
    echo "Encrypted PK: " . substr($encryptedPrivateKey, 0, 30) . "...\n";
    echo "Salt: " . substr($salt, 0, 30) . "...\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();