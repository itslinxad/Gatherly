<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$user_id = $_SESSION['user_id'];
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$venue_id) {
    header("Location: my-venues.php");
    exit();
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $result = $conn->query("UPDATE venues SET availability_status = IF(availability_status = 'available', 'unavailable', 'available') WHERE venue_id = $venue_id AND manager_id = $user_id");
    if ($result) {
        $_SESSION['venue_message'] = 'Venue status updated successfully!';
    }
    header("Location: venue-details.php?id=$venue_id");
    exit();
}

// Fetch venue details - ensure it belongs to this manager
$query = "SELECT v.*, 
          l.city, l.province, l.baranggay, l.latitude, l.longitude,
          CONCAT(l.city, ', ', l.province) as location,
          p.base_price, p.peak_price, p.offpeak_price, p.weekday_price, p.weekend_price
          FROM venues v
          LEFT JOIN locations l ON v.location_id = l.location_id
          LEFT JOIN prices p ON v.venue_id = p.venue_id
          WHERE v.venue_id = ? AND v.manager_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $venue_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$venue = $result->fetch_assoc();

if (!$venue) {
    $_SESSION['venue_message'] = 'Venue not found or access denied!';
    header("Location: my-venues.php");
    exit();
}

// Get booking statistics for this venue
$bookings_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN total_cost ELSE 0 END) as total_revenue
    FROM events 
    WHERE venue_id = ?";

$stmt_bookings = $conn->prepare($bookings_query);
$stmt_bookings->bind_param("i", $venue_id);
$stmt_bookings->execute();
$booking_stats = $stmt_bookings->get_result()->fetch_assoc();

// Handle NULL values
if (!$booking_stats || $booking_stats['total_bookings'] === null) {
    $booking_stats = [
        'total_bookings' => 0,
        'confirmed_bookings' => 0,
        'pending_bookings' => 0,
        'cancelled_bookings' => 0,
        'total_revenue' => 0
    ];
}

// Get recent bookings
$recent_bookings_query = "SELECT e.event_name, e.event_date, e.status, e.expected_guests, e.total_cost,
    CONCAT(u.first_name, ' ', u.last_name) as organizer_name,
    u.email as organizer_email
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.venue_id = ?
    ORDER BY e.event_date DESC
    LIMIT 10";

$stmt_recent = $conn->prepare($recent_bookings_query);
$stmt_recent->bind_param("i", $venue_id);
$stmt_recent->execute();
$recent_bookings = $stmt_recent->get_result();

