<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Get user details
    if ($_GET['ajax'] === 'get_user' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        $stmt = $conn->prepare("SELECT u.*, 
            (SELECT COUNT(*) FROM events WHERE organizer_id = u.user_id) as events_count,
            (SELECT COUNT(*) FROM venues WHERE manager_id = u.user_id) as venues_count
            FROM users u WHERE u.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_assoc());
        exit();
    }

    // Export users
    if ($_GET['ajax'] === 'export') {
        $format = $_GET['format'] ?? 'csv';
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';

        $query = "SELECT user_id, first_name, last_name, email, role, status, created_at FROM users WHERE 1=1";
        if ($role) $query .= " AND role = '" . $conn->real_escape_string($role) . "'";
        if ($status) $query .= " AND status = '" . $conn->real_escape_string($status) . "'";

        $result = $conn->query($query);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Status', 'Joined Date']);

            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['user_id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['role'],
                    $row['status'],
                    $row['created_at']
                ]);
            }
            fclose($output);
        }
        exit();
    }
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Bulk actions
    if ($action === 'bulk_activate' || $action === 'bulk_deactivate' || $action === 'bulk_delete') {
        $user_ids = $_POST['user_ids'] ?? [];
        if (!empty($user_ids) && is_array($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $types = str_repeat('i', count($user_ids));

            if ($action === 'bulk_activate') {
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id IN ($placeholders)");
            } elseif ($action === 'bulk_deactivate') {
                $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id IN ($placeholders)");
            } elseif ($action === 'bulk_delete') {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
            }

            if ($stmt) {
                $stmt->bind_param($types, ...$user_ids);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    // Single user actions
    else {
        $user_id = $_POST['user_id'] ?? null;
        if ($user_id && is_numeric($user_id)) {
            $stmt = null;
            switch ($action) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                    break;
                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
                    break;
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    break;
            }
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    header("Location: manage-users.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch users with filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_filter = $_GET['sort'] ?? 'latest';
$search = $_GET['search'] ?? '';

// Build query with prepared statement for search
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}
if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}
if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);
$order_clause = $sort_filter === 'oldest' ? "ORDER BY created_at ASC" : "ORDER BY created_at DESC";

// Count total records
$count_query = "SELECT COUNT(*) as total FROM users WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Fetch users
$query = "SELECT * FROM users WHERE $where_clause $order_clause LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$users_result = $stmt->get_result();

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['active'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
$stats['inactive'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'")->fetch_assoc()['count'];
$stats['organizers'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'organizer'")->fetch_assoc()['count'];
$stats['managers'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'")->fetch_assoc()['count'];
$stats['admins'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'administrator'")->fetch_assoc()['count'];

// Get top revenue generators
$top_revenue_users = $conn->query("
    SELECT 
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        u.role,
        u.email,
        CASE 
            WHEN u.role = 'organizer' THEN COALESCE((SELECT SUM(total_cost) FROM events WHERE organizer_id = u.user_id AND status = 'completed'), 0)
            WHEN u.role = 'manager' THEN COALESCE((SELECT SUM(e.total_cost) FROM events e JOIN venues v ON e.venue_id = v.venue_id WHERE v.manager_id = u.user_id AND e.status = 'completed'), 0)
            ELSE 0
        END as total_revenue,
        CASE 
            WHEN u.role = 'organizer' THEN (SELECT COUNT(*) FROM events WHERE organizer_id = u.user_id AND status = 'completed')
            WHEN u.role = 'manager' THEN (SELECT COUNT(DISTINCT e.event_id) FROM events e JOIN venues v ON e.venue_id = v.venue_id WHERE v.manager_id = u.user_id AND e.status = 'completed')
            ELSE 0
        END as completed_events
    FROM users u
    WHERE u.role IN ('organizer', 'manager')
    HAVING total_revenue > 0
    ORDER BY total_revenue DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet"
        href="../../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
</head>

<body class="bg-gray-100 font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <!-- Top Bar -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
                    <p class="text-sm text-gray-600">Manage system users and permissions</p>
                </div>
                <div class="flex gap-3">
                    <!-- Export Dropdown -->
                    <div class="relative">
                        <button onclick="toggleExportDropdown()"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-md">
                            <i class="fas fa-download"></i>
                            Export
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="exportDropdown"
                            class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 z-10">
                            <button onclick="exportUsersPDF()"
                                class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors flex items-center border-b border-gray-100">
                                <i class="fas fa-file-pdf text-red-600 mr-3 text-lg"></i>
                                <div>
                                    <div class="font-semibold text-gray-900">Export as PDF</div>
                                    <div class="text-xs text-gray-500">With charts & statistics</div>
                                </div>
                            </button>
                            <button onclick="exportUsersCSV()"
                                class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors flex items-center">
                                <i class="fas fa-file-csv text-green-600 mr-3 text-lg"></i>
                                <div>
                                    <div class="font-semibold text-gray-900">Export as CSV</div>
                                    <div class="text-xs text-gray-500">Data only spreadsheet</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 pb-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?>
                            </p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Active</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['active']); ?>
                            </p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Inactive</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['inactive']); ?>
                            </p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-lg">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Organizers</p>
                            <p class="text-2xl font-bold text-purple-600">
                                <?php echo number_format($stats['organizers']); ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-calendar-alt text-purple-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Managers</p>
                            <p class="text-2xl font-bold text-orange-600">
                                <?php echo number_format($stats['managers']); ?></p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-lg">
                            <i class="fas fa-building text-orange-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Admins</p>
                            <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($stats['admins']); ?>
                            </p>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-lg">
                            <i class="fas fa-shield-alt text-indigo-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Revenue Generators -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-trophy text-yellow-500 mr-2"></i>Top Revenue Generators
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Users ranked by total completed event revenue</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Rank</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    User</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Role</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Events</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Total Revenue</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Avg per Event</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $rank = 1;
                            $has_data = false;
                            while ($user = $top_revenue_users->fetch_assoc()):
                                $has_data = true;
                                $avg_revenue = $user['completed_events'] > 0 ? $user['total_revenue'] / $user['completed_events'] : 0;
                                $rank_color = '';
                                $rank_icon = '';
                                if ($rank == 1) {
                                    $rank_color = 'text-yellow-500';
                                    $rank_icon = 'fas fa-medal';
                                } elseif ($rank == 2) {
                                    $rank_color = 'text-gray-400';
                                    $rank_icon = 'fas fa-medal';
                                } elseif ($rank == 3) {
                                    $rank_color = 'text-orange-600';
                                    $rank_icon = 'fas fa-medal';
                                } else {
                                    $rank_color = 'text-gray-600';
                                    $rank_icon = 'fas fa-hashtag';
                                }
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="<?php echo $rank_icon . ' ' . $rank_color; ?> text-xl mr-2"></i>
                                        <span
                                            class="text-lg font-bold <?php echo $rank_color; ?>"><?php echo $rank; ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold mr-3">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($user['full_name']); ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span
                                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $user['role'] === 'organizer' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800'; ?>">
                                        <i
                                            class="<?php echo $user['role'] === 'organizer' ? 'fas fa-calendar-alt' : 'fas fa-building'; ?> mr-1"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        <span
                                            class="text-sm font-semibold text-gray-900"><?php echo number_format($user['completed_events']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-lg font-bold text-green-600">
                                        ₱<?php echo number_format($user['total_revenue'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-700">
                                        ₱<?php echo number_format($avg_revenue, 2); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                                $rank++;
                            endwhile;
                            if (!$has_data):
                            ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-chart-line text-4xl mb-3 opacity-30"></i>
                                    <p class="text-sm">No revenue data available yet</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- User Distribution by Role -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie text-blue-600 mr-2"></i>User Distribution by Role
                    </h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="roleChart"></canvas>
                    </div>
                </div>

                <!-- User Status Distribution -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar text-green-600 mr-2"></i>User Status Distribution
                    </h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Revenue Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Top 5 Revenue Generators -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-trophy text-yellow-500 mr-2"></i>Top 5 Revenue Generators
                    </h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="topRevenueChart"></canvas>
                    </div>
                </div>

                <!-- Revenue by Role -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-coins text-purple-600 mr-2"></i>Revenue Distribution by Role
                    </h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="revenueByRoleChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-search mr-1"></i>Search Users
                            </label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by name or email..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <!-- Role Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag mr-1"></i>Role
                            </label>
                            <select name="role"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="">All Roles</option>
                                <option value="organizer" <?php echo $role_filter === 'organizer' ? 'selected' : ''; ?>>
                                    Organizer</option>
                                <option value="manager" <?php echo $role_filter === 'manager' ? 'selected' : ''; ?>>
                                    Manager</option>
                                <option value="administrator"
                                    <?php echo $role_filter === 'administrator' ? 'selected' : ''; ?>>Administrator
                                </option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-filter mr-1"></i>Status
                            </label>
                            <select name="status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                                    Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive</option>
                            </select>
                        </div>
                        <!-- Sort -->
                        <div class="flex-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sort mr-1"></i>Sort By
                            </label>
                            <select name="sort"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="latest" <?php echo $sort_filter === 'latest' ? 'selected' : ''; ?>>Latest
                                    First</option>
                                <option value="oldest" <?php echo $sort_filter === 'oldest' ? 'selected' : ''; ?>>Oldest
                                    First</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">

                        <!-- Buttons -->
                        <div class="flex gap-2 items-end">
                            <button type="submit"
                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                            <a href="manage-users.php"
                                class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                <i class="fas fa-redo mr-2"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions Bar -->
            <div id="bulkActionsBar"
                class="hidden bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-semibold text-indigo-900">
                        <span id="selectedCount">0</span> user(s) selected
                    </span>
                    <div class="flex gap-2">
                        <button onclick="bulkAction('activate')"
                            class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-check mr-2"></i>Activate
                        </button>
                        <button onclick="bulkAction('deactivate')"
                            class="px-4 py-2 bg-orange-600 text-white text-sm rounded-lg hover:bg-orange-700 transition-colors">
                            <i class="fas fa-ban mr-2"></i>Deactivate
                        </button>
                        <button onclick="bulkAction('delete')"
                            class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </div>
                </div>
                <button onclick="clearSelection()" class="text-indigo-600 hover:text-indigo-800 transition-colors">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"
                                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    User
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Role
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Status
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Joined
                                </th>
                                <th
                                    class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($users_result && $users_result->num_rows > 0):
                                while ($user = $users_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <input type="checkbox"
                                        class="user-checkbox w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                        value="<?php echo $user['user_id']; ?>" onchange="updateBulkActions()">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 font-bold">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        switch ($user['role']) {
                                            case 'organizer':
                                                echo 'bg-purple-100 text-purple-700';
                                                break;
                                            case 'manager':
                                                echo 'bg-orange-100 text-orange-700';
                                                break;
                                            case 'administrator':
                                                echo 'bg-red-100 text-red-700';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-700';
                                        }
                                        ?>">
                                        <i class="fas fa-<?php
                                                                    switch ($user['role']) {
                                                                        case 'organizer':
                                                                            echo 'calendar-alt';
                                                                            break;
                                                                        case 'manager':
                                                                            echo 'building';
                                                                            break;
                                                                        case 'administrator':
                                                                            echo 'shield-alt';
                                                                            break;
                                                                    }
                                                                    ?> mr-1"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-3 py-1 text-xs font-semibold rounded-full
                                        <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <i
                                            class="fas fa-<?php echo $user['status'] === 'active' ? 'check-circle' : 'times-circle'; ?> mr-1"></i>
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="viewUserDetails(<?php echo $user['user_id']; ?>)"
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($user['status'] === 'inactive'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit"
                                                class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                title="Activate" onclick="return confirm('Activate this user?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button
                                            onclick="openDeactivateModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')"
                                            class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition-colors"
                                            title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button
                                            onclick="openDeleteModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')"
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile;
                            else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                                        <p class="text-gray-500 text-lg font-semibold">No users found</p>
                                        <p class="text-gray-400 text-sm">Try adjusting your filters</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            Showing <?php echo $offset + 1; ?> to
                            <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?>
                            users
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="px-4 py-2 <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg transition-colors">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">User Details</h3>
                <button onclick="closeUserDetails()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="userDetailsContent" class="p-6">
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-4xl text-indigo-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivate User Modal -->
    <div id="deactivateModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Deactivate User
                </h3>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-orange-100 rounded-full">
                    <i class="fas fa-ban text-3xl text-orange-600"></i>
                </div>
                <p class="text-center text-gray-700 mb-2">Are you sure you want to deactivate</p>
                <p class="text-center font-bold text-gray-900 text-lg mb-4" id="deactivateUserName"></p>
                <p class="text-center text-sm text-gray-600 mb-6">
                    This user will no longer be able to access their account until reactivated.
                </p>
                <form method="POST" id="deactivateForm">
                    <input type="hidden" name="user_id" id="deactivateUserId">
                    <input type="hidden" name="action" value="deactivate">
                    <div class="flex gap-3">
                        <button type="button" onclick="closeDeactivateModal()"
                            class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-semibold">
                            <i class="fas fa-ban mr-2"></i>Deactivate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-trash-alt mr-2"></i>Delete User
                </h3>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                </div>
                <p class="text-center text-gray-700 mb-2">Are you sure you want to permanently delete</p>
                <p class="text-center font-bold text-gray-900 text-lg mb-4" id="deleteUserName"></p>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-red-800 font-semibold mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Warning: This action cannot be undone!
                    </p>
                    <ul class="text-sm text-red-700 space-y-1 ml-6 list-disc">
                        <li>All user data will be permanently deleted</li>
                        <li>Associated events and bookings may be affected</li>
                        <li>This action is irreversible</li>
                    </ul>
                </div>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="action" value="delete">
                    <div class="flex gap-3">
                        <button type="button" onclick="closeDeleteModal()"
                            class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-semibold">
                            <i class="fas fa-trash mr-2"></i>Delete Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Bulk Actions
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateBulkActions();
    }

    function updateBulkActions() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        const bulkBar = document.getElementById('bulkActionsBar');
        const selectedCount = document.getElementById('selectedCount');
        const selectAll = document.getElementById('selectAll');

        selectedCount.textContent = checkboxes.length;
        bulkBar.classList.toggle('hidden', checkboxes.length === 0);
        bulkBar.classList.toggle('flex', checkboxes.length > 0);

        const allCheckboxes = document.querySelectorAll('.user-checkbox');
        selectAll.checked = checkboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
    }

    function clearSelection() {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        updateBulkActions();
    }

    function bulkAction(action) {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one user');
            return;
        }

        const actionNames = {
            'activate': 'activate',
            'deactivate': 'deactivate',
            'delete': 'permanently delete'
        };

        if (!confirm(`Are you sure you want to ${actionNames[action]} ${checkboxes.length} user(s)?`)) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="bulk_${action}">`;

        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // User Details Modal
    function viewUserDetails(userId) {
        const modal = document.getElementById('userDetailsModal');
        const content = document.getElementById('userDetailsContent');

        modal.classList.remove('hidden');
        content.innerHTML =
            '<div class="flex items-center justify-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-indigo-600"></i></div>';

        fetch(`?ajax=get_user&user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                const roleIcons = {
                    'organizer': 'calendar-alt',
                    'manager': 'building',
                    'administrator': 'shield-alt'
                };

                const roleColors = {
                    'organizer': 'purple',
                    'manager': 'orange',
                    'administrator': 'red'
                };

                const statusColor = data.status === 'active' ? 'green' : 'red';

                content.innerHTML = `
                        <div class="space-y-6">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center justify-center w-20 h-20 rounded-full bg-indigo-100 text-indigo-600 text-3xl font-bold">
                                    ${data.first_name.charAt(0)}${data.last_name.charAt(0)}
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-2xl font-bold text-gray-900">${data.first_name} ${data.last_name}</h4>
                                    <p class="text-gray-600">${data.email}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-${roleColors[data.role]}-50 border border-${roleColors[data.role]}-200 rounded-lg p-4">
                                    <p class="text-sm text-${roleColors[data.role]}-600 font-semibold mb-1">Role</p>
                                    <p class="text-lg font-bold text-${roleColors[data.role]}-700">
                                        <i class="fas fa-${roleIcons[data.role]} mr-2"></i>${data.role.charAt(0).toUpperCase() + data.role.slice(1)}
                                    </p>
                                </div>
                                <div class="bg-${statusColor}-50 border border-${statusColor}-200 rounded-lg p-4">
                                    <p class="text-sm text-${statusColor}-600 font-semibold mb-1">Status</p>
                                    <p class="text-lg font-bold text-${statusColor}-700">
                                        <i class="fas fa-${data.status === 'active' ? 'check-circle' : 'times-circle'} mr-2"></i>${data.status.charAt(0).toUpperCase() + data.status.slice(1)}
                                    </p>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-4">
                                <h5 class="font-bold text-gray-900 mb-3">Account Information</h5>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">User ID:</span>
                                        <span class="font-semibold text-gray-900">#${data.user_id}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Joined:</span>
                                        <span class="font-semibold text-gray-900">${new Date(data.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</span>
                                    </div>
                                    ${data.role === 'organizer' ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Events:</span>
                                        <span class="font-semibold text-gray-900">${data.events_count || 0}</span>
                                    </div>
                                    ` : ''}
                                    ${data.role === 'manager' ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Venues:</span>
                                        <span class="font-semibold text-gray-900">${data.venues_count || 0}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    `;
            })
            .catch(error => {
                content.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-circle text-6xl text-red-500 mb-4"></i>
                            <p class="text-red-600 font-semibold">Failed to load user details</p>
                        </div>
                    `;
            });
    }

    function closeUserDetails() {
        document.getElementById('userDetailsModal').classList.add('hidden');
    }

    // Deactivate Modal
    function openDeactivateModal(userId, userName) {
        document.getElementById('deactivateUserId').value = userId;
        document.getElementById('deactivateUserName').textContent = userName;
        document.getElementById('deactivateModal').classList.remove('hidden');
    }

    function closeDeactivateModal() {
        document.getElementById('deactivateModal').classList.add('hidden');
    }

    // Delete Modal
    function openDeleteModal(userId, userName) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Export Dropdown Toggle
    function toggleExportDropdown() {
        const dropdown = document.getElementById('exportDropdown');
        dropdown.classList.toggle('hidden');
    }

    // Close dropdown when clicking outside
    window.addEventListener('click', function(event) {
        const dropdown = document.getElementById('exportDropdown');
        if (!event.target.closest('button[onclick="toggleExportDropdown()"]') &&
            !event.target.closest('#exportDropdown')) {
            dropdown.classList.add('hidden');
        }
    });

    // Export Users CSV
    function exportUsersCSV() {
        const role = '<?php echo $role_filter; ?>';
        const status = '<?php echo $status_filter; ?>';
        window.location.href = `?ajax=export&format=csv&role=${role}&status=${status}`;
        document.getElementById('exportDropdown').classList.add('hidden');
    }

    // Export Users PDF
    async function exportUsersPDF() {
        document.getElementById('exportDropdown').classList.add('hidden');

        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // Header with gradient effect
        doc.setFillColor(99, 102, 241); // Indigo
        doc.rect(0, 0, pageWidth, 40, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFontSize(26);
        doc.setFont(undefined, 'bold');
        doc.text('User Management Report', pageWidth / 2, 18, {
            align: 'center'
        });

        doc.setFontSize(11);
        doc.setFont(undefined, 'normal');
        doc.text('Gatherly Event Management System', pageWidth / 2, 28, {
            align: 'center'
        });
        doc.text('Generated: <?php echo date("F d, Y H:i:s"); ?>', pageWidth / 2, 35, {
            align: 'center'
        });

        doc.setTextColor(0, 0, 0);
        let yPos = 50;

        // Summary Statistics
        doc.setFontSize(14);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(99, 102, 241);
        doc.text('Summary Statistics', 14, yPos);
        yPos += 8;

        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(0, 0, 0);

        const stats = [{
                label: 'Total Users',
                value: '<?php echo number_format($stats["total"]); ?>',
                icon: '👥'
            },
            {
                label: 'Active Users',
                value: '<?php echo number_format($stats["active"]); ?>',
                icon: '✓'
            },
            {
                label: 'Inactive Users',
                value: '<?php echo number_format($stats["inactive"]); ?>',
                icon: '✗'
            },
            {
                label: 'Organizers',
                value: '<?php echo number_format($stats["organizers"]); ?>',
                icon: '📅'
            },
            {
                label: 'Managers',
                value: '<?php echo number_format($stats["managers"]); ?>',
                icon: '🏢'
            },
            {
                label: 'Administrators',
                value: '<?php echo number_format($stats["admins"]); ?>',
                icon: '🛡️'
            }
        ];

        // Create summary boxes
        const boxWidth = 60;
        const boxHeight = 18;
        let xPos = 14;

        stats.forEach((stat, index) => {
            if (index === 3) {
                xPos = 14;
                yPos += 23;
            }

            // Box background
            doc.setFillColor(249, 250, 251);
            doc.roundedRect(xPos, yPos, boxWidth, boxHeight, 2, 2, 'F');
            doc.setDrawColor(229, 231, 235);
            doc.roundedRect(xPos, yPos, boxWidth, boxHeight, 2, 2, 'S');

            // Icon and Label
            doc.setFontSize(8);
            doc.setTextColor(107, 114, 128);
            doc.text(stat.icon + ' ' + stat.label, xPos + boxWidth / 2, yPos + 7, {
                align: 'center'
            });

            // Value
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(17, 24, 39);
            doc.text(stat.value, xPos + boxWidth / 2, yPos + 14, {
                align: 'center'
            });
            doc.setFont(undefined, 'normal');

            xPos += boxWidth + 5;
        });

        yPos += 28;

        // Capture and add charts
        const charts = [{
                id: 'roleChart',
                title: 'User Distribution by Role',
                height: 50
            },
            {
                id: 'statusChart',
                title: 'User Status Distribution',
                height: 50
            }
        ];

        for (let i = 0; i < charts.length; i++) {
            const chart = charts[i];
            const canvas = document.getElementById(chart.id);

            if (canvas) {
                // Check if we need a new page
                if (yPos > pageHeight - 80) {
                    doc.addPage();
                    yPos = 20;
                }

                // Chart title
                doc.setFontSize(12);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(99, 102, 241);
                doc.text(chart.title, 14, yPos);
                yPos += 8;

                // Add chart image
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = pageWidth - 28;
                const imgHeight = chart.height;
                doc.addImage(imgData, 'PNG', 14, yPos, imgWidth, imgHeight);
                yPos += imgHeight + 10;

                doc.setFont(undefined, 'normal');
                doc.setTextColor(0, 0, 0);
            }
        }

        // Add revenue chart if exists
        const topRevenueCanvas = document.getElementById('topRevenueChart');
        if (topRevenueCanvas && yPos < pageHeight - 80) {
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(99, 102, 241);
            doc.text('Top Revenue Generators', 14, yPos);
            yPos += 8;

            const imgData = topRevenueCanvas.toDataURL('image/png');
            const imgWidth = pageWidth - 28;
            doc.addImage(imgData, 'PNG', 14, yPos, imgWidth, 50);
            yPos += 60;
        }

        // Add users table
        if (yPos > pageHeight - 100) {
            doc.addPage();
            yPos = 20;
        }

        doc.setFontSize(14);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(99, 102, 241);
        doc.text('User Details', 14, yPos);
        yPos += 8;
        doc.setFont(undefined, 'normal');
        doc.setTextColor(0, 0, 0);

        // Get table data
        const tableData = [];
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                const nameCell = cells[0].textContent.trim();
                const emailCell = cells[1].textContent.trim();
                const roleCell = cells[2].textContent.trim();
                const statusCell = cells[3].textContent.trim();

                if (!nameCell.includes('No users found')) {
                    tableData.push([nameCell, emailCell, roleCell, statusCell]);
                }
            }
        });

        if (tableData.length > 0) {
            doc.autoTable({
                startY: yPos,
                head: [
                    ['Name', 'Email', 'Role', 'Status']
                ],
                body: tableData,
                theme: 'striped',
                headStyles: {
                    fillColor: [99, 102, 241],
                    textColor: [255, 255, 255],
                    fontSize: 10,
                    fontStyle: 'bold',
                    halign: 'left'
                },
                bodyStyles: {
                    fontSize: 9,
                    textColor: [55, 65, 81]
                },
                alternateRowStyles: {
                    fillColor: [249, 250, 251]
                },
                margin: {
                    left: 14,
                    right: 14
                },
                didDrawPage: function(data) {
                    // Footer
                    doc.setFontSize(8);
                    doc.setTextColor(156, 163, 175);
                    doc.text(
                        'Page ' + doc.internal.getCurrentPageInfo().pageNumber,
                        pageWidth / 2,
                        pageHeight - 10, {
                            align: 'center'
                        }
                    );
                }
            });
        }

        doc.save('gatherly_users_report_<?php echo date("Y-m-d"); ?>.pdf');
    }

    // Export Users (legacy function - keep for compatibility)
    function exportUsers(format) {
        const role = '<?php echo $role_filter; ?>';
        const status = '<?php echo $status_filter; ?>';
        window.location.href = `?ajax=export&format=${format}&role=${role}&status=${status}`;
    }

    // Close modals on outside click
    document.getElementById('userDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUserDetails();
        }
    });

    document.getElementById('deactivateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeactivateModal();
        }
    });

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // Chart.js Configurations
    const chartColors = {
        blue: '#3b82f6',
        purple: '#a855f7',
        green: '#22c55e',
        orange: '#f97316',
        red: '#ef4444',
        yellow: '#eab308',
        indigo: '#6366f1',
        teal: '#14b8a6',
        pink: '#ec4899',
        gray: '#6b7280'
    };

    // User Distribution by Role Chart
    new Chart(document.getElementById('roleChart'), {
        type: 'doughnut',
        data: {
            labels: ['Organizers', 'Managers', 'Administrators'],
            datasets: [{
                data: [
                    <?php echo $stats['organizers']; ?>,
                    <?php echo $stats['managers']; ?>,
                    <?php echo $stats['admins']; ?>
                ],
                backgroundColor: [chartColors.purple, chartColors.orange, chartColors.indigo],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // User Status Distribution Chart
    new Chart(document.getElementById('statusChart'), {
        type: 'bar',
        data: {
            labels: ['Active Users', 'Inactive Users'],
            datasets: [{
                label: 'Users',
                data: [
                    <?php echo $stats['active']; ?>,
                    <?php echo $stats['inactive']; ?>
                ],
                backgroundColor: [chartColors.green, chartColors.red],
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.parsed.y} users`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Top 5 Revenue Generators Chart
    <?php
        $top_revenue_users->data_seek(0);
        $top_names = [];
        $top_revenues = [];
        $count = 0;
        while ($user = $top_revenue_users->fetch_assoc()) {
            if ($count >= 5) break;
            $top_names[] = $user['full_name'];
            $top_revenues[] = $user['total_revenue'];
            $count++;
        }
        ?>
    new Chart(document.getElementById('topRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($top_names); ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo json_encode($top_revenues); ?>,
                backgroundColor: [
                    chartColors.yellow,
                    chartColors.gray,
                    chartColors.orange,
                    chartColors.blue,
                    chartColors.purple
                ],
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `₱${context.parsed.x.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString('en-US');
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Revenue by Role Chart
    <?php
        // Calculate revenue by role
        $organizer_revenue = $conn->query("SELECT COALESCE(SUM(total_cost), 0) as revenue FROM events WHERE status = 'completed' AND organizer_id IN (SELECT user_id FROM users WHERE role = 'organizer')")->fetch_assoc()['revenue'];
        $manager_revenue = $conn->query("SELECT COALESCE(SUM(e.total_cost), 0) as revenue FROM events e JOIN venues v ON e.venue_id = v.venue_id WHERE e.status = 'completed' AND v.manager_id IN (SELECT user_id FROM users WHERE role = 'manager')")->fetch_assoc()['revenue'];
        ?>
    new Chart(document.getElementById('revenueByRoleChart'), {
        type: 'pie',
        data: {
            labels: ['Organizers', 'Managers'],
            datasets: [{
                data: [
                    <?php echo $organizer_revenue; ?>,
                    <?php echo $manager_revenue; ?>
                ],
                backgroundColor: [chartColors.purple, chartColors.orange],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ₱${context.parsed.toLocaleString('en-US', {minimumFractionDigits: 2})} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    </script>
</body>

</html>