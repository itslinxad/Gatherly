-- Recreate ML Pricing Tables with Correct Schema
-- Run this to restore the ML pricing functionality

USE gatherly_sad_db;

-- Drop tables if they exist (to recreate with correct schema)
DROP TABLE IF EXISTS pricing_ml_history;
DROP TABLE IF EXISTS pricing_demand_forecast;
DROP TABLE IF EXISTS pricing_market_analysis;

-- 1. Demand Forecast Table
CREATE TABLE pricing_demand_forecast (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    month INT NOT NULL COMMENT '1-12 for January-December',
    year INT NOT NULL,
    predicted_bookings DECIMAL(10, 2) NOT NULL,
    confidence_score DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Confidence level 0-100',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(venue_id) ON DELETE CASCADE,
    UNIQUE KEY unique_venue_month_year (venue_id, month, year),
    INDEX idx_venue_date (venue_id, year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Market Analysis Table
CREATE TABLE pricing_market_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    competitor_avg_price DECIMAL(10, 2) DEFAULT 0.00,
    market_position VARCHAR(50) DEFAULT 'average' COMMENT 'premium, average, budget',
    competitive_score DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Score 0-100',
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(venue_id) ON DELETE CASCADE,
    INDEX idx_venue (venue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ML Pricing History Table
CREATE TABLE pricing_ml_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    optimization_date DATE NOT NULL,
    old_price DECIMAL(10, 2) NOT NULL,
    new_price DECIMAL(10, 2) NOT NULL,
    demand_score DECIMAL(5, 2) DEFAULT 0.00,
    competitive_score DECIMAL(5, 2) DEFAULT 0.00,
    seasonality_factor DECIMAL(5, 2) DEFAULT 1.00,
    performance_score DECIMAL(5, 2) DEFAULT 0.00,
    algorithm_version VARCHAR(20) DEFAULT 'v1.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(venue_id) ON DELETE CASCADE,
    INDEX idx_venue_date (venue_id, optimization_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample forecast data (next 6 months for demonstration)
-- You should populate this with actual ML predictions
INSERT INTO pricing_demand_forecast (venue_id, month, year, predicted_bookings, confidence_score)
SELECT DISTINCT
    v.venue_id,
    MONTH(DATE_ADD(NOW(), INTERVAL n MONTH)) as month,
    YEAR(DATE_ADD(NOW(), INTERVAL n MONTH)) as year,
    ROUND(RAND() * 10 + 5, 2) as predicted_bookings,
    ROUND(RAND() * 30 + 70, 2) as confidence_score
FROM venues v
CROSS JOIN (
    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
) months
LIMIT 100;

-- Insert sample market analysis data
INSERT INTO pricing_market_analysis (venue_id, competitor_avg_price, market_position, competitive_score)
SELECT 
    venue_id,
    ROUND(RAND() * 50000 + 30000, 2) as competitor_avg_price,
    CASE 
        WHEN RAND() > 0.66 THEN 'premium'
        WHEN RAND() > 0.33 THEN 'average'
        ELSE 'budget'
    END as market_position,
    ROUND(RAND() * 40 + 60, 2) as competitive_score
FROM venues;

SELECT 'ML Pricing tables recreated successfully!' as Status;
