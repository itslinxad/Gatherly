-- Populate dummy data for Gatherly-EMS database
-- Generated: November 30, 2025
-- Purpose: Fill empty tables with realistic test data for analytics and chart testing

USE sad_db;

-- Clear existing data from tables we're about to populate
TRUNCATE TABLE chat;
TRUNCATE TABLE event_contracts;
TRUNCATE TABLE event_payments;
TRUNCATE TABLE pricing_demand_forecast;
TRUNCATE TABLE pricing_market_analysis;

-- ============================================================================
-- CHAT TABLE - Conversations between organizers and managers
-- ============================================================================

INSERT INTO `chat` (`chat_id`, `sender_id`, `receiver_id`, `message_text`, `timestamp`, `file_url`, `is_file`, `is_read`) VALUES
-- Conversation 1: Organizer 6 (Adrian) and Manager 2 (Linux) about event planning
(1, 6, 2, 'Hi! I\'m interested in booking Shepherd\'s Events Garden for my 18th birthday.', '2025-11-25 09:00:00', NULL, 0, 1),
(2, 2, 6, 'Hello Adrian! Great choice. The garden is available. When are you planning the event?', '2025-11-25 09:15:00', NULL, 0, 1),
(3, 6, 2, 'I\'m looking at December 15, 2025. Can you accommodate 300 guests?', '2025-11-25 09:20:00', NULL, 0, 1),
(4, 2, 6, 'Yes, absolutely! Our capacity is perfect for 300 guests. Let me prepare a quote for you.', '2025-11-25 09:30:00', NULL, 0, 1),
(5, 6, 2, 'Perfect! What amenities are included in the package?', '2025-11-25 10:00:00', NULL, 0, 1),
(6, 2, 6, 'We include Wi-Fi, Security, Parking, Garden Setup, and Outdoor Seating. Air conditioning is available for an additional fee.', '2025-11-25 10:15:00', NULL, 0, 1),
(7, 6, 2, 'Sounds great! I\'ll confirm by tomorrow.', '2025-11-25 11:00:00', NULL, 0, 1),

-- Conversation 2: Organizer 9 (Maricris) and Manager 2 (Linux) about corporate event
(8, 9, 2, 'Good morning! I need a venue for a corporate team building event.', '2025-11-26 08:00:00', NULL, 0, 1),
(9, 2, 9, 'Good morning! How many participants are you expecting?', '2025-11-26 08:30:00', NULL, 0, 1),
(10, 9, 2, 'Around 150-200 people. We need a venue with good outdoor space.', '2025-11-26 09:00:00', NULL, 0, 1),
(11, 2, 9, 'I recommend Lima Park Hotel Batangas. It has excellent outdoor facilities and can accommodate 200 guests.', '2025-11-26 09:45:00', NULL, 0, 1),
(12, 9, 2, 'What\'s the pricing like?', '2025-11-26 10:00:00', NULL, 0, 1),
(13, 2, 9, 'For a weekday event, the rate is ₱42,000. Weekend is ₱50,000.', '2025-11-26 10:30:00', NULL, 0, 1),
(14, 9, 2, 'That works with our budget. Can I schedule a site visit?', '2025-11-26 11:00:00', NULL, 0, 0),

-- Conversation 3: Organizer 6 (Adrian) and Manager 8 (Dore) about wedding
(15, 6, 8, 'Hi! I\'m planning a wedding for next year. Do you have any available venues?', '2025-11-27 14:00:00', NULL, 0, 1),
(16, 8, 6, 'Hello! Congratulations on your upcoming wedding! Yes, we have several beautiful venues. How many guests?', '2025-11-27 14:30:00', NULL, 0, 1),
(17, 6, 8, 'We\'re expecting around 250-300 guests.', '2025-11-27 15:00:00', NULL, 0, 1),
(18, 8, 6, 'Perfect! Our venues can accommodate that. What\'s your preferred date and location?', '2025-11-27 15:30:00', NULL, 0, 1),
(19, 6, 8, 'We\'re looking at June 2026, preferably in Batangas City or Tagaytay.', '2025-11-27 16:00:00', NULL, 0, 1),
(20, 8, 6, 'Wonderful! I\'ll send you a list of available venues with pricing and photos.', '2025-11-27 16:30:00', NULL, 0, 0),

