<?php
require_once '../../src/services/dbconnect.php';

$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$venue_id) {
    header("Location: venues.php");
    exit();
}

// Fetch venue details
$query = "SELECT v.*, 
          l.city, l.province, l.baranggay, l.latitude, l.longitude,
          CONCAT(l.city, ', ', l.province) as location
          FROM venues v
          LEFT JOIN locations l ON v.location_id = l.location_id
          WHERE v.venue_id = ? AND v.availability_status = 'available'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $venue_id);
$stmt->execute();
$result = $stmt->get_result();
$venue = $result->fetch_assoc();

if (!$venue) {
    header("Location: venues.php");
    exit();
}

// Get confirmed events for this venue (upcoming only)
$events_query = "SELECT e.event_name, e.event_date, e.expected_guests,
    CONCAT(u.first_name, ' ', u.last_name) as organizer_name
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.venue_id = ? AND e.status = 'confirmed' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC";

$stmt_events = $conn->prepare($events_query);
$stmt_events->bind_param("i", $venue_id);
$stmt_events->execute();
$confirmed_events = $stmt_events->get_result();

// Get amenities
$amenities_query = "SELECT a.amenity_name 
    FROM venue_amenities va
    JOIN amenities a ON va.amenity_id = a.amenity_id
    WHERE va.venue_id = ?";

$stmt_amenities = $conn->prepare($amenities_query);
$stmt_amenities->bind_param("i", $venue_id);
$stmt_amenities->execute();
$amenities_result = $stmt_amenities->get_result();

$amenities = [];
while ($row = $amenities_result->fetch_assoc()) {
    $amenities[] = $row['amenity_name'];
}

