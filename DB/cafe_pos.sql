-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2026 at 10:49 AM
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
-- Database: `cafe_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(2, 'Trà'),
(5, 'Khác'),
(6, 'Croissant / Cookie'),
(7, 'Phê'),
(8, 'Ăn nhẹ'),
(9, 'Nước ép'),
(10, 'Choco'),
(11, 'Kombucha'),
(12, 'Thêm'),
(13, 'Matcha'),
(14, 'Sữa chua');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `table_id` int(11) DEFAULT NULL,
  `status` enum('open','paid') DEFAULT 'paid',
  `total_amount` decimal(10,0) NOT NULL,
  `cash_amount` decimal(10,2) DEFAULT 0.00,
  `bank_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(20) DEFAULT 'cash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `table_id`, `status`, `total_amount`, `cash_amount`, `bank_amount`, `created_at`, `payment_method`) VALUES
(18, NULL, 'paid', 100000, 40000.00, 60000.00, '2026-06-13 13:30:31', 'mixed'),
(19, NULL, 'paid', 130000, 100000.00, 30000.00, '2026-06-13 13:40:40', 'mixed'),
(20, NULL, 'paid', 100000, 30000.00, 70000.00, '2026-06-13 13:49:13', 'mixed'),
(21, NULL, 'paid', 70000, 70000.00, 0.00, '2026-06-13 13:49:36', 'cash'),
(22, NULL, 'paid', 105000, 105000.00, 0.00, '2026-06-13 14:05:36', 'cash'),
(23, NULL, 'paid', 105000, 105000.00, 0.00, '2026-06-13 14:06:22', 'cash'),
(24, NULL, 'paid', 100000, 100000.00, 0.00, '2026-06-13 14:09:20', 'cash'),
(25, 1, 'paid', 140000, 140000.00, 0.00, '2026-06-14 01:05:35', 'cash'),
(26, 2, 'paid', 30000, 0.00, 30000.00, '2026-06-14 01:09:09', 'bank'),
(27, 3, 'paid', 40000, 40000.00, 0.00, '2026-06-14 01:09:11', 'cash'),
(28, 1, 'paid', 45000, 30000.00, 15000.00, '2026-06-14 07:13:16', 'mixed'),
(29, 1, 'paid', 100000, 0.00, 100000.00, '2026-06-14 07:23:01', 'bank'),
(30, 2, 'paid', 70000, 70000.00, 0.00, '2026-06-14 07:23:09', 'cash'),
(31, 3, 'paid', 30000, 30000.00, 0.00, '2026-06-14 07:25:08', 'cash'),
(32, 4, 'paid', 40000, 0.00, 40000.00, '2026-06-14 07:25:16', 'bank'),
(33, 5, 'paid', 30000, 30000.00, 0.00, '2026-06-14 07:48:26', 'cash'),
(34, 1, 'paid', 85000, 50000.00, 35000.00, '2026-06-14 07:55:13', 'mixed'),
(35, 2, 'paid', 100000, 100000.00, 0.00, '2026-06-14 07:55:40', 'cash'),
(36, 3, 'paid', 100000, 100000.00, 0.00, '2026-06-14 07:55:44', 'cash'),
(37, 2, 'paid', 70000, 70000.00, 0.00, '2026-06-14 08:20:14', 'cash'),
(38, 1, 'paid', 40000, 40000.00, 0.00, '2026-06-14 08:21:11', 'cash'),
(39, 2, 'paid', 70000, 70000.00, 0.00, '2026-06-14 08:30:28', 'cash'),
(40, 3, 'paid', 40000, 40000.00, 0.00, '2026-06-14 08:31:06', 'cash'),
(41, 1, 'paid', 40000, 40000.00, 0.00, '2026-06-14 08:34:38', 'cash'),
(42, 1, 'paid', 70000, 70000.00, 0.00, '2026-06-14 08:43:37', 'cash'),
(43, 1, 'paid', 100000, 100000.00, 0.00, '2026-06-14 08:44:17', 'cash');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `qty`, `unit_price`) VALUES
(33, 18, 6, 1, 40000),
(34, 18, 36, 1, 60000),
(35, 19, 6, 1, 40000),
(36, 19, 10, 1, 45000),
(37, 19, 23, 1, 45000),
(38, 20, 6, 1, 40000),
(39, 20, 29, 1, 30000),
(40, 20, 35, 1, 30000),
(41, 21, 6, 1, 40000),
(42, 21, 35, 1, 30000),
(43, 22, 10, 1, 45000),
(44, 22, 29, 1, 30000),
(45, 22, 35, 1, 30000),
(46, 23, 10, 1, 45000),
(47, 23, 29, 1, 30000),
(48, 23, 35, 1, 30000),
(49, 24, 6, 1, 40000),
(50, 24, 35, 2, 30000),
(51, 25, 6, 2, 40000),
(52, 25, 36, 1, 60000),
(53, 26, 35, 1, 30000),
(54, 28, 23, 1, 45000),
(55, 27, 9, 1, 40000),
(56, 29, 35, 1, 30000),
(57, 29, 6, 1, 40000),
(58, 29, 29, 1, 30000),
(59, 31, 35, 1, 30000),
(60, 30, 35, 1, 30000),
(61, 30, 6, 1, 40000),
(62, 35, 36, 1, 60000),
(63, 35, 6, 1, 40000),
(64, 36, 6, 1, 40000),
(65, 36, 36, 1, 60000),
(66, 32, 6, 1, 40000),
(67, 34, 9, 1, 40000),
(68, 34, 23, 1, 45000),
(69, 33, 29, 1, 30000),
(70, 37, 6, 1, 40000),
(71, 37, 29, 1, 30000),
(78, 38, 6, 1, 40000),
(79, 41, 6, 1, 40000),
(80, 39, 35, 1, 30000),
(81, 39, 6, 1, 40000),
(82, 40, 6, 1, 40000),
(83, 42, 6, 1, 40000),
(84, 42, 35, 1, 30000),
(85, 43, 36, 1, 60000),
(86, 43, 6, 1, 40000);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,0) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT 5,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `status`, `created_at`, `category_id`, `sort_order`) VALUES
(1, 'Croissant Nho', 40000, 1, '2026-06-13 03:03:58', 6, 0),
(2, 'Mocha', 49000, 1, '2026-06-13 03:03:58', 7, 0),
(3, 'Latte', 40000, 1, '2026-06-13 03:03:58', 7, 0),
(4, 'Caramel', 40000, 1, '2026-06-13 03:03:58', 7, 0),
(5, 'Espresso Cam', 40000, 1, '2026-06-13 03:03:58', 7, 0),
(6, 'Khoai tây chiên', 40000, 1, '2026-06-13 03:03:58', 8, 0),
(7, 'Chanh dây', 35000, 1, '2026-06-13 03:03:58', 9, 0),
(8, 'Espresso đá', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(9, 'Choco', 40000, 1, '2026-06-13 03:03:58', 10, 0),
(10, 'Choco sữa dừa', 45000, 1, '2026-06-13 03:03:58', 10, 0),
(11, 'Kombucha atiso đỏ', 49000, 1, '2026-06-13 03:03:58', 11, 0),
(12, 'Sữa tươi 50ml', 5000, 1, '2026-06-13 03:03:58', 12, 0),
(13, 'Nâu nóng', 30000, 1, '2026-06-13 03:03:58', 7, 0),
(14, 'Cacao đá', 40000, 1, '2026-06-13 03:03:58', 7, 0),
(15, 'Cacao nóng', 40000, 1, '2026-06-13 03:03:58', 7, 0),
(16, 'Cafe đen đá', 30000, 1, '2026-06-13 03:03:58', 7, 0),
(17, 'Americano nóng', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(18, 'Bạc xỉu đá', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(19, 'Cafe đen nóng', 30000, 1, '2026-06-13 03:03:58', 7, 0),
(20, 'Nâu đá', 30000, 1, '2026-06-13 03:03:58', 7, 0),
(21, 'Cookie matcha', 10000, 1, '2026-06-13 03:03:58', 6, 0),
(22, 'Cookie choco', 10000, 1, '2026-06-13 03:03:58', 6, 0),
(23, 'Choco muối', 45000, 1, '2026-06-13 03:03:58', 10, 0),
(24, 'Matcha sữa kem', 49000, 1, '2026-06-13 03:03:58', 13, 0),
(25, 'Thư giãn', 45000, 1, '2026-06-13 03:03:58', 2, 0),
(26, 'Trà ổi chanh dây', 39000, 1, '2026-06-13 03:03:58', 2, 0),
(27, 'Sữa ô-long rang - Nóng', 39000, 1, '2026-06-13 03:03:58', 2, 0),
(28, 'Sữa ô-long rang - Đá', 39000, 1, '2026-06-13 03:03:58', 2, 0),
(29, 'Sandwich trứng', 30000, 1, '2026-06-13 03:03:58', 8, 0),
(30, 'Croissant choco', 40000, 1, '2026-06-13 03:03:58', 6, 0),
(32, 'Kombucha Nhãn', 49000, 1, '2026-06-13 03:03:58', 11, 0),
(33, 'An thần', 45000, 1, '2026-06-13 03:03:58', 2, 0),
(34, 'Matcha latte', 40000, 1, '2026-06-13 03:03:58', 13, 0),
(35, 'Mì tôm trứng nước', 30000, 1, '2026-06-13 03:03:58', 8, 0),
(36, 'COMBO viên chiên', 60000, 1, '2026-06-13 03:03:58', 8, 0),
(37, 'Kem Muối', 5000, 1, '2026-06-13 03:03:58', 12, 0),
(38, 'Kem Trứng', 5000, 1, '2026-06-13 03:03:58', 12, 0),
(39, 'Matcha Sữa Dừa', 45000, 1, '2026-06-13 03:03:58', 13, 0),
(40, 'Matcha yến mạch', 40000, 1, '2026-06-13 03:03:58', 13, 0),
(41, 'Muối', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(42, 'Sữa Chua Chanh Dây', 40000, 1, '2026-06-13 03:03:58', 14, 0),
(43, 'Sữa chua Việt Quất', 40000, 1, '2026-06-13 03:03:58', 14, 0),
(44, 'Đào Quận Cam', 39000, 1, '2026-06-13 03:03:58', 2, 0),
(45, 'Croissant truyền thống', 35000, 1, '2026-06-13 03:03:58', 6, 0),
(46, 'Croissant dừa', 40000, 1, '2026-06-13 03:03:58', 6, 0),
(47, '1 shot cà phê Ara', 15000, 1, '2026-06-13 03:03:58', 12, 0),
(48, '1 shot cà phê Ro', 15000, 1, '2026-06-13 03:03:58', 12, 0),
(49, 'Coldbrew Chanh Sả', 40000, 1, '2026-06-13 03:03:58', 7, 0),
(50, 'ColdBrew Truyền Thống', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(51, 'Mì gói thêm', 5000, 1, '2026-06-13 03:03:58', 12, 0),
(52, 'Americano đá', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(53, 'Espresso nóng', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(54, 'Sữa dừa', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(55, 'Trứng', 5000, 1, '2026-06-13 03:03:58', 12, 0),
(56, 'Bạc xỉu nóng', 35000, 1, '2026-06-13 03:03:58', 7, 0),
(57, 'Ô-long Kem Trứng', 39000, 1, '2026-06-13 03:03:58', 2, 0),
(58, 'Ấm áp', 39000, 1, '2026-06-13 03:03:58', 2, 0),
(59, 'Ba Chỉ', 39000, 1, '2026-06-13 03:03:58', 7, 0),
(60, 'Cam', 35000, 1, '2026-06-13 03:03:58', 9, 0),
(61, 'Sữa chua dâu tằm', 40000, 1, '2026-06-13 03:03:58', 14, 0),
(62, 'Sữa chua hạt', 40000, 1, '2026-06-13 03:03:58', 14, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `status` enum('empty','occupied') DEFAULT 'empty'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`id`, `table_name`, `status`) VALUES
(1, 'Bàn 1', 'empty'),
(2, 'Bàn 2', 'empty'),
(3, 'Bàn 3', 'empty'),
(4, 'Bàn 4', 'empty'),
(5, 'Bàn 5', 'empty'),
(6, 'Bàn 6', 'empty'),
(7, 'Bàn 7', 'empty'),
(8, 'Bàn 8', 'empty'),
(9, 'Bàn 9', 'empty'),
(10, 'Bàn 10', 'empty');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$dpHkqLDN38T5Pt06zok82eAutmFekbo4IFs/FsmHdegCg4IzZEvRy', '2026-06-13 11:46:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_table` (`table_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Extra columns used by current POS code
--
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `customer_name` varchar(100) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `discount_type` varchar(20) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `discount_value` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `discount_amount` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `paid_at` datetime DEFAULT NULL;

UPDATE `orders`
SET `paid_at` = `created_at`
WHERE `status` = 'paid'
AND `paid_at` IS NULL;

CREATE TABLE IF NOT EXISTS `cash_opening` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `opening_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `opening_date` (`opening_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cash_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expense_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cash_deposit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deposit_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `deposit_date` (`deposit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashbook_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cash_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `transfer_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposits` decimal(10,2) NOT NULL DEFAULT 0.00,
  `closing_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_date` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Purchase portal (run migration_purchasing_portal.sql on an existing database).
CREATE TABLE IF NOT EXISTS `purchase_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_date` date NOT NULL,
  `supplier` varchar(150) NOT NULL DEFAULT '',
  `item_name` varchar(150) NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 1.000,
  `unit` varchar(30) NOT NULL DEFAULT '',
  `total_amount` decimal(12,2) NOT NULL,
  `note` varchar(1000) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_purchase_entries_date` (`purchase_date`),
  KEY `idx_purchase_entries_created_by_date` (`created_by`,`purchase_date`),
  CONSTRAINT `fk_purchase_entries_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `purchase_advances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `advance_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(1000) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_purchase_advances_date` (`advance_date`),
  KEY `idx_purchase_advances_created_by_date` (`created_by`,`advance_date`),
  CONSTRAINT `fk_purchase_advances_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