-- Conversation 4: Follow-up messages
(21, 9, 2, 'Hi again! I\'d like to proceed with the booking for Lima Park Hotel.', '2025-11-28 09:00:00', NULL, 0, 0),
(22, 6, 2, 'I\'ve discussed with my family and we\'d like to book the venue. How do we proceed?', '2025-11-28 10:00:00', NULL, 0, 0),
(23, 6, 8, 'Thank you! Looking forward to seeing the options.', '2025-11-28 11:00:00', NULL, 0, 0);

-- ============================================================================
-- EVENT_CONTRACTS TABLE - Contract documents for events
-- ============================================================================

INSERT INTO `event_contracts` (`contract_id`, `event_id`, `contract_text`, `signed_status`, `file`) VALUES
-- Contracts for completed events (events 3-9, 11-14, 16-17, 19-20, 22-28)
(1, 3, 'This contract is for the Summer Wedding event scheduled on September 30, 2025. Total cost: ₱85,000. Terms and conditions apply.', 'approved', NULL),
(2, 4, 'This contract is for the Corporate Gala event scheduled on August 30, 2025. Total cost: ₱75,000. Terms and conditions apply.', 'approved', NULL),
(3, 5, 'This contract is for the Birthday Party event scheduled on June 30, 2025. Total cost: ₱55,000. Terms and conditions apply.', 'approved', NULL),
(4, 7, 'This contract is for the Spring Wedding event scheduled on April 30, 2025. Total cost: ₱78,000. Terms and conditions apply.', 'approved', NULL),
(5, 8, 'This contract is for the Product Launch event scheduled on February 28, 2025. Total cost: ₱68,000. Terms and conditions apply.', 'approved', NULL),
(6, 9, 'This contract is for the Anniversary Party event scheduled on December 30, 2024. Total cost: ₱72,000. Terms and conditions apply.', 'approved', NULL),
(7, 11, 'This contract is for the Team Building event scheduled on July 30, 2025. Total cost: ₱45,000. Terms and conditions apply.', 'approved', NULL),
(8, 12, 'This contract is for the Sweet 16 Party event scheduled on May 30, 2025. Total cost: ₱42,000. Terms and conditions apply.', 'approved', NULL),
(9, 13, 'This contract is for the Christmas Party event scheduled on September 30, 2025. Total cost: ₱58,000. Terms and conditions apply.', 'approved', NULL),
(10, 14, 'This contract is for the Golden Anniversary event scheduled on March 30, 2025. Total cost: ₱55,000. Terms and conditions apply.', 'approved', NULL),
(11, 15, 'This contract is for the Intimate Wedding event scheduled on August 30, 2025. Total cost: ₱48,000. Terms and conditions apply.', 'approved', NULL),
(12, 16, 'This contract is for the Retirement Party event scheduled on May 30, 2025. Total cost: ₱38,000. Terms and conditions apply.', 'approved', NULL),
(13, 17, 'This contract is for the Graduation Party event scheduled on January 30, 2025. Total cost: ₱42,000. Terms and conditions apply.', 'approved', NULL),
(14, 19, 'This contract is for the Corporate Summit event scheduled on August 30, 2025. Total cost: ₱110,000. Terms and conditions apply.', 'approved', NULL),
(15, 20, 'This contract is for the Luxury Gala event scheduled on June 30, 2025. Total cost: ₱95,000. Terms and conditions apply.', 'approved', NULL),
(16, 21, 'This contract is for the NYE Gala event scheduled on September 30, 2025. Total cost: ₱135,000. Terms and conditions apply.', 'approved', NULL),
(17, 22, 'This contract is for the Executive Meeting event scheduled on April 30, 2025. Total cost: ₱105,000. Terms and conditions apply.', 'approved', NULL),
(18, 23, 'This contract is for the Premium Wedding event scheduled on February 28, 2025. Total cost: ₱118,000. Terms and conditions apply.', 'approved', NULL),
(19, 24, 'This contract is for the Garden Wedding event scheduled on September 30, 2025. Total cost: ₱88,000. Terms and conditions apply.', 'approved', NULL),
(20, 25, 'This contract is for the Company Outing event scheduled on July 30, 2025. Total cost: ₱62,000. Terms and conditions apply.', 'approved', NULL),
(21, 26, 'This contract is for the Debut Party event scheduled on May 30, 2025. Total cost: ₱58,000. Terms and conditions apply.', 'approved', NULL),
(22, 27, 'This contract is for the Summer Festival event scheduled on March 30, 2025. Total cost: ₱92,000. Terms and conditions apply.', 'approved', NULL),
(23, 28, 'This contract is for the Wedding Reception event scheduled on December 30, 2024. Total cost: ₱82,000. Terms and conditions apply.', 'approved', NULL),

