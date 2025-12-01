<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $service_name = $_POST['service_name'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $supplier_id = $_POST['supplier_id'] ? intval($_POST['supplier_id']) : null;

        $stmt = $conn->prepare("INSERT INTO services (service_name, category, description, price, supplier_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdi", $service_name, $category, $description, $price, $supplier_id);
        $stmt->execute();
        $stmt->close();

        header("Location: manage-services.php?success=created");
        exit();
    }

    if ($action === 'edit') {
        $service_id = intval($_POST['service_id']);
        $service_name = $_POST['service_name'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $supplier_id = $_POST['supplier_id'] ? intval($_POST['supplier_id']) : null;

        $stmt = $conn->prepare("UPDATE services SET service_name = ?, category = ?, description = ?, price = ?, supplier_id = ? WHERE service_id = ?");
        $stmt->bind_param("sssdii", $service_name, $category, $description, $price, $supplier_id, $service_id);
        $stmt->execute();
        $stmt->close();

        header("Location: manage-services.php?success=updated");
        exit();
    }

    if ($action === 'delete') {
        $service_id = intval($_POST['service_id']);
        $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $stmt->close();

        header("Location: manage-services.php?success=deleted");
        exit();
    }
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($search) {
    $where_conditions[] = "(s.service_name LIKE ? OR s.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}
if ($category_filter) {
    $where_conditions[] = "s.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}
if ($supplier_filter) {
    $where_conditions[] = "s.supplier_id = ?";
    $params[] = intval($supplier_filter);
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Fetch services with supplier info
$query = "SELECT s.*, sp.supplier_name, sp.service_category as supplier_category 
          FROM services s 
          LEFT JOIN suppliers sp ON s.supplier_id = sp.supplier_id 
          WHERE $where_clause 
          ORDER BY s.service_id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$services_result = $stmt->get_result();

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
$stats['total_revenue'] = $conn->query("SELECT COALESCE(SUM(price), 0) as total FROM services")->fetch_assoc()['total'];
$stats['avg_price'] = $stats['total'] > 0 ? $stats['total_revenue'] / $stats['total'] : 0;
$stats['with_supplier'] = $conn->query("SELECT COUNT(*) as count FROM services WHERE supplier_id IS NOT NULL")->fetch_assoc()['count'];
$stats['categories'] = $conn->query("SELECT COUNT(DISTINCT category) as count FROM services")->fetch_assoc()['count'];

// Get all suppliers for dropdown
$suppliers = $conn->query("SELECT supplier_id, supplier_name, service_category FROM suppliers ORDER BY supplier_name");

// Get all categories
$categories = $conn->query("SELECT DISTINCT category FROM services WHERE category IS NOT NULL ORDER BY category");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services | Gatherly Admin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-50 font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-concierge-bell text-blue-600 mr-2"></i>
                        Manage Services
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Manage event services and supplier connections</p>
                </div>
                <a href="add-edit-service.php" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-md">
                    <i class="fas fa-plus mr-2"></i>Add New Service
                </a>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success Message -->
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <span class="text-green-700 font-semibold">
                            <?php
                            if ($_GET['success'] === 'created') echo 'Service created successfully!';
                            elseif ($_GET['success'] === 'updated') echo 'Service updated successfully!';
                            elseif ($_GET['success'] === 'deleted') echo 'Service deleted successfully!';
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Total Services</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['total']); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-concierge-bell text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Total Value</p>
                            <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($stats['total_revenue'], 2); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Avg Price</p>
                            <p class="text-2xl font-bold text-purple-600">₱<?php echo number_format($stats['avg_price'], 2); ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">With Supplier</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['with_supplier']); ?></p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-lg">
                            <i class="fas fa-truck text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Categories</p>
                            <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($stats['categories']); ?></p>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-lg">
                            <i class="fas fa-tags text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-search mr-1"></i>Search Services
                        </label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by name or description..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1"></i>Category
                        </label>
                        <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Categories</option>
                            <?php
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()):
                            ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                    <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-truck mr-1"></i>Supplier
                        </label>
                        <select name="supplier" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Suppliers</option>
                            <?php
                            $suppliers->data_seek(0);
                            while ($sup = $suppliers->fetch_assoc()):
                            ?>
                                <option value="<?php echo $sup['supplier_id']; ?>"
                                    <?php echo $supplier_filter == $sup['supplier_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sup['supplier_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <?php if ($search || $category_filter || $supplier_filter): ?>
                            <a href="manage-services.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Services Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($services_result->num_rows > 0): ?>
                                <?php while ($service = $services_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold mr-3">
                                                    <i class="fas fa-concierge-bell"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900">
                                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">ID: #<?php echo $service['service_id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($service['category']): ?>
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                                    <i class="fas fa-tag mr-1"></i>
                                                    <?php echo htmlspecialchars($service['category']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($service['supplier_name']): ?>
                                                <div class="text-sm">
                                                    <div class="font-semibold text-gray-900">
                                                        <?php echo htmlspecialchars($service['supplier_name']); ?>
                                                    </div>
                                                    <?php if ($service['supplier_category']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <?php echo htmlspecialchars($service['supplier_category']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm italic">No supplier assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-lg font-bold text-green-600">
                                                ₱<?php echo number_format($service['price'], 2); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-600 max-w-xs truncate">
                                                <?php echo htmlspecialchars($service['description'] ?? 'No description'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="add-edit-service.php?id=<?php echo $service['service_id']; ?>"
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button onclick="deleteService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['service_name']); ?>')"
                                                class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-inbox text-6xl mb-4 opacity-30"></i>
                                        <p class="text-lg font-semibold">No services found</p>
                                        <p class="text-sm">Try adjusting your filters or add a new service</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-8 border w-96 shadow-lg rounded-xl bg-white">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Service</h3>
                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete "<span id="deleteServiceName" class="font-semibold"></span>"?
                    This action cannot be undone.
                </p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="service_id" id="deleteServiceId">
                    <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function deleteService(serviceId, serviceName) {
            document.getElementById('deleteServiceId').value = serviceId;
            document.getElementById('deleteServiceName').textContent = serviceName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal on outside click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Auto-hide success message
        setTimeout(() => {
            const successMsg = document.querySelector('[class*="bg-green-50"]');
            if (successMsg) {
                successMsg.style.transition = 'opacity 0.5s';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }
        }, 3000);
    </script>
</body>

</html>
<?php $conn->close(); ?>