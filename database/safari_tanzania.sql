-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 24, 2026 at 12:54 PM
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
-- Database: `safari_tanzania`
--

-- --------------------------------------------------------

--
-- Table structure for table `accommodations`
--

CREATE TABLE `accommodations` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `accommodation_type` enum('guest_house','lodge','hotel') DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(150) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `ward_area` varchar(150) DEFAULT NULL,
  `area_other` varchar(150) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accommodations`
--

INSERT INTO `accommodations` (`id`, `owner_id`, `accommodation_type`, `name`, `description`, `location`, `region`, `district`, `ward_area`, `area_other`, `latitude`, `longitude`, `address`, `rating`, `image_url`, `status`, `created_at`) VALUES
(1, 2, NULL, 'Serengeti Safari Lodge', 'Luxury tented camp on the edge of the Serengeti with sweeping savannah views, gourmet dining, and guided game drives.', 'Serengeti', NULL, NULL, NULL, NULL, NULL, NULL, 'Seronera, Serengeti National Park', 4.8, 'https://images.unsplash.com/photo-1516426122078-c23e76319801?w=1200', 'approved', '2026-04-24 15:00:10'),
(2, 2, NULL, 'Zanzibar Beach Resort', 'Boutique beachfront resort with white sand, turquoise water, and Swahili-inspired cuisine.', 'Zanzibar', NULL, NULL, NULL, NULL, NULL, NULL, 'Nungwi Beach Road, Zanzibar', 4.7, 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200', 'approved', '2026-04-24 15:00:10'),
(3, 2, NULL, 'Kilimanjaro View Hotel', 'Modern hotel in Moshi with panoramic Mount Kilimanjaro views and easy access to trekking routes.', 'Moshi', NULL, NULL, NULL, NULL, NULL, NULL, 'Lema Road, Moshi', 4.5, 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200', 'approved', '2026-04-24 15:00:10'),
(4, 5, 'hotel', 'Focus Hotel', 'karibu upate huduma zetu kwa bei nafuu', 'Arusha', 'Arusha', 'Arusha City', 'Other', 'mianzini', -3.3635120, 36.6776620, 'mianzini', 0.0, 'https://i.pinimg.com/1200x/3f/5f/ae/3f5fae7069f96e9a4fb40e094a16c2f6.jpg', 'approved', '2026-05-11 17:39:42'),
(5, 5, 'guest_house', 'fuxing Guest House', NULL, 'Mbeya', 'Mbeya', 'Mbeya District', 'Town Centre', NULL, -3.3635120, 36.6776620, 'Mbalizi', 0.0, '/safari_tanzania/public/uploads/accommodations/acc_5_2123af373cb12936.jpg', 'approved', '2026-05-11 18:27:30'),
(6, 5, 'lodge', 'tanganyika lodge', 'karibu tanganyika lodge tupo mtaa wa buhingwa', 'Kigoma', 'Kigoma', 'Buhigwe', 'Market Area', NULL, -3.3691000, 36.6860000, 'market area', 0.0, '/safari_tanzania/public/uploads/accommodations/acc_6_03e5b108aa6c5472.jpg', 'approved', '2026-06-24 06:35:24'),
(7, 11, 'hotel', 'Focus', NULL, 'Mbeya', 'Mbeya', 'Mbeya City', 'Sisimba', NULL, -3.3635376, 36.6777273, 'Mbeya', 0.0, '/safari_tanzania/public/uploads/accommodations/acc_7_50a7dd510ae42925.png', 'pending', '2026-06-24 08:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_amenities`
--

