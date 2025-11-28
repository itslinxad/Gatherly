-- Fix time_start and time_end columns in events table
-- Run this SQL in phpMyAdmin after creating the events table

-- Step 1: Modify the column types from TIMESTAMP to TIME
ALTER TABLE `events` 
MODIFY COLUMN `time_start` TIME DEFAULT NULL,
MODIFY COLUMN `time_end` TIME DEFAULT NULL;

-- Step 2: Update existing records with proper TIME values
-- This will set default times for existing records based on event type

UPDATE `events` SET 
    `time_start` = CASE 
        WHEN `event_type` = 'Wedding' THEN '14:00:00'
        WHEN `event_type` = 'Corporate' THEN '09:00:00'
        WHEN `event_type` = 'Birthday' THEN '15:00:00'
        WHEN `event_type` = 'Concert' THEN '19:00:00'
        ELSE '10:00:00'
    END,
    `time_end` = CASE 
        WHEN `event_type` = 'Wedding' THEN '18:00:00'
        WHEN `event_type` = 'Corporate' THEN '17:00:00'
        WHEN `event_type` = 'Birthday' THEN '19:00:00'
        WHEN `event_type` = 'Concert' THEN '23:00:00'
        ELSE '18:00:00'
    END
WHERE `time_start` IS NULL 
   OR `time_start` = '00:00:00' 
   OR CAST(`time_start` AS CHAR) LIKE '0000-00-00%'
   OR CAST(`time_start` AS CHAR) LIKE '2025-11-28%';

-- Verification query - run this to check the results
SELECT event_id, event_name, event_type, event_date, time_start, time_end 
FROM events 
ORDER BY event_id DESC 
LIMIT 10;
