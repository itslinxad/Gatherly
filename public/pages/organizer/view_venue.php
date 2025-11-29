<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Organizer';
$user_id = $_SESSION['user_id'];

// Get venue ID from URL
$venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;

if ($venue_id <= 0) {
    header("Location: find-venues.php");
    exit();
}

// Fetch venue details with location
$venue_query = "
    SELECT v.*, l.city, l.province, l.baranggay, p.base_price, p.peak_price, p.offpeak_price, 
           p.weekday_price, p.weekend_price, u.first_name as manager_first_name, 
           u.last_name as manager_last_name, u.phone as manager_phone, u.email as manager_email,
           pk.two_wheels, pk.four_wheels
    FROM venues v 
    LEFT JOIN locations l ON v.location_id = l.location_id
    LEFT JOIN prices p ON v.venue_id = p.venue_id
    LEFT JOIN users u ON v.manager_id = u.user_id
    LEFT JOIN parking pk ON v.venue_id = pk.venue_id
    WHERE v.venue_id = ? AND v.status = 'active'
";

$stmt = $conn->prepare($venue_query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: find-venues.php");
    exit();
}

$venue = $result->fetch_assoc();
$stmt->close();

// Fetch venue amenities
$amenities_query = "
    SELECT a.amenity_name, COALESCE(va.custom_price, a.default_price) as price
    FROM venue_amenities va 
    JOIN amenities a ON va.amenity_id = a.amenity_id
    WHERE va.venue_id = ?
    ORDER BY a.amenity_name
