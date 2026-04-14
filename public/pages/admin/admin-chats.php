<?php
session_start();

// Load E2EE helper
require_once __DIR__ . '/../../../src/components/e2ee-dashboard-helper.php';

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../signin.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

// ENCRYPTION CONFIGURATION
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

function decryptMessage($encrypted)
{
    if (empty($encrypted)) return '';

    $decoded = base64_decode($encrypted, true);
    if ($decoded === false) return $encrypted;

    $parts = explode('::', $decoded, 2);
    if (count($parts) === 2) {
        list($encrypted_data, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
        if ($decrypted === false) return $encrypted;
        return $decrypted;
    }

    return $encrypted;
}

function encryptMessage($message)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($message, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

$first_name = $_SESSION['first_name'] ?? 'Admin';
$conn = getDbConnection();

// API HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $user_id = $_SESSION['user_id'];

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
                            CASE 
                                WHEN c.sender_id = :user_id2 THEN u2.first_name
                                ELSE u1.first_name
                            END as other_first_name,
                            CASE 
                                WHEN c.sender_id = :user_id3 THEN u2.last_name
                                ELSE u1.last_name
                            END as other_last_name,
                            CASE 
                                WHEN c.sender_id = :user_id4 THEN u2.role
                                ELSE u1.role
                            END as other_role,
                            MAX(c.timestamp) as last_message_time,
                            (SELECT COUNT(*) FROM chat c2 
                             WHERE c2.receiver_id = :user_id5 
                             AND c2.is_read = 0
                             AND ((c2.sender_id = c.sender_id AND c2.receiver_id = c.receiver_id)
                                  OR (c2.sender_id = c.receiver_id AND c2.receiver_id = c.sender_id))
                            ) as unread_count,
                            (SELECT message_text FROM chat c3 
                             WHERE ((c3.sender_id = c.sender_id AND c3.receiver_id = c.receiver_id)
                                    OR (c3.sender_id = c.receiver_id AND c3.receiver_id = c.sender_id))
                             ORDER BY c3.timestamp DESC LIMIT 1
                            ) as last_message
                        FROM chat c
                        LEFT JOIN users u1 ON c.sender_id = u1.user_id
                        LEFT JOIN users u2 ON c.receiver_id = u2.user_id
                        WHERE c.sender_id = :user_id6 OR c.receiver_id = :user_id7
                        GROUP BY other_user_id
                        ORDER BY last_message_time DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':user_id2' => $user_id,
                    ':user_id3' => $user_id,
                    ':user_id4' => $user_id,
                    ':user_id5' => $user_id,
                    ':user_id6' => $user_id,
                    ':user_id7' => $user_id
                ]);

                $conversations = $stmt->fetchAll();
                foreach ($conversations as &$conv) {
                    $conv['last_message'] = decryptMessage($conv['last_message'] ?? '');
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
                               u.first_name, u.last_name, u.role
                        FROM chat c
                        LEFT JOIN users u ON c.sender_id = u.user_id
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

                // Only decrypt legacy messages server-side; E2EE messages are decrypted client-side
                foreach ($messages as &$msg) {
                    // Set default encryption type if not set
                    if (!isset($msg['encryption_type']) || empty($msg['encryption_type'])) {
                        $msg['encryption_type'] = 'legacy';
                    }

                    // Only decrypt legacy messages on server
                    if ($msg['encryption_type'] === 'legacy' && $msg['message_text']) {
                        $decrypted = decryptMessage($msg['message_text']);
                        $msg['message_text'] = $decrypted !== false ? $decrypted : '[Unable to decrypt]';
                    }
                    // E2EE messages remain encrypted and will be decrypted client-side
                }

                echo json_encode(['success' => true, 'messages' => $messages]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'send_message':
            try {
                $receiver_id = $_POST['receiver_id'] ?? 0;
                $message_text = $_POST['message'] ?? $_POST['message_text'] ?? '';
                $encryption_type = $_POST['encryption_type'] ?? 'legacy';

                if (empty(trim($message_text))) {
                    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                    exit;
                }

                // Handle E2EE vs Legacy encryption
                if ($encryption_type === 'e2ee') {
                    // E2EE: Message is already encrypted client-side
                    $encrypted_session_key = $_POST['encrypted_session_key'] ?? null;
                    $iv = $_POST['iv'] ?? null;
                    $auth_tag = $_POST['auth_tag'] ?? null;
                    $key_version = $_POST['key_version'] ?? 1;

                    $sql = "INSERT INTO chat (sender_id, receiver_id, message_text, encryption_type, encrypted_session_key, iv, auth_tag, key_version, is_file, is_read, timestamp) 
                            VALUES (:sender_id, :receiver_id, :message_text, :encryption_type, :encrypted_session_key, :iv, :auth_tag, :key_version, 0, 0, NOW())";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':sender_id' => $user_id,
                        ':receiver_id' => $receiver_id,
                        ':message_text' => $message_text,
                        ':encryption_type' => 'e2ee',
                        ':encrypted_session_key' => $encrypted_session_key,
                        ':iv' => $iv,
                        ':auth_tag' => $auth_tag,
                        ':key_version' => $key_version,
                    ]);
                } else {
                    // Legacy: Use server-side encryption
                    $encrypted_message = encryptMessage($message_text);

                    $sql = "INSERT INTO chat (sender_id, receiver_id, message_text, encryption_type, is_file, is_read, timestamp) 
                            VALUES (:sender_id, :receiver_id, :message_text, :encryption_type, 0, 0, NOW())";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':sender_id' => $user_id,
                        ':receiver_id' => $receiver_id,
                        ':message_text' => $encrypted_message,
                        ':encryption_type' => 'legacy',
                    ]);
                }

                echo json_encode([
                    'success' => true,
                    'chat_id' => $conn->lastInsertId(),
                    'encryption_type' => $encryption_type,
                ]);
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
                    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
                    exit;
                }

                if ($file['size'] > $max_size) {
                    echo json_encode(['success' => false, 'error' => 'File too large (max 5MB)']);
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
                    $file_url = '../../uploads/chat_files/' . $filename;

                    $sql = "INSERT INTO chat (sender_id, receiver_id, message_text, file_url, is_file, is_read, timestamp) 
                            VALUES (:sender_id, :receiver_id, :message_text, :file_url, 1, 0, NOW())";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':sender_id' => $user_id,
                        ':receiver_id' => $receiver_id,
                        ':message_text' => $file['name'],
                        ':file_url' => $file_url
                    ]);

                    echo json_encode(['success' => true, 'file_url' => $file_url, 'chat_id' => $conn->lastInsertId()]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
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

        case 'get_users':
            try {
                // Get all users except current admin
                $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.role
                        FROM users u
                        WHERE u.user_id != :user_id AND u.role IN ('organizer', 'manager')
                        ORDER BY u.first_name, u.last_name";

                $stmt = $conn->prepare($sql);
                $stmt->execute([':user_id' => $user_id]);
                $users = $stmt->fetchAll();

                echo json_encode(['success' => true, 'users' => $users]);
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
                            CASE 
                                WHEN c.sender_id = :user_id2 THEN u2.first_name
                                ELSE u1.first_name
                            END as other_first_name,
                            CASE 
                                WHEN c.sender_id = :user_id3 THEN u2.last_name
                                ELSE u1.last_name
                            END as other_last_name,
                            CASE 
                                WHEN c.sender_id = :user_id4 THEN u2.role
                                ELSE u1.role
                            END as other_role,
                            MAX(c.timestamp) as last_message_time
                        FROM chat c
                        LEFT JOIN users u1 ON c.sender_id = u1.user_id
                        LEFT JOIN users u2 ON c.receiver_id = u2.user_id
                        WHERE (c.sender_id = :user_id5 OR c.receiver_id = :user_id6)
                        AND (u1.first_name LIKE :search OR u1.last_name LIKE :search2
                             OR u2.first_name LIKE :search3 OR u2.last_name LIKE :search4)
                        GROUP BY other_user_id
                        ORDER BY last_message_time DESC";

                $stmt = $conn->prepare($sql);
                $search_param = "%$search_term%";
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':user_id2' => $user_id,
                    ':user_id3' => $user_id,
                    ':user_id4' => $user_id,
                    ':user_id5' => $user_id,
                    ':user_id6' => $user_id,
                    ':search' => $search_param,
                    ':search2' => $search_param,
                    ':search3' => $search_param,
                    ':search4' => $search_param
                ]);

                $conversations = $stmt->fetchAll();
                echo json_encode(['success' => true, 'conversations' => $conversations]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Gatherly</title>
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
    <!-- E2EE Scripts - using helper functions -->
    <?php renderE2EEScripts(); ?>
</head>

<body class="bg-gray-100 font-['Montserrat'] min-h-screen" data-user-id="<?php echo $user_id; ?>"<?php echo getE2EEDataAttributes(); ?>>
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 h-screen flex">
        <!-- Chat Interface -->
        <div class="flex flex-col h-full flex-1">
            <!-- Header -->
            <div class="p-4 text-white bg-gradient-to-r from-red-600 to-orange-600 border-b border-red-700">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <button id="toggleSidebarMobile" class="lg:hidden w-10 h-10 flex items-center justify-center bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-colors">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="flex items-center justify-center w-10 h-10 bg-white rounded-full">
                            <i class="text-xl text-red-600 fas fa-comments"></i>
                        </div>
                        <div>
                            <h3 id="currentChatTitle" class="text-lg font-bold">Messages</h3>
                            <p id="currentChatSubtitle" class="text-xs opacity-90">Select a conversation to start</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <span id="e2eeStatusBadge" class="px-2 py-1 text-xs font-semibold text-gray-500 bg-white rounded-full">
                            <i class="mr-1 fas fa-lock"></i> <span id="e2eeStatusText">Checking...</span>
                        </span>
                        <span class="px-2 py-1 text-xs font-semibold text-red-700 bg-white rounded-full">
                            <i class="mr-1 fas fa-bolt"></i> Real-time
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="flex flex-1 overflow-hidden">
                <!-- Chat Area -->
                <div class="flex flex-col flex-1">
                    <!-- Chat Messages -->
                    <div id="chatMessages" class="flex-1 p-6 overflow-y-auto bg-gray-50">
                        <div class="flex items-center justify-center h-full text-gray-500">
                            <div class="text-center">
                                <i class="text-6xl text-gray-300 fas fa-comments mb-4"></i>
                                <p class="text-lg font-semibold">Select a conversation to start messaging</p>
                                <p class="text-sm text-gray-400 mt-2">Choose from the conversations list on the right</p>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Input -->
                    <div class="p-6 bg-white border-t border-gray-200">
                        <div id="filePreview" class="hidden mb-3 p-3 bg-gray-100 rounded-lg flex items-center gap-3">
                            <i class="text-2xl text-red-600 fas fa-file"></i>
                            <div class="flex-1">
                                <div id="fileName" class="text-sm font-medium text-gray-900"></div>
                                <div id="fileSize" class="text-xs text-gray-500"></div>
                            </div>
                            <button id="removeFile" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="uploadingIndicator" class="hidden mb-3 p-2 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-2 text-sm text-blue-700">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Uploading file...</span>
                        </div>
                        <div class="flex gap-3">
                            <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf" />
                            <button id="attachFile" class="p-3 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button id="emojiButton" class="p-3 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200">
                                <i class="fas fa-smile"></i>
                            </button>
                            <input type="text" id="messageInput"
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                placeholder="Type your message..." autocomplete="off" />
                            <button id="sendMessageBtn" class="px-6 py-3 font-semibold text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <div id="emojiPicker" class="hidden absolute bottom-20 right-80 w-80 max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-xl p-3 z-50">
                            <div id="emojiGrid" class="grid grid-cols-8 gap-1"></div>
                        </div>
                    </div>
                </div>

                <!-- Conversations Sidebar (Right) -->
                <div id="chatSidebar" class="w-80 bg-white border-l border-gray-200 flex-col flex">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Conversations</h3>
                        <input type="text" id="searchConversationsInput" placeholder="Search conversations..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-2" />
                        <button id="newMessageSidebarBtn"
                            class="w-full px-3 py-2 text-sm font-semibold text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700 cursor-pointer">
                            <i class="fas fa-plus mr-1"></i> New Chat
                        </button>
                        <p id="conversationCount" class="text-xs text-gray-500 mt-2">Loading...</p>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div id="conversationList" class="space-y-2 p-3">
                            <div class="flex items-center justify-center py-12">
                                <i class="fas fa-spinner fa-spin text-4xl text-red-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div id="newMessageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">New Message</h3>
                <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="usersList" class="max-h-96 p-8 overflow-y-auto">
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                    <p>Loading users...</p>
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
            '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒',
            '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯',
            '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄',
            '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕',
            '🤑', '🤠', '😈', '👿', '👹', '👺', '🤡', '💩', '👻', '💀', '☠️', '👽', '👾', '🤖', '🎃', '😺', '😸', '😹',
            '😻', '😼', '😽', '🙀', '😿', '😾'
        ];

        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
            setupEventListeners();
            setupEmojiPicker();
            setInterval(loadConversations, 5000);
        });

        function setupEventListeners() {
            const sendBtn = document.getElementById('sendMessageBtn');
            if (sendBtn) sendBtn.addEventListener('click', sendMessageOrFile);

            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('keypress', e => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessageOrFile();
                    }
                });
            }

            // File attachment handling
            const attachFileBtn = document.getElementById('attachFile');
            if (attachFileBtn) attachFileBtn.addEventListener('click', () => document.getElementById('fileInput').click());
            
            const fileInput = document.getElementById('fileInput');
            if (fileInput) fileInput.addEventListener('change', handleFileSelect);
            
            const removeFileBtn = document.getElementById('removeFile');
            if (removeFileBtn) removeFileBtn.addEventListener('click', removeSelectedFile);

            // New Message Button (sidebar)
            const newMessageSidebarBtn = document.getElementById('newMessageSidebarBtn');
            if (newMessageSidebarBtn) {
                newMessageSidebarBtn.addEventListener('click', openNewMessageModal);
            }

            // Modal close button
            const closeModalBtn = document.getElementById('closeModalBtn');
            if (closeModalBtn) closeModalBtn.addEventListener('click', closeNewMessageModal);
            
            const newMessageModal = document.getElementById('newMessageModal');
            if (newMessageModal) {
                newMessageModal.addEventListener('click', function(e) {
                    if (e.target === this) closeNewMessageModal();
                });
            }

            // Emoji button
            const emojiButton = document.getElementById('emojiButton');
            if (emojiButton) {
                emojiButton.addEventListener('click', toggleEmojiPicker);
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('#emojiButton') && !e.target.closest('#emojiPicker')) {
                        const emojiPicker = document.getElementById('emojiPicker');
                        if (emojiPicker) emojiPicker.classList.add('hidden');
                    }
                });
            }

            // Search functionality - inline search in sidebar
            const searchInput = document.getElementById('searchConversationsInput');
            if (searchInput) searchInput.addEventListener('input', handleInlineSearch);
        }

        function handleInlineSearch(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const conversationItems = document.querySelectorAll('.conversation');

            if (searchTerm.length === 0) {
                // Show all conversations
                conversationItems.forEach(item => {
                    item.style.display = '';
                });
                return;
            }

            conversationItems.forEach(item => {
                const name = item.dataset.name || '';
                const role = item.dataset.role || '';

                if (name.toLowerCase().includes(searchTerm) || role.toLowerCase().includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function setupEmojiPicker() {
            const emojiGrid = document.getElementById('emojiGrid');
            const emojiPicker = document.getElementById('emojiPicker');

            if (!emojiGrid) {
                console.error('Emoji grid not found');
                return;
            }

            commonEmojis.forEach(emoji => {
                const emojiElement = document.createElement('button');
                emojiElement.className = 'p-2 text-2xl hover:bg-gray-100 rounded cursor-pointer transition-colors';
                emojiElement.textContent = emoji;
                emojiElement.type = 'button';
                emojiElement.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
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

        function openNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('hidden');
            loadUsers();
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('hidden');
        }

        function loadUsers() {
            fetch('?action=get_users')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displayUsers(data.users);
                    }
                })
                .catch(err => console.error('Error loading users:', err));
        }

        function displayUsers(users) {
            const list = document.getElementById('usersList');

            if (!users.length) {
                list.innerHTML = `<div class="p-8 text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-3"></i>
                    <p>No users available</p>
                </div>`;
                return;
            }

            const groupedUsers = {
                organizer: [],
                manager: []
            };
            users.forEach(u => {
                if (u.role === 'organizer' || u.role === 'manager') {
                    groupedUsers[u.role].push(u);
                }
            });

            let html = '';

            if (groupedUsers.organizer.length > 0) {
                html += '<div class="px-4 py-2 text-xs font-bold tracking-wide text-purple-700 uppercase bg-purple-50">Organizers</div>';
                groupedUsers.organizer.forEach(u => html += createUserItem(u));
            }

            if (groupedUsers.manager.length > 0) {
                html += '<div class="px-4 py-2 text-xs font-bold tracking-wide text-orange-700 uppercase bg-orange-50">Managers</div>';
                groupedUsers.manager.forEach(u => html += createUserItem(u));
            }

            list.innerHTML = html;
        }

        function createUserItem(user) {
            const initials = (user.first_name[0] + user.last_name[0]).toUpperCase();
            const name = `${user.first_name} ${user.last_name}`;
            const role = user.role.charAt(0).toUpperCase() + user.role.slice(1);

            return `<div class="flex items-center gap-3 p-3 transition border border-gray-200 rounded-lg cursor-pointer hover:bg-red-50 hover:border-red-300" onclick="startConversationWith(${user.user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
        <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-red-600 rounded-full">
            ${initials}
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-gray-900">${escapeHtml(name)}</p>
            <p class="text-xs text-gray-500">${escapeHtml(user.email)} • ${escapeHtml(role)}</p>
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
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displayConversations(data.conversations);
                    }
                })
                .catch(err => console.error('Error loading conversations:', err));
        }

        function displayConversations(conversations) {
            const list = document.getElementById('conversationList');
            const countEl = document.getElementById('conversationCount');

            if (!conversations.length) {
                list.innerHTML = '<div class="p-8 text-center text-gray-400"><i class="fas fa-inbox text-4xl mb-2"></i><p class="text-sm">No conversations yet</p></div>';
                countEl.textContent = '0 conversations';
                return;
            }

            countEl.textContent = `${conversations.length} active chat${conversations.length !== 1 ? 's' : ''}`;

            list.innerHTML = conversations.map(c => {
                const name = `${c.other_first_name} ${c.other_last_name}`;
                const initials = (c.other_first_name[0] + c.other_last_name[0]).toUpperCase();
                const role = c.other_role.charAt(0).toUpperCase() + c.other_role.slice(1);
                const active = currentReceiverId === c.other_user_id ? 'bg-red-50' : '';
                const time = formatTimeAgo(c.last_message_time);
                const badge = c.unread_count > 0 ?
                    `<span class="px-2 py-1 text-xs font-bold text-white bg-red-600 rounded-full">${c.unread_count}</span>` :
                    '';
                const preview = c.last_message ? c.last_message.substring(0, 40) + (c.last_message.length > 40 ? '...' : '') : '';

                return `<div class="flex items-center justify-between p-4 transition border-b border-gray-100 cursor-pointer conversation ${active} hover:bg-red-50" data-name="${escapeHtml(name)}" data-role="${escapeHtml(role)}" onclick="selectConversation(${c.other_user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 text-sm font-bold text-white bg-red-600 rounded-full">
                    ${initials}
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900">${escapeHtml(name)}</p>
                    <p class="text-xs text-gray-600">${role}</p>
                    ${preview ? `<p class="text-xs text-gray-500 truncate max-w-[180px]">${escapeHtml(preview)}</p>` : ''}
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

            // Update E2EE status badge based on recipient's key status
            updateE2EEStatus(userId);

            removeSelectedFile();

            document.querySelectorAll('.conversation').forEach(conv => {
                conv.classList.remove('active', 'bg-red-50');
            });

            document.getElementById('chatMessages').innerHTML = '<div class="flex items-center justify-center h-full"><i class="fas fa-spinner fa-spin text-4xl text-red-600"></i></div>';

            loadInitialMessages(userId);
            markAsRead(userId);

            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            messagePollingInterval = setInterval(() => {
                if (isPollingEnabled && currentReceiverId === userId) {
                    loadNewMessages(userId);
                }
            }, 2000);
        }

        async function loadInitialMessages(receiverId) {
            const url = `?action=get_messages&receiver_id=${receiverId}`;

            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    if (data.messages.length > 0) {
                        // Decrypt E2EE messages client-side (with error handling)
                        let decryptedMessages = data.messages;
                        try {
                            if (typeof GatherlyE2EEChat !== 'undefined' && GatherlyE2EEChat.decryptMessages) {
                                decryptedMessages = await GatherlyE2EEChat.decryptMessages(
                                    data.messages,
                                    receiverId
                                );
                            } else {
                                console.log('[Chat] E2EE Chat not available, showing messages as-is');
                            }
                        } catch (decryptError) {
                            console.error('[Chat] Decryption error:', decryptError);
                            // Show messages without decryption
                            decryptedMessages = data.messages.map(m => ({...m, encryption_type: 'legacy'}));
                        }
                        
                        // Update last message ID
                        lastMessageId = Math.max(...decryptedMessages.map(m => m.chat_id));
                        displayMessages(decryptedMessages);
                    } else {
                        // No messages found - show empty state
                        document.getElementById('chatMessages').innerHTML =
                            '<div class="flex items-center justify-center h-full text-gray-500">No messages yet. Start the conversation!</div>';
                    }
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                document.getElementById('chatMessages').innerHTML =
                    '<div class="flex items-center justify-center h-full text-gray-500">Error loading messages</div>';
            }
        }

        async function loadNewMessages(receiverId) {
            if (lastMessageId === 0) return;

            const url = `?action=get_messages&receiver_id=${receiverId}&last_message_id=${lastMessageId}`;

            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && data.messages.length > 0) {
                    // Decrypt E2EE messages client-side (with error handling)
                    let decryptedMessages = data.messages;
                    try {
                        if (typeof GatherlyE2EEChat !== 'undefined' && GatherlyE2EEChat.decryptMessages) {
                            decryptedMessages = await GatherlyE2EEChat.decryptMessages(
                                data.messages,
                                receiverId
                            );
                        }
                    } catch (decryptError) {
                        console.error('[Chat] Decryption error:', decryptError);
                        decryptedMessages = data.messages.map(m => ({...m, encryption_type: 'legacy'}));
                    }
                    
                    // Update last message ID
                    lastMessageId = Math.max(...decryptedMessages.map(m => m.chat_id));
                    displayNewMessages(decryptedMessages);
                }
            } catch (error) {
                console.error('Error loading new messages:', error);
            }
        }

        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            const currentUserId = parseInt(document.body.dataset.userId);

            if (!messages.length) {
                chatMessages.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><div class="text-center"><i class="text-6xl text-gray-300 fas fa-comments mb-4"></i><p class="text-lg font-semibold">No messages yet. Start the conversation!</p></div></div>';
                return;
            }

            let lastDate = null,
                html = '';

            messages.forEach(msg => {
                const msgDate = new Date(msg.timestamp);
                const dateStr = formatDate(msgDate);

                if (dateStr !== lastDate) {
                    lastDate = dateStr;
                    html += `<div class="flex justify-center my-4"><div class="px-4 py-1 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-full shadow-sm">${dateStr}</div></div>`;
                }

                const isSent = msg.sender_id === currentUserId;
                const alignClass = isSent ? 'justify-end' : 'justify-start';
                const bgClass = isSent ? 'bg-red-600 text-white' : 'bg-white border border-gray-200';
                const timeColor = isSent ? 'text-red-100' : 'text-gray-500';

                if (msg.is_file) {
                    const isImg = /\.(jpg|jpeg|png|gif)$/i.test(msg.file_url);
                    html += `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    ${isImg 
                        ? `<div class="${isSent ? 'bg-red-600' : 'bg-white border border-gray-200'} rounded-lg p-2 shadow">
                            <img src="../../${msg.file_url}" class="max-w-full rounded cursor-pointer" onclick="window.open('../../${msg.file_url}', '_blank')" />
                           </div>`
                        : `<a href="../../${msg.file_url}" target="_blank" class="${isSent ? 'bg-red-600 text-white' : 'bg-white text-gray-700 border border-gray-200'} flex items-center gap-2 p-3 rounded-lg shadow hover:shadow-md transition">
                            <i class="${isSent ? 'text-white' : 'text-red-600'} fas fa-file"></i>
                            <span class="text-sm">${escapeHtml(msg.message_text)}</span>
                          </a>`
                    }
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${formatTime(msgDate)}</span>
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
                </div>
            </div>`;
                } else {
                    // Determine encryption icon
                    const isE2EE = msg.encryption_type === 'e2ee';
                    const encryptionIcon = isE2EE 
                        ? '<i class="fas fa-lock text-xs text-green-500 ml-1" title="End-to-end encrypted"></i>' 
                        : '';
                    
                    html += `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    <div class="${isSent ? 'bg-red-600 text-white' : 'bg-white text-gray-800 border border-gray-200'} rounded-lg px-4 py-2 shadow">
                        <p class="text-sm break-words">${escapeHtml(msg.message_text)}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${formatTime(msgDate)}</span>
                        ${encryptionIcon}
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
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
                const msgDate = new Date(msg.timestamp);
                const isSent = msg.sender_id === currentUserId;

                if (msg.is_file && msg.file_url) {
                    const isImg = /\.(jpg|jpeg|png|gif)$/i.test(msg.file_url);
                    messageHtml = `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    ${isImg 
                        ? `<div class="${isSent ? 'bg-red-600' : 'bg-white border border-gray-200'} rounded-lg p-2 shadow">
                            <img src="../../${msg.file_url}" class="max-w-full rounded cursor-pointer" onclick="window.open('../../${msg.file_url}', '_blank')" />
                           </div>`
                        : `<a href="../../${msg.file_url}" target="_blank" class="${isSent ? 'bg-red-600 text-white' : 'bg-white text-gray-700 border border-gray-200'} flex items-center gap-2 p-3 rounded-lg shadow hover:shadow-md transition">
                            <i class="${isSent ? 'text-white' : 'text-red-600'} fas fa-file"></i>
                            <span class="text-sm">${escapeHtml(msg.message_text)}</span>
                          </a>`
                    }
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${formatTime(msgDate)}</span>
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
                </div>
            </div>`;
                } else {
                    const isE2EE = msg.encryption_type === 'e2ee';
                    const encryptionIcon = isE2EE 
                        ? '<i class="fas fa-lock text-xs text-green-500 ml-1" title="End-to-end encrypted"></i>' 
                        : '';
                    
                    messageHtml = `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    <div class="${isSent ? 'bg-red-600 text-white' : 'bg-white text-gray-800 border border-gray-200'} rounded-lg px-4 py-2 shadow">
                        <p class="text-sm break-words">${escapeHtml(msg.message_text)}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${formatTime(msgDate)}</span>
                        ${encryptionIcon}
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
                </div>
            </div>`;
                }

                chatMessages.insertAdjacentHTML('beforeend', messageHtml);
            });

            const isNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 200;
            if (isNearBottom) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            selectedFile = file;

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

        async function sendMessage(messageText) {
            if (!currentReceiverId) return;

            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('receiver_id', currentReceiverId);

            // Check if E2EE is available for this recipient
            if (typeof GatherlyE2EEChat !== 'undefined' && GatherlyE2EEChat.isInitialized) {
                try {
                    const result = await GatherlyE2EEChat.encryptMessage(messageText, currentReceiverId);
                    fd.append('message', result.encryptedMessage);
                    fd.append('encryption_type', 'e2ee');
                    fd.append('encrypted_session_key', result.encryptedSessionKey);
                    fd.append('iv', result.iv);
                    fd.append('auth_tag', result.authTag);
                    fd.append('key_version', result.keyVersion);
                } catch (e2eeError) {
                    console.warn('[Chat] E2EE encryption failed, falling back to legacy:', e2eeError);
                    fd.append('message_text', messageText);
                    fd.append('encryption_type', 'legacy');
                }
            } else {
                // Legacy encryption (fallback or if E2EE not available)
                fd.append('message_text', messageText);
                fd.append('encryption_type', 'legacy');
            }

            try {
                const res = await fetch('', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('messageInput').value = '';
                    loadNewMessages(currentReceiverId);
                    loadConversations();
                }
            } catch (err) {
                console.error('Error sending message:', err);
            }
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
                .then(res => res.json())
                .then(data => {
                    uploadingIndicator.classList.add('hidden');
                    if (data.success) {
                        removeSelectedFile();
                        document.getElementById('messageInput').value = '';
                        loadNewMessages(currentReceiverId);
                        loadConversations();
                    } else {
                        alert('Failed to upload file: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    uploadingIndicator.classList.add('hidden');
                    console.error('Error uploading file:', err);
                    alert('Failed to upload file');
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

        // E2EE Status Functions
        async function updateE2EEStatus(recipientId) {
            const badge = document.getElementById('e2eeStatusBadge');
            const text = document.getElementById('e2eeStatusText');
            
            if (!badge || !text) return;
            
            // Check if user has keys
            const ownKeys = await GatherlyKeyManager.getOwnKeys();
            
            if (!ownKeys.privateKey) {
                badge.className = 'px-2 py-1 text-xs font-semibold text-yellow-700 bg-yellow-100 rounded-full';
                text.textContent = 'No Keys';
                return;
            }
            
            // Check recipient's key status
            try {
                const response = await fetch(`/Gatherly/public/api/e2ee/get-public-key.php?userId=${recipientId}`);
                const data = await response.json();
                
                if (data.success) {
                    badge.className = 'px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full';
                    text.textContent = 'E2EE Active';
                } else {
                    badge.className = 'px-2 py-1 text-xs font-semibold text-orange-700 bg-orange-100 rounded-full';
                    text.textContent = 'Legacy Mode';
                }
            } catch (error) {
                badge.className = 'px-2 py-1 text-xs font-semibold text-gray-500 bg-gray-100 rounded-full';
                text.textContent = 'Unknown';
            }
        }
    </script>
</body>

</html>