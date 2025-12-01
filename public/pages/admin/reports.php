<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';

// Get filter parameters
$filter_type = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build date filter for queries
$date_filter = '';
$where_completed = "WHERE status = 'completed'";

if ($filter_type === 'custom' && $start_date && $end_date) {
    $date_filter = " AND event_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
    $where_completed .= $date_filter;
} elseif ($filter_type === '7days') {
    $date_filter = " AND event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $where_completed .= $date_filter;
} elseif ($filter_type === '30days') {
    $date_filter = " AND event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $where_completed .= $date_filter;
} elseif ($filter_type === '3months') {
    $date_filter = " AND event_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
    $where_completed .= $date_filter;
} elseif ($filter_type === '6months') {
    $date_filter = " AND event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $where_completed .= $date_filter;
} elseif ($filter_type === '1year') {
    $date_filter = " AND YEAR(event_date) = YEAR(CURDATE())";
    $where_completed .= $date_filter;
}

// Revenue statistics
$revenue_stats = $conn->query("SELECT 
    SUM(total_cost) as total_revenue,
    COUNT(*) as total_events,
    AVG(total_cost) as avg_revenue,
    AVG(expected_guests) as avg_guests
    FROM events $where_completed")->fetch_assoc();

// Event type distribution
$event_types = $conn->query("SELECT event_type, COUNT(*) as count, SUM(total_cost) as revenue 
    FROM events $where_completed
    GROUP BY event_type ORDER BY revenue DESC");

$event_type_data = [];
while ($row = $event_types->fetch_assoc()) {
    $event_type_data[] = $row;
}

// Monthly revenue trend (last 12 months)
$monthly_data = $conn->query("SELECT 
    DATE_FORMAT(event_date, '%Y-%m') as month,
    DATE_FORMAT(event_date, '%b %Y') as month_label,
    COUNT(*) as event_count,
    SUM(total_cost) as revenue
    FROM events 
    WHERE status = 'completed'" . $date_filter . "
    GROUP BY month ORDER BY month DESC LIMIT 12");

$monthly_trend = [];
while ($row = $monthly_data->fetch_assoc()) {
    $monthly_trend[] = $row;
}
$monthly_trend = array_reverse($monthly_trend);

// Top venues
$top_venues = $conn->query("SELECT v.venue_name, CONCAT(l.city, ', ', l.province) as location, 
    COUNT(e.event_id) as event_count, 
    SUM(e.total_cost) as total_revenue
    FROM venues v
    LEFT JOIN locations l ON v.location_id = l.location_id
    LEFT JOIN events e ON v.venue_id = e.venue_id AND e.status = 'completed'" . ($date_filter ? str_replace("event_date", "e.event_date", $date_filter) : "") . "
    GROUP BY v.venue_id HAVING event_count > 0
    ORDER BY total_revenue DESC LIMIT 10");

// Top organizers
$top_organizers = $conn->query("SELECT 
    u.first_name, u.last_name, u.email,
    COUNT(e.event_id) as events_organized,
    SUM(e.total_cost) as total_value
    FROM users u
    LEFT JOIN events e ON u.user_id = e.organizer_id 
    WHERE u.role = 'organizer' AND e.status = 'completed'" . ($date_filter ? str_replace("event_date", "e.event_date", $date_filter) : "") . "
    GROUP BY u.user_id HAVING events_organized > 0
    ORDER BY total_value DESC LIMIT 10");

// Event status distribution
$status_where_clause = "WHERE 1=1";
if ($date_filter) {
    $status_where_clause .= $date_filter;
}
$status_dist = $conn->query("SELECT status, COUNT(*) as count 
    FROM events 
    $status_where_clause
    GROUP BY status");

$status_data = [];
while ($row = $status_dist->fetch_assoc()) {
    $status_data[] = $row;
}

// Booking trends by day of week
$dow_data = $conn->query("SELECT 
    DAYNAME(event_date) as day_name,
    DAYOFWEEK(event_date) as day_num,
    COUNT(*) as booking_count,
    SUM(total_cost) as revenue
    FROM events $where_completed
    GROUP BY day_num, day_name
    ORDER BY day_num");

$dow_trend = [];
while ($row = $dow_data->fetch_assoc()) {
    $dow_trend[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | Gatherly Admin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <style>
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content button {
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            border: none;
            background: white;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dropdown-content button:hover {
            background-color: #f3f4f6;
        }

        @media print {

            .no-print,
            aside,
            nav,
            button,
            .dropdown {
                display: none !important;
            }

            .bg-white {
                background: white !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50 font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                        Reports & Analytics
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Comprehensive insights and performance metrics</p>
                </div>

                <!-- Export Dropdown -->
                <div class="dropdown no-print">
                    <button onclick="toggleDropdown()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        Export Report
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div id="exportDropdown" class="dropdown-content">
                        <button onclick="exportToPDF()" class="text-red-600">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                        </button>
                        <button onclick="exportToCSV()" class="text-green-600">
                            <i class="fas fa-file-csv mr-2"></i>Export as CSV
                        </button>
                        <button onclick="window.print()" class="text-gray-700">
                            <i class="fas fa-print mr-2"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label for="filterType" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-filter mr-1 text-blue-600"></i>
                            Filter Period
                        </label>
                        <select name="filter" id="filterType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="7days" <?= $filter_type === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30days" <?= $filter_type === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="3months" <?= $filter_type === '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                            <option value="6months" <?= $filter_type === '6months' ? 'selected' : '' ?>>Last 6 Months</option>
                            <option value="1year" <?= $filter_type === '1year' ? 'selected' : '' ?>>This Year</option>
                            <option value="custom" <?= $filter_type === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>

                    <div class="flex-1 min-w-[200px]" id="startDateDiv" style="display: <?= $filter_type === 'custom' ? 'block' : 'none' ?>">
                        <label for="startDate" class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" id="startDate" value="<?= htmlspecialchars($start_date ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="flex-1 min-w-[200px]" id="endDateDiv" style="display: <?= $filter_type === 'custom' ? 'block' : 'none' ?>">
                        <label for="endDate" class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" id="endDate" value="<?= htmlspecialchars($end_date ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-md">
                        <i class="fas fa-search mr-2"></i>Apply Filters
                    </button>
                    <?php if ($filter_type !== 'all'): ?>
                        <a href="reports.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-8" id="printableArea">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 2); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-money-bill-wave text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Total Events</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($revenue_stats['total_events'] ?? 0); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-calendar-check text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Avg Revenue</p>
                            <p class="text-2xl font-bold text-purple-600">₱<?php echo number_format($revenue_stats['avg_revenue'] ?? 0, 2); ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-chart-line text-2xl text-purple-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Avg Guests</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo number_format($revenue_stats['avg_guests'] ?? 0); ?></p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-full">
                            <i class="fas fa-users text-2xl text-orange-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Monthly Revenue Trend -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                        Monthly Revenue Trend
                    </h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Event Type Distribution -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie text-purple-600 mr-2"></i>
                        Event Type Distribution
                    </h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="eventTypeChart"></canvas>
                    </div>
                </div>

                <!-- Event Status Distribution -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar text-green-600 mr-2"></i>
                        Event Status Overview
                    </h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Bookings by Day of Week -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-calendar-week text-orange-600 mr-2"></i>
                        Bookings by Day of Week
                    </h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="dowChart"></canvas>
                    </div>
                </div>

                <!-- Top Venues Bar Chart -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-building text-indigo-600 mr-2"></i>
                        Top Venues by Revenue
                    </h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="venuesChart"></canvas>
                    </div>
                </div>

                <!-- Revenue vs Events Comparison -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-area text-teal-600 mr-2"></i>
                        Revenue & Events Correlation
                    </h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="correlationChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Venues Table -->
            <div class="bg-white p-6 rounded-xl shadow-md mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-trophy text-yellow-600 mr-2"></i>
                    Top Performing Venues
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Venue Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Events</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php
                            $rank = 1;
                            while ($venue = $top_venues->fetch_assoc()):
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <span class="<?php echo $rank <= 3 ? 'text-yellow-600 font-bold' : 'text-gray-600'; ?>">
                                            #<?php echo $rank++; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($venue['venue_name']); ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($venue['location']); ?></td>
                                    <td class="px-6 py-4 text-gray-900"><?php echo $venue['event_count']; ?></td>
                                    <td class="px-6 py-4 font-semibold text-green-600">₱<?php echo number_format($venue['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Organizers Table -->
            <div class="bg-white p-6 rounded-xl shadow-md mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-star text-blue-600 mr-2"></i>
                    Top Organizers
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Organizer</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Events</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php
                            $rank = 1;
                            while ($org = $top_organizers->fetch_assoc()):
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <span class="<?php echo $rank <= 3 ? 'text-blue-600 font-bold' : 'text-gray-600'; ?>">
                                            #<?php echo $rank++; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($org['first_name'] . ' ' . $org['last_name']); ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($org['email']); ?></td>
                                    <td class="px-6 py-4 text-gray-900"><?php echo $org['events_organized']; ?></td>
                                    <td class="px-6 py-4 font-semibold text-green-600">₱<?php echo number_format($org['total_value'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle dropdown
        function toggleDropdown() {
            document.getElementById('exportDropdown').classList.toggle('show');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown button')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }

        // Toggle custom date fields
        document.getElementById('filterType').addEventListener('change', function() {
            const isCustom = this.value === 'custom';
            document.getElementById('startDateDiv').style.display = isCustom ? 'block' : 'none';
            document.getElementById('endDateDiv').style.display = isCustom ? 'block' : 'none';
        });

        // Chart.js configurations
        const chartColors = {
            blue: '#3b82f6',
            purple: '#a855f7',
            green: '#22c55e',
            orange: '#f97316',
            red: '#ef4444',
            yellow: '#eab308',
            indigo: '#6366f1',
            teal: '#14b8a6'
        };

        // Monthly Revenue Trend Chart
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trend, 'month_label')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthly_trend, 'revenue')); ?>,
                    borderColor: chartColors.blue,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Events',
                    data: <?php echo json_encode(array_column($monthly_trend, 'event_count')); ?>,
                    borderColor: chartColors.purple,
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left'
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Event Type Distribution Chart
        new Chart(document.getElementById('eventTypeChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($event_type_data, 'event_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($event_type_data, 'count')); ?>,
                    backgroundColor: [chartColors.blue, chartColors.purple, chartColors.green, chartColors.orange, chartColors.red, chartColors.yellow]
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

        // Event Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($status_data, 'status')); ?>,
                datasets: [{
                    label: 'Events',
                    data: <?php echo json_encode(array_column($status_data, 'count')); ?>,
                    backgroundColor: [chartColors.green, chartColors.blue, chartColors.orange, chartColors.red, chartColors.purple]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Day of Week Chart
        new Chart(document.getElementById('dowChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($dow_trend, 'day_name')); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode(array_column($dow_trend, 'booking_count')); ?>,
                    backgroundColor: chartColors.orange
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Top Venues Chart
        <?php
        $top_venues->data_seek(0);
        $venue_names = [];
        $venue_revenues = [];
        while ($v = $top_venues->fetch_assoc()) {
            $venue_names[] = $v['venue_name'];
            $venue_revenues[] = $v['total_revenue'];
        }
        ?>
        new Chart(document.getElementById('venuesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($venue_names); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($venue_revenues); ?>,
                    backgroundColor: chartColors.indigo
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Revenue vs Events Correlation Chart
        new Chart(document.getElementById('correlationChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trend, 'month_label')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthly_trend, 'revenue')); ?>,
                    borderColor: chartColors.teal,
                    backgroundColor: 'rgba(20, 184, 166, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Export Functions
        function exportToCSV() {
            let csv = ['"Gatherly Reports & Analytics"', '"Generated: <?php echo date("F d, Y H:i:s"); ?>"', ''];

            const tables = document.querySelectorAll('#printableArea table');
            tables.forEach(table => {
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const cols = row.querySelectorAll('td, th');
                    const csvRow = Array.from(cols).map(col => '"' + col.textContent.trim().replace(/"/g, '""') + '"');
                    csv.push(csvRow.join(','));
                });
                csv.push('');
            });

            const blob = new Blob([csv.join('\n')], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'gatherly_report_<?php echo date("Y-m-d"); ?>.csv';
            link.click();
        }

        async function exportToPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();

            // Header with gradient effect
            doc.setFillColor(59, 130, 246);
            doc.rect(0, 0, pageWidth, 35, 'F');

            doc.setTextColor(255, 255, 255);
            doc.setFontSize(24);
            doc.setFont(undefined, 'bold');
            doc.text('Gatherly Reports & Analytics', pageWidth / 2, 15, {
                align: 'center'
            });

            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            doc.text('Generated: <?php echo date("F d, Y H:i:s"); ?>', pageWidth / 2, 25, {
                align: 'center'
            });

            doc.setTextColor(0, 0, 0);
            let yPos = 45;

            // Summary Statistics
            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(59, 130, 246);
            doc.text('Summary Overview', 14, yPos);
            yPos += 8;

            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(0, 0, 0);

            const stats = [{
                    label: 'Total Revenue',
                    value: 'PHP <?php echo number_format($revenue_stats["total_revenue"] ?? 0, 2); ?>'
                },
                {
                    label: 'Total Events',
                    value: '<?php echo $revenue_stats["event_count"] ?? 0; ?> Events'
                },
                {
                    label: 'Average Revenue',
                    value: 'PHP <?php echo number_format($revenue_stats["avg_revenue"] ?? 0, 2); ?>'
                },
                {
                    label: 'Average Guests',
                    value: '<?php echo number_format($revenue_stats["avg_guests"] ?? 0); ?> Guests'
                }
            ];

            // Create summary boxes
            const boxWidth = 45;
            const boxHeight = 20;
            let xPos = 14;

            stats.forEach((stat, index) => {
                if (index === 2) {
                    xPos = 14;
                    yPos += 25;
                }

                // Box background
                doc.setFillColor(249, 250, 251);
                doc.roundedRect(xPos, yPos, boxWidth, boxHeight, 2, 2, 'F');
                doc.setDrawColor(229, 231, 235);
                doc.roundedRect(xPos, yPos, boxWidth, boxHeight, 2, 2, 'S');

                // Label
                doc.setFontSize(8);
                doc.setTextColor(107, 114, 128);
                doc.text(stat.label, xPos + boxWidth / 2, yPos + 7, {
                    align: 'center'
                });

                // Value
                doc.setFontSize(11);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(17, 24, 39);
                doc.text(stat.value, xPos + boxWidth / 2, yPos + 15, {
                    align: 'center'
                });
                doc.setFont(undefined, 'normal');

                xPos += boxWidth + 5;
            });

            yPos += 30;

            // Capture and add charts
            const charts = [{
                    id: 'monthlyChart',
                    title: 'Monthly Revenue Trend'
                },
                {
                    id: 'eventTypeChart',
                    title: 'Event Type Distribution'
                },
                {
                    id: 'statusChart',
                    title: 'Event Status Overview'
                },
                {
                    id: 'dowChart',
                    title: 'Bookings by Day of Week'
                },
                {
                    id: 'venuesChart',
                    title: 'Top Venues by Revenue'
                },
                {
                    id: 'correlationChart',
                    title: 'Revenue Correlation'
                }
            ];

            for (let i = 0; i < charts.length; i++) {
                const chart = charts[i];
                const canvas = document.getElementById(chart.id);

                if (canvas) {
                    // Check if we need a new page
                    if (yPos > pageHeight - 70) {
                        doc.addPage();
                        yPos = 20;
                    }

                    // Chart title
                    doc.setFontSize(12);
                    doc.setFont(undefined, 'bold');
                    doc.setTextColor(59, 130, 246);
                    doc.text(chart.title, 14, yPos);
                    yPos += 8;

                    // Add chart image
                    const imgData = canvas.toDataURL('image/png');
                    const imgWidth = pageWidth - 28;
                    const imgHeight = 60;
                    doc.addImage(imgData, 'PNG', 14, yPos, imgWidth, imgHeight);
                    yPos += imgHeight + 10;

                    doc.setFont(undefined, 'normal');
                    doc.setTextColor(0, 0, 0);
                }
            }

            // Add tables
            if (yPos > pageHeight - 80) {
                doc.addPage();
                yPos = 20;
            }

            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(59, 130, 246);
            doc.text('Detailed Data Tables', 14, yPos);
            yPos += 8;
            doc.setFont(undefined, 'normal');
            doc.setTextColor(0, 0, 0);

            const tables = document.querySelectorAll('#printableArea table');

            tables.forEach((table, index) => {
                if (yPos > pageHeight - 60) {
                    doc.addPage();
                    yPos = 20;
                }

                const section = table.closest('.rounded-xl');
                if (section) {
                    const title = section.querySelector('h2');
                    if (title) {
                        doc.setFontSize(11);
                        doc.setFont(undefined, 'bold');
                        doc.setTextColor(31, 41, 55);
                        doc.text(title.textContent.trim(), 14, yPos);
                        yPos += 6;
                        doc.setFont(undefined, 'normal');
                        doc.setTextColor(0, 0, 0);
                    }
                }

                doc.autoTable({
                    html: table,
                    startY: yPos,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [59, 130, 246],
                        textColor: [255, 255, 255],
                        fontSize: 9,
                        fontStyle: 'bold',
                        halign: 'left'
                    },
                    bodyStyles: {
                        fontSize: 8,
                        textColor: [55, 65, 81]
                    },
                    alternateRowStyles: {
                        fillColor: [249, 250, 251]
                    },
                    margin: {
                        left: 14,
                        right: 14
                    },
                    didDrawPage: function(data) {
                        // Footer
                        doc.setFontSize(8);
                        doc.setTextColor(156, 163, 175);
                        doc.text(
                            'Page ' + doc.internal.getCurrentPageInfo().pageNumber,
                            pageWidth / 2,
                            pageHeight - 10, {
                                align: 'center'
                            }
                        );
                    }
                });

                yPos = doc.lastAutoTable.finalY + 12;
            });

            doc.save('gatherly_report_<?php echo date("Y-m-d"); ?>.pdf');
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>