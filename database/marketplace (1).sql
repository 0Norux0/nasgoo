-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 14, 2026 at 04:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.5.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `marketplace`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `log_name` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `causer_type` varchar(255) DEFAULT NULL,
  `causer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `batch_uuid` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `log_name`, `description`, `subject_type`, `subject_id`, `event`, `causer_type`, `causer_id`, `properties`, `batch_uuid`, `created_at`, `updated_at`) VALUES
(1, 'user', 'created', 'App\\Models\\User', 1, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Marketplace Admin\",\"email\":\"admin@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:14', '2026-07-12 10:07:14'),
(2, 'user', 'created', 'App\\Models\\User', 2, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Admin Staff\",\"email\":\"staff@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:15', '2026-07-12 10:07:15'),
(3, 'user', 'created', 'App\\Models\\User', 3, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Demo Vendor\",\"email\":\"vendor@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:15', '2026-07-12 10:07:15'),
(4, 'user', 'created', 'App\\Models\\User', 4, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Demo Customer\",\"email\":\"customer@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:16', '2026-07-12 10:07:16'),
(5, 'vendor', 'created', 'App\\Models\\Vendor', 1, 'created', NULL, NULL, '{\"attributes\":{\"business_name\":\"Demo Trading Co.\",\"status\":\"approved\",\"featured\":false,\"payout_method\":null}}', NULL, '2026-07-12 10:07:16', '2026-07-12 10:07:16'),
(6, 'user', 'created', 'App\\Models\\User', 5, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Coastal Goods\",\"email\":\"vendor2@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17'),
(7, 'vendor', 'created', 'App\\Models\\Vendor', 2, 'created', NULL, NULL, '{\"attributes\":{\"business_name\":\"Coastal Goods\",\"status\":\"approved\",\"featured\":false,\"payout_method\":null}}', NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17'),
(8, 'user', 'created', 'App\\Models\\User', 6, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Pending Vendor\",\"email\":\"pending-vendor@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17'),
(9, 'vendor', 'created', 'App\\Models\\Vendor', 3, 'created', NULL, NULL, '{\"attributes\":{\"business_name\":\"Awaiting Review Shop\",\"status\":\"pending\",\"featured\":false,\"payout_method\":null}}', NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17'),
(10, 'user', 'created', 'App\\Models\\User', 7, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Rejected Vendor\",\"email\":\"rejected-vendor@marketplace.test\",\"phone\":null,\"status\":\"active\",\"locale\":\"en\",\"default_currency\":\"KWD\"}}', NULL, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(11, 'vendor', 'created', 'App\\Models\\Vendor', 4, 'created', NULL, NULL, '{\"attributes\":{\"business_name\":\"Rejected Demo\",\"status\":\"rejected\",\"featured\":false,\"payout_method\":null}}', NULL, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(12, 'product', 'created', 'App\\Models\\Product', 1, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Wireless Bluetooth Headphones\",\"status\":\"published\",\"price_minor\":12500,\"stock\":25,\"featured\":true}}', NULL, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(13, 'product', 'created', 'App\\Models\\Product', 2, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Cotton T-Shirt \\u2014 Classic Fit\",\"status\":\"published\",\"price_minor\":3500,\"stock\":80,\"featured\":false}}', NULL, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(14, 'product', 'created', 'App\\Models\\Product', 3, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Stainless Steel Water Bottle\",\"status\":\"published\",\"price_minor\":4750,\"stock\":50,\"featured\":true}}', NULL, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(15, 'product', 'created', 'App\\Models\\Product', 4, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Draft Product (vendor still editing)\",\"status\":\"draft\",\"price_minor\":0,\"stock\":0,\"featured\":false}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(16, 'product', 'created', 'App\\Models\\Product', 5, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Pending Review Product\",\"status\":\"pending_review\",\"price_minor\":2500,\"stock\":10,\"featured\":false}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(17, 'product', 'created', 'App\\Models\\Product', 6, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Handwoven Beach Towel\",\"status\":\"published\",\"price_minor\":6500,\"stock\":30,\"featured\":false}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(18, 'order', 'created', 'App\\Models\\Order', 1, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"delivered\",\"payment_status\":\"paid\",\"fulfillment_status\":\"fulfilled\",\"total_minor\":7000}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(19, 'order', 'created', 'App\\Models\\Order', 2, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"delivered\",\"payment_status\":\"paid\",\"fulfillment_status\":\"fulfilled\",\"total_minor\":14250}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(20, 'order', 'created', 'App\\Models\\Order', 3, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"delivered\",\"payment_status\":\"paid\",\"fulfillment_status\":\"fulfilled\",\"total_minor\":12500}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(21, 'order', 'created', 'App\\Models\\Order', 4, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"paid\",\"payment_status\":\"paid\",\"fulfillment_status\":\"unfulfilled\",\"total_minor\":3500}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(22, 'order', 'created', 'App\\Models\\Order', 5, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"confirmed\",\"payment_status\":\"paid\",\"fulfillment_status\":\"unfulfilled\",\"total_minor\":3500}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(23, 'order', 'created', 'App\\Models\\Order', 6, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"shipped\",\"payment_status\":\"paid\",\"fulfillment_status\":\"fulfilled\",\"total_minor\":3500}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(24, 'order', 'created', 'App\\Models\\Order', 7, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"pending_payment\",\"payment_status\":\"pending\",\"fulfillment_status\":\"unfulfilled\",\"total_minor\":3500}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(25, 'product', 'created', 'App\\Models\\Product', 7, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"USB-C Fast Charging Cable (2m)\",\"status\":\"pending_review\",\"price_minor\":1500,\"stock\":50,\"featured\":false}}', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(26, 'product', 'created', 'App\\Models\\Product', 8, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"LED Desk Lamp \\u2014 Touch Control (demo dropship)\",\"status\":\"pending_review\",\"price_minor\":4500,\"stock\":30,\"featured\":false}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(27, 'product', 'updated', 'App\\Models\\Product', 8, 'updated', NULL, NULL, '{\"attributes\":{\"status\":\"published\"},\"old\":{\"status\":\"pending_review\"}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(28, 'order', 'created', 'App\\Models\\Order', 8, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"paid\",\"payment_status\":\"paid\",\"fulfillment_status\":\"unfulfilled\",\"total_minor\":4500}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(29, 'product', 'created', 'App\\Models\\Product', 9, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Personalized Photo Mug\",\"status\":\"published\",\"price_minor\":350,\"stock\":0,\"featured\":false}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(30, 'product', 'created', 'App\\Models\\Product', 10, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Custom Printed T-Shirt\",\"status\":\"published\",\"price_minor\":800,\"stock\":0,\"featured\":false}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(31, 'order', 'created', 'App\\Models\\Order', 9, 'created', NULL, NULL, '{\"attributes\":{\"status\":\"paid\",\"payment_status\":\"paid\",\"fulfillment_status\":\"unfulfilled\",\"total_minor\":900}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(32, 'product', 'created', 'App\\Models\\Product', 11, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"General Doctor Consultation\",\"status\":\"published\",\"price_minor\":15000,\"stock\":0,\"featured\":false}}', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(33, 'product', 'created', 'App\\Models\\Product', 12, 'created', NULL, NULL, '{\"attributes\":{\"name\":\"Home AC Deep Cleaning\",\"status\":\"published\",\"price_minor\":12500,\"stock\":0,\"featured\":false}}', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'shipping',
  `country` varchar(2) NOT NULL,
  `state` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `area` varchar(255) DEFAULT NULL,
  `block` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL,
  `floor` varchar(255) DEFAULT NULL,
  `apartment` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `type`, `country`, `state`, `city`, `area`, `block`, `street`, `building`, `floor`, `apartment`, `postal_code`, `phone`, `latitude`, `longitude`, `is_default`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 4, 'Home', 'shipping', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', '+96599887766', NULL, NULL, 1, '2026-07-12 10:07:18', '2026-07-12 10:07:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_product_relationships`
--

CREATE TABLE `admin_product_relationships` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `related_product_id` bigint(20) UNSIGNED NOT NULL,
  `relationship_type` varchar(24) NOT NULL,
  `reciprocal` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attributes`
--

CREATE TABLE `attributes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name_translations`)),
  `type` varchar(255) NOT NULL DEFAULT 'select',
  `is_filterable` tinyint(1) NOT NULL DEFAULT 1,
  `is_variation` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attributes`
--

INSERT INTO `attributes` (`id`, `slug`, `name`, `name_translations`, `type`, `is_filterable`, `is_variation`, `position`, `created_at`, `updated_at`) VALUES
(1, 'color', 'Color', '{\"ar\":\"\\u0627\\u0644\\u0644\\u0648\\u0646\",\"ur\":\"\\u0631\\u0646\\u06af\"}', 'select', 1, 1, 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(2, 'size', 'Size', '{\"ar\":\"\\u0627\\u0644\\u0645\\u0642\\u0627\\u0633\",\"ur\":\"\\u0633\\u0627\\u0626\\u0632\"}', 'select', 1, 1, 2, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(3, 'brand', 'Brand', '{\"ar\":\"\\u0627\\u0644\\u0639\\u0644\\u0627\\u0645\\u0629 \\u0627\\u0644\\u062a\\u062c\\u0627\\u0631\\u064a\\u0629\",\"ur\":\"\\u0628\\u0631\\u0627\\u0646\\u0688\"}', 'select', 1, 0, 3, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(4, 'material', 'Material', '{\"ar\":\"\\u0627\\u0644\\u062e\\u0627\\u0645\\u0629\",\"ur\":\"\\u0645\\u0627\\u062f\\u06c1\"}', 'select', 1, 0, 4, '2026-07-12 10:07:13', '2026-07-12 10:07:13');

-- --------------------------------------------------------

--
-- Table structure for table `attribute_values`
--

CREATE TABLE `attribute_values` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attribute_id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `value_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value_translations`)),
  `color_hex` varchar(9) DEFAULT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attribute_values`
--

INSERT INTO `attribute_values` (`id`, `attribute_id`, `slug`, `value`, `value_translations`, `color_hex`, `position`, `created_at`, `updated_at`) VALUES
(1, 1, 'red', 'Red', '{\"ar\":\"\\u0623\\u062d\\u0645\\u0631\",\"ur\":\"\\u0633\\u0631\\u062e\"}', '#EF4444', 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(2, 1, 'blue', 'Blue', '{\"ar\":\"\\u0623\\u0632\\u0631\\u0642\",\"ur\":\"\\u0646\\u06cc\\u0644\\u0627\"}', '#3B82F6', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(3, 1, 'green', 'Green', '{\"ar\":\"\\u0623\\u062e\\u0636\\u0631\",\"ur\":\"\\u0633\\u0628\\u0632\"}', '#10B981', 2, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(4, 1, 'black', 'Black', '{\"ar\":\"\\u0623\\u0633\\u0648\\u062f\",\"ur\":\"\\u0633\\u06cc\\u0627\\u06c1\"}', '#0F172A', 3, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(5, 1, 'white', 'White', '{\"ar\":\"\\u0623\\u0628\\u064a\\u0636\",\"ur\":\"\\u0633\\u0641\\u06cc\\u062f\"}', '#F1F5F9', 4, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(6, 2, 'xs', 'XS', NULL, NULL, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(7, 2, 's', 'S', NULL, NULL, 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(8, 2, 'm', 'M', NULL, NULL, 2, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(9, 2, 'l', 'L', NULL, NULL, 3, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(10, 2, 'xl', 'XL', NULL, NULL, 4, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(11, 4, 'cotton', 'Cotton', NULL, NULL, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(12, 4, 'leather', 'Leather', NULL, NULL, 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(13, 4, 'metal', 'Metal', NULL, NULL, 2, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(14, 4, 'plastic', 'Plastic', NULL, NULL, 3, '2026-07-12 10:07:13', '2026-07-12 10:07:13');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `model_type` varchar(255) DEFAULT NULL,
  `model_id` bigint(20) UNSIGNED DEFAULT NULL,
  `before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before`)),
  `after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `subtotal_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `items_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `coupon_id` bigint(20) UNSIGNED DEFAULT NULL,
  `discount_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_minor` int(10) UNSIGNED NOT NULL,
  `customization_fee_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_item_customizations`
--

CREATE TABLE `cart_item_customizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_item_id` bigint(20) UNSIGNED NOT NULL,
  `field_id` bigint(20) UNSIGNED DEFAULT NULL,
  `field_key` varchar(64) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` varchar(32) NOT NULL,
  `value` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_original_name` varchar(255) DEFAULT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `file_size_bytes` int(10) UNSIGNED DEFAULT NULL,
  `extra_fee_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name_translations`)),
  `description` text DEFAULT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `depth` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `path` varchar(255) DEFAULT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `products_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `slug`, `name`, `name_translations`, `description`, `icon_path`, `image_path`, `depth`, `path`, `position`, `is_active`, `products_count`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, NULL, 'electronics', 'Electronics', '{\"ar\":\"\\u0625\\u0644\\u0643\\u062a\\u0631\\u0648\\u0646\\u064a\\u0627\\u062a\",\"ur\":\"\\u0627\\u0644\\u06cc\\u06a9\\u0679\\u0631\\u0627\\u0646\\u06a9\\u0633\"}', NULL, NULL, NULL, 0, 'electronics', 0, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(2, 1, 'electronics-phones', 'Phones', '{\"ar\":\"\\u0647\\u0648\\u0627\\u062a\\u0641\",\"ur\":\"\\u0641\\u0648\\u0646\"}', NULL, NULL, NULL, 1, 'electronics/electronics-phones', 0, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(3, 1, 'electronics-laptops', 'Laptops', '{\"ar\":\"\\u062d\\u0648\\u0627\\u0633\\u064a\\u0628 \\u0645\\u062d\\u0645\\u0648\\u0644\\u0629\",\"ur\":\"\\u0644\\u06cc\\u067e \\u0679\\u0627\\u067e\"}', NULL, NULL, NULL, 1, 'electronics/electronics-laptops', 1, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(4, 1, 'electronics-accessories', 'Accessories', '{\"ar\":\"\\u0625\\u0643\\u0633\\u0633\\u0648\\u0627\\u0631\\u0627\\u062a\",\"ur\":\"\\u0644\\u0648\\u0627\\u0632\\u0645\\u0627\\u062a\"}', NULL, NULL, NULL, 1, 'electronics/electronics-accessories', 2, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(5, NULL, 'fashion', 'Fashion', '{\"ar\":\"\\u0623\\u0632\\u064a\\u0627\\u0621\",\"ur\":\"\\u0641\\u06cc\\u0634\\u0646\"}', NULL, NULL, NULL, 0, 'fashion', 1, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(6, 5, 'fashion-men', 'Men', '{\"ar\":\"\\u0631\\u062c\\u0627\\u0644\",\"ur\":\"\\u0645\\u0631\\u062f\"}', NULL, NULL, NULL, 1, 'fashion/fashion-men', 0, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(7, 5, 'fashion-women', 'Women', '{\"ar\":\"\\u0646\\u0633\\u0627\\u0621\",\"ur\":\"\\u062e\\u0648\\u0627\\u062a\\u06cc\\u0646\"}', NULL, NULL, NULL, 1, 'fashion/fashion-women', 1, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(8, 5, 'fashion-kids', 'Kids', '{\"ar\":\"\\u0623\\u0637\\u0641\\u0627\\u0644\",\"ur\":\"\\u0628\\u0686\\u06d2\"}', NULL, NULL, NULL, 1, 'fashion/fashion-kids', 2, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(9, NULL, 'home-living', 'Home & Living', '{\"ar\":\"\\u0627\\u0644\\u0645\\u0646\\u0632\\u0644\",\"ur\":\"\\u06af\\u06be\\u0631 \\u0627\\u0648\\u0631 \\u0632\\u0646\\u062f\\u06af\\u06cc\"}', NULL, NULL, NULL, 0, 'home-living', 2, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(10, 9, 'home-living-kitchen', 'Kitchen', '{\"ar\":\"\\u0627\\u0644\\u0645\\u0637\\u0628\\u062e\",\"ur\":\"\\u0628\\u0627\\u0648\\u0631\\u0686\\u06cc \\u062e\\u0627\\u0646\\u06c1\"}', NULL, NULL, NULL, 1, 'home-living/home-living-kitchen', 0, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(11, 9, 'home-living-furniture', 'Furniture', '{\"ar\":\"\\u0623\\u062b\\u0627\\u062b\",\"ur\":\"\\u0641\\u0631\\u0646\\u06cc\\u0686\\u0631\"}', NULL, NULL, NULL, 1, 'home-living/home-living-furniture', 1, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(12, NULL, 'beauty', 'Beauty', '{\"ar\":\"\\u0627\\u0644\\u062c\\u0645\\u0627\\u0644\",\"ur\":\"\\u062e\\u0648\\u0628\\u0635\\u0648\\u0631\\u062a\\u06cc\"}', NULL, NULL, NULL, 0, 'beauty', 3, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL),
(13, NULL, 'sports', 'Sports', '{\"ar\":\"\\u0631\\u064a\\u0627\\u0636\\u0629\",\"ur\":\"\\u06a9\\u06be\\u06cc\\u0644\"}', NULL, NULL, NULL, 0, 'sports', 4, 1, 0, '2026-07-12 10:07:13', '2026-07-12 10:07:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category_product`
--

