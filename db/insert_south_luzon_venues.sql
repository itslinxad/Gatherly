-- Insert realistic venues from South Luzon (Laguna, Batangas, Cavite, Quezon)
-- This includes real venue names and realistic pricing based on Philippine event venues

-- First, insert locations for South Luzon areas
INSERT INTO `locations` (`city`, `province`, `baranggay`) VALUES
('Santa Rosa', 'Laguna', 'Tagapo'),
('Calamba', 'Laguna', 'Pansol'),
('San Pablo', 'Laguna', 'San Buenaventura'),
('Tagaytay', 'Cavite', 'Sungay East'),
('Tagaytay', 'Cavite', 'Kaybagal South'),
('Nasugbu', 'Batangas', 'Wawa'),
('Tanauan', 'Batangas', 'Sambat'),
('Lipa', 'Batangas', 'Marauoy'),
('Imus', 'Cavite', 'Alapan'),
('Lucena', 'Quezon', 'Ibabang Dupay'),
('Tagaytay', 'Cavite', 'Maharlika East'),
('Calamba', 'Laguna', 'Real'),
('Alfonso', 'Cavite', 'Marahan'),
('Talisay', 'Batangas', 'Poblacion');

-- Get location IDs for venue insertion (these will be used in INSERT statements)
SET @santa_rosa_loc = LAST_INSERT_ID();
SET @calamba_pansol_loc = LAST_INSERT_ID() + 1;
SET @san_pablo_loc = LAST_INSERT_ID() + 2;
SET @tagaytay_sungay_loc = LAST_INSERT_ID() + 3;
SET @tagaytay_kaybagal_loc = LAST_INSERT_ID() + 4;
SET @nasugbu_loc = LAST_INSERT_ID() + 5;
SET @tanauan_loc = LAST_INSERT_ID() + 6;
SET @lipa_loc = LAST_INSERT_ID() + 7;
SET @imus_loc = LAST_INSERT_ID() + 8;
SET @lucena_loc = LAST_INSERT_ID() + 9;
SET @tagaytay_maharlika_loc = LAST_INSERT_ID() + 10;
SET @calamba_real_loc = LAST_INSERT_ID() + 11;
SET @alfonso_loc = LAST_INSERT_ID() + 12;
SET @talisay_loc = LAST_INSERT_ID() + 13;

-- Insert South Luzon Venues (manager_id = 2 from existing data)
INSERT INTO `venues` (`manager_id`, `location_id`, `venue_name`, `capacity`, `description`, `availability_status`, `status`, `suitable_themes`, `venue_type`, `ambiance`) VALUES

-- Tagaytay Venues (Popular wedding and events destination)
(2, @tagaytay_sungay_loc, 'Sonya\'s Garden', 200, 'A charming garden venue nestled in Tagaytay with lush greenery, organic farm, and rustic-chic ambiance. Perfect for intimate weddings and garden parties with all-natural setting and fresh farm-to-table catering.', 'available', 'active', 'Wedding, Anniversary, Engagement, Birthday, Reunion', 'Garden', 'Rustic'),

(2, @tagaytay_kaybagal_loc, 'Caleruega Church', 150, 'A stunning hilltop chapel and event venue overlooking Taal Lake. Features Mediterranean-inspired architecture, beautiful gardens, and breathtaking views. Ideal for weddings, christenings, and religious ceremonies.', 'available', 'active', 'Wedding, Christening, Anniversary, Engagement', 'Chapel & Garden', 'Elegant'),

(2, @tagaytay_maharlika_loc, 'Estancia Resort Hotel', 300, 'Elegant resort venue with panoramic Taal Lake views, spacious function rooms, and well-manicured gardens. Offers indoor and outdoor event spaces suitable for weddings, corporate events, and large celebrations.', 'available', 'active', 'Wedding, Corporate, Conference, Gala, Team Building', 'Resort', 'Elegant'),

