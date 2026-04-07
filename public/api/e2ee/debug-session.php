<?php
/**
 * Debug: Check session and key status
 */

session_start();
header('Content-Type: text/plain');

echo "=== Session ===\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
echo "e2ee_needs_decrypt: " . ($_SESSION['e2ee_needs_decrypt'] ?? 'not set') . "\n";
echo "e2ee_key_version: " . ($_SESSION['e2ee_key_version'] ?? 'not set') . "\n";

require_once __DIR__ . '/../../../config/database.php';

$userId = $_SESSION['user_id'] ?? 0;

if ($userId) {
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query("SELECT key_version FROM user_keys WHERE user_id = $userId");
    echo "\n=== DB Keys ===\n";
    if ($row = $result->fetch_assoc()) {
        echo "Has keys: YES (version " . $row['key_version'] . ")\n";
    } else {
        echo "Has keys: NO\n";
    }
    $conn->close();
}

echo "\n=== Actions ===\n";
echo "<a href='/Gatherly/public/api/e2ee/force-logout.php'>Force Logout</a>\n";