";
$stmt = $conn->prepare($amenities_query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$amenities_result = $stmt->get_result();
$amenities = [];
while ($row = $amenities_result->fetch_assoc()) {
    $amenities[] = $row;
}
$stmt->close();

// Fetch parking info
$parking_query = "SELECT two_wheels, four_wheels FROM parking WHERE venue_id = ?";
$stmt = $conn->prepare($parking_query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$parking_result = $stmt->get_result();
$parking = $parking_result->fetch_assoc();
$stmt->close();

$conn->close();

// Build full address for map
$full_address = trim(($venue['baranggay'] ?? '') . ', ' . ($venue['city'] ?? '') . ', ' . ($venue['province'] ?? ''));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($venue['venue_name']); ?> | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet"
        href="../../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 0.5rem;
        }
    </style>
</head>

<body class="bg-gray-100 font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="find-venues.php"
                        class="text-gray-600 hover:text-indigo-600 transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <span class="hidden sm:inline">Back to Venues</span>
                    </a>
                    <div class="border-l border-gray-300 h-6"></div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Venue Details</h1>
                </div>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Venue Header -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <!-- Image Gallery -->
                <div class="relative bg-gray-200 h-96 flex items-center justify-center">
                    <span class="text-gray-500 text-lg">No image available</span>
                </div>

                <!-- Venue Info -->
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-2">
                                <?php echo htmlspecialchars($venue['venue_name']); ?>
                            </h2>
                            <div class="flex items-center text-gray-600 mb-2">
                                <i class="fas fa-map-marker-alt mr-2 text-indigo-600"></i>
                                <span><?php echo htmlspecialchars($full_address); ?></span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-users mr-2 text-indigo-600"></i>
                                <span>Capacity: <?php echo number_format($venue['capacity']); ?> guests</span>
                            </div>
                        </div>
                        <div class="lg:text-right">
                            <div class="inline-block bg-green-100 text-green-800 px-4 py-2 rounded-lg mb-4">
                                <i class="fas fa-check-circle mr-2"></i>
                                <?php echo ucfirst($venue['availability_status']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Select Venue Button -->
                    <div class="border-t border-gray-200 pt-6">
                        <a href="create-event.php?venue_id=<?php echo $venue_id; ?>"
                            class="block w-full sm:w-auto sm:inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                            <i class="fas fa-calendar-check mr-3"></i>
                            Select This Venue for Event
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Description -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-info-circle mr-3 text-indigo-600"></i>
                            Description
                        </h3>
                        <p class="text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($venue['description'] ?? 'No description available.')); ?>
                        </p>
                    </div>

                    <!-- Pricing -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-tag mr-3 text-indigo-600"></i>
                            Pricing
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-indigo-50 rounded-lg p-4 border-l-4 border-indigo-600">
                                <p class="text-sm text-gray-600 mb-1">Base Price</p>
                                <p class="text-2xl font-bold text-indigo-600">
                                    ₱<?php echo number_format($venue['base_price'], 2); ?>
                                </p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-600">
                                <p class="text-sm text-gray-600 mb-1">Weekday Price</p>
                                <p class="text-2xl font-bold text-green-600">
                                    ₱<?php echo number_format($venue['weekday_price'], 2); ?>
                                </p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-600">
                                <p class="text-sm text-gray-600 mb-1">Weekend Price</p>
                                <p class="text-2xl font-bold text-purple-600">
                                    ₱<?php echo number_format($venue['weekend_price'], 2); ?>
                                </p>
                            </div>
                            <div class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-600">
                                <p class="text-sm text-gray-600 mb-1">Peak Season</p>
                                <p class="text-2xl font-bold text-orange-600">
                                    ₱<?php echo number_format($venue['peak_price'], 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Amenities -->
                    <?php if (!empty($amenities)): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-check-circle mr-3 text-indigo-600"></i>
                                Amenities & Services
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($amenities as $amenity): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-3"></i>
                                            <span
                                                class="text-gray-800"><?php echo htmlspecialchars($amenity['amenity_name']); ?></span>
                                        </div>
                                        <span class="text-sm font-semibold text-indigo-600">
                                            ₱<?php echo number_format($amenity['price'], 2); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Map -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-map-marked-alt mr-3 text-indigo-600"></i>
                            Location
                        </h3>
                        <div id="map" class="mb-4"></div>
                        <p class="text-gray-600 flex items-center">
                            <i class="fas fa-location-dot mr-2 text-indigo-600"></i>
                            <?php echo htmlspecialchars($full_address); ?>
                        </p>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Parking Info -->
                    <?php if ($parking): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-parking mr-3 text-indigo-600"></i>
                                Parking
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-motorcycle text-blue-600 mr-3"></i>
                                        <span class="text-gray-800">Two Wheels</span>
                                    </div>
                                    <span class="font-bold text-blue-600"><?php echo $parking['two_wheels']; ?> slots</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-car text-indigo-600 mr-3"></i>
                                        <span class="text-gray-800">Four Wheels</span>
                                    </div>
                                    <span class="font-bold text-indigo-600"><?php echo $parking['four_wheels']; ?>
                                        slots</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Manager Contact -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user-tie mr-3 text-indigo-600"></i>
                            Venue Manager
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-user w-5 text-indigo-600 mr-3"></i>
                                <span><?php echo htmlspecialchars($venue['manager_first_name'] . ' ' . $venue['manager_last_name']); ?></span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-phone w-5 text-indigo-600 mr-3"></i>
                                <a href="tel:<?php echo htmlspecialchars($venue['manager_phone']); ?>"
                                    class="hover:text-indigo-600">
                                    <?php echo htmlspecialchars($venue['manager_phone']); ?>
                                </a>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-envelope w-5 text-indigo-600 mr-3"></i>
                                <a href="mailto:<?php echo htmlspecialchars($venue['manager_email']); ?>"
                                    class="hover:text-indigo-600 break-all">
                                    <?php echo htmlspecialchars($venue['manager_email']); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div
                        class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl shadow-sm border border-indigo-200 p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-bolt mr-3 text-indigo-600"></i>
                            Quick Actions
                        </h3>
                        <div class="space-y-3">
                            <a href="create-event.php?venue_id=<?php echo $venue_id; ?>"
                                class="block w-full px-4 py-3 text-center font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-calendar-plus mr-2"></i>
                                Create Event Here
                            </a>
                            <a href="find-venues.php"
                                class="block w-full px-4 py-3 text-center font-medium text-indigo-600 bg-white border border-indigo-300 rounded-lg hover:bg-indigo-50 transition-colors">
                                <i class="fas fa-search mr-2"></i>
                                Browse Other Venues
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet.js for Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const address = <?php echo json_encode($full_address); ?>;
        const venueName = <?php echo json_encode($venue['venue_name']); ?>;

        // Default coordinates (Philippines center)
        let defaultLat = 14.5995;
        let defaultLng = 120.9842;

        // Initialize map
        const map = L.map('map').setView([defaultLat, defaultLng], 13);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Geocode address using Nominatim
        if (address) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lon = parseFloat(data[0].lon);

                        // Center map on location
                        map.setView([lat, lon], 15);

                        // Add marker
                        const marker = L.marker([lat, lon]).addTo(map);
                        marker.bindPopup(`<b>${venueName}</b><br>${address}`).openPopup();
                    } else {
                        console.log('Address not found, using default location');
                        const marker = L.marker([defaultLat, defaultLng]).addTo(map);
                        marker.bindPopup(`<b>${venueName}</b><br>${address}`).openPopup();
                    }
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    const marker = L.marker([defaultLat, defaultLng]).addTo(map);
                    marker.bindPopup(`<b>${venueName}</b><br>${address}`).openPopup();
                });
        }
    </script>
</body>

</html>