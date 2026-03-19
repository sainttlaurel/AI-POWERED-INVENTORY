-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 15, 2026 at 09:53 AM
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
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(14, 'Nike', 'Nike brand footwear and apparel', '2026-03-13 08:49:05'),
(20, 'Adidas', 'Adidas brand footwear and apparel', '2026-03-13 09:40:56'),
(21, 'Puma', 'Puma brand footwear and apparel', '2026-03-13 09:40:56'),
(22, 'Under Armour', 'Under Armour brand footwear and apparel', '2026-03-13 09:40:56'),
(24, 'New Balance', 'New Balance footwear and apparel', '2026-03-13 12:51:38'),
(25, 'Converse', 'Converse sneakers and apparel', '2026-03-13 12:51:45'),
(26, 'Reebok', 'Reebok sportswear and training shoes', '2026-03-13 12:52:09'),
(27, 'ASICS', 'ASICS running and performance footwear', '2026-03-13 12:52:22'),
(28, 'JORDAN', 'Air Jordan sneakers and apparel', '2026-03-13 12:52:32'),
(29, 'The North Face', 'Outdoor jackets and apparel', '2026-03-13 12:52:41'),
(30, 'Columbia', 'Columbia outdoor clothing and gear', '2026-03-13 12:52:48'),
(31, 'Lacoste', 'Lacoste premium sportswear and polo shirts', '2026-03-13 16:08:56'),
(32, 'Tommy Hilfiger', 'Tommy Hilfiger fashion and lifestyle wear', '2026-03-13 16:08:56'),
(33, 'Ralph Lauren', 'Ralph Lauren premium apparel and accessories', '2026-03-13 16:08:56'),
(36, 'Supreme', 'Supreme streetwear and limited edition items', '2026-03-13 16:08:56'),
(37, 'Off-White', 'Off-White luxury streetwear and accessories', '2026-03-13 16:08:56'),
(38, 'Stone Island', 'Stone Island technical and casual wear', '2026-03-13 16:08:56'),
(39, 'Stussy', 'Stussy streetwear and surf culture apparel', '2026-03-13 16:08:56'),
(40, 'Thrasher', 'Thrasher skateboard magazine merchandise', '2026-03-13 16:08:56'),
(41, 'Dr. Martens', 'Dr. Martens boots and alternative footwear', '2026-03-13 16:08:56'),
(46, 'Patagonia', 'Patagonia sustainable outdoor clothing', '2026-03-13 16:08:57'),
(47, 'Arc\'teryx', 'Arc\'teryx premium outdoor and technical wear', '2026-03-13 16:08:57'),
(50, 'Levi\'s', 'Levi\'s denim jeans and casual wear', '2026-03-13 16:08:57'),
(51, 'Wrangler', 'Wrangler western and workwear denim', '2026-03-13 16:08:57');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `query` text NOT NULL,
  `response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forecast_data`
--

CREATE TABLE `forecast_data` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `avg_daily_sales` decimal(10,2) DEFAULT NULL,
  `forecast_weekly` decimal(10,2) DEFAULT NULL,
  `forecast_monthly` decimal(10,2) DEFAULT NULL,
  `predicted_depletion_days` int(11) DEFAULT NULL,
  `reorder_suggestion` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forecast_data`
--

INSERT INTO `forecast_data` (`id`, `product_id`, `avg_daily_sales`, `forecast_weekly`, `forecast_monthly`, `predicted_depletion_days`, `reorder_suggestion`, `last_updated`) VALUES
(199, 11, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 12:06:45'),
(200, 13, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:10'),
(201, 14, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(202, 15, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(203, 16, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(204, 17, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(205, 18, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(206, 19, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(207, 20, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(208, 21, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 08:51:11'),
(209, 22, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:26:07'),
(231, 24, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(232, 25, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(233, 26, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(234, 27, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(235, 28, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(236, 29, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 13:21:26'),
(237, 30, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(238, 31, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(239, 32, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(240, 33, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(241, 34, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(242, 35, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(243, 36, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(244, 37, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(245, 38, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:34'),
(246, 40, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(247, 41, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(248, 42, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(249, 54, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(250, 43, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(251, 44, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(252, 45, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(253, 46, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(254, 47, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(255, 48, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(256, 49, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(257, 50, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(258, 51, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(259, 52, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 09:48:35'),
(1300, 55, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1301, 56, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1302, 57, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1303, 58, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1304, 59, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1305, 60, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1306, 61, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1307, 62, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1308, 63, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1309, 64, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1310, 65, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1311, 66, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1312, 67, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1313, 68, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1314, 69, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1315, 70, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1316, 71, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1317, 72, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1318, 73, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:07'),
(1319, 74, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1320, 75, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1321, 76, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1322, 77, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1323, 78, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1324, 79, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1325, 80, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1326, 81, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08'),
(1327, 82, 0.00, 0.00, 0.00, 999, 0, '2026-03-13 16:38:08');

-- --------------------------------------------------------

--
-- Table structure for table `forecast_data_advanced`
--

CREATE TABLE `forecast_data_advanced` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `avg_daily_sales` decimal(10,2) DEFAULT 0.00,
  `avg_sales_active_days` decimal(10,2) DEFAULT 0.00,
  `forecast_weekly` decimal(10,2) DEFAULT 0.00,
  `forecast_monthly` decimal(10,2) DEFAULT 0.00,
  `forecast_quarterly` decimal(10,2) DEFAULT 0.00,
  `predicted_depletion_days` int(11) DEFAULT 999,
  `reorder_suggestion` int(11) DEFAULT 0,
  `trend_direction` enum('increasing','decreasing','stable') DEFAULT 'stable',
  `trend_strength` decimal(10,4) DEFAULT 0.0000,
  `volatility` decimal(10,2) DEFAULT 0.00,
  `confidence_range` decimal(10,2) DEFAULT 0.00,
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `demand_pattern` enum('smooth','variable','erratic','intermittent','sporadic','no_demand') DEFAULT 'smooth',
  `revenue_forecast_weekly` decimal(12,2) DEFAULT 0.00,
  `revenue_forecast_monthly` decimal(12,2) DEFAULT 0.00,
  `sales_frequency` decimal(5,2) DEFAULT 0.00,
  `zero_sales_days` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forecast_data_advanced`
--

