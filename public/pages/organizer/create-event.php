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

// Fetch available venues
$venues_query = "
SELECT 
    v.venue_id,
    v.venue_name, 
    v.capacity, 
    p.base_price, 
    v.location 
FROM venues v 
JOIN pricing p ON v.venue_id = p.venue_id
WHERE v.availability_status = 'available' 
ORDER BY v.venue_name";
$venues_result = $conn->query($venues_query);

// Fetch available services by category
$services_query = "SELECT s.service_id, s.service_name, s.category, s.description, s.price, 
                   sup.supplier_name, sup.location 
                   FROM services s 
                   JOIN suppliers sup ON s.supplier_id = sup.supplier_id 
                   WHERE sup.availability_status = 'available' 
                   ORDER BY s.category, s.price";
$services_result = $conn->query($services_query);

$services_by_category = [];
while ($service = $services_result->fetch_assoc()) {
    $services_by_category[$service['category']][] = $service;
}

// Fetch all venues into array for UI
$venues_all = [];
$venues_result->data_seek(0);
while ($v = $venues_result->fetch_assoc()) {
    $venues_all[$v['venue_id']] = $v;
}

// ONLY pre-select if ?venue_id is provided and valid
$preselected_id = isset($_GET['venue_id']) && is_numeric($_GET['venue_id']) ? (int)$_GET['venue_id'] : null;
$selected_venue = null;
if ($preselected_id && isset($venues_all[$preselected_id])) {
    $selected_venue = $venues_all[$preselected_id];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event | Gatherly</title>
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
</head>

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-purple-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
        <!-- Top Bar for Sidebar Layout -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Create Event</h1>
            <p class="text-sm text-gray-600">Plan your event with venue and service selection</p>
        </div>
        <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
            <!-- Header for Navbar Layout -->
            <div class="mb-8">
                <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Create Event</h1>
                <p class="text-gray-600">Plan your event with venue and service selection</p>
            </div>
            <?php endif; ?>

            <!-- Scrollable Main Content Area -->
            <div class="mx-auto overflow-y-auto" style="max-height: calc(100vh - 100px);">

                <!-- Success/Error Messages -->
                <div id="alertContainer" class="mb-6"></div>

                <!-- Create Event Form -->
                <form id="createEventForm" class="max-w-5xl mx-auto">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        <!-- Main Form Section (2/3 width) -->
                        <div class="lg:col-span-2">
                            <!-- Basic Information -->
                            <div class="p-6 mb-6 bg-white shadow-lg rounded-2xl">
                                <h2 class="flex items-center gap-2 mb-6 text-2xl font-bold text-gray-800">
                                    <i class="text-indigo-600 fas fa-info-circle"></i>
                                    Basic Information
                                </h2>

                                <div class="space-y-4">
                                    <!-- Event Name -->
                                    <div>
                                        <label class="block mb-2 text-sm font-semibold text-gray-700">Event Name <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" id="event_name" name="event_name" required
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            placeholder="e.g., Mike & Anna Wedding">
                                    </div>

                                    <!-- Event Type & Theme -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Event Type
                                                <span class="text-red-500">*</span></label>
                                            <select id="event_type" name="event_type" required
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <option value="">Select Type</option>
                                                <option value="Wedding">Wedding</option>
                                                <option value="Corporate">Corporate Event</option>
                                                <option value="Birthday">Birthday Party</option>
                                                <option value="Concert">Concert</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Theme</label>
                                            <input type="text" id="theme" name="theme"
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                placeholder="e.g., Rustic Garden">
                                        </div>
                                    </div>

                                    <!-- Expected Guests & Date -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Expected
                                                Guests <span class="text-red-500">*</span></label>
                                            <input type="number" id="expected_guests" name="expected_guests" required
                                                min="1"
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                placeholder="e.g., 150">
                                        </div>
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Event Date &
                                                Time
                                                <span class="text-red-500">*</span></label>
                                            <input type="datetime-local" id="event_date" name="event_date" required
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue Selection -->
                            <div class="p-6 mb-6 bg-white shadow-lg rounded-2xl">
                                <h2 class="flex items-center gap-2 mb-6 text-2xl font-bold text-gray-800">
                                    <i class="text-indigo-600 fas fa-building"></i>
                                    Selected Venue <span class="text-red-500">*</span>
                                </h2>

                                <!-- Selected Venue Card -->
                                <?php if ($selected_venue): ?>
                                <div id="selected-venue-card"
                                    class="p-4 mb-4 border-2 border-indigo-500 rounded-xl bg-indigo-50">
                                    <div class="flex items-start justify-between mb-3">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            <?php echo htmlspecialchars($selected_venue['venue_name']); ?>
                                        </h3>
                                        <input type="radio" name="venue_id"
                                            value="<?php echo $selected_venue['venue_id']; ?>"
                                            class="w-5 h-5 text-indigo-600 focus:ring-indigo-500" checked>
                                    </div>
                                    <p class="mb-2 text-sm text-gray-600">
                                        <i class="mr-1 text-indigo-600 fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($selected_venue['location']); ?>
                                    </p>
                                    <p class="mb-2 text-sm text-gray-600">
                                        <i class="mr-1 text-indigo-600 fas fa-users"></i>
                                        Capacity: <?php echo $selected_venue['capacity']; ?> guests
                                    </p>
                                    <p class="text-lg font-bold text-green-600">
                                        ₱<?php echo number_format($selected_venue['base_price'], 2); ?>
                                    </p>
                                </div>
                                <?php else: ?>
                                <div id="selected-venue-card" class="hidden"></div> <?php endif; ?>

                                <!-- Choose Other Venue Button -->
                                <div class="mt-4">
                                    <a href="find-venues.php"
                                        class="inline-flex items-center px-4 py-2 font-medium text-indigo-600 transition-colors bg-indigo-100 rounded-lg hover:bg-indigo-200">
                                        <i class="mr-2 fas fa-exchange-alt"></i>
                                        Choose other venue
                                    </a>
                                </div>
                            </div>

                            <!-- Services Selection -->
                            <div class="p-6 mb-6 bg-white shadow-lg rounded-2xl">
                                <h2 class="flex items-center gap-2 mb-6 text-2xl font-bold text-gray-800">
                                    <i class="text-indigo-600 fas fa-concierge-bell"></i>
                                    Select Services (Optional)
                                </h2>

                                <div class="space-y-6">
                                    <?php foreach ($services_by_category as $category => $services): ?>
                                    <div class="p-4 border-2 border-gray-200 rounded-xl">
                                        <h3 class="mb-4 text-lg font-bold text-gray-800">
                                            <?php
                                                $icons = [
                                                    'Catering' => '🍽️',
                                                    'Lights and Sounds' => '🎵',
                                                    'Photography' => '📸',
                                                    'Videography' => '🎥',
                                                    'Host/Emcee' => '🎤',
                                                    'Styling and Flowers' => '💐',
                                                    'Equipment Rental' => '🪑'
                                                ];
                                        echo $icons[$category] ?? '📋';
                                        ?>
                                            <?php echo htmlspecialchars($category); ?>
                                        </h3>
                                        <div class="space-y-3">
                                            <?php foreach ($services as $service): ?>
                                            <label
                                                class="flex items-start gap-3 p-3 transition-all border border-gray-200 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-300">
                                                <input type="checkbox" name="services[]"
                                                    value="<?php echo $service['service_id']; ?>"
                                                    class="w-5 h-5 mt-1 text-indigo-600 service-checkbox focus:ring-indigo-500"
                                                    data-price="<?php echo $service['price']; ?>">
                                                <div class="flex-1">
                                                    <div class="flex items-start justify-between">
                                                        <div>
                                                            <p class="font-semibold text-gray-800">
                                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                                            </p>
                                                            <p class="text-sm text-gray-600">
                                                                <?php echo htmlspecialchars($service['supplier_name']); ?>
                                                                -
                                                                <?php echo htmlspecialchars($service['location']); ?>
                                                            </p>
                                                            <p class="mt-1 text-xs text-gray-500">
                                                                <?php echo htmlspecialchars($service['description']); ?>
                                                            </p>
                                                        </div>
                                                        <p class="ml-4 text-lg font-bold text-green-600">
                                                            ₱<?php echo number_format($service['price'], 2); ?></p>
                                                    </div>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Sidebar (1/3 width) -->
                        <div class="lg:col-span-1">
                            <div class="sticky p-6 bg-white shadow-lg top-24 rounded-2xl">
                                <h2 class="flex items-center gap-2 mb-6 text-2xl font-bold text-gray-800">
                                    <i class="text-indigo-600 fas fa-file-invoice-dollar"></i>
                                    Cost Summary
                                </h2>

                                <div class="space-y-4">
                                    <!-- Venue Cost -->
                                    <div class="flex justify-between pb-3 border-b border-gray-200">
                                        <span class="text-gray-700">Venue</span>
                                        <span id="venue-cost" class="font-semibold text-gray-800">
                                            ₱<?php echo $selected_venue ? number_format($selected_venue['base_price'], 2) : '0.00'; ?>
                                        </span>
                                    </div>

                                    <!-- Services Cost -->
                                    <div class="flex justify-between pb-3 border-b border-gray-200">
                                        <span class="text-gray-700">Services</span>
                                        <span id="services-cost" class="font-semibold text-gray-800">₱0.00</span>
                                    </div>

                                    <!-- Total Cost -->
                                    <div class="flex justify-between pt-3">
                                        <span class="text-lg font-bold text-gray-800">Total Cost</span>
                                        <span id="total-cost" class="text-2xl font-bold text-indigo-600">
                                            ₱<?php echo $selected_venue ? number_format($selected_venue['base_price'], 2) : '0.00'; ?>
                                        </span>
                                    </div>

                                    <input type="hidden" id="total_cost" name="total_cost"
                                        value="<?php echo $selected_venue ? $selected_venue['base_price'] : '0'; ?>">
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" id="submitBtn"
                                    class="w-full px-6 py-4 mt-6 font-bold text-white transition-all transform bg-indigo-600 rounded-lg shadow-lg hover:bg-indigo-700 hover:scale-105">
                                    <i class="mr-2 fas fa-calendar-check"></i>
                                    Create Event
                                </button>

                                <!-- AI Suggestion Button -->
                                <a href="ai-planner.php"
                                    class="block w-full px-6 py-4 mt-3 font-semibold text-center text-indigo-600 transition-all bg-indigo-100 rounded-lg hover:bg-indigo-200">
                                    <i class="mr-2 fas fa-robot"></i>
                                    Get AI Recommendations
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <script>
            function updateCostSummary() {
                const selectedVenue = document.querySelector('input[name="venue_id"]:checked');
                const venueCost = selectedVenue ? parseFloat(document.getElementById('total_cost').value) : 0;

                let servicesCost = 0;
                document.querySelectorAll('input.service-checkbox:checked').forEach(checkbox => {
                    servicesCost += parseFloat(checkbox.dataset.price) || 0;
                });

                const total = venueCost + servicesCost;

                document.getElementById('venue-cost').textContent = '₱' + venueCost.toFixed(2);
                document.getElementById('services-cost').textContent = '₱' + servicesCost.toFixed(2);
                document.getElementById('total-cost').textContent = '₱' + total.toFixed(2);
                document.getElementById('total_cost').value = total.toFixed(2);
            }

            // Toggle radio selection
            let lastCheckedRadio = null;
            document.addEventListener('click', function(e) {
                if (e.target.matches('input[name="venue_id"]')) {
                    if (e.target === lastCheckedRadio) {
                        e.target.checked = false;
                        document.getElementById('selected-venue-card').classList.add('hidden');
                        lastCheckedRadio = null;
                    } else {
                        lastCheckedRadio = e.target;
                        document.getElementById('selected-venue-card').classList.remove('hidden');
                    }
                    updateCostSummary();
                }
            });

            // Services
            document.querySelectorAll('input.service-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateCostSummary);
            });
            </script>

            <?php if ($nav_layout === 'sidebar'): ?>
        </div>
        <?php endif; ?>
    </div>
</body>

</html>
