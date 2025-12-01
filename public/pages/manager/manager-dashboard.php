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

// Get total venues owned by this manager
$result = $conn->query("SELECT COUNT(*) as count FROM venues WHERE manager_id = $user_id AND status = 'active'");
$stats['total_venues'] = $result->fetch_assoc()['count'];

// Get total bookings/events for this manager's venues
$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE manager_id = $user_id AND status IN ('confirmed', 'completed')");
$stats['total_bookings'] = $result->fetch_assoc()['count'];

// Get pending bookings for this manager's venues
$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE manager_id = $user_id AND status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['count'];

// Get total revenue from this manager's venues
$result = $conn->query("SELECT SUM(total_cost) as total FROM events WHERE manager_id = $user_id AND status IN ('confirmed', 'completed')");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Get recent bookings for this manager's venues
$recent_bookings_query = "SELECT e.event_id, e.event_name, e.event_date, e.status, e.total_cost, 
                          v.venue_name, u.first_name, u.last_name 
                          FROM events e 
                          LEFT JOIN venues v ON e.venue_id = v.venue_id 
                          LEFT JOIN users u ON e.organizer_id = u.user_id 
                          WHERE e.manager_id = $user_id
                          ORDER BY e.created_at DESC 
                          LIMIT 3";
$recent_bookings = $conn->query($recent_bookings_query);

// Get venue performance data for this manager's venues
$venue_performance_query = "SELECT v.venue_name, COUNT(e.event_id) as booking_count, 
                            COALESCE(SUM(e.total_cost), 0) as revenue 
                            FROM venues v 
                            LEFT JOIN events e ON v.venue_id = e.venue_id AND e.status IN ('confirmed', 'completed')
                            WHERE v.manager_id = $user_id AND v.status = 'active'
                            GROUP BY v.venue_id, v.venue_name 
                            ORDER BY booking_count DESC 
                            LIMIT 5";
$venue_performance = $conn->query($venue_performance_query);

// Get monthly revenue data for the last 6 months
$monthly_revenue_query = "SELECT DATE_FORMAT(e.event_date, '%Y-%m') as month, 
                          SUM(e.total_cost) as revenue,
                          COUNT(e.event_id) as bookings
                          FROM events e 
                          WHERE e.manager_id = $user_id 
                          AND e.status IN ('confirmed', 'completed')
                          AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
                          ORDER BY month ASC";
$monthly_revenue = $conn->query($monthly_revenue_query);

// Get booking status breakdown
$status_breakdown_query = "SELECT status, COUNT(*) as count 
                           FROM events 
                           WHERE manager_id = $user_id
                           GROUP BY status";
$status_breakdown = $conn->query($status_breakdown_query);

// Get event type distribution
$event_type_query = "SELECT event_type, COUNT(*) as count 
                     FROM events 
                     WHERE manager_id = $user_id 
                     AND status IN ('confirmed', 'completed')
                     GROUP BY event_type 
                     ORDER BY count DESC 
                     LIMIT 5";
$event_types = $conn->query($event_type_query);

// Get payment status breakdown
$payment_status_query = "SELECT payment_status, COUNT(*) as count, SUM(total_cost) as total 
                         FROM events 
                         WHERE manager_id = $user_id 
                         AND status IN ('confirmed', 'completed')
                         GROUP BY payment_status";
$payment_status = $conn->query($payment_status_query);

// Prepare data for JavaScript
$venue_names = [];
$venue_bookings = [];
$venue_revenues = [];
while ($row = $venue_performance->fetch_assoc()) {
    $venue_names[] = $row['venue_name'];
    $venue_bookings[] = $row['booking_count'];
    $venue_revenues[] = $row['revenue'];
}

$months = [];
$monthly_revenues = [];
$monthly_bookings = [];
while ($row = $monthly_revenue->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_revenues[] = $row['revenue'];
    $monthly_bookings[] = $row['bookings'];
}

$status_labels = [];
$status_counts = [];
while ($row = $status_breakdown->fetch_assoc()) {
    $status_labels[] = ucfirst($row['status']);
    $status_counts[] = $row['count'];
}

$event_type_labels = [];
$event_type_counts = [];
while ($row = $event_types->fetch_assoc()) {
    $event_type_labels[] = $row['event_type'] ?? 'Other';
    $event_type_counts[] = $row['count'];
}

