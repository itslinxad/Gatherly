<?php

/**
 * AI Chat History Manager
 * Manages conversation storage and retrieval for AI Planner
 */

class ChatHistory
{
    private $conn;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    /**
     * Get or create active conversation for user
     */
    public function getActiveConversation($userId)
    {
        $stmt = $this->conn->prepare("
            SELECT conversation_id, title, 
                   DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+08:00'), '%Y-%m-%dT%H:%i:%s') as created_at,
                   DATE_FORMAT(CONVERT_TZ(updated_at, '+00:00', '+08:00'), '%Y-%m-%dT%H:%i:%s') as updated_at
            FROM ai_conversations 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        // Create new conversation if none exists
        return $this->createConversation($userId);
    }

    /**
     * Create new conversation
     */
    public function createConversation($userId, $title = 'New Conversation')
    {
        $stmt = $this->conn->prepare("
            INSERT INTO ai_conversations (user_id, title) 
            VALUES (?, ?)
        ");
        $stmt->bind_param("is", $userId, $title);

        if ($stmt->execute()) {
            $conversationId = $stmt->insert_id;
            return [
                'conversation_id' => $conversationId,
                'title' => $title,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        return null;
    }

    /**
     * Save message to conversation
     */
    public function saveMessage($conversationId, $role, $content, $venueIds = null)
    {
        $venueIdsJson = null;
        if ($venueIds && is_array($venueIds) && count($venueIds) > 0) {
            $venueIdsJson = json_encode($venueIds);
        }

        $stmt = $this->conn->prepare("
            INSERT INTO ai_messages (conversation_id, role, content, venue_ids) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $conversationId, $role, $content, $venueIdsJson);

        if ($stmt->execute()) {
            // Update conversation updated_at timestamp
            $updateStmt = $this->conn->prepare("
                UPDATE ai_conversations 
                SET updated_at = CURRENT_TIMESTAMP 
                WHERE conversation_id = ?
            ");
            $updateStmt->bind_param("i", $conversationId);
            $updateStmt->execute();

            return [
                'message_id' => $stmt->insert_id,
                'conversation_id' => $conversationId,
                'role' => $role,
                'content' => $content,
                'venue_ids' => $venueIds,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        return null;
    }

    /**
     * Get conversation messages
     */
    public function getMessages($conversationId, $limit = 50)
    {
        $stmt = $this->conn->prepare("
            SELECT message_id, role, content, venue_ids, created_at 
            FROM ai_messages 
            WHERE conversation_id = ? 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $conversationId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'message_id' => $row['message_id'],
                'role' => $row['role'],
                'content' => $row['content'],
                'venue_ids' => $row['venue_ids'] ? json_decode($row['venue_ids'], true) : null,
                'created_at' => $row['created_at']
            ];
        }

        return $messages;
    }

    /**
     * Get conversation history formatted for AI API
     */
    public function getConversationHistory($conversationId, $limit = 20)
    {
        $messages = $this->getMessages($conversationId, $limit);

        // Convert to OpenAI/Gemini format (exclude system messages)
        $history = [];
        foreach ($messages as $msg) {
            if ($msg['role'] !== 'system') {
                $history[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
        }

        return $history;
    }

    /**
     * Get all conversations for user
     */
    public function getUserConversations($userId, $limit = 10)
    {
        $stmt = $this->conn->prepare("
            SELECT c.conversation_id, c.title, 
                   DATE_FORMAT(CONVERT_TZ(c.created_at, '+00:00', '+08:00'), '%Y-%m-%dT%H:%i:%s') as created_at,
                   DATE_FORMAT(CONVERT_TZ(c.updated_at, '+00:00', '+08:00'), '%Y-%m-%dT%H:%i:%s') as updated_at,
                   c.is_active,
                   COUNT(m.message_id) as message_count,
                   (SELECT content FROM ai_messages 
                    WHERE conversation_id = c.conversation_id 
                    AND role = 'user' 
                    ORDER BY created_at ASC 
                    LIMIT 1) as first_message
            FROM ai_conversations c
            LEFT JOIN ai_messages m ON c.conversation_id = m.conversation_id
            WHERE c.user_id = ?
            GROUP BY c.conversation_id
            ORDER BY c.updated_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        return $conversations;
    }

    /**
     * Update conversation title
     */
    public function updateConversationTitle($conversationId, $title)
    {
        $stmt = $this->conn->prepare("
            UPDATE ai_conversations 
            SET title = ? 
            WHERE conversation_id = ?
        ");
        $stmt->bind_param("si", $title, $conversationId);
        return $stmt->execute();
    }

    /**
     * Archive conversation (set is_active = 0)
     */
    public function archiveConversation($conversationId)
    {
        $stmt = $this->conn->prepare("
            UPDATE ai_conversations 
            SET is_active = 0 
            WHERE conversation_id = ?
        ");
        $stmt->bind_param("i", $conversationId);
        return $stmt->execute();
    }

    /**
     * Delete conversation and all messages
     */
    public function deleteConversation($conversationId, $userId)
    {
        // Verify ownership before deleting
        $stmt = $this->conn->prepare("
            DELETE FROM ai_conversations 
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $conversationId, $userId);
        return $stmt->execute();
    }

    /**
     * Generate smart title from first user message
     */
    public function generateTitle($content)
    {
        // Extract first 50 characters or first sentence
        $title = substr($content, 0, 50);

        // Try to break at sentence end
        if (strlen($content) > 50) {
            $dotPos = strpos($title, '.');
            $questionPos = strpos($title, '?');
            $exclamPos = strpos($title, '!');

            $breaks = array_filter([$dotPos, $questionPos, $exclamPos], function ($pos) {
                return $pos !== false;
            });

            if (!empty($breaks)) {
                $breakPos = min($breaks);
                $title = substr($content, 0, $breakPos + 1);
            } else {
                $title .= '...';
            }
        }

        return $title;
    }

    /**
     * Generate AI-powered title based on conversation content
     * 
     * @param string $userMessage The first user message
     * @param object $chatbot The chatbot instance to use for AI generation
     * @return string Generated title (max 60 chars)
     */
    public function generateTitleWithAI($userMessage, $chatbot)
    {
        try {
            // Request AI to generate a concise title
            $response = $chatbot->generateConversationTitle($userMessage);

            if ($response['success'] && !empty($response['title'])) {
                // Limit title length
                $title = substr($response['title'], 0, 60);
                return $title;
            }
        } catch (Exception $e) {
            // Fallback to basic title generation
            error_log('AI title generation failed: ' . $e->getMessage());
        }

        // Fallback to simple extraction
        return $this->generateTitle($userMessage);
    }
}
