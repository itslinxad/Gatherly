# OpenAI Chatbot Setup Guide

## Overview

The AI event planning assistant has been upgraded to use OpenAI's GPT API for intelligent, conversational venue recommendations. The chatbot analyzes your database of venues and provides personalized recommendations based on event requirements.

## Features

- 🤖 **Natural Conversation**: Powered by GPT-3.5 Turbo (or GPT-4)
- 🏛️ **Smart Recommendations**: AI analyzes venues based on capacity, pricing, location, and amenities
- 💬 **Context-Aware**: Maintains conversation history for better understanding
- 📊 **Database Integration**: Real-time venue data from your MariaDB database
- 🎯 **Personalized**: Matches venues to event type, guest count, budget, and preferences

## Setup Instructions

### Step 1: Get Your OpenAI API Key

1. **Create an OpenAI Account**

   - Go to [https://platform.openai.com/signup](https://platform.openai.com/signup)
   - Sign up for a free account or log in if you already have one

2. **Generate API Key**

   - Navigate to [https://platform.openai.com/api-keys](https://platform.openai.com/api-keys)
   - Click **"+ Create new secret key"**
   - Give it a name (e.g., "Gatherly-Chatbot")
   - **Copy the API key immediately** (you won't be able to see it again!)

3. **Add Credits** (if needed)
   - New accounts get $5 free credits (valid for 3 months)
   - For production use, add billing at [https://platform.openai.com/account/billing](https://platform.openai.com/account/billing)

### Step 2: Configure the Application

1. **Open Configuration File**

   - Navigate to `config/openai.php`
   - This file was created automatically

2. **Add Your API Key**

   ```php
   return [
       'api_key' => 'sk-proj-xxxxxxxxxxxxxxxxxxxxx', // ← Replace this
       'model' => 'gpt-3.5-turbo', // or 'gpt-4' for better results
       'max_tokens' => 1500,
       'temperature' => 0.7,
   ];
   ```

3. **Save the File**
   - Replace `'your-openai-api-key-here'` with your actual API key
   - Save and close the file

### Step 3: Test the Chatbot

1. **Start Your Server**

   - Make sure XAMPP Apache and MySQL are running
   - Navigate to your Gatherly application

2. **Login as Organizer**

   - Go to AI Planner page
   - You should see the chatbot interface

3. **Test Conversation**
   - Example conversation:
     ```
     You: "I need a venue for a wedding"
     AI: "Great! I'd love to help you find the perfect wedding venue.
          How many guests are you expecting?"
     You: "Around 150 people"
     AI: "Wonderful! What's your budget range?"
     You: "About 100,000 pesos"
     AI: "Based on your requirements, here are my top 3 recommendations..."
     ```

## Configuration Options

### Model Selection

- **gpt-3.5-turbo** (Recommended for most use cases)
  - Fast responses
  - Lower cost (~$0.002 per 1K tokens)
  - Good for general conversations
- **gpt-4** (Premium option)
  - More intelligent responses
  - Better reasoning
  - Higher cost (~$0.03 per 1K tokens)
  - Requires separate API access

### Temperature (0.0 - 2.0)

- **0.7** (Default): Balanced creativity and consistency
- **0.3-0.5**: More focused and deterministic responses
- **0.8-1.0**: More creative and varied responses

### Max Tokens

- **1500** (Default): Allows detailed responses with venue details
- Increase if you want longer, more detailed explanations
- Decrease to reduce API costs

## Cost Estimation

### Example Usage (gpt-3.5-turbo)

- **10 messages/day**: ~$0.30/month
- **50 messages/day**: ~$1.50/month
- **200 messages/day**: ~$6.00/month

_Costs are approximate. Monitor your usage at [https://platform.openai.com/usage](https://platform.openai.com/usage)_

## Security Best Practices

1. **Never commit API keys to Git**
   - The `.gitignore` file already excludes `config/openai.php`
2. **Use environment variables in production**

   ```php
   'api_key' => getenv('OPENAI_API_KEY') ?: 'your-key-here'
   ```

3. **Set up usage limits in OpenAI dashboard**
   - Prevent unexpected charges
   - Set monthly budget caps

## Troubleshooting

### Error: "Please configure your OpenAI API key"

- Check that you've replaced the placeholder in `config/openai.php`
- Make sure the key starts with `sk-`

### Error: "Invalid API key"

- Verify you copied the entire key correctly
- Check if the key is still active in your OpenAI dashboard
- Generate a new key if necessary

### Error: "Rate limit exceeded"

- You've exceeded your quota
- Wait a few minutes and try again
- Add more credits to your account

### Slow Responses

- Normal for first message (loads venue database)
- Subsequent messages should be faster
- Consider switching to gpt-3.5-turbo if using gpt-4

### No Venue Recommendations

- Check that you have active venues in the database
- Venue status must be 'active' and availability_status must be 'available'
- Review database connection in `src/services/dbconnect.php`

## How It Works

### System Architecture

```
User Message → JavaScript (ai-planner.js)
              ↓
         ai-conversation.php
              ↓
         OpenAIChatbot.php
              ↓
    1. Fetch venues from database
    2. Format as JSON context
    3. Build system prompt with venue data
    4. Send to OpenAI API with conversation history
    5. Parse AI response
    6. Extract venue IDs from response
    7. Fetch venue details
              ↓
         Return to frontend with venues
              ↓
         Display chat + venue cards
```

### Database Integration

The chatbot automatically:

- Fetches all active, available venues
- Includes capacity, pricing, location, amenities, parking
- Sends venue data as context to GPT
- GPT recommends venues based on this real data

### Conversation Flow

1. AI greets and asks about event type
2. Gathers: guest count, budget, location preferences
3. Analyzes venue database
4. Recommends top 3 matches with detailed reasoning
5. Answers follow-up questions
6. Helps with final decision

## File Structure

```
config/
  └── openai.php              # API configuration (DO NOT COMMIT)

src/services/ai/
  ├── OpenAIChatbot.php       # Main chatbot class
  └── ai-conversation.php     # API endpoint

public/assets/js/
  └── ai-planner.js           # Frontend JavaScript

public/pages/organizer/
  └── ai-planner.php          # Chat UI page
```

## Support

### OpenAI Documentation

- [API Reference](https://platform.openai.com/docs/api-reference)
- [Chat Completions Guide](https://platform.openai.com/docs/guides/chat)
- [Pricing](https://openai.com/pricing)

### Monitor Usage

- [Usage Dashboard](https://platform.openai.com/usage)
- [API Keys Management](https://platform.openai.com/api-keys)
- [Billing Settings](https://platform.openai.com/account/billing)

---

**Ready to start?** Just add your API key to `config/openai.php` and start chatting! 🚀
