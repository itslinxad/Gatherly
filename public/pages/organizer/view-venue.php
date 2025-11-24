<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$venue_id = isset($_GET['venue_id']) ? (int)$_GET['venue_id'] : 0;

if ($venue_id <= 0) {
    header("Location: find-venues.php");
    exit();
}

// Fetch venue details
$venue_query = "SELECT * FROM venues WHERE venue_id = ? AND availability_status = 'available'";
$stmt = $conn->prepare($venue_query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$venue_result = $stmt->get_result();

if ($venue_result->num_rows === 0) {
    header("Location: find-venues.php");
    exit();
}

$venue = $venue_result->fetch_assoc();

// Fetch amenities for this venue
$amenities_query = "SELECT a.amenity_name, va.custom_price, a.default_price 
                    FROM venue_amenities va 
                    JOIN amenities a ON va.amenity_id = a.amenity_id 
                    WHERE va.venue_id = ?";
$stmt = $conn->prepare($amenities_query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$amenities_result = $stmt->get_result();
$amenities = [];
while ($row = $amenities_result->fetch_assoc()) {
    $amenities[] = $row;
}

// Determine suitable event types based on venue characteristics
function getSuitableEvents($venue) {
    $suitable = [];
    $capacity = $venue['capacity'];
    $location_type = strtolower($venue['location']);
    $description = strtolower($venue['description']);
    
    if ($capacity >= 200) {
        $suitable[] = "Corporate Events";
        $suitable[] = "Concerts";
        $suitable[] = "Large Weddings";
    }
    
    if ($capacity >= 100 && $capacity < 200) {
        $suitable[] = "Weddings";
        $suitable[] = "Birthday Parties";
        $suitable[] = "Corporate Meetings";
    }
    
    if ($capacity < 100) {
        $suitable[] = "Intimate Weddings";
        $suitable[] = "Small Birthday Parties";
        $suitable[] = "Business Meetings";
    }
    
    if (strpos($description, 'garden') !== false || strpos($description, 'outdoor') !== false) {
        $suitable[] = "Garden Weddings";
        $suitable[] = "Outdoor Parties";
    }
    
    if (strpos($description, 'elegant') !== false || strpos($description, 'indoor') !== false) {
        $suitable[] = "Formal Events";
        $suitable[] = "Gala Dinners";
    }
    
    if (strpos($description, 'seaside') !== false || strpos($description, 'beach') !== false) {
        $suitable[] = "Beach Weddings";
        $suitable[] = "Summer Parties";
    }
    
    return array_unique($suitable);
}

$suitable_events = getSuitableEvents($venue);

// Fetch dynamic pricing if available
$dynamic_pricing_query = "SELECT * FROM dynamic_pricing WHERE venue_id = ? 
                         AND CURDATE() BETWEEN date_range_start AND date_range_end";
$stmt = $conn->prepare($dynamic_pricing_query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$dynamic_pricing = $stmt->get_result()->fetch_assoc();

$final_price = $dynamic_pricing ? $dynamic_pricing['price'] : $venue['base_price'];

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($venue['venue_name']); ?> | Gatherly</title>
    <link rel="stylesheet" href="../../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../../src/output.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .venue-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        .venue-content {
            background: white;
            border-radius: 16px;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
            position: relative;
        }
        .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
        }
        .venue-image {
            height: 300px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
    </style>
</head>
<body class="font-['Montserrat']">
    <div class="venue-popup" onclick="closePopup(event)">
        <div class="venue-content" onclick="stopPropagation(event)">
            <button class="close-btn" onclick="closePopup()">
                <i class="fas fa-times text-gray-600 text-xl"></i>
            </button>
            
            <div class="venue-image">
                <i class="fas fa-landmark text-4xl"></i>
                <span class="ml-3 text-2xl"><?php echo htmlspecialchars($venue['venue_name']); ?></span>
            </div>
            
            <div class="p-6">
                <!-- Basic Info -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($venue['venue_name']); ?></h2>
                    <div class="flex items-center text-gray-600 mb-2">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span><?php echo htmlspecialchars($venue['location']); ?></span>
                    </div>
                    <div class="flex items-center text-gray-600 mb-4">
                        <i class="fas fa-users mr-2"></i>
                        <span>Capacity: <?php echo $venue['capacity']; ?> guests</span>
                    </div>
                    
                    <?php if ($dynamic_pricing): ?>
                        <div class="flex items-center text-green-600 font-bold text-lg">
                            <i class="fas fa-tag mr-2"></i>
                            <span>₱<?php echo number_format($final_price, 2); ?></span>
                            <span class="text-sm text-gray-500 ml-2">(Dynamic Pricing)</span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center text-green-600 font-bold text-lg">
                            <i class="fas fa-tag mr-2"></i>
                            <span>₱<?php echo number_format($venue['base_price'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Description -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Description</h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($venue['description']); ?></p>
                </div>
                
                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Amenities</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <?php foreach ($amenities as $amenity): ?>
                        <div class="flex items-center p-2 bg-gray-50 rounded-lg">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span class="text-gray-700"><?php echo htmlspecialchars($amenity['amenity_name']); ?></span>
                            <?php if ($amenity['custom_price'] !== null): ?>
                                <span class="text-sm text-green-600 ml-auto">₱<?php echo number_format($amenity['custom_price'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Suitable For -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Suitable For</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($suitable_events as $event_type): ?>
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-700 text-sm rounded-full">
                            <?php echo htmlspecialchars($event_type); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <a href="create-event.php?venue_id=<?php echo $venue_id; ?>" 
                       class="flex-1 bg-indigo-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-indigo-700 transition-colors text-center">
                        <i class="fas fa-calendar-plus mr-2"></i>Book This Venue
                    </a>
                    <button onclick="closePopup()" 
                            class="flex-1 border border-gray-300 text-gray-700 py-3 px-4 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function closePopup(event = null) {
        if (event && event.target !== event.currentTarget) return;
        window.location.href = 'find-venues.php';
    }
    
    function stopPropagation(event) {
        event.stopPropagation();
    }
    </script>
</body>
</html>