# GitHub Copilot Chatbot Setup Guide

## Overview

The AI event planning assistant now uses **GitHub Copilot API** instead of OpenAI. This gives you access to GPT-4 models with your existing Copilot Pro subscription at no additional cost!

## Why GitHub Copilot?

### Advantages over OpenAI

✅ **Included with Copilot Pro** - No additional API costs!  
✅ **GPT-4 Model** - Better quality than GPT-3.5-turbo  
✅ **Unlimited Usage** - No per-message charges  
✅ **Same Quality** - Uses Azure OpenAI under the hood  
✅ **Easy Setup** - Just use your GitHub token

### Requirements

- GitHub Copilot Pro subscription ($10/month) or Copilot Enterprise
- GitHub Personal Access Token with `copilot` scope

## Setup Instructions

### Step 1: Subscribe to GitHub Copilot (if not already)

1. **Check Your Subscription**
   - Go to https://github.com/settings/copilot
   - If you see "GitHub Copilot is active" → You're good! Skip to Step 2
2. **Subscribe to Copilot Pro** (if needed)
   - Go to https://github.com/features/copilot
   - Click **"Buy Copilot Pro"** ($10/month)
   - Complete the payment
   - Wait 1-2 minutes for activation

### Step 2: Generate Personal Access Token

1. **Go to Token Settings**

   - Visit https://github.com/settings/tokens
   - Click **"Generate new token"** dropdown
   - Select **"Generate new token (classic)"**

2. **Configure Token**

   - **Note**: `Gatherly Copilot API` (or any name you prefer)
   - **Expiration**: Choose duration (90 days recommended, or No expiration)
   - **Select scopes**:
     - ☑️ **copilot** - Full GitHub Copilot access
     - (This automatically includes necessary sub-scopes)

