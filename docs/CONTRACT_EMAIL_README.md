# 📧 Auto-Generated Contract & Email System

## ✨ Overview

When a venue manager changes a booking status to **"Confirmed"**, the system automatically:

1. ✅ Generates a professional HTML contract
2. 📄 Saves the contract to the database
3. 📧 Emails the contract to the organizer
4. 📋 CCs the venue manager

## 🎯 Features

- **Professional Contract Generation**: Beautifully formatted HTML contracts with all event details
- **Automatic Email Delivery**: Powered by PHPMailer (from GitHub)
- **Database Storage**: Contracts saved in `event_contracts` table
- **Success Notifications**: Manager sees confirmation when email is sent
- **Error Handling**: Graceful fallback if email fails
- **Responsive Design**: Contracts look great on all devices

## 📦 What Was Installed

### New Files Created:

```
Gatherly-EMS_2025/
├── composer.json                           # Composer configuration for PHPMailer
├── composer.lock                           # Locked dependency versions
├── vendor/                                 # PHPMailer library (auto-generated)
│   └── phpmailer/phpmailer/               # PHPMailer from GitHub
├── config/
│   ├── email.php                          # SMTP configuration (not in git)
│   └── email.php.template                 # Template for email config
├── src/services/
│   ├── ContractGenerator.php              # Generates HTML contracts
│   └── EmailService.php                   # Sends emails via PHPMailer
└── docs/
    └── EMAIL_CONTRACT_SETUP.md            # Detailed setup guide
```

### Modified Files:

```
public/pages/manager/bookings.php          # Added email trigger on status change
.gitignore                                 # Added vendor/ and email.php
```

## 🚀 Quick Start

### Step 1: Install Dependencies (Already Done ✓)

```bash
cd /opt/lampp/htdocs/Gatherly-EMS_2025
composer install
```

### Step 2: Configure Email Settings

1. Copy the template:

   ```bash
   cp config/email.php.template config/email.php
   ```

2. Edit `config/email.php` with your SMTP credentials:
   ```php
   return [
       'smtp_host' => 'smtp.gmail.com',
       'smtp_username' => 'your-email@gmail.com',
       'smtp_password' => 'your-app-password',  // NOT your regular password!
   ];
   ```

#### 📧 Gmail Setup (Recommended):

1. Go to your Google Account: https://myaccount.google.com/
2. Enable **2-Step Verification** (Security > 2-Step Verification)
3. Generate an **App Password**:
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and your device
   - Copy the 16-character password
   - Use this as `smtp_password` in config/email.php

#### 📧 Alternative SMTP Providers:

- **Outlook**: `smtp-mail.outlook.com`, port 587
- **Yahoo**: `smtp.mail.yahoo.com`, port 465
- **SendGrid**: `smtp.sendgrid.net`, port 587
- **Mailtrap** (testing): `smtp.mailtrap.io`, port 2525

### Step 3: Test the System

1. Log in as a **manager** (e.g., username: `manager_linux`)
2. Go to **Bookings** page
3. Find a booking with status "Pending"
4. Click the status button (confirm icon)
5. Confirm the status change
6. ✅ Success message appears: "Booking confirmed and contract sent to organizer via email!"
7. Check the organizer's email inbox for the contract

## 📋 How It Works

### Workflow Diagram:

```
┌─────────────────┐
│ Manager clicks  │
│ "Confirm" btn   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Status changes  │
│ pending →       │
│ confirmed       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ System detects  │
│ status change   │
└────────┬────────┘
         │
         ├─────────────────┐
         │                 │
         ▼                 ▼
┌─────────────────┐  ┌─────────────────┐
│ Generate        │  │ Save contract   │
│ Contract HTML   │  │ to database     │
└────────┬────────┘  └─────────────────┘
         │
         ▼
┌─────────────────┐
│ Send Email with │
│ PHPMailer       │
└────────┬────────┘
         │
         ├──────────┬──────────┐
         │          │          │
         ▼          ▼          ▼
    ┌────────┐  ┌──────┐  ┌────────┐
    │  To:   │  │ CC:  │  │ Body:  │
    │Organizer│  │Manager│  │Contract│
    └────────┘  └──────┘  └────────┘
```

### Database Impact:

When a booking is confirmed, a new record is inserted into `event_contracts`:

```sql
INSERT INTO event_contracts (event_id, contract_text, signed_status)
VALUES (123, '<html>...</html>', 'pending');
```

## 🎨 Contract Features

The generated contract includes:

### 📋 Header Section

- Company branding (Gatherly logo)
- Contract number (e.g., GEM-000123)
- Issue date

### 📅 Event Details

- Event name, type, and theme
- Date and time
- Expected guests
- Location details

### 🏢 Venue Information

