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

// Fetch ALL events for this organizer, newest first - with full details for contract
$query = "
    SELECT 
        e.event_id,
        e.event_name,
        e.event_type,
        e.event_date,
        e.status,
        e.total_cost,
        e.total_paid,
        e.payment_status,
        e.theme,
        e.time_start,
        e.time_end,
        e.expected_guests,
        v.venue_name,
        v.capacity as venue_capacity,
        CONCAT(l.city, ', ', l.province) as location,
        CONCAT(l.baranggay, ', ', l.city, ', ', l.province) as full_location,
        u.first_name,
        u.last_name,
        u.email as organizer_email,
        u.phone as organizer_phone
    FROM events e
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    LEFT JOIN locations l ON v.location_id = l.location_id
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.organizer_id = ?
    ORDER BY e.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

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
    <style>
        @media print {

            /* Hide all page elements except the modal */
            body>*:not(#detailsModal) {
                display: none !important;
            }

            /* Hide modal backdrop and footer */
            #detailsModalBackdrop,
            #detailsModal .bg-gray-50.border-t {
                display: none !important;
            }

            /* Hide close button in header */
            #detailsModal .bg-gradient-to-r button {
                display: none !important;
            }

            /* Reset modal positioning for print */
            #detailsModal {
                position: static !important;
                display: block !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Remove flex container that creates blank space */
            #detailsModal>div {
                display: block !important;
                min-height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            #detailsModal .flex.items-end {
                display: block !important;
                min-height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Remove centering span that creates space */
            #detailsModal span[aria-hidden="true"] {
                display: none !important;
            }

            #detailsModal .inline-block {
                display: block !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                transform: none !important;
                box-shadow: none !important;
            }

            /* Style the contract header for printing */
            #detailsModal .bg-gradient-to-r {
                background: white !important;
                color: black !important;
                border-bottom: 3px solid #000 !important;
                padding: 1rem !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #detailsModal .bg-gradient-to-r h3 {
                color: black !important;
            }

            /* Ensure contract content is visible */
            #detailsModal .px-8 {
                padding-left: 1.5rem !important;
                padding-right: 1.5rem !important;
                padding-top: 1.5rem !important;
                padding-bottom: 1.5rem !important;
                max-height: none !important;
                overflow: visible !important;
                display: block !important;
            }

            /* Remove backgrounds for print */
            #detailsModal .bg-gray-50 {
                background: white !important;
                border: 1px solid #ccc !important;
            }

            /* Ensure borders print correctly */
            #detailsModal .border-gray-200,
            #detailsModal .border-gray-300 {
                border-color: #666 !important;
            }

            #detailsModal .border-gray-800 {
                border-color: #000 !important;
            }

            #detailsModal .border-b-2 {
                border-bottom-width: 2px !important;
            }

            #detailsModal .border-t-2 {
                border-top-width: 2px !important;
            }

            /* Page breaks */
            #detailsModal .mb-6 {
                page-break-inside: avoid;
            }

            /* Ensure text colors print correctly */
            #detailsModal .text-gray-500,
            #detailsModal .text-gray-600,
            #detailsModal .text-gray-700 {
                color: #333 !important;
            }

            #detailsModal .text-gray-800,
            #detailsModal .text-gray-900 {
                color: #000 !important;
            }

            /* Preserve important color coding */
            #detailsModal .text-green-600 {
                color: #059669 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #detailsModal .text-red-600 {
                color: #DC2626 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #detailsModal .text-yellow-600 {
                color: #D97706 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #detailsModal .text-blue-600 {
                color: #2563EB !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #detailsModal .text-indigo-600,
            #detailsModal .text-indigo-700 {
                color: #4F46E5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Ensure progress bar colors print */
            #detailsModal .bg-green-600 {
                background-color: #059669 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Grid layouts */
            #detailsModal .grid {
                display: grid !important;
            }

            /* Flexbox layouts */
            #detailsModal .flex {
                display: flex !important;
            }

            /* Rounded corners - remove for cleaner print */
            #detailsModal .rounded-lg,
            #detailsModal .rounded-xl,
            #detailsModal .rounded-full {
                border-radius: 0 !important;
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
                <h1 class="text-2xl font-bold text-gray-800">My Events</h1>
                <p class="text-sm text-gray-600">Manage all your event bookings and reservations</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">My Events</h1>
                    <p class="text-gray-600">Manage all your event bookings and reservations</p>
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
                            <option value="canceled">Canceled</option>
                        </select>
                        <select id="sortBy"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="date-desc">Latest First</option>
                            <option value="date-asc">Oldest First</option>
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                            <option value="cost-desc">Cost (High-Low)</option>
                            <option value="cost-asc">Cost (Low-High)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Events Table -->
            <?php if ($result && $result->num_rows > 0): ?>
                <?php
                $events = [];
                while ($event = $result->fetch_assoc()) {
                    $events[] = $event;
                }
                ?>
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
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Total Cost</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Payment</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Action</th>
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
                                                <?php
                                                $eventDate = new DateTime($event['event_date']);
                                                $now = new DateTime();
                                                $diff = $now->diff($eventDate);
                                                if ($eventDate > $now) {
                                                    echo 'In ' . $diff->days . ' days';
                                                } else {
                                                    echo $diff->days . ' days ago';
                                                }
                                                ?>
                                            </div>
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
                                            case 'canceled':
                                                echo 'bg-red-100 text-red-700';
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
                                        <td class="px-6 py-4">
                                            <?php
                                            $totalCost = $event['total_cost'] ?? 0;
                                            $paidAmount = $event['total_paid'] ?? 0;
                                            $remainingAmount = $totalCost - $paidAmount;
                                            $paymentPercentage = $totalCost > 0 ? ($paidAmount / $totalCost) * 100 : 0;
                                            ?>
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-medium text-gray-600">Paid:</span>
                                                    <span
                                                        class="text-xs font-semibold text-green-600">₱<?php echo number_format($paidAmount, 2); ?></span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                    <div class="bg-green-600 h-1.5 rounded-full"
                                                        style="width: <?php echo min($paymentPercentage, 100); ?>%"></div>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo number_format($paymentPercentage, 1); ?>% paid
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <button onclick='viewEventDetails(<?php echo json_encode($event); ?>)'
                                                    class="px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors"
                                                    title="View Details">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    View
                                                </button>

                                                <?php if ($remainingAmount <= 0 && ($event['status'] === 'confirmed' || $event['status'] === 'completed')): ?>
                                                    <span class="text-xs font-semibold text-green-600">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Fully Paid
                                                    </span>
                                                <?php elseif ($event['status'] === 'completed'): ?>
                                                    <span class="text-xs font-semibold text-blue-600">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Completed
                                                    </span>
                                                <?php elseif ($event['status'] === 'confirmed' && $remainingAmount > 0): ?>
                                                    <button
                                                        onclick="openPaymentModal(<?php echo $event['event_id']; ?>, <?php echo $totalCost; ?>, <?php echo $paidAmount; ?>)"
                                                        class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                                                        <i class="fas fa-wallet mr-1"></i>
                                                        Pay Now
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-500">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Awaiting Confirmation
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($event['status'] === 'pending' || $event['status'] === 'canceled'): ?>
                                                    <button
                                                        onclick="openDeleteModal(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_name'], ENT_QUOTES); ?>')"
                                                        class="px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                                                        title="Delete Event">
                                                        <i class="fas fa-trash mr-1"></i>
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No events yet</h3>
                    <p class="text-gray-600 mb-6">You haven't created any event bookings.</p>
                    <a href="find-venues.php"
                        class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Find a Venue & Book
                    </a>
                </div>
            <?php endif; ?>
            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="delete-modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="deleteModalBackdrop"
                class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm" aria-hidden="true">
            </div>

            <!-- Center modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div
                class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="px-6 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-red-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg font-semibold leading-6 text-gray-900" id="delete-modal-title">
                                Delete Event
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to delete <span id="deleteEventName"
                                        class="font-semibold text-gray-900"></span>? This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" id="confirmDeleteBtn"
                        class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-red-600 border border-transparent rounded-lg shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        <i class="fas fa-trash mr-2"></i> Delete Event
                    </button>
                    <button type="button" id="cancelDeleteBtn"
                        class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="payment-modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="paymentModalBackdrop" class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm"
                aria-hidden="true">
            </div>

            <!-- Center modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div
                class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="px-6 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                    <div class="text-center">
                        <h3 class="text-xl font-bold text-gray-900 mb-4" id="payment-modal-title">
                            <i class="fas fa-wallet text-indigo-600 mr-2"></i>
                            GCash Payment
                        </h3>

                        <!-- QR Code -->
                        <div class="mb-4">
                            <img src="../../assets/images/QR-Pay.jpg" alt="GCash QR Code"
                                class="mx-auto w-64 h-64 object-contain border-2 border-gray-200 rounded-lg">
                        </div>

                        <!-- Payment Summary -->
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Total Cost:</span>
                                <span class="font-semibold text-gray-900" id="modalTotalCost">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Already Paid:</span>
                                <span class="font-semibold text-green-600" id="modalPaidAmount">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm border-t pt-2">
                                <span class="text-gray-600">Remaining:</span>
                                <span class="font-semibold text-indigo-600" id="modalRemainingAmount">₱0.00</span>
                            </div>
                        </div>

                        <!-- Payment Type Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2 text-left">Payment Type</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" onclick="selectPaymentType('full')" id="btn-full"
                                    class="payment-type-btn px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                    Full Payment
                                </button>
                                <button type="button" onclick="selectPaymentType('downpayment')" id="btn-downpayment"
                                    class="payment-type-btn px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                    Downpayment (30%)
                                </button>
                                <button type="button" onclick="selectPaymentType('partial')" id="btn-partial"
                                    class="payment-type-btn px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-medium hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                    Partial
                                </button>
                            </div>
                        </div>

                        <!-- Payment Amount -->
                        <div class="mb-4">
                            <label for="paymentAmount" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                Amount to Pay <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="paymentAmount" step="0.01" min="0"
                                class="w-full px-4 py-3 text-center text-lg font-semibold border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="text-xs text-gray-500 mt-1 text-left" id="amountHint">Select a payment type or
                                enter custom amount</p>
                        </div>

                        <!-- Reference Number -->
                        <div class="mb-4">
                            <label for="gcashReference" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                GCash Reference Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="gcashReference" maxlength="13"
                                placeholder="Enter 13-digit reference number"
                                class="w-full px-4 py-3 text-center text-lg font-mono tracking-wider border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="text-xs text-gray-500 mt-1 text-left">Example: 1234567890123</p>
                            <p id="paymentError" class="text-xs text-red-600 mt-1 hidden"></p>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" id="confirmPaymentBtn"
                        class="inline-flex justify-center w-full px-6 py-3 text-base font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm transition-colors">
                        <i class="fas fa-check-circle mr-2"></i> Confirm Payment
                    </button>
                    <button type="button" id="cancelPaymentBtn"
                        class="inline-flex justify-center w-full px-6 py-3 mt-3 sm:mt-0 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="details-modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="detailsModalBackdrop" class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm"
                aria-hidden="true">
            </div>

            <!-- Center modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div
                class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white flex items-center" id="details-modal-title">
                            <i class="fas fa-file-contract mr-3"></i>
                            Event Contract & Details
                        </h3>
                        <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Document Content -->
                <div class="px-8 py-6 bg-white max-h-[75vh] overflow-y-auto">
                    <!-- Contract Header -->
                    <div class="text-center border-b-2 border-gray-800 pb-4 mb-6">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">EVENT BOOKING CONTRACT</h1>
                        <p class="text-sm text-gray-600">Gatherly Event Management System</p>
                        <p class="text-xs text-gray-500 mt-1">Contract Date: <?php echo date('F d, Y'); ?></p>
                    </div>

                    <!-- Event Information Section -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2">
                            I. EVENT INFORMATION
                        </h2>
                        <div class="grid grid-cols-2 gap-4 pl-4">
                            <div>
                                <p class="text-sm text-gray-600">Event Name:</p>
                                <p id="contractEventName" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Event Type:</p>
                                <p id="contractEventType" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Theme:</p>
                                <p id="contractTheme" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Event Date:</p>
                                <p id="contractEventDate" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Start Time:</p>
                                <p id="contractStartTime" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">End Time:</p>
                                <p id="contractEndTime" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Expected Guests:</p>
                                <p id="contractGuests" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Status:</p>
                                <p id="contractStatus" class="text-base font-semibold"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Client Information Section -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2">
                            II. CLIENT INFORMATION
                        </h2>
                        <div class="grid grid-cols-2 gap-4 pl-4">
                            <div>
                                <p class="text-sm text-gray-600">Client Name:</p>
                                <p id="contractClientName" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email Address:</p>
                                <p id="contractClientEmail" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-sm text-gray-600">Contact Number:</p>
                                <p id="contractClientPhone" class="text-base font-semibold text-gray-900"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Venue Information Section -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2">
                            III. VENUE INFORMATION
                        </h2>
                        <div class="pl-4">
                            <div class="mb-3">
                                <p class="text-sm text-gray-600">Venue Name:</p>
                                <p id="contractVenueName" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div class="mb-3">
                                <p class="text-sm text-gray-600">Location:</p>
                                <p id="contractVenueLocation" class="text-base font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Capacity:</p>
                                <p id="contractVenueCapacity" class="text-base font-semibold text-gray-900"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Terms Section -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2">
                            IV. FINANCIAL TERMS
                        </h2>
                        <div class="pl-4">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="grid grid-cols-2 gap-3 mb-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Total Contract Amount:</span>
                                        <span id="contractTotalCost" class="text-base font-bold text-gray-900"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Amount Paid:</span>
                                        <span id="contractPaidAmount" class="text-base font-bold text-green-600"></span>
                                    </div>
                                </div>
                                <div class="pt-3 border-t border-gray-300">
                                    <div class="flex justify-between mb-2">
                                        <span class="text-base font-semibold text-gray-700">Outstanding Balance:</span>
                                        <span id="contractRemainingAmount"
                                            class="text-lg font-bold text-red-600"></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                                            <span>Payment Completion</span>
                                            <span id="contractPaymentPercentage">0%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="contractPaymentBar"
                                                class="bg-green-600 h-2 rounded-full transition-all" style="width: 0%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="text-sm text-gray-600">Payment Status:</p>
                                <p id="contractPaymentStatus" class="text-base font-semibold"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions Section -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2">
                            V. TERMS AND CONDITIONS
                        </h2>
                        <div class="pl-4 text-sm text-gray-700 space-y-2">
                            <p><strong>1. Payment Terms:</strong> Full payment must be completed before the event date.
                                Late payments may result in event cancellation.</p>
                            <p><strong>2. Cancellation Policy:</strong> Cancellations made 30 days prior to the event
                                are eligible for 50% refund. No refund for cancellations within 30 days.</p>
                            <p><strong>3. Venue Usage:</strong> The client agrees to use the venue responsibly and is
                                liable for any damages incurred during the event.</p>
                            <p><strong>4. Capacity Compliance:</strong> The client must not exceed the maximum venue
                                capacity specified in this contract.</p>
                            <p><strong>5. Force Majeure:</strong> Neither party shall be liable for failure to perform
                                obligations due to circumstances beyond reasonable control.</p>
                        </div>
                    </div>

                    <!-- Signatures Section -->
                    <div class="mt-8 pt-6 border-t-2 border-gray-800">
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <div class="border-t-2 border-gray-800 pt-2 mt-12">
                                    <p class="text-sm font-semibold text-gray-900">CLIENT SIGNATURE</p>
                                    <p id="signatureClientName" class="text-xs text-gray-600 mt-1"></p>
                                </div>
                            </div>
                            <div>
                                <div class="border-t-2 border-gray-800 pt-2 mt-12">
                                    <p class="text-sm font-semibold text-gray-900">GATHERLY REPRESENTATIVE</p>
                                    <p class="text-xs text-gray-600 mt-1">Authorized Signatory</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-6">
                            <p class="text-xs text-gray-500">This is a system-generated contract. For inquiries, please
                                contact Gatherly Event Management.</p>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <button type="button" onclick="printContract()"
                        class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                        <i class="fas fa-print mr-2"></i>
                        Print Contract
                    </button>
                    <button type="button" onclick="closeDetailsModal()"
                        class="px-6 py-2 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Close
                    </button>
                    <button type="button" id="detailPayButton" onclick="payFromDetails()"
                        class="hidden px-6 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        <i class="fas fa-wallet mr-2"></i>
                        Make Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Event Details Modal Variables
        let currentEventData = null;

        function viewEventDetails(event) {
            currentEventData = event;

            // Set event information for contract
            document.getElementById('contractEventName').textContent = event.event_name || 'N/A';
            document.getElementById('contractEventType').textContent = event.event_type || 'N/A';
            document.getElementById('contractTheme').textContent = event.theme || 'N/A';

            // Format event date
            if (event.event_date) {
                const eventDate = new Date(event.event_date);
                const formattedDate = eventDate.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                document.getElementById('contractEventDate').textContent = formattedDate;
            } else {
                document.getElementById('contractEventDate').textContent = 'N/A';
            }

            // Format time to 12-hour format
            function formatTimeTo12Hour(time) {
                if (!time) return 'N/A';
                const [hours, minutes] = time.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                return `${displayHour}:${minutes} ${ampm}`;
            }

            document.getElementById('contractStartTime').textContent = formatTimeTo12Hour(event.time_start);
            document.getElementById('contractEndTime').textContent = formatTimeTo12Hour(event.time_end);
            document.getElementById('contractGuests').textContent = event.expected_guests ? parseInt(event.expected_guests)
                .toLocaleString() + ' guests' : 'N/A';

            // Set status badge with appropriate styling
            const statusEl = document.getElementById('contractStatus');
            statusEl.textContent = event.status ? event.status.charAt(0).toUpperCase() + event.status.slice(1) : 'N/A';
            switch (event.status) {
                case 'confirmed':
                    statusEl.className = 'text-base font-semibold text-green-600';
                    break;
                case 'pending':
                    statusEl.className = 'text-base font-semibold text-yellow-600';
                    break;
                case 'completed':
                    statusEl.className = 'text-base font-semibold text-blue-600';
                    break;
                case 'canceled':
                    statusEl.className = 'text-base font-semibold text-red-600';
                    break;
                default:
                    statusEl.className = 'text-base font-semibold text-gray-600';
            }

            // Set client information
            const clientName = (event.first_name || '') + ' ' + (event.last_name || '');
            document.getElementById('contractClientName').textContent = clientName.trim() || 'N/A';
            document.getElementById('contractClientEmail').textContent = event.organizer_email || 'N/A';
            document.getElementById('contractClientPhone').textContent = event.organizer_phone || 'N/A';
            document.getElementById('signatureClientName').textContent = clientName.trim() || 'N/A';

            // Set venue information
            document.getElementById('contractVenueName').textContent = event.venue_name || 'N/A';
            document.getElementById('contractVenueLocation').textContent = event.full_location || event.location || 'N/A';
            document.getElementById('contractVenueCapacity').textContent = event.venue_capacity ? parseInt(event
                .venue_capacity).toLocaleString() + ' guests' : 'N/A';

            // Set financial information
            const totalCost = parseFloat(event.total_cost) || 0;
            const paidAmount = parseFloat(event.total_paid) || 0;
            const remainingAmount = totalCost - paidAmount;
            const paymentPercentage = totalCost > 0 ? (paidAmount / totalCost) * 100 : 0;

            document.getElementById('contractTotalCost').textContent = '₱' + totalCost.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('contractPaidAmount').textContent = '₱' + paidAmount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('contractRemainingAmount').textContent = '₱' + remainingAmount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('contractPaymentPercentage').textContent = paymentPercentage.toFixed(1) + '%';
            document.getElementById('contractPaymentBar').style.width = Math.min(paymentPercentage, 100) + '%';

            // Set payment status
            const paymentStatusEl = document.getElementById('contractPaymentStatus');
            if (event.payment_status) {
                paymentStatusEl.textContent = event.payment_status.charAt(0).toUpperCase() + event.payment_status.slice(1);
                if (event.payment_status === 'paid' || remainingAmount <= 0) {
                    paymentStatusEl.className = 'text-base font-semibold text-green-600';
                } else if (event.payment_status === 'partial') {
                    paymentStatusEl.className = 'text-base font-semibold text-yellow-600';
                } else {
                    paymentStatusEl.className = 'text-base font-semibold text-gray-600';
                }
            } else {
                paymentStatusEl.textContent = remainingAmount <= 0 ? 'Fully Paid' : 'Pending Payment';
                paymentStatusEl.className = remainingAmount <= 0 ? 'text-base font-semibold text-green-600' :
                    'text-base font-semibold text-gray-600';
            }

            // Show/hide pay button
            const payButton = document.getElementById('detailPayButton');
            if (event.status === 'confirmed' && remainingAmount > 0) {
                payButton.classList.remove('hidden');
            } else {
                payButton.classList.add('hidden');
            }

            // Open modal
            document.body.style.overflow = 'hidden';
            document.getElementById('detailsModal').classList.remove('hidden');
        }

        function closeDetailsModal() {
            document.body.style.overflow = '';
            document.getElementById('detailsModal').classList.add('hidden');
            currentEventData = null;
        }

        function printContract() {
            window.print();
        }

        function payFromDetails() {
            if (currentEventData) {
                closeDetailsModal();
                const totalCost = parseFloat(currentEventData.total_cost) || 0;
                const paidAmount = parseFloat(currentEventData.total_paid) || 0;
                openPaymentModal(currentEventData.event_id, totalCost, paidAmount);
            }
        }

        // Close modal when clicking backdrop
        document.getElementById('detailsModalBackdrop').addEventListener('click', closeDetailsModal);

        // Delete Modal Variables
        let deleteEventId = null;

        function openDeleteModal(eventId, eventName) {
            deleteEventId = eventId;
            document.getElementById('deleteEventName').textContent = eventName;
            document.body.style.overflow = 'hidden';
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.body.style.overflow = '';
            document.getElementById('deleteModal').classList.add('hidden');
            deleteEventId = null;
        }

        // Delete event handlers
        document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteModal);
        document.getElementById('deleteModalBackdrop').addEventListener('click', closeDeleteModal);

        document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
            if (!deleteEventId) return;

            const confirmBtn = this;
            const originalText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

            try {
                const formData = new FormData();
                formData.append('event_id', deleteEventId);

                const response = await fetch('../../../src/services/delete-event.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    closeDeleteModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete event'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Connection error. Please try again.');
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        });

        // Payment Modal Variables
        let currentEventId = null;
        let currentTotalCost = 0;
        let currentPaidAmount = 0;
        let currentRemainingAmount = 0;
        let selectedPaymentType = null;

        function openPaymentModal(eventId, totalCost, paidAmount) {
            currentEventId = eventId;
            currentTotalCost = parseFloat(totalCost);
            currentPaidAmount = parseFloat(paidAmount);
            currentRemainingAmount = currentTotalCost - currentPaidAmount;

            document.getElementById('modalTotalCost').textContent = '₱' + currentTotalCost.toFixed(2);
            document.getElementById('modalPaidAmount').textContent = '₱' + currentPaidAmount.toFixed(2);
            document.getElementById('modalRemainingAmount').textContent = '₱' + currentRemainingAmount.toFixed(2);

            document.getElementById('paymentAmount').value = '';
            document.getElementById('gcashReference').value = '';
            document.getElementById('paymentError').classList.add('hidden');

            // Reset payment type buttons
            document.querySelectorAll('.payment-type-btn').forEach(btn => {
                btn.classList.remove('border-indigo-500', 'bg-indigo-100', 'text-indigo-700');
                btn.classList.add('border-gray-300');
            });
            selectedPaymentType = null;

            document.body.style.overflow = 'hidden';
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closePaymentModal() {
            document.body.style.overflow = '';
            document.getElementById('paymentModal').classList.add('hidden');
            currentEventId = null;
        }

        function selectPaymentType(type) {
            selectedPaymentType = type;

            // Update button styles
            document.querySelectorAll('.payment-type-btn').forEach(btn => {
                btn.classList.remove('border-indigo-500', 'bg-indigo-100', 'text-indigo-700');
                btn.classList.add('border-gray-300');
            });

            const selectedBtn = document.getElementById('btn-' + type);
            selectedBtn.classList.remove('border-gray-300');
            selectedBtn.classList.add('border-indigo-500', 'bg-indigo-100', 'text-indigo-700');

            // Set payment amount
            const paymentAmountInput = document.getElementById('paymentAmount');
            const amountHint = document.getElementById('amountHint');

            if (type === 'full') {
                paymentAmountInput.value = currentRemainingAmount.toFixed(2);
                amountHint.textContent = 'Full remaining balance';
            } else if (type === 'downpayment') {
                const downpayment = currentTotalCost * 0.3;
                paymentAmountInput.value = downpayment.toFixed(2);
                amountHint.textContent = 'Minimum 30% downpayment';
            } else if (type === 'partial') {
                paymentAmountInput.value = '';
                amountHint.textContent = 'Enter any amount up to remaining balance';
                paymentAmountInput.focus();
            }
        }

        // Validate reference number input
        document.getElementById('gcashReference').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });

        // Cancel button
        document.getElementById('cancelPaymentBtn').addEventListener('click', closePaymentModal);
        document.getElementById('paymentModalBackdrop').addEventListener('click', closePaymentModal);

        // Confirm payment
        document.getElementById('confirmPaymentBtn').addEventListener('click', async function() {
            const amount = parseFloat(document.getElementById('paymentAmount').value);
            const reference = document.getElementById('gcashReference').value.trim();
            const errorEl = document.getElementById('paymentError');

            // Validation
            if (!selectedPaymentType) {
                errorEl.textContent = 'Please select a payment type';
                errorEl.classList.remove('hidden');
                return;
            }

            if (!amount || amount <= 0) {
                errorEl.textContent = 'Please enter a valid payment amount';
                errorEl.classList.remove('hidden');
                return;
            }

            if (amount > currentRemainingAmount) {
                errorEl.textContent = 'Amount cannot exceed remaining balance';
                errorEl.classList.remove('hidden');
                return;
            }

            if (selectedPaymentType === 'downpayment' && amount < (currentTotalCost * 0.3)) {
                errorEl.textContent = 'Downpayment must be at least 30% of total cost';
                errorEl.classList.remove('hidden');
                return;
            }

            if (!reference || reference.length !== 13) {
                errorEl.textContent = 'Please enter a valid 13-digit GCash reference number';
                errorEl.classList.remove('hidden');
                return;
            }

            // Disable button
            const confirmBtn = this;
            const originalText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            errorEl.classList.add('hidden');

            try {
                const formData = new FormData();
                formData.append('event_id', currentEventId);
                formData.append('payment_type', selectedPaymentType);
                formData.append('amount', amount);
                formData.append('reference_no', reference);

                const response = await fetch('../../../src/services/process-payment.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    closePaymentModal();
                    alert('Payment submitted successfully! Your payment is pending verification.');
                    location.reload();
                } else {
                    errorEl.textContent = data.error || 'Failed to process payment';
                    errorEl.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error:', error);
                errorEl.textContent = 'Connection error. Please try again.';
                errorEl.classList.remove('hidden');
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        });
    </script>

</body>

</html>