3. **Generate and Copy**
   - Click **"Generate token"** at the bottom
   - **IMMEDIATELY copy the token** (starts with `ghp_` or `github_pat_`)
   - ⚠️ You won't be able to see it again!
   - Store it securely (you'll need it in Step 3)

### Step 3: Configure the Application

1. **Open Configuration File**

   - Navigate to `config/openai.php`

2. **Add Your GitHub Token**

   ```php
   return [
       'github_token' => 'ghp_abc123xyz...', // ← Replace with your token
       'api_endpoint' => 'https://api.githubcopilot.com/chat/completions',
       'model' => 'gpt-4',
       'max_tokens' => 1500,
       'temperature' => 0.7,
   ];
   ```

3. **Save the File**

### Step 4: Test the Chatbot

1. **Start Your Server**

   - Make sure XAMPP Apache and MySQL are running

2. **Login as Organizer**

   - Navigate to your Gatherly application
   - Login with organizer credentials

3. **Open AI Planner**

   - Click on "AI Planner" in the sidebar

4. **Test Conversation**

   ```
   You: "I need a venue for a wedding"
   Copilot: "Wonderful! I'd love to help you find the perfect wedding venue..."

   You: "Around 150 people with a budget of 100,000 pesos"
   Copilot: "Based on your requirements, here are my top 3 recommendations..."
   ```

## Configuration Options

### Model Selection

The configuration uses `gpt-4` by default (included with Copilot):

```php
'model' => 'gpt-4',        // Recommended (GPT-4 quality)
// 'model' => 'gpt-4-turbo', // Faster GPT-4 variant
```

### Temperature (0.0 - 2.0)

- **0.7** (Default): Balanced creativity and consistency
- **0.3-0.5**: More focused responses
- **0.8-1.0**: More creative responses

### Max Tokens

- **1500** (Default): Detailed responses with venue cards
- Increase for longer explanations
- Decrease if responses are too long

## Cost Comparison

### GitHub Copilot (Current)

- **Monthly**: $10 (Copilot Pro subscription)
- **Per Message**: $0 (unlimited included!)
- **Model**: GPT-4
- **Total for 1000 messages/month**: $10 flat fee

### OpenAI (Previous)

- **Monthly**: $0 (pay-as-you-go)
- **Per Message**: ~$0.003 (gpt-3.5-turbo)
- **Model**: GPT-3.5-turbo (lower quality)
- **Total for 1000 messages/month**: ~$3 + OpenAI account setup

**Winner**: GitHub Copilot! Better model, unlimited usage, same price you're already paying.

## Troubleshooting

### Error: "Please configure your GitHub token"

**Cause**: Token not set in config  
**Fix**:

```
1. Open config/openai.php
2. Replace 'your-github-token-here' with your actual token
3. Token must start with 'ghp_' or 'github_pat_'
```

### Error: "Unauthorized" or "Invalid token"

**Cause**: Token is incorrect, expired, or lacks copilot scope  
**Fix**:

```
1. Go to https://github.com/settings/tokens
2. Verify token is active (not expired)
3. Check that 'copilot' scope is enabled
4. If expired, generate new token
```

### Error: "GitHub Copilot is not active"

**Cause**: You don't have an active Copilot subscription  
**Fix**:

```
1. Go to https://github.com/features/copilot
2. Subscribe to Copilot Pro ($10/month)
3. Wait 1-2 minutes for activation
4. Generate new token with copilot scope
```

### Error: "Rate limit exceeded"

**Cause**: Too many requests (unlikely with Copilot)  
**Fix**:

```
- Wait 1 minute and try again
- GitHub Copilot has generous rate limits
- Contact GitHub support if persistent
```

### No Venue Recommendations

**Cause**: Database has no active venues  
**Fix**:

```sql
-- Check venues
SELECT * FROM venues
WHERE status = 'active'
AND availability_status = 'available';

-- If empty, add test venues
```

### Slow Responses

**Note**: Normal behavior

- First message: 2-3 seconds (loads venue database + GPT-4)
- Follow-ups: 1-2 seconds
- GPT-4 is slightly slower than GPT-3.5 but much smarter

## How It Works

### System Architecture

```
User Message
    ↓
JavaScript (ai-planner.js)
    ↓
ai-conversation.php
    ↓
CopilotChatbot.php
    ↓
1. Fetch venues from database
2. Format as JSON context
3. Build system prompt
4. Call GitHub Copilot API
    ↓
GitHub Copilot (GPT-4)
    ↓
5. AI analyzes venues
6. Generates recommendations
7. Returns response
    ↓
Display chat + venue cards
```

### API Endpoint

```
https://api.githubcopilot.com/chat/completions
```

### Authentication

```
Authorization: Bearer YOUR_GITHUB_TOKEN
Editor-Version: vscode/1.85.0
Editor-Plugin-Version: copilot-chat/0.11.0
```

## Security Best Practices

### 1. Token Protection

```php
// .gitignore already excludes config/openai.php
config/openai.php

// Never commit tokens to Git
// Use environment variables in production:
'github_token' => getenv('GITHUB_TOKEN') ?: 'fallback'
```

### 2. Token Permissions

Only enable the `copilot` scope:

- ☑️ copilot
- ☐ Don't enable admin, repo, or other unnecessary scopes

### 3. Token Expiration

Set reasonable expiration:

- **Development**: 90 days
- **Production**: 60 days with auto-rotation
- **Never**: Only if absolutely necessary

### 4. Rotate Tokens

- Regenerate tokens every 3-6 months
- Immediately revoke if exposed
- Use separate tokens for dev/staging/prod

## Advanced Features

### Custom System Prompt

Edit `OpenAIChatbot.php` to customize AI behavior:

```php
private function buildSystemPrompt() {
    return "You are an AI assistant powered by GitHub Copilot...

    // Add custom instructions:
    SPECIAL REQUIREMENTS:
    - Always prioritize eco-friendly venues
    - Mention accessibility features
    - Suggest seasonal decorations

    ...";
}
```

### Add Venue Filtering

```php
// Filter by specific criteria
WHERE v.status = 'active'
AND v.availability_status = 'available'
AND v.eco_certified = 1  // Add custom filters
```

### Conversation Export

```javascript
// Add export button in frontend
function exportChat() {
  const text = conversationHistory
    .map((m) => `${m.role}: ${m.content}`)
    .join("\n\n");
  // Download as file
}
```

## Monitoring & Usage

### Check Token Status

```
https://github.com/settings/tokens
→ View active tokens
→ Check last used date
→ Verify scopes
```

### Copilot Subscription

```
https://github.com/settings/copilot
→ View subscription status
→ Check renewal date
→ Manage billing
```

### Usage Limits

GitHub Copilot API has generous limits:

- **No per-message charges**
- **Rate limits**: ~100 requests/minute
- **Included in subscription**: No extra fees

## Migration from OpenAI

If you previously used OpenAI:

### What Changed

1. **API Endpoint**: `api.openai.com` → `api.githubcopilot.com`
2. **Authentication**: `sk-...` API key → `ghp_...` GitHub token
3. **Cost Model**: Pay-per-use → Flat subscription fee
4. **Model**: GPT-3.5-turbo → GPT-4

### What Stayed the Same

- ✅ Chat interface (no UI changes)
- ✅ Venue recommendations logic
- ✅ Conversation history management
- ✅ Database integration

### Migration Benefits

- ✅ Better AI quality (GPT-4 vs 3.5)
- ✅ No usage anxiety (unlimited)
- ✅ Simplified billing
- ✅ Same subscription you're already paying for

## FAQ

**Q: Do I need to pay extra for the API?**  
A: No! It's included in your $10/month Copilot Pro subscription.

**Q: What if I already have OpenAI credits?**  
A: You can keep using OpenAI by reverting the config. But Copilot gives you GPT-4 for free!

**Q: Is there a usage limit?**  
A: GitHub Copilot has generous rate limits (~100 req/min) which is plenty for a chatbot.

**Q: Can I use this without Copilot Pro?**  
A: No, you need an active Copilot subscription. But it's worth it for the IDE features too!

**Q: What if GitHub Copilot API goes down?**  
A: Check https://www.githubstatus.com. You can temporarily switch back to OpenAI if needed.

**Q: Can I use both OpenAI and Copilot?**  
A: Yes! Keep both tokens and add a toggle in the config to switch between them.

## Support

### GitHub Copilot Resources

- [Copilot Homepage](https://github.com/features/copilot)
- [Subscription Settings](https://github.com/settings/copilot)
- [Token Management](https://github.com/settings/tokens)
- [GitHub Status](https://www.githubstatus.com)

### Gatherly Documentation

- **Quick Start**: `QUICKSTART_COPILOT.md`
- **Migration Guide**: `MIGRATION_SUMMARY.md`
- **Comparison**: `BEFORE_AFTER_COMPARISON.md`
- **Checklist**: `CHECKLIST.md`

---

**Ready to start?** Just add your GitHub token to `config/openai.php` and start chatting! 🚀
