-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 28, 2025 at 02:28 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sad_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `amenity_id` int(11) NOT NULL,
  `amenity_name` varchar(100) NOT NULL,
  `default_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`amenity_id`, `amenity_name`, `default_price`) VALUES
(1, 'Air Conditioning', 1200.00),
(2, 'Wi-Fi', 650.00),
(3, 'Security Services', 1500.00),
(4, 'Projector', 1200.00),
(5, 'Parking Space', 900.00),
(6, 'Stage Setup', 6000.00),
(7, 'Accessibility Features', 1000.00),
(8, 'Garden Setup', 3000.00),
(9, 'VIP Lounge', 6500.00),
(10, 'Outdoor Seating', 2500.00),
(11, 'Others', 2000.00);

-- --------------------------------------------------------

--
-- Table structure for table `chat`
--

CREATE TABLE `chat` (
  `chat_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message_text` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `file_url` varchar(255) DEFAULT NULL,
  `is_file` tinyint(1) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `theme` varchar(100) DEFAULT NULL,
  `expected_guests` int(11) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `event_date` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','completed','canceled') DEFAULT 'pending',
  `manager_id` int(11) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_name`, `event_type`, `theme`, `expected_guests`, `total_cost`, `event_date`, `status`, `manager_id`, `venue_id`, `created_at`) VALUES