- Venue name and address
- Capacity
- Full location details

### 👥 Parties Involved

- Organizer contact info
- Manager contact info

### 💰 Financial Summary

- Total contract amount
- Payment status
- Highlighted for visibility

### 📜 Terms & Conditions

1. Booking Confirmation
2. Payment Terms (deposit & full payment)
3. Cancellation Policy (with refund tiers)
4. Venue Usage & Damage Responsibility
5. Guest Capacity Limits
6. Setup & Cleanup Requirements
7. Force Majeure Clause
8. Amendment Process

### ✍️ Signature Blocks

- Organizer signature line
- Manager signature line
- Date fields

## 📧 Email Features

### Email Design:

- ✅ Professional HTML layout
- ✅ Responsive (mobile-friendly)
- ✅ Branded with Gatherly colors (green theme)
- ✅ Embedded contract (no attachments needed)
- ✅ Plain text fallback for old email clients

### Email Recipients:

- **To**: Organizer's email
- **CC**: Manager's email
- **From**: noreply@gatherly.com

### Email Content:

- Personalized greeting
- Event summary box
- Important next steps
- Full contract embedded below
- Professional footer

## 🔧 Customization

### Modify Contract Template:

Edit `src/services/ContractGenerator.php`:

```php
public function generateContractHTML() {
    // Customize HTML structure, styling, and content here
}
```

### Change Email Design:

Edit `src/services/EmailService.php`:

```php
private function getConfirmationEmailBody($eventData, $contractHTML) {
    // Customize email HTML and styling here
}
```

### Add More Terms:

Add new list items in ContractGenerator.php:

```php
<li><strong>Your New Term:</strong> Description here...</li>
```

## 🐛 Troubleshooting

### ❌ "Class 'PHPMailer' not found"

**Solution**:

```bash
composer install
```

### ❌ "SMTP connect() failed"

**Solutions**:

1. Check SMTP credentials in `config/email.php`
2. For Gmail: Use App Password, not regular password
3. Check firewall allows outbound port 587
4. Verify SMTP server is correct

### ❌ Email not received

**Solutions**:

1. Check spam/junk folder
2. Verify organizer's email address in database
3. Check PHP error logs: `/opt/lampp/logs/error_log`
4. Test with a different email address

### ❌ "Failed to load autoloader"

**Solution**:

```bash
composer dump-autoload
```

### 🔍 Enable Debug Mode:

Edit `config/email.php`:

```php
'debug' => 2,  // Shows SMTP conversation
```

## 🔒 Security Best Practices

1. ✅ **Never commit credentials**: `config/email.php` is in `.gitignore`
2. ✅ **Use App Passwords**: Not your main email password
3. ✅ **Enable TLS/SSL**: Encrypted SMTP connections
4. ✅ **Validate emails**: Check format before sending
5. ✅ **Rate limiting**: Prevent email spam abuse

## 📊 Database Schema

The `event_contracts` table stores generated contracts:

```sql
CREATE TABLE `event_contracts` (
  `contract_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `contract_text` text DEFAULT NULL,
  `signed_status` enum('pending','approved') DEFAULT 'pending',
  `file` longblob DEFAULT NULL,
  PRIMARY KEY (`contract_id`),
  KEY `event_id` (`event_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`)
);
```

## 📈 Future Enhancements

Possible improvements:

- [ ] PDF generation (using mPDF or TCPDF)
- [ ] Digital signatures
- [ ] Email tracking (open/read receipts)
- [ ] Scheduled reminder emails
- [ ] Multi-language support
- [ ] Email templates management
- [ ] Attachment support for venue images

## 🆘 Support

For issues:

1. Check `docs/EMAIL_CONTRACT_SETUP.md` for detailed guide
2. Review PHP error logs: `/opt/lampp/logs/error_log`
3. Test SMTP connection independently
4. Verify database schema is updated

## 📝 Testing Checklist

Before going live:

- [ ] Composer dependencies installed
- [ ] Email configuration set up
- [ ] SMTP credentials verified
- [ ] Test email sent successfully
- [ ] Contract generates correctly
- [ ] Database stores contracts
- [ ] Success messages display
- [ ] Error handling works
- [ ] Organizer receives email
- [ ] Manager receives CC
- [ ] Email appears professional

## 🎓 Learn More

- **PHPMailer GitHub**: https://github.com/PHPMailer/PHPMailer
- **PHPMailer Docs**: https://github.com/PHPMailer/PHPMailer/wiki
- **Gmail App Passwords**: https://support.google.com/accounts/answer/185833
- **SMTP Troubleshooting**: https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting

---

**Version**: 1.0.0  
**Last Updated**: December 1, 2025  
**Status**: ✅ Production Ready
