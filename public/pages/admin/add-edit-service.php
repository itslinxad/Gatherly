<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$first_name = $_SESSION['first_name'] ?? 'Admin';
$is_edit = isset($_GET['id']);
$service_id = $is_edit ? intval($_GET['id']) : null;

// Fetch service data if editing
$service = null;
if ($is_edit) {
    $stmt = $conn->prepare("SELECT s.*, sp.supplier_name 
                           FROM services s 
                           LEFT JOIN suppliers sp ON s.supplier_id = sp.supplier_id 
                           WHERE s.service_id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    $stmt->close();

    if (!$service) {
        header("Location: manage-services.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name = trim($_POST['service_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;

    if ($is_edit) {
        $stmt = $conn->prepare("UPDATE services SET service_name = ?, category = ?, description = ?, price = ?, supplier_id = ? WHERE service_id = ?");
        $stmt->bind_param("sssdii", $service_name, $category, $description, $price, $supplier_id, $service_id);
        $stmt->execute();
        $stmt->close();

        header("Location: manage-services.php?success=updated");
    } else {
        $stmt = $conn->prepare("INSERT INTO services (service_name, category, description, price, supplier_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdi", $service_name, $category, $description, $price, $supplier_id);
        $stmt->execute();
        $stmt->close();

        header("Location: manage-services.php?success=created");
    }
    exit();
}

// Get all categories for datalist
$categories = $conn->query("SELECT DISTINCT category FROM services WHERE category IS NOT NULL ORDER BY category");

// Get all suppliers for search
$suppliers = $conn->query("SELECT supplier_id, supplier_name, service_category, phone, email FROM suppliers ORDER BY supplier_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Service | Gatherly Admin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .supplier-option {
            cursor: pointer;
            transition: all 0.2s;
        }

        .supplier-option:hover {
            background-color: #f3f4f6;
        }

        .supplier-option.selected {
            background-color: #dbeafe;
            border-color: #3b82f6;
        }
    </style>
</head>

<body class="bg-gray-50 font-['Montserrat']">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="manage-services.php" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-arrow-left text-2xl"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <i class="fas fa-concierge-bell text-blue-600 mr-2"></i>
                            <?php echo $is_edit ? 'Edit Service' : 'Add New Service'; ?>
                        </h1>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo $is_edit ? 'Update service information and supplier connection' : 'Create a new service and connect it to a supplier'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <div class="max-w-5xl mx-auto">
                <form method="POST" class="space-y-6">
                    <!-- Service Information Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            Service Information
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Service Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="service_name" required
                                    value="<?php echo htmlspecialchars($service['service_name'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="e.g., Premium Photography Package">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Category <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="category" required list="categoryList"
                                    value="<?php echo htmlspecialchars($service['category'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="e.g., Photography, Catering, Decoration">
                                <datalist id="categoryList">
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                        <?php endwhile; ?>
                                </datalist>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Price (₱) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3.5 text-gray-500 font-semibold">₱</span>
                                    <input type="number" name="price" required step="0.01" min="0"
                                        value="<?php echo $service['price'] ?? ''; ?>"
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Description
                            </label>
                            <textarea name="description" rows="4"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Provide a detailed description of the service..."><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Supplier Selection Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                            <i class="fas fa-truck text-orange-600 mr-2"></i>
                            Supplier Connection
                        </h2>
                        <p class="text-sm text-gray-600 mb-6">Search and select a supplier for this service (optional)</p>

                        <!-- Search Input -->
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Search Suppliers
                            </label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                                <input type="text" id="supplierSearch"
                                    class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Search by supplier name, category, or contact info...">
                            </div>
                        </div>

                        <!-- Hidden input for selected supplier -->
                        <input type="hidden" name="supplier_id" id="selectedSupplierId" value="<?php echo $service['supplier_id'] ?? ''; ?>">

                        <!-- Selected Supplier Display -->
                        <div id="selectedSupplierDisplay" class="mb-4 <?php echo empty($service['supplier_id']) ? 'hidden' : ''; ?>">
                            <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-lg">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div id="selectedSupplierInfo">
                                            <?php if ($service && $service['supplier_name']): ?>
                                                <div class="font-bold text-gray-900"><?php echo htmlspecialchars($service['supplier_name']); ?></div>
                                                <div class="text-sm text-gray-600">Selected Supplier</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button type="button" onclick="clearSupplier()" class="text-red-600 hover:text-red-800 font-semibold">
                                        <i class="fas fa-times mr-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Suppliers List -->
                        <div id="suppliersList" class="space-y-2 max-h-96 overflow-y-auto">
                            <?php
                            $suppliers->data_seek(0);
                            while ($supplier = $suppliers->fetch_assoc()):
                            ?>
                                <div class="supplier-option border-2 border-gray-200 rounded-lg p-4 <?php echo ($service['supplier_id'] ?? '') == $supplier['supplier_id'] ? 'selected' : ''; ?>"
                                    data-id="<?php echo $supplier['supplier_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                                    data-category="<?php echo htmlspecialchars($supplier['service_category'] ?? ''); ?>"
                                    data-phone="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>"
                                    onclick="selectSupplier(this)">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white font-bold">
                                                <?php echo strtoupper(substr($supplier['supplier_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($supplier['supplier_name']); ?></div>
                                                <div class="text-sm text-gray-600">
                                                    <?php if ($supplier['service_category']): ?>
                                                        <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($supplier['service_category']); ?>
                                                    <?php endif; ?>
                                                    <?php if ($supplier['phone']): ?>
                                                        <i class="fas fa-phone ml-3 mr-1"></i><?php echo htmlspecialchars($supplier['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($supplier['email']): ?>
                                                    <div class="text-xs text-gray-500">
                                                        <i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($supplier['email']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-check-circle text-2xl text-blue-600 hidden check-icon"></i>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <!-- No Supplier Option -->
                        <div class="mt-4">
                            <button type="button" onclick="clearSupplier()"
                                class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-gray-400 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-times-circle mr-2"></i>No Supplier (Independent Service)
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-4 pt-6">
                        <a href="manage-services.php"
                            class="px-8 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit"
                            class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold shadow-md">
                            <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Update Service' : 'Create Service'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Supplier search functionality
        const searchInput = document.getElementById('supplierSearch');
        const supplierOptions = document.querySelectorAll('.supplier-option');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            supplierOptions.forEach(option => {
                const name = option.dataset.name.toLowerCase();
                const category = option.dataset.category.toLowerCase();
                const phone = option.dataset.phone.toLowerCase();
                const email = option.dataset.email.toLowerCase();

                if (name.includes(searchTerm) ||
                    category.includes(searchTerm) ||
                    phone.includes(searchTerm) ||
                    email.includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        // Select supplier
        function selectSupplier(element) {
            // Remove selected class from all options
            supplierOptions.forEach(opt => {
                opt.classList.remove('selected');
                opt.querySelector('.check-icon').classList.add('hidden');
            });

            // Add selected class to clicked option
            element.classList.add('selected');
            element.querySelector('.check-icon').classList.remove('hidden');

            // Update hidden input
            document.getElementById('selectedSupplierId').value = element.dataset.id;

            // Update selected supplier display
            const displayDiv = document.getElementById('selectedSupplierDisplay');
            const infoDiv = document.getElementById('selectedSupplierInfo');

            infoDiv.innerHTML = `
                <div class="font-bold text-gray-900">${element.dataset.name}</div>
                <div class="text-sm text-gray-600">${element.dataset.category || 'No category'}</div>
            `;

            displayDiv.classList.remove('hidden');

            // Scroll to top to show selection
            displayDiv.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        // Clear supplier selection
        function clearSupplier() {
            document.getElementById('selectedSupplierId').value = '';
            document.getElementById('selectedSupplierDisplay').classList.add('hidden');

            supplierOptions.forEach(opt => {
                opt.classList.remove('selected');
                opt.querySelector('.check-icon').classList.add('hidden');
            });
        }

        // Initialize - show check icon for pre-selected supplier
        window.addEventListener('DOMContentLoaded', function() {
            const selectedId = document.getElementById('selectedSupplierId').value;
            if (selectedId) {
                const selectedOption = document.querySelector(`.supplier-option[data-id="${selectedId}"]`);
                if (selectedOption) {
                    selectedOption.querySelector('.check-icon').classList.remove('hidden');
                }
            }
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>