-- Contracts for confirmed events (events 6, 10, 18)
(24, 6, 'This contract is for the New Year Party event scheduled on October 30, 2025. Total cost: ₱95,000. Terms and conditions apply.', 'approved', NULL),
(25, 10, 'This contract is for the Elegant Wedding event scheduled on October 30, 2025. Total cost: ₱65,000. Terms and conditions apply.', 'approved', NULL),
(26, 18, 'This contract is for the Grand Wedding event scheduled on October 30, 2025. Total cost: ₱125,000. Terms and conditions apply.', 'approved', NULL),

-- Contracts for pending events (events 1, 2)
(27, 1, 'This contract is for the 18th Birthday event scheduled on November 30, 2025. Total cost: ₱57,000. Awaiting customer signature.', 'pending', NULL),
(28, 2, 'This contract is for the Test event scheduled on November 30, 2025. Total cost: ₱70,000. Awaiting customer signature.', 'pending', NULL);

-- ============================================================================
-- EVENT_PAYMENTS TABLE - Payment records for events
-- ============================================================================

INSERT INTO `event_payments` (`payment_id`, `event_id`, `amount_paid`, `payment_type`, `payment_method`, `reference_no`, `payment_status`, `payment_date`, `verified_by`, `verified_at`, `notes`) VALUES
-- Completed events - Full payments
(1, 3, 85000.00, 'full', 'bank_transfer', 'TXN20250825001', 'verified', '2025-08-25 14:30:00', 1, '2025-08-25 15:00:00', 'Full payment for Summer Wedding'),
(2, 4, 75000.00, 'full', 'gcash', 'GC20250725001', 'verified', '2025-07-25 16:20:00', 1, '2025-07-25 17:00:00', 'Full payment for Corporate Gala'),
(3, 5, 55000.00, 'full', 'bank_transfer', 'TXN20250525001', 'verified', '2025-05-25 10:15:00', 1, '2025-05-25 11:00:00', 'Full payment for Birthday Party'),

-- Split payments (downpayment + partial)
(4, 7, 39000.00, 'downpayment', 'bank_transfer', 'TXN20250325001', 'verified', '2025-03-25 09:00:00', 1, '2025-03-25 10:00:00', '50% downpayment for Spring Wedding'),
(5, 7, 39000.00, 'partial', 'gcash', 'GC20250420001', 'verified', '2025-04-20 14:00:00', 1, '2025-04-20 15:00:00', 'Remaining balance for Spring Wedding'),

(6, 8, 34000.00, 'downpayment', 'bank_transfer', 'TXN20250125001', 'verified', '2025-01-25 11:30:00', 1, '2025-01-25 12:00:00', '50% downpayment for Product Launch'),
(7, 8, 34000.00, 'partial', 'paymaya', 'PM20250220001', 'verified', '2025-02-20 13:00:00', 1, '2025-02-20 14:00:00', 'Remaining balance for Product Launch'),

