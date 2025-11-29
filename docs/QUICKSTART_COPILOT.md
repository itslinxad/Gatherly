# ⚡ Quick Start - GitHub Copilot Chatbot

## 1️⃣ Get Your GitHub Token (3 minutes)

1. Go to https://github.com/settings/tokens
2. Click **"Generate new token"** → **"Generate new token (classic)"**
3. Name: `Gatherly-Copilot-API`
4. Select scopes: ☑️ **copilot** (full Copilot access)
5. Click **"Generate token"**
6. **Copy the token immediately** (starts with `ghp_` or `github_pat_`)

## 2️⃣ Configure (30 seconds)

1. Open `config/openai.php`
2. Replace this line:
   ```php
   'github_token' => 'your-github-token-here',
   ```
   With your actual token:
   ```php
   'github_token' => 'ghp_abc123xyz...',
   ```
3. Save the file

## 3️⃣ Test (1 minute)

1. Login as **Organizer**
2. Go to **AI Planner** page
3. Type: "I need a wedding venue for 150 guests"
4. Watch GitHub Copilot recommend venues! 🎉

## 💰 Costs

- **Free with Copilot Pro**: Included in your $10/month subscription!
- **No per-message charges**: Unlimited API calls
- **Better than OpenAI**: GPT-4 model at no extra cost

## ⚠️ Troubleshooting

| Error                                | Solution                                             |
| ------------------------------------ | ---------------------------------------------------- |
| "Please configure your GitHub token" | Add your token to `config/openai.php`                |
| "Invalid token"                      | Check you copied the full token starting with `ghp_` |
| "Unauthorized"                       | Verify you have Copilot Pro subscription active      |
| "copilot scope required"             | Regenerate token with copilot scope selected         |

## 📚 Full Documentation

See `GITHUB_COPILOT_SETUP.md` for detailed information.

---

**That's it!** Your GitHub Copilot-powered chatbot is ready! 🚀
