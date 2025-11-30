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
    SELECT v.*, l.city, l.province, l.baranggay, l.latitude, l.longitude,
           p.base_price, p.peak_price, p.offpeak_price, 
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
            height: 500px;
            width: 100%;
            border-radius: 0.5rem;
        }

        .map-instruction {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
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

                    <!-- Travel Time Calculator -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-route mr-3 text-indigo-600"></i>
                            Travel Time Calculator
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-map-pin mr-1 text-green-600"></i>
                                    Your Starting Location
                                </label>
                                <div class="flex gap-2">
                                    <input type="text" id="startingPointDisplay" readonly
                                        class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg"
                                        placeholder="Click 'Pin My Location' on the map below">
                                    <button onclick="useCurrentLocation()"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 whitespace-nowrap">
                                        <i class="fas fa-crosshairs"></i>
                                        <span class="hidden sm:inline">Use My Location</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-location-dot mr-1 text-red-600"></i>
                                    Destination
                                </label>
                                <input type="text" readonly
                                    class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed"
                                    value="<?php echo htmlspecialchars($venue['venue_name']); ?>">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <button id="calculateDrivingBtn" onclick="calculateTravelTime('DRIVING')"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center disabled:bg-gray-400 disabled:cursor-not-allowed"
                                    disabled>
                                    <i class="fas fa-car mr-2"></i>
                                    By Car
                                </button>
                                <button id="calculateTransitBtn" onclick="calculateTravelTime('TRANSIT')"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center disabled:bg-gray-400 disabled:cursor-not-allowed"
                                    disabled>
                                    <i class="fas fa-bus mr-2"></i>
                                    Transit
                                </button>
                            </div>

                            <!-- Results -->
                            <div id="travelResults"
                                class="hidden mt-4 p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg border border-indigo-200">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-info-circle text-indigo-600 mt-1"></i>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-gray-900 mb-2">Travel Information</h4>
                                        <div id="travelDetailsContent"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div id="travelError" class="hidden mt-4 p-4 bg-red-50 rounded-lg border border-red-200">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-exclamation-circle text-red-600 mt-1"></i>
                                    <p class="text-red-700 text-sm" id="travelErrorMessage"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-map-marked-alt mr-3 text-indigo-600"></i>
                                Location & Route
                            </h3>
                            <button id="togglePinModeBtn" onclick="togglePinMode()"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                                <i class="fas fa-map-pin"></i>
                                Pin My Location
                            </button>
                        </div>

                        <!-- Map Instructions -->
                        <div id="mapInstructions"
                            class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800 map-instruction flex items-center gap-2">
                                <i class="fas fa-hand-pointer"></i>
                                Click anywhere on the map to set your starting location
                            </p>
                        </div>

                        <div id="map" class="mb-4"></div>
                        <p class="text-gray-600 flex items-center">
                            <i class="fas fa-location-dot mr-2 text-red-600"></i>
                            <strong class="mr-2">Venue:</strong> <?php echo htmlspecialchars($full_address); ?>
                        </p>
                        <p id="startingLocationInfo" class="text-gray-600 flex items-center mt-2 hidden">
                            <i class="fas fa-map-pin mr-2 text-green-600"></i>
                            <strong class="mr-2">Your Location:</strong> <span id="startingLocationText"></span>
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

    <!-- Google Maps API with required libraries -->
    <script>
        (g => {
            var h, a, k, p = "The Google Maps JavaScript API",
                c = "google",
                l = "importLibrary",
                q = "__ib__",
                m = document,
                b = window;
            b = b[c] || (b[c] = {});
            var d = b.maps || (b.maps = {}),
                r = new Set,
                e = new URLSearchParams,
                u = () => h || (h = new Promise(async (f, n) => {
                    await (a = m.createElement("script"));
                    e.set("libraries", [...r] + "");
                    for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
                    e.set("callback", c + ".maps." + q);
                    a.src = `https://maps.googleapis.com/maps/api/js?` + e;
                    d[q] = f;
                    a.onerror = () => h = n(Error(p + " could not load."));
                    a.nonce = m.querySelector("script[nonce]")?.nonce || "";
                    m.head.append(a)
                }));
            d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() =>
                d[l](f, ...n))
        })({
            key: "AIzaSyAAfxgWViv9h7RTVTH3clJe7tkJPXaWQIA",
            v: "weekly"
        });
    </script>

    <!-- Initialize Map Script -->
    <script>
        let map;
        let venueMarker;
        let startMarker;
        let directionsService;
        let directionsRenderer;
        let venueLocation;
        let startLocation = null;
        let isPinMode = false;
        let geocoder;

        async function initMap() {
            // Load required libraries
            const {
                Map
            } = await google.maps.importLibrary("maps");
            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            // Store for global use
            window.AdvancedMarkerElement = AdvancedMarkerElement;

            // Get venue data from PHP
            const venueName = <?php echo json_encode($venue['venue_name']); ?>;
            const address = <?php echo json_encode($full_address); ?>;
            const latitude =
                <?php echo isset($venue['latitude']) && !empty($venue['latitude']) ? $venue['latitude'] : 'null'; ?>;
            const longitude =
                <?php echo isset($venue['longitude']) && !empty($venue['longitude']) ? $venue['longitude'] : 'null'; ?>;

            // Default coordinates (Philippines center) if no coordinates in database
            const defaultLat = 14.5995;
            const defaultLng = 120.9842;

            // Use database coordinates or fall back to default
            const lat = latitude !== null ? parseFloat(latitude) : defaultLat;
            const lng = longitude !== null ? parseFloat(longitude) : defaultLng;

            // Store venue location globally
            venueLocation = {
                lat: lat,
                lng: lng
            };

            // Initialize map centered on venue location
            map = new Map(document.getElementById('map'), {
                center: venueLocation,
                zoom: 13,
                mapId: 'GATHERLY_MAP', // Required for AdvancedMarkerElement
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true,
                zoomControl: true
            });

            // Initialize geocoder
            geocoder = new google.maps.Geocoder();

            // Initialize directions service and renderer for route display
            const {
                DirectionsService,
                DirectionsRenderer
            } = await google.maps.importLibrary("routes");
            directionsService = new DirectionsService();
            directionsRenderer = new DirectionsRenderer({
                map: map,
                suppressMarkers: true, // We'll use custom markers
                polylineOptions: {
                    strokeColor: '#4F46E5',
                    strokeWeight: 5,
                    strokeOpacity: 0.8
                }
            });

            // Create custom venue marker (indigo - matching project theme)
            const venueMarkerElement = document.createElement('div');
            venueMarkerElement.className =
                'bg-indigo-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
            venueMarkerElement.innerHTML = `
                <i class="fas fa-location-dot"></i>
                <span>Venue</span>
        `;

            venueMarker = new AdvancedMarkerElement({
                map: map,
                position: venueLocation,
                content: venueMarkerElement,
                title: venueName
            });

            // Add click listener to show info
            venueMarker.addListener('click', () => {
                new google.maps.InfoWindow({
                    content: `
                    <div style="padding: 10px; max-width: 250px;">
                        <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold; color: #DC2626;">
                            ${venueName}
                        </h3>
                        <p style="margin: 0; font-size: 14px; color: #6B7280;">
                            <i class="fas fa-map-marker-alt" style="color: #DC2626; margin-right: 5px;"></i>
                            ${address}
                        </p>
                    </div>
                `
                }).open(map, venueMarker);
            });

            // Add click listener for pin mode
            map.addListener('click', (event) => {
                if (isPinMode) {
                    setStartLocation(event.latLng);
                }
            });
        }

        // Initialize map on load
        initMap();

        // Toggle pin mode
        function togglePinMode() {
            isPinMode = !isPinMode;
            const btn = document.getElementById('togglePinModeBtn');
            const instructions = document.getElementById('mapInstructions');

            if (isPinMode) {
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-red-600', 'hover:bg-red-700');
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel Pin Mode';
                instructions.classList.remove('hidden');
                map.setOptions({
                    draggableCursor: 'crosshair'
                });
            } else {
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                btn.innerHTML = '<i class="fas fa-map-pin"></i> Pin My Location';
                instructions.classList.add('hidden');
                map.setOptions({
                    draggableCursor: null
                });
            }
        }

        // Set starting location from map click
        async function setStartLocation(latLng) {
            startLocation = {
                lat: latLng.lat(),
                lng: latLng.lng()
            };

            // Remove existing start marker if any
            if (startMarker) {
                startMarker.map = null;
            }

            // Create custom start marker (green - matching success color)
            const startMarkerElement = document.createElement('div');
            startMarkerElement.className =
                'bg-green-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
            startMarkerElement.innerHTML = `
                <i class="fas fa-map-pin"></i>
                <span>Start</span>
        `;

            startMarker = new window.AdvancedMarkerElement({
                map: map,
                position: startLocation,
                content: startMarkerElement,
                title: 'Your Starting Location'
            });

            // Reverse geocode to get address
            try {
                const response = await geocoder.geocode({
                    location: startLocation
                });
                if (response.results[0]) {
                    const address = response.results[0].formatted_address;
                    document.getElementById('startingPointDisplay').value = address;
                    document.getElementById('startingLocationText').textContent = address;
                    document.getElementById('startingLocationInfo').classList.remove('hidden');
                }
            } catch (error) {
                console.error('Geocoding error:', error);
                document.getElementById('startingPointDisplay').value =
                    `${startLocation.lat.toFixed(6)}, ${startLocation.lng.toFixed(6)}`;
                document.getElementById('startingLocationText').textContent =
                    `${startLocation.lat.toFixed(6)}, ${startLocation.lng.toFixed(6)}`;
                document.getElementById('startingLocationInfo').classList.remove('hidden');
            }

            // Enable calculate buttons
            document.getElementById('calculateDrivingBtn').disabled = false;
            document.getElementById('calculateTransitBtn').disabled = false;

            // Exit pin mode
            isPinMode = false;
            togglePinMode();

            // Adjust map to show both markers
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(startLocation);
            bounds.extend(venueLocation);
            map.fitBounds(bounds);
        }

        // Use current GPS location
        function useCurrentLocation() {
            if (!navigator.geolocation) {
                showError('Geolocation is not supported by your browser.');
                return;
            }

            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Getting location...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latLng = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    setStartLocation(new google.maps.LatLng(latLng.lat, latLng.lng));
                    btn.disabled = false;
                    btn.innerHTML =
                        '<i class="fas fa-crosshairs"></i><span class="hidden sm:inline"> Use My Location</span>';
                },
                (error) => {
                    showError('Unable to get your location. Please pin it manually on the map.');
                    btn.disabled = false;
                    btn.innerHTML =
                        '<i class="fas fa-crosshairs"></i><span class="hidden sm:inline"> Use My Location</span>';
                }
            );
        }

        // Calculate travel time and distance
        async function calculateTravelTime(travelMode) {
            if (!startLocation) {
                showError('Please set your starting location first.');
                return;
            }

            const resultsDiv = document.getElementById('travelResults');
            const errorDiv = document.getElementById('travelError');
            const detailsContent = document.getElementById('travelDetailsContent');

            // Hide previous results/errors
            resultsDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');

            // Show loading state
            detailsContent.innerHTML =
                '<div class="flex items-center gap-2"><i class="fas fa-spinner fa-spin"></i> Calculating route...</div>';
            resultsDiv.classList.remove('hidden');

            // For transit, find nearest transit-accessible point near venue
            let destinationPoint = venueLocation;
            let nearestTransitNote = '';

            if (travelMode === 'TRANSIT') {
                // Create a small search radius around venue (500m) to find transit-accessible point
                const searchRadius = 500; // meters
                const offset = searchRadius / 111320; // Convert meters to degrees (approximate)

                // Try multiple points around the venue to find best transit route
                const searchPoints = [
                    venueLocation, // Original venue location
                    {
                        lat: venueLocation.lat + offset,
                        lng: venueLocation.lng
                    }, // North
                    {
                        lat: venueLocation.lat - offset,
                        lng: venueLocation.lng
                    }, // South
                    {
                        lat: venueLocation.lat,
                        lng: venueLocation.lng + offset
                    }, // East
                    {
                        lat: venueLocation.lat,
                        lng: venueLocation.lng - offset
                    }, // West
                ];

                // We'll try the original first, then alternatives if it fails
                destinationPoint = venueLocation;
            }

            // Try to use Directions API for accurate routing
            const request = {
                origin: startLocation,
                destination: destinationPoint,
                travelMode: google.maps.TravelMode[travelMode],
                unitSystem: google.maps.UnitSystem.METRIC
            };

            directionsService.route(request, async function(result, status) {
                const modeIcon = travelMode === 'DRIVING' ? 'fa-car' : 'fa-bus';
                const modeText = travelMode === 'DRIVING' ? 'Driving' : 'Public Transit';
                const modeColor = travelMode === 'DRIVING' ? 'indigo' : 'green';

                if (status === 'OK') {
                    // Success! Use actual route data
                    directionsRenderer.setDirections(result);

                    const route = result.routes[0].legs[0];
                    const distance = route.distance.text;
                    const duration = route.duration.text;

                    // Check if destination is different from venue (for transit)
                    const endLat = route.end_location.lat();
                    const endLng = route.end_location.lng();
                    const distanceToVenue = calculateDistance(endLat, endLng, venueLocation.lat,
                        venueLocation.lng);

                    let walkingNote = '';
                    if (travelMode === 'TRANSIT' && distanceToVenue > 0.05) {
                        // If transit drops off more than 50m from venue
                        const walkingDistance = (distanceToVenue * 1000).toFixed(0); // Convert to meters
                        const walkingTime = Math.round(distanceToVenue * 12); // ~12 min per km walking
                        walkingNote = `
                        <div class="flex items-center gap-2 text-sm mt-2 p-2 bg-blue-50 rounded border border-blue-200">
                            <i class="fas fa-walking text-blue-600"></i>
                            <span class="text-gray-700">
                                <strong>Final walk:</strong> ~${walkingDistance}m (~${walkingTime} min) from nearest transit stop to venue
                            </span>
                        </div>
                    `;
                    }

                    // Get traffic info if available (only for DRIVING mode)
                    let trafficInfo = '';
                    if (travelMode === 'DRIVING' && route.duration_in_traffic) {
                        const trafficDuration = route.duration_in_traffic.text;
                        trafficInfo = `
                        <div class="flex items-center gap-2 text-sm mt-2 p-2 bg-yellow-50 rounded border border-yellow-200">
                            <i class="fas fa-traffic-light text-yellow-600"></i>
                            <span class="text-gray-700">With current traffic: <strong>${trafficDuration}</strong></span>
                        </div>
                    `;
                    }

                    detailsContent.innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 pb-2 border-b border-${modeColor}-200">
                            <i class="fas ${modeIcon} text-${modeColor}-600"></i>
                            <span class="font-semibold text-gray-900">${modeText}</span>
                            <span class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Actual Route</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-500 mb-1">Distance</p>
                                <p class="text-lg font-bold text-${modeColor}-600">
                                    <i class="fas fa-route mr-1"></i>${distance}
                                </p>
                            </div>
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-500 mb-1">Duration</p>
                                <p class="text-lg font-bold text-${modeColor}-600">
                                    <i class="fas fa-clock mr-1"></i>${duration}
                                </p>
                            </div>
                        </div>

                        ${trafficInfo}
                        ${walkingNote}

                        <div class="text-xs text-gray-600 pt-2 border-t border-gray-200">
                            <p class="mb-1">
                                <i class="fas fa-map-pin text-green-600 mr-1"></i> 
                                <strong>From:</strong> ${route.start_address}
                            </p>
                            <p>
                                <i class="fas fa-location-dot text-red-600 mr-1"></i> 
                                <strong>To:</strong> ${route.end_address}
                            </p>
                        </div>

                        <div class="text-xs text-gray-500 italic pt-2">
                            <i class="fas fa-check-circle text-green-600 mr-1"></i>
                            Route displayed on the map shows actual roads
                        </div>
                    </div>
                `;

                    resultsDiv.classList.remove('hidden');
                } else if (status === 'ZERO_RESULTS' && travelMode === 'TRANSIT') {
                    // For transit, try finding alternative nearby points
                    console.log('No transit route found to exact location, trying nearby points...');
                    await tryAlternativeTransitRoutes(startLocation, venueLocation, modeIcon, modeText,
                        modeColor, resultsDiv, detailsContent);
                } else {
                    // Directions API failed, fall back to estimation
                    console.warn('Directions API failed:', status, '- Using estimation method');

                    // Clear any previous directions
                    directionsRenderer.setDirections({
                        routes: []
                    });

                    try {
                        // Calculate straight-line distance using Haversine formula
                        const distance = calculateDistance(
                            startLocation.lat,
                            startLocation.lng,
                            venueLocation.lat,
                            venueLocation.lng
                        );

                        // Estimate travel time based on mode
                        let speedKmh = travelMode === 'DRIVING' ? 40 : 25;

                        // Add 30% to distance for roads (not straight line) - more realistic
                        const roadDistance = distance * 1.3;
                        const durationHours = roadDistance / speedKmh;
                        const durationMinutes = Math.round(durationHours * 60);

                        // Format duration
                        let durationText;
                        if (durationMinutes < 60) {
                            durationText = `${durationMinutes} mins`;
                        } else {
                            const hours = Math.floor(durationMinutes / 60);
                            const mins = durationMinutes % 60;
                            durationText = mins > 0 ? `${hours} hr ${mins} mins` : `${hours} hr`;
                        }

                        // Draw a curved line on map to better represent road route
                        drawCurvedRouteLine(startLocation, venueLocation);

                        // Provide reason for estimation
                        let reasonText = '';
                        if (status === 'ZERO_RESULTS') {
                            reasonText = 'No direct route found by the routing service.';
                        } else if (status === 'NOT_FOUND') {
                            reasonText = 'One or both locations are in areas with limited mapping data.';
                        } else if (status === 'REQUEST_DENIED') {
                            reasonText = 'Route calculation service is currently unavailable.';
                        } else {
                            reasonText = 'Detailed route data is unavailable for this area.';
                        }

                        detailsContent.innerHTML = `
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 pb-2 border-b border-${modeColor}-200">
                                <i class="fas ${modeIcon} text-${modeColor}-600"></i>
                                <span class="font-semibold text-gray-900">${modeText}</span>
                                <span class="ml-auto text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Estimated</span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-white p-3 rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Est. Distance</p>
                                    <p class="text-lg font-bold text-${modeColor}-600">
                                        <i class="fas fa-route mr-1"></i>~${roadDistance.toFixed(1)} km
                                    </p>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Est. Duration</p>
                                    <p class="text-lg font-bold text-${modeColor}-600">
                                        <i class="fas fa-clock mr-1"></i>~${durationText}
                                    </p>
                                </div>
                            </div>

                            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                <p class="text-xs text-yellow-800 mb-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <strong>Estimated Route:</strong> ${reasonText}
                                </p>
                                <p class="text-xs text-yellow-700 mt-1">
                                    Distance and time are calculated estimates. Actual travel may vary based on roads, traffic, and route taken.
                                </p>
                            </div>

                            <div class="text-xs text-gray-600 pt-2 border-t border-gray-200">
                                <p class="mb-1">
                                    <i class="fas fa-map-pin text-green-600 mr-1"></i> 
                                    <strong>From:</strong> ${document.getElementById('startingPointDisplay').value}
                                </p>
                                <p>
                                    <i class="fas fa-location-dot text-red-600 mr-1"></i> 
                                    <strong>To:</strong> <?php echo htmlspecialchars($venue['venue_name']); ?>
                                </p>
                                <p class="mt-2 text-xs text-gray-500 italic">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Straight-line distance: ${distance.toFixed(1)} km
                                </p>
                            </div>
                        </div>
                    `;

                        resultsDiv.classList.remove('hidden');
                    } catch (error) {
                        console.error('Error calculating route:', error);
                        showError('Unable to calculate distance. Please try again.');
                    }
                }
            });
        }

        // Try alternative nearby points for transit routes
        async function tryAlternativeTransitRoutes(start, venue, modeIcon, modeText, modeColor, resultsDiv,
            detailsContent) {
            // Create search points in a grid around the venue (within 1km radius)
            const offsets = [{
                    lat: 0.005,
                    lng: 0
                }, // ~500m north
                {
                    lat: -0.005,
                    lng: 0
                }, // ~500m south
                {
                    lat: 0,
                    lng: 0.005
                }, // ~500m east
                {
                    lat: 0,
                    lng: -0.005
                }, // ~500m west
                {
                    lat: 0.01,
                    lng: 0
                }, // ~1km north
                {
                    lat: -0.01,
                    lng: 0
                }, // ~1km south
                {
                    lat: 0,
                    lng: 0.01
                }, // ~1km east
                {
                    lat: 0,
                    lng: -0.01
                }, // ~1km west
            ];

            for (const offset of offsets) {
                const testPoint = {
                    lat: venue.lat + offset.lat,
                    lng: venue.lng + offset.lng
                };

                try {
                    const result = await new Promise((resolve, reject) => {
                        directionsService.route({
                            origin: start,
                            destination: testPoint,
                            travelMode: google.maps.TravelMode.TRANSIT,
                            unitSystem: google.maps.UnitSystem.METRIC
                        }, (res, status) => {
                            if (status === 'OK') resolve(res);
                            else reject(status);
                        });
                    });

                    // Found a route! Display it
                    directionsRenderer.setDirections(result);
                    const route = result.routes[0].legs[0];
                    const distance = route.distance.text;
                    const duration = route.duration.text;

                    // Calculate walking distance from transit drop-off to venue
                    const endLat = route.end_location.lat();
                    const endLng = route.end_location.lng();
                    const walkingDist = calculateDistance(endLat, endLng, venue.lat, venue.lng);
                    const walkingMeters = (walkingDist * 1000).toFixed(0);
                    const walkingMins = Math.round(walkingDist * 12);

                    detailsContent.innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 pb-2 border-b border-${modeColor}-200">
                            <i class="fas ${modeIcon} text-${modeColor}-600"></i>
                            <span class="font-semibold text-gray-900">${modeText}</span>
                            <span class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Actual Route</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-500 mb-1">Distance</p>
                                <p class="text-lg font-bold text-${modeColor}-600">
                                    <i class="fas fa-route mr-1"></i>${distance}
                                </p>
                            </div>
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-500 mb-1">Duration</p>
                                <p class="text-lg font-bold text-${modeColor}-600">
                                    <i class="fas fa-clock mr-1"></i>${duration}
                                </p>
                            </div>
                        </div>

                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                            <p class="text-xs text-blue-800 mb-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Transit Info:</strong> Route ends at nearest accessible transit stop
                            </p>
                            <div class="flex items-center gap-2 mt-2 text-sm">
                                <i class="fas fa-walking text-blue-600"></i>
                                <span class="text-gray-700">
                                    <strong>Walk to venue:</strong> ~${walkingMeters}m (~${walkingMins} min)
                                </span>
                            </div>
                        </div>

                        <div class="text-xs text-gray-600 pt-2 border-t border-gray-200">
                            <p class="mb-1">
                                <i class="fas fa-map-pin text-green-600 mr-1"></i> 
                                <strong>From:</strong> ${route.start_address}
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-bus text-green-600 mr-1"></i> 
                                <strong>Transit to:</strong> ${route.end_address}
                            </p>
                            <p>
                                <i class="fas fa-location-dot text-red-600 mr-1"></i> 
                                <strong>Final destination:</strong> <?php echo htmlspecialchars($venue['venue_name']); ?>
                            </p>
                        </div>

                        <div class="text-xs text-gray-500 italic pt-2">
                            <i class="fas fa-check-circle text-green-600 mr-1"></i>
                            Route to nearest transit-accessible point near venue
                        </div>
                    </div>
                `;

                    resultsDiv.classList.remove('hidden');
                    return; // Success, exit the function
                } catch (error) {
                    // This point didn't work, try next one
                    continue;
                }
            }

            // If we get here, no alternative points worked - use fallback estimation
            console.warn('No transit routes found to any nearby points - using estimation');
            showTransitEstimation(start, venue, modeIcon, modeText, modeColor, resultsDiv, detailsContent);
        }

        // Show transit estimation when no routes found
        function showTransitEstimation(start, venue, modeIcon, modeText, modeColor, resultsDiv, detailsContent) {
            // Clear any previous directions
            directionsRenderer.setDirections({
                routes: []
            });

            // Calculate straight-line distance
            const distance = calculateDistance(start.lat, start.lng, venue.lat, venue.lng);
            const speedKmh = 25; // Average transit speed
            const roadDistance = distance * 1.3;
            const durationHours = roadDistance / speedKmh;
            const durationMinutes = Math.round(durationHours * 60);

            let durationText;
            if (durationMinutes < 60) {
                durationText = `${durationMinutes} mins`;
            } else {
                const hours = Math.floor(durationMinutes / 60);
                const mins = durationMinutes % 60;
                durationText = mins > 0 ? `${hours} hr ${mins} mins` : `${hours} hr`;
            }

            drawCurvedRouteLine(start, venue);

            detailsContent.innerHTML = `
            <div class="space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-${modeColor}-200">
                    <i class="fas ${modeIcon} text-${modeColor}-600"></i>
                    <span class="font-semibold text-gray-900">${modeText}</span>
                    <span class="ml-auto text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Estimated</span>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                        <p class="text-xs text-gray-500 mb-1">Est. Distance</p>
                        <p class="text-lg font-bold text-${modeColor}-600">
                            <i class="fas fa-route mr-1"></i>~${roadDistance.toFixed(1)} km
                        </p>
                    </div>
                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                        <p class="text-xs text-gray-500 mb-1">Est. Duration</p>
                        <p class="text-lg font-bold text-${modeColor}-600">
                            <i class="fas fa-clock mr-1"></i>~${durationText}
                        </p>
                    </div>
                </div>

                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                    <p class="text-xs text-yellow-800 mb-1">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Transit Route Unavailable:</strong> No public transit routes found to this area.
                    </p>
                    <p class="text-xs text-yellow-700 mt-1">
                        This venue may not be well-served by public transportation. Consider using driving or alternative transport.
                    </p>
                </div>

                <div class="text-xs text-gray-600 pt-2 border-t border-gray-200">
                    <p class="mb-1">
                        <i class="fas fa-map-pin text-green-600 mr-1"></i> 
                        <strong>From:</strong> ${document.getElementById('startingPointDisplay').value}
                    </p>
                    <p>
                        <i class="fas fa-location-dot text-red-600 mr-1"></i> 
                        <strong>To:</strong> <?php echo htmlspecialchars($venue['venue_name']); ?>
                    </p>
                </div>
            </div>
        `;

            resultsDiv.classList.remove('hidden');
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in km
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function toRad(degrees) {
            return degrees * (Math.PI / 180);
        }

        // Draw route line on map
        let routeLine = null;

        function drawRouteLine(start, end) {
            // Remove existing line if any
            if (routeLine) {
                routeLine.setMap(null);
            }

            // Create polyline
            routeLine = new google.maps.Polyline({
                path: [start, end],
                geodesic: true,
                strokeColor: '#4F46E5',
                strokeOpacity: 0.8,
                strokeWeight: 4,
                map: map,
                icons: [{
                    icon: {
                        path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                        scale: 3,
                        strokeColor: '#4F46E5'
                    },
                    offset: '100%'
                }]
            });

            // Adjust map to show both points
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(start);
            bounds.extend(end);
            map.fitBounds(bounds);
        }

        // Draw curved route line to better simulate road route
        function drawCurvedRouteLine(start, end) {
            // Remove existing line if any
            if (routeLine) {
                routeLine.setMap(null);
            }

            // Create a curved path with intermediate points
            const path = [];
            const numPoints = 20; // Number of points to create smooth curve

            for (let i = 0; i <= numPoints; i++) {
                const fraction = i / numPoints;

                // Linear interpolation
                const lat = start.lat + (end.lat - start.lat) * fraction;
                const lng = start.lng + (end.lng - start.lng) * fraction;

                // Add slight curve (perpendicular offset)
                const curvature = Math.sin(fraction * Math.PI) * 0.015; // Adjust curve intensity
                const perpLat = -(end.lng - start.lng) * curvature;
                const perpLng = (end.lat - start.lat) * curvature;

                path.push({
                    lat: lat + perpLat,
                    lng: lng + perpLng
                });
            }

            // Create dashed polyline to indicate it's an estimate
            routeLine = new google.maps.Polyline({
                path: path,
                geodesic: false,
                strokeColor: '#F59E0B', // Orange/amber for estimation
                strokeOpacity: 0,
                strokeWeight: 0,
                map: map,
                icons: [{
                    icon: {
                        path: 'M 0,-1 0,1',
                        strokeOpacity: 0.8,
                        strokeWeight: 4,
                        scale: 3
                    },
                    offset: '0',
                    repeat: '15px'
                }, {
                    icon: {
                        path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                        scale: 3,
                        strokeColor: '#F59E0B',
                        fillColor: '#F59E0B',
                        fillOpacity: 0.8
                    },
                    offset: '100%'
                }]
            });

            // Adjust map to show both points
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(start);
            bounds.extend(end);
            map.fitBounds(bounds);
        }

        // Calculate distance between two points using Haversine formula (in km)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in km
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function toRad(degrees) {
            return degrees * (Math.PI / 180);
        }

        // Show error message
        function showError(message) {
            const errorDiv = document.getElementById('travelError');
            const errorMessage = document.getElementById('travelErrorMessage');
            errorMessage.textContent = message;
            errorDiv.classList.remove('hidden');
        }
    </script>
</body>

</html>