<?php

/**
 * Manage Conversations API
 * List, create, update, delete conversations
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

$action = isset($input['action']) ? $input['action'] : 'list';

try {
    require_once __DIR__ . '/../dbconnect.php';
    require_once __DIR__ . '/chat-history.php';

    $chatHistory = new ChatHistory($conn);
    $userId = $_SESSION['user_id'];

    switch ($action) {
        case 'list':
            // Get all conversations for user
            $conversations = $chatHistory->getUserConversations($userId);

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'conversations' => $conversations
            ]);
            break;

        case 'create':
            // Create new conversation
            $title = isset($input['title']) ? $input['title'] : 'New Conversation';
            $conversation = $chatHistory->createConversation($userId, $title);

            // Archive current active conversation
            $activeConv = $chatHistory->getActiveConversation($userId);
            if ($activeConv && $activeConv['conversation_id'] != $conversation['conversation_id']) {
                $chatHistory->archiveConversation($activeConv['conversation_id']);
            }

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'conversation' => $conversation
            ]);
            break;

        case 'update':
            // Update conversation title
            $conversationId = isset($input['conversation_id']) ? intval($input['conversation_id']) : null;
            $title = isset($input['title']) ? $input['title'] : null;

            if (!$conversationId || !$title) {
                throw new Exception('conversation_id and title are required');
            }

            $result = $chatHistory->updateConversationTitle($conversationId, $title);

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Title updated' : 'Failed to update title'
            ]);
            break;

        case 'delete':
            // Delete conversation
            $conversationId = isset($input['conversation_id']) ? intval($input['conversation_id']) : null;

            if (!$conversationId) {
                throw new Exception('conversation_id is required');
            }

            $result = $chatHistory->deleteConversation($conversationId, $userId);

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Conversation deleted' : 'Failed to delete conversation'
            ]);
            break;

        case 'archive':
            // Archive conversation
            $conversationId = isset($input['conversation_id']) ? intval($input['conversation_id']) : null;

            if (!$conversationId) {
                throw new Exception('conversation_id is required');
            }

            $result = $chatHistory->archiveConversation($conversationId);

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Conversation archived' : 'Failed to archive conversation'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
