<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $event_id = $_POST['event_id'] ?? null;
    $action = $_POST['action'];

    if ($event_id && is_numeric($event_id)) {
        $stmt = null;
        switch ($action) {
            case 'confirm':
                $stmt = $conn->prepare("UPDATE events SET status = 'confirmed' WHERE event_id = ?");
                break;
            case 'cancel':
                $stmt = $conn->prepare("UPDATE events SET status = 'canceled' WHERE event_id = ?");
                break;
            case 'complete':
                $stmt = $conn->prepare("UPDATE events SET status = 'completed' WHERE event_id = ?");
                break;
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
                break;
        }
        if ($stmt) {
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: manage-events.php");
    exit();
}

// Fetch events with filters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination configuration
$items_per_page = 10;
$page_num = (int)(isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1);

// Build base query for counting and fetching
$where_conditions = "WHERE 1=1";
if ($status_filter) {
    $where_conditions .= " AND e.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($type_filter) {
    $where_conditions .= " AND e.event_type = '" . $conn->real_escape_string($type_filter) . "'";
}
if ($search) {
    $search_term = $conn->real_escape_string($search);
    $where_conditions .= " AND (e.event_name LIKE '%$search_term%' OR e.theme LIKE '%$search_term%')";
}

// Count total records
$count_query = "SELECT COUNT(*) as total 
                FROM events e 
                LEFT JOIN venues v ON e.venue_id = v.venue_id 
                LEFT JOIN users u ON e.organizer_id = u.user_id 
                $where_conditions";
$count_result = $conn->query($count_query);
$total_records = (int)$count_result->fetch_assoc()['total'];
$total_pages = (int)ceil($total_records / $items_per_page);

// Calculate offset
$offset = (int)(($page_num - 1) * $items_per_page);

// Fetch events with pagination
$query = "SELECT e.*, v.venue_name, v.capacity as venue_capacity, 
          CONCAT(l.baranggay, ', ', l.city, ', ', l.province) as venue_location,
          u.first_name, u.last_name, u.email as organizer_email, u.phone as organizer_phone,
          e.time_start, e.time_end
          FROM events e 
          LEFT JOIN venues v ON e.venue_id = v.venue_id 
          LEFT JOIN locations l ON v.location_id = l.location_id
          LEFT JOIN users u ON e.organizer_id = u.user_id 
          $where_conditions
          ORDER BY e.event_date DESC
          LIMIT $items_per_page OFFSET $offset";

$events_result = $conn->query($query);

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['confirmed'] = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'confirmed'")->fetch_assoc()['count'];
$stats['completed'] = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'completed'")->fetch_assoc()['count'];
$stats['canceled'] = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'canceled'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | Gatherly</title>
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

            /* Preserve background colors for progress bar */
            #detailsModal .bg-green-600 {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body class="<?php
                $nav_layout = $_SESSION['nav_layout'] ?? 'sidebar';
                echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-slate-50 via-white to-blue-50';
                ?> font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">
                    <!-- <i class="mr-2 text-indigo-600 fas fa-calendar-alt"></i> -->
                    Event Management
                </h1>
                <p class="text-sm text-gray-600">Manage events and their status</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">
                        <!-- <i class="mr-2 text-indigo-600 fas fa-calendar-alt"></i> -->
                        Event Management
                    </h1>
                    <p class="text-gray-600">Manage events and their status</p>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-4 mb-6 md:mb-8">
                <div class="p-3 md:p-4 bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1 md:mb-2">
                            <i class="fas fa-calendar text-blue-500 text-sm md:text-base"></i>
                            <span
                                class="text-lg md:text-xl font-bold text-gray-800"><?php echo number_format($stats['total']); ?></span>
                        </div>
                        <p class="text-xs text-gray-600">Total</p>
                    </div>
                </div>
                <div class="p-3 md:p-4 bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1 md:mb-2">
                            <i class="fas fa-clock text-yellow-500 text-sm md:text-base"></i>
                            <span
                                class="text-lg md:text-xl font-bold text-gray-800"><?php echo number_format($stats['pending']); ?></span>
                        </div>
                        <p class="text-xs text-gray-600">Pending</p>
                    </div>
                </div>
                <div class="p-3 md:p-4 bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1 md:mb-2">
                            <i class="fas fa-check-circle text-green-500 text-sm md:text-base"></i>
                            <span
                                class="text-lg md:text-xl font-bold text-gray-800"><?php echo number_format($stats['confirmed']); ?></span>
                        </div>
                        <p class="text-xs text-gray-600">Confirmed</p>
                    </div>
                </div>
                <div class="p-3 md:p-4 bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1 md:mb-2">
                            <i class="fas fa-check-double text-purple-500 text-sm md:text-base"></i>
                            <span
                                class="text-lg md:text-xl font-bold text-gray-800"><?php echo number_format($stats['completed']); ?></span>
                        </div>
                        <p class="text-xs text-gray-600">Completed</p>
                    </div>
                </div>
                <div class="p-3 md:p-4 bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1 md:mb-2">
                            <i class="fas fa-times-circle text-red-500 text-sm md:text-base"></i>
                            <span
                                class="text-lg md:text-xl font-bold text-gray-800"><?php echo number_format($stats['canceled']); ?></span>
                        </div>
                        <p class="text-xs text-gray-600">Canceled</p>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="p-4 md:p-6 mb-6 bg-white shadow-md rounded-xl">
                <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Event name or theme..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Event Type</label>
                        <select name="type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Types</option>
                            <option value="Wedding" <?php echo $type_filter === 'Wedding' ? 'selected' : ''; ?>>Wedding
                            </option>
                            <option value="Corporate" <?php echo $type_filter === 'Corporate' ? 'selected' : ''; ?>>
                                Corporate</option>
                            <option value="Birthday" <?php echo $type_filter === 'Birthday' ? 'selected' : ''; ?>>
                                Birthday
                            </option>
                            <option value="Concert" <?php echo $type_filter === 'Concert' ? 'selected' : ''; ?>>Concert
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-semibold text-gray-700">Status</label>
                        <select name="status"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                Pending
                            </option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>
                                Confirmed</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>
                                Completed</option>
                            <option value="canceled" <?php echo $status_filter === 'canceled' ? 'selected' : ''; ?>>
                                Canceled
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="flex-1 px-6 py-2 text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                            <i class="mr-2 fas fa-search"></i>Filter
                        </button>
                        <a href="manage-events.php"
                            class="px-4 py-2 text-gray-600 transition-colors bg-gray-200 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Events Table -->
            <div class="overflow-hidden bg-white shadow-md rounded-xl mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Event</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Client</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Venue</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Date</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Guests</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Cost</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-left text-gray-700 uppercase">
                                    Status</th>
                                <th
                                    class="px-6 py-3 text-xs font-semibold tracking-wider text-center text-gray-700 uppercase">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($events_result->num_rows > 0): ?>
                                <?php while ($event = $events_result->fetch_assoc()): ?>
                                    <tr class="transition-colors hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($event['event_name']); ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($event['event_type']); ?>
                                                - <?php echo htmlspecialchars($event['theme']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($event['venue_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            <?php echo number_format($event['expected_guests']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-900 whitespace-nowrap">
                                            ₱<?php echo number_format($event['total_cost'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'confirmed' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'canceled' => 'bg-red-100 text-red-800'
                                            ];
                                            $color = $status_colors[$event['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <div class="flex justify-center gap-1">
                                                <button
                                                    onclick="viewEventDetails(<?php echo htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="px-2 py-1 text-xs text-white transition-colors bg-indigo-500 rounded hover:bg-indigo-600"
                                                    title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($event['status'] === 'pending'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="event_id"
                                                            value="<?php echo $event['event_id']; ?>">
                                                        <input type="hidden" name="action" value="confirm">
                                                        <button type="submit"
                                                            class="px-2 py-1 text-xs text-white transition-colors bg-green-500 rounded hover:bg-green-600"
                                                            title="Confirm">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($event['status'] === 'confirmed'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="event_id"
                                                            value="<?php echo $event['event_id']; ?>">
                                                        <input type="hidden" name="action" value="complete">
                                                        <button type="submit"
                                                            class="px-2 py-1 text-xs text-white transition-colors bg-blue-500 rounded hover:bg-blue-600"
                                                            title="Complete">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($event['status'] !== 'canceled' && $event['status'] !== 'completed'): ?>
                                                    <form method="POST" class="inline"
                                                        onsubmit="return confirm('Cancel this event?');">
                                                        <input type="hidden" name="event_id"
                                                            value="<?php echo $event['event_id']; ?>">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <button type="submit"
                                                            class="px-2 py-1 text-xs text-white transition-colors bg-orange-500 rounded hover:bg-orange-600"
                                                            title="Cancel">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="inline"
                                                    onsubmit="return confirm('Delete this event permanently?');">
                                                    <input type="hidden" name="event_id"
                                                        value="<?php echo $event['event_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit"
                                                        class="px-2 py-1 text-xs text-white transition-colors bg-red-500 rounded hover:bg-red-600"
                                                        title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        <i class="mb-2 text-4xl fas fa-calendar"></i>
                                        <p>No events found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            <!-- Page Info -->
                            <div class="text-sm text-gray-700 flex flex-col sm:flex-row sm:items-center gap-1">
                                <span>
                                    Showing <span class="font-semibold"><?php echo $offset + 1; ?></span> to
                                    <span
                                        class="font-semibold"><?php echo min($offset + $items_per_page, $total_records); ?></span>
                                    of <span class="font-semibold"><?php echo $total_records; ?></span> events
                                </span>
                                <span
                                    class="text-xs sm:ml-3 px-2 py-1 rounded bg-indigo-50 text-indigo-700 font-medium border border-indigo-200">
                                    Page <?php echo $page_num; ?> of <?php echo $total_pages; ?>
                                </span>
                            </div>

                            <!-- Pagination Buttons -->
                            <div class="flex flex-wrap gap-1">
                                <?php
                                // Ensure all variables are integers to prevent type errors
                                $page_num = (int)$page_num;
                                $total_pages = (int)$total_pages;
                                $total_records = (int)$total_records;
                                $offset = (int)$offset;

                                // Build query string for pagination links
                                $query_params = [];
                                if ($status_filter) $query_params['status'] = $status_filter;
                                if ($type_filter) $query_params['type'] = $type_filter;
                                if ($search) $query_params['search'] = $search;

                                // Previous button
                                if ($page_num > 1):
                                    $prev_params = array_merge($query_params, ['page' => $page_num - 1]);
                                ?>
                                    <a href="?<?php echo http_build_query($prev_params); ?>"
                                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span
                                        class="px-3 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <?php
                                // Build page sequence to match specified patterns
                                $pages = [];
                                if ($total_pages <= 7) {
                                    // Show all pages when small
                                    for ($i = 1; $i <= $total_pages; $i++) {
                                        $pages[] = $i;
                                    }
                                } else {
                                    if ($page_num <= 2) {
                                        // [1] 2 3 ... last OR 1 [2] 3 ... last
                                        $pages = [1, 2, 3, '...', $total_pages];
                                    } elseif ($page_num == 3) {
                                        // 1 ... [3] 4 ... last
                                        $pages = [1, '...', 3, 4, '...', $total_pages];
                                    } elseif ($page_num >= 4 && $page_num <= $total_pages - 3) {
                                        // 1 ... [c] c+1 ... last (middle window)
                                        $pages = [1, '...', $page_num, $page_num + 1, '...', $total_pages];
                                    } elseif ($page_num == $total_pages - 2) {
                                        // 1 ... [c] c+1 last
                                        $pages = [1, '...', $page_num, $page_num + 1, $total_pages];
                                    } elseif ($page_num == $total_pages - 1) {
                                        // 1 ... c-1 [c] last
                                        $pages = [1, '...', $page_num - 1, $page_num, $total_pages];
                                    } else { // page_num == $total_pages
                                        // 1 ... last-2 last-1 [last]
                                        $pages = [1, '...', $total_pages - 2, $total_pages - 1, $total_pages];
                                    }
                                }

                                foreach ($pages as $p) {
                                    if ($p === '...') {
                                        echo '<span class="px-3 py-2 text-sm font-medium text-gray-700">...</span>';
                                        continue;
                                    }
                                    $page_params = array_merge($query_params, ['page' => $p]);
                                    if ((int)$p === (int)$page_num) {
                                        echo '<span class="px-3 py-2 text-sm font-bold text-white bg-indigo-600 border border-indigo-600 rounded-lg ring-2 ring-indigo-400" aria-current="page">' . $p . '</span>';
                                    } else {
                                        echo '<a href="?' . http_build_query($page_params) . '" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">' . $p . '</a>';
                                    }
                                }
                                ?>

                                <!-- Next button -->
                                <?php if ($page_num < $total_pages):
                                    $next_params = array_merge($query_params, ['page' => $page_num + 1]);
                                ?>
                                    <a href="?<?php echo http_build_query($next_params); ?>"
                                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span
                                        class="px-3 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($nav_layout === 'sidebar'): ?>
            </div> <!-- Close sidebar inner wrapper -->
        <?php endif; ?>
    </div> <!-- Close main content -->

    <!-- Event Details Modal -->
    <div id="detailsModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="details-modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div id="detailsModalBackdrop"
                class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm" aria-hidden="true">
            </div>

            <!-- Center modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div
                class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <!-- Header -->
                <div class="bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-4">
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
                                        <span id="contractRemainingAmount" class="text-lg font-bold text-red-600"></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                                            <span>Payment Completion</span>
                                            <span id="contractPaymentPercentage">0%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="contractPaymentBar" class="bg-green-600 h-2 rounded-full transition-all" style="width: 0%"></div>
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
                            <p><strong>1. Payment Terms:</strong> Full payment must be completed before the event date. Late payments may result in event cancellation.</p>
                            <p><strong>2. Cancellation Policy:</strong> Cancellations made 30 days prior to the event are eligible for 50% refund. No refund for cancellations within 30 days.</p>
                            <p><strong>3. Venue Usage:</strong> The client agrees to use the venue responsibly and is liable for any damages incurred during the event.</p>
                            <p><strong>4. Capacity Compliance:</strong> The client must not exceed the maximum venue capacity specified in this contract.</p>
                            <p><strong>5. Force Majeure:</strong> Neither party shall be liable for failure to perform obligations due to circumstances beyond reasonable control.</p>
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
                            <p class="text-xs text-gray-500">This is a system-generated contract. For inquiries, please contact Gatherly Event Management.</p>
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
                </div>
            </div>
        </div>
    </div>

    <script>
        // Event Details Modal Functions
        let currentEventData = null;

        function viewEventDetails(event) {
            currentEventData = event;

            // Set event information
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
            document.getElementById('contractGuests').textContent = event.expected_guests ? parseInt(event.expected_guests).toLocaleString() + ' guests' : 'N/A';

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
            document.getElementById('contractVenueLocation').textContent = event.venue_location || 'N/A';
            document.getElementById('contractVenueCapacity').textContent = event.venue_capacity ? parseInt(event.venue_capacity).toLocaleString() + ' guests' : 'N/A';

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
                paymentStatusEl.className = remainingAmount <= 0 ? 'text-base font-semibold text-green-600' : 'text-base font-semibold text-gray-600';
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

        // Close modal when clicking backdrop
        document.getElementById('detailsModalBackdrop').addEventListener('click', closeDetailsModal);
    </script>
</body>

</html>
<?php $conn->close(); ?>