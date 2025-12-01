<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'create':
            try {
                $sql = "INSERT INTO suppliers (supplier_name, service_category, email, phone, location, availability_status) 
                        VALUES (:name, :category, :email, :phone, :location, :status)";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $_POST['supplier_name'],
                    ':category' => $_POST['service_category'] ?? null,
                    ':email' => $_POST['email'] ?? null,
                    ':phone' => $_POST['phone'] ?? null,
                    ':location' => $_POST['location'] ?? null,
                    ':status' => $_POST['availability_status'] ?? 'available'
                ]);

                echo json_encode(['success' => true, 'message' => 'Supplier created successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'update':
            try {
                $sql = "UPDATE suppliers SET supplier_name = :name, service_category = :category, 
                        email = :email, phone = :phone, location = :location,
                        availability_status = :status
                        WHERE supplier_id = :id";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':id' => $_POST['supplier_id'],
                    ':name' => $_POST['supplier_name'],
                    ':category' => $_POST['service_category'] ?? null,
                    ':email' => $_POST['email'] ?? null,
                    ':phone' => $_POST['phone'] ?? null,
                    ':location' => $_POST['location'] ?? null,
                    ':status' => $_POST['availability_status'] ?? 'available'
                ]);

                echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'delete':
            try {
                $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = :id");
                $stmt->execute([':id' => $_POST['supplier_id']]);
                echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'toggle_status':
            try {
                $stmt = $conn->prepare("UPDATE suppliers SET availability_status = :status WHERE supplier_id = :id");
                $stmt->execute([
                    ':id' => $_POST['supplier_id'],
                    ':status' => $_POST['availability_status']
                ]);
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_supplier':
            try {
                $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = :id");
                $stmt->execute([':id' => $_POST['supplier_id']]);
                $supplier = $stmt->fetch();
                echo json_encode(['success' => true, 'supplier' => $supplier]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($category) {
    $where[] = "service_category = :category";
    $params[':category'] = $category;
}

if ($status) {
    $where[] = "availability_status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $where[] = "(supplier_name LIKE :search OR email LIKE :search2)";
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM suppliers $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalSuppliers = $countStmt->fetch()['total'];
$totalPages = ceil($totalSuppliers / $perPage);

// Get suppliers
$sql = "SELECT * FROM suppliers $whereClause ORDER BY supplier_id DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$suppliers = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
    'available' => $conn->query("SELECT COUNT(*) FROM suppliers WHERE availability_status = 'available'")->fetchColumn(),
    'booked' => $conn->query("SELECT COUNT(*) FROM suppliers WHERE availability_status = 'booked'")->fetchColumn()
];

$categoryStats = $conn->query("SELECT service_category, COUNT(*) as count FROM suppliers GROUP BY service_category")->fetchAll(PDO::FETCH_KEY_PAIR);

$first_name = $_SESSION['first_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers | Gatherly</title>
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
</head>

<body class="bg-gray-100 font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-truck mr-2 text-orange-600"></i>Manage Suppliers
                    </h1>
                    <p class="text-sm text-gray-600">Manage event suppliers and service providers</p>
                </div>
                <button onclick="openCreateModal()"
                    class="flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-plus"></i>
                    <span>Add Supplier</span>
                </button>
            </div>
        </div>

        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Suppliers</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?>
                            </p>
                        </div>
                        <div class="bg-blue-100 p-4 rounded-lg">
                            <i class="fas fa-truck text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Available</p>
                            <p class="text-3xl font-bold text-green-600">
                                <?php echo number_format($stats['available']); ?></p>
                        </div>
                        <div class="bg-green-100 p-4 rounded-lg">
                            <i class="fas fa-check-circle text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Booked</p>
                            <p class="text-3xl font-bold text-orange-600"><?php echo number_format($stats['booked']); ?>
                            </p>
                        </div>
                        <div class="bg-orange-100 p-4 rounded-lg">
                            <i class="fas fa-calendar-check text-2xl text-orange-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by name or email..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                        <select name="category"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="">All Categories</option>
                            <option value="Lights and Sounds"
                                <?php echo $category === 'Lights and Sounds' ? 'selected' : ''; ?>>Lights and Sounds
                            </option>
                            <option value="Photography" <?php echo $category === 'Photography' ? 'selected' : ''; ?>>
                                Photography</option>
                            <option value="Styling and Flowers"
                                <?php echo $category === 'Styling and Flowers' ? 'selected' : ''; ?>>Styling and Flowers
                            </option>
                            <option value="Catering" <?php echo $category === 'Catering' ? 'selected' : ''; ?>>Catering
                            </option>
                            <option value="Transportation"
                                <?php echo $category === 'Transportation' ? 'selected' : ''; ?>>Transportation</option>
                            <option value="Entertainment"
                                <?php echo $category === 'Entertainment' ? 'selected' : ''; ?>>Entertainment</option>
                            <option value="Security" <?php echo $category === 'Security' ? 'selected' : ''; ?>>Security
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select name="status"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available
                            </option>
                            <option value="booked" <?php echo $status === 'booked' ? 'selected' : ''; ?>>Booked</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="manage-suppliers.php"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Suppliers Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Supplier</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Category</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Contact</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Location</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3"></i>
                                    <p class="text-lg font-semibold">No suppliers found</p>
                                    <p class="text-sm">Try adjusting your filters or add a new supplier</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div
                                            class="flex-shrink-0 w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-truck text-orange-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                                                <?php
                                                $categoryColors = [
                                                    'Lights and Sounds' => 'bg-purple-100 text-purple-700',
                                                    'Photography' => 'bg-pink-100 text-pink-700',
                                                    'Styling and Flowers' => 'bg-blue-100 text-blue-700',
                                                    'Catering' => 'bg-green-100 text-green-700',
                                                    'Transportation' => 'bg-indigo-100 text-indigo-700',
                                                    'Entertainment' => 'bg-red-100 text-red-700',
                                                    'Security' => 'bg-gray-100 text-gray-700'
                                                ];
                                                echo $categoryColors[$supplier['service_category']] ?? 'bg-gray-100 text-gray-700';
                                                ?>">
                                        <?php echo htmlspecialchars($supplier['service_category'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-gray-500">
                                        <?php if ($supplier['email']): ?>
                                        <i
                                            class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($supplier['email']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php if ($supplier['phone']): ?>
                                        <i
                                            class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($supplier['phone']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                        <?php echo htmlspecialchars($supplier['location'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-3 py-1 text-xs font-semibold rounded-full
                                                <?php echo $supplier['availability_status'] === 'available' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'; ?>">
                                        <?php echo ucfirst($supplier['availability_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="viewSupplier(<?php echo $supplier['supplier_id']; ?>)"
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editSupplier(<?php echo $supplier['supplier_id']; ?>)"
                                            class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button
                                            onclick="toggleStatus(<?php echo $supplier['supplier_id']; ?>, '<?php echo $supplier['availability_status'] === 'available' ? 'booked' : 'available'; ?>')"
                                            class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition-colors"
                                            title="Toggle Status">
                                            <i
                                                class="fas fa-<?php echo $supplier['availability_status'] === 'available' ? 'calendar-check' : 'check'; ?>-circle"></i>
                                        </button>
                                        <button onclick="deleteSupplier(<?php echo $supplier['supplier_id']; ?>)"
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalSuppliers); ?> of
                        <?php echo $totalSuppliers; ?> suppliers
                    </div>
                    <div class="flex gap-2">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $status ? '&status=' . urlencode($status) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                            class="px-4 py-2 rounded-lg transition-colors <?php echo $i === $page ? 'bg-orange-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="supplierModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Add Supplier</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="supplierForm" class="p-6 space-y-6">
                    <input type="hidden" id="supplier_id" name="supplier_id">
                    <input type="hidden" id="formAction" name="action" value="create">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Supplier Name <span class="text-red-600">*</span>
                            </label>
                            <input type="text" id="supplier_name" name="supplier_name" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Category <span class="text-red-600">*</span>
                            </label>
                            <select id="service_category" name="service_category" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Select Category</option>
                                <option value="Lights and Sounds">Lights and Sounds</option>
                                <option value="Photography">Photography</option>
                                <option value="Styling and Flowers">Styling and Flowers</option>
                                <option value="Catering">Catering</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Security">Security</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select id="availability_status" name="availability_status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="available">Available</option>
                                <option value="booked">Booked</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                            <input type="tel" id="phone" name="phone"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Location</label>
                            <input type="text" id="location" name="location"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button type="submit"
                            class="flex-1 px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-semibold">
                            <i class="fas fa-save mr-2"></i>Save Supplier
                        </button>
                        <button type="button" onclick="closeModal()"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full">
                <div
                    class="bg-gradient-to-r from-orange-600 to-red-600 text-white px-6 py-4 flex items-center justify-between rounded-t-xl">
                    <h3 class="text-xl font-bold">Supplier Details</h3>
                    <button onclick="closeViewModal()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div id="viewContent" class="p-6"></div>
            </div>
        </div>
    </div>

    <script>
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Add Supplier';
        document.getElementById('formAction').value = 'create';
        document.getElementById('supplierForm').reset();
        document.getElementById('supplier_id').value = '';
        document.getElementById('supplierModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('supplierModal').classList.add('hidden');
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    async function editSupplier(id) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_supplier');
            formData.append('supplier_id', id);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                const supplier = data.supplier;
                document.getElementById('modalTitle').textContent = 'Edit Supplier';
                document.getElementById('formAction').value = 'update';
                document.getElementById('supplier_id').value = supplier.supplier_id;
                document.getElementById('supplier_name').value = supplier.supplier_name;
                document.getElementById('service_category').value = supplier.service_category;
                document.getElementById('email').value = supplier.email || '';
                document.getElementById('phone').value = supplier.phone || '';
                document.getElementById('location').value = supplier.location || '';
                document.getElementById('availability_status').value = supplier.availability_status;
                document.getElementById('supplierModal').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to load supplier details');
        }
    }

    async function viewSupplier(id) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_supplier');
            formData.append('supplier_id', id);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                const s = data.supplier;
                const content = `
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm font-semibold text-gray-600 mb-1"><i class="fas fa-building mr-2"></i>Supplier Name</p>
                                    <p class="text-lg font-bold text-gray-900">${s.supplier_name}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm font-semibold text-gray-600 mb-1"><i class="fas fa-tag mr-2"></i>Category</p>
                                    <p class="text-lg text-gray-900">${s.service_category}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm font-semibold text-gray-600 mb-1"><i class="fas fa-envelope mr-2"></i>Email</p>
                                    <p class="text-lg text-gray-900">${s.email || 'N/A'}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm font-semibold text-gray-600 mb-1"><i class="fas fa-phone mr-2"></i>Phone</p>
                                    <p class="text-lg text-gray-900">${s.phone || 'N/A'}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm font-semibold text-gray-600 mb-1"><i class="fas fa-map-marker-alt mr-2"></i>Location</p>
                                    <p class="text-lg text-gray-900">${s.location || 'N/A'}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm font-semibold text-gray-600 mb-1"><i class="fas fa-info-circle mr-2"></i>Status</p>
                                    <p class="text-lg"><span class="px-3 py-1 text-sm font-semibold rounded-full ${s.availability_status === 'available' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'}">${s.availability_status.toUpperCase()}</span></p>
                                </div>
                            </div>
                        </div>
                    `;
                document.getElementById('viewContent').innerHTML = content;
                document.getElementById('viewModal').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to load supplier details');
        }
    }

    async function deleteSupplier(id) {
        if (!confirm('Are you sure you want to delete this supplier? This action cannot be undone.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('supplier_id', id);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to delete supplier');
        }
    }

    async function toggleStatus(id, newStatus) {
        try {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('supplier_id', id);
            formData.append('status', newStatus);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update status');
        }
    }

    document.getElementById('supplierForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save supplier');
        }
    });
    </script>
</body>

</html>