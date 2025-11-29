# 🤖 AI Event Planner - OpenAI Integration

## Overview

The AI Event Planner is an intelligent chatbot that helps event organizers find the perfect venue for their events. Powered by OpenAI's GPT technology, it provides natural, conversational recommendations based on your venue database.

---

## 🌟 Features

### Intelligent Conversation

- Natural language understanding
- Context-aware responses
- Remembers conversation history
- Adapts to user's communication style

### Smart Recommendations

- Analyzes venue database in real-time
- Considers multiple factors: capacity, price, location, amenities
- Provides detailed reasoning for recommendations
- Answers follow-up questions about venues

### Database Integration

- Fetches live venue data from MariaDB
- Includes pricing, capacity, amenities, parking
- Only shows active, available venues
- Updates automatically when database changes

---

## 📋 Quick Setup

### Prerequisites

- XAMPP with PHP 7.4+ and MariaDB
- Active internet connection
- OpenAI account (free tier available)

### Setup Steps

1. **Get OpenAI API Key** (2 minutes)

   ```
   https://platform.openai.com/api-keys
   → Click "Create new secret key"
   → Copy the key (starts with sk-)
   ```

2. **Configure API Key** (30 seconds)

   ```
   Open: config/openai.php
   Replace: 'your-openai-api-key-here'
   With: 'sk-proj-your-actual-key-123xyz'
   Save file
   ```

3. **Test** (1 minute)
   ```
   Login as Organizer
   → Go to AI Planner page
   → Type: "I need a wedding venue for 150 guests"
   → Watch the magic! ✨
   ```

**That's it!** See `QUICKSTART.md` for more details.

---

## 💬 Usage Examples

### Example 1: Basic Request

```
User: "I need a venue for a wedding"

AI: "Wonderful! I'd love to help you find the perfect wedding
     venue. How many guests are you expecting?"

User: "About 150 people"

AI: "Great! What's your budget range for the venue?"

User: "Around 100,000 pesos"

AI: "Perfect! Based on your wedding with 150 guests and a
     100k budget, here are my top 3 recommendations:

     1. Grand Ballroom - Elegant space with capacity for 200...
     2. Garden Paradise - Beautiful outdoor ceremony area...
     3. Skyline Hall - Modern venue with city views..."
```

### Example 2: All-in-One Request

```
User: "Corporate event, 80 people, 50k budget, need projector
       and good parking"

AI: "I understand you need a venue for a corporate event with:
     - 80 guests
     - ₱50,000 budget
     - Projector equipment
     - Good parking facilities

     Here are my recommendations:

     1. Business Hub Conference Center - Perfect match!
        - Capacity: 100 guests
        - Price: ₱45,000 (within budget!)
        - Built-in projector and AV system
        - 50 parking spaces
        - Why: Designed for corporate events, modern tech..."
```

### Example 3: Follow-up Questions

```
User: "Tell me more about Garden Paradise"

AI: "Garden Paradise is an excellent choice for your wedding!

     Details:
     - Capacity: 180 guests (comfortable for your 150)
     - Weekend price: ₱95,000
     - Location: Quezon City, Metro Manila

     Special features:
     - Beautiful outdoor garden for ceremony
     - Indoor air-conditioned ballroom for reception
     - Professional lighting for evening events
     - Garden fountains create romantic ambiance
     - Bridal suite included

     Amenities: Stage, Sound System, Garden, Fountains,
                Bridal Suite, Kitchen

     Parking: 30 cars, 20 motorcycles

     Would you like to schedule a visit or see other options?"
```

---

## ⚙️ Configuration

### Model Settings (`config/openai.php`)

```php
return [
    // Your API key from OpenAI
    'api_key' => 'sk-proj-...',

    // Model selection
    'model' => 'gpt-3.5-turbo',  // Fast, affordable
    // 'model' => 'gpt-4',       // Smarter, expensive

    // Response length (tokens)
    'max_tokens' => 1500,        // Good for detailed responses

    // Creativity (0.0 - 2.0)
    'temperature' => 0.7,        // Balanced
];
```

### Model Comparison

| Model         | Speed  | Quality   | Cost/1K tokens |
| ------------- | ------ | --------- | -------------- |
| gpt-3.5-turbo | Fast   | Good      | $0.002         |
| gpt-4         | Slower | Excellent | $0.030         |

**Recommendation:** Start with gpt-3.5-turbo, upgrade to gpt-4 if needed.

---

## 📊 How It Works

### System Architecture

```
┌─────────────┐
│    User     │
│  (Browser)  │
└──────┬──────┘
       │ Message
       ↓
┌─────────────────────┐
│  ai-planner.js      │
│  (Frontend)         │
│  - Manage history   │
│  - Display chat     │
└──────┬──────────────┘
       │ POST /ai-conversation.php
       ↓
┌─────────────────────┐
│ ai-conversation.php │
│ (API Endpoint)      │
│ - Auth check        │
│ - Route request     │
└──────┬──────────────┘
       │
       ↓
┌─────────────────────┐
│ OpenAIChatbot.php   │
│                     │
│ 1. Fetch venues     │──→ MariaDB
│ 2. Format as JSON   │
│ 3. Build prompt     │
│ 4. Call OpenAI      │──→ OpenAI API
│ 5. Parse response   │
│ 6. Extract venues   │
│ 7. Return data      │
└──────┬──────────────┘
       │
       ↓
┌─────────────────────┐
│    Response         │
│                     │
│ {                   │
│   success: true,    │
│   response: "...",  │
│   venues: [...]     │
│ }                   │
└─────────────────────┘
```

