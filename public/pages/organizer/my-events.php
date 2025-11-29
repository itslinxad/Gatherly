<?php
session_start();

// Ensure user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Organizer';
$user_id = $_SESSION['user_id'];

// Fetch organizer's upcoming or ongoing events with venue info
$query = "
    SELECT 
        e.event_id,
        e.event_name,
        e.event_type,
        e.event_date,
        e.time_start,
        e.time_end,
        e.status,
        e.total_cost,
        v.venue_name,
        CONCAT(l.city, ', ', l.province) as location,
        e.expected_guests
    FROM events e
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    LEFT JOIN locations l ON v.location_id = l.location_id
    WHERE e.organizer_id = ?
      AND e.event_date >= NOW()
      AND e.status IN ('pending', 'completed')
    ORDER BY e.event_date ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$events = [];
while ($event = $result->fetch_assoc()) {
    $events[] = $event;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events | Gatherly</title>
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
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-cyan-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">My Events</h1>
                <p class="text-sm text-gray-600">View your booked or upcoming events</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">My Events</h1>
                    <p class="text-gray-600">View your booked or upcoming events</p>
                </div>
            <?php endif; ?>

            <!-- Search & Filter Section -->
            <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <i
                                class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchInput"
                                placeholder="Search by event name, venue, or location..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <select id="statusFilter"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                        </select>
                        <select id="sortBy"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="date-asc">Upcoming First</option>
                            <option value="date-desc">Latest First</option>
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                            <option value="cost-desc">Cost (High-Low)</option>
                            <option value="cost-asc">Cost (Low-High)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Events Table -->
            <?php if (count($events) > 0): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full" id="eventsTable">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Event Name</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Venue</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Location</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Event Date</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Guests</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Total Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($events as $event): ?>
                                    <tr class="hover:bg-gray-50 transition-colors event-row"
                                        data-event-name="<?php echo htmlspecialchars($event['event_name']); ?>"
                                        data-venue="<?php echo htmlspecialchars($event['venue_name'] ?? ''); ?>"
                                        data-location="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($event['status']); ?>"
                                        data-date="<?php echo htmlspecialchars($event['event_date']); ?>"
                                        data-cost="<?php echo $event['total_cost'] ?? 0; ?>">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($event['event_name']); ?></div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($event['event_type'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($event['venue_name'] ?? '—'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($event['location'] ?? '—'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('g:i A', strtotime($event['time_start'])); ?> -
                                                <?php echo date('g:i A', strtotime($event['time_end'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <i class="fas fa-users mr-1 text-indigo-600"></i>
                                            <?php echo number_format($event['expected_guests'] ?? 0); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        switch ($event['status']) {
                                            case 'confirmed':
                                                echo 'bg-green-100 text-green-700';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-700';
                                                break;
                                            case 'completed':
                                                echo 'bg-blue-100 text-blue-700';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-700';
                                        }
                                        ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-indigo-600">
                                            ₱<?php echo number_format($event['total_cost'] ?? 0, 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                Showing <span id="showingCount"><?php echo count($events); ?></span> of <span
                                    id="totalCount"><?php echo count($events); ?></span> events
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    const searchInput = document.getElementById('searchInput');
                    const statusFilter = document.getElementById('statusFilter');
                    const sortBy = document.getElementById('sortBy');
                    const tableRows = document.querySelectorAll('.event-row');
                    const showingCount = document.getElementById('showingCount');
                    const totalCount = document.getElementById('totalCount');

                    function filterAndSortTable() {
                        const searchTerm = searchInput.value.toLowerCase();
                        const statusValue = statusFilter.value.toLowerCase();

                        let visibleRows = Array.from(tableRows).filter(row => {
                            const eventName = row.dataset.eventName.toLowerCase();
                            const venue = row.dataset.venue.toLowerCase();
                            const location = row.dataset.location.toLowerCase();
                            const status = row.dataset.status.toLowerCase();

                            const matchesSearch = eventName.includes(searchTerm) ||
                                venue.includes(searchTerm) ||
                                location.includes(searchTerm);
                            const matchesStatus = !statusValue || status === statusValue;

                            return matchesSearch && matchesStatus;
                        });

                        // Sort visible rows
                        const sortValue = sortBy.value;
                        visibleRows.sort((a, b) => {
                            switch (sortValue) {
                                case 'date-desc':
                                    return new Date(b.dataset.date) - new Date(a.dataset.date);
                                case 'date-asc':
                                    return new Date(a.dataset.date) - new Date(b.dataset.date);
                                case 'name-asc':
                                    return a.dataset.eventName.localeCompare(b.dataset.eventName);
                                case 'name-desc':
                                    return b.dataset.eventName.localeCompare(a.dataset.eventName);
                                case 'cost-desc':
                                    return parseFloat(b.dataset.cost) - parseFloat(a.dataset.cost);
                                case 'cost-asc':
                                    return parseFloat(a.dataset.cost) - parseFloat(b.dataset.cost);
                            }
                        });

                        // Hide all rows first
                        tableRows.forEach(row => row.style.display = 'none');

                        // Show and reorder visible rows
                        const tbody = document.querySelector('#eventsTable tbody');
                        visibleRows.forEach(row => {
                            row.style.display = '';
                            tbody.appendChild(row);
                        });

                        showingCount.textContent = visibleRows.length;
                    }

                    searchInput.addEventListener('input', filterAndSortTable);
                    statusFilter.addEventListener('change', filterAndSortTable);
                    sortBy.addEventListener('change', filterAndSortTable);
                </script>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-calendar-check text-6xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No upcoming events</h3>
                    <p class="text-gray-600 mb-6">You haven't booked any venues for future events yet.</p>
                    <a href="find-venues.php"
                        class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Find a Venue
                    </a>
                </div>
            <?php endif; ?>
            </div>
            <?php if ($nav_layout === 'sidebar'): ?>
    </div>
<?php endif; ?>
</div>

</body>

</html>