(2, @tagaytay_sungay_loc, 'Monte Vista Events Pavilion', 250, 'Modern event pavilion with floor-to-ceiling glass windows offering stunning Taal Volcano views. Features contemporary design, professional AV equipment, and climate-controlled spaces.', 'available', 'active', 'Wedding, Corporate, Conference, Product Launch, Seminar', 'Pavilion', 'Modern'),

-- Laguna Venues (Hot spring resorts and event spaces)
(2, @calamba_pansol_loc, 'Laguna Bel Air', 350, 'Premier resort and convention center featuring multiple function halls, outdoor gardens, and hot spring pools. Perfect for large-scale events, conferences, and weddings with complete amenities and accommodations.', 'available', 'active', 'Wedding, Corporate, Conference, Team Building, Reunion, Festival', 'Resort & Convention Center', 'Professional'),

(2, @santa_rosa_loc, 'Garden Pavilion at Paseo de Santa Rosa', 180, 'Elegant garden venue within a premier residential community. Features manicured lawns, modern pavilion, and excellent facilities. Ideal for sophisticated weddings and upscale events.', 'available', 'active', 'Wedding, Engagement, Anniversary, Cocktail Party, Gala', 'Garden Pavilion', 'Elegant'),

(2, @san_pablo_loc, 'Villa Escudero Plantations and Resort', 400, 'Historic coconut plantation resort offering unique cultural experience with traditional Filipino ambiance. Features bamboo pavilions, museum, and waterfall restaurant. Perfect for cultural events and large gatherings.', 'available', 'active', 'Wedding, Cultural Event, Team Building, Reunion, Festival, Corporate', 'Heritage Resort', 'Cultural'),

(2, @calamba_real_loc, 'The Gazebo Royale', 220, 'Beautiful garden venue with Victorian-inspired gazebos and romantic settings. Features landscaped gardens, koi ponds, and charming bridges. Perfect for romantic celebrations and themed events.', 'available', 'active', 'Wedding, Engagement, Anniversary, Birthday, Baby Shower', 'Garden', 'Romantic'),

-- Batangas Venues (Beach and countryside options)
(2, @nasugbu_loc, 'Punta Fuego', 500, 'Exclusive beach and mountain club offering world-class facilities, private beach, championship golf course, and multiple event venues. Ideal for luxury weddings, corporate retreats, and high-end events.', 'available', 'active', 'Wedding, Corporate, Team Building, Gala, Product Launch, Conference', 'Beach Resort & Club', 'Luxurious'),

(2, @tanauan_loc, 'The Farm at San Benito', 180, 'Holistic wellness resort set in lush tropical rainforest. Offers unique vegan dining, eco-friendly facilities, and serene natural environment. Perfect for wellness retreats, intimate weddings, and mindful gatherings.', 'available', 'active', 'Wedding, Corporate, Team Building, Workshop, Retreat', 'Eco-Wellness Resort', 'Zen'),

(2, @lipa_loc, 'The Manor at Camp John Hay Batangas', 280, 'Elegant countryside manor with colonial architecture, sprawling gardens, and mountain views. Features grand ballrooms and outdoor terraces. Ideal for sophisticated weddings and corporate events.', 'available', 'active', 'Wedding, Corporate, Conference, Gala, Anniversary', 'Manor House', 'Elegant'),

(2, @talisay_loc, 'Taal Vista Hotel', 400, 'Historic hotel with iconic Taal Lake and Volcano views. Offers grand ballrooms, manicured gardens, and professional event services. Perfect for weddings, conferences, and milestone celebrations.', 'available', 'active', 'Wedding, Corporate, Conference, Gala, Graduation, Anniversary', 'Historic Hotel', 'Classic'),

-- Cavite Venues (Modern and accessible)
(2, @imus_loc, 'Arcadia Events Hall', 300, 'Modern event venue with contemporary design, state-of-the-art facilities, and ample parking. Features flexible spaces suitable for various events from corporate functions to social celebrations.', 'available', 'active', 'Wedding, Corporate, Conference, Seminar, Product Launch, Birthday', 'Events Hall', 'Modern'),

