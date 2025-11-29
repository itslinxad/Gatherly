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

// DELETE VENUE
if (isset($_GET['delete'])) {
    $venue_id = intval($_GET['delete']);
    $conn->query("DELETE FROM venues WHERE venue_id = $venue_id");
    echo "<script>alert('Venue deleted successfully!'); window.location='my-venues.php';</script>";
    exit();
}

// UPDATE VENUE + AMENITIES
if (isset($_POST['update_venue'])) {
    $venue_id = intval($_POST['venue_id']);
    $venue_name = $conn->real_escape_string($_POST['venue_name']);
    $location_id = intval($_POST['location_id']);
    $capacity = intval($_POST['capacity']);
    $base_price = floatval($_POST['base_price']);
    $description = $conn->real_escape_string($_POST['description']);
    $suitable_themes = $conn->real_escape_string($_POST['suitable_themes'] ?? '');
    $venue_type = $conn->real_escape_string($_POST['venue_type'] ?? '');
    $ambiance = $conn->real_escape_string($_POST['ambiance'] ?? '');
    $availability_status = $_POST['availability_status'];
    $status = $_POST['status'] ?? 'active';
    $price_percentage = floatval($_POST['price_percentage']);

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

    echo "<script>alert('Venue updated successfully!'); window.location='my-venues.php';</script>";
    exit();
}

// FETCH DATA
$venues = $conn->query("
    SELECT v.*, 
           CONCAT(l.city, ', ', l.province) as location,
           l.location_id,
           p.base_price,
           p.peak_price,
           p.offpeak_price,
           p.weekday_price,
           p.weekend_price
    FROM venues v
    LEFT JOIN locations l ON v.location_id = l.location_id
    LEFT JOIN prices p ON v.venue_id = p.venue_id
    ORDER BY v.venue_id ASC
");

$allAmenities = [];
$amenityResult = $conn->query("SELECT * FROM amenities ORDER BY amenity_id ASC");
if ($amenityResult && $amenityResult->num_rows > 0) {
    while ($row = $amenityResult->fetch_assoc()) {
        $allAmenities[$row['amenity_name']] = floatval($row['default_price']);
    }
}

$venueAmenities = [];
$result = $conn->query("
    SELECT va.venue_id, a.amenity_name, va.custom_price, a.default_price
    FROM venue_amenities va
    INNER JOIN amenities a ON va.amenity_id = a.amenity_id
");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $venueId = $row['venue_id'];
        $amenityName = $row['amenity_name'];
        $customPrice = $row['custom_price'];
        $venueAmenities[$venueId][$amenityName] = ($customPrice !== null) ? floatval($customPrice) : floatval($row['default_price']);
    }
}