$payment_labels = [];
$payment_counts = [];
$payment_totals = [];
while ($row = $payment_status->fetch_assoc()) {
    $payment_labels[] = ucfirst($row['payment_status']);
    $payment_counts[] = $row['count'];
    $payment_totals[] = $row['total'];
}

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
    <script>
        // Chart data from PHP
        const chartData = {
            venueNames: <?php echo json_encode($venue_names); ?>,
            venueBookings: <?php echo json_encode($venue_bookings); ?>,
            venueRevenues: <?php echo json_encode($venue_revenues); ?>,
            months: <?php echo json_encode($months); ?>,
            monthlyRevenues: <?php echo json_encode($monthly_revenues); ?>,
            monthlyBookings: <?php echo json_encode($monthly_bookings); ?>,
            statusLabels: <?php echo json_encode($status_labels); ?>,
            statusCounts: <?php echo json_encode($status_counts); ?>,
            eventTypeLabels: <?php echo json_encode($event_type_labels); ?>,
            eventTypeCounts: <?php echo json_encode($event_type_counts); ?>,
            paymentLabels: <?php echo json_encode($payment_labels); ?>,
            paymentCounts: <?php echo json_encode($payment_counts); ?>,
            paymentTotals: <?php echo json_encode($payment_totals); ?>
        };
    </script>
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

            <!-- Main Content Grid: Stats Cards (Left) + Charts (Right) -->
            <div class="space-y-6 mb-8">
                <!-- First Row: 2 Stats Cards + Monthly Revenue Chart -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    <!-- First 2 Stats Cards -->
                    <div class="space-y-4 lg:col-span-1">
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 text-center">
                            <div class="flex justify-center mb-3">
                                <div class="p-4 bg-green-100 rounded-full">
                                    <i class="text-3xl text-green-600 fas fa-building"></i>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-600 mb-2">My Venues</p>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo number_format($stats['total_venues']); ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 text-center">
                            <div class="flex justify-center mb-3">
                                <div class="p-4 bg-blue-100 rounded-full">
                                    <i class="text-3xl text-blue-600 fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-600 mb-2">Total Bookings</p>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo number_format($stats['total_bookings']); ?></p>
                        </div>
                    </div>

                    <!-- Monthly Revenue Trend Chart -->
                    <div class="p-6 bg-white shadow-md rounded-xl lg:col-span-3">
                        <h2 class="mb-4 text-xl font-bold text-gray-800">
                            <i class="mr-2 text-blue-600 fas fa-chart-line"></i>
                            Monthly Revenue Trend (Last 6 Months)
                        </h2>
                        <div class="relative" style="height: 300px;">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Second Row: 2 Stats Cards + 2 Pie Charts -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    <!-- Last 2 Stats Cards -->
                    <div class="space-y-4 lg:col-span-1">
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 text-center">
                            <div class="flex justify-center mb-3">
                                <div class="p-4 bg-yellow-100 rounded-full">
                                    <i class="text-3xl text-yellow-600 fas fa-clock"></i>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-600 mb-2">Pending</p>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo number_format($stats['pending_bookings']); ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 text-center">
                            <div class="flex justify-center mb-3">
                                <div class="p-4 bg-purple-100 rounded-full">
                                    <i class="text-3xl text-purple-600 fas fa-peso-sign"></i>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-600 mb-2">Total Revenue</p>
                            <p class="text-3xl font-bold text-gray-900">
                                ₱<?php echo number_format($stats['total_revenue'], 2); ?></p>
                        </div>
                    </div>

                    <!-- Event Types & Booking Status Charts -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:col-span-3">
                        <!-- Event Type Distribution -->
                        <div class="p-6 bg-white shadow-md rounded-xl">
                            <h2 class="mb-4 text-xl font-bold text-gray-800">
                                <i class="mr-2 text-purple-600 fas fa-chart-pie"></i>
                                Event Types
                            </h2>
                            <div class="relative" style="height: 280px;">
                                <canvas id="eventTypeChart"></canvas>
                            </div>
                        </div>

                        <!-- Booking Status Breakdown -->
                        <div class="p-6 bg-white shadow-md rounded-xl">
                            <h2 class="mb-4 text-xl font-bold text-gray-800">
                                <i class="mr-2 text-teal-600 fas fa-chart-pie"></i>
                                Booking Status
                            </h2>
                            <div class="relative" style="height: 280px;">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
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
                    <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
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