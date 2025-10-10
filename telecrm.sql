-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2025 at 03:16 PM
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
-- Database: `telecrm`
--

-- --------------------------------------------------------

--
-- Table structure for table `all_leads`
--

CREATE TABLE `all_leads` (
  `id` int(10) UNSIGNED NOT NULL,
  `stage` varchar(50) DEFAULT NULL,
  `rating` varchar(50) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `alternate_phone` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `interested_in` varchar(100) DEFAULT NULL,
  `property_type` varchar(100) DEFAULT NULL,
  `location_preferences` varchar(255) DEFAULT NULL,
  `budget_range` varchar(100) DEFAULT NULL,
  `size_required` varchar(100) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `urgency` varchar(100) DEFAULT NULL,
  `alternate_email` varchar(255) DEFAULT NULL,
  `payout_received` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `all_leads`
--

INSERT INTO `all_leads` (`id`, `stage`, `rating`, `assigned_to`, `source`, `name`, `phone`, `email`, `alternate_phone`, `nationality`, `interested_in`, `property_type`, `location_preferences`, `budget_range`, `size_required`, `purpose`, `urgency`, `alternate_email`, `payout_received`, `created_at`) VALUES
(1, '[\"New\", \"Contacted\", \"Follow Up - In Progress\", \"Q', 'Warm', 'Agent 1', 'Website', 'Shoaib Ahmad', '08400438136', 'shoaib@reliantsurveyors.com', '08400438136', 'India', 'Invest', 'Apartment', 'Dubai', '500K - 1.9 M', '35,00 sq.ft', 'For Living', 'Flexible', 'shoaib@reliantsurveyors.com', 1, '2025-10-09 10:20:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `contact_number` varchar(30) DEFAULT NULL,
  `role` enum('admin','manager','agent') NOT NULL DEFAULT 'agent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `contact_number`, `role`, `created_at`, `updated_at`) VALUES
(5, 'Administrator', 'admin@example.com', '$2y$10$FczfYBAJzoibRAjjDKzFQOAmYmkUZnu0RRnsixoIIMoAHP6A2tMi6', NULL, 'admin', '2025-10-08 12:18:29', '2025-10-08 12:18:29'),
(6, 'Shoaib Ahmad', 'shoaib@reliantsurveyors.com', '$2y$10$E7jMZWFOxdLdtMGE3dVu9eQlDzOr0beyw5Ok99goRnKq6UgtL9hqe', '+918400438136', 'manager', '2025-10-09 06:41:29', '2025-10-09 06:41:29'),
(7, 'Dev', 'dev@gmail.com', '$2y$10$fSbgZF.4QqsoEIhOITTmKuWaeMRTPibWyIyd72GUrt.LV8TYch3/.', '+918400438136', 'agent', '2025-10-09 06:42:01', '2025-10-09 06:42:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `all_leads`
--
ALTER TABLE `all_leads`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `all_leads`
--
ALTER TABLE `all_leads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