$imageSrc = !empty($venue['image'])
    ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
    : '../assets/images/venue-placeholder.jpg';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($venue['venue_name']); ?> | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gradient-to-br from-green-50 via-white to-teal-50 font-['Montserrat']">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 w-full border-b border-gray-200 shadow-md bg-white/90 backdrop-blur-lg">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-12 md:h-16">
                <div class="flex items-center h-full">
                    <a href="../../index.php" class="flex items-center group">
                        <img class="w-8 h-8 mr-2 transition-transform sm:w-10 sm:h-10 group-hover:scale-110"
                            src="../assets/images/logo.png" alt="Gatherly Logo">
                        <span class="text-lg font-bold text-gray-800 sm:text-xl">Gatherly</span>
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="venues.php"
                        class="px-3 sm:px-4 py-2 text-sm sm:text-base font-semibold text-gray-700 transition-all hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Venues
                    </a>
                    <a href="signin.php"
                        class="px-3 sm:px-4 py-2 text-sm sm:text-base font-semibold text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5">
                        Sign in
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container px-4 py-10 mx-auto sm:px-6 lg:px-8 max-w-6xl">
        <!-- Venue Header -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-8">
            <!-- Venue Image -->
            <div class="w-full h-96 overflow-hidden bg-gray-100">
                <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($venue['venue_name']); ?>"
                    class="w-full h-full object-cover object-center">
            </div>

            <!-- Venue Info -->
            <div class="p-6 sm:p-8">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($venue['venue_name']); ?>
                        </h1>
                        <p class="text-lg text-gray-600 flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-green-500"></i>
                            <?php echo htmlspecialchars($venue['location']); ?>
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-full bg-green-50 text-green-700 border border-green-300 shadow-sm">
                        <i class="fas fa-circle text-[8px] text-green-500"></i>
                        Available for Booking
                    </span>
                </div>

                <!-- Key Details Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <div class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-medium">Capacity</p>
                            <p class="text-lg font-bold text-gray-800">
                                <?php echo htmlspecialchars($venue['capacity']); ?> guests</p>
                        </div>
                    </div>

                    <?php if (!empty($venue['baranggay'])): ?>
                    <div class="flex items-center gap-3 p-4 bg-purple-50 rounded-lg border border-purple-200">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-location-dot text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-medium">Barangay</p>
                            <p class="text-lg font-bold text-gray-800">
                                <?php echo htmlspecialchars($venue['baranggay']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-3 p-4 bg-amber-50 rounded-lg border border-amber-200">
                        <div class="p-3 bg-amber-100 rounded-lg">
                            <i class="fas fa-calendar-check text-amber-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-medium">Status</p>
                            <p class="text-lg font-bold text-green-700">Available</p>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-align-left text-indigo-600"></i>
                        About This Venue
                    </h2>
                    <p class="text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($venue['description'])); ?>
                    </p>
                </div>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-star text-indigo-600"></i>
                        Amenities
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($amenities as $amenity): ?>
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-full text-sm font-medium border border-indigo-200">
                            <i class="fas fa-check-circle text-indigo-500 text-xs"></i>
                            <?php echo htmlspecialchars($amenity); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Map Section -->
        <?php if (!empty($venue['latitude']) && !empty($venue['longitude'])): ?>
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-8 p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-map-location-dot text-indigo-600"></i>
                Location & Route Calculator
            </h2>

            <!-- Travel Time Calculator -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-route text-indigo-600"></i>
                    Calculate Travel Time
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-pin mr-1 text-green-600"></i>
                            Your Starting Location
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="startingPointDisplay" readonly
                                class="flex-1 px-4 py-2 bg-white border border-gray-300 rounded-lg"
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
            <div class="mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-map-marked-alt text-indigo-600"></i>
                        Interactive Map
                    </h3>
                    <button id="togglePinModeBtn" onclick="togglePinMode()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i class="fas fa-map-pin"></i>
                        Pin My Location
                    </button>
                </div>

                <!-- Map Instructions -->
                <div id="mapInstructions" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800 flex items-center gap-2">
                        <i class="fas fa-hand-pointer"></i>
                        Click anywhere on the map to set your starting location
                    </p>
                </div>

                <div id="map" class="w-full h-96 rounded-xl border border-gray-200"></div>
            </div>

            <p class="text-gray-600 flex items-center text-sm">
                <i class="fas fa-location-dot mr-2 text-red-600"></i>
                <strong class="mr-2">Venue:</strong> <?php echo htmlspecialchars($venue['location']); ?>
            </p>
            <p id="startingLocationInfo" class="text-gray-600 flex items-center mt-2 text-sm hidden">
                <i class="fas fa-map-pin mr-2 text-green-600"></i>
                <strong class="mr-2">Your Location:</strong> <span id="startingLocationText"></span>
            </p>
        </div>
        <?php endif; ?>

        <!-- Upcoming Events -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-days text-indigo-600"></i>
                Upcoming Events
            </h2>

            <?php if ($confirmed_events && $confirmed_events->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Event Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Guests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Organizer</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($event = $confirmed_events->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($event['event_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <i class="fas fa-users text-blue-500 mr-1"></i>
                                    <?php echo htmlspecialchars($event['expected_guests']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($event['organizer_name']); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <i class="fas fa-calendar-xmark text-5xl text-gray-400 mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">No Upcoming Events</h3>
                <p class="text-gray-500">This venue doesn't have any upcoming confirmed bookings.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Call to Action -->
        <div class="mt-8 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-8 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3">Interested in This Venue?</h2>
            <p class="text-indigo-100 mb-6 max-w-2xl mx-auto">
                Sign in to your account to book this venue for your next event or create a new account to get started.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="signin.php"
                    class="inline-block px-8 py-3 bg-white text-indigo-600 font-semibold rounded-lg hover:bg-gray-50 transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In to Book
                </a>
                <a href="signup.php"
                    class="inline-block px-8 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white/10 transition-all">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </a>
            </div>
        </div>
    </div>

    <?php include '../../src/components/footer.php'; ?>

    <!-- Google Maps API -->
    <?php if (!empty($venue['latitude']) && !empty($venue['longitude'])): ?>
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
        key: "YOUR_GOOGLE_MAPS_API_KEY_HERE",
        v: "weekly",
    });
    </script>

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
        const position = {
            lat: <?php echo floatval($venue['latitude']); ?>,
            lng: <?php echo floatval($venue['longitude']); ?>
        };

        venueLocation = position;

        const {
            Map
        } = await google.maps.importLibrary("maps");
        const {
            AdvancedMarkerElement
        } = await google.maps.importLibrary("marker");

        window.AdvancedMarkerElement = AdvancedMarkerElement;

        map = new Map(document.getElementById("map"), {
            zoom: 15,
            center: position,
            mapId: "VENUE_MAP",
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
        });

        // Initialize geocoder
        geocoder = new google.maps.Geocoder();

        // Initialize directions service and renderer
        const {
            DirectionsService,
            DirectionsRenderer
        } = await google.maps.importLibrary("routes");

        directionsService = new DirectionsService();
        directionsRenderer = new DirectionsRenderer({
            map: map,
            suppressMarkers: true,
            polylineOptions: {
                strokeColor: '#4F46E5',
                strokeWeight: 5,
                strokeOpacity: 0.8
            }
        });

        // Create custom venue marker
        const venueMarkerElement = document.createElement('div');
        venueMarkerElement.className =
            'bg-indigo-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
        venueMarkerElement.innerHTML = `
            <i class="fas fa-location-dot"></i>
            <span>Venue</span>
        `;

        venueMarker = new AdvancedMarkerElement({
            map: map,
            position: position,
            content: venueMarkerElement,
            title: "<?php echo addslashes($venue['venue_name']); ?>",
        });

        // Info window
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div class="p-3">
                    <h3 class="font-bold text-lg mb-1"><?php echo addslashes($venue['venue_name']); ?></h3>
                    <p class="text-sm text-gray-600"><?php echo addslashes($venue['location']); ?></p>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="fas fa-users"></i> Capacity: <?php echo $venue['capacity']; ?> guests
                    </p>
                </div>
            `,
        });

        venueMarker.addListener("click", () => {
            infoWindow.open(map, venueMarker);
        });

        // Add click listener for pin mode
        map.addListener('click', (event) => {
            if (isPinMode) {
                setStartLocation(event.latLng);
            }
        });
    }

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

    // Set starting location
    function setStartLocation(latLng) {
        startLocation = latLng;
        isPinMode = false;
        togglePinMode();

        // Remove old start marker if exists
        if (startMarker) {
            startMarker.map = null;
        }

        // Create custom start marker
        const startMarkerElement = document.createElement('div');
        startMarkerElement.className =
            'bg-green-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
        startMarkerElement.innerHTML = `
            <i class="fas fa-map-pin"></i>
            <span>Start</span>
        `;

        startMarker = new window.AdvancedMarkerElement({
            map: map,
            position: latLng,
            content: startMarkerElement,
            title: "Your Location"
        });

        // Geocode to get address
        geocoder.geocode({
            location: latLng
        }, (results, status) => {
            if (status === 'OK' && results[0]) {
                document.getElementById('startingPointDisplay').value = results[0].formatted_address;
                document.getElementById('startingLocationText').textContent = results[0].formatted_address;
                document.getElementById('startingLocationInfo').classList.remove('hidden');
            } else {
                const coords = `${latLng.lat().toFixed(6)}, ${latLng.lng().toFixed(6)}`;
                document.getElementById('startingPointDisplay').value = coords;
                document.getElementById('startingLocationText').textContent = coords;
                document.getElementById('startingLocationInfo').classList.remove('hidden');
            }
        });

        // Enable calculate buttons
        document.getElementById('calculateDrivingBtn').disabled = false;
        document.getElementById('calculateTransitBtn').disabled = false;

        // Adjust map to show both markers
        const bounds = new google.maps.LatLngBounds();
        bounds.extend(latLng);
        bounds.extend(venueLocation);
        map.fitBounds(bounds);
    }

    // Use current location
    function useCurrentLocation() {
        if (navigator.geolocation) {
            document.getElementById('startingPointDisplay').value = 'Getting your location...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latLng = new google.maps.LatLng(
                        position.coords.latitude,
                        position.coords.longitude
                    );
                    setStartLocation(latLng);
                },
                (error) => {
                    let errorMessage = 'Unable to get your location. ';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Please enable location permissions.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Location request timed out.';
                            break;
                        default:
                            errorMessage += 'An unknown error occurred.';
                    }
                    showError(errorMessage);
                    document.getElementById('startingPointDisplay').value = '';
                }
            );
        } else {
            showError('Geolocation is not supported by your browser.');
        }
    }

    // Calculate travel time
    async function calculateTravelTime(travelMode) {
        if (!startLocation) {
            showError('Please set your starting location first.');
            return;
        }

        const resultsDiv = document.getElementById('travelResults');
        const errorDiv = document.getElementById('travelError');
        const detailsContent = document.getElementById('travelDetailsContent');

        resultsDiv.classList.add('hidden');
        errorDiv.classList.add('hidden');

        detailsContent.innerHTML =
            '<div class="flex items-center gap-2"><i class="fas fa-spinner fa-spin"></i> Calculating route...</div>';
        resultsDiv.classList.remove('hidden');

        const request = {
            origin: startLocation,
            destination: venueLocation,
            travelMode: google.maps.TravelMode[travelMode],
            unitSystem: google.maps.UnitSystem.METRIC
        };

        directionsService.route(request, function(result, status) {
            const modeIcon = travelMode === 'DRIVING' ? 'fa-car' : 'fa-bus';
            const modeText = travelMode === 'DRIVING' ? 'Driving' : 'Public Transit';
            const modeColor = travelMode === 'DRIVING' ? 'indigo' : 'green';

            if (status === 'OK') {
                directionsRenderer.setDirections(result);

                const route = result.routes[0].legs[0];
                const distance = route.distance.text;
                const duration = route.duration.text;

                let walkingNote = '';
                if (travelMode === 'TRANSIT') {
                    const endLat = route.end_location.lat();
                    const endLng = route.end_location.lng();
                    const distanceToVenue = calculateDistance(endLat, endLng, venueLocation.lat,
                        venueLocation.lng);

                    if (distanceToVenue > 0.05) {
                        const walkingDistance = (distanceToVenue * 1000).toFixed(0);
                        const walkingTime = Math.round(distanceToVenue * 12);
                        walkingNote = `
                            <div class="flex items-center gap-2 text-sm mt-2 p-2 bg-blue-50 rounded border border-blue-200">
                                <i class="fas fa-walking text-blue-600"></i>
                                <span class="text-gray-700">
                                    <strong>Final walk:</strong> ~${walkingDistance}m (~${walkingTime} min) from nearest transit stop to venue
                                </span>
                            </div>
                        `;
                    }
                }

                detailsContent.innerHTML = `
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <i class="fas ${modeIcon} text-${modeColor}-600"></i>
                            <span class="font-semibold text-gray-800">${modeText}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="p-2 bg-white rounded border border-gray-200">
                                <div class="text-gray-500 text-xs">Distance</div>
                                <div class="font-bold text-gray-900">${distance}</div>
                            </div>
                            <div class="p-2 bg-white rounded border border-gray-200">
                                <div class="text-gray-500 text-xs">Duration</div>
                                <div class="font-bold text-gray-900">${duration}</div>
                            </div>
                        </div>
                        ${walkingNote}
                    </div>
                `;
            } else {
                resultsDiv.classList.add('hidden');

                let errorMessage = 'Could not calculate route. ';
                if (status === 'ZERO_RESULTS') {
                    errorMessage += 'No route found between these locations.';
                } else if (status === 'NOT_FOUND') {
                    errorMessage += 'One of the locations could not be found.';
                } else {
                    errorMessage += 'Please try again later.';
                }

                showError(errorMessage);
            }
        });
    }

    // Calculate distance between two points (in km)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    // Show error message
    function showError(message) {
        const errorDiv = document.getElementById('travelError');
        const errorMessage = document.getElementById('travelErrorMessage');
        errorMessage.textContent = message;
        errorDiv.classList.remove('hidden');
    }

    initMap();
    </script>
    <?php endif; ?>

</body>

</html>