(2, @alfonso_loc, 'Marcia Adams Event Place', 200, 'Charming garden venue with Spanish-colonial inspired architecture. Features beautiful courtyard, vintage decor, and intimate settings. Perfect for romantic weddings and classic celebrations.', 'available', 'active', 'Wedding, Anniversary, Engagement, Birthday, Christening', 'Garden', 'Romantic'),

-- Quezon Province
(2, @lucena_loc, 'Graceland Estates and Country Club', 350, 'Premier country club offering championship golf course, spacious function halls, and complete recreational facilities. Ideal for weddings, corporate events, and sports tournaments.', 'available', 'active', 'Wedding, Corporate, Team Building, Conference, Birthday, Reunion', 'Country Club', 'Professional');

-- Insert Pricing for all venues (15 venues total)
-- Get the starting venue_id
SET @start_venue_id = LAST_INSERT_ID();

-- Sonya's Garden - Tagaytay (Mid-range garden venue)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id, 45000.00, 60000.00, 38000.00, 42000.00, 55000.00);

-- Caleruega Church (Premium chapel venue)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 1, 55000.00, 75000.00, 48000.00, 52000.00, 68000.00);

-- Estancia Resort Hotel (Upscale resort)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 2, 80000.00, 110000.00, 70000.00, 75000.00, 95000.00);

-- Monte Vista Events Pavilion (Modern premium)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 3, 65000.00, 85000.00, 55000.00, 60000.00, 78000.00);

-- Laguna Bel Air (Large convention center)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 4, 95000.00, 130000.00, 80000.00, 88000.00, 115000.00);

-- Garden Pavilion at Paseo (Premium residential venue)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 5, 70000.00, 95000.00, 60000.00, 65000.00, 85000.00);

-- Villa Escudero (Cultural heritage resort)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 6, 75000.00, 100000.00, 65000.00, 70000.00, 90000.00);

-- The Gazebo Royale (Garden venue)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 7, 52000.00, 70000.00, 45000.00, 48000.00, 65000.00);

-- Punta Fuego (Luxury exclusive)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 8, 150000.00, 200000.00, 130000.00, 140000.00, 180000.00);

-- The Farm at San Benito (Wellness resort)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 9, 85000.00, 115000.00, 72000.00, 78000.00, 105000.00);

-- The Manor (Countryside manor)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 10, 78000.00, 105000.00, 68000.00, 72000.00, 95000.00);

-- Taal Vista Hotel (Historic premium)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 11, 90000.00, 125000.00, 78000.00, 85000.00, 110000.00);

-- Arcadia Events Hall (Modern accessible)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 12, 58000.00, 78000.00, 50000.00, 55000.00, 72000.00);

-- Marcia Adams Event Place (Garden romantic)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 13, 48000.00, 65000.00, 42000.00, 45000.00, 60000.00);

