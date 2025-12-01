<?php
// TEMPORARY: Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

session_start();

// Initialize debug info array
$debug_info = [];
$has_error = false;
$error_message = '';

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: /../../signin.php");
    exit();
}

$debug_info[] = "User ID: " . $_SESSION['user_id'];
$debug_info[] = "User Role: " . $_SESSION['role'];
$debug_info[] = "Current directory: " . __DIR__;

try {
    $debug_info[] = "Loading dbconnect.php...";
    require_once '../../../src/services/dbconnect.php';
    $debug_info[] = "dbconnect.php loaded successfully";

    if (!isset($conn)) {
        throw new Exception("Database connection variable \$conn is not set");
    }

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $debug_info[] = "Database connection verified";
} catch (Exception $e) {
    $has_error = true;
    $error_message = $e->getMessage();
    $debug_info[] = "ERROR: " . $e->getMessage();
    error_log("ERROR in organizer-dashboard.php: " . $e->getMessage());
}

$first_name = $_SESSION['first_name'] ?? 'Organizer';
$user_id = $_SESSION['user_id'];

// Fetch organizer's statistics
$stats = [
    'my_events' => 0,
    'pending_events' => 0,
    'confirmed_events' => 0,
    'completed_events' => 0,
    'cancelled_events' => 0,
    'total_spent' => 0
];

$recent_events = null;
$monthly_spending = [];
$events_by_status = [];

