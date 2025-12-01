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
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">My Venues</h1>
                            <p class="text-sm text-gray-600">Manage your venues, view details, and track availability</p>
                        </div>
                        <a href="add-venue.php?from_my_venues=1"
                            class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg shadow-md flex items-center gap-2 transition-all hover:scale-105">
                            <i class="fas fa-plus-circle"></i> Add New Venue
                        </a>
                    </div>
                    <!-- Search and Filter Bar -->
                    <div class="flex flex-col sm:flex-row gap-3 mt-4">
                        <div class="flex-1 relative">
                            <input type="text" id="searchInput" placeholder="Search venues by name or location..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select id="filterStatus"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                        <select id="filterLocation"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">All Provinces</option>
                            <?php
                            $locationResult->data_seek(0);
                            $uniqueProvinces = [];
                            while ($loc = $locationResult->fetch_assoc()) {
                                $province = $loc['province'];
                                if (!in_array($province, $uniqueProvinces)) {
                                    $uniqueProvinces[] = $province;
                                    echo '<option value="' . htmlspecialchars($province) . '">' . htmlspecialchars($province) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                    <!-- Header for Navbar Layout -->
                    <div class="container px-4 py-10 mx-auto sm:px-6 lg:px-8">
                    <?php endif; ?>

                    <?php if ($nav_layout !== 'sidebar'): ?>
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-800">My Venues</h1>
                                <p class="text-gray-600">Manage your venues, view details, and track availability</p>
                            </div>
                            <a href="add-venue.php?from_my_venues=1"
                                class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg shadow-md flex items-center gap-2 transition-all hover:scale-105">
                                <i class="fas fa-plus-circle"></i> Add New Venue
                            </a>
                        </div>

                        <!-- Search and Filter Bar for Navbar Layout -->
                        <div class="flex flex-col sm:flex-row gap-3 mb-8">
                            <div class="flex-1 relative">
                                <input type="text" id="searchInput" placeholder="Search venues by name or location..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <select id="filterStatus"
                                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                            <select id="filterLocation"
                                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="">All Provinces</option>
                                <?php
                                $locationResult->data_seek(0);
                                $uniqueProvinces = [];
                                while ($loc = $locationResult->fetch_assoc()) {
                                    $province = $loc['province'];
                                    if (!in_array($province, $uniqueProvinces)) {
                                        $uniqueProvinces[] = $province;
                                        echo '<option value="' . htmlspecialchars($province) . '">' . htmlspecialchars($province) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($venues && $venues->num_rows > 0): ?>
                        <!-- No Results Message -->
                        <div id="noResults" class="hidden flex flex-col items-center justify-center py-20 text-center bg-white border border-gray-200 rounded-2xl shadow-md mb-6">
                            <i class="mb-3 text-5xl text-gray-400 fas fa-search"></i>
                            <h3 class="mb-2 text-xl font-semibold text-gray-700">No venues found</h3>
                            <p class="mb-4 text-gray-500">Try adjusting your search or filter criteria.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-6" id="venueGrid">
                            <?php while ($venue = $venues->fetch_assoc()): ?>
                                <?php
                                // Venue Image
                                $imageSrc = !empty($venue['image'])
                                    ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
                                    : '../../assets/images/venue-placeholder.jpg';
                                ?>

                                <div
                                    class="venue-card bg-white border border-gray-200 shadow-md rounded-xl hover:shadow-lg transition-all overflow-hidden"
                                    data-venue-name="<?php echo htmlspecialchars(strtolower($venue['venue_name'])); ?>"
                                    data-venue-location="<?php echo htmlspecialchars(strtolower($venue['location'])); ?>"
                                    data-venue-status="<?php echo htmlspecialchars($venue['availability_status'] ?? 'available'); ?>">
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
                                                <!-- VIEW BUTTON -->
                                                <a href="venue-details.php?id=<?php echo $venue['venue_id']; ?>"
                                                    class="flex items-center gap-1 text-green-600 hover:text-green-700 font-semibold text-sm transition-colors hover:underline">
                                                    <i class="fas fa-eye"></i> View
                                                </a>

                                                <!-- EDIT BUTTON -->
                                                <a href="edit-venue.php?id=<?php echo $venue['venue_id']; ?>"
                                                    class="flex items-center gap-1 text-blue-600 hover:text-blue-700 font-semibold text-sm transition-colors hover:underline">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>

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

                        // Search and Filter Functionality
                        function filterVenues() {
                            const searchValue = document.getElementById('searchInput').value.toLowerCase();
                            const statusFilter = document.getElementById('filterStatus').value;
                            const locationFilter = document.getElementById('filterLocation').value.toLowerCase();

                            const venueCards = document.querySelectorAll('.venue-card');
                            let visibleCount = 0;

                            venueCards.forEach(card => {
                                const venueName = card.dataset.venueName || '';
                                const venueLocation = card.dataset.venueLocation || '';
                                const venueStatus = card.dataset.venueStatus || 'available';

                                const matchesSearch = venueName.includes(searchValue) || venueLocation.includes(searchValue);
                                const matchesStatus = !statusFilter || venueStatus === statusFilter;
                                const matchesLocation = !locationFilter || venueLocation.includes(locationFilter);

                                if (matchesSearch && matchesStatus && matchesLocation) {
                                    card.style.display = '';
                                    visibleCount++;
                                } else {
                                    card.style.display = 'none';
                                }
                            });

                            // Show/hide no results message
                            const noResults = document.getElementById('noResults');
                            const venueGrid = document.getElementById('venueGrid');
                            if (visibleCount === 0) {
                                noResults.classList.remove('hidden');
                                venueGrid.classList.add('hidden');
                            } else {
                                noResults.classList.add('hidden');
                                venueGrid.classList.remove('hidden');
                            }
                        }

                        // Add event listeners for search and filter
                        document.addEventListener('DOMContentLoaded', function() {
                            const searchInput = document.getElementById('searchInput');
                            const filterStatus = document.getElementById('filterStatus');
                            const filterLocation = document.getElementById('filterLocation');

                            if (searchInput) {
                                searchInput.addEventListener('input', filterVenues);
                            }
                            if (filterStatus) {
                                filterStatus.addEventListener('change', filterVenues);
                            }
                            if (filterLocation) {
                                filterLocation.addEventListener('change', filterVenues);
                            }
                        });

                        function openModal(id) {
                            document.getElementById(id).classList.add('show');
                        }

                        function closeModal(id) {
                            document.getElementById(id).classList.remove('show');
                        }

                        // Delete button handler
                        document.querySelectorAll('.delete-btn').forEach((btn) => {
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();

                                const venueId = btn.dataset.id;
                                const venueName = btn.dataset.name;

                                document.getElementById('delete_venue_id').value = venueId;
                                document.getElementById('delete_venue_name').textContent = venueName;

                                openModal('deleteModal');
                            });
                        });

                        // Close modal when clicking outside
                        document.querySelectorAll('.modal-overlay').forEach(overlay => {
                            overlay.addEventListener('click', (e) => {
                                if (e.target === overlay) {
                                    overlay.classList.remove('show');
                                }
                            });
                        });
                    </script>

                    <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            <?php endif; ?>
            </div>

            <!-- Success Modal -->
            <div id="successModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all scale-95 opacity-0" id="successModalContent">
                    <div class="p-6 text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                            <i class="fas fa-check text-green-600 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Success!</h3>
                        <p id="successMessage" class="text-gray-600 mb-6">Venue has been processed successfully.</p>
                        <button onclick="closeSuccessModal()"
                            class="w-full px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                            Continue
                        </button>
                    </div>
                </div>
            </div>

            <script>
                // Show success modal if redirected from add/edit
                <?php if (isset($_SESSION['venue_success'])): ?>
                    const successType = '<?php echo $_SESSION['venue_success']; ?>';
                    const successMessage = document.getElementById('successMessage');

                    if (successType === 'added') {
                        successMessage.textContent = 'Venue has been added successfully.';
                    } else if (successType === 'updated') {
                        successMessage.textContent = 'Venue has been updated successfully.';
                    }

                    const modal = document.getElementById('successModal');
                    const content = document.getElementById('successModalContent');
                    modal.classList.remove('hidden');

                    setTimeout(() => {
                        content.classList.add('scale-100', 'opacity-100');
                        content.classList.remove('scale-95', 'opacity-0');
                    }, 10);

                    <?php unset($_SESSION['venue_success']); ?>
                <?php endif; ?>

                function closeSuccessModal() {
                    const modal = document.getElementById('successModal');
                    const content = document.getElementById('successModalContent');
                    content.classList.remove('scale-100', 'opacity-100');
                    content.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        modal.classList.add('hidden');
                    }, 200);
                }
            </script>

</body>

</html>