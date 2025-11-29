# OpenAI Chatbot Migration Summary

## What Changed

### 🔄 Complete AI System Replacement

The entire rule-based AI system has been replaced with OpenAI GPT API integration for intelligent, conversational venue recommendations.

---

## 📁 Files Created

### 1. **config/openai.php**

- OpenAI API configuration
- **ACTION REQUIRED**: Add your API key here
- Contains model settings (gpt-3.5-turbo by default)
- **SECURITY**: Excluded from Git via .gitignore

### 2. **config/openai.php.template**

- Template file for API configuration
- Safe to commit to version control
- Copy this when setting up new environments

### 3. **src/services/ai/OpenAIChatbot.php** (NEW)

- Main chatbot class with OpenAI integration
- Fetches venues from database
- Formats venue data for GPT context
- Calls OpenAI API with conversation history
- Extracts venue IDs from AI response
- Returns recommendations with venue details

---

## 📝 Files Modified

### 1. **src/services/ai/ai-conversation.php** (REPLACED)

**Before:** Rule-based conversation with ConversationalPlanner

```php
require_once __DIR__ . '/ConversationalPlanner.php';
$planner = new ConversationalPlanner($pdo);
$result = $planner->processConversation($message, $conversation_state);
```

**After:** OpenAI API integration

```php
require_once __DIR__ . '/OpenAIChatbot.php';
$chatbot = new OpenAIChatbot($conn);
$response = $chatbot->chat($message, $conversationHistory);
```

**Changes:**

- Removed ConversationalPlanner dependency
- Added OpenAIChatbot class
- Changed from `conversation_state` to `conversationHistory` format
- Returns: `{success, response, venues, has_recommendations}`

---

### 2. **public/assets/js/ai-planner.js** (UPDATED)

**Before:** Tracked conversation state with stages

```javascript
let conversationState = {};
body: JSON.stringify({
  message: message,
  conversation_state: conversationState,
});
```

**After:** Maintains conversation history for OpenAI

```javascript
let conversationHistory = [];
conversationHistory.push({
  role: "user",
  content: message,
});
body: JSON.stringify({
  message: message,
  history: conversationHistory,
});
```

**Changes:**

- Replaced `conversationState` object with `conversationHistory` array
- Added `fetchGreeting()` function to get AI welcome message
- Simplified response handling (removed algorithm comparison mode)
- Updated venue card rendering for new data structure
- Clear chat now resets history and fetches new greeting

---

### 3. **.gitignore** (UPDATED)

Added security rule:

```
# OpenAI Configuration (IMPORTANT: Contains API key)
config/openai.php
```

---

## 📚 Documentation Added

### 1. **OPENAI_SETUP.md**

Complete setup guide covering:

- How to get OpenAI API key
- Configuration instructions
- Model selection (gpt-3.5-turbo vs gpt-4)
- Cost estimation
- Security best practices
- Troubleshooting guide
- System architecture

### 2. **QUICKSTART.md**

Quick 3-step setup:

1. Get API key
2. Add to config
3. Test chatbot

---

## 🗑️ Files Deprecated (Not Used Anymore)

These files are still in the codebase but **no longer used**:

### 1. **src/services/ai/ConversationalPlanner.php**

- Old rule-based conversation system
- Used stage-based flow (greeting → event_type → guests → budget → etc.)
- Manual recommendation logic
- **Replaced by:** OpenAI GPT handles conversation flow

### 2. **src/services/ai/VenueRecommender.php**

- Ensemble ML scoring system
- MCDM (35%) + KNN (35%) + Decision Tree (30%)
- Complex scoring algorithms
- **Replaced by:** OpenAI GPT with venue database context

### 3. **src/services/ai/VenueRecommender.php.backup**

- Backup of original recommender
- No longer needed

### 4. **src/services/ai/ai-recommendation.php**

- Standalone recommendation endpoint
- Used by old system
- **Replaced by:** Built into OpenAIChatbot class

**Note:** You can safely delete these files if you want to clean up the codebase, or keep them for reference.

---

## 🔑 Key Differences: Old vs New System

| Feature                   | Old (Rule-Based)             | New (OpenAI)                 |
| ------------------------- | ---------------------------- | ---------------------------- |
| **Conversation**          | Stage-based flow             | Natural language GPT         |
| **Recommendations**       | 3-algorithm ensemble scoring | GPT analysis with venue data |
| **Flexibility**           | Fixed question sequence      | Adaptive to user input       |
| **Context Understanding** | Limited to stages            | Full conversation history    |
| **Venue Matching**        | Mathematical scoring         | Semantic understanding       |
| **Setup Complexity**      | No external dependencies     | Requires API key             |
| **Cost**                  | Free (local PHP)             | ~$0.003 per message          |
| **Quality**               | Good but rigid               | Excellent and flexible       |

---

## 🎯 How It Works Now

### Conversation Flow

```
User → JavaScript → ai-conversation.php → OpenAIChatbot.php
                                             ↓
                                    1. Fetch venues from DB
                                    2. Build system prompt with venue data
                                    3. Send to OpenAI with history
                                    4. Get GPT response
                                    5. Extract venue IDs
                                    6. Fetch venue details
                                             ↓
                            Return: {success, response, venues}
                                             ↓
                            Display chat + venue cards
```

### System Prompt Structure

```
You are an AI event planning assistant.

AVAILABLE VENUES:
[
  {
    "id": 1,
    "name": "Grand Ballroom",
    "capacity": "200 guests",
    "location": "Manila, NCR",
    "prices": {...},
    "amenities": "Stage, Sound System, Lighting",
    ...
  },
  ...
]

YOUR CAPABILITIES:
- Recommend venues based on requirements
- Compare and explain suitability
- Provide detailed information
...
```

---

## ✅ What You Need To Do

### Immediate (Required)

1. **Get OpenAI API Key**
   - Go to https://platform.openai.com/api-keys
   - Create new key
2. **Configure API Key**
   - Open `config/openai.php`
   - Replace `'your-openai-api-key-here'` with your actual key
3. **Test the Chatbot**
   - Login as Organizer
   - Go to AI Planner page
   - Start conversation

### Optional

1. **Monitor Usage**
   - Check https://platform.openai.com/usage
   - Set up billing alerts
2. **Clean Up Old Files**

   - Delete deprecated AI files if desired
   - Keep for reference if needed

3. **Adjust Settings**
   - Try gpt-4 for better quality (higher cost)
   - Adjust temperature for more/less creativity
   - Modify max_tokens for longer/shorter responses

---

## 🆘 Support

### Quick Fixes

- **"Please configure API key"**: Add key to `config/openai.php`
- **"Invalid API key"**: Copy full key starting with `sk-`
- **No venues shown**: Check database has active venues
- **Slow responses**: Normal for first message (loads DB)

### Documentation

- See `OPENAI_SETUP.md` for detailed setup
- See `QUICKSTART.md` for 3-step guide
- Check OpenAI docs: https://platform.openai.com/docs

---

## 🎉 Benefits of New System

### For Users (Organizers)

- ✅ Natural conversation (like chatting with a person)
- ✅ Better understanding of complex requirements
- ✅ More detailed explanations
- ✅ Flexible question answering
- ✅ Context-aware recommendations

### For You (Developer)

- ✅ No complex ML algorithms to maintain
- ✅ Easier to improve (just update prompt)
- ✅ Scalable with better models
- ✅ Less code to debug
- ✅ Industry-standard AI integration

---

**Ready to go live!** Just add your API key and start chatting! 🚀
