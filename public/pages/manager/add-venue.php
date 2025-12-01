<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Manager';
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venue_name = $_POST['venue_name'];
    $capacity = intval($_POST['capacity']);
    $base_price = floatval($_POST['base_price']);
    $price_percentage = floatval($_POST['price_percentage']);
    $description = $_POST['description'];
    $suitable_themes = $_POST['suitable_themes'] ?? '';
    $venue_type = $_POST['venue_type'] ?? '';
    $ambiance = $_POST['ambiance'] ?? '';
    $availability_status = $_POST['availability_status'] ?? 'available';
    $status = $_POST['status'] ?? 'active';
    $selected_amenities = $_POST['amenities'] ?? [];
    $two_wheels = isset($_POST['two_wheels']) ? intval($_POST['two_wheels']) : 0;
    $four_wheels = isset($_POST['four_wheels']) ? intval($_POST['four_wheels']) : 0;
    $manager_id = $_SESSION['user_id'];

    // Get location data from form
    $baranggay = trim($_POST['baranggay']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);

    // Insert or get location
    $stmt = $conn->prepare("SELECT location_id FROM locations WHERE city = ? AND province = ? AND baranggay = ?");
    $stmt->bind_param("sss", $city, $province, $baranggay);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $location = $result->fetch_assoc();
        $location_id = $location['location_id'];

        // Update coordinates for existing location
        $updateStmt = $conn->prepare("UPDATE locations SET latitude = ?, longitude = ? WHERE location_id = ?");
        $updateStmt->bind_param("ssi", $latitude, $longitude, $location_id);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Insert new location
        $insertStmt = $conn->prepare("INSERT INTO locations (city, province, baranggay, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sssss", $city, $province, $baranggay, $latitude, $longitude);
        $insertStmt->execute();
        $location_id = $conn->insert_id;
        $insertStmt->close();
    }
    $stmt->close();

    // Compute dynamic pricing
    $peak_price = $base_price + ($base_price * ($price_percentage / 100));
    $offpeak_price = $base_price - ($base_price * ($price_percentage / 100));
    $weekday_price = $base_price;
    $weekend_price = $peak_price;

    // Handle image upload 
    $imageData = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $imageData = file_get_contents($_FILES['image']['tmp_name']);
    }

    // Insert venue with new schema
    $stmt = $conn->prepare("INSERT INTO venues 
        (venue_name, location_id, capacity, description, suitable_themes, venue_type, ambiance, availability_status, status, image, manager_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssssssi", $venue_name, $location_id, $capacity, $description, $suitable_themes, $venue_type, $ambiance, $availability_status, $status, $imageData, $manager_id);
    if ($imageData) {
        $stmt->send_long_data(9, $imageData);
    }
    $stmt->execute();

    $venue_id = $conn->insert_id;
    $stmt->close();

    // Insert pricing into separate prices table
    $stmtPrice = $conn->prepare("INSERT INTO prices (venue_id, base_price, peak_price, offpeak_price, weekday_price, weekend_price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtPrice->bind_param("iddddd", $venue_id, $base_price, $peak_price, $offpeak_price, $weekday_price, $weekend_price);
    $stmtPrice->execute();
    $stmtPrice->close();

    // Insert parking information
    $insertParkingSQL = "INSERT INTO parking (venue_id, two_wheels, four_wheels) VALUES ($venue_id, $two_wheels, $four_wheels)";
    $conn->query($insertParkingSQL);

    // Insert selected standard amenities with custom prices
    if (!empty($selected_amenities)) {
        foreach ($selected_amenities as $amenityName => $data) {
            if (!empty($data['enabled'])) {
                $price = floatval($data['price']);

                $stmt = $conn->prepare("SELECT amenity_id, default_price FROM amenities WHERE amenity_name=?");
                $stmt->bind_param("s", $amenityName);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $amenityRow = $result->fetch_assoc();
                    $amenity_id = $amenityRow['amenity_id'];
                    $default_price = floatval($amenityRow['default_price']);
                    $custom_price = ($price !== $default_price) ? $price : NULL;

                    $stmtInsert = $conn->prepare("INSERT INTO venue_amenities (venue_id, amenity_id, custom_price) VALUES (?, ?, ?)");
                    $stmtInsert->bind_param("iid", $venue_id, $amenity_id, $custom_price);
                    $stmtInsert->execute();
                    $stmtInsert->close();
                }
                $stmt->close();
            }
        }
    }

    // Handle custom amenities
    if (isset($_POST['custom_amenities']) && is_array($_POST['custom_amenities'])) {
        foreach ($_POST['custom_amenities'] as $customAmenity) {
            if (!empty($customAmenity['name']) && isset($customAmenity['price'])) {
                $amenityName = $conn->real_escape_string(trim($customAmenity['name']));
                $amenityPrice = floatval($customAmenity['price']);

                $checkStmt = $conn->prepare("SELECT amenity_id FROM amenities WHERE amenity_name=?");
                $checkStmt->bind_param("s", $amenityName);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    $row = $checkResult->fetch_assoc();
                    $amenity_id = $row['amenity_id'];
                } else {
                    $insertAmenityStmt = $conn->prepare("INSERT INTO amenities (amenity_name, default_price) VALUES (?, ?)");
                    $insertAmenityStmt->bind_param("sd", $amenityName, $amenityPrice);
                    $insertAmenityStmt->execute();
                    $amenity_id = $insertAmenityStmt->insert_id;
                    $insertAmenityStmt->close();
                }

                $insertVenueAmenity = $conn->prepare("INSERT INTO venue_amenities (venue_id, amenity_id, custom_price) VALUES (?, ?, ?)");
                $insertVenueAmenity->bind_param("iid", $venue_id, $amenity_id, $amenityPrice);
                $insertVenueAmenity->execute();
                $insertVenueAmenity->close();

                $checkStmt->close();
            }
        }
    }

    $_SESSION['venue_success'] = 'added';
    header("Location: my-venues.php");
    exit();
}

