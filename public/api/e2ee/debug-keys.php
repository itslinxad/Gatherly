<?php
/**
 * Debug: Check stored key format
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: text/plain');

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
$result = $conn->query("SELECT user_id, key_version, LENGTH(encrypted_private_key) as len, LEFT(encrypted_private_key, 60) as preview FROM user_keys");

if ($row = $result->fetch_assoc()) {
    echo "User: " . $row['user_id'] . ", Version: " . $row['key_version'] . "\n";
    echo "Key length: " . $row['len'] . "\n";
    echo "Key preview: " . $row['preview'] . "\n";
    echo "Contains '::': " . (strpos($row['preview'], '::') !== false ? 'YES' : 'NO') . "\n";
} else {
    echo "No keys found";
}

$conn->close();