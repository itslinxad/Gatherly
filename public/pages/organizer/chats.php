<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
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
                // Get managers from organizer's events (venue managers they're working with)
                $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.role,
                        e.event_name, e.status as event_status, v.venue_name
                        FROM events e
                        INNER JOIN users u ON e.manager_id = u.user_id
                        INNER JOIN venues v ON e.venue_id = v.venue_id
                        WHERE e.organizer_id = :user_id 
                        AND u.status = 'active'
                        AND e.status IN ('pending', 'confirmed')
                        ORDER BY e.created_at DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute([':user_id' => $user_id]);
                $managers = $stmt->fetchAll();

                echo json_encode(['success' => true, 'managers' => $managers, 'has_events' => count($managers) > 0]);
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

$first_name = $_SESSION['first_name'] ?? 'Organizer';
$user_id = $_SESSION['user_id'];
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .message {
            display: flex;
            flex-direction: column;
            max-width: 70%;
            animation: messageSlide 0.3s ease-out;
        }

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

        .message.received {
            align-self: flex-start;
        }

        .message.sent {
            align-self: flex-end;
            text-align: right;
        }

        .message p {
            background: #f3f4f6;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            color: #1f2937;
            line-height: 1.5;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            word-wrap: break-word;
        }

        .message.received p {
            border-bottom-left-radius: 4px;
        }

        .message.sent p {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }

        .timestamp {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 500;
        }

        .chat-image {
            max-width: 250px;
            max-height: 250px;
            border-radius: 12px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .chat-image:hover {
            transform: scale(1.02);
        }

        .conversation.active {
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            border-left: 3px solid #6366f1;
        }

        #chatMessages::-webkit-scrollbar {
            width: 6px;
        }

        #chatMessages::-webkit-scrollbar-track {
            background: #f3f4f6;
        }

        #chatMessages::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        #chatMessages::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .emoji-picker {
            position: absolute;
            bottom: 60px;
            right: 80px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            width: 300px;
            max-height: 250px;
            overflow-y: auto;
            display: none;
        }

        .emoji-picker.show {
            display: block;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 5px;
        }

        .emoji {
            padding: 8px;
            cursor: pointer;
            border-radius: 5px;
            text-align: center;
            font-size: 20px;
            transition: all 0.2s;
            user-select: none;
        }

        .emoji:hover {
            background-color: #eef2ff;
            transform: scale(1.2);
        }

        .emoji-picker::-webkit-scrollbar {
            width: 6px;
        }

        .emoji-picker::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 3px;
        }

        .emoji-picker::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        .emoji-picker::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .search-modal-container {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            width: 400px;
            max-width: 90vw;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }

        .search-modal-container.show {
            display: block;
        }

        .search-results {
            max-height: 300px;
            overflow-y: auto;
        }

        .search-result-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .search-result-item:hover {
            background-color: #f9fafb;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .message-status {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .message.sent .message-status {
            justify-content: flex-end;
        }

        .status-icon {
            font-size: 12px;
        }

        .status-read {
            color: #10b981;
        }

        .status-unread {
            color: #9ca3af;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 250px);
            min-height: 500px;
        }

        .chat-area {
            display: flex;
            flex-direction: column;
            flex: 1;
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

        .file-preview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            display: none;
        }

        .file-preview.show {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-preview-info {
            flex: 1;
        }

        .file-preview-name {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }

        .file-preview-size {
            font-size: 12px;
            color: #64748b;
        }

        .file-preview-remove {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .file-preview-remove:hover {
            background-color: #fecaca;
        }

        .file-icon {
            font-size: 24px;
            color: #6366f1;
        }

        .uploading-indicator {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 8px 12px;
            margin: 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #0369a1;
        }

        .uploading-indicator.hidden {
            display: none;
        }

        .messages-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
    </style>
</head>

<body
    class="<?php echo $nav_layout === 'sidebar' ? 'bg-gray-100' : 'bg-linear-to-br from-indigo-50 via-white to-purple-50'; ?> font-['Montserrat'] min-h-screen"
    data-user-id="<?php echo $user_id; ?>">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div
        class="<?php echo $nav_layout === 'sidebar' ? 'lg:ml-64' : 'container mx-auto'; ?> <?php echo $nav_layout === 'sidebar' ? '' : 'px-4 sm:px-6 lg:px-8'; ?> min-h-screen">
        <?php if ($nav_layout === 'sidebar'): ?>
            <!-- Top Bar for Sidebar Layout -->
            <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 lg:px-8 py-4 mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
                <p class="text-sm text-gray-600">Connect with venue managers and other organizers</p>
            </div>
            <div class="px-4 sm:px-6 lg:px-8">
            <?php else: ?>
                <!-- Header for Navbar Layout -->
                <div class="mb-8">
                    <h1 class="mb-2 text-3xl font-bold text-gray-800 sm:text-4xl">Messages</h1>
                    <p class="text-gray-600">Connect with venue managers and other organizers</p>
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
                    </div>
                    <div class="flex gap-3">
                        <div class="relative">
                            <button id="searchBtn"
                                class="flex items-center gap-2 px-4 py-2 text-gray-700 transition-colors bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50">
                                <i class="fas fa-search"></i>
                                <span class="hidden sm:inline">Search</span>
                            </button>
                            <div id="searchModal" class="search-modal-container">
                                <div class="p-3 border-b border-gray-200">
                                    <input type="text" id="searchInput" placeholder="Search conversations..."
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div id="searchResults" class="search-results">
                                    <div class="p-4 text-sm text-center text-gray-500">Type to search conversations
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button id="newMessageBtn" onclick="openNewMessageModal()"
                            class="flex items-center gap-2 px-4 py-2 text-white transition-colors bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
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
                    <div class="p-5 border-b border-gray-200 shrink-0 bg-linear-to-r from-indigo-50 to-purple-50">
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
                <div id="chatArea"
                    class="flex-1 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl chat-container">

                    <!-- Chat Header -->
                    <div id="chatHeader"
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-linear-to-r from-indigo-50 to-purple-50">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div
                                    class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-indigo-600 rounded-full">
                                    <i class="fas fa-comments"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-gray-900">Select a conversation</h4>
                                <span class="text-sm text-gray-500">Choose a chat from the left to start
                                    messaging</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-indigo-600">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button
                                class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-indigo-600">
                                <i class="fas fa-video"></i>
                            </button>
                            <button
                                class="p-2 text-gray-600 transition-colors rounded-lg hover:bg-white hover:text-indigo-600">
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
                            <div id="filePreview" class="file-preview">
                                <i class="file-icon fas fa-file"></i>
                                <div class="file-preview-info">
                                    <div id="fileName" class="file-preview-name"></div>
                                    <div id="fileSize" class="file-preview-size"></div>
                                </div>
                                <button id="removeFile" class="file-preview-remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <!-- Uploading Indicator -->
                            <div id="uploadingIndicator" class="uploading-indicator hidden">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Uploading file...</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <button id="attachFile"
                                    class="p-2 text-lg text-gray-600 transition-colors rounded-lg hover:bg-gray-100 hover:text-indigo-600">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf" />

                                <!-- Emoji Picker -->
                                <div id="emojiPicker" class="emoji-picker">
                                    <div class="emoji-grid">
                                        <!-- Emojis by JavaScript -->
                                    </div>
                                </div>

                                <div
                                    class="flex-1 flex items-center gap-2 px-4 py-2.5 bg-gray-100 rounded-lg border border-gray-200 focus-within:border-indigo-600 focus-within:ring-2 focus-within:ring-indigo-200">
                                    <input type="text" id="messageInput" placeholder="Type your message..."
                                        class="flex-1 text-sm text-gray-900 placeholder-gray-500 bg-transparent border-none outline-none" />
                                    <button id="emojiButton"
                                        class="p-1 text-gray-500 transition-colors hover:text-indigo-600">
                                        <i class="text-lg far fa-smile"></i>
                                    </button>
                                </div>
                                <button id="sendMessageBtn"
                                    class="flex items-center justify-center w-10 h-10 text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- New Message Modal -->
            <div id="newMessageModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl">
                        <div
                            class="flex items-center justify-between p-5 border-b border-gray-200 bg-linear-to-r from-indigo-50 to-purple-50">
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

                const commonEmojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍',
                    '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏',
                    '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡',
                    '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶', '😐',
                    '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮',
                    '🤧', '😷', '🤒', '🤕', '🤑', '🤠', '😈', '👿', '👹', '👺', '🤡', '💩', '👻', '💀', '☠️', '👽', '👾',
                    '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'
                ];

                document.addEventListener('DOMContentLoaded', function() {
                    loadConversations();
                    setupEventListeners();
                    setupEmojiPicker();
                    checkEventsAvailability();
                    setInterval(loadConversations, 5000);
                });

                function checkEventsAvailability() {
                    fetch('?action=get_managers')
                        .then(res => res.json())
                        .then(data => {
                            const newMessageBtn = document.getElementById('newMessageBtn');
                            const messageInput = document.getElementById('messageInput');
                            const fileInput = document.getElementById('fileInput');
                            const sendBtn = document.querySelector('button[onclick="sendMessageOrFile()"]');

                            if (!data.has_events) {
                                newMessageBtn.disabled = true;
                                newMessageBtn.title = 'Create an event first to chat with managers';

                                if (messageInput) messageInput.disabled = true;
                                if (fileInput) fileInput.disabled = true;
                                if (sendBtn) sendBtn.disabled = true;

                                // Show disabled state message in chat area
                                document.getElementById('chatArea').innerHTML = `
                                    <div class="flex flex-col items-center justify-center h-full p-8 text-center bg-gray-50">
                                        <div class="flex items-center justify-center w-20 h-20 mb-4 text-gray-400 bg-white rounded-full shadow-sm">
                                            <i class="text-3xl fas fa-comments-dollar"></i>
                                        </div>
                                        <h3 class="mb-2 text-xl font-bold text-gray-800">Chat with Venue Managers</h3>
                                        <p class="max-w-md mb-6 text-gray-600">Create an event to start communicating with venue managers about your bookings.</p>
                                        <a href="create-event.php" class="inline-flex items-center px-6 py-3 text-sm font-semibold text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg">
                                            <i class="mr-2 fas fa-calendar-plus"></i>Create Your First Event
                                        </a>
                                    </div>`;
                            }
                        })
                        .catch(err => console.error('Error checking events:', err));
                }

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
                    document.getElementById('attachFile').addEventListener('click', () => document.getElementById('fileInput')
                        .click());
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
                            document.getElementById('searchModal').classList.remove('show');
                        }
                    });

                    // Emoji button
                    document.getElementById('emojiButton').addEventListener('click', toggleEmojiPicker);
                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('#emojiButton') && !e.target.closest('#emojiPicker')) {
                            document.getElementById('emojiPicker').classList.remove('show');
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
                            emojiPicker.classList.remove('show');
                        });
                        emojiGrid.appendChild(emojiElement);
                    });
                }

                function toggleEmojiPicker() {
                    document.getElementById('emojiPicker').classList.toggle('show');
                }

                function toggleSearchModal() {
                    document.getElementById('searchModal').classList.toggle('show');
                    if (document.getElementById('searchModal').classList.contains('show')) {
                        document.getElementById('searchInput').focus();
                    }
                }

                function handleSearch(e) {
                    const searchTerm = e.target.value.trim();
                    const resultsContainer = document.getElementById('searchResults');

                    if (searchTerm.length === 0) {
                        resultsContainer.innerHTML =
                            '<div class="p-4 text-sm text-center text-gray-500">Type to search conversations</div>';
                        return;
                    }

                    if (searchTerm.length < 2) {
                        resultsContainer.innerHTML =
                            '<div class="p-4 text-sm text-center text-gray-500">Type at least 2 characters to search</div>';
                        return;
                    }

                    resultsContainer.innerHTML =
                        '<div class="p-4 text-sm text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';

                    fetch(`?action=search_conversations&search_term=${encodeURIComponent(searchTerm)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                displaySearchResults(data.conversations);
                            }
                        })
                        .catch(e => {
                            console.error('Error:', e);
                            resultsContainer.innerHTML =
                                '<div class="p-4 text-sm text-center text-red-500">Search failed. Please try again.</div>';
                        });
                }

                function displaySearchResults(conversations) {
                    const resultsContainer = document.getElementById('searchResults');

                    if (!conversations.length) {
                        resultsContainer.innerHTML =
                            '<div class="p-4 text-sm text-center text-gray-500">No conversations found</div>';
                        return;
                    }

                    resultsContainer.innerHTML = conversations.map(c => {
                        const initials = (c.first_name[0] + c.last_name[0]).toUpperCase();
                        const name = `${c.first_name} ${c.last_name}`;
                        const role = c.role.charAt(0).toUpperCase() + c.role.slice(1);

                        return `<div class="search-result-item" onclick="selectConversationFromSearch(${c.other_user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 text-sm font-bold text-white bg-indigo-600 rounded-full">
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
                    document.getElementById('searchModal').classList.remove('show');
                    document.getElementById('searchInput').value = '';
                    selectConversation(userId, userName, userRole, initials);
                }

                function openNewMessageModal() {
                    // Check if button is disabled
                    const btn = document.getElementById('newMessageBtn');
                    if (btn.disabled) {
                        alert('You need to create an event first to chat with venue managers.');
                        return;
                    }
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
                            if (data.success) displayManagers(data.managers, data.has_events);
                        })
                        .catch(e => console.error('Error:', e));
                }

                function displayManagers(managers, hasEvents) {
                    const list = document.getElementById('managersList');

                    if (!hasEvents) {
                        list.innerHTML = `
                            <div class="flex flex-col items-center justify-center p-8 text-center">
                                <div class="flex items-center justify-center w-16 h-16 mb-4 text-gray-400 bg-gray-100 rounded-full">
                                    <i class="text-2xl fas fa-calendar-xmark"></i>
                                </div>
                                <h3 class="mb-2 text-lg font-bold text-gray-800">No Events Yet</h3>
                                <p class="mb-4 text-sm text-gray-600">You need to create an event first to chat with venue managers.</p>
                                <a href="create-event.php" class="px-4 py-2 text-sm font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                    <i class="mr-2 fas fa-plus"></i>Create Event
                                </a>
                            </div>`;
                        return;
                    }

                    if (!managers.length) {
                        list.innerHTML = '<div class="p-4 text-center text-gray-500">No venue managers found for your events.</div>';
                        return;
                    }

                    let html = '<div class="px-4 py-2 text-xs font-bold tracking-wide text-gray-500 uppercase bg-gray-50">Your Venue Managers</div>';
                    managers.forEach(m => html += createManagerItem(m));
                    list.innerHTML = html;
                }

                function createManagerItem(manager) {
                    const name = `${manager.first_name} ${manager.last_name}`;
                    const role = manager.role.charAt(0).toUpperCase() + manager.role.slice(1);
                    const initials = `${manager.first_name[0]}${manager.last_name[0]}`.toUpperCase();
                    const eventStatus = manager.event_status === 'confirmed' ? 'Confirmed' : 'Pending';
                    const statusColor = manager.event_status === 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';

                    return `<div class="flex items-center gap-3 p-3 transition border border-gray-200 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-300" onclick="startConversationWith(${manager.user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
        <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-indigo-600 rounded-full">
            ${initials}
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-gray-900">${escapeHtml(name)}</p>
            <p class="text-xs text-gray-600 truncate">${escapeHtml(manager.venue_name)}</p>
            <div class="flex items-center gap-2 mt-1">
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${statusColor}">${eventStatus}</span>
                <span class="text-xs text-gray-500 truncate">${escapeHtml(manager.event_name)}</span>
            </div>
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
                        const badge = c.unread_count > 0 ?
                            `<span class="badge bg-indigo-600 text-white text-xs rounded-full px-2 py-0.5 font-semibold">${c.unread_count}</span>` :
                            '';
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

                    document.getElementById('chatMessages').innerHTML =
                        '<div class="flex items-center justify-center h-full text-gray-500">Loading messages...</div>';

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
                                    document.getElementById('chatMessages').innerHTML =
                                        '<div class="flex items-center justify-center h-full text-gray-500">No messages yet. Start the conversation!</div>';
                                }
                            }
                        })
                        .catch(e => {
                            console.error('Error:', e);
                            document.getElementById('chatMessages').innerHTML =
                                '<div class="flex items-center justify-center h-full text-gray-500">Error loading messages</div>';
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
                        chatMessages.innerHTML =
                            '<div class="flex items-center justify-center h-full text-gray-500">No messages yet. Start the conversation!</div>';
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
                            html += `<div class="message ${cls}">
                ${isImg 
                    ? `<img src="../../${msg.file_url}" class="chat-image" onclick="window.open('../../${msg.file_url}', '_blank')" />`
                    : `<a href="../../${msg.file_url}" target="_blank" class="flex items-center gap-2 p-3 bg-white rounded-lg shadow">
                        <i class="text-indigo-600 fas fa-file"></i>
                        <span class="text-sm text-gray-700">${escapeHtml(msg.message_text)}</span>
                      </a>`
                }
                <div class="message-status">
                    <span class="timestamp">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double status-icon ${msg.is_read ? 'status-read' : 'status-unread'}"></i>` : ''}
                </div>
            </div>`;
                        } else {
                            html += `<div class="message ${cls}">
                <p>${escapeHtml(msg.message_text)}</p>
                <div class="message-status">
                    <span class="timestamp">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double status-icon ${msg.is_read ? 'status-read' : 'status-unread'}"></i>` : ''}
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
                            messageHtml = `<div class="message ${cls}">
                ${isImg 
                    ? `<img src="../../${msg.file_url}" class="chat-image" onclick="window.open('../../${msg.file_url}', '_blank')" />`
                    : `<a href="../../${msg.file_url}" target="_blank" class="flex items-center gap-2 p-3 bg-white rounded-lg shadow">
                        <i class="text-indigo-600 fas fa-file"></i>
                        <span class="text-sm text-gray-700">${escapeHtml(msg.message_text)}</span>
                      </a>`
                }
                <div class="message-status">
                    <span class="timestamp">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double status-icon ${msg.is_read ? 'status-read' : 'status-unread'}"></i>` : ''}
                </div>
            </div>`;
                        } else {
                            messageHtml = `<div class="message ${cls}">
                <p>${escapeHtml(msg.message_text)}</p>
                <div class="message-status">
                    <span class="timestamp">${time}</span>
                    ${isSent ? `<i class="fas fa-check-double status-icon ${msg.is_read ? 'status-read' : 'status-unread'}"></i>` : ''}
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
                    filePreview.classList.add('show');

                    event.target.value = '';
                }

                function removeSelectedFile() {
                    selectedFile = null;
                    document.getElementById('filePreview').classList.remove('show');
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
            <?php if ($nav_layout === 'sidebar'): ?>
    </div>
<?php endif; ?>
</div>
</body>

</html>