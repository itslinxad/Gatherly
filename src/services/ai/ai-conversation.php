<?php

/**
 * AI Conversation API - OpenAI Integration
 * Uses OpenAI GPT API for intelligent event planning assistance
 */

// Disable error display to prevent breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
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

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

$message = isset($input['message']) ? trim($input['message']) : '';
$conversationHistory = isset($input['history']) ? $input['history'] : [];

if (empty($message)) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit();
}

try {
    // Load database connection
    require_once __DIR__ . '/../dbconnect.php';

    // Load ChatHistory class
    require_once __DIR__ . '/chat-history.php';

    // Load GitHub Copilot Chatbot class
    require_once __DIR__ . '/OpenAIChatbot.php';

    // Create chatbot instance
    $chatbot = new CopilotChatbot($conn);

    // Create chat history manager
    $chatHistory = new ChatHistory($conn);

    // Get or create conversation ID
    $conversationId = isset($input['conversation_id']) ? intval($input['conversation_id']) : null;

    if (!$conversationId) {
        // Get active conversation or create new one
        $conversation = $chatHistory->getActiveConversation($_SESSION['user_id']);
        $conversationId = $conversation['conversation_id'];
    }

    // Handle greeting request
    if (strtolower(trim($message)) === '__greeting__') {
        $response = $chatbot->getGreeting();

        // Save greeting to history
        $chatHistory->saveMessage($conversationId, 'assistant', $response['response']);
    } else {
        // Load conversation history from database if not provided
        if (empty($conversationHistory)) {
            $conversationHistory = $chatHistory->getConversationHistory($conversationId);
        }

        // Save user message to history
        $chatHistory->saveMessage($conversationId, 'user', $message);

        // Auto-generate title from first user message
        $messages = $chatHistory->getMessages($conversationId, 5);
        $userMessages = array_filter($messages, function ($m) {
            return $m['role'] === 'user';
        });
        if (count($userMessages) === 1) {
            $title = $chatHistory->generateTitle($message);
            $chatHistory->updateConversationTitle($conversationId, $title);
        }

        // Process conversation with AI
        $response = $chatbot->chat($message, $conversationHistory);

        // Save assistant response to history
        $venueIds = isset($response['recommended_venue_ids']) ? $response['recommended_venue_ids'] : null;
        $chatHistory->saveMessage($conversationId, 'assistant', $response['response'], $venueIds);
    }

    // Clear any unwanted output and send JSON
    ob_end_clean();
    header('Content-Type: application/json');

    // Add venue IDs and conversation ID if recommendations were made
    if (isset($response['recommended_venue_ids']) && !empty($response['recommended_venue_ids'])) {
        echo json_encode([
            'success' => $response['success'],
            'response' => $response['response'],
            'venue_ids' => $response['recommended_venue_ids'],
            'conversation_id' => $conversationId
        ]);
    } else {
        echo json_encode([
            'success' => $response['success'],
            'response' => $response['response'],
            'conversation_id' => $conversationId
        ]);
    }
} catch (Exception $e) {
    // Clear any unwanted output
    ob_end_clean();

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'AI service error: ' . $e->getMessage(),
        'response' => 'I apologize, but I encountered an error. Please check your GitHub Copilot configuration in config/openai.php and ensure your GitHub token is valid.',
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ]);
}
