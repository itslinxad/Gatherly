<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

$venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;

if ($venue_id <= 0) {
    header("Location: pricing.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';
require_once '../../../src/services/ml/PricingOptimizer.php';

$first_name = $_SESSION['first_name'] ?? 'Manager';
$user_id = $_SESSION['user_id'];
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

// Verify venue belongs to this manager
$stmt = $conn->prepare("SELECT v.venue_name, p.base_price 
                       FROM venues v 
                       LEFT JOIN prices p ON v.venue_id = p.venue_id
                       WHERE v.venue_id = ? AND v.manager_id = ?");
$stmt->bind_param("ii", $venue_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: pricing.php");
    exit();
}

$venue_data = $result->fetch_assoc();
$venue_name = $venue_data['venue_name'];
$base_price = $venue_data['base_price'] ?? 50000;

// Get ML insights
try {
    $optimizer = new PricingOptimizer($conn, $venue_id, $base_price);
    $insights = $optimizer->getMLInsights();
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML Pricing Insights - <?php echo htmlspecialchars($venue_name); ?> | Gatherly</title>
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

<body class="bg-gray-50 font-['Montserrat']">

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
                            <div class="flex items-center gap-3 mb-2">
                                <a href="pricing.php" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-arrow-left text-xl"></i>
                                </a>
                                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                                    <i class="fas fa-chart-line text-purple-600 mr-3"></i>
                                    ML Pricing Insights
                                </h1>
                            </div>
                            <p class="text-sm text-gray-600 ml-12"><?php echo htmlspecialchars($venue_name); ?></p>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <div class="bg-green-50 border border-green-200 px-3 py-1 rounded-full">
                                <i class="fas fa-brain text-green-600 mr-1"></i>
                                <span class="text-green-700 font-semibold">AI Analysis</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                    <!-- Header for Navbar Layout -->
                    <div class="max-w-7xl mx-auto px-4 py-8">
                        <div class="mb-8">
                            <div class="flex items-center gap-3 mb-2">
                                <a href="pricing.php" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-arrow-left text-xl"></i>
                                </a>
                                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-chart-line text-purple-600 mr-3"></i>
                                    ML Pricing Insights
                                </h1>
                            </div>
                            <p class="text-gray-600 ml-12"><?php echo htmlspecialchars($venue_name); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Main Content -->
                <div class="<?php echo $nav_layout !== 'sidebar' ? 'max-w-7xl mx-auto px-4 pb-8' : 'pb-8'; ?>">
                    <?php if (isset($error_message)): ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                <span class="text-red-700">Failed to load insights:
                                    <?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Venue Info -->
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
                            <h4 class="font-bold text-indigo-900 mb-2"><?php echo htmlspecialchars($venue_name); ?></h4>
                            <p class="text-indigo-700">Current Base Price:
                                <strong>₱<?php echo number_format($base_price, 2); ?></strong>
                            </p>
                        </div>

                        <!-- AI Calculation Explanation
                        <?php if (isset($insights['price_calculation_explanation'])): ?>
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-indigo-300 rounded-lg p-6 mb-6">
                                <div class="flex items-start gap-3 mb-3">
                                    <i class="fas fa-brain text-indigo-600 text-2xl mt-1"></i>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-indigo-900 text-lg mb-2">How AI Calculates Your Optimal Prices</h4>
                                        <p class="text-gray-700 text-sm mb-4"><?php echo $insights['price_calculation_explanation']['explanation']; ?></p>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            <div class="bg-white rounded-lg p-3 border border-indigo-200">
                                                <div class="text-xs text-gray-600 mb-1">Demand Analysis</div>
                                                <div class="font-bold text-indigo-600"><?php echo $insights['price_calculation_explanation']['factors']['demand']['weight']; ?></div>
                                                <div class="text-xs text-gray-500">Score: <?php echo $insights['price_calculation_explanation']['factors']['demand']['score']; ?></div>
                                            </div>
                                            <div class="bg-white rounded-lg p-3 border border-purple-200">
                                                <div class="text-xs text-gray-600 mb-1">Competition</div>
                                                <div class="font-bold text-purple-600"><?php echo $insights['price_calculation_explanation']['factors']['competition']['weight']; ?></div>
                                                <div class="text-xs text-gray-500">Score: <?php echo $insights['price_calculation_explanation']['factors']['competition']['score']; ?></div>
                                            </div>
                                            <div class="bg-white rounded-lg p-3 border border-orange-200">
                                                <div class="text-xs text-gray-600 mb-1">Seasonality</div>
                                                <div class="font-bold text-orange-600"><?php echo $insights['price_calculation_explanation']['factors']['seasonality']['weight']; ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $insights['price_calculation_explanation']['factors']['seasonality']['multiplier_range']; ?></div>
                                            </div>
                                            <div class="bg-white rounded-lg p-3 border border-green-200">
                                                <div class="text-xs text-gray-600 mb-1">Performance</div>
                                                <div class="font-bold text-green-600"><?php echo $insights['price_calculation_explanation']['factors']['performance']['weight']; ?></div>
                                                <div class="text-xs text-gray-500">Score: <?php echo $insights['price_calculation_explanation']['factors']['performance']['score']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        -->

                        <!-- Insights Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <!-- Demand Forecast with Chart -->
                            <div class="bg-white border border-gray-200 rounded-lg p-6 lg:col-span-2">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-chart-line text-indigo-600 text-xl"></i>
                                        <h5 class="font-bold text-gray-900 text-lg">Demand Forecast - Last 12 Months</h5>
                                    </div>
                                    <span class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-semibold">
                                        <i class="fas fa-arrow-up mr-1"></i>Higher is Better
                                    </span>
                                </div>
                                <div class="bg-blue-50 border-l-4 border-indigo-500 p-4 mb-4">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-info-circle text-indigo-600 text-lg mt-0.5"></i>
                                        <div class="text-sm text-gray-700">
                                            <p class="font-semibold text-indigo-900 mb-1">How This Analysis Works:</p>
                                            <p class="mb-2">The AI analyzes your venue's booking patterns over the last 12 months to predict future demand. It considers:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2 text-xs">
                                                <li><strong>Historical bookings:</strong> Number of confirmed events each month</li>
                                                <li><strong>Booking trends:</strong> Whether demand is increasing, stable, or decreasing</li>
                                                <li><strong>Seasonal patterns:</strong> Peak months vs. slow months</li>
                                                <li><strong>Future forecast:</strong> Predicted bookings for next 3 months based on trends</li>
                                            </ul>
                                            <p class="mt-2 text-indigo-800 font-medium">
                                                💡 <strong>Higher score (closer to 100%) = Higher demand = You can charge more</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full mb-4" style="height: 350px; position: relative;">
                                    <canvas id="demandChart"></canvas>
                                </div>
                                <div class="mt-4">
                                    <div class="mb-3">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm text-gray-600 font-medium">Current Demand Score</span>
                                            <span
                                                class="font-bold text-indigo-600 text-xl"><?php echo $insights['demand_forecast']['score']; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-4 shadow-inner">
                                            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-4 rounded-full transition-all duration-500 shadow-sm"
                                                style="width: <?php echo $insights['demand_forecast']['score']; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="text-sm bg-gray-50 p-3 rounded-lg">
                                        <p class="text-gray-600">
                                            <span class="font-medium">Trend:</span>
                                            <strong class="text-<?php
                                                                $trend = $insights['demand_forecast']['trend'];
                                                                echo $trend === 'increasing' ? 'green' : ($trend === 'decreasing' ? 'red' : 'gray');
                                                                ?>-600 ml-1">
                                                <?php echo ucfirst($trend); ?>
                                            </strong>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <p class="text-gray-700 bg-gray-50 p-3 rounded text-sm">
                                        <i class="fas fa-lightbulb text-indigo-600 mr-2"></i>
                                        <?php echo $insights['demand_forecast']['recommendation']; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Competitive Analysis -->
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-users text-purple-600 text-xl"></i>
                                        <h5 class="font-bold text-gray-900 text-lg">Competitive Analysis</h5>
                                    </div>
                                    <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-semibold">
                                        <i class="fas fa-balance-scale mr-1"></i>Balanced is Best
                                    </span>
                                </div>
                                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-4">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-info-circle text-purple-600 text-lg mt-0.5"></i>
                                        <div class="text-sm text-gray-700">
                                            <p class="font-semibold text-purple-900 mb-1">How This Analysis Works:</p>
                                            <p class="mb-2">The AI compares your venue's price to similar venues in your area:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2 text-xs">
                                                <li><strong>Market comparison:</strong> Your price vs. average market price</li>
                                                <li><strong>Similar venues:</strong> Venues with comparable capacity and location</li>
                                                <li><strong>Position score:</strong> Where you stand (0-100%)</li>
                                            </ul>
                                            <div class="mt-2 space-y-1">
                                                <p class="text-purple-800 font-medium text-xs">
                                                    • <strong>Below 40%:</strong> Value pricing - attracting budget-conscious clients
                                                </p>
                                                <p class="text-purple-800 font-medium text-xs">
                                                    • <strong>40-60%:</strong> Competitive - balanced with market
                                                </p>
                                                <p class="text-purple-800 font-medium text-xs">
                                                    • <strong>Above 60%:</strong> Premium - charging more than competitors
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm text-gray-600 font-medium">Market Position</span>
                                        <span
                                            class="font-bold text-purple-600 text-lg"><?php echo $insights['competitive_analysis']['position']; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-purple-600 h-3 rounded-full"
                                            style="width: <?php echo $insights['competitive_analysis']['position']; ?>%">
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm mb-4">
                                    <p class="text-gray-600 mb-2">
                                        Status:
                                        <strong class="text-<?php
                                                            $status = $insights['competitive_analysis']['status'];
                                                            echo $status === 'premium' ? 'green' : ($status === 'value' ? 'blue' : 'gray');
                                                            ?>-600">
                                            <?php echo ucfirst($status); ?>
                                        </strong>
                                    </p>
                                </div>
                                <div class="mt-4">
                                    <p class="text-gray-700 bg-gray-50 p-3 rounded text-sm">
                                        <i class="fas fa-lightbulb text-purple-600 mr-2"></i>
                                        <?php echo $insights['competitive_analysis']['recommendation']; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Seasonality -->
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
                                        <h5 class="font-bold text-gray-900 text-lg">Seasonality Analysis</h5>
                                    </div>
                                    <span class="text-xs bg-orange-100 text-orange-700 px-3 py-1 rounded-full font-semibold">
                                        <i class="fas fa-sun mr-1"></i>Season-Based
                                    </span>
                                </div>
                                <div class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-4">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-info-circle text-orange-600 text-lg mt-0.5"></i>
                                        <div class="text-sm text-gray-700">
                                            <p class="font-semibold text-orange-900 mb-1">How This Analysis Works:</p>
                                            <p class="mb-2">The AI identifies seasonal patterns in event bookings:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2 text-xs">
                                                <li><strong>Peak Season:</strong> High-demand months (Dec-Feb, Apr-May)</li>
                                                <li><strong>Off-Peak Season:</strong> Low-demand months (Jun-Aug rainy season)</li>
                                                <li><strong>Price multipliers:</strong> Automatic adjustments based on season</li>
                                            </ul>
                                            <div class="mt-2 space-y-1">
                                                <p class="text-orange-800 font-medium text-xs">
                                                    🔥 <strong>Peak Multiplier:</strong> Multiply base price during high demand
                                                </p>
                                                <p class="text-orange-800 font-medium text-xs">
                                                    ❄️ <strong>Off-Peak Multiplier:</strong> Reduce base price to attract bookings
                                                </p>
                                            </div>
                                            <p class="mt-2 text-orange-800 font-medium text-xs">
                                                💡 <strong>Higher multipliers during peak = Maximize revenue when demand is high</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3 mb-4">
                                    <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                                        <span class="text-sm text-gray-700 font-medium">Peak Season Multiplier:</span>
                                        <span
                                            class="font-bold text-orange-600 text-lg"><?php echo $insights['seasonality']['peak_multiplier']; ?>x</span>
                                    </div>
                                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                        <span class="text-sm text-gray-700 font-medium">Off-Peak Multiplier:</span>
                                        <span
                                            class="font-bold text-blue-600 text-lg"><?php echo $insights['seasonality']['offpeak_multiplier']; ?>x</span>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <p class="text-gray-700 bg-gray-50 p-3 rounded text-sm">
                                        <i class="fas fa-lightbulb text-orange-600 mr-2"></i>
                                        <?php echo $insights['seasonality']['recommendation']; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Performance -->
                            <div class="bg-white border border-gray-200 rounded-lg p-6 lg:col-span-2">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-trophy text-green-600 text-xl"></i>
                                        <h5 class="font-bold text-gray-900 text-lg">Performance Metrics</h5>
                                    </div>
                                    <span class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-semibold">
                                        <i class="fas fa-arrow-up mr-1"></i>Higher is Better
                                    </span>
                                </div>
                                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-info-circle text-green-600 text-lg mt-0.5"></i>
                                        <div class="text-sm text-gray-700">
                                            <p class="font-semibold text-green-900 mb-1">How This Analysis Works:</p>
                                            <p class="mb-2">The AI evaluates your venue's overall business performance:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2 text-xs">
                                                <li><strong>Booking rate:</strong> Percentage of months with confirmed bookings</li>
                                                <li><strong>Revenue consistency:</strong> Steady income vs. irregular bookings</li>
                                                <li><strong>Customer retention:</strong> Repeat clients and recommendations</li>
                                                <li><strong>Completion rate:</strong> Successfully completed vs. cancelled events</li>
                                            </ul>
                                            <div class="mt-2 space-y-1">
                                                <p class="text-green-800 font-medium text-xs">
                                                    ⭐ <strong>Excellent (90-100%):</strong> Top performer - can charge premium
                                                </p>
                                                <p class="text-green-800 font-medium text-xs">
                                                    ✅ <strong>Good (70-89%):</strong> Strong performance - competitive pricing
                                                </p>
                                                <p class="text-green-800 font-medium text-xs">
                                                    📊 <strong>Average (50-69%):</strong> Room for improvement - market rate
                                                </p>
                                                <p class="text-green-800 font-medium text-xs">
                                                    ⚠️ <strong>Below 50%:</strong> Needs attention - consider lower pricing
                                                </p>
                                            </div>
                                            <p class="mt-2 text-green-800 font-medium">
                                                💡 <strong>Higher score = Better track record = Justified higher pricing</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm text-gray-600 font-medium">Performance Score</span>
                                        <span
                                            class="font-bold text-green-600 text-lg"><?php echo $insights['performance']['score']; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-green-600 h-3 rounded-full"
                                            style="width: <?php echo $insights['performance']['score']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="text-sm mb-4">
                                    <p class="text-gray-600 mb-2">
                                        Rating: <strong
                                            class="text-green-600"><?php echo ucfirst($insights['performance']['rating']); ?></strong>
                                    </p>
                                </div>
                                <div class="mt-4">
                                    <p class="text-gray-700 bg-gray-50 p-3 rounded text-sm">
                                        <i class="fas fa-lightbulb text-green-600 mr-2"></i>
                                        <?php echo $insights['performance']['recommendation']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Back Button -->
                        <div class="flex justify-center">
                            <a href="pricing.php"
                                class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Pricing
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if (isset($insights['demand_forecast']['history']) && !empty($insights['demand_forecast']['history']['labels'])): ?>
            // Create demand chart with historical and forecast data
            const ctx = document.getElementById('demandChart').getContext('2d');
            const historicalLabels = <?php echo json_encode($insights['demand_forecast']['history']['labels']); ?>;
            const forecastLabels = <?php echo json_encode($insights['demand_forecast']['history']['forecast_labels'] ?? []); ?>;
            const allLabels = [...historicalLabels, ...forecastLabels];

            // Prepare datasets
            const historicalData = <?php echo json_encode($insights['demand_forecast']['history']['data']); ?>;
            const forecastData = <?php echo json_encode($insights['demand_forecast']['history']['forecast_data'] ?? []); ?>;

            console.log('Historical:', historicalData);
            console.log('Forecast:', forecastData);
            console.log('All labels:', allLabels);

            // Extend historical data with nulls for forecast period
            const extendedHistorical = [...historicalData, ...Array(forecastData.length).fill(null)];

            // Create forecast data starting from last historical point
            const extendedForecast = Array(historicalData.length).fill(null);
            if (forecastData.length > 0) {
                extendedForecast[historicalData.length - 1] = historicalData[historicalData.length - 1];
                forecastData.forEach((val, idx) => {
                    extendedForecast[historicalData.length + idx] = val;
                });
            }

            console.log('Extended Historical:', extendedHistorical);
            console.log('Extended Forecast:', extendedForecast);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: allLabels,
                    datasets: [{
                            label: 'Historical Bookings',
                            data: extendedHistorical,
                            borderColor: 'rgb(79, 70, 229)',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgb(79, 70, 229)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            spanGaps: false
                        },
                        {
                            label: 'Forecasted Bookings',
                            data: extendedForecast,
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            borderDash: [8, 4],
                            tension: 0.3,
                            fill: false,
                            borderWidth: 3,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgb(34, 197, 94)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointStyle: 'triangle',
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12,
                                    family: 'Montserrat'
                                },
                                usePointStyle: true,
                                boxWidth: 8
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.parsed.y !== null) {
                                        const label = context.dataset.label || '';
                                        return label + ': ' + context.parsed.y.toFixed(1) + ' bookings';
                                    }
                                    return null;
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 13,
                                family: 'Montserrat'
                            },
                            bodyFont: {
                                size: 12,
                                family: 'Montserrat'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11,
                                    family: 'Montserrat'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Number of Bookings',
                                font: {
                                    size: 12,
                                    weight: 'bold',
                                    family: 'Montserrat'
                                },
                                color: '#4B5563'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 10,
                                    family: 'Montserrat'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Month',
                                font: {
                                    size: 12,
                                    weight: 'bold',
                                    family: 'Montserrat'
                                },
                                color: '#4B5563'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                }
            });
        <?php endif; ?>
    </script>

</body>

</html>