<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Debug: Log session info
error_log("venue-details.php: Session started. User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    error_log("venue-details.php: Access denied - redirecting to signin");
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

// Debug: Check database connection
if (!$conn) {
    error_log("venue-details.php: Database connection failed!");
    die("Database connection error. Check error logs.");
}
error_log("venue-details.php: Database connected successfully");

$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
error_log("venue-details.php: Venue ID: $venue_id");

if (!$venue_id) {
    error_log("venue-details.php: No venue ID provided - redirecting");
    header("Location: manage-venues.php");
    exit();
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    error_log("venue-details.php: Toggling status for venue $venue_id");
    $result = $conn->query("UPDATE venues SET status = IF(status = 'active', 'inactive', 'active') WHERE venue_id = $venue_id");
    if (!$result) {
        error_log("venue-details.php: Status update failed: " . $conn->error);
    }
    $_SESSION['venue_message'] = 'Venue status updated successfully!';
    header("Location: venue-details.php?id=$venue_id");
    exit();
}

// Fetch venue details
error_log("venue-details.php: Fetching venue details");
$query = "SELECT v.*, 
          l.city, l.province, l.baranggay, l.latitude, l.longitude,
          CONCAT(l.city, ', ', l.province) as location,
          m.first_name as manager_fname, 
          m.last_name as manager_lname,
          m.email as manager_email,
          m.phone as manager_phone
          FROM venues v
          LEFT JOIN locations l ON v.location_id = l.location_id
          LEFT JOIN users m ON v.manager_id = m.user_id
          WHERE v.venue_id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("venue-details.php: Query prepare failed: " . $conn->error);
    die("Database query error. Check error logs.");
}

$stmt->bind_param("i", $venue_id);
$stmt->execute();
$result = $stmt->get_result();
$venue = $result->fetch_assoc();

if (!$venue) {
    error_log("venue-details.php: Venue $venue_id not found in database");
    $_SESSION['venue_message'] = 'Venue not found!';
    header("Location: manage-venues.php");
    exit();
}
error_log("venue-details.php: Venue found: " . $venue['venue_name']);

// Get booking statistics for this venue
error_log("venue-details.php: Fetching booking statistics");
$bookings_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM events 
    WHERE venue_id = ?";

$stmt_bookings = $conn->prepare($bookings_query);
if (!$stmt_bookings) {
    error_log("venue-details.php: Bookings query prepare failed: " . $conn->error);
    die("Database query error. Check error logs.");
}

$stmt_bookings->bind_param("i", $venue_id);
$stmt_bookings->execute();
$booking_stats = $stmt_bookings->get_result()->fetch_assoc();

// Handle NULL values
if (!$booking_stats || $booking_stats['total_bookings'] === null) {
    error_log("venue-details.php: No booking stats found, using defaults");
    $booking_stats = [
        'total_bookings' => 0,
        'confirmed_bookings' => 0,
        'pending_bookings' => 0,
        'cancelled_bookings' => 0
    ];
} else {
    error_log("venue-details.php: Booking stats - Total: " . $booking_stats['total_bookings']);
}

// Get recent bookings
error_log("venue-details.php: Fetching recent bookings");
$recent_bookings_query = "SELECT e.event_name, e.event_date, e.status, e.expected_guests,
    CONCAT(u.first_name, ' ', u.last_name) as organizer_name
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.venue_id = ?
    ORDER BY e.event_date DESC
    LIMIT 5";

$stmt_recent = $conn->prepare($recent_bookings_query);
if (!$stmt_recent) {
    error_log("venue-details.php: Recent bookings query prepare failed: " . $conn->error);
    die("Database query error. Check error logs.");
}

$stmt_recent->bind_param("i", $venue_id);
$stmt_recent->execute();
$recent_bookings = $stmt_recent->get_result();

error_log("venue-details.php: Preparing image data");
$imageSrc = !empty($venue['image'])
    ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
    : '../../assets/images/venue-placeholder.jpg';

error_log("venue-details.php: All data loaded successfully. Rendering HTML...");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($venue['venue_name']); ?> | Venue Details</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <style>
    #map {
        height: 400px;
        border-radius: 12px;
    }
    </style>
</head>