// Fetch all amenities from database
$allAmenities = [];
$amenityResult = $conn->query("SELECT * FROM amenities ORDER BY amenity_id ASC");
if ($amenityResult && $amenityResult->num_rows > 0) {
    while ($row = $amenityResult->fetch_assoc()) {
        $allAmenities[$row['amenity_name']] = floatval($row['default_price']);
    }
}
?>

<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Venue | Gatherly</title>
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
    #map {
        height: 400px;
        width: 100%;
        border-radius: 0.5rem;
    }
    </style>
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
                <h1 class="text-2xl font-bold text-gray-800">Add New Venue</h1>
                <p class="text-sm text-gray-600">Fill in the details to add a new venue to your listings</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="container px-6 py-10 mx-auto sm:px-6 lg:px-8">
                    <?php endif; ?>
                    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md border border-gray-200 p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div
                                class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-800">Add New Venue</h1>
                                <p class="text-sm text-gray-500">Fill in the details to add a new venue to your listings
                                </p>
                            </div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <!-- Hidden fields for coordinates -->
                            <input type="hidden" id="latitude" name="latitude" required>
                            <input type="hidden" id="longitude" name="longitude" required>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Venue Name</label>
                                    <input type="text" name="venue_name" placeholder="e.g., Aurora Pavilion" required
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Capacity</label>
                                    <input type="number" name="capacity" placeholder="e.g., 150" min="1" required
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
                            </div>

                            <!-- Location Section with Map -->
                            <div class="border-2 border-green-200 rounded-lg p-5 bg-green-50">
                                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-green-600"></i>
                                    Venue Location
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700">Barangay *</label>
                                        <input type="text" id="baranggay" name="baranggay" placeholder="e.g., Poblacion"
                                            required
                                            class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500 bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700">City *</label>
                                        <input type="text" id="city" name="city" placeholder="e.g., Batangas City"
                                            required
                                            class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500 bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700">Province *</label>
                                        <input type="text" id="province" name="province" placeholder="e.g., Batangas"
                                            required
                                            class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500 bg-white">
                                    </div>
                                </div>

                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="block text-sm font-semibold text-gray-700">
                                            <i class="fas fa-map mr-2 text-green-600"></i>Pin Venue Location on Map *
                                        </label>
                                        <button type="button" onclick="useCurrentLocation()"
                                            class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1">
                                            <i class="fas fa-crosshairs"></i>
                                            Use My Location
                                        </button>
                                    </div>
                                    <div id="map" class="mb-3"></div>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                        Click on the map to pin your venue's exact location. You can also use the search
                                        box or your current GPS location.
                                    </p>
                                    <div id="coordinatesDisplay" class="mt-2 text-xs text-gray-500 hidden">
                                        <i class="fas fa-map-pin text-green-600 mr-1"></i>
                                        Selected: <span id="coordText"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Venue Type</label>
                                    <select name="venue_type"
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">Select Type</option>
                                        <option value="Garden">Garden</option>
                                        <option value="Ballroom">Ballroom</option>
                                        <option value="Resort">Resort</option>
                                        <option value="Hotel">Hotel</option>
                                        <option value="Beach">Beach</option>
                                        <option value="Hall">Hall</option>
                                        <option value="Restaurant">Restaurant</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Ambiance</label>
                                    <select name="ambiance"
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">Select Ambiance</option>
                                        <option value="Elegant">Elegant</option>
                                        <option value="Rustic">Rustic</option>
                                        <option value="Modern">Modern</option>
                                        <option value="Classic">Classic</option>
                                        <option value="Tropical">Tropical</option>
                                        <option value="Romantic">Romantic</option>
                                        <option value="Casual">Casual</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Base Price (₱)</label>
                                    <input type="number" id="base_price" name="base_price" placeholder="e.g., 50000"
                                        step="0.01" required
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Price Percentage
                                        (%)</label>
                                    <input type="number" id="price_percentage" name="price_percentage"
                                        placeholder="e.g., 15" step="0.01" min="0"
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Availability</label>
                                    <select name="availability_status"
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="available">Available</option>
                                        <option value="unavailable">Unavailable</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Parking Information -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-parking text-blue-600 mr-2"></i>Parking Capacity
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-2">
                                            <i class="fas fa-motorcycle text-gray-500 mr-1"></i>Two-Wheel Vehicles
                                        </label>
                                        <input type="number" name="two_wheels" value="0"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500"
                                            min="0" placeholder="Number of motorcycle/bike slots">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-2">
                                            <i class="fas fa-car text-gray-500 mr-1"></i>Four-Wheel Vehicles
                                        </label>
                                        <input type="number" name="four_wheels" value="0"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500"
                                            min="0" placeholder="Number of car parking slots">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Suitable Themes
                                    (comma-separated)</label>
                                <input type="text" name="suitable_themes"
                                    placeholder="e.g., Wedding, Corporate, Birthday"
                                    class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                <p class="text-xs text-gray-500 mt-1">Enter event themes this venue is suitable for</p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Peak Price (₱)</label>
                                    <input type="text" id="peak_price" readonly placeholder="Auto"
                                        class="w-full mt-2 bg-gray-100 border border-gray-300 px-3 py-2 rounded-md text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Off-Peak Price (₱)</label>
                                    <input type="text" id="offpeak_price" readonly placeholder="Auto"
                                        class="w-full mt-2 bg-gray-100 border border-gray-300 px-3 py-2 rounded-md text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Weekday Price (₱)</label>
                                    <input type="text" id="weekday_price" readonly placeholder="Auto"
                                        class="w-full mt-2 bg-gray-100 border border-gray-300 px-3 py-2 rounded-md text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600">Weekend Price (₱)</label>
                                    <input type="text" id="weekend_price" readonly placeholder="Auto"
                                        class="w-full mt-2 bg-gray-100 border border-gray-300 px-3 py-2 rounded-md text-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Description</label>
                                <textarea name="description" rows="3" placeholder="Describe the venue..."
                                    class="w-full mt-2 border border-gray-300 px-4 py-2.5 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Venue Image</label>
                                <label
                                    class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition relative">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-sm text-gray-600">Click to upload image</p>
                                    <p class="text-xs text-gray-400">PNG, JPG up to 10MB</p>
                                    <input type="file" id="imageInput" name="image" accept="image/*" class="hidden">
                                </label>
                                <p id="imageStatus" class="text-xs text-green-600 mt-2 hidden font-medium"></p>
                            </div>

                            <!-- Amenities Section -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-star text-yellow-500 mr-2"></i>Amenities
                                </label>

                                <!-- Standard Amenities -->
                                <div id="standardAmenities"
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4 bg-gray-50 mb-4">
                                    <?php foreach ($allAmenities as $amenityName => $defaultPrice): ?>
                                    <div
                                        class="flex items-center justify-between gap-3 p-3 rounded-lg hover:bg-green-50 transition-all border border-gray-200 bg-white">
                                        <label class="flex items-center gap-3 flex-1 cursor-pointer">
                                            <input type="checkbox"
                                                name="amenities[<?php echo htmlspecialchars($amenityName); ?>][enabled]"
                                                class="checkbox-amenity w-5 h-5 text-green-600 rounded focus:ring-2 focus:ring-green-500"
                                                value="1" data-default-price="<?php echo $defaultPrice; ?>">
                                            <span
                                                class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($amenityName); ?></span>
                                        </label>
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-gray-500">₱</span>
                                            <input type="number"
                                                name="amenities[<?php echo htmlspecialchars($amenityName); ?>][price]"
                                                value="<?php echo $defaultPrice; ?>"
                                                class="w-20 text-sm border border-gray-300 rounded p-2 text-right shadow-sm focus:ring-2 focus:ring-green-500"
                                                step="0.01">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Custom Amenities -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-gray-700">Custom Amenities</h4>
                                        <button type="button" id="addCustomAmenity"
                                            class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all flex items-center gap-2">
                                            <i class="fas fa-plus"></i> Add Custom Amenity
                                        </button>
                                    </div>
                                    <div id="customAmenitiesContainer" class="space-y-3">
                                        <!-- Custom amenities will be added here dynamically -->
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <a href="my-venues.php"
                                    class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</a>
                                <button type="submit"
                                    class="px-6 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white font-semibold shadow-md transition">
                                    <i class="fas fa-plus-circle mr-1"></i> Add Venue
                                </button>
                            </div>
                        </form>
                    </div>
                    ```

                </div>

                <?php include '../../../src/components/footer.php'; ?>

                <!-- Google Maps API -->
                <script>
                (g => {
                    var h, a, k, p = "The Google Maps JavaScript API",
                        c = "google",
                        l = "importLibrary",
                        q = "__ib__",
                        m = document,
                        b = window;
                    b = b[c] || (b[c] = {});
                    var d = b.maps || (b.maps = {}),
                        r = new Set,
                        e = new URLSearchParams,
                        u = () => h || (h = new Promise(async (f, n) => {
                            await (a = m.createElement("script"));
                            e.set("libraries", [...r] + "");
                            for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[
                                k]);
                            e.set("callback", c + ".maps." + q);
                            a.src = `https://maps.googleapis.com/maps/api/js?` + e;
                            d[q] = f;
                            a.onerror = () => h = n(Error(p + " could not load."));
                            a.nonce = m.querySelector("script[nonce]")?.nonce || "";
                            m.head.append(a)
                        }));
                    d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u()
                        .then(() =>
                            d[l](f, ...n))
                })({
                    key: "YOUR_API_KEY_HERE",
                    v: "weekly"
                });
                </script>

                <script>
                let map;
                let marker;
                let geocoder;
                let searchBox;

                async function initMap() {
                    const {
                        Map
                    } = await google.maps.importLibrary("maps");
                    const {
                        AdvancedMarkerElement
                    } = await google.maps.importLibrary("marker");
                    const {
                        Geocoder
                    } = await google.maps.importLibrary("geocoding");

                    geocoder = new Geocoder();

                    // Default to Philippines center
                    const defaultLocation = {
                        lat: 14.5995,
                        lng: 120.9842
                    };

                    map = new Map(document.getElementById('map'), {
                        center: defaultLocation,
                        zoom: 12,
                        mapId: 'VENUE_ADD_MAP',
                        mapTypeControl: true,
                        streetViewControl: true,
                        fullscreenControl: true
                    });

                    // Add click listener to map
                    map.addListener('click', (event) => {
                        placeMarker(event.latLng);
                    });

                    // Create search box
                    const input = document.createElement('input');
                    input.setAttribute('type', 'text');
                    input.setAttribute('placeholder', 'Search for a location...');
                    input.className =
                        'px-4 py-2 mt-2 ml-2 border border-gray-300 rounded-lg shadow-sm w-80 focus:ring-2 focus:ring-green-500 focus:outline-none';

                    const {
                        SearchBox
                    } = await google.maps.importLibrary("places");
                    searchBox = new SearchBox(input);
                    map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

                    // Bias SearchBox results to map viewport
                    map.addListener('bounds_changed', () => {
                        searchBox.setBounds(map.getBounds());
                    });

                    searchBox.addListener('places_changed', () => {
                        const places = searchBox.getPlaces();
                        if (places.length === 0) return;

                        const place = places[0];
                        if (!place.geometry || !place.geometry.location) return;

                        placeMarker(place.geometry.location);
                        map.setCenter(place.geometry.location);
                        map.setZoom(15);
                    });
                }

                function placeMarker(location) {
                    // Remove existing marker if any
                    if (marker) {
                        marker.map = null;
                    }

                    // Create custom marker
                    const markerElement = document.createElement('div');
                    markerElement.className =
                        'bg-green-600 text-white px-3 py-2 rounded-full font-bold shadow-lg flex items-center gap-2';
                    markerElement.innerHTML = `
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Venue</span>
                            `;

                    marker = new google.maps.marker.AdvancedMarkerElement({
                        map: map,
                        position: location,
                        content: markerElement,
                        title: 'Venue Location'
                    });

                    // Update hidden fields
                    const lat = location.lat();
                    const lng = location.lng();
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;

                    // Update display
                    document.getElementById('coordinatesDisplay').classList.remove('hidden');
                    document.getElementById('coordText').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

                    // Reverse geocode to get address
                    geocoder.geocode({
                        location: location
                    }, (results, status) => {
                        if (status === 'OK' && results[0]) {
                            const addressComponents = results[0].address_components;

                            // Extract components
                            let barangay = '';
                            let city = '';
                            let province = '';

                            for (const component of addressComponents) {
                                if (component.types.includes('sublocality') || component.types.includes(
                                        'sublocality_level_1')) {
                                    barangay = component.long_name;
                                }
                                if (component.types.includes('locality') || component.types.includes(
                                        'administrative_area_level_2')) {
                                    city = component.long_name;
                                }
                                if (component.types.includes('administrative_area_level_1')) {
                                    province = component.long_name;
                                }
                            }

                            // Auto-fill location fields
                            if (barangay) document.getElementById('baranggay').value = barangay;
                            if (city) document.getElementById('city').value = city;
                            if (province) document.getElementById('province').value = province;
                        }
                    });
                }

                function useCurrentLocation() {
                    if (!navigator.geolocation) {
                        alert('Geolocation is not supported by your browser.');
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const location = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            };
                            placeMarker(new google.maps.LatLng(location.lat, location.lng));
                            map.setCenter(location);
                            map.setZoom(15);
                        },
                        (error) => {
                            alert('Unable to get your location. Please pin manually on the map.');
                        }
                    );
                }

                // Initialize map on load
                initMap();

                // Dynamic pricing auto-compute
                const baseInput = document.getElementById('base_price');
                const percentInput = document.getElementById('price_percentage');
                const peak = document.getElementById('peak_price');
                const offpeak = document.getElementById('offpeak_price');
                const weekday = document.getElementById('weekday_price');
                const weekend = document.getElementById('weekend_price');

                function computePrices() {
                    const base = parseFloat(baseInput.value) || 0;
                    const percent = parseFloat(percentInput.value) || 0;
                    const peakPrice = base + (base * (percent / 100));
                    const offpeakPrice = base - (base * (percent / 100));
                    const weekdayPrice = base;
                    const weekendPrice = peakPrice;

                    peak.value = peakPrice.toFixed(2);
                    offpeak.value = offpeakPrice.toFixed(2);
                    weekday.value = weekdayPrice.toFixed(2);
                    weekend.value = weekendPrice.toFixed(2);
                }

                baseInput.addEventListener('input', computePrices);
                percentInput.addEventListener('input', computePrices);

                // Custom amenity functionality
                let customAmenityCounter = 0;
                document.getElementById('addCustomAmenity').addEventListener('click', function() {
                    const container = document.getElementById('customAmenitiesContainer');
                    const amenityId = `custom_${customAmenityCounter++}`;

                    const amenityRow = document.createElement('div');
                    amenityRow.className =
                        'flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white';
                    amenityRow.innerHTML = `
                                <div class="flex-1">
                                    <input type="text" 
                                        name="custom_amenities[${amenityId}][name]" 
                                        placeholder="Amenity name (e.g., Pool, Sauna)"
                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-2 focus:ring-green-500"
                                        required>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-gray-500">₱</span>
                                    <input type="number" 
                                        name="custom_amenities[${amenityId}][price]" 
                                        placeholder="0.00"
                                        class="w-24 border border-gray-300 rounded-lg p-2 text-sm text-right shadow-sm focus:ring-2 focus:ring-green-500"
                                        step="0.01"
                                        min="0"
                                        required>
                                </div>
                                <button type="button" 
                                    class="remove-custom-amenity text-red-600 hover:text-red-700 px-2 py-1"
                                    onclick="this.parentElement.remove()">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            `;

                    container.appendChild(amenityRow);
                });

                // Handle amenity checkbox changes
                document.querySelectorAll('.checkbox-amenity').forEach(cb => {
                    cb.addEventListener('change', (e) => {
                        const priceInput = e.target.closest('div').parentElement.querySelector(
                            'input[type=number]');
                        if (e.target.checked) {
                            priceInput.value = e.target.dataset.defaultPrice;
                        } else {
                            priceInput.value = 0;
                        }
                    });
                });

                const imageInput = document.getElementById('imageInput');
                const imageStatus = document.getElementById('imageStatus');
                imageInput.addEventListener('change', () => {
                    if (imageInput.files && imageInput.files.length > 0) {
                        imageStatus.textContent = `Image selected: ${imageInput.files[0].name}`;
                        imageStatus.classList.remove('hidden');
                    } else {
                        imageStatus.textContent = '';
                        imageStatus.classList.add('hidden');
                    }
                });

                // Profile dropdown handled by ManagerSidebar.php
                </script>

                <?php if ($nav_layout === 'sidebar'): ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Success Modal -->
        <div id="successModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <i class="fas fa-check text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Success!</h3>
                    <p class="text-gray-600 mb-6">Venue has been added successfully.</p>
                    <button onclick="closeSuccessModal()"
                        class="w-full px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                        Continue
                    </button>
                </div>
            </div>
        </div>

        <script>
        function showSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.querySelector('.bg-white').classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.querySelector('.bg-white').classList.remove('scale-100', 'opacity-100');
            setTimeout(() => {
                modal.classList.add('hidden');
                window.location = 'my-venues.php';
            }, 200);
        }
        </script>

</body>

</html>