-- Add payment tracking table
CREATE TABLE IF NOT EXISTS `event_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_type` enum('full','downpayment','partial') NOT NULL,
  `payment_method` varchar(50) DEFAULT 'gcash',
  `reference_no` varchar(100) NOT NULL,
  `payment_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `event_id` (`event_id`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `event_payments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `event_payments_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add payment tracking columns to events table
ALTER TABLE `events` 
ADD COLUMN IF NOT EXISTS `total_paid` decimal(10,2) DEFAULT 0.00 AFTER `total_cost`,
ADD COLUMN IF NOT EXISTS `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid' AFTER `total_paid`;

-- Update existing events to have payment_status as 'unpaid' if NULL
UPDATE `events` SET `payment_status` = 'unpaid' WHERE `payment_status` IS NULL;
