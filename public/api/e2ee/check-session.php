<?php
/**
 * Debug: Session state after login
 */

session_start();

header('Content-Type: text/plain');

echo "=== Session Data ===\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "e2ee_needs_decrypt: " . (isset($_SESSION['e2ee_needs_decrypt']) ? $_SESSION['e2ee_needs_decrypt'] : 'NOT SET') . "\n";
echo "e2ee_key_version: " . (isset($_SESSION['e2ee_key_version']) ? $_SESSION['e2ee_key_version'] : 'NOT SET') . "\n";
echo "e2ee_encrypted_private_key: " . (isset($_SESSION['e2ee_encrypted_private_key']) ? 'SET (' . strlen($_SESSION['e2ee_encrypted_private_key']) . ' chars)' : 'NOT SET') . "\n";
echo "e2ee_public_key: " . (isset($_SESSION['e2ee_public_key']) ? 'SET (' . strlen($_SESSION['e2ee_public_key']) . ' chars)' : 'NOT SET') . "\n";
echo "e2ee_salt: " . (isset($_SESSION['e2ee_salt']) ? 'SET (' . strlen($_SESSION['e2ee_salt']) . ' chars)' : 'NOT SET') . "\n";

echo "\n=== Database Keys ===\n";
require_once __DIR__ . '/../../../config/database.php';
$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
$userId = $_SESSION['user_id'] ?? 0;
if ($userId) {
    $result = $conn->query("SELECT key_version, LENGTH(encrypted_private_key) as len FROM user_keys WHERE user_id = $userId");
    if ($row = $result->fetch_assoc()) {
        echo "Has keys: YES (version " . $row['key_version'] . ", " . $row['len'] . " chars)\n";
    } else {
        echo "Has keys: NO\n";
    }
}
$conn->close();