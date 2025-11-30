-- Migration Script: Add ML metadata to prices table
-- Version: 2.0
-- Date: 2025-11-30

-- Add ML metadata columns to prices table
ALTER TABLE `prices` 
ADD COLUMN `ml_demand_score` DECIMAL(5,4) DEFAULT NULL COMMENT 'ML calculated demand score (0-1)',
ADD COLUMN `ml_competitive_position` DECIMAL(5,4) DEFAULT NULL COMMENT 'Competitive position score (0-1)',
ADD COLUMN `ml_seasonality_high` DECIMAL(5,4) DEFAULT NULL COMMENT 'Peak season multiplier',
ADD COLUMN `ml_seasonality_low` DECIMAL(5,4) DEFAULT NULL COMMENT 'Off-peak season multiplier',
ADD COLUMN `ml_performance_score` DECIMAL(5,4) DEFAULT NULL COMMENT 'Venue performance score (0-1)',
ADD COLUMN `ml_last_calculated` TIMESTAMP NULL DEFAULT NULL COMMENT 'When prices were last calculated by ML',
ADD COLUMN `price_optimization_enabled` TINYINT(1) DEFAULT 1 COMMENT '1=ML auto-calculate, 0=manual pricing';

-- Add index for faster queries
ALTER TABLE `prices` 
ADD INDEX `idx_ml_calculated` (`ml_last_calculated`),
ADD INDEX `idx_optimization_enabled` (`price_optimization_enabled`);

-- Create table for ML calculation history/audit log
CREATE TABLE IF NOT EXISTS `pricing_ml_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `venue_id` INT(11) NOT NULL,
  `base_price` DECIMAL(10,2) NOT NULL,
  `calculated_peak_price` DECIMAL(10,2) NOT NULL,
  `calculated_offpeak_price` DECIMAL(10,2) NOT NULL,
  `calculated_weekday_price` DECIMAL(10,2) NOT NULL,
  `calculated_weekend_price` DECIMAL(10,2) NOT NULL,
  `ml_demand_score` DECIMAL(5,4) DEFAULT NULL,
  `ml_competitive_position` DECIMAL(5,4) DEFAULT NULL,
  `ml_seasonality_high` DECIMAL(5,4) DEFAULT NULL,
  `ml_seasonality_low` DECIMAL(5,4) DEFAULT NULL,
  `ml_performance_score` DECIMAL(5,4) DEFAULT NULL,
  `calculation_type` ENUM('auto', 'manual_trigger', 'scheduled') DEFAULT 'auto',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_venue_history` (`venue_id`, `created_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create table for competitive market analysis cache
CREATE TABLE IF NOT EXISTS `pricing_market_analysis` (
  `analysis_id` INT(11) NOT NULL AUTO_INCREMENT,
  `city` VARCHAR(100) NOT NULL,
  `capacity_range_min` INT(11) NOT NULL,
  `capacity_range_max` INT(11) NOT NULL,
  `market_avg_price` DECIMAL(10,2) DEFAULT NULL,
  `market_avg_bookings` DECIMAL(10,2) DEFAULT NULL,
  `competitor_count` INT(11) DEFAULT 0,
  `analysis_date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`analysis_id`),
  UNIQUE KEY `idx_market_analysis` (`city`, `capacity_range_min`, `capacity_range_max`, `analysis_date`),
  KEY `idx_analysis_date` (`analysis_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create table for demand forecasting data
CREATE TABLE IF NOT EXISTS `pricing_demand_forecast` (
  `forecast_id` INT(11) NOT NULL AUTO_INCREMENT,
  `venue_id` INT(11) NOT NULL,
  `month` INT(2) NOT NULL COMMENT 'Month number 1-12',
  `year` INT(4) NOT NULL,
  `predicted_bookings` DECIMAL(10,2) DEFAULT NULL,
  `predicted_revenue` DECIMAL(12,2) DEFAULT NULL,
  `confidence_score` DECIMAL(5,4) DEFAULT NULL COMMENT 'Prediction confidence (0-1)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`forecast_id`),
  UNIQUE KEY `idx_venue_forecast` (`venue_id`, `year`, `month`),
  KEY `idx_forecast_date` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add comment to prices table
ALTER TABLE `prices` 
COMMENT = 'Venue pricing with ML-powered optimization. peak_price, offpeak_price, weekday_price, weekend_price are auto-calculated based on base_price using ML algorithms.';
