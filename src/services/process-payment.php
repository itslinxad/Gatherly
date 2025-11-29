<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

require_once 'dbconnect.php';

// Get POST data
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$reference_no = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';
$organizer_id = $_SESSION['user_id'];

// Validation
if ($event_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid event ID']);
    exit();
}

if (!in_array($payment_type, ['full', 'downpayment', 'partial'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment type']);
    exit();
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment amount']);
    exit();
}

if (empty($reference_no) || strlen($reference_no) !== 13 || !ctype_digit($reference_no)) {
    echo json_encode(['success' => false, 'error' => 'Invalid GCash reference number. Must be 13 digits']);
    exit();
}

// Verify event belongs to organizer
$stmt = $conn->prepare("SELECT total_cost, total_paid, payment_status FROM events WHERE event_id = ? AND organizer_id = ?");
$stmt->bind_param("ii", $event_id, $organizer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Event not found or access denied']);
    exit();
}

$event = $result->fetch_assoc();
$stmt->close();

$total_cost = floatval($event['total_cost']);
$total_paid = floatval($event['total_paid']);
$remaining_balance = $total_cost - $total_paid;

// Validate payment amount
if ($payment_type === 'full' && $amount != $remaining_balance) {
    echo json_encode(['success' => false, 'error' => 'Full payment must equal remaining balance']);
    exit();
}

if ($payment_type === 'downpayment' && $amount < ($total_cost * 0.3)) {
    echo json_encode(['success' => false, 'error' => 'Downpayment must be at least 30% of total cost']);
    exit();
}

if ($amount > $remaining_balance) {
    echo json_encode(['success' => false, 'error' => 'Payment amount exceeds remaining balance']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO event_payments (event_id, amount_paid, payment_type, reference_no) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idss", $event_id, $amount, $payment_type, $reference_no);

    if (!$stmt->execute()) {
        throw new Exception("Failed to record payment: " . $stmt->error);
    }

    $payment_id = $conn->insert_id;
    $stmt->close();

    // Update event total_paid and payment_status
    $new_total_paid = $total_paid + $amount;
    $new_payment_status = ($new_total_paid >= $total_cost) ? 'paid' : 'partial';

    $stmt = $conn->prepare("UPDATE events SET total_paid = ?, payment_status = ? WHERE event_id = ?");
    $stmt->bind_param("dsi", $new_total_paid, $new_payment_status, $event_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update event payment status: " . $stmt->error);
    }

    $stmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully and is pending verification',
        'payment_id' => $payment_id,
        'new_total_paid' => $new_total_paid,
        'remaining_balance' => $total_cost - $new_total_paid,
        'payment_status' => $new_payment_status
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'error' => 'Failed to process payment',
        'details' => $e->getMessage()
    ]);
}

$conn->close();