INSERT INTO `forecast_data_advanced` (`id`, `product_id`, `avg_daily_sales`, `avg_sales_active_days`, `forecast_weekly`, `forecast_monthly`, `forecast_quarterly`, `predicted_depletion_days`, `reorder_suggestion`, `trend_direction`, `trend_strength`, `volatility`, `confidence_range`, `risk_level`, `demand_pattern`, `revenue_forecast_weekly`, `revenue_forecast_monthly`, `sales_frequency`, `zero_sales_days`, `last_updated`) VALUES
(1, 11, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(2, 13, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(3, 14, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(4, 15, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(5, 16, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(6, 17, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(7, 18, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(8, 19, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(9, 20, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12'),
(10, 21, 0.00, 0.00, 0.00, 0.00, 0.00, 999, 0, 'stable', 0.0000, 0.00, 0.00, 'medium', 'no_demand', 0.00, 0.00, 0.00, 90, '2026-03-15 01:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `action` enum('stock_in','stock_out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `product_id`, `action`, `quantity`, `user_id`, `notes`, `created_at`) VALUES
(189, 31, 'stock_out', 1, 10, 'Sale #3 - Adidas Pureboost', '2026-03-13 12:36:19'),
(190, 28, 'stock_out', 1, 10, 'Sale #3 - Adidas Gazelle', '2026-03-13 12:36:19'),
(191, 22, 'stock_out', 2, 10, 'Sale #3 - Adidas Samba OG', '2026-03-13 12:36:19'),
(192, 11, 'stock_out', 1, 10, 'Sale #4 - Nike Air Force 1 \'07', '2026-03-13 12:38:39'),
(193, 35, 'stock_out', 2, 10, 'Sale #5 - Puma Cali Dream', '2026-03-13 12:39:38'),
(194, 11, 'stock_out', 50, 10, 'Sale #6 - Nike Air Force 1 \'07', '2026-03-13 12:42:30'),
(195, 20, 'stock_out', 5, 10, 'Sale #7 - Nike ZoomX Invincible Run', '2026-03-13 13:02:19'),
(196, 40, 'stock_out', 10, 10, 'Sale #8 - Puma Speedcat OG', '2026-03-13 13:05:44'),
(197, 24, 'stock_out', 1, 14, 'Sale #9 - Adidas Superstar', '2026-03-13 13:13:57'),
(198, 29, 'stock_out', 5, 14, 'Sale #10 - Adidas Campus 00s', '2026-03-13 13:16:15'),
(199, 30, 'stock_out', 5, 14, 'Sale #10 - Adidas Forum Low', '2026-03-13 13:16:15'),
(200, 28, 'stock_out', 2, 14, 'Sale #10 - Adidas Gazelle', '2026-03-13 13:16:15'),
(201, 27, 'stock_out', 4, 14, 'Sale #10 - Adidas NMD_R1', '2026-03-13 13:16:16'),
(202, 22, 'stock_out', 10, 14, 'Sale #10 - Adidas Samba OG', '2026-03-13 13:16:16'),
(203, 35, 'stock_out', 1, 14, 'Sale #11 - Puma Cali Dream', '2026-03-13 13:22:00'),
(204, 33, 'stock_out', 1, 14, 'Sale #11 - Puma Suede Classic', '2026-03-13 13:22:00'),
(205, 40, 'stock_out', 1, 14, 'Sale #11 - Puma Speedcat OG', '2026-03-13 13:22:00'),
(206, 54, 'stock_in', 50, 14, '', '2026-03-13 13:23:15'),
(207, 32, 'stock_out', 3, 14, 'Sale #12 - Adidas Handball Spezial', '2026-03-13 13:25:25'),
(208, 16, 'stock_out', 1, 14, 'Sale #13 - Nike Pegasus 40', '2026-03-13 13:28:56'),
(209, 13, 'stock_out', 1, 14, 'Sale #14 - Nike Air Max 90', '2026-03-13 13:38:18'),
(210, 26, 'stock_out', 1, 14, 'Sale #15 - Adidas Ultraboost 1.0', '2026-03-13 13:40:55'),
(211, 29, 'stock_out', 1, 14, 'Sale #16 - Adidas Campus 00s', '2026-03-13 13:50:52'),
(212, 41, 'stock_out', 5, 10, 'Sale #17 - Puma Club II Era', '2026-03-13 15:54:59'),
(213, 38, 'stock_out', 5, 10, 'Sale #17 - Puma Mayze Classic', '2026-03-13 15:54:59'),
(214, 42, 'stock_out', 3, 10, 'Sale #17 - Puma Palermo', '2026-03-13 15:54:59'),
(215, 34, 'stock_out', 2, 10, 'Sale #17 - Puma RS-X', '2026-03-13 15:55:00'),
(216, 54, 'stock_out', 1, 10, 'Sale #17 - Puma Slipstream', '2026-03-13 15:55:00'),
(217, 36, 'stock_out', 12, 10, 'Sale #17 - Puma Smash V2', '2026-03-13 15:55:00'),
(218, 59, 'stock_in', 95, 10, '', '2026-03-13 16:14:00'),
(219, 70, 'stock_out', 1, 10, 'Sale #18 - Stussy World Tour Hoodie', '2026-03-13 16:15:27'),
(220, 67, 'stock_out', 1, 10, 'Sale #18 - Stone Island Cargo Pants', '2026-03-13 16:15:27'),
(221, 71, 'stock_out', 1, 10, 'Sale #18 - Stussy 8 Ball Tee', '2026-03-13 16:15:27'),
(222, 73, 'stock_out', 7, 10, 'Sale #19 - Stussy Cargo Shorts', '2026-03-13 16:43:53'),
(223, 31, 'stock_out', 1, 10, 'Sale #20 - Adidas Pureboost', '2026-03-15 06:22:32'),
(224, 75, 'stock_out', 1, 10, 'Sale #20 - Thrasher Flame Logo Tee', '2026-03-15 06:22:32'),
(225, 76, 'stock_out', 1, 10, 'Sale #20 - Thrasher Skateboard Deck', '2026-03-15 06:22:32'),
(226, 33, 'stock_out', 2, 10, 'Sale #20 - Puma Suede Classic', '2026-03-15 06:22:32'),
(227, 77, 'stock_in', 52, 10, '', '2026-03-15 06:23:36'),
(228, 76, 'stock_out', 3, 10, '', '2026-03-15 06:31:28'),
(229, 71, 'stock_out', 5, 10, 'Sale #33 - Stussy 8 Ball Tee', '2026-03-15 08:22:59'),
(230, 59, 'stock_out', 25, 10, 'Sale #33 - Supreme Box Logo Tee White', '2026-03-15 08:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `type` enum('warehouse','store','outlet') DEFAULT 'store',
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('low_stock','reorder','sale','transfer','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `user_id`, `is_read`, `priority`, `action_url`, `created_at`) VALUES
(1, 'low_stock', 'Low Stock Alert', 'Product \'Printer Ink\' (ID: 5) has 7 units remaining. Reorder level: 10', NULL, 1, 'high', 'products.php?highlight=5', '2026-03-13 07:51:56'),
(2, 'system', 'Product Added', 'admin: Added new product: Nike Air Force 1 \'07', NULL, 1, 'medium', NULL, '2026-03-13 08:34:00'),
(3, 'system', 'Product Added', 'admin: Added new product: Nike Air Force 1 \'07', NULL, 1, 'medium', NULL, '2026-03-13 08:37:33'),
(4, 'system', 'Product Added', 'admin: Added new product: Nike Dunk Low', NULL, 1, 'medium', NULL, '2026-03-13 08:37:54'),
(5, 'system', 'Product Added', 'admin: Added new product: Nike Air Max 90', NULL, 1, 'medium', NULL, '2026-03-13 08:38:17'),
(6, 'system', 'Product Added', 'admin: Added new product: Nike Air Max 270', NULL, 1, 'medium', NULL, '2026-03-13 08:38:33'),
(7, 'system', 'Product Added', 'admin: Added new product: Nike Dunk Low', NULL, 1, 'medium', NULL, '2026-03-13 08:39:14'),
(8, 'system', 'Product Added', 'admin: Added new product: Nike Pegasus 40', NULL, 1, 'medium', NULL, '2026-03-13 08:40:03'),
(9, 'system', 'Product Added', 'admin: Added new product: Nike Blazer Mid \'77 Vintage', NULL, 1, 'medium', NULL, '2026-03-13 08:40:17'),
(10, 'system', 'Product Added', 'admin: Added new product: Nike React Infinity Run Flyknit', NULL, 1, 'medium', NULL, '2026-03-13 08:40:32'),
(11, 'system', 'Product Added', 'admin: Added new product: Nike Metcon 9', NULL, 1, 'medium', NULL, '2026-03-13 08:40:49'),
(12, 'system', 'Product Added', 'admin: Added new product: Nike ZoomX Invincible Run', NULL, 1, 'medium', NULL, '2026-03-13 08:41:03'),
(13, 'system', 'Product Added', 'admin: Added new product: Nike Waffle One', NULL, 1, 'medium', NULL, '2026-03-13 08:41:16'),
(14, 'system', 'Product Added', 'admin: Added new product: Adidas Samba OG', NULL, 1, 'medium', NULL, '2026-03-13 09:03:08'),
(21, 'low_stock', 'Low Stock Alert', 'Product \'Puma Slipstream\' (ID: 54) has 4 units remaining. Reorder level: 5', NULL, 1, 'high', 'products.php?highlight=54', '2026-03-13 11:56:58'),
(31, 'system', 'Login', 'admin: Successful login from ::1', 10, 1, 'low', NULL, '2026-03-13 15:21:51'),
(32, 'low_stock', '???? Out of Stock', 'Product \'Stussy Cargo Shorts\' (ID: 73) has 0 units. Reorder level: 3', NULL, 1, 'critical', 'products.php?highlight=73', '2026-03-13 16:50:14'),
(33, 'low_stock', 'Out of Stock Alert', 'Product \'Stussy Cargo Shorts\' (ID: 73) has 0 units remaining. Reorder level: 3', NULL, 1, 'critical', 'products.php?highlight=73', '2026-03-13 18:56:07'),
(34, 'low_stock', 'Out of Stock Alert', 'Product \'Stussy Cargo Shorts\' (ID: 73) has 0 units remaining. Reorder level: 3', NULL, 1, 'critical', 'products.php?highlight=73', '2026-03-13 20:59:37'),
(35, 'system', 'Login', 'Len: Successful login from ::1', 10, 1, 'low', NULL, '2026-03-15 05:51:51'),
(36, 'low_stock', 'Out of Stock Alert', 'Product \'Stussy Cargo Shorts\' (ID: 73) has 0 units remaining. Reorder level: 3', NULL, 1, 'critical', 'products.php?highlight=73', '2026-03-15 05:51:51'),
(37, 'system', 'Login', 'admin: Successful login from ::1', 10, 1, 'low', NULL, '2026-03-15 06:12:41'),
(38, 'low_stock', 'Low Stock Alert', 'Product \'Thrasher Skateboard Deck\' (ID: 76) has 1 units remaining. Reorder level: 2', NULL, 1, 'high', 'products.php?highlight=76', '2026-03-15 06:42:54'),
(39, 'system', 'Login', 'Len: Successful login from ::1', 10, 1, 'low', NULL, '2026-03-15 07:01:50'),
(40, 'system', 'Login', 'admin: Successful login from ::1', 10, 1, 'low', NULL, '2026-03-15 07:15:22'),
(41, 'low_stock', 'Out of Stock Alert', 'Product \'Stussy Cargo Shorts\' (ID: 73) has 0 units remaining. Reorder level: 3', NULL, 0, 'critical', 'products.php?highlight=73', '2026-03-15 08:17:13'),
(42, 'system', 'Login', 'admin: Successful login from ::1', 10, 0, 'low', NULL, '2026-03-15 08:19:38'),
(43, 'low_stock', 'Low Stock Alert', 'Product \'Thrasher Skateboard Deck\' (ID: 76) has 1 units remaining. Reorder level: 2', NULL, 0, 'high', 'products.php?highlight=76', '2026-03-15 08:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `low_stock_alerts` tinyint(1) DEFAULT 1,
  `reorder_alerts` tinyint(1) DEFAULT 1,
  `sale_notifications` tinyint(1) DEFAULT 0,
  `system_notifications` tinyint(1) DEFAULT 1,
  `email_address` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `barcode` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `category_id`, `supplier_id`, `price`, `cost_price`, `stock_quantity`, `reorder_level`, `barcode`, `image`, `date_added`) VALUES
(11, 'Nike Air Force 1 \'07', 14, 3, 6500.00, 5200.00, 49, 10, '00195236160452', 'product_1773391844_fcaff972.jpg', '2026-03-13 08:37:33'),
(13, 'Nike Air Max 90', 14, 3, 7500.00, 6000.00, 101, 5, '00194272720415', '1773391376_Nike Air Max 90 Triple White.jpg', '2026-03-13 08:38:17'),
(14, 'Nike Air Max 270', 14, 3, 8500.00, 6800.00, 101, 10, '00191206334308', '1773391388_Nike Air Max 270 Big Kids\' Shoes.jpg', '2026-03-13 08:38:33'),
(15, 'Nike Dunk Low', 14, 3, 6200.00, 4960.00, 102, 10, '00194495760063', '1773391403_download.jpg', '2026-03-13 08:39:14'),
(16, 'Nike Pegasus 40', 14, 3, 7200.00, 5760.00, 101, 5, '00196609082138', '1773391504_pegasus.jpg', '2026-03-13 08:40:03'),
(17, 'Nike Blazer Mid \'77 Vintage', 14, 3, 5500.00, 4400.00, 100, 1, '00193849469502', '1773391513_blazer.jpg', '2026-03-13 08:40:17'),
(18, 'Nike React Infinity Run Flyknit', 14, 3, 8900.00, 7120.00, 102, 5, '00194498732514', '1773391521_react.jpg', '2026-03-13 08:40:32'),
(19, 'Nike Metcon 9', 14, 3, 7800.00, 6240.00, 101, 10, '00196607973069', '1773391528_metcon.jpg', '2026-03-13 08:40:49'),
(20, 'Nike ZoomX Invincible Run', 14, 3, 9900.00, 7920.00, 96, 10, '00196148071055', '1773391535_zoomx.jpg', '2026-03-13 08:41:03'),
(21, 'Nike Waffle One', 14, 3, 6000.00, 4800.00, 102, 10, '00194957362814', '1773391542_waffle.jpg', '2026-03-13 08:41:16'),
(22, 'Adidas Samba OG', 20, 11, 6800.00, 5440.00, 90, 10, '04060513991741', 'product_1773402804_d08222e7.jpg', '2026-03-13 09:03:08'),
(24, 'Adidas Superstar', 20, 11, 6000.00, 4800.00, 101, 10, '04065410112190', 'product_1773396027_0e24feae.jpg', '2026-03-13 09:32:39'),
(25, 'Adidas Stan Smith', 20, 11, 5800.00, 4640.00, 102, 10, '04060513923810', 'product_1773396126_f30cdc65.jpg', '2026-03-13 09:32:39'),
(26, 'Adidas Ultraboost 1.0', 20, 11, 9500.00, 7600.00, 101, 5, '04066044456692', 'product_1773396150_09f2ea1e.jpg', '2026-03-13 09:32:39'),
(27, 'Adidas NMD_R1', 20, 11, 8000.00, 6400.00, 98, 5, '04066044291064', 'product_1773396217_8f51bf2c.jpg', '2026-03-13 09:32:39'),
(28, 'Adidas Gazelle', 20, 11, 6200.00, 4960.00, 99, 10, '04060513845761', 'product_1773396288_39b002d6.jpg', '2026-03-13 09:32:39'),
(29, 'Adidas Campus 00s', 20, 11, 6500.00, 5200.00, 94, 10, '04066046881352', 'product_1773402596_6a44d51c.jpg', '2026-03-13 09:32:39'),
(30, 'Adidas Forum Low', 20, 11, 6900.00, 5520.00, 97, 10, '04066044121265', 'product_1773402654_1ec85acf.jpg', '2026-03-13 09:32:39'),
(31, 'Adidas Pureboost', 20, 11, 7500.00, 6000.00, 100, 5, '04066044478090', 'product_1773402699_37bf088f.jpg', '2026-03-13 09:32:39'),
(32, 'Adidas Handball Spezial', 20, 11, 6300.00, 5040.00, 99, 10, '04066047045005', 'product_1773402718_038ab220.jpg', '2026-03-13 09:32:39'),
(33, 'Puma Suede Classic', 21, 12, 5500.00, 4400.00, 99, 10, '04063697381254', 'product_1773402773_9277919a.jpg', '2026-03-13 09:32:39'),
(34, 'Puma RS-X', 21, 12, 7000.00, 5600.00, 100, 10, '04063697188495', 'product_1773402751_8b7f30e2.jpg', '2026-03-13 09:32:39'),
(35, 'Puma Cali Dream', 21, 12, 6800.00, 5440.00, 99, 10, '04063697503206', 'product_1773402825_1f2b1325.jpg', '2026-03-13 09:32:39'),
(36, 'Puma Smash V2', 21, 12, 3800.00, 3040.00, 90, 10, '04063697005137', 'product_1773402938_284aaccb.jpg', '2026-03-13 09:32:39'),
(37, 'Puma Future Rider', 21, 12, 5900.00, 4720.00, 102, 10, '04063697342590', 'product_1773402957_e556c709.jpg', '2026-03-13 09:32:39'),
(38, 'Puma Mayze Classic', 21, 12, 7600.00, 6080.00, 97, 5, '04063697651914', 'product_1773402990_af52a131.jpg', '2026-03-13 09:32:39'),
(40, 'Puma Speedcat OG', 21, 12, 7100.00, 5680.00, 91, 10, '04063697221412', 'product_1773403088_56f5bec4.jpg', '2026-03-13 09:32:39'),
(41, 'Puma Club II Era', 21, 12, 2800.00, 2240.00, 97, 15, '04063697700412', 'product_1773403110_a629dcf1.jpg', '2026-03-13 09:32:39'),
(42, 'Puma Palermo', 21, 12, 6200.00, 4960.00, 99, 10, '04063697740136', 'product_1773403203_aeb70905.jpg', '2026-03-13 09:32:39'),
(43, 'Under Armour HOVR Phantom 3', 22, 13, 8500.00, 6800.00, 102, 10, '01960395120345', 'product_1773403223_2912dc60.jpg', '2026-03-13 09:32:39'),
(44, 'Under Armour HOVR Infinite 4', 22, 13, 8200.00, 6560.00, 102, 10, '01960395120412', 'product_1773403447_0cfd4892.jpg', '2026-03-13 09:32:39'),
(45, 'Under Armour Charged Assert 10', 22, 13, 4500.00, 3600.00, 102, 10, '01960395120589', 'product_1773403465_25ff36e4.jpg', '2026-03-13 09:32:39'),
(46, 'Under Armour Charged Rogue 3', 22, 13, 5200.00, 4160.00, 102, 10, '01960395120656', 'product_1773409001_4a4a8771.jpg', '2026-03-13 09:32:39'),
(47, 'Under Armour HOVR Machina 3', 22, 13, 9200.00, 7360.00, 102, 5, '01960395120723', 'product_1773409148_2e5277c8.jpg', '2026-03-13 09:32:39'),
(48, 'Under Armour Flow Velociti Wind 2', 22, 13, 8700.00, 6960.00, 102, 5, '01960395120890', 'product_1773409166_46b2dfba.jpg', '2026-03-13 09:32:39'),
(49, 'Under Armour Tribase Reign 5', 22, 13, 7800.00, 6240.00, 102, 10, '01960395120967', 'product_1773409185_e0cab3cf.jpg', '2026-03-13 09:32:39'),
(50, 'Under Armour Spawn 5 Basketball', 22, 13, 6900.00, 5520.00, 102, 10, '01960395121044', 'product_1773409229_3edc30fe.jpg', '2026-03-13 09:32:39'),
(51, 'Under Armour Lockdown 6', 22, 13, 4800.00, 3840.00, 102, 10, '01960395121111', 'product_1773409209_2f7a0d7f.jpg', '2026-03-13 09:32:39'),
(52, 'Under Armour Project Rock 5', 22, 13, 9500.00, 7600.00, 102, 5, '01960395121288', 'product_1773395975_131a344d.jpg', '2026-03-13 09:32:39'),
(54, 'Puma Slipstream', 21, 12, 6900.00, 5520.00, 53, 5, '04063697398009', 'product_1773395997_47052def.jpg', '2026-03-13 09:40:57'),
(55, 'The North Face Nuptse Jacket', 29, 18, 18000.00, 14400.00, 5, 2, 'TNF001NUP', NULL, '2026-03-13 16:07:28'),
(56, 'The North Face Box Logo Hoodie', 29, 18, 8500.00, 6800.00, 9, 4, 'TNF002BOX', NULL, '2026-03-13 16:07:28'),
(57, 'The North Face Base Camp Duffel', 29, 18, 12000.00, 9600.00, 6, 2, 'TNF003DUF', NULL, '2026-03-13 16:07:29'),
(58, 'Supreme Box Logo Hoodie Red', 36, 19, 15000.00, 12000.00, 5, 2, 'SUP001RED', NULL, '2026-03-13 16:10:52'),
(59, 'Supreme Box Logo Tee White', 36, 19, 8000.00, 6400.00, 82, 5, 'SUP002WHT', NULL, '2026-03-13 16:10:52'),
(60, 'Supreme Backpack SS24', 36, 19, 12000.00, 9600.00, 3, 1, 'SUP003BAG', NULL, '2026-03-13 16:10:52'),
(61, 'Supreme Beanie Black', 36, 19, 4500.00, 3600.00, 8, 3, 'SUP004BLK', NULL, '2026-03-13 16:10:52'),
(62, 'Off-White Diagonal Hoodie', 37, 20, 18000.00, 14400.00, 4, 2, 'OW001DIAG', NULL, '2026-03-13 16:10:53'),
(63, 'Off-White Arrow Tee', 37, 20, 9500.00, 7600.00, 7, 3, 'OW002ARW', NULL, '2026-03-13 16:10:53'),
(64, 'Off-White Industrial Belt', 37, 20, 6500.00, 5200.00, 6, 2, 'OW003BLT', NULL, '2026-03-13 16:10:53'),
(65, 'Off-White Vulc Sneakers', 37, 20, 22000.00, 17600.00, 2, 1, 'OW004SNK', NULL, '2026-03-13 16:10:53'),
(66, 'Stone Island Compass Hoodie', 38, 21, 16500.00, 13200.00, 6, 2, 'SI001CMP', NULL, '2026-03-13 16:10:53'),
(67, 'Stone Island Cargo Pants', 38, 21, 14000.00, 11200.00, 7, 3, 'SI002CRG', NULL, '2026-03-13 16:10:53'),
(68, 'Stone Island Badge Tee', 38, 21, 7500.00, 6000.00, 10, 4, 'SI003BDG', NULL, '2026-03-13 16:10:53'),
(69, 'Stone Island Jacket Black', 38, 21, 25000.00, 20000.00, 3, 1, 'SI004JKT', NULL, '2026-03-13 16:10:53'),
(70, 'Stussy World Tour Hoodie', 39, 22, 8500.00, 6800.00, 8, 4, 'STU001WTR', NULL, '2026-03-13 16:10:53'),
(71, 'Stussy 8 Ball Tee', 39, 22, 4500.00, 3600.00, 9, 6, 'STU002BAL', NULL, '2026-03-13 16:10:53'),
(72, 'Stussy Bucket Hat', 39, 22, 3500.00, 2800.00, 12, 5, 'STU003HAT', NULL, '2026-03-13 16:10:53'),
(73, 'Stussy Cargo Shorts', 39, 22, 6000.00, 4800.00, 0, 3, 'STU004SHT', NULL, '2026-03-13 16:10:53'),
(74, 'Thrasher Magazine Hoodie', 40, 23, 6500.00, 5200.00, 11, 5, 'THR001MAG', NULL, '2026-03-13 16:10:53'),
(75, 'Thrasher Flame Logo Tee', 40, 23, 3800.00, 3040.00, 17, 8, 'THR002FLM', NULL, '2026-03-13 16:10:53'),
(76, 'Thrasher Skateboard Deck', 40, 23, 4200.00, 3360.00, 1, 2, 'THR003DCK', NULL, '2026-03-13 16:10:53'),
(77, 'Dr. Martens 1460 Black', 41, 24, 3800.00, 3200.00, 60, 3, 'DRM001460', NULL, '2026-03-13 16:10:54'),
(78, 'Dr. Martens 1461 Oxford', 41, 24, 11500.00, 9200.00, 6, 2, 'DRM002461', NULL, '2026-03-13 16:10:54'),
(79, 'Dr. Martens Jadon Platform', 41, 24, 15000.00, 12000.00, 4, 2, 'DRM003JAD', NULL, '2026-03-13 16:10:54'),
(80, 'Levi\'s 501 Original Jeans', 50, 25, 5500.00, 4400.00, 25, 10, 'LEV001501', NULL, '2026-03-13 16:10:54'),
(81, 'Levi\'s Trucker Jacket', 50, 25, 7500.00, 6000.00, 12, 5, 'LEV002TRK', NULL, '2026-03-13 16:10:54'),
(82, 'Levi\'s Logo Hoodie', 50, 25, 4800.00, 3840.00, 16, 6, 'LEV003HOD', NULL, '2026-03-13 16:10:54');

-- --------------------------------------------------------

--
-- Table structure for table `product_locations`
--

CREATE TABLE `product_locations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reserved_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `status` enum('draft','sent','received','cancelled') DEFAULT 'draft',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_delivery` date DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `status`, `order_date`, `expected_delivery`, `total_amount`, `notes`, `created_by`) VALUES
(4, 'AUTO-PO-20260313-4960', 3, 'draft', '2026-03-13 09:48:55', NULL, 793800.00, 'Auto-generated reorder for low stock items', 10),
(5, 'AUTO-PO-20260313-1024', 11, 'draft', '2026-03-13 09:48:55', NULL, 798000.00, 'Auto-generated reorder for low stock items', 10),
(6, 'AUTO-PO-20260313-4604', 12, 'draft', '2026-03-13 09:48:56', NULL, 752500.00, 'Auto-generated reorder for low stock items', 10),
(7, 'AUTO-PO-20260313-5233', 13, 'draft', '2026-03-13 09:48:56', NULL, 834400.00, 'Auto-generated reorder for low stock items', 10),
(8, 'AUTO-PO-20260313-7392', 12, 'draft', '2026-03-13 12:16:57', NULL, 48300.00, 'Auto-generated reorder for low stock items', 10),
(9, 'PO-20260313-5338', 11, 'draft', '2026-03-13 12:28:32', '2026-04-05', 350000.00, NULL, 10),
(10, 'AUTO-PO-20260313-9779', 12, 'draft', '2026-03-13 13:18:12', NULL, 48300.00, 'Auto-generated reorder for low stock items', 14);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `product_id`, `quantity`, `unit_cost`, `total_cost`, `received_quantity`) VALUES
(4, 4, 11, 20, 4550.00, 91000.00, 0),
(5, 4, 14, 20, 5950.00, 119000.00, 0),
(6, 4, 13, 10, 5250.00, 52500.00, 0),
(7, 4, 15, 20, 4340.00, 86800.00, 0),
(8, 4, 19, 20, 5460.00, 109200.00, 0),
(9, 4, 16, 10, 5040.00, 50400.00, 0),
(10, 4, 18, 10, 6230.00, 62300.00, 0),
(11, 4, 21, 20, 4200.00, 84000.00, 0),
(12, 4, 20, 20, 6930.00, 138600.00, 0),
(13, 5, 29, 20, 4550.00, 91000.00, 0),
(14, 5, 30, 20, 4830.00, 96600.00, 0),
(15, 5, 28, 20, 4340.00, 86800.00, 0),
(16, 5, 32, 20, 4410.00, 88200.00, 0),
(17, 5, 27, 10, 5600.00, 56000.00, 0),
(18, 5, 31, 10, 5250.00, 52500.00, 0),
(19, 5, 22, 20, 4760.00, 95200.00, 0),
(20, 5, 25, 20, 4060.00, 81200.00, 0),
(21, 5, 24, 20, 4200.00, 84000.00, 0),
(22, 5, 26, 10, 6650.00, 66500.00, 0),
(23, 6, 35, 20, 4760.00, 95200.00, 0),
(24, 6, 41, 30, 1960.00, 58800.00, 0),
(25, 6, 37, 20, 4130.00, 82600.00, 0),
(26, 6, 38, 10, 5320.00, 53200.00, 0),
(27, 6, 42, 20, 4340.00, 86800.00, 0),
(28, 6, 34, 20, 4900.00, 98000.00, 0),
(29, 6, 54, 10, 4830.00, 48300.00, 0),
(30, 6, 36, 20, 2660.00, 53200.00, 0),
(31, 6, 40, 20, 4970.00, 99400.00, 0),
(32, 6, 33, 20, 3850.00, 77000.00, 0),
(33, 7, 45, 20, 3150.00, 63000.00, 0),
(34, 7, 46, 20, 3640.00, 72800.00, 0),
(35, 7, 48, 10, 6090.00, 60900.00, 0),
(36, 7, 44, 20, 5740.00, 114800.00, 0),
(37, 7, 47, 10, 6440.00, 64400.00, 0),
(38, 7, 43, 20, 5950.00, 119000.00, 0),
(39, 7, 51, 20, 3360.00, 67200.00, 0),
(40, 7, 52, 10, 6650.00, 66500.00, 0),
(41, 7, 50, 20, 4830.00, 96600.00, 0),
(42, 7, 49, 20, 5460.00, 109200.00, 0),
(43, 8, 54, 10, 4830.00, 48300.00, 0),
(44, 9, 29, 100, 3500.00, 350000.00, 0),
(45, 10, 54, 10, 4830.00, 48300.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `ip_address`, `action`, `attempts`, `last_attempt`, `blocked_until`) VALUES
(49, '::1', 'product_action', 2, '2026-03-15 08:03:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reorder_rules`
--

CREATE TABLE `reorder_rules` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `auto_reorder` tinyint(1) DEFAULT 0,
  `reorder_quantity` int(11) NOT NULL,
  `lead_time_days` int(11) DEFAULT 7,
  `safety_stock` int(11) DEFAULT 0,
  `supplier_preference` int(11) DEFAULT NULL,
  `last_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `reservation_id` varchar(50) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `payment_method` enum('cash','card','digital_wallet','bank_transfer') DEFAULT 'cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `product_id`, `quantity`, `total_price`, `customer_name`, `customer_email`, `customer_phone`, `payment_method`, `created_at`) VALUES
(11, 0, 0, 19400.00, NULL, NULL, NULL, 'cash', '2026-03-13 13:22:00'),
(12, 0, 0, 18900.00, NULL, NULL, NULL, 'cash', '2026-03-13 13:25:25'),
(13, 0, 0, 7200.00, NULL, NULL, NULL, 'cash', '2026-03-13 13:28:56'),
(14, 0, 0, 7500.00, NULL, NULL, NULL, 'cash', '2026-03-13 13:38:18'),
(15, 0, 0, 9500.00, NULL, NULL, NULL, 'cash', '2026-03-13 13:40:55'),
(16, 0, 0, 6500.00, NULL, NULL, NULL, 'cash', '2026-03-13 13:50:52'),
(17, 0, 0, 137100.00, NULL, NULL, NULL, 'cash', '2026-03-13 15:54:59'),
(18, 0, 0, 27000.00, NULL, NULL, NULL, 'cash', '2026-03-13 16:15:27'),
(19, 0, 0, 42000.00, NULL, NULL, NULL, 'cash', '2026-03-13 16:43:53'),
(20, 0, 0, 26500.00, NULL, NULL, NULL, 'cash', '2026-03-15 06:22:32'),
(21, 1, 5, 250.00, NULL, NULL, NULL, 'cash', '2026-02-21 08:18:12'),
(22, 1, 3, 150.00, NULL, NULL, NULL, 'cash', '2026-02-26 08:18:12'),
(23, 1, 7, 350.00, NULL, NULL, NULL, 'cash', '2026-02-26 08:18:12'),
(24, 1, 2, 100.00, NULL, NULL, NULL, 'cash', '2026-02-13 08:18:12'),
(25, 2, 10, 500.00, NULL, NULL, NULL, 'cash', '2026-03-02 08:18:12'),
(26, 2, 8, 400.00, NULL, NULL, NULL, 'cash', '2026-03-03 08:18:12'),
(27, 2, 12, 600.00, NULL, NULL, NULL, 'cash', '2026-02-20 08:18:12'),
(28, 2, 6, 300.00, NULL, NULL, NULL, 'cash', '2026-02-14 08:18:12'),
(29, 3, 15, 750.00, NULL, NULL, NULL, 'cash', '2026-02-15 08:18:12'),
(30, 3, 20, 1000.00, NULL, NULL, NULL, 'cash', '2026-02-19 08:18:12'),
(31, 3, 18, 900.00, NULL, NULL, NULL, 'cash', '2026-02-26 08:18:12'),
(32, 3, 12, 600.00, NULL, NULL, NULL, 'cash', '2026-03-05 08:18:12'),
(33, 0, 0, 222500.00, NULL, NULL, NULL, 'cash', '2026-03-15 08:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `created_at`) VALUES
(19, 11, 35, 1, 6800.00, 6800.00, '2026-03-13 13:22:00'),
(20, 11, 33, 1, 5500.00, 5500.00, '2026-03-13 13:22:00'),
(21, 11, 40, 1, 7100.00, 7100.00, '2026-03-13 13:22:00'),
(22, 12, 32, 3, 6300.00, 18900.00, '2026-03-13 13:25:25'),
(23, 13, 16, 1, 7200.00, 7200.00, '2026-03-13 13:28:56'),
(24, 14, 13, 1, 7500.00, 7500.00, '2026-03-13 13:38:18'),
(25, 15, 26, 1, 9500.00, 9500.00, '2026-03-13 13:40:55'),
(26, 16, 29, 1, 6500.00, 6500.00, '2026-03-13 13:50:52'),
(27, 17, 41, 5, 2800.00, 14000.00, '2026-03-13 15:54:59'),
(28, 17, 38, 5, 7600.00, 38000.00, '2026-03-13 15:54:59'),
(29, 17, 42, 3, 6200.00, 18600.00, '2026-03-13 15:55:00'),
(30, 17, 34, 2, 7000.00, 14000.00, '2026-03-13 15:55:00'),
(31, 17, 54, 1, 6900.00, 6900.00, '2026-03-13 15:55:00'),
(32, 17, 36, 12, 3800.00, 45600.00, '2026-03-13 15:55:00'),
(33, 18, 70, 1, 8500.00, 8500.00, '2026-03-13 16:15:27'),
(34, 18, 67, 1, 14000.00, 14000.00, '2026-03-13 16:15:27'),
(35, 18, 71, 1, 4500.00, 4500.00, '2026-03-13 16:15:27'),
(36, 19, 73, 7, 6000.00, 42000.00, '2026-03-13 16:43:53'),
(37, 20, 31, 1, 7500.00, 7500.00, '2026-03-15 06:22:32'),
(38, 20, 75, 1, 3800.00, 3800.00, '2026-03-15 06:22:32'),
(39, 20, 76, 1, 4200.00, 4200.00, '2026-03-15 06:22:32'),
(40, 20, 33, 2, 5500.00, 11000.00, '2026-03-15 06:22:32'),
(41, 33, 71, 5, 4500.00, 22500.00, '2026-03-15 08:22:59'),
(42, 33, 59, 25, 8000.00, 200000.00, '2026-03-15 08:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `transfer_number` varchar(50) NOT NULL,
  `from_location_id` int(11) NOT NULL,
  `to_location_id` int(11) NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `transfer_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `quantity_sent` int(11) DEFAULT 0,
  `quantity_received` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `created_at`) VALUES
(3, 'Nike Manila', 'Sarah Miller', 'nike@supplier.com', '0917-222-3344', '1 Bowerman Dr, Beaverton, OR', '2026-03-13 08:54:36'),
(11, 'Adidas US', 'John Smith', 'orders@adidas.us', '+1-800-ADIDAS1', '5055 N Greeley Ave, Portland, OR 97217, USA', '2026-03-13 09:40:56'),
(12, 'Puma International', 'Maria Garcia', 'sales@puma.com', '+49-9132-81-0', 'PUMA Way 1, 91074 Herzogenaurach, Germany', '2026-03-13 09:40:56'),
(13, 'Under Armour Inc', 'David Johnson', 'wholesale@underarmour.com', '+1-888-727-6687', '1020 Hull St, Baltimore, MD 21230, USA', '2026-03-13 09:40:56'),
(14, 'Vans Supplier', 'Mark Johnson', 'vans@supplier.com', '0917-111-2233', '123 Market St, Los Angeles, CA', '2026-03-13 12:48:11'),
(15, 'Converse Distribution', 'Michael Brown', 'converse@supplier.com', '0917-555-6677', '1 Lovejoy Wharf, Boston, MA', '2026-03-13 12:49:02'),
(16, 'New Balance Supplier', 'Emily Carter', 'newbalance@supplier.com', '0917-666-7788', '100 Guest St, Boston, MA', '2026-03-13 12:49:34'),
(17, 'Reebok Supply Hub', 'Daniel White', 'reebok@supplier.com', '0917-888-9900', '25 Drydock Ave, Boston, MA', '2026-03-13 12:49:54'),
(18, 'The North Face', 'The North Face Contact', 'thenorthface@example.com', '+1234567890', NULL, '2026-03-13 16:07:28'),
(19, 'Supreme', 'Supreme Contact', 'supreme@example.com', '+1234567890', NULL, '2026-03-13 16:10:52'),
(20, 'Off-White', 'Off-White Contact', 'off-white@example.com', '+1234567890', NULL, '2026-03-13 16:10:52'),
(21, 'Stone Island', 'Stone Island Contact', 'stoneisland@example.com', '+1234567890', NULL, '2026-03-13 16:10:53'),
(22, 'Stussy', 'Stussy Contact', 'stussy@example.com', '+1234567890', NULL, '2026-03-13 16:10:53'),
(23, 'Thrasher', 'Thrasher Contact', 'thrasher@example.com', '+1234567890', NULL, '2026-03-13 16:10:53'),
(24, 'Dr. Martens', 'Dr. Martens Contact', 'dr.martens@example.com', '+1234567890', NULL, '2026-03-13 16:10:53'),
(25, 'Levi\'s', 'Levi\'s Contact', 'levi\'s@example.com', '+1234567890', NULL, '2026-03-13 16:10:54'),
(26, 'Arc\'teryx', 'Arc\'teryx Sales', 'arcteryx@supplier.com', '+63-2-797-4213', 'Arc\'teryx Warehouse', '2026-03-13 16:19:33'),
(27, 'ASICS', 'ASICS Sales', 'asics@supplier.com', '+63-2-120-3054', 'ASICS Warehouse', '2026-03-13 16:19:33'),
(28, 'Birkenstock', 'Birkenstock Sales', 'birkenstock@supplier.com', '+63-2-734-7396', 'Birkenstock Warehouse', '2026-03-13 16:19:33'),
(29, 'Calvin Klein', 'Calvin Klein Sales', 'calvinklein@supplier.com', '+63-2-868-7007', 'Calvin Klein Warehouse', '2026-03-13 16:19:33'),
(30, 'Clarks', 'Clarks Sales', 'clarks@supplier.com', '+63-2-609-6272', 'Clarks Warehouse', '2026-03-13 16:19:33'),
(31, 'Columbia', 'Columbia Sales', 'columbia@supplier.com', '+63-2-342-8707', 'Columbia Warehouse', '2026-03-13 16:19:34'),
(32, 'Converse', 'Converse Sales', 'converse@supplier.com', '+63-2-326-3393', 'Converse Warehouse', '2026-03-13 16:19:34'),
(33, 'Crocs', 'Crocs Sales', 'crocs@supplier.com', '+63-2-910-8756', 'Crocs Warehouse', '2026-03-13 16:19:34'),
(34, 'Diesel', 'Diesel Sales', 'diesel@supplier.com', '+63-2-401-5596', 'Diesel Warehouse', '2026-03-13 16:19:34'),
(35, 'Eastpak', 'Eastpak Sales', 'eastpak@supplier.com', '+63-2-448-2040', 'Eastpak Warehouse', '2026-03-13 16:19:34'),
(36, 'Fjällräven', 'Fjällräven Sales', 'fjällräven@supplier.com', '+63-2-468-9106', 'Fjällräven Warehouse', '2026-03-13 16:19:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','cashier','viewer') DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `email`, `first_name`, `last_name`, `phone`, `status`, `last_login`, `failed_login_attempts`, `locked_until`, `two_factor_enabled`, `two_factor_secret`, `created_by`) VALUES
(10, 'admin', '$2y$10$2QHt5.xz5RhIMU/DxkooqegCqgKhH.pb4ZbQYlGwN1A.Hknvh2oli', 'admin', '2026-03-13 09:21:48', 'admin@gmail.com', 'admin', 'admin', '', 'active', '2026-03-15 08:19:38', 0, NULL, 0, NULL, 1),
(14, 'Len', '$2y$10$DsuZYlC8tgoRIsV0NAYhXei1rkySTZ8ujyo72uAzg.7n0dHdAkRO.', 'cashier', '2026-03-13 13:12:39', 'saintlaurel@gmail.com', 'Len', 'Sailtz', '', 'active', '2026-03-15 07:01:50', 0, NULL, 0, NULL, 10),
(15, 'Miguel', '$2y$10$O7paYVqHfUWC1txv.iPUNO8L6qY3CePsKBQmsPGsvaxLHujJSQ/R2', 'manager', '2026-03-13 16:45:39', 'Miguelsantiago@gmail.com', 'Miguel', 'Santiago', '12345678001', 'active', NULL, 0, NULL, 0, NULL, 10);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_log`
--

INSERT INTO `user_activity_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(123, 10, 'multi_sale', 'Completed sale of 4 items to Juan. Total: ₱37,200.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:34:24'),
(124, 10, 'multi_sale', 'Completed sale of 3 items to Helen. Total: ₱27,300.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:36:19'),
(125, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:36:25'),
(126, 10, 'multi_sale', 'Completed sale of 1 items to Peter. Total: ₱6,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:38:39'),
(127, 10, 'multi_sale', 'Completed sale of 1 items to Saint. Total: ₱13,600.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:39:38'),
(128, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:41:29'),
(129, 10, 'multi_sale', 'Completed sale of 1 items to Saint Laurel. Total: ₱325,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:42:30'),
(130, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:46:33'),
(131, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:51:38'),
(132, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 12:56:42'),
(133, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:01:43'),
(134, 10, 'multi_sale', 'Completed sale of 1 items to Saint Laurel. Total: ₱49,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:02:19'),
(135, 10, 'multi_sale', 'Completed sale of 1 items to Peter. Total: ₱71,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:05:44'),
(136, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:06:45'),
(137, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:11:49'),
(138, 10, 'create_user', 'Created user: Migs (manager)', '::1', NULL, '2026-03-13 13:12:19'),
(139, 10, 'create_user', 'Created user: Len (cashier)', '::1', NULL, '2026-03-13 13:12:39'),
(140, 10, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:12:41'),
(141, 14, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:12:46'),
(142, 14, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:12:46'),
(143, 14, 'multi_sale', 'Completed sale of 1 items to Nathaniel. Total: ₱6,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:13:57'),
(144, 14, 'multi_sale', 'Completed sale of 5 items to Nathaniel. Total: ₱179,400.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:16:16'),
(145, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:17:47'),
(146, 14, 'multi_sale', 'Completed sale of 3 items to Nathaniel. Total: ₱19,400.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:22:00'),
(147, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:22:50'),
(148, 14, 'stock_in', 'Added 50 units to product ID: 54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:23:15'),
(149, 14, 'multi_sale', 'Completed sale of 1 items to Nathaniel. Total: ₱18,900.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:25:25'),
(150, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:27:51'),
(151, 14, 'multi_sale', 'Completed sale of 1 items to Nathaniel. Total: ₱7,200.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:28:56'),
(152, 14, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:31:12'),
(153, 10, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:31:23'),
(154, 10, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:31:23'),
(155, 10, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:33:19'),
(156, 14, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:33:52'),
(157, 14, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:33:52'),
(158, 14, 'product_updated', 'Updated product: Under Armour Charged Rogue 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:36:41'),
(159, 14, 'multi_sale', 'Completed sale of 1 items to Nathaniel. Total: ₱7,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:38:18'),
(160, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:38:55'),
(161, 14, 'product_updated', 'Updated product: Under Armour HOVR Machina 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:39:08'),
(162, 14, 'product_updated', 'Updated product: Under Armour Flow Velociti Wind 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:39:26'),
(163, 14, 'product_updated', 'Updated product: Under Armour Tribase Reign 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:39:45'),
(164, 14, 'product_updated', 'Updated product: Under Armour Lockdown 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:40:09'),
(165, 14, 'product_updated', 'Updated product: Under Armour Spawn 5 Basketball', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:40:29'),
(166, 14, 'multi_sale', 'Completed sale of 1 items to Nathaniel. Total: ₱9,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:40:55'),
(167, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:43:58'),
(168, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:49:08'),
(169, 14, 'multi_sale', 'Completed sale of 1 items to . Total: ₱6,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:50:52'),
(170, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:54:09'),
(171, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 13:59:12'),
(172, 14, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:00:25'),
(173, 10, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:00:53'),
(174, 10, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:00:54'),
(175, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:05:59'),
(176, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:11:09'),
(177, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:16:15'),
(178, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:21:17'),
(179, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:26:26'),
(180, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:31:28'),
(181, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:36:31'),
(182, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:41:37'),
(183, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:46:44'),
(184, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:51:53'),
(185, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 14:56:59'),
(186, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:02:09'),
(187, 10, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:06:24'),
(188, 14, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:06:34'),
(189, 14, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:06:34'),
(190, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:11:39'),
(191, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:16:58'),
(192, 14, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:21:43'),
(193, 10, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:21:51'),
(194, 10, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:21:51'),
(195, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:26:56'),
(196, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:32:06'),
(197, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:37:07'),
(198, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:42:09'),
(199, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:47:13'),
(200, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:52:21'),
(201, 10, 'multi_sale', 'Completed sale of 6 items to Nathaniel. Total: ₱137,100.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:55:00'),
(202, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 15:57:24'),
(203, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:02:30'),
(204, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:07:41'),
(205, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:12:45'),
(206, 10, 'stock_in', 'Added 95 units to product ID: 59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:14:00'),
(207, 10, 'multi_sale', 'Completed sale of 3 items to Nathaniel. Total: ₱27,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:15:27'),
(208, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:17:47'),
(209, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:22:53'),
(210, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:27:59'),
(211, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:33:04'),
(212, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:38:08'),
(213, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:43:11'),
(214, 10, 'multi_sale', 'Completed sale of 1 items to Nathaniel. Total: ₱42,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:43:53'),
(215, 10, 'create_user', 'Created user: Miguel (manager)', '::1', NULL, '2026-03-13 16:45:39'),
(216, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:48:19'),
(217, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:53:26'),
(218, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 16:58:36'),
(219, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:03:46'),
(220, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:08:56'),
(221, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:13:57'),
(222, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:18:59'),
(223, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:24:02'),
(224, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:29:11'),
(225, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:34:21'),
(226, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:39:25'),
(227, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:44:26'),
(228, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:49:30'),
(229, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:54:31'),
(230, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 17:59:36'),
(231, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:04:46'),
(232, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:09:56'),
(233, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:14:59'),
(234, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:20:06'),
(235, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:25:16'),
(236, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:30:27'),
(237, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:35:36'),
(238, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:40:46'),
(239, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:45:56'),
(240, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:50:59'),
(241, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:56:06'),
(242, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:01:16'),
(243, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:06:27'),
(244, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:11:36'),
(245, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:16:47'),
(246, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:21:57'),
(247, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:26:59'),
(248, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:32:07'),
(249, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:37:16'),
(250, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:42:27'),
(251, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:47:36'),
(252, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:52:47'),
(253, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 19:57:56'),
(254, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:02:59'),
(255, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:08:07'),
(256, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:13:16'),
(257, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:18:26'),
(258, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:23:36'),
(259, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:28:46'),
(260, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:33:56'),
(261, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:38:59'),
(262, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:44:06'),
(263, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:49:16'),
(264, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:54:27'),
(265, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 20:59:36'),
(266, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 21:04:46'),
(267, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 21:09:56'),
(268, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 21:14:59'),
(269, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 21:20:06'),
(270, 14, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 05:51:51'),
(271, 14, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 05:51:51'),
(272, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 05:56:56'),
(273, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:02:06'),
(274, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:07:12'),
(275, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:12:14'),
(276, 14, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:12:34'),
(277, 10, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:12:41'),
(278, 10, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:12:41'),
(279, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:17:42'),
(280, 10, 'multi_sale', 'Completed sale of 4 items to Nathaniel. Total: ₱26,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:22:32'),
(281, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:22:48'),
(282, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:27:49'),
(283, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:32:50'),
(284, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:37:51'),
(285, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:42:54'),
(286, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:47:56'),
(287, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:52:59'),
(288, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 06:58:01'),
(289, 10, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:01:39'),
(290, 14, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:01:50'),
(291, 14, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:01:50'),
(292, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:06:51'),
(293, 14, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:11:59'),
(294, 14, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:15:13'),
(295, 10, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:15:22'),
(296, 10, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:15:22'),
(297, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:20:28'),
(298, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:25:30'),
(299, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:30:35'),
(300, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:35:45'),
(301, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:41:12'),
(302, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:46:13'),
(303, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:51:14'),
(304, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 07:56:21'),
(305, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 08:01:24'),
(306, 10, 'product_updated', 'Updated product: Dr. Martens 1460 Black', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 08:03:12'),
(307, 10, 'product_updated', 'Updated product: Dr. Martens 1460 Black', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 08:03:29'),
(308, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 08:06:28'),
(309, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 08:11:46'),
(310, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 08:17:12'),
(311, 10, 'login', 'Successful login from ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:19:38'),
(312, 10, 'view_page', 'Viewed dashboard page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:19:38'),
(313, 10, 'multi_sale', 'Completed sale of 2 items to . Total: ₱222,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:22:59'),
(314, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:24:42'),
(315, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:29:44'),
(316, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:34:47'),
(317, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:40:11'),
(318, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:46:12'),
(319, 10, 'view_page', 'Viewed notifications page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 08:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission`, `granted_by`, `granted_at`) VALUES
(24, 14, 'manage_inventory', 10, '2026-03-13 13:12:39'),
(25, 14, 'view_products', 10, '2026-03-13 13:12:39'),
(26, 15, 'manage_products', 10, '2026-03-13 16:45:39'),
(27, 15, 'manage_inventory', 10, '2026-03-13 16:45:39'),
(28, 15, 'view_reports', 10, '2026-03-13 16:45:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `forecast_data`
--
ALTER TABLE `forecast_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product` (`product_id`);

--
-- Indexes for table `forecast_data_advanced`
--
ALTER TABLE `forecast_data_advanced`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_settings` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `product_locations`
--
ALTER TABLE `product_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_location` (`product_id`,`location_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action` (`ip_address`,`action`),
  ADD KEY `idx_blocked_until` (`blocked_until`);

--
-- Indexes for table `reorder_rules`
--
ALTER TABLE `reorder_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_preference` (`supplier_preference`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservation_id` (`reservation_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_customer` (`customer_name`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale` (`sale_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_number` (`transfer_number`),
  ADD KEY `from_location_id` (`from_location_id`),
  ADD KEY `to_location_id` (`to_location_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`created_at`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission`),
  ADD KEY `fk_user_permissions_granted_by` (`granted_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forecast_data`
--
ALTER TABLE `forecast_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2552;

--
-- AUTO_INCREMENT for table `forecast_data_advanced`
--
ALTER TABLE `forecast_data_advanced`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `product_locations`
--
ALTER TABLE `product_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `reorder_rules`
--
ALTER TABLE `reorder_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=320;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD CONSTRAINT `chatbot_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `forecast_data`
--
ALTER TABLE `forecast_data`
  ADD CONSTRAINT `forecast_data_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forecast_data_advanced`
--
ALTER TABLE `forecast_data_advanced`
  ADD CONSTRAINT `forecast_data_advanced_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_locations`
--
ALTER TABLE `product_locations`
  ADD CONSTRAINT `product_locations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_locations_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `reorder_rules`
--
ALTER TABLE `reorder_rules`
  ADD CONSTRAINT `reorder_rules_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reorder_rules_ibfk_2` FOREIGN KEY (`supplier_preference`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD CONSTRAINT `fk_user_activity_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_user_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
