# ⚡ Quick Start - OpenAI Chatbot

## 1️⃣ Get Your API Key (2 minutes)

1. Go to https://platform.openai.com/api-keys
2. Click **"Create new secret key"**
3. Copy the key (starts with `sk-`)

## 2️⃣ Configure (30 seconds)

1. Open `config/openai.php`
2. Replace this line:
   ```php
   'api_key' => 'your-openai-api-key-here',
   ```
   With your actual key:
   ```php
   'api_key' => 'sk-proj-abc123xyz...',
   ```
3. Save the file

## 3️⃣ Test (1 minute)

1. Login as **Organizer**
2. Go to **AI Planner** page
3. Type: "I need a wedding venue for 150 guests"
4. Watch the AI recommend venues! 🎉

## 💰 Costs

- **Free tier**: $5 credit (expires in 3 months)
- **Per message**: ~$0.003 (less than a cent!)
- **100 messages**: ~$0.30

## ⚠️ Troubleshooting

| Error                           | Solution                                          |
| ------------------------------- | ------------------------------------------------- |
| "Please configure your API key" | Add your key to `config/openai.php`               |
| "Invalid API key"               | Check you copied the full key starting with `sk-` |
| "Rate limit exceeded"           | Wait a few minutes or add billing                 |

## 📚 Full Documentation

See `OPENAI_SETUP.md` for detailed information.

---

**That's it!** Your AI chatbot is ready to help event organizers find the perfect venue! 🚀
