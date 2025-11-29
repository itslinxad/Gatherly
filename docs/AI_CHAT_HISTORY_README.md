# AI Chat History Installation Guide

## Overview

This update adds persistent chat history to the AI Event Planner, storing all conversations in the database.

## Features

- ✅ Automatic conversation persistence
- ✅ Load previous chat history on page load
- ✅ Auto-generate conversation titles from first message
- ✅ Create new conversations (archives current one)
- ✅ Support for multiple conversations per user
- ✅ Stores venue recommendations with messages

## Installation Steps

### 1. Create Database Tables

**Option A: Using phpMyAdmin**

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select the `sad_db` database
3. Click on "SQL" tab
4. Copy and paste the contents of `db/ai_chat_history.sql`
5. Click "Go" to execute

**Option B: Using MySQL Command Line**

```bash
mysql -u root -p sad_db < db/ai_chat_history.sql
```

**Option C: Using PowerShell (from project root)**

```powershell
Get-Content "db\ai_chat_history.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root -p sad_db
```

### 2. Verify Tables Created

Run this query in phpMyAdmin to verify:

```sql
SHOW TABLES LIKE 'ai_%';
```

You should see:

- `ai_conversations`
- `ai_messages`

### 3. Test the Feature

1. Navigate to AI Event Planner page
2. Send a message - it will auto-save to database
3. Refresh the page - your chat history should load automatically
4. Click "New Chat" to start a new conversation (archives the current one)

## Database Schema

### ai_conversations

- `conversation_id` - Primary key
- `user_id` - FK to users table
- `title` - Auto-generated from first user message
- `created_at` - When conversation started
- `updated_at` - Last message timestamp
- `is_active` - Currently active conversation (1) or archived (0)

### ai_messages

- `message_id` - Primary key
- `conversation_id` - FK to ai_conversations
- `role` - 'user', 'assistant', or 'system'
- `content` - Message text
- `venue_ids` - JSON array of recommended venue IDs (nullable)
- `created_at` - When message was sent

## API Endpoints

### Load Chat History

**Endpoint:** `src/services/ai/load-chat-history.php`
**Method:** POST
**Body:**

```json
{
  "conversation_id": 123 // Optional, loads active conversation if omitted
}
```

### Manage Conversations

**Endpoint:** `src/services/ai/manage-conversations.php`
**Method:** POST

**List all conversations:**

```json
{
  "action": "list"
}
```

**Create new conversation:**

```json
{
  "action": "create",
  "title": "Wedding Planning"
}
```

**Update conversation title:**

```json
{
  "action": "update",
  "conversation_id": 123,
  "title": "Updated Title"
}
```

**Delete conversation:**

```json
{
  "action": "delete",
  "conversation_id": 123
}
```

**Archive conversation:**

```json
{
  "action": "archive",
  "conversation_id": 123
}
```

## Files Modified/Created

### New Files

- `db/ai_chat_history.sql` - Database schema
- `src/services/ai/chat-history.php` - ChatHistory class
- `src/services/ai/load-chat-history.php` - Load history API
- `src/services/ai/manage-conversations.php` - Conversation management API

### Modified Files

- `src/services/ai/ai-conversation.php` - Integrated chat history
- `public/assets/js/ai-planner.js` - Load history on page load

## How It Works

1. **On Page Load:**

   - JavaScript calls `load-chat-history.php`
   - Retrieves active conversation and all messages
   - Displays messages in chat UI
   - Populates `conversationHistory` array for API

2. **On New Message:**

   - User sends message → saved to `ai_messages` table
   - AI responds → response also saved to `ai_messages`
   - Conversation `updated_at` timestamp updated
   - First user message auto-generates conversation title

3. **On New Chat:**
   - Creates new conversation in database
   - Archives current conversation (sets `is_active = 0`)
   - Clears UI and starts fresh

## Troubleshooting

### Chat history not loading

1. Check browser console for errors
2. Verify tables exist: `SHOW TABLES LIKE 'ai_%';`
3. Check user is logged in (session active)
4. Verify database connection in `src/services/dbconnect.php`

### Messages not saving

1. Check Apache error log: `C:\xampp\apache\logs\error.log`
2. Verify foreign key constraint (user_id must exist in users table)
3. Test with SQL: `SELECT * FROM ai_messages ORDER BY created_at DESC LIMIT 5;`

### "New Chat" button not working

1. Open browser console to see error messages
2. Verify `manage-conversations.php` is accessible
3. Check if conversation was created: `SELECT * FROM ai_conversations WHERE user_id = YOUR_USER_ID;`

## Future Enhancements

Possible additions:

- Conversation sidebar to switch between chats
- Search within conversations
- Export conversation as PDF/text
- Share conversations with other organizers
- Conversation categories/tags
