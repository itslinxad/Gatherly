<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';

// Handle delete action
if (isset($_GET['delete'])) {
    $venue_id = intval($_GET['delete']);
    $conn->query("DELETE FROM venues WHERE venue_id = $venue_id");
    $_SESSION['venue_message'] = 'Venue deleted successfully!';
    header("Location: manage-venues.php");
    exit();
}

// Handle status update
if (isset($_GET['toggle_status'])) {
    $venue_id = intval($_GET['toggle_status']);
    $conn->query("UPDATE venues SET status = IF(status = 'active', 'inactive', 'active') WHERE venue_id = $venue_id");
    $_SESSION['venue_message'] = 'Venue status updated successfully!';
    header("Location: manage-venues.php");
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter) {
    $where_clauses[] = "v.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_clauses[] = "(v.venue_name LIKE ? OR l.city LIKE ? OR l.province LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch venues
$query = "SELECT v.*, 
          CONCAT(l.city, ', ', l.province) as location,
          l.city, l.province,
          m.first_name as manager_fname, 
          m.last_name as manager_lname,
          m.email as manager_email
          FROM venues v
          LEFT JOIN locations l ON v.location_id = l.location_id
          LEFT JOIN users m ON v.manager_id = m.user_id
          $where_sql
          ORDER BY v.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $venues = $stmt->get_result();
} else {
    $venues = $conn->query($query);
}

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM venues")->fetch_assoc()['count'],
    'active' => $conn->query("SELECT COUNT(*) as count FROM venues WHERE status = 'active'")->fetch_assoc()['count'],
    'inactive' => $conn->query("SELECT COUNT(*) as count FROM venues WHERE status = 'inactive'")->fetch_assoc()['count'],
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Venues | Admin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        }
    </style>
</head>

<body class="bg-gray-50 font-['Montserrat']">

    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Manage Venues</h1>
                    <p class="text-sm text-gray-600 mt-1">Monitor and manage all venues in the system</p>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (isset($_SESSION['venue_message'])): ?>
                <div class="mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['venue_message']; ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['venue_message']); ?>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Total Venues</p>
                            <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total']); ?></p>
                        </div>
                        <div class="bg-blue-100 p-4 rounded-full">
                            <i class="fas fa-building text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Active Venues</p>
                            <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['active']); ?></p>
                        </div>
                        <div class="bg-green-100 p-4 rounded-full">
                            <i class="fas fa-check-circle text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-gray-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Inactive</p>
                            <p class="text-3xl font-bold text-gray-600"><?php echo number_format($stats['inactive']); ?></p>
                        </div>
                        <div class="bg-gray-100 p-4 rounded-full">
                            <i class="fas fa-pause-circle text-2xl text-gray-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-xl shadow-md mt-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search venues by name or location..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <?php if ($search || $status_filter): ?>
                        <a href="manage-venues.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Venues Grid -->
        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <?php if ($venues && $venues->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php while ($venue = $venues->fetch_assoc()): ?>
                        <?php
                        $imageSrc = !empty($venue['image'])
                            ? 'data:image/jpeg;base64,' . base64_encode($venue['image'])
                            : '../../assets/images/venue-placeholder.jpg';
                        ?>
                        <div class="bg-white border border-gray-200 shadow-md rounded-xl hover:shadow-lg transition-all overflow-hidden">
                            <!-- Image -->
                            <div class="relative w-full h-48 overflow-hidden bg-gray-100">
                                <img src="<?php echo $imageSrc; ?>" alt="Venue Image"
                                    class="w-full h-full object-cover object-center transition-transform duration-300 hover:scale-105">
                                <!-- Status Badge on Image -->
                                <div class="absolute top-3 right-3">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $venue['status'] === 'active' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-gray-100 text-gray-700 border border-gray-300'; ?>">
                                        <?php echo strtoupper($venue['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-5">
                                <h2 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($venue['venue_name']); ?></h2>

                                <div class="space-y-2 mb-4">
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt text-blue-500 mr-2 w-4"></i>
                                        <?php echo htmlspecialchars($venue['location'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-users text-green-500 mr-2 w-4"></i>
                                        Capacity: <?php echo htmlspecialchars($venue['capacity']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-user-tie text-purple-500 mr-2 w-4"></i>
                                        <?php echo htmlspecialchars($venue['manager_fname'] . ' ' . $venue['manager_lname']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-paint-brush text-orange-500 mr-2 w-4"></i>
                                        <?php echo htmlspecialchars($venue['ambiance'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <p class="text-sm text-gray-700 line-clamp-2 mb-4">
                                    <?php echo htmlspecialchars($venue['description']); ?>
                                </p>

                                <!-- Actions -->
                                <div class="flex gap-2 pt-4 border-t border-gray-100">
                                    <a href="venue-details.php?id=<?php echo $venue['venue_id']; ?>"
                                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium text-center">
                                        <i class="fas fa-eye mr-1"></i> View Details
                                    </a>
                                    <a href="?toggle_status=<?php echo $venue['venue_id']; ?>"
                                        onclick="return confirm('Toggle venue status?')"
                                        class="px-4 py-2 <?php echo $venue['status'] === 'active' ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> rounded-lg transition-colors text-sm font-medium"
                                        title="<?php echo $venue['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $venue['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                    </a>
                                    <button onclick="confirmDelete(<?php echo $venue['venue_id']; ?>, '<?php echo htmlspecialchars($venue['venue_name']); ?>')"
                                        class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-sm font-medium">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-20 text-center bg-white border border-gray-200 rounded-2xl shadow-md">
                    <i class="fas fa-building text-5xl text-gray-400 mb-3"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No venues found</h3>
                    <p class="text-gray-500 mb-4">
                        <?php echo ($search || $status_filter) ? 'Try adjusting your search or filter criteria.' : 'There are no venues in the system yet.'; ?>
                    </p>
                    <?php if ($search || $status_filter): ?>
                        <a href="manage-venues.php" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-times mr-2"></i>Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content max-w-md text-center">
            <div class="mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Confirm Delete</h2>
                <p class="text-gray-600">Are you sure you want to delete <strong id="delete_venue_name"></strong>?</p>
                <p class="text-sm text-red-600 mt-2">This action cannot be undone.</p>
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
        function confirmDelete(id, name) {
            document.getElementById('delete_venue_id').value = id;
            document.getElementById('delete_venue_name').textContent = name;
            openModal('deleteModal');
        }

        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('show');
                }
            });
        });
    </script>

</body>

</html>
<?php $conn->close(); ?>