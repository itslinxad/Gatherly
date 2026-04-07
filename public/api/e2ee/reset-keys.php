<?php
/**
 * Clear E2EE keys endpoint
 * GET /Gatherly/public/api/e2ee/reset-keys.php?user_id=X
 */

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

$userId = $_GET['user_id'] ?? 0;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    $conn->query("DELETE FROM user_keys WHERE user_id = $userId");
    $conn->query("DELETE FROM user_pins WHERE user_id = $userId");
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Keys deleted for user ' . $userId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}