### System Prompt

The chatbot uses a detailed system prompt that includes:

- Role definition (AI event planning assistant)
- Complete venue database (JSON format)
- Recommendation guidelines
- Response format instructions
- Conversation flow guidance

**Example prompt structure:**

```
You are an AI event planning assistant for Gatherly.

AVAILABLE VENUES DATABASE:
[JSON array of all venues with details]

YOUR CAPABILITIES:
1. Recommend venues based on requirements
2. Compare and explain suitability
3. Provide detailed information
...

RECOMMENDATION GUIDELINES:
- Consider capacity, budget, location
- Prioritize venues 100-150% of guest count
- Base price should be 35-40% of budget
...
```

---

## 💰 Pricing

### OpenAI API Costs (gpt-3.5-turbo)

**Per Token Pricing:**

- Input: $0.0015 / 1K tokens
- Output: $0.002 / 1K tokens

**Real-World Estimate:**

- Average message: ~1000 tokens total
- Cost per message: ~$0.003 (less than 1 cent)

**Monthly Estimates:**
| Usage | Messages/Month | Cost |
|-------|----------------|------|
| Light | 100 | $0.30 |
| Medium | 500 | $1.50 |
| Active | 2,000 | $6.00 |
| Heavy | 10,000 | $30.00 |

**Free Tier:**

- New accounts get $5 free credit
- Expires after 3 months
- Enough for ~1,600 messages

**Monitor Usage:**
https://platform.openai.com/usage

---

## 🔒 Security

### API Key Protection

1. **.gitignore** excludes `config/openai.php`

   - Never commit API keys to version control
   - Use `openai.php.template` for distribution

2. **Environment Variables** (Production)

   ```php
   'api_key' => getenv('OPENAI_API_KEY') ?: 'fallback-key'
   ```

3. **Usage Limits** (OpenAI Dashboard)
   - Set monthly budget caps
   - Get alerts at thresholds
   - Prevent unexpected charges

### Data Privacy

**What's sent to OpenAI:**

- User messages
- Conversation history
- Venue database (non-sensitive data)

**What's NOT sent:**

- User passwords
- Payment information
- Personal user data

**OpenAI Data Policy:**

- API data not used for training (as of March 2023)
- Retained for 30 days for abuse monitoring
- See: https://openai.com/policies/api-data-usage-policies

---

## 🐛 Troubleshooting

### Common Issues

#### 1. "Please configure your OpenAI API key"

**Cause:** API key not set in config
**Fix:**

```
1. Open config/openai.php
2. Replace 'your-openai-api-key-here' with actual key
3. Key must start with 'sk-'
```

#### 2. "Invalid API key"

**Cause:** Key is incorrect or inactive
**Fix:**

```
1. Go to https://platform.openai.com/api-keys
2. Verify key is active
3. Copy the FULL key (starts with sk-)
4. Create new key if needed
```

#### 3. "Rate limit exceeded"

**Cause:** Too many requests in short time
**Fix:**

```
- Wait 1 minute and try again
- Check usage: https://platform.openai.com/usage
- Verify you have available credits
```

#### 4. No venue recommendations shown

**Cause:** Database has no active venues
**Fix:**

```sql
-- Check venues in database
SELECT * FROM venues
WHERE status = 'active'
AND availability_status = 'available';

-- If empty, add test venues or check status
```

#### 5. Slow responses

**Cause:** Normal for OpenAI API calls
**Notes:**

- First message: 1-2 seconds (loads venue DB)
- Follow-ups: 0.5-1 second
- Try gpt-3.5-turbo if using gpt-4

```

#### 6. Connection timeout
**Cause:** Internet connection or OpenAI server issues
**Fix:**
```

1. Check internet connection
2. Check OpenAI status: https://status.openai.com
3. Increase timeout in OpenAIChatbot.php if needed

```

---

## 📁 File Structure

```

Gatherly-EMS_2025/
│
├── config/
│ ├── openai.php # API configuration (git-ignored)
│ └── openai.php.template # Template for setup
│
├── src/services/ai/
│ ├── OpenAIChatbot.php # Main chatbot class ⭐
│ ├── ai-conversation.php # API endpoint ⭐
│ │
│ ├── ConversationalPlanner.php # DEPRECATED (old system)
│ ├── VenueRecommender.php # DEPRECATED (old system)
│ └── ai-recommendation.php # DEPRECATED (old system)
│
├── public/
│ ├── assets/js/
│ │ └── ai-planner.js # Frontend logic ⭐
│ │
│ └── pages/organizer/
│ └── ai-planner.php # Chat UI page
│
├── QUICKSTART.md # Quick setup guide
├── OPENAI_SETUP.md # Detailed setup
├── MIGRATION_SUMMARY.md # What changed
└── BEFORE_AFTER_COMPARISON.md # Old vs New system

