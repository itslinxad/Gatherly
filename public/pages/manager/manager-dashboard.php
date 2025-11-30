<?php
session_start();

// Check if user is logged in and is a manager (venue owner)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Manager';
$user_id = $_SESSION['user_id'];

// Fetch manager's statistics
$stats = [
    'total_venues' => 0,
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'total_revenue' => 0
];

// Get total venues (assuming managers can own/manage venues)
// Note: You might need to add a manager_id field to venues table
$result = $conn->query("SELECT COUNT(*) as count FROM venues");
$stats['total_venues'] = $result->fetch_assoc()['count'];

// Get total bookings/events
$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status IN ('confirmed', 'completed')");
$stats['total_bookings'] = $result->fetch_assoc()['count'];

// Get pending bookings
$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['count'];

// Get total revenue
$result = $conn->query("SELECT SUM(total_cost) as total FROM events WHERE status IN ('confirmed', 'completed')");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Get recent bookings
$recent_bookings_query = "SELECT e.event_id, e.event_name, e.event_date, e.status, e.total_cost, 
                          v.venue_name, u.first_name, u.last_name 
                          FROM events e 
                          LEFT JOIN venues v ON e.venue_id = v.venue_id 
                          LEFT JOIN users u ON e.manager_id = u.user_id 
                          ORDER BY e.created_at DESC 
                          LIMIT 3";
$recent_bookings = $conn->query($recent_bookings_query);

// Get venue performance data
$venue_performance_query = "SELECT v.venue_name, COUNT(e.event_id) as booking_count, 
                            SUM(e.total_cost) as revenue 
                            FROM venues v 
                            LEFT JOIN events e ON v.venue_id = e.venue_id 
                            WHERE e.status IN ('confirmed', 'completed')
                            GROUP BY v.venue_id 
                            ORDER BY booking_count DESC 
                            LIMIT 5";
$venue_performance = $conn->query($venue_performance_query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Manager Dashboard | Gatherly</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="<?php
                $nav_layout = $_SESSION['nav_layout'] ?? 'sidebar';
                echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-green-50 via-white to-teal-50';
                ?> font-['Montserrat']">
    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
        <!-- Top Bar for Sidebar Layout -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($first_name); ?>! 🏢
            </h1>
            <p class="text-sm text-gray-600">Manage your venues and optimize your business</p>
        </div>
        <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
            <!-- Header for Navbar Layout -->
            <div class="mb-8">
                <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Welcome back,
                    <?php echo htmlspecialchars($first_name); ?>! 🏢</h1>
                <p class="text-gray-600">Manage your venues and optimize your business</p>
            </div>
            <?php endif; ?>

            <!-- Dynamic Pricing Tool Highlight Banner -->
            <div
                class="p-6 mb-8 border-2 border-green-300 shadow-lg bg-linear-to-r from-green-100 to-teal-100 rounded-xl">
                <div class="flex flex-col items-start justify-between lg:flex-row lg:items-center">
                    <div class="mb-4 lg:mb-0">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex items-center justify-center w-12 h-12 bg-green-600 rounded-full">
                                <i class="text-2xl text-white fas fa-chart-line"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-green-900">Dynamic Pricing & Analytics</h2>
                        </div>
                        <p class="text-gray-700">Optimize your venue pricing with AI-powered demand forecasting and
                            competitive analysis!</p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <span class="px-3 py-1 text-xs font-semibold text-green-700 bg-green-200 rounded-full">
                                <i class="mr-1 fas fa-brain"></i> Smart Pricing
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold text-teal-700 bg-teal-200 rounded-full">
                                <i class="mr-1 fas fa-calendar-alt"></i> Demand Forecasting
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-200 rounded-full">
                                <i class="mr-1 fas fa-chart-bar"></i> Revenue Optimization
                            </span>
                        </div>
                    </div>
                    <a href="pricing.php"
                        class="inline-block px-6 py-3 font-semibold text-white transition-all transform bg-green-600 shadow-lg rounded-xl hover:bg-green-700 hover:scale-105 hover:shadow-xl">
                        <i class="mr-2 fas fa-robot"></i>
                        AI Pricing Tool
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6 md:mb-8">
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">My Venues</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                <?php echo number_format($stats['total_venues']); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-green-100 rounded-lg">
                            <i class="text-2xl text-green-600 fas fa-building"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">Total Bookings</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                <?php echo number_format($stats['total_bookings']); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-blue-100 rounded-lg">
                            <i class="text-2xl text-blue-600 fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">Pending</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                <?php echo number_format($stats['pending_bookings']); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-yellow-100 rounded-lg">
                            <i class="text-2xl text-yellow-600 fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">Total Revenue</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                ₱<?php echo number_format($stats['total_revenue'], 2); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-purple-100 rounded-lg">
                            <i class="text-2xl text-purple-600 fas fa-peso-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Venue Performance Chart -->
            <div class="p-6 mb-8 bg-white shadow-md rounded-xl">
                <h2 class="mb-4 text-xl font-bold text-gray-800">
                    <i class="mr-2 text-green-600 fas fa-chart-bar"></i>
                    Venue Performance
                </h2>
                <div class="relative" style="height: 300px;">
                    <canvas id="venuePerformanceChart"></canvas>
                </div>
            </div>

            <!-- Quick Actions & Recent Bookings -->
            <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-2">
                <!-- Quick Actions -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-green-600 fas fa-bolt"></i>
                        Quick Actions
                    </h2>
                    <div class="space-y-3">
                        <a href="add-venue.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-green-200 hover:bg-green-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-green-600 fas fa-plus-circle"></i>
                                <span class="font-semibold text-gray-700">Add New Venue</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                        <a href="my-venues.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-blue-200 hover:bg-blue-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-blue-600 fas fa-building"></i>
                                <span class="font-semibold text-gray-700">Manage Venues</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                        <a href="bookings.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-yellow-200 hover:bg-yellow-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-yellow-600 fas fa-calendar-alt"></i>
                                <span class="font-semibold text-gray-700">View Bookings</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                        <a href="pricing.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-purple-200 hover:bg-purple-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-purple-600 fas fa-robot"></i>
                                <span class="font-semibold text-gray-700">AI Pricing Optimization</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-green-600 fas fa-history"></i>
                        Recent Bookings
                    </h2>
                    <div class="space-y-3">
                        <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                        <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                        <div
                            class="p-4 transition-all border border-gray-200 rounded-lg hover:border-green-200 hover:bg-green-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="mb-1 font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($booking['event_name']); ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="mr-1 fas fa-user"></i>
                                        <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="mr-1 fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($booking['venue_name'] ?? 'No venue assigned'); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="mr-1 fas fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($booking['event_date'])); ?>
                                    </p>
                                    <p class="text-sm font-semibold text-green-600">
                                        <i class="mr-1 fas fa-peso-sign"></i>
                                        ₱<?php echo number_format($booking['total_cost'], 2); ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        echo $booking['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : ($booking['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : ($booking['status'] == 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'));
                                        ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500">
                            <i class="mb-3 text-4xl fas fa-calendar-times"></i>
                            <p class="mb-2 font-semibold">No bookings yet</p>
                            <p class="text-sm">Bookings will appear here once clients start reserving your venues</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>



        <?php if ($nav_layout === 'sidebar'): ?>
    </div>
    <?php endif; ?>
    </div>

    <script src="../../assets/js/manager.js"></script>
</body>

</html>