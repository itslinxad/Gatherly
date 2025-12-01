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
        CONCAT(l.city, ', ', l.province) as location,
        v.suitable_themes,
        v.venue_type,
        v.ambiance
    FROM venues v 
    JOIN prices p ON v.venue_id = p.venue_id
    JOIN locations l ON v.location_id = l.location_id
    WHERE v.availability_status = 'available' AND v.status = 'active'
    ORDER BY v.venue_name
";
$venues_result = $conn->query($venues_query);

// Fetch all venues into array for UI
$venues_all = [];
$venues_result->data_seek(0);
while ($v = $venues_result->fetch_assoc()) {
    $venues_all[$v['venue_id']] = $v;
}

// Fetch available services by category (will be filtered by venue location via JavaScript)
// Include venue location data in services for filtering
$services_query = "SELECT s.service_id, s.service_name, s.category, s.description, s.price, 
                   sup.supplier_name, sup.location as supplier_location,
                   sup.supplier_id
                   FROM services s 
                   JOIN suppliers sup ON s.supplier_id = sup.supplier_id 
                   WHERE sup.availability_status = 'available' 
                   ORDER BY s.category, s.price";
$services_result = $conn->query($services_query);

$services_by_category = [];
$all_services = [];
while ($service = $services_result->fetch_assoc()) {
    $services_by_category[$service['category']][] = $service;
    $all_services[] = $service;
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
                <form id="createEventForm" class="max-w-7xl mx-auto">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        <!-- Main Form Section (2/3 width) -->
                        <div class="lg:col-span-2">

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
                                                data-price="<?php echo $selected_venue['base_price']; ?>"
                                                data-location="<?php echo htmlspecialchars($selected_venue['location']); ?>"
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
                                    <button type="button" id="chooseOtherVenueBtn"
                                        class="inline-flex items-center cursor-pointer px-4 py-3 font-medium text-indigo-600 transition-all duration-200 bg-indigo-100 rounded-lg hover:bg-indigo-200 hover:shadow-md transform hover:-translate-y-0.5">
                                        <i class="mr-2 fas fa-exchange-alt"></i>
                                        Choose other venue
                                    </button>
                                </div>
                            </div>

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
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400"
                                            placeholder="e.g., Mike & Anna Wedding">
                                    </div>

                                    <!-- Event Type & Theme -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Event Type
                                                <span class="text-red-500">*</span></label>
                                            <select id="event_type" name="event_type" required
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400 cursor-pointer">
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

                                    <!-- Specify Event Type (shown when "Other" is selected) -->
                                    <div id="otherEventTypeContainer" class="hidden">
                                        <label class="block mb-2 text-sm font-semibold text-gray-700">
                                            Please Specify Event Type <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="other_event_type" name="other_event_type"
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400"
                                            placeholder="e.g., Reunion, Seminar, Workshop">
                                    </div>

                                    <!-- Event Date & Time Range -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <!-- Expected Guests -->
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Expected
                                                Guests <span class="text-red-500">*</span></label>
                                            <input type="number" id="expected_guests" name="expected_guests" required
                                                min="1"
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400"
                                                placeholder="e.g., 150">
                                        </div>
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Event Date
                                                <span class="text-red-500">*</span></label>
                                            <input type="date" id="event_date" name="event_date" required
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400">
                                            <p id="date-availability-msg" class="mt-1 text-sm"></p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Start Time
                                                <span class="text-red-500">*</span></label>
                                            <select id="event_start_time" name="event_start_time" required disabled
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400 cursor-pointer disabled:bg-gray-100 disabled:cursor-not-allowed">
                                                <option value="">Select date and venue first</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">End Time
                                                <span class="text-red-500">*</span></label>
                                            <select id="event_end_time" name="event_end_time" required disabled
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400 cursor-pointer disabled:bg-gray-100 disabled:cursor-not-allowed">
                                                <option value="">Select start time first</option>
                                            </select>
                                        </div>
                                    </div>
                                    <p id="time-availability-msg" class="text-sm text-gray-500">Available time slots
                                        will appear after selecting a date and venue</p>
                                </div>
                            </div>

                            <!-- Services Selection -->
                            <div class="p-6 mb-6 bg-white shadow-lg rounded-2xl">
                                <h2 class="flex items-center gap-2 mb-6 text-2xl font-bold text-gray-800">
                                    <i class="text-indigo-600 fas fa-concierge-bell"></i>
                                    Services
                                </h2>

                                <!-- Location Filter Info -->
                                <div id="locationFilterInfo" class="hidden mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm text-blue-800">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Showing services from suppliers near <strong id="venueLocationText"></strong>
                                    </p>
                                </div>

                                <!-- Search and Filter -->
                                <div class="mb-4 space-y-3">
                                    <div class="flex gap-3">
                                        <div class="flex-1">
                                            <input type="text" id="serviceSearch"
                                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                placeholder="Search services...">
                                        </div>
                                        <select id="categoryFilter"
                                            class="px-4 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <option value="">All Categories</option>
                                            <?php foreach (array_keys($services_by_category) as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                                    <?php echo htmlspecialchars($cat); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Available Services Table -->
                                <div class="mb-6 overflow-hidden border-2 border-gray-200 rounded-lg">
                                    <div class="overflow-x-auto" style="max-height: 400px;">
                                        <table class="w-full">
                                            <thead class="sticky top-0 bg-gray-50">
                                                <tr class="border-b border-gray-200">
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Service Name</th>
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Category</th>
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Supplier</th>
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Price</th>
                                                    <th
                                                        class="px-4 py-3 text-xs font-semibold text-center text-gray-700">
                                                        Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="servicesTableBody">
                                                <?php foreach ($services_by_category as $category => $services): ?>
                                                    <?php foreach ($services as $service): ?>
                                                        <tr class="service-row border-b border-gray-100 hover:bg-indigo-50 transition-all duration-200 cursor-pointer hover:shadow-sm"
                                                            data-service-id="<?php echo $service['service_id']; ?>"
                                                            data-service-name="<?php echo htmlspecialchars($service['service_name']); ?>"
                                                            data-category="<?php echo htmlspecialchars($category); ?>"
                                                            data-supplier="<?php echo htmlspecialchars($service['supplier_name']); ?>"
                                                            data-supplier-location="<?php echo htmlspecialchars($service['supplier_location']); ?>"
                                                            data-price="<?php echo $service['price']; ?>"
                                                            data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                            data-location="<?php echo htmlspecialchars($service['supplier_location']); ?>">
                                                            <td class="px-4 py-3">
                                                                <p class="font-semibold text-gray-800">
                                                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                                                </p>
                                                                <p class="text-xs text-gray-500">
                                                                    <?php echo htmlspecialchars($service['description']); ?>
                                                                </p>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-600">
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
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                                <?php echo htmlspecialchars($service['supplier_name']); ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm font-bold text-green-600">
                                                                ₱<?php echo number_format($service['price'], 2); ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-center">
                                                                <button type="button"
                                                                    class="add-service-btn flex items-center cursor-pointer px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all duration-200 transform hover:scale-105 hover:shadow-md active:scale-95">
                                                                    <i class="fas fa-plus mr-1"></i> Add
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Selected Services -->
                                <div id="selectedServicesSection" class="hidden">
                                    <h3 class="mb-3 text-lg font-bold text-gray-800">Selected Services</h3>
                                    <div class="overflow-hidden border-2 border-indigo-200 rounded-lg bg-indigo-50">
                                        <table class="w-full">
                                            <thead class="bg-indigo-100">
                                                <tr class="border-b border-indigo-200">
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Service</th>
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Category</th>
                                                    <th class="px-4 py-3 text-xs font-semibold text-left text-gray-700">
                                                        Price</th>
                                                    <th
                                                        class="px-4 py-3 text-xs font-semibold text-center text-gray-700">
                                                        Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="selectedServicesBody">
                                                <!-- Selected services will be added here -->
                                            </tbody>
                                        </table>
                                    </div>
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
                                    class="w-full px-6 py-4 mt-6 font-bold text-white transition-all duration-300 transform bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg hover:from-indigo-700 hover:to-purple-700 hover:scale-105 hover:shadow-xl active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                                    <i class="mr-2 fas fa-calendar-check"></i>
                                    Create Event
                                </button>

                                <!-- AI Suggestion Button -->
                                <a href="ai-planner.php"
                                    class="block w-full px-6 py-4 mt-3 font-semibold text-center text-indigo-600 transition-all duration-200 bg-indigo-100 rounded-lg hover:bg-indigo-200 hover:shadow-md transform hover:-translate-y-0.5">
                                    <i class="mr-2 fas fa-robot"></i>
                                    Chat with AI
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Success/Error Modal -->
                <div id="responseModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto"
                    aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <!-- Background overlay with blur -->
                        <div id="modalBackdrop"
                            class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-30 backdrop-blur-sm"
                            style="backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);"
                            aria-hidden="true"></div>

                        <!-- Center modal -->
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                            aria-hidden="true">&#8203;</span>

                        <!-- Modal panel -->
                        <div id="modalPanel"
                            class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div id="modalIconContainer"
                                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto rounded-full sm:mx-0 sm:h-10 sm:w-10">
                                        <i id="modalIcon" class="text-2xl"></i>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                        <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">
                                            Modal Title
                                        </h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-700" id="modal-message">
                                                Modal message here
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" id="modalCloseBtn"
                                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Form data persistence
                function saveFormData() {
                    const formData = {
                        event_name: document.getElementById('event_name')?.value || '',
                        event_type: document.getElementById('event_type')?.value || '',
                        other_event_type: document.getElementById('other_event_type')?.value || '',
                        theme: document.getElementById('theme')?.value || '',
                        expected_guests: document.getElementById('expected_guests')?.value || '',
                        event_date: document.getElementById('event_date')?.value || '',
                        event_start_time: document.getElementById('event_start_time')?.value || '',
                        event_end_time: document.getElementById('event_end_time')?.value || '',
                        selectedServices: Array.from(document.querySelectorAll(
                            '#selectedServicesBody input[name="services[]"]')).map(input => ({
                            id: input.value,
                            price: input.dataset.price,
                            name: input.closest('tr').querySelector('.font-semibold').textContent,
                            supplier: input.closest('tr').querySelector('.text-xs').textContent.split(
                                ' - ')[0],
                            location: input.closest('tr').querySelector('.text-xs').textContent.split(
                                ' - ')[1],
                            category: input.closest('tr').querySelectorAll('td')[1].textContent
                        }))
                    };
                    localStorage.setItem('eventFormData', JSON.stringify(formData));
                }

                function loadFormData() {
                    const savedData = localStorage.getItem('eventFormData');
                    if (!savedData) return;

                    try {
                        const formData = JSON.parse(savedData);

                        // Restore basic form fields
                        if (formData.event_name) document.getElementById('event_name').value = formData.event_name;
                        if (formData.event_type) {
                            document.getElementById('event_type').value = formData.event_type;
                            if (formData.event_type === 'Other') {
                                document.getElementById('other_event_type_container').classList.remove('hidden');
                                if (formData.other_event_type) {
                                    document.getElementById('other_event_type').value = formData.other_event_type;
                                }
                            }
                        }
                        if (formData.theme) document.getElementById('theme').value = formData.theme;
                        if (formData.expected_guests) document.getElementById('expected_guests').value = formData
                            .expected_guests;
                        if (formData.event_date) document.getElementById('event_date').value = formData.event_date;
                        if (formData.event_start_time) document.getElementById('event_start_time').value = formData
                            .event_start_time;
                        if (formData.event_end_time) document.getElementById('event_end_time').value = formData
                            .event_end_time;

                        // Restore selected services
                        if (formData.selectedServices && formData.selectedServices.length > 0) {
                            document.getElementById('selectedServicesSection').classList.remove('hidden');
                            const tbody = document.getElementById('selectedServicesBody');
                            tbody.innerHTML = '';

                            formData.selectedServices.forEach(service => {
                                const selectedRow = document.createElement('tr');
                                selectedRow.className = 'border-b border-indigo-200';
                                selectedRow.innerHTML = `
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-800">${service.name}</p>
                                        <p class="text-xs text-gray-500">${service.supplier} - ${service.location}</p>
                                        <input type="hidden" name="services[]" value="${service.id}" data-price="${service.price}">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${service.category}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-green-600">₱${parseFloat(service.price).toFixed(2)}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" class="remove-service-btn flex items-center cursor-pointer px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-all duration-200 transform hover:scale-105 hover:shadow-md active:scale-95">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </td>
                                `;
                                tbody.appendChild(selectedRow);
                            });

                            updateCostSummary();
                        }

                        // Clear saved data after loading
                        localStorage.removeItem('eventFormData');
                    } catch (error) {
                        console.error('Error loading form data:', error);
                        localStorage.removeItem('eventFormData');
                    }
                }

                // Load saved form data on page load
                window.addEventListener('DOMContentLoaded', function() {
                    loadFormData();
                    // Filter services based on pre-selected venue (if any)
                    if (document.querySelector('input[name="venue_id"]:checked')) {
                        filterServices();
                    }
                });

                // Save form data before navigating to choose other venue
                document.getElementById('chooseOtherVenueBtn')?.addEventListener('click', function() {
                    saveFormData();
                    window.location.href = 'find-venues.php';
                });

                // Auto-save form data periodically
                setInterval(saveFormData, 30000); // Save every 30 seconds

                // Cost summary calculation
                function updateCostSummary() {
                    const selectedVenue = document.querySelector('input[name="venue_id"]:checked');
                    const venueCost = selectedVenue ? parseFloat(selectedVenue.dataset.price || 0) : 0;

                    let servicesCost = 0;
                    document.querySelectorAll('#selectedServicesBody input[name="services[]"]').forEach(input => {
                        servicesCost += parseFloat(input.dataset.price) || 0;
                    });

                    const total = venueCost + servicesCost;

                    document.getElementById('venue-cost').textContent = '₱' + venueCost.toFixed(2);
                    document.getElementById('services-cost').textContent = '₱' + servicesCost.toFixed(2);
                    document.getElementById('total-cost').textContent = '₱' + total.toFixed(2);
                    document.getElementById('total_cost').value = total.toFixed(2);
                }

                // Toggle radio selection for venue
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
                        // Filter services based on new venue location
                        filterServices();
                    }
                });

                // Service search functionality
                const serviceSearch = document.getElementById('serviceSearch');
                const categoryFilter = document.getElementById('categoryFilter');
                const serviceRows = document.querySelectorAll('.service-row');

                function filterServices() {
                    const searchTerm = serviceSearch.value.toLowerCase();
                    const selectedCategory = categoryFilter.value.toLowerCase();

                    // Get selected venue location
                    const selectedVenue = document.querySelector('input[name="venue_id"]:checked');
                    const venueLocation = selectedVenue ? selectedVenue.dataset.location : null;

                    // Update location filter info
                    const locationFilterInfo = document.getElementById('locationFilterInfo');
                    const venueLocationText = document.getElementById('venueLocationText');
                    if (venueLocation) {
                        locationFilterInfo.classList.remove('hidden');
                        venueLocationText.textContent = venueLocation;
                    } else {
                        locationFilterInfo.classList.add('hidden');
                    }

                    // Extract city and province from venue location (format: "City, Province")
                    let venueCity = null;
                    let venueProvince = null;
                    if (venueLocation) {
                        const locationParts = venueLocation.split(',').map(part => part.trim());
                        venueCity = locationParts[0] ? locationParts[0].toLowerCase() : null;
                        venueProvince = locationParts[1] ? locationParts[1].toLowerCase() : null;
                    }

                    serviceRows.forEach(row => {
                        const serviceName = row.dataset.serviceName.toLowerCase();
                        const category = row.dataset.category.toLowerCase();
                        const supplier = row.dataset.supplier.toLowerCase();
                        const description = row.dataset.description.toLowerCase();
                        const supplierLocation = (row.dataset.supplierLocation || '').toLowerCase();

                        const matchesSearch = serviceName.includes(searchTerm) ||
                            supplier.includes(searchTerm) ||
                            description.includes(searchTerm);
                        const matchesCategory = !selectedCategory || category === selectedCategory;

                        // Check if supplier is near venue location
                        let matchesLocation = true;
                        if (venueCity && venueProvince && supplierLocation) {
                            // Supplier location should contain either the city or province of the venue
                            matchesLocation = supplierLocation.includes(venueCity) ||
                                supplierLocation.includes(venueProvince);
                        }

                        if (matchesSearch && matchesCategory && matchesLocation) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
                serviceSearch.addEventListener('input', filterServices);
                categoryFilter.addEventListener('change', filterServices);

                // Add service to selected list
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.add-service-btn')) {
                        const btn = e.target.closest('.add-service-btn');
                        const row = btn.closest('.service-row');
                        const serviceId = row.dataset.serviceId;

                        // Check if already added
                        if (document.querySelector(`#selectedServicesBody input[value="${serviceId}"]`)) {
                            alert('This service has already been added!');
                            return;
                        }

                        // Add to selected services
                        const selectedRow = document.createElement('tr');
                        selectedRow.className = 'border-b border-indigo-200';
                        selectedRow.innerHTML = `
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-800">${row.dataset.serviceName}</p>
                            <p class="text-xs text-gray-500">${row.dataset.supplier} - ${row.dataset.location}</p>
                            <input type="hidden" name="services[]" value="${serviceId}" data-price="${row.dataset.price}">
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">${row.dataset.category}</td>
                        <td class="px-4 py-3 text-sm font-bold text-green-600">₱${parseFloat(row.dataset.price).toFixed(2)}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" class="remove-service-btn flex items-center cursor-pointer px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-all duration-200 transform hover:scale-105 hover:shadow-md active:scale-95">
                                <i class="fas fa-trash mr-2"></i>Remove
                            </button>
                        </td>
                    `;

                        document.getElementById('selectedServicesBody').appendChild(selectedRow);
                        document.getElementById('selectedServicesSection').classList.remove('hidden');

                        updateCostSummary();
                    }

                    // Remove service from selected list
                    if (e.target.closest('.remove-service-btn')) {
                        const row = e.target.closest('tr');
                        row.remove();

                        if (document.getElementById('selectedServicesBody').children.length === 0) {
                            document.getElementById('selectedServicesSection').classList.add('hidden');
                        }

                        updateCostSummary();
                    }
                });

                // Form submission - Direct submit
                const createEventForm = document.getElementById('createEventForm');
                if (createEventForm) {
                    createEventForm.addEventListener('submit', async (e) => {
                        e.preventDefault();

                        // Validate venue selection
                        const selectedVenue = document.querySelector('input[name="venue_id"]:checked');
                        if (!selectedVenue) {
                            showModal('error', 'Validation Error', 'Please select a venue for your event.');
                            return;
                        }

                        // Validate form
                        if (!validateForm()) {
                            return;
                        }

                        // Gather form data
                        const formData = new FormData(createEventForm);

                        // Get date and times
                        const eventDate = document.getElementById('event_date').value;
                        const eventStartTime = document.getElementById('event_start_time').value;
                        const eventEndTime = document.getElementById('event_end_time').value;

                        // Combine date and start time into datetime format for event_date
                        const combinedDateTime = `${eventDate} ${eventStartTime}`;
                        formData.set('event_date', combinedDateTime);

                        // Ensure start and end times are sent separately
                        formData.set('event_start_time', eventStartTime);
                        formData.set('event_end_time', eventEndTime);

                        try {
                            const response = await fetch('../../../src/services/create-event-handler.php', {
                                method: 'POST',
                                body: formData
                            });

                            const data = await response.json();

                            if (data.success) {
                                showModal('success', 'Event Created Successfully!',
                                    `Your event "${data.event_name}" has been created and is pending approval. You can make payment once the event is confirmed.`,
                                    true);
                                localStorage.removeItem('eventFormData');
                            } else {
                                showModal('error', 'Error Creating Event', data.error || 'Please try again.');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showModal('error', 'Connection Error',
                                'Unable to create event. Please check your connection and try again.');
                        }
                    });
                }

                // Form validation
                function validateForm() {
                    const eventName = document.getElementById('event_name').value.trim();
                    const eventType = document.getElementById('event_type').value;
                    const otherEventType = document.getElementById('other_event_type').value.trim();
                    const expectedGuests = document.getElementById('expected_guests').value;
                    const eventDate = document.getElementById('event_date').value;
                    const eventStartTime = document.getElementById('event_start_time').value;
                    const eventEndTime = document.getElementById('event_end_time').value;

                    if (!eventName) {
                        showAlert('error', 'Validation Error', 'Please enter an event name.');
                        return false;
                    }

                    if (!eventType) {
                        showAlert('error', 'Validation Error', 'Please select an event type.');
                        return false;
                    }

                    // Validate "Other" event type specification
                    if (eventType === 'Other' && !otherEventType) {
                        showAlert('error', 'Validation Error', 'Please specify what type of event this is.');
                        return false;
                    }

                    if (!expectedGuests || expectedGuests < 1) {
                        showAlert('error', 'Validation Error', 'Please enter a valid number of expected guests.');
                        return false;
                    }

                    if (!eventDate) {
                        showAlert('error', 'Validation Error', 'Please select an event date.');
                        return false;
                    }

                    // Validate date is not in the past
                    const selectedDate = new Date(eventDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (selectedDate < today) {
                        showAlert('error', 'Validation Error', 'Event date cannot be in the past.');
                        return false;
                    }

                    if (!eventStartTime) {
                        showAlert('error', 'Validation Error', 'Please select a start time.');
                        return false;
                    }

                    if (!eventEndTime) {
                        showAlert('error', 'Validation Error', 'Please select an end time.');
                        return false;
                    }

                    // Validate end time is after start time
                    if (convertToMinutes(eventEndTime) <= convertToMinutes(eventStartTime)) {
                        showAlert('error', 'Validation Error', 'End time must be after start time.');
                        return false;
                    }

                    // Check if date is in the future
                    const selectedDateTime = new Date(`${eventDate}T${eventStartTime}`);
                    const now = new Date();
                    if (selectedDateTime < now) {
                        showAlert('error', 'Validation Error', 'Event date and time must be in the future.');
                        return false;
                    }

                    return true;
                }

                // Show alert message
                function showAlert(type, title, message) {
                    const alertContainer = document.getElementById('alertContainer');

                    const alertColors = {
                        success: 'bg-green-100 border-green-500 text-green-800',
                        error: 'bg-red-100 border-red-500 text-red-800',
                        warning: 'bg-yellow-100 border-yellow-500 text-yellow-800',
                        info: 'bg-blue-100 border-blue-500 text-blue-800'
                    };

                    const alertIcons = {
                        success: 'fa-check-circle',
                        error: 'fa-exclamation-circle',
                        warning: 'fa-exclamation-triangle',
                        info: 'fa-info-circle'
                    };

                    const alertDiv = document.createElement('div');
                    alertDiv.className = `${alertColors[type]} border-l-4 p-4 rounded-lg shadow-lg mb-4 animate-fade-in`;
                    alertDiv.innerHTML = `
                    <div class="flex items-start">
                        <i class="fas ${alertIcons[type]} text-2xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <p class="font-bold text-lg">${title}</p>
                            <p class="mt-1">${message}</p>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="text-2xl ml-4 hover:opacity-70 transition-opacity">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;

                    alertContainer.innerHTML = '';
                    alertContainer.appendChild(alertDiv);

                    // Auto-remove after 5 seconds for success messages
                    if (type === 'success') {
                        setTimeout(() => {
                            alertDiv.remove();
                        }, 5000);
                    }

                    // Scroll to top to show alert
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }

                // Show modal function
                function showModal(type, title, message, redirectOnClose = false) {
                    const modal = document.getElementById('responseModal');
                    const modalTitle = document.getElementById('modal-title');
                    const modalMessage = document.getElementById('modal-message');
                    const modalIcon = document.getElementById('modalIcon');
                    const modalIconContainer = document.getElementById('modalIconContainer');
                    const modalCloseBtn = document.getElementById('modalCloseBtn');
                    const modalBackdrop = document.getElementById('modalBackdrop');

                    if (!modal) {
                        console.error('Modal not found');
                        return;
                    }

                    // Configure based on type
                    if (type === 'success') {
                        modalIconContainer.className =
                            'flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-green-100 rounded-full sm:mx-0 sm:h-10 sm:w-10';
                        modalIcon.className = 'text-2xl fas fa-check-circle text-green-600';
                        modalCloseBtn.className =
                            'inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors';
                    } else if (type === 'error') {
                        modalIconContainer.className =
                            'flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-red-100 rounded-full sm:mx-0 sm:h-10 sm:w-10';
                        modalIcon.className = 'text-2xl fas fa-exclamation-circle text-red-600';
                        modalCloseBtn.className =
                            'inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors';
                    }

                    modalTitle.textContent = title;
                    modalMessage.textContent = message;

                    // Prevent body scroll when modal is open
                    document.body.style.overflow = 'hidden';
                    modal.classList.remove('hidden');

                    // Close modal function
                    function closeModal() {
                        modal.classList.add('hidden');
                        document.body.style.overflow = '';
                        if (redirectOnClose) {
                            window.location.href = 'organizer-dashboard.php';
                        }
                    }

                    // Close modal handler
                    modalCloseBtn.onclick = closeModal;

                    // Close on backdrop click
                    if (modalBackdrop) {
                        modalBackdrop.onclick = closeModal;
                    }

                    // Close on escape key
                    const escapeHandler = function(e) {
                        if (e.key === 'Escape') {
                            closeModal();
                            document.removeEventListener('keydown', escapeHandler);
                        }
                    };
                    document.addEventListener('keydown', escapeHandler);
                }

                // Set minimum date to today
                const eventDateInput = document.getElementById('event_date');
                const eventStartTimeSelect = document.getElementById('event_start_time');
                const eventEndTimeSelect = document.getElementById('event_end_time');
                const dateAvailabilityMsg = document.getElementById('date-availability-msg');
                const timeAvailabilityMsg = document.getElementById('time-availability-msg');

                if (eventDateInput) {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const today = `${year}-${month}-${day}`;
                    eventDateInput.min = today;
                    eventDateInput.setAttribute('min', today);

                    // Check availability when date changes
                    eventDateInput.addEventListener('change', function() {
                        // Validate that the selected date is not in the past
                        const selectedDate = new Date(this.value);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);

                        if (selectedDate < today) {
                            showAlert('error', 'Invalid Date', 'You cannot select a date that has already passed.');
                            this.value = '';
                            eventStartTimeSelect.disabled = true;
                            eventStartTimeSelect.innerHTML =
                                '<option value="">Select date and venue first</option>';
                            eventEndTimeSelect.disabled = true;
                            eventEndTimeSelect.innerHTML = '<option value="">Select start time first</option>';
                            dateAvailabilityMsg.textContent = '';
                            timeAvailabilityMsg.textContent =
                                'Available time slots will appear after selecting a date and venue';
                            return;
                        }

                        checkVenueAvailability();
                    });
                }

                // Also check when venue changes
                document.addEventListener('click', function(e) {
                    if (e.target.matches('input[name="venue_id"]')) {
                        setTimeout(checkVenueAvailability, 100);
                    }
                });

                // Function to check venue availability
                async function checkVenueAvailability() {
                    const selectedVenue = document.querySelector('input[name="venue_id"]:checked');
                    const selectedDate = eventDateInput.value;

                    if (!selectedVenue || !selectedDate) {
                        eventStartTimeSelect.disabled = true;
                        eventStartTimeSelect.innerHTML = '<option value="">Select date and venue first</option>';
                        eventEndTimeSelect.disabled = true;
                        eventEndTimeSelect.innerHTML = '<option value="">Select start time first</option>';
                        dateAvailabilityMsg.textContent = '';
                        timeAvailabilityMsg.textContent =
                            'Available time slots will appear after selecting a date and venue';
                        return;
                    }

                    const venueId = selectedVenue.value;

                    try {
                        dateAvailabilityMsg.innerHTML =
                            '<i class="fas fa-spinner fa-spin"></i> Checking availability...';
                        dateAvailabilityMsg.className = 'mt-1 text-sm text-blue-600';

                        const response = await fetch(
                            `../../../src/services/check-venue-availability.php?venue_id=${venueId}&date=${selectedDate}`
                        );
                        const data = await response.json();

                        if (data.success) {
                            if (data.is_fully_booked) {
                                dateAvailabilityMsg.innerHTML =
                                    '<i class="fas fa-times-circle"></i> This date is fully booked';
                                dateAvailabilityMsg.className = 'mt-1 text-sm text-red-600 font-semibold';
                                eventStartTimeSelect.disabled = true;
                                eventStartTimeSelect.innerHTML = '<option value="">No available time slots</option>';
                                eventEndTimeSelect.disabled = true;
                                eventEndTimeSelect.innerHTML = '<option value="">Select start time first</option>';
                                timeAvailabilityMsg.textContent = 'This date is fully booked for this venue';
                                timeAvailabilityMsg.className = 'text-sm text-red-600';
                            } else {
                                populateAvailableTimeSlots(data.booked_slots);
                                dateAvailabilityMsg.innerHTML =
                                    '<i class="fas fa-check-circle"></i> Available slots found';
                                dateAvailabilityMsg.className = 'mt-1 text-sm text-green-600 font-semibold';
                            }
                        } else {
                            dateAvailabilityMsg.textContent = 'Error checking availability';
                            dateAvailabilityMsg.className = 'mt-1 text-sm text-red-600';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        dateAvailabilityMsg.textContent = 'Error checking availability';
                        dateAvailabilityMsg.className = 'mt-1 text-sm text-red-600';
                    }
                }

                // Function to populate available time slots
                function populateAvailableTimeSlots(bookedSlots) {
                    // Store booked slots globally for end time validation
                    currentBookedSlots = bookedSlots;

                    const timeSlots = generateTimeSlots();
                    const availableSlots = filterAvailableSlots(timeSlots, bookedSlots);

                    eventStartTimeSelect.disabled = false;
                    eventStartTimeSelect.innerHTML = '<option value="">Select start time</option>';
                    eventEndTimeSelect.disabled = true;
                    eventEndTimeSelect.innerHTML = '<option value="">Select start time first</option>';

                    if (availableSlots.length === 0) {
                        eventStartTimeSelect.disabled = true;
                        eventStartTimeSelect.innerHTML = '<option value="">No available time slots</option>';
                        timeAvailabilityMsg.textContent = 'All time slots are booked for this date';
                        timeAvailabilityMsg.className = 'text-sm text-red-600';
                    } else {
                        availableSlots.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot;
                            option.textContent = formatTime(slot);
                            eventStartTimeSelect.appendChild(option);
                        });
                        timeAvailabilityMsg.textContent = `${availableSlots.length} start time slot(s) available`;
                        timeAvailabilityMsg.className = 'text-sm text-green-600';
                    }
                }

                // Generate time slots from 7 AM to 7 PM (every 1 hour)
                function generateTimeSlots() {
                    const slots = [];
                    for (let hour = 7; hour <= 19; hour++) {
                        const time = `${hour.toString().padStart(2, '0')}:00:00`;
                        slots.push(time);
                    }
                    return slots;
                }

                // Filter out booked time slots
                function filterAvailableSlots(allSlots, bookedSlots) {
                    return allSlots.filter(slot => {
                        const slotTime = convertToMinutes(slot);

                        // Check if this slot conflicts with any booked slot
                        for (let booked of bookedSlots) {
                            const bookedStart = convertToMinutes(booked.start);
                            const bookedEnd = convertToMinutes(booked.end);

                            // A slot is unavailable if starting an event here would overlap with any booked event
                            // We need to check if any potential end time would conflict
                            // Since we don't know the event duration yet, we check if the slot falls within a booked period
                            if (slotTime >= bookedStart && slotTime < bookedEnd) {
                                return false; // This start time falls within a booked event
                            }
                        }
                        return true;
                    });
                }

                // Convert time string to minutes
                function convertToMinutes(timeStr) {
                    const [hours, minutes] = timeStr.split(':').map(Number);
                    return hours * 60 + minutes;
                }

                // Format time for display
                function formatTime(timeStr) {
                    const [hours, minutes] = timeStr.split(':');
                    const hour = parseInt(hours);
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                    return `${displayHour}:${minutes} ${ampm}`;
                }

                // Store booked slots globally to check against them
                let currentBookedSlots = [];

                // Handle start time selection to populate end time options
                if (eventStartTimeSelect) {
                    eventStartTimeSelect.addEventListener('change', function() {
                        const startTime = this.value;

                        if (!startTime) {
                            eventEndTimeSelect.disabled = true;
                            eventEndTimeSelect.innerHTML = '<option value="">Select start time first</option>';
                            return;
                        }

                        // Generate end time options (minimum 1 hour after start, maximum 7 PM)
                        const startMinutes = convertToMinutes(startTime);
                        const allTimeSlots = generateTimeSlots();

                        eventEndTimeSelect.disabled = false;
                        eventEndTimeSelect.innerHTML = '<option value="">Select end time</option>';

                        // Find the next booked event after the selected start time
                        let nextBookedStart = 19 * 60; // Default to 7 PM (closing time)
                        for (let booked of currentBookedSlots) {
                            const bookedStartMin = convertToMinutes(booked.start);
                            if (bookedStartMin > startMinutes && bookedStartMin < nextBookedStart) {
                                nextBookedStart = bookedStartMin;
                            }
                        }

                        let endOptionsAdded = 0;
                        allTimeSlots.forEach(slot => {
                            const slotMinutes = convertToMinutes(slot);
                            // End time must be:
                            // 1. At least 1 hour after start time
                            // 2. Before or at 7 PM (19:00)
                            // 3. Not extend into the next booked slot
                            if (slotMinutes > startMinutes && slotMinutes <= nextBookedStart && slotMinutes <= 19 * 60) {
                                const option = document.createElement('option');
                                option.value = slot;
                                option.textContent = formatTime(slot);
                                eventEndTimeSelect.appendChild(option);
                                endOptionsAdded++;
                            }
                        });

                        if (endOptionsAdded === 0) {
                            eventEndTimeSelect.disabled = true;
                            eventEndTimeSelect.innerHTML = '<option value="">No valid end times available</option>';
                        }
                    });
                }

                // Handle "Other" event type selection
                const eventTypeSelect = document.getElementById('event_type');
                const otherEventTypeContainer = document.getElementById('otherEventTypeContainer');
                const otherEventTypeInput = document.getElementById('other_event_type');

                if (eventTypeSelect && otherEventTypeContainer && otherEventTypeInput) {
                    eventTypeSelect.addEventListener('change', function() {
                        if (this.value === 'Other') {
                            otherEventTypeContainer.classList.remove('hidden');
                            otherEventTypeContainer.classList.add('animate-fade-in');
                            otherEventTypeInput.required = true;
                        } else {
                            otherEventTypeContainer.classList.add('hidden');
                            otherEventTypeContainer.classList.remove('animate-fade-in');
                            otherEventTypeInput.required = false;
                            otherEventTypeInput.value = '';
                        }
                    });
                }
            </script>

            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>