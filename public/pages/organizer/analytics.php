<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Organizer';
$user_id = $_SESSION['user_id'];

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
    'total_events' => 0,
    'total_spent' => 0,
    'avg_event_cost' => 0,
    'total_guests' => 0,
    'events_by_status' => [],
    'events_by_venue' => [],
    'monthly_events' => [],
    'monthly_spending' => [],
    'avg_guests_per_event' => 0
];

try {
    // Determine bind types based on params
    $bind_types = count($params) == 1 ? 'i' : 'iss';

    // Total events
    $query = "SELECT COUNT(*) as count FROM events e WHERE e.organizer_id = ? $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['total_events'] = $stmt->get_result()->fetch_assoc()['count'];

    // Total spent
    $query = "SELECT SUM(e.total_cost) as total FROM events e WHERE e.organizer_id = ? AND e.status IN ('confirmed', 'completed') $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['total_spent'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Average event cost
    $query = "SELECT AVG(e.total_cost) as avg FROM events e WHERE e.organizer_id = ? AND e.status IN ('confirmed', 'completed') $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['avg_event_cost'] = $stmt->get_result()->fetch_assoc()['avg'] ?? 0;

    // Total guests
    $query = "SELECT SUM(e.expected_guests) as total FROM events e WHERE e.organizer_id = ? $date_condition";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $analytics['total_guests'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Average guests per event
    if ($analytics['total_events'] > 0) {
        $analytics['avg_guests_per_event'] = $analytics['total_guests'] / $analytics['total_events'];
    }

    // Events by status
    $query = "SELECT e.status, COUNT(*) as count FROM events e WHERE e.organizer_id = ? $date_condition GROUP BY e.status";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['events_by_status'][$row['status']] = $row['count'];
    }

    // Events by venue (top 5)
    $query = "SELECT v.venue_name, COUNT(*) as count, SUM(e.total_cost) as total_spent 
              FROM events e 
              LEFT JOIN venues v ON e.venue_id = v.venue_id 
              WHERE e.organizer_id = ? $date_condition 
              GROUP BY v.venue_id, v.venue_name 
              ORDER BY count DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['events_by_venue'][] = $row;
    }

    // Monthly events count
    $query = "SELECT DATE_FORMAT(e.event_date, '%Y-%m') as month, COUNT(*) as count 
              FROM events e 
              WHERE e.organizer_id = ? $date_condition 
              GROUP BY DATE_FORMAT(e.event_date, '%Y-%m') 
              ORDER BY month ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['monthly_events'][$row['month']] = $row['count'];
    }

    // Monthly spending
    $query = "SELECT DATE_FORMAT(e.event_date, '%Y-%m') as month, SUM(e.total_cost) as total 
              FROM events e 
              WHERE e.organizer_id = ? AND e.status IN ('confirmed', 'completed') $date_condition 
              GROUP BY DATE_FORMAT(e.event_date, '%Y-%m') 
              ORDER BY month ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics['monthly_spending'][$row['month']] = $row['total'];
    }
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

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-cyan-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-bar mr-2 text-indigo-600"></i>
                    Event Analytics
                </h1>
                <p class="text-sm text-gray-600">Detailed insights into your event management</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">
                        <i class="fas fa-chart-bar mr-2 text-indigo-600"></i>
                        Event Analytics
                    </h1>
                    <p class="text-gray-600">Detailed insights into your event management</p>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-xl shadow-md mb-8">
                <form method="GET" action="" class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Time Period</label>
                        <select name="filter" id="filterType"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Time
                            </option>
                            <option value="7days" <?php echo $filter_type === '7days' ? 'selected' : ''; ?>>7 Days
                            </option>
                            <option value="30days" <?php echo $filter_type === '30days' ? 'selected' : ''; ?>>30
                                Days</option>
                            <option value="3months" <?php echo $filter_type === '3months' ? 'selected' : ''; ?>>3
                                Months</option>
                            <option value="6months" <?php echo $filter_type === '6months' ? 'selected' : ''; ?>>6
                                Months</option>
                            <option value="1year" <?php echo $filter_type === '1year' ? 'selected' : ''; ?>>This Year
                            </option>
                            <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>Custom
                                Range</option>
                        </select>
                    </div>

                    <div class="flex-1 min-w-[200px]" id="startDateDiv"
                        style="display: <?php echo $filter_type === 'custom' ? 'block' : 'none'; ?>;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>

                    <div class="flex-1 min-w-[200px]" id="endDateDiv"
                        style="display: <?php echo $filter_type === 'custom' ? 'block' : 'none'; ?>;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>

                    <div class="flex items-end gap-3 ml-auto">
                        <button type="submit"
                            class="px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors whitespace-nowrap">
                            <i class="fas fa-filter mr-2"></i>Apply Filter
                        </button>

                        <!-- Export Dropdown -->
                        <div class="relative">
                            <button type="button" id="exportDropdownBtn"
                                class="px-6 py-2.5 bg-gray-700 text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors flex items-center gap-2 whitespace-nowrap">
                                <i class="fas fa-download"></i>
                                Export
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            <div id="exportDropdown"
                                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                <button onclick="exportPDF()"
                                    class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3 border-b border-gray-100">
                                    <i class="fas fa-file-pdf text-red-600"></i>
                                    <span class="font-medium text-gray-700">Export as PDF</span>
                                </button>
                                <button onclick="exportCSV()"
                                    class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3">
                                    <i class="fas fa-file-csv text-green-600"></i>
                                    <span class="font-medium text-gray-700">Export as CSV</span>
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
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Events</p>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo number_format($analytics['total_events']); ?></p>
                        </div>
                        <div class="p-4 bg-indigo-100 rounded-lg">
                            <i class="text-indigo-600 text-2xl fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Spent</p>
                            <p class="text-3xl font-bold text-gray-900">
                                ₱<?php echo number_format($analytics['total_spent'], 2); ?></p>
                        </div>
                        <div class="p-4 bg-green-100 rounded-lg">
                            <i class="text-green-600 text-2xl fas fa-peso-sign"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Avg Event Cost</p>
                            <p class="text-3xl font-bold text-gray-900">
                                ₱<?php echo number_format($analytics['avg_event_cost'], 2); ?></p>
                        </div>
                        <div class="p-4 bg-blue-100 rounded-lg">
                            <i class="text-blue-600 text-2xl fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Guests</p>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo number_format($analytics['total_guests']); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Avg:
                                <?php echo number_format($analytics['avg_guests_per_event'], 0); ?>/event</p>
                        </div>
                        <div class="p-4 bg-purple-100 rounded-lg">
                            <i class="text-purple-600 text-2xl fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Events by Status -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie text-indigo-600 mr-2"></i>
                        Events by Status
                    </h2>
                    <div class="relative" style="height: 300px;">
                        <?php if (empty($analytics['events_by_status'])): ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i class="fas fa-chart-pie text-5xl mb-3"></i>
                                <p class="font-semibold">No data available</p>
                            </div>
                        <?php else: ?>
                            <canvas id="statusChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Events -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar text-indigo-600 mr-2"></i>
                        Events Over Time
                    </h2>
                    <div class="relative" style="height: 300px;">
                        <?php if (empty($analytics['monthly_events'])): ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i class="fas fa-chart-bar text-5xl mb-3"></i>
                                <p class="font-semibold">No data available</p>
                                <p class="text-sm">Try adjusting your filter settings</p>
                            </div>
                        <?php else: ?>
                            <canvas id="monthlyEventsChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Monthly Spending -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-line text-indigo-600 mr-2"></i>
                        Spending Trend
                    </h2>
                    <div class="relative" style="height: 300px;">
                        <?php if (empty($analytics['monthly_spending'])): ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <i class="fas fa-chart-line text-5xl mb-3"></i>
                                <p class="font-semibold">No spending data available</p>
                                <p class="text-sm">Only confirmed/completed events are shown</p>
                            </div>
                        <?php else: ?>
                            <canvas id="spendingChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Venues -->
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-building text-indigo-600 mr-2"></i>
                        Top Venues
                    </h2>
                    <div class="space-y-4">
                        <?php if (empty($analytics['events_by_venue'])): ?>
                            <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                                <i class="fas fa-building text-5xl mb-3"></i>
                                <p class="font-semibold">No venue data available</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($analytics['events_by_venue'] as $venue): ?>
                                <div class="border-l-4 border-indigo-500 pl-4 py-2">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($venue['venue_name'] ?? 'Unknown Venue'); ?></h3>
                                        <span class="text-sm font-bold text-indigo-600"><?php echo $venue['count']; ?>
                                            events</span>
                                    </div>
                                    <p class="text-sm text-gray-600">Total spent:
                                        ₱<?php echo number_format($venue['total_spent'], 2); ?></p>
                                    <div class="mt-2 bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full"
                                            style="width: <?php echo ($venue['count'] / $analytics['total_events']) * 100; ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($nav_layout === 'sidebar'): ?>
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

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!exportDropdownBtn.contains(e.target) && !exportDropdown.contains(e.target)) {
                exportDropdown.classList.add('hidden');
            }
        });

        // Close dropdown after export
        function closeExportDropdown() {
            exportDropdown.classList.add('hidden');
        }

        // Export to CSV
        function exportCSV() {
            closeExportDropdown();
            const analytics = {
                totalEvents: <?php echo $analytics['total_events']; ?>,
                totalSpent: <?php echo $analytics['total_spent']; ?>,
                avgEventCost: <?php echo $analytics['avg_event_cost']; ?>,
                totalGuests: <?php echo $analytics['total_guests']; ?>,
                avgGuestsPerEvent: <?php echo $analytics['avg_guests_per_event']; ?>,
                eventsByStatus: <?php echo json_encode($analytics['events_by_status']); ?>,
                eventsByVenue: <?php echo json_encode($analytics['events_by_venue']); ?>,
                monthlyEvents: <?php echo json_encode($analytics['monthly_events']); ?>,
                monthlySpending: <?php echo json_encode($analytics['monthly_spending']); ?>
            };

            const filterInfo = '<?php
                                if ($filter_type === "custom" && $start_date && $end_date) {
                                    echo "Custom Range: $start_date to $end_date";
                                } else {
                                    echo ucfirst(str_replace("_", " ", $filter_type));
                                }
                                ?>';

            let csv = 'Gatherly Event Analytics Report\n';
            csv += 'Filter Period: ' + filterInfo + '\n';
            csv += 'Generated: ' + new Date().toLocaleString() + '\n\n';

            // Summary Statistics
            csv += 'SUMMARY STATISTICS\n';
            csv += 'Metric,Value\n';
            csv += 'Total Events,' + analytics.totalEvents + '\n';
            csv += 'Total Spent,₱' + analytics.totalSpent.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            }) + '\n';
            csv += 'Average Event Cost,₱' + analytics.avgEventCost.toLocaleString('en-PH', {
                minimumFractionDigits: 2
            }) + '\n';
            csv += 'Total Guests,' + analytics.totalGuests + '\n';
            csv += 'Average Guests per Event,' + analytics.avgGuestsPerEvent.toFixed(0) + '\n\n';

            // Events by Status
            csv += 'EVENTS BY STATUS\n';
            csv += 'Status,Count\n';
            for (const [status, count] of Object.entries(analytics.eventsByStatus)) {
                csv += status.charAt(0).toUpperCase() + status.slice(1) + ',' + count + '\n';
            }
            csv += '\n';

            // Top Venues
            csv += 'TOP VENUES\n';
            csv += 'Venue Name,Event Count,Total Spent\n';
            analytics.eventsByVenue.forEach(venue => {
                csv += '"' + (venue.venue_name || 'Unknown') + '",' + venue.count + ',₱' +
                    parseFloat(venue.total_spent).toLocaleString('en-PH', {
                        minimumFractionDigits: 2
                    }) + '\n';
            });
            csv += '\n';

            // Monthly Events
            csv += 'MONTHLY EVENTS\n';
            csv += 'Month,Event Count\n';
            for (const [month, count] of Object.entries(analytics.monthlyEvents)) {
                const [year, monthNum] = month.split('-');
                const monthName = new Date(year, monthNum - 1).toLocaleDateString('en-US', {
                    month: 'long',
                    year: 'numeric'
                });
                csv += monthName + ',' + count + '\n';
            }
            csv += '\n';

            // Monthly Spending
            csv += 'MONTHLY SPENDING\n';
            csv += 'Month,Total Spending\n';
            for (const [month, total] of Object.entries(analytics.monthlySpending)) {
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
            link.setAttribute('download', 'analytics_report_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Export to PDF
        async function exportPDF() {
            try {
                closeExportDropdown();

                // Check if jsPDF is loaded
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
                        <i class="fas fa-spinner fa-spin text-4xl text-indigo-600 mb-4"></i>
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
                    pdf.setFillColor(79, 70, 229);
                    pdf.rect(0, 0, pageWidth, 8, 'F');
                    pdf.setFontSize(8);
                    pdf.setTextColor(255, 255, 255);
                    pdf.text('Gatherly Analytics Report', margin, 5);
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
                pdf.setFillColor(79, 70, 229);
                pdf.rect(0, 0, pageWidth, 60, 'F');

                // Logo/Icon area
                pdf.setFillColor(255, 255, 255);
                pdf.circle(pageWidth / 2, 25, 12, 'F');
                pdf.setFontSize(18);
                pdf.setTextColor(79, 70, 229);
                pdf.text('G', pageWidth / 2, 28, {
                    align: 'center'
                });

                // Title
                pdf.setFontSize(24);
                pdf.setTextColor(255, 255, 255);
                pdf.setFont(undefined, 'bold');
                pdf.text('Event Analytics Report', pageWidth / 2, 48, {
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
                pdf.setTextColor(79, 70, 229);
                pdf.setFont(undefined, 'bold');
                pdf.text('Organizer: <?php echo htmlspecialchars($first_name); ?>', pageWidth / 2, yPos, {
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
                        title: 'Total Events',
                        value: '<?php echo number_format($analytics['total_events']); ?>',
                        color: [99, 102, 241],
                        iconSymbol: 'E'
                    },
                    {
                        title: 'Total Spent',
                        value: '<?php echo number_format($analytics['total_spent'], 2); ?>',
                        color: [34, 197, 94],
                        iconSymbol: '$'
                    },
                    {
                        title: 'Avg Event Cost',
                        value: '<?php echo number_format($analytics['avg_event_cost'], 2); ?>',
                        color: [59, 130, 246],
                        iconSymbol: 'A'
                    },
                    {
                        title: 'Total Guests',
                        value: '<?php echo number_format($analytics['total_guests']); ?>',
                        color: [168, 85, 247],
                        iconSymbol: 'G'
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

                // Capture and add charts
                <?php if (!empty($analytics['events_by_status'])): ?>
                    // Events by Status Chart
                    const statusCanvas = document.getElementById('statusChart');
                    if (statusCanvas) {
                        pdf.setFontSize(14);
                        pdf.setTextColor(79, 70, 229);
                        pdf.setFont(undefined, 'bold');
                        pdf.text('Events by Status', margin, yPos);
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

                <?php if (!empty($analytics['monthly_events'])): ?>
                    // Check if we need a new page
                    if (yPos > pageHeight - 100) {
                        addPageFooter();
                        pdf.addPage();
                        pageCount++;
                        addPageHeader(pageCount);
                        yPos = 15;
                    }

                    // Monthly Events Chart
                    const monthlyCanvas = document.getElementById('monthlyEventsChart');
                    if (monthlyCanvas) {
                        pdf.setFontSize(14);
                        pdf.setTextColor(79, 70, 229);
                        pdf.setFont(undefined, 'bold');
                        pdf.text('Events Over Time', margin, yPos);
                        yPos += 8;

                        const monthlyImgData = monthlyCanvas.toDataURL('image/png');
                        const chartWidth = contentWidth - 10;
                        const chartHeight = 70;

                        pdf.setDrawColor(220, 220, 220);
                        pdf.setFillColor(255, 255, 255);
                        pdf.roundedRect(margin - 2, yPos - 5, chartWidth + 4, chartHeight + 10, 3, 3, 'FD');
                        pdf.addImage(monthlyImgData, 'PNG', margin, yPos, chartWidth, chartHeight);
                        yPos += chartHeight + 20;
                    }
                <?php endif; ?>

                <?php if (!empty($analytics['monthly_spending'])): ?>
                    // Check if we need a new page
                    if (yPos > pageHeight - 100) {
                        addPageFooter();
                        pdf.addPage();
                        pageCount++;
                        addPageHeader(pageCount);
                        yPos = 15;
                    }

                    // Spending Trend Chart
                    const spendingCanvas = document.getElementById('spendingChart');
                    if (spendingCanvas) {
                        pdf.setFontSize(14);
                        pdf.setTextColor(79, 70, 229);
                        pdf.setFont(undefined, 'bold');
                        pdf.text('Spending Trend', margin, yPos);
                        yPos += 8;

                        const spendingImgData = spendingCanvas.toDataURL('image/png');
                        const chartWidth = contentWidth - 10;
                        const chartHeight = 70;

                        pdf.setDrawColor(220, 220, 220);
                        pdf.setFillColor(255, 255, 255);
                        pdf.roundedRect(margin - 2, yPos - 5, chartWidth + 4, chartHeight + 10, 3, 3, 'FD');
                        pdf.addImage(spendingImgData, 'PNG', margin, yPos, chartWidth, chartHeight);
                        yPos += chartHeight + 20;
                    }
                <?php endif; ?>

                // Top Venues Section
                <?php if (!empty($analytics['events_by_venue'])): ?>
                    if (yPos > pageHeight - 70) {
                        addPageFooter();
                        pdf.addPage();
                        pageCount++;
                        addPageHeader(pageCount);
                        yPos = 15;
                    }

                    pdf.setFontSize(14);
                    pdf.setTextColor(79, 70, 229);
                    pdf.setFont(undefined, 'bold');
                    pdf.text('Top Venues', margin, yPos);
                    yPos += 10;

                    const venues = <?php echo json_encode($analytics['events_by_venue']); ?>;
                    const maxEvents = Math.max(...venues.map(v => v.count));

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
                            [99, 102, 241], // Indigo
                            [99, 102, 241] // Indigo
                        ];
                        const badgeColor = badgeColors[index] || [99, 102, 241];
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
                        const truncatedName = venueName.length > 40 ? venueName.substring(0, 38) + '...' : venueName;
                        pdf.text(truncatedName, margin + 12, yPos + 6);

                        // Stats
                        pdf.setFontSize(8);
                        pdf.setTextColor(100, 100, 100);
                        pdf.setFont(undefined, 'normal');
                        pdf.text(venue.count + ' events', margin + 12, yPos + 11);
                        pdf.text('₱' + parseFloat(venue.total_spent).toLocaleString('en-PH', {
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

                        const fillWidth = (venue.count / maxEvents) * barWidth;
                        pdf.setFillColor(99, 102, 241);
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
                pdf.setTextColor(79, 70, 229);
                pdf.setFont(undefined, 'bold');
                pdf.text('Detailed Data Tables', margin, yPos);
                yPos += 12;

                // Events by Status Table
                <?php if (!empty($analytics['events_by_status'])): ?>
                    pdf.setFontSize(12);
                    pdf.setFont(undefined, 'bold');
                    pdf.setTextColor(79, 70, 229);
                    pdf.text('Events by Status', margin, yPos);
                    yPos += 8;

                    // Modern table header
                    pdf.setFillColor(79, 70, 229);
                    pdf.setDrawColor(79, 70, 229);
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
                    const statusData = <?php echo json_encode($analytics['events_by_status']); ?>;
                    const totalEvents = <?php echo $analytics['total_events']; ?>;
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
                        const percentage = ((count / totalEvents) * 100).toFixed(1);
                        pdf.text(percentage + '%', margin + 90, yPos + 5.5, {
                            align: 'right'
                        });

                        yPos += 8;
                        rowIndex++;
                    }

                    yPos += 10;
                <?php endif; ?>

                // Monthly Events Table
                const monthlyData = <?php echo json_encode($analytics['monthly_events']); ?>;
                if (Object.keys(monthlyData).length > 0) {
                    if (yPos > pageHeight - 60) {
                        addPageFooter();
                        pdf.addPage();
                        pageCount++;
                        addPageHeader(pageCount);
                        yPos = 15;
                    }

                    pdf.setFontSize(12);
                    pdf.setFont(undefined, 'bold');
                    pdf.setTextColor(79, 70, 229);
                    pdf.text('Events Over Time', margin, yPos);
                    yPos += 8;

                    // Table header
                    pdf.setFillColor(79, 70, 229);
                    const monthTableWidth = 120;
                    pdf.roundedRect(margin, yPos, monthTableWidth, 10, 2, 2, 'FD');

                    pdf.setFontSize(10);
                    pdf.setTextColor(255, 255, 255);
                    pdf.text('Month', margin + 3, yPos + 6.5);
                    pdf.text('Events', margin + 100, yPos + 6.5);
                    yPos += 10;

                    // Table data
                    pdf.setTextColor(0, 0, 0);
                    pdf.setFont(undefined, 'normal');
                    let monthRowIndex = 0;

                    for (const [month, count] of Object.entries(monthlyData)) {
                        if (yPos > pageHeight - 25) {
                            addPageFooter();
                            pdf.addPage();
                            pageCount++;
                            addPageHeader(pageCount);
                            yPos = 15;
                        }

                        const [year, monthNum] = month.split('-');
                        const monthName = new Date(year, monthNum - 1).toLocaleDateString('en-US', {
                            month: 'long',
                            year: 'numeric'
                        });

                        const isEven = monthRowIndex % 2 === 0;
                        if (isEven) {
                            pdf.setFillColor(249, 250, 251);
                            pdf.rect(margin, yPos, monthTableWidth, 8, 'F');
                        }

                        pdf.setFontSize(9);
                        pdf.text(monthName, margin + 3, yPos + 5.5);
                        pdf.setFont(undefined, 'bold');
                        pdf.text(String(count), margin + 100, yPos + 5.5);
                        pdf.setFont(undefined, 'normal');

                        yPos += 8;
                        monthRowIndex++;
                    }
                }

                // Final footer
                addPageFooter();

                // Final footer
                addPageFooter();

                // Save and open PDF
                const pdfBlob = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfBlob);
                window.open(pdfUrl, '_blank');

                // Remove loading indicator
                document.getElementById('pdfLoading')?.remove();

            } catch (error) {
                console.error('Error generating PDF:', error);

                // Remove loading indicator
                document.getElementById('pdfLoading')?.remove();

                alert('Error generating PDF: ' + error.message + '\n\nPlease check the console for more details.');
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

        <?php if (!empty($analytics['events_by_status'])): ?>
            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_map('ucfirst', array_keys($analytics['events_by_status']))); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($analytics['events_by_status'])); ?>,
                        backgroundColor: [
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderWidth: 2
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
        <?php endif; ?>

        // Monthly Events Chart
        const monthlyEventsChart = document.getElementById('monthlyEventsChart');
        <?php if (!empty($analytics['monthly_events'])): ?>
            const monthlyEventsCtx = monthlyEventsChart.getContext('2d');
            const monthlyEventsData = <?php echo json_encode($analytics['monthly_events']); ?>;
            const eventLabels = Object.keys(monthlyEventsData).map((m, index, array) => {
                const [year, month] = m.split('-');
                const monthName = new Date(year, month - 1).toLocaleDateString('en-US', {
                    month: 'short'
                });

                // Always show year for first item, last item, or when year changes
                if (index === 0 || index === array.length - 1) {
                    return monthName + ' ' + year;
                }

                const [prevYear] = array[index - 1].split('-');
                if (year !== prevYear) {
                    return monthName + ' ' + year;
                }

                return monthName + ' ' + "'" + year.slice(2);
            });

            new Chart(monthlyEventsCtx, {
                type: 'bar',
                data: {
                    labels: eventLabels,
                    datasets: [{
                        label: 'Events',
                        data: Object.values(monthlyEventsData),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderColor: 'rgba(99, 102, 241, 1)',
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

        // Spending Chart
        const spendingChart = document.getElementById('spendingChart');
        <?php if (!empty($analytics['monthly_spending'])): ?>
            const spendingCtx = spendingChart.getContext('2d');
            const spendingData = <?php echo json_encode($analytics['monthly_spending']); ?>;
            const spendingLabels = Object.keys(spendingData).map((m, index, array) => {
                const [year, month] = m.split('-');
                const monthName = new Date(year, month - 1).toLocaleDateString('en-US', {
                    month: 'short'
                });

                // Always show year for first item, last item, or when year changes
                if (index === 0 || index === array.length - 1) {
                    return monthName + ' ' + year;
                }

                const [prevYear] = array[index - 1].split('-');
                if (year !== prevYear) {
                    return monthName + ' ' + year;
                }

                return monthName + ' ' + "'" + year.slice(2);
            });

            new Chart(spendingCtx, {
                type: 'line',
                data: {
                    labels: spendingLabels,
                    datasets: [{
                        label: 'Spending (₱)',
                        data: Object.values(spendingData),
                        borderColor: 'rgba(34, 197, 94, 1)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
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
                                label: (context) => 'Spending: ₱' + context.parsed.y.toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => '₱' + value.toLocaleString('en-PH', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                })
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: false,
                                maxTicksLimit: 15,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>