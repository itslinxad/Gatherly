# Email Contract System - Setup Guide

## Overview

This system automatically generates and emails booking contracts to organizers when a booking status is changed to "Confirmed" by the venue manager.

## Components

### 1. **PHPMailer** (from GitHub)

- Installed via Composer
- Handles email sending via SMTP
- Source: `phpmailer/phpmailer` v6.9+

### 2. **ContractGenerator.php**

- Generates professional HTML contracts
- Includes event details, venue information, terms & conditions
- Saves contracts to database (`event_contracts` table)

### 3. **EmailService.php**

- Wraps PHPMailer functionality
- Sends beautifully formatted emails with embedded contracts
- Handles errors gracefully

### 4. **Email Configuration**

- File: `config/email.php`
- Contains SMTP settings and credentials

## Installation Steps

### Step 1: Install Composer (if not already installed)

```bash
# On Linux/Mac
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Verify installation
composer --version
```

### Step 2: Install PHPMailer

Navigate to your project root and run:

```bash
cd /opt/lampp/htdocs/Gatherly-EMS_2025
composer install
```

This will:

- Download PHPMailer from GitHub
- Create a `vendor/` folder
- Generate autoload files

### Step 2: Configure Email Settings

Edit `config/email.php` with your SMTP credentials:

```php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'from_email' => 'noreply@gatherly.com',
    'from_name' => 'Gatherly Event Management System',
];
```

#### For Gmail Users:

1. Enable 2-Factor Authentication on your Google Account
2. Generate an App Password:
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and your device
   - Copy the 16-character password
   - Use this as `smtp_password`

#### For Other Providers:

- **Outlook/Hotmail**: `smtp-mail.outlook.com`, port 587, TLS
- **Yahoo**: `smtp.mail.yahoo.com`, port 465, SSL
- **SendGrid**: `smtp.sendgrid.net`, port 587, TLS
- **Mailtrap** (testing): `smtp.mailtrap.io`, port 2525

### Step 3: Test the System

1. **Test Email Configuration**:
   Create a test file: `test/test-email.php`

   ```php
   <?php
   require_once '../src/services/EmailService.php';

   $emailService = new EmailService();
   $result = $emailService->sendEmail(
       'test@example.com',
       'Test User',
       'Test Email',
       '<h1>Test</h1><p>This is a test email.</p>',
       'Test - This is a test email.'
   );

   if ($result) {
       echo "Email sent successfully!";
   } else {
       echo "Failed to send email.";
   }
   ```

2. **Test Contract Generation**:
   - Log in as a manager
   - Go to Bookings page
   - Click the status button for a pending booking
   - Confirm the status change to "Confirmed"
   - Check the organizer's email inbox

## How It Works

### Workflow:

1. **Manager** updates booking status from "Pending" to "Confirmed"
2. **System** detects the status change
3. **ContractGenerator** creates an HTML contract with:
   - Event details
   - Venue information
   - Financial summary
   - Terms and conditions
   - Signature blocks
4. **EmailService** sends the contract to:
   - **To**: Organizer's email
   - **CC**: Manager's email
5. **Database** stores the contract in `event_contracts` table
6. **Notification** displays success/warning message to manager

### Email Features:

- Professional HTML design
- Embedded contract (no attachments)
- Plain text fallback
- Responsive layout
- Branded with Gatherly colors

## Database Schema

The contract is saved to the `event_contracts` table:

```sql
CREATE TABLE `event_contracts` (
  `contract_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `contract_text` text DEFAULT NULL,
  `signed_status` enum('pending','approved') DEFAULT 'pending',
  `file` longblob DEFAULT NULL,
  PRIMARY KEY (`contract_id`)
);
```

## Troubleshooting

### Issue: "Class 'PHPMailer' not found"

**Solution**: Run `composer install` in the project root

### Issue: "SMTP connect() failed"

**Solutions**:

- Check SMTP credentials in `config/email.php`
- Verify firewall allows outbound connections on SMTP port
- For Gmail: Use App Password, not regular password
- Check `error_log` for detailed error messages

### Issue: Email not received

**Solutions**:

- Check spam/junk folder
- Verify recipient email address is correct
- Check email logs in `error_log`
- Test with a different email provider

### Issue: "Failed to load class autoloader"

**Solution**: Ensure `vendor/autoload.php` exists:

```bash
composer dump-autoload
```

## Security Recommendations

1. **Never commit email credentials to Git**:

   - Add `config/email.php` to `.gitignore`
   - Use environment variables in production

2. **Use App Passwords** instead of main account passwords

3. **Enable SSL/TLS** for SMTP connections

4. **Validate email addresses** before sending

5. **Rate limit** email sending to prevent abuse

## Customization

### Modify Contract Template:

Edit `src/services/ContractGenerator.php`, method `generateContractHTML()`

### Change Email Design:

Edit `src/services/EmailService.php`, method `getConfirmationEmailBody()`

### Add Attachments:

```php
$this->mailer->addAttachment('/path/to/file.pdf', 'Contract.pdf');
```

### Send to Multiple Recipients:

```php
$this->mailer->addCC('cc@example.com', 'CC Name');
$this->mailer->addBCC('bcc@example.com', 'BCC Name');
```

## Production Deployment

1. Update `config/email.php` with production credentials
2. Set `debug` to 0 in email config
3. Use environment variables for sensitive data
4. Set up email logging and monitoring
5. Configure SPF, DKIM, and DMARC records for your domain
6. Test thoroughly before going live

## Support

For issues or questions:

- Check Laravel/PHPMailer documentation
- Review error logs: `error_log`
- Test SMTP connection independently
- Verify database schema is up to date

---

**Last Updated**: December 1, 2025
**Version**: 1.0.0
