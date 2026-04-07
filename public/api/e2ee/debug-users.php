<?php
/**
 * Debug: Check users table
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: text/plain');

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);

// Get recent users (last 5)
$result = $conn->query("SELECT user_id, email, role FROM users ORDER BY user_id DESC LIMIT 5");
echo "=== Recent Users ===\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['user_id'] . ", Email: " . $row['email'] . ", Role: " . $row['role'] . "\n";
}

// Check user_keys table
$keysResult = $conn->query("SELECT user_id, key_version FROM user_keys");
echo "\n=== User Keys ===\n";
if ($keysResult->num_rows > 0) {
    while ($row = $keysResult->fetch_assoc()) {
        echo "User: " . $row['user_id'] . ", Version: " . $row['key_version'] . "\n";
    }
} else {
    echo "No keys in database\n";
}

$conn->close();