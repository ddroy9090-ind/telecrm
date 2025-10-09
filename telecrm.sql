-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2025 at 02:36 PM
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
(5, 'Administrator', 'admin@example.com', '$2y$10$FczfYBAJzoibRAjjDKzFQOAmYmkUZnu0RRnsixoIIMoAHP6A2tMi6', '+1 555 0100', 'admin', '2025-10-08 12:18:29', '2025-10-08 12:18:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

-- --------------------------------------------------------

--
-- Table structure for table `All leads`
--

CREATE TABLE `All leads` (
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
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `All leads`
--

ALTER TABLE `All leads`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `All leads`
--
ALTER TABLE `All leads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