````

⭐ = Active files (OpenAI system)

---

## 🔧 Customization

### Modify AI Behavior

Edit the system prompt in `OpenAIChatbot.php`:

```php
private function buildSystemPrompt() {
    $venues = $this->getVenuesFromDatabase();
    $venuesJson = $this->formatVenuesForAI($venues);

    return "You are an intelligent AI event planning assistant...

    // Add your custom instructions here:

    SPECIAL REQUIREMENTS:
    - Always mention if a venue is eco-friendly
    - Prioritize venues with outdoor spaces for weddings
    - Suggest venues with good public transport access
    - Recommend 3-5 venues instead of just 3

    ...";
}
````

### Add Custom Venue Data

Include additional fields in the venue context:

```php
private function formatVenuesForAI($venues) {
    foreach ($venues as $venue) {
        $venueInfo = [
            // ... existing fields ...

            // Add custom fields:
            'eco_certified' => $venue['eco_certified'],
            'public_transport' => $venue['nearby_transport'],
            'special_features' => $venue['special_notes'],
        ];

        $venuesList[] = $venueInfo;
    }
    return json_encode($venuesList, JSON_PRETTY_PRINT);
}
```

### Change Response Style

Adjust the prompt tone:

```php
// Formal tone
"Respond professionally and formally, using business language."

// Friendly tone (current)
"Be friendly, conversational, and helpful like a personal assistant."

// Concise tone
"Provide brief, to-the-point recommendations without lengthy explanations."
```

---

## 🚀 Advanced Features

### Add GPT-4 Support

In `config/openai.php`:

```php
'model' => 'gpt-4',  // or 'gpt-4-turbo'
```

**Benefits:**

- Better understanding of complex requests
- More accurate recommendations
- Superior reasoning

**Trade-offs:**

- 15x more expensive
- Slightly slower responses

### Implement Caching

Cache venue database to reduce API context size:

```php
// In OpenAIChatbot.php
private $venueCache = null;
private $cacheTimestamp = null;
private $cacheLifetime = 3600; // 1 hour

private function getVenuesFromDatabase() {
    $now = time();
    if ($this->venueCache &&
        ($now - $this->cacheTimestamp) < $this->cacheLifetime) {
        return $this->venueCache;
    }

    // Fetch from database
    $venues = /* query */;

    $this->venueCache = $venues;
    $this->cacheTimestamp = $now;
    return $venues;
}
```

### Add Conversation Export

Allow users to download chat history:

```javascript
// In ai-planner.js
function exportConversation() {
  const text = conversationHistory
    .map((msg) => `${msg.role}: ${msg.content}`)
    .join("\n\n");

  const blob = new Blob([text], { type: "text/plain" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `conversation-${Date.now()}.txt`;
  a.click();
}
```

---

## 📚 Resources

### Documentation

- [OpenAI API Docs](https://platform.openai.com/docs)
- [GPT Best Practices](https://platform.openai.com/docs/guides/gpt-best-practices)
- [Chat Completions Guide](https://platform.openai.com/docs/guides/chat)

### Monitoring

- [Usage Dashboard](https://platform.openai.com/usage)
- [API Keys](https://platform.openai.com/api-keys)
- [Billing](https://platform.openai.com/account/billing)
- [Status Page](https://status.openai.com)

### Community

- [OpenAI Community Forum](https://community.openai.com)
- [OpenAI Discord](https://discord.gg/openai)

---

## ❓ FAQ

**Q: Do I need a credit card for OpenAI?**
A: Not for testing! New accounts get $5 free credit. You'll need a card for production use after the free tier.

**Q: How long does $5 credit last?**
A: About 1,600 messages with gpt-3.5-turbo. Expires after 3 months.

**Q: Can I use this offline?**
A: No, it requires internet to call OpenAI API.

**Q: Will my API key be stolen?**
A: No, if you follow security practices:

- Keep `config/openai.php` git-ignored
- Don't share your key publicly
- Set usage limits in OpenAI dashboard

**Q: Can I switch back to the old system?**
A: Yes, the old files are still there. Just restore the old `ai-conversation.php`.

**Q: What if OpenAI is down?**
A: Check https://status.openai.com. Implement fallback error messages in the UI.

**Q: Does this work in production?**
A: Yes! Just add your API key and set up proper billing monitoring.

---

## 🎯 Next Steps

1. ✅ **Setup** - Add API key to `config/openai.php`
2. ✅ **Test** - Try the chatbot as an organizer
3. ✅ **Monitor** - Check usage at OpenAI dashboard
4. ✅ **Customize** - Modify prompts for your use case
5. ✅ **Deploy** - Launch to production!

---

## 🤝 Support

Need help? Check these resources:

1. **Quick Start** - `QUICKSTART.md`
2. **Full Setup** - `OPENAI_SETUP.md`
3. **What Changed** - `MIGRATION_SUMMARY.md`
4. **Comparison** - `BEFORE_AFTER_COMPARISON.md`

---

**Enjoy your AI-powered event planning assistant!** 🎉🤖
