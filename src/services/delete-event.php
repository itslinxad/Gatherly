<?php
session_start();
header('Content-Type: application/json');

// Ensure user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once 'dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$event_id = $_POST['event_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$event_id) {
    echo json_encode(['success' => false, 'error' => 'Event ID is required']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // First, verify that the event belongs to this user and check its status
    $checkQuery = "SELECT status, organizer_id FROM events WHERE event_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $event_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Event not found');
    }

    $event = $result->fetch_assoc();

    // Verify ownership
    if ($event['organizer_id'] != $user_id) {
        throw new Exception('You do not have permission to delete this event');
    }

    // Only allow deletion of pending or canceled events
    if ($event['status'] !== 'pending' && $event['status'] !== 'canceled') {
        throw new Exception('Only pending or canceled events can be deleted');
    }

    // Delete associated payments first (if any)
    $deletePaymentsQuery = "DELETE FROM payments WHERE event_id = ?";
    $deletePaymentsStmt = $conn->prepare($deletePaymentsQuery);
    $deletePaymentsStmt->bind_param("i", $event_id);
    $deletePaymentsStmt->execute();

    // Delete the event
    $deleteEventQuery = "DELETE FROM events WHERE event_id = ?";
    $deleteEventStmt = $conn->prepare($deleteEventQuery);
    $deleteEventStmt->bind_param("i", $event_id);
    $deleteEventStmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    $conn->close();
}
