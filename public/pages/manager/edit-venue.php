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

// Check if venue_id is provided
if (!isset($_GET['id'])) {
    header("Location: my-venues.php");
    exit();
}

$venue_id = intval($_GET['id']);

// UPDATE VENUE + AMENITIES
if (isset($_POST['update_venue'])) {
    $venue_name = $conn->real_escape_string($_POST['venue_name']);

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
        $updateStmt->bind_param("ddi", $latitude, $longitude, $location_id);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Insert new location
        $insertStmt = $conn->prepare("INSERT INTO locations (city, province, baranggay, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sssdd", $city, $province, $baranggay, $latitude, $longitude);
        $insertStmt->execute();
        $location_id = $conn->insert_id;
        $insertStmt->close();
    }
    $stmt->close();

    $capacity = intval($_POST['capacity']);
    $base_price = floatval($_POST['base_price']);
    $description = $conn->real_escape_string($_POST['description']);
    $suitable_themes = $conn->real_escape_string($_POST['suitable_themes'] ?? '');
    $venue_type = $conn->real_escape_string($_POST['venue_type'] ?? '');
    $ambiance = $conn->real_escape_string($_POST['ambiance'] ?? '');
    $availability_status = $_POST['availability_status'];
    $status = $_POST['status'] ?? 'active';
    $price_percentage = floatval($_POST['price_percentage']);
    $two_wheels = isset($_POST['two_wheels']) ? intval($_POST['two_wheels']) : 0;
    $four_wheels = isset($_POST['four_wheels']) ? intval($_POST['four_wheels']) : 0;

    // Derived prices
    $peak_price = $base_price * (1 + $price_percentage / 100);
    $offpeak_price = $base_price * 0.8;
    $weekday_price = $base_price;
    $weekend_price = $peak_price;

    // Handle image upload if provided
    $imageSQL = '';
    if (!empty($_FILES['image']['tmp_name'])) {
        $imageData = addslashes(file_get_contents($_FILES['image']['tmp_name']));
        $imageSQL = ", image='$imageData'";
    }

    // Update venue info with new schema
    $updateVenueSQL = "UPDATE venues SET 
        venue_name='$venue_name', 
        location_id=$location_id, 
        capacity=$capacity,
        description='$description',
        suitable_themes='$suitable_themes',
        venue_type='$venue_type',
        ambiance='$ambiance',
        availability_status='$availability_status',
        status='$status'
        $imageSQL
        WHERE venue_id=$venue_id AND manager_id=$user_id";

    $conn->query($updateVenueSQL);

    // Update pricing
    $updatePriceSQL = "UPDATE prices SET 
        base_price=$base_price,
        peak_price=$peak_price,
        offpeak_price=$offpeak_price,
        weekday_price=$weekday_price,
        weekend_price=$weekend_price
        WHERE venue_id=$venue_id";

    $conn->query($updatePriceSQL);

    // Update or insert parking information
    $checkParkingSQL = "SELECT parking_id FROM parking WHERE venue_id=$venue_id";
    $parkingResult = $conn->query($checkParkingSQL);

    if ($parkingResult && $parkingResult->num_rows > 0) {
        // Update existing parking
        $updateParkingSQL = "UPDATE parking SET two_wheels=$two_wheels, four_wheels=$four_wheels WHERE venue_id=$venue_id";
        $conn->query($updateParkingSQL);
    } else {
        // Insert new parking
        $insertParkingSQL = "INSERT INTO parking (venue_id, two_wheels, four_wheels) VALUES ($venue_id, $two_wheels, $four_wheels)";
        $conn->query($insertParkingSQL);
    }

    // Handle amenities
    if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
        // Delete old amenities
        $conn->query("DELETE FROM venue_amenities WHERE venue_id=$venue_id");

        foreach ($_POST['amenities'] as $amenityName => $data) {
            if (!empty($data['enabled'])) {
                $price = floatval($data['price']);

                // Get amenity ID and default price
                $stmt = $conn->prepare("SELECT amenity_id, default_price FROM amenities WHERE amenity_name=?");
                $stmt->bind_param("s", $amenityName);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $amenityRow = $result->fetch_assoc();
                    $amenity_id = $amenityRow['amenity_id'];
                    $default_price = floatval($amenityRow['default_price']);
                    $custom_price = ($price !== $default_price) ? $price : NULL;

                    // Insert into venue_amenities
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

                // Check if this amenity already exists in the amenities table
                $checkStmt = $conn->prepare("SELECT amenity_id FROM amenities WHERE amenity_name=?");
                $checkStmt->bind_param("s", $amenityName);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    // Amenity exists, use it
                    $row = $checkResult->fetch_assoc();
                    $amenity_id = $row['amenity_id'];
                } else {
                    // Create new amenity
                    $insertAmenityStmt = $conn->prepare("INSERT INTO amenities (amenity_name, default_price) VALUES (?, ?)");
                    $insertAmenityStmt->bind_param("sd", $amenityName, $amenityPrice);
                    $insertAmenityStmt->execute();
                    $amenity_id = $insertAmenityStmt->insert_id;
                    $insertAmenityStmt->close();
                }

                // Add to venue_amenities with custom price
                $insertVenueAmenity = $conn->prepare("INSERT INTO venue_amenities (venue_id, amenity_id, custom_price) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE custom_price=?");
                $insertVenueAmenity->bind_param("iidd", $venue_id, $amenity_id, $amenityPrice, $amenityPrice);
                $insertVenueAmenity->execute();
                $insertVenueAmenity->close();

                $checkStmt->close();
            }
        }
    }

    $_SESSION['venue_success'] = 'updated';
    header("Location: my-venues.php");
    exit();
}

