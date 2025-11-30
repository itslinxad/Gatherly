<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

require_once '../../../src/services/dbconnect.php';

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Organizer';
$success_message = '';
$error_message = '';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name_input = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Validate inputs
    if (empty($first_name_input) || empty($last_name) || empty($email)) {
        $error_message = "First name, last name, and email are required.";
    } else {
        // Check if email already exists for another user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Email already exists.";
        } else {
            // Update profile
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $first_name_input, $last_name, $email, $phone, $user_id);

            if ($stmt->execute()) {
                $_SESSION['first_name'] = $first_name_input;
                $success_message = "Profile updated successfully!";
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error_message = "Failed to update profile.";
            }
        }
        $stmt->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);

            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Failed to change password.";
            }
            $stmt->close();
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Gatherly</title>
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

<body class="<?php
                $nav_layout = $_SESSION['nav_layout'] ?? 'sidebar';
                echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-slate-50 via-white to-blue-50';
                ?> font-['Montserrat']">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    My Profile
                </h1>
                <p class="text-sm text-gray-600">Manage your account settings and preferences</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">
                        My Profile
                    </h1>
                    <p class="text-gray-600">Manage your personal information and account settings</p>
                </div>
            <?php endif; ?>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="p-4 mb-6 text-green-800 bg-green-100 border border-green-200 rounded-lg">
                    <i class="mr-2 fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="p-4 mb-6 text-red-800 bg-red-100 border border-red-200 rounded-lg">
                    <i class="mr-2 fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
                <!-- Profile Card -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <div class="text-center">
                        <div
                            class="flex items-center justify-center w-24 h-24 mx-auto mb-4 text-4xl text-white bg-indigo-600 rounded-full">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <h2 class="mb-1 text-2xl font-bold text-gray-800">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </h2>
                        <p class="mb-4 text-sm text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-indigo-800 bg-indigo-100 rounded-full">
                            <i class="mr-1 fas fa-calendar-check"></i>Event Organizer
                        </span>
                    </div>
                    <div class="pt-6 mt-6 border-t border-gray-200">
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Member Since</span>
                                <span class="font-semibold text-gray-800">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Status</span>
                                <span class="font-semibold text-green-600">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                            <?php if ($user['phone']): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Phone</span>
                                    <span class="font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Forms Section -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Personal Information Form -->
                    <div class="p-6 bg-white shadow-md rounded-xl">
                        <h2 class="mb-4 text-xl font-bold text-gray-800">
                            <i class="mr-2 text-indigo-600 fas fa-user-edit"></i>
                            Personal Information
                        </h2>
                        <form method="POST" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-gray-700">First Name *</label>
                                    <input type="text" name="first_name"
                                        value="<?php echo htmlspecialchars($user['first_name']); ?>" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-semibold text-gray-700">Last Name *</label>
                                    <input type="text" name="last_name"
                                        value="<?php echo htmlspecialchars($user['last_name']); ?>" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-semibold text-gray-700">Email Address *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-semibold text-gray-700">Phone Number</label>
                                <input type="tel" name="phone"
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="pt-4">
                                <button type="submit" name="update_profile"
                                    class="px-6 py-2 text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                    <i class="mr-2 fas fa-save"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="p-6 bg-white shadow-md rounded-xl">
                        <h2 class="mb-4 text-xl font-bold text-gray-800">
                            <i class="mr-2 text-indigo-600 fas fa-lock"></i>
                            Change Password
                        </h2>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block mb-2 text-sm font-semibold text-gray-700">Current Password *</label>
                                <input type="password" name="current_password" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-semibold text-gray-700">New Password *</label>
                                <input type="password" name="new_password" required minlength="8"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters long</p>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-semibold text-gray-700">Confirm New Password
                                    *</label>
                                <input type="password" name="confirm_password" required minlength="8"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="pt-4">
                                <button type="submit" name="change_password"
                                    class="px-6 py-2 text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                    <i class="mr-2 fas fa-key"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            </div>

            <?php if ($nav_layout === 'sidebar'): ?>
    </div> <!-- Close sidebar inner wrapper -->
<?php endif; ?>
</div> <!-- Close main content -->

<script>
    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('-translate-x-full');
        });
    }
</script>
</body>

</html>