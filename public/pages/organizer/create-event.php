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

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = $_POST['event_name'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $theme = $_POST['theme'] ?? '';
    $expected_guests = (int)($_POST['expected_guests'] ?? 0);
    $event_date = $_POST['event_date'] ?? '';
    $venue_id = (int)($_POST['venue_id'] ?? 0);
    $total_cost = (float)($_POST['total_cost'] ?? 0);
    $selected_services = $_POST['selected_services'] ?? '';
    
    // Validate required fields
    if (empty($event_name) || empty($event_type) || $expected_guests <= 0 || empty($event_date) || $venue_id <= 0) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Format the datetime for MySQL
        $mysql_datetime = date('Y-m-d H:i:s', strtotime($event_date));
        
        // Start transaction
        $conn->autocommit(FALSE);
        try {
            // Insert event - 8 parameters for 8 placeholders
            $stmt = $conn->prepare("INSERT INTO events (event_name, event_type, theme, expected_guests, total_cost, event_date, coordinator_id, venue_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("sssidsii", $event_name, $event_type, $theme, $expected_guests, $total_cost, $mysql_datetime, $user_id, $venue_id);
            $stmt->execute();
            $event_id = $conn->insert_id;
            
            // Insert selected services
            if (!empty($selected_services)) {
                $service_ids = explode(',', $selected_services);
                foreach ($service_ids as $service_id) {
                    // Skip catering service (it's virtual)
                    if (strpos($service_id, 'catering-') === 0) {
                        continue;
                    }
                    
                    $service_id = (int)$service_id;
                    if ($service_id > 0) {
                        // Get service details
                        $service_stmt = $conn->prepare("SELECT price, supplier_id FROM services WHERE service_id = ?");
                        $service_stmt->bind_param("i", $service_id);
                        $service_stmt->execute();
                        $service_result = $service_stmt->get_result();
                        
                        if ($service_row = $service_result->fetch_assoc()) {
                            $insert_stmt = $conn->prepare("INSERT INTO event_services (event_id, service_id, supplier_id, price_at_booking, status) VALUES (?, ?, ?, ?, 'pending')");
                            $insert_stmt->bind_param("iiids", $event_id, $service_id, $service_row['supplier_id'], $service_row['price']);
                            $insert_stmt->execute();
                        }
                    }
                }
            }
            
            $conn->commit();
            $success_message = "Event booked successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error booking event: " . $e->getMessage();
        }
        $conn->autocommit(TRUE);
    }
}

