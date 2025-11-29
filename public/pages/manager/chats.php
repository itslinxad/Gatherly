<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../signin.php");
    exit();
}

// DATABASE CONFIGURATION 
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sad_db');
define('ENCRYPTION_KEY', 'your-secret-encryption-key-32-chars!!');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function getDbConnection()
{
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
        return $conn;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

function encryptMessage($message)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($message, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptMessage($encrypted)
{
    $parts = explode('::', base64_decode($encrypted), 2);
    if (count($parts) === 2) {
        list($encrypted_data, $iv) = $parts;
        return openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    }
    return false;
}

// API HANDLER 
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $conn = getDbConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    switch ($action) {
        case 'get_conversations':
            try {
                $sql = "SELECT DISTINCT
                            CASE 
                                WHEN c.sender_id = :user_id THEN c.receiver_id
                                ELSE c.sender_id
                            END as other_user_id,
                            u.first_name,
                            u.last_name,
                            u.role,
                            (SELECT message_text 
                             FROM chat 
                             WHERE (sender_id = :user_id2 AND receiver_id = other_user_id)
                                OR (sender_id = other_user_id AND receiver_id = :user_id3)
                             ORDER BY timestamp DESC LIMIT 1) as last_message,
                            (SELECT timestamp 
                             FROM chat 
                             WHERE (sender_id = :user_id4 AND receiver_id = other_user_id)
                                OR (sender_id = other_user_id AND receiver_id = :user_id5)
                             ORDER BY timestamp DESC LIMIT 1) as last_message_time,
                            (SELECT COUNT(*) 
                             FROM chat 
                             WHERE sender_id = other_user_id 
                                AND receiver_id = :user_id6 
                                AND is_read = 0) as unread_count
                        FROM chat c
                        JOIN users u ON u.user_id = CASE 
                            WHEN c.sender_id = :user_id7 THEN c.receiver_id
                            ELSE c.sender_id
                        END
                        WHERE c.sender_id = :user_id8 OR c.receiver_id = :user_id9
                        ORDER BY last_message_time DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':user_id2' => $user_id,
                    ':user_id3' => $user_id,
                    ':user_id4' => $user_id,
                    ':user_id5' => $user_id,
                    ':user_id6' => $user_id,
                    ':user_id7' => $user_id,
                    ':user_id8' => $user_id,
                    ':user_id9' => $user_id
                ]);

                $conversations = $stmt->fetchAll();
                foreach ($conversations as &$conv) {
                    if ($conv['last_message']) {
                        $conv['last_message'] = decryptMessage($conv['last_message']) ?: '[Encrypted message]';
                    }
                }
                echo json_encode(['success' => true, 'conversations' => $conversations]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'get_messages':
            try {
                $receiver_id = $_GET['receiver_id'] ?? 0;
                $last_message_id = $_GET['last_message_id'] ?? 0;

                $sql = "SELECT c.*, 
                               s.first_name as sender_first_name,
                               s.last_name as sender_last_name
                        FROM chat c
                        JOIN users s ON c.sender_id = s.user_id
                        WHERE ((c.sender_id = :user_id AND c.receiver_id = :receiver_id)
                           OR (c.sender_id = :receiver_id2 AND c.receiver_id = :user_id2))";

                // Only get new messages if last_message_id is provided
                if ($last_message_id > 0) {
                    $sql .= " AND c.chat_id > :last_message_id";
                }

                $sql .= " ORDER BY c.timestamp ASC";

                $stmt = $conn->prepare($sql);
                $params = [
                    ':user_id' => $user_id,
                    ':receiver_id' => $receiver_id,
                    ':receiver_id2' => $receiver_id,
                    ':user_id2' => $user_id
                ];

                if ($last_message_id > 0) {
                    $params[':last_message_id'] = $last_message_id;
                }

                $stmt->execute($params);

                $messages = $stmt->fetchAll();
                foreach ($messages as &$msg) {
                    if ($msg['message_text']) {
                        $decrypted = decryptMessage($msg['message_text']);
                        $msg['message_text'] = $decrypted !== false ? $decrypted : '[Unable to decrypt]';
                    }
                }
                echo json_encode(['success' => true, 'messages' => $messages]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'send_message':
            try {
                $receiver_id = $_POST['receiver_id'] ?? 0;
                $message_text = $_POST['message_text'] ?? '';

                if (empty(trim($message_text))) {
                    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                    exit;
                }

                $encrypted_message = encryptMessage($message_text);
                $sql = "INSERT INTO chat (sender_id, receiver_id, message_text, is_file, is_read, timestamp) 
                        VALUES (:sender_id, :receiver_id, :message_text, 0, 0, NOW())";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':sender_id' => $user_id,
                    ':receiver_id' => $receiver_id,
                    ':message_text' => $encrypted_message
                ]);

                echo json_encode(['success' => true, 'chat_id' => $conn->lastInsertId()]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'upload_file':
            try {
                if (!isset($_FILES['file'])) {
                    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                    exit;
                }

                $receiver_id = $_POST['receiver_id'] ?? 0;
                $file = $_FILES['file'];
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $max_size = 5 * 1024 * 1024;

                if (!in_array($file['type'], $allowed)) {
                    echo json_encode(['success' => false, 'error' => 'File type not allowed']);
                    exit;
                }

                if ($file['size'] > $max_size) {
                    echo json_encode(['success' => false, 'error' => 'File exceeds 5MB']);
                    exit;
                }

                $upload_dir = '../../uploads/chat_files/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('chat_') . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $file_url = 'uploads/chat_files/' . $filename;
                    $encrypted_message = encryptMessage('File: ' . $file['name']);

                    $sql = "INSERT INTO chat (sender_id, receiver_id, message_text, file_url, is_file, is_read, timestamp) 
                            VALUES (:sender_id, :receiver_id, :message_text, :file_url, 1, 0, NOW())";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':sender_id' => $user_id,
                        ':receiver_id' => $receiver_id,
                        ':message_text' => $encrypted_message,
                        ':file_url' => $file_url
                    ]);

                    echo json_encode(['success' => true, 'file_url' => $file_url, 'chat_id' => $conn->lastInsertId()]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Upload failed']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'mark_as_read':
            try {
                $sender_id = $_POST['receiver_id'] ?? 0;
                $sql = "UPDATE chat SET is_read = 1 
                        WHERE sender_id = :sender_id AND receiver_id = :user_id AND is_read = 0";

                $stmt = $conn->prepare($sql);
                $stmt->execute([':sender_id' => $sender_id, ':user_id' => $user_id]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'get_managers':
            try {
                // Get organizers who have booked events with this manager's venues
                $sql = "SELECT DISTINCT 
                            u.user_id, 
                            u.first_name, 
                            u.last_name, 
                            u.email, 
                            u.role,
                            e.event_name,
                            e.status as event_status,
                            v.venue_name
                        FROM events e
                        INNER JOIN users u ON e.organizer_id = u.user_id
                        INNER JOIN venues v ON e.venue_id = v.venue_id
                        WHERE e.manager_id = :user_id
                        AND e.status IN ('pending', 'confirmed')
                        AND u.status = 'active'
                        ORDER BY e.event_date DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute([':user_id' => $user_id]);
                $managers = $stmt->fetchAll();

                echo json_encode(['success' => true, 'managers' => $managers]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'search_conversations':
            try {
                $search_term = $_GET['search_term'] ?? '';
                $sql = "SELECT DISTINCT
                            CASE 
                                WHEN c.sender_id = :user_id THEN c.receiver_id
                                ELSE c.sender_id
                            END as other_user_id,
                            u.first_name,
                            u.last_name,
                            u.role
                        FROM chat c
                        JOIN users u ON u.user_id = CASE 
                            WHEN c.sender_id = :user_id2 THEN c.receiver_id
                            ELSE c.sender_id
                        END
                        WHERE (c.sender_id = :user_id3 OR c.receiver_id = :user_id4)
                        AND (u.first_name LIKE :search OR u.last_name LIKE :search2)
                        ORDER BY u.first_name, u.last_name";

                $stmt = $conn->prepare($sql);
                $search_param = "%$search_term%";
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':user_id2' => $user_id,
                    ':user_id3' => $user_id,
                    ':user_id4' => $user_id,
                    ':search' => $search_param,
                    ':search2' => $search_param
                ]);

                $conversations = $stmt->fetchAll();
                echo json_encode(['success' => true, 'conversations' => $conversations]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
    exit;
}

$first_name = $_SESSION['first_name'] ?? 'Manager';
$user_id = $_SESSION['user_id'];
$nav_layout = $_SESSION['nav_layout'] ?? 'navbar';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Messages | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../../src/output.css'); ?>"
        rel="stylesheet">
    <script src="https://kit.fontawesome.com/2a99de0fa5.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <style>
        /* Minimal custom animations and styles that can't be done with Tailwind */
        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message {
            animation: messageSlide 0.3s ease-out;
        }

        .message.sent p {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }

        .conversation.active {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 3px solid #16a34a;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 250px);
            min-height: 500px;
        }

        .messages-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #chatMessages {
            flex: 1;
            overflow-y: auto;
            min-height: 200px;
            max-height: none !important;
            padding: 1.25rem;
        }

        .chat-input-container {
            flex-shrink: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            padding: 1rem 1.25rem;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 5px;
        }

        .emoji {
            cursor: pointer;
            font-size: 1.5rem;
            padding: 5px;
            text-align: center;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .emoji:hover {
            background-color: #f3f4f6;
        }

        /* Custom scrollbar styles */
        #chatMessages::-webkit-scrollbar,
        .emoji-picker::-webkit-scrollbar,
        .search-results::-webkit-scrollbar,
        #conversationList::-webkit-scrollbar {
            width: 6px;
        }

        #chatMessages::-webkit-scrollbar-track,
        .emoji-picker::-webkit-scrollbar-track,
        .search-results::-webkit-scrollbar-track,
        #conversationList::-webkit-scrollbar-track {
            background: #f3f4f6;
        }

        #chatMessages::-webkit-scrollbar-thumb,
        .emoji-picker::-webkit-scrollbar-thumb,
        .search-results::-webkit-scrollbar-thumb,
        #conversationList::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        #chatMessages::-webkit-scrollbar-thumb:hover,
        .emoji-picker::-webkit-scrollbar-thumb:hover,
        .search-results::-webkit-scrollbar-thumb:hover,
        #conversationList::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
</head>

<body class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-green-50 via-white to-teal-50'; ?> font-['Montserrat'] min-h-screen" data-user-id="<?php echo $user_id; ?>">

    <?php include '../../../src/components/ManagerSidebar.php'; ?>

    <!-- Main Content -->
    <div class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
                <p class="text-sm text-gray-600">Connect with event organizers and other managers</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Messages</h1>
                    <p class="text-gray-600">Connect with event organizers and other managers</p>
                </div>
            <?php endif; ?>
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button id="toggleSidebar"
                            class="p-2 text-gray-600 transition-colors rounded-lg md:hidden hover:bg-gray-100">
                            <i class="text-xl fas fa-bars"></i>
                        </button>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">
                                <i class="mr-2 text-green-600 fas fa-comments"></i>Messages
                            </h1>
                            <p class="mt-1 text-gray-600">Connect with event organizers and other managers</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="relative">
                            <button id="searchBtn"
                                class="flex items-center gap-2 px-4 py-2 text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50">
                                <i class="fas fa-search"></i>
                                <span class="hidden sm:inline">Search</span>
                            </button>
                            <div id="searchModal" class="hidden absolute top-full right-0 mt-2 w-[400px] max-w-[90vw] bg-white border border-gray-200 rounded-xl shadow-xl z-50">
                                <div class="p-3 border-b border-gray-200">
                                    <input type="text" id="searchInput" placeholder="Search conversations..."
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" />
                                </div>
                                <div id="searchResults" class="max-h-[300px] overflow-y-auto">
                                    <div class="p-4 text-sm text-center text-gray-500">Type to search conversations</div>
                                </div>
                            </div>
                        </div>
                        <button id="newMessageBtn"
                            class="flex items-center gap-2 px-4 py-2 text-white transition-colors bg-green-600 rounded-lg shadow-sm hover:bg-green-700">
                            <i class="fas fa-plus"></i>
                            <span class="hidden sm:inline">New Message</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="relative flex gap-5 h-[calc(100vh-250px)]">
                <!-- Sidebar - Conversations List -->
                <div id="chatSidebar"
                    class="fixed inset-y-0 left-0 z-50 flex flex-col overflow-hidden transition-transform duration-300 transform -translate-x-full bg-white border border-gray-200 shadow-lg w-80 md:relative md:translate-x-0 md:w-80 rounded-xl md:rounded-xl">
                    <!-- Close button for mobile -->
                    <button id="closeSidebar"
                        class="absolute z-10 p-2 text-gray-600 transition-colors rounded-lg top-4 right-4 md:hidden hover:bg-gray-100">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="p-5 border-b border-gray-200 shrink-0 bg-linear-to-r from-green-50 to-teal-50">
                        <h3 class="text-sm font-semibold text-gray-700">All Conversations</h3>
                        <p id="conversationCount" class="text-xs text-gray-500">Loading...</p>
                    </div>

                    <div id="conversationList" class="flex-1 overflow-x-hidden overflow-y-auto">
                        <!-- Conversations will be loaded dynamically -->
                    </div>
                </div>

                <!-- Overlay for mobile -->
                <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-black bg-opacity-50 md:hidden"></div>

                <!-- Chat Area -->
                <div id="chatArea" class="flex-1 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl chat-container">

                    <!-- Chat Header -->
                    <div id="chatHeader"
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-linear-to-r from-green-50 to-teal-50">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div
                                    class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-green-600 rounded-full">
                                    <i class="fas fa-comments"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-gray-900">Select a conversation</h4>
                                <span class="text-sm text-gray-500">Choose a chat from the left to start messaging</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-green-600">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button
                                class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-green-600">
                                <i class="fas fa-video"></i>
                            </button>
                            <button
                                class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-green-600">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Messages Wrapper -->
                    <div class="messages-wrapper">
                        <!-- Chat Messages -->
                        <div id="chatMessages" class="flex flex-col gap-4 overflow-y-auto bg-gray-50">
                            <!-- Messages will be loaded dynamically -->
                        </div>

                        <!-- Chat Input -->
                        <div class="chat-input-container">
                            <!-- File Preview -->
                            <div id="filePreview" class="hidden bg-slate-50 border border-slate-200 rounded-lg p-3 mb-2.5 flex items-center gap-2.5">
                                <i class="fas fa-file text-2xl text-green-600"></i>
                                <div class="flex-1">
                                    <div id="fileName" class="text-sm font-medium text-slate-800"></div>
                                    <div id="fileSize" class="text-xs text-slate-600"></div>
                                </div>
                                <button id="removeFile" class="p-1 text-red-500 transition-colors bg-transparent border-none rounded hover:bg-red-100">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <!-- Uploading Indicator -->
                            <div id="uploadingIndicator" class="hidden bg-sky-50 border border-sky-200 rounded-lg p-2 px-3 my-1 flex items-center gap-2 text-xs text-sky-700">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Uploading file...</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <button id="attachFile"
                                    class="p-2 text-lg text-gray-600 transition-colors rounded-lg hover:bg-gray-100 hover:text-green-600">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf" />

                                <!-- Emoji Picker -->
                                <div id="emojiPicker" class="hidden absolute bottom-16 right-20 bg-white border border-gray-200 rounded-xl p-2.5 shadow-xl z-50 w-[300px] max-h-[250px] overflow-y-auto">
                                    <div class="emoji-grid">
                                        <!-- Emojis by JavaScript -->
                                    </div>
                                </div>

                                <div
                                    class="flex-1 flex items-center gap-2 px-4 py-2.5 bg-gray-100 rounded-lg border border-gray-200 focus-within:border-green-600 focus-within:ring-2 focus-within:ring-green-200">
                                    <input type="text" id="messageInput" placeholder="Type your message..."
                                        class="flex-1 text-sm text-gray-900 placeholder-gray-500 bg-transparent border-none outline-none" />
                                    <button id="emojiButton" class="p-1 text-gray-500 transition-colors hover:text-green-600">
                                        <i class="text-lg far fa-smile"></i>
                                    </button>
                                </div>
                                <button id="sendMessageBtn"
                                    class="flex items-center justify-center w-10 h-10 text-white transition-all bg-green-600 rounded-lg shadow-md hover:bg-green-700 hover:shadow-lg">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($nav_layout === 'sidebar'): ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Message Modal -->
    <div id="newMessageModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-linear-to-r from-green-50 to-teal-50">
                    <h3 class="text-lg font-bold text-gray-900">New Message</h3>
                    <button id="closeModalBtn" class="text-gray-600 transition-colors hover:text-gray-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-5">
                    <p class="mb-4 text-sm text-gray-600">Select a user to start a conversation:</p>
                    <div id="managersList" class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Managers will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentReceiverId = null;
        let messagePollingInterval = null;
        let lastMessageId = 0;
        let isPollingEnabled = true;
        let selectedFile = null;

        const commonEmojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '🤑', '🤠', '😈', '👿', '👹', '👺', '🤡', '💩', '👻', '💀', '☠️', '👽', '👾', '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'];

        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
            setupEventListeners();
            setupEmojiPicker();
            setInterval(loadConversations, 5000);
        });

        function setupEventListeners() {
            const sendBtn = document.getElementById('sendMessageBtn');
            sendBtn.addEventListener('click', sendMessageOrFile);

            const messageInput = document.getElementById('messageInput');
            messageInput.addEventListener('keypress', e => {
                if (e.key === 'Enter') {
                    sendMessageOrFile();
                }
            });

            // File attachment handling
            document.getElementById('attachFile').addEventListener('click', () => document.getElementById('fileInput').click());
            document.getElementById('fileInput').addEventListener('change', handleFileSelect);
            document.getElementById('removeFile').addEventListener('click', removeSelectedFile);

            // New Message Button
            document.getElementById('newMessageBtn').addEventListener('click', openNewMessageModal);
            document.getElementById('closeModalBtn').addEventListener('click', closeNewMessageModal);
            document.getElementById('newMessageModal').addEventListener('click', function(e) {
                if (e.target === this) closeNewMessageModal();
            });

            // Search functionality
            document.getElementById('searchBtn').addEventListener('click', toggleSearchModal);
            document.getElementById('searchInput').addEventListener('input', handleSearch);
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#searchBtn') && !e.target.closest('#searchModal')) {
                    document.getElementById('searchModal').classList.add('hidden');
                }
            });

            // Emoji button
            document.getElementById('emojiButton').addEventListener('click', toggleEmojiPicker);
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#emojiButton') && !e.target.closest('#emojiPicker')) {
                    document.getElementById('emojiPicker').classList.add('hidden');
                }
            });

            const profileBtn = document.getElementById('profile-dropdown-btn');
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                    }
                });
                profileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }

            const toggleSidebarBtn = document.getElementById('toggleSidebar');
            const closeSidebarBtn = document.getElementById('closeSidebar');
            const chatSidebar = document.getElementById('chatSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                chatSidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
            }

            function closeSidebar() {
                chatSidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }

            if (toggleSidebarBtn) {
                toggleSidebarBtn.addEventListener('click', openSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', closeSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }
        }

        function setupEmojiPicker() {
            const emojiPicker = document.getElementById('emojiPicker');
            const emojiGrid = emojiPicker.querySelector('.emoji-grid');
            commonEmojis.forEach(emoji => {
                const emojiElement = document.createElement('div');
                emojiElement.className = 'emoji';
                emojiElement.textContent = emoji;
                emojiElement.addEventListener('click', () => {
                    const messageInput = document.getElementById('messageInput');
                    messageInput.value += emoji;
                    messageInput.focus();
                    emojiPicker.classList.add('hidden');
                });
                emojiGrid.appendChild(emojiElement);
            });
        }

        function toggleEmojiPicker() {
            document.getElementById('emojiPicker').classList.toggle('hidden');
        }

        function toggleSearchModal() {
            document.getElementById('searchModal').classList.toggle('hidden');
            if (!document.getElementById('searchModal').classList.contains('hidden')) {
                document.getElementById('searchInput').focus();
            }
        }

        function handleSearch(e) {
            const searchTerm = e.target.value.trim();
            const resultsContainer = document.getElementById('searchResults');

            if (searchTerm.length === 0) {
                resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-gray-500">Type to search conversations</div>';
                return;
            }

            if (searchTerm.length < 2) {
                resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-gray-500">Type at least 2 characters to search</div>';
                return;
            }

            resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';

            fetch(`?action=search_conversations&search_term=${encodeURIComponent(searchTerm)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        displaySearchResults(data.conversations);
                    }
                })
                .catch(e => {
                    console.error('Error:', e);
                    resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-red-500">Search failed. Please try again.</div>';
                });
        }

        function displaySearchResults(conversations) {
            const resultsContainer = document.getElementById('searchResults');

            if (!conversations.length) {
                resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-gray-500">No conversations found</div>';
                return;
            }

            resultsContainer.innerHTML = conversations.map(c => {
                const initials = (c.first_name[0] + c.last_name[0]).toUpperCase();
                const name = `${c.first_name} ${c.last_name}`;
                const role = c.role.charAt(0).toUpperCase() + c.role.slice(1);

                return `<div class="p-3 border-b border-gray-100 cursor-pointer transition-colors hover:bg-gray-50 last:border-b-0" onclick="selectConversationFromSearch(${c.other_user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 text-sm font-bold text-white bg-green-600 rounded-full">
                    ${initials}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">${escapeHtml(name)}</p>
                    <p class="text-xs text-gray-500">${escapeHtml(role)}</p>
                </div>
                <i class="text-gray-400 fas fa-chevron-right"></i>
            </div>
        </div>`;
            }).join('');
        }

        function selectConversationFromSearch(userId, userName, userRole, initials) {
            document.getElementById('searchModal').classList.add('hidden');
            document.getElementById('searchInput').value = '';
            selectConversation(userId, userName, userRole, initials);
        }

        function openNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('hidden');
            loadManagers();
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('hidden');
        }

        function loadManagers() {
            fetch('?action=get_managers')
                .then(r => r.json())
                .then(data => {
                    if (data.success) displayManagers(data.managers);
                })
                .catch(e => console.error('Error:', e));
        }

        function displayManagers(managers) {
            const list = document.getElementById('managersList');
            const newMessageBtn = document.getElementById('newMessageBtn');

            if (!managers.length) {
                list.innerHTML = `<div class="p-4 text-center text-gray-500">
                    <i class="fas fa-info-circle text-2xl mb-2"></i>
                    <p class="text-sm">No organizers available to chat with.</p>
                    <p class="text-xs mt-1">You can only chat with organizers who have booked events at your venues.</p>
                </div>`;

                // Disable the New Message button when no events
                if (newMessageBtn) {
                    newMessageBtn.disabled = true;
                    newMessageBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    newMessageBtn.classList.remove('hover:bg-green-700');
                    newMessageBtn.title = 'No organizers available to message';
                }
                return;
            }

            // Enable the New Message button
            if (newMessageBtn) {
                newMessageBtn.disabled = false;
                newMessageBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                newMessageBtn.classList.add('hover:bg-green-700');
                newMessageBtn.title = '';
            }

            const groupedManagers = {
                'organizer': managers.filter(m => m.role === 'organizer'),
                'manager': managers.filter(m => m.role === 'manager')
            };

            let html = '';

            if (groupedManagers.organizer.length > 0) {
                html += '<div class="mb-4">';
                html += '<p class="text-xs font-semibold text-gray-500 uppercase mb-2">Organizers</p>';
                html += groupedManagers.organizer.map(m => createManagerItem(m)).join('');
                html += '</div>';
            }

            if (groupedManagers.manager.length > 0) {
                html += '<div>';
                html += '<p class="text-xs font-semibold text-gray-500 uppercase mb-2">Managers</p>';
                html += groupedManagers.manager.map(m => createManagerItem(m)).join('');
                html += '</div>';
            }

            list.innerHTML = html;
        }

        function createManagerItem(manager) {
            const initials = (manager.first_name[0] + manager.last_name[0]).toUpperCase();
            const name = `${manager.first_name} ${manager.last_name}`;
            const role = manager.role.charAt(0).toUpperCase() + manager.role.slice(1);
            const eventInfo = manager.event_name ? `${manager.event_name} - ${manager.venue_name}` : '';
            const statusClass = manager.event_status === 'confirmed' ? 'text-green-600' : 'text-yellow-600';

            return `<div class="flex items-center gap-3 p-3 transition border border-gray-200 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-300" onclick="startConversationWith(${manager.user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
        <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-indigo-600 rounded-full">
            ${initials}
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-gray-900">${escapeHtml(name)}</p>
            ${eventInfo ? `<p class="text-xs ${statusClass} font-medium"><i class="fas fa-calendar-alt mr-1"></i>${escapeHtml(eventInfo)}</p>` : ''}
            <p class="text-xs text-gray-500">${escapeHtml(manager.email)} • ${escapeHtml(role)}</p>
        </div>
        <i class="text-gray-400 fas fa-chevron-right"></i>
    </div>`;
        }

        function startConversationWith(userId, userName, userRole, initials) {
            closeNewMessageModal();
            selectConversation(userId, userName, userRole, initials);

            document.getElementById('messageInput').focus();
        }

        function loadConversations() {
            fetch('?action=get_conversations')
                .then(r => r.json())
                .then(data => {
                    if (data.success) displayConversations(data.conversations);
                })
                .catch(e => console.error('Error:', e));
        }

        function displayConversations(conversations) {
            const list = document.getElementById('conversationList');
            const countEl = document.getElementById('conversationCount');

            if (!conversations.length) {
                list.innerHTML = '<div class="p-4 text-center text-gray-500">No conversations yet</div>';
                countEl.textContent = '0 active chats';
                return;
            }

            countEl.textContent = `${conversations.length} active chat${conversations.length !== 1 ? 's' : ''}`;

            list.innerHTML = conversations.map(c => {
                const initials = (c.first_name[0] + c.last_name[0]).toUpperCase();
                const name = `${c.first_name} ${c.last_name}`;
                const role = c.role.charAt(0).toUpperCase() + c.role.slice(1);
                const msg = c.last_message || 'No messages yet';
                const time = formatTimeAgo(c.last_message_time);
                const badge = c.unread_count > 0 ? `<span class="badge bg-indigo-600 text-white text-xs rounded-full px-2 py-0.5 font-semibold">${c.unread_count}</span>` : '';
                const active = currentReceiverId == c.other_user_id ? 'active' : '';

                return `<div class="flex items-center justify-between p-4 transition border-b border-gray-100 cursor-pointer conversation ${active} hover:bg-indigo-50" onclick="selectConversation(${c.other_user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="flex items-center justify-center w-12 h-12 text-sm font-bold text-white bg-indigo-600 rounded-full shadow-md">${initials}</div>
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-900 truncate">${escapeHtml(name)}</p>
                    <p class="text-xs text-gray-500 truncate">${escapeHtml(role)}</p>
                    <span class="text-xs text-gray-500 truncate">${escapeHtml(msg)}</span>
                </div>
            </div>
            <div class="flex flex-col items-end gap-1">${badge}<span class="text-xs text-gray-400">${time}</span></div>
        </div>`;
            }).join('');
        }

        function selectConversation(userId, userName, userRole, initials) {
            currentReceiverId = userId;
            lastMessageId = 0;
            isPollingEnabled = true;

            removeSelectedFile();

            document.querySelectorAll('.conversation').forEach(conv => {
                conv.classList.remove('active');
            });

            const conversations = document.querySelectorAll('.conversation');
            conversations.forEach(conv => {
                if (conv.textContent.includes(userName)) {
                    conv.classList.add('active');
                }
            });

            // Update chat header
            document.getElementById('chatHeader').innerHTML = `
        <div class="flex items-center gap-3">
            <div class="relative">
                <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-indigo-600 rounded-full">
                    ${initials}
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
            </div>
            <div>
                <h4 class="text-base font-bold text-gray-900">${userName}</h4>
                <span class="text-sm text-green-600"><i class="mr-1 fas fa-circle text-[8px]"></i>Online - ${userRole}</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-indigo-600">
                <i class="fas fa-phone"></i>
            </button>
            <button class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-indigo-600">
                <i class="fas fa-video"></i>
            </button>
            <button class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-indigo-600">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    `;

            document.getElementById('chatMessages').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">Loading messages...</div>';

            loadInitialMessages(userId);

            markAsRead(userId);

            // Close sidebar on mobile
            if (window.innerWidth < 768) {
                document.getElementById('chatSidebar').classList.add('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
            }

            // Start polling for new messages
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            messagePollingInterval = setInterval(() => {
                if (isPollingEnabled && currentReceiverId) {
                    loadNewMessages(currentReceiverId);
                }
            }, 2000);
        }

        function loadInitialMessages(receiverId) {
            const url = `?action=get_messages&receiver_id=${receiverId}`;

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.messages.length > 0) {
                            // Update last message ID
                            lastMessageId = Math.max(...data.messages.map(m => m.chat_id));
                            displayMessages(data.messages);
                        } else {
                            // No messages found - show empty state
                            document.getElementById('chatMessages').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">No messages yet. Start the conversation!</div>';
                        }
                    }
                })
                .catch(e => {
                    console.error('Error:', e);
                    document.getElementById('chatMessages').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">Error loading messages</div>';
                });
        }

        function loadNewMessages(receiverId) {
            if (lastMessageId === 0) return;

            const url = `?action=get_messages&receiver_id=${receiverId}&last_message_id=${lastMessageId}`;

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        // Update last message ID
                        lastMessageId = Math.max(...data.messages.map(m => m.chat_id));
                        displayNewMessages(data.messages);
                    }
                })
                .catch(e => console.error('Error loading new messages:', e));
        }

        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            const currentUserId = parseInt(document.body.dataset.userId);

            if (!messages.length) {
                chatMessages.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">No messages yet. Start the conversation!</div>';
                return;
            }

            let lastDate = null,
                html = '';

            messages.forEach(msg => {
                const d = new Date(msg.timestamp);
                const dateStr = formatDate(d);

                if (dateStr !== lastDate) {
                    html += `<div class="flex items-center justify-center my-2">
                <div class="px-4 py-1 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-full shadow-sm">${dateStr}</div>
            </div>`;
                    lastDate = dateStr;
                }

                const isSent = msg.sender_id === currentUserId;
                const cls = isSent ? 'sent' : 'received';
                const time = formatTime(d);

                if (msg.is_file && msg.file_url) {
                    const isImg = /\.(jpg|jpeg|png|gif)$/i.test(msg.file_url);
                    html += `<div class="message flex flex-col max-w-[70%] ${cls === 'received' ? 'self-start' : 'self-end text-right'}">
                ${isImg 
                    ? `<img src="../../${msg.file_url}" class="max-w-[250px] max-h-[250px] rounded-xl mb-2 shadow-md cursor-pointer transition-transform hover:scale-105" onclick="window.open('../../${msg.file_url}', '_blank')" />`
                    : `<a href="../../${msg.file_url}" target="_blank" class="flex items-center gap-2 p-3 bg-white rounded-lg shadow">
                        <i class="text-green-600 fas fa-file"></i>
                        <span class="text-sm text-gray-700">${escapeHtml(msg.message_text)}</span>
                      </a>`
                }
                <div class="flex items-center gap-1 mt-1 text-[10px] text-gray-400 ${cls === 'sent' ? 'justify-end' : ''}">
                    <span class="font-medium">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-green-500' : 'text-gray-400'}"></i>` : ''}
                </div>
            </div>`;
                } else {
                    html += `<div class="message flex flex-col max-w-[70%] ${cls === 'received' ? 'self-start' : 'self-end text-right'}">
                <p class="${cls === 'received' ? 'bg-gray-100 text-gray-800 rounded-bl-sm' : 'text-white rounded-br-sm shadow-lg'} px-4 py-3 rounded-2xl text-sm leading-relaxed break-words shadow-sm">${escapeHtml(msg.message_text)}</p>
                <div class="flex items-center gap-1 mt-1 text-[10px] text-gray-400 ${cls === 'sent' ? 'justify-end' : ''}">
                    <span class="font-medium">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-green-500' : 'text-gray-400'}"></i>` : ''}
                </div>
            </div>`;
                }
            });

            const shouldScroll = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
            chatMessages.innerHTML = html;
            if (shouldScroll) chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function displayNewMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            const currentUserId = parseInt(document.body.dataset.userId);

            messages.forEach(msg => {
                const d = new Date(msg.timestamp);
                const time = formatTime(d);
                const isSent = msg.sender_id === currentUserId;
                const cls = isSent ? 'sent' : 'received';

                let messageHtml = '';

                if (msg.is_file && msg.file_url) {
                    const isImg = /\.(jpg|jpeg|png|gif)$/i.test(msg.file_url);
                    messageHtml = `<div class="message flex flex-col max-w-[70%] ${cls === 'received' ? 'self-start' : 'self-end text-right'}">
                ${isImg 
                    ? `<img src="../../${msg.file_url}" class="max-w-[250px] max-h-[250px] rounded-xl mb-2 shadow-md cursor-pointer transition-transform hover:scale-105" onclick="window.open('../../${msg.file_url}', '_blank')" />`
                    : `<a href="../../${msg.file_url}" target="_blank" class="flex items-center gap-2 p-3 bg-white rounded-lg shadow">
                        <i class="text-green-600 fas fa-file"></i>
                        <span class="text-sm text-gray-700">${escapeHtml(msg.message_text)}</span>
                      </a>`
                }
                <div class="flex items-center gap-1 mt-1 text-[10px] text-gray-400 ${cls === 'sent' ? 'justify-end' : ''}">
                    <span class="font-medium">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-green-500' : 'text-gray-400'}"></i>` : ''}
                </div>
            </div>`;
                } else {
                    messageHtml = `<div class="message flex flex-col max-w-[70%] ${cls === 'received' ? 'self-start' : 'self-end text-right'}">
                <p class="${cls === 'received' ? 'bg-gray-100 text-gray-800 rounded-bl-sm' : 'text-white rounded-br-sm shadow-lg'} px-4 py-3 rounded-2xl text-sm leading-relaxed break-words shadow-sm">${escapeHtml(msg.message_text)}</p>
                <div class="flex items-center gap-1 mt-1 text-[10px] text-gray-400 ${cls === 'sent' ? 'justify-end' : ''}">
                    <span class="font-medium">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-green-500' : 'text-gray-400'}"></i>` : ''}
                </div>
            </div>`;
                }

                chatMessages.insertAdjacentHTML('beforeend', messageHtml);
            });

            // Auto scroll to bottom if user is near the bottom
            const isNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 200;
            if (isNearBottom) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            selectedFile = file;

            // Show file preview
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            filePreview.classList.remove('hidden');

            event.target.value = '';
        }

        function removeSelectedFile() {
            selectedFile = null;
            document.getElementById('filePreview').classList.add('hidden');
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function sendMessageOrFile() {
            const messageInput = document.getElementById('messageInput');
            const messageText = messageInput.value.trim();

            if (selectedFile) {
                uploadFile(selectedFile, messageText);
            } else if (messageText) {
                sendMessage(messageText);
            }
        }

        function sendMessage(messageText) {
            if (!currentReceiverId) return;

            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('receiver_id', currentReceiverId);
            fd.append('message_text', messageText);

            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('messageInput').value = '';
                        loadConversations();
                    } else alert('Error: ' + data.error);
                })
                .catch(e => {
                    console.error(e);
                    alert('Failed to send');
                });
        }

        function uploadFile(file, caption = '') {
            if (!currentReceiverId) {
                alert('Please select a conversation first');
                return;
            }

            const uploadingIndicator = document.getElementById('uploadingIndicator');
            uploadingIndicator.classList.remove('hidden');

            const fd = new FormData();
            fd.append('action', 'upload_file');
            fd.append('receiver_id', currentReceiverId);
            fd.append('file', file);

            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    uploadingIndicator.classList.add('hidden');

                    if (data.success) {
                        // Clear file preview and selected file
                        removeSelectedFile();
                        document.getElementById('fileInput').value = '';

                        // If there's a caption, send it as a separate message
                        if (caption.trim()) {
                            sendMessage(caption);
                        }

                        loadConversations();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(e => {
                    console.error(e);
                    uploadingIndicator.classList.add('hidden');
                    alert('Upload failed');
                });
        }

        function markAsRead(senderId) {
            const fd = new FormData();
            fd.append('action', 'mark_as_read');
            fd.append('receiver_id', senderId);
            fetch('', {
                method: 'POST',
                body: fd
            }).catch(e => console.error(e));
        }

        function formatTimeAgo(ts) {
            if (!ts) return '';
            const now = new Date(),
                then = new Date(ts);
            const diff = now - then;
            const mins = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (mins < 1) return 'Just now';
            if (mins < 60) return `${mins}m ago`;
            if (hours < 24) return `${hours}h ago`;
            if (days === 1) return 'Yesterday';
            if (days < 7) return `${days}d ago`;
            return then.toLocaleDateString();
        }

        function formatDate(date) {
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);

            if (date.toDateString() === today.toDateString()) return 'Today';
            if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function formatTime(date) {
            return date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>