-- Migration: Add Contract Approval Flow
-- Date: December 2, 2025
-- Description: Adds 'rejected' status to event_contracts and approval timestamps

-- Modify event_contracts table to add 'rejected' status and timestamps
ALTER TABLE `event_contracts` 
MODIFY COLUMN `signed_status` ENUM('pending','approved','rejected') DEFAULT 'pending';

-- Add approval/rejection timestamp columns
ALTER TABLE `event_contracts`
ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When organizer approved the contract',
ADD COLUMN `rejected_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When organizer rejected the contract',
ADD COLUMN `rejection_reason` TEXT DEFAULT NULL COMMENT 'Optional reason for contract rejection';