CREATE TABLE `accommodation_amenities` (
  `id` int(11) NOT NULL,
  `accommodation_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accommodation_amenities`
--

INSERT INTO `accommodation_amenities` (`id`, `accommodation_id`, `amenity_id`) VALUES
(1, 1, 1),
(2, 1, 3),
(3, 1, 6),
(4, 1, 7),
(5, 1, 8),
(6, 2, 1),
(7, 2, 2),
(8, 2, 3),
(9, 2, 4),
(10, 2, 6),
(11, 3, 1),
(12, 3, 3),
(13, 3, 4),
(14, 3, 5),
(15, 3, 6);

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_images`
--

CREATE TABLE `accommodation_images` (
  `id` int(11) NOT NULL,
  `accommodation_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `is_cover` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accommodation_images`
--

INSERT INTO `accommodation_images` (`id`, `accommodation_id`, `image_path`, `original_name`, `is_cover`, `sort_order`, `created_at`) VALUES
(1, 5, '/safari_tanzania/public/uploads/accommodations/acc_5_d03e52c630fd5f85.jpg', 'download (6).jpg', 0, 0, '2026-05-11 18:27:30'),
(4, 5, '/safari_tanzania/public/uploads/accommodations/acc_5_5db6e3cc611475f2.jpg', 'download (2).jpg', 0, 3, '2026-05-11 18:44:33'),
(5, 5, '/safari_tanzania/public/uploads/accommodations/acc_5_2123af373cb12936.jpg', 'download.jpg', 1, 4, '2026-05-11 18:45:01'),
(6, 6, '/safari_tanzania/public/uploads/accommodations/acc_6_03e5b108aa6c5472.jpg', 'download (3).jpg', 1, 0, '2026-06-24 06:35:24'),
(7, 7, '/safari_tanzania/public/uploads/accommodations/acc_7_50a7dd510ae42925.png', 'ChatGPT Image Jun 23, 2026, 10_48_54 AM.png', 1, 0, '2026-06-24 08:00:22'),
(8, 7, '/safari_tanzania/public/uploads/accommodations/acc_7_40e18bccc8b92d0c.png', 'ChatGPT Image Jun 23, 2026, 10_22_16 AM.png', 0, 1, '2026-06-24 08:00:22'),
(9, 7, '/safari_tanzania/public/uploads/accommodations/acc_7_125c2e58956809de.png', 'DS-KP8200-HE1_image_1.png', 0, 2, '2026-06-24 08:00:22'),
(10, 7, '/safari_tanzania/public/uploads/accommodations/acc_7_60b0ca9d24666caa.jpg', 'IMG-20260623-WA0015.jpg', 0, 3, '2026-06-24 08:00:22'),
(11, 7, '/safari_tanzania/public/uploads/accommodations/acc_7_a0eb9faada579196.jpg', 'IMG-20260623-WA0016.jpg', 0, 4, '2026-06-24 08:00:22'),
(12, 7, '/safari_tanzania/public/uploads/accommodations/acc_7_cd6c0477d699a9ea.jpg', 'IMG-20260623-WA0012.jpg', 0, 5, '2026-06-24 08:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_services`
--

CREATE TABLE `accommodation_services` (
  `id` int(11) NOT NULL,
  `accommodation_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accommodation_services`
--

INSERT INTO `accommodation_services` (`id`, `accommodation_id`, `name`, `description`, `price`, `is_visible`, `created_at`, `updated_at`) VALUES
(1, 4, 'Breakfast', NULL, NULL, 1, '2026-05-11 18:56:41', '2026-05-11 18:56:41'),
(2, 4, 'Parking', 'we offer car parcking with no payment', NULL, 1, '2026-05-11 19:00:02', '2026-05-11 19:00:02'),
(3, 5, 'Swimming Pool', 'free sweemming pool', NULL, 1, '2026-05-15 21:58:11', '2026-05-15 21:58:11');

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_workers`
--

CREATE TABLE `accommodation_workers` (
  `id` int(11) NOT NULL,
  `accommodation_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accommodation_workers`
--

INSERT INTO `accommodation_workers` (`id`, `accommodation_id`, `worker_id`, `created_at`) VALUES
(1, 4, 7, '2026-06-15 23:43:21'),
(2, 5, 9, '2026-06-16 09:58:18');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `created_at`) VALUES
(1, 1, 'Approved accommodation: \"Focus Hotel\" (ID 4)', '2026-05-11 21:49:04'),
(2, 1, 'Approved accommodation: \"fuxing Guest House\" (ID 5)', '2026-05-11 21:50:30'),
(3, 1, 'updated platform settings', '2026-06-24 09:40:28'),
(4, 1, 'Approved accommodation: \"tanganyika lodge\" (ID 6)', '2026-06-24 09:43:59');

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`) VALUES
(5, 'Air Conditioning'),
(8, 'Airport Shuttle'),
(3, 'Breakfast'),
(7, 'Game Drive'),
(4, 'Parking'),
(6, 'Restaurant'),
(2, 'Swimming Pool'),
(1, 'WiFi');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `traveler_id` int(11) NOT NULL,
  `accommodation_id` int(11) DEFAULT NULL,
  `room_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `nights` int(11) DEFAULT NULL,
  `guests` int(11) NOT NULL DEFAULT 1,
  `total_price` decimal(10,2) NOT NULL,
  `booking_status` enum('confirmed','cancelled','completed') NOT NULL DEFAULT 'confirmed',
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `traveler_id`, `accommodation_id`, `room_id`, `check_in`, `check_out`, `nights`, `guests`, `total_price`, `booking_status`, `payment_status`, `created_at`) VALUES
(1, 6, NULL, 4, '2026-05-16', '2026-05-17', NULL, 2, 520.00, 'confirmed', 'pending', '2026-05-14 16:45:02'),
(2, 8, NULL, 5, '2026-06-16', '2026-06-18', NULL, 1, 240.00, 'confirmed', 'paid', '2026-06-16 00:12:25'),
(3, 8, NULL, 10, '2026-06-16', '2026-06-19', NULL, 2, 390.00, 'confirmed', 'paid', '2026-06-16 05:08:57'),
(4, 10, NULL, 10, '2026-06-16', '2026-06-18', NULL, 2, 520.00, 'confirmed', 'paid', '2026-06-16 10:06:07'),
(5, 6, NULL, 12, '2026-06-24', '2026-06-28', NULL, 1, 1000000.00, 'confirmed', 'pending', '2026-06-24 09:47:36'),
(6, 8, NULL, 12, '2026-06-28', '2026-06-30', NULL, 1, 500000.00, 'confirmed', 'paid', '2026-06-24 10:44:19'),
(7, 8, NULL, 2, '2026-06-24', '2026-06-26', NULL, 1, 1200.00, 'confirmed', 'paid', '2026-06-24 10:46:07'),
(8, 8, NULL, 2, '2026-06-24', '2026-06-25', NULL, 1, 600.00, 'confirmed', 'paid', '2026-06-24 10:46:56');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `rooms_booked` int(11) NOT NULL DEFAULT 1,
  `price_per_room` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `room_id`, `rooms_booked`, `price_per_room`, `subtotal`, `created_at`) VALUES
(1, 1, 4, 1, 520.00, 520.00, '2026-06-24 10:51:39'),
(2, 2, 5, 1, 120.00, 240.00, '2026-06-24 10:51:39'),
(3, 3, 10, 1, 130.00, 390.00, '2026-06-24 10:51:39'),
(4, 4, 10, 1, 130.00, 260.00, '2026-06-24 10:51:39'),
(5, 5, 12, 1, 250000.00, 1000000.00, '2026-06-24 10:51:39'),
(6, 6, 12, 1, 250000.00, 500000.00, '2026-06-24 10:51:39'),
(7, 7, 2, 1, 600.00, 1200.00, '2026-06-24 10:51:40'),
(8, 8, 2, 1, 600.00, 600.00, '2026-06-24 10:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(80) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `owner_verifications`
--

CREATE TABLE `owner_verifications` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `property_type` enum('guest_house','lodge','hotel') NOT NULL,
  `registration_number` varchar(100) NOT NULL,
  `business_address` varchar(255) NOT NULL,
  `document_path` varchar(500) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `owner_verifications`
--

INSERT INTO `owner_verifications` (`id`, `owner_id`, `business_name`, `property_type`, `registration_number`, `business_address`, `document_path`, `document_name`, `status`, `admin_notes`, `submitted_at`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 5, 'Daniel Timoth', 'hotel', '898y9120', 'ARUSHA', 'public/uploads/owner_verifications/owner_5_bcd36e259fe08974.jpg', 'nida.jpg', 'pending', NULL, '2026-05-11 17:46:24', NULL, '2026-05-11 17:46:24', '2026-05-11 17:46:24'),
(2, 11, 'mbeya', 'guest_house', 'ETNNB67799', 'Mbeya', 'public/uploads/owner_verifications/owner_11_d170ec9046398e91.jpg', 'letters and applications.pdf.jpg', 'pending', NULL, '2026-06-24 08:01:53', NULL, '2026-06-24 08:01:50', '2026-06-24 08:01:53');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('card','mobile_money','bank_transfer') NOT NULL,
  `transaction_reference` varchar(150) DEFAULT NULL,
  `payment_status` enum('success','failed') NOT NULL DEFAULT 'success',
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `payment_method`, `transaction_reference`, `payment_status`, `paid_at`) VALUES
(1, 2, 240.00, 'mobile_money', 'B200B69EE2F1', 'success', '2026-06-16 00:12:49'),
(2, 4, 520.00, 'mobile_money', 'EA6EDEECC099', 'success', '2026-06-16 10:06:30'),
(3, 6, 500000.00, 'card', 'A4084CCBC754', 'success', '2026-06-24 10:44:50'),
(4, 7, 1200.00, 'card', '36C539E7E9C5', 'success', '2026-06-24 10:46:10'),
(5, 8, 600.00, 'card', 'CB8F8503F268', 'success', '2026-06-24 10:47:04'),
(6, 3, 390.00, 'card', '2EC629892CF5', 'success', '2026-06-24 10:48:49');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`key`, `value`, `updated_at`) VALUES
('login_rate_limit_per_minute', '1', '2026-06-24 12:40:28'),
('maintenance_mode', '0', '2026-06-24 12:28:38'),
('site_title', 'Safari Tanzania', '2026-06-24 12:28:38'),
('verification_required', '1', '2026-06-24 12:28:38');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `accommodation_id` int(11) NOT NULL,
  `room_type` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 1,
  `total_rooms` int(11) NOT NULL DEFAULT 1,
  `room_amenities` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `accommodation_id`, `room_type`, `price`, `capacity`, `total_rooms`, `room_amenities`, `created_at`) VALUES
(1, 1, 'Luxury Tented Suite', 450.00, 2, 6, NULL, '2026-04-24 15:00:10'),
(2, 1, 'Family Tent', 600.00, 4, 3, NULL, '2026-04-24 15:00:10'),
(3, 2, 'Ocean View Room', 220.00, 2, 10, NULL, '2026-04-24 15:00:10'),
(4, 2, 'Beachfront Villa', 520.00, 4, 4, NULL, '2026-04-24 15:00:10'),
(5, 3, 'Standard Double', 120.00, 2, 12, NULL, '2026-04-24 15:00:10'),
(6, 3, 'Mountain Suite', 280.00, 3, 5, NULL, '2026-04-24 15:00:10'),
(7, 4, 'VIP', 100.00, 2, 1, NULL, '2026-05-11 17:42:37'),
(8, 4, 'VIPP', 170.00, 2, 1, NULL, '2026-05-11 17:43:04'),
(9, 5, 'VIP', 150.00, 2, 1, NULL, '2026-05-11 18:34:51'),
(10, 5, 'Regular', 130.00, 2, 4, NULL, '2026-05-11 18:48:17'),
(11, 6, 'Double Room', 50000.00, 2, 1, 'AC, private bathroom', '2026-06-24 06:38:02'),
(12, 6, 'VIP Single', 250000.00, 1, 1, 'AC, free wifi, bathroom', '2026-06-24 06:39:14'),
(13, 7, 'Double Room', 25000.00, 2, 1, 'private bathroom', '2026-06-24 08:06:44');

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `is_cover` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `image_path`, `original_name`, `is_cover`, `sort_order`, `created_at`) VALUES
(1, 10, '/safari_tanzania/public/uploads/rooms/room_10_cbf822411e560146.jpg', 'The Atlantis Royal.jpg', 1, 0, '2026-05-11 18:48:17'),
(2, 11, '/safari_tanzania/public/uploads/rooms/room_11_47881ce24796d39a.jpg', 'photo-1.jpg', 1, 0, '2026-06-24 06:38:02'),
(3, 12, '/safari_tanzania/public/uploads/rooms/room_12_7689f234963e8cc6.jpg', 'photo-1.jpg', 1, 0, '2026-06-24 06:39:14'),
(4, 12, '/safari_tanzania/public/uploads/rooms/room_12_6e1c302d0a0e580e.jpg', 'photo-2.jpg', 0, 1, '2026-06-24 06:39:14'),
(5, 12, '/safari_tanzania/public/uploads/rooms/room_12_04bebe9f18427a44.jpg', 'photo-3.jpg', 0, 2, '2026-06-24 06:39:14'),
(6, 13, '/safari_tanzania/public/uploads/rooms/room_13_ff0869554c9406f6.jpg', 'photo-1.jpg', 1, 0, '2026-06-24 08:06:44'),
(7, 13, '/safari_tanzania/public/uploads/rooms/room_13_21bb9352edc1d4a9.jpg', 'photo-2.jpg', 0, 1, '2026-06-24 08:06:44'),
(8, 13, '/safari_tanzania/public/uploads/rooms/room_13_f88a61378cb9a873.jpg', 'photo-3.jpg', 0, 2, '2026-06-24 08:06:44');

-- --------------------------------------------------------

--
-- Table structure for table `room_inventory`
--

CREATE TABLE `room_inventory` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `inventory_date` date NOT NULL,
  `booked_rooms` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_inventory`
--

INSERT INTO `room_inventory` (`id`, `room_id`, `inventory_date`, `booked_rooms`) VALUES
(1, 4, '2026-05-16', 1),
(2, 5, '2026-06-16', 1),
(3, 5, '2026-06-17', 1),
(4, 10, '2026-06-16', 2),
(5, 10, '2026-06-17', 2),
(6, 10, '2026-06-18', 1),
(9, 12, '2026-06-24', 1),
(10, 12, '2026-06-25', 1),
(11, 12, '2026-06-26', 1),
(12, 12, '2026-06-27', 1),
(13, 12, '2026-06-28', 1),
(14, 12, '2026-06-29', 1),
(15, 2, '2026-06-24', 2),
(16, 2, '2026-06-25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_events`
--

INSERT INTO `security_events` (`id`, `user_id`, `event_type`, `meta`, `created_at`) VALUES
(1, 5, 'login_success', '{\"ip\":\"::1\"}', '2026-06-24 12:38:07'),
(2, 9, 'login_success', '{\"ip\":\"::1\"}', '2026-06-24 12:41:48'),
(3, 6, 'login_success', '{\"ip\":\"::1\"}', '2026-06-24 12:42:58'),
(4, 1, 'logout', NULL, '2026-06-24 12:44:31'),
(5, 1, 'logout', '{\"ip\":\"::1\"}', '2026-06-24 13:32:53'),
(6, 8, 'login_success', '{\"ip\":\"::1\"}', '2026-06-24 13:34:11');

-- --------------------------------------------------------

--
-- Table structure for table `service_images`
--

CREATE TABLE `service_images` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_images`
--

INSERT INTO `service_images` (`id`, `service_id`, `image_path`, `original_name`, `sort_order`, `created_at`) VALUES
(1, 1, '/safari_tanzania/public/uploads/services/service_1_8425cd9b4d360551.jpg', 'download (7).jpg', 0, '2026-05-11 18:56:41'),
(2, 2, '/safari_tanzania/public/uploads/services/service_2_f4ad18850fecc850.jpg', 'download (30).jpg', 0, '2026-05-11 19:00:02'),
(3, 3, '/safari_tanzania/public/uploads/services/service_3_070a9544515d19f9.jpg', '5840674512011923.jpg', 0, '2026-05-15 21:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('traveler','owner','admin','worker') NOT NULL DEFAULT 'traveler',
  `owner_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `owner_id`, `status`, `created_at`) VALUES
(1, 'System Admin', 'Admin@gmail.com', '+255700000000', '$2y$10$IDfZ2xUJzg9nMoFYsbU0su7VG1Ozd1Ar9RzR.vHvSOrm.6ONdCsti', 'admin', NULL, 'active', '2026-04-24 15:00:10'),
(2, 'Demo Owner', 'owner@safaritanzania.test', '+255711111111', '$2y$10$u7mOlRHtwm.sDKE1TVc5VuvVHrwWz69iP/VdfZaT9BoKiaAiyE7kW', 'owner', NULL, 'active', '2026-04-24 15:00:10'),
(3, 'Demo Traveler', 'traveler@safaritanzania.test', '+255722222222', '$2y$10$u7mOlRHtwm.sDKE1TVc5VuvVHrwWz69iP/VdfZaT9BoKiaAiyE7kW', 'traveler', NULL, 'active', '2026-04-24 15:00:10'),
(4, 'Owner Gate Test', 'owner-gate-20260511041619@example.test', '+255700111222', '$2y$10$WPF.wexomCpJWFmAJ3RNjebrNnvAog.M4jtWJ5bRFsQJSaBCccbnO', 'owner', NULL, 'active', '2026-05-11 01:16:19'),
(5, 'Daniel Timoth', 'dtimoth24@gmail.com', '0615292503', '$2y$10$xp5GcSthv5QNPfv0YrpuIe14oqnFSgfZgdIm1blqK9XfEnpgW88ce', 'owner', NULL, 'active', '2026-05-11 01:16:48'),
(6, 'Daniel Matope', 'dmatope24@gmail.com', '0615292503', '$2y$10$DF4Pzn5LeDMa2wj1IKDOduM7WEezY2tbYolDPPpdC9Kt1rAWmctRm', 'traveler', NULL, 'active', '2026-05-11 21:11:29'),
(7, 'Samwel Matope', 'samtimoth4@gmail.com', '0655851270', '$2y$10$qmfwIgZHFnPX6uwNVLpFBeGzjZ7GABEiffZgeSqI6VKiM9T2WhZfC', 'worker', 5, 'active', '2026-06-15 23:43:21'),
(8, 'dry food enterprise', 'dryfoodenterprise@gmail.com', '+255752492255', '$2y$10$yHkhgK4wHBPrSOGItv7k0eqY.NHkuw87TAxyXPA5em9hokZY2MFWq', 'traveler', NULL, 'active', '2026-06-16 00:11:42'),
(9, 'shalom erick', 'dmatope2410@gmail.com', '0743651270', '$2y$10$BoHs0msXc/145JEp05a3P.XSpOxSnzvvJ7J6jpHfMTLEEe5icgHCO', 'worker', 5, 'active', '2026-06-16 09:58:18'),
(10, 'erick ayo', 'erick@gmail.com', '+255752492255', '$2y$10$/4nmXMy8Ieb1QXm0ujwUIObQJ4z7ywwyr9J.jFPz.EhbYsPw3ruFK', 'traveler', NULL, 'active', '2026-06-16 10:03:14'),
(11, 'shalom phidosy', 'shalomphidosy@gmail.com', '+255743651270', '$2y$10$M6ynF711LBYFjWI0blZriOJ4VQmJ64gVId3ZQkcmWPGGlNfRd2cn.', 'owner', NULL, 'active', '2026-06-24 07:58:22'),
(12, 'Sam Timoth', 'admin@mail.com', '+255743651270', '$2y$10$akDeyDE1MSAwEDB6h8Phee7OnPe2dBtYJ/6im6WXYMqzLi8RPgpFy', 'traveler', NULL, 'active', '2026-06-24 08:09:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accommodations`
--
ALTER TABLE `accommodations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_acc_owner` (`owner_id`);

--
-- Indexes for table `accommodation_amenities`
--
ALTER TABLE `accommodation_amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_acc_amenity` (`accommodation_id`,`amenity_id`),
  ADD KEY `fk_aa_amenity` (`amenity_id`);

--
-- Indexes for table `accommodation_images`
--
ALTER TABLE `accommodation_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_acc_image_acc` (`accommodation_id`);

--
-- Indexes for table `accommodation_services`
--
ALTER TABLE `accommodation_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_service_acc` (`accommodation_id`);

--
-- Indexes for table `accommodation_workers`
--
ALTER TABLE `accommodation_workers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_acc_worker` (`accommodation_id`,`worker_id`),
  ADD KEY `fk_aw_worker` (`worker_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_user` (`user_id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_admin` (`admin_id`);

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_book_user` (`traveler_id`),
  ADD KEY `fk_book_room` (`room_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bi_booking` (`booking_id`),
  ADD KEY `fk_bi_room` (`room_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`user_id`);

--
-- Indexes for table `owner_verifications`
--
ALTER TABLE `owner_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `owner_id` (`owner_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pay_book` (`booking_id`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_room_acc` (`accommodation_id`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_room_image_room` (`room_id`);

--
-- Indexes for table `room_inventory`
--
ALTER TABLE `room_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_room_date` (`room_id`,`inventory_date`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_images`
--
ALTER TABLE `service_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_service_image_service` (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_owner` (`owner_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accommodations`
--
ALTER TABLE `accommodations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `accommodation_amenities`
--
ALTER TABLE `accommodation_amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `accommodation_images`
--
ALTER TABLE `accommodation_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `accommodation_services`
--
ALTER TABLE `accommodation_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `accommodation_workers`
--
ALTER TABLE `accommodation_workers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `owner_verifications`
--
ALTER TABLE `owner_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `room_inventory`
--
ALTER TABLE `room_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_images`
--
ALTER TABLE `service_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accommodations`
--
ALTER TABLE `accommodations`
  ADD CONSTRAINT `fk_acc_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accommodation_amenities`
--
ALTER TABLE `accommodation_amenities`
  ADD CONSTRAINT `fk_aa_acc` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aa_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accommodation_images`
--
ALTER TABLE `accommodation_images`
  ADD CONSTRAINT `fk_acc_image_acc` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accommodation_services`
--
ALTER TABLE `accommodation_services`
  ADD CONSTRAINT `fk_service_acc` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accommodation_workers`
--
ALTER TABLE `accommodation_workers`
  ADD CONSTRAINT `fk_aw_acc` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aw_worker` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_book_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_book_user` FOREIGN KEY (`traveler_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `fk_bi_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bi_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `owner_verifications`
--
ALTER TABLE `owner_verifications`
  ADD CONSTRAINT `fk_owner_verification_user` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_book` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_room_acc` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `fk_room_image_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_inventory`
--
ALTER TABLE `room_inventory`
  ADD CONSTRAINT `fk_ri_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_images`
--
ALTER TABLE `service_images`
  ADD CONSTRAINT `fk_service_image_service` FOREIGN KEY (`service_id`) REFERENCES `accommodation_services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
