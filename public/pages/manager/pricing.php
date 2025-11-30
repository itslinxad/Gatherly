<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';
require_once '../../../src/services/ml/PricingOptimizer.php';

$first_name = $_SESSION['first_name'] ?? 'Manager';
$user_id = $_SESSION['user_id'];
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

$success_message = '';
$error_message = '';

// Pagination and filter settings
$items_per_page = 10;

// Better page number extraction with validation - using unique variable name
$page_param = isset($_GET['page']) ? $_GET['page'] : 1;
// Only accept numeric values, otherwise default to 1
if (is_numeric($page_param)) {
    $pagination_current_page = max(1, intval($page_param));
} else {
    $pagination_current_page = 1;
}

$offset = ($pagination_current_page - 1) * $items_per_page;

// Debug log
error_log("Pricing Page - Raw page param: '" . (isset($_GET['page']) ? $_GET['page'] : 'NOT SET') . "', pagination_current_page: " . $pagination_current_page . " (type: " . gettype($pagination_current_page) . ")");

// Search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'venue_name';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Handle base price update (ML will auto-calculate other prices)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_base_price'])) {
    $venue_id = intval($_POST['venue_id']);
    $new_base_price = floatval($_POST['base_price']);

    if ($venue_id > 0 && $new_base_price > 0) {
        // Verify venue ownership
        $stmt = $conn->prepare("SELECT venue_id FROM venues WHERE venue_id = ? AND manager_id = ?");
        $stmt->bind_param("ii", $venue_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            try {
                // Initialize ML pricing optimizer
                $optimizer = new PricingOptimizer($conn, $venue_id, $new_base_price);
                $calculated_prices = $optimizer->calculateOptimizedPrices();

                // Check if price record exists
                $check_stmt = $conn->prepare("SELECT price_id FROM prices WHERE venue_id = ?");
                $check_stmt->bind_param("i", $venue_id);
                $check_stmt->execute();
                $price_exists = $check_stmt->get_result()->num_rows > 0;

                if ($price_exists) {
                    // Update existing prices
                    $update_stmt = $conn->prepare("UPDATE prices SET 
                                                   base_price = ?,
                                                   peak_price = ?,
                                                   offpeak_price = ?,
                                                   weekday_price = ?,
                                                   weekend_price = ?,
                                                   ml_demand_score = ?,
                                                   ml_competitive_position = ?,
                                                   ml_seasonality_high = ?,
                                                   ml_seasonality_low = ?,
                                                   ml_performance_score = ?,
                                                   ml_last_calculated = NOW()
                                                   WHERE venue_id = ?");
                    $update_stmt->bind_param(
                        "ddddddddddi",
                        $new_base_price,
                        $calculated_prices['peak_price'],
                        $calculated_prices['offpeak_price'],
                        $calculated_prices['weekday_price'],
                        $calculated_prices['weekend_price'],
                        $calculated_prices['ml_metadata']['demand_score'],
                        $calculated_prices['ml_metadata']['competitive_position'],
                        $calculated_prices['ml_metadata']['seasonality_high'],
                        $calculated_prices['ml_metadata']['seasonality_low'],
                        $calculated_prices['ml_metadata']['performance_metric'],
                        $venue_id
                    );
                    $update_stmt->execute();
                } else {
                    // Insert new price record
                    $insert_stmt = $conn->prepare("INSERT INTO prices 
                                                   (venue_id, base_price, peak_price, offpeak_price, weekday_price, weekend_price,
                                                    ml_demand_score, ml_competitive_position, ml_seasonality_high, ml_seasonality_low,
                                                    ml_performance_score, ml_last_calculated)
                                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $insert_stmt->bind_param(
                        "idddddddddd",
                        $venue_id,
                        $new_base_price,
                        $calculated_prices['peak_price'],
                        $calculated_prices['offpeak_price'],
                        $calculated_prices['weekday_price'],
                        $calculated_prices['weekend_price'],
                        $calculated_prices['ml_metadata']['demand_score'],
                        $calculated_prices['ml_metadata']['competitive_position'],
                        $calculated_prices['ml_metadata']['seasonality_high'],
                        $calculated_prices['ml_metadata']['seasonality_low'],
                        $calculated_prices['ml_metadata']['performance_metric']
                    );
                    $insert_stmt->execute();
                }

                // Log to history
                $history_stmt = $conn->prepare("INSERT INTO pricing_ml_history 
                                               (venue_id, base_price, calculated_peak_price, calculated_offpeak_price,
                                                calculated_weekday_price, calculated_weekend_price, ml_demand_score,
                                                ml_competitive_position, ml_seasonality_high, ml_seasonality_low,
                                                ml_performance_score, calculation_type)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual_trigger')");
                $history_stmt->bind_param(
                    "idddddddddd",
                    $venue_id,
                    $new_base_price,
                    $calculated_prices['peak_price'],
                    $calculated_prices['offpeak_price'],
                    $calculated_prices['weekday_price'],
                    $calculated_prices['weekend_price'],
                    $calculated_prices['ml_metadata']['demand_score'],
                    $calculated_prices['ml_metadata']['competitive_position'],
                    $calculated_prices['ml_metadata']['seasonality_high'],
                    $calculated_prices['ml_metadata']['seasonality_low'],
                    $calculated_prices['ml_metadata']['performance_metric']
                );
                $history_stmt->execute();

                $success_message = "Base price updated! AI has optimized other prices based on market analysis.";
            } catch (Exception $e) {
                $error_message = "Failed to calculate optimized prices: " . $e->getMessage();
            }
        } else {
            $error_message = "Venue not found or access denied.";
        }
    } else {
        $error_message = "Invalid venue or price value.";
    }
}

// Build WHERE clause for search and filters
$where_conditions = ["v.manager_id = ?"];
$params = [$user_id];
$param_types = "i";

if (!empty($search_query)) {
    $where_conditions[] = "(v.venue_name LIKE ? OR l.city LIKE ? OR l.province LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "v.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Validate sort column
$allowed_sort_columns = ['venue_name', 'base_price', 'capacity', 'total_bookings', 'total_revenue'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'venue_name';
}

// Count total records for pagination
$count_query = "SELECT COUNT(DISTINCT v.venue_id) as total
                FROM venues v
                LEFT JOIN locations l ON v.location_id = l.location_id
                WHERE {$where_clause}";

$count_stmt = $conn->prepare($count_query);
if (count($params) > 0) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = intval($count_stmt->get_result()->fetch_assoc()['total']);
$total_pages = $total_records > 0 ? ceil($total_records / $items_per_page) : 1;

// Fetch venues with their pricing data
$venues_query = "SELECT 
    v.venue_id, v.venue_name, v.capacity, v.status as venue_status,
    l.city, l.province,
    p.base_price, p.peak_price, p.offpeak_price, p.weekday_price, p.weekend_price,
    p.ml_demand_score, p.ml_competitive_position, p.ml_seasonality_high, p.ml_seasonality_low,
    p.ml_performance_score, p.ml_last_calculated,
    COUNT(DISTINCT e.event_id) as total_bookings,
    SUM(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost ELSE 0 END) as total_revenue,
    AVG(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost END) as avg_booking_value
FROM venues v
LEFT JOIN locations l ON v.location_id = l.location_id
LEFT JOIN prices p ON v.venue_id = p.venue_id
LEFT JOIN events e ON v.venue_id = e.venue_id AND e.event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
WHERE {$where_clause}
GROUP BY v.venue_id, v.venue_name, v.capacity, v.status, l.city, l.province, p.base_price, p.peak_price, p.offpeak_price, p.weekday_price, p.weekend_price, p.ml_demand_score, p.ml_competitive_position, p.ml_seasonality_high, p.ml_seasonality_low, p.ml_performance_score, p.ml_last_calculated
ORDER BY {$sort_by} {$sort_order}
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($venues_query);
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$venues_result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Pricing Optimization | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
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

<body class="bg-gradient-to-br from-green-50 via-white to-teal-50 font-['Montserrat']">

    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <div class="<?php echo $nav_layout === 'sidebar' ? 'md:ml-64' : ''; ?> transition-all duration-300">
        <?php if ($nav_layout === 'sidebar'): ?>
        <div class="min-h-screen">
            <?php endif; ?>

            <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-robot text-green-600 mr-3"></i>
                            AI Pricing Optimization
                        </h1>
                        <p class="text-sm text-gray-600 mt-1">Machine learning powered dynamic pricing for maximum
                            revenue</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <div class="bg-green-50 border border-green-200 px-3 py-1 rounded-full">
                            <i class="fas fa-brain text-green-600 mr-1"></i>
                            <span class="text-green-700 font-semibold">ML Active</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                <!-- Header for Navbar Layout -->
                <?php endif; ?>

                <!-- Main Content -->
                <div class="<?php echo $nav_layout !== 'sidebar' ? 'max-w-7xl mx-auto px-4 py-8' : ''; ?>">
                    <?php if ($nav_layout !== 'sidebar'): ?>
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-robot text-green-600 mr-3"></i>
                            AI Pricing Optimization
                        </h1>
                        <p class="text-gray-600">Machine learning powered dynamic pricing for maximum revenue</p>
                    </div>
                    <?php endif; ?>

                    <!-- Success/Error Messages -->
                    <?php if ($success_message): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span class="text-green-700"><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                            <span class="text-red-700"><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- How AI Pricing Works -->
                    <div class="bg-gradient-to-r from-green-50 to-teal-50 border border-green-200 rounded-2xl p-6 mb-8">
                        <div class="flex items-start gap-4">
                            <div class="bg-green-600 text-white p-3 rounded-xl">
                                <i class="fas fa-lightbulb text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-green-900 mb-2">How AI Pricing Optimization Works
                                </h3>
                                <p class="text-green-700 mb-4">Our machine learning algorithms analyze multiple factors
                                    to determine the optimal pricing for your venues:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="bg-white rounded-lg p-4 border border-indigo-100">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-chart-line text-green-600"></i>
                                            <h4 class="font-semibold text-green-900">Demand Forecasting</h4>
                                        </div>
                                        <p class="text-sm text-gray-600">Analyzes booking patterns and trends to predict
                                            future demand</p>
                                    </div>
                                    <div class="bg-white rounded-lg p-4 border border-indigo-100">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-users text-teal-600"></i>
                                            <h4 class="font-semibold text-purple-900">Competitive Analysis</h4>
                                        </div>
                                        <p class="text-sm text-gray-600">Compares your pricing with similar venues in
                                            your market</p>
                                    </div>
                                    <div class="bg-white rounded-lg p-4 border border-indigo-100">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-calendar-alt text-green-600"></i>
                                            <h4 class="font-semibold text-green-900">Seasonality Patterns</h4>
                                        </div>
                                        <p class="text-sm text-gray-600">Identifies peak and off-peak periods for
                                            dynamic pricing</p>
                                    </div>
                                    <div class="bg-white rounded-lg p-4 border border-indigo-100">
                                        <div class="flex items-center gap-2 mb-2">
                                            <i class="fas fa-trophy text-orange-600"></i>
                                            <h4 class="font-semibold text-orange-900">Performance Metrics</h4>
                                        </div>
                                        <p class="text-sm text-gray-600">Evaluates venue performance to optimize pricing
                                            strategy</p>
                                    </div>
                                </div>
                                <div class="mt-4 p-3 bg-green-100 rounded-lg">
                                    <p class="text-sm text-green-800">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <strong>Just set your base price</strong> - AI automatically calculates peak,
                                        off-peak, weekday, and weekend prices for maximum revenue.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Venues Pricing Table -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">
                                        <i class="fas fa-building text-green-600 mr-2"></i>
                                        Venue Pricing Management
                                    </h2>
                                    <p class="text-gray-600">Set base prices and let AI optimize your revenue</p>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Showing
                                    <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $venues_result->num_rows, $total_records); ?>
                                    of <?php echo $total_records; ?> venues
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter Bar -->
                        <div class="p-6 bg-gray-50 border-b border-gray-200">
                            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <!-- Search -->
                                <div class="md:col-span-2">
                                    <div class="relative">
                                        <i
                                            class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        <input type="text" name="search"
                                            value="<?php echo htmlspecialchars($search_query); ?>"
                                            placeholder="Search by venue name, city, or province..."
                                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>
                                </div>

                                <!-- Status Filter -->
                                <div>
                                    <select name="status"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>
                                            All Status</option>
                                        <option value="active"
                                            <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive"
                                            <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive
                                        </option>
                                    </select>
                                </div>

                                <!-- Sort By -->
                                <div>
                                    <select name="sort"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        <option value="venue_name"
                                            <?php echo $sort_by === 'venue_name' ? 'selected' : ''; ?>>Sort: Name
                                        </option>
                                        <option value="base_price"
                                            <?php echo $sort_by === 'base_price' ? 'selected' : ''; ?>>Sort: Price
                                        </option>
                                        <option value="capacity"
                                            <?php echo $sort_by === 'capacity' ? 'selected' : ''; ?>>Sort: Capacity
                                        </option>
                                        <option value="total_bookings"
                                            <?php echo $sort_by === 'total_bookings' ? 'selected' : ''; ?>>Sort:
                                            Bookings</option>
                                        <option value="total_revenue"
                                            <?php echo $sort_by === 'total_revenue' ? 'selected' : ''; ?>>Sort: Revenue
                                        </option>
                                    </select>
                                </div>

                                <!-- Hidden field for sort order -->
                                <input type="hidden" name="order"
                                    value="<?php echo $sort_order === 'DESC' ? 'asc' : 'desc'; ?>">

                                <!-- Action Buttons -->
                                <div class="md:col-span-4 flex gap-2">
                                    <button type="submit"
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="fas fa-filter mr-2"></i>Apply Filters
                                    </button>
                                    <a href="pricing.php"
                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        <i class="fas fa-redo mr-2"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Venue</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Base Price</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Optimized Prices</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            ML Scores</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Performance</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($venues_result && $venues_result->num_rows > 0): ?>
                                    <?php while ($venue = $venues_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($venue['venue_name']); ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                                    <?php echo htmlspecialchars($venue['city']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-users mr-1"></i>
                                                    <?php echo number_format($venue['capacity']); ?> capacity
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-2xl font-bold text-green-600">
                                                ₱<?php echo number_format($venue['base_price'] ?? 0, 2); ?>
                                            </div>
                                            <?php if ($venue['ml_last_calculated']): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-clock mr-1"></i>
                                                Updated
                                                <?php echo date('M d, Y', strtotime($venue['ml_last_calculated'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1 text-sm">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-600">Peak:</span>
                                                    <span
                                                        class="font-semibold text-orange-600">₱<?php echo number_format($venue['peak_price'] ?? 0, 0); ?></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-600">Weekend:</span>
                                                    <span
                                                        class="font-semibold text-teal-600">₱<?php echo number_format($venue['weekend_price'] ?? 0, 0); ?></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-600">Weekday:</span>
                                                    <span
                                                        class="font-semibold text-green-600">₱<?php echo number_format($venue['weekday_price'] ?? 0, 0); ?></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-600">Off-peak:</span>
                                                    <span
                                                        class="font-semibold text-blue-600">₱<?php echo number_format($venue['offpeak_price'] ?? 0, 0); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($venue['ml_demand_score']): ?>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="text-xs text-gray-600 mb-1">Demand</div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                            <div class="bg-green-600 h-2 rounded-full"
                                                                style="width: <?php echo round($venue['ml_demand_score'] * 100); ?>%">
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="text-xs font-semibold"><?php echo round($venue['ml_demand_score'] * 100); ?>%</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-600 mb-1">Competitive</div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                            <div class="bg-teal-600 h-2 rounded-full"
                                                                style="width: <?php echo round($venue['ml_competitive_position'] * 100); ?>%">
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="text-xs font-semibold"><?php echo round($venue['ml_competitive_position'] * 100); ?>%</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-sm text-gray-400 italic">Not calculated</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-calendar-check text-green-600"></i>
                                                    <span class="text-gray-700"><?php echo $venue['total_bookings']; ?>
                                                        bookings</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-money-bill-wave text-blue-600"></i>
                                                    <span
                                                        class="text-gray-700">₱<?php echo number_format($venue['total_revenue'] ?? 0, 0); ?>
                                                        revenue</span>
                                                </div>
                                                <?php if ($venue['avg_booking_value']): ?>
                                                <div class="text-xs text-gray-500">
                                                    Avg: ₱<?php echo number_format($venue['avg_booking_value'], 0); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button
                                                onclick="openPricingModal(<?php echo $venue['venue_id']; ?>, '<?php echo addslashes($venue['venue_name']); ?>', <?php echo $venue['base_price'] ?? 0; ?>)"
                                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                                                <i class="fas fa-edit mr-2"></i>
                                                Update Price
                                            </button>
                                            <a href="pricing-insights.php?venue_id=<?php echo $venue['venue_id']; ?>"
                                                class="inline-flex items-center px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors text-sm font-medium mt-2">
                                                <i class="fas fa-chart-line mr-2"></i>
                                                View Insights
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="text-gray-400">
                                                <i class="fas fa-building text-5xl mb-3"></i>
                                                <p class="text-lg">No venues found</p>
                                                <p class="text-sm">Add a venue to start using AI pricing optimization
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php
                        // Debug output
                        echo "<!-- DEBUG: pagination_current_page=" . var_export($pagination_current_page, true) . ", total_pages=" . var_export($total_pages, true) . ", total_records=" . var_export($total_records, true) . ", offset=" . var_export($offset, true) . " -->";
                        ?>
                        <?php if ($total_pages > 1): ?>
                        <?php
                            // Build query string for pagination
                            $query_params = [];
                            if (!empty($search_query)) {
                                $query_params[] = 'search=' . urlencode($search_query);
                            }
                            if ($status_filter !== 'all') {
                                $query_params[] = 'status=' . urlencode($status_filter);
                            }
                            $query_params[] = 'sort=' . urlencode($sort_by);
                            $query_params[] = 'order=' . urlencode(strtolower($sort_order));
                            $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                            ?>
                        <div class="p-6 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-600">
                                    Page <?php echo $pagination_current_page; ?> of <?php echo $total_pages; ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <!-- First Page -->
                                    <?php if ($pagination_current_page > 1): ?>
                                    <a href="?page=1<?php echo $query_string; ?>"
                                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                        <i class="fas fa-angle-double-left"></i>
                                    </span>
                                    <?php endif; ?>

                                    <!-- Previous Page -->
                                    <?php if ($pagination_current_page > 1): ?>
                                    <a href="?page=<?php echo ($pagination_current_page - 1); ?><?php echo $query_string; ?>"
                                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        <i class="fas fa-angle-left"></i> Previous
                                    </a>
                                    <?php else: ?>
                                    <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                        <i class="fas fa-angle-left"></i> Previous
                                    </span>
                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <div class="flex items-center gap-1">
                                        <?php
                                            $start_page = max(1, $pagination_current_page - 2);
                                            $end_page = min($total_pages, $pagination_current_page + 2);

                                            for ($i = $start_page; $i <= $end_page; $i++):
                                            ?>
                                        <?php if ($i == $pagination_current_page): ?>
                                        <span class="px-3 py-2 bg-green-600 text-white rounded-lg font-semibold">
                                            <?php echo $i; ?>
                                        </span>
                                        <?php else: ?>
                                        <a href="?page=<?php echo $i; ?><?php echo $query_string; ?>"
                                            class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                            <?php echo $i; ?>
                                        </a>
                                        <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>

                                    <!-- Next Page -->
                                    <?php if ($pagination_current_page < $total_pages): ?>
                                    <a href="?page=<?php echo ($pagination_current_page + 1); ?><?php echo $query_string; ?>"
                                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        Next <i class="fas fa-angle-right"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                        Next <i class="fas fa-angle-right"></i>
                                    </span>
                                    <?php endif; ?>

                                    <!-- Last Page -->
                                    <?php if ($pagination_current_page < $total_pages): ?>
                                    <a href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>"
                                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                        <i class="fas fa-angle-double-right"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($nav_layout === 'sidebar'): ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($nav_layout === 'sidebar'): ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pricing Modal -->
    <div id="pricingModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-robot text-green-600 mr-2"></i>
                        Update Base Price
                    </h3>
                    <button onclick="closePricingModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mt-1">AI will automatically optimize other prices</p>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="venue_id" id="modal_venue_id">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Venue</label>
                    <div class="px-4 py-2 bg-gray-50 rounded-lg text-gray-900 font-medium" id="modal_venue_name"></div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Base Price (₱)</label>
                    <input type="number" name="base_price" id="modal_base_price" step="0.01" min="0" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-lg font-semibold">
                    <p class="text-xs text-gray-500 mt-1">This is your standard pricing before any adjustments</p>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-brain text-green-600 mt-1"></i>
                        <div>
                            <p class="text-sm font-semibold text-green-900 mb-1">AI Will Calculate:</p>
                            <ul class="text-sm text-green-700 space-y-1">
                                <li><i class="fas fa-check mr-1"></i> Peak Season Price (high demand periods)</li>
                                <li><i class="fas fa-check mr-1"></i> Off-Peak Price (low demand periods)</li>
                                <li><i class="fas fa-check mr-1"></i> Weekend Price (premium days)</li>
                                <li><i class="fas fa-check mr-1"></i> Weekday Price (standard days)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closePricingModal()"
                        class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        Cancel
                    </button>
                    <button type="submit" name="update_base_price"
                        class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium">
                        <i class="fas fa-magic mr-2"></i>
                        Calculate with AI
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Insights Modal -->
    <div id="insightsModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-chart-line text-teal-600 mr-2"></i>
                        ML Pricing Insights
                    </h3>
                    <button onclick="closeInsightsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="insightsContent" class="p-6">
                <div class="flex items-center justify-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-green-600"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openPricingModal(venueId, venueName, basePrice) {
        document.getElementById('modal_venue_id').value = venueId;
        document.getElementById('modal_venue_name').textContent = venueName;
        document.getElementById('modal_base_price').value = basePrice;
        document.getElementById('pricingModal').classList.remove('hidden');
    }

    function closePricingModal() {
        document.getElementById('pricingModal').classList.add('hidden');
    }

    async function viewInsights(venueId) {
        document.getElementById('insightsModal').classList.remove('hidden');

        try {
            const response = await fetch(`../../../src/services/ml/calculate-pricing.php?venue_id=${venueId}`);
            const data = await response.json();

            if (data.success) {
                displayInsights(data);
            } else {
                console.error('Insights error:', data);
                document.getElementById('insightsContent').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
                            <p class="font-semibold mb-2">${data.error || 'Failed to load insights'}</p>
                            <p class="text-sm">${data.message || ''}</p>
                        </div>
                    `;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            document.getElementById('insightsContent').innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
                        <p class="font-semibold mb-2">Failed to load insights</p>
                        <p class="text-sm">Please check the browser console for details</p>
                    </div>
                `;
        }
    }

    function displayInsights(data) {
        const insights = data.insights;
        const html = `
                <div class="space-y-6">
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                        <h4 class="font-bold text-indigo-900 mb-2">${data.venue_name}</h4>
                        <p class="text-green-700">Current Base Price: <strong>₱${data.current_base_price.toLocaleString()}</strong></p>
                    </div>

                    <!-- AI Calculation Explanation -->
                    ${insights.price_calculation_explanation ? `
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-indigo-300 rounded-lg p-5">
                        <div class="flex items-start gap-3 mb-3">
                            <i class="fas fa-brain text-green-600 text-2xl mt-1"></i>
                            <div class="flex-1">
                                <h4 class="font-bold text-indigo-900 text-lg mb-2">How AI Calculates Your Optimal Prices</h4>
                                <p class="text-gray-700 text-sm mb-3">${insights.price_calculation_explanation.explanation}</p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div class="bg-white rounded-lg p-3 border border-indigo-200">
                                        <div class="text-xs text-gray-600 mb-1">Demand Analysis</div>
                                        <div class="font-bold text-green-600">${insights.price_calculation_explanation.factors.demand.weight}</div>
                                        <div class="text-xs text-gray-500">Score: ${insights.price_calculation_explanation.factors.demand.score}</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-purple-200">
                                        <div class="text-xs text-gray-600 mb-1">Competition</div>
                                        <div class="font-bold text-teal-600">${insights.price_calculation_explanation.factors.competition.weight}</div>
                                        <div class="text-xs text-gray-500">Score: ${insights.price_calculation_explanation.factors.competition.score}</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-orange-200">
                                        <div class="text-xs text-gray-600 mb-1">Seasonality</div>
                                        <div class="font-bold text-orange-600">${insights.price_calculation_explanation.factors.seasonality.weight}</div>
                                        <div class="text-xs text-gray-500">${insights.price_calculation_explanation.factors.seasonality.multiplier_range}</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-green-200">
                                        <div class="text-xs text-gray-600 mb-1">Performance</div>
                                        <div class="font-bold text-green-600">${insights.price_calculation_explanation.factors.performance.weight}</div>
                                        <div class="text-xs text-gray-500">Score: ${insights.price_calculation_explanation.factors.performance.score}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ` : ''}

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Demand Forecast with Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 md:col-span-2">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-chart-line text-green-600 text-xl"></i>
                                <h5 class="font-bold text-gray-900">Demand Forecast - Last 12 Months</h5>
                            </div>
                            <div class="mb-4">
                                <canvas id="demandChart" height="80"></canvas>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm text-gray-600">Current Demand Score</span>
                                        <span class="font-bold text-green-600">${insights.demand_forecast.score}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: ${insights.demand_forecast.score}%"></div>
                                    </div>
                                </div>
                                <div class="text-sm">
                                    <p class="text-gray-600 mb-1">Trend: <strong class="text-${insights.demand_forecast.trend === 'increasing' ? 'green' : insights.demand_forecast.trend === 'decreasing' ? 'red' : 'gray'}-600">${insights.demand_forecast.trend}</strong></p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="text-gray-700 bg-gray-50 p-2 rounded text-sm">${insights.demand_forecast.recommendation}</p>
                            </div>
                        </div>

                        <!-- Competitive Analysis -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-users text-teal-600 text-xl"></i>
                                <h5 class="font-bold text-gray-900">Competitive Analysis</h5>
                            </div>
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm text-gray-600">Market Position</span>
                                    <span class="font-bold text-teal-600">${insights.competitive_analysis.position}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-teal-600 h-2 rounded-full" style="width: ${insights.competitive_analysis.position}%"></div>
                                </div>
                            </div>
                            <div class="text-sm">
                                <p class="text-gray-600 mb-1">Status: <strong class="text-${insights.competitive_analysis.status === 'premium' ? 'green' : insights.competitive_analysis.status === 'value' ? 'blue' : 'gray'}-600">${insights.competitive_analysis.status}</strong></p>
                                <p class="text-gray-700 bg-gray-50 p-2 rounded">${insights.competitive_analysis.recommendation}</p>
                            </div>
                        </div>

                        <!-- Seasonality -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
                                <h5 class="font-bold text-gray-900">Seasonality</h5>
                            </div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Peak Multiplier:</span>
                                    <span class="font-bold text-orange-600">${insights.seasonality.peak_multiplier}x</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Off-Peak Multiplier:</span>
                                    <span class="font-bold text-blue-600">${insights.seasonality.offpeak_multiplier}x</span>
                                </div>
                                <p class="text-gray-700 bg-gray-50 p-2 rounded mt-2">${insights.seasonality.recommendation}</p>
                            </div>
                        </div>

                        <!-- Performance -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 md:col-span-2">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-trophy text-green-600 text-xl"></i>
                                <h5 class="font-bold text-gray-900">Performance</h5>
                            </div>
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm text-gray-600">Performance Score</span>
                                    <span class="font-bold text-green-600">${insights.performance.score}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: ${insights.performance.score}%"></div>
                                </div>
                            </div>
                            <div class="text-sm">
                                <p class="text-gray-600 mb-1">Rating: <strong class="text-green-600">${insights.performance.rating}</strong></p>
                                <p class="text-gray-700 bg-gray-50 p-2 rounded">${insights.performance.recommendation}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

        document.getElementById('insightsContent').innerHTML = html;

        // Create demand chart
        if (insights.demand_forecast.history && insights.demand_forecast.history.labels.length > 0) {
            setTimeout(() => {
                const ctx = document.getElementById('demandChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: insights.demand_forecast.history.labels,
                        datasets: [{
                            label: 'Bookings per Month',
                            data: insights.demand_forecast.history.data,
                            borderColor: 'rgb(79, 70, 229)',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Bookings: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Bookings'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Month'
                                }
                            }
                        }
                    }
                });
            }, 100);
        }
    }

    function closeInsightsModal() {
        document.getElementById('insightsModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.id === 'pricingModal') {
            closePricingModal();
        }
        if (event.target.id === 'insightsModal') {
            closeInsightsModal();
        }
    });
    </script>

</body>

</html>