<body class="bg-gray-50 font-['Montserrat']">

    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <!-- Header with Back Button -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center gap-4">
                <a href="manage-venues.php" class="text-gray-600 hover:text-gray-800 transition-colors">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($venue['venue_name']); ?>
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                        <?php echo htmlspecialchars($venue['location']); ?>
                    </p>
                </div>
                <div class="flex gap-2">
                    <span
                        class="px-4 py-2 text-sm font-semibold rounded-full <?php echo $venue['status'] === 'active' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-gray-100 text-gray-700 border border-gray-300'; ?>">
                        <?php echo strtoupper($venue['status']); ?>
                    </span>
                    <a href="?id=<?php echo $venue_id; ?>&toggle_status=1"
                        onclick="return confirm('Toggle venue status?')"
                        class="px-4 py-2 <?php echo $venue['status'] === 'active' ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> rounded-lg transition-colors text-sm font-medium">
                        <i class="fas fa-<?php echo $venue['status'] === 'active' ? 'pause' : 'play'; ?> mr-1"></i>
                        <?php echo $venue['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                    </a>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (isset($_SESSION['venue_message'])): ?>
            <div
                class="mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['venue_message']; ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['venue_message']); ?>
            <?php endif; ?>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Left Column - Main Details -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Venue Image -->
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($venue['venue_name']); ?>"
                            class="w-full h-96 object-cover">
                    </div>

                    <!-- Basic Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>Basic Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Venue Type</p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($venue['venue_type'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Capacity</p>
                                <p class="font-semibold text-gray-800">
                                    <i class="fas fa-users text-green-500 mr-1"></i>
                                    <?php echo number_format($venue['capacity']); ?> guests
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Ambiance</p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($venue['ambiance'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Created On</p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo date('F d, Y', strtotime($venue['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-align-left text-purple-500 mr-2"></i>Description
                        </h2>
                        <p class="text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($venue['description'])); ?></p>
                    </div>

                    <!-- Themes -->
                    <?php if ($venue['suitable_themes']): ?>
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-star text-yellow-500 mr-2"></i>Suitable Themes
                        </h2>

                        <?php if ($venue['suitable_themes']): ?>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 mb-2">Suitable Themes</p>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                        $themes = explode(',', $venue['suitable_themes']);
                                        foreach ($themes as $theme):
                                        ?>
                                <span
                                    class="px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-sm border border-purple-200">
                                    <i class="fas fa-palette mr-1"></i><?php echo trim(htmlspecialchars($theme)); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Location & Map -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-map-marked-alt text-red-500 mr-2"></i>Location
                        </h2>

                        <div class="mb-4 space-y-2">
                            <?php if (!empty($venue['baranggay'])): ?>
                            <p class="text-gray-700">
                                <i class="fas fa-home text-gray-400 mr-2 w-5"></i>
                                <strong>Barangay:</strong> <?php echo htmlspecialchars($venue['baranggay']); ?>
                            </p>
                            <?php endif; ?>
                            <p class="text-gray-700">
                                <i class="fas fa-city text-gray-400 mr-2 w-5"></i>
                                <strong>City:</strong> <?php echo htmlspecialchars($venue['city']); ?>
                            </p>
                            <p class="text-gray-700">
                                <i class="fas fa-map text-gray-400 mr-2 w-5"></i>
                                <strong>Province:</strong> <?php echo htmlspecialchars($venue['province']); ?>
                            </p>
                            <?php if ($venue['latitude'] && $venue['longitude']): ?>
                            <p class="text-gray-700">
                                <i class="fas fa-map-pin text-gray-400 mr-2 w-5"></i>
                                <strong>Coordinates:</strong> <?php echo htmlspecialchars($venue['latitude']); ?>,
                                <?php echo htmlspecialchars($venue['longitude']); ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Map -->
                        <?php if ($venue['latitude'] && $venue['longitude']): ?>
                        <div id="map" class="mt-4"></div>
                        <?php else: ?>
                        <div class="bg-gray-100 rounded-lg p-8 text-center">
                            <i class="fas fa-map-marked-alt text-4xl text-gray-400 mb-2"></i>
                            <p class="text-gray-600">No map coordinates available</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Bookings -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-calendar-check text-green-500 mr-2"></i>Recent Bookings
                        </h2>

                        <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Event</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Organizer</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Guests</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars($booking['event_name']); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($booking['organizer_name']); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($booking['event_date'])); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?php echo number_format($booking['expected_guests']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php
                                                    $statusColors = [
                                                        'confirmed' => 'bg-green-100 text-green-700',
                                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                                        'canceled' => 'bg-red-100 text-red-700',
                                                        'completed' => 'bg-blue-100 text-blue-700'
                                                    ];
                                                    $colorClass = $statusColors[$booking['status']] ?? 'bg-gray-100 text-gray-700';
                                                    ?>
                                            <span
                                                class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $colorClass; ?>">
                                                <?php echo strtoupper($booking['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-calendar-times text-4xl mb-2"></i>
                            <p>No bookings yet</p>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Right Column - Sidebar -->
                <div class="space-y-6">

                    <!-- Manager Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-user-tie text-blue-500 mr-2"></i>Manager
                        </h2>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Name</p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($venue['manager_fname'] . ' ' . $venue['manager_lname']); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Email</p>
                                <a href="mailto:<?php echo htmlspecialchars($venue['manager_email']); ?>"
                                    class="text-blue-600 hover:text-blue-700 break-all">
                                    <i
                                        class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($venue['manager_email']); ?>
                                </a>
                            </div>
                            <?php if ($venue['manager_phone']): ?>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Phone</p>
                                <a href="tel:<?php echo htmlspecialchars($venue['manager_phone']); ?>"
                                    class="text-blue-600 hover:text-blue-700">
                                    <i
                                        class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($venue['manager_phone']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Booking Statistics -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-chart-bar text-green-500 mr-2"></i>Statistics
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="bg-blue-500 text-white p-3 rounded-lg">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Total Bookings</p>
                                        <p class="text-xl font-bold text-blue-600">
                                            <?php echo number_format($booking_stats['total_bookings']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="bg-green-500 text-white p-3 rounded-lg">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Confirmed</p>
                                        <p class="text-xl font-bold text-green-600">
                                            <?php echo number_format($booking_stats['confirmed_bookings']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="bg-yellow-500 text-white p-3 rounded-lg">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Pending</p>
                                        <p class="text-xl font-bold text-yellow-600">
                                            <?php echo number_format($booking_stats['pending_bookings']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="bg-red-500 text-white p-3 rounded-lg">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Cancelled</p>
                                        <p class="text-xl font-bold text-red-600">
                                            <?php echo number_format($booking_stats['cancelled_bookings']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-bolt text-yellow-500 mr-2"></i>Quick Actions
                        </h2>
                        <div class="space-y-3">
                            <a href="?id=<?php echo $venue_id; ?>&toggle_status=1"
                                onclick="return confirm('Toggle venue status?')"
                                class="block w-full px-4 py-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors text-center font-medium">
                                <i class="fas fa-toggle-on mr-2"></i>Toggle Status
                            </a>
                            <a href="manage-venues.php"
                                class="block w-full px-4 py-3 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors text-center font-medium">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Venues
                            </a>
                            <a href="manage-venues.php?delete=<?php echo $venue_id; ?>"
                                onclick="return confirm('Are you sure you want to delete this venue? This action cannot be undone.')"
                                class="block w-full px-4 py-3 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors text-center font-medium">
                                <i class="fas fa-trash mr-2"></i>Delete Venue
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
        key: "YOUR_API_KEY_HERE",
        v: "weekly"
    });
    </script>

    <?php if ($venue['latitude'] && $venue['longitude']): ?>
    <script>
    let map;
    let venueMarker;
    let venueLocation;

    async function initMap() {
        // Load required libraries
        const {
            Map
        } = await google.maps.importLibrary("maps");
        const {
            AdvancedMarkerElement
        } = await google.maps.importLibrary("marker");

        // Get venue data from PHP
        const venueName = <?php echo json_encode($venue['venue_name']); ?>;
        const address = <?php echo json_encode($venue['city'] . ', ' . $venue['province']); ?>;
        const latitude = <?php echo $venue['latitude']; ?>;
        const longitude = <?php echo $venue['longitude']; ?>;

        // Store venue location
        venueLocation = {
            lat: latitude,
            lng: longitude
        };

        // Initialize map centered on venue location
        map = new Map(document.getElementById('map'), {
            center: venueLocation,
            zoom: 15,
            mapId: 'GATHERLY_ADMIN_MAP',
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            zoomControl: true
        });

        // Create custom venue marker (red for venue)
        const venueMarkerElement = document.createElement('div');
        venueMarkerElement.className =
            'bg-red-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
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
                            <p style="margin: 0 0 8px 0; font-size: 14px; color: #6B7280;">
                                <i class="fas fa-map-marker-alt" style="color: #DC2626; margin-right: 5px;"></i>
                                ${address}
                            </p>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}" 
                               target="_blank"
                               style="display: inline-block; padding: 6px 12px; background: #4F46E5; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">
                                <i class="fas fa-directions" style="margin-right: 4px;"></i>Get Directions
                            </a>
                        </div>
                    `
            }).open(map, venueMarker);
        });
    }

    // Initialize map on load
    initMap();
    </script>
    <?php endif; ?>

</body>

</html>
<?php $conn->close(); ?>