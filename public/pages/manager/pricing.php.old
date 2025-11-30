<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Manager';
$user_id = $_SESSION['user_id'];
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

// Handle package creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_premium_package'])) {
        $venue_id = intval($_POST['venue_id']);
        $package_name = $conn->real_escape_string($_POST['package_name']);
        $package_price = floatval($_POST['package_price']);
        $package_description = $conn->real_escape_string($_POST['package_description']);

        // Create premium package (you might need to create this table)
        $stmt = $conn->prepare("INSERT INTO premium_packages 
            (venue_id, package_name, package_price, package_description, created_at) 
            VALUES (?, ?, ?, ?, NOW())");

        if ($stmt) {
            $stmt->bind_param("isds", $venue_id, $package_name, $package_price, $package_description);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Premium package created successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to create premium package.";
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing package creation.";
        }

        header("Location: pricing.php");
        exit();
    }
}

// Fetch all data needed for the pricing page
$venues_query = "SELECT 
    v.venue_id, v.venue_name, v.location, v.capacity,
    v.base_price, v.price_percentage, v.peak_price, 
    v.offpeak_price, v.weekday_price, v.weekend_price,
    COUNT(e.event_id) as total_bookings,
    COALESCE(SUM(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost ELSE 0 END), 0) as total_revenue,
    COALESCE(AVG(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost ELSE NULL END), 0) as actual_avg_price,
    COUNT(DISTINCT va.amenity_id) as amenity_count
FROM venues v
LEFT JOIN events e ON v.venue_id = e.venue_id
LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
GROUP BY v.venue_id
ORDER BY v.venue_name";

$venues_result = $conn->query($venues_query);

// Fetch seasonal data for pricing guide
$seasonal_query = "
    SELECT 
        MONTH(event_date) as month,
        COUNT(*) as booking_count,
        AVG(total_cost) as avg_price,
        AVG(expected_guests) as avg_guests
    FROM events 
    WHERE status IN ('confirmed', 'completed')
    AND event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY MONTH(event_date)
    ORDER BY month
";

$seasonal_result = $conn->query($seasonal_query);
$seasonal_data = [];
while ($row = $seasonal_result->fetch_assoc()) {
    $seasonal_data[$row['month']] = $row;
}

// Fetch peak hours data
$peak_hours_query = "
    SELECT 
        HOUR(event_date) as hour,
        COUNT(*) as booking_count,
        AVG(total_cost) as avg_price
    FROM events 
    WHERE status IN ('confirmed', 'completed')
    GROUP BY HOUR(event_date)
    HAVING booking_count > 0
    ORDER BY booking_count DESC
    LIMIT 6
";

$peak_hours_result = $conn->query($peak_hours_query);

// Fetch price elasticity data
$elasticity_query = "
    SELECT 
        CASE 
            WHEN total_cost < 30000 THEN 'Budget (<30k)'
            WHEN total_cost BETWEEN 30000 AND 60000 THEN 'Mid-range (30k-60k)'
            ELSE 'Premium (>60k)'
        END as price_range,
        COUNT(*) as bookings,
        AVG(expected_guests) as avg_guests,
        AVG(DATEDIFF(event_date, created_at)) as avg_lead_time
    FROM events 
    WHERE status IN ('confirmed', 'completed')
    GROUP BY price_range
    ORDER BY bookings DESC
";

$elasticity_result = $conn->query($elasticity_query);

// Fetch package bundling opportunities (venues with multiple amenities)
$package_query = "
    SELECT 
        v.venue_id,
        v.venue_name,
        v.base_price,
        COUNT(DISTINCT va.amenity_id) as amenity_count,
        COALESCE(AVG(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost ELSE NULL END), 0) as actual_avg_price,
        GROUP_CONCAT(DISTINCT a.amenity_name SEPARATOR ', ') as amenities_list
    FROM venues v
    LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
    LEFT JOIN amenities a ON va.amenity_id = a.amenity_id
    LEFT JOIN events e ON v.venue_id = e.venue_id
    GROUP BY v.venue_id
    HAVING amenity_count >= 3
    ORDER BY actual_avg_price DESC
";

$package_result = $conn->query($package_query);

// Calculate seasonal patterns for the guide
$peak_months = [];
$low_months = [];
$regular_months = [];

if (!empty($seasonal_data)) {
    $avg_bookings = array_sum(array_column($seasonal_data, 'booking_count')) / count($seasonal_data);
    foreach ($seasonal_data as $month => $data) {
        if ($data['booking_count'] > $avg_bookings * 1.2) {
            $peak_months[] = $month;
        } elseif ($data['booking_count'] < $avg_bookings * 0.8) {
            $low_months[] = $month;
        } else {
            $regular_months[] = $month;
        }
    }
}

