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
    if (!isset($_POST['venue_id']) || !isset($_FILES['image'])) {
        throw new Exception('Missing required fields');
    }

    $venue_id = intval($_POST['venue_id']);
    $is_primary = isset($_POST['is_primary']) && $_POST['is_primary'] == '1';
    $manager_id = $_SESSION['user_id'];

    // Verify venue belongs to this manager
    $check_query = "SELECT venue_id FROM venues WHERE venue_id = ? AND manager_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $venue_id, $manager_id);
    $check_stmt->execute();

    if ($check_stmt->get_result()->num_rows === 0) {
        throw new Exception('Venue not found or unauthorized');
    }

    // Validate image
    $image = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($image['type'], $allowed_types)) {
        throw new Exception('Invalid image type. Only JPG, PNG, GIF, and WebP are allowed');
    }

    if ($image['size'] > 5 * 1024 * 1024) { // 5MB max
        throw new Exception('Image size must be less than 5MB');
    }

    // Read image data
    $image_data = file_get_contents($image['tmp_name']);

    if ($image_data === false) {
        throw new Exception('Failed to read image file');
    }

    $conn->begin_transaction();

    // If this is set as primary, unset other primary images for this venue
    if ($is_primary) {
        $update_query = "UPDATE venue_image_urls SET is_primary = 0 WHERE venue_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $venue_id);
        $update_stmt->execute();
    }

    // Update the venue's main image (LONGBLOB)
    $update_venue_query = "UPDATE venues SET image = ? WHERE venue_id = ?";
    $update_venue_stmt = $conn->prepare($update_venue_query);
    $update_venue_stmt->bind_param("si", $image_data, $venue_id);
    $update_venue_stmt->execute();

    // Also save to venue_images table for multiple image support
    $insert_query = "INSERT INTO venue_images (venue_id, image_data, image_name, image_type, mime_type, file_size, is_primary) 
                     VALUES (?, ?, ?, 'main', ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);

    $image_name = $image['name'];
    $mime_type = $image['type'];
    $file_size = $image['size'];
    $primary_int = $is_primary ? 1 : 0;

    $insert_stmt->bind_param("isssii", $venue_id, $image_data, $image_name, $mime_type, $file_size, $primary_int);
    $insert_stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'venue_id' => $venue_id
    ]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