// Fetch all locations for dropdown
$locations = [];
$locationResult = $conn->query("SELECT location_id, city, province, baranggay FROM locations ORDER BY province, city");
if ($locationResult && $locationResult->num_rows > 0) {
    while ($row = $locationResult->fetch_assoc()) {
        $locations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Venues | Gatherly</title>
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
        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(3px);
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 10000;
        }

        .venue-image-container {
            pointer-events: none !important;
        }

        .venue-actions button {
            pointer-events: auto !important;
            cursor: pointer !important;
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
                    <h1 class="text-2xl font-bold text-gray-800">My Venues</h1>
                    <p class="text-sm text-gray-600">Manage your venues, view details, and track availability</p>
                </div>
                <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                    <!-- Header for Navbar Layout -->
                    <div class="container px-4 py-10 mx-auto sm:px-6 lg:px-8">
                    <?php endif; ?>

                    <div class="flex items-center justify-between mb-8">
                        <?php if ($nav_layout !== 'sidebar'): ?>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-800">My Venues</h1>
                                <p class="text-gray-600">Manage your venues, view details, and track availability</p>
                            </div>
                        <?php endif; ?>
                        <a href="add-venue.php?from_my_venues=1"
                            class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg shadow-md flex items-center gap-2 transition-all hover:scale-105">
                            <i class="fas fa-plus-circle"></i> Add New Venue
                        </a>
                    </div>

                    <?php if ($venues && $venues->num_rows > 0): ?>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                            <?php while ($venue = $venues->fetch_assoc()): ?>
                                <?php
                                // Venue Image
                                $imageSrc = !empty($venue['image'])
                                    ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
                                    : '../../assets/images/venue-placeholder.jpg';

                                // Fetch amenities for this venue
                                $venueAmenities = [];
                                $sql = $conn->prepare("
                SELECT a.amenity_name, va.custom_price, a.default_price
                FROM venue_amenities va
                INNER JOIN amenities a ON va.amenity_id = a.amenity_id
                WHERE va.venue_id = ?
            ");
                                $sql->bind_param("i", $venue['venue_id']);
                                $sql->execute();
                                $resultAmenities = $sql->get_result();
                                while ($rowAmenity = $resultAmenities->fetch_assoc()) {
                                    $amenityName = $rowAmenity['amenity_name'];
                                    $customPrice = $rowAmenity['custom_price'];
                                    $venueAmenities[$amenityName] = ($customPrice !== null) ? floatval($customPrice) : floatval($rowAmenity['default_price']);
                                }
                                ?>

                                <div
                                    class="venue-card bg-white border border-gray-200 shadow-md rounded-xl hover:shadow-lg transition-all overflow-hidden">
                                    <!-- Image Container -->
                                    <div
                                        class="venue-image-container relative w-full h-48 overflow-hidden bg-gray-100 rounded-t-xl">
                                        <img src="<?php echo $imageSrc; ?>" alt="Venue Image"
                                            class="w-full h-full object-cover object-center transition-transform duration-300 hover:scale-105">
                                    </div>

                                    <!-- Content -->
                                    <div class="p-5">
                                        <h2 class="text-lg font-bold text-gray-800 mb-1">
                                            <?php echo htmlspecialchars($venue['venue_name']); ?></h2>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <i class="fas fa-map-marker-alt text-green-500 mr-1.5"></i>
                                            <?php echo htmlspecialchars($venue['location']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <i class="fas fa-users text-blue-500 mr-1.5"></i>
                                            Capacity: <?php echo htmlspecialchars($venue['capacity']); ?>
                                        </p>
                                        <div class="mb-3 bg-gray-50 rounded-lg p-3 border border-gray-100">
                                            <p class="text-sm font-semibold text-green-700">
                                                ₱<?php echo number_format($venue['base_price'], 2); ?>
                                            </p>
                                        </div>
                                        <p class="text-sm text-gray-700 line-clamp-3 mb-4">
                                            <?php echo htmlspecialchars($venue['description']); ?></p>

                                        <div class="flex items-center justify-between">
                                            <div class="venue-actions flex gap-3">
                                                <!-- EDIT BUTTON -->
                                                <button
                                                    class="edit-btn flex items-center gap-1 text-blue-600 hover:text-blue-700 font-semibold text-sm transition-colors hover:underline"
                                                    data-venue-id="<?php echo $venue['venue_id']; ?>"
                                                    data-venue-name="<?php echo htmlspecialchars($venue['venue_name']); ?>"
                                                    data-venue-location-id="<?php echo $venue['location_id']; ?>"
                                                    data-venue-capacity="<?php echo $venue['capacity']; ?>"
                                                    data-venue-base-price="<?php echo $venue['base_price']; ?>"
                                                    data-venue-description="<?php echo htmlspecialchars($venue['description']); ?>"
                                                    data-venue-status="<?php echo $venue['availability_status'] ?? 'available'; ?>"
                                                    data-venue-percentage="<?php echo $venue['price_percentage'] ?? '0'; ?>"
                                                    data-venue-amenities='<?php echo json_encode($venueAmenities); ?>'>
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>

                                                <!-- DELETE BUTTON -->
                                                <button
                                                    class="delete-btn flex items-center gap-1 text-red-600 hover:text-red-700 font-semibold text-sm transition-colors hover:underline"
                                                    data-id="<?php echo $venue['venue_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($venue['venue_name']); ?>">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </div>

                                            <!-- AVAILABILITY STATUS -->
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full
                        <?php echo ($venue['availability_status'] ?? 'available') === 'available'
                                    ? 'bg-green-50 text-green-700 border border-green-300 shadow-sm'
                                    : 'bg-red-50 text-red-700 border border-red-300 shadow-sm'; ?>">
                                                <i class="fas fa-circle text-[6px]
                            <?php echo ($venue['availability_status'] ?? 'available') === 'available'
                                    ? 'text-green-500'
                                    : 'text-red-500'; ?>">
                                                </i>
                                                <?php echo ucfirst($venue['availability_status'] ?? 'Available'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div
                            class="flex flex-col items-center justify-center py-20 text-center bg-white border border-gray-200 rounded-2xl shadow-md">
                            <i class="mb-3 text-5xl text-gray-400 fas fa-building"></i>
                            <h3 class="mb-2 text-xl font-semibold text-gray-700">No venues added yet</h3>
                            <p class="mb-4 text-gray-500">Start by adding your first venue to display it here.</p>
                            <a href="add-venue.php"
                                class="px-6 py-3 font-semibold text-white bg-green-600 rounded-lg shadow-md hover:bg-green-700 transition-all">
                                <i class="mr-2 fas fa-plus-circle"></i> Add Venue
                            </a>
                        </div>
                    <?php endif; ?>
                    </div>

                    <?php include '../../../src/components/footer.php'; ?>

                    <!-- Edit Modal -->
                    <div id="editModal" class="modal-overlay">
                        <div class="modal-content shadow-xl bg-white rounded-2xl p-6 max-w-lg mx-auto relative">
                            <div class="flex items-center justify-between mb-5 border-b pb-3">
                                <h2 class="text-2xl font-bold text-gray-800">Edit Venue</h2>
                                <button onclick="closeModal('editModal')"
                                    class="text-gray-400 hover:text-gray-700 text-3xl font-bold">&times;</button>
                            </div>

                            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                                <input type="hidden" name="venue_id" id="edit_venue_id">

                                <!-- Venue Name & Location -->
                                <div class="grid grid-cols-1 gap-3">
                                    <div>
                                        <label class="block font-semibold mb-1 text-sm">Venue Name</label>
                                        <input type="text" name="venue_name" id="edit_venue_name"
                                            class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-sm">Location</label>
                                        <select name="location_id" id="edit_location_id"
                                            class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500"
                                            required>
                                            <option value="">Select Location</option>
                                            <?php foreach ($locations as $loc): ?>
                                                <option value="<?php echo $loc['location_id']; ?>">
                                                    <?php echo htmlspecialchars($loc['city'] . ', ' . $loc['province'] . ' - ' . $loc['baranggay']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Capacity & Base Price -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-semibold mb-1 text-sm">Capacity</label>
                                        <input type="number" name="capacity" id="edit_capacity"
                                            class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-sm">Base Price (₱)</label>
                                        <input type="number" step="0.01" name="base_price" id="edit_base_price"
                                            class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500"
                                            required>
                                    </div>
                                </div>

                                <!-- Price Percentage -->
                                <div>
                                    <label class="block font-semibold mb-1 text-sm">Price Percentage (%)</label>
                                    <input type="number" step="0.01" name="price_percentage" id="edit_price_percentage"
                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500"
                                        required>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block font-semibold mb-1 text-sm">Description</label>
                                    <textarea name="description" id="edit_description" rows="3"
                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500"
                                        required></textarea>
                                </div>

                                <!-- Amenities Section -->
                                <div>
                                    <label class="block font-semibold mb-1 text-sm">Amenities</label>
                                    <div id="amenitiesContainer"
                                        class="grid grid-cols-2 gap-3 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50 shadow-inner">
                                        <?php foreach ($allAmenities as $amenityName => $defaultPrice): ?>
                                            <div
                                                class="flex items-center justify-between gap-2 p-2 rounded-lg hover:bg-green-50 transition-all">
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox"
                                                        name="amenities[<?php echo htmlspecialchars($amenityName); ?>][enabled]"
                                                        class="checkbox-amenity" value="1"
                                                        data-default-price="<?php echo $defaultPrice; ?>">
                                                    <span
                                                        class="text-sm font-medium"><?php echo htmlspecialchars($amenityName); ?></span>
                                                </label>
                                                <input type="number"
                                                    name="amenities[<?php echo htmlspecialchars($amenityName); ?>][price]"
                                                    value="<?php echo $defaultPrice; ?>"
                                                    class="w-12 text-sm border border-gray-300 rounded p-1 text-right shadow-sm focus:ring-1 focus:ring-green-500"
                                                    step="0.01">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Availability -->
                                <div>
                                    <label class="block font-semibold mb-1 text-sm">Availability Status</label>
                                    <select name="availability_status" id="edit_availability_status"
                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm focus:ring-1 focus:ring-green-500">
                                        <option value="available">Available</option>
                                        <option value="unavailable">Unavailable</option>
                                    </select>
                                </div>

                                <!-- Image Upload -->
                                <div>
                                    <label class="block font-semibold mb-1 text-sm">Update Image (Optional)</label>
                                    <input type="file" name="image" accept="image/*"
                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm shadow-sm">
                                    <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-end gap-3 pt-3">
                                    <button type="button" onclick="closeModal('editModal')"
                                        class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold text-sm">Cancel</button>
                                    <button type="submit" name="update_venue"
                                        class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-sm">Save
                                        Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Delete Confirmation Modal -->
                    <div id="deleteModal" class="modal-overlay">
                        <div class="modal-content max-w-md text-center">
                            <div class="mb-4">
                                <h2 class="text-xl font-bold text-gray-800 mb-2">Confirm Delete</h2>
                                <p class="text-gray-600">Are you sure you want to delete <strong
                                        id="delete_venue_name"></strong>?</p>
                            </div>

                            <form method="GET" class="flex justify-center gap-3">
                                <input type="hidden" name="delete" id="delete_venue_id">
                                <button type="button" onclick="closeModal('deleteModal')"
                                    class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                                    Yes, Delete
                                </button>
                            </form>
                        </div>
                    </div>

                    <script>
                        console.log('🔧 Script loaded with debugging');

                        // Profile dropdown handled by ManagerSidebar.php

                        function openModal(id) {
                            console.log('✅ Opening modal:', id);
                            document.getElementById(id).classList.add('show');
                        }

                        document.querySelectorAll('.checkbox-amenity').forEach(cb => {
                            cb.addEventListener('change', (e) => {
                                const priceInput = e.target.closest('div').querySelector('input[type=number]');
                                if (e.target.checked) {
                                    priceInput.value = e.target.dataset.defaultPrice;
                                } else {
                                    priceInput.value = 0;
                                }
                            });
                        });

                        function closeModal(id) {
                            console.log('❌ Closing modal:', id);
                            document.getElementById(id).classList.remove('show');
                        }

                        const editButtons = document.querySelectorAll('.edit-btn');
                        console.log('📊 Found edit buttons:', editButtons.length);

                        editButtons.forEach((btn, index) => {
                            const rect = btn.getBoundingClientRect();
                            const styles = window.getComputedStyle(btn);
                            console.log(`🔘 Button ${index}:`, {
                                visible: btn.offsetWidth > 0 && btn.offsetHeight > 0,
                                position: {
                                    x: rect.x,
                                    y: rect.y,
                                    width: rect.width,
                                    height: rect.height
                                },
                                pointerEvents: styles.pointerEvents,
                                cursor: styles.cursor,
                                zIndex: styles.zIndex
                            });
                        });

                        document.querySelectorAll('.edit-btn').forEach(btn => {
                            btn.addEventListener('click', e => {
                                e.preventDefault();
                                e.stopPropagation();

                                const venueData = {
                                    venue_id: btn.dataset.venueId,
                                    venue_name: btn.dataset.venueName,
                                    location_id: btn.dataset.venueLocationId,
                                    capacity: btn.dataset.venueCapacity,
                                    base_price: btn.dataset.venueBasePrice,
                                    description: btn.dataset.venueDescription,
                                    availability_status: btn.dataset.venueStatus,
                                    price_percentage: btn.dataset.venuePercentage,
                                    amenities: JSON.parse(btn.dataset.venueAmenities || '{}') // ✅
                                };

                                document.getElementById('edit_venue_id').value = venueData.venue_id;
                                document.getElementById('edit_venue_name').value = venueData.venue_name;
                                document.getElementById('edit_location_id').value = venueData.location_id;
                                document.getElementById('edit_capacity').value = venueData.capacity;
                                document.getElementById('edit_base_price').value = venueData.base_price;
                                document.getElementById('edit_description').value = venueData.description;
                                document.getElementById('edit_availability_status').value = venueData
                                    .availability_status;
                                document.getElementById('edit_price_percentage').value = venueData
                                    .price_percentage;

                                document.querySelectorAll('#amenitiesContainer .checkbox-amenity').forEach(
                                    cb => {
                                        const name = cb.name.match(/amenities\[(.*)\]\[enabled\]/)[1];
                                        const priceInput = cb.closest('div').querySelector(
                                            'input[type=number]');

                                        if (venueData.amenities[name] !== undefined) {
                                            cb.checked = true;
                                            priceInput.value = venueData.amenities[name];
                                        } else {
                                            cb.checked = false;
                                            priceInput.value = priceInput.dataset.defaultPrice;
                                        }
                                    });
                                openModal('editModal');
                            });
                        });

                        // Delete button handler
                        document.querySelectorAll('.delete-btn').forEach((btn, index) => {
                            btn.addEventListener('click', (e) => {
                                console.log('🗑️ ============ DELETE CLICKED ============');
                                console.log('Button index:', index);

                                e.preventDefault();
                                e.stopPropagation();

                                const venueId = btn.dataset.id;
                                const venueName = btn.dataset.name;

                                console.log('Venue ID:', venueId, 'Name:', venueName);

                                document.getElementById('delete_venue_id').value = venueId;
                                document.getElementById('delete_venue_name').textContent = venueName;

                                openModal('deleteModal');
                            });
                        });

                        document.querySelectorAll('.modal-overlay').forEach(overlay => {
                            overlay.addEventListener('click', (e) => {
                                if (e.target === overlay) {
                                    overlay.classList.remove('show');
                                }
                            });
                        });

                        // Track ALL clicks with element detection
                        document.addEventListener('click', (e) => {
                            const elementAtPoint = document.elementFromPoint(e.clientX, e.clientY);
                            console.log('👆 CLICK:', {
                                target: e.target.className || e.target.tagName,
                                position: {
                                    x: e.clientX,
                                    y: e.clientY
                                },
                                elementAtPoint: elementAtPoint?.className || elementAtPoint?.tagName,
                                isEditBtn: e.target.classList.contains('edit-btn'),
                                isDeleteBtn: e.target.classList.contains('delete-btn')
                            });
                        }, true);

                        console.log('✅ All event listeners registered');
                    </script>

                    <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            <?php endif; ?>
            </div>

</body>

</html>