<?php
// Helper script to retrieve venue image as binary data
// Usage: <img src="get-venue-image.php?venue_id=1">

require_once __DIR__ . '/dbconnect.php';

if (!isset($_GET['venue_id'])) {
    http_response_code(400);
    exit('Missing venue_id parameter');
}

$venue_id = intval($_GET['venue_id']);

try {
    // First, try to get image from venues table (LONGBLOB)
    $query = "SELECT image FROM venues WHERE venue_id = ? AND image IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Output image with proper headers
        header("Content-Type: image/jpeg"); // Default to JPEG, could be determined from mime_type
        header("Cache-Control: max-age=2592000"); // Cache for 30 days
        echo $row['image'];
    } else {
        // If no LONGBLOB image, try venue_images table
        $query2 = "SELECT image_data, mime_type FROM venue_images WHERE venue_id = ? AND is_primary = 1 LIMIT 1";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bind_param("i", $venue_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($row2 = $result2->fetch_assoc()) {
            header("Content-Type: " . ($row2['mime_type'] ?? 'image/jpeg'));
            header("Cache-Control: max-age=2592000"); // Cache for 30 days
            echo $row2['image_data'];
        } else {
            // If no binary image, try venue_image_urls table
            $query3 = "SELECT image_url FROM venue_image_urls WHERE venue_id = ? AND is_primary = 1 LIMIT 1";
            $stmt3 = $conn->prepare($query3);
            $stmt3->bind_param("i", $venue_id);
            $stmt3->execute();
            $result3 = $stmt3->get_result();

            if ($row3 = $result3->fetch_assoc()) {
                // Generate placeholder image using CDN
                // Use Unsplash Source API for venue-related images
                $seed = $venue_id; // Use venue ID as seed for consistent images
                $categories = ['venue', 'event', 'hall', 'resort', 'garden', 'ballroom'];
                $category = $categories[$venue_id % count($categories)];

                // Redirect to Unsplash placeholder
                $placeholder_url = "https://source.unsplash.com/800x600/?{$category},events,{$seed}";
                header("Location: $placeholder_url");
                exit;
            }

            // Return placeholder image from CDN if no image found
            $seed = $venue_id;
            $placeholder_url = "https://source.unsplash.com/800x600/?venue,events,{$seed}";
            header("Location: $placeholder_url");
            exit;
        }
    }
} catch (Exception $e) {
    // On error, redirect to Unsplash placeholder
    $seed = isset($venue_id) ? $venue_id : rand(1, 1000);
    header("Location: https://source.unsplash.com/800x600/?venue,events,{$seed}");
    exit;
}
