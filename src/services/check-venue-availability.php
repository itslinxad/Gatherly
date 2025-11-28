<?php
session_start();
header('Content-Type: application/json');

require_once 'dbconnect.php';

// Get parameters
$venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if ($venue_id < 1 || empty($date)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    // Query to get all bookings for this venue on this date
    $stmt = $conn->prepare("
        SELECT 
            TIME(event_date) as start_time,
            DATE_ADD(event_date, INTERVAL 2 HOUR) as end_time
        FROM events 
        WHERE venue_id = ? 
        AND DATE(event_date) = ? 
        AND status IN ('pending', 'confirmed')
        ORDER BY event_date
    ");

    $stmt->bind_param("is", $venue_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $booked_slots = [];
    while ($row = $result->fetch_assoc()) {
        $booked_slots[] = [
            'start' => $row['start_time'],
            'end' => date('H:i:s', strtotime($row['end_time']))
        ];
    }

    // Check if entire day is booked (7 AM to 7 PM)
    $is_fully_booked = false;
    if (count($booked_slots) > 0) {
        // Simple check: if there are 6 or more bookings (assuming 2-hour slots), day is likely full
        if (count($booked_slots) >= 6) {
            $is_fully_booked = true;
        }
    }

    echo json_encode([
        'success' => true,
        'booked_slots' => $booked_slots,
        'is_fully_booked' => $is_fully_booked
    ]);

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
