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
    $location_id = intval($_POST['location_id']);
    $capacity = intval($_POST['capacity']);
    $base_price = floatval($_POST['base_price']);
    $price_percentage = floatval($_POST['price_percentage']);
    $description = $_POST['description'];
    $suitable_themes = $_POST['suitable_themes'] ?? '';
    $venue_type = $_POST['venue_type'] ?? '';
    $ambiance = $_POST['ambiance'] ?? '';
    $availability_status = $_POST['availability_status'] ?? 'available';
    $status = $_POST['status'] ?? 'active';
    $selected_amenities = $_POST['venue_amenities'] ?? [];
    $manager_id = $_SESSION['user_id'];

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

    // Insert selected amenities
    if (!empty($selected_amenities)) {
        $stmtAmenity = $conn->prepare("INSERT INTO venue_amenities (venue_id, amenity_id) VALUES (?, ?)");
        foreach ($selected_amenities as $amenity_name) {
            $stmtAmenityId = $conn->prepare("SELECT amenity_id FROM amenities WHERE amenity_name = ?");
            $stmtAmenityId->bind_param("s", $amenity_name);
            $stmtAmenityId->execute();
            $stmtAmenityId->bind_result($amenity_id);
            if ($stmtAmenityId->fetch()) {
                $stmtAmenity->bind_param("ii", $venue_id, $amenity_id);
                $stmtAmenity->execute();
            }
            $stmtAmenityId->close();
        }
        $stmtAmenity->close();
    }

    echo "<script>alert('Venue added successfully!'); window.location='my-venues.php';</script>";
    exit();
}

// Fetch all locations for dropdown
$locations = [];
$locationQuery = $conn->query("SELECT * FROM locations ORDER BY province, city");
if ($locationQuery && $locationQuery->num_rows > 0) {
    while ($row = $locationQuery->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Default amenities
$default_amenities = [
    "Air Conditioning",
    "Wi-Fi",
    "Security Services",
    "Projector",
    "Parking Space",
    "Stage Setup",
    "Accessibility Features",
    "Garden Setup",
    "VIP Lounge",
    "Outdoor Seating",
    "Others"
];
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Venue Name</label>
                                    <input type="text" name="venue_name" placeholder="e.g., Aurora Pavilion" required
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Location</label>
                                    <select name="location_id" required
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?php echo $loc['location_id']; ?>">
                                                <?php echo htmlspecialchars($loc['city'] . ', ' . $loc['province']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                    <label class="block text-sm font-semibold text-gray-700">Capacity</label>
                                    <input type="number" name="capacity" placeholder="e.g., 150" min="1" required
                                        class="w-full mt-2 rounded-lg border border-gray-300 px-4 py-2.5 shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
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

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Suitable Themes (comma-separated)</label>
                                <input type="text" name="suitable_themes" placeholder="e.g., Wedding, Corporate, Birthday"
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

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Amenities</label>
                                <div
                                    class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-2 bg-gray-50 p-4 rounded-lg border border-gray-300">
                                    <?php foreach ($default_amenities as $amenity): ?>
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="venue_amenities[]"
                                                value="<?php echo htmlspecialchars($amenity); ?>"
                                                class="text-green-600 focus:ring-green-500">
                                            <?php echo htmlspecialchars($amenity); ?>
                                        </label>
                                    <?php endforeach; ?>
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

                    <script>
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

</body>

</html>