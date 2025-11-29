<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once __DIR__ . '/dbconnect.php';

try {
    // Get all venues for the logged-in manager
    $manager_id = $_SESSION['user_id'];

    $query = "SELECT venue_id, venue_name FROM venues WHERE manager_id = ? AND status = 'active' ORDER BY venue_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $venues = [];
    while ($row = $result->fetch_assoc()) {
        $venues[] = $row;
    }

    echo json_encode(['success' => true, 'venues' => $venues]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
