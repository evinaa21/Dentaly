-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2025 at 09:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dental_clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('pending','completed','canceled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `service_id`, `appointment_date`, `status`, `notes`, `created_at`) VALUES
(2, 2, 2, 2, '2025-04-26 14:00:00', 'completed', NULL, '2025-04-18 18:26:44'),
(3, 1, 5, 1, '2025-04-24 11:08:00', 'completed', NULL, '2025-04-19 11:06:26'),
(34, 1, 2, 1, '2025-04-20 10:00:00', 'pending', NULL, '2025-04-19 12:51:19'),
(35, 2, 2, 2, '2025-04-21 14:00:00', 'completed', NULL, '2025-04-19 12:51:19'),
(38, 30, 5, 5, '2025-04-24 15:00:00', 'canceled', NULL, '2025-04-19 12:51:19'),
(39, 31, 5, 6, '2025-04-25 13:00:00', 'pending', NULL, '2025-04-19 12:51:19'),
(40, 32, 9, 7, '2025-04-26 08:30:00', 'pending', NULL, '2025-04-19 12:51:19'),
(41, 33, 9, 8, '2025-04-27 10:00:00', 'completed', NULL, '2025-04-19 12:51:19'),
(42, 34, 12, 9, '2025-04-28 14:00:00', 'completed', NULL, '2025-04-19 12:51:19'),
(45, 28, 13, 7, '2025-04-08 21:41:00', 'pending', NULL, '2025-04-24 19:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_availability`
--

CREATE TABLE `doctor_availability` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ID of the user receiving the notification (doctor, admin, etc.)',
  `message` varchar(255) NOT NULL COMMENT 'The notification text',
  `link` varchar(255) DEFAULT NULL COMMENT 'Optional link related to the notification (e.g., view_appointment.php?id=123)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = read',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `dental_history` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `last_name`, `contact_number`, `address`, `dental_history`, `created_at`) VALUES
(1, 2, 'Alice', 'Johnson', '555-1234', '123 Elm Street', 'No previous dental surgeries', '2025-04-18 18:26:44'),
(2, 2, 'Bob', 'Williams', '555-5678', '456 Oak Avenue', 'Had a root canal 2 years ago', '2025-04-18 18:26:44'),
(28, 2, 'Charlie', 'Brown', '555-9876', '789 Pine Road', 'Wisdom teeth removed last year', '2025-04-19 12:45:12'),
(29, 2, 'Diana', 'Smith', '555-6543', '321 Maple Lane', 'No significant dental history', '2025-04-19 12:45:12'),
(30, 5, 'Ethan', 'Harris', '555-4321', '654 Cedar Drive', 'Had braces as a teenager', '2025-04-19 12:45:12'),
(31, 5, 'Fiona', 'Clark', '555-8765', '987 Birch Boulevard', 'Regular cleanings every 6 months', '2025-04-19 12:45:12'),
(32, 8, 'George', 'Miller', '555-3456', '159 Spruce Street', 'Cavity fillings 3 years ago', '2025-04-19 12:45:12'),
(33, 8, 'Hannah', 'Davis', '555-7654', '753 Willow Way', 'No dental issues reported', '2025-04-19 12:45:12'),
(34, 9, 'Ian', 'Taylor', '555-2468', '246 Aspen Avenue', 'Teeth whitening procedure last year', '2025-04-19 12:45:12'),
(35, 9, 'Julia', 'Anderson', '555-8642', '864 Redwood Road', 'No dental surgeries', '2025-04-19 12:45:12'),
(49, NULL, 'djsd', 'fsdf', '0505005', '', NULL, '2025-05-06 10:27:50');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `description`, `price`, `created_at`) VALUES
(1, 'Teeth Cleaning', 'A routine cleaning of teeth to remove plaque and tartar buildup', 50.00, '2025-04-18 18:26:44'),
(2, 'Root Canal', 'A procedure to treat infection at the center of a tooth', 200.00, '2025-04-18 18:26:44'),
(5, 'Teeth Whitening', 'A cosmetic procedure to whiten teeth', 150.00, '2025-04-19 12:42:27'),
(6, 'Cavity Filling', 'A procedure to fill cavities in teeth', 100.00, '2025-04-19 12:42:27'),
(7, 'Braces Consultation', 'Initial consultation for braces treatment', 75.00, '2025-04-19 12:42:27'),
(8, 'Wisdom Teeth Removal', 'Surgical removal of wisdom teeth', 300.00, '2025-04-19 12:42:27'),
(9, 'Dental X-Ray', 'X-ray imaging of teeth and jaw', 80.00, '2025-04-19 12:42:27'),
(10, 'Gum Treatment', 'Treatment for gum disease and infections', 120.00, '2025-04-19 12:42:27'),
(11, 'Dental Implant', 'Procedure to replace missing teeth with implants', 1000.00, '2025-04-19 12:42:27'),
(12, 'Orthodontic Adjustment', 'Adjustment of braces or aligners', 60.00, '2025-04-19 12:42:27');

