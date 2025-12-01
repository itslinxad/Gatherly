<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../src/services/dbconnect.php';

$venue_id = $_GET['id'] ?? null;

if (!$venue_id) {
    echo json_encode(['success' => false, 'message' => 'Venue ID required']);
    exit();
}

$stmt = $conn->prepare("SELECT v.*, 
                       l.city, l.province,
                       m.first_name as manager_fname, 
                       m.last_name as manager_lname,
                       m.email as manager_email,
                       GROUP_CONCAT(DISTINCT a.amenity_name SEPARATOR ', ') as amenities
                       FROM venues v
                       LEFT JOIN locations l ON v.location_id = l.location_id
                       LEFT JOIN users m ON v.manager_id = m.user_id
                       LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
                       LEFT JOIN amenities a ON va.amenity_id = a.amenity_id
                       WHERE v.venue_id = ?
                       GROUP BY v.venue_id");
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$result = $stmt->get_result();
$venue = $result->fetch_assoc();

if ($venue) {
    // Convert BLOB image to base64 if exists
    if (!empty($venue['image'])) {
        $venue['image'] = base64_encode($venue['image']);
    }

    echo json_encode(['success' => true, 'venue' => $venue]);
} else {
    echo json_encode(['success' => false, 'message' => 'Venue not found']);
}

$stmt->close();
$conn->close();
