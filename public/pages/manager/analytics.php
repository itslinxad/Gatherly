<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Manager';
$user_id = $_SESSION['user_id'];
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

// Get filter parameters
$filter_type = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Build date filter for queries
$date_condition = "";
$params = [$user_id];

if ($filter_type === 'custom' && $start_date && $end_date) {
    $date_condition = "AND e.event_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif ($filter_type === '7days') {
    $date_condition = "AND e.event_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter_type === '30days') {
    $date_condition = "AND e.event_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($filter_type === '3months') {
    $date_condition = "AND e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
} elseif ($filter_type === '6months') {
    $date_condition = "AND e.event_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
} elseif ($filter_type === '1year') {
    $date_condition = "AND e.event_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

// Fetch analytics data
$analytics = [
    'total_bookings' => 0,
    'total_revenue' => 0,
    'avg_booking_value' => 0,
    'total_venues' => 0,
    'bookings_by_status' => [],
    'revenue_by_venue' => [],
    'monthly_bookings' => [],
    'monthly_revenue' => [],
    'event_types' => [],
    'payment_completion' => [],
    'avg_guests_per_booking' => 0
];

try {
    // Determine bind types based on params
    $bind_types = count($params) == 1 ? 'i' : 'iss';

    // Total bookings
    $query = "SELECT COUNT(*) as count FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['total_bookings'] = $stmt->get_result()->fetch_assoc()['count'];

    // Total revenue
    $query = "SELECT SUM(e.total_cost) as total FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? AND e.status IN ('confirmed', 'completed') $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['total_revenue'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Average booking value
    $query = "SELECT AVG(e.total_cost) as avg FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? AND e.status IN ('confirmed', 'completed') $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['avg_booking_value'] = $stmt->get_result()->fetch_assoc()['avg'] ?? 0;

    // Total active venues
    $query = "SELECT COUNT(*) as count FROM venues WHERE manager_id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $analytics['total_venues'] = $stmt->get_result()->fetch_assoc()['count'];

    // Average guests per booking
    $query = "SELECT AVG(e.expected_guests) as avg FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['avg_guests_per_booking'] = $stmt->get_result()->fetch_assoc()['avg'] ?? 0;

    // Bookings by status
    $query = "SELECT e.status, COUNT(*) as count FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? $date_condition 
              GROUP BY e.status";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['bookings_by_status'][$row['status']] = $row['count'];
    }

    // Revenue by venue (top 5)
    $query = "SELECT v.venue_name, COUNT(e.event_id) as booking_count, SUM(e.total_cost) as revenue 
              FROM venues v 
              LEFT JOIN events e ON v.venue_id = e.venue_id AND e.status IN ('confirmed', 'completed') $date_condition
              WHERE v.manager_id = ? 
              GROUP BY v.venue_id, v.venue_name 
              ORDER BY revenue DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['revenue_by_venue'][] = $row;
    }

    // Monthly bookings count
    $query = "SELECT DATE_FORMAT(e.event_date, '%Y-%m') as month, COUNT(*) as count 
              FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? $date_condition 
              GROUP BY DATE_FORMAT(e.event_date, '%Y-%m') 
              ORDER BY month ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['monthly_bookings'][$row['month']] = $row['count'];
    }

    // Monthly revenue
    $query = "SELECT DATE_FORMAT(e.event_date, '%Y-%m') as month, SUM(e.total_cost) as total 
              FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? AND e.status IN ('confirmed', 'completed') $date_condition 
              GROUP BY DATE_FORMAT(e.event_date, '%Y-%m') 
              ORDER BY month ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['monthly_revenue'][$row['month']] = $row['total'];
    }

    // Event types distribution
    $query = "SELECT e.event_type, COUNT(*) as count, SUM(e.total_cost) as revenue 
              FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE v.manager_id = ? AND e.status IN ('confirmed', 'completed') $date_condition 
              GROUP BY e.event_type 
              ORDER BY count DESC 
              LIMIT 6";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['event_types'][] = $row;
    }

    // Payment completion rate
    $query = "SELECT 
              SUM(CASE WHEN ep.payment_status = 'verified' THEN ep.amount_paid ELSE 0 END) as paid_amount,
              SUM(e.total_cost) as total_amount,
              COUNT(DISTINCT e.event_id) as total_bookings,
              COUNT(DISTINCT CASE WHEN ep.payment_status = 'verified' THEN e.event_id END) as paid_bookings
              FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              LEFT JOIN event_payments ep ON e.event_id = ep.event_id
              WHERE v.manager_id = ? AND e.status IN ('confirmed', 'completed') $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $analytics['payment_completion'] = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body class="bg-gradient-to-br from-green-50 via-white to-teal-50 font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'md:ml-64' : 'container mx-auto'; ?> transition-all duration-300">
        <?php if ($nav_layout === 'sidebar'): ?>
        <div class="min-h-screen">
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-line mr-2 text-green-600"></i>
                    Venue Analytics
                </h1>
                <p class="text-sm text-gray-600">Comprehensive insights into your venue performance</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                <div class="container px-6 py-10 mx-auto">
                    <!-- Header for Navbar Layout -->
                    <div class="mb-8">
                        <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">
                            <i class="fas fa-chart-line mr-2 text-green-600"></i>
                            Venue Analytics
                        </h1>
                        <p class="text-gray-600">Comprehensive insights into your venue performance</p>
                    </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="bg-white p-6 rounded-xl shadow-md mb-8">
                        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <label for="filterType" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-filter mr-1 text-green-600"></i>
                                    Filter Period
                                </label>
                                <select name="filter" id="filterType"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Time
                                    </option>
                                    <option value="7days" <?= $filter_type === '7days' ? 'selected' : '' ?>>7 Days
                                    </option>
                                    <option value="30days" <?= $filter_type === '30days' ? 'selected' : '' ?>>30 Days
                                    </option>
                                    <option value="3months" <?= $filter_type === '3months' ? 'selected' : '' ?>>3 Months
                                    </option>
                                    <option value="6months" <?= $filter_type === '6months' ? 'selected' : '' ?>>6 Months
                                    </option>
                                    <option value="1year" <?= $filter_type === '1year' ? 'selected' : '' ?>>This Year
                                    </option>
                                    <option value="custom" <?= $filter_type === 'custom' ? 'selected' : '' ?>>Custom
                                        Range</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-[200px]" id="startDateDiv"
                                style="display: <?= $filter_type === 'custom' ? 'block' : 'none' ?>">
                                <label for="startDate" class="block text-sm font-semibold text-gray-700 mb-2">Start
                                    Date</label>
                                <input type="date" name="start_date" id="startDate"
                                    value="<?= htmlspecialchars($start_date ?? '') ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            </div>

                            <div class="flex-1 min-w-[200px]" id="endDateDiv"
                                style="display: <?= $filter_type === 'custom' ? 'block' : 'none' ?>">
                                <label for="endDate" class="block text-sm font-semibold text-gray-700 mb-2">End
                                    Date</label>
                                <input type="date" name="end_date" id="endDate"
                                    value="<?= htmlspecialchars($end_date ?? '') ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            </div>

                            <div class="flex items-end gap-3 ml-auto">
                                <button type="submit"
                                    class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors shadow-md">
                                    <i class="fas fa-search mr-2"></i>Apply Filters
                                </button>
                                <div class="relative">
                                    <button type="button" id="exportDropdownBtn"
                                        class="px-6 py-2 bg-gray-700 text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors shadow-md">
                                        <i class="fas fa-download mr-2"></i>Export
                                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                    </button>
                                    <div id="exportDropdown"
                                        class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-10">
                                        <button type="button" onclick="exportCSV()"
                                            class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors flex items-center border-b border-gray-100">
                                            <i class="fas fa-file-csv text-green-600 mr-3"></i>
                                            <span class="font-medium">Export as CSV</span>
                                        </button>
                                        <button type="button" onclick="exportPDF()"
                                            class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors flex items-center">
                                            <i class="fas fa-file-pdf text-red-600 mr-3"></i>
                                            <span class="font-medium">Export as PDF</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Key Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 mb-1">Total Bookings</p>
                                    <p class="text-3xl font-bold text-gray-900">
                                        <?php echo number_format($analytics['total_bookings']); ?>
                                    </p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <i class="text-green-600 text-2xl fas fa-calendar-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 mb-1">Total Revenue</p>
                                    <p class="text-3xl font-bold text-green-600">
                                        ₱<?php echo number_format($analytics['total_revenue'], 2); ?>
                                    </p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <i class="text-green-600 text-2xl fas fa-peso-sign"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 mb-1">Avg Booking Value</p>
                                    <p class="text-3xl font-bold text-gray-900">
                                        ₱<?php echo number_format($analytics['avg_booking_value'], 2); ?>
                                    </p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <i class="text-blue-600 text-2xl fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 mb-1">Active Venues</p>
                                    <p class="text-3xl font-bold text-gray-900">
                                        <?php echo number_format($analytics['total_venues']); ?>
                                    </p>
                                </div>
                                <div class="p-3 bg-teal-100 rounded-lg">
                                    <i class="text-teal-600 text-2xl fas fa-building"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 1 -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Bookings by Status -->
                        <div class="bg-white p-6 rounded-xl shadow-md">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">
                                <i class="fas fa-tasks mr-2 text-green-600"></i>
                                Bookings by Status
                            </h2>
                            <div class="relative" style="height: 300px;">
                                <?php if (!empty($analytics['bookings_by_status'])): ?>
                                <canvas id="statusChart"></canvas>
                                <?php else: ?>
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <i class="mb-3 text-5xl fas fa-chart-pie"></i>
                                    <p class="font-semibold">No bookings data yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Monthly Revenue -->
                        <div class="bg-white p-6 rounded-xl shadow-md">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">
                                <i class="fas fa-money-bill-trend-up mr-2 text-green-600"></i>
                                Monthly Revenue
                            </h2>
                            <div class="relative" style="height: 300px;">
                                <?php if (!empty($analytics['monthly_revenue'])): ?>
                                <canvas id="revenueChart"></canvas>
                                <?php else: ?>
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <i class="mb-3 text-5xl fas fa-chart-line"></i>
                                    <p class="font-semibold">No revenue data yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Event Types Distribution -->
                        <div class="bg-white p-6 rounded-xl shadow-md">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">
                                <i class="fas fa-layer-group mr-2 text-green-600"></i>
                                Event Types Distribution
                            </h2>
                            <div class="relative" style="height: 300px;">
                                <?php if (!empty($analytics['event_types'])): ?>
                                <canvas id="eventTypesChart"></canvas>
                                <?php else: ?>
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <i class="mb-3 text-5xl fas fa-chart-bar"></i>
                                    <p class="font-semibold">No event types data yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top Venues by Revenue -->
                        <div class="bg-white p-6 rounded-xl shadow-md">
                            <h2 class="text-xl font-bold text-gray-800 mb-4">
                                <i class="fas fa-trophy mr-2 text-green-600"></i>
                                Top Venues by Revenue
                            </h2>
                            <div class="space-y-4">
                                <?php if (!empty($analytics['revenue_by_venue'])): ?>
                                <?php foreach ($analytics['revenue_by_venue'] as $index => $venue): ?>
                                <?php
                                        $maxRevenue = $analytics['revenue_by_venue'][0]['revenue'];
                                        $percentage = $maxRevenue > 0 ? ($venue['revenue'] / $maxRevenue) * 100 : 0;
                                        $colors = ['bg-green-500', 'bg-teal-500', 'bg-blue-500', 'bg-indigo-500', 'bg-purple-500'];
                                        $bgColor = $colors[$index % 5];
                                        ?>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($venue['venue_name'] ?? 'Unknown'); ?>
                                        </span>
                                        <span class="text-sm font-bold text-green-600">
                                            ₱<?php echo number_format($venue['revenue'] ?? 0, 2); ?>
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="<?php echo $bgColor; ?> h-3 rounded-full transition-all duration-500"
                                            style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo $venue['booking_count']; ?>
                                        booking<?php echo $venue['booking_count'] != 1 ? 's' : ''; ?>
                                    </p>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                                    <i class="mb-3 text-5xl fas fa-building"></i>
                                    <p class="font-semibold">No venue data yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Completion Rate -->
                    <?php if (!empty($analytics['payment_completion'])): ?>
                    <div class="bg-white p-6 rounded-xl shadow-md mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-coins mr-2 text-green-600"></i>
                            Payment Collection Overview
                        </h2>
                        <?php
                            $paymentData = $analytics['payment_completion'];
                            $totalAmount = $paymentData['total_amount'] ?? 0;
                            $paidAmount = $paymentData['paid_amount'] ?? 0;
                            $collectionRate = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;
                            ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                                <p class="text-sm font-medium text-gray-600 mb-2">Total Expected</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    ₱<?php echo number_format($totalAmount, 2); ?></p>
                            </div>
                            <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <p class="text-sm font-medium text-gray-600 mb-2">Amount Collected</p>
                                <p class="text-2xl font-bold text-blue-600">
                                    ₱<?php echo number_format($paidAmount, 2); ?></p>
                            </div>
                            <div class="text-center p-4 bg-teal-50 rounded-lg border border-teal-200">
                                <p class="text-sm font-medium text-gray-600 mb-2">Collection Rate</p>
                                <p class="text-2xl font-bold text-teal-600">
                                    <?php echo number_format($collectionRate, 1); ?>%</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-gray-200 rounded-full h-6">
                                <div class="bg-gradient-to-r from-green-500 to-teal-500 h-6 rounded-full flex items-center justify-end pr-3"
                                    style="width: <?php echo min($collectionRate, 100); ?>%">
                                    <span
                                        class="text-xs font-bold text-white"><?php echo number_format($collectionRate, 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            </div>
            <?php else: ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Export dropdown toggle
    const exportDropdownBtn = document.getElementById('exportDropdownBtn');
    const exportDropdown = document.getElementById('exportDropdown');

    exportDropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        exportDropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!exportDropdownBtn.contains(e.target) && !exportDropdown.contains(e.target)) {
            exportDropdown.classList.add('hidden');
        }
    });

    function closeExportDropdown() {
        exportDropdown.classList.add('hidden');
    }

    // Export to CSV
    function exportCSV() {
        closeExportDropdown();
        const analytics = {
            totalBookings: <?php echo $analytics['total_bookings']; ?>,
            totalRevenue: <?php echo $analytics['total_revenue']; ?>,
            avgBookingValue: <?php echo $analytics['avg_booking_value']; ?>,
            totalVenues: <?php echo $analytics['total_venues']; ?>,
            avgGuests: <?php echo $analytics['avg_guests_per_booking']; ?>,
            bookingsByStatus: <?php echo json_encode($analytics['bookings_by_status']); ?>,
            revenueByVenue: <?php echo json_encode($analytics['revenue_by_venue']); ?>,
            monthlyBookings: <?php echo json_encode($analytics['monthly_bookings']); ?>,
            monthlyRevenue: <?php echo json_encode($analytics['monthly_revenue']); ?>,
            eventTypes: <?php echo json_encode($analytics['event_types']); ?>
        };

        const filterInfo = '<?php
                                if ($filter_type === "custom" && $start_date && $end_date) {
                                    echo "Custom Range: $start_date to $end_date";
                                } else {
                                    echo ucfirst(str_replace("_", " ", $filter_type));
                                }
                                ?>';

        let csv = 'Gatherly Venue Analytics Report\n';
        csv += 'Filter Period: ' + filterInfo + '\n';
        csv += 'Generated: ' + new Date().toLocaleString() + '\n\n';

        // Summary Statistics
        csv += 'SUMMARY STATISTICS\n';
        csv += 'Metric,Value\n';
        csv += 'Total Bookings,' + analytics.totalBookings + '\n';
        csv += 'Total Revenue,₱' + analytics.totalRevenue.toLocaleString('en-PH', {
            minimumFractionDigits: 2
        }) + '\n';
        csv += 'Average Booking Value,₱' + analytics.avgBookingValue.toLocaleString('en-PH', {
            minimumFractionDigits: 2
        }) + '\n';
        csv += 'Active Venues,' + analytics.totalVenues + '\n';
        csv += 'Average Guests per Booking,' + analytics.avgGuests.toFixed(0) + '\n\n';

        // Bookings by Status
        csv += 'BOOKINGS BY STATUS\n';
        csv += 'Status,Count\n';
        for (const [status, count] of Object.entries(analytics.bookingsByStatus)) {
            csv += status.charAt(0).toUpperCase() + status.slice(1) + ',' + count + '\n';
        }
        csv += '\n';

        // Top Venues
        csv += 'TOP VENUES BY REVENUE\n';
        csv += 'Venue Name,Bookings,Revenue\n';
        analytics.revenueByVenue.forEach(venue => {
            csv += '"' + (venue.venue_name || 'Unknown') + '",' + venue.booking_count + ',₱' +
                parseFloat(venue.revenue).toLocaleString('en-PH', {
                    minimumFractionDigits: 2
                }) + '\n';
        });
        csv += '\n';

        // Event Types
        csv += 'EVENT TYPES DISTRIBUTION\n';
        csv += 'Event Type,Count,Revenue\n';
        analytics.eventTypes.forEach(type => {
            csv += '"' + (type.event_type || 'Unknown') + '",' + type.count + ',₱' +
                parseFloat(type.revenue).toLocaleString('en-PH', {
                    minimumFractionDigits: 2
                }) + '\n';
        });
        csv += '\n';

        // Monthly Revenue
        csv += 'MONTHLY REVENUE\n';
        csv += 'Month,Revenue\n';
        for (const [month, total] of Object.entries(analytics.monthlyRevenue)) {
            const [year, monthNum] = month.split('-');
            const monthName = new Date(year, monthNum - 1).toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
            csv += monthName + ',₱' + parseFloat(total).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            }) + '\n';
        }

        // Download CSV
        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'venue_analytics_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Export to PDF
    async function exportPDF() {
        try {
            closeExportDropdown();

            if (typeof window.jspdf === 'undefined') {
                alert('PDF library not loaded. Please refresh the page and try again.');
                return;
            }

            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'pdfLoading';
            loadingDiv.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loadingDiv.innerHTML = `
                    <div class="bg-white rounded-lg p-6 text-center">
                        <i class="fas fa-spinner fa-spin text-4xl text-green-600 mb-4"></i>
                        <p class="text-lg font-semibold text-gray-700">Generating PDF...</p>
                        <p class="text-sm text-gray-500">Capturing charts and formatting data...</p>
                    </div>
                `;
            document.body.appendChild(loadingDiv);

            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const margin = 15;
            const contentWidth = pageWidth - 2 * margin;
            let yPos = 20;

            // Helper function to add page header
            function addPageHeader(pageNum) {
                pdf.setFillColor(34, 197, 94);
                pdf.rect(0, 0, pageWidth, 8, 'F');
                pdf.setFontSize(8);
                pdf.setTextColor(255, 255, 255);
                pdf.text('Gatherly Venue Analytics Report', margin, 5);
                pdf.text('Page ' + pageNum, pageWidth - margin, 5, {
                    align: 'right'
                });
            }

            // Helper function to add page footer
            function addPageFooter() {
                pdf.setDrawColor(220, 220, 220);
                pdf.line(margin, pageHeight - 10, pageWidth - margin, pageHeight - 10);
                pdf.setFontSize(7);
                pdf.setTextColor(150, 150, 150);
                pdf.text('Generated by Gatherly EMS', pageWidth / 2, pageHeight - 5, {
                    align: 'center'
                });
            }

            let pageCount = 1;

            // Title Page with decorative header
            pdf.setFillColor(34, 197, 94);
            pdf.rect(0, 0, pageWidth, 60, 'F');

            // Logo/Icon area
            pdf.setFillColor(255, 255, 255);
            pdf.circle(pageWidth / 2, 25, 12, 'F');
            pdf.setFontSize(18);
            pdf.setTextColor(34, 197, 94);
            pdf.text('G', pageWidth / 2, 28, {
                align: 'center'
            });

            // Title
            pdf.setFontSize(24);
            pdf.setTextColor(255, 255, 255);
            pdf.setFont(undefined, 'bold');
            pdf.text('Venue Analytics Report', pageWidth / 2, 48, {
                align: 'center'
            });

            yPos = 75;

            // Report Info Box
            pdf.setFillColor(249, 250, 251);
            pdf.setDrawColor(220, 220, 220);
            pdf.roundedRect(margin, yPos, contentWidth, 25, 3, 3, 'FD');

            yPos += 7;
            pdf.setFontSize(10);
            pdf.setTextColor(100, 100, 100);
            const filterInfo = '<?php
                                    if ($filter_type === "custom" && $start_date && $end_date) {
                                        echo "Filter Period: $start_date to $end_date";
                                    } else {
                                        echo "Filter Period: " . ucfirst(str_replace(["_", "days", "months", "year"], [" ", " Days", " Months", " Year"], $filter_type));
                                    }
                                    ?>';
            pdf.text(filterInfo, pageWidth / 2, yPos, {
                align: 'center'
            });
            yPos += 6;
            pdf.text('Generated: ' + new Date().toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }), pageWidth / 2, yPos, {
                align: 'center'
            });
            yPos += 6;
            pdf.setTextColor(34, 197, 94);
            pdf.setFont(undefined, 'bold');
            pdf.text('Manager: <?php echo htmlspecialchars($first_name); ?>', pageWidth / 2, yPos, {
                align: 'center'
            });

            yPos += 25;

            // Summary Statistics with Card Design
            pdf.setFontSize(16);
            pdf.setTextColor(0, 0, 0);
            pdf.setFont(undefined, 'bold');
            pdf.text('Summary Statistics', margin, yPos);
            yPos += 10;

            // Create 4 metric cards
            const cardWidth = (contentWidth - 9) / 2;
            const cardHeight = 28;
            const cards = [{
                    title: 'Total Bookings',
                    value: '<?php echo number_format($analytics['total_bookings']); ?>',
                    color: [34, 197, 94],
                    iconSymbol: 'B'
                },
                {
                    title: 'Total Revenue',
                    value: '₱<?php echo number_format($analytics['total_revenue'], 2); ?>',
                    color: [16, 185, 129],
                    iconSymbol: '$'
                },
                {
                    title: 'Avg Booking Value',
                    value: '₱<?php echo number_format($analytics['avg_booking_value'], 2); ?>',
                    color: [59, 130, 246],
                    iconSymbol: 'A'
                },
                {
                    title: 'Active Venues',
                    value: '<?php echo number_format($analytics['total_venues']); ?>',
                    color: [20, 184, 166],
                    iconSymbol: 'V'
                }
            ];

            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                const col = i % 2;
                const row = Math.floor(i / 2);
                const x = margin + col * (cardWidth + 3);
                const y = yPos + row * (cardHeight + 3);

                // Card background with shadow effect
                pdf.setFillColor(245, 245, 245);
                pdf.roundedRect(x + 1, y + 1, cardWidth, cardHeight, 2, 2, 'F');

                // Card with gradient-like top border
                pdf.setFillColor(255, 255, 255);
                pdf.setDrawColor(card.color[0], card.color[1], card.color[2]);
                pdf.setLineWidth(0.5);
                pdf.roundedRect(x, y, cardWidth, cardHeight, 2, 2, 'FD');

                // Colored top accent
                pdf.setFillColor(card.color[0], card.color[1], card.color[2]);
                pdf.rect(x, y, cardWidth, 3, 'F');

                // Icon circle with letter
                pdf.setFillColor(card.color[0], card.color[1], card.color[2]);
                pdf.circle(x + 8, y + 12, 5, 'F');
                pdf.setFontSize(12);
                pdf.setTextColor(255, 255, 255);
                pdf.setFont(undefined, 'bold');
                pdf.text(card.iconSymbol, x + 8, y + 13.5, {
                    align: 'center'
                });
                pdf.setTextColor(0, 0, 0);

                // Title
                pdf.setFontSize(9);
                pdf.setTextColor(100, 100, 100);
                pdf.setFont(undefined, 'normal');
                pdf.text(card.title, x + 16, y + 12);

                // Value
                pdf.setFontSize(12);
                pdf.setTextColor(0, 0, 0);
                pdf.setFont(undefined, 'bold');
                const valueText = card.value.length > 18 ? card.value.substring(0, 16) + '...' : card.value;
                pdf.text(valueText, x + 16, y + 22);
            }

            yPos += cardHeight * 2 + 15;

            addPageFooter();
            pdf.addPage();
            pageCount++;
            addPageHeader(pageCount);
            yPos = 15;

            addPageFooter();
            pdf.addPage();
            pageCount++;
            addPageHeader(pageCount);
            yPos = 15;

            // Capture and add charts
            <?php if (!empty($analytics['bookings_by_status'])): ?>
            // Bookings by Status Chart
            const statusCanvas = document.getElementById('statusChart');
            if (statusCanvas) {
                pdf.setFontSize(14);
                pdf.setTextColor(34, 197, 94);
                pdf.setFont(undefined, 'bold');
                pdf.text('Bookings by Status', margin, yPos);
                yPos += 8;

                const statusImgData = statusCanvas.toDataURL('image/png');
                const chartWidth = contentWidth * 0.6;
                const chartHeight = 70;
                const chartX = (pageWidth - chartWidth) / 2;

                pdf.setDrawColor(220, 220, 220);
                pdf.setFillColor(255, 255, 255);
                pdf.roundedRect(chartX - 5, yPos - 5, chartWidth + 10, chartHeight + 10, 3, 3, 'FD');
                pdf.addImage(statusImgData, 'PNG', chartX, yPos, chartWidth, chartHeight);
                yPos += chartHeight + 20;
            }
            <?php endif; ?>

            <?php if (!empty($analytics['monthly_revenue'])): ?>
            // Check if we need a new page
            if (yPos > pageHeight - 100) {
                addPageFooter();
                pdf.addPage();
                pageCount++;
                addPageHeader(pageCount);
                yPos = 15;
            }

            // Monthly Revenue Chart
            const revenueCanvas = document.getElementById('revenueChart');
            if (revenueCanvas) {
                pdf.setFontSize(14);
                pdf.setTextColor(34, 197, 94);
                pdf.setFont(undefined, 'bold');
                pdf.text('Monthly Revenue', margin, yPos);
                yPos += 8;

                const revenueImgData = revenueCanvas.toDataURL('image/png');
                const chartWidth = contentWidth - 10;
                const chartHeight = 70;

                pdf.setDrawColor(220, 220, 220);
                pdf.setFillColor(255, 255, 255);
                pdf.roundedRect(margin - 2, yPos - 5, chartWidth + 4, chartHeight + 10, 3, 3, 'FD');
                pdf.addImage(revenueImgData, 'PNG', margin, yPos, chartWidth, chartHeight);
                yPos += chartHeight + 20;
            }
            <?php endif; ?>

            <?php if (!empty($analytics['event_types'])): ?>
            // Check if we need a new page
            if (yPos > pageHeight - 100) {
                addPageFooter();
                pdf.addPage();
                pageCount++;
                addPageHeader(pageCount);
                yPos = 15;
            }

            // Event Types Chart
            const eventTypesCanvas = document.getElementById('eventTypesChart');
            if (eventTypesCanvas) {
                pdf.setFontSize(14);
                pdf.setTextColor(34, 197, 94);
                pdf.setFont(undefined, 'bold');
                pdf.text('Event Types Distribution', margin, yPos);
                yPos += 8;

                const eventTypesImgData = eventTypesCanvas.toDataURL('image/png');
                const chartWidth = contentWidth - 10;
                const chartHeight = 70;

                pdf.setDrawColor(220, 220, 220);
                pdf.setFillColor(255, 255, 255);
                pdf.roundedRect(margin - 2, yPos - 5, chartWidth + 4, chartHeight + 10, 3, 3, 'FD');
                pdf.addImage(eventTypesImgData, 'PNG', margin, yPos, chartWidth, chartHeight);
                yPos += chartHeight + 20;
            }
            <?php endif; ?>

            // Top Venues Section
            <?php if (!empty($analytics['revenue_by_venue'])): ?>
            if (yPos > pageHeight - 70) {
                addPageFooter();
                pdf.addPage();
                pageCount++;
                addPageHeader(pageCount);
                yPos = 15;
            }

            pdf.setFontSize(14);
            pdf.setTextColor(34, 197, 94);
            pdf.setFont(undefined, 'bold');
            pdf.text('Top Venues by Revenue', margin, yPos);
            yPos += 10;

            const venues = <?php echo json_encode($analytics['revenue_by_venue']); ?>;
            const maxRevenue = venues[0]?.revenue || 1;

            venues.forEach((venue, index) => {
                if (yPos > pageHeight - 25) {
                    addPageFooter();
                    pdf.addPage();
                    pageCount++;
                    addPageHeader(pageCount);
                    yPos = 15;
                }

                // Venue card with gradient bar
                const cardHeight = 18;
                pdf.setFillColor(249, 250, 251);
                pdf.setDrawColor(220, 220, 220);
                pdf.roundedRect(margin, yPos, contentWidth, cardHeight, 2, 2, 'FD');

                // Rank badge
                const badgeColors = [
                    [251, 191, 36], // Gold
                    [156, 163, 175], // Silver
                    [205, 127, 50], // Bronze
                    [34, 197, 94], // Green
                    [34, 197, 94] // Green
                ];
                const badgeColor = badgeColors[index] || [34, 197, 94];
                pdf.setFillColor(badgeColor[0], badgeColor[1], badgeColor[2]);
                pdf.circle(margin + 5, yPos + cardHeight / 2, 4, 'F');
                pdf.setFontSize(8);
                pdf.setTextColor(255, 255, 255);
                pdf.setFont(undefined, 'bold');
                pdf.text(String(index + 1), margin + 5, yPos + cardHeight / 2 + 1, {
                    align: 'center'
                });

                // Venue name
                pdf.setFontSize(10);
                pdf.setTextColor(0, 0, 0);
                pdf.setFont(undefined, 'bold');
                const venueName = venue.venue_name || 'Unknown';
                const truncatedName = venueName.length > 40 ? venueName.substring(0, 38) + '...' :
                    venueName;
                pdf.text(truncatedName, margin + 12, yPos + 6);

                // Stats
                pdf.setFontSize(8);
                pdf.setTextColor(100, 100, 100);
                pdf.setFont(undefined, 'normal');
                pdf.text(venue.booking_count + ' bookings', margin + 12, yPos + 11);
                pdf.text('₱' + parseFloat(venue.revenue || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }), margin + 12, yPos + 15);

                // Progress bar
                const barWidth = 40;
                const barHeight = 3;
                const barX = pageWidth - margin - barWidth - 5;
                const barY = yPos + cardHeight / 2 - barHeight / 2;

                pdf.setFillColor(230, 230, 230);
                pdf.roundedRect(barX, barY, barWidth, barHeight, 1, 1, 'F');

                const fillWidth = (venue.revenue / maxRevenue) * barWidth;
                pdf.setFillColor(34, 197, 94);
                pdf.roundedRect(barX, barY, fillWidth, barHeight, 1, 1, 'F');

                yPos += cardHeight + 3;
            });

            yPos += 5;
            <?php endif; ?>

            // Data Tables Section
            addPageFooter();
            pdf.addPage();
            pageCount++;
            addPageHeader(pageCount);
            yPos = 15;

            pdf.setFontSize(14);
            pdf.setTextColor(34, 197, 94);
            pdf.setFont(undefined, 'bold');
            pdf.text('Detailed Data Tables', margin, yPos);
            yPos += 12;

            // Bookings by Status Table
            <?php if (!empty($analytics['bookings_by_status'])): ?>
            pdf.setFontSize(12);
            pdf.setFont(undefined, 'bold');
            pdf.setTextColor(34, 197, 94);
            pdf.text('Bookings by Status', margin, yPos);
            yPos += 8;

            // Modern table header
            pdf.setFillColor(34, 197, 94);
            pdf.setDrawColor(34, 197, 94);
            const tableWidth = 100;
            pdf.roundedRect(margin, yPos, tableWidth, 10, 2, 2, 'FD');

            pdf.setFontSize(10);
            pdf.setTextColor(255, 255, 255);
            pdf.setFont(undefined, 'bold');
            pdf.text('Status', margin + 3, yPos + 6.5);
            pdf.text('Count', margin + 70, yPos + 6.5);
            pdf.text('%', margin + 90, yPos + 6.5, {
                align: 'right'
            });
            yPos += 10;

            // Table data with alternating colors
            pdf.setTextColor(0, 0, 0);
            pdf.setFont(undefined, 'normal');
            const statusData = <?php echo json_encode($analytics['bookings_by_status']); ?>;
            const totalBookings = <?php echo $analytics['total_bookings']; ?>;
            let rowIndex = 0;

            for (const [status, count] of Object.entries(statusData)) {
                const isEven = rowIndex % 2 === 0;
                if (isEven) {
                    pdf.setFillColor(249, 250, 251);
                    pdf.rect(margin, yPos, tableWidth, 8, 'F');
                }

                pdf.setFontSize(9);
                pdf.text(status.charAt(0).toUpperCase() + status.slice(1), margin + 3, yPos + 5.5);
                pdf.setFont(undefined, 'bold');
                pdf.text(String(count), margin + 70, yPos + 5.5);
                pdf.setFont(undefined, 'normal');
                const percentage = ((count / totalBookings) * 100).toFixed(1);
                pdf.text(percentage + '%', margin + 90, yPos + 5.5, {
                    align: 'right'
                });

                yPos += 8;
                rowIndex++;
            }

            yPos += 10;
            <?php endif; ?>

            // Event Types Table
            const eventTypes = <?php echo json_encode($analytics['event_types']); ?>;
            if (eventTypes.length > 0) {
                if (yPos > pageHeight - 60) {
                    addPageFooter();
                    pdf.addPage();
                    pageCount++;
                    addPageHeader(pageCount);
                    yPos = 15;
                }

                pdf.setFontSize(12);
                pdf.setFont(undefined, 'bold');
                pdf.setTextColor(34, 197, 94);
                pdf.text('Event Types Distribution', margin, yPos);
                yPos += 8;

                // Table header
                pdf.setFillColor(34, 197, 94);
                const eventTableWidth = 140;
                pdf.roundedRect(margin, yPos, eventTableWidth, 10, 2, 2, 'FD');

                pdf.setFontSize(10);
                pdf.setTextColor(255, 255, 255);
                pdf.text('Event Type', margin + 3, yPos + 6.5);
                pdf.text('Count', margin + 90, yPos + 6.5);
                pdf.text('Revenue', margin + 110, yPos + 6.5);
                yPos += 10;

                // Table data
                pdf.setTextColor(0, 0, 0);
                pdf.setFont(undefined, 'normal');
                let eventRowIndex = 0;

                eventTypes.forEach(type => {
                    if (yPos > pageHeight - 25) {
                        addPageFooter();
                        pdf.addPage();
                        pageCount++;
                        addPageHeader(pageCount);
                        yPos = 15;
                    }

                    const isEven = eventRowIndex % 2 === 0;
                    if (isEven) {
                        pdf.setFillColor(249, 250, 251);
                        pdf.rect(margin, yPos, eventTableWidth, 8, 'F');
                    }

                    pdf.setFontSize(9);
                    const eventType = (type.event_type || 'Unknown').substring(0, 25);
                    pdf.text(eventType, margin + 3, yPos + 5.5);
                    pdf.setFont(undefined, 'bold');
                    pdf.text(String(type.count), margin + 90, yPos + 5.5);
                    pdf.setFont(undefined, 'normal');
                    pdf.text('₱' + parseFloat(type.revenue).toLocaleString('en-PH', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }), margin + 110, yPos + 5.5);

                    yPos += 8;
                    eventRowIndex++;
                });
            }

            // Final footer
            addPageFooter();

            // Final footer
            addPageFooter();

            // Save and open PDF
            const pdfBlob = pdf.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            window.open(pdfUrl, '_blank');

            document.getElementById('pdfLoading')?.remove();

        } catch (error) {
            console.error('Error generating PDF:', error);
            document.getElementById('pdfLoading')?.remove();
            alert('Error generating PDF: ' + error.message);
        }
    }
    </script>
    <script>
    // Filter toggle
    document.getElementById('filterType').addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        document.getElementById('startDateDiv').style.display = isCustom ? 'block' : 'none';
        document.getElementById('endDateDiv').style.display = isCustom ? 'block' : 'none';
    });

    // Chart.js Configuration
    Chart.defaults.font.family = 'Montserrat, sans-serif';
    Chart.defaults.color = '#6B7280';

    // Status Chart
    <?php if (!empty($analytics['bookings_by_status'])): ?>
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map('ucfirst', array_keys($analytics['bookings_by_status']))); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($analytics['bookings_by_status'])); ?>,
                backgroundColor: ['#FCD34D', '#34D399', '#60A5FA', '#EF4444'],
                borderColor: ['#F59E0B', '#10B981', '#3B82F6', '#DC2626'],
                borderWidth: 2
            }]
        },
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
                }
            }
        }
    });
    <?php endif; ?>

    // Revenue Chart
    <?php if (!empty($analytics['monthly_revenue'])): ?>
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = <?php echo json_encode($analytics['monthly_revenue']); ?>;
    const revenueLabels = Object.keys(revenueData).map(m => {
        const [year, month] = m.split('-');
        return new Date(year, month - 1).toLocaleDateString('en-US', {
            month: 'short'
        }) + " '" + year.slice(2);
    });

    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Revenue (₱)',
                data: Object.values(revenueData),
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 7
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
                        label: (context) => '₱' + context.parsed.y.toLocaleString('en-PH', {
                            minimumFractionDigits: 2
                        })
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => '₱' + value.toLocaleString('en-PH')
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Event Types Chart
    <?php if (!empty($analytics['event_types'])): ?>
    const eventTypesCtx = document.getElementById('eventTypesChart').getContext('2d');
    const eventTypesData = <?php echo json_encode($analytics['event_types']); ?>;
    new Chart(eventTypesCtx, {
        type: 'bar',
        data: {
            labels: eventTypesData.map(e => e.event_type || 'Unknown'),
            datasets: [{
                label: 'Bookings',
                data: eventTypesData.map(e => e.count),
                backgroundColor: '#34D399',
                borderColor: '#10B981',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
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
    <?php endif; ?>
    </script>
</body>

</html>