(8, 9, 72000.00, 'full', 'bank_transfer', 'TXN20241125001', 'verified', '2024-11-25 15:45:00', 1, '2024-11-25 16:00:00', 'Full payment for Anniversary Party'),

(9, 11, 45000.00, 'full', 'gcash', 'GC20250625001', 'verified', '2025-06-25 10:00:00', 1, '2025-06-25 11:00:00', 'Full payment for Team Building'),

(10, 12, 21000.00, 'downpayment', 'bank_transfer', 'TXN20250425001', 'verified', '2025-04-25 14:00:00', 1, '2025-04-25 15:00:00', '50% downpayment for Sweet 16 Party'),
(11, 12, 21000.00, 'partial', 'gcash', 'GC20250520001', 'verified', '2025-05-20 09:00:00', 1, '2025-05-20 10:00:00', 'Remaining balance for Sweet 16 Party'),

(12, 13, 58000.00, 'full', 'bank_transfer', 'TXN20250825002', 'verified', '2025-08-25 16:30:00', 1, '2025-08-25 17:00:00', 'Full payment for Christmas Party'),

(13, 14, 55000.00, 'full', 'gcash', 'GC20250223001', 'verified', '2025-02-23 11:00:00', 1, '2025-02-23 12:00:00', 'Full payment for Golden Anniversary'),

(14, 15, 48000.00, 'full', 'bank_transfer', 'TXN20250725002', 'verified', '2025-07-25 13:30:00', 1, '2025-07-25 14:00:00', 'Full payment for Intimate Wedding'),

(15, 16, 38000.00, 'full', 'paymaya', 'PM20250425001', 'verified', '2025-04-25 10:30:00', 1, '2025-04-25 11:00:00', 'Full payment for Retirement Party'),

(16, 17, 42000.00, 'full', 'bank_transfer', 'TXN20241225001', 'verified', '2024-12-25 15:00:00', 1, '2024-12-25 16:00:00', 'Full payment for Graduation Party'),

-- Large events with split payments
(17, 19, 55000.00, 'downpayment', 'bank_transfer', 'TXN20250725003', 'verified', '2025-07-25 09:00:00', 1, '2025-07-25 10:00:00', '50% downpayment for Corporate Summit'),
(18, 19, 55000.00, 'partial', 'bank_transfer', 'TXN20250820001', 'verified', '2025-08-20 14:00:00', 1, '2025-08-20 15:00:00', 'Remaining balance for Corporate Summit'),

(19, 20, 47500.00, 'downpayment', 'gcash', 'GC20250525002', 'verified', '2025-05-25 11:00:00', 1, '2025-05-25 12:00:00', '50% downpayment for Luxury Gala'),
(20, 20, 47500.00, 'partial', 'bank_transfer', 'TXN20250620001', 'verified', '2025-06-20 13:00:00', 1, '2025-06-20 14:00:00', 'Remaining balance for Luxury Gala'),

(21, 21, 67500.00, 'downpayment', 'bank_transfer', 'TXN20250825003', 'verified', '2025-08-25 10:00:00', 1, '2025-08-25 11:00:00', '50% downpayment for NYE Gala'),
(22, 21, 67500.00, 'partial', 'gcash', 'GC20250920001', 'verified', '2025-09-20 15:00:00', 1, '2025-09-20 16:00:00', 'Remaining balance for NYE Gala'),

(23, 22, 105000.00, 'full', 'bank_transfer', 'TXN20250325002', 'verified', '2025-03-25 14:00:00', 1, '2025-03-25 15:00:00', 'Full payment for Executive Meeting'),

(24, 23, 59000.00, 'downpayment', 'bank_transfer', 'TXN20250125002', 'verified', '2025-01-25 09:30:00', 1, '2025-01-25 10:00:00', '50% downpayment for Premium Wedding'),
(25, 23, 59000.00, 'partial', 'gcash', 'GC20250220002', 'verified', '2025-02-20 11:00:00', 1, '2025-02-20 12:00:00', 'Remaining balance for Premium Wedding'),

