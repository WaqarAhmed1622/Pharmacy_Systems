-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2025 at 09:52 AM
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
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','other') DEFAULT 'cash',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `cashier_id`, `subtotal`, `tax_amount`, `total_amount`, `payment_method`, `order_date`) VALUES
(1, 'ORD-20250915-7572', 1, 15.00, 1.50, 16.50, 'card', '2025-09-15 15:02:09'),
(2, 'ORD-20250920-0744', 1, 50.00, 5.00, 55.00, 'cash', '2025-09-20 15:24:39'),
(3, 'ORD-20251009-2952', 1, 13.50, 1.35, 14.85, 'cash', '2025-10-09 11:55:11'),
(4, 'ORD-20251009-6310', 1, 50.00, 5.00, 55.00, 'cash', '2025-10-09 14:45:44'),
(5, 'ORD-20251015-3432', 1, 10.00, 1.00, 11.00, 'cash', '2025-10-15 11:23:24'),
(6, 'ORD-20251015-8692', 1, 50.00, 5.00, 55.00, 'cash', '2025-10-15 16:45:44'),
(7, 'ORD-20251016-7255', 1, 450.00, 45.00, 495.00, 'cash', '2025-10-16 10:05:24'),
(8, 'ORD-20251016-2780', 1, 13.60, 1.36, 14.96, 'cash', '2025-10-16 12:23:39'),
(9, 'ORD-20251016-6104', 1, 15.00, 1.50, 16.50, 'cash', '2025-10-16 12:53:24'),
(10, 'ORD-20251016-9986', 1, 10.00, 1.00, 11.00, 'cash', '2025-10-16 13:19:44'),
(11, 'ORD-20251016-4973', 1, 20.00, 2.00, 22.00, 'cash', '2025-10-16 13:34:13');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 2, 1, 5.00, 5.00),
(2, 1, 3, 1, 10.00, 10.00),
(3, 2, 2, 10, 5.00, 50.00),
(4, 3, 1, 9, 1.50, 13.50),
(5, 4, 2, 10, 5.00, 50.00),
(7, 6, 2, 10, 5.00, 50.00),
(10, 9, 3, 1, 10.00, 10.00),
(11, 9, 2, 1, 5.00, 5.00),
(12, 10, 3, 1, 10.00, 10.00),
(13, 11, 3, 2, 10.00, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 5,
  `expiry_date` date NOT NULL,
  `manufacturer` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `barcode`, `category_id`, `price`, `cost`, `stock_quantity`, `min_stock_level`, `expiry_date`, `manufacturer`, `description`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Paracetamol 500mg', '1111111111111', 1, 1.50, 0.80, 191, 20, '2026-12-31', 'GSK Pharma', 'Pain relief medicine', '2025-09-15 14:50:17', '2025-10-09 11:55:11', 1),
(2, 'Amoxicillin 250mg', '2222222222222', 2, 5.00, 2.50, 80, 10, '2027-01-15', 'Pfizer', 'Antibiotic medicine', '2025-09-15 14:50:17', '2025-10-16 12:54:22', 1),
(3, 'Vitamin C 1000mg', '3333333333333', 3, 10.00, 6.00, 145, 15, '2026-05-20', 'Abbott', 'Immunity booster supplement', '2025-09-15 14:50:17', '2025-10-16 13:34:13', 1),
(12, 'Hello', '123124124', 2, 235.00, 135.00, 10, 5, '2029-05-05', '', '', '2025-10-16 13:21:50', '2025-10-16 13:36:57', 0),
(13, 'mtk', '24343545', 1, 44.00, 33.00, 10, 5, '2025-01-11', '', '', '2025-10-16 14:06:11', '2025-10-16 14:06:11', 1);

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
(1, 'tax_rate', '0.1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT '',
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
(1, 'admin', 'admin@pharmacy.com', '$2y$10$rszWI1sJ03gOs/fpjB8gVO8pKEeLuKQJgNtpz.CgFCCzmUANKHGuC', 'admin', 'Pharmacy Administrator', '2025-09-15 14:50:17', '2025-09-15 14:50:17', 1);

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
  ADD KEY `idx_orders_cashier` (`cashier_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_products_barcode` (`barcode`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
