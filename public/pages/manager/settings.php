<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

$first_name = $_SESSION['first_name'] ?? 'Manager';
$success_message = '';
$error_message = '';

// Get current settings from session or set defaults
$notifications_enabled = $_SESSION['notifications_enabled'] ?? true;
$email_notifications = $_SESSION['email_notifications'] ?? true;
$items_per_page = $_SESSION['items_per_page'] ?? 10;
$timezone = $_SESSION['timezone'] ?? 'Asia/Manila';
$nav_layout = $_SESSION['nav_layout'] ?? 'sidebar';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $_SESSION['notifications_enabled'] = isset($_POST['notifications_enabled']);
    $_SESSION['email_notifications'] = isset($_POST['email_notifications']);
    $_SESSION['items_per_page'] = (int)($_POST['items_per_page'] ?? 10);
    $_SESSION['timezone'] = $_POST['timezone'] ?? 'Asia/Manila';
    $_SESSION['nav_layout'] = $_POST['nav_layout'] ?? 'sidebar';

    $notifications_enabled = $_SESSION['notifications_enabled'];
    $email_notifications = $_SESSION['email_notifications'];
    $items_per_page = $_SESSION['items_per_page'];
    $timezone = $_SESSION['timezone'];
    $nav_layout = $_SESSION['nav_layout'];

    $success_message = "Settings saved successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../../src/output.css">
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

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-slate-50 via-white to-blue-50'; ?> font-['Montserrat']">
    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">
                    Settings
                </h1>
                <p class="text-sm text-gray-600">Customize your experience and preferences</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">
                        Settings
                    </h1>
                    <p class="text-gray-600">Customize your experience and preferences</p>
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

            <form method="POST" class="space-y-6">
                <!-- Notification Settings -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-green-600 fas fa-bell"></i>
                        Notifications
                    </h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-semibold text-gray-800">Enable Notifications</p>
                                <p class="text-sm text-gray-500">Receive in-app notifications</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notifications_enabled"
                                    <?php echo $notifications_enabled ? 'checked' : ''; ?> class="sr-only peer">
                                <div
                                    class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                </div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-semibold text-gray-800">Email Notifications</p>
                                <p class="text-sm text-gray-500">Receive notifications via email</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="email_notifications"
                                    <?php echo $email_notifications ? 'checked' : ''; ?> class="sr-only peer">
                                <div
                                    class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600">
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Display Settings -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-green-600 fas fa-desktop"></i>
                        Display Preferences
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-semibold text-gray-700">Items per Page</label>
                            <select name="items_per_page"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 md:w-1/2 bg-white text-gray-800">
                                <option value="10" <?php echo $items_per_page === 10 ? 'selected' : ''; ?>>10 items
                                </option>
                                <option value="25" <?php echo $items_per_page === 25 ? 'selected' : ''; ?>>25 items
                                </option>
                                <option value="50" <?php echo $items_per_page === 50 ? 'selected' : ''; ?>>50 items
                                </option>
                                <option value="100" <?php echo $items_per_page === 100 ? 'selected' : ''; ?>>100 items
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Number of items to display in tables</p>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-semibold text-gray-700">Navigation Layout</label>
                            <select name="nav_layout"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 md:w-1/2 bg-white text-gray-800">
                                <option value="sidebar" <?php echo $nav_layout === 'sidebar' ? 'selected' : ''; ?>>
                                    <i class="fas fa-bars"></i> Sidebar (Modern)
                                </option>
                                <option value="navbar" <?php echo $nav_layout === 'navbar' ? 'selected' : ''; ?>>
                                    <i class="fas fa-window-maximize"></i> Top Navbar (Classic)
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Choose how you want the navigation to be displayed</p>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Security -->
                <div class="p-6 bg-white shadow-md rounded-xl">
                    <h2 class="mb-4 text-xl font-bold text-gray-800">
                        <i class="mr-2 text-green-600 fas fa-shield-alt"></i>
                        Privacy & Security
                    </h2>
                    <div class="space-y-3">
                        <a href="profile.php"
                            class="flex items-center justify-between p-4 transition-all border border-gray-200 rounded-lg hover:border-green-200 hover:bg-green-50">
                            <div class="flex items-center gap-3">
                                <i class="text-xl text-green-600 fas fa-key"></i>
                                <div>
                                    <p class="font-semibold text-gray-800">Change Password</p>
                                    <p class="text-xs text-gray-500">Update your account password</p>
                                </div>
                            </div>
                            <i class="text-gray-400 fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="flex justify-end gap-4 mb-8">
                    <a href="manager-dashboard.php"
                        class="px-6 py-3 text-gray-700 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </a>
                    <button type="submit" name="update_settings"
                        class="px-6 py-3 text-white transition-colors bg-green-600 rounded-lg hover:bg-green-700">
                        <i class="mr-2 fas fa-save"></i>
                        Save Settings
                    </button>
                </div>
            </form>
            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>