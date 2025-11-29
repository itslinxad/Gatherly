<?php

/**
 * Get Venue Details API
 * Returns venue information for displaying in AI chat venue cards
 */

// Disable error display to prevent breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

$venueIds = isset($input['venue_ids']) ? $input['venue_ids'] : [];

if (empty($venueIds) || !is_array($venueIds)) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Venue IDs are required']);
    exit();
}

try {
    // Load database connection
    require_once __DIR__ . '/../dbconnect.php';

    // Sanitize venue IDs
    $venueIds = array_map('intval', $venueIds);
    $placeholders = implode(',', array_fill(0, count($venueIds), '?'));

    // Query to get venue details
    $query = "
        SELECT 
            v.venue_id,
            v.venue_name,
            v.capacity,
            v.description,
            l.city,
            l.province,
            l.baranggay,
            p.base_price,
            p.weekday_price,
            p.weekend_price,
            p.peak_price,
            GROUP_CONCAT(DISTINCT a.amenity_name SEPARATOR ', ') as amenities
        FROM venues v
        LEFT JOIN locations l ON v.location_id = l.location_id
        LEFT JOIN prices p ON v.venue_id = p.venue_id
        LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
        LEFT JOIN amenities a ON va.amenity_id = a.amenity_id
        WHERE v.venue_id IN ($placeholders)
        AND v.status = 'active'
        GROUP BY v.venue_id
        ORDER BY FIELD(v.venue_id, $placeholders)
    ";

    $stmt = $conn->prepare($query);

    // Bind parameters (venue IDs twice - once for IN clause, once for ORDER BY)
    $types = str_repeat('i', count($venueIds) * 2);
    $params = array_merge($venueIds, $venueIds);
    $stmt->bind_param($types, ...$params);

    $stmt->execute();
    $result = $stmt->get_result();

    $venues = [];
    while ($row = $result->fetch_assoc()) {
        $venues[] = $row;
    }

    $stmt->close();

    // Clear any unwanted output and send JSON
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'venues' => $venues
    ]);
} catch (Exception $e) {
    // Clear any unwanted output
    ob_end_clean();

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
