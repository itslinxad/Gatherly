<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once '../../services/dbconnect.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'approve' or 'reject'
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;

if ($event_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    // Verify that the event belongs to the logged-in organizer
    $verifyQuery = "SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("ii", $event_id, $user_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Event not found or access denied']);
        exit();
    }

    // Check if contract exists
    $contractQuery = "SELECT contract_id, signed_status FROM event_contracts WHERE event_id = ? ORDER BY contract_id DESC LIMIT 1";
    $contractStmt = $conn->prepare($contractQuery);
    $contractStmt->bind_param("i", $event_id);
    $contractStmt->execute();
    $contractResult = $contractStmt->get_result();

    if ($contractResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Contract not found']);
        exit();
    }

    $contract = $contractResult->fetch_assoc();

    // Check if contract is already approved or rejected
    if ($contract['signed_status'] !== 'pending') {
        echo json_encode(['success' => false, 'error' => 'Contract has already been ' . $contract['signed_status']]);
        exit();
    }

    // Update contract status
    if ($action === 'approve') {
        $updateQuery = "UPDATE event_contracts SET signed_status = 'approved', approved_at = NOW() WHERE contract_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $contract['contract_id']);

        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Contract approved successfully! You can now proceed with payment.',
                'action' => 'approved'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to approve contract']);
        }
    } else if ($action === 'reject') {
        $updateQuery = "UPDATE event_contracts SET signed_status = 'rejected', rejected_at = NOW(), rejection_reason = ? WHERE contract_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $rejection_reason, $contract['contract_id']);

        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Contract rejected. The manager will be notified.',
                'action' => 'rejected'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to reject contract']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
