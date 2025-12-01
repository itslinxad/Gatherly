<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';
$user_id = $_SESSION['user_id'];

// Fetch system-wide statistics
$stats = [
    'total_users' => 0,
    'total_organizers' => 0,
    'total_managers' => 0,
    'total_venues' => 0,
    'active_venues' => 0,
    'total_events' => 0,
    'pending_events' => 0,
    'confirmed_events' => 0,
    'completed_events' => 0,
    'total_revenue' => 0,
    'monthly_revenue' => 0,
    'active_chats' => 0,
    'pending_approvals' => 0
];

try {
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];

    // Users by role
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'organizer'");
    $stats['total_organizers'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
    $stats['total_managers'] = $result->fetch_assoc()['count'];

    // Venues
    $result = $conn->query("SELECT COUNT(*) as count FROM venues");
    $stats['total_venues'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM venues WHERE status = 'active'");
    $stats['active_venues'] = $result->fetch_assoc()['count'];

    // Events
    $result = $conn->query("SELECT COUNT(*) as count FROM events");
    $stats['total_events'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'pending'");
    $stats['pending_events'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'confirmed'");
    $stats['confirmed_events'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'completed'");
    $stats['completed_events'] = $result->fetch_assoc()['count'];

    // Revenue
    $result = $conn->query("SELECT SUM(total_cost) as total FROM events WHERE status IN ('confirmed', 'completed')");
    $stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

    $result = $conn->query("SELECT SUM(total_cost) as total FROM events WHERE status IN ('confirmed', 'completed') AND MONTH(event_date) = MONTH(CURRENT_DATE()) AND YEAR(event_date) = YEAR(CURRENT_DATE())");
    $stats['monthly_revenue'] = $result->fetch_assoc()['total'] ?? 0;

    // Active chats
    $result = $conn->query("SELECT COUNT(DISTINCT CONCAT(LEAST(sender_id, receiver_id), '-', GREATEST(sender_id, receiver_id))) as count FROM chat");
    $stats['active_chats'] = $result->fetch_assoc()['count'];

    // Pending approvals (events + inactive venues)
    $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'pending'");
    $pending_events = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM venues WHERE status = 'inactive'");
    $pending_venues = $result->fetch_assoc()['count'];

    $stats['pending_approvals'] = $pending_events + $pending_venues;
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// User growth data (last 6 months)
$user_growth_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$user_growth = $conn->query($user_growth_query);

// Revenue trend (last 6 months)
$revenue_trend_query = "SELECT 
    DATE_FORMAT(event_date, '%Y-%m') as month,
    SUM(total_cost) as revenue,
    COUNT(*) as event_count
    FROM events 
    WHERE status IN ('confirmed', 'completed')
    AND event_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(event_date, '%Y-%m')
    ORDER BY month ASC";
$revenue_trend = $conn->query($revenue_trend_query);

// Event status distribution
$event_status_query = "SELECT status, COUNT(*) as count FROM events GROUP BY status";
$event_status = $conn->query($event_status_query);

// Recent activities
$recent_events_query = "SELECT e.event_id, e.event_name, e.event_date, e.status, 
    u.first_name, u.last_name, v.venue_name
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    ORDER BY e.created_at DESC
    LIMIT 5";
$recent_events = $conn->query($recent_events_query);

// Recent user registrations
$recent_users_query = "SELECT user_id, first_name, last_name, email, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5";
$recent_users = $conn->query($recent_users_query);

// Top venues by bookings
$top_venues_query = "SELECT v.venue_name, COUNT(e.event_id) as booking_count,
    SUM(e.total_cost) as revenue
    FROM venues v
    LEFT JOIN events e ON v.venue_id = e.venue_id AND e.status IN ('confirmed', 'completed')
    GROUP BY v.venue_id, v.venue_name
    ORDER BY booking_count DESC
    LIMIT 5";
$top_venues = $conn->query($top_venues_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="bg-gray-100 font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-sm text-gray-600">System Overview & Analytics</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Welcome back,</p>
                    <p class="text-lg font-semibold text-indigo-600"><?php echo htmlspecialchars($first_name); ?></p>
                </div>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 pb-8">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium mb-1">Total Users</p>
                            <p class="text-3xl font-bold"><?php echo number_format($stats['total_users']); ?></p>
                            <p class="text-blue-100 text-xs mt-2">
                                <i class="fas fa-users mr-1"></i>
                                <?php echo $stats['total_organizers']; ?> Organizers,
                                <?php echo $stats['total_managers']; ?> Managers
                            </p>
                        </div>
                        <div class="bg-white/20 rounded-full p-4">
                            <i class="fas fa-users text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Venues -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium mb-1">Total Venues</p>
                            <p class="text-3xl font-bold"><?php echo number_format($stats['total_venues']); ?></p>
                            <p class="text-green-100 text-xs mt-2">
                                <i class="fas fa-check-circle mr-1"></i>
                                <?php echo $stats['active_venues']; ?> Active
                            </p>
                        </div>
                        <div class="bg-white/20 rounded-full p-4">
                            <i class="fas fa-building text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Events -->
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium mb-1">Total Events</p>
                            <p class="text-3xl font-bold"><?php echo number_format($stats['total_events']); ?></p>
                            <p class="text-purple-100 text-xs mt-2">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo $stats['pending_events']; ?> Pending
                            </p>
                        </div>
                        <div class="bg-white/20 rounded-full p-4">
                            <i class="fas fa-calendar text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium mb-1">Total Revenue</p>
                            <p class="text-3xl font-bold">₱<?php echo number_format($stats['total_revenue'], 0); ?></p>
                            <p class="text-orange-100 text-xs mt-2">
                                <i class="fas fa-chart-line mr-1"></i>
                                ₱<?php echo number_format($stats['monthly_revenue'], 0); ?> this month
                            </p>
                        </div>
                        <div class="bg-white/20 rounded-full p-4">
                            <i class="fas fa-peso-sign text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="manage-users.php"
                        class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors group">
                        <i
                            class="fas fa-users text-3xl text-blue-600 mb-2 group-hover:scale-110 transition-transform"></i>
                        <span class="text-sm font-semibold text-gray-700">Manage Users</span>
                    </a>
                    <a href="manage-venues.php"
                        class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors group">
                        <i
                            class="fas fa-building text-3xl text-green-600 mb-2 group-hover:scale-110 transition-transform"></i>
                        <span class="text-sm font-semibold text-gray-700">Manage Venues</span>
                    </a>
                    <a href="manage-events.php"
                        class="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors group">
                        <i
                            class="fas fa-calendar text-3xl text-purple-600 mb-2 group-hover:scale-110 transition-transform"></i>
                        <span class="text-sm font-semibold text-gray-700">Manage Events</span>
                    </a>
                    <a href="reports.php"
                        class="flex flex-col items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors group">
                        <i
                            class="fas fa-chart-bar text-3xl text-orange-600 mb-2 group-hover:scale-110 transition-transform"></i>
                        <span class="text-sm font-semibold text-gray-700">View Reports</span>
                    </a>
                </div>

                <?php if ($stats['pending_approvals'] > 0): ?>
                    <div class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                            <div>
                                <p class="text-sm font-semibold text-yellow-800">Pending Approvals</p>
                                <p class="text-sm text-yellow-700">
                                    You have <?php echo $stats['pending_approvals']; ?> items requiring attention
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- User Growth Chart -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">User Growth</h3>
                        <select id="userGrowthFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="1">Last Month</option>
                            <option value="3">Last 3 Months</option>
                            <option value="6" selected>Last 6 Months</option>
                            <option value="12">Last Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <div style="height: 300px; position: relative;">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>

                <!-- Revenue Trend Chart -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Revenue Trend</h3>
                        <select id="revenueTrendFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="1">Last Month</option>
                            <option value="3">Last 3 Months</option>
                            <option value="6" selected>Last 6 Months</option>
                            <option value="12">Last Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <div style="height: 300px; position: relative;">
                        <canvas id="revenueTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Event Status & Top Venues -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Event Status Distribution -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Event Status Distribution</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="eventStatusChart"></canvas>
                    </div>
                </div>

                <!-- Top Venues -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Top Performing Venues</h3>
                    <div class="space-y-4">
                        <?php if ($top_venues && $top_venues->num_rows > 0):
                            $rank = 1;
                            while ($venue = $top_venues->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 bg-indigo-600 text-white rounded-full font-bold text-sm">
                                            <?php echo $rank++; ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($venue['venue_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $venue['booking_count']; ?> bookings</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-green-600">
                                            ₱<?php echo number_format($venue['revenue'] ?? 0, 0); ?></p>
                                        <p class="text-xs text-gray-500">Revenue</p>
                                    </div>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <p class="text-gray-500 text-center py-4">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Tables -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Events -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800">Recent Events</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Event
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                                        Organizer</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($recent_events && $recent_events->num_rows > 0):
                                    while ($event = $recent_events->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <p class="text-sm font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($event['event_name']); ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            <?php
                                            switch ($event['status']) {
                                                case 'confirmed':
                                                    echo 'bg-green-100 text-green-700';
                                                    break;
                                                case 'pending':
                                                    echo 'bg-yellow-100 text-yellow-700';
                                                    break;
                                                case 'completed':
                                                    echo 'bg-blue-100 text-blue-700';
                                                    break;
                                                case 'canceled':
                                                    echo 'bg-red-100 text-red-700';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-700';
                                            }
                                            ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                else: ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">No recent events</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800">Recent User Registrations</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">User
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Role
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Joined
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($recent_users && $recent_users->num_rows > 0):
                                    while ($user = $recent_users->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <p class="text-sm font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            <?php
                                            switch ($user['role']) {
                                                case 'organizer':
                                                    echo 'bg-blue-100 text-blue-700';
                                                    break;
                                                case 'manager':
                                                    echo 'bg-purple-100 text-purple-700';
                                                    break;
                                                case 'administrator':
                                                    echo 'bg-red-100 text-red-700';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-700';
                                            }
                                            ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                else: ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">No recent users</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // User Growth Chart
        let userGrowthChart;
        let revenueTrendChart;

        function initUserGrowthChart(months = 6) {
            fetch(`get-chart-data.php?type=user_growth&months=${months}`)
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('userGrowthChart').getContext('2d');

                    if (userGrowthChart) {
                        userGrowthChart.destroy();
                    }

                    userGrowthChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'New Users',
                                data: data.values,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'New Users: ' + context.parsed.y;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error loading user growth data:', error));
        }

        function initRevenueTrendChart(months = 6) {
            fetch(`get-chart-data.php?type=revenue_trend&months=${months}`)
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('revenueTrendChart').getContext('2d');

                    if (revenueTrendChart) {
                        revenueTrendChart.destroy();
                    }

                    revenueTrendChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Revenue (₱)',
                                data: data.values,
                                backgroundColor: 'rgba(249, 115, 22, 0.8)',
                                borderColor: 'rgb(249, 115, 22)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Revenue: ₱' + context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '₱' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error loading revenue trend data:', error));
        }

        // Initialize charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            initUserGrowthChart(6);
            initRevenueTrendChart(6);

            // User Growth filter
            document.getElementById('userGrowthFilter').addEventListener('change', function() {
                const months = this.value;
                initUserGrowthChart(months);
            });

            // Revenue Trend filter
            document.getElementById('revenueTrendFilter').addEventListener('change', function() {
                const months = this.value;
                initRevenueTrendChart(months);
            });
        });

        // Event Status Chart
        <?php
        $status_labels = [];
        $status_counts = [];
        $status_colors = [];
        $color_map = [
            'pending' => '#EAB308',
            'confirmed' => '#22C55E',
            'completed' => '#3B82F6',
            'canceled' => '#EF4444'
        ];
        if ($event_status && $event_status->num_rows > 0) {
            while ($row = $event_status->fetch_assoc()) {
                $status_labels[] = ucfirst($row['status']);
                $status_counts[] = $row['count'];
                $status_colors[] = $color_map[$row['status']] ?? '#6B7280';
            }
        }
        ?>
        const eventStatusCtx = document.getElementById('eventStatusChart').getContext('2d');
        new Chart(eventStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>

</html>