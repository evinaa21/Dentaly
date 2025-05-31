-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 28, 2025 at 11:37 PM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, NULL, 'John', 'Anderson', '555-0101', '123 Main Street, Downtown', 'Regular cleanings, no major procedures', '2025-05-28 21:36:35'),
(2, NULL, 'Emma', 'Johnson', '555-0102', '456 Oak Avenue, Midtown', 'Had wisdom teeth removed in 2023', '2025-05-28 21:36:35'),
(3, NULL, 'Michael', 'Williams', '555-0103', '789 Pine Road, Uptown', 'Root canal on molar #19 in 2022', '2025-05-28 21:36:35'),
(4, NULL, 'Sophia', 'Brown', '555-0104', '321 Elm Street, Westside', 'Braces from 2020-2022, excellent oral health', '2025-05-28 21:36:35'),
(5, NULL, 'William', 'Davis', '555-0105', '654 Maple Lane, Eastside', 'Multiple fillings, gum treatment needed', '2025-05-28 21:36:35'),
(6, NULL, 'Olivia', 'Miller', '555-0106', '987 Cedar Drive, Northside', 'No significant dental history', '2025-05-28 21:36:35'),
(7, NULL, 'James', 'Wilson', '555-0107', '147 Birch Boulevard, Southside', 'Crown on front tooth, regular maintenance', '2025-05-28 21:36:35'),
(8, NULL, 'Isabella', 'Moore', '555-0108', '258 Spruce Street, Central', 'Dental implant consultation needed', '2025-05-28 21:36:35'),
(9, NULL, 'Benjamin', 'Taylor', '555-0109', '369 Willow Way, Riverside', 'Orthodontic treatment completed', '2025-05-28 21:36:35'),
(10, NULL, 'Mia', 'Anderson', '555-0110', '741 Aspen Avenue, Hillside', 'Emergency root canal in 2024', '2025-05-28 21:36:35'),
(11, NULL, 'Lucas', 'Thomas', '555-0111', '852 Redwood Road, Valley', 'Regular patient, preventive care', '2025-05-28 21:36:35'),
(12, NULL, 'Charlotte', 'Jackson', '555-0112', '963 Poplar Place, Heights', 'Teeth whitening, cosmetic focus', '2025-05-28 21:36:35'),
(13, NULL, 'Henry', 'White', '555-0113', '159 Sycamore Street, Gardens', 'Bridge work on lower jaw', '2025-05-28 21:36:35'),
(14, NULL, 'Amelia', 'Harris', '555-0114', '357 Dogwood Drive, Meadows', 'Night guard for teeth grinding', '2025-05-28 21:36:35'),
(15, NULL, 'Alexander', 'Martin', '555-0115', '468 Hickory Hill, Lakeside', 'Gum disease treatment ongoing', '2025-05-28 21:36:35'),
(16, NULL, 'Harper', 'Garcia', '555-0116', '579 Magnolia Manor, Parkside', 'Cavity-prone, frequent checkups', '2025-05-28 21:36:35'),
(17, NULL, 'Ethan', 'Rodriguez', '555-0117', '681 Walnut Way, Creekside', 'Wisdom teeth extraction needed', '2025-05-28 21:36:35'),
(18, NULL, 'Evelyn', 'Lewis', '555-0118', '792 Chestnut Court, Woodland', 'Perfect dental health, preventive only', '2025-05-28 21:36:35'),
(19, NULL, 'Daniel', 'Lee', '555-0119', '813 Pecan Path, Fairway', 'Multiple crowns, high maintenance', '2025-05-28 21:36:35'),
(20, NULL, 'Abigail', 'Walker', '555-0120', '924 Acorn Acres, Sunset', 'Orthodontic retainer adjustments', '2025-05-28 21:36:35'),
(21, NULL, 'Matthew', 'Hall', '555-0121', '135 Hazel Heights, Sunrise', 'Emergency patient, trauma history', '2025-05-28 21:36:35'),
(22, NULL, 'Emily', 'Allen', '555-0122', '246 Juniper Junction, Moonlight', 'Routine care, excellent hygiene', '2025-05-28 21:36:35'),
(23, NULL, 'Joseph', 'Young', '555-0123', '357 Fir Forest, Starlight', 'Implant surgery completed', '2025-05-28 21:36:35'),
(24, NULL, 'Elizabeth', 'Hernandez', '555-0124', '468 Pine Plaza, Daybreak', 'Pediatric to adult transition', '2025-05-28 21:36:35'),
(25, NULL, 'David', 'King', '555-0125', '579 Oak Oaks, Twilight', 'TMJ treatment and night guard', '2025-05-28 21:36:35'),
(26, NULL, 'Sofia', 'Wright', '555-0126', '681 Elm Estate, Midnight', 'Cosmetic dentistry focus', '2025-05-28 21:36:35'),
(27, NULL, 'Samuel', 'Lopez', '555-0127', '792 Maple Manor, Dawn', 'Sports injury, crown replacement', '2025-05-28 21:36:35'),
(28, NULL, 'Grace', 'Hill', '555-0128', '813 Cedar Circle, Dusk', 'Regular cleanings, no issues', '2025-05-28 21:36:35'),
(29, NULL, 'Jackson', 'Scott', '555-0129', '924 Birch Bay, Noon', 'Wisdom teeth monitoring', '2025-05-28 21:36:35'),
(30, NULL, 'Victoria', 'Green', '555-0130', '135 Willow Walk, Storm', 'Gum grafting procedure needed', '2025-05-28 21:36:35');

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
(1, 'General Checkup', 'Comprehensive dental examination and consultation', 75.00, '2025-05-28 21:36:35'),
(2, 'Teeth Cleaning', 'Professional dental cleaning and plaque removal', 85.00, '2025-05-28 21:36:35'),
(3, 'Teeth Whitening', 'Professional teeth whitening treatment', 180.00, '2025-05-28 21:36:35'),
(4, 'Cavity Filling', 'Tooth restoration with composite or amalgam filling', 120.00, '2025-05-28 21:36:35'),
(5, 'Root Canal Therapy', 'Endodontic treatment for infected tooth pulp', 350.00, '2025-05-28 21:36:35'),
(6, 'Crown Installation', 'Dental crown placement for damaged teeth', 450.00, '2025-05-28 21:36:35'),
(7, 'Tooth Extraction', 'Surgical or simple tooth removal', 150.00, '2025-05-28 21:36:35'),
(8, 'Wisdom Teeth Removal', 'Surgical extraction of wisdom teeth', 400.00, '2025-05-28 21:36:35'),
(9, 'Dental X-Ray', 'Digital radiographic imaging of teeth and jaw', 90.00, '2025-05-28 21:36:35'),
(10, 'Braces Consultation', 'Orthodontic evaluation and treatment planning', 100.00, '2025-05-28 21:36:35'),
(11, 'Braces Installation', 'Complete orthodontic braces setup', 2500.00, '2025-05-28 21:36:35'),
(12, 'Orthodontic Adjustment', 'Monthly braces adjustment and monitoring', 80.00, '2025-05-28 21:36:35'),
(13, 'Gum Treatment', 'Periodontal therapy for gum disease', 200.00, '2025-05-28 21:36:35'),
(14, 'Dental Implant', 'Surgical implant placement for missing teeth', 1200.00, '2025-05-28 21:36:35'),
(15, 'Bridge Installation', 'Dental bridge for multiple missing teeth', 800.00, '2025-05-28 21:36:35'),
(16, 'Dentures', 'Complete or partial denture fitting', 600.00, '2025-05-28 21:36:35'),
(17, 'Emergency Treatment', 'Urgent dental care for pain or trauma', 180.00, '2025-05-28 21:36:35'),
(18, 'Fluoride Treatment', 'Professional fluoride application', 50.00, '2025-05-28 21:36:35'),
(19, 'Sealants', 'Protective sealant application for molars', 60.00, '2025-05-28 21:36:35'),
(20, 'Night Guard', 'Custom night guard for teeth grinding', 250.00, '2025-05-28 21:36:35');

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
(1, 'Test Admin', 'admin@test.com', 'hashed_password', 'admin', '2025-05-28 21:30:25'),
(2, 'Dr. Alice Smith', 'alice@test.com', 'hashed_password', 'doctor', '2025-05-28 21:30:25'),
(3, 'Dr. Bob Jones', 'bob@test.com', 'hashed_password', 'doctor', '2025-05-28 21:30:25'),
(4, 'Receptionist Jane', 'jane@test.com', 'hashed_password', 'receptionist', '2025-05-28 21:30:25'),
(5, 'Admin Manager', 'admin@dentaly.com', '$2y$10$p7VIWuBSdAS3Vc6lPrJKQ.3sH/2s5tniss3DxE4OHIKZDTW2U2f.K', 'admin', '2025-05-28 21:36:35'),
(6, 'Dr. Sarah Wilson', 'sarah.wilson@dentaly.com', '$2y$10$NyA4QTYcAO8XgdIGcB5cJ.2vDVLoI7edsFcsY8TrXOadH6tq6OOwu', 'doctor', '2025-05-28 21:36:35'),
(7, 'Dr. Michael Chen', 'michael.chen@dentaly.com', '$2y$10$GY1uEd6V9J551j1ArQY5GuXNKlU2GBsVGDWO2ssbrc.6dSadPxfDW', 'doctor', '2025-05-28 21:36:35'),
(8, 'Dr. Emily Rodriguez', 'emily.rodriguez@dentaly.com', '$2y$10$SdDITvZX3J3RwHXTyDUZXegXrqJ8wAGkpcw0A.JOW2HORsVV3eBPC', 'doctor', '2025-05-28 21:36:35'),
(9, 'Dr. James Thompson', 'james.thompson@dentaly.com', '$2y$10$BaNDjdfMBSzFOaxw.eAYS.BsRb7XcenPBfK2UPVCCYWybEL4ZxjG6', 'doctor', '2025-05-28 21:36:35'),
(10, 'Dr. Lisa Park', 'lisa.park@dentaly.com', '$2y$10$0vd0jHXSFc6EL8a1mxVVpuq5poUR7CHNcxE4KZchczlbrQZdycQza', 'doctor', '2025-05-28 21:36:35'),
(11, 'Dr. Robert Davis', 'robert.davis@dentaly.com', '$2y$10$IE4FjjE4/K1Fgn71IUPLq.QyULU8QVrT8aEojJGnPFnh4/VL83bTO', 'doctor', '2025-05-28 21:36:35'),
(12, 'Maria Garcia', 'maria.garcia@dentaly.com', '$2y$10$wHm8.ti1MHXh8deUXcVNwOP/1dSHpDPfhrWSFW4aVE9nAigO4xDVq', 'receptionist', '2025-05-28 21:36:35'),
(13, 'Jennifer Smith', 'jennifer.smith@dentaly.com', '$2y$10$nvsYtb84SAGdoaz8gqP9feZYfBM/MKixoSdR2wPP7OAOo.Sfb/tgG', 'receptionist', '2025-05-28 21:36:35'),
(14, 'David Johnson', 'david.johnson@dentaly.com', '$2y$10$WwMuBJlF7rJjat3mxVOK9.kz2blDU/ARscNgxRG6P9YTOBqc4815y', 'receptionist', '2025-05-28 21:36:35'),
(15, 'Amanda Brown', 'amanda.brown@dentaly.com', '$2y$10$JS9k/OeQUnwfMGxeCKPsXe7INPp2aauWORGwHNsc0EtS5cMPtVTOq', 'receptionist', '2025-05-28 21:36:35'),
(16, 'Kevin Wilson', 'kevin.wilson@dentaly.com', '$2y$10$vOztmlxDiIdgyEpImGNxFenyEjngkU/IXNXdEXLusGn1mPt9A02JK', 'receptionist', '2025-05-28 21:36:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `teeth_graph`
--
ALTER TABLE `teeth_graph`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

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