(1, 'Mike & Anna Wedding', 'Wedding', 'Rustic Garden', 150, 85000.00, '2025-01-15 00:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(2, 'ABC Corp Year-End', 'Corporate', 'Modern Gala', 200, 95000.00, '2025-02-10 00:00:00', 'canceled', 2, 2, '2025-11-03 22:29:33'),
(3, 'Sophia 18th Birthday', 'Birthday', 'Royal Blue', 100, 60000.00, '2025-03-25 00:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(4, 'Charity Concert 2025', 'Concert', 'Hope & Light', 300, 120000.00, '0000-00-00 00:00:00', 'pending', 2, 4, '2025-11-03 22:29:33'),
(5, 'Team Building Summit', 'Corporate', 'Tropical Retreat', 80, 45000.00, '2025-05-12 00:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(6, 'Linux & Julie Wedding', 'Wedding', 'Rustic Garden', 200, 85000.00, '2025-01-21 00:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(7, 'QRT Corp Year-End', 'Corporate', 'Modern Gala', 150, 95000.00, '2025-03-19 00:00:00', 'confirmed', 2, 2, '2025-11-03 22:29:33'),
(8, 'Maricris 18th Birthday', 'Birthday', 'Royal Blue', 100, 60000.00, '2025-04-29 00:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(10, 'Team Collab Summit', 'Corporate', 'Tropical Retreat', 100, 45000.00, '2025-05-13 00:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(11, 'New Year Gala 2020', 'Corporate', 'Celebration', 250, 125000.00, '2020-01-15 18:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(12, 'Valentine Wedding', 'Wedding', 'Romance', 180, 95000.00, '2020-02-14 16:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(13, 'Spring Conference', 'Corporate', 'Business', 200, 85000.00, '2020-03-20 09:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(14, 'Easter Celebration', 'Birthday', 'Spring Garden', 120, 65000.00, '2020-04-10 14:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(15, 'Summer Kickoff', 'Concert', 'Beach Party', 300, 150000.00, '2020-07-05 19:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(16, 'Corporate Retreat', 'Corporate', 'Team Building', 100, 75000.00, '2020-09-12 10:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(17, 'Halloween Party', 'Birthday', 'Spooky', 150, 80000.00, '2020-10-31 20:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(18, 'Christmas Gala', 'Corporate', 'Winter Wonderland', 280, 135000.00, '2020-12-20 18:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(19, 'New Year Celebration 2021', 'Corporate', 'Fresh Start', 200, 110000.00, '2021-01-10 19:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(20, 'Love is in the Air', 'Wedding', 'Romantic', 160, 88000.00, '2021-02-20 15:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(21, 'Spring Fashion Show', 'Corporate', 'Elegant', 220, 98000.00, '2021-03-15 18:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(22, 'April Birthday Bash', 'Birthday', 'Colorful', 90, 55000.00, '2021-04-25 17:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(23, 'Mid-Year Summit', 'Corporate', 'Professional', 180, 92000.00, '2021-06-18 09:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(24, 'Summer Music Festival', 'Concert', 'Vibrant', 350, 180000.00, '2021-07-22 19:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(25, 'Back to Business', 'Corporate', 'Modern', 150, 82000.00, '2021-09-08 10:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(26, 'Autumn Wedding', 'Wedding', 'Fall Colors', 200, 105000.00, '2021-10-15 16:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(27, 'Year End Party 2021', 'Corporate', 'Celebration', 240, 128000.00, '2021-12-18 19:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(28, 'January Kickoff 2022', 'Corporate', 'Goals', 190, 95000.00, '2022-01-20 10:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(29, 'Sweetheart Wedding', 'Wedding', 'Love Story', 175, 92000.00, '2022-02-12 16:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(30, 'Tech Conference', 'Corporate', 'Innovation', 250, 115000.00, '2022-03-28 09:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(31, 'Spring Gala', 'Corporate', 'Elegance', 200, 102000.00, '2022-04-15 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(32, 'May Day Celebration', 'Birthday', 'Garden Party', 110, 68000.00, '2022-05-01 15:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(33, 'Summer Solstice Concert', 'Concert', 'Sunset', 320, 165000.00, '2022-06-21 19:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(34, 'July Wedding Extravaganza', 'Wedding', 'Grand', 220, 118000.00, '2022-07-16 17:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(35, 'Corporate Anniversary', 'Corporate', 'Milestone', 180, 95000.00, '2022-09-25 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(36, 'October Fest', 'Birthday', 'Bavarian', 140, 78000.00, '2022-10-20 19:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(37, 'Holiday Spectacular', 'Concert', 'Christmas', 300, 155000.00, '2022-12-15 19:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(38, 'New Beginnings 2023', 'Corporate', 'Fresh', 210, 108000.00, '2023-01-14 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(39, 'Valentine Gala', 'Birthday', 'Love', 130, 72000.00, '2023-02-14 19:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(40, 'March Madness', 'Corporate', 'Sports', 190, 98000.00, '2023-03-22 17:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(41, 'Spring Wedding Bliss', 'Wedding', 'Floral', 195, 105000.00, '2023-04-08 16:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(42, 'May Corporate Summit', 'Corporate', 'Leadership', 220, 112000.00, '2023-05-19 09:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(43, 'Summer Concert Series', 'Concert', 'Rock', 340, 175000.00, '2023-07-28 20:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(44, 'August Birthday Party', 'Birthday', 'Tropical', 100, 62000.00, '2023-08-12 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(45, 'Fall Business Expo', 'Corporate', 'Exhibition', 260, 132000.00, '2023-09-30 10:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(46, 'Halloween Masquerade', 'Birthday', 'Mysterious', 180, 92000.00, '2023-10-31 20:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(47, 'November Wedding', 'Wedding', 'Elegant', 170, 95000.00, '2023-11-18 15:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(48, 'Holiday Party 2023', 'Corporate', 'Festive', 250, 130000.00, '2023-12-22 19:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(49, 'January Business Launch', 'Corporate', 'Innovation', 200, 105000.00, '2024-01-25 10:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(50, 'February Romance', 'Wedding', 'Love', 185, 98000.00, '2024-02-16 16:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(51, 'Spring Conference 2024', 'Corporate', 'Growth', 230, 118000.00, '2024-03-12 09:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(52, 'April Showers Gala', 'Birthday', 'Garden', 120, 70000.00, '2024-04-20 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(53, 'May Day Wedding', 'Wedding', 'Spring', 190, 102000.00, '2024-05-11 15:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(54, 'Mid-Year Concert', 'Concert', 'Pop', 310, 162000.00, '2024-06-28 19:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(55, 'July Corporate Retreat', 'Corporate', 'Team', 150, 85000.00, '2024-07-15 10:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(56, 'August Birthday Bash', 'Birthday', 'Beach', 140, 78000.00, '2024-08-24 17:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(57, 'September Elegance', 'Wedding', 'Classic', 205, 112000.00, '2024-09-14 16:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(58, 'October Conference', 'Corporate', 'Professional', 240, 125000.00, '2024-10-18 09:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(59, 'November Thanksgiving', 'Birthday', 'Harvest', 160, 88000.00, '2024-11-28 18:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(60, 'December Holiday Gala', 'Corporate', 'Winter', 270, 140000.00, '2024-12-20 19:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(61, 'January Celebration 2025', 'Corporate', 'New Year', 215, 112000.00, '2025-01-18 18:00:00', 'canceled', 2, 2, '2025-11-03 22:29:33'),
(62, 'February Love Fest', 'Wedding', 'Romantic', 180, 96000.00, '2025-02-22 16:00:00', 'completed', 2, 1, '2025-11-03 22:29:33'),
(63, 'March Business Summit', 'Corporate', 'Strategy', 225, 115000.00, '2025-03-16 09:00:00', 'completed', 2, 3, '2025-11-03 22:29:33'),
(64, 'April Spring Wedding', 'Wedding', 'Floral Garden', 195, 105000.00, '2025-04-12 15:00:00', 'canceled', 2, 1, '2025-11-03 22:29:33'),
(65, 'May Tech Expo', 'Corporate', 'Technology', 250, 128000.00, '2025-05-20 10:00:00', 'canceled', 2, 4, '2025-11-03 22:29:33'),
(66, 'June Summer Bash', 'Birthday', 'Tropical', 130, 75000.00, '2025-06-15 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33'),
(67, 'July Concert Night', 'Concert', 'Music', 320, 168000.00, '2025-07-25 20:00:00', 'completed', 2, 4, '2025-11-03 22:29:33'),
(68, 'August Corporate Gala', 'Corporate', 'Elegant', 200, 108000.00, '2025-08-30 19:00:00', 'canceled', 2, 3, '2025-11-03 22:29:33'),
(69, 'September Wedding Dream', 'Wedding', 'Romantic', 210, 115000.00, '2025-09-20 00:00:00', 'canceled', 2, 1, '2025-11-03 22:29:33'),
(70, 'October Birthday Party', 'Birthday', 'Autumn', 145, 82000.00, '2025-10-25 18:00:00', 'completed', 2, 2, '2025-11-03 22:29:33');

-- --------------------------------------------------------

--
-- Table structure for table `event_contracts`
--

CREATE TABLE `event_contracts` (
  `contract_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `contract_text` text DEFAULT NULL,
  `signed_status` enum('pending','approved') DEFAULT 'pending',
  `file` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_services`
--

CREATE TABLE `event_services` (
  `event_service_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `status` enum('pending','booked','canceled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parking`
--

CREATE TABLE `parking` (
  `parking_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `two_wheels` int(11) DEFAULT NULL,
  `four_wheels` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prices`
--

CREATE TABLE `prices` (
  `price_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `peak_price` decimal(10,2) DEFAULT NULL,
  `offpeak_price` decimal(10,2) DEFAULT NULL,
  `weekday_price` decimal(10,2) DEFAULT NULL,
  `weekend_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `category`, `description`, `price`, `supplier_id`) VALUES
(1, 'Basic Sound Package', 'Lights and Sounds', 'Includes speakers, mic, and DJ setup.', 12000.00, 1),
(2, 'Premium Photography', 'Photography', 'Full-day coverage with edited album.', 25000.00, 2),
(3, 'Floral Styling', 'Styling and Flowers', 'Full floral and event styling setup.', 18000.00, 3);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `service_category` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `availability_status` enum('available','booked') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `service_category`, `email`, `phone`, `location`, `availability_status`) VALUES
(1, 'Luxe Lights & Sounds', 'Lights and Sounds', 'luxe@suppliers.com', '09172223333', 'Makati City', 'available'),
(2, 'Perfect Shots Photography', 'Photography', 'shots@suppliers.com', '09175556666', 'Quezon City', 'available'),
(3, 'Blooms & Beyond', 'Styling and Flowers', 'blooms@suppliers.com', '09173334444', 'Taguig City', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('administrator','manager','organizer','supplier') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `first_name`, `last_name`, `email`, `phone`, `role`, `status`, `created_at`) VALUES
(1, 'admin123', '$2y$10$uh3m79DGqHoJ8z/HCo4iluGb18gWzZEj0MT.TaWU9e1l5lDiolBTi', 'System', 'Admin', 'admin@gmail.com', '09171234567', 'administrator', 'active', '2025-11-08 15:04:09'),
(2, 'manager_linux', '$2y$10$uh3m79DGqHoJ8z/HCo4iluGb18gWzZEj0MT.TaWU9e1l5lDiolBTi', 'Linux', 'Adona', 'linux@gmail.com', '09181234567', 'manager', 'active', '2025-11-08 15:04:09'),
(6, 'organizer_adrian', '$2y$10$DOYBedKeOf3.SsEHrxVdfu0X90eYn8uunI4/RU2FnH6XRA9ekw/Jy', 'Adrian', 'Cornado', 'adrian@gmail.com', '09201234567', 'organizer', 'active', '2025-11-08 15:04:09'),
(8, 'dore', '$2y$10$iI55luzQgDAV.g4wBdpA/Oz0S5nbV4A7aPkmqtLJgxNXa.X/psm1O', 'Dorina', 'Cables', 'dore@gmail.com', '09211234567', 'manager', 'active', '2025-11-17 11:33:32'),
(9, 'maricris', '$2y$10$1bUMzJLAaT9Bo8T4ZTqFd.Bd8toKSHXBM6CYcWUTJBBNthmsvUTrS', 'Maricris', 'Barcelon', 'maricris@gmail.com', '09221234567', 'organizer', 'active', '2025-11-17 11:37:32');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `venue_id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `venue_name` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `availability_status` enum('available','booked') DEFAULT 'available',
  `image` blob DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`venue_id`, `manager_id`, `venue_name`, `location`, `capacity`, `description`, `availability_status`, `image`, `status`, `created_at`) VALUES
(1, 2, 'Crystal Hall', 'Taguig City', 300, 'Elegant indoor venue ideal for weddings and corporate events.', 'available', NULL, 'active', '2025-11-08 15:04:09'),
(2, 2, 'Aurora Pavilion', 'Makati City', 200, 'Modern glass pavilion with garden access.', 'available', NULL, 'active', '2025-11-08 15:04:09'),
(3, 2, 'Emerald Garden', 'Quezon City', 150, 'Outdoor garden venue surrounded by lush greenery.', 'available', NULL, 'active', '2025-11-08 15:04:09'),
(4, 2, 'Sunset Veranda', 'Pasay City', 250, 'Seaside view venue perfect for receptions.', 'available', NULL, 'active', '2025-11-08 15:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `venue_amenities`
--

CREATE TABLE `venue_amenities` (
  `venue_amenity_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`amenity_id`);

--
-- Indexes for table `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`chat_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_sender_receiver` (`sender_id`,`receiver_id`),
  ADD KEY `idx_receiver_sender` (`receiver_id`,`sender_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `coordinator_id` (`manager_id`),
  ADD KEY `venue_id` (`venue_id`);

--
-- Indexes for table `event_contracts`
--
ALTER TABLE `event_contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `event_services`
--
ALTER TABLE `event_services`
  ADD PRIMARY KEY (`event_service_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `parking`
--
ALTER TABLE `parking`
  ADD PRIMARY KEY (`parking_id`);

--
-- Indexes for table `prices`
--
ALTER TABLE `prices`
  ADD PRIMARY KEY (`price_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`venue_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `amenity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `chat`
--
ALTER TABLE `chat`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `event_contracts`
--
ALTER TABLE `event_contracts`
  MODIFY `contract_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_services`
--
ALTER TABLE `event_services`
  MODIFY `event_service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parking`
--
ALTER TABLE `parking`
  MODIFY `parking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prices`
--
ALTER TABLE `prices`
  MODIFY `price_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `chat_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_ibfk_3` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON DELETE SET NULL;

--
-- Constraints for table `event_contracts`
--
ALTER TABLE `event_contracts`
  ADD CONSTRAINT `event_contracts_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_services`
--
ALTER TABLE `event_services`
  ADD CONSTRAINT `event_services_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
