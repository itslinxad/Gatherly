<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';
$first_name = $_SESSION['first_name'] ?? 'Manager';
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';

// Handle delete
if (isset($_POST['delete_submit'])) {
    $del_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    header("Location: bookings.php");
    exit();
}

// Handle edit
if (isset($_POST['edit_submit'])) {
    $eid = intval($_POST['edit_id']);

    $stmt = $conn->prepare("SELECT event_name, event_type, theme, expected_guests, total_cost, event_date, status FROM events WHERE event_id=? LIMIT 1");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $result = $stmt->get_result();
    $old = $result->fetch_assoc();
    $stmt->close();

    if (!$old) {
        header("Location: bookings.php");
        exit();
    }

    // Use submitted values if not empty, otherwise keep old value
    $ename = trim($_POST['event_name'] ?? '') ?: $old['event_name'];

    $etype_raw = trim($_POST['event_type'] ?? '');
    $etype = $etype_raw !== '' ? $conn->real_escape_string($etype_raw) : $old['event_type'];

    $theme_raw = trim($_POST['theme'] ?? '');
    $theme = $theme_raw !== '' ? $conn->real_escape_string($theme_raw) : $old['theme'];

    $guests_raw = $_POST['expected_guests'] ?? '';
    $guests = $guests_raw !== '' ? intval($guests_raw) : $old['expected_guests'];

    $cost_raw = $_POST['total_cost'] ?? '';
    $cost = $cost_raw !== '' ? floatval($cost_raw) : $old['total_cost'];

    // Handle datetime
    $edate_raw = trim($_POST['event_date'] ?? '');
    $edate = $old['event_date'];

    if ($edate_raw !== '') {
        // Convert datetime-local string (YYYY-MM-DDTHH:MM) to YYYY-MM-DD HH:MM:SS format
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $edate_raw);

        if ($dt) {
            $edate = $dt->format('Y-m-d H:i:s');
        }
    }

    $estatus = $_POST['status'] ?? $old['status'];

    // Prepare update query 
    $stmt = $conn->prepare("UPDATE events 
        SET event_name=?, event_type=?, theme=?, expected_guests=?, total_cost=?, event_date=?, status=? 
        WHERE event_id=?");

    $stmt->bind_param(
        "sssidssi",
        $ename,
        $etype,
        $theme,
        $guests,
        $cost,
        $edate,
        $estatus,
        $eid
    );

    $stmt->execute();
    $stmt->close();

    header("Location: bookings.php");
    exit();
}

// Filter & sort
$status_filter = $conn->real_escape_string($_GET['status'] ?? '');
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$where_clause = $status_filter ? "WHERE e.status = '{$status_filter}'" : "";

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

