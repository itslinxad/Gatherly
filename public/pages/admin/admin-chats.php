<?php
session_start();

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
                foreach ($messages as &$msg) {
                    if (!$msg['is_file']) {
                        $msg['message_text'] = decryptMessage($msg['message_text'] ?? '');
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
</head>

<body class="bg-gray-100 font-['Montserrat'] min-h-screen" data-user-id="<?php echo $user_id; ?>">
    <?php include '../../../src/components/AdminSidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 h-screen flex">
        <!-- Chat Interface -->
        <div class="flex flex-col h-full flex-1">
            <!-- Header -->
            <div class="p-6 text-white bg-gradient-to-r from-red-600 to-orange-600 border-b border-red-700 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-12 h-12 text-white bg-white/20 rounded-full">
                        <i class="text-xl fas fa-comments"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">Messages</h2>
                        <p class="text-sm text-red-100">Administrator Chat</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button id="searchBtn" class="p-2 transition-colors rounded-lg hover:bg-white/20">
                        <i class="fas fa-search"></i>
                    </button>
                    <button id="newMessageBtn" class="flex items-center gap-2 px-4 py-2 transition-colors bg-white rounded-lg text-red-600 hover:bg-red-50">
                        <i class="fas fa-plus"></i>
                        <span class="hidden sm:inline">New Message</span>
                    </button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="flex flex-1 overflow-hidden">
                <!-- Chat Area -->
                <div class="flex flex-col flex-1">
                    <!-- Chat Messages -->
                    <div id="chatMessages" class="flex-1 p-6 overflow-y-auto bg-gray-50">
                        <div class="flex flex-col items-center justify-center h-full text-gray-400">
                            <i class="text-6xl fas fa-comments"></i>
                            <p class="mt-4 text-lg font-semibold">No conversation selected</p>
                            <p class="text-sm">Choose a conversation from the list to view messages</p>
                        </div>
                    </div>

                    <!-- File Preview (hidden by default) -->
                    <div id="filePreview" class="hidden px-6 py-3 bg-yellow-50 border-t border-yellow-200">
                        <div class="flex items-center gap-3">
                            <i class="text-xl text-yellow-600 fas fa-paperclip"></i>
                            <div class="flex-1">
                                <p id="fileName" class="text-sm font-semibold text-gray-900"></p>
                                <p id="fileSize" class="text-xs text-gray-600"></p>
                            </div>
                            <button id="removeFile" class="text-red-600 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Uploading Indicator -->
                    <div id="uploadingIndicator" class="hidden px-6 py-3 bg-blue-50 border-t border-blue-200">
                        <div class="flex items-center gap-3">
                            <i class="text-blue-600 fas fa-spinner fa-spin"></i>
                            <p class="text-sm font-semibold text-blue-900">Uploading file...</p>
                        </div>
                    </div>

                    <!-- Chat Input -->
                    <div class="p-6 bg-white border-t border-gray-200">
                        <div class="flex items-end gap-3">
                            <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf">
                            <button id="attachFile" class="p-3 text-gray-600 transition-colors rounded-lg hover:bg-gray-100">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button id="emojiButton" class="relative p-3 text-gray-600 transition-colors rounded-lg hover:bg-gray-100">
                                <i class="fas fa-smile"></i>
                                <div id="emojiPicker" class="absolute bottom-full mb-2 hidden p-2 bg-white rounded-lg shadow-lg border border-gray-200 w-64 max-h-48 overflow-y-auto left-0">
                                    <div id="emojiGrid" class="grid grid-cols-8 gap-1"></div>
                                </div>
                            </button>
                            <div class="relative flex-1">
                                <textarea id="messageInput" rows="1" placeholder="Type your message..."
                                    class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                            </div>
                            <button id="sendMessageBtn"
                                class="p-3 text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Conversations Sidebar (Right) -->
                <div id="chatSidebar" class="w-80 bg-white border-l border-gray-200 flex-col flex">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Conversations</h3>
                        <p id="conversationCount" class="text-xs text-gray-500">Loading...</p>
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

    <!-- Search Modal -->
    <div id="searchModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-4 border-b border-gray-200">
                <input type="text" id="searchInput" placeholder="Search conversations..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
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
            sendBtn.addEventListener('click', sendMessageOrFile);

            const messageInput = document.getElementById('messageInput');
            messageInput.addEventListener('keypress', e => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
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
                resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-gray-500">Type at least 2 characters</div>';
                return;
            }

            resultsContainer.innerHTML = '<div class="p-4 text-center"><i class="fas fa-spinner fa-spin text-red-600"></i></div>';

            fetch(`?action=search_conversations&search_term=${encodeURIComponent(searchTerm)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displaySearchResults(data.conversations);
                    }
                })
                .catch(err => console.error('Search error:', err));
        }

        function displaySearchResults(conversations) {
            const resultsContainer = document.getElementById('searchResults');

            if (!conversations.length) {
                resultsContainer.innerHTML = '<div class="p-4 text-sm text-center text-gray-500">No results found</div>';
                return;
            }

            resultsContainer.innerHTML = conversations.map(c => {
                const name = `${c.other_first_name} ${c.other_last_name}`;
                const role = c.other_role.charAt(0).toUpperCase() + c.other_role.slice(1);
                const initials = (c.other_first_name[0] + c.other_last_name[0]).toUpperCase();

                return `<div class="p-3 border-b border-gray-100 cursor-pointer transition-colors hover:bg-gray-50 last:border-b-0" onclick="selectConversationFromSearch(${c.other_user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-red-600 rounded-full">
                    ${initials}
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900">${escapeHtml(name)}</p>
                    <p class="text-xs text-gray-500">${role}</p>
                </div>
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

                return `<div class="flex items-center justify-between p-4 transition border-b border-gray-100 cursor-pointer conversation ${active} hover:bg-red-50" onclick="selectConversation(${c.other_user_id}, '${escapeHtml(name)}', '${role}', '${initials}')">
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

        function loadInitialMessages(receiverId) {
            const url = `?action=get_messages&receiver_id=${receiverId}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.messages.length > 0) {
                            lastMessageId = data.messages[data.messages.length - 1].chat_id;
                        }
                        displayMessages(data.messages);
                    }
                })
                .catch(err => {
                    console.error('Error loading messages:', err);
                    document.getElementById('chatMessages').innerHTML = '<div class="flex items-center justify-center h-full text-red-500"><i class="fas fa-exclamation-circle text-4xl mb-2"></i><p>Error loading messages</p></div>';
                });
        }

        function loadNewMessages(receiverId) {
            if (lastMessageId === 0) return;

            const url = `?action=get_messages&receiver_id=${receiverId}&last_message_id=${lastMessageId}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        lastMessageId = data.messages[data.messages.length - 1].chat_id;
                        displayNewMessages(data.messages);
                    }
                })
                .catch(err => console.error('Error loading new messages:', err));
        }

        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            const currentUserId = parseInt(document.body.dataset.userId);

            if (!messages.length) {
                chatMessages.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-gray-400"><i class="fas fa-comments text-6xl mb-4"></i><p class="text-lg font-semibold">No messages yet</p><p class="text-sm">Start the conversation!</p></div>';
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
                    const fileIcon = msg.file_url?.endsWith('.pdf') ? 'fa-file-pdf' : 'fa-image';
                    html += `<div class="flex ${alignClass} mb-4"><div class="max-w-[70%]">
                        <div class="p-3 rounded-lg ${bgClass}">
                            <a href="${msg.file_url}" target="_blank" class="flex items-center gap-2 ${isSent ? 'text-white' : 'text-blue-600 hover:underline'}">
                                <i class="fas ${fileIcon}"></i>
                                <span>${escapeHtml(msg.message_text)}</span>
                            </a>
                        </div>
                        <p class="text-xs ${timeColor} mt-1 ${isSent ? 'text-right' : ''}">${formatTime(msgDate)}</p>
                    </div></div>`;
                } else {
                    html += `<div class="flex ${alignClass} mb-4"><div class="max-w-[70%]">
                        <div class="p-3 rounded-lg ${bgClass}">
                            <p class="text-sm whitespace-pre-wrap break-words">${escapeHtml(msg.message_text)}</p>
                        </div>
                        <p class="text-xs ${timeColor} mt-1 ${isSent ? 'text-right' : ''}">${formatTime(msgDate)}</p>
                    </div></div>`;
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
                const alignClass = isSent ? 'justify-end' : 'justify-start';
                const bgClass = isSent ? 'bg-red-600 text-white' : 'bg-white border border-gray-200';
                const timeColor = isSent ? 'text-red-100' : 'text-gray-500';

                let messageHtml;
                if (msg.is_file) {
                    const fileIcon = msg.file_url?.endsWith('.pdf') ? 'fa-file-pdf' : 'fa-image';
                    messageHtml = `<div class="flex ${alignClass} mb-4"><div class="max-w-[70%]">
                        <div class="p-3 rounded-lg ${bgClass}">
                            <a href="${msg.file_url}" target="_blank" class="flex items-center gap-2 ${isSent ? 'text-white' : 'text-blue-600 hover:underline'}">
                                <i class="fas ${fileIcon}"></i>
                                <span>${escapeHtml(msg.message_text)}</span>
                            </a>
                        </div>
                        <p class="text-xs ${timeColor} mt-1 ${isSent ? 'text-right' : ''}">${formatTime(msgDate)}</p>
                    </div></div>`;
                } else {
                    messageHtml = `<div class="flex ${alignClass} mb-4"><div class="max-w-[70%]">
                        <div class="p-3 rounded-lg ${bgClass}">
                            <p class="text-sm whitespace-pre-wrap break-words">${escapeHtml(msg.message_text)}</p>
                        </div>
                        <p class="text-xs ${timeColor} mt-1 ${isSent ? 'text-right' : ''}">${formatTime(msgDate)}</p>
                    </div></div>`;
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
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('messageInput').value = '';
                        loadNewMessages(currentReceiverId);
                        loadConversations();
                    }
                })
                .catch(err => console.error('Error sending message:', err));
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
    </script>
</body>

</html>