$imageSrc = !empty($venue['image'])
    ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
    : '../../assets/images/venue-placeholder.jpg';
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

    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <!-- Header with Back Button -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center gap-4">
                <a href="my-venues.php" class="text-gray-600 hover:text-gray-800 transition-colors">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($venue['venue_name']); ?>
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="fas fa-map-marker-alt text-green-500 mr-1"></i>
                        <?php echo htmlspecialchars($venue['location']); ?>
                    </p>
                </div>
                <div class="flex gap-2">
                    <span
                        class="px-4 py-2 text-sm font-semibold rounded-full <?php echo ($venue['availability_status'] ?? 'available') === 'available' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-gray-100 text-gray-700 border border-gray-300'; ?>">
                        <?php echo strtoupper($venue['availability_status'] ?? 'AVAILABLE'); ?>
                    </span>
                    <a href="?id=<?php echo $venue_id; ?>&toggle_status=1"
                        onclick="return confirm('Toggle venue availability?')"
                        class="px-4 py-2 <?php echo ($venue['availability_status'] ?? 'available') === 'available' ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> rounded-lg transition-colors text-sm font-medium">
                        <i
                            class="fas fa-<?php echo ($venue['availability_status'] ?? 'available') === 'available' ? 'pause' : 'play'; ?> mr-1"></i>
                        <?php echo ($venue['availability_status'] ?? 'available') === 'available' ? 'Mark Unavailable' : 'Mark Available'; ?>
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
                            <i class="fas fa-info-circle text-green-500 mr-2"></i>Basic Information
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

                    <!-- Pricing Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                            <i class="fas fa-tag text-blue-500 mr-2"></i>Pricing
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                                <p class="text-sm text-gray-600 mb-1">Base Price</p>
                                <p class="text-2xl font-bold text-blue-700">
                                    ₱<?php echo number_format($venue['base_price'] ?? 0, 2); ?></p>
                            </div>
                            <div
                                class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                                <p class="text-sm text-gray-600 mb-1">Peak Season</p>
                                <p class="text-2xl font-bold text-green-700">
                                    ₱<?php echo number_format($venue['peak_price'] ?? 0, 2); ?></p>
                            </div>
                            <div
                                class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                                <p class="text-sm text-gray-600 mb-1">Off-Peak Season</p>
                                <p class="text-2xl font-bold text-purple-700">
                                    ₱<?php echo number_format($venue['offpeak_price'] ?? 0, 2); ?></p>
                            </div>
                            <div
                                class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
                                <p class="text-sm text-gray-600 mb-1">Weekend Price</p>
                                <p class="text-2xl font-bold text-yellow-700">
                                    ₱<?php echo number_format($venue['weekend_price'] ?? 0, 2); ?></p>
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
                        <div class="flex flex-wrap gap-2">
                            <?php
                                $themes = explode(',', $venue['suitable_themes']);
                                foreach ($themes as $theme):
                                ?>
                            <span
                                class="px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-sm border border-purple-200">
                                <?php echo htmlspecialchars(trim($theme)); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
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
                                            Revenue</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                            Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($booking['event_name']); ?></p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-gray-700">
                                                <?php echo htmlspecialchars($booking['organizer_name']); ?></p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($booking['organizer_email']); ?></p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <i class="fas fa-users text-gray-400 mr-1"></i>
                                            <?php echo number_format($booking['expected_guests']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-green-600 font-semibold">
                                            ₱<?php echo number_format($booking['total_cost'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php
                                                    $statusColors = [
                                                        'confirmed' => 'bg-green-100 text-green-700 border-green-300',
                                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                                                        'canceled' => 'bg-red-100 text-red-700 border-red-300'
                                                    ];
                                                    $statusColor = $statusColors[$booking['status']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                                    ?>
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full border <?php echo $statusColor; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
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

                            <div
                                class="flex items-center justify-between p-3 bg-gradient-to-br from-green-50 to-emerald-100 rounded-lg border border-green-200">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="bg-gradient-to-br from-green-500 to-emerald-600 text-white p-3 rounded-lg">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600">Total Revenue</p>
                                        <p class="text-xl font-bold text-green-700">
                                            ₱<?php echo number_format($booking_stats['total_revenue'], 2); ?></p>
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
                            <a href="edit-venue.php?id=<?php echo $venue_id; ?>"
                                class="block w-full px-4 py-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors text-center font-medium">
                                <i class="fas fa-edit mr-2"></i>Edit Venue
                            </a>
                            <a href="?id=<?php echo $venue_id; ?>&toggle_status=1"
                                onclick="return confirm('Toggle venue availability?')"
                                class="block w-full px-4 py-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors text-center font-medium">
                                <i class="fas fa-toggle-on mr-2"></i>Toggle Availability
                            </a>
                            <a href="my-venues.php"
                                class="block w-full px-4 py-3 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors text-center font-medium">
                                <i class="fas fa-arrow-left mr-2"></i>Back to My Venues
                            </a>
                            <a href="my-venues.php?delete=<?php echo $venue_id; ?>"
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
        key: "YOUR_GOOGLE_MAPS_API_KEY_HERE",
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
            mapId: 'GATHERLY_MANAGER_MAP',
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            zoomControl: true
        });

        // Create custom venue marker (green for manager's venue)
        const venueMarkerElement = document.createElement('div');
        venueMarkerElement.className =
            'bg-green-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
        venueMarkerElement.innerHTML = `
                <i class="fas fa-location-dot"></i>
                <span>My Venue</span>
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
                            <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold; color: #059669;">
                                ${venueName}
                            </h3>
                            <p style="margin: 0 0 8px 0; font-size: 14px; color: #6B7280;">
                                <i class="fas fa-map-marker-alt" style="color: #059669; margin-right: 5px;"></i>
                                ${address}
                            </p>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}" 
                               target="_blank"
                               style="display: inline-block; padding: 6px 12px; background: #059669; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">
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