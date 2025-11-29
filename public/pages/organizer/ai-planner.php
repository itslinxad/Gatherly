<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

$first_name = $_SESSION['first_name'] ?? 'Organizer';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Event Planner | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/chat-history.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-pink-50'; ?> font-['Montserrat'] min-h-screen">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : ''; ?> h-screen flex">
        <!-- Chat Interface - Full Screen -->
        <div class="flex flex-col h-full flex-1">
            <div class="flex flex-col h-full bg-white">
                <!-- Chat Header (replaces top bar) -->
                <div class="p-4 text-white bg-linear-to-r from-indigo-600 to-cyan-600 border-b border-indigo-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <button id="toggleSidebarMobile"
                                class="lg:hidden w-10 h-10 flex items-center justify-center bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-colors">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div class="flex items-center justify-center w-10 h-10 bg-white rounded-full">
                                <i class="text-xl text-indigo-600 fas fa-robot"></i>
                            </div>
                            <div>
                                <h3 id="currentChatTitle" class="text-lg font-bold">AI Event Planner</h3>
                                <p class="text-xs opacity-90">Powered by Google Gemini Pro</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button id="showSidebar"
                                class="hidden px-3 py-1.5 text-sm font-semibold text-indigo-700 transition-colors bg-white rounded-lg hover:bg-gray-100">
                                <i class="fas fa-comments mr-1"></i>
                                Chat History
                            </button>
                            <button id="renameChatBtn"
                                class="px-3 py-1.5 text-sm font-semibold text-indigo-700 transition-colors bg-white rounded-lg hover:bg-gray-100 hidden">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button id="clearChat"
                                class="px-3 py-1.5 text-sm font-semibold text-indigo-700 transition-colors bg-white rounded-lg hover:bg-gray-100">
                                <i class="mr-1 fas fa-plus"></i>
                                New Chat
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-white rounded-full">
                            <i class="mr-1 fab fa-google"></i> Gemini
                        </span>
                        <span class="px-2 py-1 text-xs font-semibold text-indigo-700 bg-white rounded-full">
                            <i class="mr-1 fas fa-chart-line"></i> Smart Matching
                        </span>
                        <span class="px-2 py-1 text-xs font-semibold text-cyan-700 bg-white rounded-full">
                            <i class="mr-1 fas fa-comments"></i> Natural Conversation
                        </span>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div id="chatMessages" class="flex-1 p-6 overflow-y-auto bg-gray-50">
                    <!-- Messages will be added here dynamically -->
                </div>

                <!-- Chat Input -->
                <div class="p-6 bg-white border-t border-gray-200">
                    <form id="chatForm" class="flex gap-3">
                        <input type="text" id="chatInput"
                            class="flex-1 px-5 py-3 text-lg border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Type your message here..." autocomplete="off" autofocus>
                        <button type="submit"
                            class="px-8 py-3 font-semibold text-white transition-all transform bg-indigo-600 shadow-lg rounded-xl hover:bg-indigo-700 hover:scale-105">
                            <i class="mr-2 fas fa-paper-plane"></i>
                            Send
                        </button>
                    </form>

                    <!-- Quick Action Buttons -->
                    <div class="flex flex-wrap gap-2 mt-4">
                        <button
                            class="quick-action px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors">
                            Wedding for 150 guests
                        </button>
                        <button
                            class="quick-action px-3 py-1.5 text-sm bg-pink-100 text-pink-700 rounded-lg hover:bg-pink-200 transition-colors">
                            Corporate event for 100 people
                        </button>
                        <button
                            class="quick-action px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition-colors">
                            Birthday party for 80 guests
                        </button>
                        <button
                            class="quick-action px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                            Need all services
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat History Sidebar -->
        <div id="chatHistorySidebar" class="w-80 bg-white border-l border-gray-200 flex-col lg:flex">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200">
                <button id="newChatSidebar"
                    class="w-full px-4 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i>
                    New Chat
                </button>
            </div>

            <!-- Conversations List -->
            <div class="flex-1 overflow-y-auto p-3">
                <div id="conversationsList" class="space-y-2">
                    <!-- Conversations will be loaded here -->
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p class="text-sm">Loading conversations...</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-gray-200">
                <button id="toggleSidebar"
                    class="w-full px-3 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-chevron-right"></i>
                    Hide Sidebar
                </button>
            </div>
        </div>
    </div>

    <!-- Rename Conversation Modal -->
    <div id="renameModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background-color: rgba(0, 0, 0, 0.3);">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Rename Conversation</h3>
                <button id="closeRenameModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <label for="newConversationTitle" class="block text-sm font-medium text-gray-700 mb-2">
                    New Title
                </label>
                <input type="text" id="newConversationTitle"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="Enter conversation title..." maxlength="100">
            </div>
            <div class="flex gap-3 justify-end">
                <button id="cancelRename"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancel
                </button>
                <button id="confirmRename"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                    Save
                </button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ai-planner.js"></script>
</body>

</html>