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

// Fetch venues with amenities
$venues_query = "
    SELECT v.venue_id, v.venue_name, v.location, v.capacity, v.base_price, v.description
    FROM venues v 
    WHERE v.availability_status = 'available'
    ORDER BY v.venue_name
";
$venues_result = $conn->query($venues_query);

// Fetch all amenities grouped by venue_id
$amenities_query = "SELECT va.venue_id, a.amenity_name FROM venue_amenities va JOIN amenities a ON va.amenity_id = a.amenity_id";
$amenities_result = $conn->query($amenities_query);
$amenities_by_venue = [];
$all_amenities = [];
while ($row = $amenities_result->fetch_assoc()) {
    $amenities_by_venue[$row['venue_id']][] = $row['amenity_name'];
    $all_amenities[] = $row['amenity_name'];
}
$all_amenities = array_unique($all_amenities);
sort($all_amenities);

// Get min/max price and capacity for filters
$stats_query = "SELECT MIN(base_price) as min_price, MAX(base_price) as max_price, MIN(capacity) as min_cap, MAX(capacity) as max_cap FROM venues WHERE availability_status = 'available'";
$stats = $conn->query($stats_query)->fetch_assoc();
$min_price = (int)($stats['min_price'] ?? 0);
$max_price = (int)($stats['max_price'] ?? 100000);
$min_cap = (int)($stats['min_cap'] ?? 0);
$max_cap = (int)($stats['max_cap'] ?? 1000);

