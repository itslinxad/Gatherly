<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';
$first_name = $_SESSION['first_name'] ?? 'Manager';
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

// Pagination settings
$items_per_page = 10;
$page_param = isset($_GET['page']) ? $_GET['page'] : 1;
if (is_numeric($page_param)) {
    $pagination_current_page = max(1, intval($page_param));
} else {
    $pagination_current_page = 1;
}
$offset = ($pagination_current_page - 1) * $items_per_page;

// Handle cancel/reject booking
if (isset($_POST['cancel_submit'])) {
    $event_id = intval($_POST['cancel_id']);
    $stmt = $conn->prepare("UPDATE events SET status = 'cancelled' WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $stmt->close();
    header("Location: bookings.php");
    exit();
}

// Handle status update (iterative progression)
if (isset($_POST['status_submit'])) {
    $event_id = intval($_POST['status_event_id']);
    $current_status = $_POST['current_status'] ?? 'pending';

    // Define status progression workflow
    $status_progression = [
        'pending' => 'confirmed',
        'confirmed' => 'completed',
        'completed' => 'completed', // Already at final status
        'cancelled' => 'cancelled'  // Cannot progress from cancelled
    ];

    $new_status = $status_progression[$current_status] ?? 'pending';

    $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
    $stmt->bind_param("si", $new_status, $event_id);
    $stmt->execute();
    $stmt->close();

    // If status changed to confirmed, send contract email
    if ($new_status === 'confirmed' && $current_status === 'pending') {
        try {
            require_once '../../../src/services/EmailService.php';
            $emailService = new EmailService();
            $result = $emailService->sendBookingConfirmation($event_id);

            // Store message in session for display
            if ($result['success']) {
                $_SESSION['success_message'] = 'Booking confirmed and contract sent to organizer via email!';
            } else {
                $_SESSION['warning_message'] = 'Booking confirmed but email failed to send: ' . $result['message'];
            }
        } catch (Exception $e) {
            error_log("Error sending confirmation email: " . $e->getMessage());
            $_SESSION['warning_message'] = 'Booking confirmed but email could not be sent. Please check email configuration.';
        }
    }

    header("Location: bookings.php");
    exit();
}

// Filter & sort
$status_filter = $conn->real_escape_string($_GET['status'] ?? '');
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$where_clause = $status_filter ? "AND e.status = '{$status_filter}'" : "";

switch ($sort_by) {
    case 'name_asc':
        $order_clause = "ORDER BY e.event_name ASC";
        break;
    case 'name_desc':
        $order_clause = "ORDER BY e.event_name DESC";
        break;
    case 'cost_asc':
        $order_clause = "ORDER BY e.total_cost ASC";
        break;
    case 'cost_desc':
        $order_clause = "ORDER BY e.total_cost DESC";
        break;
    case 'date_asc':
        $order_clause = "ORDER BY e.event_date ASC";
        break;
    default:
        $order_clause = "ORDER BY e.event_date DESC";
        break;
}

// Count total records for pagination
$count_sql = "
    SELECT COUNT(DISTINCT e.event_id) as total
    FROM events e
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    WHERE v.manager_id = {$_SESSION['user_id']}
    $where_clause
";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = $total_records > 0 ? ceil($total_records / $items_per_page) : 1;

// Fetch events with proper location join and payment information
$sql = "
    SELECT 
        e.event_id, e.event_name, e.event_type, e.theme,
        e.expected_guests, e.total_cost, e.event_date, e.status,
        e.time_start, e.time_end, e.payment_status,
        CONCAT(org.first_name, ' ', org.last_name) AS organizer_name,
        org.first_name, org.last_name, org.email as organizer_email, org.phone as organizer_phone,
        v.venue_name, v.capacity as venue_capacity,
        CONCAT(l.city, ', ', l.province) as location,
        CONCAT(l.baranggay, ', ', l.city, ', ', l.province) as full_location,
        COALESCE(SUM(ep.amount_paid), 0) as total_paid
    FROM events e
    LEFT JOIN users org ON e.organizer_id = org.user_id
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    LEFT JOIN locations l ON v.location_id = l.location_id
    LEFT JOIN event_payments ep ON e.event_id = ep.event_id AND ep.payment_status = 'verified'
    WHERE v.manager_id = {$_SESSION['user_id']}
    $where_clause
    GROUP BY e.event_id
    $order_clause
    LIMIT $items_per_page OFFSET $offset
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings | Gatherly</title>
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
            z-index: 50;
            backdrop-filter: blur(3px);
            background-color: rgba(0, 0, 0, 0.25);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 650px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            z-index: 60;
        }

        @media print {

            /* Hide all page elements */
            body * {
                visibility: hidden !important;
            }

            /* Show only the modal and its children */
            #detailsModal,
            #detailsModal * {
                visibility: visible !important;
            }

            /* Hide modal backdrop and footer */
            #detailsModalBackdrop,
            #detailsModal .bg-gray-50.border-t,
            #detailsModal .bg-gray-50.border-t * {
                visibility: hidden !important;
                display: none !important;
            }

            /* Hide close button in header */
            #detailsModal .bg-gradient-to-r button {
                visibility: hidden !important;
                display: none !important;
            }

            /* Reset modal positioning for print */
            #detailsModal {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
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
                visibility: hidden !important;
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

            /* Force colors to print */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
                    <h1 class="text-2xl font-bold text-gray-800">Bookings</h1>
                    <p class="text-sm text-gray-600">Create, view, and manage your bookings now</p>
                </div>
                <div class="px-4 sm:px-6 lg:px-8">
                <?php else: ?>
                    <!-- Header for Navbar Layout -->
                <?php endif; ?>

                <!-- Success/Warning Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            <p class="text-green-800 font-medium">
                                <?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['warning_message'])): ?>
                    <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
                            <p class="text-yellow-800 font-medium">
                                <?php echo htmlspecialchars($_SESSION['warning_message']); ?></p>
                        </div>
                    </div>
                    <?php unset($_SESSION['warning_message']); ?>
                <?php endif; ?>

                <!-- Main -->
                <main class="<?php echo $nav_layout !== 'sidebar' ? 'container px-6 py-10 mx-auto' : ''; ?>">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="mb-4 text-sm text-gray-600">
                            Showing
                            <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $result->num_rows, $total_records); ?>
                            of <?php echo $total_records; ?> bookings
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col items-center justify-between mb-8 space-y-4 sm:flex-row sm:space-y-0">
                        <?php if ($nav_layout !== 'sidebar'): ?>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-800">Bookings</h1>
                                <p class="text-gray-600">Create, view, and manage your bookings now</p>
                            </div>
                        <?php else: ?>
                            <div class="relative flex-1 max-w-md">
                                <input type="text" id="searchInput" placeholder="Search bookings..."
                                    class="w-full px-4 py-2 pl-10 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <i
                                    class="absolute text-gray-400 transform -translate-y-1/2 fas fa-search left-3 top-1/2"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-3">
                            <form method="GET" class="flex flex-wrap items-center gap-2">
                                <label class="font-medium text-gray-700">Status:</label>
                                <select name="status" class="p-2 bg-white border border-gray-300 rounded-lg">
                                    <option value="">All</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>
                                        Pending</option>
                                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>
                                        Confirmed
                                    </option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>
                                        Completed
                                    </option>
                                    <option value="canceled" <?= $status_filter === 'canceled' ? 'selected' : '' ?>>
                                        Canceled
                                    </option>
                                </select>
                                <select name="sort_by" class="p-2 bg-white border border-gray-300 rounded-lg">
                                    <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Date
                                        (Newest)
                                    </option>
                                    <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Date
                                        (Oldest)</option>
                                    <option value="cost_desc" <?= $sort_by === 'cost_desc' ? 'selected' : '' ?>>Cost
                                        (High → Low)
                                    </option>
                                    <option value="cost_asc" <?= $sort_by === 'cost_asc' ? 'selected' : '' ?>>Cost (Low
                                        → High)
                                    </option>
                                    <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A →
                                        Z)</option>
                                    <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z
                                        → A)
                                    </option>
                                </select>
                                <button
                                    class="px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">Apply</button>
                            </form>

                        </div>
                    </div>

                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                            <table class="w-full">
                                <thead class="bg-gradient-to-r from-green-50 to-teal-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Event Name</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Organizer</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Venue</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Location</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Date</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Total Cost</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php while ($row = $result->fetch_assoc()):
                                        $statusColor = match (strtolower($row['status'])) {
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'confirmed' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-blue-100 text-blue-800',
                                            'canceled' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($row['event_name']) ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-700">
                                                <?= htmlspecialchars($row['organizer_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-700">
                                                <?= htmlspecialchars($row['venue_name'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-600">
                                                <?= htmlspecialchars($row['location'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-700">
                                                <?= date('M d, Y', strtotime($row['event_date'])) ?></td>
                                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                                ₱<?= number_format($row['total_cost'], 2) ?></td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex px-3 py-1 text-xs font-medium rounded-full <?= $statusColor ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex justify-center gap-2">
                                                    <button
                                                        class="view-btn px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors"
                                                        data-booking='<?= htmlentities(json_encode($row)) ?>'
                                                        title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php
                                                    $status_icons = [
                                                        'pending' => 'fa-check-circle',
                                                        'confirmed' => 'fa-flag-checkered',
                                                        'completed' => 'fa-check-double',
                                                        'cancelled' => 'fa-ban'
                                                    ];
                                                    $status_labels = [
                                                        'pending' => 'Confirm',
                                                        'confirmed' => 'Complete',
                                                        'completed' => 'Completed',
                                                        'cancelled' => 'Cancelled'
                                                    ];
                                                    $current_status = $row['status'];
                                                    $is_final = in_array($current_status, ['completed', 'cancelled']);
                                                    ?>
                                                    <button
                                                        class="status-btn px-3 py-1.5 text-xs font-semibold text-white rounded-lg transition-colors <?= $is_final ? 'bg-gray-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700' ?>"
                                                        data-id="<?= $row['event_id'] ?>" data-status="<?= $row['status'] ?>"
                                                        title="<?= $is_final ? 'Status is final' : 'Click to ' . $status_labels[$current_status] ?>"
                                                        <?= $is_final ? 'disabled' : '' ?>>
                                                        <i class="fas <?= $status_icons[$current_status] ?>"></i>
                                                    </button>
                                                    <button
                                                        class="cancel-btn px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                                                        data-id="<?= $row['event_id'] ?>"
                                                        data-name="<?= htmlspecialchars($row['event_name']) ?>"
                                                        title="Cancel Booking">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <?php
                            // Build query string for pagination
                            $query_params = [];
                            if (!empty($status_filter)) {
                                $query_params[] = 'status=' . urlencode($status_filter);
                            }
                            if (!empty($sort_by)) {
                                $query_params[] = 'sort_by=' . urlencode($sort_by);
                            }
                            $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                            ?>
                            <div class="mt-6 p-6 bg-white rounded-lg shadow-md">
                                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                    <div class="text-sm text-gray-600">
                                        Page <?php echo $pagination_current_page; ?> of <?php echo $total_pages; ?>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- First Page -->
                                        <?php if ($pagination_current_page > 1): ?>
                                            <a href="?page=1<?php echo $query_string; ?>"
                                                class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                                <i class="fas fa-angle-double-left"></i>
                                            </span>
                                        <?php endif; ?>

                                        <!-- Previous Page -->
                                        <?php if ($pagination_current_page > 1): ?>
                                            <a href="?page=<?php echo ($pagination_current_page - 1); ?><?php echo $query_string; ?>"
                                                class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                                <i class="fas fa-angle-left"></i> Previous
                                            </a>
                                        <?php else: ?>
                                            <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                                <i class="fas fa-angle-left"></i> Previous
                                            </span>
                                        <?php endif; ?>

                                        <!-- Page Numbers -->
                                        <div class="flex items-center gap-1">
                                            <?php
                                            $start_page = max(1, $pagination_current_page - 2);
                                            $end_page = min($total_pages, $pagination_current_page + 2);

                                            for ($i = $start_page; $i <= $end_page; $i++):
                                            ?>
                                                <?php if ($i == $pagination_current_page): ?>
                                                    <span class="px-3 py-2 bg-green-600 text-white rounded-lg font-semibold">
                                                        <?php echo $i; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <a href="?page=<?php echo $i; ?><?php echo $query_string; ?>"
                                                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                                        <?php echo $i; ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>

                                        <!-- Next Page -->
                                        <?php if ($pagination_current_page < $total_pages): ?>
                                            <a href="?page=<?php echo ($pagination_current_page + 1); ?><?php echo $query_string; ?>"
                                                class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                                Next <i class="fas fa-angle-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                                Next <i class="fas fa-angle-right"></i>
                                            </span>
                                        <?php endif; ?>

                                        <!-- Last Page -->
                                        <?php if ($pagination_current_page < $total_pages): ?>
                                            <a href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>"
                                                class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="px-3 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                                <i class="fas fa-angle-double-right"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="py-20 text-center text-gray-500">
                            <i class="mb-3 text-5xl text-gray-400 fas fa-calendar-times"></i>
                            <p class="text-lg">No bookings found.</p>
                        </div>
                    <?php endif; ?>
                </main>

                <!-- Modals -->
                <div id="viewModal" class="modal-overlay">
                    <div class="modal-content bg-white rounded-lg shadow-xl p-0 max-w-2xl w-full">
                        <div class="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-4 rounded-t-lg">
                            <h2 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-calendar-check mr-3"></i>
                                Booking Details
                            </h2>
                        </div>
                        <div id="viewContent" class="p-6"></div>
                        <div class="px-6 pb-6 flex justify-end gap-3">
                            <button onclick="closeModal('viewModal')"
                                class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-semibold transition-colors">
                                <i class="fas fa-times mr-2"></i>Close
                            </button>
                        </div>
                    </div>
                </div>

                <div id="statusModal" class="modal-overlay">
                    <div class="modal-content max-w-md">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-sync-alt text-indigo-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-800">Update Booking Status</h2>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-center flex-1">
                                    <p class="text-xs text-gray-500 mb-1">Current Status</p>
                                    <span id="currentStatusBadge"
                                        class="inline-flex items-center px-3 py-1.5 text-sm font-bold rounded-full"></span>
                                </div>
                                <div class="px-3">
                                    <i class="fas fa-arrow-right text-gray-400 text-xl"></i>
                                </div>
                                <div class="text-center flex-1">
                                    <p class="text-xs text-gray-500 mb-1">New Status</p>
                                    <span id="newStatusBadge"
                                        class="inline-flex items-center px-3 py-1.5 text-sm font-bold rounded-full"></span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Note:</strong> This will update the booking status and notify the organizer.
                            </p>
                        </div>
                        <form method="POST" id="statusForm">
                            <input type="hidden" name="status_event_id" id="status_event_id">
                            <input type="hidden" name="current_status" id="current_status">
                            <input type="hidden" name="status_submit" value="1">
                            <div class="flex justify-end gap-2 pt-3">
                                <button type="button" onclick="closeModal('statusModal')"
                                    class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold transition-colors">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </button>
                                <button type="submit"
                                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold transition-colors">
                                    <i class="fas fa-check mr-2"></i>Confirm Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="cancelModal" class="modal-overlay">
                    <div class="modal-content text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-3">Cancel Booking</h2>
                        <p class="text-gray-600 mb-2">Are you sure you want to cancel this booking?</p>
                        <p id="cancelEventName" class="text-lg font-semibold text-gray-800 mb-4"></p>
                        <p class="text-sm text-gray-500 mb-6">This will update the status to "Cancelled" and notify the
                            organizer.</p>
                        <form method="POST">
                            <input type="hidden" name="cancel_id" id="cancel_id">
                            <div class="flex justify-center gap-3">
                                <button type="button" onclick="closeModal('cancelModal')"
                                    class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold">No,
                                    Keep It</button>
                                <button type="submit" name="cancel_submit"
                                    class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                                    <i class="fas fa-ban mr-2"></i>Yes, Cancel Booking
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Event Details/Contract Modal -->
                <div id="detailsModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto"
                    aria-labelledby="details-modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <!-- Background overlay -->
                        <div id="detailsModalBackdrop"
                            class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm" aria-hidden="true">
                        </div>

                        <!-- Center modal -->
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                            aria-hidden="true">&#8203;</span>

                        <!-- Modal panel -->
                        <div
                            class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                            <!-- Header -->
                            <div class="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xl font-bold text-white flex items-center" id="details-modal-title">
                                        <i class="fas fa-file-contract mr-3"></i>
                                        Event Contract & Details
                                    </h3>
                                    <button onclick="closeDetailsModal()"
                                        class="text-white hover:text-gray-200 transition-colors">
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
                                    <p class="text-xs text-gray-500 mt-1">Contract Date: <?php echo date('F d, Y'); ?>
                                    </p>
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
                                            <p id="contractClientName" class="text-base font-semibold text-gray-900">
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Email Address:</p>
                                            <p id="contractClientEmail" class="text-base font-semibold text-gray-900">
                                            </p>
                                        </div>
                                        <div class="col-span-2">
                                            <p class="text-sm text-gray-600">Contact Number:</p>
                                            <p id="contractClientPhone" class="text-base font-semibold text-gray-900">
                                            </p>
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
                                            <p id="contractVenueLocation" class="text-base font-semibold text-gray-900">
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">Capacity:</p>
                                            <p id="contractVenueCapacity" class="text-base font-semibold text-gray-900">
                                            </p>
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
                                                    <span id="contractTotalCost"
                                                        class="text-base font-bold text-gray-900"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-sm text-gray-600">Amount Paid:</span>
                                                    <span id="contractPaidAmount"
                                                        class="text-base font-bold text-green-600"></span>
                                                </div>
                                            </div>
                                            <div class="pt-3 border-t border-gray-300">
                                                <div class="flex justify-between mb-2">
                                                    <span class="text-base font-semibold text-gray-700">Outstanding
                                                        Balance:</span>
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
                                                            class="bg-green-600 h-2 rounded-full transition-all"
                                                            style="width: 0%"></div>
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
                                        <p><strong>1. Payment Terms:</strong> Full payment must be completed before the
                                            event date. Late payments may result in event cancellation.</p>
                                        <p><strong>2. Cancellation Policy:</strong> Cancellations made 30 days prior to
                                            the event are eligible for 50% refund. No refund for cancellations within 30
                                            days.</p>
                                        <p><strong>3. Venue Usage:</strong> The client agrees to use the venue
                                            responsibly and is liable for any damages incurred during the event.</p>
                                        <p><strong>4. Capacity Compliance:</strong> The client must not exceed the
                                            maximum venue capacity specified in this contract.</p>
                                        <p><strong>5. Force Majeure:</strong> Neither party shall be liable for failure
                                            to perform obligations due to circumstances beyond reasonable control.</p>
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
                                                <p class="text-sm font-semibold text-gray-900">GATHERLY REPRESENTATIVE
                                                </p>
                                                <p class="text-xs text-gray-600 mt-1">Authorized Signatory</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center mt-6">
                                        <p class="text-xs text-gray-500">This is a system-generated contract. For
                                            inquiries, please contact Gatherly Event Management.</p>
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
                    // Search functionality
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        searchInput.addEventListener('input', function(e) {
                            const searchTerm = e.target.value.toLowerCase();
                            const rows = document.querySelectorAll('tbody tr');

                            rows.forEach(row => {
                                const text = row.textContent.toLowerCase();
                                if (text.includes(searchTerm)) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                        });
                    }

                    function openModal(id) {
                        document.getElementById(id).classList.add('show');
                    }

                    function closeModal(id) {
                        document.getElementById(id).classList.remove('show');
                    }

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
                        document.getElementById('contractGuests').textContent = event.expected_guests ? parseInt(event
                            .expected_guests).toLocaleString() + ' guests' : 'N/A';

                        // Set status badge with appropriate styling
                        const statusEl = document.getElementById('contractStatus');
                        statusEl.textContent = event.status ? event.status.charAt(0).toUpperCase() + event.status.slice(1) :
                            'N/A';
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
                            case 'cancelled':
                                statusEl.className = 'text-base font-semibold text-red-600';
                                break;
                            default:
                                statusEl.className = 'text-base font-semibold text-gray-600';
                        }

                        // Set client information
                        const clientName = (event.first_name || '') + ' ' + (event.last_name || '');
                        document.getElementById('contractClientName').textContent = clientName.trim() || event
                            .organizer_name || 'N/A';
                        document.getElementById('contractClientEmail').textContent = event.organizer_email || 'N/A';
                        document.getElementById('contractClientPhone').textContent = event.organizer_phone || 'N/A';
                        document.getElementById('signatureClientName').textContent = clientName.trim() || event
                            .organizer_name || 'N/A';

                        // Set venue information
                        document.getElementById('contractVenueName').textContent = event.venue_name || 'N/A';
                        document.getElementById('contractVenueLocation').textContent = event.full_location || event
                            .location || 'N/A';
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
                        document.getElementById('contractPaidAmount').textContent = '₱' + paidAmount.toLocaleString(
                            'en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        document.getElementById('contractRemainingAmount').textContent = '₱' + remainingAmount
                            .toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        document.getElementById('contractPaymentPercentage').textContent = paymentPercentage.toFixed(1) +
                            '%';
                        document.getElementById('contractPaymentBar').style.width = Math.min(paymentPercentage, 100) + '%';

                        // Set payment status
                        const paymentStatusEl = document.getElementById('contractPaymentStatus');
                        if (event.payment_status) {
                            paymentStatusEl.textContent = event.payment_status.charAt(0).toUpperCase() + event
                                .payment_status.slice(1);
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
                    const detailsModalBackdrop = document.getElementById('detailsModalBackdrop');
                    if (detailsModalBackdrop) {
                        detailsModalBackdrop.addEventListener('click', closeDetailsModal);
                    }

                    document.querySelectorAll('.view-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const data = JSON.parse(btn.dataset.booking);
                            viewEventDetails(data);
                        });
                    });

                    document.querySelectorAll('.status-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            if (btn.disabled) return;

                            const eventId = btn.dataset.id;
                            const currentStatus = btn.dataset.status;

                            // Define status progression
                            const statusProgression = {
                                'pending': 'confirmed',
                                'confirmed': 'completed',
                                'completed': 'completed',
                                'cancelled': 'cancelled'
                            };

                            const statusConfig = {
                                'pending': {
                                    label: 'Pending',
                                    color: 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    icon: 'fa-clock'
                                },
                                'confirmed': {
                                    label: 'Confirmed',
                                    color: 'bg-green-100 text-green-800 border-green-200',
                                    icon: 'fa-check-circle'
                                },
                                'completed': {
                                    label: 'Completed',
                                    color: 'bg-blue-100 text-blue-800 border-blue-200',
                                    icon: 'fa-flag-checkered'
                                },
                                'cancelled': {
                                    label: 'Cancelled',
                                    color: 'bg-red-100 text-red-800 border-red-200',
                                    icon: 'fa-times-circle'
                                }
                            };

                            const newStatus = statusProgression[currentStatus];
                            const currentConfig = statusConfig[currentStatus];
                            const newConfig = statusConfig[newStatus];

                            // Set modal values
                            document.getElementById('status_event_id').value = eventId;
                            document.getElementById('current_status').value = currentStatus;

                            // Update current status badge
                            const currentBadge = document.getElementById('currentStatusBadge');
                            currentBadge.className =
                                `inline-flex items-center px-3 py-1.5 text-sm font-bold rounded-full border ${currentConfig.color}`;
                            currentBadge.innerHTML =
                                `<i class="fas ${currentConfig.icon} mr-2"></i>${currentConfig.label}`;

                            // Update new status badge
                            const newBadge = document.getElementById('newStatusBadge');
                            newBadge.className =
                                `inline-flex items-center px-3 py-1.5 text-sm font-bold rounded-full border ${newConfig.color}`;
                            newBadge.innerHTML =
                                `<i class="fas ${newConfig.icon} mr-2"></i>${newConfig.label}`;

                            openModal('statusModal');
                        });
                    });

                    document.querySelectorAll('.cancel-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            document.getElementById('cancel_id').value = btn.dataset.id;
                            document.getElementById('cancelEventName').textContent = btn.dataset.name;
                            openModal('cancelModal');
                        });
                    });
                </script>

                <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            <?php endif; ?>
            </div>

</body>

</html>