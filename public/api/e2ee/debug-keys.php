<?php
/**
 * Debug: Check real key format
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: text/plain');

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
$result = $conn->query("SELECT user_id, key_version, encrypted_private_key FROM user_keys");

if ($result->num_rows === 0) {
    echo "No keys in database\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "User " . $row['user_id'] . " (v" . $row['key_version'] . "):\n";
        echo "  Length: " . strlen($row['encrypted_private_key']) . "\n";
        echo "  Starts: " . substr($row['encrypted_private_key'], 0, 50) . "\n";
        echo "  Has '::': " . (strpos($row['encrypted_private_key'], '::') !== false ? 'YES' : 'NO') . "\n\n";
    }
}

$conn->close();