CREATE TABLE `category_product` (
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` varchar(20) NOT NULL,
  `discount_value` int(10) UNSIGNED NOT NULL,
  `min_order_minor` int(10) UNSIGNED DEFAULT NULL,
  `max_discount_minor` int(10) UNSIGNED DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL,
  `per_user_limit` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `assigned_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `vendor_id`, `created_by`, `code`, `description`, `discount_type`, `discount_value`, `min_order_minor`, `max_discount_minor`, `starts_at`, `ends_at`, `is_active`, `usage_limit`, `per_user_limit`, `assigned_user_id`, `currency`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'SAVE10', '10% off your next order, no minimum.', 'percentage', 10, NULL, 50000, '2026-07-11 10:07:21', '2026-08-12 10:07:21', 1, 1000, 3, NULL, 'KWD', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, NULL, 1, 'WELCOME5', '5 KWD off orders of 20 KWD or more.', 'fixed_amount', 5000, 20000, NULL, '2026-07-11 10:07:21', '2026-08-12 10:07:21', 1, 100, 1, NULL, 'KWD', '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `coupon_usages`
--

CREATE TABLE `coupon_usages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coupon_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `discount_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(3) NOT NULL,
  `name` varchar(255) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `decimal_places` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `decimal_places`, `is_default`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'KWD', 'Kuwaiti Dinar', 'KD', 3, 1, 1, 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(2, 'USD', 'US Dollar', '$', 2, 0, 1, 2, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(3, 'AED', 'UAE Dirham', 'AED', 2, 0, 1, 3, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(4, 'PKR', 'Pakistani Rupee', '₨', 2, 0, 1, 4, '2026-07-12 10:07:12', '2026-07-12 10:07:12');

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `base_currency` varchar(3) NOT NULL,
  `target_currency` varchar(3) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'manual',
  `effective_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currency_rates`
--

INSERT INTO `currency_rates` (`id`, `base_currency`, `target_currency`, `rate`, `source`, `effective_at`, `created_at`, `updated_at`) VALUES
(1, 'KWD', 'USD', 3.25000000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(2, 'KWD', 'AED', 11.95000000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(3, 'KWD', 'PKR', 905.00000000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(4, 'USD', 'KWD', 0.30800000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(5, 'USD', 'AED', 3.67000000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(6, 'USD', 'PKR', 278.50000000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(7, 'AED', 'USD', 0.27200000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(8, 'AED', 'KWD', 0.08360000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(9, 'AED', 'PKR', 75.85000000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(10, 'PKR', 'USD', 0.00359000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(11, 'PKR', 'KWD', 0.00110000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(12, 'PKR', 'AED', 0.01318000, 'seeded', '2026-07-12 10:07:12', '2026-07-12 10:07:12', '2026-07-12 10:07:12');

-- --------------------------------------------------------

--
-- Table structure for table `customer_affinities`
--

CREATE TABLE `customer_affinities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `dimension` varchar(24) NOT NULL,
  `dimension_id` bigint(20) UNSIGNED DEFAULT NULL,
  `dimension_key` varchar(64) DEFAULT NULL,
  `score` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `signal_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_signal_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_product_views`
--

CREATE TABLE `customer_product_views` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_key` varchar(64) DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `locale` varchar(8) NOT NULL,
  `device_category` varchar(16) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customization_proofs`
--

CREATE TABLE `customization_proofs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_original_name` varchar(255) NOT NULL,
  `file_mime` varchar(100) NOT NULL,
  `file_size_bytes` int(10) UNSIGNED NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `vendor_note` text DEFAULT NULL,
  `customer_response` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customization_proofs`
--

INSERT INTO `customization_proofs` (`id`, `order_item_id`, `vendor_id`, `file_path`, `file_original_name`, `file_mime`, `file_size_bytes`, `status`, `vendor_note`, `customer_response`, `sent_at`, `responded_at`, `created_at`, `updated_at`) VALUES
(1, 9, 1, 'customization-proofs/1/9/demo-proof-v1.png', 'mug-proof-v1.png', 'image/png', 70, 'sent', 'First proof — please check the photo placement and text positioning.', NULL, '2026-07-12 09:47:20', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `license_activations`
--

CREATE TABLE `license_activations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `license_holder` varchar(255) DEFAULT NULL,
  `license_type` varchar(32) NOT NULL DEFAULT 'standard',
  `domain` varchar(255) DEFAULT NULL,
  `app_url` varchar(255) DEFAULT NULL,
  `server_fingerprint` varchar(64) DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `activated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `license_audit_logs`
--

CREATE TABLE `license_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event` varchar(48) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `token_hash` varchar(64) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '0001_01_01_000003_create_personal_access_tokens_table', 1),
(5, '2026_01_01_000001_create_permission_tables', 1),
(6, '2026_01_01_000002_create_addresses_table', 1),
(7, '2026_01_01_000003_create_settings_table', 1),
(8, '2026_01_01_000004_create_notification_templates_table', 1),
(9, '2026_01_01_000005_create_currencies_tables', 1),
(10, '2026_01_01_000006_create_audit_logs_table', 1),
(11, '2026_01_01_000007_create_activity_log_table', 1),
(12, '2026_01_02_000001_create_vendors_table', 1),
(13, '2026_01_02_000002_create_vendor_packages_table', 1),
(14, '2026_01_02_000003_create_vendor_subscriptions_table', 1),
(15, '2026_01_02_000004_create_vendor_commission_rules_table', 1),
(16, '2026_01_03_000001_create_categories_table', 1),
(17, '2026_01_03_000002_create_attributes_tables', 1),
(18, '2026_01_03_000003_create_products_table', 1),
(19, '2026_01_03_000004_create_product_variants_and_images', 1),
(20, '2026_01_04_000001_create_carts_table', 1),
(21, '2026_01_04_000002_create_orders_tables', 1),
(22, '2026_01_04_000003_create_payments_tables', 1),
(23, '2026_01_05_000001_create_product_reviews_table', 1),
(24, '2026_01_05_000002_create_wishlists_table', 1),
(25, '2026_01_05_000003_create_vendor_payout_requests_table', 1),
(26, '2026_01_05_000004_create_shipping_zones_and_methods_tables', 1),
(27, '2026_01_06_000001_create_supplier_platforms_table', 1),
(28, '2026_01_06_000002_create_supplier_integrations_table', 1),
(29, '2026_01_06_000003_create_supplier_products_table', 1),
(30, '2026_01_06_000004_create_supplier_orders_table', 1),
(31, '2026_01_06_000005_create_supplier_product_imports_table', 1),
(32, '2026_01_06_000006_add_dropshipping_fields_to_products_table', 1),
(33, '2026_01_06_000007_add_supplier_fields_to_order_items_table', 1),
(34, '2026_01_07_000001_create_product_customization_fields_table', 1),
(35, '2026_01_07_000002_create_cart_item_customizations_table', 1),
(36, '2026_01_07_000003_create_order_item_customizations_table', 1),
(37, '2026_01_07_000004_create_customization_proofs_table', 1),
(38, '2026_01_07_000005_add_customization_columns_to_cart_and_order_items', 1),
(39, '2026_01_08_000001_create_service_details_table', 1),
(40, '2026_01_08_000002_create_service_providers_table', 1),
(41, '2026_01_08_000003_create_service_provider_assignments_table', 1),
(42, '2026_01_08_000004_create_service_availabilities_table', 1),
(43, '2026_01_08_000005_create_service_blocked_dates_table', 1),
(44, '2026_01_08_000006_create_service_bookings_table', 1),
(45, '2026_01_08_000007_document_service_product_type', 1),
(46, '2026_01_15_000001_create_promotions_table', 1),
(47, '2026_01_15_000002_create_promotion_targets_table', 1),
(48, '2026_01_15_000003_create_coupons_table', 1),
(49, '2026_01_15_000004_create_coupon_usages_table', 1),
(50, '2026_01_15_000005_create_support_tickets_table', 1),
(51, '2026_01_15_000006_create_support_ticket_messages_table', 1),
(52, '2026_01_15_000007_extend_product_reviews_for_phase_9', 1),
(53, '2026_01_20_000001_add_coupon_allocation_to_order_items', 1),
(54, '2026_06_15_000001_add_phase10_v101_performance_indexes', 1),
(55, '2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns', 1),
(56, '2026_06_19_000001_phase10_v109_ensure_reports_permission_assigned', 1),
(57, '2026_06_21_000001_add_phase10_v1014_performance_indexes', 1),
(58, '2026_06_24_000001_backfill_arabic_category_translations', 1),
(59, '2026_06_25_000001_create_search_synonyms_table', 1),
(60, '2026_06_25_000002_create_search_queries_table', 1),
(61, '2026_06_25_000003_create_user_recent_searches_table', 1),
(62, '2026_06_25_000004_add_search_performance_indexes_to_products', 1),
(63, '2026_06_27_000001_add_short_description_translations_to_products', 1),
(64, '2026_06_28_000001_create_product_translations_table', 1),
(65, '2026_07_01_000001_create_product_pair_stats_table', 1),
(66, '2026_07_01_000002_create_product_recommendations_table', 1),
(67, '2026_07_01_000003_create_admin_product_relationships_table', 1),
(68, '2026_07_01_000004_create_recommendation_events_table', 1),
(69, '2026_07_05_000001_extend_recommendation_events_for_purchase_attribution', 1),
(70, '2026_08_01_000001_create_customer_product_views_table', 1),
(71, '2026_08_01_000002_create_customer_affinities_table', 1),
(72, '2026_08_01_000003_create_personalization_preferences_and_feedback_tables', 1),
(73, '2026_09_01_000001_add_audit_and_translatable_to_settings', 1),
(74, '2026_10_01_000001_add_admin_performance_indexes', 1),
(75, '2026_11_01_000001_create_vendor_intelligence_tables', 1),
(76, '2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale', 1),
(77, '2027_01_01_000001_add_vendor_intelligence_digest_columns', 1),
(78, '2027_02_01_000001_create_license_tables', 1);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2),
(3, 'App\\Models\\User', 3),
(3, 'App\\Models\\User', 5),
(3, 'App\\Models\\User', 6),
(3, 'App\\Models\\User', 7),
(4, 'App\\Models\\User', 4);

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(255) NOT NULL,
  `channel` varchar(255) NOT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en',
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`placeholders`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_templates`
--

INSERT INTO `notification_templates` (`id`, `event_key`, `channel`, `locale`, `subject`, `body`, `placeholders`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'user.registered', 'mail', 'en', 'Welcome to {{ site_name }}', 'Hi {{ name }}, welcome aboard. Please verify your email to activate your account.', '[\"site_name\",\"name\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(2, 'user.registered', 'database', 'en', 'Welcome to {{ site_name }}', 'Hi {{ name }}, welcome aboard. Please verify your email to activate your account.', '[\"site_name\",\"name\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(3, 'user.registered', 'mail', 'ar', 'أهلاً بك في {{ site_name }}', 'مرحباً {{ name }}، يُرجى تأكيد بريدك الإلكتروني لتفعيل حسابك.', '[\"site_name\",\"name\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(4, 'user.email_verification', 'mail', 'en', 'Verify your email', 'Hi {{ name }}, please click the link to verify your email: {{ verification_url }}', '[\"name\",\"verification_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(5, 'user.email_verification', 'database', 'en', 'Verify your email', 'Hi {{ name }}, please click the link to verify your email: {{ verification_url }}', '[\"name\",\"verification_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(6, 'user.email_verification', 'mail', 'ar', 'تأكيد بريدك الإلكتروني', 'مرحباً {{ name }}، اضغط الرابط للتأكيد: {{ verification_url }}', '[\"name\",\"verification_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(7, 'password.reset', 'mail', 'en', 'Reset your password', 'Hi {{ name }}, use this link to reset your password: {{ reset_url }} (expires in 60 minutes).', '[\"name\",\"reset_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(8, 'password.reset', 'database', 'en', 'Reset your password', 'Hi {{ name }}, use this link to reset your password: {{ reset_url }} (expires in 60 minutes).', '[\"name\",\"reset_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(9, 'password.reset', 'mail', 'ar', 'إعادة تعيين كلمة المرور', 'مرحباً {{ name }}، استخدم الرابط: {{ reset_url }} (صالح لمدة 60 دقيقة).', '[\"name\",\"reset_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(10, 'vendor.approved', 'mail', 'en', 'Your vendor account is approved', 'Hi {{ business_name }}, your vendor application has been approved. You can now upload products.', '[\"business_name\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(11, 'vendor.approved', 'database', 'en', 'Your vendor account is approved', 'Hi {{ business_name }}, your vendor application has been approved. You can now upload products.', '[\"business_name\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(12, 'vendor.approved', 'mail', 'ar', 'تمت الموافقة على حسابك', 'مرحباً {{ business_name }}، تمت الموافقة على طلبك ويمكنك الآن رفع المنتجات.', '[\"business_name\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(13, 'vendor.rejected', 'mail', 'en', 'Vendor application rejected', 'Hi {{ business_name }}, unfortunately we cannot approve your application. Reason: {{ reason }}', '[\"business_name\",\"reason\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(14, 'vendor.rejected', 'database', 'en', 'Vendor application rejected', 'Hi {{ business_name }}, unfortunately we cannot approve your application. Reason: {{ reason }}', '[\"business_name\",\"reason\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(15, 'vendor.rejected', 'mail', 'ar', 'رفض طلب البائع', 'مرحباً {{ business_name }}، لم نتمكن من قبول طلبك. السبب: {{ reason }}', '[\"business_name\",\"reason\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(16, 'product.approved', 'mail', 'en', 'Product approved: {{ title }}', 'Your product \"{{ title }}\" has been approved and is now live on the marketplace.', '[\"title\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(17, 'product.approved', 'database', 'en', 'Product approved: {{ title }}', 'Your product \"{{ title }}\" has been approved and is now live on the marketplace.', '[\"title\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(18, 'product.approved', 'mail', 'ar', 'تمت الموافقة على المنتج', 'تمت الموافقة على منتجك \"{{ title }}\" وأصبح متاحاً الآن.', '[\"title\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(19, 'product.rejected', 'mail', 'en', 'Product rejected: {{ title }}', 'Your product \"{{ title }}\" was not approved. Reason: {{ reason }}', '[\"title\",\"reason\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(20, 'product.rejected', 'database', 'en', 'Product rejected: {{ title }}', 'Your product \"{{ title }}\" was not approved. Reason: {{ reason }}', '[\"title\",\"reason\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(21, 'product.rejected', 'mail', 'ar', 'رفض المنتج', 'لم تتم الموافقة على منتجك \"{{ title }}\". السبب: {{ reason }}', '[\"title\",\"reason\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(22, 'order.placed', 'mail', 'en', 'Order #{{ order_number }} placed', 'Hi {{ name }}, your order #{{ order_number }} totaling {{ total }} has been placed. We will notify you of updates.', '[\"name\",\"order_number\",\"total\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(23, 'order.placed', 'database', 'en', 'Order #{{ order_number }} placed', 'Hi {{ name }}, your order #{{ order_number }} totaling {{ total }} has been placed. We will notify you of updates.', '[\"name\",\"order_number\",\"total\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(24, 'order.placed', 'mail', 'ar', 'تم استلام طلبك #{{ order_number }}', 'مرحباً {{ name }}، تم استلام طلبك #{{ order_number }} بمبلغ {{ total }}.', '[\"name\",\"order_number\",\"total\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(25, 'order.confirmed', 'mail', 'en', 'Order #{{ order_number }} confirmed', 'Your order #{{ order_number }} is confirmed and is being prepared for shipment.', '[\"order_number\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(26, 'order.confirmed', 'database', 'en', 'Order #{{ order_number }} confirmed', 'Your order #{{ order_number }} is confirmed and is being prepared for shipment.', '[\"order_number\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(27, 'order.confirmed', 'mail', 'ar', 'تم تأكيد الطلب #{{ order_number }}', 'تم تأكيد طلبك #{{ order_number }} وجارٍ تجهيزه.', '[\"order_number\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(28, 'order.shipped', 'mail', 'en', 'Order #{{ order_number }} shipped', 'Your order #{{ order_number }} has shipped. Track it here: {{ tracking_url }}', '[\"order_number\",\"tracking_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(29, 'order.shipped', 'database', 'en', 'Order #{{ order_number }} shipped', 'Your order #{{ order_number }} has shipped. Track it here: {{ tracking_url }}', '[\"order_number\",\"tracking_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(30, 'order.shipped', 'mail', 'ar', 'شحن الطلب #{{ order_number }}', 'تم شحن طلبك. تتبع الشحنة: {{ tracking_url }}', '[\"order_number\",\"tracking_url\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(31, 'order.delivered', 'mail', 'en', 'Order #{{ order_number }} delivered', 'Your order #{{ order_number }} has been delivered. Thanks for shopping with us!', '[\"order_number\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(32, 'order.delivered', 'database', 'en', 'Order #{{ order_number }} delivered', 'Your order #{{ order_number }} has been delivered. Thanks for shopping with us!', '[\"order_number\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(33, 'order.delivered', 'mail', 'ar', 'تم تسليم الطلب', 'تم تسليم طلبك #{{ order_number }}. شكراً لك!', '[\"order_number\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(34, 'booking.created', 'mail', 'en', 'Booking #{{ booking_number }} received', 'Hi {{ name }}, your booking for {{ service_name }} on {{ scheduled_at }} has been received.', '[\"name\",\"booking_number\",\"service_name\",\"scheduled_at\"]', 1, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(35, 'booking.created', 'database', 'en', 'Booking #{{ booking_number }} received', 'Hi {{ name }}, your booking for {{ service_name }} on {{ scheduled_at }} has been received.', '[\"name\",\"booking_number\",\"service_name\",\"scheduled_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(36, 'booking.created', 'mail', 'ar', 'حجز جديد #{{ booking_number }}', 'مرحباً {{ name }}، تم استلام حجزك لخدمة {{ service_name }}.', '[\"name\",\"booking_number\",\"service_name\",\"scheduled_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(37, 'booking.confirmed', 'mail', 'en', 'Booking #{{ booking_number }} confirmed', 'Your booking for {{ service_name }} on {{ scheduled_at }} is confirmed.', '[\"booking_number\",\"service_name\",\"scheduled_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(38, 'booking.confirmed', 'database', 'en', 'Booking #{{ booking_number }} confirmed', 'Your booking for {{ service_name }} on {{ scheduled_at }} is confirmed.', '[\"booking_number\",\"service_name\",\"scheduled_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(39, 'booking.confirmed', 'mail', 'ar', 'تأكيد الحجز', 'تم تأكيد حجزك لخدمة {{ service_name }} في {{ scheduled_at }}.', '[\"booking_number\",\"service_name\",\"scheduled_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(40, 'payout.requested', 'mail', 'en', 'Payout request submitted', 'Your payout request of {{ amount }} has been submitted and is pending review.', '[\"amount\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(41, 'payout.requested', 'database', 'en', 'Payout request submitted', 'Your payout request of {{ amount }} has been submitted and is pending review.', '[\"amount\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(42, 'payout.requested', 'mail', 'ar', 'طلب سحب جديد', 'تم استلام طلب السحب بمبلغ {{ amount }} وهو قيد المراجعة.', '[\"amount\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(43, 'payout.approved', 'mail', 'en', 'Payout approved', 'Your payout of {{ amount }} has been approved and will be processed within 1-3 business days.', '[\"amount\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(44, 'payout.approved', 'database', 'en', 'Payout approved', 'Your payout of {{ amount }} has been approved and will be processed within 1-3 business days.', '[\"amount\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(45, 'payout.approved', 'mail', 'ar', 'تمت الموافقة على السحب', 'تمت الموافقة على سحب {{ amount }} وسيُنفذ خلال 1-3 أيام عمل.', '[\"amount\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(46, 'vendor.application_submitted', 'mail', 'en', 'Vendor application received', 'Hi {{ business_name }}, we received your vendor application. Our team will review it shortly.', '[\"business_name\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(47, 'vendor.application_submitted', 'database', 'en', 'Vendor application received', 'Hi {{ business_name }}, we received your vendor application. Our team will review it shortly.', '[\"business_name\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(48, 'vendor.application_submitted', 'mail', 'ar', 'تم استلام طلب البائع', 'مرحباً {{ business_name }}، تم استلام طلبك وسيتم مراجعته قريباً.', '[\"business_name\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(49, 'vendor.suspended', 'mail', 'en', 'Vendor account suspended', 'Hi {{ business_name }}, your vendor account has been suspended. Reason: {{ reason }}', '[\"business_name\",\"reason\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(50, 'vendor.suspended', 'database', 'en', 'Vendor account suspended', 'Hi {{ business_name }}, your vendor account has been suspended. Reason: {{ reason }}', '[\"business_name\",\"reason\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(51, 'vendor.suspended', 'mail', 'ar', 'تعليق حساب البائع', 'مرحباً {{ business_name }}، تم تعليق حسابك. السبب: {{ reason }}', '[\"business_name\",\"reason\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(52, 'vendor.subscription_activated', 'mail', 'en', 'Subscription activated', 'Hi {{ business_name }}, your {{ package }} subscription is now active until {{ ends_at }}.', '[\"business_name\",\"package\",\"ends_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(53, 'vendor.subscription_activated', 'database', 'en', 'Subscription activated', 'Hi {{ business_name }}, your {{ package }} subscription is now active until {{ ends_at }}.', '[\"business_name\",\"package\",\"ends_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(54, 'vendor.subscription_activated', 'mail', 'ar', 'تفعيل الاشتراك', 'مرحباً {{ business_name }}، تم تفعيل اشتراك {{ package }} حتى {{ ends_at }}.', '[\"business_name\",\"package\",\"ends_at\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(55, 'vendor.package_changed', 'mail', 'en', 'Vendor package changed', 'Hi {{ business_name }}, your package has been changed to {{ package }}.', '[\"business_name\",\"package\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(56, 'vendor.package_changed', 'database', 'en', 'Vendor package changed', 'Hi {{ business_name }}, your package has been changed to {{ package }}.', '[\"business_name\",\"package\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(57, 'vendor.package_changed', 'mail', 'ar', 'تغيير الباقة', 'مرحباً {{ business_name }}، تم تغيير باقتك إلى {{ package }}.', '[\"business_name\",\"package\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(58, 'vendor.commission_changed', 'mail', 'en', 'Vendor commission updated', 'Hi {{ business_name }}, your commission rule has been updated. New rate: {{ rate }}.', '[\"business_name\",\"rate\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(59, 'vendor.commission_changed', 'database', 'en', 'Vendor commission updated', 'Hi {{ business_name }}, your commission rule has been updated. New rate: {{ rate }}.', '[\"business_name\",\"rate\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(60, 'vendor.commission_changed', 'mail', 'ar', 'تحديث عمولة البائع', 'مرحباً {{ business_name }}، تم تحديث عمولتك. المعدل الجديد: {{ rate }}.', '[\"business_name\",\"rate\"]', 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `number` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending_payment',
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `fulfillment_status` varchar(255) NOT NULL DEFAULT 'unfulfilled',
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `shipping_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `shipping_method_name` varchar(120) DEFAULT NULL,
  `subtotal_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tax_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `discount_minor` int(11) NOT NULL DEFAULT 0,
  `promotion_discount_minor` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Phase 10 v10.8: sum of per-line promotion discounts (separate from coupon_discount_minor)',
  `total_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `coupon_id` bigint(20) UNSIGNED DEFAULT NULL,
  `coupon_discount_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `coupon_code` varchar(50) DEFAULT NULL,
  `platform_commission_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vendor_earnings_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `customer_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `earnings_release_at` timestamp NULL DEFAULT NULL,
  `earnings_released` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `number`, `user_id`, `status`, `payment_status`, `fulfillment_status`, `currency`, `shipping_method_id`, `shipping_method_name`, `subtotal_minor`, `shipping_minor`, `tax_minor`, `discount_minor`, `promotion_discount_minor`, `total_minor`, `coupon_id`, `coupon_discount_minor`, `coupon_code`, `platform_commission_minor`, `vendor_earnings_minor`, `customer_notes`, `internal_notes`, `paid_at`, `confirmed_at`, `shipped_at`, `delivered_at`, `completed_at`, `cancelled_at`, `cancellation_reason`, `earnings_release_at`, `earnings_released`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'DEMO-DELIVERED-01-20260712130719', 4, 'delivered', 'paid', 'fulfilled', 'KWD', NULL, NULL, 7000, 0, 0, 0, 0, 7000, NULL, 0, NULL, 1400, 5600, NULL, NULL, '2026-06-12 10:07:19', '2026-06-13 10:07:19', '2026-06-15 10:07:19', '2026-06-17 10:07:19', NULL, NULL, NULL, '2026-06-24 10:07:19', 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(2, 'DEMO-DELIVERED-02-20260712130719', 4, 'delivered', 'paid', 'fulfilled', 'KWD', NULL, NULL, 14250, 0, 0, 0, 0, 14250, NULL, 0, NULL, 2850, 11400, NULL, NULL, '2026-06-19 10:07:19', '2026-06-20 10:07:19', '2026-06-22 10:07:19', '2026-06-24 10:07:19', NULL, NULL, NULL, '2026-07-01 10:07:19', 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(3, 'DEMO-DELIVERED-03-20260712130719', 4, 'delivered', 'paid', 'fulfilled', 'KWD', NULL, NULL, 12500, 0, 0, 0, 0, 12500, NULL, 0, NULL, 2500, 10000, NULL, NULL, '2026-06-25 10:07:19', '2026-06-26 10:07:19', '2026-06-28 10:07:19', '2026-06-30 10:07:19', NULL, NULL, NULL, '2026-07-07 10:07:19', 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(4, 'DEMO-ACTIONABLE-PAID-20260712130719', 4, 'paid', 'paid', 'unfulfilled', 'KWD', NULL, NULL, 3500, 0, 0, 0, 0, 3500, NULL, 0, NULL, 700, 2800, NULL, NULL, '2026-07-12 04:07:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(5, 'DEMO-ACTIONABLE-CONFIRMED-20260712130719', 4, 'confirmed', 'paid', 'unfulfilled', 'KWD', NULL, NULL, 3500, 0, 0, 0, 0, 3500, NULL, 0, NULL, 700, 2800, NULL, NULL, '2026-07-11 10:07:19', '2026-07-11 22:07:19', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(6, 'DEMO-ACTIONABLE-SHIPPED-20260712130719', 4, 'shipped', 'paid', 'fulfilled', 'KWD', NULL, NULL, 3500, 0, 0, 0, 0, 3500, NULL, 0, NULL, 700, 2800, NULL, NULL, '2026-07-10 10:07:19', '2026-07-10 10:07:19', '2026-07-12 02:07:19', NULL, NULL, NULL, NULL, NULL, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(7, 'DEMO-ACTIONABLE-COD-PENDING-20260712130719', 4, 'pending_payment', 'pending', 'unfulfilled', 'KWD', NULL, NULL, 3500, 0, 0, 0, 0, 3500, NULL, 0, NULL, 700, 2800, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(8, 'DEMO-DROPSHIP-20260712130720', 4, 'paid', 'paid', 'unfulfilled', 'KWD', NULL, NULL, 4500, 0, 0, 0, 0, 4500, NULL, 0, NULL, 900, 3600, NULL, NULL, '2026-07-12 09:07:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20', NULL),
(9, 'DEMO-CUSTOM-20260712130720', 4, 'paid', 'paid', 'unfulfilled', 'KWD', NULL, NULL, 900, 0, 0, 0, 0, 900, NULL, 0, NULL, 180, 720, NULL, NULL, '2026-07-12 08:07:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_addresses`
--

CREATE TABLE `order_addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `country` varchar(2) NOT NULL,
  `state` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `area` varchar(255) DEFAULT NULL,
  `block` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL,
  `floor` varchar(255) DEFAULT NULL,
  `apartment` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_addresses`
--

INSERT INTO `order_addresses` (`id`, `order_id`, `type`, `recipient_name`, `phone`, `country`, `state`, `city`, `area`, `block`, `street`, `building`, `floor`, `apartment`, `postal_code`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(1, 1, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 2, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(3, 3, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(4, 4, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(5, 5, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(6, 6, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(7, 7, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(8, 8, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(9, 9, 'shipping', 'Demo Customer', '+96599887766', 'KW', 'Al Asimah', 'Kuwait City', 'Salmiya', '7', 'Beach Road', '15', '3', '4', '13001', NULL, NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `order_events`
--

CREATE TABLE `order_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `promotion_id` bigint(20) UNSIGNED DEFAULT NULL,
  `promotion_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot — survives promotion deletion',
  `promotion_discount_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supplier_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(255) DEFAULT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `variant_attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variant_attributes`)),
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price_minor` int(10) UNSIGNED NOT NULL,
  `original_unit_price_minor` int(10) UNSIGNED DEFAULT NULL COMMENT 'Phase 10 v10.8: pre-promotion unit price; null when no promotion applied',
  `line_total_minor` int(10) UNSIGNED NOT NULL,
  `coupon_allocation_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL,
  `commission_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_amount_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vendor_earning_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `supplier_cost_minor` int(10) UNSIGNED DEFAULT NULL,
  `customization_fee_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fulfillment_status` varchar(255) NOT NULL DEFAULT 'unfulfilled',
  `customization_status` varchar(32) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `vendor_id`, `promotion_id`, `promotion_name`, `promotion_discount_minor`, `product_id`, `variant_id`, `supplier_order_id`, `product_name`, `product_sku`, `variant_name`, `variant_attributes`, `quantity`, `unit_price_minor`, `original_unit_price_minor`, `line_total_minor`, `coupon_allocation_minor`, `currency`, `commission_percent`, `commission_amount_minor`, `vendor_earning_minor`, `supplier_cost_minor`, `customization_fee_minor`, `fulfillment_status`, `customization_status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL, 0, 2, NULL, NULL, 'Cotton T-Shirt — Classic Fit', 'DEMO-TSHIRT-001', NULL, NULL, 2, 3500, NULL, 7000, 0, 'KWD', 20.00, 1400, 5600, NULL, 0, 'fulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 2, 1, NULL, NULL, 0, 3, NULL, NULL, 'Stainless Steel Water Bottle', 'DEMO-BOTTLE-001', NULL, NULL, 3, 4750, NULL, 14250, 0, 'KWD', 20.00, 2850, 11400, NULL, 0, 'fulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(3, 3, 1, NULL, NULL, 0, 1, NULL, NULL, 'Wireless Bluetooth Headphones', 'DEMO-HEAD-001', NULL, NULL, 1, 12500, NULL, 12500, 0, 'KWD', 20.00, 2500, 10000, NULL, 0, 'fulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(4, 4, 1, NULL, NULL, 0, 2, NULL, NULL, 'Cotton T-Shirt — Classic Fit', 'DEMO-TSHIRT-001', NULL, NULL, 1, 3500, NULL, 3500, 0, 'KWD', 20.00, 700, 2800, NULL, 0, 'unfulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(5, 5, 1, NULL, NULL, 0, 2, NULL, NULL, 'Cotton T-Shirt — Classic Fit', 'DEMO-TSHIRT-001', NULL, NULL, 1, 3500, NULL, 3500, 0, 'KWD', 20.00, 700, 2800, NULL, 0, 'unfulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(6, 6, 1, NULL, NULL, 0, 2, NULL, NULL, 'Cotton T-Shirt — Classic Fit', 'DEMO-TSHIRT-001', NULL, NULL, 1, 3500, NULL, 3500, 0, 'KWD', 20.00, 700, 2800, NULL, 0, 'fulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(7, 7, 1, NULL, NULL, 0, 2, NULL, NULL, 'Cotton T-Shirt — Classic Fit', 'DEMO-TSHIRT-001', NULL, NULL, 1, 3500, NULL, 3500, 0, 'KWD', 20.00, 700, 2800, NULL, 0, 'unfulfilled', 'pending', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(8, 8, 1, NULL, NULL, 0, 8, NULL, 1, 'LED Desk Lamp — Touch Control (demo dropship)', 'DRP-ALIE-ABCA489F', NULL, NULL, 1, 4500, NULL, 4500, 0, 'KWD', 20.00, 900, 3600, 1200, 0, 'unfulfilled', 'pending', '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(9, 9, 1, NULL, NULL, 0, 9, NULL, NULL, 'Personalized Photo Mug', 'DEMO-CUSTOM-MUG-001', NULL, NULL, 1, 350, NULL, 900, 0, 'KWD', 20.00, 180, 720, NULL, 550, 'unfulfilled', 'proof_uploaded', '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `order_item_customizations`
--

CREATE TABLE `order_item_customizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `field_key` varchar(64) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` varchar(32) NOT NULL,
  `value` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_original_name` varchar(255) DEFAULT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `file_size_bytes` int(10) UNSIGNED DEFAULT NULL,
  `extra_fee_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_item_customizations`
--

INSERT INTO `order_item_customizations` (`id`, `order_item_id`, `field_key`, `field_label`, `field_type`, `value`, `file_path`, `file_original_name`, `file_mime`, `file_size_bytes`, `extra_fee_minor`, `created_at`, `updated_at`) VALUES
(1, 9, 'photo', 'Your photo / logo', 'image', NULL, 'customizations/4/demo-family-photo.png', 'family-photo.png', 'image/png', 70, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(2, 9, 'custom_text', 'Custom text (optional)', 'text', 'Best Dad Ever ❤', NULL, NULL, NULL, NULL, 250, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(3, 9, 'color', 'Mug color', 'color', 'black', NULL, NULL, NULL, NULL, 100, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(4, 9, 'placement', 'Image placement', 'placement', 'wrap', NULL, NULL, NULL, NULL, 200, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `method_slug` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `amount_minor` int(10) UNSIGNED NOT NULL,
  `currency` varchar(3) NOT NULL,
  `refunded_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `external_id` varchar(255) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `failure_reason` text DEFAULT NULL,
  `authorized_at` timestamp NULL DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `payment_method_id`, `method_slug`, `provider`, `status`, `amount_minor`, `currency`, `refunded_minor`, `external_id`, `reference`, `metadata`, `failure_reason`, `authorized_at`, `captured_at`, `failed_at`, `refunded_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'cod', 'cod', 'captured', 7000, 'KWD', 0, NULL, 'COD-DEMO-DELIVERED-01-20260712130719', NULL, NULL, NULL, '2026-06-12 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 2, NULL, 'cod', 'cod', 'captured', 14250, 'KWD', 0, NULL, 'COD-DEMO-DELIVERED-02-20260712130719', NULL, NULL, NULL, '2026-06-19 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(3, 3, NULL, 'cod', 'cod', 'captured', 12500, 'KWD', 0, NULL, 'COD-DEMO-DELIVERED-03-20260712130719', NULL, NULL, NULL, '2026-06-25 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(4, 4, NULL, 'cod', 'cod', 'captured', 3500, 'KWD', 0, NULL, 'COD-DEMO-ACTIONABLE-PAID-20260712130719', NULL, NULL, NULL, '2026-07-11 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(5, 5, NULL, 'online_mock', 'mock', 'captured', 3500, 'KWD', 0, NULL, 'ONLINE_MOCK-DEMO-ACTIONABLE-CONFIRMED-20260712130719', NULL, NULL, NULL, '2026-07-11 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(6, 6, NULL, 'online_mock', 'mock', 'captured', 3500, 'KWD', 0, NULL, 'ONLINE_MOCK-DEMO-ACTIONABLE-SHIPPED-20260712130719', NULL, NULL, NULL, '2026-07-11 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(7, 7, NULL, 'cod', 'cod', 'pending', 3500, 'KWD', 0, NULL, 'COD-DEMO-ACTIONABLE-COD-PENDING-20260712130719', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(8, 8, NULL, 'cod', 'cod', 'captured', 4500, 'KWD', 0, NULL, 'COD-DEMO-DROPSHIP-20260712130720', NULL, NULL, NULL, '2026-07-12 09:07:20', NULL, NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name_translations`)),
  `description` text DEFAULT NULL,
  `description_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description_translations`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `available_at_checkout` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `supported_currencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supported_currencies`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `slug`, `provider`, `name`, `name_translations`, `description`, `description_translations`, `is_active`, `position`, `available_at_checkout`, `config`, `supported_currencies`, `created_at`, `updated_at`) VALUES
(1, 'cod', 'cod', 'Cash on Delivery', '{\"ar\":\"\\u0627\\u0644\\u062f\\u0641\\u0639 \\u0639\\u0646\\u062f \\u0627\\u0644\\u0627\\u0633\\u062a\\u0644\\u0627\\u0645\",\"ur\":\"\\u0688\\u0644\\u06cc\\u0648\\u0631\\u06cc \\u067e\\u0631 \\u0627\\u062f\\u0627\\u0626\\u06cc\\u06af\\u06cc\"}', 'Pay in cash when your order is delivered. No upfront charge.', '{\"ar\":\"\\u0627\\u062f\\u0641\\u0639 \\u0646\\u0642\\u062f\\u064b\\u0627 \\u0639\\u0646\\u062f \\u0627\\u0633\\u062a\\u0644\\u0627\\u0645 \\u0637\\u0644\\u0628\\u0643. \\u0644\\u0627 \\u062a\\u0648\\u062c\\u062f \\u0631\\u0633\\u0648\\u0645 \\u0645\\u0642\\u062f\\u0645\\u0629.\",\"ur\":\"\\u0627\\u067e\\u0646\\u0627 \\u0622\\u0631\\u0688\\u0631 \\u0688\\u0644\\u06cc\\u0648\\u0631 \\u06c1\\u0648\\u0646\\u06d2 \\u067e\\u0631 \\u0646\\u0642\\u062f \\u0627\\u062f\\u0627\\u0626\\u06cc\\u06af\\u06cc \\u06a9\\u0631\\u06cc\\u06ba\\u06d4 \\u06a9\\u0648\\u0626\\u06cc \\u067e\\u06cc\\u0634\\u06af\\u06cc \\u0686\\u0627\\u0631\\u062c \\u0646\\u06c1\\u06cc\\u06ba\\u06d4\"}', 1, 1, 1, NULL, '[\"KWD\",\"AED\"]', '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(2, 'manual_transfer', 'manual_transfer', 'Bank Transfer', '{\"ar\":\"\\u062a\\u062d\\u0648\\u064a\\u0644 \\u0628\\u0646\\u0643\\u064a\",\"ur\":\"\\u0628\\u06cc\\u0646\\u06a9 \\u0679\\u0631\\u0627\\u0646\\u0633\\u0641\\u0631\"}', 'Transfer the order total to our bank account. We confirm receipt within one business day.', '{\"ar\":\"\\u062d\\u0648\\u0651\\u0644 \\u0627\\u0644\\u0645\\u0628\\u0644\\u063a \\u0625\\u0644\\u0649 \\u062d\\u0633\\u0627\\u0628 \\u0627\\u0644\\u0645\\u0646\\u0635\\u0629. \\u0646\\u0624\\u0643\\u062f \\u0627\\u0644\\u0627\\u0633\\u062a\\u0644\\u0627\\u0645 \\u062e\\u0644\\u0627\\u0644 \\u064a\\u0648\\u0645 \\u0639\\u0645\\u0644 \\u0648\\u0627\\u062d\\u062f.\",\"ur\":\"\\u0622\\u0631\\u0688\\u0631 \\u06a9\\u06cc \\u0631\\u0642\\u0645 \\u06c1\\u0645\\u0627\\u0631\\u06d2 \\u0628\\u06cc\\u0646\\u06a9 \\u0627\\u06a9\\u0627\\u0624\\u0646\\u0679 \\u0645\\u06cc\\u06ba \\u0645\\u0646\\u062a\\u0642\\u0644 \\u06a9\\u0631\\u06cc\\u06ba\\u06d4 \\u06c1\\u0645 \\u0627\\u06cc\\u06a9 \\u0648\\u0631\\u06a9\\u0646\\u06af \\u0688\\u06d2 \\u0645\\u06cc\\u06ba \\u062a\\u0635\\u062f\\u06cc\\u0642 \\u06a9\\u0631\\u062a\\u06d2 \\u06c1\\u06cc\\u06ba\\u06d4\"}', 1, 2, 1, NULL, NULL, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(3, 'online_mock', 'online_mock', 'Card / Online (Demo)', '{\"ar\":\"\\u0628\\u0637\\u0627\\u0642\\u0629 \\/ \\u0625\\u0646\\u062a\\u0631\\u0646\\u062a (\\u062a\\u062c\\u0631\\u064a\\u0628\\u064a)\",\"ur\":\"\\u06a9\\u0627\\u0631\\u0688 \\/ \\u0622\\u0646 \\u0644\\u0627\\u0626\\u0646 (\\u0688\\u06cc\\u0645\\u0648)\"}', 'Demo gateway. Real card processing (MyFatoorah / Tap / Stripe) is configured in a future sub-phase.', '{\"ar\":\"\\u0628\\u0648\\u0627\\u0628\\u0629 \\u062a\\u062c\\u0631\\u064a\\u0628\\u064a\\u0629. \\u0633\\u064a\\u062a\\u0645 \\u062a\\u0643\\u0648\\u064a\\u0646 \\u0645\\u0639\\u0627\\u0644\\u062c\\u0629 \\u0627\\u0644\\u0628\\u0637\\u0627\\u0642\\u0627\\u062a \\u0627\\u0644\\u062d\\u0642\\u064a\\u0642\\u064a\\u0629 \\u0641\\u064a \\u0645\\u0631\\u062d\\u0644\\u0629 \\u0641\\u0631\\u0639\\u064a\\u0629 \\u0644\\u0627\\u062d\\u0642\\u0629.\",\"ur\":\"\\u0688\\u06cc\\u0645\\u0648 \\u06af\\u06cc\\u0679 \\u0648\\u06d2\\u06d4 \\u0627\\u0635\\u0644 \\u06a9\\u0627\\u0631\\u0688 \\u067e\\u0631\\u0627\\u0633\\u06cc\\u0633\\u0646\\u06af \\u0645\\u0633\\u062a\\u0642\\u0628\\u0644 \\u06a9\\u06d2 \\u0633\\u0628 \\u0641\\u06cc\\u0632 \\u0645\\u06cc\\u06ba \\u062a\\u0631\\u062a\\u06cc\\u0628 \\u062f\\u06cc \\u062c\\u0627\\u0626\\u06d2 \\u06af\\u06cc\\u06d4\"}', 1, 3, 1, '{\"force_outcome\":\"success\"}', NULL, '2026-07-12 10:07:13', '2026-07-12 10:07:13');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `amount_minor` int(11) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'reports.view', 'web', '2026-07-12 10:07:05', '2026-07-12 10:07:05'),
(2, 'users.view', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(3, 'users.create', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(4, 'users.update', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(5, 'users.delete', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(6, 'roles.view', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(7, 'roles.manage', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(8, 'settings.view', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(9, 'settings.manage', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(10, 'vendors.view', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(11, 'vendors.approve', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(12, 'vendors.suspend', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(13, 'vendor_packages.manage', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(14, 'vendor_subscriptions.manage', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(15, 'products.view', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(16, 'products.create', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(17, 'products.update', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(18, 'products.delete', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(19, 'products.approve', 'web', '2026-07-12 10:07:10', '2026-07-12 10:07:10'),
(20, 'products.publish', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(21, 'products.feature', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(22, 'categories.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(23, 'attributes.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(24, 'services.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(25, 'services.create', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(26, 'services.approve', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(27, 'orders.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(28, 'orders.view.any', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(29, 'orders.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(30, 'orders.confirm', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(31, 'orders.ship', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(32, 'orders.deliver', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(33, 'orders.cancel', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(34, 'orders.refund', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(35, 'orders.export', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(36, 'payments.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(37, 'payments.capture', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(38, 'payments.refund', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(39, 'payment_methods.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(40, 'payouts.approve', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(41, 'commissions.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(42, 'bookings.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(43, 'bookings.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(44, 'reviews.moderate', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(45, 'promotions.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(46, 'support.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(47, 'audit_logs.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(48, 'supplier_platforms.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(49, 'supplier_platforms.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(50, 'supplier_integrations.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(51, 'supplier_integrations.create', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(52, 'supplier_integrations.update', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(53, 'supplier_integrations.delete', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(54, 'supplier_products.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(55, 'supplier_products.create', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(56, 'supplier_products.import', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(57, 'supplier_products.update', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(58, 'supplier_products.delete', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(59, 'supplier_products.map', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(60, 'supplier_products.approve', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(61, 'supplier_products.reject', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(62, 'supplier_orders.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(63, 'supplier_orders.update', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(64, 'customization_fields.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(65, 'customization_fields.manage', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(66, 'customization_proofs.view', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(67, 'customization_proofs.upload', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(68, 'customization_proofs.respond', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11');

-- --------------------------------------------------------

--
-- Table structure for table `personalization_feedback`
--

CREATE TABLE `personalization_feedback` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_key` varchar(64) DEFAULT NULL,
  `feedback_type` varchar(32) NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personalization_preferences`
--

CREATE TABLE `personalization_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `behavioral_personalization_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `guest_merge_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `behavior_tracking_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_reset_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supplier_platform_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name_translations`)),
  `short_description` text DEFAULT NULL,
  `short_description_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`short_description_translations`)),
  `description` longtext DEFAULT NULL,
  `description_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description_translations`)),
  `type` varchar(255) NOT NULL DEFAULT 'simple',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `price_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `compare_at_price_minor` int(10) UNSIGNED DEFAULT NULL,
  `cost_price_minor` int(10) UNSIGNED DEFAULT NULL,
  `supplier_cost_minor` int(10) UNSIGNED DEFAULT NULL,
  `fulfillment_mode` varchar(255) NOT NULL DEFAULT 'vendor_self',
  `estimated_delivery_days` smallint(5) UNSIGNED DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,
  `stock` int(11) NOT NULL DEFAULT 0,
  `weight_grams` int(10) UNSIGNED DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_until` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `views_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sales_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `vendor_id`, `supplier_product_id`, `supplier_platform_id`, `category_id`, `sku`, `slug`, `name`, `name_translations`, `short_description`, `short_description_translations`, `description`, `description_translations`, `type`, `status`, `approved_at`, `approved_by`, `rejection_reason`, `published_at`, `price_minor`, `compare_at_price_minor`, `cost_price_minor`, `supplier_cost_minor`, `fulfillment_mode`, `estimated_delivery_days`, `currency`, `track_stock`, `stock`, `weight_grams`, `featured`, `featured_until`, `meta_title`, `meta_description`, `views_count`, `sales_count`, `rating_avg`, `rating_count`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, NULL, NULL, 1, 'DEMO-HEAD-001', 'wireless-bluetooth-headphones', 'Wireless Bluetooth Headphones', '{\"ar\":\"\\u0633\\u0645\\u0627\\u0639\\u0627\\u062a \\u0644\\u0627\\u0633\\u0644\\u0643\\u064a\\u0629 \\u0628\\u062a\\u0642\\u0646\\u064a\\u0629 \\u0627\\u0644\\u0628\\u0644\\u0648\\u062a\\u0648\\u062b\"}', 'Crisp audio, 20-hour battery, comfortable over-ear fit.', '{\"ar\":\"\\u0633\\u0645\\u0627\\u0639\\u0627\\u062a \\u0644\\u0627\\u0633\\u0644\\u0643\\u064a\\u0629 \\u0639\\u0627\\u0644\\u064a\\u0629 \\u0627\\u0644\\u062c\\u0648\\u062f\\u0629 \\u0645\\u0639 \\u0645\\u064a\\u0643\\u0631\\u0648\\u0641\\u0648\\u0646 \\u0645\\u062f\\u0645\\u062c\\u060c \\u0639\\u0645\\u0631 \\u0628\\u0637\\u0627\\u0631\\u064a\\u0629 \\u0637\\u0648\\u064a\\u0644\\u060c \\u0648\\u0645\\u0646\\u0627\\u0633\\u0628\\u0629 \\u0644\\u0644\\u0627\\u0633\\u062a\\u062e\\u062f\\u0627\\u0645 \\u0627\\u0644\\u064a\\u0648\\u0645\\u064a.\"}', NULL, '{\"ar\":\"\\u0633\\u0645\\u0627\\u0639\\u0627\\u062a \\u0644\\u0627\\u0633\\u0644\\u0643\\u064a\\u0629 \\u0628\\u062a\\u0642\\u0646\\u064a\\u0629 \\u0627\\u0644\\u0628\\u0644\\u0648\\u062a\\u0648\\u062b 5.0 \\u062a\\u0648\\u0641\\u0631 \\u0635\\u0648\\u062a\\u064b\\u0627 \\u0648\\u0627\\u0636\\u062d\\u064b\\u0627 \\u0648\\u0639\\u0645\\u0631 \\u0628\\u0637\\u0627\\u0631\\u064a\\u0629 \\u064a\\u0635\\u0644 \\u0625\\u0644\\u0649 30 \\u0633\\u0627\\u0639\\u0629. \\u062a\\u0623\\u062a\\u064a \\u0645\\u0639 \\u0645\\u064a\\u0643\\u0631\\u0648\\u0641\\u0648\\u0646 \\u0645\\u062f\\u0645\\u062c \\u0644\\u0644\\u0645\\u0643\\u0627\\u0644\\u0645\\u0627\\u062a\\u060c \\u0623\\u0632\\u0631\\u0627\\u0631 \\u062a\\u062d\\u0643\\u0645 \\u0633\\u0647\\u0644\\u0629 \\u0627\\u0644\\u0627\\u0633\\u062a\\u062e\\u062f\\u0627\\u0645\\u060c \\u0648\\u062a\\u0635\\u0645\\u064a\\u0645 \\u0645\\u0631\\u064a\\u062d \\u064a\\u062f\\u0648\\u0645 \\u0644\\u0633\\u0627\\u0639\\u0627\\u062a \\u0637\\u0648\\u064a\\u0644\\u0629 \\u0645\\u0646 \\u0627\\u0644\\u0627\\u0633\\u062a\\u0645\\u0627\\u0639.\\n\\n\\u0627\\u0644\\u0645\\u064a\\u0632\\u0627\\u062a \\u0627\\u0644\\u0631\\u0626\\u064a\\u0633\\u064a\\u0629:\\n\\u2022 \\u062a\\u0642\\u0646\\u064a\\u0629 \\u0628\\u0644\\u0648\\u062a\\u0648\\u062b 5.0\\n\\u2022 \\u0639\\u0645\\u0631 \\u0628\\u0637\\u0627\\u0631\\u064a\\u0629 \\u062d\\u062a\\u0649 30 \\u0633\\u0627\\u0639\\u0629\\n\\u2022 \\u0645\\u064a\\u0643\\u0631\\u0648\\u0641\\u0648\\u0646 \\u0645\\u062f\\u0645\\u062c\\n\\u2022 \\u062a\\u0635\\u0645\\u064a\\u0645 \\u0642\\u0627\\u0628\\u0644 \\u0644\\u0644\\u0637\\u064a\"}', 'simple', 'published', '2026-07-12 10:07:18', NULL, NULL, '2026-07-12 10:07:18', 12500, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 1, 25, NULL, 1, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:18', '2026-07-12 10:07:21', NULL),
(2, 1, NULL, NULL, 5, 'DEMO-TSHIRT-001', 'cotton-t-shirt-classic-fit', 'Cotton T-Shirt — Classic Fit', '{\"ar\":\"\\u0642\\u0645\\u064a\\u0635 \\u0642\\u0637\\u0646\\u064a \\u2014 \\u0642\\u0635\\u0629 \\u0643\\u0644\\u0627\\u0633\\u064a\\u0643\\u064a\\u0629\"}', '100% combed cotton, pre-shrunk, sizes S to XXL.', '{\"ar\":\"\\u0642\\u0645\\u064a\\u0635 \\u0642\\u0637\\u0646\\u064a \\u0661\\u0660\\u0660\\u066a\\u060c \\u0642\\u0635\\u0629 \\u0643\\u0644\\u0627\\u0633\\u064a\\u0643\\u064a\\u0629 \\u0645\\u0631\\u064a\\u062d\\u0629 \\u0645\\u0646\\u0627\\u0633\\u0628\\u0629 \\u0644\\u0644\\u0627\\u0633\\u062a\\u062e\\u062f\\u0627\\u0645 \\u0627\\u0644\\u064a\\u0648\\u0645\\u064a.\"}', NULL, '{\"ar\":\"\\u0642\\u0645\\u064a\\u0635 \\u0645\\u0635\\u0646\\u0648\\u0639 \\u0645\\u0646 \\u0627\\u0644\\u0642\\u0637\\u0646 \\u0627\\u0644\\u062e\\u0627\\u0644\\u0635 \\u0661\\u0660\\u0660\\u066a\\u060c \\u0628\\u0642\\u0635\\u0629 \\u0643\\u0644\\u0627\\u0633\\u064a\\u0643\\u064a\\u0629 \\u0645\\u0631\\u064a\\u062d\\u0629. \\u064a\\u062a\\u0645\\u064a\\u0632 \\u0628\\u0646\\u0639\\u0648\\u0645\\u0629 \\u0627\\u0644\\u0645\\u0644\\u0645\\u0633 \\u0648\\u062c\\u0648\\u062f\\u0629 \\u0627\\u0644\\u062e\\u064a\\u0627\\u0637\\u0629\\u060c \\u0648\\u0645\\u0646\\u0627\\u0633\\u0628 \\u0644\\u0645\\u062e\\u062a\\u0644\\u0641 \\u0627\\u0644\\u0645\\u0646\\u0627\\u0633\\u0628\\u0627\\u062a \\u0627\\u0644\\u064a\\u0648\\u0645\\u064a\\u0629.\\n\\n\\u0627\\u0644\\u0645\\u0648\\u0627\\u0635\\u0641\\u0627\\u062a:\\n\\u2022 \\u0642\\u0637\\u0646 \\u062e\\u0627\\u0644\\u0635 \\u0661\\u0660\\u0660\\u066a\\n\\u2022 \\u0642\\u0635\\u0629 \\u0643\\u0644\\u0627\\u0633\\u064a\\u0643\\u064a\\u0629\\n\\u2022 \\u0645\\u062a\\u0648\\u0641\\u0631 \\u0628\\u0639\\u062f\\u0629 \\u0645\\u0642\\u0627\\u0633\\u0627\\u062a\\n\\u2022 \\u0642\\u0627\\u0628\\u0644 \\u0644\\u0644\\u063a\\u0633\\u0644 \\u0627\\u0644\\u0622\\u0644\\u064a\"}', 'simple', 'published', '2026-07-12 10:07:18', NULL, NULL, '2026-07-12 10:07:18', 3500, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 1, 80, NULL, 0, NULL, NULL, NULL, 0, 0, 5.00, 1, '2026-07-12 10:07:18', '2026-07-12 10:07:21', NULL),
(3, 1, NULL, NULL, NULL, 'DEMO-BOTTLE-001', 'stainless-steel-water-bottle', 'Stainless Steel Water Bottle', '{\"ar\":\"\\u0632\\u062c\\u0627\\u062c\\u0629 \\u0645\\u0627\\u0621 \\u0645\\u0646 \\u0627\\u0644\\u0641\\u0648\\u0644\\u0627\\u0630 \\u0627\\u0644\\u0645\\u0642\\u0627\\u0648\\u0645 \\u0644\\u0644\\u0635\\u062f\\u0623\"}', 'Double-walled, keeps drinks cold 24 hours.', '{\"ar\":\"\\u0632\\u062c\\u0627\\u062c\\u0629 \\u0645\\u0627\\u0621 \\u0645\\u0639\\u0632\\u0648\\u0644\\u0629 \\u0645\\u0646 \\u0627\\u0644\\u0641\\u0648\\u0644\\u0627\\u0630 \\u0627\\u0644\\u0645\\u0642\\u0627\\u0648\\u0645 \\u0644\\u0644\\u0635\\u062f\\u0623 \\u062a\\u062d\\u0627\\u0641\\u0638 \\u0639\\u0644\\u0649 \\u062f\\u0631\\u062c\\u0629 \\u062d\\u0631\\u0627\\u0631\\u0629 \\u0627\\u0644\\u0634\\u0631\\u0627\\u0628 \\u0644\\u0633\\u0627\\u0639\\u0627\\u062a.\"}', NULL, '{\"ar\":\"\\u0632\\u062c\\u0627\\u062c\\u0629 \\u0645\\u0627\\u0621 \\u0639\\u0627\\u0644\\u064a\\u0629 \\u0627\\u0644\\u062c\\u0648\\u062f\\u0629 \\u0645\\u0635\\u0646\\u0648\\u0639\\u0629 \\u0645\\u0646 \\u0627\\u0644\\u0641\\u0648\\u0644\\u0627\\u0630 \\u0627\\u0644\\u0645\\u0642\\u0627\\u0648\\u0645 \\u0644\\u0644\\u0635\\u062f\\u0623 \\u0628\\u0637\\u0628\\u0642\\u0629 \\u0639\\u0627\\u0632\\u0644\\u0629 \\u0645\\u0632\\u062f\\u0648\\u062c\\u0629. \\u062a\\u062d\\u0627\\u0641\\u0638 \\u0639\\u0644\\u0649 \\u062f\\u0631\\u062c\\u0629 \\u062d\\u0631\\u0627\\u0631\\u0629 \\u0627\\u0644\\u0634\\u0631\\u0627\\u0628 \\u0627\\u0644\\u0628\\u0627\\u0631\\u062f\\u0629 \\u0644\\u0645\\u062f\\u0629 \\u0662\\u0664 \\u0633\\u0627\\u0639\\u0629 \\u0648\\u0627\\u0644\\u0633\\u0627\\u062e\\u0646\\u0629 \\u0644\\u0645\\u062f\\u0629 \\u0661\\u0662 \\u0633\\u0627\\u0639\\u0629.\\n\\n\\u0627\\u0644\\u0645\\u064a\\u0632\\u0627\\u062a:\\n\\u2022 \\u0641\\u0648\\u0644\\u0627\\u0630 \\u0645\\u0642\\u0627\\u0648\\u0645 \\u0644\\u0644\\u0635\\u062f\\u0623 \\u0645\\u0646 \\u0627\\u0644\\u062f\\u0631\\u062c\\u0629 \\u0627\\u0644\\u063a\\u0630\\u0627\\u0626\\u064a\\u0629\\n\\u2022 \\u0639\\u0632\\u0644 \\u0645\\u0632\\u062f\\u0648\\u062c\\n\\u2022 \\u062e\\u0627\\u0644\\u064a\\u0629 \\u0645\\u0646 \\u0645\\u0627\\u062f\\u0629 BPA\\n\\u2022 \\u0633\\u0639\\u0629 \\u0667\\u0665\\u0660 \\u0645\\u0644\"}', 'simple', 'published', '2026-07-12 10:07:18', NULL, NULL, '2026-07-12 10:07:18', 4750, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 1, 50, NULL, 1, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:18', '2026-07-12 10:07:21', NULL),
(4, 1, NULL, NULL, NULL, 'DEMO-DRAFT-001', 'demo-draft-product', 'Draft Product (vendor still editing)', NULL, NULL, NULL, NULL, NULL, 'simple', 'draft', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 1, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:18', '2026-07-12 10:07:18', NULL),
(5, 1, NULL, NULL, NULL, 'DEMO-PENDING-001', 'demo-pending-review-product', 'Pending Review Product', NULL, 'Awaiting admin approval.', NULL, NULL, NULL, 'simple', 'pending_review', NULL, NULL, NULL, NULL, 2500, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 1, 10, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(6, 2, NULL, NULL, 5, 'CG-TOWEL-001', 'handwoven-beach-towel', 'Handwoven Beach Towel', '{\"ar\":\"\\u0645\\u0646\\u0634\\u0641\\u0629 \\u0634\\u0627\\u0637\\u0626 \\u0645\\u0646\\u0633\\u0648\\u062c\\u0629 \\u064a\\u062f\\u0648\\u064a\\u064b\\u0627\"}', 'Soft cotton, traditional Gulf weave, generously sized.', '{\"ar\":\"\\u0645\\u0646\\u0634\\u0641\\u0629 \\u0634\\u0627\\u0637\\u0626 \\u0645\\u0646\\u0633\\u0648\\u062c\\u0629 \\u064a\\u062f\\u0648\\u064a\\u064b\\u0627 \\u0645\\u0646 \\u0627\\u0644\\u0642\\u0637\\u0646 \\u0627\\u0644\\u0645\\u0635\\u0631\\u064a\\u060c \\u062e\\u0641\\u064a\\u0641\\u0629 \\u0627\\u0644\\u0648\\u0632\\u0646 \\u0648\\u0633\\u0631\\u064a\\u0639\\u0629 \\u0627\\u0644\\u062c\\u0641\\u0627\\u0641.\"}', NULL, '{\"ar\":\"\\u0645\\u0646\\u0634\\u0641\\u0629 \\u0634\\u0627\\u0637\\u0626 \\u0641\\u0627\\u062e\\u0631\\u0629 \\u0645\\u0646\\u0633\\u0648\\u062c\\u0629 \\u064a\\u062f\\u0648\\u064a\\u064b\\u0627 \\u0645\\u0646 \\u0627\\u0644\\u0642\\u0637\\u0646 \\u0627\\u0644\\u0645\\u0635\\u0631\\u064a \\u0639\\u0627\\u0644\\u064a \\u0627\\u0644\\u062c\\u0648\\u062f\\u0629. \\u062e\\u0641\\u064a\\u0641\\u0629 \\u0627\\u0644\\u0648\\u0632\\u0646 \\u0648\\u0633\\u0631\\u064a\\u0639\\u0629 \\u0627\\u0644\\u062c\\u0641\\u0627\\u0641\\u060c \\u0645\\u0639 \\u062a\\u0635\\u0645\\u064a\\u0645\\u0627\\u062a \\u0639\\u0635\\u0631\\u064a\\u0629 \\u062a\\u062c\\u0645\\u0639 \\u0628\\u064a\\u0646 \\u0627\\u0644\\u0623\\u0646\\u0627\\u0642\\u0629 \\u0648\\u0627\\u0644\\u0648\\u0638\\u064a\\u0641\\u064a\\u0629.\\n\\n\\u0627\\u0644\\u0645\\u0645\\u064a\\u0632\\u0627\\u062a:\\n\\u2022 \\u0642\\u0637\\u0646 \\u0645\\u0635\\u0631\\u064a \\u0661\\u0660\\u0660\\u066a\\n\\u2022 \\u0645\\u0646\\u0633\\u0648\\u062c\\u0629 \\u064a\\u062f\\u0648\\u064a\\u064b\\u0627\\n\\u2022 \\u062e\\u0641\\u064a\\u0641\\u0629 \\u0648\\u0633\\u0631\\u064a\\u0639\\u0629 \\u0627\\u0644\\u062c\\u0641\\u0627\\u0641\\n\\u2022 \\u0645\\u0642\\u0627\\u0633 \\u0643\\u0628\\u064a\\u0631 \\u0661\\u0667\\u0660\\u00d7\\u0669\\u0660 \\u0633\\u0645\"}', 'simple', 'published', NULL, NULL, NULL, '2026-07-12 10:07:19', 6500, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 1, 30, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:21', NULL),
(7, 1, 2, 1, 1, 'DRP-ALIE-5B8FE03E', 'usb-c-fast-charging-cable-2m', 'USB-C Fast Charging Cable (2m)', NULL, NULL, NULL, 'Demo dropshipping product. Awaiting admin approval.', NULL, 'dropship', 'pending_review', NULL, NULL, NULL, NULL, 1500, NULL, 200, 200, 'dropship_manual', 14, 'KWD', 1, 50, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:19', NULL),
(8, 1, 3, 1, 1, 'DRP-ALIE-ABCA489F', 'led-desk-lamp-touch-control-demo-dropship', 'LED Desk Lamp — Touch Control (demo dropship)', NULL, NULL, NULL, 'Demo published dropshipping product. Add to cart and check out to test the supplier order flow.', NULL, 'dropship', 'published', '2026-07-12 10:07:20', 1, NULL, '2026-07-12 10:07:20', 4500, NULL, 1200, 1200, 'dropship_manual', 16, 'KWD', 1, 30, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20', NULL),
(9, 1, NULL, NULL, NULL, 'DEMO-CUSTOM-MUG-001', 'demo-custom-mug', 'Personalized Photo Mug', NULL, '11oz ceramic mug — upload your photo, choose a color and font.', NULL, 'High-quality ceramic mug printed with your design. Dishwasher and microwave safe.', NULL, 'custom', 'published', NULL, NULL, NULL, '2026-07-12 10:07:20', 350, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20', NULL),
(10, 1, NULL, NULL, NULL, 'DEMO-CUSTOM-TSHIRT-001', 'demo-custom-tshirt', 'Custom Printed T-Shirt', NULL, 'Cotton T-shirt — upload your design, pick size + color + font.', NULL, 'Premium ringspun cotton. DTG-printed with your design — vibrant and durable.', NULL, 'custom', 'published', NULL, NULL, NULL, '2026-07-12 10:07:20', 800, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20', NULL),
(11, 1, NULL, NULL, NULL, 'SVC-DEMO-DOCTOR-001', 'demo-doctor-consultation', 'General Doctor Consultation', NULL, NULL, NULL, '30-minute general consultation with a licensed physician. Suitable for non-emergency concerns, prescription refills, and follow-up visits.\n\nBring your previous medical records if any.', NULL, 'service', 'published', NULL, NULL, NULL, NULL, 15000, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:20', '2026-07-12 10:07:20', NULL),
(12, 2, NULL, NULL, NULL, 'SVC-DEMO-AC-CLEAN-001', 'demo-home-ac-cleaning', 'Home AC Deep Cleaning', NULL, NULL, NULL, 'Professional split AC unit deep cleaning at your home. Includes filter wash, coil cleaning, and drain pipe flush. Approximately 90 minutes per unit.\n\nPrice is per single unit; additional units quoted on site.', NULL, 'service', 'published', NULL, NULL, NULL, NULL, 12500, NULL, NULL, NULL, 'vendor_self', NULL, 'KWD', 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0.00, 0, '2026-07-12 10:07:21', '2026-07-12 10:07:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_attribute_value`
--

CREATE TABLE `product_attribute_value` (
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_value_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_customization_fields`
--

CREATE TABLE `product_customization_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(64) NOT NULL,
  `label` varchar(255) NOT NULL,
  `label_translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`label_translations`)),
  `type` varchar(32) NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `allowed_file_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_file_types`)),
  `max_file_size_kb` int(10) UNSIGNED DEFAULT NULL,
  `max_text_length` int(10) UNSIGNED DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `extra_fee_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `placeholder` varchar(255) DEFAULT NULL,
  `helper_text` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_customization_fields`
--

INSERT INTO `product_customization_fields` (`id`, `product_id`, `key`, `label`, `label_translations`, `type`, `required`, `sort_order`, `allowed_file_types`, `max_file_size_kb`, `max_text_length`, `options`, `extra_fee_minor`, `placeholder`, `helper_text`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 9, 'photo', 'Your photo / logo', NULL, 'image', 1, 1, '[\"jpg\",\"jpeg\",\"png\",\"webp\"]', 5120, NULL, NULL, 0, NULL, 'High-resolution image works best (min 1000x1000px).', 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(2, 9, 'custom_text', 'Custom text (optional)', NULL, 'text', 0, 2, NULL, NULL, 30, NULL, 250, 'e.g. Happy Birthday Mom!', NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(3, 9, 'color', 'Mug color', NULL, 'color', 1, 3, NULL, NULL, NULL, '[{\"value\":\"white\",\"label\":\"White\",\"extra_fee\":0},{\"value\":\"black\",\"label\":\"Black\",\"extra_fee\":100},{\"value\":\"blue\",\"label\":\"Blue\",\"extra_fee\":100}]', 0, NULL, NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(4, 9, 'placement', 'Image placement', NULL, 'placement', 1, 4, NULL, NULL, NULL, '[{\"value\":\"front\",\"label\":\"Front only\"},{\"value\":\"wrap\",\"label\":\"Wrap-around\",\"extra_fee\":200}]', 0, NULL, NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(5, 10, 'design', 'Your design', NULL, 'image', 1, 1, '[\"jpg\",\"jpeg\",\"png\",\"webp\",\"svg\",\"pdf\"]', 10240, NULL, NULL, 0, NULL, 'PNG with transparent background recommended.', 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(6, 10, 'size', 'Size', NULL, 'size', 1, 2, NULL, NULL, NULL, '[{\"value\":\"S\",\"label\":\"Small\"},{\"value\":\"M\",\"label\":\"Medium\"},{\"value\":\"L\",\"label\":\"Large\"},{\"value\":\"XL\",\"label\":\"Extra Large\",\"extra_fee\":100},{\"value\":\"XXL\",\"label\":\"Double XL\",\"extra_fee\":150}]', 0, NULL, NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(7, 10, 'color', 'Shirt color', NULL, 'color', 1, 3, NULL, NULL, NULL, '[{\"value\":\"white\",\"label\":\"White\"},{\"value\":\"black\",\"label\":\"Black\"},{\"value\":\"navy\",\"label\":\"Navy\"}]', 0, NULL, NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(8, 10, 'text', 'Custom text under design (optional)', NULL, 'text', 0, 4, NULL, NULL, 40, NULL, 300, 'Team name, slogan, etc.', NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20'),
(9, 10, 'font', 'Font for the text', NULL, 'font', 0, 5, NULL, NULL, NULL, '[{\"value\":\"sans\",\"label\":\"Modern Sans\"},{\"value\":\"serif\",\"label\":\"Classic Serif\"},{\"value\":\"script\",\"label\":\"Handwritten Script\",\"extra_fee\":50}]', 0, NULL, NULL, 1, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `variant_id`, `path`, `alt_text`, `position`, `is_primary`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'products/demo/wireless-bluetooth-headphones.svg', 'Wireless Bluetooth Headphones', 1, 1, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(2, 2, NULL, 'products/demo/cotton-t-shirt-classic-fit.svg', 'Cotton T-Shirt — Classic Fit', 1, 1, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(3, 3, NULL, 'products/demo/stainless-steel-water-bottle.svg', 'Stainless Steel Water Bottle', 1, 1, '2026-07-12 10:07:18', '2026-07-12 10:07:18'),
(4, 6, NULL, 'products/demo/handwoven-beach-towel.svg', 'Handwoven Beach Towel', 1, 1, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `product_pair_stats`
--

CREATE TABLE `product_pair_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_a_id` bigint(20) UNSIGNED NOT NULL,
  `product_b_id` bigint(20) UNSIGNED NOT NULL,
  `pair_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `distinct_customer_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `first_seen_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_recommendations`
--

CREATE TABLE `product_recommendations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `recommended_product_id` bigint(20) UNSIGNED NOT NULL,
  `recommendation_type` varchar(24) NOT NULL,
  `score` double NOT NULL DEFAULT 0,
  `evidence_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `confidence` double DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `vendor_response` text DEFAULT NULL,
  `vendor_responded_at` timestamp NULL DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `order_item_id`, `rating`, `title`, `body`, `status`, `is_verified_purchase`, `rejection_reason`, `vendor_response`, `vendor_responded_at`, `images`, `approved_at`, `rejected_at`, `created_at`, `updated_at`) VALUES
(1, 2, 4, 1, 5, 'Excellent quality', 'Arrived quickly and exactly as described. Recommended.', 'approved', 1, NULL, NULL, NULL, NULL, '2026-07-10 10:07:19', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 3, 4, NULL, 5, 'Excellent quality!', 'Great product, exactly as described. Fast shipping.', 'approved', 1, NULL, 'Thank you for your kind words! We are delighted you enjoyed it.', '2026-07-12 10:07:21', NULL, '2026-07-12 10:07:21', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(3, 6, 4, NULL, 4, 'Good but could be better', 'Solid build quality. Packaging could be improved.', 'approved', 1, NULL, NULL, NULL, NULL, '2026-07-12 10:07:21', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `product_translations`
--

CREATE TABLE `product_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `locale` varchar(8) NOT NULL,
  `field` varchar(40) NOT NULL,
  `value` text DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'missing',
  `source_provenance` varchar(24) NOT NULL DEFAULT 'manual',
  `source_checksum` char(64) DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `translated_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_translations`
--

INSERT INTO `product_translations` (`id`, `product_id`, `locale`, `field`, `value`, `status`, `source_provenance`, `source_checksum`, `reviewed_by`, `translated_at`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'ar', 'name', 'سماعات لاسلكية بتقنية البلوتوث', 'approved', 'manual', 'fbc382076f75f0b7de857663ee876cf9c1ba12e45744ce3df284003aca611baf', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 1, 'ar', 'short_description', 'سماعات لاسلكية عالية الجودة مع ميكروفون مدمج، عمر بطارية طويل، ومناسبة للاستخدام اليومي.', 'approved', 'manual', 'c0eec14549fe8a3cf03be5aa6634eb7cbcdd7ec54cc4420b857dab7e0bbdf8a3', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(3, 1, 'ar', 'description', 'سماعات لاسلكية بتقنية البلوتوث 5.0 توفر صوتًا واضحًا وعمر بطارية يصل إلى 30 ساعة. تأتي مع ميكروفون مدمج للمكالمات، أزرار تحكم سهلة الاستخدام، وتصميم مريح يدوم لساعات طويلة من الاستماع.\n\nالميزات الرئيسية:\n• تقنية بلوتوث 5.0\n• عمر بطارية حتى 30 ساعة\n• ميكروفون مدمج\n• تصميم قابل للطي', 'approved', 'manual', NULL, NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(4, 2, 'ar', 'name', 'قميص قطني — قصة كلاسيكية', 'approved', 'manual', 'c1bb33d6a2a943740c243772e34b5e4bc7295b2a3d91f60a13f8354fdbd5cf2d', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(5, 2, 'ar', 'short_description', 'قميص قطني ١٠٠٪، قصة كلاسيكية مريحة مناسبة للاستخدام اليومي.', 'approved', 'manual', '45f6ab4d5d9e37a242a1575d50ae2d2e84baf9c129bd0e605338e1f08bf1da7a', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(6, 2, 'ar', 'description', 'قميص مصنوع من القطن الخالص ١٠٠٪، بقصة كلاسيكية مريحة. يتميز بنعومة الملمس وجودة الخياطة، ومناسب لمختلف المناسبات اليومية.\n\nالمواصفات:\n• قطن خالص ١٠٠٪\n• قصة كلاسيكية\n• متوفر بعدة مقاسات\n• قابل للغسل الآلي', 'approved', 'manual', NULL, NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(7, 3, 'ar', 'name', 'زجاجة ماء من الفولاذ المقاوم للصدأ', 'approved', 'manual', '62eb7a8c733d0d713e14f8a38e81efc37d9b14e52cf122b03fd1cba81996480e', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(8, 3, 'ar', 'short_description', 'زجاجة ماء معزولة من الفولاذ المقاوم للصدأ تحافظ على درجة حرارة الشراب لساعات.', 'approved', 'manual', '93a207000203ac225ca1026d28bd4a7ce65c4fafd3c3da121e6b6c8fade6de6b', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(9, 3, 'ar', 'description', 'زجاجة ماء عالية الجودة مصنوعة من الفولاذ المقاوم للصدأ بطبقة عازلة مزدوجة. تحافظ على درجة حرارة الشراب الباردة لمدة ٢٤ ساعة والساخنة لمدة ١٢ ساعة.\n\nالميزات:\n• فولاذ مقاوم للصدأ من الدرجة الغذائية\n• عزل مزدوج\n• خالية من مادة BPA\n• سعة ٧٥٠ مل', 'approved', 'manual', NULL, NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(10, 6, 'ar', 'name', 'منشفة شاطئ منسوجة يدويًا', 'approved', 'manual', '6fd7c3cc1f09755b4ed1fda9143657a1b48733c4627e874cdfb20b9c9e0c2279', NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(11, 6, 'ar', 'short_description', 'منشفة شاطئ منسوجة يدويًا من القطن المصري، خفيفة الوزن وسريعة الجفاف.', 'approved', 'manual', 'd65ed1a317be309415d55fd692fabf5bfa3ef3ef4086bbd93229353631411fa0', NULL, '2026-07-12 10:07:22', '2026-07-12 10:07:22', '2026-07-12 10:07:22', '2026-07-12 10:07:22'),
(12, 6, 'ar', 'description', 'منشفة شاطئ فاخرة منسوجة يدويًا من القطن المصري عالي الجودة. خفيفة الوزن وسريعة الجفاف، مع تصميمات عصرية تجمع بين الأناقة والوظيفية.\n\nالمميزات:\n• قطن مصري ١٠٠٪\n• منسوجة يدويًا\n• خفيفة وسريعة الجفاف\n• مقاس كبير ١٧٠×٩٠ سم', 'approved', 'manual', NULL, NULL, '2026-07-12 10:07:22', '2026-07-12 10:07:22', '2026-07-12 10:07:22', '2026-07-12 10:07:22');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `price_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `compare_at_price_minor` int(10) UNSIGNED DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `stock` int(11) NOT NULL DEFAULT 0,
  `attribute_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attribute_values`)),
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `promotion_type` varchar(30) NOT NULL,
  `discount_type` varchar(20) NOT NULL,
  `discount_value` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL,
  `per_customer_limit` int(10) UNSIGNED DEFAULT NULL,
  `min_order_minor` int(10) UNSIGNED DEFAULT NULL,
  `max_discount_minor` int(10) UNSIGNED DEFAULT NULL,
  `approval_status` varchar(20) NOT NULL DEFAULT 'approved',
  `rejection_reason` text DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `vendor_id`, `created_by`, `title`, `slug`, `description`, `promotion_type`, `discount_type`, `discount_value`, `starts_at`, `ends_at`, `is_active`, `usage_limit`, `per_customer_limit`, `min_order_minor`, `max_discount_minor`, `approval_status`, `rejection_reason`, `currency`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'Summer Flash Sale — 20% off all products', 'phase9-summer-flash-sale', 'Platform-wide flash sale running this week.', 'flash_sale', 'percentage', 20, '2026-07-11 10:07:21', '2026-07-19 10:07:21', 1, NULL, NULL, NULL, NULL, 'approved', NULL, 'KWD', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 1, 1, 'Demo Trading Co. — Deal of the Day', 'phase9-vendor1-deal-of-day', 'Vendor-specific promotion approved by admin.', 'deal_of_day', 'percentage', 15, '2026-07-12 09:07:21', '2026-07-13 10:07:21', 1, NULL, NULL, NULL, NULL, 'approved', NULL, 'KWD', '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `promotion_targets`
--

CREATE TABLE `promotion_targets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `promotion_id` bigint(20) UNSIGNED NOT NULL,
  `targetable_type` varchar(50) NOT NULL,
  `targetable_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recommendation_events`
--

CREATE TABLE `recommendation_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(24) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `recommended_product_id` bigint(20) UNSIGNED NOT NULL,
  `recommendation_type` varchar(24) NOT NULL,
  `locale` varchar(8) DEFAULT NULL,
  `device_category` varchar(16) DEFAULT NULL,
  `session_token` char(64) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'web', '2026-07-12 10:07:05', '2026-07-12 10:07:05'),
(2, 'admin_staff', 'web', '2026-07-12 10:07:05', '2026-07-12 10:07:05'),
(3, 'vendor', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11'),
(4, 'customer', 'web', '2026-07-12 10:07:11', '2026-07-12 10:07:11');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 1),
(2, 2),
(3, 1),
(3, 2),
(4, 1),
(4, 2),
(5, 1),
(6, 1),
(6, 2),
(7, 1),
(8, 1),
(8, 2),
(9, 1),
(10, 1),
(10, 2),
(11, 1),
(11, 2),
(12, 1),
(12, 2),
(13, 1),
(13, 2),
(14, 1),
(15, 1),
(15, 2),
(15, 3),
(15, 4),
(16, 1),
(16, 2),
(16, 3),
(17, 1),
(17, 2),
(17, 3),
(18, 1),
(18, 2),
(19, 1),
(19, 2),
(20, 1),
(20, 2),
(21, 1),
(21, 2),
(22, 1),
(22, 2),
(23, 1),
(23, 2),
(24, 1),
(24, 2),
(24, 3),
(24, 4),
(25, 1),
(25, 2),
(25, 3),
(26, 1),
(26, 2),
(27, 1),
(27, 2),
(27, 3),
(27, 4),
(28, 1),
(28, 2),
(29, 1),
(29, 2),
(30, 1),
(30, 2),
(30, 3),
(31, 1),
(31, 2),
(31, 3),
(32, 1),
(32, 2),
(32, 3),
(33, 1),
(33, 2),
(34, 1),
(34, 2),
(35, 1),
(35, 2),
(36, 1),
(36, 2),
(36, 3),
(37, 1),
(37, 2),
(38, 1),
(38, 2),
(39, 1),
(39, 2),
(40, 1),
(41, 1),
(42, 1),
(42, 2),
(42, 3),
(42, 4),
(43, 1),
(43, 2),
(44, 1),
(44, 2),
(44, 3),
(45, 1),
(45, 2),
(46, 1),
(46, 2),
(47, 1),
(47, 2),
(48, 1),
(48, 2),
(48, 3),
(49, 1),
(49, 2),
(50, 1),
(50, 2),
(50, 3),
(51, 1),
(51, 2),
(51, 3),
(52, 1),
(52, 2),
(52, 3),
(53, 1),
(53, 2),
(53, 3),
(54, 1),
(54, 2),
(54, 3),
(55, 1),
(55, 2),
(55, 3),
(56, 1),
(56, 2),
(56, 3),
(57, 1),
(57, 2),
(57, 3),
(58, 1),
(58, 2),
(58, 3),
(59, 1),
(59, 2),
(59, 3),
(60, 1),
(60, 2),
(61, 1),
(61, 2),
(62, 1),
(62, 2),
(62, 3),
(63, 1),
(63, 2),
(63, 3),
(64, 1),
(64, 2),
(64, 3),
(65, 1),
(65, 2),
(65, 3),
(66, 1),
(66, 2),
(66, 3),
(67, 1),
(67, 2),
(67, 3),
(68, 1),
(68, 2);

-- --------------------------------------------------------

--
-- Table structure for table `search_queries`
--

CREATE TABLE `search_queries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `query` varchar(100) NOT NULL,
  `locale` varchar(8) NOT NULL,
  `search_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_result_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_searched_at` timestamp NULL DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_synonyms`
--

CREATE TABLE `search_synonyms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `locale` varchar(8) NOT NULL,
  `term` varchar(80) NOT NULL,
  `synonym` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_availabilities`
--

CREATE TABLE `service_availabilities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_provider_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `max_bookings_per_slot` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_availabilities`
--

INSERT INTO `service_availabilities` (`id`, `service_provider_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration_minutes`, `max_bookings_per_slot`, `break_start_time`, `break_end_time`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 1, 2, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(3, 1, 3, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(4, 1, 4, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(5, 1, 5, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(6, 1, 6, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(7, 2, 1, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(8, 2, 2, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(9, 2, 3, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(10, 2, 4, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(11, 2, 5, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(12, 2, 6, '10:00:00', '20:00:00', 30, 1, '13:00:00', '14:00:00', 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `service_blocked_dates`
--

CREATE TABLE `service_blocked_dates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_provider_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_bookings`
--

CREATE TABLE `service_bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `number` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `service_provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `booked_for_date` date NOT NULL,
  `booked_for_time` time NOT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL,
  `location_mode` varchar(32) NOT NULL,
  `price_minor` int(10) UNSIGNED NOT NULL,
  `currency` varchar(8) NOT NULL,
  `service_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`service_address`)),
  `status` varchar(32) NOT NULL,
  `customer_notes` text DEFAULT NULL,
  `vendor_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_bookings`
--

INSERT INTO `service_bookings` (`id`, `number`, `user_id`, `vendor_id`, `product_id`, `service_provider_id`, `order_id`, `booked_for_date`, `booked_for_time`, `duration_minutes`, `location_mode`, `price_minor`, `currency`, `service_address`, `status`, `customer_notes`, `vendor_notes`, `rejection_reason`, `confirmed_at`, `accepted_at`, `completed_at`, `cancelled_at`, `created_at`, `updated_at`) VALUES
(1, 'SVC-DEMO-20260713-0001', 4, 1, 11, 1, NULL, '2026-07-13', '10:00:00', 30, 'provider_location', 15000, 'KWD', NULL, 'confirmed', 'First-time visit — annual check-up.', NULL, NULL, '2026-07-12 10:07:21', NULL, NULL, NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `service_details`
--

CREATE TABLE `service_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `service_type` varchar(32) NOT NULL,
  `location_mode` varchar(32) NOT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `service_area_text` varchar(500) DEFAULT NULL,
  `min_lead_time_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `max_advance_days` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `allow_customer_provider_pick` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_details`
--

INSERT INTO `service_details` (`id`, `product_id`, `service_type`, `location_mode`, `duration_minutes`, `service_area_text`, `min_lead_time_minutes`, `max_advance_days`, `allow_customer_provider_pick`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 11, 'consultation', 'provider_location', 30, 'Kuwait City, Salmiya, Hawalli', 60, 30, 1, 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 12, 'home_visit', 'customer_location', 90, 'All Kuwait', 120, 14, 0, 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `service_providers`
--

CREATE TABLE `service_providers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `qualification` varchar(500) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_providers`
--

INSERT INTO `service_providers` (`id`, `vendor_id`, `name`, `slug`, `email`, `phone`, `bio`, `specialization`, `qualification`, `profile_image_path`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Dr. Sarah Ahmed', 'dr-sarah-ahmed', 'sarah.ahmed@example.com', NULL, 'Board-certified general physician with a focus on family medicine and preventative care.', 'General Medicine', 'MBBS, MD — 12 years experience', NULL, 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 2, 'Ahmad Khalid', 'ahmad-khalid', NULL, '+965 9000-0001', 'Senior AC technician trained on all major split-unit brands. Carries spare parts and cleaning chemicals.', 'HVAC Technician', 'Certified HVAC specialist — 8 years experience', NULL, 1, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `service_provider_assignments`
--

CREATE TABLE `service_provider_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_provider_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_provider_assignments`
--

INSERT INTO `service_provider_assignments` (`id`, `service_provider_id`, `product_id`, `created_at`, `updated_at`) VALUES
(1, 1, 11, '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 2, 12, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `type` varchar(255) NOT NULL DEFAULT 'string',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `is_translatable` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `group`, `key`, `value`, `type`, `is_encrypted`, `is_public`, `is_translatable`, `description`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'general', 'site_name', '{\"v\":\"Marketplace\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(2, 'general', 'site_tagline', '{\"v\":\"Multi-vendor marketplace, dropshipping & services\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(3, 'general', 'support_email', '{\"v\":\"support@marketplace.test\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(4, 'general', 'support_phone', '{\"v\":\"+965-0000-0000\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(5, 'general', 'timezone', '{\"v\":\"Asia\\/Kuwait\"}', 'string', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(6, 'general', 'maintenance_mode', '{\"v\":false}', 'boolean', 0, 0, 0, 'When true, public storefront returns 503.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(7, 'marketplace', 'guest_browsing', '{\"v\":true}', 'boolean', 0, 1, 0, 'Guests can browse products/services.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(8, 'marketplace', 'guest_checkout', '{\"v\":false}', 'boolean', 0, 1, 0, 'Guests can complete checkout without login.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(9, 'marketplace', 'earnings_release_days', '{\"v\":7}', 'integer', 0, 0, 0, 'Days after delivery before vendor earnings become available.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(10, 'marketplace', 'require_email_verified', '{\"v\":true}', 'boolean', 0, 0, 0, 'Block checkout if email not verified.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(11, 'currency', 'default', '{\"v\":\"KWD\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(12, 'currency', 'enabled', '{\"v\":[\"KWD\",\"USD\",\"AED\",\"PKR\"]}', 'array', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(13, 'payment', 'methods_enabled', '{\"v\":[\"card\",\"cod\",\"wallet\"]}', 'array', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(14, 'payment', 'cod_max_order', '{\"v\":50000}', 'integer', 0, 0, 0, 'Max order total in default-currency minor units that allows COD.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(15, 'shipping', 'free_shipping_threshold', '{\"v\":25000}', 'integer', 0, 1, 0, 'Free shipping above this amount in minor units of default currency.', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(16, 'shipping', 'default_delivery_days', '{\"v\":3}', 'integer', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(17, 'commission', 'default_basic_percent', '{\"v\":30}', 'integer', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(18, 'commission', 'default_standard_percent', '{\"v\":20}', 'integer', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(19, 'commission', 'default_professional_percent', '{\"v\":10}', 'integer', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(20, 'commission', 'default_calculation_base', '{\"v\":\"selling_price\"}', 'string', 0, 0, 0, 'selling_price | net_profit_after_cost', NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(21, 'email', 'from_address', '{\"v\":\"no-reply@marketplace.test\"}', 'string', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(22, 'email', 'from_name', '{\"v\":\"Marketplace\"}', 'string', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(23, 'email', 'reply_to', '{\"v\":\"support@marketplace.test\"}', 'string', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(24, 'seo', 'meta_title', '{\"v\":\"Marketplace \\u2014 Buy, sell, book\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(25, 'seo', 'meta_description', '{\"v\":\"Multi-vendor marketplace.\"}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(26, 'seo', 'og_image_path', '{\"v\":null}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(27, 'social', 'facebook_url', '{\"v\":null}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(28, 'social', 'instagram_url', '{\"v\":null}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(29, 'social', 'twitter_url', '{\"v\":null}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(30, 'social', 'youtube_url', '{\"v\":null}', 'string', 0, 1, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(31, 'security', 'two_factor_required_for_admin', '{\"v\":false}', 'boolean', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(32, 'security', 'session_timeout_minutes', '{\"v\":120}', 'integer', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(33, 'security', 'password_min_length', '{\"v\":8}', 'integer', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12'),
(34, 'security', 'max_login_attempts', '{\"v\":5}', 'integer', 0, 0, 0, NULL, NULL, '2026-07-12 10:07:12', '2026-07-12 10:07:12');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_methods`
--

CREATE TABLE `shipping_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shipping_zone_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `type` varchar(20) NOT NULL,
  `fee_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `min_subtotal_minor` int(10) UNSIGNED DEFAULT NULL,
  `max_weight_grams` int(10) UNSIGNED DEFAULT NULL,
  `eta_label` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipping_methods`
--

INSERT INTO `shipping_methods` (`id`, `shipping_zone_id`, `name`, `slug`, `type`, `fee_minor`, `currency`, `min_subtotal_minor`, `max_weight_grams`, `eta_label`, `is_active`, `position`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'Standard delivery', 'flat-rate-kuwait', 'flat_rate', 1500, 'KWD', NULL, NULL, '1-3 business days', 1, 1, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 1, 'Free shipping (orders ≥ 30 KWD)', 'free-over-30-kwd', 'free', 0, 'KWD', 30000, NULL, '2-4 business days', 1, 2, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(3, 1, 'Vendor pickup (Kuwait City)', 'pickup-kuwait-city', 'pickup', 0, 'KWD', NULL, NULL, 'Ready next business day', 1, 3, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(4, 2, 'GCC courier', 'flat-rate-gcc', 'flat_rate', 5000, 'KWD', NULL, NULL, '3-7 business days', 1, 1, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_zones`
--

CREATE TABLE `shipping_zones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`countries`)),
  `regions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`regions`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipping_zones`
--

INSERT INTO `shipping_zones` (`id`, `name`, `slug`, `countries`, `regions`, `is_active`, `position`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Kuwait Domestic', 'kuwait-domestic', '[\"KW\"]', NULL, 1, 1, 'Same-country delivery anywhere in Kuwait.', '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 'GCC cross-border', 'gcc-cross-border', '[\"AE\",\"SA\",\"BH\",\"QA\",\"OM\"]', NULL, 1, 2, 'Delivery to UAE, Saudi Arabia, Bahrain, Qatar, Oman.', '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_integrations`
--

CREATE TABLE `supplier_integrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_platform_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `integration_type` varchar(255) NOT NULL,
  `credentials` text DEFAULT NULL,
  `feed_url` varchar(255) DEFAULT NULL,
  `sync_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sync_options`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_sync_status` varchar(255) DEFAULT NULL,
  `last_sync_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_integrations`
--

INSERT INTO `supplier_integrations` (`id`, `vendor_id`, `supplier_platform_id`, `name`, `integration_type`, `credentials`, `feed_url`, `sync_options`, `is_active`, `last_synced_at`, `last_sync_status`, `last_sync_message`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'AliExpress demo catalogue', 'manual', 'eyJpdiI6IisxZys1MEJJbld0bDVEb05hSlhaUmc9PSIsInZhbHVlIjoiZXI4YWlIdHllTFhuUkhxZjBEdHVYSEVGYlU5Qy9OMVJYdG1MNEE4RmF1UnFzelNzeFBkQjk0d2gzd242enU2OWVPTTdxbmljZmI1b2dnM0crVEdDUlE9PSIsIm1hYyI6IjA1ZTc4MTIxMDExZTMwODRkYmIxODA0Y2RiMTFlZjE2Y2RiZjM3NDBlODMyMTgxMzI5MWJmNDExOTc3ODdjMzQiLCJ0YWciOiIifQ==', NULL, NULL, 1, NULL, NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_orders`
--

CREATE TABLE `supplier_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_platform_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `number` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `supplier_reference` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `tracking_url` varchar(1024) DEFAULT NULL,
  `carrier` varchar(255) DEFAULT NULL,
  `supplier_cost_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `supplier_shipping_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `placed_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_orders`
--

INSERT INTO `supplier_orders` (`id`, `vendor_id`, `supplier_platform_id`, `order_id`, `supplier_product_id`, `number`, `status`, `supplier_reference`, `tracking_number`, `tracking_url`, `carrier`, `supplier_cost_minor`, `supplier_shipping_minor`, `total_minor`, `currency`, `placed_at`, `shipped_at`, `delivered_at`, `cancelled_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 8, 3, 'SUP-202607-87C837', 'pending', NULL, NULL, NULL, NULL, 1200, 0, 1200, 'KWD', NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_order_events`
--

CREATE TABLE `supplier_order_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_order_id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_role` varchar(255) DEFAULT NULL,
  `event_type` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_order_events`
--

INSERT INTO `supplier_order_events` (`id`, `supplier_order_id`, `actor_id`, `actor_role`, `event_type`, `message`, `payload`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'system', 'supplier_order.created', 'Auto-created from order #DEMO-DROPSHIP-20260712130720.', NULL, '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_platforms`
--

CREATE TABLE `supplier_platforms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `integration_type` varchar(255) NOT NULL DEFAULT 'manual',
  `default_currency` varchar(3) NOT NULL DEFAULT 'USD',
  `default_delivery_days` smallint(5) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `display_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_platforms`
--

INSERT INTO `supplier_platforms` (`id`, `name`, `slug`, `logo_path`, `website_url`, `integration_type`, `default_currency`, `default_delivery_days`, `is_active`, `notes`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'AliExpress', 'aliexpress', NULL, 'https://www.aliexpress.com', 'manual', 'USD', 18, 1, NULL, 10, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 'Alibaba', 'alibaba', NULL, 'https://www.alibaba.com', 'manual', 'USD', 25, 1, NULL, 20, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(3, 'Amazon', 'amazon', NULL, 'https://www.amazon.com', 'manual', 'USD', 10, 1, NULL, 30, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(4, 'Temu', 'temu', NULL, 'https://www.temu.com', 'manual', 'USD', 14, 1, NULL, 40, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(5, 'Daraz', 'daraz', NULL, 'https://www.daraz.pk', 'manual', 'PKR', 7, 1, NULL, 50, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(6, 'Local Wholesale', 'local-wholesale', NULL, NULL, 'csv', 'KWD', 3, 1, NULL, 60, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(7, 'Private Supplier', 'private-supplier', NULL, NULL, 'manual', 'KWD', 5, 1, NULL, 70, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_platform_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_integration_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_product_id` varchar(255) DEFAULT NULL,
  `external_sku` varchar(255) DEFAULT NULL,
  `source_url` varchar(1024) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `supplier_cost_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `supplier_currency` varchar(3) NOT NULL DEFAULT 'USD',
  `supplier_stock_status` varchar(255) NOT NULL DEFAULT 'unknown',
  `supplier_stock_qty` int(10) UNSIGNED DEFAULT NULL,
  `supplier_shipping_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `estimated_delivery_days` smallint(5) UNSIGNED DEFAULT NULL,
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload`)),
  `import_status` varchar(255) NOT NULL DEFAULT 'pending',
  `import_notes` text DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mapped_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`id`, `vendor_id`, `supplier_platform_id`, `supplier_integration_id`, `product_id`, `external_product_id`, `external_sku`, `source_url`, `title`, `description`, `images`, `supplier_cost_minor`, `supplier_currency`, `supplier_stock_status`, `supplier_stock_qty`, `supplier_shipping_minor`, `estimated_delivery_days`, `raw_payload`, `import_status`, `import_notes`, `imported_at`, `mapped_at`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, NULL, 'DEMO-AE-001', 'AE-EARBUDS-001', 'https://www.aliexpress.com/item/demo/001.html', 'Bluetooth Earbuds (demo supplier import)', 'Demo supplier-imported earbuds. Vendor maps this into a marketplace listing.', NULL, 800, 'USD', 'in_stock', 250, 0, 18, '{\"source\":\"demo_seed\"}', 'pending', NULL, '2026-07-10 10:07:19', NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 1, 1, 1, 7, 'DEMO-AE-002', 'AE-CABLE-002', 'https://www.aliexpress.com/item/demo/002.html', 'USB-C Fast Charging Cable', 'Demo cable; mapped to a marketplace listing pending admin approval.', NULL, 200, 'USD', 'in_stock', 500, 0, 14, '{\"source\":\"demo_seed\"}', 'mapped', NULL, '2026-07-09 10:07:19', '2026-07-12 10:07:20', NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:20'),
(3, 1, 1, 1, 8, 'DEMO-AE-003', 'AE-LAMP-003', 'https://www.aliexpress.com/item/demo/003.html', 'LED Desk Lamp with Touch Controls', 'Demo published dropshipping product. Buy this to test the dropship checkout flow.', NULL, 1200, 'USD', 'in_stock', 80, 0, 16, '{\"source\":\"demo_seed\"}', 'published', NULL, '2026-07-07 10:07:20', '2026-07-12 10:07:20', '2026-07-12 10:07:20', '2026-07-12 10:07:20', '2026-07-12 10:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_product_imports`
--

CREATE TABLE `supplier_product_imports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_integration_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supplier_platform_id` bigint(20) UNSIGNED NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'processing',
  `dry_run` tinyint(1) NOT NULL DEFAULT 0,
  `total_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `successful_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`errors`)),
  `summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`summary`)),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `number` varchar(20) NOT NULL,
  `ticket_type` varchar(30) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `priority` varchar(10) NOT NULL DEFAULT 'normal',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `last_replied_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `user_id`, `number`, `ticket_type`, `order_id`, `booking_id`, `vendor_id`, `product_id`, `subject`, `priority`, `status`, `assigned_to`, `last_replied_at`, `resolved_at`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 4, 'TKT-260712-0001', 'general_inquiry', NULL, NULL, NULL, NULL, 'Question about shipping times', 'normal', 'answered', NULL, '2026-07-12 10:07:21', NULL, NULL, '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_messages`
--

CREATE TABLE `support_ticket_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `support_ticket_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `author_role` varchar(20) NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_ticket_messages`
--

INSERT INTO `support_ticket_messages` (`id`, `support_ticket_id`, `user_id`, `body`, `author_role`, `is_internal`, `attachments`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 'How long does shipping usually take to Kuwait City?', 'customer', 0, '[]', '2026-07-12 10:07:21', '2026-07-12 10:07:21'),
(2, 1, 1, 'For Kuwait City addresses we typically deliver within 1-2 business days. Thank you!', 'admin', 0, '[]', '2026-07-12 10:07:21', '2026-07-12 10:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en',
  `default_currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `email_verified_at`, `phone_verified_at`, `password`, `avatar_path`, `locale`, `default_currency`, `status`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `last_login_at`, `last_login_ip`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Marketplace Admin', 'admin@marketplace.test', NULL, '2026-07-12 10:07:14', NULL, '$2y$12$D58x3omeZip1IbuKzpX2SOmwvEJUMeQAJGybvFgIqSQg2Bg9AWZr.', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:14', '2026-07-12 10:07:14', NULL),
(2, 'Admin Staff', 'staff@marketplace.test', NULL, '2026-07-12 10:07:15', NULL, '$2y$12$dWO8u4NL2rp4BDtPb0viI.SfH3fX5E2HIGJxsXc9ExqDUQoBbitv2', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:15', '2026-07-12 10:07:15', NULL),
(3, 'Demo Vendor', 'vendor@marketplace.test', NULL, '2026-07-12 10:07:15', NULL, '$2y$12$9hqucpKVN1Pr8610aUdY3.r8io5lobJQTt7HmJCuIfI484NvGLiNy', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:15', '2026-07-12 10:07:15', NULL),
(4, 'Demo Customer', 'customer@marketplace.test', NULL, '2026-07-12 10:07:16', NULL, '$2y$12$64PQXy43FxkR6a2M7oksc.IINVoRCHOeyRVfcFbHTrzfGr.L2imUa', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:16', '2026-07-12 10:07:16', NULL),
(5, 'Coastal Goods', 'vendor2@marketplace.test', NULL, '2026-07-12 10:07:17', NULL, '$2y$12$pncs8PB6iKq8i1iAZ08Wfe92hr1q5xa3dRuPOJAuY2IcdoxphrHbi', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17', NULL),
(6, 'Pending Vendor', 'pending-vendor@marketplace.test', NULL, '2026-07-12 10:07:17', NULL, '$2y$12$c2eqYyzclzV.QiykQFa8QufLtb3ayFPgyeJ89tZTy2xiPtDkTJcOm', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17', NULL),
(7, 'Rejected Vendor', 'rejected-vendor@marketplace.test', NULL, '2026-07-12 10:07:18', NULL, '$2y$12$4zUH1MJANq.0WIzvwLxNPeu7RxOspBJSxy2rLBOCB8dt6IUBLSVCO', NULL, 'en', 'KWD', 'active', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 10:07:18', '2026-07-12 10:07:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_recent_searches`
--

CREATE TABLE `user_recent_searches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `query` varchar(100) NOT NULL,
  `locale` varchar(8) NOT NULL,
  `searched_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `business_email` varchar(255) NOT NULL,
  `business_phone` varchar(255) DEFAULT NULL,
  `business_type` varchar(255) NOT NULL DEFAULT 'individual',
  `description` text DEFAULT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `owner_email` varchar(255) DEFAULT NULL,
  `owner_phone` varchar(255) DEFAULT NULL,
  `country` varchar(2) NOT NULL DEFAULT 'KW',
  `city` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `banner_path` varchar(255) DEFAULT NULL,
  `license_document_path` varchar(255) DEFAULT NULL,
  `id_document_path` varchar(255) DEFAULT NULL,
  `commercial_license_no` varchar(255) DEFAULT NULL,
  `tax_id` varchar(255) DEFAULT NULL,
  `civil_id` varchar(255) DEFAULT NULL,
  `payout_method` varchar(255) DEFAULT NULL,
  `payout_details` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_until` timestamp NULL DEFAULT NULL,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sales_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `user_id`, `business_name`, `slug`, `business_email`, `business_phone`, `business_type`, `description`, `owner_name`, `owner_email`, `owner_phone`, `country`, `city`, `address`, `logo_path`, `banner_path`, `license_document_path`, `id_document_path`, `commercial_license_no`, `tax_id`, `civil_id`, `payout_method`, `payout_details`, `status`, `approved_at`, `approved_by`, `rejection_reason`, `admin_notes`, `featured`, `featured_until`, `rating_avg`, `rating_count`, `sales_count`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 3, 'Demo Trading Co.', 'demo-trading-co', 'shop@demo-trading.test', '+96522334455', 'company', 'A demo vendor used to exercise the marketplace workflows end-to-end.', 'Demo Vendor', NULL, NULL, 'KW', 'Kuwait City', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'approved', '2026-07-12 10:07:16', NULL, NULL, NULL, 0, NULL, 0.00, 0, 0, '2026-07-12 10:07:16', '2026-07-12 10:07:16', NULL),
(2, 5, 'Coastal Goods', 'coastal-goods', 'shop@coastal-goods.test', '+96522567890', 'individual', 'Second demo vendor for multi-vendor checkout testing.', 'Coastal Goods', NULL, NULL, 'KW', 'Salmiya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'approved', '2026-07-12 10:07:17', NULL, NULL, NULL, 0, NULL, 0.00, 0, 0, '2026-07-12 10:07:17', '2026-07-12 10:07:17', NULL),
(3, 6, 'Awaiting Review Shop', 'awaiting-review-shop', 'shop@awaiting-review.test', '+96523344556', 'individual', NULL, NULL, NULL, NULL, 'KW', 'Salmiya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0, 0, '2026-07-12 10:07:17', '2026-07-12 10:07:17', NULL),
(4, 7, 'Rejected Demo', 'rejected-demo', 'shop@rejected-demo.test', '+96524455667', 'individual', NULL, NULL, NULL, NULL, 'KW', 'Hawalli', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'rejected', NULL, NULL, 'Demo rejection — used to test the rejected vendor flow.', NULL, 0, NULL, 0.00, 0, 0, '2026-07-12 10:07:18', '2026-07-12 10:07:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_commission_rules`
--

CREATE TABLE `vendor_commission_rules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scope` varchar(255) NOT NULL DEFAULT 'vendor',
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_type` varchar(255) NOT NULL DEFAULT 'any',
  `payment_method` varchar(255) NOT NULL DEFAULT 'any',
  `calculation_base` varchar(255) NOT NULL DEFAULT 'selling_price',
  `commission_type` varchar(255) NOT NULL DEFAULT 'percent',
  `percent_value` decimal(7,4) DEFAULT NULL,
  `fixed_value_minor` int(10) UNSIGNED DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `priority` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `effective_from` timestamp NULL DEFAULT NULL,
  `effective_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_commission_rules`
--

INSERT INTO `vendor_commission_rules` (`id`, `vendor_id`, `scope`, `scope_id`, `product_type`, `payment_method`, `calculation_base`, `commission_type`, `percent_value`, `fixed_value_minor`, `currency`, `priority`, `effective_from`, `effective_until`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'vendor', NULL, 'any', 'any', 'selling_price', 'percent', 20.0000, NULL, 'KWD', 10, NULL, NULL, 1, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_intelligence_alerts`
--

CREATE TABLE `vendor_intelligence_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `alert_type` varchar(64) NOT NULL,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `priority` varchar(16) NOT NULL DEFAULT 'medium',
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `active_dedupe_key` varchar(255) DEFAULT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_intelligence_feedback`
--

CREATE TABLE `vendor_intelligence_feedback` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `suggestion_type` varchar(64) NOT NULL,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(16) NOT NULL,
  `snoozed_until` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_intelligence_summaries`
--

CREATE TABLE `vendor_intelligence_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `total_products` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_active_products` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `out_of_stock_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `low_stock_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `slow_moving_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `missing_arabic_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `missing_images_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `active_alerts_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `store_completion_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `store_missing_fields` text DEFAULT NULL,
  `avg_product_quality` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `computed_at` timestamp NULL DEFAULT NULL,
  `stale_at` timestamp NULL DEFAULT NULL,
  `stale_reason` varchar(64) DEFAULT NULL,
  `last_generated_at` timestamp NULL DEFAULT NULL,
  `last_digest_sent_at` timestamp NULL DEFAULT NULL,
  `email_opted_out` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_intelligence_summaries`
--

INSERT INTO `vendor_intelligence_summaries` (`id`, `vendor_id`, `total_products`, `total_active_products`, `out_of_stock_count`, `low_stock_count`, `slow_moving_count`, `missing_arabic_count`, `missing_images_count`, `active_alerts_count`, `store_completion_score`, `store_missing_fields`, `avg_product_quality`, `computed_at`, `stale_at`, `stale_reason`, `last_generated_at`, `last_digest_sent_at`, `email_opted_out`, `created_at`, `updated_at`) VALUES
(1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, NULL, '2026-07-12 10:07:21', 'product_translation_created', NULL, NULL, 0, '2026-07-12 10:07:18', '2026-07-12 10:07:21'),
(2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, NULL, '2026-07-12 10:07:22', 'product_translation_created', NULL, NULL, 0, '2026-07-12 10:07:19', '2026-07-12 10:07:22');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_packages`
--

CREATE TABLE `vendor_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `billing_cycle` varchar(255) NOT NULL DEFAULT 'monthly',
  `trial_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `max_products` int(10) UNSIGNED DEFAULT NULL,
  `max_services` int(10) UNSIGNED DEFAULT NULL,
  `max_images_per_product` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `allow_video` tinyint(1) NOT NULL DEFAULT 0,
  `allow_3d` tinyint(1) NOT NULL DEFAULT 0,
  `allow_dropshipping` tinyint(1) NOT NULL DEFAULT 0,
  `allow_product_import` tinyint(1) NOT NULL DEFAULT 0,
  `allow_customization` tinyint(1) NOT NULL DEFAULT 0,
  `allow_services` tinyint(1) NOT NULL DEFAULT 0,
  `allow_promotions` tinyint(1) NOT NULL DEFAULT 0,
  `allow_deal_of_day` tinyint(1) NOT NULL DEFAULT 0,
  `allow_featured_vendor` tinyint(1) NOT NULL DEFAULT 0,
  `analytics_level` varchar(255) NOT NULL DEFAULT 'basic',
  `default_admin_commission_percent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_packages`
--

INSERT INTO `vendor_packages` (`id`, `name`, `slug`, `description`, `price_minor`, `currency`, `billing_cycle`, `trial_days`, `max_products`, `max_services`, `max_images_per_product`, `allow_video`, `allow_3d`, `allow_dropshipping`, `allow_product_import`, `allow_customization`, `allow_services`, `allow_promotions`, `allow_deal_of_day`, `allow_featured_vendor`, `analytics_level`, `default_admin_commission_percent`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Basic Vendor', 'basic', 'Get started with the essentials. Best for individual sellers and small businesses.', 0, 'KWD', 'monthly', 0, 25, NULL, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'basic', 30.00, 1, 1, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(2, 'Standard Vendor', 'standard', 'For growing businesses with richer media and service offerings.', 5000, 'KWD', 'monthly', 14, 200, 25, 6, 1, 0, 0, 1, 0, 1, 1, 0, 0, 'standard', 20.00, 1, 2, '2026-07-12 10:07:13', '2026-07-12 10:07:13'),
(3, 'Professional Vendor', 'professional', 'Full feature set: video, 3D, dropshipping, customization, featured eligibility.', 25000, 'KWD', 'monthly', 30, NULL, NULL, 10, 1, 1, 1, 1, 1, 1, 1, 1, 1, 'advanced', 10.00, 1, 3, '2026-07-12 10:07:13', '2026-07-12 10:07:13');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payout_requests`
--

CREATE TABLE `vendor_payout_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `requested_amount_minor` int(10) UNSIGNED NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `payout_method` varchar(40) NOT NULL DEFAULT 'bank_transfer',
  `payout_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payout_details`)),
  `admin_notes` varchar(500) DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `transfer_reference` varchar(120) DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_payout_requests`
--

INSERT INTO `vendor_payout_requests` (`id`, `vendor_id`, `requested_amount_minor`, `currency`, `status`, `payout_method`, `payout_details`, `admin_notes`, `rejection_reason`, `transfer_reference`, `processed_by`, `requested_at`, `approved_at`, `rejected_at`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2000, 'KWD', 'pending', 'bank_transfer', '{\"iban\":\"KW00DEMO0000000000000001\",\"bank_name\":\"National Bank of Kuwait\",\"account_holder_name\":\"Demo Trading Co.\"}', NULL, NULL, NULL, NULL, '2026-07-11 10:07:19', NULL, NULL, NULL, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_product_quality_scores`
--

CREATE TABLE `vendor_product_quality_scores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `missing_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`missing_fields`)),
  `score_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`score_breakdown`)),
  `computed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_subscriptions`
--

CREATE TABLE `vendor_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_package_id` bigint(20) UNSIGNED NOT NULL,
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ends_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `amount_paid_minor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL DEFAULT 'KWD',
  `payment_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_subscriptions`
--

INSERT INTO `vendor_subscriptions` (`id`, `vendor_id`, `vendor_package_id`, `starts_at`, `ends_at`, `status`, `auto_renew`, `amount_paid_minor`, `currency`, `payment_reference`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-06-12 10:07:16', NULL, 'active', 0, 0, 'KWD', NULL, '2026-07-12 10:07:16', '2026-07-12 10:07:16'),
(2, 2, 1, '2026-06-27 10:07:17', NULL, 'active', 0, 0, 'KWD', NULL, '2026-07-12 10:07:17', '2026-07-12 10:07:17');

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `product_id`, `created_at`, `updated_at`) VALUES
(1, 4, 1, '2026-07-12 10:07:19', '2026-07-12 10:07:19'),
(2, 4, 2, '2026-07-12 10:07:19', '2026-07-12 10:07:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject` (`subject_type`,`subject_id`),
  ADD KEY `causer` (`causer_type`,`causer_id`),
  ADD KEY `activity_log_log_name_index` (`log_name`);

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addresses_user_id_is_default_index` (`user_id`,`is_default`),
  ADD KEY `addresses_country_city_index` (`country`,`city`);

--
-- Indexes for table `admin_product_relationships`
--
ALTER TABLE `admin_product_relationships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_rel_unique` (`product_id`,`related_product_id`,`relationship_type`),
  ADD KEY `admin_rel_read_idx` (`product_id`,`relationship_type`),
  ADD KEY `admin_product_relationships_related_product_id_foreign` (`related_product_id`),
  ADD KEY `admin_product_relationships_created_by_foreign` (`created_by`);

--
-- Indexes for table `attributes`
--
ALTER TABLE `attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attributes_slug_unique` (`slug`);

--
-- Indexes for table `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attribute_values_attribute_id_slug_unique` (`attribute_id`,`slug`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  ADD KEY `audit_logs_action_created_at_index` (`action`,`created_at`),
  ADD KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
  ADD KEY `audit_logs_created_at_idx` (`created_at`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `carts_user_id_unique` (`user_id`),
  ADD KEY `carts_coupon_id_foreign` (`coupon_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cart_items_unique_line` (`cart_id`,`product_id`,`variant_id`),
  ADD KEY `cart_items_product_id_foreign` (`product_id`),
  ADD KEY `cart_items_variant_id_foreign` (`variant_id`),
  ADD KEY `cart_items_vendor_id_index` (`vendor_id`);

--
-- Indexes for table `cart_item_customizations`
--
ALTER TABLE `cart_item_customizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_item_customizations_field_id_foreign` (`field_id`),
  ADD KEY `cart_item_customizations_cart_item_id_index` (`cart_item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`),
  ADD KEY `categories_parent_id_position_index` (`parent_id`,`position`),
  ADD KEY `categories_is_active_position_index` (`is_active`,`position`),
  ADD KEY `categories_is_active_idx` (`is_active`),
  ADD KEY `categories_parent_id_idx` (`parent_id`);

--
-- Indexes for table `category_product`
--
ALTER TABLE `category_product`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `category_product_category_id_index` (`category_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupons_code_unique` (`code`),
  ADD KEY `coupons_created_by_foreign` (`created_by`),
  ADD KEY `coupons_assigned_user_id_foreign` (`assigned_user_id`),
  ADD KEY `coupons_active_window_idx` (`is_active`,`starts_at`,`ends_at`),
  ADD KEY `coupons_vendor_active_idx` (`vendor_id`,`is_active`);

--
-- Indexes for table `coupon_usages`
--
ALTER TABLE `coupon_usages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coupon_usages_order_id_foreign` (`order_id`),
  ADD KEY `cu_coupon_user_idx` (`coupon_id`,`user_id`),
  ADD KEY `cu_user_idx` (`user_id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currencies_code_unique` (`code`),
  ADD KEY `currencies_is_active_sort_order_index` (`is_active`,`sort_order`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_rates_base_currency_target_currency_effective_at_index` (`base_currency`,`target_currency`,`effective_at`),
  ADD KEY `currency_rates_target_currency_foreign` (`target_currency`);

--
-- Indexes for table `customer_affinities`
--
ALTER TABLE `customer_affinities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ca_user_dim_unique` (`user_id`,`dimension`,`dimension_id`,`dimension_key`),
  ADD KEY `ca_user_score_idx` (`user_id`,`score`),
  ADD KEY `ca_last_signal_idx` (`last_signal_at`);

--
-- Indexes for table `customer_product_views`
--
ALTER TABLE `customer_product_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_product_views_product_id_foreign` (`product_id`),
  ADD KEY `cpv_user_recent_idx` (`user_id`,`viewed_at`),
  ADD KEY `cpv_session_recent_idx` (`session_key`,`viewed_at`),
  ADD KEY `cpv_user_product_idx` (`user_id`,`product_id`),
  ADD KEY `cpv_viewed_at_idx` (`viewed_at`);

--
-- Indexes for table `customization_proofs`
--
ALTER TABLE `customization_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customization_proofs_vendor_id_foreign` (`vendor_id`),
  ADD KEY `customization_proofs_order_item_id_status_index` (`order_item_id`,`status`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `license_activations`
--
ALTER TABLE `license_activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_activations_token_hash_unique` (`token_hash`),
  ADD KEY `license_activations_activated_by_foreign` (`activated_by`),
  ADD KEY `license_status_expires_idx` (`status`,`expires_at`),
  ADD KEY `license_activations_activated_at_index` (`activated_at`);

--
-- Indexes for table `license_audit_logs`
--
ALTER TABLE `license_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `license_audit_event_created_idx` (`event`,`created_at`),
  ADD KEY `license_audit_logs_user_id_index` (`user_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_templates_event_key_channel_locale_unique` (`event_key`,`channel`,`locale`),
  ADD KEY `notification_templates_event_key_is_active_index` (`event_key`,`is_active`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_number_unique` (`number`),
  ADD KEY `orders_user_id_status_index` (`user_id`,`status`),
  ADD KEY `orders_status_created_at_index` (`status`,`created_at`),
  ADD KEY `orders_payment_status_index` (`payment_status`),
  ADD KEY `orders_shipping_method_id_foreign` (`shipping_method_id`),
  ADD KEY `orders_coupon_id_foreign` (`coupon_id`),
  ADD KEY `orders_created_status_idx` (`created_at`,`status`),
  ADD KEY `orders_user_created_idx` (`user_id`,`created_at`),
  ADD KEY `orders_status_created_idx` (`status`,`created_at`),
  ADD KEY `orders_status_payment_status_idx` (`status`,`payment_status`);

--
-- Indexes for table `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_addresses_order_id_type_unique` (`order_id`,`type`);

--
-- Indexes for table `order_events`
--
ALTER TABLE `order_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_events_actor_id_foreign` (`actor_id`),
  ADD KEY `order_events_order_id_event_type_index` (`order_id`,`event_type`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_variant_id_foreign` (`variant_id`),
  ADD KEY `order_items_vendor_id_fulfillment_status_index` (`vendor_id`,`fulfillment_status`),
  ADD KEY `order_items_order_id_vendor_id_index` (`order_id`,`vendor_id`),
  ADD KEY `order_items_supplier_order_id_index` (`supplier_order_id`),
  ADD KEY `order_items_customization_status_index` (`customization_status`),
  ADD KEY `order_items_vendor_order_idx` (`vendor_id`,`order_id`),
  ADD KEY `order_items_product_idx` (`product_id`),
  ADD KEY `order_items_promotion_id_index` (`promotion_id`);

--
-- Indexes for table `order_item_customizations`
--
ALTER TABLE `order_item_customizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_customizations_order_item_id_index` (`order_item_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payments_payment_method_id_foreign` (`payment_method_id`),
  ADD KEY `payments_order_id_status_index` (`order_id`,`status`),
  ADD KEY `payments_external_id_index` (`external_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_methods_slug_unique` (`slug`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_transactions_payment_id_type_index` (`payment_id`,`type`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personalization_feedback`
--
ALTER TABLE `personalization_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personalization_feedback_product_id_foreign` (`product_id`),
  ADD KEY `personalization_feedback_category_id_foreign` (`category_id`),
  ADD KEY `pf_user_type_idx` (`user_id`,`feedback_type`,`expires_at`),
  ADD KEY `pf_session_type_idx` (`session_key`,`feedback_type`,`expires_at`),
  ADD KEY `pf_expires_idx` (`expires_at`);

--
-- Indexes for table `personalization_preferences`
--
ALTER TABLE `personalization_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personalization_preferences_user_id_unique` (`user_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_slug_unique` (`slug`),
  ADD UNIQUE KEY `products_vendor_id_sku_unique` (`vendor_id`,`sku`),
  ADD KEY `products_approved_by_foreign` (`approved_by`),
  ADD KEY `products_vendor_id_status_index` (`vendor_id`,`status`),
  ADD KEY `products_category_id_status_index` (`category_id`,`status`),
  ADD KEY `products_status_published_at_index` (`status`,`published_at`),
  ADD KEY `products_featured_featured_until_index` (`featured`,`featured_until`),
  ADD KEY `products_supplier_product_id_foreign` (`supplier_product_id`),
  ADD KEY `products_supplier_platform_id_foreign` (`supplier_platform_id`),
  ADD KEY `products_fulfillment_mode_index` (`fulfillment_mode`),
  ADD KEY `products_status_type_idx` (`status`,`type`),
  ADD KEY `products_category_status_idx` (`category_id`,`status`),
  ADD KEY `products_status_rating_idx` (`status`,`rating_avg`),
  ADD KEY `products_status_sales_idx` (`status`,`sales_count`),
  ADD KEY `products_status_views_idx` (`status`,`views_count`),
  ADD KEY `products_status_price_idx` (`status`,`price_minor`),
  ADD KEY `products_name_prefix_idx` (`name`(64)),
  ADD KEY `products_status_idx` (`status`);

--
-- Indexes for table `product_attribute_value`
--
ALTER TABLE `product_attribute_value`
  ADD PRIMARY KEY (`product_id`,`attribute_value_id`),
  ADD KEY `product_attribute_value_attribute_value_id_index` (`attribute_value_id`);

--
-- Indexes for table `product_customization_fields`
--
ALTER TABLE `product_customization_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_customization_fields_product_id_key_unique` (`product_id`,`key`),
  ADD KEY `product_customization_fields_product_id_sort_order_index` (`product_id`,`sort_order`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_images_variant_id_foreign` (`variant_id`),
  ADD KEY `product_images_product_id_position_index` (`product_id`,`position`);

--
-- Indexes for table `product_pair_stats`
--
ALTER TABLE `product_pair_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pair_stats_unique_pair` (`product_a_id`,`product_b_id`),
  ADD KEY `pair_stats_a_idx` (`product_a_id`),
  ADD KEY `pair_stats_b_idx` (`product_b_id`),
  ADD KEY `pair_stats_count_idx` (`pair_count`),
  ADD KEY `pair_stats_recency_idx` (`last_seen_at`);

--
-- Indexes for table `product_recommendations`
--
ALTER TABLE `product_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_recommendations_unique` (`product_id`,`recommended_product_id`,`recommendation_type`),
  ADD KEY `product_recommendations_read_idx` (`product_id`,`recommendation_type`,`score`),
  ADD KEY `product_recommendations_expiry_idx` (`expires_at`),
  ADD KEY `product_recommendations_recommended_product_id_foreign` (`recommended_product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reviews_user_product_orderitem_unique` (`user_id`,`product_id`,`order_item_id`),
  ADD KEY `product_reviews_order_item_id_foreign` (`order_item_id`),
  ADD KEY `product_reviews_product_id_status_index` (`product_id`,`status`),
  ADD KEY `product_reviews_user_id_created_at_index` (`user_id`,`created_at`),
  ADD KEY `product_reviews_status_index` (`status`),
  ADD KEY `product_reviews_prod_status_idx` (`product_id`,`status`);

--
-- Indexes for table `product_translations`
--
ALTER TABLE `product_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_translations_unique` (`product_id`,`locale`,`field`),
  ADD KEY `product_translations_resolve_idx` (`product_id`,`locale`,`status`),
  ADD KEY `product_translations_status_idx` (`status`,`locale`),
  ADD KEY `product_translations_reviewed_by_foreign` (`reviewed_by`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_variants_product_id_sku_unique` (`product_id`,`sku`),
  ADD KEY `product_variants_product_id_is_active_index` (`product_id`,`is_active`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promotions_slug_unique` (`slug`),
  ADD KEY `promotions_created_by_foreign` (`created_by`),
  ADD KEY `prom_active_window_idx` (`is_active`,`starts_at`,`ends_at`),
  ADD KEY `prom_vendor_active_idx` (`vendor_id`,`is_active`),
  ADD KEY `prom_approval_idx` (`approval_status`);

--
-- Indexes for table `promotion_targets`
--
ALTER TABLE `promotion_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pt_unique` (`promotion_id`,`targetable_type`,`targetable_id`),
  ADD KEY `pt_target_idx` (`targetable_type`,`targetable_id`);

--
-- Indexes for table `recommendation_events`
--
ALTER TABLE `recommendation_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rec_events_purchase_unique` (`order_item_id`,`event_type`,`product_id`,`recommendation_type`),
  ADD KEY `rec_events_report_idx` (`recommendation_type`,`event_type`,`created_at`),
  ADD KEY `rec_events_source_idx` (`product_id`,`recommendation_type`),
  ADD KEY `rec_events_target_idx` (`recommended_product_id`),
  ADD KEY `rec_events_recency_idx` (`created_at`),
  ADD KEY `recommendation_events_user_id_foreign` (`user_id`),
  ADD KEY `rec_events_reversed_idx` (`reversed_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `search_queries`
--
ALTER TABLE `search_queries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `search_queries_query_locale_unique` (`query`,`locale`),
  ADD KEY `search_queries_popular_idx` (`locale`,`is_blocked`,`search_count`);

--
-- Indexes for table `search_synonyms`
--
ALTER TABLE `search_synonyms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `search_synonyms_locale_pair_unique` (`locale`,`term`,`synonym`),
  ADD KEY `search_synonyms_lookup_idx` (`locale`,`is_active`,`term`);

--
-- Indexes for table `service_availabilities`
--
ALTER TABLE `service_availabilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sa_provider_dow_unique` (`service_provider_id`,`day_of_week`);

--
-- Indexes for table `service_blocked_dates`
--
ALTER TABLE `service_blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_blocked_dates_service_provider_id_date_unique` (`service_provider_id`,`date`),
  ADD KEY `service_blocked_dates_date_service_provider_id_index` (`date`,`service_provider_id`);

--
-- Indexes for table `service_bookings`
--
ALTER TABLE `service_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_bookings_number_unique` (`number`),
  ADD KEY `service_bookings_product_id_foreign` (`product_id`),
  ADD KEY `service_bookings_order_id_foreign` (`order_id`),
  ADD KEY `sb_provider_date_time_idx` (`service_provider_id`,`booked_for_date`,`booked_for_time`),
  ADD KEY `sb_vendor_status_date_idx` (`vendor_id`,`status`,`booked_for_date`),
  ADD KEY `sb_user_status_idx` (`user_id`,`status`);

--
-- Indexes for table `service_details`
--
ALTER TABLE `service_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_details_product_id_unique` (`product_id`);

--
-- Indexes for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_providers_vendor_id_slug_unique` (`vendor_id`,`slug`),
  ADD KEY `service_providers_is_active_index` (`is_active`);

--
-- Indexes for table `service_provider_assignments`
--
ALTER TABLE `service_provider_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `spa_provider_product_unique` (`service_provider_id`,`product_id`),
  ADD KEY `spa_product_provider_idx` (`product_id`,`service_provider_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_group_key_unique` (`group`,`key`),
  ADD KEY `settings_group_index` (`group`),
  ADD KEY `settings_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `shipping_methods`
--
ALTER TABLE `shipping_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shipping_methods_zone_slug_unique` (`shipping_zone_id`,`slug`),
  ADD KEY `shipping_methods_shipping_zone_id_is_active_position_index` (`shipping_zone_id`,`is_active`,`position`),
  ADD KEY `shipping_methods_type_index` (`type`);

--
-- Indexes for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shipping_zones_slug_unique` (`slug`),
  ADD KEY `shipping_zones_is_active_position_index` (`is_active`,`position`);

--
-- Indexes for table `supplier_integrations`
--
ALTER TABLE `supplier_integrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_integrations_vendor_id_supplier_platform_id_name_unique` (`vendor_id`,`supplier_platform_id`,`name`),
  ADD KEY `supplier_integrations_supplier_platform_id_foreign` (`supplier_platform_id`),
  ADD KEY `supplier_integrations_vendor_id_is_active_index` (`vendor_id`,`is_active`);

--
-- Indexes for table `supplier_orders`
--
ALTER TABLE `supplier_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_orders_number_unique` (`number`),
  ADD KEY `supplier_orders_supplier_platform_id_foreign` (`supplier_platform_id`),
  ADD KEY `supplier_orders_supplier_product_id_foreign` (`supplier_product_id`),
  ADD KEY `supplier_orders_vendor_id_status_index` (`vendor_id`,`status`),
  ADD KEY `supplier_orders_order_id_status_index` (`order_id`,`status`);

--
-- Indexes for table `supplier_order_events`
--
ALTER TABLE `supplier_order_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_order_events_actor_id_foreign` (`actor_id`),
  ADD KEY `supplier_order_events_supplier_order_id_index` (`supplier_order_id`),
  ADD KEY `supplier_order_events_event_type_index` (`event_type`);

--
-- Indexes for table `supplier_platforms`
--
ALTER TABLE `supplier_platforms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_platforms_slug_unique` (`slug`),
  ADD KEY `supplier_platforms_is_active_display_order_index` (`is_active`,`display_order`);

--
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_products_supplier_integration_id_foreign` (`supplier_integration_id`),
  ADD KEY `supplier_products_product_id_foreign` (`product_id`),
  ADD KEY `supplier_products_vendor_id_import_status_index` (`vendor_id`,`import_status`),
  ADD KEY `supplier_products_supplier_platform_id_external_product_id_index` (`supplier_platform_id`,`external_product_id`),
  ADD KEY `supplier_products_imported_at_index` (`imported_at`);

--
-- Indexes for table `supplier_product_imports`
--
ALTER TABLE `supplier_product_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_product_imports_supplier_integration_id_foreign` (`supplier_integration_id`),
  ADD KEY `supplier_product_imports_supplier_platform_id_foreign` (`supplier_platform_id`),
  ADD KEY `supplier_product_imports_vendor_id_created_at_index` (`vendor_id`,`created_at`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `support_tickets_number_unique` (`number`),
  ADD KEY `support_tickets_order_id_foreign` (`order_id`),
  ADD KEY `support_tickets_booking_id_foreign` (`booking_id`),
  ADD KEY `support_tickets_product_id_foreign` (`product_id`),
  ADD KEY `tickets_user_status_idx` (`user_id`,`status`),
  ADD KEY `tickets_vendor_status_idx` (`vendor_id`,`status`),
  ADD KEY `tickets_status_priority_idx` (`status`,`priority`),
  ADD KEY `tickets_assigned_idx` (`assigned_to`),
  ADD KEY `st_user_status_created_idx` (`user_id`,`status`,`created_at`),
  ADD KEY `st_vendor_status_created_idx` (`vendor_id`,`status`,`created_at`),
  ADD KEY `st_status_created_idx` (`status`,`created_at`);

--
-- Indexes for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_ticket_messages_user_id_foreign` (`user_id`),
  ADD KEY `tmsg_ticket_idx` (`support_ticket_id`),
  ADD KEY `tmsg_ticket_created_idx` (`support_ticket_id`,`created_at`),
  ADD KEY `stm_ticket_created_idx` (`support_ticket_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_phone_unique` (`phone`),
  ADD KEY `users_status_index` (`status`),
  ADD KEY `users_locale_index` (`locale`),
  ADD KEY `users_status_idx` (`status`);

--
-- Indexes for table `user_recent_searches`
--
ALTER TABLE `user_recent_searches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_recent_searches_unique` (`user_id`,`query`,`locale`),
  ADD KEY `user_recent_searches_recent_idx` (`user_id`,`locale`,`searched_at`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendors_slug_unique` (`slug`),
  ADD KEY `vendors_user_id_foreign` (`user_id`),
  ADD KEY `vendors_approved_by_foreign` (`approved_by`),
  ADD KEY `vendors_status_created_at_index` (`status`,`created_at`),
  ADD KEY `vendors_country_city_index` (`country`,`city`),
  ADD KEY `vendors_featured_index` (`featured`),
  ADD KEY `vendors_status_created_idx` (`status`,`created_at`),
  ADD KEY `vendors_status_idx` (`status`);

--
-- Indexes for table `vendor_commission_rules`
--
ALTER TABLE `vendor_commission_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_commission_rules_scope_scope_id_is_active_index` (`scope`,`scope_id`,`is_active`),
  ADD KEY `vendor_commission_rules_vendor_id_priority_index` (`vendor_id`,`priority`),
  ADD KEY `vendor_commission_rules_effective_from_effective_until_index` (`effective_from`,`effective_until`);

--
-- Indexes for table `vendor_intelligence_alerts`
--
ALTER TABLE `vendor_intelligence_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `via_active_dedupe_uniq` (`active_dedupe_key`),
  ADD KEY `via_vsp_idx` (`vendor_id`,`status`,`priority`),
  ADD KEY `via_uniqness_idx` (`vendor_id`,`alert_type`,`entity_type`,`entity_id`,`status`),
  ADD KEY `via_status_expiry_idx` (`status`,`expires_at`);

--
-- Indexes for table `vendor_intelligence_feedback`
--
ALTER TABLE `vendor_intelligence_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vif_lookup_idx` (`vendor_id`,`suggestion_type`,`entity_type`,`entity_id`),
  ADD KEY `vif_snooze_idx` (`snoozed_until`);

--
-- Indexes for table `vendor_intelligence_summaries`
--
ALTER TABLE `vendor_intelligence_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vis_vendor_uniq` (`vendor_id`),
  ADD KEY `vis_computed_at_idx` (`computed_at`),
  ADD KEY `vis_stale_at_idx` (`stale_at`),
  ADD KEY `vis_last_digest_idx` (`last_digest_sent_at`);

--
-- Indexes for table `vendor_packages`
--
ALTER TABLE `vendor_packages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_packages_slug_unique` (`slug`),
  ADD KEY `vendor_packages_is_active_sort_order_index` (`is_active`,`sort_order`);

--
-- Indexes for table `vendor_payout_requests`
--
ALTER TABLE `vendor_payout_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_payout_requests_processed_by_foreign` (`processed_by`),
  ADD KEY `vendor_payout_requests_vendor_id_status_index` (`vendor_id`,`status`),
  ADD KEY `vendor_payout_requests_status_index` (`status`),
  ADD KEY `vendor_payout_requests_requested_at_index` (`requested_at`),
  ADD KEY `vpr_created_status_idx` (`created_at`,`status`),
  ADD KEY `vpr_vendor_status_created_idx` (`vendor_id`,`status`,`created_at`);

--
-- Indexes for table `vendor_product_quality_scores`
--
ALTER TABLE `vendor_product_quality_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vpqs_product_uniq` (`product_id`),
  ADD KEY `vpqs_vendor_score_idx` (`vendor_id`,`score`);

--
-- Indexes for table `vendor_subscriptions`
--
ALTER TABLE `vendor_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_subscriptions_vendor_package_id_foreign` (`vendor_package_id`),
  ADD KEY `vendor_subscriptions_vendor_id_status_index` (`vendor_id`,`status`),
  ADD KEY `vendor_subscriptions_status_ends_at_index` (`status`,`ends_at`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wishlists_user_product_unique` (`user_id`,`product_id`),
  ADD KEY `wishlists_product_id_foreign` (`product_id`),
  ADD KEY `wishlists_user_id_created_at_index` (`user_id`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_product_relationships`
--
ALTER TABLE `admin_product_relationships`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attributes`
--
ALTER TABLE `attributes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attribute_values`
--
ALTER TABLE `attribute_values`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_item_customizations`
--
ALTER TABLE `cart_item_customizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `coupon_usages`
--
ALTER TABLE `coupon_usages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customer_affinities`
--
ALTER TABLE `customer_affinities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_product_views`
--
ALTER TABLE `customer_product_views`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customization_proofs`
--
ALTER TABLE `customization_proofs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_activations`
--
ALTER TABLE `license_activations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_audit_logs`
--
ALTER TABLE `license_audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_addresses`
--
ALTER TABLE `order_addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_events`
--
ALTER TABLE `order_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_item_customizations`
--
ALTER TABLE `order_item_customizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `personalization_feedback`
--
ALTER TABLE `personalization_feedback`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personalization_preferences`
--
ALTER TABLE `personalization_preferences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_customization_fields`
--
ALTER TABLE `product_customization_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_pair_stats`
--
ALTER TABLE `product_pair_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_recommendations`
--
ALTER TABLE `product_recommendations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_translations`
--
ALTER TABLE `product_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `promotion_targets`
--
ALTER TABLE `promotion_targets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recommendation_events`
--
ALTER TABLE `recommendation_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `search_queries`
--
ALTER TABLE `search_queries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_synonyms`
--
ALTER TABLE `search_synonyms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_availabilities`
--
ALTER TABLE `service_availabilities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `service_blocked_dates`
--
ALTER TABLE `service_blocked_dates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_bookings`
--
ALTER TABLE `service_bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `service_details`
--
ALTER TABLE `service_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `service_providers`
--
ALTER TABLE `service_providers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `service_provider_assignments`
--
ALTER TABLE `service_provider_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `shipping_methods`
--
ALTER TABLE `shipping_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_integrations`
--
ALTER TABLE `supplier_integrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_orders`
--
ALTER TABLE `supplier_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_order_events`
--
ALTER TABLE `supplier_order_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_platforms`
--
ALTER TABLE `supplier_platforms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `supplier_product_imports`
--
ALTER TABLE `supplier_product_imports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_recent_searches`
--
ALTER TABLE `user_recent_searches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vendor_commission_rules`
--
ALTER TABLE `vendor_commission_rules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_intelligence_alerts`
--
ALTER TABLE `vendor_intelligence_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_intelligence_feedback`
--
ALTER TABLE `vendor_intelligence_feedback`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_intelligence_summaries`
--
ALTER TABLE `vendor_intelligence_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendor_packages`
--
ALTER TABLE `vendor_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor_payout_requests`
--
ALTER TABLE `vendor_payout_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_product_quality_scores`
--
ALTER TABLE `vendor_product_quality_scores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_subscriptions`
--
ALTER TABLE `vendor_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_product_relationships`
--
ALTER TABLE `admin_product_relationships`
  ADD CONSTRAINT `admin_product_relationships_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_product_relationships_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_product_relationships_related_product_id_foreign` FOREIGN KEY (`related_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD CONSTRAINT `attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `cart_item_customizations`
--
ALTER TABLE `cart_item_customizations`
  ADD CONSTRAINT `cart_item_customizations_cart_item_id_foreign` FOREIGN KEY (`cart_item_id`) REFERENCES `cart_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_item_customizations_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `product_customization_fields` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_product`
--
ALTER TABLE `category_product`
  ADD CONSTRAINT `category_product_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `coupons_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `coupons_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `coupons_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupon_usages`
--
ALTER TABLE `coupon_usages`
  ADD CONSTRAINT `coupon_usages_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_usages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `coupon_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD CONSTRAINT `currency_rates_base_currency_foreign` FOREIGN KEY (`base_currency`) REFERENCES `currencies` (`code`) ON DELETE CASCADE,
  ADD CONSTRAINT `currency_rates_target_currency_foreign` FOREIGN KEY (`target_currency`) REFERENCES `currencies` (`code`) ON DELETE CASCADE;

--
-- Constraints for table `customer_affinities`
--
ALTER TABLE `customer_affinities`
  ADD CONSTRAINT `customer_affinities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_product_views`
--
ALTER TABLE `customer_product_views`
  ADD CONSTRAINT `customer_product_views_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_product_views_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customization_proofs`
--
ALTER TABLE `customization_proofs`
  ADD CONSTRAINT `customization_proofs_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customization_proofs_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `license_activations`
--
ALTER TABLE `license_activations`
  ADD CONSTRAINT `license_activations_activated_by_foreign` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `license_audit_logs`
--
ALTER TABLE `license_audit_logs`
  ADD CONSTRAINT `license_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD CONSTRAINT `order_addresses_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_events`
--
ALTER TABLE `order_events`
  ADD CONSTRAINT `order_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_events_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_items_supplier_order_id_foreign` FOREIGN KEY (`supplier_order_id`) REFERENCES `supplier_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_items_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_items_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `order_item_customizations`
--
ALTER TABLE `order_item_customizations`
  ADD CONSTRAINT `order_item_customizations_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personalization_feedback`
--
ALTER TABLE `personalization_feedback`
  ADD CONSTRAINT `personalization_feedback_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personalization_feedback_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personalization_feedback_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personalization_preferences`
--
ALTER TABLE `personalization_preferences`
  ADD CONSTRAINT `personalization_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_supplier_platform_id_foreign` FOREIGN KEY (`supplier_platform_id`) REFERENCES `supplier_platforms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_supplier_product_id_foreign` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_attribute_value`
--
ALTER TABLE `product_attribute_value`
  ADD CONSTRAINT `product_attribute_value_attribute_value_id_foreign` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_attribute_value_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_customization_fields`
--
ALTER TABLE `product_customization_fields`
  ADD CONSTRAINT `product_customization_fields_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_images_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_pair_stats`
--
ALTER TABLE `product_pair_stats`
  ADD CONSTRAINT `product_pair_stats_product_a_id_foreign` FOREIGN KEY (`product_a_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_pair_stats_product_b_id_foreign` FOREIGN KEY (`product_b_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_recommendations`
--
ALTER TABLE `product_recommendations`
  ADD CONSTRAINT `product_recommendations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recommendations_recommended_product_id_foreign` FOREIGN KEY (`recommended_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_translations`
--
ALTER TABLE `product_translations`
  ADD CONSTRAINT `product_translations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_translations_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `promotions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `promotions_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promotion_targets`
--
ALTER TABLE `promotion_targets`
  ADD CONSTRAINT `promotion_targets_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recommendation_events`
--
ALTER TABLE `recommendation_events`
  ADD CONSTRAINT `recommendation_events_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `recommendation_events_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recommendation_events_recommended_product_id_foreign` FOREIGN KEY (`recommended_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recommendation_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_availabilities`
--
ALTER TABLE `service_availabilities`
  ADD CONSTRAINT `service_availabilities_service_provider_id_foreign` FOREIGN KEY (`service_provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_blocked_dates`
--
ALTER TABLE `service_blocked_dates`
  ADD CONSTRAINT `service_blocked_dates_service_provider_id_foreign` FOREIGN KEY (`service_provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_bookings`
--
ALTER TABLE `service_bookings`
  ADD CONSTRAINT `service_bookings_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `service_bookings_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `service_bookings_service_provider_id_foreign` FOREIGN KEY (`service_provider_id`) REFERENCES `service_providers` (`id`),
  ADD CONSTRAINT `service_bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_bookings_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `service_details`
--
ALTER TABLE `service_details`
  ADD CONSTRAINT `service_details_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD CONSTRAINT `service_providers_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_provider_assignments`
--
ALTER TABLE `service_provider_assignments`
  ADD CONSTRAINT `service_provider_assignments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_provider_assignments_service_provider_id_foreign` FOREIGN KEY (`service_provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shipping_methods`
--
ALTER TABLE `shipping_methods`
  ADD CONSTRAINT `shipping_methods_shipping_zone_id_foreign` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_integrations`
--
ALTER TABLE `supplier_integrations`
  ADD CONSTRAINT `supplier_integrations_supplier_platform_id_foreign` FOREIGN KEY (`supplier_platform_id`) REFERENCES `supplier_platforms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_integrations_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_orders`
--
ALTER TABLE `supplier_orders`
  ADD CONSTRAINT `supplier_orders_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_orders_supplier_platform_id_foreign` FOREIGN KEY (`supplier_platform_id`) REFERENCES `supplier_platforms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_orders_supplier_product_id_foreign` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_orders_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_order_events`
--
ALTER TABLE `supplier_order_events`
  ADD CONSTRAINT `supplier_order_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_order_events_supplier_order_id_foreign` FOREIGN KEY (`supplier_order_id`) REFERENCES `supplier_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `supplier_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_products_supplier_integration_id_foreign` FOREIGN KEY (`supplier_integration_id`) REFERENCES `supplier_integrations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_products_supplier_platform_id_foreign` FOREIGN KEY (`supplier_platform_id`) REFERENCES `supplier_platforms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_products_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_product_imports`
--
ALTER TABLE `supplier_product_imports`
  ADD CONSTRAINT `supplier_product_imports_supplier_integration_id_foreign` FOREIGN KEY (`supplier_integration_id`) REFERENCES `supplier_integrations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_product_imports_supplier_platform_id_foreign` FOREIGN KEY (`supplier_platform_id`) REFERENCES `supplier_platforms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_product_imports_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_tickets_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `service_bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_tickets_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_tickets_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD CONSTRAINT `support_ticket_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_ticket_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_recent_searches`
--
ALTER TABLE `user_recent_searches`
  ADD CONSTRAINT `user_recent_searches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendors`
--
ALTER TABLE `vendors`
  ADD CONSTRAINT `vendors_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vendors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_commission_rules`
--
ALTER TABLE `vendor_commission_rules`
  ADD CONSTRAINT `vendor_commission_rules_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_intelligence_alerts`
--
ALTER TABLE `vendor_intelligence_alerts`
  ADD CONSTRAINT `vendor_intelligence_alerts_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_intelligence_feedback`
--
ALTER TABLE `vendor_intelligence_feedback`
  ADD CONSTRAINT `vendor_intelligence_feedback_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_intelligence_summaries`
--
ALTER TABLE `vendor_intelligence_summaries`
  ADD CONSTRAINT `vendor_intelligence_summaries_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_payout_requests`
--
ALTER TABLE `vendor_payout_requests`
  ADD CONSTRAINT `vendor_payout_requests_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vendor_payout_requests_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_product_quality_scores`
--
ALTER TABLE `vendor_product_quality_scores`
  ADD CONSTRAINT `vendor_product_quality_scores_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_product_quality_scores_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_subscriptions`
--
ALTER TABLE `vendor_subscriptions`
  ADD CONSTRAINT `vendor_subscriptions_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_subscriptions_vendor_package_id_foreign` FOREIGN KEY (`vendor_package_id`) REFERENCES `vendor_packages` (`id`);

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
