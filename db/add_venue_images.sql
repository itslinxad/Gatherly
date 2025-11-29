-- Update venues with image URLs (stored as LONGBLOB)
-- Note: In production, you would upload actual images. This script shows the structure.
-- For now, we'll set image paths that can be populated later through the admin interface.

-- First, let's check the current venues
SELECT venue_id, venue_name FROM venues WHERE status = 'active' ORDER BY venue_id;

-- Update venues with placeholder image indicators
-- In a real scenario, you would use LOAD_FILE() to insert actual image files
-- Example: UPDATE venues SET image = LOAD_FILE('/path/to/image.jpg') WHERE venue_id = X;

-- For development, we'll create a stored procedure to easily add images later
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS add_venue_image(
    IN p_venue_id INT,
    IN p_image_path VARCHAR(500)
)
BEGIN
    UPDATE venues 
    SET image = LOAD_FILE(p_image_path)
    WHERE venue_id = p_venue_id;
END$$

DELIMITER ;

-- Note: To add actual images, you would run:
-- CALL add_venue_image(1, 'C:/path/to/sonyas-garden.jpg');

-- Alternative: Update with base64 encoded images or URLs for reference
-- This creates a reference table for image management

CREATE TABLE IF NOT EXISTS `venue_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `venue_id` int(11) NOT NULL,
  `image_data` LONGBLOB NOT NULL,
  `image_name` varchar(255) DEFAULT NULL,
  `image_type` varchar(50) DEFAULT NULL COMMENT 'main, gallery, thumbnail',
  `mime_type` varchar(100) DEFAULT 'image/jpeg',
  `file_size` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `venue_id` (`venue_id`),
  KEY `idx_primary` (`venue_id`, `is_primary`),
  CONSTRAINT `venue_images_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert placeholder image references for all venues
-- These can be replaced with actual images through the admin panel

-- Note: For actual image insertion, you would use one of these methods:
-- Method 1: LOAD_FILE (requires file_priv permission)
-- UPDATE venues SET image = LOAD_FILE('C:/xampp/htdocs/Gatherly-EMS_2025/public/assets/images/venues/sonya-garden.jpg') WHERE venue_id = 1;

-- Method 2: Using PHP to insert binary data
-- This is the recommended approach - create an upload interface

-- Method 3: Insert image URLs as metadata (alternative approach for web display)
CREATE TABLE IF NOT EXISTS `venue_image_urls` (
  `url_id` int(11) NOT NULL AUTO_INCREMENT,
  `venue_id` int(11) NOT NULL,
  `image_url` varchar(500) NOT NULL COMMENT 'URL or path to image',
  `image_type` enum('main','gallery','thumbnail') DEFAULT 'main',
  `is_primary` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`url_id`),
  KEY `venue_id` (`venue_id`),
  CONSTRAINT `venue_image_urls_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert placeholder image URLs for reference (these should be replaced with actual images)
INSERT INTO `venue_image_urls` (`venue_id`, `image_url`, `image_type`, `is_primary`, `display_order`) VALUES
-- Get venue IDs dynamically - adjust these based on your actual venue_id values
(1, 'assets/images/venues/sonyas-garden.jpg', 'main', 1, 1),
(2, 'assets/images/venues/caleruega.jpg', 'main', 1, 1),
(3, 'assets/images/venues/estancia-resort.jpg', 'main', 1, 1),
(4, 'assets/images/venues/monte-vista.jpg', 'main', 1, 1),
(5, 'assets/images/venues/laguna-bel-air.jpg', 'main', 1, 1),
(6, 'assets/images/venues/paseo-garden.jpg', 'main', 1, 1),
(7, 'assets/images/venues/villa-escudero.jpg', 'main', 1, 1),
(8, 'assets/images/venues/gazebo-royale.jpg', 'main', 1, 1),
(9, 'assets/images/venues/punta-fuego.jpg', 'main', 1, 1),
(10, 'assets/images/venues/farm-san-benito.jpg', 'main', 1, 1),
(11, 'assets/images/venues/the-manor.jpg', 'main', 1, 1),
(12, 'assets/images/venues/taal-vista.jpg', 'main', 1, 1),
(13, 'assets/images/venues/arcadia.jpg', 'main', 1, 1),
(14, 'assets/images/venues/marcia-adams.jpg', 'main', 1, 1),
(15, 'assets/images/venues/graceland.jpg', 'main', 1, 1);

-- Create a view for easy venue image access
CREATE OR REPLACE VIEW `venue_with_images` AS
SELECT 
    v.*,
    viu.image_url as primary_image_url,
    viu.image_type
FROM venues v
LEFT JOIN venue_image_urls viu ON v.venue_id = viu.venue_id AND viu.is_primary = 1
WHERE v.status = 'active';
