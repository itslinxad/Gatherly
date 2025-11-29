-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2025 at 05:36 AM
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
  `event_date` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `status` enum('pending','confirmed','completed','canceled') DEFAULT 'pending',
  `organizer_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `event_services`
--

INSERT INTO `event_services` (`event_service_id`, `event_id`, `service_id`, `status`) VALUES
(7, 1, 1, 'pending'),
(8, 2, 1, 'pending'),
(9, 1, 1, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `baranggay` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
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
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `image` longblob DEFAULT NULL,
  `reference_no` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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

--
-- Dumping data for table `prices`
--

INSERT INTO `prices` (`price_id`, `venue_id`, `base_price`, `peak_price`, `offpeak_price`, `weekday_price`, `weekend_price`) VALUES
(1, 1, 50000.00, 65000.00, 40000.00, 48000.00, 60000.00),
(2, 2, 40000.00, 55000.00, 35000.00, 42000.00, 50000.00),
(3, 3, 35000.00, 50000.00, 30000.00, 37000.00, 45000.00),
(4, 4, 45000.00, 60000.00, 35000.00, 40000.00, 55000.00);

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
  `location_id` int(11) DEFAULT NULL,
  `venue_name` varchar(100) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `availability_status` enum('available','booked') DEFAULT 'available',
  `image` blob DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `parking`
--
ALTER TABLE `parking`
  ADD PRIMARY KEY (`parking_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

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
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_contracts`
--
ALTER TABLE `event_contracts`
  MODIFY `contract_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_services`
--
ALTER TABLE `event_services`
  MODIFY `event_service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parking`
--
ALTER TABLE `parking`
  MODIFY `parking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prices`
--
ALTER TABLE `prices`
  MODIFY `price_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `chat_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `event_services`
--
ALTER TABLE `event_services`
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
