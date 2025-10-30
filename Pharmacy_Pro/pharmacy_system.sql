-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 25, 2025 at 10:24 AM
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
-- Database: `pharmacy_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Pain Relief', 'Medicines for pain relief', '2025-09-15 14:50:17', '2025-09-15 14:50:17'),
(2, 'Antibiotics', 'Antibiotic medicines', '2025-09-15 14:50:17', '2025-09-15 14:50:17'),
(3, 'Vitamins & Supplements', 'Health supplements', '2025-09-15 14:50:17', '2025-09-15 14:50:17'),
(4, 'Skin Care', 'Dermatology medicines', '2025-09-15 14:50:17', '2025-09-15 14:50:17'),
(5, 'General Health', 'General over-the-counter medicines', '2025-09-15 14:50:17', '2025-09-15 14:50:17');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `item_discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','other') DEFAULT 'cash',
  `order_type` enum('takeaway','delivery') DEFAULT 'takeaway',
  `delivery_charge` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `cashier_id`, `subtotal`, `discount_amount`, `item_discount`, `tax_amount`, `total_amount`, `payment_method`, `order_type`, `delivery_charge`, `status`, `refund_amount`, `order_date`) VALUES
(1, 'ORD-20250915-7572', 1, 15.00, 0.00, 0.00, 1.50, 16.50, 'card', 'takeaway', 0.00, 'completed', 0.00, '2025-09-15 15:02:09'),
(2, 'ORD-20250920-0744', 1, 50.00, 0.00, 0.00, 5.00, 55.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-09-20 15:24:39'),
(3, 'ORD-20251009-2952', 1, 13.50, 0.00, 0.00, 1.35, 14.85, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-09 11:55:11'),
(4, 'ORD-20251009-6310', 1, 50.00, 0.00, 0.00, 5.00, 55.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-09 14:45:44'),
(5, 'ORD-20251015-3432', 1, 10.00, 0.00, 0.00, 1.00, 11.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-15 11:23:24'),
(6, 'ORD-20251015-8692', 1, 50.00, 0.00, 0.00, 5.00, 55.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-15 16:45:44'),
(7, 'ORD-20251016-7255', 1, 450.00, 0.00, 0.00, 45.00, 495.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-16 10:05:24'),
(8, 'ORD-20251016-2780', 1, 13.60, 0.00, 0.00, 1.36, 14.96, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-16 12:23:39'),
(9, 'ORD-20251016-6104', 1, 15.00, 0.00, 0.00, 1.50, 16.50, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-16 12:53:24'),
(10, 'ORD-20251016-9986', 1, 10.00, 0.00, 0.00, 1.00, 11.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-16 13:19:44'),
(11, 'ORD-20251016-4973', 1, 20.00, 0.00, 0.00, 2.00, 22.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-16 13:34:13'),
(12, 'ORD-20251017-1599', 1, 10.00, 0.00, 0.00, 1.00, 11.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-17 08:23:43'),
(13, 'ORD-20251017-7727', 1, 10.00, 0.50, 0.00, 0.95, 10.45, 'cash', 'takeaway', 0.00, 'returned', 10.45, '2025-10-17 08:24:28'),
(14, 'ORD-20251017-5200', 1, 99.99, 5.00, 0.00, 9.50, 104.49, 'cash', 'takeaway', 0.00, 'cancelled', 0.00, '2025-10-17 10:56:38'),
(15, 'ORD-20251017-6425', 1, 5.01, 0.25, 0.00, 0.48, 5.24, 'cash', 'takeaway', 0.00, 'returned', 5.24, '2025-10-17 11:47:29'),
(16, 'ORD-20251018-8427', 1, 135.00, 6.75, 0.00, 12.83, 141.08, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-18 13:26:32'),
(17, 'ORD-20251019-7847', 1, 225.00, 11.25, 0.00, 21.38, 235.13, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-19 08:18:27'),
(18, 'ORD-20251022-5526', 1, 50.00, 2.50, 0.00, 4.75, 52.25, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-22 16:44:56'),
(19, 'ORD-20251022-3178', 1, 15.00, 0.75, 0.00, 1.43, 15.68, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-22 16:59:23'),
(20, 'ORD-20251022-8598', 1, 5.00, 0.25, 0.00, 0.48, 5.23, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-22 17:08:26'),
(21, 'ORD-20251023-8088', 1, 40.00, 2.00, 0.00, 3.80, 41.80, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 07:46:28'),
(22, 'ORD-20251023-9956', 1, 1000.00, 50.00, 0.00, 95.00, 1045.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 07:51:42'),
(23, 'ORD-20251023-6364', 1, 130.00, 6.50, 0.00, 12.35, 135.85, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 08:07:07'),
(24, 'ORD-20251023-1747', 1, 60.00, 3.00, 0.00, 5.70, 62.70, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 08:24:36'),
(25, 'ORD-20251023-8704', 1, 180.00, 9.00, 0.00, 17.10, 188.10, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 13:17:56'),
(26, 'ORD-20251023-5237', 1, 120.00, 6.00, 0.00, 11.40, 125.40, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 14:11:50'),
(27, 'ORD-20251023-2407', 1, 200.00, 10.00, 0.00, 19.00, 209.00, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 15:24:31'),
(28, 'ORD-20251023-6702', 1, 100.00, 5.00, 0.00, 9.50, 104.50, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 16:14:59'),
(29, 'ORD-20251023-7846', 1, 100.00, 5.00, 0.00, 9.50, 104.50, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 16:30:43'),
(30, 'ORD-20251023-6860', 1, 150.00, 7.50, 0.00, 14.25, 156.75, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-23 16:53:28'),
(31, 'ORD-20251024-1683', 1, 50.00, 2.50, 0.00, 4.75, 52.25, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-24 06:20:54'),
(32, 'ORD-20251024-7609', 1, 50.00, 2.50, 0.00, 4.75, 302.25, 'cash', 'delivery', 250.00, 'completed', 0.00, '2025-10-24 07:28:09'),
(33, 'ORD-20251024-0547', 1, 40.00, 2.00, 0.00, 3.80, 41.80, 'cash', 'takeaway', 0.00, 'returned', 41.80, '2025-10-24 12:52:42'),
(34, 'ORD-20251024-0560', 1, 50.00, 2.50, 0.00, 4.75, 152.25, 'cash', 'delivery', 100.00, 'completed', 0.00, '2025-10-24 14:47:30'),
(35, 'ORD-20251025-2159', 1, 5.00, 0.25, 0.00, 0.48, 5.23, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-25 07:11:18'),
(36, 'ORD-20251025-8795', 1, 12.00, 0.60, 0.00, 1.14, 12.54, 'cash', 'takeaway', 0.00, 'returned', 12.54, '2025-10-25 07:11:41'),
(37, 'ORD-20251025-3269', 1, 1.50, 0.00, 0.00, 0.15, 1.65, 'cash', 'takeaway', 0.00, 'completed', 0.00, '2025-10-25 07:16:37'),
(38, 'ORD-20251025-9224', 1, 47.00, 0.00, 0.00, 4.70, 51.70, 'cash', 'takeaway', 0.00, 'returned', 51.70, '2025-10-25 07:40:54');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_returned` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `item_discount` decimal(5,2) DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `quantity_returned`, `unit_price`, `item_discount`, `total_price`, `profit`) VALUES
(1, 1, 2, 1, 0, 5.00, 0.00, 5.00, 0.00),
(2, 1, 3, 1, 0, 10.00, 0.00, 10.00, 0.00),
(3, 2, 2, 10, 0, 5.00, 0.00, 50.00, 0.00),
(4, 3, 1, 9, 0, 1.50, 0.00, 13.50, 0.00),
(5, 4, 2, 10, 0, 5.00, 0.00, 50.00, 0.00),
(7, 6, 2, 10, 0, 5.00, 0.00, 50.00, 0.00),
(10, 9, 3, 1, 0, 10.00, 0.00, 10.00, 0.00),
(11, 9, 2, 1, 0, 5.00, 0.00, 5.00, 0.00),
(12, 10, 3, 1, 0, 10.00, 0.00, 10.00, 0.00),
(13, 11, 3, 2, 0, 10.00, 0.00, 20.00, 0.00),
(14, 12, 3, 1, 0, 10.00, 0.00, 10.00, 0.00),
(15, 13, 3, 1, 0, 10.00, 0.00, 10.00, 0.00),
(17, 15, 2, 1, 0, 5.01, 0.00, 5.01, 0.00),
(18, 16, 27, 30, 0, 4.50, 0.00, 135.00, 0.00),
(19, 17, 2, 45, 0, 5.00, 0.00, 225.00, 112.50),
(20, 18, 2, 10, 0, 5.00, 0.00, 50.00, 0.00),
(21, 19, 1, 10, 0, 1.50, 0.00, 15.00, 0.00),
(22, 20, 2, 1, 0, 5.00, 0.00, 5.00, 0.00),
(23, 21, 2, 10, 0, 4.00, 0.00, 40.00, 0.00),
(24, 22, 1, 10, 0, 100.00, 0.00, 1000.00, 0.00),
(25, 23, 2, 7, 0, 10.00, 20.00, 70.00, 0.00),
(26, 23, 27, 30, 0, 2.00, 50.00, 60.00, 0.00),
(27, 24, 27, 30, 0, 2.00, 50.00, 60.00, 0.00),
(28, 25, 1, 20, 0, 10.00, 0.00, 200.00, 0.00),
(29, 26, 27, 10, 0, 12.00, 0.00, 120.00, 0.00),
(30, 27, 27, 100, 0, 2.00, 0.00, 200.00, 0.00),
(31, 28, 1, 10, 0, 10.00, 0.00, 100.00, 0.00),
(32, 29, 2, 20, 0, 5.00, 0.00, 100.00, 0.00),
(33, 30, 1, 10, 0, 15.00, 0.00, 150.00, 0.00),
(34, 31, 2, 10, 0, 5.00, 5.00, 50.00, 0.00),
(35, 32, 2, 10, 0, 5.00, 10.00, 50.00, 0.00),
(36, 33, 28, 2, 0, 20.00, 10.00, 40.00, 0.00),
(37, 34, 2, 10, 0, 5.00, 10.00, 50.00, 0.00),
(38, 35, 2, 1, 0, 5.00, 0.00, 5.00, 0.00),
(39, 36, 27, 1, 0, 12.00, 2.00, 12.00, 0.00),
(40, 37, 1, 1, 0, 1.50, 2.00, 1.50, 0.00),
(41, 38, 2, 3, 3, 5.00, 0.00, 15.00, 0.00),
(42, 38, 28, 1, 1, 20.00, 0.00, 20.00, 0.00),
(43, 38, 27, 1, 1, 12.00, 0.00, 12.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_returns`
--

CREATE TABLE `order_returns` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_returned` int(11) NOT NULL,
  `original_quantity` int(11) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `returned_by` int(11) NOT NULL,
  `return_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_returns`
--

INSERT INTO `order_returns` (`id`, `order_id`, `order_item_id`, `product_id`, `quantity_returned`, `original_quantity`, `refund_amount`, `reason`, `returned_by`, `return_date`) VALUES
(1, 38, 41, 2, 1, 3, 5.50, '', 1, '2025-10-25 12:41:52'),
(2, 38, 41, 2, 1, 3, 5.50, '', 1, '2025-10-25 12:42:23'),
(3, 38, 42, 28, 1, 1, 22.00, '', 1, '2025-10-25 12:57:22'),
(4, 38, 43, 27, 1, 1, 13.20, '', 1, '2025-10-25 12:57:43'),
(5, 38, 41, 2, 1, 3, 5.50, '', 1, '2025-10-25 13:05:53');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 5,
  `expiry_date` date NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `manufacturer` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `barcode`, `category_id`, `price`, `cost`, `stock_quantity`, `min_stock_level`, `expiry_date`, `manufacturing_date`, `manufacturer`, `description`, `created_at`, `updated_at`, `is_active`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 'Paracetamol 500mg', '1111111111111', 1, 1.50, 0.80, 130, 20, '2026-12-31', NULL, 'GSK Pharma', 'Pain relief medicine', '2025-09-15 14:50:17', '2025-10-25 07:16:37', 1, 0, NULL, NULL),
(2, 'Amoxicillin 250mg', '2222222222222', 2, 5.00, 2.50, 49, 10, '2027-01-15', NULL, 'Pfizer', 'Antibiotic medicine', '2025-09-15 14:50:17', '2025-10-25 08:05:53', 1, 0, NULL, NULL),
(3, 'Vitamin C 1000mg', '3333333333333', 3, 10.00, 6.00, 143, 15, '2026-05-20', '2023-12-22', 'Abbott', 'Immunity booster supplement', '2025-09-15 14:50:17', '2025-10-18 12:47:34', 1, 0, NULL, NULL),
(27, 'Panadol', '123456543321', 1, 12.00, 11.00, 99, 5, '2025-12-12', '2024-11-11', 'abbott', 'pain relief and versatile pills', '2025-10-18 12:31:09', '2025-10-25 07:57:43', 1, 1, '2025-10-19 11:20:23', 1),
(28, 'betnovate', '0001213243545', 4, 20.00, 15.00, 98, 5, '2027-12-20', '2025-11-20', 'jisper', 'skin scraper', '2025-10-18 12:48:48', '2025-10-25 07:57:22', 1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'tax_rate', '0'),
(4, 'discount_rate', '0');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier') DEFAULT 'cashier',
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `full_name`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'admin', 'admin@pharmacy.com', '$2y$10$rszWI1sJ03gOs/fpjB8gVO8pKEeLuKQJgNtpz.CgFCCzmUANKHGuC', 'admin', 'Pharmacy Administrator', '2025-09-15 14:50:17', '2025-09-15 14:50:17', 1),
(3, 'cashier1', '', '$2y$10$5kf4P7n2Srihdr1.CDdw1.WNuaJLDTpWG1BvL3yZE4ERyqtgYhz46', 'cashier', 'zaheer', '2025-10-24 14:51:45', '2025-10-24 14:51:45', 1);

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
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_orders_date` (`order_date`),
  ADD KEY `idx_orders_cashier` (`cashier_id`),
  ADD KEY `idx_order_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

--
-- Indexes for table `order_returns`
--
ALTER TABLE `order_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `returned_by` (`returned_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_products_barcode` (`barcode`),
  ADD KEY `deleted_by` (`deleted_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `order_returns`
--
ALTER TABLE `order_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `order_returns`
--
ALTER TABLE `order_returns`
  ADD CONSTRAINT `order_returns_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_returns_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_returns_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_returns_ibfk_4` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
