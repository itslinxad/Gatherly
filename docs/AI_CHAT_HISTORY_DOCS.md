# 📚 AI Chat History - Complete Documentation

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Architecture](#architecture)
4. [Database Schema](#database-schema)
5. [API Reference](#api-reference)
6. [Usage Examples](#usage-examples)
7. [Troubleshooting](#troubleshooting)

---

## Overview

The AI Chat History system provides persistent conversation storage for the AI Event Planner. All messages are automatically saved to the database and can be retrieved when users return to the application.

### Key Features

- ✅ Automatic message persistence
- ✅ Load chat history on page load
- ✅ Auto-generated conversation titles
- ✅ Multiple conversations per user
- ✅ Venue recommendation tracking
- ✅ Archive/delete conversations
- ✅ RESTful API endpoints

### Status

**✅ IMPLEMENTED & TESTED**

- Database tables created: `ai_conversations`, `ai_messages`
- Backend services: ChatHistory class + 2 API endpoints
- Frontend integration: Auto-load history on page load
- Testing: Test script available

---

## Quick Start

### 1. Verify Database Tables

```sql
SHOW TABLES LIKE 'ai_%';
-- Should show: ai_conversations, ai_messages
```

### 2. Test the System

Visit: `http://localhost/Gatherly-EMS_2025/test/test-chat-history.php`

All 9 tests should pass ✅

### 3. Use AI Planner

Visit: `http://localhost/Gatherly-EMS_2025/public/pages/organizer/ai-planner.php`

1. Send a message
2. Refresh the page
3. Your chat history loads automatically!

---

## Architecture

### System Flow

```
User Opens AI Planner
        ↓
JavaScript: loadChatHistory()
        ↓
POST → load-chat-history.php
        ↓
ChatHistory::getActiveConversation()
        ↓
ChatHistory::getMessages()
        ↓
Return JSON with messages
        ↓
Display messages in chat UI
        ↓
User sends new message
        ↓
POST → ai-conversation.php
        ↓
ChatHistory::saveMessage() [user]
        ↓
AI processes and responds
        ↓
ChatHistory::saveMessage() [assistant]
        ↓
Return response + conversation_id
        ↓
Display in chat UI
```

### Components

#### Backend

1. **ChatHistory Class** (`src/services/ai/chat-history.php`)

   - Core business logic
   - Database operations
   - Conversation management

2. **API Endpoints**
   - `load-chat-history.php` - Retrieve messages
   - `manage-conversations.php` - CRUD operations
   - `ai-conversation.php` - Chat + save messages

#### Frontend

1. **JavaScript** (`public/assets/js/ai-planner.js`)
   - `loadChatHistory()` - Fetch on page load
   - `fetchGreeting()` - Initial greeting
   - `addUserMessage()` - Display user messages
   - `addBotMessage()` - Display AI messages
   - Track `currentConversationId`

---

## Database Schema

### Table: `ai_conversations`

| Column          | Type         | Description                       |
| --------------- | ------------ | --------------------------------- |
| conversation_id | INT(11) PK   | Auto-increment primary key        |
| user_id         | INT(11) FK   | References users.user_id          |
| title           | VARCHAR(255) | Auto-generated from first message |
| created_at      | TIMESTAMP    | When conversation started         |
| updated_at      | TIMESTAMP    | Last message timestamp            |
| is_active       | TINYINT(1)   | 1 = active, 0 = archived          |

**Indexes:**

- PRIMARY KEY (conversation_id)
- KEY user_id (user_id)
- KEY idx_user_active (user_id, is_active, updated_at)

**Foreign Keys:**

- user_id → users(user_id) ON DELETE CASCADE

### Table: `ai_messages`

| Column          | Type       | Description                        |
| --------------- | ---------- | ---------------------------------- |
| message_id      | INT(11) PK | Auto-increment primary key         |
| conversation_id | INT(11) FK | References ai_conversations        |
| role            | ENUM       | 'user', 'assistant', 'system'      |
| content         | TEXT       | Message content                    |
| venue_ids       | TEXT       | JSON array of venue IDs (nullable) |
| created_at      | TIMESTAMP  | When message was sent              |

**Indexes:**

- PRIMARY KEY (message_id)
- KEY conversation_id (conversation_id)
- KEY idx_conversation_created (conversation_id, created_at)

**Foreign Keys:**

- conversation_id → ai_conversations(conversation_id) ON DELETE CASCADE

---

## API Reference

### 1. Load Chat History

**Endpoint:** `src/services/ai/load-chat-history.php`

**Method:** POST

**Request Body:**

```json
{
  "conversation_id": 123 // Optional - loads active if omitted
}
```

**Response (Success):**

```json
{
  "success": true,
  "conversation_id": 123,
  "messages": [
    {
      "message_id": 1,
      "role": "assistant",
      "content": "Hello! How can I help you?",
      "venue_ids": null,
      "created_at": "2025-11-29 10:00:00"
    },
    {
      "message_id": 2,
      "role": "user",
      "content": "I need a wedding venue for 150 guests",
      "venue_ids": null,
      "created_at": "2025-11-29 10:01:00"
    }
  ]
}
```

**Response (Error):**

```json
{
  "success": false,
  "error": "Failed to load chat history: ..."
}
```

---

### 2. Manage Conversations

**Endpoint:** `src/services/ai/manage-conversations.php`

**Method:** POST

#### 2.1 List Conversations

**Request:**

```json
{
  "action": "list"
}
```

**Response:**

```json
{
  "success": true,
  "conversations": [
    {
      "conversation_id": 1,
      "title": "Wedding for 150 guests",
      "created_at": "2025-11-29 10:00:00",
      "updated_at": "2025-11-29 10:30:00",
      "is_active": 1,
      "message_count": 12,
      "first_message": "I need a wedding venue..."
    }
  ]
}
```

#### 2.2 Create Conversation

**Request:**

```json
{
  "action": "create",
  "title": "Birthday Party Planning"
}
```

**Response:**

```json
{
  "success": true,
  "conversation": {
    "conversation_id": 2,
    "title": "Birthday Party Planning",
    "created_at": "2025-11-29 11:00:00",
    "updated_at": "2025-11-29 11:00:00"
  }
}
```

#### 2.3 Update Title

**Request:**

```json
{
  "action": "update",
  "conversation_id": 1,
  "title": "Updated Title"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Title updated"
}
```

#### 2.4 Delete Conversation

**Request:**

```json
{
  "action": "delete",
  "conversation_id": 1
}
```

**Response:**

```json
{
  "success": true,
  "message": "Conversation deleted"
}
```

#### 2.5 Archive Conversation

**Request:**

```json
{
  "action": "archive",
  "conversation_id": 1
}
```

**Response:**

```json
{
  "success": true,
  "message": "Conversation archived"
}
```

---

### 3. Send Message (with history saving)

**Endpoint:** `src/services/ai/ai-conversation.php`

**Method:** POST

**Request:**

```json
{
  "message": "I need a wedding venue for 150 guests",
  "history": [
    { "role": "assistant", "content": "Hello!" },
    { "role": "user", "content": "Hi there" }
  ],
  "conversation_id": 123 // Optional
}
```

**Response:**

```json
{
  "success": true,
  "response": "I can help you find venues...",
  "venue_ids": [1, 2, 3], // Optional
  "conversation_id": 123
}
```

**Note:** This endpoint now automatically:

1. Saves user message to database
2. Processes with AI
3. Saves AI response to database
4. Updates conversation timestamp
5. Auto-generates title on first message

---

## Usage Examples

### Load History on Page Load

```javascript
async function loadChatHistory() {
  const response = await fetch(
    "../../../src/services/ai/load-chat-history.php",
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({}),
    }
  );

  const data = await response.json();

  if (data.success && data.messages.length > 0) {
    currentConversationId = data.conversation_id;

    data.messages.forEach((msg) => {
      if (msg.role === "user") {
        addUserMessage(msg.content, false);
      } else if (msg.role === "assistant") {
        addBotMessage(msg.content, msg.venue_ids, false);
      }
    });

    scrollToBottom();
  } else {
    fetchGreeting();
  }
}
```

### Create New Conversation

```javascript
async function startNewChat() {
  const response = await fetch(
    "../../../src/services/ai/manage-conversations.php",
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "create",
        title: "New Conversation",
      }),
    }
  );

  const data = await response.json();

  if (data.success) {
    chatMessages.innerHTML = "";
    conversationHistory = [];
    currentConversationId = data.conversation.conversation_id;
    fetchGreeting();
  }
}
```

### PHP - Use ChatHistory Class

```php
require_once __DIR__ . '/chat-history.php';

$chatHistory = new ChatHistory($conn);
$userId = $_SESSION['user_id'];

// Get active conversation
$conversation = $chatHistory->getActiveConversation($userId);

// Save message
$chatHistory->saveMessage(
    $conversation['conversation_id'],
    'user',
    'I need a wedding venue',
    null  // venue_ids
);

// Get messages
$messages = $chatHistory->getMessages($conversation['conversation_id']);

// Get conversation history for AI
$history = $chatHistory->getConversationHistory($conversation['conversation_id']);
```

---

## Troubleshooting

### Issue: Chat history not loading

**Symptoms:**

- Page loads but no messages appear
- Browser console shows errors

**Solutions:**

1. Check browser console (F12) for JavaScript errors
2. Verify logged in as organizer: `$_SESSION['role'] === 'organizer'`
3. Check database tables exist:
   ```sql
   SHOW TABLES LIKE 'ai_%';
   ```
4. Verify API endpoint accessible:
   ```
   http://localhost/Gatherly-EMS_2025/src/services/ai/load-chat-history.php
   ```

---

### Issue: Messages not saving

**Symptoms:**

- Messages send but don't persist
- Refresh shows no history

**Solutions:**

1. Check Apache error log:
   ```powershell
   Get-Content "C:\xampp\apache\logs\error.log" -Tail 20
   ```
2. Verify foreign key constraint (user_id exists in users table)
3. Test directly in database:
   ```sql
   SELECT * FROM ai_messages ORDER BY created_at DESC LIMIT 5;
   ```
4. Run test script:
   ```
   http://localhost/Gatherly-EMS_2025/test/test-chat-history.php
   ```

---

### Issue: "New Chat" button not working

**Symptoms:**

- Click button, nothing happens
- No new conversation created

**Solutions:**

1. Check browser console for errors
2. Verify manage-conversations.php accessible
3. Check database:
   ```sql
   SELECT * FROM ai_conversations WHERE user_id = YOUR_ID;
   ```
4. Check Apache error log for PHP errors

---

### Issue: Venue recommendations not loading

**Symptoms:**

- AI recommends venues but no cards appear
- venue_ids not in database

**Solutions:**

1. Check if `extractVenueIds()` method working in OpenAIChatbot.php
2. Verify response contains venue_ids:
   ```javascript
   console.log(data.venue_ids);
   ```
3. Check database:
   ```sql
   SELECT * FROM ai_messages WHERE venue_ids IS NOT NULL;
   ```
4. Verify get-venue-details.php accessible

---

### Issue: Database connection errors

**Symptoms:**

- 500 errors from API endpoints
- "Connection refused" errors

**Solutions:**

1. Check XAMPP - MySQL running?
2. Verify database credentials in `src/services/dbconnect.php`
3. Test connection:
   ```php
   <?php
   require_once 'src/services/dbconnect.php';
   echo $conn ? 'Connected!' : 'Failed';
   ?>
   ```

---

## Testing

### Run Automated Tests

```
http://localhost/Gatherly-EMS_2025/test/test-chat-history.php
```

**Expected Output:**

- ✅ Test 1: Create Conversation
- ✅ Test 2: Save Messages
- ✅ Test 3: Retrieve Messages
- ✅ Test 4: Get Conversation History
- ✅ Test 5: Update Conversation Title
- ✅ Test 6: Get User Conversations
- ✅ Test 7: Get Active Conversation
- ✅ Test 8: Generate Smart Title
- ✅ Test 9: Archive Conversation

### Manual Testing Checklist

- [ ] Open AI Planner page
- [ ] Send first message
- [ ] Verify message appears
- [ ] Refresh page
- [ ] Chat history loads
- [ ] Send another message
- [ ] Click "New Chat"
- [ ] New conversation starts
- [ ] Check database for both conversations
- [ ] Test venue card functionality

---

## Files Reference

### Created Files

```
db/
  ai_chat_history.sql              # Database schema

src/services/ai/
  chat-history.php                 # ChatHistory class (core logic)
  load-chat-history.php            # Load messages API
  manage-conversations.php         # Conversation CRUD API

test/
  test-chat-history.php            # Automated test script

docs/
  AI_CHAT_HISTORY_README.md        # This file
  IMPLEMENTATION_SUMMARY.md        # Technical summary
  QUICK_START.md                   # User guide
```

### Modified Files

```
src/services/ai/
  ai-conversation.php              # Added history saving

public/assets/js/
  ai-planner.js                    # Added history loading
```

---

## Future Enhancements

### Recommended Next Steps

1. **Conversation Sidebar**

   - List all conversations in sidebar
   - Click to switch between chats
   - Show unread message count

2. **Search Functionality**

   - Full-text search across messages
   - Filter by date range
   - Search by venue name

3. **Export Features**

   - Export conversation as PDF
   - Export as text file
   - Email conversation summary

4. **Collaboration**

   - Share conversations with team
   - Multi-user chat support
   - Comment on AI suggestions

5. **Analytics**

   - Most discussed topics
   - Popular venue searches
   - Conversion tracking

6. **Advanced Features**
   - Message reactions (👍 👎)
   - Bookmark important messages
   - Conversation templates
   - AI conversation summary

---

## Support

### Resources

1. **Full Documentation:** This file
2. **Quick Start:** QUICK_START.md
3. **Technical Summary:** IMPLEMENTATION_SUMMARY.md
4. **Test Script:** test/test-chat-history.php

### Getting Help

1. Check browser console for errors
2. Review Apache error log
3. Run test script for diagnosis
4. Verify database schema
5. Check API endpoint responses

---

## Credits

**Implementation Date:** November 29, 2025  
**Database:** MariaDB 10.4.32  
**PHP Version:** 8.2.12  
**Framework:** Vanilla JS + Tailwind CSS  
**AI Provider:** Google Gemini 2.5 Pro

---

**📝 Last Updated:** November 29, 2025  
**✅ Status:** Production Ready