// Fetch venue data
$venueResult = $conn->query("
    SELECT v.*, 
           l.location_id,
           l.city,
           l.province,
           l.baranggay,
           l.latitude,
           l.longitude,
           CONCAT(l.city, ', ', l.province) as location,
           p.base_price,
           p.peak_price,
           p.offpeak_price,
           p.weekday_price,
           p.weekend_price
    FROM venues v
    LEFT JOIN locations l ON v.location_id = l.location_id
    LEFT JOIN prices p ON v.venue_id = p.venue_id
    WHERE v.venue_id = $venue_id AND v.manager_id = $user_id
");

if (!$venueResult || $venueResult->num_rows === 0) {
    header("Location: my-venues.php");
    exit();
}

$venue = $venueResult->fetch_assoc();

// Calculate price percentage
$price_percentage = 0;
if ($venue['base_price'] > 0 && $venue['peak_price'] > 0) {
    $price_percentage = (($venue['peak_price'] - $venue['base_price']) / $venue['base_price']) * 100;
}

// Fetch all amenities
$allAmenities = [];
$amenityResult = $conn->query("SELECT * FROM amenities ORDER BY amenity_id ASC");
if ($amenityResult && $amenityResult->num_rows > 0) {
    while ($row = $amenityResult->fetch_assoc()) {
        $allAmenities[$row['amenity_name']] = floatval($row['default_price']);
    }
}

// Fetch venue amenities (both standard and custom)
$venueAmenities = [];
$customVenueAmenities = [];
$result = $conn->query("
    SELECT a.amenity_name, va.custom_price, a.default_price, a.amenity_id
    FROM venue_amenities va
    INNER JOIN amenities a ON va.amenity_id = a.amenity_id
    WHERE va.venue_id = $venue_id
");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $amenityName = $row['amenity_name'];
        $customPrice = $row['custom_price'];
        $price = ($customPrice !== null) ? floatval($customPrice) : floatval($row['default_price']);

        $venueAmenities[$amenityName] = $price;

        // If this amenity is not in the standard amenities list, it's custom
        if (!isset($allAmenities[$amenityName])) {
            $customVenueAmenities[] = [
                'name' => $amenityName,
                'price' => $price
            ];
        }
    }
}

// Fetch parking information
$parkingData = ['two_wheels' => 0, 'four_wheels' => 0];
$parkingResult = $conn->query("SELECT two_wheels, four_wheels FROM parking WHERE venue_id = $venue_id");
if ($parkingResult && $parkingResult->num_rows > 0) {
    $parkingData = $parkingResult->fetch_assoc();
}