// Fetch available venues
$venues_query = "SELECT venue_id, venue_name, capacity, base_price, location FROM venues WHERE availability_status = 'available' ORDER BY venue_name";
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
    <title>Book an Event | Gatherly</title>
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
    <style>
        .success-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .success-popup.show {
            opacity: 1;
            visibility: visible;
        }
        
        .popup-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        
        .success-popup.show .popup-content {
            transform: scale(1);
        }
        
        .popup-icon {
            width: 80px;
            height: 80px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .popup-icon i {
            font-size: 2.5rem;
            color: #16a34a;
        }
        
        .popup-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .popup-message {
            color: #4b5563;
            margin-bottom: 2rem;
        }
        
        .popup-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .popup-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .close-btn {
            background: #6b7280;
            color: white;
        }
        
        .close-btn:hover {
            background: #4b5563;
        }
        
        .view-events-btn {
            background: #4f46e5;
            color: white;
        }
        
        .view-events-btn:hover {
            background: #4338ca;
        }
    </style>
</head>

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-purple-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Success Popup -->
    <div id="successPopup" class="success-popup">
        <div class="popup-content">
            <div class="popup-icon">
                <i class="fas fa-check"></i>
            </div>
            <h3 class="popup-title">Event Booked Successfully!</h3>
            <p class="popup-message">Your event has been successfully booked. You can view your events or continue booking more events.</p>
            <div class="popup-buttons">
                <button class="popup-btn close-btn" onclick="closePopup()">Close</button>
                <a href="my-events.php" class="popup-btn view-events-btn">View My Events</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
        <!-- Top Bar for Sidebar Layout -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Book an Event</h1>
            <p class="text-sm text-gray-600">Plan your event with venue and service selection</p>
        </div>
        <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
            <!-- Header for Navbar Layout -->
            <div class="mb-8">
                <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Book an Event</h1>
                <p class="text-gray-600">Plan your event with venue and service selection</p>
            </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <!-- Scrollable Main Content Area -->
            <div class="mx-auto overflow-y-auto" style="max-height: calc(100vh - 100px);">

                <!-- Create Event Form -->
                <form id="createEventForm" class="max-w-5xl mx-auto" method="POST">
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
                                            placeholder="e.g., Mike & Anna Wedding"
                                            value="<?php echo htmlspecialchars($_POST['event_name'] ?? ''); ?>">
                                    </div>

                                    <!-- Event Type & Theme -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Event Type
                                                <span class="text-red-500">*</span></label>
                                            <select id="event_type" name="event_type" required
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <option value="">Select Type</option>
                                                <option value="Wedding" <?php echo (($_POST['event_type'] ?? '') === 'Wedding') ? 'selected' : ''; ?>>Wedding</option>
                                                <option value="Corporate" <?php echo (($_POST['event_type'] ?? '') === 'Corporate') ? 'selected' : ''; ?>>Corporate Event</option>
                                                <option value="Birthday" <?php echo (($_POST['event_type'] ?? '') === 'Birthday') ? 'selected' : ''; ?>>Birthday Party</option>
                                                <option value="Concert" <?php echo (($_POST['event_type'] ?? '') === 'Concert') ? 'selected' : ''; ?>>Concert</option>
                                                <option value="Other" <?php echo (($_POST['event_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Theme</label>
                                            <input type="text" id="theme" name="theme"
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                placeholder="e.g., Rustic Garden"
                                                value="<?php echo htmlspecialchars($_POST['theme'] ?? ''); ?>">
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
                                                placeholder="e.g., 150"
                                                value="<?php echo htmlspecialchars($_POST['expected_guests'] ?? ''); ?>">
                                        </div>
                                        <div>
                                            <label class="block mb-2 text-sm font-semibold text-gray-700">Event Date &
                                                Time
                                                <span class="text-red-500">*</span></label>
                                            <input type="datetime-local" id="event_date" name="event_date" required
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>">
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

                                <!-- Searchable Service Input -->
                                <div class="mb-6">
                                    <label class="block mb-2 text-sm font-semibold text-gray-700">Search Services</label>
                                    <div class="relative">
                                        <input type="text" id="service-search" 
                                            placeholder="Search for services (e.g., catering, photography)"
                                            class="w-full px-4 py-3 pr-10 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            autocomplete="off">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                            <i class="text-gray-400 fas fa-search"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Service Recommendations Dropdown -->
                                    <div id="service-recommendations" class="mt-2 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg hidden">
                                        <!-- Recommendations will be populated dynamically -->
                                    </div>
                                </div>

                                <!-- Selected Services List -->
                                <div class="mb-6">
                                    <h3 class="mb-3 text-lg font-semibold text-gray-800">Selected Services</h3>
                                    <div id="selected-services-list" class="space-y-3">
                                        <!-- Selected services will appear here -->
                                        <div id="no-services-message" class="p-4 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
                                            No services selected yet. Use the search above to add services.
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden input to store selected service IDs -->
                                <input type="hidden" id="selected-services" name="selected_services" value="">
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
                                    Book an Event
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
            // Show success popup if there's a success message
            <?php if (!empty($success_message)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    document.getElementById('successPopup').classList.add('show');
                }, 100);
            });
            <?php endif; ?>
            
            function closePopup() {
                document.getElementById('successPopup').classList.remove('show');
            }

            // Global variables to track selected services
            let selectedServices = [];
            
            // Function to update cost summary
            function updateCostSummary() {
                const selectedVenue = document.querySelector('input[name="venue_id"]:checked');
                const venueCost = selectedVenue ? parseFloat(document.getElementById('total_cost').value) : 0;

                let servicesCost = 0;
                selectedServices.forEach(service => {
                    servicesCost += parseFloat(service.price) || 0;
                });

                const total = venueCost + servicesCost;

                // Update UI elements
                document.getElementById('venue-cost').textContent = '₱' + venueCost.toFixed(2);
                document.getElementById('services-cost').textContent = '₱' + servicesCost.toFixed(2);
                document.getElementById('total-cost').textContent = '₱' + total.toFixed(2);
                document.getElementById('total_cost').value = total.toFixed(2);
                
                // Update hidden input with selected service IDs
                const serviceIds = selectedServices.map(service => service.id).join(',');
                document.getElementById('selected-services').value = serviceIds;
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

            // Initialize service data from PHP
            const allServices = [
                <?php foreach ($services_by_category as $category => $services): ?>
                    <?php foreach ($services as $service): ?>
                        {
                            id: <?php echo $service['service_id']; ?>,
                            name: "<?php echo addslashes($service['service_name']); ?>",
                            category: "<?php echo addslashes($service['category']); ?>",
                            description: "<?php echo addslashes($service['description']); ?>",
                            price: <?php echo $service['price']; ?>,
                            supplier: "<?php echo addslashes($service['supplier_name']); ?>",
                            location: "<?php echo addslashes($service['location']); ?>"
                        },
                    <?php endforeach; ?>
                <?php endforeach; ?>
            ];
            
            // Add catering service to recommendations (with unique ID)
            const cateringService = {
                id: 'catering-' + Date.now(), // Unique ID for catering service
                name: "Catering Service",
                category: "Catering",
                description: "Basic catering package for your event",
                price: 3000,
                supplier: "Event Catering Co.",
                location: "Metro Manila"
            };
            
            // Add catering service to allServices array
            allServices.push(cateringService);

            // Service search functionality
            const serviceSearchInput = document.getElementById('service-search');
            const serviceRecommendations = document.getElementById('service-recommendations');
            
            serviceSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim().toLowerCase();
                
                if (searchTerm.length > 0) {
                    // Filter services based on search term
                    const filteredServices = allServices.filter(service => {
                        return service.name.toLowerCase().includes(searchTerm) || 
                               service.category.toLowerCase().includes(searchTerm) ||
                               service.supplier.toLowerCase().includes(searchTerm) ||
                               service.description.toLowerCase().includes(searchTerm);
                    });
                    
                    // Show recommendations if there are results
                    if (filteredServices.length > 0) {
                        serviceRecommendations.classList.remove('hidden');
                        renderServiceRecommendations(filteredServices);
                    } else {
                        serviceRecommendations.classList.add('hidden');
                    }
                } else {
                    serviceRecommendations.classList.add('hidden');
                }
            });

            // Hide recommendations when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#service-search') && !e.target.closest('#service-recommendations')) {
                    serviceRecommendations.classList.add('hidden');
                }
            });

            // Render service recommendations
            function renderServiceRecommendations(services) {
                serviceRecommendations.innerHTML = '';
                
                services.forEach(service => {
                    const serviceDiv = document.createElement('div');
                    serviceDiv.className = 'p-3 border-b border-gray-100 cursor-pointer hover:bg-indigo-50';
                    serviceDiv.innerHTML = `
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">${getServiceIcon(service.category)}</span>
                                    <h4 class="font-semibold text-gray-800">${service.name}</h4>
                                </div>
                                <p class="text-sm text-gray-600">${service.supplier} - ${service.location}</p>
                                <p class="mt-1 text-xs text-gray-500">${service.description}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-green-600">₱${service.price.toFixed(2)}</span>
                                <button type="button" class="px-2 py-1 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 select-service-btn" data-service-id="${service.id}">
                                    Select
                                </button>
                            </div>
                        </div>
                    `;
                    
                    serviceRecommendations.appendChild(serviceDiv);
                });
                
                // Add click handlers to select buttons
                document.querySelectorAll('.select-service-btn').forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const serviceId = this.getAttribute('data-service-id');
                        const service = allServices.find(s => s.id == serviceId);
                        
                        if (service && !selectedServices.some(s => s.id == service.id)) {
                            selectedServices.push(service);
                            renderSelectedServices();
                            updateCostSummary();
                            serviceSearchInput.value = '';
                            serviceRecommendations.classList.add('hidden');
                        }
                    });
                });
            }

            // Get service icon based on category
            function getServiceIcon(category) {
                const icons = {
                    'Catering': '🍽️',
                    'Lights and Sounds': '🎵',
                    'Photography': '📸',
                    'Videography': '🎥',
                    'Host/Emcee': '🎤',
                    'Styling and Flowers': '💐',
                    'Equipment Rental': '🪑'
                };
                return icons[category] || '📋';
            }

            // Render selected services list
            function renderSelectedServices() {
                const selectedServicesList = document.getElementById('selected-services-list');
                const noServicesMessage = document.getElementById('no-services-message');
                
                if (selectedServices.length === 0) {
                    selectedServicesList.innerHTML = '';
                    selectedServicesList.appendChild(noServicesMessage);
                    // Just call updateCostSummary() - it will handle setting costs to 0
                    updateCostSummary();
                    return;
                }
                
                // Clear existing content
                selectedServicesList.innerHTML = '';
                
                selectedServices.forEach((service, index) => {
                    const serviceDiv = document.createElement('div');
                    serviceDiv.className = 'p-3 border border-gray-200 rounded-lg bg-white flex items-start justify-between';
                    serviceDiv.innerHTML = `
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">${getServiceIcon(service.category)}</span>
                                <h4 class="font-semibold text-gray-800">${service.name}</h4>
                            </div>
                            <p class="text-sm text-gray-600">${service.supplier} - ${service.location}</p>
                            <p class="mt-1 text-xs text-gray-500">${service.description}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold text-green-600">₱${service.price.toFixed(2)}</span>
                        </div>
                    `;
                    
                    selectedServicesList.appendChild(serviceDiv);
                });
            }

            // Initialize the page
            document.addEventListener('DOMContentLoaded', function() {
                // Initial rendering of selected services (if any)
                renderSelectedServices();
            });
            </script>

            <?php if ($nav_layout === 'sidebar'): ?>
        </div>
        <?php endif; ?>
    </div>
</body>

</html>