-- Graceland Estates (Country club)
INSERT INTO `prices` (`venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(@start_venue_id + 14, 72000.00, 98000.00, 62000.00, 68000.00, 88000.00);

-- Add parking information for all venues
INSERT INTO `parking` (`venue_id`, `two_wheels`, `four_wheels`) VALUES
(@start_venue_id, 30, 50),      -- Sonya's Garden
(@start_venue_id + 1, 20, 40),  -- Caleruega
(@start_venue_id + 2, 50, 100), -- Estancia Resort
(@start_venue_id + 3, 40, 80),  -- Monte Vista
(@start_venue_id + 4, 80, 150), -- Laguna Bel Air
(@start_venue_id + 5, 35, 70),  -- Garden Pavilion
(@start_venue_id + 6, 100, 200), -- Villa Escudero
(@start_venue_id + 7, 40, 75),  -- Gazebo Royale
(@start_venue_id + 8, 100, 250), -- Punta Fuego
(@start_venue_id + 9, 30, 60),  -- Farm at San Benito
(@start_venue_id + 10, 50, 90), -- The Manor
(@start_venue_id + 11, 80, 150), -- Taal Vista
(@start_venue_id + 12, 60, 120), -- Arcadia
(@start_venue_id + 13, 35, 65), -- Marcia Adams
(@start_venue_id + 14, 70, 130); -- Graceland

-- Add common amenities to venues
-- Get amenity IDs
SET @wifi = 2;
SET @security = 3;
SET @aircon = 1;
SET @parking = 5;
SET @stage = 6;
SET @garden = 8;
SET @projector = 4;
SET @outdoor = 10;
SET @vip = 9;

-- Add amenities for each venue (sample - you can customize)
-- Sonya's Garden
INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id, @wifi), (@start_venue_id, @security), (@start_venue_id, @parking), 
(@start_venue_id, @garden), (@start_venue_id, @outdoor);

-- Caleruega
INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 1, @wifi), (@start_venue_id + 1, @security), (@start_venue_id + 1, @parking),
(@start_venue_id + 1, @garden), (@start_venue_id + 1, @aircon);

-- Estancia Resort
INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 2, @wifi), (@start_venue_id + 2, @security), (@start_venue_id + 2, @parking),
(@start_venue_id + 2, @aircon), (@start_venue_id + 2, @projector), (@start_venue_id + 2, @vip),
(@start_venue_id + 2, @stage);

-- Monte Vista
INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 3, @wifi), (@start_venue_id + 3, @security), (@start_venue_id + 3, @parking),
(@start_venue_id + 3, @aircon), (@start_venue_id + 3, @projector), (@start_venue_id + 3, @stage);

-- Laguna Bel Air
INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 4, @wifi), (@start_venue_id + 4, @security), (@start_venue_id + 4, @parking),
(@start_venue_id + 4, @aircon), (@start_venue_id + 4, @projector), (@start_venue_id + 4, @stage),
(@start_venue_id + 4, @vip), (@start_venue_id + 4, @outdoor);

-- Continue for remaining venues...
INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 5, @wifi), (@start_venue_id + 5, @security), (@start_venue_id + 5, @parking),
(@start_venue_id + 5, @garden), (@start_venue_id + 5, @aircon), (@start_venue_id + 5, @vip);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 6, @wifi), (@start_venue_id + 6, @security), (@start_venue_id + 6, @parking),
(@start_venue_id + 6, @outdoor), (@start_venue_id + 6, @stage);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 7, @wifi), (@start_venue_id + 7, @security), (@start_venue_id + 7, @parking),
(@start_venue_id + 7, @garden), (@start_venue_id + 7, @outdoor);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 8, @wifi), (@start_venue_id + 8, @security), (@start_venue_id + 8, @parking),
(@start_venue_id + 8, @aircon), (@start_venue_id + 8, @vip), (@start_venue_id + 8, @outdoor),
(@start_venue_id + 8, @stage), (@start_venue_id + 8, @projector);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 9, @wifi), (@start_venue_id + 9, @security), (@start_venue_id + 9, @parking),
(@start_venue_id + 9, @outdoor), (@start_venue_id + 9, @garden);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 10, @wifi), (@start_venue_id + 10, @security), (@start_venue_id + 10, @parking),
(@start_venue_id + 10, @aircon), (@start_venue_id + 10, @garden), (@start_venue_id + 10, @vip);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 11, @wifi), (@start_venue_id + 11, @security), (@start_venue_id + 11, @parking),
(@start_venue_id + 11, @aircon), (@start_venue_id + 11, @projector), (@start_venue_id + 11, @stage),
(@start_venue_id + 11, @vip);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 12, @wifi), (@start_venue_id + 12, @security), (@start_venue_id + 12, @parking),
(@start_venue_id + 12, @aircon), (@start_venue_id + 12, @projector), (@start_venue_id + 12, @stage);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 13, @wifi), (@start_venue_id + 13, @security), (@start_venue_id + 13, @parking),
(@start_venue_id + 13, @garden), (@start_venue_id + 13, @outdoor);

INSERT INTO `venue_amenities` (`venue_id`, `amenity_id`) VALUES
(@start_venue_id + 14, @wifi), (@start_venue_id + 14, @security), (@start_venue_id + 14, @parking),
(@start_venue_id + 14, @aircon), (@start_venue_id + 14, @projector), (@start_venue_id + 14, @stage),
(@start_venue_id + 14, @vip);
