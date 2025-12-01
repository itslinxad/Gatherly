<?php

/**
 * Email Service using PHPMailer
 * Handles sending emails including booking confirmation contracts
 */

// Load PHPMailer from GitHub repository
require_once __DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/ContractGenerator.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mailer;
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/email.php';
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }

    private function configureSMTP()
    {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = $this->config['smtp_auth'];
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_secure'];
            $this->mailer->Port = $this->config['smtp_port'];
            $this->mailer->CharSet = $this->config['charset'];

            // Debug mode
            if ($this->config['debug'] > 0) {
                $this->mailer->SMTPDebug = $this->config['debug'];
                $this->mailer->Debugoutput = 'html';
            }

            // Default sender
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send booking confirmation contract to organizer
     */
    public function sendBookingConfirmation($event_id)
    {
        try {
            // Generate contract
            $contractGen = new ContractGenerator($event_id);
            $contractHTML = $contractGen->generateContractHTML();
            $eventData = $contractGen->getEventData();

            if (!$contractHTML || !$eventData) {
                throw new Exception("Failed to generate contract for event ID: $event_id");
            }

            // Save contract to database
            $contractGen->saveContractToDatabase($contractHTML);

            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Recipients
            $this->mailer->addAddress($eventData['organizer_email'], $eventData['organizer_name']);

            // CC to manager
            if (!empty($eventData['manager_email'])) {
                $this->mailer->addCC($eventData['manager_email'], $eventData['manager_name']);
            }

            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = '🎉 Booking Confirmed - ' . $eventData['event_name'] . ' | Gatherly';

            // Email body with contract
            $this->mailer->Body = $this->getConfirmationEmailBody($eventData, $contractHTML);

            // Plain text version
            $this->mailer->AltBody = $this->getConfirmationEmailPlainText($eventData);

            // Send email
            $result = $this->mailer->send();

            // Log success
            error_log("Booking confirmation email sent successfully for Event ID: $event_id to " . $eventData['organizer_email']);

            return [
                'success' => true,
                'message' => 'Confirmation email sent successfully',
                'recipient' => $eventData['organizer_email']
            ];
        } catch (Exception $e) {
            error_log("Email sending failed for Event ID $event_id: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate email body with contract embedded
     */
    private function getConfirmationEmailBody($eventData, $contractHTML)
    {
        $event_date = date('F d, Y', strtotime($eventData['event_date']));
        $time_start = date('g:i A', strtotime($eventData['time_start']));
        $time_end = date('g:i A', strtotime($eventData['time_end']));

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px 0;">
        <tr>
            <td align="center">
                <!-- Header -->
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 10px 10px 0 0; padding: 30px; color: white;">
                    <tr>
                        <td align="center">
                            <h1 style="margin: 0; font-size: 28px; font-weight: bold;">🎉 Booking Confirmed!</h1>
                            <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">Your event has been successfully confirmed</p>
                        </td>
                    </tr>
                </table>

                <!-- Content -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; padding: 40px 30px;">
                    <tr>
                        <td>
                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #374151; line-height: 1.6;">
                                Dear <strong>' . htmlspecialchars($eventData['organizer_name']) . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #374151; line-height: 1.6;">
                                Great news! Your booking for <strong>' . htmlspecialchars($eventData['event_name']) . '</strong> has been confirmed by the venue manager.
                            </p>
                            
                            <!-- Event Summary Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-left: 4px solid #059669; padding: 20px; margin: 20px 0; border-radius: 5px;">
                                <tr>
                                    <td>
                                        <h3 style="margin: 0 0 15px 0; color: #059669; font-size: 18px;">📅 Event Summary</h3>
                                        <p style="margin: 5px 0; font-size: 14px; color: #4b5563;"><strong>Event:</strong> ' . htmlspecialchars($eventData['event_name']) . '</p>
                                        <p style="margin: 5px 0; font-size: 14px; color: #4b5563;"><strong>Date:</strong> ' . $event_date . '</p>
                                        <p style="margin: 5px 0; font-size: 14px; color: #4b5563;"><strong>Time:</strong> ' . $time_start . ' - ' . $time_end . '</p>
                                        <p style="margin: 5px 0; font-size: 14px; color: #4b5563;"><strong>Venue:</strong> ' . htmlspecialchars($eventData['venue_name']) . '</p>
                                        <p style="margin: 5px 0; font-size: 14px; color: #4b5563;"><strong>Location:</strong> ' . htmlspecialchars($eventData['venue_location']) . '</p>
                                        <p style="margin: 10px 0 5px 0; font-size: 18px; color: #059669;"><strong>Total Amount:</strong> ₱' . number_format($eventData['total_cost'], 2) . '</p>
                                    </td>
                                </tr>
                            </table>

                            <h3 style="margin: 30px 0 15px 0; color: #1f2937; font-size: 18px;">📄 Your Booking Contract</h3>
                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                Please find your booking contract attached below. Review all terms and conditions carefully. If you have any questions or need to make changes, please contact us at least 7 days before your event date.
                            </p>

                            <!-- Important Notice Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 5px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.6;">
                                            <strong>⚠️ Next Steps:</strong><br>
                                            1. Review your contract below<br>
                                            2. Ensure payment is completed as per the payment terms<br>
                                            3. Contact the venue manager if you have any questions<br>
                                            4. Keep this email for your records
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 20px 0; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                Thank you for choosing Gatherly! We look forward to making your event a success.
                            </p>
                            
                            <p style="margin: 20px 0 0 0; font-size: 14px; color: #6b7280;">
                                Best regards,<br>
                                <strong style="color: #059669;">Gatherly Event Management Team</strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Contract Divider -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #059669; padding: 15px 30px;">
                    <tr>
                        <td align="center">
                            <p style="margin: 0; color: white; font-size: 16px; font-weight: bold;">📋 OFFICIAL BOOKING CONTRACT</p>
                        </td>
                    </tr>
                </table>

                <!-- Embedded Contract -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white;">
                    <tr>
                        <td>
                            ' . $contractHTML . '
                        </td>
                    </tr>
                </table>

                <!-- Footer -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #1f2937; border-radius: 0 0 10px 10px; padding: 20px 30px;">
                    <tr>
                        <td align="center">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                This is an automated email from Gatherly Event Management System<br>
                                Please do not reply to this email. For support, contact us through the platform.
                            </p>
                            <p style="margin: 10px 0 0 0; color: #6b7280; font-size: 11px;">
                                © ' . date('Y') . ' Gatherly. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Plain text version of confirmation email
     */
    private function getConfirmationEmailPlainText($eventData)
    {
        $event_date = date('F d, Y', strtotime($eventData['event_date']));
        $time_start = date('g:i A', strtotime($eventData['time_start']));
        $time_end = date('g:i A', strtotime($eventData['time_end']));

        return "
BOOKING CONFIRMED - Gatherly Event Management System

Dear " . $eventData['organizer_name'] . ",

Great news! Your booking for " . $eventData['event_name'] . " has been confirmed.

EVENT SUMMARY:
--------------
Event: " . $eventData['event_name'] . "
Date: " . $event_date . "
Time: " . $time_start . " - " . $time_end . "
Venue: " . $eventData['venue_name'] . "
Location: " . $eventData['venue_location'] . "
Total Amount: ₱" . number_format($eventData['total_cost'], 2) . "

Your official booking contract is included in the HTML version of this email. 
Please review all terms and conditions carefully.

NEXT STEPS:
1. Review your contract
2. Complete payment as per the payment terms
3. Contact the venue manager if you have any questions

Thank you for choosing Gatherly!

Best regards,
Gatherly Event Management Team

---
This is an automated email. Please do not reply.
© " . date('Y') . " Gatherly. All rights reserved.
        ";
    }

    /**
     * Send a generic email
     */
    public function sendEmail($to, $toName, $subject, $htmlBody, $plainBody = '')
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($to, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $plainBody ?: strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
}