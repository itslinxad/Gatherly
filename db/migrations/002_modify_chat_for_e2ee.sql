-- Migration: Add E2EE columns to chat table
-- Date: 2026-04-06
-- Description: Adds encryption metadata columns to support end-to-end encryption

USE sad_db;

-- Add E2EE columns to chat table
ALTER TABLE `chat`
  ADD COLUMN `encryption_type` ENUM('legacy', 'e2ee') DEFAULT 'legacy' AFTER `is_read`,
  ADD COLUMN `encrypted_session_key` TEXT DEFAULT NULL AFTER `encryption_type`,
  ADD COLUMN `iv` VARCHAR(255) DEFAULT NULL AFTER `encrypted_session_key`,
  ADD COLUMN `auth_tag` VARCHAR(255) DEFAULT NULL AFTER `iv`,
  ADD COLUMN `key_version` INT(11) DEFAULT 1 AFTER `auth_tag`;

-- Add indexes for performance
ALTER TABLE `chat`
  ADD INDEX `idx_encryption_type` (`encryption_type`),
  ADD INDEX `idx_key_version` (`key_version`);

-- Display success message
SELECT 'Chat table successfully modified for E2EE support!' AS Status;
