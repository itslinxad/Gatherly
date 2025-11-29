<?php

/**
 * Test Script for AI Chat History
 * Run this to verify the chat history system is working
 */

session_start();

// Simulate logged-in organizer
if (!isset($_SESSION['user_id'])) {
    // Get first organizer user for testing
    require_once __DIR__ . '/../src/services/dbconnect.php';

    $result = $conn->query("SELECT user_id, first_name, last_name, role FROM users WHERE role = 'organizer' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        echo "✅ Session created for: " . $user['first_name'] . " " . $user['last_name'] . "<br>";
    } else {
        die("❌ No organizer user found. Please create an organizer account first.");
    }
}

require_once __DIR__ . '/../src/services/dbconnect.php';
require_once __DIR__ . '/../src/services/ai/chat-history.php';

$chatHistory = new ChatHistory($conn);
$userId = $_SESSION['user_id'];

echo "<h1>AI Chat History Test</h1>";
echo "<p>Testing chat history functionality...</p><br>";

// Test 1: Create a conversation
echo "<h3>Test 1: Create Conversation</h3>";
$conversation = $chatHistory->createConversation($userId, "Test Wedding Planning");
if ($conversation) {
    echo "✅ Conversation created: ID = " . $conversation['conversation_id'] . ", Title = " . $conversation['title'] . "<br>";
    $testConvId = $conversation['conversation_id'];
} else {
    die("❌ Failed to create conversation<br>");
}
echo "<br>";

// Test 2: Save messages
echo "<h3>Test 2: Save Messages</h3>";
$msg1 = $chatHistory->saveMessage($testConvId, 'user', 'I need a wedding venue for 150 guests');
if ($msg1) {
    echo "✅ User message saved: ID = " . $msg1['message_id'] . "<br>";
} else {
    echo "❌ Failed to save user message<br>";
}

$msg2 = $chatHistory->saveMessage($testConvId, 'assistant', 'I can help you find the perfect wedding venue! Let me search for venues that can accommodate 150 guests.', [1, 2]);
if ($msg2) {
    echo "✅ Assistant message saved: ID = " . $msg2['message_id'] . " (with venue IDs)<br>";
} else {
    echo "❌ Failed to save assistant message<br>";
}
echo "<br>";

// Test 3: Retrieve messages
echo "<h3>Test 3: Retrieve Messages</h3>";
$messages = $chatHistory->getMessages($testConvId);
echo "✅ Retrieved " . count($messages) . " messages:<br>";
foreach ($messages as $msg) {
    echo "- [" . $msg['role'] . "] " . substr($msg['content'], 0, 50) . "...<br>";
    if ($msg['venue_ids']) {
        echo "  Venues: " . json_encode($msg['venue_ids']) . "<br>";
    }
}
echo "<br>";

// Test 4: Get conversation history for AI
echo "<h3>Test 4: Get Conversation History (AI Format)</h3>";
$history = $chatHistory->getConversationHistory($testConvId);
echo "✅ Retrieved " . count($history) . " messages in AI format:<br>";
echo "<pre>" . json_encode($history, JSON_PRETTY_PRINT) . "</pre><br>";

// Test 5: Update conversation title
echo "<h3>Test 5: Update Conversation Title</h3>";
$updated = $chatHistory->updateConversationTitle($testConvId, "Wedding for 150 - Test");
if ($updated) {
    echo "✅ Title updated successfully<br>";
} else {
    echo "❌ Failed to update title<br>";
}
echo "<br>";

// Test 6: Get all user conversations
echo "<h3>Test 6: Get User Conversations</h3>";
$conversations = $chatHistory->getUserConversations($userId);
echo "✅ User has " . count($conversations) . " conversation(s):<br>";
foreach ($conversations as $conv) {
    echo "- ID: " . $conv['conversation_id'] . " | Title: " . $conv['title'] . " | Messages: " . $conv['message_count'] . " | Active: " . ($conv['is_active'] ? 'Yes' : 'No') . "<br>";
}
echo "<br>";

// Test 7: Get active conversation
echo "<h3>Test 7: Get Active Conversation</h3>";
$activeConv = $chatHistory->getActiveConversation($userId);
echo "✅ Active conversation: ID = " . $activeConv['conversation_id'] . ", Title = " . $activeConv['title'] . "<br>";
echo "<br>";

// Test 8: Generate title
echo "<h3>Test 8: Generate Smart Title</h3>";
$testMessage = "I'm planning a corporate event for 200 people in Manila. We need a venue with good AV equipment and parking.";
$generatedTitle = $chatHistory->generateTitle($testMessage);
echo "✅ Generated title: \"" . $generatedTitle . "\"<br>";
echo "<br>";

// Test 9: Archive conversation
echo "<h3>Test 9: Archive Conversation</h3>";
$archived = $chatHistory->archiveConversation($testConvId);
if ($archived) {
    echo "✅ Conversation archived successfully<br>";

    // Verify it's no longer active
    $convCheck = $chatHistory->getUserConversations($userId);
    $archivedConv = array_filter($convCheck, function ($c) use ($testConvId) {
        return $c['conversation_id'] == $testConvId;
    });
    $archivedConv = reset($archivedConv);
    echo "Status: is_active = " . $archivedConv['is_active'] . " (should be 0)<br>";
} else {
    echo "❌ Failed to archive conversation<br>";
}
echo "<br>";

echo "<h3>✅ All Tests Completed!</h3>";
echo "<p><a href='../public/pages/organizer/ai-planner.php'>Go to AI Planner →</a></p>";
?>

<!DOCTYPE html>
<html>

<head>
    <title>AI Chat History Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            color: #4F46E5;
        }

        h3 {
            color: #6366F1;
            margin-top: 20px;
        }

        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }

        a {
            color: #4F46E5;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
</body>

</html>