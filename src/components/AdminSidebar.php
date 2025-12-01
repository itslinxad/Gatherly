<?php
// Get navigation layout preference from session (default: sidebar)
$nav_layout = $_SESSION['nav_layout'] ?? 'sidebar';
$current_page = basename($_SERVER['PHP_SELF']);
$first_name = $_SESSION['first_name'] ?? 'Admin';
?>

<!-- Sidebar (only shown when nav_layout is 'sidebar') -->
<?php if ($nav_layout === 'sidebar'): ?>
    <aside id="sidebar"
        class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full lg:translate-x-0 bg-white shadow-lg">
        <div class="h-full px-3 py-4 overflow-y-auto flex flex-col">
            <!-- Logo -->
            <div class="flex items-center mb-8 px-3">
                <img class="w-10 h-10 mr-3" src="../../assets/images/logo.png" alt="Gatherly Logo">
                <span class="text-xl font-bold text-gray-800">Gatherly</span>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-1">
                <a href="admin-dashboard.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'admin-dashboard.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-home w-5 text-center mr-3"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="manage-users.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'manage-users.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-users w-5 text-center mr-3"></i>
                    <span class="font-medium">Users</span>
                </a>
                <a href="manage-venues.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'manage-venues.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-building w-5 text-center mr-3"></i>
                    <span class="font-medium">Venues</span>
                </a>
                <a href="manage-events.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'manage-events.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-calendar w-5 text-center mr-3"></i>
                    <span class="font-medium">Events</span>
                </a>
                <a href="admin-chats.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'admin-chats.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-comments w-5 text-center mr-3"></i>
                    <span class="font-medium">Chats</span>
                </a>
                <a href="manage-suppliers.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'manage-suppliers.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-truck w-5 text-center mr-3"></i>
                    <span class="font-medium">Suppliers</span>
                </a>
                <a href="manage-services.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'manage-services.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-concierge-bell w-5 text-center mr-3"></i>
                    <span class="font-medium">Services</span>
                </a>
                <a href="reports.php"
                    class="flex items-center px-4 py-3 rounded-lg group <?php echo $current_page === 'reports.php' ? 'text-white bg-indigo-600' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-600'; ?> transition-colors">
                    <i class="fas fa-chart-bar w-5 text-center mr-3"></i>
                    <span class="font-medium">Reports</span>
                </a>
            </nav>

            <!-- User Menu -->
            <div class="pt-4 mt-4 border-t border-gray-200">
                <div class="relative">
                    <button id="profile-dropdown-btn"
                        class="flex items-center w-full px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                        <i class="fas fa-user-shield w-5 text-center mr-3 text-indigo-600"></i>
                        <span class="flex-1 text-left font-medium"><?php echo htmlspecialchars($first_name); ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div id="profile-dropdown"
                        class="hidden absolute bottom-full left-0 right-0 mb-2 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50 transition-colors">
                            <i class="fas fa-user mr-2"></i>Profile
                        </a>
                        <a href="settings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50 transition-colors">
                            <i class="fas fa-cog mr-2"></i>Settings
                        </a>
                        <a href="../../../src/services/signout-handler.php"
                            class="block px-4 py-2 text-red-600 hover:bg-red-50 transition-colors border-t border-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Mobile menu button -->
    <button id="sidebar-toggle"
        class="lg:hidden fixed top-4 left-4 z-50 p-2 text-gray-600 bg-white rounded-lg shadow-lg hover:bg-gray-100">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <!-- Overlay for mobile -->
    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"></div>

<?php else: ?>
    <!-- Top Navbar (only shown when nav_layout is 'navbar') -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="container px-4 mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-12 sm:h-16">
                <div class="flex items-center h-full">
                    <a href="../../../index.php" class="flex items-center group">
                        <img class="w-8 h-8 mr-2 transition-transform sm:w-10 sm:h-10 group-hover:scale-110"
                            src="../../assets/images/logo.png" alt="Gatherly Logo">
                        <span class="text-lg font-bold text-gray-800 sm:text-xl">Gatherly</span>
                    </a>
                </div>
                <div class="items-center hidden gap-6 md:flex">
                    <a href="admin-dashboard.php"
                        class="transition-colors <?php echo $current_page === 'admin-dashboard.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Dashboard</a>
                    <a href="manage-users.php"
                        class="transition-colors <?php echo $current_page === 'manage-users.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Users</a>
                    <a href="manage-venues.php"
                        class="transition-colors <?php echo $current_page === 'manage-venues.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Venues</a>
                    <a href="manage-events.php"
                        class="transition-colors <?php echo $current_page === 'manage-events.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Events</a>
                    <a href="admin-chats.php"
                        class="transition-colors <?php echo $current_page === 'admin-chats.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Chats</a>
                    <a href="manage-suppliers.php"
                        class="transition-colors <?php echo $current_page === 'manage-suppliers.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Suppliers</a>
                    <a href="reports.php"
                        class="transition-colors <?php echo $current_page === 'reports.php' ? 'font-semibold text-indigo-600' : 'text-gray-700 hover:text-indigo-600'; ?>">Reports</a>
                    <div class="relative">
                        <button id="profile-dropdown-btn"
                            class="flex items-center gap-2 text-gray-700 transition-colors cursor-pointer hover:text-indigo-600">
                            <i class="text-2xl fas fa-user-shield"></i>
                            <span><?php echo htmlspecialchars($first_name); ?></span>
                            <i class="text-xs fas fa-chevron-down"></i>
                        </button>
                        <div id="profile-dropdown"
                            class="absolute right-0 hidden w-48 py-2 mt-2 bg-white rounded-lg shadow-lg">
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Profile</a>
                            <a href="settings.php" class="block px-4 py-2 text-gray-700 hover:bg-indigo-50">Settings</a>
                            <a href="../../../src/services/signout-handler.php"
                                class="block px-4 py-2 text-red-600 hover:bg-red-50">Sign Out</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

<script>
    // Sidebar toggle for mobile (only if sidebar layout)
    <?php if ($nav_layout === 'sidebar'): ?>
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            });

            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }
    <?php endif; ?>

    // Profile dropdown toggle
    const profileBtn = document.getElementById('profile-dropdown-btn');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
</script>