(26, 24, 88000.00, 'full', 'bank_transfer', 'TXN20250825004', 'verified', '2025-08-25 13:00:00', 1, '2025-08-25 14:00:00', 'Full payment for Garden Wedding'),

(27, 25, 62000.00, 'full', 'gcash', 'GC20250625002', 'verified', '2025-06-25 10:30:00', 1, '2025-06-25 11:00:00', 'Full payment for Company Outing'),

(28, 26, 29000.00, 'downpayment', 'bank_transfer', 'TXN20250425002', 'verified', '2025-04-25 14:30:00', 1, '2025-04-25 15:00:00', '50% downpayment for Debut Party'),
(29, 26, 29000.00, 'partial', 'paymaya', 'PM20250520001', 'verified', '2025-05-20 10:00:00', 1, '2025-05-20 11:00:00', 'Remaining balance for Debut Party'),

(30, 27, 92000.00, 'full', 'bank_transfer', 'TXN20250223002', 'verified', '2025-02-23 15:00:00', 1, '2025-02-23 16:00:00', 'Full payment for Summer Festival'),

(31, 28, 82000.00, 'full', 'gcash', 'GC20241125001', 'verified', '2024-11-25 12:00:00', 1, '2024-11-25 13:00:00', 'Full payment for Wedding Reception'),

-- Confirmed events - Downpayments paid
(32, 6, 47500.00, 'downpayment', 'bank_transfer', 'TXN20250925001', 'verified', '2025-09-25 10:00:00', 1, '2025-09-25 11:00:00', '50% downpayment for New Year Party'),
(33, 10, 32500.00, 'downpayment', 'gcash', 'GC20250925001', 'verified', '2025-09-25 14:00:00', 1, '2025-09-25 15:00:00', '50% downpayment for Elegant Wedding'),
(34, 18, 62500.00, 'downpayment', 'bank_transfer', 'TXN20250925002', 'verified', '2025-09-25 16:00:00', 1, '2025-09-25 17:00:00', '50% downpayment for Grand Wedding');

-- Pending events - Awaiting payment (no actual payment records created yet for pending status)
-- Events 1 and 2 will have payment records created when they make initial payment

-- ============================================================================
-- PRICING_DEMAND_FORECAST TABLE - ML demand predictions by month
-- ============================================================================

