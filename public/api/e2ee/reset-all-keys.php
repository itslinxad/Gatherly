<?php
/**
 * Clear ALL E2EE keys - for testing/reset
 * ACCESS WITH CAUTION - deletes ALL user keys!
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

try {
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    // Show current keys before deleting (for debugging)
    $result = $conn->query("SELECT user_id, LENGTH(encrypted_private_key) as key_len, key_version FROM user_keys");
    $before = [];
    while ($row = $result->fetch_assoc()) {
        $before[] = $row;
    }
    
    // Delete all
    $conn->query("DELETE FROM user_keys");
    $conn->query("DELETE FROM user_pins");
    
    $conn->close();
    
    echo json_encode([
        'success' => true, 
        'deleted' => count($before),
        'before' => $before,
        'message' => 'All E2EE keys deleted'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}