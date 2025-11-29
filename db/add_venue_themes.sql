-- Add venue theme support to the database
-- Run this SQL script to add theme-based recommendations

-- Step 1: Add suitable_themes column to venues table
ALTER TABLE `venues` 
ADD COLUMN `suitable_themes` TEXT DEFAULT NULL COMMENT 'Comma-separated list of suitable event themes: Wedding, Corporate, Birthday, Concert, Conference, Workshop, Anniversary, Engagement, Reunion, Festival, etc.' 
AFTER `description`;

-- Step 2: Add venue_type column for additional categorization
ALTER TABLE `venues`
ADD COLUMN `venue_type` VARCHAR(100) DEFAULT NULL COMMENT 'e.g., Garden, Ballroom, Conference Hall, Resort, Beach, Rooftop, Industrial, etc.'
AFTER `suitable_themes`;

-- Step 3: Add ambiance column to describe the atmosphere
ALTER TABLE `venues`
ADD COLUMN `ambiance` VARCHAR(100) DEFAULT NULL COMMENT 'e.g., Elegant, Rustic, Modern, Tropical, Industrial, Romantic, Professional, etc.'
AFTER `venue_type`;

-- Step 4: Create venue_themes table for more structured theme management
CREATE TABLE IF NOT EXISTS `venue_themes` (
  `theme_id` int(11) NOT NULL AUTO_INCREMENT,
  `theme_name` varchar(50) NOT NULL COMMENT 'Wedding, Corporate, Birthday, etc.',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`theme_id`),
  UNIQUE KEY `theme_name` (`theme_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 5: Insert predefined event themes
INSERT INTO `venue_themes` (`theme_name`, `description`) VALUES
('Wedding', 'Romantic and elegant venues perfect for wedding ceremonies and receptions'),
('Corporate', 'Professional venues suitable for business meetings, conferences, and corporate events'),
('Birthday', 'Fun and flexible venues for birthday celebrations of all ages'),
('Concert', 'Venues with stage setup and acoustics suitable for musical performances'),
('Conference', 'Professional venues with presentation equipment for conferences and seminars'),
('Workshop', 'Intimate spaces suitable for training sessions and workshops'),
('Anniversary', 'Romantic venues perfect for milestone celebrations'),
('Engagement', 'Elegant venues for engagement parties and proposals'),
('Reunion', 'Casual to formal venues suitable for family or class reunions'),
('Festival', 'Large outdoor or indoor spaces for festivals and fairs'),
('Graduation', 'Venues suitable for graduation ceremonies and celebrations'),
('Baby Shower', 'Cozy and cheerful venues for baby shower parties'),
('Christening', 'Elegant venues appropriate for religious celebrations'),
('Team Building', 'Venues with both indoor and outdoor facilities for corporate team building'),
('Product Launch', 'Modern venues suitable for product launches and brand events'),
('Gala', 'Luxurious venues for formal gala events and fundraisers'),
('Seminar', 'Professional venues with audio-visual equipment for seminars'),
('Trade Show', 'Large venues suitable for exhibitions and trade shows'),
('Cocktail Party', 'Sophisticated venues for cocktail parties and networking events'),
('Cultural Event', 'Venues suitable for cultural performances and celebrations');

-- Step 6: Create junction table for many-to-many relationship
CREATE TABLE IF NOT EXISTS `venue_theme_mapping` (
  `mapping_id` int(11) NOT NULL AUTO_INCREMENT,
  `venue_id` int(11) NOT NULL,
  `theme_id` int(11) NOT NULL,
  `suitability_score` tinyint(1) DEFAULT 5 COMMENT 'Score from 1-10 indicating how suitable the venue is for this theme',
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `venue_theme_unique` (`venue_id`, `theme_id`),
  KEY `venue_id` (`venue_id`),
  KEY `theme_id` (`theme_id`),
  CONSTRAINT `venue_theme_mapping_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON DELETE CASCADE,
  CONSTRAINT `venue_theme_mapping_ibfk_2` FOREIGN KEY (`theme_id`) REFERENCES `venue_themes` (`theme_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Note: After running this script, venue managers can tag their venues with appropriate themes
-- The AI will use this data to make intelligent theme-based recommendations
