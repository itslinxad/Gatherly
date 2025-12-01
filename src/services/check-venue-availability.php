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
            time_start,
            time_end
        FROM events 
        WHERE venue_id = ? 
        AND event_date = ? 
        AND status IN ('pending', 'confirmed')
        ORDER BY time_start
    ");

    $stmt->bind_param("is", $venue_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $booked_slots = [];
    while ($row = $result->fetch_assoc()) {
        $booked_slots[] = [
            'start' => $row['time_start'],
            'end' => $row['time_end']
        ];
    }

    // Check if entire day is booked (7 AM to 7 PM = 12 hours)
    // Calculate total booked time
    $is_fully_booked = false;
    if (count($booked_slots) > 0) {
        // Sort slots by start time
        usort($booked_slots, function ($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        // Merge overlapping slots to get accurate coverage
        $merged_slots = [];
        $current_slot = $booked_slots[0];

        for ($i = 1; $i < count($booked_slots); $i++) {
            $next_slot = $booked_slots[$i];

            // If slots overlap or are adjacent, merge them
            if (strtotime($next_slot['start']) <= strtotime($current_slot['end'])) {
                $current_slot['end'] = max($current_slot['end'], $next_slot['end']);
            } else {
                $merged_slots[] = $current_slot;
                $current_slot = $next_slot;
            }
        }
        $merged_slots[] = $current_slot;

        // Calculate total booked minutes
        $total_booked_minutes = 0;
        foreach ($merged_slots as $slot) {
            $start_minutes = strtotime($slot['start']) / 60;
            $end_minutes = strtotime($slot['end']) / 60;
            $total_booked_minutes += ($end_minutes - $start_minutes);
        }

        // Day is fully booked if more than 10 hours are occupied (7 AM to 7 PM = 12 hours)
        if ($total_booked_minutes >= 600) { // 10 hours = 600 minutes
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
