<?php
require_once '../../src/services/dbconnect.php';

// FETCH ALL AVAILABLE VENUES
$venues = $conn->query("
    SELECT v.*, 
           CONCAT(l.city, ', ', l.province) as location,
           l.location_id
    FROM venues v
    LEFT JOIN locations l ON v.location_id = l.location_id
    WHERE v.availability_status = 'available'
    ORDER BY v.venue_id ASC
");

// Fetch all locations for filter dropdown
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
    <title>Available Venues | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo.png">
    <link rel="stylesheet"
        href="../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../src/output.css'); ?>">
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

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 w-full border-b border-gray-200 shadow-md bg-white/90 backdrop-blur-lg">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-12 md:h-16">
                <div class="flex items-center h-full">
                    <a href="../../index.php" class="flex items-center group">
                        <img class="w-8 h-8 mr-2 transition-transform sm:w-10 sm:h-10 group-hover:scale-110"
                            src="../assets/images/logo.png" alt="Gatherly Logo">
                        <span class="text-lg font-bold text-gray-800 sm:text-xl">Gatherly</span>
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="../../index.php"
                        class="px-3 sm:px-4 py-2 text-sm sm:text-base font-semibold text-gray-700 transition-all hover:bg-gray-100 rounded-lg">
                        Home
                    </a>
                    <a href="signin.php"
                        class="px-3 sm:px-4 py-2 text-sm sm:text-base font-semibold text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5">
                        Sign in
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container px-4 py-10 mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800 sm:text-4xl">Available Venues</h1>
            <p class="mt-2 text-gray-600">Discover the perfect venue for your next event</p>
        </div>

        <!-- Search and Filter Bar -->
        <div class="flex flex-col sm:flex-row gap-3 mb-8">
            <div class="flex-1 relative">
                <input type="text" id="searchInput" placeholder="Search venues by name or location..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            <select id="filterLocation"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="">All Provinces</option>
                <?php
                $uniqueProvinces = [];
                foreach ($locations as $loc) {
                    $province = $loc['province'];
                    if (!in_array($province, $uniqueProvinces)) {
                        $uniqueProvinces[] = $province;
                        echo '<option value="' . htmlspecialchars($province) . '">' . htmlspecialchars($province) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

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
                        : '../assets/images/venue-placeholder.jpg';
                    ?>

                    <div
                        class="venue-card bg-white border border-gray-200 shadow-md rounded-xl hover:shadow-lg transition-all overflow-hidden"
                        data-venue-name="<?php echo htmlspecialchars(strtolower($venue['venue_name'])); ?>"
                        data-venue-location="<?php echo htmlspecialchars(strtolower($venue['location'])); ?>">
                        <!-- Image Container -->
                        <div class="relative w-full h-48 overflow-hidden bg-gray-100 rounded-t-xl">
                            <img src="<?php echo $imageSrc; ?>" alt="Venue Image"
                                class="w-full h-full object-cover object-center transition-transform duration-300 hover:scale-105">
                        </div>

                        <!-- Content -->
                        <div class="p-5">
                            <h2 class="text-lg font-bold text-gray-800 mb-1">
                                <?php echo htmlspecialchars($venue['venue_name']); ?>
                            </h2>
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-map-marker-alt text-green-500 mr-1.5"></i>
                                <?php echo htmlspecialchars($venue['location']); ?>
                            </p>
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-users text-blue-500 mr-1.5"></i>
                                Capacity: <?php echo htmlspecialchars($venue['capacity']); ?>
                            </p>
                            <p class="text-sm text-gray-700 line-clamp-3 mb-4">
                                <?php echo htmlspecialchars($venue['description']); ?>
                            </p>

                            <div class="flex items-center justify-between">
                                <a href="venue-detail.php?id=<?php echo $venue['venue_id']; ?>"
                                    class="flex items-center gap-1 text-green-600 hover:text-green-700 font-semibold text-sm transition-colors hover:underline">
                                    <i class="fas fa-eye"></i> View Details
                                </a>

                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-300 shadow-sm">
                                    <i class="fas fa-circle text-[6px] text-green-500"></i>
                                    Available
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-20 text-center bg-white border border-gray-200 rounded-2xl shadow-md">
                <i class="mb-3 text-5xl text-gray-400 fas fa-building"></i>
                <h3 class="mb-2 text-xl font-semibold text-gray-700">No venues available</h3>
                <p class="mb-4 text-gray-500">Please check back later for available venues.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../src/components/footer.php'; ?>

    <script>
        // Search and Filter Functionality
        function filterVenues() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const locationFilter = document.getElementById('filterLocation').value.toLowerCase();

            const venueCards = document.querySelectorAll('.venue-card');
            let visibleCount = 0;

            venueCards.forEach(card => {
                const venueName = card.dataset.venueName || '';
                const venueLocation = card.dataset.venueLocation || '';

                const matchesSearch = venueName.includes(searchValue) || venueLocation.includes(searchValue);
                const matchesLocation = !locationFilter || venueLocation.includes(locationFilter);

                if (matchesSearch && matchesLocation) {
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
            const filterLocation = document.getElementById('filterLocation');

            if (searchInput) {
                searchInput.addEventListener('input', filterVenues);
            }
            if (filterLocation) {
                filterLocation.addEventListener('change', filterVenues);
            }
        });
    </script>

</body>

</html>