// Fetch all locations for dropdown
$locations = [];
$locationResult = $conn->query("SELECT location_id, city, province, baranggay FROM locations ORDER BY province, city");
if ($locationResult && $locationResult->num_rows > 0) {
    while ($row = $locationResult->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Get venue image
$imageSrc = !empty($venue['image'])
    ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
    : '../../assets/images/venue-placeholder.jpg';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue | Gatherly</title>
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
                    <div class="flex items-center gap-4">
                        <a href="my-venues.php" class="text-gray-600 hover:text-gray-800 transition-colors">
                            <i class="fas fa-arrow-left text-xl"></i>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Edit Venue</h1>
                            <p class="text-sm text-gray-600">Update venue information and settings</p>
                        </div>
                    </div>
                </div>
                <div class="px-4 sm:px-6 lg:px-8 pb-8">
                <?php else: ?>
                    <!-- Header for Navbar Layout -->
                    <div class="container px-4 py-10 mx-auto sm:px-6 lg:px-8">
                        <div class="flex items-center gap-4 mb-8">
                            <a href="my-venues.php" class="text-gray-600 hover:text-gray-800 transition-colors">
                                <i class="fas fa-arrow-left text-2xl"></i>
                            </a>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-800">Edit Venue</h1>
                                <p class="text-gray-600">Update venue information and settings</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="max-w-4xl mx-auto">
                        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-lg p-8">
                            <input type="hidden" name="venue_id" value="<?php echo $venue_id; ?>">
                            <!-- Hidden fields for coordinates -->
                            <input type="hidden" id="latitude" name="latitude"
                                value="<?php echo htmlspecialchars($venue['latitude'] ?? ''); ?>">
                            <input type="hidden" id="longitude" name="longitude"
                                value="<?php echo htmlspecialchars($venue['longitude'] ?? ''); ?>">

                            <!-- Current Venue Image & Upload -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">
                                    <i class="fas fa-image text-green-600 mr-2"></i>Venue Image
                                </h3>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Current Image Display -->
                                    <div>
                                        <label class="block font-semibold mb-3 text-sm text-gray-700">Current
                                            Image</label>
                                        <div
                                            class="w-full h-64 rounded-xl overflow-hidden bg-gray-100 border-2 border-gray-200 shadow-sm">
                                            <img src="<?php echo $imageSrc; ?>" alt="Venue Image"
                                                class="w-full h-full object-cover object-center">
                                        </div>
                                    </div>

                                    <!-- Upload New Image -->
                                    <div>
                                        <label class="block font-semibold mb-3 text-sm text-gray-700">
                                            Upload New Image <span
                                                class="text-gray-500 text-xs font-normal">(Optional)</span>
                                        </label>
                                        <div
                                            class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-green-500 transition-colors bg-gray-50">
                                            <div class="space-y-3">
                                                <div class="flex justify-center">
                                                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                                                </div>
                                                <div>
                                                    <label for="imageUpload" class="cursor-pointer">
                                                        <span
                                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors inline-block font-semibold text-sm">
                                                            <i class="fas fa-upload mr-2"></i>Choose New Image
                                                        </span>
                                                        <input type="file" id="imageUpload" name="image"
                                                            accept="image/*" class="hidden">
                                                    </label>
                                                </div>
                                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                                <p class="text-xs text-gray-400">Leave empty to keep current image</p>
                                            </div>
                                            <div id="imagePreview" class="mt-4 hidden">
                                                <p class="text-sm text-green-600 font-semibold mb-2">
                                                    <i class="fas fa-check-circle mr-1"></i>New image selected
                                                </p>
                                                <div
                                                    class="w-full h-40 rounded-lg overflow-hidden border-2 border-green-500">
                                                    <img id="previewImg" src="" alt="Preview"
                                                        class="w-full h-full object-cover">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue Basic Information -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">Basic Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Venue Name <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" name="venue_name"
                                            value="<?php echo htmlspecialchars($venue['venue_name']); ?>"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Capacity <span
                                                class="text-red-500">*</span></label>
                                        <input type="number" name="capacity" value="<?php echo $venue['capacity']; ?>"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Venue Type</label>
                                        <input type="text" name="venue_type"
                                            value="<?php echo htmlspecialchars($venue['venue_type'] ?? ''); ?>"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Ballroom, Garden, Conference Hall">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Ambiance</label>
                                        <input type="text" name="ambiance"
                                            value="<?php echo htmlspecialchars($venue['ambiance'] ?? ''); ?>"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Elegant, Rustic, Modern">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Availability
                                            Status <span class="text-red-500">*</span></label>
                                        <select name="availability_status"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="available"
                                                <?php echo ($venue['availability_status'] ?? 'available') === 'available' ? 'selected' : ''; ?>>
                                                Available</option>
                                            <option value="unavailable"
                                                <?php echo ($venue['availability_status'] ?? 'available') === 'unavailable' ? 'selected' : ''; ?>>
                                                Unavailable</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Location Section with Map -->
                                <div class="mt-6">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-3">
                                        <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>Venue Location
                                    </h4>

                                    <!-- Address Fields -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <label class="block font-semibold mb-2 text-sm text-gray-700">Barangay <span
                                                    class="text-red-500">*</span></label>
                                            <input type="text" id="baranggay" name="baranggay"
                                                value="<?php echo htmlspecialchars($venue['baranggay'] ?? ''); ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                required placeholder="Enter barangay name">
                                        </div>
                                        <div>
                                            <label class="block font-semibold mb-2 text-sm text-gray-700">City <span
                                                    class="text-red-500">*</span></label>
                                            <input type="text" id="city" name="city"
                                                value="<?php echo htmlspecialchars($venue['city'] ?? ''); ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                required placeholder="Enter city name">
                                        </div>
                                        <div>
                                            <label class="block font-semibold mb-2 text-sm text-gray-700">Province <span
                                                    class="text-red-500">*</span></label>
                                            <input type="text" id="province" name="province"
                                                value="<?php echo htmlspecialchars($venue['province'] ?? ''); ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                required placeholder="Enter province name">
                                        </div>
                                    </div>

                                    <!-- Map Container -->
                                    <div class="mb-4">
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">
                                            Select Location on Map <span class="text-red-500">*</span>
                                        </label>
                                        <div id="map" class="w-full h-96 rounded-lg border-2 border-gray-300"></div>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Click on the map to set the venue location, or use the search box to find a
                                            place.
                                        </p>
                                    </div>

                                    <!-- Use Current Location Button -->
                                    <div class="mb-4">
                                        <button type="button" id="useCurrentLocation"
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                            <i class="fas fa-location-arrow mr-2"></i>Use My Current Location
                                        </button>
                                    </div>

                                    <!-- Coordinates Display -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block font-semibold mb-2 text-sm text-gray-700">Latitude</label>
                                            <input type="text" id="latitude-display"
                                                value="<?php echo htmlspecialchars($venue['latitude'] ?? ''); ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm bg-gray-50"
                                                readonly placeholder="Latitude will appear here">
                                        </div>
                                        <div>
                                            <label
                                                class="block font-semibold mb-2 text-sm text-gray-700">Longitude</label>
                                            <input type="text" id="longitude-display"
                                                value="<?php echo htmlspecialchars($venue['longitude'] ?? ''); ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm bg-gray-50"
                                                readonly placeholder="Longitude will appear here">
                                        </div>
                                    </div>
                                </div>

                                <!-- Parking Information -->
                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-3">
                                        <i class="fas fa-parking text-blue-600 mr-2"></i>Parking Capacity
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block font-semibold mb-2 text-sm text-gray-700">
                                                <i class="fas fa-motorcycle text-gray-500 mr-1"></i>Two-Wheel Vehicles
                                            </label>
                                            <input type="number" name="two_wheels"
                                                value="<?php echo $parkingData['two_wheels'] ?? 0; ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                min="0" placeholder="Number of motorcycle/bike slots">
                                        </div>
                                        <div>
                                            <label class="block font-semibold mb-2 text-sm text-gray-700">
                                                <i class="fas fa-car text-gray-500 mr-1"></i>Four-Wheel Vehicles
                                            </label>
                                            <input type="number" name="four_wheels"
                                                value="<?php echo $parkingData['four_wheels'] ?? 0; ?>"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                min="0" placeholder="Number of car parking slots">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue Description -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">Venue Description
                                </h3>
                                <div>
                                    <label class="block font-semibold mb-2 text-sm text-gray-700">Suitable
                                        Themes</label>
                                    <input type="text" name="suitable_themes"
                                        value="<?php echo htmlspecialchars($venue['suitable_themes'] ?? ''); ?>"
                                        class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                        placeholder="e.g., Wedding, Corporate, Birthday">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-2 text-sm text-gray-700">Description <span
                                            class="text-red-500">*</span></label>
                                    <textarea name="description" rows="4"
                                        class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                        required><?php echo htmlspecialchars($venue['description']); ?></textarea>
                                </div>
                            </div>

                            <!-- Pricing Information -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">Pricing Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Base Price (₱)
                                            <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.01" name="base_price"
                                            value="<?php echo $venue['base_price']; ?>"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-2 text-sm text-gray-700">Price Percentage
                                            (%) <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.01" name="price_percentage"
                                            value="<?php echo number_format($price_percentage, 2); ?>"
                                            class="w-full border border-gray-300 rounded-lg p-3 text-sm shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            required>
                                        <p class="text-xs text-gray-500 mt-1">This percentage will be added to the base
                                            price for peak pricing</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Amenities Section -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 text-gray-800 border-b pb-2">Amenities</h3>

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
                                                    value="1" data-default-price="<?php echo $defaultPrice; ?>"
                                                    <?php echo isset($venueAmenities[$amenityName]) ? 'checked' : ''; ?>>
                                                <span
                                                    class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($amenityName); ?></span>
                                            </label>
                                            <div class="flex items-center gap-1">
                                                <span class="text-xs text-gray-500">₱</span>
                                                <input type="number"
                                                    name="amenities[<?php echo htmlspecialchars($amenityName); ?>][price]"
                                                    value="<?php echo $venueAmenities[$amenityName] ?? $defaultPrice; ?>"
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
                                        <!-- Existing custom amenities -->
                                        <?php foreach ($customVenueAmenities as $customAmenity): ?>
                                            <div
                                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white">
                                                <div class="flex-1">
                                                    <input type="text"
                                                        name="custom_amenities[existing_<?php echo htmlspecialchars($customAmenity['name']); ?>][name]"
                                                        value="<?php echo htmlspecialchars($customAmenity['name']); ?>"
                                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-2 focus:ring-green-500"
                                                        required>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-xs text-gray-500">₱</span>
                                                    <input type="number"
                                                        name="custom_amenities[existing_<?php echo htmlspecialchars($customAmenity['name']); ?>][price]"
                                                        value="<?php echo $customAmenity['price']; ?>"
                                                        class="w-24 border border-gray-300 rounded-lg p-2 text-sm text-right shadow-sm focus:ring-2 focus:ring-green-500"
                                                        step="0.01" min="0" required>
                                                </div>
                                                <button type="button"
                                                    class="remove-custom-amenity text-red-600 hover:text-red-700 px-2 py-1"
                                                    onclick="this.parentElement.remove()">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t">
                                <a href="my-venues.php"
                                    class="px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-100 font-semibold text-center transition-all">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                                <button type="submit" name="update_venue"
                                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold transition-all hover:shadow-lg">
                                    <i class="fas fa-save mr-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if ($nav_layout !== 'sidebar'): ?>
                    </div>
                <?php endif; ?>

                <?php include '../../../src/components/footer.php'; ?>

                <script>
                    let customAmenityCounter = 0;

                    // Add custom amenity functionality
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

                    // Image preview functionality
                    const imageUpload = document.getElementById('imageUpload');
                    const imagePreview = document.getElementById('imagePreview');
                    const previewImg = document.getElementById('previewImg');

                    imageUpload.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                                imagePreview.classList.remove('hidden');
                            };
                            reader.readAsDataURL(file);
                        } else {
                            imagePreview.classList.add('hidden');
                        }
                    });
                </script>

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
                            .then(() => d[l](f, ...n))
                    })({
                        key: "AIzaSyAAfxgWViv9h7RTVTH3clJe7tkJPXaWQIA",
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

                        // Get existing coordinates or use Philippines center as default
                        const existingLat = parseFloat(document.getElementById('latitude').value) || 14.5995;
                        const existingLng = parseFloat(document.getElementById('longitude').value) || 120.9842;
                        const hasExistingLocation = document.getElementById('latitude').value && document
                            .getElementById('longitude').value;

                        const defaultLocation = {
                            lat: existingLat,
                            lng: existingLng
                        };

                        map = new Map(document.getElementById('map'), {
                            center: defaultLocation,
                            zoom: hasExistingLocation ? 15 : 12,
                            mapId: 'VENUE_EDIT_MAP',
                            mapTypeControl: true,
                            streetViewControl: true,
                            fullscreenControl: true
                        });

                        // If venue has existing location, place marker
                        if (hasExistingLocation) {
                            placeMarker(new google.maps.LatLng(existingLat, existingLng));
                        }

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

                                // Auto-fill location fields if empty
                                if (barangay && !document.getElementById('baranggay').value) document
                                    .getElementById('baranggay').value = barangay;
                                if (city && !document.getElementById('city').value) document.getElementById('city')
                                    .value = city;
                                if (province && !document.getElementById('province').value) document.getElementById(
                                    'province').value = province;
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
                        <p class="text-gray-600 mb-6">Venue has been updated successfully.</p>
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