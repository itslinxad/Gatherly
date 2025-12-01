<?php
session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: ../signin.php");
    exit();
}

// Load database configuration
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

function encryptMessage($message)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($message, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptMessage($encrypted)
{
    // Check if the message is empty
    if (empty($encrypted)) {
        return '';
    }

    // Try to decode
    $decoded = base64_decode($encrypted, true);
    if ($decoded === false) {
        // Not base64 encoded, might be plain text
        return $encrypted;
    }

    $parts = explode('::', $decoded, 2);
    if (count($parts) === 2) {
        list($encrypted_data, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);

        // If decryption fails, return the original (might be plain text)
        if ($decrypted === false) {
            return $encrypted;
        }

        return $decrypted;
    }

    // If format doesn't match, return original (might be plain text)
    return $encrypted;
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
</head>

<body class="bg-gray-100 font-['Montserrat'] min-h-screen" data-user-id="<?php echo $user_id; ?>">
    <?php include '../../../src/components/OrganizerSidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 h-screen flex">
        <!-- Chat Interface - Full Screen -->
        <div class="flex flex-col h-full flex-1">
            <div class="flex flex-col h-full bg-white">
                <!-- Chat Header -->
                <div class="p-4 text-white bg-gradient-to-r from-indigo-600 to-purple-600 border-b border-indigo-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <button id="toggleSidebarMobile"
                                class="lg:hidden w-10 h-10 flex items-center justify-center bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-colors">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div class="flex items-center justify-center w-10 h-10 bg-white rounded-full">
                                <i class="text-xl text-indigo-600 fas fa-comments"></i>
                            </div>
                            <div>
                                <h3 id="currentChatTitle" class="text-lg font-bold">Messages</h3>
                                <p id="currentChatSubtitle" class="text-xs opacity-90">Select a conversation to start
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button id="searchBtn"
                                class="px-3 py-1.5 text-sm font-semibold text-indigo-700 transition-colors bg-white rounded-lg hover:bg-gray-100">
                                <i class="fas fa-search"></i>
                            </button>
                            <button id="newMessageBtn"
                                class="px-3 py-1.5 text-sm font-semibold text-indigo-700 transition-colors bg-white rounded-lg hover:bg-gray-100">
                                <i class="fas fa-plus mr-1"></i>
                                New Chat
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-white rounded-full">
                            <i class="mr-1 fas fa-check-circle"></i> Encrypted
                        </span>
                        <span class="px-2 py-1 text-xs font-semibold text-indigo-700 bg-white rounded-full">
                            <i class="mr-1 fas fa-bolt"></i> Real-time
                        </span>
                    </div>
                </div>

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
                        <i class="text-2xl text-indigo-600 fas fa-file"></i>
                        <div class="flex-1">
                            <div id="fileName" class="text-sm font-medium text-gray-900"></div>
                            <div id="fileSize" class="text-xs text-gray-500"></div>
                        </div>
                        <button id="removeFile" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="uploadingIndicator"
                        class="hidden mb-3 p-2 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-2 text-sm text-blue-700">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Uploading file...</span>
                    </div>
                    <div class="flex gap-3">
                        <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf" />
                        <button id="attachFile"
                            class="p-3 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button id="emojiButton"
                            class="p-3 text-gray-600 transition-colors bg-gray-100 rounded-lg hover:bg-gray-200">
                            <i class="fas fa-smile"></i>
                        </button>
                        <input type="text" id="messageInput"
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Type your message..." autocomplete="off" />
                        <button id="sendMessageBtn"
                            class="px-6 py-3 font-semibold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div id="emojiPicker"
                        class="hidden absolute bottom-20 right-80 w-80 max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-xl p-3 z-50">
                        <div id="emojiGrid" class="grid grid-cols-8 gap-1"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversations Sidebar (Right) -->
        <div id="chatSidebar" class="w-80 bg-white border-l border-gray-200 flex-col flex">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Conversations</h3>
                <p id="conversationCount" class="text-xs text-gray-500">Loading...</p>
            </div>

            <!-- Conversations List -->
            <div class="flex-1 overflow-y-auto p-3">
                <div id="conversationList" class="space-y-2">
                    <!-- Conversations will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Search Modal -->
    <div id="searchModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-4 border-b border-gray-200">
                <input type="text" id="searchInput" placeholder="Search conversations..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
            </div>
            <div id="searchResults" class="max-h-96 overflow-y-auto">
                <div class="p-4 text-sm text-center text-gray-500">Type to search conversations</div>
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
            <div id="managersList" class="max-h-96 overflow-y-auto">
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <p class="text-sm">Loading...</p>
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
                    const sendBtn = document.getElementById('sendMessageBtn');
                    const chatMessages = document.getElementById('chatMessages');

                    if (!data.has_events) {
                        newMessageBtn.disabled = true;
                        newMessageBtn.title = 'Create an event first to chat with managers';

                        if (messageInput) messageInput.disabled = true;
                        if (fileInput) fileInput.disabled = true;
                        if (sendBtn) sendBtn.disabled = true;

                        // Show disabled state message in chat messages area
                        if (chatMessages) {
                            chatMessages.innerHTML = `
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

            // Profile dropdown is handled by OrganizerSidebar.php

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
            document.getElementById('searchModal').classList.add('hidden');
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
                list.innerHTML =
                    '<div class="p-4 text-center text-gray-500">No venue managers found for your events.</div>';
                return;
            }

            let html =
                '<div class="px-4 py-2 text-xs font-bold tracking-wide text-gray-500 uppercase bg-gray-50">Your Venue Managers</div>';
            managers.forEach(m => html += createManagerItem(m));
            list.innerHTML = html;
        }

        function createManagerItem(manager) {
            const name = `${manager.first_name} ${manager.last_name}`;
            const role = manager.role.charAt(0).toUpperCase() + manager.role.slice(1);
            const initials = `${manager.first_name[0]}${manager.last_name[0]}`.toUpperCase();
            const eventStatus = manager.event_status === 'confirmed' ? 'Confirmed' : 'Pending';
            const statusColor = manager.event_status === 'confirmed' ? 'bg-green-100 text-green-700' :
                'bg-yellow-100 text-yellow-700';

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

            // Show loading state in chat messages
            document.getElementById('chatMessages').innerHTML =
                '<div class="flex items-center justify-center h-full text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading messages...</div>';

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
                const time = formatTime(d);

                if (msg.is_file && msg.file_url) {
                    const isImg = /\.(jpg|jpeg|png|gif)$/i.test(msg.file_url);
                    html += `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    ${isImg 
                        ? `<div class="${isSent ? 'bg-indigo-600' : 'bg-white border border-gray-200'} rounded-lg p-2 shadow">
                            <img src="../../${msg.file_url}" class="max-w-full rounded cursor-pointer" onclick="window.open('../../${msg.file_url}', '_blank')" />
                           </div>`
                        : `<a href="../../${msg.file_url}" target="_blank" class="${isSent ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200'} flex items-center gap-2 p-3 rounded-lg shadow hover:shadow-md transition">
                            <i class="${isSent ? 'text-white' : 'text-indigo-600'} fas fa-file"></i>
                            <span class="text-sm">${escapeHtml(msg.message_text)}</span>
                          </a>`
                    }
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${time}</span>
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
                </div>
            </div>`;
                } else {
                    html += `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    <div class="${isSent ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border border-gray-200'} rounded-lg px-4 py-2 shadow">
                        <p class="text-sm break-words">${escapeHtml(msg.message_text)}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${time}</span>
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
                const d = new Date(msg.timestamp);
                const time = formatTime(d);
                const isSent = msg.sender_id === currentUserId;

                let messageHtml = '';

                if (msg.is_file && msg.file_url) {
                    const isImg = /\.(jpg|jpeg|png|gif)$/i.test(msg.file_url);
                    messageHtml = `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    ${isImg 
                        ? `<div class="${isSent ? 'bg-indigo-600' : 'bg-white border border-gray-200'} rounded-lg p-2 shadow">
                            <img src="../../${msg.file_url}" class="max-w-full rounded cursor-pointer" onclick="window.open('../../${msg.file_url}', '_blank')" />
                           </div>`
                        : `<a href="../../${msg.file_url}" target="_blank" class="${isSent ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200'} flex items-center gap-2 p-3 rounded-lg shadow hover:shadow-md transition">
                            <i class="${isSent ? 'text-white' : 'text-indigo-600'} fas fa-file"></i>
                            <span class="text-sm">${escapeHtml(msg.message_text)}</span>
                          </a>`
                    }
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${time}</span>
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
                </div>
            </div>`;
                } else {
                    messageHtml = `<div class="flex ${isSent ? 'justify-end' : 'justify-start'} mb-4">
                <div class="max-w-xs lg:max-w-md">
                    <div class="${isSent ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border border-gray-200'} rounded-lg px-4 py-2 shadow">
                        <p class="text-sm break-words">${escapeHtml(msg.message_text)}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-1 px-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span class="text-xs text-gray-500">${time}</span>
                        ${isSent ? `<i class="fas fa-check-double text-xs ${msg.is_read ? 'text-blue-500' : 'text-gray-400'}"></i>` : ''}
                    </div>
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