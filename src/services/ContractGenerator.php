<?php

/**
 * Contract Generator Service
 * Generates booking contracts for confirmed events
 */

require_once __DIR__ . '/dbconnect.php';

class ContractGenerator
{
    private $conn;
    private $event_id;
    private $event_data;

    public function __construct($event_id)
    {
        global $conn;
        $this->conn = $conn;
        $this->event_id = $event_id;
        $this->loadEventData();
    }

    private function loadEventData()
    {
        $stmt = $this->conn->prepare("
            SELECT 
                e.*,
                v.venue_name,
                v.capacity as venue_capacity,
                v.description as venue_description,
                CONCAT(l.baranggay, ', ', l.city, ', ', l.province) as venue_address,
                CONCAT(l.city, ', ', l.province) as venue_location,
                CONCAT(org.first_name, ' ', org.last_name) as organizer_name,
                org.email as organizer_email,
                org.phone as organizer_phone,
                CONCAT(mgr.first_name, ' ', mgr.last_name) as manager_name,
                mgr.email as manager_email,
                mgr.phone as manager_phone,
                p.base_price,
                p.weekday_price,
                p.weekend_price,
                p.peak_price
            FROM events e
            LEFT JOIN venues v ON e.venue_id = v.venue_id
            LEFT JOIN locations l ON v.location_id = l.location_id
            LEFT JOIN users org ON e.organizer_id = org.user_id
            LEFT JOIN users mgr ON e.manager_id = mgr.user_id
            LEFT JOIN prices p ON v.venue_id = p.venue_id
            WHERE e.event_id = ?
        ");

        $stmt->bind_param("i", $this->event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->event_data = $result->fetch_assoc();
        $stmt->close();
    }

    public function generateContractHTML()
    {
        if (!$this->event_data) {
            return false;
        }

        $data = $this->event_data;
        $contract_date = date('F d, Y');
        $event_date = date('F d, Y', strtotime($data['event_date']));
        $time_start = date('g:i A', strtotime($data['time_start']));
        $time_end = date('g:i A', strtotime($data['time_end']));

        $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Booking Contract - ' . htmlspecialchars($data['event_name']) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; line-height: 1.6; color: #333; background: #fff; padding: 40px 60px; }
        .container { max-width: 800px; margin: 0 auto; background: white; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #059669; padding-bottom: 20px; }
        .header h1 { font-size: 28px; color: #059669; margin-bottom: 5px; font-weight: bold; }
        .header p { font-size: 14px; color: #666; }
        .contract-number { text-align: right; margin-bottom: 20px; font-size: 12px; color: #666; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: bold; color: #059669; margin-bottom: 10px; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; text-transform: uppercase; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .info-item { margin-bottom: 8px; }
        .info-label { font-weight: bold; color: #555; font-size: 13px; }
        .info-value { color: #333; font-size: 14px; margin-left: 5px; }
        .terms { margin-top: 20px; }
        .terms ol { margin-left: 25px; margin-top: 10px; }
        .terms li { margin-bottom: 12px; font-size: 13px; line-height: 1.7; }
        .financial-summary { background: #f9fafb; padding: 20px; border-radius: 8px; border: 2px solid #e5e7eb; margin: 20px 0; }
        .total-amount { font-size: 24px; font-weight: bold; color: #059669; text-align: right; margin-top: 10px; }
        .signatures { margin-top: 50px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .signature-block { text-align: center; }
        .signature-line { border-top: 2px solid #333; margin-top: 60px; padding-top: 10px; font-size: 13px; }
        .signature-label { font-weight: bold; margin-top: 5px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e5e7eb; text-align: center; font-size: 11px; color: #666; }
        .highlight { background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin: 15px 0; font-size: 13px; }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 GATHERLY EVENT MANAGEMENT SYSTEM</h1>
            <p>Event Booking Contract</p>
        </div>

        <div class="contract-number">
            <strong>Contract No:</strong> GEM-' . str_pad($data['event_id'], 6, '0', STR_PAD_LEFT) . '<br>
            <strong>Date Issued:</strong> ' . $contract_date . '
        </div>

        <div class="section">
            <div class="section-title">📋 Event Details</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Event Name:</span>
                    <span class="info-value">' . htmlspecialchars($data['event_name']) . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Event Type:</span>
                    <span class="info-value">' . htmlspecialchars($data['event_type']) . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Event Date:</span>
                    <span class="info-value">' . $event_date . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Event Time:</span>
                    <span class="info-value">' . $time_start . ' - ' . $time_end . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Expected Guests:</span>
                    <span class="info-value">' . number_format($data['expected_guests']) . ' persons</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Theme:</span>
                    <span class="info-value">' . htmlspecialchars($data['theme'] ?? 'N/A') . '</span>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">🏢 Venue Information</div>
            <div class="info-item">
                <span class="info-label">Venue Name:</span>
                <span class="info-value">' . htmlspecialchars($data['venue_name']) . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Location:</span>
                <span class="info-value">' . htmlspecialchars($data['venue_address']) . '</span>
            </div>
            <div class="info-item">
                <span class="info-label">Capacity:</span>
                <span class="info-value">' . number_format($data['venue_capacity']) . ' persons</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">👤 Parties Involved</div>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Organizer Name:</span>
                        <span class="info-value">' . htmlspecialchars($data['organizer_name']) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value">' . htmlspecialchars($data['organizer_email']) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">' . htmlspecialchars($data['organizer_phone']) . '</span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Manager Name:</span>
                        <span class="info-value">' . htmlspecialchars($data['manager_name']) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value">' . htmlspecialchars($data['manager_email']) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">' . htmlspecialchars($data['manager_phone']) . '</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="financial-summary">
            <div class="section-title">💰 Financial Summary</div>
            <div class="info-item">
                <span class="info-label">Total Contract Amount:</span>
                <div class="total-amount">₱' . number_format($data['total_cost'], 2) . '</div>
            </div>
            <div class="info-item" style="margin-top: 10px; font-size: 13px;">
                <span class="info-label">Payment Status:</span>
                <span class="info-value">' . ucfirst($data['payment_status']) . '</span>
            </div>
        </div>

        <div class="highlight">
            <strong>⚠️ Important Notice:</strong> This booking has been confirmed. Please review all details carefully. Any changes must be requested at least 7 days before the event date.
        </div>

        <div class="section terms">
            <div class="section-title">📜 Terms and Conditions</div>
            <ol>
                <li><strong>Booking Confirmation:</strong> This contract confirms the booking of the specified venue for the event date and time mentioned above. The organizer agrees to comply with all venue rules and regulations.</li>
                
                <li><strong>Payment Terms:</strong> 
                    <ul style="margin-top: 5px; margin-left: 20px;">
                        <li>A deposit of 50% of the total amount is required to secure the booking</li>
                        <li>Full payment must be settled at least 3 days before the event date</li>
                        <li>Accepted payment methods: Bank Transfer, GCash, Cash</li>
                    </ul>
                </li>
                
                <li><strong>Cancellation Policy:</strong> 
                    <ul style="margin-top: 5px; margin-left: 20px;">
                        <li>Cancellations made 30+ days before event: 100% refund (minus processing fee)</li>
                        <li>Cancellations made 15-29 days before: 50% refund</li>
                        <li>Cancellations made less than 14 days before: No refund</li>
                    </ul>
                </li>
                
                <li><strong>Venue Usage:</strong> The organizer is responsible for any damage to the venue or its property during the event. Additional charges may apply for damages beyond normal wear and tear.</li>
                
                <li><strong>Guest Capacity:</strong> The number of guests must not exceed the venue\'s maximum capacity of ' . number_format($data['venue_capacity']) . ' persons for safety and compliance reasons.</li>
                
                <li><strong>Setup and Cleanup:</strong> The organizer must ensure that the venue is left in good condition. Additional cleanup fees may apply if the venue is not properly cleaned after the event.</li>
                
                <li><strong>Force Majeure:</strong> Neither party shall be liable for failure to perform due to events beyond their reasonable control, including natural disasters, government actions, or pandemics.</li>
                
                <li><strong>Amendments:</strong> Any changes to this contract must be made in writing and agreed upon by both parties at least 7 days before the event date.</li>
            </ol>
        </div>

        <div class="signatures">
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signature-label">' . htmlspecialchars($data['organizer_name']) . '</div>
                    <div style="font-size: 12px; color: #666;">Event Organizer</div>
                    <div style="font-size: 11px; color: #999; margin-top: 3px;">Date: _______________</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signature-label">' . htmlspecialchars($data['manager_name']) . '</div>
                    <div style="font-size: 12px; color: #666;">Venue Manager</div>
                    <div style="font-size: 11px; color: #999; margin-top: 3px;">Date: _______________</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>Gatherly Event Management System</strong></p>
            <p>This is a system-generated contract. For inquiries, please contact us through the platform.</p>
            <p style="margin-top: 10px;">Generated on ' . date('F d, Y \a\t g:i A') . '</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    public function getEventData()
    {
        return $this->event_data;
    }

    public function saveContractToDatabase($contract_html)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO event_contracts (event_id, contract_text, signed_status, file) 
            VALUES (?, ?, 'pending', NULL)
            ON DUPLICATE KEY UPDATE 
            contract_text = VALUES(contract_text),
            signed_status = 'pending'
        ");

        $stmt->bind_param("is", $this->event_id, $contract_html);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}