// Only proceed with queries if database connection is successful
if (!$has_error && isset($conn)) {
    try {
        $debug_info[] = "Fetching organizer statistics...";

        // Get organizer's events count
        $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id");
        if ($result === false) {
            throw new Exception("Failed to fetch events count: " . $conn->error);
        }
        $stats['my_events'] = $result->fetch_assoc()['count'];
        $debug_info[] = "My events count: " . $stats['my_events'];

        // Get pending events
        $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id AND status = 'pending'");
        if ($result === false) {
            throw new Exception("Failed to fetch pending events: " . $conn->error);
        }
        $stats['pending_events'] = $result->fetch_assoc()['count'];
        $debug_info[] = "Pending events count: " . $stats['pending_events'];

        // Get confirmed events
        $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id AND status = 'confirmed'");
        if ($result === false) {
            throw new Exception("Failed to fetch confirmed events: " . $conn->error);
        }
        $stats['confirmed_events'] = $result->fetch_assoc()['count'];
        $debug_info[] = "Confirmed events count: " . $stats['confirmed_events'];

        // Get completed events
        $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id AND status = 'completed'");
        if ($result === false) {
            throw new Exception("Failed to fetch completed events: " . $conn->error);
        }
        $stats['completed_events'] = $result->fetch_assoc()['count'];

        // Get cancelled events
        $result = $conn->query("SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id AND status = 'cancelled'");
        if ($result === false) {
            throw new Exception("Failed to fetch cancelled events: " . $conn->error);
        }
        $stats['cancelled_events'] = $result->fetch_assoc()['count'];

        // Get total spent
        $result = $conn->query("SELECT SUM(total_cost) as total FROM events WHERE organizer_id = $user_id AND status IN ('confirmed', 'completed')");
        if ($result === false) {
            throw new Exception("Failed to fetch total spent: " . $conn->error);
        }
        $stats['total_spent'] = $result->fetch_assoc()['total'] ?? 0;
        $debug_info[] = "Total spent: ₱" . number_format($stats['total_spent'], 2);

        // Get events by status for chart
        $result = $conn->query("
            SELECT status, COUNT(*) as count 
            FROM events 
            WHERE organizer_id = $user_id 
            GROUP BY status
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events_by_status[$row['status']] = $row['count'];
            }
        }

        // Get monthly spending for last 12 months (to support filtering)
        $result = $conn->query("
            SELECT 
                DATE_FORMAT(event_date, '%Y-%m') as month,
                SUM(total_cost) as total
            FROM events 
            WHERE organizer_id = $user_id 
            AND status IN ('confirmed', 'completed')
            AND event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(event_date, '%Y-%m')
            ORDER BY month ASC
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthly_spending[$row['month']] = $row['total'];
            }
        }

        // Get recent events
        $debug_info[] = "Fetching recent events...";
        $recent_events_query = "SELECT e.event_id, e.event_name, e.event_date, e.status, e.total_cost, v.venue_name 
                                FROM events e 
                                LEFT JOIN venues v ON e.venue_id = v.venue_id 
                                WHERE e.organizer_id = $user_id 
                                ORDER BY e.event_date DESC 
                                LIMIT 5";
        $recent_events = $conn->query($recent_events_query);
        if ($recent_events === false) {
            throw new Exception("Failed to fetch recent events: " . $conn->error);
        }
        $debug_info[] = "Recent events fetched: " . $recent_events->num_rows . " rows";

        $debug_info[] = "All queries completed successfully";
    } catch (Exception $e) {
        $has_error = true;
        $error_message = $e->getMessage();
        $debug_info[] = "ERROR: " . $e->getMessage();
        error_log("ERROR in organizer-dashboard.php queries: " . $e->getMessage());
    }

    if (isset($conn)) {
        $conn->close();
        $debug_info[] = "Database connection closed";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard | Gatherly</title>
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

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-cyan-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($first_name); ?>! 👋
                </h1>
                <p class="text-sm text-gray-600">Plan your events with intelligent venue recommendations</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Welcome back,
                        <?php echo htmlspecialchars($first_name); ?>! 👋</h1>
                    <p class="text-gray-600">Plan your events with intelligent venue recommendations</p>
                </div>
            <?php endif; ?>

            <!-- AI Chatbot Highlight Banner -->
            <div
                class="p-6 mb-8 border-2 border-indigo-300 shadow-lg bg-linear-to-r from-indigo-100 to-cyan-100 rounded-xl">
                <div class="flex flex-col items-start justify-between lg:flex-row lg:items-center">
                    <div class="mb-4 lg:mb-0">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-full">
                                <i class="text-2xl text-white fas fa-robot"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-indigo-900">AI Event Planner Assistant</h2>
                        </div>
                        <p class="text-gray-700">Get personalized venue and supplier recommendations powered by AI. Tell
                            us
                            about your event and we'll create the perfect plan!</p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <span class="px-3 py-1 text-xs font-semibold text-indigo-700 bg-indigo-200 rounded-full">
                                <i class="mr-1 fas fa-brain"></i> Smart Matching
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full text-cyan-700 bg-cyan-200">
                                <i class="mr-1 fas fa-chart-line"></i> Budget Optimization
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold text-indigo-700 bg-indigo-200 rounded-full">
                                <i class="mr-1 fas fa-users"></i> Supplier Recommendations
                            </span>
                        </div>
                    </div>
                    <a href="ai-planner.php"
                        class="px-6 py-3 font-semibold text-white transition-all transform bg-indigo-600 shadow-lg rounded-xl hover:bg-indigo-700 hover:scale-105 hover:shadow-xl">
                        <i class="mr-2 fas fa-comments"></i>
                        Chat with AI
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6 md:mb-8">
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">My Events</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                <?php echo number_format($stats['my_events']); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-indigo-100 rounded-lg">
                            <i class="text-indigo-600 text-lg md:text-xl fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">Pending Events</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                <?php echo number_format($stats['pending_events']); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-yellow-100 rounded-lg">
                            <i class="text-yellow-600 text-lg md:text-xl fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">Confirmed Events</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">
                                <?php echo number_format($stats['confirmed_events']); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-green-100 rounded-lg">
                            <i class="text-green-600 text-lg md:text-xl fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">Total Spent</p>
                            <p class="text-lg md:text-xl font-bold text-gray-900">₱
                                <?php echo number_format($stats['total_spent'], 2); ?></p>
                        </div>
                        <div class="p-2 md:p-3 bg-blue-100 rounded-lg">
                            <i class="text-blue-600 text-lg md:text-xl fas fa-peso-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-2">
                <!-- Events Status Chart -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-indigo-600 fas fa-chart-pie"></i>
                        Events by Status
                    </h2>
                    <div class="relative" style="height: 300px;">
                        <?php
                        $totalEvents = $stats['pending_events'] + $stats['confirmed_events'] + $stats['completed_events'] + $stats['cancelled_events'];
                        if ($totalEvents == 0):
                        ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i class="mb-3 text-5xl fas fa-chart-pie"></i>
                                <p class="font-semibold">No events data yet</p>
                                <p class="text-sm">Create your first event to see statistics</p>
                            </div>
                        <?php else: ?>
                            <canvas id="eventsStatusChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Spending Chart -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="mr-2 text-indigo-600 fas fa-chart-line"></i>
                            Spending Trend
                        </h2>
                        <select id="spendingPeriodFilter"
                            class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="3">Last 3 Months</option>
                            <option value="6" selected>Last 6 Months</option>
                            <option value="12">Last 12 Months</option>
                        </select>
                    </div>
                    <div class="relative" style="height: 300px;">
                        <canvas id="monthlySpendingChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Recent Events -->
            <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-2">
                <!-- Quick Actions -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-indigo-600 fas fa-bolt"></i>
                        Quick Actions
                    </h2>
                    <div class="space-y-3">
                        <a href="find-venues.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-indigo-200 hover:bg-indigo-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-indigo-600 fas fa-search"></i>
                                <span class="font-semibold text-gray-700">Search Venues</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                        <a href="create-event.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-green-200 hover:bg-green-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-green-600 fas fa-plus-circle"></i>
                                <span class="font-semibold text-gray-700">Create New Event</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                        <a href="my-events.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-blue-200 hover:bg-blue-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-blue-600 fas fa-list"></i>
                                <span class="font-semibold text-gray-700">View All Events</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                        <a href="bookings.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-orange-200 hover:bg-orange-50 group">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-orange-600 fas fa-ticket-alt"></i>
                                <span class="font-semibold text-gray-700">Manage Bookings</span>
                            </div>
                            <i
                                class="text-gray-400 transition-transform group-hover:translate-x-1 fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Recent Events -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-indigo-600 fas fa-history"></i>
                        Recent Events
                    </h2>
                    <div class="space-y-3">
                        <?php if ($recent_events && $recent_events->num_rows > 0): ?>
                            <?php while ($event = $recent_events->fetch_assoc()): ?>
                                <div
                                    class="p-4 transition-all border border-gray-200 rounded-lg hover:border-indigo-200 hover:bg-indigo-50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h3 class="mb-1 font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($event['event_name']); ?></h3>
                                            <p class="text-sm text-gray-600">
                                                <i class="mr-1 fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($event['venue_name'] ?? 'No venue assigned'); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <i class="mr-1 fas fa-calendar"></i>
                                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        echo $event['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : ($event['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : ($event['status'] == 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'));
                                        ?>">
                                            <?php echo ucfirst($event['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500">
                                <i class="mb-3 text-4xl fas fa-calendar-times"></i>
                                <p class="mb-2 font-semibold">No events yet</p>
                                <p class="text-sm">Create your first event to get started!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="../../assets/js/organizer.js"></script>
    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = 'Montserrat, sans-serif';
        Chart.defaults.color = '#6B7280';

        <?php
        $totalEvents = $stats['pending_events'] + $stats['confirmed_events'] + $stats['completed_events'] + $stats['cancelled_events'];
        if ($totalEvents > 0):
        ?>
            // Events Status Pie Chart
            const eventsStatusCtx = document.getElementById('eventsStatusChart').getContext('2d');
            const eventsStatusData = {
                labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending_events']; ?>,
                        <?php echo $stats['confirmed_events']; ?>,
                        <?php echo $stats['completed_events']; ?>,
                        <?php echo $stats['cancelled_events']; ?>
                    ],
                    backgroundColor: [
                        'rgba(251, 191, 36, 0.8)', // Yellow for Pending
                        'rgba(34, 197, 94, 0.8)', // Green for Confirmed
                        'rgba(59, 130, 246, 0.8)', // Blue for Completed
                        'rgba(239, 68, 68, 0.8)' // Red for Cancelled
                    ],
                    borderColor: [
                        'rgba(251, 191, 36, 1)',
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 2
                }]
            };

            new Chart(eventsStatusCtx, {
                type: 'doughnut',
                data: eventsStatusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12,
                                    weight: '600'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Monthly Spending Line Chart
        const monthlySpendingCtx = document.getElementById('monthlySpendingChart').getContext('2d');

        // Prepare data for last 12 months
        const monthlyData = <?php echo json_encode($monthly_spending); ?>;

        // Function to generate chart data based on period
        function generateSpendingData(months) {
            const labels = [];
            const data = [];
            const today = new Date();

            for (let i = months - 1; i >= 0; i--) {
                const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
                const monthKey = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                const monthLabel = d.toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                });
                labels.push(monthLabel);
                data.push(monthlyData[monthKey] || 0);
            }

            return {
                labels,
                data
            };
        }

        const monthlySpendingData = {
            labels: [],
            datasets: [{
                label: 'Total Spending (₱)',
                data: [],
                borderColor: 'rgba(99, 102, 241, 1)',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: 'rgba(99, 102, 241, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: 'rgba(99, 102, 241, 1)',
                pointHoverBorderColor: '#fff'
            }]
        };

        const spendingChart = new Chart(monthlySpendingCtx, {
            type: 'line',
            data: monthlySpendingData,
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
                                return '₱' + context.parsed.y.toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString('en-PH');
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Update chart based on filter
        function updateSpendingChart(period) {
            const {
                labels,
                data
            } = generateSpendingData(parseInt(period));
            spendingChart.data.labels = labels;
            spendingChart.data.datasets[0].data = data;
            spendingChart.update();
        }

        // Initialize chart with default 6 months
        updateSpendingChart(6);

        // Add event listener for filter
        document.getElementById('spendingPeriodFilter').addEventListener('change', function(e) {
            updateSpendingChart(e.target.value);
        });
    </script>
</body>

</html>