INSERT INTO `pricing_demand_forecast` (`forecast_id`, `venue_id`, `month`, `year`, `predicted_bookings`, `predicted_revenue`, `confidence_score`, `created_at`) VALUES
-- Venue 1 (Shepherd's Events Garden) - Historical and future forecasts
(1, 1, 1, 2025, 2.00, 100000.00, 0.7800, '2024-12-15 10:00:00'),
(2, 1, 2, 2025, 1.00, 55000.00, 0.7500, '2025-01-15 10:00:00'),
(3, 1, 3, 2025, 1.00, 50000.00, 0.7200, '2025-02-15 10:00:00'),
(4, 1, 4, 2025, 1.00, 60000.00, 0.7600, '2025-03-15 10:00:00'),
(5, 1, 5, 2025, 1.00, 55000.00, 0.7400, '2025-04-15 10:00:00'),
(6, 1, 6, 2025, 1.00, 65000.00, 0.7700, '2025-05-15 10:00:00'),
(7, 1, 7, 2025, 1.00, 50000.00, 0.7100, '2025-06-15 10:00:00'),
(8, 1, 8, 2025, 2.00, 120000.00, 0.8200, '2025-07-15 10:00:00'),
(9, 1, 9, 2025, 2.00, 130000.00, 0.8500, '2025-08-15 10:00:00'),
(10, 1, 10, 2025, 2.00, 115000.00, 0.8000, '2025-09-15 10:00:00'),
(11, 1, 11, 2025, 2.00, 125000.00, 0.8300, '2025-10-15 10:00:00'),
(12, 1, 12, 2025, 3.00, 180000.00, 0.8800, '2025-11-15 10:00:00'),

-- Venue 2 (Lima Park Hotel Batangas)
(13, 2, 1, 2025, 1.00, 45000.00, 0.7000, '2024-12-15 10:00:00'),
(14, 2, 2, 2025, 1.00, 42000.00, 0.6800, '2025-01-15 10:00:00'),
(15, 2, 3, 2025, 1.00, 55000.00, 0.7500, '2025-02-15 10:00:00'),
(16, 2, 4, 2025, 1.00, 48000.00, 0.7200, '2025-03-15 10:00:00'),
(17, 2, 5, 2025, 1.00, 50000.00, 0.7400, '2025-04-15 10:00:00'),
(18, 2, 6, 2025, 0.00, 0.00, 0.6500, '2025-05-15 10:00:00'),
(19, 2, 7, 2025, 1.00, 60000.00, 0.7800, '2025-06-15 10:00:00'),
(20, 2, 8, 2025, 0.00, 0.00, 0.6200, '2025-07-15 10:00:00'),
(21, 2, 9, 2025, 1.00, 65000.00, 0.8000, '2025-08-15 10:00:00'),
(22, 2, 10, 2025, 2.00, 115000.00, 0.8300, '2025-09-15 10:00:00'),
(23, 2, 11, 2025, 1.00, 52000.00, 0.7100, '2025-10-15 10:00:00'),
(24, 2, 12, 2025, 1.00, 55000.00, 0.7400, '2025-11-15 10:00:00'),

-- Venue 4 (La Solana Splendido Event Center) - High capacity venue
(25, 4, 1, 2025, 1.00, 60000.00, 0.7600, '2024-12-15 10:00:00'),
(26, 4, 2, 2025, 2.00, 118000.00, 0.8500, '2025-01-15 10:00:00'),
(27, 4, 3, 2025, 1.00, 55000.00, 0.7400, '2025-02-15 10:00:00'),
(28, 4, 4, 2025, 1.00, 105000.00, 0.8000, '2025-03-15 10:00:00'),
(29, 4, 5, 2025, 1.00, 50000.00, 0.7200, '2025-04-15 10:00:00'),
(30, 4, 6, 2025, 2.00, 125000.00, 0.8300, '2025-05-15 10:00:00'),
(31, 4, 7, 2025, 0.00, 0.00, 0.6800, '2025-06-15 10:00:00'),
(32, 4, 8, 2025, 2.00, 135000.00, 0.8600, '2025-07-15 10:00:00'),
(33, 4, 9, 2025, 3.00, 220000.00, 0.8900, '2025-08-15 10:00:00'),
(34, 4, 10, 2025, 3.00, 235000.00, 0.9200, '2025-09-15 10:00:00'),
(35, 4, 11, 2025, 2.00, 115000.00, 0.7800, '2025-10-15 10:00:00'),
(36, 4, 12, 2025, 1.00, 60000.00, 0.7000, '2025-11-15 10:00:00'),

-- Venue 5 (South Peak Garden & Events Place)
(37, 5, 1, 2025, 0.00, 0.00, 0.6200, '2024-12-15 10:00:00'),
(38, 5, 2, 2025, 0.00, 0.00, 0.5800, '2025-01-15 10:00:00'),
(39, 5, 3, 2025, 1.00, 92000.00, 0.7800, '2025-02-15 10:00:00'),
(40, 5, 4, 2025, 1.00, 58000.00, 0.7100, '2025-03-15 10:00:00'),
(41, 5, 5, 2025, 1.00, 62000.00, 0.7500, '2025-04-15 10:00:00'),
(42, 5, 6, 2025, 0.00, 0.00, 0.6000, '2025-05-15 10:00:00'),
(43, 5, 7, 2025, 1.00, 68000.00, 0.8000, '2025-06-15 10:00:00'),
(44, 5, 8, 2025, 2.00, 140000.00, 0.8300, '2025-07-15 10:00:00'),
(45, 5, 9, 2025, 2.00, 150000.00, 0.8600, '2025-08-15 10:00:00'),
(46, 5, 10, 2025, 1.00, 60000.00, 0.7400, '2025-09-15 10:00:00'),
(47, 5, 11, 2025, 1.00, 55000.00, 0.7200, '2025-10-15 10:00:00'),
(48, 5, 12, 2025, 3.00, 195000.00, 0.9000, '2025-11-15 10:00:00');

-- ============================================================================
-- PRICING_MARKET_ANALYSIS TABLE - Competitive market data cache
-- ============================================================================

INSERT INTO `pricing_market_analysis` (`analysis_id`, `city`, `capacity_range_min`, `capacity_range_max`, `market_avg_price`, `market_avg_bookings`, `competitor_count`, `analysis_date`, `created_at`) VALUES
-- Batangas City market analysis (Capacity 200-300)
(1, 'Batangas City', 200, 300, 52000.00, 1.5, 8, '2025-10-15', '2025-10-15 10:00:00'),
(2, 'Batangas City', 200, 300, 51500.00, 1.6, 8, '2025-11-15', '2025-11-15 10:00:00'),

-- Batangas City market analysis (Capacity 100-200)
(3, 'Batangas City', 100, 200, 48000.00, 1.2, 7, '2025-10-15', '2025-10-15 10:00:00'),
(4, 'Batangas City', 100, 200, 49000.00, 1.3, 7, '2025-11-15', '2025-11-15 10:00:00'),

-- Tanauan City market analysis
(5, 'Tanauan City', 100, 200, 42000.00, 1.0, 6, '2025-10-15', '2025-10-15 10:00:00'),
(6, 'Tanauan City', 100, 200, 43000.00, 1.1, 6, '2025-11-15', '2025-11-15 10:00:00'),

-- Batangas City market analysis (Capacity 300-400)
(7, 'Batangas City', 300, 400, 62000.00, 2.1, 9, '2025-10-15', '2025-10-15 10:00:00'),
(8, 'Batangas City', 300, 400, 63500.00, 2.3, 9, '2025-11-15', '2025-11-15 10:00:00'),

-- Batangas City market analysis (Capacity 250-350)
(9, 'Batangas City', 250, 350, 55000.00, 1.8, 8, '2025-10-15', '2025-10-15 10:00:00'),
(10, 'Batangas City', 250, 350, 56000.00, 1.9, 8, '2025-11-15', '2025-11-15 10:00:00'),

-- Santa Rosa market analysis
(11, 'Santa Rosa', 200, 300, 58000.00, 1.4, 7, '2025-10-15', '2025-10-15 10:00:00'),
(12, 'Santa Rosa', 200, 300, 59000.00, 1.5, 7, '2025-11-15', '2025-11-15 10:00:00'),

-- Calamba market analysis
(13, 'Calamba', 250, 350, 68000.00, 1.6, 6, '2025-10-15', '2025-10-15 10:00:00'),
(14, 'Calamba', 250, 350, 70000.00, 1.7, 6, '2025-11-15', '2025-11-15 10:00:00'),

-- San Pablo market analysis
(15, 'San Pablo', 500, 700, 95000.00, 2.0, 5, '2025-10-15', '2025-10-15 10:00:00'),
(16, 'San Pablo', 500, 700, 96000.00, 2.1, 5, '2025-11-15', '2025-11-15 10:00:00'),

-- Tagaytay market analysis (Capacity 300-400)
(17, 'Tagaytay', 300, 400, 78000.00, 1.8, 7, '2025-10-15', '2025-10-15 10:00:00'),
(18, 'Tagaytay', 300, 400, 79000.00, 1.9, 7, '2025-11-15', '2025-11-15 10:00:00'),

-- Tagaytay market analysis (Capacity 600-800)
(19, 'Tagaytay', 600, 800, 115000.00, 2.5, 4, '2025-10-15', '2025-10-15 10:00:00'),
(20, 'Tagaytay', 600, 800, 117000.00, 2.6, 4, '2025-11-15', '2025-11-15 10:00:00'),

-- Nasugbu market analysis
(21, 'Nasugbu', 400, 500, 82000.00, 1.5, 6, '2025-10-15', '2025-10-15 10:00:00'),
(22, 'Nasugbu', 400, 500, 83000.00, 1.6, 6, '2025-11-15', '2025-11-15 10:00:00'),

-- Lipa market analysis
(23, 'Lipa', 350, 450, 88000.00, 1.7, 5, '2025-10-15', '2025-10-15 10:00:00'),
(24, 'Lipa', 350, 450, 89000.00, 1.8, 5, '2025-11-15', '2025-11-15 10:00:00'),

-- Imus market analysis
(25, 'Imus', 200, 300, 62000.00, 1.3, 8, '2025-10-15', '2025-10-15 10:00:00'),
(26, 'Imus', 200, 300, 63000.00, 1.4, 8, '2025-11-15', '2025-11-15 10:00:00'),

-- Lucena market analysis
(27, 'Lucena', 800, 1000, 180000.00, 3.0, 3, '2025-10-15', '2025-10-15 10:00:00'),
(28, 'Lucena', 800, 1000, 182000.00, 3.1, 3, '2025-11-15', '2025-11-15 10:00:00'),

-- Tagaytay market analysis (Capacity 400-600)
(29, 'Tagaytay', 400, 600, 98000.00, 2.0, 5, '2025-10-15', '2025-10-15 10:00:00'),
(30, 'Tagaytay', 400, 600, 99000.00, 2.1, 5, '2025-11-15', '2025-11-15 10:00:00'),

-- Calamba market analysis (Capacity 400-600)
(31, 'Calamba', 400, 600, 92000.00, 1.9, 6, '2025-10-15', '2025-10-15 10:00:00'),
(32, 'Calamba', 400, 600, 93000.00, 2.0, 6, '2025-11-15', '2025-11-15 10:00:00'),

-- Alfonso market analysis
(33, 'Alfonso', 500, 700, 105000.00, 2.2, 5, '2025-10-15', '2025-10-15 10:00:00'),
(34, 'Alfonso', 500, 700, 107000.00, 2.3, 5, '2025-11-15', '2025-11-15 10:00:00'),

-- Tagaytay market analysis (Capacity 350-450)
(35, 'Tagaytay', 350, 450, 72000.00, 1.6, 7, '2025-10-15', '2025-10-15 10:00:00'),
(36, 'Tagaytay', 350, 450, 73000.00, 1.7, 7, '2025-11-15', '2025-11-15 10:00:00'),

-- Tagaytay market analysis (Capacity 200-300)
(37, 'Tagaytay', 200, 300, 60000.00, 1.4, 8, '2025-10-15', '2025-10-15 10:00:00'),
(38, 'Tagaytay', 200, 300, 61000.00, 1.5, 8, '2025-11-15', '2025-11-15 10:00:00'),

-- Tagaytay market analysis (Capacity 350-500)
(39, 'Tagaytay', 350, 500, 85000.00, 1.8, 6, '2025-10-15', '2025-10-15 10:00:00'),
(40, 'Tagaytay', 350, 500, 86000.00, 1.9, 6, '2025-11-15', '2025-11-15 10:00:00');

-- ============================================================================
-- SUMMARY
-- ============================================================================
-- Tables populated:
-- 1. chat - 23 messages across 3 conversations
-- 2. event_contracts - 28 contracts for all events
-- 3. event_payments - 36 payment records (split and full payments)
-- 4. pricing_demand_forecast - 48 monthly forecasts for 5 venues
-- 5. pricing_market_analysis - 40 market analysis records

SELECT 'Data population completed successfully!' as Status;