// Fetch events with proper location join
$sql = "
    SELECT 
        e.event_id, e.event_name, e.event_type, e.theme,
        e.expected_guests, e.total_cost, e.event_date, e.status,
        CONCAT(org.first_name, ' ', org.last_name) AS organizer_name,
        v.venue_name,
        CONCAT(l.city, ', ', l.province) as location
    FROM events e
    LEFT JOIN users org ON e.organizer_id = org.user_id
    LEFT JOIN venues v ON e.venue_id = v.venue_id
    LEFT JOIN locations l ON v.location_id = l.location_id
    WHERE v.manager_id = {$_SESSION['user_id']}
    $where_clause
    $order_clause
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

                <!-- Main -->
                <main class="<?php echo $nav_layout !== 'sidebar' ? 'container px-6 py-10 mx-auto' : ''; ?>">
                    <div class="flex flex-col items-center justify-between mb-8 space-y-4 sm:flex-row sm:space-y-0">
                        <?php if ($nav_layout !== 'sidebar'): ?>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-800">Bookings</h1>
                                <p class="text-gray-600">Create, view, and manage your bookings now</p>
                            </div>
                        <?php else: ?>
                            <div class="relative flex-1 max-w-md">
                                <input type="text" id="searchInput" placeholder="Search bookings..."
                                    class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <i
                                    class="absolute text-gray-400 transform -translate-y-1/2 fas fa-search left-3 top-1/2"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-3">
                            <form method="GET" class="flex flex-wrap items-center gap-2">
                                <label class="font-medium text-gray-700">Status:</label>
                                <select name="status" class="p-2 border border-gray-300 rounded-lg">
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
                                <select name="sort_by" class="p-2 border border-gray-300 rounded-lg">
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
                                                    <button
                                                        class="edit-btn px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                                                        data-booking='<?= htmlentities(json_encode($row)) ?>' title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button
                                                        class="delete-btn px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                                                        data-id="<?= $row['event_id'] ?>" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-20 text-center text-gray-500">
                            <i class="mb-3 text-5xl text-gray-400 fas fa-calendar-times"></i>
                            <p class="text-lg">No bookings found.</p>
                        </div>
                    <?php endif; ?>
                </main>

                <!-- Modals -->
                <div id="viewModal" class="modal-overlay">
                    <div class="modal-content bg-white rounded-lg shadow-lg p-6 max-w-lg w-full">
                        <h2 class="text-xl font-bold mb-4 text-gray-800">Booking Details</h2>
                        <div id="viewContent" class="text-sm text-gray-700 space-y-2"></div>
                        <div class="mt-4 text-right">
                            <button onclick="closeModal('viewModal')"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                Close
                            </button>
                        </div>
                    </div>
                </div>

                <div id="editModal" class="modal-overlay">
                    <div class="modal-content">
                        <h2 class="text-xl font-bold mb-4 text-gray-800">Edit Booking</h2>
                        <form method="POST" id="editForm" class="space-y-3">
                            <input type="hidden" name="edit_id" id="edit_id">
                            <label>Event Name:</label>
                            <input type="text" name="event_name" id="edit_event_name"
                                class="w-full border rounded-lg p-2">
                            <label>Type:</label>
                            <input type="text" name="event_type" id="edit_event_type"
                                class="w-full border rounded-lg p-2">
                            <label>Theme:</label>
                            <input type="text" name="theme" id="edit_theme" class="w-full border rounded-lg p-2">
                            <label>Expected Guests:</label>
                            <input type="number" name="expected_guests" id="edit_guests"
                                class="w-full border rounded-lg p-2">
                            <label>Total Cost (₱):</label>
                            <input type="number" name="total_cost" id="edit_cost" step="0.01"
                                class="w-full border rounded-lg p-2">
                            <label>Date:</label>
                            <input type="datetime-local" name="event_date" id="edit_date"
                                class="w-full border rounded-lg p-2">
                            <label>Status:</label>
                            <select name="status" id="edit_status" class="w-full border rounded-lg p-2">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="canceled">Canceled</option>
                            </select>
                            <div class="flex justify-end gap-2 pt-3">
                                <button type="button" onclick="closeModal('editModal')"
                                    class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold">Cancel</button>
                                <button type="submit" name="edit_submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Save
                                    Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="deleteModal" class="modal-overlay">
                    <div class="modal-content text-center">
                        <h2 class="text-lg font-bold text-gray-800 mb-3">Confirm Delete</h2>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this booking?</p>
                        <form method="POST">
                            <input type="hidden" name="delete_id" id="delete_id">
                            <div class="flex justify-center gap-3">
                                <button type="button" onclick="closeModal('deleteModal')"
                                    class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold">Cancel</button>
                                <button type="submit" name="delete_submit"
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Delete</button>
                            </div>
                        </form>
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

                    document.querySelectorAll('.view-btn').forEach(btn => {
                                let html = `
        <p><strong>Event Name:</strong> ${data.event_name}</p>
        <p><strong>Type:</strong> ${data.event_type || 'N/A'}</p>
        <p><strong>Theme:</strong> ${data.theme || 'N/A'}</p>
        <p><strong>Expected Guests:</strong> ${data.expected_guests || 'N/A'}</p>
        <p><strong>Total Cost:</strong> ₱${parseFloat(data.total_cost).toLocaleString()}</p>
        <p><strong>Date:</strong> ${data.event_date}</p>
        <p><strong>Organizer:</strong> ${data.organizer_name || 'N/A'}</p>
        <p><strong>Venue:</strong> ${data.venue_name || 'N/A'}</p>
        <p><strong>Location:</strong> ${data.location || 'N/A'}</p>
      `; < p > < strong > Coordinator: < /strong> ${data.coordinator_name}</p >
                                    <
                                    p > < strong > Venue: < /strong> ${data.venue_name}</p >
                                    `;
                            document.getElementById('viewContent').innerHTML = html;
                            openModal('viewModal');
                        });
                    });

                    document.querySelectorAll('.edit-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const d = JSON.parse(btn.dataset.booking);

                            let formattedDate = '';
                            if (d.event_date) {
                                let dateStr = d.event_date.replace(' ', 'T').substring(0, 16);
                                formattedDate = dateStr;
                            }

                            document.getElementById('edit_id').value = d.event_id;
                            document.getElementById('edit_event_name').value = d.event_name || '';
                            document.getElementById('edit_event_type').value = d.event_type || '';
                            document.getElementById('edit_theme').value = d.theme || '';
                            document.getElementById('edit_guests').value = d.expected_guests || '';
                            document.getElementById('edit_cost').value = d.total_cost || '';
                            document.getElementById('edit_date').value = formattedDate;
                            document.getElementById('edit_status').value = d.status || 'pending';

                            openModal('editModal');
                        });
                    });

                    document.querySelectorAll('.delete-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            document.getElementById('delete_id').value = btn.dataset.id;
                            openModal('deleteModal');
                        });

                    });
                </script>

                <?php if ($nav_layout === 'sidebar'): ?>
                </div>
            <?php endif; ?>
            </div>

</body>

</html>