// Fetch unique locations
$locations_query = "SELECT DISTINCT location FROM venues WHERE availability_status = 'available' ORDER BY location";
$locations_result = $conn->query($locations_query);
$locations = [];
while ($row = $locations_result->fetch_assoc()) {
    $locations[] = $row['location'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Venues | Gatherly</title>
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
    .filter-drawer {
        position: fixed;
        top: 0;
        right: 0;
        height: 100vh;
        width: 100%;
        max-width: 320px;
        background: white;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        overflow-y: auto;
    }

    .filter-drawer.open {
        transform: translateX(0);
    }

    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .overlay.open {
        opacity: 1;
        visibility: visible;
    }

    @media (max-width: 1023px) {
        .filter-drawer {
            max-width: 100%;
        }
    }
    </style>
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
            <h1 class="text-2xl font-bold text-gray-800">Find Venues</h1>
            <p class="text-sm text-gray-600">Browse and filter available venues</p>
        </div>
        <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
            <!-- Header for Navbar Layout -->
            <div class="mb-8">
                <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Find Venues</h1>
                <p class="text-gray-600">Browse and filter available venues</p>
            </div>
            <?php endif; ?>

            <!-- Search + Filter Button: SIDE-BY-SIDE -->
            <div class="flex flex-col gap-2 p-2 mb-6 bg-white border border-gray-200 shadow-sm rounded-xl sm:flex-row">
                <div class="relative flex-1">
                    <i class="absolute text-gray-400 transform -translate-y-1/2 fas fa-search left-3 top-1/2"></i>
                    <input type="text" id="searchInput" placeholder="Search venues by name or location..."
                        class="w-full py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        oninput="applyFilters()">
                </div>
                <button onclick="openFilterDrawer()"
                    class="flex items-center justify-center px-4 py-2 font-medium text-indigo-700 bg-indigo-100 rounded-lg hover:bg-indigo-200 whitespace-nowrap">
                    <i class="mr-2 fas fa-filter"></i> Filters
                </button>
            </div>

            <!-- Venue Listings -->
            <div id="venuesContainer" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 mb-6">
                <?php if ($venues_result && $venues_result->num_rows > 0): ?>
                <?php while ($venue = $venues_result->fetch_assoc()): ?>
                <?php
                        $amenities = $amenities_by_venue[$venue['venue_id']] ?? [];
                        $amenities_html = '';
                        $more_count = 0;
                        if (count($amenities) > 3) {
                            $display = array_slice($amenities, 0, 3);
                            $more_count = count($amenities) - 3;
                        } else {
                            $display = $amenities;
                        }
                        foreach ($display as $a) {
                            $amenities_html .= '<span class="px-2 py-1 text-xs text-gray-700 bg-gray-100 rounded-md">' . htmlspecialchars($a) . '</span>';
                        }
                        if ($more_count > 0) {
                            $amenities_html .= '<span class="px-2 py-1 text-xs text-gray-500 bg-gray-100 rounded-md">+' . $more_count . ' more</span>';
                        }
                        ?>
                <div class="overflow-hidden transition-shadow bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md venue-card"
                    data-name="<?php echo htmlspecialchars($venue['venue_name']); ?>"
                    data-location="<?php echo htmlspecialchars($venue['location']); ?>"
                    data-capacity="<?php echo $venue['capacity']; ?>" data-price="<?php echo $venue['base_price']; ?>"
                    data-amenities="<?php echo implode(',', array_map('htmlspecialchars', $amenities)); ?>">
                    <div class="relative">
                        <div class="flex items-center justify-center w-full h-48 bg-gray-200">
                            <span class="text-gray-500">No image</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="mb-2 text-xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($venue['venue_name']); ?></h3>
                        <div class="flex items-center mb-3 text-gray-600">
                            <i class="mr-2 fas fa-map-marker-alt"></i>
                            <span class="text-sm"><?php echo htmlspecialchars($venue['location']); ?></span>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center text-gray-600">
                                <i class="mr-2 fas fa-users"></i>
                                <span class="text-sm"><?php echo $venue['capacity']; ?> capacity</span>
                            </div>
                            <span
                                class="text-lg font-bold text-indigo-600">₱<?php echo number_format($venue['base_price'], 2); ?></span>
                        </div>
                        <?php if (!empty($amenities)): ?>
                        <div class="mb-4">
                            <p class="mb-2 text-sm font-medium text-gray-900">Amenities:</p>
                            <div class="flex flex-wrap gap-2">
                                <?php echo $amenities_html; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="flex space-x-4">
                            <a href="view-venue.php?venue_id=<?php echo $venue['venue_id']; ?>"
                                class="block w-full px-4 py-2 font-medium text-center text-white transition-colors bg-cyan-500 rounded-lg hover:bg-cyan-600">
                                View
                            </a>
                            <a href="create-event.php?venue_id=<?php echo $venue['venue_id']; ?>"
                                class="block w-full px-4 py-2 font-medium text-center text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                Select
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="py-12 text-center col-span-full">
                    <div class="mb-4 text-gray-400">
                        <i class="text-4xl fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium text-gray-900">No venues available</h3>
                    <p class="text-gray-600">Check back later or contact support.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Drawer -->
        <div id="filterDrawer" class="filter-drawer">
            <div class="p-4">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Filters</h3>
                    <button onclick="closeFilterDrawer()" class="text-gray-500 hover:text-gray-700">
                        <i class="text-xl fas fa-times"></i>
                    </button>
                </div>

                <div class="space-y-5">
                    <!-- Price Range -->
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Price Range (₱)</label>
                        <div class="mb-1 text-sm text-gray-600">
                            <span id="priceRangeText">₱<?php echo number_format($min_price, 0); ?> –
                                ₱<?php echo number_format($max_price, 0); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="range" id="priceMin" min="<?php echo $min_price; ?>"
                                max="<?php echo $max_price; ?>" value="<?php echo $min_price; ?>"
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                oninput="updatePriceRange()">
                            <input type="range" id="priceMax" min="<?php echo $min_price; ?>"
                                max="<?php echo $max_price; ?>" value="<?php echo $max_price; ?>"
                                class="w-full h-2 bg-indigo-200 rounded-lg appearance-none cursor-pointer"
                                oninput="updatePriceRange()">
                        </div>
                    </div>

                    <!-- Capacity -->
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Capacity (guests)</label>
                        <div class="mb-1 text-sm text-gray-600">
                            <span id="capRangeText"><?php echo $min_cap; ?> – <?php echo $max_cap; ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="range" id="capMin" min="<?php echo $min_cap; ?>" max="<?php echo $max_cap; ?>"
                                value="<?php echo $min_cap; ?>"
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                oninput="updateCapRange()">
                            <input type="range" id="capMax" min="<?php echo $min_cap; ?>" max="<?php echo $max_cap; ?>"
                                value="<?php echo $max_cap; ?>"
                                class="w-full h-2 bg-indigo-200 rounded-lg appearance-none cursor-pointer"
                                oninput="updateCapRange()">
                        </div>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Location</label>
                        <select id="locationFilter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Amenities -->
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Amenities</label>
                        <div class="space-y-2 overflow-y-auto max-h-40">
                            <?php foreach ($all_amenities as $amenity): ?>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" class="text-indigo-600 rounded amenity-checkbox"
                                    value="<?php echo htmlspecialchars($amenity); ?>">
                                <span class="ml-2"><?php echo htmlspecialchars($amenity); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button onclick="clearAllFilters()"
                        class="w-full py-2 font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50">
                        Clear All Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Overlay -->
        <div id="overlay" class="overlay" onclick="closeFilterDrawer()"></div>

        <script>
        function openFilterDrawer() {
            document.getElementById('filterDrawer').classList.add('open');
            document.getElementById('overlay').classList.add('open');
        }

        function closeFilterDrawer() {
            document.getElementById('filterDrawer').classList.remove('open');
            document.getElementById('overlay').classList.remove('open');
        }

        function updatePriceRange() {
            const minSlider = document.getElementById('priceMin');
            const maxSlider = document.getElementById('priceMax');
            const minVal = parseInt(minSlider.value);
            const maxVal = parseInt(maxSlider.value);

            if (minVal > maxVal) {
                minSlider.value = maxVal;
                maxSlider.value = minVal;
            }

            document.getElementById('priceRangeText').textContent =
                '₱' + parseInt(minSlider.value).toLocaleString() + ' – ₱' + parseInt(maxSlider.value).toLocaleString();
            applyFilters();
        }

        function updateCapRange() {
            const minSlider = document.getElementById('capMin');
            const maxSlider = document.getElementById('capMax');
            const minVal = parseInt(minSlider.value);
            const maxVal = parseInt(maxSlider.value);

            if (minVal > maxVal) {
                minSlider.value = maxVal;
                maxSlider.value = minVal;
            }

            document.getElementById('capRangeText').textContent =
                minSlider.value + ' – ' + maxSlider.value;
            applyFilters();
        }

        function applyFilters() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const minPrice = parseInt(document.getElementById('priceMin').value);
            const maxPrice = parseInt(document.getElementById('priceMax').value);
            const minCap = parseInt(document.getElementById('capMin').value);
            const maxCap = parseInt(document.getElementById('capMax').value);
            const locationFilter = document.getElementById('locationFilter').value;
            const selectedAmenities = Array.from(document.querySelectorAll('.amenity-checkbox:checked'))
                .map(cb => cb.value);

            const cards = document.querySelectorAll('.venue-card');
            cards.forEach(card => {
                const name = card.dataset.name.toLowerCase();
                const location = card.dataset.location.toLowerCase();
                const capacity = parseInt(card.dataset.capacity);
                const price = parseFloat(card.dataset.price);
                const amenities = card.dataset.amenities ? card.dataset.amenities.split(',') : [];

                let matches = true;

                if (searchTerm && !name.includes(searchTerm) && !location.includes(searchTerm)) matches = false;
                if (price < minPrice || price > maxPrice) matches = false;
                if (capacity < minCap || capacity > maxCap) matches = false;
                if (locationFilter && card.dataset.location !== locationFilter) matches = false;
                if (selectedAmenities.length > 0) {
                    for (let amenity of selectedAmenities) {
                        if (!amenities.includes(amenity)) {
                            matches = false;
                            break;
                        }
                    }
                }

                card.classList.toggle('hidden', !matches);
            });
        }

        function clearAllFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('priceMin').value = <?php echo $min_price; ?>;
            document.getElementById('priceMax').value = <?php echo $max_price; ?>;
            document.getElementById('capMin').value = <?php echo $min_cap; ?>;
            document.getElementById('capMax').value = <?php echo $max_cap; ?>;
            document.getElementById('locationFilter').value = '';
            document.querySelectorAll('.amenity-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('priceRangeText').textContent =
                '₱<?php echo number_format($min_price, 0); ?> – ₱<?php echo number_format($max_price, 0); ?>';
            document.getElementById('capRangeText').textContent = '<?php echo $min_cap; ?> – <?php echo $max_cap; ?>';
            applyFilters();
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('#priceMin, #priceMax, #capMin, #capMax, #locationFilter').forEach(el => {
                el.addEventListener('input', applyFilters);
            });
            document.querySelectorAll('.amenity-checkbox').forEach(cb => {
                cb.addEventListener('change', applyFilters);
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeFilterDrawer();
        });
        </script>

    </div>
    </div>
</body>

</html>