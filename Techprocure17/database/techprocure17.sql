-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 04, 2026 at 10:09 AM
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
-- Database: `techprocure17`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'User Registered', 'buyer', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 10:53:08'),
(2, 2, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:03:15'),
(3, 2, 'Requested Quotation', 'quotation', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:33:28'),
(4, 2, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:43:15'),
(5, 2, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:43:24'),
(6, 2, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:43:48'),
(7, 2, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:44:00'),
(8, 2, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 11:44:24'),
(9, 5, 'Admin Registered', 'user', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 12:23:56'),
(10, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 19:32:03'),
(11, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 19:49:04'),
(12, 5, 'Approved Supplier', 'supplier', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 19:57:14'),
(13, 5, 'Added Product', 'product', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 20:00:20'),
(14, 5, 'Approved Product', 'product', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 20:05:10'),
(15, 5, 'Updated Product', 'product', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 21:44:15'),
(16, 5, 'Added Product', 'product', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:08:44'),
(17, 5, 'Approved Product', 'product', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:09:14'),
(18, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:10:43'),
(19, 5, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:10:56'),
(20, 5, 'Updated Product', 'product', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:12:06'),
(21, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:15:09'),
(22, 8, 'User Registered', 'buyer', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:16:23'),
(23, 9, 'User Registered', 'buyer', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:19:00'),
(24, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:19:16'),
(25, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-17 22:29:49'),
(26, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-18 07:50:31'),
(27, 9, 'Placed Order', 'order', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-18 09:19:53'),
(28, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-18 09:38:24'),
(29, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:06:16'),
(30, 9, 'Payment Made', 'payment', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:27:26'),
(31, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:31:24'),
(32, 5, 'Approved Supplier', 'supplier', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:34:34'),
(33, 5, 'Updated Order Status', 'order', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:42:45'),
(34, 5, 'Refunded Payment', 'payment', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:47:05'),
(35, 5, 'Updated Order Status', 'order', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:48:02'),
(36, 5, 'Updated Order Status', 'order', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 22:48:27'),
(37, 5, 'Updated Order Status', 'order', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 23:16:14'),
(38, 5, 'Created Database Backup', 'system', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 23:25:46'),
(39, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 23:27:33'),
(40, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 23:27:48'),
(41, 5, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-23 10:27:35'),
(42, 5, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 09:58:17'),
(43, 5, 'Added Product', 'product', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 10:14:56'),
(44, 5, 'Approved Product', 'product', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 10:15:22'),
(45, 5, 'Toggled Product Status', 'product', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 10:17:02'),
(46, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 12:34:46'),
(47, 9, 'Placed Order', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 16:25:30'),
(48, 9, 'Placed Order', 'order', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 16:31:30'),
(49, 9, 'Placed Order', 'order', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 16:40:26'),
(50, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 17:00:30'),
(51, 5, 'Updated Order Status', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 17:04:52'),
(52, 5, 'Updated Order Status', 'order', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 17:05:53'),
(53, 5, 'Updated Order Status', 'order', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 17:06:11'),
(54, 5, 'Updated System Settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:10:33'),
(55, 5, 'Updated System Settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:38:10'),
(56, 5, 'Updated System Settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:38:29'),
(57, 5, 'Cleared System Cache', 'system', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:38:40'),
(58, 5, 'Updated order #3 to completed', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:52:13'),
(59, 5, 'Updated order #3 to completed', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:52:29'),
(60, 5, 'Updated order #3 to delivered', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:53:01'),
(61, 5, 'Updated order #4 to shipped', 'order', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:53:14'),
(62, 5, 'Updated order #3 to cancelled', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:54:24'),
(63, 5, 'Updated System Settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 23:55:42'),
(64, 5, 'Updated Product', 'product', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:00:42'),
(65, 5, 'Placed Order', 'order', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:02:19'),
(66, 5, 'Placed Order', 'order', 7, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:10:03'),
(67, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:21:48'),
(68, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:21:59'),
(69, 9, 'Placed Order', 'order', 8, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:39:35'),
(70, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:39:55'),
(71, 5, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:40:03'),
(72, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 11:40:11'),
(73, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:24:27'),
(74, 9, 'Placed Order', 'order', 9, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:26:10'),
(75, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:28:46'),
(76, 5, 'Updated order #9 to completed', 'order', 9, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:29:41'),
(77, 5, 'Updated Order Status', 'order', 9, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:31:05'),
(78, 5, 'Released Escrow Payment', 'escrow', 8, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:51:01'),
(79, 5, 'Released Escrow Payment', 'escrow', 7, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:51:07'),
(80, 5, 'Released Escrow Payment', 'escrow', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:51:13'),
(81, 5, 'Deleted Payment', 'payment', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 18:51:44'),
(82, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:46:29'),
(83, 5, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 09:19:04'),
(84, 5, 'Updated order #9 to shipped', 'order', 9, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 09:22:33'),
(85, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:02:35'),
(86, 9, 'Placed Order', 'order', 10, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:05:07'),
(87, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:09:44'),
(88, 5, 'Updated Product', 'product', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:19:54'),
(89, 5, 'Updated Product', 'product', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:20:15'),
(90, 5, 'Updated order #6 to completed', 'order', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:21:56'),
(91, 5, 'Updated order #7 to completed', 'order', 7, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:22:18'),
(92, 5, 'Updated order #8 to completed', 'order', 8, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:22:34'),
(93, 5, 'Updated order #10 to completed', 'order', 10, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:22:43'),
(94, 5, 'Updated order #4 to completed', 'order', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:22:58'),
(95, 5, 'Updated order #3 to completed', 'order', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:23:13'),
(96, 5, 'Released Escrow Payment', 'escrow', 10, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:24:32'),
(97, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:30:15'),
(98, 5, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:32:40'),
(99, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:37:56'),
(100, 10, 'User Registered', 'buyer', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 13:59:25'),
(101, 11, 'User Registered', 'buyer', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 14:03:02'),
(102, 12, 'User Registered', 'buyer', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 14:37:31'),
(103, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 14:48:52'),
(104, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 15:31:24'),
(105, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 15:32:07'),
(106, NULL, 'Failed Login Attempt', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 15:32:38'),
(107, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 15:32:52'),
(108, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 15:41:47'),
(109, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 15:45:34'),
(110, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 16:16:48'),
(111, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 16:20:25'),
(112, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 16:52:47'),
(113, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 16:53:21'),
(114, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 16:59:46'),
(115, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 17:04:00'),
(116, 9, 'User Logged In', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 17:04:09'),
(117, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 17:04:29'),
(118, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 17:41:26'),
(119, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-28 19:56:02'),
(120, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-28 20:00:49'),
(121, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-28 20:39:05'),
(122, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 08:28:09'),
(123, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 09:30:42'),
(124, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 16:32:43'),
(125, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 16:34:05'),
(126, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 17:58:02'),
(127, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 18:10:26'),
(128, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 19:01:54'),
(129, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 19:42:02'),
(130, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 21:04:37'),
(131, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 21:06:40'),
(132, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 22:39:25'),
(133, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 22:52:02'),
(134, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 22:58:31'),
(135, 20, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-01 23:03:36'),
(136, 20, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 08:54:10'),
(137, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 09:04:47'),
(138, 20, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 09:35:16'),
(139, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 11:47:39'),
(140, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 11:48:49'),
(141, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 11:53:39'),
(142, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 13:14:43'),
(143, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 14:03:27'),
(144, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 19:40:16'),
(145, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 15:58:19'),
(146, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 16:12:07'),
(147, 20, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 21:42:24'),
(148, 9, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 22:46:10'),
(149, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:16:22'),
(150, 20, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:17:00'),
(151, 5, 'User Logged Out', 'auth', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 09:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','manager','support') DEFAULT 'manager',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `created_at`) VALUES
(1, 'admin', 'admin@techprocure.co.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'super_admin', 'active', '2026-06-27 06:11:33'),
(2, 'superadmin', 'superadmin@techprocure.co.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'super_admin', 'active', '2026-06-29 06:41:06');

-- --------------------------------------------------------

--
-- Table structure for table `api_logs`
--

CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL,
  `api_key` varchar(100) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `status_code` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_entities`
--

CREATE TABLE `blocked_entities` (
  `id` int(11) NOT NULL,
  `entity_type` enum('ip','user','email') NOT NULL,
  `entity_value` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `views` int(11) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`, `logo`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'HP', 'hp', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(2, 'Dell', 'dell', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(3, 'Lenovo', 'lenovo', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(4, 'Apple', 'apple', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(5, 'Samsung', 'samsung', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(6, 'Sony', 'sony', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(7, 'LG', 'lg', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(8, 'Microsoft', 'microsoft', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(9, 'Intel', 'intel', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(10, 'AMD', 'amd', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(11, 'Cisco', 'cisco', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10'),
(12, 'IBM', 'ibm', NULL, NULL, 'active', '2026-07-03 13:20:10', '2026-07-03 13:20:10');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_discounts`
--

CREATE TABLE `bulk_discounts` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `min_quantity` int(11) NOT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `fixed_price` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_class` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon_class`, `parent_id`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Computers', 'computers', 'Desktop computers and workstations', 'fa-desktop', NULL, 1, 'active', '2026-06-16 12:08:44', NULL),
(2, 'Laptops', 'laptops', 'Laptops and notebooks', 'fa-laptop', NULL, 2, 'active', '2026-06-16 12:08:44', NULL),
(3, 'Servers', 'servers', 'Enterprise servers and racks', 'fa-server', NULL, 3, 'active', '2026-06-16 12:08:44', NULL),
(4, 'Networking', 'networking', 'Switches, routers, and networking equipment', 'fa-network-wired', NULL, 4, 'active', '2026-06-16 12:08:44', NULL),
(5, 'Software', 'software', 'Software licenses and solutions', 'fa-code', NULL, 5, 'active', '2026-06-16 12:08:44', NULL),
(6, 'Storage', 'storage', 'Storage devices and solutions', 'fa-database', NULL, 6, 'active', '2026-06-16 12:08:44', NULL),
(7, 'Accessories', 'accessories', 'Computer accessories and peripherals', 'fa-keyboard', NULL, 7, 'active', '2026-06-16 12:08:44', NULL),
(8, 'Printers', 'printers', 'Printers, scanners, and copiers', 'fa-print', NULL, 8, 'active', '2026-06-16 12:08:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `replied` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `company_reg_no` varchar(100) DEFAULT NULL,
  `company_type` enum('sole_proprietorship','partnership','limited_company','corporation','government','non_profit') DEFAULT 'limited_company',
  `industry` varchar(100) DEFAULT NULL,
  `company_size` enum('micro','small','medium','large','enterprise') DEFAULT 'small',
  `year_established` year(4) DEFAULT NULL,
  `contact_person` varchar(100) NOT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Tanzania',
  `tax_id` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL COMMENT 'Tanzania Tax Identification Number',
  `vat_registered` tinyint(1) DEFAULT 0,
  `vat_number` varchar(50) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `status` enum('active','suspended','blocked') DEFAULT 'active',
  `account_type` enum('individual','business','government') DEFAULT 'business',
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `currency` varchar(10) DEFAULT 'TZS',
  `language` varchar(10) DEFAULT 'en',
  `preferred_payment_method` varchar(50) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_account_name` varchar(150) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `linkedin_url` varchar(200) DEFAULT NULL,
  `twitter_handle` varchar(100) DEFAULT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(15,2) DEFAULT 0.00,
  `average_order_value` decimal(15,2) DEFAULT 0.00,
  `last_order_date` datetime DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified` tinyint(1) DEFAULT 0,
  `phone_verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_sales_summary`
--

CREATE TABLE `daily_sales_summary` (
  `id` int(11) NOT NULL,
  `summary_date` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_discounts` decimal(15,2) DEFAULT 0.00,
  `total_savings` decimal(15,2) DEFAULT 0.00,
  `unique_customers` int(11) DEFAULT 0,
  `unique_suppliers` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `dispute_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `type` enum('item_not_delivered','damaged_item','wrong_item','quality_issue','payment_issue','other') NOT NULL,
  `description` text NOT NULL,
  `amount_claimed` decimal(15,2) DEFAULT NULL,
  `evidence_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_files`)),
  `status` enum('open','investigating','resolved','closed','refunded') DEFAULT 'open',
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispute_messages`
--

CREATE TABLE `dispute_messages` (
  `id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `priority` int(11) DEFAULT 0,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow_payments`
--

CREATE TABLE `escrow_payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','released','refunded','disputed') DEFAULT 'pending',
  `release_date` datetime DEFAULT NULL,
  `refund_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `escrow_payments`
--

INSERT INTO `escrow_payments` (`id`, `order_id`, `amount`, `status`, `release_date`, `refund_date`, `created_at`, `updated_at`) VALUES
(1, 1, 24534560.00, 'released', '2026-06-22 23:16:14', '2026-06-22 22:47:05', '2026-06-18 09:19:52', '2026-06-22 23:16:14'),
(3, 3, 5093470.00, 'released', '2026-06-24 17:04:52', NULL, '2026-06-24 16:25:30', '2026-06-24 17:04:52'),
(4, 4, 9444720.00, 'released', '2026-06-24 17:05:53', NULL, '2026-06-24 16:31:30', '2026-06-24 17:05:53'),
(5, 5, 3719950.00, 'released', '2026-06-24 17:06:11', NULL, '2026-06-24 16:40:26', '2026-06-24 17:06:11'),
(6, 6, 8126660.00, 'released', '2026-06-26 18:51:13', NULL, '2026-06-25 11:02:19', '2026-06-26 18:51:13'),
(7, 7, 8901920.00, 'released', '2026-06-26 18:51:07', NULL, '2026-06-25 11:10:03', '2026-06-26 18:51:07'),
(8, 8, 767000.00, 'released', '2026-06-26 18:51:01', NULL, '2026-06-25 11:39:35', '2026-06-26 18:51:01'),
(9, 9, 2301000.00, 'released', '2026-06-26 18:31:05', NULL, '2026-06-26 18:26:10', '2026-06-26 18:31:05'),
(10, 10, 2301000.00, 'released', '2026-06-27 13:24:32', NULL, '2026-06-27 13:05:04', '2026-06-27 13:24:32'),
(11, 11, 10421760.00, 'released', '2026-06-29 08:32:25', NULL, '2026-06-28 19:53:33', '2026-06-29 08:32:25'),
(12, 12, 12430120.00, 'pending', '2026-07-01 18:07:08', NULL, '2026-07-01 11:11:35', '2026-07-01 21:20:24'),
(13, 13, 4235020.00, 'released', '2026-07-01 19:03:04', '2026-07-01 18:57:16', '2026-07-01 18:51:15', '2026-07-01 19:03:04'),
(14, 14, 2861500.00, 'pending', NULL, NULL, '2026-07-01 20:49:53', NULL),
(15, 15, 8241120.00, 'pending', '2026-07-01 21:05:19', NULL, '2026-07-01 20:53:10', '2026-07-01 21:05:57'),
(16, 16, 4248000.00, 'pending', NULL, NULL, '2026-07-01 21:14:06', NULL),
(17, 17, 1416000.00, 'pending', NULL, NULL, '2026-07-01 21:49:17', NULL),
(18, 18, 1416000.00, 'pending', NULL, NULL, '2026-07-01 21:55:27', NULL),
(19, 19, 2832000.00, 'pending', NULL, NULL, '2026-07-01 22:16:13', NULL),
(20, 20, 5664000.00, 'pending', NULL, NULL, '2026-07-01 22:21:05', NULL),
(21, 21, 2773000.00, 'pending', NULL, NULL, '2026-07-02 08:56:30', NULL),
(22, 22, 1180000.00, 'pending', NULL, NULL, '2026-07-02 10:00:03', NULL),
(23, 23, 767000.00, 'pending', NULL, NULL, '2026-07-02 10:12:14', NULL),
(24, 24, 767000.00, 'pending', NULL, NULL, '2026-07-02 11:40:29', NULL),
(25, 25, 1534000.00, 'pending', NULL, NULL, '2026-07-02 11:46:44', NULL),
(26, 26, 24208880.00, 'pending', NULL, NULL, '2026-07-02 13:07:24', NULL),
(27, 27, 2773000.00, 'pending', NULL, NULL, '2026-07-02 19:41:07', NULL),
(28, 28, 2773000.00, 'pending', NULL, NULL, '2026-07-03 21:43:16', NULL),
(29, 29, 767000.00, 'pending', NULL, NULL, '2026-07-03 21:58:05', NULL),
(30, 30, 3903086.00, 'pending', NULL, NULL, '2026-07-04 09:48:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `escrow_transactions`
--

CREATE TABLE `escrow_transactions` (
  `id` int(11) NOT NULL,
  `escrow_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `category`, `question`, `answer`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'General', 'What is TechProcure?', 'TechProcure is a B2B platform connecting IT equipment buyers with verified suppliers in Tanzania.', 1, 'active', '2026-06-27 05:28:28', '2026-06-27 05:28:28'),
(2, 'Buying', 'How do I place an order?', 'Register as a buyer, browse products, add to cart, and proceed to checkout.', 2, 'active', '2026-06-27 05:28:28', '2026-06-27 05:28:28'),
(3, 'Selling', 'How do I become a supplier?', 'Register as a supplier, submit your business documents, and await admin approval.', 3, 'active', '2026-06-27 05:28:28', '2026-06-27 05:28:28'),
(4, 'Payment', 'What payment methods are accepted?', 'We accept M-Pesa, Airtel Money, Tigo Pesa, Halopesa, Azam Pesa, Bank Transfer, Visa, and Mastercard.', 4, 'active', '2026-06-27 05:28:28', '2026-06-27 05:28:28'),
(5, 'Delivery', 'What is the delivery time?', 'Delivery times vary by supplier and location, typically 2-7 business days within Tanzania.', 5, 'active', '2026-06-27 05:28:28', '2026-06-27 05:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `global_discount_tiers`
--

CREATE TABLE `global_discount_tiers` (
  `id` int(11) NOT NULL,
  `min_quantity` int(11) NOT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `global_discount_tiers`
--

INSERT INTO `global_discount_tiers` (`id`, `min_quantity`, `max_quantity`, `discount_percent`, `is_active`, `created_at`) VALUES
(1, 5, 9, 3.00, 1, '2026-06-16 12:08:50'),
(2, 10, 49, 8.00, 1, '2026-06-16 12:08:50'),
(3, 50, 199, 15.00, 1, '2026-06-16 12:08:50'),
(4, 200, NULL, 25.00, 1, '2026-06-16 12:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reserved_quantity` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 0,
  `max_stock` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `quantity`, `reserved_quantity`, `min_stock`, `max_stock`, `last_updated`, `created_at`) VALUES
(1, 1, 0, 0, 0, NULL, '2026-07-03 13:49:31', '2026-07-03 13:49:31'),
(2, 2, 0, 0, 0, NULL, '2026-07-03 13:49:31', '2026-07-03 13:49:31'),
(3, 3, 0, 0, 0, NULL, '2026-07-03 13:49:31', '2026-07-03 13:49:31'),
(4, 16, 0, 0, 0, NULL, '2026-07-03 20:25:47', '2026-07-03 20:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `previous_quantity` int(11) DEFAULT 0,
  `new_quantity` int(11) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `pdf_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `order_id`, `user_id`, `supplier_id`, `invoice_date`, `due_date`, `subtotal`, `tax_amount`, `tax_rate`, `total_amount`, `paid_amount`, `status`, `pdf_path`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-20260618-0135', 1, 9, 2, '2026-06-18', '2026-07-18', 22600000.00, 3742560.00, 0.00, 24534560.00, 0.00, 'draft', NULL, NULL, 9, '2026-06-18 09:19:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  `was_successful` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempt_time`, `was_successful`) VALUES
(1, 'zeno1@gmail.com', '::1', '2026-06-17 09:23:13', 0),
(2, 'zeno1@gmail.com', '::1', '2026-06-17 09:23:21', 0),
(3, 'zeno2', '::1', '2026-06-17 10:14:43', 0),
(4, 'heroku001@gmail.com', '::1', '2026-06-17 10:46:45', 0),
(5, 'heroku001@gmail.com', '::1', '2026-06-17 10:51:08', 0),
(6, 'zeno2', '::1', '2026-06-17 10:53:21', 0),
(7, 'zeno2', '::1', '2026-06-17 11:01:09', 0),
(8, 'zeno2', '::1', '2026-06-17 11:01:37', 0),
(9, 'heroku1', '::1', '2026-06-17 11:02:03', 0),
(10, 'heroku', '::1', '2026-06-17 11:47:44', 0),
(11, 'heroku', '::1', '2026-06-17 11:48:21', 0),
(12, 'admin@techprocure.co.tz', '::1', '2026-06-17 11:53:18', 0),
(13, 'admin@techprocure.co.tz', '::1', '2026-06-17 11:53:46', 0),
(14, 'admin@techprocure.co.tz', '::1', '2026-06-17 11:55:52', 0),
(15, 'admin@techprocure.co.tz', '::1', '2026-06-17 11:56:24', 0),
(16, 'admin', '::1', '2026-06-17 11:57:30', 0),
(17, 'admin@techprocure.co.tz', '::1', '2026-06-17 12:02:23', 0),
(18, 'admin@techprocure.co.tz', '::1', '2026-06-17 12:12:32', 0),
(19, 'heroku01@gmail.com', '::1', '2026-06-17 19:53:28', 0),
(20, 'customer@techprocure.com', '::1', '2026-06-17 22:15:25', 0),
(21, 'heroku12@gmail.com', '::1', '2026-06-17 22:16:53', 0),
(22, 'heroku12@gmail.com', '::1', '2026-06-17 22:17:16', 0);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_type` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `login_status` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_type`, `user_id`, `email`, `login_status`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'admin', NULL, 'heroku01@gmail.com', 'failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 06:08:58'),
(2, 'admin', NULL, 'admin@techprocure.com', 'failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 06:10:07'),
(3, 'admin', NULL, 'admin@techprocure.com', 'failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 06:11:45'),
(4, 'admin', NULL, 'admin', 'failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 06:12:14'),
(5, 'admin', NULL, 'admin', 'failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 06:12:47'),
(6, 'admin', NULL, 'admin@techprocure.com', 'failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 06:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_sales_summary`
--

CREATE TABLE `monthly_sales_summary` (
  `id` int(11) NOT NULL,
  `summary_month` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_discounts` decimal(15,2) DEFAULT 0.00,
  `total_savings` decimal(15,2) DEFAULT 0.00,
  `unique_customers` int(11) DEFAULT 0,
  `unique_suppliers` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `read_at`, `created_at`) VALUES
(1, 9, 'order', 'Order Placed', 'Your order ORD-20260618-1096 has been placed successfully.', 'customer/orders/order-details.php?id=1', 0, NULL, '2026-06-18 09:19:53'),
(2, 9, 'payment', 'Payment Successful', 'Your payment of TSh 24,534,560.00 for order ORD-20260618-1096 has been processed successfully.', '../customer/orders/order-details.php?id=1', 0, NULL, '2026-06-22 22:27:26'),
(3, 9, 'order', 'Order Status Updated', 'Your order ORD-20260618-1096 has been updated to Completed', '../customer/orders/order-details.php?id=1', 0, NULL, '2026-06-22 23:16:14'),
(4, 9, 'order', 'Order Placed', 'Your order ORD-20260624-9554 has been placed successfully.', 'customer/orders/order-details.php?id=3', 0, NULL, '2026-06-24 16:25:30'),
(5, 9, 'order', 'Order Placed', 'Your order ORD-20260624-8205 has been placed successfully.', 'customer/orders/order-details.php?id=4', 0, NULL, '2026-06-24 16:31:30'),
(6, 9, 'order', 'Order Placed', 'Your order ORD-20260624-3756 has been placed successfully.', 'customer/orders/order-details.php?id=5', 0, NULL, '2026-06-24 16:40:26'),
(7, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-9554 has been updated to: Completed', '../customer/orders/order-details.php?id=3', 0, NULL, '2026-06-24 23:52:13'),
(8, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-9554 has been updated to: Completed', '../customer/orders/order-details.php?id=3', 0, NULL, '2026-06-24 23:52:28'),
(9, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-9554 has been updated to: Delivered', '../customer/orders/order-details.php?id=3', 0, NULL, '2026-06-24 23:53:01'),
(10, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-8205 has been updated to: Shipped', '../customer/orders/order-details.php?id=4', 0, NULL, '2026-06-24 23:53:14'),
(11, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-9554 has been updated to: Cancelled', '../customer/orders/order-details.php?id=3', 0, NULL, '2026-06-24 23:54:24'),
(12, 5, 'order', 'Order Placed', 'Your order ORD-20260625-6272 has been placed successfully.', 'customer/orders/order-details.php?id=6', 0, NULL, '2026-06-25 11:02:19'),
(13, 5, 'order', 'Order Placed', 'Your order ORD-20260625-7868 has been placed successfully.', 'customer/orders/order-details.php?id=7', 0, NULL, '2026-06-25 11:10:03'),
(14, 9, 'order', 'Order Placed', 'Your order ORD-20260625-8024 has been placed successfully.', 'customer/orders/order-details.php?id=8', 0, NULL, '2026-06-25 11:39:35'),
(15, 9, 'order', 'Order Placed', 'Your order ORD-20260626-3109 has been placed successfully.', 'customer/orders/order-details.php?id=9', 0, NULL, '2026-06-26 18:26:10'),
(16, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260626-3109 has been updated to: Completed', '../customer/orders/order-details.php?id=9', 0, NULL, '2026-06-26 18:29:41'),
(17, 9, 'order', 'Order Status Updated', 'Your order ORD-20260626-3109 has been updated to Completed', '../customer/orders/order-details.php?id=9', 0, NULL, '2026-06-26 18:31:05'),
(18, 9, 'escrow', 'Escrow Released', 'Your escrow payment of TSh 767,000.00 has been released to the supplier.', '../customer/orders/order-details.php?id=8', 0, NULL, '2026-06-26 18:51:02'),
(19, 5, 'escrow', 'Escrow Released', 'Your escrow payment of TSh 8,901,920.00 has been released to the supplier.', '../customer/orders/order-details.php?id=7', 0, NULL, '2026-06-26 18:51:07'),
(20, 5, 'escrow', 'Escrow Released', 'Your escrow payment of TSh 8,126,660.00 has been released to the supplier.', '../customer/orders/order-details.php?id=6', 0, NULL, '2026-06-26 18:51:13'),
(21, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260626-3109 has been updated to: Shipped', '../customer/orders/order-details.php?id=9', 0, NULL, '2026-06-27 09:22:33'),
(22, 9, 'order', 'Order Placed', 'Your order ORD-20260627-4517 has been placed successfully.', 'customer/orders/order-details.php?id=10', 0, NULL, '2026-06-27 13:05:06'),
(23, 5, 'order', 'Order Status Updated', 'Your order #ORD-20260625-6272 has been updated to: Completed', '../customer/orders/order-details.php?id=6', 0, NULL, '2026-06-27 13:21:56'),
(24, 5, 'order', 'Order Status Updated', 'Your order #ORD-20260625-7868 has been updated to: Completed', '../customer/orders/order-details.php?id=7', 0, NULL, '2026-06-27 13:22:18'),
(25, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260625-8024 has been updated to: Completed', '../customer/orders/order-details.php?id=8', 0, NULL, '2026-06-27 13:22:34'),
(26, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260627-4517 has been updated to: Completed', '../customer/orders/order-details.php?id=10', 0, NULL, '2026-06-27 13:22:43'),
(27, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-8205 has been updated to: Completed', '../customer/orders/order-details.php?id=4', 0, NULL, '2026-06-27 13:22:58'),
(28, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-9554 has been updated to: Completed', '../customer/orders/order-details.php?id=3', 0, NULL, '2026-06-27 13:23:13'),
(29, 9, 'escrow', 'Escrow Released', 'Your escrow payment of TSh 2,301,000.00 has been released to the supplier.', '../customer/orders/order-details.php?id=10', 0, NULL, '2026-06-27 13:24:32'),
(30, 9, 'order', 'Order Placed', 'Your order ORD-20260628-5085 has been placed successfully.', 'customer/orders/order-details.php?id=11', 0, NULL, '2026-06-28 19:53:33'),
(31, 9, 'escrow', 'Escrow Released', 'Your escrow payment of TSh 10,421,760.00 has been released to the supplier.', '../customer/orders/order-details.php?id=11', 0, NULL, '2026-06-28 20:03:42'),
(32, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260626-3109 has been updated to: Shipped', '../customer/orders/order-details.php?id=9', 0, NULL, '2026-06-28 20:22:36'),
(33, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260626-3109 has been updated to: Completed', '../customer/orders/order-details.php?id=9', 0, NULL, '2026-06-28 20:22:46'),
(34, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260626-3109 has been updated to: Completed', '../customer/orders/order-details.php?id=9', 0, NULL, '2026-06-28 20:27:41'),
(35, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-3756 has been updated to: Completed', '../customer/orders/order-details.php?id=5', 0, NULL, '2026-06-28 20:27:53'),
(36, 9, 'order', 'Order Status Updated', 'Your order #ORD-20260624-3756 has been updated to: Completed', '../customer/orders/order-details.php?id=5', 0, NULL, '2026-06-28 20:28:02'),
(37, 9, 'order', 'Order Status Updated', 'Your order ORD-20260628-5085 has been updated to Completed', '../customer/orders/order-details.php?id=11', 0, NULL, '2026-06-29 08:32:25'),
(38, 9, 'order', 'Order Placed', 'Your order ORD-20260701-8988 has been placed successfully.', 'customer/orders/order-details.php?id=12', 0, NULL, '2026-07-01 11:11:36'),
(39, 9, 'order', 'Order Status Updated', 'Your order ORD-20260701-8988 has been updated to Completed', '../customer/orders/order-details.php?id=12', 0, NULL, '2026-07-01 18:07:08'),
(40, 9, 'order', 'Order Placed', 'Your order ORD-20260701-8443 has been placed successfully.', 'customer/orders/order-details.php?id=13', 0, NULL, '2026-07-01 18:51:15'),
(41, 9, 'payment', 'Payment Successful', 'Your payment of TSh 4,235,020.00 for order ORD-20260701-8443 has been processed successfully.', '../customer/orders/order-details.php?id=13', 0, NULL, '2026-07-01 18:55:54'),
(42, 9, 'order', 'Order Status Updated', 'Your order ORD-20260701-8443 has been updated to Completed', '../customer/orders/order-details.php?id=13', 0, NULL, '2026-07-01 19:03:04'),
(43, 5, 'order', 'Order Placed', 'Your order ORD-20260701-2382 has been placed successfully.', 'customer/orders/order-details.php?id=14', 0, NULL, '2026-07-01 20:49:54'),
(44, 5, 'order', 'Order Placed', 'Your order ORD-20260701-6473 has been placed successfully.', 'customer/orders/order-details.php?id=15', 0, NULL, '2026-07-01 20:53:10'),
(45, 5, 'order', 'Order Status Updated', 'Your order ORD-20260701-6473 has been updated to Completed', '../customer/orders/order-details.php?id=15', 0, NULL, '2026-07-01 21:05:19'),
(46, 5, 'payment', 'Payment Successful', 'Your payment of TSh 8,241,120.00 for order ORD-20260701-6473 has been processed successfully.', '../customer/orders/order-details.php?id=15', 0, NULL, '2026-07-01 21:05:57'),
(47, 9, 'order', 'Order Placed', 'Your order ORD-20260701-0143 has been placed successfully.', 'customer/orders/order-details.php?id=16', 0, NULL, '2026-07-01 21:14:06'),
(48, 9, 'payment', 'Payment Successful', 'Your payment of TSh 4,248,000.00 for order ORD-20260701-0143 has been processed successfully.', '../customer/orders/order-details.php?id=16', 0, NULL, '2026-07-01 21:16:52'),
(49, 9, 'payment', 'Payment Successful', 'Your payment of TSh 12,430,120.00 for order ORD-20260701-8988 has been processed successfully.', '../customer/orders/order-details.php?id=12', 0, NULL, '2026-07-01 21:20:25'),
(50, 9, 'order', 'Order Placed', 'Your order ORD-20260701-7904 has been placed successfully.', 'customer/orders/order-details.php?id=17', 0, NULL, '2026-07-01 21:49:17'),
(51, 9, 'payment', 'Payment Successful', 'Your payment of TSh 1,416,000.00 for order ORD-20260701-7904 has been processed successfully.', '../customer/orders/order-details.php?id=17', 0, NULL, '2026-07-01 21:50:43'),
(52, 9, 'order', 'Order Placed', 'Your order ORD-20260701-9339 has been placed successfully.', 'customer/orders/order-details.php?id=18', 0, NULL, '2026-07-01 21:55:27'),
(53, 9, 'payment', 'Payment Successful', 'Your payment of TSh 1,416,000.00 for order ORD-20260701-9339 has been processed successfully.', '../customer/orders/order-details.php?id=18', 0, NULL, '2026-07-01 21:55:55'),
(54, 9, 'order', 'Order Placed', 'Your order ORD-20260701-0365 has been placed successfully.', 'customer/orders/order-details.php?id=19', 0, NULL, '2026-07-01 22:16:13'),
(55, 9, 'payment', 'Payment Successful', 'Your payment of TSh 2,832,000.00 for order ORD-20260701-0365 has been processed successfully.', '../customer/orders/order-details.php?id=19', 0, NULL, '2026-07-01 22:16:37'),
(56, 9, 'order', 'Order Placed', 'Your order ORD-20260701-0854 has been placed successfully.', 'customer/orders/order-details.php?id=20', 0, NULL, '2026-07-01 22:21:05'),
(57, 9, 'payment', 'Payment Successful', 'Your payment of TSh 5,664,000.00 for order ORD-20260701-0854 has been processed successfully.', '../customer/orders/order-details.php?id=20', 0, NULL, '2026-07-01 22:27:42'),
(58, 9, 'order', 'Order Placed', 'Your order ORD-20260702-4717 has been placed successfully.', 'customer/orders/order-details.php?id=21', 0, NULL, '2026-07-02 08:56:33'),
(59, 9, 'payment', 'Payment Successful', 'Your payment of TSh 2,773,000.00 for order ORD-20260702-4717 has been processed successfully.', '../customer/orders/order-details.php?id=21', 0, NULL, '2026-07-02 09:42:59'),
(60, 9, 'order', 'Order Placed', 'Your order ORD-20260702-9282 has been placed successfully.', 'customer/orders/order-details.php?id=22', 0, NULL, '2026-07-02 10:00:03'),
(61, 9, 'payment', 'Payment Successful', 'Your payment of TSh 1,180,000.00 for order ORD-20260702-9282 has been processed successfully.', '../customer/orders/order-details.php?id=22', 0, NULL, '2026-07-02 10:01:27'),
(62, 9, 'order', 'Order Placed', 'Your order ORD-20260702-0833 has been placed successfully.', 'customer/orders/order-details.php?id=23', 0, NULL, '2026-07-02 10:12:14'),
(63, 9, 'payment', 'Payment Successful', 'Your payment of TSh 767,000.00 for order ORD-20260702-0833 has been processed successfully.', '../customer/orders/order-details.php?id=23', 0, NULL, '2026-07-02 10:15:58'),
(64, 9, 'order', 'Order Placed', 'Your order ORD-20260702-0330 has been placed successfully.', 'customer/orders/order-details.php?id=24', 0, NULL, '2026-07-02 11:40:29'),
(65, 9, 'payment', 'Payment Successful', 'Your payment of TSh 767,000.00 for order ORD-20260702-0330 has been processed successfully.', '../customer/orders/order-details.php?id=24', 0, NULL, '2026-07-02 11:44:50'),
(66, 9, 'payment', 'Payment Successful', 'Your payment of TSh 767,000.00 for order ORD-20260702-0330 has been processed successfully.', '../customer/orders/order-details.php?id=24', 0, NULL, '2026-07-02 11:45:42'),
(67, 9, 'order', 'Order Placed', 'Your order ORD-20260702-1387 has been placed successfully.', 'customer/orders/order-details.php?id=25', 0, NULL, '2026-07-02 11:46:44'),
(68, 9, 'payment', 'Payment Successful', 'Your payment of TSh 1,534,000.00 for order ORD-20260702-1387 has been processed successfully.', '../customer/orders/order-details.php?id=25', 0, NULL, '2026-07-02 11:47:20'),
(69, 9, 'order', 'Order Placed', 'Your order ORD-20260702-1293 has been placed successfully.', 'customer/orders/order-details.php?id=26', 0, NULL, '2026-07-02 13:07:24'),
(70, 9, 'payment', 'Payment Successful', 'Your payment of TSh 24,208,880.00 for order ORD-20260702-1293 has been processed successfully.', '../customer/orders/order-details.php?id=26', 0, NULL, '2026-07-02 13:12:47'),
(71, 9, 'order', 'Order Placed', 'Your order ORD-20260702-2322 has been placed successfully.', 'customer/orders/order-details.php?id=27', 0, NULL, '2026-07-02 19:41:08'),
(72, 9, 'payment', 'Payment Successful', 'Your payment of TSh 2,773,000.00 for order ORD-20260702-2322 has been processed successfully.', '../customer/orders/order-details.php?id=27', 0, NULL, '2026-07-02 19:41:46'),
(73, 9, 'order', 'Order Placed', 'Your order ORD-20260703-9644 has been placed successfully.', 'customer/orders/order-details.php?id=28', 0, NULL, '2026-07-03 21:43:16'),
(74, 9, 'payment', 'Payment Successful', 'Your payment of TSh 2,773,000.00 for order ORD-20260703-9644 has been processed successfully.', '../customer/orders/order-details.php?id=28', 0, NULL, '2026-07-03 21:44:44'),
(75, 9, 'order', 'Order Placed', 'Your order ORD-20260703-6063 has been placed successfully.', 'customer/orders/order-details.php?id=29', 0, NULL, '2026-07-03 21:58:05'),
(76, 9, 'payment', 'Payment Successful', 'Your payment of TSh 767,000.00 for order ORD-20260703-6063 has been processed successfully.', '../customer/orders/order-details.php?id=29', 0, NULL, '2026-07-03 22:40:32'),
(77, 9, 'order', 'Order Placed', 'Your order ORD-20260704-0271 has been placed successfully.', 'customer/orders/order-details.php?id=30', 0, NULL, '2026-07-04 09:48:29');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quotation_id` int(11) DEFAULT NULL,
  `quotation_response_id` int(11) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','shipped','delivered','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed','refunded','partial') DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `total_amount_tsh` decimal(15,2) DEFAULT 0.00,
  `total_savings` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'TSh',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_number`, `buyer_id`, `user_id`, `supplier_id`, `quotation_id`, `quotation_response_id`, `po_number`, `order_status`, `payment_status`, `subtotal`, `discount_amount`, `tax_amount`, `shipping_cost`, `total_amount`, `total_amount_tsh`, `total_savings`, `currency`, `payment_method`, `transaction_id`, `shipping_address`, `billing_address`, `tracking_number`, `estimated_delivery`, `delivered_at`, `cancelled_at`, `cancellation_reason`, `notes`, `internal_notes`, `created_at`, `updated_at`) VALUES
(1, NULL, 'ORD-20260618-1096', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'refunded', 22600000.00, 1808000.00, 3742560.00, 0.00, 24534560.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260622-2342', 'kk', 'kk', '001', NULL, NULL, NULL, NULL, '', '', '2026-06-18 09:19:52', '2026-06-22 23:16:14'),
(3, NULL, 'ORD-20260624-9554', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'pending', 4450000.00, 133500.00, 776970.00, 0.00, 5093470.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'kk', '123DER4', NULL, NULL, NULL, NULL, '', '', '2026-06-24 16:25:30', '2026-06-27 13:23:13'),
(4, NULL, 'ORD-20260624-8205', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'pending', 8700000.00, 696000.00, 1440720.00, 0.00, 9444720.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'kk', '123DER4ZE', NULL, NULL, NULL, NULL, '', '', '2026-06-24 16:31:30', '2026-06-27 13:22:58'),
(5, NULL, 'ORD-20260624-3756', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'pending', 3250000.00, 97500.00, 567450.00, 0.00, 3719950.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'kk', '123DER4ZE1212', NULL, NULL, NULL, NULL, '', '', '2026-06-24 16:40:26', '2026-06-28 20:28:02'),
(6, NULL, 'ORD-20260625-6272', NULL, 5, NULL, NULL, NULL, NULL, 'completed', 'paid', 7100000.00, 213000.00, 1239660.00, 0.00, 8126660.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'KK', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-06-25 11:02:18', '2026-06-27 13:21:56'),
(7, NULL, 'ORD-20260625-7868', NULL, 5, NULL, NULL, NULL, NULL, 'completed', 'paid', 8200000.00, 656000.00, 1357920.00, 0.00, 8901920.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'YOTE', 'YOTE', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-06-25 11:10:02', '2026-06-27 13:22:18'),
(8, NULL, 'ORD-20260625-8024', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'paid', 650000.00, 0.00, 117000.00, 0.00, 767000.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-06-25 11:39:34', '2026-06-27 13:22:34'),
(9, NULL, 'ORD-20260626-3109', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'pending', 1950000.00, 0.00, 351000.00, 0.00, 2301000.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'kk', '44er', NULL, NULL, NULL, NULL, '', '', '2026-06-26 18:26:10', '2026-06-28 20:27:41'),
(10, NULL, 'ORD-20260627-4517', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'paid', 1950000.00, 0.00, 351000.00, 0.00, 2301000.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'll', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-06-27 13:04:59', '2026-06-27 13:24:32'),
(11, NULL, 'ORD-20260628-5085', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'paid', 9600000.00, 768000.00, 1589760.00, 0.00, 10421760.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'kk', 'kk', '123KL', NULL, NULL, NULL, NULL, '', '', '2026-06-28 19:53:32', '2026-06-29 08:32:25'),
(12, NULL, 'ORD-20260701-8988', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'paid', 11450000.00, 916000.00, 1896120.00, 0.00, 12430120.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-5353', 'kk', 'kk', '123DER42', NULL, NULL, NULL, NULL, '', '', '2026-07-01 11:11:34', '2026-07-01 21:20:24'),
(13, NULL, 'ORD-20260701-8443', NULL, 9, NULL, NULL, NULL, NULL, 'completed', 'paid', 3700000.00, 111000.00, 646020.00, 0.00, 4235020.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-8670', 'kk', 'kk', '', NULL, NULL, '2026-07-01 18:57:16', NULL, '', '', '2026-07-01 18:51:14', '2026-07-01 19:03:04'),
(14, NULL, 'ORD-20260701-2382', NULL, 5, NULL, NULL, NULL, NULL, 'pending', 'pending', 2500000.00, 75000.00, 436500.00, 0.00, 2861500.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'morogoro', 'morogoro', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-01 20:49:53', NULL),
(15, NULL, 'ORD-20260701-6473', NULL, 5, NULL, NULL, NULL, NULL, 'completed', 'paid', 7200000.00, 216000.00, 1257120.00, 0.00, 8241120.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-7170', 'kk', 'kk', '123DER4ZE', NULL, NULL, NULL, NULL, '', '', '2026-07-01 20:53:10', '2026-07-01 21:05:56'),
(16, NULL, 'ORD-20260701-0143', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 3600000.00, 0.00, 648000.00, 0.00, 4248000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-4701', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-01 21:14:06', '2026-07-01 21:16:52'),
(17, NULL, 'ORD-20260701-7904', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 1200000.00, 0.00, 216000.00, 0.00, 1416000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-1651', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-01 21:49:17', '2026-07-01 21:50:43'),
(18, NULL, 'ORD-20260701-9339', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 1200000.00, 0.00, 216000.00, 0.00, 1416000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-9977', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-01 21:55:27', '2026-07-01 21:55:55'),
(19, NULL, 'ORD-20260701-0365', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 2400000.00, 0.00, 432000.00, 0.00, 2832000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-4870', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-01 22:16:13', '2026-07-01 22:16:37'),
(20, NULL, 'ORD-20260701-0854', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 4800000.00, 0.00, 864000.00, 0.00, 5664000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260701-0122', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-01 22:21:05', '2026-07-01 22:27:41'),
(21, NULL, 'ORD-20260702-4717', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 2350000.00, 0.00, 423000.00, 0.00, 2773000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260702-8839', 'dar', 'dar', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 08:56:22', '2026-07-02 09:42:59'),
(22, NULL, 'ORD-20260702-9282', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 1000000.00, 0.00, 180000.00, 0.00, 1180000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260702-3987', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 10:00:03', '2026-07-02 10:01:27'),
(23, NULL, 'ORD-20260702-0833', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 650000.00, 0.00, 117000.00, 0.00, 767000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260702-7792', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 10:12:14', '2026-07-02 10:15:58'),
(24, NULL, 'ORD-20260702-0330', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 650000.00, 0.00, 117000.00, 0.00, 767000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260702-5477', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 11:40:29', '2026-07-02 11:45:42'),
(25, NULL, 'ORD-20260702-1387', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 1300000.00, 0.00, 234000.00, 0.00, 1534000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260702-4905', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 11:46:44', '2026-07-02 11:47:19'),
(26, NULL, 'ORD-20260702-1293', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 22300000.00, 1784000.00, 3692880.00, 0.00, 24208880.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260702-5825', 'Mwanza', 'Mwanza', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 13:07:24', '2026-07-02 13:12:47'),
(27, NULL, 'ORD-20260702-2322', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 2350000.00, 0.00, 423000.00, 0.00, 2773000.00, 0.00, 0.00, 'TSh', 'airtel_money', 'TXN-20260702-0638', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-02 19:41:07', '2026-07-02 19:41:46'),
(28, NULL, 'ORD-20260703-9644', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 2350000.00, 0.00, 423000.00, 0.00, 2773000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260703-4854', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-03 21:43:16', '2026-07-03 21:44:43'),
(29, NULL, 'ORD-20260703-6063', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'paid', 650000.00, 0.00, 117000.00, 0.00, 767000.00, 0.00, 0.00, 'TSh', 'mpesa', 'TXN-20260703-3607', 'kk', 'kk', NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-07-03 21:58:05', '2026-07-03 22:40:32'),
(30, NULL, 'ORD-20260704-0271', NULL, 9, NULL, NULL, NULL, NULL, 'pending', 'pending', 3410000.00, 102300.00, 595386.00, 0.00, 3903086.00, 0.00, 0.00, 'TSh', 'mpesa', NULL, 'dar es salaam', 'bank', NULL, NULL, NULL, NULL, NULL, 'very glass', NULL, '2026-07-04 09:48:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `specifications_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications_snapshot`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `price`, `variant_id`, `quantity`, `unit_price`, `discount_amount`, `total_price`, `product_name_snapshot`, `specifications_snapshot`, `created_at`) VALUES
(1, 1, 1, 0.00, NULL, 18, 1200000.00, 0.00, 21600000.00, NULL, NULL, '2026-06-18 09:19:52'),
(2, 1, 2, 0.00, NULL, 2, 500000.00, 0.00, 1000000.00, NULL, NULL, '2026-06-18 09:19:52'),
(5, 3, 2, 0.00, NULL, 5, 500000.00, 0.00, 2500000.00, NULL, NULL, '2026-06-24 16:25:30'),
(6, 3, 3, 0.00, NULL, 3, 650000.00, 0.00, 1950000.00, NULL, NULL, '2026-06-24 16:25:30'),
(7, 4, 2, 0.00, NULL, 7, 500000.00, 0.00, 3500000.00, NULL, NULL, '2026-06-24 16:31:30'),
(8, 4, 3, 0.00, NULL, 8, 650000.00, 0.00, 5200000.00, NULL, NULL, '2026-06-24 16:31:30'),
(9, 5, 3, 0.00, NULL, 5, 650000.00, 0.00, 3250000.00, NULL, NULL, '2026-06-24 16:40:26'),
(10, 6, 1, 0.00, NULL, 4, 1200000.00, 0.00, 4800000.00, NULL, NULL, '2026-06-25 11:02:18'),
(11, 6, 2, 0.00, NULL, 2, 500000.00, 0.00, 1000000.00, NULL, NULL, '2026-06-25 11:02:19'),
(12, 6, 3, 0.00, NULL, 2, 650000.00, 0.00, 1300000.00, NULL, NULL, '2026-06-25 11:02:19'),
(13, 7, 1, 0.00, NULL, 3, 1200000.00, 0.00, 3600000.00, NULL, NULL, '2026-06-25 11:10:03'),
(14, 7, 2, 0.00, NULL, 4, 500000.00, 0.00, 2000000.00, NULL, NULL, '2026-06-25 11:10:03'),
(15, 7, 3, 0.00, NULL, 4, 650000.00, 0.00, 2600000.00, NULL, NULL, '2026-06-25 11:10:03'),
(16, 8, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-06-25 11:39:35'),
(17, 9, 3, 0.00, NULL, 3, 650000.00, 0.00, 1950000.00, NULL, NULL, '2026-06-26 18:26:10'),
(18, 10, 3, 0.00, NULL, 3, 650000.00, 0.00, 1950000.00, NULL, NULL, '2026-06-27 13:05:00'),
(19, 11, 1, 0.00, NULL, 2, 1200000.00, 0.00, 2400000.00, NULL, NULL, '2026-06-28 19:53:33'),
(20, 11, 2, 0.00, NULL, 4, 500000.00, 0.00, 2000000.00, NULL, NULL, '2026-06-28 19:53:33'),
(21, 11, 3, 0.00, NULL, 8, 650000.00, 0.00, 5200000.00, NULL, NULL, '2026-06-28 19:53:33'),
(22, 12, 2, 0.00, NULL, 6, 500000.00, 0.00, 3000000.00, NULL, NULL, '2026-07-01 11:11:35'),
(23, 12, 3, 0.00, NULL, 13, 650000.00, 0.00, 8450000.00, NULL, NULL, '2026-07-01 11:11:35'),
(24, 13, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-01 18:51:15'),
(25, 13, 2, 0.00, NULL, 5, 500000.00, 0.00, 2500000.00, NULL, NULL, '2026-07-01 18:51:15'),
(26, 14, 2, 0.00, NULL, 5, 500000.00, 0.00, 2500000.00, NULL, NULL, '2026-07-01 20:49:53'),
(27, 15, 1, 0.00, NULL, 6, 1200000.00, 0.00, 7200000.00, NULL, NULL, '2026-07-01 20:53:10'),
(28, 16, 1, 0.00, NULL, 3, 1200000.00, 0.00, 3600000.00, NULL, NULL, '2026-07-01 21:14:06'),
(29, 17, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-01 21:49:17'),
(30, 18, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-01 21:55:27'),
(31, 19, 1, 0.00, NULL, 2, 1200000.00, 0.00, 2400000.00, NULL, NULL, '2026-07-01 22:16:13'),
(32, 20, 1, 0.00, NULL, 4, 1200000.00, 0.00, 4800000.00, NULL, NULL, '2026-07-01 22:21:05'),
(33, 21, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-02 08:56:23'),
(34, 21, 2, 0.00, NULL, 1, 500000.00, 0.00, 500000.00, NULL, NULL, '2026-07-02 08:56:27'),
(35, 21, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-02 08:56:29'),
(36, 22, 2, 0.00, NULL, 2, 500000.00, 0.00, 1000000.00, NULL, NULL, '2026-07-02 10:00:03'),
(37, 23, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-02 10:12:14'),
(38, 24, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-02 11:40:29'),
(39, 25, 3, 0.00, NULL, 2, 650000.00, 0.00, 1300000.00, NULL, NULL, '2026-07-02 11:46:44'),
(40, 26, 1, 0.00, NULL, 10, 1200000.00, 0.00, 12000000.00, NULL, NULL, '2026-07-02 13:07:24'),
(41, 26, 2, 0.00, NULL, 5, 500000.00, 0.00, 2500000.00, NULL, NULL, '2026-07-02 13:07:24'),
(42, 26, 3, 0.00, NULL, 12, 650000.00, 0.00, 7800000.00, NULL, NULL, '2026-07-02 13:07:24'),
(43, 27, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-02 19:41:07'),
(44, 27, 2, 0.00, NULL, 1, 500000.00, 0.00, 500000.00, NULL, NULL, '2026-07-02 19:41:07'),
(45, 27, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-02 19:41:07'),
(46, 28, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-03 21:43:16'),
(47, 28, 2, 0.00, NULL, 1, 500000.00, 0.00, 500000.00, NULL, NULL, '2026-07-03 21:43:16'),
(48, 28, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-03 21:43:16'),
(49, 29, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-03 21:58:05'),
(50, 30, 1, 0.00, NULL, 1, 1200000.00, 0.00, 1200000.00, NULL, NULL, '2026-07-04 09:48:29'),
(51, 30, 2, 0.00, NULL, 1, 500000.00, 0.00, 500000.00, NULL, NULL, '2026-07-04 09:48:29'),
(52, 30, 3, 0.00, NULL, 1, 650000.00, 0.00, 650000.00, NULL, NULL, '2026-07-04 09:48:29'),
(53, 30, 13, 0.00, NULL, 1, 1000000.00, 0.00, 1000000.00, NULL, NULL, '2026-07-04 09:48:29'),
(54, 30, 16, 0.00, NULL, 1, 60000.00, 0.00, 60000.00, NULL, NULL, '2026-07-04 09:48:29');

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tracking`
--

INSERT INTO `order_tracking` (`id`, `order_id`, `status`, `location`, `description`, `updated_by`, `created_at`) VALUES
(1, 1, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-18 09:19:52'),
(2, 1, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-22 22:42:45'),
(3, 1, 'pending', NULL, 'Order status updated to Pending', NULL, '2026-06-22 22:48:02'),
(4, 1, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-22 22:48:27'),
(5, 1, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-22 23:16:14'),
(6, 4, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-24 16:31:30'),
(7, 5, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-24 16:40:26'),
(8, 3, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-24 17:04:52'),
(9, 4, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-24 17:05:52'),
(10, 5, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-24 17:06:11'),
(11, 3, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-24 23:52:13'),
(12, 3, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-24 23:52:28'),
(13, 3, 'delivered', NULL, 'Order status updated to Delivered', NULL, '2026-06-24 23:53:01'),
(14, 4, 'shipped', NULL, 'Order status updated to Shipped', NULL, '2026-06-24 23:53:14'),
(15, 3, 'cancelled', NULL, 'Order status updated to Cancelled', NULL, '2026-06-24 23:54:24'),
(16, 6, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-25 11:02:19'),
(17, 7, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-25 11:10:03'),
(18, 8, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-25 11:39:35'),
(19, 9, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-26 18:26:10'),
(20, 9, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-26 18:29:41'),
(21, 9, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-26 18:31:05'),
(22, 9, 'shipped', NULL, 'Order status updated to Shipped', NULL, '2026-06-27 09:22:33'),
(23, 10, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-27 13:05:05'),
(24, 6, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-27 13:21:56'),
(25, 7, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-27 13:22:18'),
(26, 8, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-27 13:22:34'),
(27, 10, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-27 13:22:43'),
(28, 4, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-27 13:22:58'),
(29, 3, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-27 13:23:13'),
(30, 11, 'pending', NULL, 'Order placed successfully', NULL, '2026-06-28 19:53:33'),
(31, 9, 'shipped', NULL, 'Order status updated to Shipped', NULL, '2026-06-28 20:22:36'),
(32, 9, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-28 20:22:46'),
(33, 9, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-28 20:27:41'),
(34, 5, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-28 20:27:53'),
(35, 5, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-28 20:28:02'),
(36, 11, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-06-29 08:32:25'),
(37, 12, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 11:11:35'),
(38, 12, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-07-01 18:07:08'),
(39, 13, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 18:51:15'),
(40, 13, 'cancelled', NULL, 'Order cancelled by customer', NULL, '2026-07-01 18:57:16'),
(41, 13, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-07-01 19:03:04'),
(42, 14, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 20:49:53'),
(43, 15, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 20:53:10'),
(44, 15, 'completed', NULL, 'Order status updated to Completed', NULL, '2026-07-01 21:05:19'),
(45, 16, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 21:14:06'),
(46, 17, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 21:49:17'),
(47, 18, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 21:55:27'),
(48, 19, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 22:16:13'),
(49, 20, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-01 22:21:05'),
(50, 21, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 08:56:30'),
(51, 22, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 10:00:03'),
(52, 23, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 10:12:14'),
(53, 24, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 11:40:29'),
(54, 25, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 11:46:44'),
(55, 26, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 13:07:24'),
(56, 27, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-02 19:41:07'),
(57, 28, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-03 21:43:16'),
(58, 29, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-03 21:58:05'),
(59, 30, 'pending', NULL, 'Order placed successfully', NULL, '2026-07-04 09:48:29');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_proof` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `refund_amount` decimal(15,2) DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_number`, `order_id`, `user_id`, `payment_method_id`, `amount`, `transaction_id`, `payment_status`, `payment_proof`, `notes`, `processed_by`, `processed_at`, `refund_amount`, `refund_reason`, `refunded_at`, `created_at`) VALUES
(5, 'PAY-20260701-8229', 13, 9, 1, 4235020.00, 'TXN-20260701-8670', 'completed', NULL, NULL, NULL, '2026-07-01 18:55:54', 0.00, NULL, NULL, '2026-07-01 18:55:54'),
(6, 'PAY-20260701-3850', 15, 5, 1, 8241120.00, 'TXN-20260701-7170', 'completed', NULL, NULL, NULL, '2026-07-01 21:05:56', 0.00, NULL, NULL, '2026-07-01 21:05:56'),
(7, 'PAY-20260701-3659', 16, 9, 1, 4248000.00, 'TXN-20260701-4701', 'completed', NULL, NULL, NULL, '2026-07-01 21:16:52', 0.00, NULL, NULL, '2026-07-01 21:16:52'),
(8, 'PAY-20260701-7701', 12, 9, 1, 12430120.00, 'TXN-20260701-5353', 'completed', NULL, NULL, NULL, '2026-07-01 21:20:24', 0.00, NULL, NULL, '2026-07-01 21:20:24'),
(9, 'PAY-20260701-9096', 17, 9, 1, 1416000.00, 'TXN-20260701-1651', 'completed', NULL, NULL, NULL, '2026-07-01 21:50:43', 0.00, NULL, NULL, '2026-07-01 21:50:43'),
(10, 'PAY-20260701-7313', 18, 9, 1, 1416000.00, 'TXN-20260701-9977', 'completed', NULL, NULL, NULL, '2026-07-01 21:55:55', 0.00, NULL, NULL, '2026-07-01 21:55:55'),
(11, 'PAY-20260701-5671', 19, 9, 1, 2832000.00, 'TXN-20260701-4870', 'completed', NULL, NULL, NULL, '2026-07-01 22:16:37', 0.00, NULL, NULL, '2026-07-01 22:16:37'),
(12, 'PAY-20260701-5381', 20, 9, 1, 5664000.00, 'TXN-20260701-0122', 'completed', NULL, NULL, NULL, '2026-07-01 22:27:41', 0.00, NULL, NULL, '2026-07-01 22:27:41'),
(13, 'PAY-20260702-7916', 21, 9, 1, 2773000.00, 'TXN-20260702-8839', 'completed', NULL, NULL, NULL, '2026-07-02 09:42:59', 0.00, NULL, NULL, '2026-07-02 09:42:59'),
(14, 'PAY-20260702-3157', 22, 9, 1, 1180000.00, 'TXN-20260702-3987', 'completed', NULL, NULL, NULL, '2026-07-02 10:01:27', 0.00, NULL, NULL, '2026-07-02 10:01:27'),
(15, 'PAY-20260702-9519', 23, 9, 1, 767000.00, 'TXN-20260702-7792', 'completed', NULL, NULL, NULL, '2026-07-02 10:15:58', 0.00, NULL, NULL, '2026-07-02 10:15:58'),
(16, 'PAY-20260702-8657', 24, 9, 1, 767000.00, 'TXN-20260702-9657', 'completed', NULL, NULL, NULL, '2026-07-02 11:44:50', 0.00, NULL, NULL, '2026-07-02 11:44:50'),
(17, 'PAY-20260702-8179', 24, 9, 1, 767000.00, 'TXN-20260702-5477', 'completed', NULL, NULL, NULL, '2026-07-02 11:45:42', 0.00, NULL, NULL, '2026-07-02 11:45:42'),
(18, 'PAY-20260702-2450', 25, 9, 1, 1534000.00, 'TXN-20260702-4905', 'completed', NULL, NULL, NULL, '2026-07-02 11:47:19', 0.00, NULL, NULL, '2026-07-02 11:47:19'),
(19, 'PAY-20260702-2098', 26, 9, 1, 24208880.00, 'TXN-20260702-5825', 'completed', NULL, NULL, NULL, '2026-07-02 13:12:47', 0.00, NULL, NULL, '2026-07-02 13:12:47'),
(20, 'PAY-20260702-9139', 27, 9, 2, 2773000.00, 'TXN-20260702-0638', 'completed', NULL, NULL, NULL, '2026-07-02 19:41:46', 0.00, NULL, NULL, '2026-07-02 19:41:46'),
(21, 'PAY-20260703-5123', 28, 9, 1, 2773000.00, 'TXN-20260703-4854', 'completed', NULL, NULL, NULL, '2026-07-03 21:44:43', 0.00, NULL, NULL, '2026-07-03 21:44:43'),
(22, 'PAY-20260703-7359', 29, 9, 1, 767000.00, 'TXN-20260703-3607', 'completed', NULL, NULL, NULL, '2026-07-03 22:40:32', 0.00, NULL, NULL, '2026-07-03 22:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `payment_logs`
--

CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `status` enum('pending','success','failed','refunded') DEFAULT 'pending',
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `method_code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `additional_fee_percent` decimal(5,2) DEFAULT 0.00,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_name`, `method_code`, `is_active`, `additional_fee_percent`, `settings`, `created_at`) VALUES
(1, 'M-Pesa', 'MPESA', 1, 0.00, NULL, '2026-06-16 12:08:56'),
(2, 'Airtel Money', 'AIRTEL', 1, 0.00, NULL, '2026-06-16 12:08:56'),
(3, 'Tigo Pesa', 'TIGO', 1, 0.00, NULL, '2026-06-16 12:08:56'),
(4, 'Halopesa', 'HALOPESA', 1, 0.00, NULL, '2026-06-16 12:08:56'),
(5, 'Bank Transfer', 'BANK', 1, 0.00, NULL, '2026-06-16 12:08:56'),
(6, 'Credit/Debit Card', 'CARD', 1, 0.00, NULL, '2026-06-16 12:08:56');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `price_tsh` decimal(15,2) NOT NULL,
  `cost_price_tsh` decimal(15,2) DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `min_order` int(11) DEFAULT 1,
  `weight_kg` decimal(8,2) DEFAULT NULL,
  `compare_price_tsh` decimal(15,2) DEFAULT NULL,
  `bulk_price_tsh` decimal(15,2) DEFAULT NULL,
  `bulk_min_quantity` int(11) DEFAULT 10,
  `min_order_quantity` int(11) DEFAULT 1,
  `stock_quantity` int(11) DEFAULT 0,
  `stock_status` enum('in_stock','out_of_stock','pre_order','discontinued') DEFAULT 'out_of_stock',
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `warranty_months` int(11) DEFAULT 12,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `compare_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `min_quantity` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `tags` varchar(255) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `product_code`, `supplier_id`, `category_id`, `brand_id`, `product_name`, `slug`, `description`, `short_description`, `sku`, `brand`, `price_tsh`, `cost_price_tsh`, `quantity`, `quantity_available`, `min_order`, `weight_kg`, `compare_price_tsh`, `bulk_price_tsh`, `bulk_min_quantity`, `min_order_quantity`, `stock_quantity`, `stock_status`, `weight`, `dimensions`, `warranty_months`, `rating`, `total_reviews`, `views`, `is_featured`, `approval_status`, `status`, `featured`, `created_by`, `created_at`, `updated_at`, `approved_by`, `approved_at`, `price`, `compare_price`, `cost_price`, `min_quantity`, `is_active`, `tags`, `meta_title`, `meta_description`, `meta_keywords`) VALUES
(1, '', NULL, 2, 1, NULL, 'Dell Optiplex 7020 Plus', 'dell-optiplex-7020-plus', 'for heavy duties like gaming', 'high quality', '001', 'Dell', 1200000.00, 0.00, 0, 0, 1, NULL, 0.00, 0.00, 11, 1, 86, 'out_of_stock', 2.00, '2x2x5', 12, 0.00, 0, 19, 1, 'approved', 'active', 0, 5, '2026-06-17 20:00:20', '2026-07-04 09:48:29', 5, '2026-06-17 20:05:10', 0.00, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(2, '', NULL, 2, 4, NULL, 'Router', 'router', 'very strong and fiderity', 'high quality', '002', 'Cisco', 500000.00, 0.00, 0, 0, 1, NULL, 0.00, 550000.00, 10, 5, 89, 'out_of_stock', 1.50, '2x2x5', 6, 0.00, 0, 9, 1, 'approved', 'active', 0, 5, '2026-06-17 22:08:44', '2026-07-04 09:48:29', 5, '2026-06-17 22:09:13', 0.00, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(3, '', NULL, 1, 2, NULL, 'laptop', 'laptop', '', '', '003', 'dell', 650000.00, 0.00, 0, 0, 1, NULL, 0.00, 0.00, 10, 1, 79, 'out_of_stock', 2.00, '', 11, 0.00, 0, 4, 0, 'approved', 'active', 0, 5, '2026-06-24 10:14:56', '2026-07-04 09:48:29', 5, '2026-06-24 10:15:22', 0.00, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(13, 'Server', 'PRD-20260703-78C804', 5, 3, 12, 'Server', 'server', '', 'high quality', '004', 'IBM', 1000000.00, 0.00, 100, 0, 1, NULL, 1000000.00, 10000.00, 10, 1, 99, 'out_of_stock', 0.00, '', 12, 0.00, 0, 1, 0, 'approved', 'active', 0, 5, '2026-07-03 23:12:07', '2026-07-04 09:48:29', 5, '2026-07-03 23:15:18', 1000000.00, NULL, NULL, 1, 1, '', '', '', ''),
(16, 'SSD', 'PRD-20260703-B22CA7', 20, 6, 9, 'Ssd', 'ssd', '', 'high quality', '005', 'Intel', 60000.00, 0.00, 200, 200, 1, NULL, 0.00, 0.00, 10, 1, 99, 'out_of_stock', 0.00, '', 12, 0.00, 0, 0, 1, 'approved', 'active', 0, 20, '2026-07-03 23:25:47', '2026-07-04 09:48:29', 5, '2026-07-04 09:43:53', 65000.00, NULL, NULL, 1, 1, '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`, `created_at`) VALUES
(1, 1, 'uploads/products/1/product_1781715620_0.png', NULL, 1, 0, '2026-06-17 20:00:20'),
(2, 2, 'uploads/products/2/product_1781723324_0.png', NULL, 1, 0, '2026-06-17 22:08:44'),
(3, 3, 'uploads/products/3/product_1782285296_0.png', NULL, 1, 0, '2026-06-24 10:14:56'),
(4, 13, 'uploads/products/13/product_1783109710_0.jpg', NULL, 1, 0, '2026-07-03 23:15:10'),
(5, 16, 'uploads/products/16/product_1783147423_0.png', NULL, 1, 0, '2026-07-04 09:43:43');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `images` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_verified` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_specifications`
--

CREATE TABLE `product_specifications` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `spec_name` varchar(100) NOT NULL,
  `spec_value` text NOT NULL,
  `spec_unit` varchar(20) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `spec_key` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_sku` varchar(50) NOT NULL,
  `variant_name` varchar(200) DEFAULT NULL,
  `specifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications`)),
  `price_adjustment` decimal(10,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`products`)),
  `delivery_location` text DEFAULT NULL,
  `delivery_deadline` date DEFAULT NULL,
  `budget_min` decimal(15,2) DEFAULT NULL,
  `budget_max` decimal(15,2) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `status` enum('draft','open','closed','cancelled','awarded') DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `quotation_number`, `user_id`, `title`, `description`, `products`, `delivery_location`, `delivery_deadline`, `budget_min`, `budget_max`, `payment_terms`, `special_requirements`, `status`, `created_at`, `updated_at`) VALUES
(1, 'QTN-20260617-3356', 2, 'laptops', 'heavy', '{\"name\":\"laptop\",\"quantity\":5,\"category_id\":2}', 'mwanza', '2026-07-02', 1200000.00, 0.00, 'On Delivery', 'ssd', 'open', '2026-06-17 11:33:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quotation_responses`
--

CREATE TABLE `quotation_responses` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `response_number` varchar(50) NOT NULL,
  `quote_message` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `delivery_timeframe` varchar(100) DEFAULT NULL,
  `validity_days` int(11) DEFAULT 30,
  `status` enum('draft','submitted','accepted','rejected','expired') DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `role_description`, `permissions`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Full system administrator with all permissions', '{\"all\": true}', 1, '2026-06-16 12:08:41', NULL),
(2, 'supplier', 'Supplier who sells products on the platform', '{\"manage_products\": true, \"view_orders\": true, \"send_quotations\": true}', 1, '2026-06-16 12:08:41', NULL),
(3, 'customer', 'Customer who buys products on the platform', '{\"purchase_products\": true, \"view_orders\": true, \"request_quotations\": true}', 1, '2026-06-16 12:08:41', NULL),
(5, 'superadmin', NULL, NULL, 1, '2026-06-28 17:25:43', NULL),
(6, 'vendor', NULL, NULL, 1, '2026-06-28 17:25:43', NULL),
(7, 'user', NULL, NULL, 1, '2026-06-28 17:25:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `business_description` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `verification_badge` tinyint(1) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_sales` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `country` varchar(100) DEFAULT 'Tanzania',
  `company_description` text DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `vat_registered` tinyint(1) DEFAULT 0,
  `verification_status` enum('unverified','pending','verified','rejected') DEFAULT 'unverified',
  `website` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `business_license` varchar(255) DEFAULT NULL,
  `tax_clearance` varchar(255) DEFAULT NULL,
  `verification_documents` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `company_name`, `company_logo`, `registration_number`, `tax_id`, `contact_person`, `email`, `phone`, `address`, `city`, `region`, `business_description`, `approval_status`, `verification_badge`, `rating`, `total_sales`, `total_reviews`, `status`, `created_at`, `updated_at`, `country`, `company_description`, `business_type`, `tin_number`, `vat_registered`, `verification_status`, `website`, `facebook`, `twitter`, `instagram`, `linkedin`, `bank_name`, `bank_account_name`, `bank_account_number`, `swift_code`, `bank_branch`, `business_license`, `tax_clearance`, `verification_documents`) VALUES
(1, 6, 'vicking', NULL, '122', '0.18', 'yenu', 'zeno1@gmail.com', '+255760211221', 'kk', 'Dar es Salaam', 'Dar es Salaam', 'good service', 'approved', 1, 0.00, 0, 0, 'active', '2026-06-17 19:50:59', '2026-06-22 22:34:33', 'Tanzania', NULL, NULL, NULL, 0, 'unverified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 7, 'vicking', NULL, '122', '0.18', 'heroku ic', 'heroku11@gmail.com', '+255760211221', 'kk', 'Dar es Salaam', 'Dar es Salaam', 'good products', 'approved', 1, 0.00, 0, 0, 'active', '2026-06-17 19:55:34', '2026-06-17 19:57:14', 'Tanzania', NULL, NULL, NULL, 0, 'unverified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 13, 'ANU', NULL, '221', '0.18', 'annu ic', 'annu0@gmail.com', '+255760211221', 'kk', 'Dar es Salaam', '', '', 'approved', 1, 0.00, 0, 0, 'active', '2026-06-27 17:55:09', '2026-06-28 20:07:11', 'Tanzania', NULL, NULL, NULL, 0, 'unverified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 19, 'Hk', NULL, '122', '0.18', 'annu', 'annu12@gmail.com', '+255760211221', 'kk', 'Dar es Salaam', 'Dar es Salaam', '', 'approved', 1, 0.00, 0, 0, 'active', '2026-06-28 20:41:13', '2026-06-28 21:30:31', 'Tanzania', NULL, NULL, NULL, 0, 'unverified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 20, 'MICROSOFT', NULL, '12222', '0.18', 'MACK OTD', 'macrosoft@gmail.com', '+255760211221', 'kk', 'Dar es Salaam', 'Dar es Salaam', 'high quality products', 'approved', 1, 0.00, 0, 0, 'active', '2026-07-01 22:55:34', '2026-07-01 22:58:01', 'Tanzania', NULL, NULL, NULL, 0, 'unverified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 20, 'MICROSOFT', NULL, NULL, NULL, 'MACK OTD', 'macrosoft@gmail.com', '+255760211221', '', '', '', NULL, 'approved', 1, 0.00, 0, 0, 'active', '2026-07-02 09:29:26', '2026-07-04 00:17:56', 'Tanzania', NULL, NULL, NULL, 0, 'unverified', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_bank_accounts`
--

CREATE TABLE `supplier_bank_accounts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(200) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_reviews`
--

CREATE TABLE `supplier_reviews` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `response_text` text DEFAULT NULL,
  `response_by` int(11) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','file') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `category` varchar(50) DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `updated_at`, `created_at`, `category`) VALUES
(1, 'site_name', 'TechProcure Tanzania', 'text', 'Platform name', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(2, 'site_email', 'info@techprocure.co.tz', 'text', 'Default support email', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(3, 'site_phone', '+255 123 456 789', 'text', 'Default contact phone', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(4, 'tax_rate', '18', 'number', 'Default tax rate percentage', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(5, 'currency', 'TSh', 'text', 'Default currency', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(6, 'currency_symbol', 'TSh', 'text', 'Currency symbol', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(7, 'commission_rate', '2.5', 'number', 'Commission rate for suppliers', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(8, 'escrow_enabled', 'true', 'boolean', 'Enable escrow payment system', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(9, 'bulk_discount_enabled', 'true', 'boolean', 'Enable bulk discount system', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(10, 'rfq_enabled', 'true', 'boolean', 'Enable RFQ system', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(11, 'maintenance_mode', 'false', 'boolean', 'Put site in maintenance mode', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(12, 'allow_registration', 'true', 'boolean', 'Allow new user registration', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(13, 'email_verification_required', 'false', 'boolean', 'Require email verification', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(14, 'max_cart_items', '10000', 'number', 'Maximum items per cart', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(15, 'min_order_amount', '0', 'number', 'Minimum order amount', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(16, 'free_shipping_threshold', '100000', 'number', 'Free shipping threshold', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general'),
(17, 'delivery_days', '7', 'number', 'Default delivery days', 5, '2026-06-24 23:55:42', '2026-06-16 12:09:05', 'general');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company_name` varchar(200) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_type` enum('customer','supplier','admin') DEFAULT 'customer',
  `status` enum('active','inactive','pending','suspended') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `role` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Tanzania'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `first_name`, `last_name`, `phone`, `company_name`, `company_address`, `city`, `region`, `tin_number`, `role_id`, `user_type`, `status`, `is_active`, `email_verified`, `phone_verified`, `profile_image`, `last_login`, `last_ip`, `created_at`, `updated_at`, `remember_token`, `token_expires`, `role`, `address`, `postal_code`, `country`) VALUES
(2, 'heroku', 'heroku001@gmail.com', '$2y$10$v.F54agSDtidns6hVigPDueiEjrAzAdiNe4nkcBxK4tUUfiPC7G3K', 'heroku ic', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, '2026-06-17 11:43:59', '::1', '2026-06-17 10:53:08', '2026-06-17 11:43:59', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(5, 'yeye', 'heroku01@gmail.com', '$2y$10$A.cZkta9vCZnIrUiKOo69euJF5.0g.RsyXCHEfmhweJm3TtnDX//2', 'yenu', NULL, NULL, '+255760211221', NULL, NULL, NULL, NULL, NULL, 5, 'admin', 'active', 1, 1, 0, NULL, '2026-07-04 00:17:12', '::1', '2026-06-17 12:23:56', '2026-07-04 00:17:12', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(6, 'zeno1971', 'zeno1@gmail.com', '$2y$10$VN4isZehjt01p9HZ7IuYbO6ibNWYMqpjMnLRj.9L1fpFN7XMpsRKm', 'yenu', NULL, NULL, '+255760211221', 'vicking', NULL, NULL, NULL, NULL, 2, 'supplier', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-17 19:50:59', '2026-06-22 22:34:33', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(7, 'heroku11854', 'heroku11@gmail.com', '$2y$10$dUrw5n8yC1pOxZX2HZT.GelWY91DTkEZ4lSDkvpbKZf7Ax8e.mhbC', 'heroku ic', NULL, NULL, '+255760211221', 'vicking', NULL, NULL, NULL, NULL, 2, 'supplier', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-17 19:55:34', '2026-06-17 19:57:14', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(8, 'heroku01@gmail.com', 'zeno12@gmail.com', '$2y$10$ideDozGZJIiHcbb6npzDUu7gK6C8g8JtXzEM9Ffr7LvEYwlQ7oTtC', 'yenu', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-17 22:16:23', NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(9, 'heroku00', 'heroku0001@gmail.com', '$2y$10$KgEzbsABCTi47l9dlsS2i.xDw16oocu0uRnnDh5mSrrDAFWzNkrpK', 'herok', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', 'Dar es Salaam', '', 3, 'customer', 'active', 1, 0, 0, 'uploads/profiles/profile_9_1782974771.png', '2026-07-04 09:46:01', '::1', '2026-06-17 22:19:00', '2026-07-04 09:46:01', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(10, 'annu1', 'annu1@gmail.com', '$2y$10$3ODL0509iyLno4..rZ30leuW93jpwfLRrIyGVLxZ9i9ejsQ8zWBq6', 'annu', NULL, NULL, '+255760211221', 'oop', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 13:59:24', NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(11, 'annu2', 'annu11@gmail.com', '$2y$10$ZrG0xjUYqmRNXZ/rlQG6f.f85gLisMSM6ycr4Auha15l1AC8nttOm', 'Annu ic', NULL, NULL, '+255760211221', 'vick', '', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 14:03:02', NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(12, 'lily1', 'lily@gmail.com', '$2y$10$CrgKJ4tLI9UsLX3hTFUM5eAwwsKBCMlG4.3NDzt8S1.J2W4q2Rlne', 'lil mk', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 14:37:31', NULL, NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(13, 'annu0450', 'annu0@gmail.com', '$2y$10$ZjaeNhY.0FoZqGtXcZkKte8ZALUQ9itUtAd234mk6UNI96pOLf6m2', 'annu ic', NULL, NULL, '+255760211221', 'ANU', NULL, NULL, NULL, NULL, NULL, 'supplier', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 17:55:09', '2026-06-28 20:07:11', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(14, 'supplier', 'supplier@techprocure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Supplier User', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 'supplier', 'active', 1, 1, 0, NULL, NULL, NULL, '2026-06-28 17:25:44', '2026-06-28 19:38:51', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(15, 'customer', 'customer@techprocure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Customer User', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, 'customer', 'active', 1, 1, 0, NULL, NULL, NULL, '2026-06-28 17:25:44', '2026-06-28 19:38:51', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(19, 'annu12855', 'annu12@gmail.com', '$2y$12$xOWewqQYnLygqo639b4U3Op1SbET7ukgwQEQZ34W0TD4mZIcCp0tC', 'annu', NULL, NULL, '+255760211221', 'Hk', NULL, NULL, NULL, NULL, NULL, 'supplier', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-28 20:41:13', '2026-06-28 21:30:32', NULL, NULL, NULL, NULL, NULL, 'Tanzania'),
(20, 'macrosoft371', 'macrosoft@gmail.com', '$2y$12$eDVJvDqe80AbIwqIj.Btl.sGmU81lGAIggbL7brK4vLZzdjnd2SXm', 'MACK OTD', NULL, NULL, '+255760211221', 'MICROSOFT', NULL, '', '', NULL, NULL, 'supplier', 'active', 1, 0, 0, NULL, '2026-07-03 23:16:44', NULL, '2026-07-01 22:55:34', '2026-07-04 00:13:59', NULL, NULL, NULL, '', '', 'Tanzania');

-- --------------------------------------------------------

--
-- Table structure for table `users_backup`
--

CREATE TABLE `users_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company_name` varchar(200) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_type` enum('customer','supplier','admin') DEFAULT 'customer',
  `status` enum('active','inactive','pending','suspended') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `role` enum('admin','customer','supplier') DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_backup`
--

INSERT INTO `users_backup` (`id`, `username`, `email`, `password_hash`, `full_name`, `first_name`, `last_name`, `phone`, `company_name`, `company_address`, `city`, `region`, `tin_number`, `role_id`, `user_type`, `status`, `is_active`, `email_verified`, `phone_verified`, `profile_image`, `last_login`, `last_ip`, `created_at`, `updated_at`, `role`) VALUES
(2, 'heroku', 'heroku001@gmail.com', '$2y$10$v.F54agSDtidns6hVigPDueiEjrAzAdiNe4nkcBxK4tUUfiPC7G3K', 'heroku ic', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, '2026-06-17 11:43:59', '::1', '2026-06-17 10:53:08', '2026-06-17 11:43:59', 'customer'),
(4, 'admin', 'admin@techprocure.co.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'System', 'Administrator', '+255760211221', 'TechProcure Tanzania', NULL, NULL, NULL, NULL, 1, 'admin', 'active', 1, 1, 0, NULL, NULL, NULL, '2026-06-17 12:11:00', '2026-06-27 15:08:34', 'admin'),
(5, 'yeye', 'heroku01@gmail.com', '$2y$10$A.cZkta9vCZnIrUiKOo69euJF5.0g.RsyXCHEfmhweJm3TtnDX//2', 'yenu', NULL, NULL, '+255760211221', NULL, NULL, NULL, NULL, NULL, 1, 'admin', 'active', 1, 1, 0, NULL, '2026-06-27 14:44:33', '::1', '2026-06-17 12:23:56', '2026-06-27 14:44:33', 'customer'),
(6, 'zeno1971', 'zeno1@gmail.com', '$2y$10$VN4isZehjt01p9HZ7IuYbO6ibNWYMqpjMnLRj.9L1fpFN7XMpsRKm', 'yenu', NULL, NULL, '+255760211221', 'vicking', NULL, NULL, NULL, NULL, 2, 'supplier', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-17 19:50:59', '2026-06-22 22:34:33', 'customer'),
(7, 'heroku11854', 'heroku11@gmail.com', '$2y$10$dUrw5n8yC1pOxZX2HZT.GelWY91DTkEZ4lSDkvpbKZf7Ax8e.mhbC', 'heroku ic', NULL, NULL, '+255760211221', 'vicking', NULL, NULL, NULL, NULL, 2, 'supplier', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-17 19:55:34', '2026-06-17 19:57:14', 'customer'),
(8, 'heroku01@gmail.com', 'zeno12@gmail.com', '$2y$10$ideDozGZJIiHcbb6npzDUu7gK6C8g8JtXzEM9Ffr7LvEYwlQ7oTtC', 'yenu', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-17 22:16:23', NULL, 'customer'),
(9, 'heroku00', 'heroku0001@gmail.com', '$2y$10$KgEzbsABCTi47l9dlsS2i.xDw16oocu0uRnnDh5mSrrDAFWzNkrpK', 'herok', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, '2026-06-27 13:02:34', '::1', '2026-06-17 22:19:00', '2026-06-27 13:02:34', 'customer'),
(10, 'annu1', 'annu1@gmail.com', '$2y$10$3ODL0509iyLno4..rZ30leuW93jpwfLRrIyGVLxZ9i9ejsQ8zWBq6', 'annu', NULL, NULL, '+255760211221', 'oop', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 13:59:24', NULL, 'customer'),
(11, 'annu2', 'annu11@gmail.com', '$2y$10$ZrG0xjUYqmRNXZ/rlQG6f.f85gLisMSM6ycr4Auha15l1AC8nttOm', 'Annu ic', NULL, NULL, '+255760211221', 'vick', '', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 14:03:02', NULL, 'customer'),
(12, 'lily1', 'lily@gmail.com', '$2y$10$CrgKJ4tLI9UsLX3hTFUM5eAwwsKBCMlG4.3NDzt8S1.J2W4q2Rlne', 'lil mk', NULL, NULL, '+255760211221', 'vicking', 'kk', 'Dar es Salaam', NULL, NULL, 3, 'customer', 'active', 1, 0, 0, NULL, NULL, NULL, '2026-06-27 14:37:31', NULL, 'customer');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(1, 2, 'd4dd3fd69c9da42895c29fe3b37206f5e59675fa83689c15219d7dd95041d34c', NULL, NULL, '2026-06-24 11:43:59', '2026-06-17 11:43:59'),
(3, 9, 'e572289768aeef0f84e839737f503f75be850db6b88b0068358f38473aa76dfe', NULL, NULL, '2026-07-03 18:24:27', '2026-06-26 18:24:27');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `order_updates` tinyint(1) DEFAULT 1,
  `promotional_emails` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `currency` varchar(10) DEFAULT 'TSh',
  `timezone` varchar(50) DEFAULT 'Africa/Dar_es_Salaam',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `order_notifications` tinyint(1) DEFAULT 1,
  `quotation_notifications` tinyint(1) DEFAULT 1,
  `payment_notifications` tinyint(1) DEFAULT 1,
  `promotion_emails` tinyint(1) DEFAULT 0,
  `two_factor_auth` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `email_notifications`, `sms_notifications`, `order_updates`, `promotional_emails`, `language`, `currency`, `timezone`, `created_at`, `updated_at`, `order_notifications`, `quotation_notifications`, `payment_notifications`, `promotion_emails`, `two_factor_auth`) VALUES
(1, 9, 1, 1, 1, 1, 'en', 'TSh', 'Africa/Dar_es_Salaam', '2026-06-22 23:32:14', '2026-07-02 09:44:39', 1, 1, 1, 0, 0),
(2, 20, 1, 0, 1, 0, 'sw', 'TSh', 'Africa/Dar_es_Salaam', '2026-07-04 00:08:04', '2026-07-04 00:08:18', 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_active_products`
-- (See below for the actual view)
--
CREATE TABLE `v_active_products` (
`id` int(11)
,`sku` varchar(50)
,`product_name` varchar(255)
,`slug` varchar(255)
,`short_description` varchar(500)
,`price_tsh` decimal(15,2)
,`bulk_price_tsh` decimal(15,2)
,`stock_quantity` int(11)
,`min_order_quantity` int(11)
,`rating` decimal(3,2)
,`total_reviews` int(11)
,`category_name` varchar(100)
,`category_slug` varchar(100)
,`supplier_name` varchar(200)
,`verification_badge` tinyint(1)
,`supplier_rating` decimal(3,2)
,`primary_image` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_order_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_order_summary` (
`id` int(11)
,`order_number` varchar(50)
,`order_status` enum('pending','confirmed','processing','shipped','delivered','completed','cancelled')
,`payment_status` enum('pending','paid','failed','refunded','partial')
,`total_amount` decimal(15,2)
,`created_at` datetime
,`customer_name` varchar(200)
,`customer_email` varchar(255)
,`customer_company` varchar(200)
,`supplier_company` varchar(200)
,`item_count` bigint(21)
,`total_items` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pending_suppliers`
-- (See below for the actual view)
--
CREATE TABLE `v_pending_suppliers` (
`id` int(11)
,`user_id` int(11)
,`company_name` varchar(200)
,`company_logo` varchar(255)
,`registration_number` varchar(100)
,`tax_id` varchar(100)
,`contact_person` varchar(200)
,`email` varchar(255)
,`phone` varchar(20)
,`address` text
,`city` varchar(100)
,`region` varchar(100)
,`business_description` text
,`approval_status` enum('pending','approved','rejected','suspended')
,`verification_badge` tinyint(1)
,`rating` decimal(3,2)
,`total_sales` int(11)
,`total_reviews` int(11)
,`status` enum('active','inactive')
,`created_at` datetime
,`updated_at` datetime
,`user_email` varchar(255)
,`days_pending` int(7)
);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `v_active_products`
--
DROP TABLE IF EXISTS `v_active_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_products`  AS SELECT `p`.`id` AS `id`, `p`.`sku` AS `sku`, `p`.`product_name` AS `product_name`, `p`.`slug` AS `slug`, `p`.`short_description` AS `short_description`, `p`.`price_tsh` AS `price_tsh`, `p`.`bulk_price_tsh` AS `bulk_price_tsh`, `p`.`stock_quantity` AS `stock_quantity`, `p`.`min_order_quantity` AS `min_order_quantity`, `p`.`rating` AS `rating`, `p`.`total_reviews` AS `total_reviews`, `c`.`name` AS `category_name`, `c`.`slug` AS `category_slug`, `s`.`company_name` AS `supplier_name`, `s`.`verification_badge` AS `verification_badge`, `s`.`rating` AS `supplier_rating`, (select `product_images`.`image_path` from `product_images` where `product_images`.`product_id` = `p`.`id` and `product_images`.`is_primary` = 1 limit 1) AS `primary_image` FROM ((`products` `p` join `categories` `c` on(`p`.`category_id` = `c`.`id`)) join `suppliers` `s` on(`p`.`supplier_id` = `s`.`id`)) WHERE `p`.`status` = 'active' AND `p`.`approval_status` = 'approved' ;

-- --------------------------------------------------------

--
-- Structure for view `v_order_summary`
--
DROP TABLE IF EXISTS `v_order_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_summary`  AS SELECT `o`.`id` AS `id`, `o`.`order_number` AS `order_number`, `o`.`order_status` AS `order_status`, `o`.`payment_status` AS `payment_status`, `o`.`total_amount` AS `total_amount`, `o`.`created_at` AS `created_at`, `u`.`full_name` AS `customer_name`, `u`.`email` AS `customer_email`, `u`.`company_name` AS `customer_company`, `s`.`company_name` AS `supplier_company`, count(`oi`.`id`) AS `item_count`, sum(`oi`.`quantity`) AS `total_items` FROM (((`orders` `o` join `users` `u` on(`o`.`user_id` = `u`.`id`)) left join `suppliers` `s` on(`o`.`supplier_id` = `s`.`id`)) left join `order_items` `oi` on(`o`.`id` = `oi`.`order_id`)) GROUP BY `o`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_pending_suppliers`
--
DROP TABLE IF EXISTS `v_pending_suppliers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_suppliers`  AS SELECT `s`.`id` AS `id`, `s`.`user_id` AS `user_id`, `s`.`company_name` AS `company_name`, `s`.`company_logo` AS `company_logo`, `s`.`registration_number` AS `registration_number`, `s`.`tax_id` AS `tax_id`, `s`.`contact_person` AS `contact_person`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`address` AS `address`, `s`.`city` AS `city`, `s`.`region` AS `region`, `s`.`business_description` AS `business_description`, `s`.`approval_status` AS `approval_status`, `s`.`verification_badge` AS `verification_badge`, `s`.`rating` AS `rating`, `s`.`total_sales` AS `total_sales`, `s`.`total_reviews` AS `total_reviews`, `s`.`status` AS `status`, `s`.`created_at` AS `created_at`, `s`.`updated_at` AS `updated_at`, `u`.`email` AS `user_email`, to_days(current_timestamp()) - to_days(`s`.`created_at`) AS `days_pending` FROM (`suppliers` `s` join `users` `u` on(`s`.`user_id` = `u`.`id`)) WHERE `s`.`approval_status` = 'pending' ORDER BY `s`.`created_at` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_key` (`api_key`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `blocked_entities`
--
ALTER TABLE `blocked_entities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blocked_by` (`blocked_by`),
  ADD KEY `idx_entity` (`entity_type`,`entity_value`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_published` (`published_at`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `bulk_discounts`
--
ALTER TABLE `bulk_discounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tier` (`product_id`,`min_quantity`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_quantity` (`min_quantity`,`max_quantity`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `idx_cart` (`cart_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_company` (`company_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_tin_number` (`tin_number`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_last_login` (`last_login`),
  ADD KEY `idx_account_type` (`account_type`);
ALTER TABLE `customers` ADD FULLTEXT KEY `idx_search` (`company_name`,`contact_person`,`email`,`address`);

--
-- Indexes for table `daily_sales_summary`
--
ALTER TABLE `daily_sales_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `summary_date` (`summary_date`),
  ADD KEY `idx_date` (`summary_date`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dispute_number` (`dispute_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_dispute_number` (`dispute_number`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `dispute_messages`
--
ALTER TABLE `dispute_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_dispute` (`dispute_id`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `escrow_payments`
--
ALTER TABLE `escrow_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_escrow` (`escrow_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `global_discount_tiers`
--
ALTER TABLE `global_discount_tiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quantity` (`min_quantity`,`max_quantity`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_min_stock` (`min_stock`);

--
-- Indexes for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_time` (`attempt_time`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`login_status`);

--
-- Indexes for table `monthly_sales_summary`
--
ALTER TABLE `monthly_sales_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `summary_month` (`summary_month`),
  ADD KEY `idx_month` (`summary_month`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `quotation_response_id` (`quotation_response_id`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_status` (`order_status`,`payment_status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_number` (`payment_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_payment_number` (`payment_number`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_status` (`payment_status`),
  ADD KEY `idx_transaction` (`transaction_id`);

--
-- Indexes for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `method_name` (`method_name`),
  ADD UNIQUE KEY `method_code` (`method_code`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `idx_product_code` (`product_code`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_status` (`status`,`approval_status`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_price` (`price_tsh`),
  ADD KEY `idx_brand` (`brand_id`),
  ADD KEY `idx_sku` (`sku`);
ALTER TABLE `products` ADD FULLTEXT KEY `idx_search` (`product_name`,`description`,`brand`,`sku`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_primary` (`is_primary`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `product_specifications`
--
ALTER TABLE `product_specifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `variant_sku` (`variant_sku`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_sku` (`variant_sku`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `idx_quotation_number` (`quotation_number`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `quotation_responses`
--
ALTER TABLE `quotation_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `response_number` (`response_number`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_quotation` (`quotation_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_company` (`company_name`),
  ADD KEY `idx_approval` (`approval_status`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `supplier_bank_accounts`
--
ALTER TABLE `supplier_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`);

--
-- Indexes for table `supplier_reviews`
--
ALTER TABLE `supplier_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_entities`
--
ALTER TABLE `blocked_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bulk_discounts`
--
ALTER TABLE `bulk_discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_sales_summary`
--
ALTER TABLE `daily_sales_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispute_messages`
--
ALTER TABLE `dispute_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow_payments`
--
ALTER TABLE `escrow_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `global_discount_tiers`
--
ALTER TABLE `global_discount_tiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory_log`
--
ALTER TABLE `inventory_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `monthly_sales_summary`
--
ALTER TABLE `monthly_sales_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_specifications`
--
ALTER TABLE `product_specifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quotation_responses`
--
ALTER TABLE `quotation_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `supplier_bank_accounts`
--
ALTER TABLE `supplier_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_reviews`
--
ALTER TABLE `supplier_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blocked_entities`
--
ALTER TABLE `blocked_entities`
  ADD CONSTRAINT `blocked_entities_ibfk_1` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blog_posts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bulk_discounts`
--
ALTER TABLE `bulk_discounts`
  ADD CONSTRAINT `bulk_discounts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`);

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `disputes_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `disputes_ibfk_4` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dispute_messages`
--
ALTER TABLE `dispute_messages`
  ADD CONSTRAINT `dispute_messages_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispute_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `escrow_payments`
--
ALTER TABLE `escrow_payments`
  ADD CONSTRAINT `escrow_payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  ADD CONSTRAINT `escrow_transactions_ibfk_1` FOREIGN KEY (`escrow_id`) REFERENCES `escrow_payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `escrow_transactions_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD CONSTRAINT `inventory_log_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `invoices_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`quotation_response_id`) REFERENCES `quotation_responses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_5` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`);

--
-- Constraints for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_tracking_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD CONSTRAINT `payment_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_specifications`
--
ALTER TABLE `product_specifications`
  ADD CONSTRAINT `product_specifications_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `quotation_responses`
--
ALTER TABLE `quotation_responses`
  ADD CONSTRAINT `quotation_responses_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quotation_responses_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `quotation_responses_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_bank_accounts`
--
ALTER TABLE `supplier_bank_accounts`
  ADD CONSTRAINT `supplier_bank_accounts_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_reviews`
--
ALTER TABLE `supplier_reviews`
  ADD CONSTRAINT `supplier_reviews_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

