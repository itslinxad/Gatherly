<?php

/**
 * Load Chat History API
 * Retrieves conversation messages for display
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();

// Check if user is logged in and is an organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$conversationId = isset($input['conversation_id']) ? intval($input['conversation_id']) : null;

try {
    require_once __DIR__ . '/../dbconnect.php';
    require_once __DIR__ . '/chat-history.php';

    $chatHistory = new ChatHistory($conn);

    if ($conversationId) {
        // Load specific conversation
        $messages = $chatHistory->getMessages($conversationId);
    } else {
        // Load active conversation
        $conversation = $chatHistory->getActiveConversation($_SESSION['user_id']);
        $conversationId = $conversation['conversation_id'];
        $messages = $chatHistory->getMessages($conversationId);
    }

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'messages' => $messages
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load chat history: ' . $e->getMessage()
    ]);
}