-- --------------------------------------------------------

--
-- Table structure for table `teeth_graph`
--

CREATE TABLE `teeth_graph` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teeth_graph`
--

INSERT INTO `teeth_graph` (`id`, `patient_id`, `appointment_id`, `image_url`, `description`, `created_at`) VALUES
(7, 34, NULL, '../uploads/teeth_graphs/6809ec137f566_rel3.webp', 'kkk', '2025-04-24 07:45:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','receptionist') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Admin User', 'admin@clinic.com', '$2y$10$p7VIWuBSdAS3Vc6lPrJKQ.3sH/2s5tniss3DxE4OHIKZDTW2U2f.K', 'admin', '2025-04-18 18:26:44'),
(2, 'Dr. John Doe', 'john.doe@clinic.com', '$2y$10$NyA4QTYcAO8XgdIGcB5cJ.2vDVLoI7edsFcsY8TrXOadH6tq6OOwu', 'doctor', '2025-04-18 18:26:44'),
(5, 'Rexhens', 'r@clinic.com', '$2y$10$GY1uEd6V9J551j1ArQY5GuXNKlU2GBsVGDWO2ssbrc.6dSadPxfDW', 'doctor', '2025-04-19 10:21:00'),
(7, 'Mark', 'mark.receptionist@clinic.com', '$2y$10$wHm8.ti1MHXh8deUXcVNwOP/1dSHpDPfhrWSFW4aVE9nAigO4xDVq', 'receptionist', '2025-04-19 11:40:51'),
(8, 'Sarah Lee', 'sarah.lee@clinic.com', '$2y$10$SdDITvZX3J3RwHXTyDUZXegXrqJ8wAGkpcw0A.JOW2HORsVV3eBPC', 'doctor', '2025-04-19 12:30:33'),
(9, 'Emily Brown', 'emily.brown@clinic.com', '$2y$10$BaNDjdfMBSzFOaxw.eAYS.BsRb7XcenPBfK2UPVCCYWybEL4ZxjG6', 'doctor', '2025-04-19 12:30:33'),
(10, 'Jane Smith', 'jane.smith@clinic.com', '$2y$10$nvsYtb84SAGdoaz8gqP9feZYfBM/MKixoSdR2wPP7OAOo.Sfb/tgG', 'receptionist', '2025-04-19 12:30:33'),
(11, 'Paul Adams', 'paul.adams@clinic.com', '$2y$10$WwMuBJlF7rJjat3mxVOK9.kz2blDU/ARscNgxRG6P9YTOBqc4815y', 'receptionist', '2025-04-19 12:30:33'),
(12, 'Michael Green', 'michael.green@clinic.com', '$2y$10$0vd0jHXSFc6EL8a1mxVVpuq5poUR7CHNcxE4KZchczlbrQZdycQza', 'doctor', '2025-04-19 12:30:33'),
(13, 'Olivia White', 'olivia.white@clinic.com', '$2y$10$IE4FjjE4/K1Fgn71IUPLq.QyULU8QVrT8aEojJGnPFnh4/VL83bTO', 'doctor', '2025-04-19 12:30:33'),
(14, 'Receptionist Amy', 'amy.receptionist@clinic.com', '$2y$10$JS9k/OeQUnwfMGxeCKPsXe7INPp2aauWORGwHNsc0EtS5cMPtVTOq', 'receptionist', '2025-04-19 12:30:33'),
(15, 'rec', 'rec@clinic.com', '$2y$10$vOztmlxDiIdgyEpImGNxFenyEjngkU/IXNXdEXLusGn1mPt9A02JK', 'receptionist', '2025-04-24 20:16:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`doctor_id`,`day_of_week`,`start_time`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patients_ibfk_1` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teeth_graph`
--
ALTER TABLE `teeth_graph`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `teeth_graph_ibfk_2` (`appointment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `teeth_graph`
--
ALTER TABLE `teeth_graph`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD CONSTRAINT `doctor_availability_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teeth_graph`
--
ALTER TABLE `teeth_graph`
  ADD CONSTRAINT `teeth_graph_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `teeth_graph_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