// Month names for display
$month_names = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Intelligence | Gatherly</title>
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
</head>

<body class="bg-gray-50 font-['Montserrat']">

    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <div class="<?php echo $nav_layout === 'sidebar' ? 'md:ml-64' : ''; ?> transition-all duration-300">
        <?php if ($nav_layout === 'sidebar'): ?>
            <div class="min-h-screen">
            <?php endif; ?>

            <?php if ($nav_layout === 'sidebar'): ?>
                <!-- Top Bar for Sidebar Layout -->
                <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Pricing Intelligence Hub</h1>
                    <p class="text-sm text-gray-600">Data-driven pricing strategies based on your actual booking patterns
                    </p>
                </div>
                <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                    <!-- Header for Navbar Layout -->
                <?php endif; ?>


                <!-- Main Content -->
                <div class="<?php echo $nav_layout !== 'sidebar' ? 'max-w-7xl mx-auto px-4 py-8' : ''; ?>">
                    <?php if ($nav_layout !== 'sidebar'): ?>
                        <!-- Header -->
                        <div class="mb-8">
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Pricing Intelligence Hub</h1>
                            <p class="text-gray-600">Data-driven pricing strategies based on your actual booking patterns
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span class="text-green-700"><?php echo $_SESSION['success_message'];
                                                                unset($_SESSION['success_message']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                <span class="text-red-700"><?php echo $_SESSION['error_message'];
                                                            unset($_SESSION['error_message']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Feature 1: Seasonal Pricing Guide -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">
                                <i class="fas fa-calendar-alt text-orange-500 mr-2"></i>
                                Seasonal Pricing Guide
                            </h2>
                            <p class="text-gray-600">Optimize your pricing based on seasonal demand patterns</p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Peak Season -->
                                <div class="bg-orange-50 border border-orange-200 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-bold text-orange-800">Peak Season</h3>
                                        <span
                                            class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm font-semibold">
                                            <?php
                                            if (!empty($peak_months)) {
                                                $peak_month_names = array_map(function ($m) use ($month_names) {
                                                    return $month_names[$m];
                                                }, $peak_months);
                                                echo implode(', ', array_slice($peak_month_names, 0, 2));
                                            } else {
                                                echo 'Dec-Mar';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <p class="text-orange-700 mb-4">
                                        Increase prices by 20-30% for event services and bookings
                                    </p>
                                    <div class="bg-white rounded-lg p-3 border border-orange-300">
                                        <p class="text-sm text-orange-600">
                                            Avg. Booking Value:
                                            <strong>
                                                ₱<?php
                                                    $peak_avg = 0;
                                                    $peak_count = 0;
                                                    foreach ($peak_months as $month) {
                                                        if (isset($seasonal_data[$month])) {
                                                            $peak_avg += $seasonal_data[$month]['avg_price'];
                                                            $peak_count++;
                                                        }
                                                    }
                                                    echo $peak_count > 0 ? number_format($peak_avg / $peak_count, 0) : '0';
                                                    ?>
                                            </strong>
                                        </p>
                                    </div>
                                </div>

                                <!-- Regular Season -->
                                <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-bold text-green-800">Regular Season</h3>
                                        <span
                                            class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold">
                                            <?php
                                            if (!empty($regular_months)) {
                                                $regular_month_names = array_map(function ($m) use ($month_names) {
                                                    return $month_names[$m];
                                                }, array_slice($regular_months, 0, 2));
                                                echo implode(', ', $regular_month_names);
                                            } else {
                                                echo 'Apr-Jul';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <p class="text-green-700 mb-4">
                                        Standard pricing with occasional promotions
                                    </p>
                                    <div class="bg-white rounded-lg p-3 border border-green-300">
                                        <p class="text-sm text-green-600">
                                            Avg. Booking Value:
                                            <strong>
                                                ₱<?php
                                                    $regular_avg = 0;
                                                    $regular_count = 0;
                                                    foreach ($regular_months as $month) {
                                                        if (isset($seasonal_data[$month])) {
                                                            $regular_avg += $seasonal_data[$month]['avg_price'];
                                                            $regular_count++;
                                                        }
                                                    }
                                                    echo $regular_count > 0 ? number_format($regular_avg / $regular_count, 0) : '0';
                                                    ?>
                                            </strong>
                                        </p>
                                    </div>
                                </div>

                                <!-- Low Season -->
                                <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-bold text-blue-800">Low Season</h3>
                                        <span
                                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                                            <?php
                                            if (!empty($low_months)) {
                                                $low_month_names = array_map(function ($m) use ($month_names) {
                                                    return $month_names[$m];
                                                }, $low_months);
                                                echo implode(', ', array_slice($low_month_names, 0, 2));
                                            } else {
                                                echo 'Aug-Nov';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <p class="text-blue-700 mb-4">
                                        Offer 10-15% discounts to attract more bookings
                                    </p>
                                    <div class="bg-white rounded-lg p-3 border border-blue-300">
                                        <p class="text-sm text-blue-600">
                                            Avg. Booking Value:
                                            <strong>
                                                ₱<?php
                                                    $low_avg = 0;
                                                    $low_count = 0;
                                                    foreach ($low_months as $month) {
                                                        if (isset($seasonal_data[$month])) {
                                                            $low_avg += $seasonal_data[$month]['avg_price'];
                                                            $low_count++;
                                                        }
                                                    }
                                                    echo $low_count > 0 ? number_format($low_avg / $low_count, 0) : '0';
                                                    ?>
                                            </strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Pro Tip -->
                            <div class="mt-6 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                                <div class="flex items-start">
                                    <i class="fas fa-lightbulb text-purple-500 text-lg mt-1 mr-3"></i>
                                    <div>
                                        <p class="font-semibold text-purple-800">Pro Tip</p>
                                        <p class="text-purple-700 text-sm">
                                            Monitor booking patterns and adjust your price percentage monthly for
                                            maximum revenue.
                                            Current average price percentage across venues:
                                            <strong>
                                                <?php
                                                $avg_percentage = 0;
                                                $venue_count = 0;
                                                if ($venues_result->num_rows > 0) {
                                                    $venues_result->data_seek(0);
                                                    while ($venue = $venues_result->fetch_assoc()) {
                                                        $avg_percentage += $venue['price_percentage'] ?? 15;
                                                        $venue_count++;
                                                    }
                                                    echo $venue_count > 0 ? round($avg_percentage / $venue_count, 1) . '%' : '15%';
                                                    $venues_result->data_seek(0);
                                                }
                                                ?>
                                            </strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Feature 2: Price Optimization Calculator -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-900">
                                    <i class="fas fa-calculator text-green-600 mr-2"></i>
                                    Price Optimization Calculator
                                </h2>
                                <p class="text-gray-600">Calculate optimal pricing based on multiple factors</p>
                            </div>
                            <div class="p-6">
                                <form id="priceCalculator" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Venue</label>
                                        <select id="calc_venue"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <?php if ($venues_result && $venues_result->num_rows > 0): ?>
                                                <?php while ($venue = $venues_result->fetch_assoc()): ?>
                                                    <option value="<?php echo $venue['venue_id']; ?>"
                                                        data-base-price="<?php echo $venue['base_price']; ?>"
                                                        data-percentage="<?php echo $venue['price_percentage'] ?? 15; ?>">
                                                        <?php echo htmlspecialchars($venue['venue_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Season</label>
                                            <select id="calc_season"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                                <option value="0.8">Off-Peak (-20%)</option>
                                                <option value="1.0" selected>Regular</option>
                                                <option value="1.2">Peak (+20%)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Day Type</label>
                                            <select id="calc_day"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                                <option value="1.0">Weekday</option>
                                                <option value="1.15" selected>Weekend (+15%)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Demand
                                            Level</label>
                                        <select id="calc_demand"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="0.9">Low (-10%)</option>
                                            <option value="1.0">Normal</option>
                                            <option value="1.1" selected>High (+10%)</option>
                                        </select>
                                    </div>

                                    <button type="button" id="calculatePrice"
                                        class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition-colors font-semibold">
                                        Calculate Optimal Price
                                    </button>
                                </form>

                                <!-- Results -->
                                <div id="calcResults" class="hidden mt-6 space-y-4">
                                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                                        <h4 class="font-semibold text-green-800 mb-2">Recommended Price</h4>
                                        <p class="text-3xl font-bold text-green-600" id="optimalPrice">₱0</p>
                                        <p class="text-sm text-green-700 mt-1" id="priceExplanation"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Feature 3: Peak Hours Pricing Analysis -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-900">
                                    <i class="fas fa-clock text-red-500 mr-2"></i>
                                    Peak Hours Pricing Analysis
                                </h2>
                                <p class="text-gray-600">Identify high-demand time slots for premium pricing</p>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                                    <?php if ($peak_hours_result && $peak_hours_result->num_rows > 0): ?>
                                        <?php while ($hour = $peak_hours_result->fetch_assoc()):
                                            $is_peak = $hour['booking_count'] >= 3;
                                        ?>
                                            <div
                                                class="text-center p-4 border rounded-lg <?php echo $is_peak ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'; ?>">
                                                <p
                                                    class="text-lg font-bold <?php echo $is_peak ? 'text-red-600' : 'text-gray-600'; ?>">
                                                    <?php echo $hour['hour']; ?>:00
                                                </p>
                                                <p class="text-sm text-gray-600"><?php echo $hour['booking_count']; ?> bookings
                                                </p>
                                                <p class="text-sm font-semibold text-green-600">
                                                    ₱<?php echo number_format($hour['avg_price'], 0); ?>
                                                </p>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-span-3 text-center py-8">
                                            <i class="fas fa-clock text-gray-400 text-4xl mb-3"></i>
                                            <p class="text-gray-600">No peak hours data available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Opportunity Insight -->
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-bolt text-red-500 text-lg mt-1 mr-3"></i>
                                        <div>
                                            <p class="font-semibold text-red-800">Opportunity</p>
                                            <p class="text-red-700 text-sm">
                                                Consider implementing time-based pricing for peak hours (
                                                <?php
                                                $peak_hours_list = [];
                                                if ($peak_hours_result) {
                                                    $peak_hours_result->data_seek(0);
                                                    while ($hour = $peak_hours_result->fetch_assoc()) {
                                                        if ($hour['booking_count'] >= 3) {
                                                            $peak_hours_list[] = $hour['hour'] . ':00';
                                                        }
                                                    }
                                                }
                                                echo !empty($peak_hours_list) ? implode(', ', array_slice($peak_hours_list, 0, 3)) : '14:00, 18:00, 20:00';
                                                ?>
                                                )
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Profile Dropdown
                        // Profile dropdown handled by ManagerSidebar.php

                        // Price Optimization Calculator
                        document.getElementById('calculatePrice').addEventListener('click', function() {
                            const venueSelect = document.getElementById('calc_venue');
                            const selectedOption = venueSelect.selectedOptions[0];
                            const basePrice = parseFloat(selectedOption.dataset.basePrice) || 0;
                            const currentPercentage = parseFloat(selectedOption.dataset.percentage) || 15;

                            const seasonMultiplier = parseFloat(document.getElementById('calc_season').value);
                            const dayMultiplier = parseFloat(document.getElementById('calc_day').value);
                            const demandMultiplier = parseFloat(document.getElementById('calc_demand').value);

                            const optimalPrice = basePrice * seasonMultiplier * dayMultiplier * demandMultiplier;
                            const priceDifference = ((optimalPrice - basePrice) / basePrice * 100).toFixed(1);

                            // Update results
                            document.getElementById('optimalPrice').textContent =
                                `₱${optimalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                            let explanation = '';
                            if (priceDifference > 0) {
                                explanation = `+${priceDifference}% from base price due to favorable conditions`;
                            } else if (priceDifference < 0) {
                                explanation = `${priceDifference}% from base price to remain competitive`;
                            } else {
                                explanation = 'Same as base price - standard conditions';
                            }
                            document.getElementById('priceExplanation').textContent = explanation;

                            // Show results
                            document.getElementById('calcResults').classList.remove('hidden');
                        });

                        // Auto-calculate when selections change
                        ['calc_season', 'calc_day', 'calc_demand'].forEach(id => {
                            document.getElementById(id).addEventListener('change', function() {
                                if (document.getElementById('calcResults').classList.contains('hidden') ===
                                    false) {
                                    document.getElementById('calculatePrice').click();
                                }
                            });
                        });

                        // Package Modal Functions
                        function openPackageModal(venueId, venueName, basePrice, avgPrice) {
                            document.getElementById('package_venue_id').value = venueId;
                            document.getElementById('package_venue_name').textContent = venueName;
                            document.getElementById('package_base_price').textContent = '₱' + basePrice.toLocaleString();
                            document.getElementById('package_avg_price').textContent = '₱' + avgPrice.toLocaleString();

                            // Suggest package price (25% above base)
                            const suggestedPrice = basePrice * 1.25;
                            document.getElementById('package_price').value = suggestedPrice.toFixed(0);

                            // Auto-generate package name
                            document.getElementById('package_name').value = venueName + ' Premium Package';

                            document.getElementById('packageModal').classList.remove('hidden');
                            document.getElementById('packageModal').classList.add('flex');
                        }

                        function closePackageModal() {
                            document.getElementById('packageModal').classList.add('hidden');
                            document.getElementById('packageModal').classList.remove('flex');
                        }

                        // Close modal when clicking outside
                        document.addEventListener('click', function(event) {
                            if (event.target.id === 'packageModal') {
                                closePackageModal();
                            }
                        });
                    </script>

                    <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            <?php endif; ?>
                </div>

</body>

</html>