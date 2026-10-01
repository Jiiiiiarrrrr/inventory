-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 19, 2026 at 02:00 PM
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
-- Database: `brewco_inventory`
--

-- Shared-hosting note: stock movement logic runs in PHP (db.php); no stored routines are required.


-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `detail` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `role`, `action`, `detail`, `ip`, `created_at`) VALUES
(1, 4, 'superadmin', 'login', 'User logged in as superadmin', '::1', '2026-09-07 06:33:01'),
(2, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-09 11:30:38'),
(3, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-09 11:36:28'),
(4, 5, 'cashier', 'login', 'User logged in as cashier', '::1', '2026-09-09 11:43:27'),
(5, 0, 'client', 'pos.order_placed', 'Order POS-1001 placed (₱660)', '::1', '2026-09-09 11:43:44'),
(6, 5, 'cashier', 'pos.paid', 'Order POS-1001 paid (₱700, change ₱40)', '::1', '2026-09-09 11:44:16'),
(7, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-14 02:36:48'),
(8, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-14 03:50:48'),
(9, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-14 03:51:00'),
(10, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-14 15:09:14'),
(11, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-14 15:10:02'),
(12, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-14 15:10:13'),
(13, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-14 15:10:27'),
(14, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 03:09:03'),
(15, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 03:09:20'),
(16, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 03:09:26'),
(17, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 03:10:50'),
(18, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 03:10:54'),
(19, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 03:10:59'),
(20, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 03:11:01'),
(21, 2, 'manager', 'pr.create', 'Created PR-0044: Arabica Beans x5 kg (₱3,100) + formal letter to Finance', '::1', '2026-09-15 03:11:16'),
(22, 2, 'manager', 'pr.letter', 'Formal letter attached to PR-0044', '::1', '2026-09-15 03:11:16'),
(23, 2, 'manager', 'signature.register', 'Registered inventory e-signature from profile', '::1', '2026-09-15 03:24:27'),
(24, 2, 'manager', 'pr.create', 'Created PR-0045: Arabica Beans x5 kg (₱3,100) + formal letter to Finance', '::1', '2026-09-15 03:24:58'),
(25, 2, 'manager', 'pr.letter', 'Formal letter attached to PR-0045', '::1', '2026-09-15 03:24:58'),
(26, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 03:25:12'),
(27, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-15 03:25:15'),
(28, 3, 'finance', 'budget.set', 'Set budget for 2026-10 to ₱5,000,000,000,000', '::1', '2026-09-15 03:43:55'),
(29, 3, 'finance', 'budget.set', 'Set budget for 2026-09 to ₱100', '::1', '2026-09-15 03:44:23'),
(30, 3, 'finance', 'budget.set', 'Set budget for 2026-09 to ₱500,000,000', '::1', '2026-09-15 03:44:35'),
(31, 3, 'finance', 'pr.approved', 'approved PR-0045: Arabica Beans x5.00 kg (₱3,100)', '::1', '2026-09-15 03:45:05'),
(32, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-15 03:45:29'),
(33, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 03:45:33'),
(34, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 03:56:24'),
(35, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 06:45:13'),
(36, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:46:41'),
(37, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 06:46:45'),
(38, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:48:39'),
(39, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 06:48:44'),
(40, 2, 'manager', 'shipment.receive', 'Received 80 L from SHP-769 into warehouse (WHR-0001) — full quantity.', '::1', '2026-09-15 06:48:52'),
(41, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:49:05'),
(42, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 06:49:13'),
(43, 1, 'clerk', 'warehouse.request', 'Clerk requested REQ-0001: Fresh Milk x22 L from warehouse (available 80 L)', '::1', '2026-09-15 06:49:30'),
(44, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:49:35'),
(45, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 06:49:47'),
(46, 2, 'manager', 'stockgate.approve', 'Approved stock request #1 - released to the warehouse queue', '::1', '2026-09-15 06:49:55'),
(47, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:50:07'),
(48, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 06:50:13'),
(49, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:50:20'),
(50, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 06:50:24'),
(51, 2, 'manager', 'warehouse.approve_request', 'Approved REQ-0001: Fresh Milk x22 L -> cafe', '::1', '2026-09-15 06:50:29'),
(52, 2, 'manager', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:50:39'),
(53, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 06:50:45'),
(54, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 06:51:42'),
(55, 2, 'manager', 'login', 'User logged in as manager', '::1', '2026-09-15 06:51:46'),
(56, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-15 08:02:27'),
(57, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-15 08:03:04'),
(58, 1, 'clerk', 'login', 'User logged in as clerk', '::1', '2026-09-15 08:03:08'),
(59, 1, 'clerk', 'auth.logout', 'User logged out', '::1', '2026-09-15 08:04:07'),
(60, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-15 09:13:15'),
(61, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-15 09:13:53'),
(62, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:11:22'),
(63, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:12:16'),
(64, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:12:23'),
(65, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:12:29'),
(66, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:15:04'),
(67, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:24:20'),
(68, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:24:32'),
(69, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:24:40'),
(70, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:28:04'),
(71, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:28:57'),
(72, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:29:32'),
(73, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:29:38'),
(74, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:29:50'),
(75, 3, 'finance', 'login', 'User logged in as finance', '::1', '2026-09-17 01:56:20'),
(76, 3, 'finance', 'auth.logout', 'User logged out', '::1', '2026-09-17 01:56:41');

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `batch_ref` varchar(40) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget`
--

CREATE TABLE `budget` (
  `id` int(10) UNSIGNED NOT NULL,
  `month` char(7) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `used` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget`
--

INSERT INTO `budget` (`id`, `month`, `total`, `used`) VALUES
(1, '2026-08', 150000.00, 96500.00),
(2, '2026-10', 9999999999.99, 0.00),
(3, '2026-09', 500000000.00, 3100.00);

-- --------------------------------------------------------

--
-- Table structure for table `budget_log`
--

CREATE TABLE `budget_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `pr_id` int(10) UNSIGNED NOT NULL,
  `decision` enum('approved','declined') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `decided_by` int(10) UNSIGNED NOT NULL,
  `decided_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_log`
--

INSERT INTO `budget_log` (`id`, `pr_id`, `decision`, `amount`, `decided_by`, `decided_at`) VALUES
(1, 4, 'approved', 3100.00, 3, '2026-09-15 11:45:05');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(60) NOT NULL,
  `emoji` varchar(10) NOT NULL DEFAULT '?️',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `emoji`, `deleted_at`) VALUES
(1, 'Coffee', '🏷️', NULL),
(2, 'Dairy', '🏷️', NULL),
(3, 'Syrup', '🏷️', NULL),
(4, 'Supplies', '🏷️', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hrms_applicants`
--

CREATE TABLE `hrms_applicants` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `interview_mode` enum('Online','Walk-in') DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `stage` varchar(30) NOT NULL DEFAULT 'Pending',
  `offer_department` varchar(100) DEFAULT NULL,
  `offer_salary` decimal(12,2) DEFAULT NULL,
  `offer_schedule_days` varchar(100) DEFAULT NULL,
  `offer_schedule_start` varchar(10) DEFAULT NULL,
  `offer_schedule_end` varchar(10) DEFAULT NULL,
  `offer_notes` text DEFAULT NULL,
  `offer_published_at` timestamp NULL DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contract_path` varchar(255) DEFAULT NULL,
  `registered_signature_path` varchar(255) DEFAULT NULL,
  `staff_password_hash` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_applicants`
--

INSERT INTO `hrms_applicants` (`id`, `name`, `first_name`, `last_name`, `email`, `position`, `interview_mode`, `status`, `stage`, `offer_department`, `offer_salary`, `offer_schedule_days`, `offer_schedule_start`, `offer_schedule_end`, `offer_notes`, `offer_published_at`, `cv_path`, `notes`, `created_at`, `contract_path`, `registered_signature_path`, `staff_password_hash`) VALUES
(1, 'Miguel Torres', 'Miguel', 'Torres', 'miguel@email.com', 'Barista', NULL, 'Hired', 'Hired', '93493247', 9999999999.99, '823497509234875', '15:08', NULL, '34985728345', '2026-09-14 06:36:12', NULL, 'Walk-in', '2026-09-07 02:10:13', NULL, NULL, NULL),
(2, 'Lisa Wong', 'Lisa', 'Wong', 'lisa@email.com', 'Cashier', NULL, 'Pending', 'Final Interview', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Referred', '2026-09-07 02:10:13', NULL, NULL, NULL),
(3, 'Robert Kim', 'Robert', 'Kim', 'robert@email.com', 'Cleaner', NULL, 'Hired', 'Hired', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hired', '2026-09-07 02:10:13', NULL, NULL, NULL),
(4, 'Elena Diaz', 'Elena', 'Diaz', 'elena@email.com', 'Barista', NULL, 'Rejected', 'Rejected', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Failed interview', '2026-09-07 02:10:13', NULL, NULL, NULL),
(5, 'Tomás Rivera', 'Tomás', 'Rivera', 'tomas@email.com', 'Cashier', NULL, 'Pending', 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Online', '2026-09-07 02:10:13', NULL, NULL, NULL),
(6, 'Yuki Tanaka', 'Yuki', 'Tanaka', 'yuki@email.com', 'Barista', NULL, 'Pending', 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3 yrs exp', '2026-09-07 02:10:13', NULL, NULL, NULL),
(7, 'jayr fabon', 'jayr', 'fabon', 'fabonjayr89@gmail.com', 'Barista', 'Online', 'Hired', 'Hired', 'sdadasdasdasdasdasfadgadfaf', 190000.00, NULL, '14:01', '13:02', 'sdada', '2026-09-14 05:01:27', 'uploads/cv/cv_1788760348_469a8fef.pdf', 'ra', '2026-09-07 05:52:28', 'uploads/contracts/contract_1789362087_4e3561f2.docx', NULL, NULL),
(8, 'Jenwin Docabo', 'Jenwin', 'Docabo', 'jenwinnnn@gmail.com', 'Barista', 'Walk-in', 'Rejected', 'Rejected', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/cv/cv_1789367531_df6c9cc8.docx', 'putangina mo buseng', '2026-09-14 06:32:11', NULL, NULL, NULL),
(9, 'jayrpapwet fabon', 'jayrpapwet', 'fabon', 'papwet@gmail.com', 'Barista', 'Online', 'Hired', 'Hired', 'dasd098asua', 1000.00, 'lskdhya7dfyvasbl', '14:47', NULL, 'dasfiayfuaifoa[faofiassa', '2026-09-14 06:46:47', 'uploads/cv/cv_1789368330_6109e338.docx', 'buseng', '2026-09-14 06:45:30', 'uploads/contracts/contract_1789368407_a84f1fce.docx', NULL, NULL),
(10, 'Jayr Fabon', 'Jayr', 'Fabon', 'fabonjayr99@gmail.com', 'Barista', 'Walk-in', 'Hired', 'Hired', 'cafe', 2234324.00, NULL, NULL, NULL, 'fasadasdss', '2026-09-15 04:20:22', 'uploads/cv/cv_1789445908_086c53bb.docx', 'dasd', '2026-09-15 04:18:28', 'uploads/contracts/contract_20260915_062022_81ba0f9d.docx', 'uploads/signatures/reg_20260915_081448_e6e3f95d.png', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hrms_applicant_accounts`
--

CREATE TABLE `hrms_applicant_accounts` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_applicant_accounts`
--

INSERT INTO `hrms_applicant_accounts` (`id`, `applicant_id`, `email`, `password`, `full_name`, `status`, `created_at`) VALUES
(1, 8, 'jenwinnnn@gmail.com', '$2y$10$0n3ChJeVtSodeIYq32W1VOxcYIkkZUmAPU2izqN68qNEKolPG6/bO', 'Jenwin Docabo', 'active', '2026-09-14 06:32:11'),
(2, 9, 'papwet@gmail.com', '$2y$10$Udbbpu3ob9QB2az2yYV16.6GJ5ubtppgHz6khPcrZT.vc556twnhS', 'jayrpapwet fabon', 'active', '2026-09-14 06:45:30'),
(3, 10, 'fabonjayr99@gmail.com', '$2y$10$K45qyWHjVZ./y5ZslOt7XerShK1anqen8sIwkvYMQQzDveUQ7N2V.', 'Jayr Fabon', 'active', '2026-09-15 04:18:28');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_approval_history`
--

CREATE TABLE `hrms_approval_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `request_type` varchar(50) DEFAULT NULL,
  `employee` varchar(150) DEFAULT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `comment` text DEFAULT NULL,
  `acted_by` varchar(150) DEFAULT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_approval_history`
--

INSERT INTO `hrms_approval_history` (`id`, `request_id`, `request_type`, `employee`, `old_status`, `new_status`, `comment`, `acted_by`, `acted_at`) VALUES
(1, 8, 'General', 'Maria Santos', 'Pending', 'Rejected', '', 'System Admin', '2026-09-14 04:52:33'),
(2, 10, 'Schedule Change', 'jayr fabon', 'Pending', 'Approved', '', 'System Admin', '2026-09-14 05:05:28'),
(3, 9, 'General', 'jayr fabon', 'Pending', 'Rejected', '', 'System Admin', '2026-09-14 05:05:39'),
(4, 7, 'Schedule Change', 'Pedro Garcia', 'Pending', 'Approved', '', 'System Admin', '2026-09-14 06:39:31'),
(5, 4, 'Schedule Change', 'Ana Reyes', 'Pending', 'Approved', '', 'System Admin', '2026-09-14 06:39:42'),
(6, 2, 'Leave', 'Jenwin Docabo', 'Pending', 'Approved', '', 'System Admin', '2026-09-14 06:39:43'),
(7, 1, 'Leave', 'Jayr Fabon', 'Pending', 'Approved', '', 'System Admin', '2026-09-14 06:39:44'),
(8, 6, 'Schedule Change', 'Pedro Garcia', 'Pending', 'Rejected', '', 'System Admin', '2026-09-14 06:39:46'),
(9, 11, 'General', 'jayr fabon', 'Pending', 'Rejected', '', 'System Admin', '2026-09-15 01:32:41'),
(10, 4, 'Leave', 'jayr fabon', 'Pending', 'Rejected', '', 'System Admin', '2026-09-15 02:02:17'),
(11, 1, 'Leave', 'Pedro Garcia', 'Pending', 'Rejected', '', 'System Admin', '2026-09-15 02:06:23'),
(12, 2, 'Leave', 'Sofia Cruz', 'Pending', 'Rejected', '', 'System Admin', '2026-09-15 02:06:24'),
(13, 5, 'Leave', 'jayr fabon', 'Pending', 'Approved', '', 'System Admin', '2026-09-15 09:18:53'),
(14, 13, 'General', 'jayr fabon', 'Pending', 'Approved', '', 'System Admin', '2026-09-15 09:18:54'),
(15, 12, 'General', 'jayr fabon', 'Pending', 'Approved', '', 'System Admin', '2026-09-15 09:19:02'),
(16, 14, 'General', 'Jayr Fabon', 'Pending', 'Rejected', '', 'System Admin', '2026-09-15 09:24:58');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_approval_requests`
--

CREATE TABLE `hrms_approval_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `employee_name` varchar(150) NOT NULL,
  `kind` varchar(50) NOT NULL,
  `detail` text NOT NULL,
  `leave_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `days` int(11) DEFAULT 1,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_comment` text DEFAULT NULL,
  `acted_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `routed_to` varchar(20) NOT NULL DEFAULT 'superadmin',
  `submitted_by` int(11) DEFAULT NULL,
  `letter_path` varchar(255) DEFAULT NULL,
  `letter_name` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signed_by` varchar(150) DEFAULT NULL,
  `signer_name` varchar(150) DEFAULT NULL,
  `signer_photo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_approval_requests`
--

INSERT INTO `hrms_approval_requests` (`id`, `employee_id`, `employee_name`, `kind`, `detail`, `leave_type`, `start_date`, `end_date`, `days`, `status`, `admin_comment`, `acted_by`, `created_at`, `routed_to`, `submitted_by`, `letter_path`, `letter_name`, `signature_path`, `signed_at`, `signed_by`, `signer_name`, `signer_photo_path`) VALUES
(1, 1, 'Jayr Fabon', 'Leave', 'Family emergency', 'Paid', '2025-09-10', '2025-09-11', 2, 'Approved', '', 'System Admin', '2026-09-07 02:10:13', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 'Jenwin Docabo', 'Leave', 'Medical appointment', 'Paid', '2025-09-15', '2025-09-15', 1, 'Approved', '', 'System Admin', '2026-09-07 02:10:13', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 4, 'Juan dela Cruz', 'Leave', 'Vacation', 'Unpaid', '2025-09-20', '2025-09-22', 3, 'Approved', NULL, NULL, '2026-09-07 02:10:13', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 5, 'Ana Reyes', 'Schedule Change', 'Morning shift', NULL, NULL, NULL, 1, 'Approved', '', 'System Admin', '2026-09-07 02:10:13', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 6, 'Pedro Garcia', 'Schedule Change', 'Schedule approval request for Pedro Garcia (Staff): Mon, Wed, Fri from 14:00 to 22:00. Admin comment: asff', NULL, NULL, NULL, 1, 'Rejected', '', 'System Admin', '2026-09-14 04:18:41', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 6, 'Pedro Garcia', 'Schedule Change', 'Schedule approval request for Pedro Garcia (Staff): Mon, Wed, Fri from 01:00 to 22:00. Admin comment: sdasd', NULL, NULL, NULL, 1, 'Approved', '', 'System Admin', '2026-09-14 04:18:56', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 3, 'Maria Santos', 'General', 'adsad', NULL, NULL, NULL, NULL, 'Rejected', '', 'System Admin', '2026-09-14 04:51:56', 'superadmin', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 9, 'jayr fabon', 'General', 'sdasd', NULL, NULL, NULL, 1, 'Rejected', '', 'System Admin', '2026-09-14 05:03:21', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 9, 'jayr fabon', 'Schedule Change', 'Schedule approval request for jayr fabon (staff): Mon, Tue, Wed, Thu, Fri, Sat from 05:00 to 17:00. Admin comment: raw', NULL, NULL, NULL, 1, 'Approved', '', 'System Admin', '2026-09-14 05:04:16', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 9, 'jayr fabon', 'General', 'xcxcx', NULL, NULL, NULL, 1, 'Rejected', '', 'System Admin', '2026-09-14 23:19:18', 'superadmin', 24, 'uploads/requests/letter_20260915_011918_c1967e94.docx', 'cv_1789368330_6109e338.docx', 'uploads/requests/sig_20260915_011918_b581197a.png', '2026-09-14 17:19:18', 'jayr fabon', NULL, NULL),
(12, 9, 'jayr fabon', 'General', 'ehhdsd', NULL, NULL, NULL, 1, 'Approved', '', 'System Admin', '2026-09-15 00:52:35', 'superadmin', 24, 'uploads/requests/letter_20260915_025235_08e3f67f.docx', 'letter_20260915_011918_c1967e94.docx', 'uploads/requests/sig_20260915_025235_1c65f171.png', '2026-09-14 18:52:35', 'jayr fabon', 'jayr fabon', 'uploads/requests/signer_20260915_025235_0d76743e.jpg'),
(13, 9, 'jayr fabon', 'General', 'gdsg', NULL, NULL, NULL, 1, 'Approved', '', 'System Admin', '2026-09-15 00:53:18', 'superadmin', 24, 'uploads/requests/letter_20260915_025318_f3e59b07.docx', 'cv_1789368330_6109e338.docx', 'uploads/requests/sig_20260915_025318_98e8d43f.png', '2026-09-14 18:53:18', 'jayr fabon', 'jayr fabon', 'uploads/requests/signer_20260915_025318_8e9a8be6.jpg'),
(14, 12, 'Jayr Fabon', 'General', 'hahaha', NULL, NULL, NULL, 1, 'Rejected', '', 'System Admin', '2026-09-15 09:23:46', 'superadmin', 28, 'uploads/requests/letter_20260915_112346_2e936ffc.docx', 'Formal_Letter_Jayr_Fabon.docx', 'uploads/requests/sig_20260915_112346_1c00e8e3.png', '2026-09-15 03:23:46', 'Jayr Fabon', 'Jayr Fabon', 'uploads/requests/signer_20260915_112346_6456b36e.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_archived_items`
--

CREATE TABLE `hrms_archived_items` (
  `id` int(11) NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `original_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `removed_by` varchar(100) DEFAULT NULL,
  `data_json` longtext DEFAULT NULL,
  `removed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `restored_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_archived_items`
--

INSERT INTO `hrms_archived_items` (`id`, `item_type`, `original_id`, `name`, `email`, `role`, `department`, `reason`, `removed_by`, `data_json`, `removed_at`, `restored_at`) VALUES
(1, 'employee', 99, 'Old Employee', 'old@brewco.ph', 'Staff', NULL, 'Resigned', 'System Admin', NULL, '2026-09-07 02:10:13', '2026-09-14 06:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_attendance_records`
--

CREATE TABLE `hrms_attendance_records` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `type` enum('time_in','time_out') NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_attendance_records`
--

INSERT INTO `hrms_attendance_records` (`id`, `employee_id`, `type`, `photo_path`, `created_at`) VALUES
(1, 1, 'time_in', NULL, '2025-08-31 23:55:00'),
(2, 1, 'time_out', NULL, '2025-09-01 09:05:00'),
(3, 1, 'time_in', NULL, '2025-09-02 00:00:00'),
(4, 1, 'time_out', NULL, '2025-09-02 09:00:00'),
(5, 2, 'time_in', NULL, '2025-09-01 01:05:00'),
(6, 2, 'time_out', NULL, '2025-09-01 10:00:00'),
(7, 4, 'time_in', NULL, '2025-09-01 22:00:00'),
(8, 4, 'time_out', NULL, '2025-09-02 06:00:00'),
(9, 5, 'time_in', NULL, '2025-08-31 23:00:00'),
(10, 5, 'time_out', NULL, '2025-09-01 07:00:00'),
(11, 6, 'time_in', NULL, '2025-09-07 00:00:00'),
(12, 9, 'time_in', 'uploads/attendance/att_9_1789362171_f4075868.jpg', '2026-09-14 05:02:51'),
(13, 9, 'time_out', 'uploads/attendance/att_9_1789362172_367e4ca7.jpg', '2026-09-14 05:02:52'),
(14, 9, 'time_in', 'uploads/attendance/att_20260915_020732_ff78863a.jpg', '2026-09-15 00:07:32'),
(15, 9, 'time_out', 'uploads/attendance/att_20260915_020733_bd1f542f.jpg', '2026-09-15 00:07:33'),
(16, 12, 'time_in', 'uploads/attendance/att_20260915_082214_ae564234.jpg', '2026-09-15 06:22:14'),
(17, 12, 'time_out', 'uploads/attendance/att_20260915_082214_600513d1.jpg', '2026-09-15 06:22:14');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_audit_logs`
--

CREATE TABLE `hrms_audit_logs` (
  `id` int(11) NOT NULL,
  `employee_name` varchar(150) NOT NULL,
  `type` varchar(50) NOT NULL,
  `detail` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_audit_logs`
--

INSERT INTO `hrms_audit_logs` (`id`, `employee_name`, `type`, `detail`, `created_at`) VALUES
(1, 'System Admin', 'User Login', 'Superadmin logged in', '2026-09-07 02:10:13'),
(2, 'Maria Santos', 'Employee Update', 'Updated Juan position', '2026-09-07 02:10:13'),
(3, 'System Admin', 'Payroll Release', 'Released August 2025 payroll', '2026-09-07 02:10:13'),
(4, 'Maria Santos', 'Applicant Hired', 'Hired Robert Kim', '2026-09-07 02:10:13'),
(5, 'jayr fabon', 'Applicant Submitted', 'Applied for Barista (Online interview)', '2026-09-07 05:52:28'),
(6, 'HR Manager', 'Applicant Stage Advanced', 'jayr fabon moved to Initial Interview', '2026-09-07 06:03:29'),
(7, 'HR Manager', 'Applicant Stage Advanced', 'jayr fabon moved to Actual Interview', '2026-09-14 03:52:07'),
(8, 'HR Manager', 'Applicant Stage Advanced', 'jayr fabon moved to Final Interview', '2026-09-14 03:52:11'),
(9, 'Pedro Garcia', 'Schedule Update', 'Sent schedule for superadmin approval: Pedro Garcia - Mon, Wed, Fri 14:00-22:00', '2026-09-14 04:16:12'),
(10, 'Pedro Garcia', 'Schedule Rejected', 'Superadmin rejected schedule for Pedro Garcia: ssada', '2026-09-14 04:18:02'),
(11, 'Pedro Garcia', 'Schedule Update', 'Sent schedule for superadmin approval: Pedro Garcia - Mon, Wed, Fri 14:00-22:00', '2026-09-14 04:18:41'),
(12, 'Pedro Garcia', 'Schedule Update', 'Sent schedule for superadmin approval: Pedro Garcia - Mon, Wed, Fri 01:00-22:00', '2026-09-14 04:18:56'),
(13, 'Pedro Garcia', 'Schedule Rejected', 'Superadmin rejected schedule for Pedro Garcia: asdsa', '2026-09-14 04:19:16'),
(14, 'Maria Santos', 'Request Submitted', 'Submitted General request', '2026-09-14 04:51:56'),
(15, 'System Admin', 'Request Rejected', 'Rejected General request for Maria Santos', '2026-09-14 04:52:33'),
(16, 'HR Manager', 'Offer Published', 'Published offer for jayr fabon (Barista) with contract document', '2026-09-14 05:01:27'),
(17, 'HR Manager', 'Applicant Hired', 'Hired jayr fabon as Barista', '2026-09-14 05:02:23'),
(18, 'jayr fabon', 'Attendance', 'Timed in (photo captured)', '2026-09-14 05:02:51'),
(19, 'jayr fabon', 'Attendance', 'Timed out (photo captured)', '2026-09-14 05:02:52'),
(20, 'jayr fabon', 'Leave Request', 'Submitted Unpaid leave request', '2026-09-14 05:03:17'),
(21, 'jayr fabon', 'General Request', 'Submitted general request', '2026-09-14 05:03:21'),
(22, 'jayr fabon', 'Schedule Update', 'Sent schedule for superadmin approval: jayr fabon - Mon, Tue, Wed, Thu, Fri, Sat 05:00-17:00', '2026-09-14 05:04:16'),
(23, 'jayr fabon', 'Schedule Approved', 'Superadmin approved schedule for jayr fabon', '2026-09-14 05:05:15'),
(24, 'System Admin', 'Request Approved', 'Approved Schedule Change request for jayr fabon', '2026-09-14 05:05:28'),
(25, 'System Admin', 'Request Rejected', 'Rejected General request for jayr fabon', '2026-09-14 05:05:39'),
(26, 'Jenwin Docabo', 'Applicant Submitted', 'Applied for Barista (Walk-in interview)', '2026-09-14 06:32:11'),
(27, 'HR Manager', 'Accepted', 'Jenwin Docabo moved to Accepted', '2026-09-14 06:33:19'),
(28, 'HR Manager', 'Applicant Stage Advanced', 'Jenwin Docabo moved to Initial Interview', '2026-09-14 06:34:31'),
(29, 'HR Manager', 'Applicant Rejected', 'Rejected Jenwin Docabo for Barista (was at Initial Interview)', '2026-09-14 06:34:36'),
(30, 'HR Manager', 'Accepted', 'Miguel Torres moved to Accepted', '2026-09-14 06:34:40'),
(31, 'HR Manager', 'Applicant Stage Advanced', 'Miguel Torres moved to Initial Interview', '2026-09-14 06:34:43'),
(32, 'HR Manager', 'Applicant Stage Advanced', 'Miguel Torres moved to Actual Interview', '2026-09-14 06:34:46'),
(33, 'HR Manager', 'Applicant Stage Advanced', 'Miguel Torres moved to Final Interview', '2026-09-14 06:34:49'),
(34, 'HR Manager', 'Offer Published', 'Published offer for Miguel Torres (Barista)', '2026-09-14 06:35:18'),
(35, 'HR Manager', 'Offer Published', 'Published offer for Miguel Torres (Barista)', '2026-09-14 06:36:12'),
(36, 'HR Manager', 'Applicant Hired', 'Hired Miguel Torres as Barista', '2026-09-14 06:36:29'),
(37, 'System Admin', 'Request Approved', 'Approved Schedule Change request for Pedro Garcia', '2026-09-14 06:39:31'),
(38, 'System Admin', 'Request Approved', 'Approved Schedule Change request for Ana Reyes', '2026-09-14 06:39:42'),
(39, 'System Admin', 'Request Approved', 'Approved Leave request for Jenwin Docabo', '2026-09-14 06:39:43'),
(40, 'System Admin', 'Request Approved', 'Approved Leave request for Jayr Fabon', '2026-09-14 06:39:44'),
(41, 'System Admin', 'Request Rejected', 'Rejected Schedule Change request for Pedro Garcia', '2026-09-14 06:39:46'),
(42, 'System Admin', 'Payroll Released', 'Released payroll for Carlos Mendoza (September 2026) — net ₱8,959.50', '2026-09-14 06:41:25'),
(43, 'System Admin', 'Archive Restored', 'Restored archived employee: Old Employee', '2026-09-14 06:41:49'),
(44, 'jayrpapwet fabon', 'Applicant Submitted', 'Applied for Barista (Online interview)', '2026-09-14 06:45:30'),
(45, 'HR Manager', 'Accepted', 'jayrpapwet fabon moved to Accepted', '2026-09-14 06:45:51'),
(46, 'HR Manager', 'Applicant Stage Advanced', 'jayrpapwet fabon moved to Initial Interview', '2026-09-14 06:45:53'),
(47, 'HR Manager', 'Applicant Stage Advanced', 'jayrpapwet fabon moved to Actual Interview', '2026-09-14 06:45:57'),
(48, 'HR Manager', 'Applicant Stage Advanced', 'jayrpapwet fabon moved to Final Interview', '2026-09-14 06:46:15'),
(49, 'HR Manager', 'Offer Published', 'Published offer for jayrpapwet fabon (Barista) with contract document', '2026-09-14 06:46:47'),
(50, 'HR Manager', 'Applicant Hired', 'Hired jayrpapwet fabon as Barista', '2026-09-14 06:47:26'),
(51, 'HR Manager', 'Accepted', 'Lisa Wong moved to Accepted', '2026-09-14 06:48:21'),
(52, 'HR Manager', 'Applicant Stage Advanced', 'Lisa Wong moved to Initial Interview', '2026-09-14 06:48:23'),
(53, 'HR Manager', 'Applicant Stage Advanced', 'Lisa Wong moved to Actual Interview', '2026-09-14 06:48:25'),
(54, 'HR Manager', 'Applicant Stage Advanced', 'Lisa Wong moved to Final Interview', '2026-09-14 06:48:29'),
(55, 'Lavish Herzicm Ancero', 'Budget Updated', 'Set 2026-09 payroll budget to ₱50,000,000.00', '2026-09-14 21:47:15'),
(56, 'jayr fabon', 'General Request', 'Submitted general request to Superadmin (signed letter: cv_1789368330_6109e338.docx)', '2026-09-14 23:19:18'),
(57, 'jayr fabon', 'Attendance', 'Time in recorded', '2026-09-15 00:07:32'),
(58, 'jayr fabon', 'Attendance', 'Time out recorded', '2026-09-15 00:07:33'),
(59, 'jayr fabon', 'Signature Updated', 'Registered signature re-registered from profile', '2026-09-15 00:39:35'),
(60, 'jayr fabon', 'General Request', 'Submitted general request to Superadmin (signed letter: letter_20260915_011918_c1967e94.docx)', '2026-09-15 00:52:35'),
(61, 'jayr fabon', 'General Request', 'Submitted general request to Superadmin (signed letter: cv_1789368330_6109e338.docx)', '2026-09-15 00:53:18'),
(62, 'jayr fabon', 'Request Rejected', 'General request #11 marked Rejected by System Admin', '2026-09-15 01:32:41'),
(63, 'jayr fabon', 'Request Rejected', 'Leave request #4 marked Rejected by System Admin', '2026-09-15 02:02:17'),
(64, 'System Admin', 'Payroll Submitted', 'Submitted payroll for jayr fabon (September 2026 (1st half: 1–15)) to Finance — net ₱87,675.00', '2026-09-15 02:03:30'),
(65, 'Lavish Herzicm Ancero', 'Payroll Approved', 'Finance approved payroll for jayr fabon (September 2026 (1st half: 1–15) — net ₱87,675.00', '2026-09-15 02:04:24'),
(66, 'Pedro Garcia', 'Request Rejected', 'Leave request #1 marked Rejected by System Admin', '2026-09-15 02:06:23'),
(67, 'Sofia Cruz', 'Request Rejected', 'Leave request #2 marked Rejected by System Admin', '2026-09-15 02:06:24'),
(68, 'jayr fabon', 'Leave Request', 'Submitted Unpaid leave from 2026-09-17 to 2026-09-19 (signed letter: Formal_Letter_jayr_fabon.docx)', '2026-09-15 02:30:44'),
(69, 'Jayr Fabon', 'Applicant Submitted', 'Applied for Barista (Walk-in interview)', '2026-09-15 04:18:28'),
(70, 'Jayr Fabon', 'Applicant Accepted', 'Accepted for interview (Barista)', '2026-09-15 04:19:01'),
(71, 'Jayr Fabon', 'Applicant Advanced', 'Moved to stage: Initial Interview', '2026-09-15 04:19:02'),
(72, 'Jayr Fabon', 'Applicant Advanced', 'Moved to stage: Actual Interview', '2026-09-15 04:19:03'),
(73, 'Jayr Fabon', 'Applicant Advanced', 'Moved to stage: Final Interview', '2026-09-15 04:19:04'),
(74, 'Jayr Fabon', 'Offer Published', 'Offer published: cafe, salary 2234324', '2026-09-15 04:20:22'),
(75, 'Jayr Fabon', 'Offer E-Signed', 'Applicant e-signed the published offer (registered signature recorded)', '2026-09-15 06:14:48'),
(76, 'Jayr Fabon', 'Applicant Hired', 'Hired as Barista (cafe)', '2026-09-15 06:15:05'),
(77, 'Jayr Fabon', 'Password Change', 'Changed account password.', '2026-09-15 06:21:37'),
(78, 'Jayr Fabon', 'Attendance', 'Time in recorded', '2026-09-15 06:22:14'),
(79, 'Jayr Fabon', 'Attendance', 'Time out recorded', '2026-09-15 06:22:14'),
(80, 'System Admin', 'Payroll Submitted', 'Submitted payroll for Jayr Fabon (October 2026 (1st half: 1–15)) to Finance — net ₱1,033,174.85', '2026-09-15 06:39:19'),
(81, 'Lavish Herzicm Ancero', 'Payroll Approved', 'Finance approved payroll for Jayr Fabon (October 2026 (1st half: 1–15)) — net ₱1,033,174.85', '2026-09-15 06:39:37'),
(82, 'jayr fabon', 'Request Approved', 'Leave request #5 marked Approved by System Admin', '2026-09-15 09:18:53'),
(83, 'jayr fabon', 'Request Approved', 'General request #13 marked Approved by System Admin', '2026-09-15 09:18:54'),
(84, 'jayr fabon', 'Request Approved', 'General request #12 marked Approved by System Admin', '2026-09-15 09:19:02'),
(85, 'Jayr Fabon', 'General Request', 'Submitted general request to Superadmin (signed letter: Formal_Letter_Jayr_Fabon.docx)', '2026-09-15 09:23:46'),
(86, 'Jayr Fabon', 'Request Rejected', 'General request #14 marked Rejected by System Admin', '2026-09-15 09:24:58');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_budget`
--

CREATE TABLE `hrms_budget` (
  `id` int(11) NOT NULL,
  `month` char(7) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_budget`
--

INSERT INTO `hrms_budget` (`id`, `month`, `total`, `note`, `created_at`, `updated_at`) VALUES
(1, '2026-09', 50000000.00, NULL, '2026-09-14 21:47:15', '2026-09-14 21:47:15');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_employees`
--

CREATE TABLE `hrms_employees` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Staff',
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `daily_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `schedule_days` varchar(100) DEFAULT NULL,
  `schedule_start` varchar(10) DEFAULT '09:00',
  `schedule_end` varchar(10) DEFAULT '17:00',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `hire_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_type` varchar(30) NOT NULL DEFAULT 'Staff',
  `registered_signature_path` varchar(255) DEFAULT NULL,
  `registered_signature_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_employees`
--

INSERT INTO `hrms_employees` (`id`, `name`, `email`, `role`, `department`, `position`, `daily_rate`, `monthly_salary`, `schedule_days`, `schedule_start`, `schedule_end`, `status`, `hire_date`, `created_at`, `account_type`, `registered_signature_path`, `registered_signature_updated_at`) VALUES
(1, 'Jayr Fabon', 'jayr@brewco.ph', 'Manager', 'Operations', 'Inventory Manager', 650.00, 14300.00, 'Mon,Tue,Wed,Thu,Fri', '08:00', '17:00', 'active', '2024-01-15', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(2, 'Jenwin Docabo', 'jenwin@brewco.ph', 'Staff', 'Operations', 'Inventory Clerk', 500.00, 11000.00, 'Mon,Wed,Fri', '09:00', '18:00', 'active', '2024-03-01', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(3, 'Maria Santos', 'maria@brewco.ph', 'Admin', 'Human Resources', 'HR Manager', 750.00, 16500.00, 'Mon,Tue,Wed,Thu,Fri', '08:00', '17:00', 'active', '2023-06-01', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(4, 'Juan dela Cruz', 'juan@brewco.ph', 'Staff', 'Cafe Operations', 'Barista', 450.00, 9900.00, 'Tue,Wed,Thu,Sat,Sun', '06:00', '14:00', 'active', '2024-05-15', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(5, 'Ana Reyes', 'ana@brewco.ph', 'Staff', 'Cafe Operations', 'Cashier', 450.00, 9900.00, 'Mon,Tue,Thu,Fri,Sat', '07:00', '15:00', 'active', '2024-02-20', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(6, 'Pedro Garcia', 'pedro@brewco.ph', 'Staff', 'Facilities', 'Cleaner', 400.00, 8800.00, 'Mon,Wed,Fri', '14:00', '22:00', 'active', '2024-04-10', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(7, 'Sofia Cruz', 'sofia@brewco.ph', 'Staff', 'Cafe Operations', 'Barista', 450.00, 9900.00, 'Tue,Thu,Sat', '06:00', '14:00', 'active', '2024-06-01', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(8, 'Carlos Mendoza', 'carlos@brewco.ph', 'Staff', 'Cafe Operations', 'Cashier', 450.00, 9900.00, 'Mon,Wed,Fri,Sun', '07:00', '15:00', 'active', '2024-07-01', '2026-09-07 02:10:13', 'Staff', NULL, NULL),
(9, 'jayr fabon', 'fabonjayr89@gmail.com', 'staff', 'sdadasdasdasdasdasfadgadfaf', 'Barista', 0.00, 190000.00, NULL, '09:00', '17:00', 'active', NULL, '2026-09-14 05:02:23', 'Staff', 'uploads/requests/sig_20260915_025235_1c65f171.png', '2026-09-15 00:52:35'),
(10, 'Miguel Torres', 'miguel@email.com', 'staff', '93493247', 'Barista', 0.00, 0.00, NULL, '09:00', '17:00', 'active', NULL, '2026-09-14 06:36:28', 'Staff', NULL, NULL),
(11, 'jayrpapwet fabon', 'papwet@gmail.com', 'staff', 'dasd098asua', 'Barista', 0.00, 1000.00, NULL, '09:00', '17:00', 'active', NULL, '2026-09-14 06:47:26', 'Staff', NULL, NULL),
(12, 'Jayr Fabon', 'fabonjayr99@gmail.com', 'Barista', 'cafe', 'Barista', 0.00, 2234324.00, 'Mon,Tue,Wed,Thu,Fri', '09:00', '17:00', 'active', '2026-09-15', '2026-09-15 06:15:05', 'Staff', 'uploads/signatures/reg_20260915_081448_e6e3f95d.png', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hrms_interview_scores`
--

CREATE TABLE `hrms_interview_scores` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `communication` tinyint(4) NOT NULL DEFAULT 0,
  `experience` tinyint(4) NOT NULL DEFAULT 0,
  `availability` tinyint(4) NOT NULL DEFAULT 0,
  `attitude` tinyint(4) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `recommendation` enum('Pending','Hire','Reject') NOT NULL DEFAULT 'Pending',
  `scored_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hrms_leave_requests`
--

CREATE TABLE `hrms_leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `employee_name` varchar(150) NOT NULL,
  `leave_type` enum('Paid','Unpaid') NOT NULL DEFAULT 'Paid',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` int(11) NOT NULL DEFAULT 1,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `letter_path` varchar(255) DEFAULT NULL,
  `letter_name` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signed_by` varchar(150) DEFAULT NULL,
  `signer_name` varchar(150) DEFAULT NULL,
  `signer_photo_path` varchar(255) DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_leave_requests`
--

INSERT INTO `hrms_leave_requests` (`id`, `employee_id`, `employee_name`, `leave_type`, `start_date`, `end_date`, `days`, `reason`, `status`, `admin_comment`, `created_at`, `updated_at`, `letter_path`, `letter_name`, `signature_path`, `signed_at`, `signed_by`, `signer_name`, `signer_photo_path`, `submitted_by`) VALUES
(1, 6, 'Pedro Garcia', 'Paid', '2025-09-10', '2025-09-10', 1, 'Medical appointment', 'Rejected', '', '2026-09-07 02:39:25', '2026-09-15 02:06:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 7, 'Sofia Cruz', 'Unpaid', '2025-09-15', '2025-09-17', 3, 'Family event', 'Rejected', '', '2026-09-07 02:39:25', '2026-09-15 02:06:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 8, 'Carlos Mendoza', 'Paid', '2025-09-01', '2025-09-02', 2, 'Vacation', 'Approved', NULL, '2026-09-07 02:39:25', '2026-09-07 02:39:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 9, 'jayr fabon', 'Unpaid', '2026-09-16', '2026-09-26', 11, 'sdadsad', 'Rejected', '', '2026-09-14 05:03:17', '2026-09-15 02:02:17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 9, 'jayr fabon', 'Unpaid', '2026-09-17', '2026-09-19', 3, 'fafafsafa', 'Approved', '', '2026-09-15 02:30:44', '2026-09-15 09:18:53', 'uploads/requests/letter_20260915_043044_14b340a1.docx', 'Formal_Letter_jayr_fabon.docx', 'uploads/requests/sig_20260915_043044_d7f80477.png', '2026-09-14 20:30:44', 'jayr fabon', 'jayr fabon', 'uploads/requests/signer_20260915_043044_f1553650.jpg', 24);

-- --------------------------------------------------------

--
-- Table structure for table `hrms_notifications`
--

CREATE TABLE `hrms_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(30) DEFAULT NULL,
  `employee_email` varchar(150) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_notifications`
--

INSERT INTO `hrms_notifications` (`id`, `user_id`, `role`, `employee_email`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, NULL, 'superadmin', NULL, 'Pending Leave', 'Jayr and Jenwin pending requests', 1, '2026-09-07 02:10:13'),
(2, NULL, 'superadmin', NULL, 'Payroll Released', 'August 2025 payroll released', 1, '2026-09-07 02:10:13'),
(3, NULL, 'admin', NULL, 'New Applicant', 'Miguel Torres applied', 1, '2026-09-07 02:10:13'),
(4, NULL, 'superadmin', NULL, 'New Request', 'Maria Santos submitted a General request', 1, '2026-09-14 04:51:56'),
(5, 3, NULL, NULL, 'Request Rejected', 'Your General request was Rejected.', 1, '2026-09-14 04:52:33'),
(6, NULL, NULL, 'fabonjayr89@gmail.com', 'Offer Published', 'Offer published for jayr fabon (Barista)', 1, '2026-09-14 05:01:27'),
(7, NULL, 'admin', NULL, 'Applicant Hired', 'jayr fabon has been hired as Barista. Default password: password123', 1, '2026-09-14 05:02:23'),
(8, NULL, NULL, NULL, 'Request Approved', 'Your Schedule Change request was Approved.', 1, '2026-09-14 05:05:28'),
(9, NULL, NULL, NULL, 'Request Rejected', 'Your General request was Rejected.', 1, '2026-09-14 05:05:39'),
(10, NULL, NULL, 'miguel@email.com', 'Offer Published', 'Offer published for Miguel Torres (Barista)', 1, '2026-09-14 06:35:18'),
(11, NULL, NULL, 'miguel@email.com', 'Offer Published', 'Offer published for Miguel Torres (Barista)', 1, '2026-09-14 06:36:12'),
(12, NULL, 'admin', NULL, 'Applicant Hired', 'Miguel Torres has been hired as Barista. Default password: password123', 1, '2026-09-14 06:36:29'),
(13, NULL, NULL, NULL, 'Request Approved', 'Your Schedule Change request was Approved.', 0, '2026-09-14 06:39:31'),
(14, NULL, NULL, NULL, 'Request Approved', 'Your Schedule Change request was Approved.', 0, '2026-09-14 06:39:42'),
(15, NULL, NULL, NULL, 'Request Approved', 'Your Leave request was Approved.', 0, '2026-09-14 06:39:43'),
(16, NULL, NULL, NULL, 'Request Approved', 'Your Leave request was Approved.', 0, '2026-09-14 06:39:44'),
(17, NULL, NULL, NULL, 'Request Rejected', 'Your Schedule Change request was Rejected.', 0, '2026-09-14 06:39:46'),
(18, NULL, NULL, 'carlos@brewco.ph', 'Payroll Released', 'Payroll for September 2026 has been released. Net pay: ₱8,959.50', 0, '2026-09-14 06:41:25'),
(19, NULL, NULL, 'papwet@gmail.com', 'Offer Published', 'Offer published for jayrpapwet fabon (Barista)', 0, '2026-09-14 06:46:47'),
(20, NULL, 'admin', NULL, 'Applicant Hired', 'jayrpapwet fabon has been hired as Barista. Default password: password123', 0, '2026-09-14 06:47:26'),
(21, NULL, 'superadmin', NULL, 'General request', 'jayr fabon submitted a general request with a signed formal letter.', 1, '2026-09-14 23:19:18'),
(22, NULL, 'superadmin', NULL, 'General request', 'jayr fabon submitted a general request with a signed formal letter.', 1, '2026-09-15 00:52:35'),
(23, NULL, 'superadmin', NULL, 'General request', 'jayr fabon submitted a general request with a signed formal letter.', 1, '2026-09-15 00:53:18'),
(24, NULL, NULL, 'fabonjayr89@gmail.com', 'Request Rejected', 'Your general request was Rejected.', 0, '2026-09-15 01:32:41'),
(25, NULL, NULL, 'fabonjayr89@gmail.com', 'Request Rejected', 'Your leave request was Rejected.', 0, '2026-09-15 02:02:17'),
(26, NULL, 'finance', NULL, 'Payroll Pending Approval', 'Payroll for jayr fabon (September 2026 (1st half: 1–15)) was submitted and is awaiting your approval. Net: ₱87,675.00', 0, '2026-09-15 02:03:30'),
(27, NULL, NULL, 'fabonjayr89@gmail.com', 'Payroll Released', 'Your payroll for September 2026 (1st half: 1–15 was approved by Finance and is now released. Net pay: ₱87,675.00', 0, '2026-09-15 02:04:24'),
(28, NULL, NULL, 'pedro@brewco.ph', 'Request Rejected', 'Your leave request was Rejected.', 0, '2026-09-15 02:06:23'),
(29, NULL, NULL, 'sofia@brewco.ph', 'Request Rejected', 'Your leave request was Rejected.', 0, '2026-09-15 02:06:24'),
(30, NULL, 'superadmin', NULL, 'Leave request', 'jayr fabon submitted a Unpaid leave request (2026-09-17 to 2026-09-19) with a signed formal letter.', 1, '2026-09-15 02:30:44'),
(31, NULL, NULL, 'fabonjayr99@gmail.com', 'Application update', 'You were accepted for an interview at Brew & Co.', 0, '2026-09-15 04:19:01'),
(32, NULL, NULL, 'fabonjayr99@gmail.com', 'Application update', 'Your application moved to: Initial Interview.', 0, '2026-09-15 04:19:02'),
(33, NULL, NULL, 'fabonjayr99@gmail.com', 'Application update', 'Your application moved to: Actual Interview.', 0, '2026-09-15 04:19:03'),
(34, NULL, NULL, 'fabonjayr99@gmail.com', 'Application update', 'Your application moved to: Final Interview.', 0, '2026-09-15 04:19:04'),
(35, NULL, NULL, 'fabonjayr99@gmail.com', 'Job offer published', 'Your job offer from Brew & Co. is now available in your applicant portal.', 0, '2026-09-15 04:20:22'),
(36, NULL, 'admin', NULL, 'Offer e-signed', 'Jayr Fabon e-signed the offer. Their signature is now on file for hiring.', 0, '2026-09-15 06:14:48'),
(37, NULL, 'superadmin', NULL, 'New hire', 'Jayr Fabon was hired as Barista.', 1, '2026-09-15 06:15:05'),
(38, NULL, NULL, 'fabonjayr99@gmail.com', 'Congratulations!', 'You are now hired at Brew & Co. Your staff login is ready (temporary password: staff123).', 0, '2026-09-15 06:15:05'),
(39, NULL, 'finance', NULL, 'Payroll Pending Approval', 'Payroll for Jayr Fabon (October 2026 (1st half: 1–15)) was submitted and is awaiting your approval. Net: ₱1,033,174.85', 1, '2026-09-15 06:39:19'),
(40, NULL, NULL, 'fabonjayr99@gmail.com', 'Payroll Released', 'Your payroll for October 2026 (1st half: 1–15) was approved by Finance and is now released. Net pay: ₱1,033,174.85', 0, '2026-09-15 06:39:37'),
(41, NULL, NULL, 'fabonjayr89@gmail.com', 'Request Approved', 'Your leave request was Approved.', 0, '2026-09-15 09:18:53'),
(42, NULL, NULL, 'fabonjayr89@gmail.com', 'Request Approved', 'Your general request was Approved.', 0, '2026-09-15 09:18:54'),
(43, NULL, NULL, 'fabonjayr89@gmail.com', 'Request Approved', 'Your general request was Approved.', 0, '2026-09-15 09:19:02'),
(44, NULL, 'superadmin', NULL, 'General request', 'Jayr Fabon submitted a general request with a signed formal letter.', 1, '2026-09-15 09:23:46'),
(45, NULL, NULL, 'fabonjayr99@gmail.com', 'Request Rejected', 'Your general request was Rejected.', 0, '2026-09-15 09:24:58');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_payroll`
--

CREATE TABLE `hrms_payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(150) NOT NULL,
  `period` varchar(30) NOT NULL,
  `gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sss` decimal(12,2) NOT NULL DEFAULT 0.00,
  `philhealth` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pagibig` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payroll_status` varchar(20) NOT NULL DEFAULT 'Released',
  `payroll_period` varchar(30) DEFAULT NULL,
  `finance_comment` text DEFAULT NULL,
  `finance_acted_by` varchar(150) DEFAULT NULL,
  `finance_acted_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `daily_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `yearly_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_minutes` int(11) NOT NULL DEFAULT 0,
  `undertime_minutes` int(11) NOT NULL DEFAULT 0,
  `attendance_deduction` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_payroll`
--

INSERT INTO `hrms_payroll` (`id`, `employee_id`, `employee_name`, `period`, `gross`, `sss`, `philhealth`, `pagibig`, `other_deductions`, `deductions`, `net_pay`, `status`, `released_at`, `created_at`, `payroll_status`, `payroll_period`, `finance_comment`, `finance_acted_by`, `finance_acted_at`, `submitted_at`, `daily_rate`, `monthly_gross`, `yearly_gross`, `late_minutes`, `undertime_minutes`, `attendance_deduction`) VALUES
(1, 1, 'Jayr Fabon', 'August 2025', 14300.00, 450.00, 200.00, 150.00, 0.00, 800.00, 13500.00, 'Released', NULL, '2026-09-07 02:10:13', 'Released', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0.00),
(2, 2, 'Jenwin Docabo', 'August 2025', 11000.00, 350.00, 150.00, 100.00, 0.00, 600.00, 10400.00, 'Released', NULL, '2026-09-07 02:10:13', 'Released', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0.00),
(3, 4, 'Juan dela Cruz', 'August 2025', 9900.00, 300.00, 120.00, 100.00, 0.00, 520.00, 9380.00, 'Released', NULL, '2026-09-07 02:10:13', 'Released', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0.00),
(4, 5, 'Ana Reyes', 'August 2025', 9900.00, 300.00, 120.00, 100.00, 0.00, 520.00, 9380.00, 'Released', NULL, '2026-09-07 02:10:13', 'Released', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0.00),
(5, 8, 'Carlos Mendoza', 'September 2026', 9900.00, 495.00, 247.50, 198.00, 0.00, 940.50, 8959.50, 'Released', '2026-09-14 06:41:25', '2026-09-14 06:41:25', 'Released', 'September 2026', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0.00),
(6, 9, 'jayr fabon', 'September 2026 (1st half: 1–15', 95000.00, 4750.00, 2375.00, 200.00, 0.00, 7325.00, 87675.00, 'Released', '2026-09-15 02:04:24', '2026-09-15 02:03:30', 'Released', 'September 2026 (1st half: 1–15', NULL, 'Lavish Herzicm Ancero', '2026-09-15 02:04:24', '2026-09-15 02:03:30', 0.00, 0.00, 0.00, 0, 0, 0.00),
(7, 12, 'Jayr Fabon', 'October 2026 (1st half: 1–15)', 1117162.00, 55858.10, 27929.05, 200.00, 0.00, 83987.15, 1033174.85, 'Released', '2026-09-15 06:39:37', '2026-09-15 06:39:19', 'Released', 'October 2026 (1st half: 1–15)', NULL, 'Lavish Herzicm Ancero', '2026-09-15 06:39:37', '2026-09-15 06:39:19', 0.00, 0.00, 0.00, 0, 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `hrms_positions`
--

CREATE TABLE `hrms_positions` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `slots_total` int(11) NOT NULL DEFAULT 1,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `linked_role` varchar(30) NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_positions`
--

INSERT INTO `hrms_positions` (`id`, `title`, `description`, `slots_total`, `is_open`, `linked_role`, `created_at`, `updated_at`) VALUES
(0, 'Barista', NULL, 10, 1, 'staff', '2026-09-19 10:12:42', '2026-09-19 10:12:42'),
(0, 'Cashier', NULL, 5, 1, 'staff', '2026-09-19 10:12:42', '2026-09-19 10:12:42'),
(0, 'Cleaner', NULL, 5, 1, 'staff', '2026-09-19 10:12:42', '2026-09-19 10:12:42');

-- --------------------------------------------------------

--
-- Table structure for table `hrms_schedules`
--

CREATE TABLE `hrms_schedules` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `days` varchar(100) NOT NULL DEFAULT '',
  `start` varchar(10) NOT NULL DEFAULT '09:00',
  `end` varchar(10) NOT NULL DEFAULT '17:00',
  `approval_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `approval_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rejection_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_schedules`
--

INSERT INTO `hrms_schedules` (`id`, `employee_id`, `days`, `start`, `end`, `approval_status`, `approval_comment`, `created_at`, `rejection_comment`) VALUES
(1, 1, 'Mon,Tue,Wed,Thu,Fri', '08:00', '17:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(2, 2, 'Mon,Wed,Fri', '09:00', '18:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(3, 3, 'Mon,Tue,Wed,Thu,Fri', '08:00', '17:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(4, 4, 'Tue,Wed,Thu,Sat,Sun', '06:00', '14:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(5, 5, 'Mon,Tue,Thu,Fri,Sat', '07:00', '15:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(6, 6, 'Mon, Wed, Fri', '01:00', '22:00', 'Rejected', 'sdasd', '2026-09-07 02:10:13', 'asdsa'),
(7, 7, 'Tue,Thu,Sat', '06:00', '14:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(8, 8, 'Mon,Wed,Fri,Sun', '07:00', '15:00', 'Approved', NULL, '2026-09-07 02:10:13', NULL),
(9, 9, 'Mon, Tue, Wed, Thu, Fri, Sat', '05:00', '17:00', 'Approved', 'raw', '2026-09-14 05:04:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hrms_users`
--

CREATE TABLE `hrms_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','staff','finance') NOT NULL DEFAULT 'staff',
  `employee_id` int(11) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hrms_users`
--

INSERT INTO `hrms_users` (`id`, `username`, `full_name`, `email`, `password`, `role`, `employee_id`, `must_change_password`, `created_at`) VALUES
(1, 'admin', 'System Admin', 'jayr@brewco.ph', '$2y$12$rhOGPMCHdEePSdidLISKUeUlCUzqLLp8.6o0oTgQITA2twVLK1L8q', 'superadmin', 1, 0, '2026-09-07 01:26:56'),
(3, 'hr', 'HR Manager', 'maria@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'admin', 3, 0, '2026-09-07 01:27:14'),
(4, 'cashier', 'Cashier Staff', 'juan@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'staff', 4, 0, '2026-09-07 01:27:14'),
(5, 'barista', 'Barista Staff', 'sofia@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'staff', 7, 0, '2026-09-07 01:27:14'),
(6, 'cleaner', 'Cleaner Staff', 'pedro@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'staff', 6, 0, '2026-09-07 01:27:14'),
(24, 'fabonjayr89@gmail.com', 'jayr fabon', 'fabonjayr89@gmail.com', '$2y$10$b/o/8193qf8s55jHYaQ6e.AmBz1qRc8pMA1NBlamB7PzgQEuAY.Ze', 'staff', NULL, 0, '2026-09-14 05:02:23'),
(25, 'miguel@email.com', 'Miguel Torres', 'miguel@email.com', '$2y$10$ZF7KxVFlxtBD6dSPRp7SMu/QnTDGEoczDXRuLpCwTWUY2fKzQ0rn6', 'staff', NULL, 0, '2026-09-14 06:36:29'),
(26, 'papwet@gmail.com', 'jayrpapwet fabon', 'papwet@gmail.com', '$2y$10$3V8FrYK73s7/VR4GxojZDeIXVJeGZL5bYYkAmMTDAZeXrvRBSjSmG', 'staff', NULL, 0, '2026-09-14 06:47:26'),
(27, 'finance', 'Lavish Herzicm Ancero', 'lavish@brewco.ph', '$2y$10$viJbvZ/SvrA04irPh6OSwu0yTE.4ECNcmmJffwXexQEdQPEoI6JVC', 'finance', NULL, 0, '2026-09-14 21:46:06'),
(28, 'fabonjayr99', 'Jayr Fabon', 'fabonjayr99@gmail.com', '$2y$10$RcGxtPID//DK345VCukkSeIro6rxSAO1s82S3KoX3cZNvqpM.Owm2', 'staff', 12, 0, '2026-09-15 06:15:05');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `unit` varchar(20) NOT NULL,
  `current_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_status` enum('approved','pending') NOT NULL DEFAULT 'approved',
  `old_cost` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `code`, `name`, `category_id`, `unit`, `current_qty`, `reorder_level`, `cost`, `cost_status`, `old_cost`, `is_active`, `created_at`) VALUES
(1, 'ING-001', 'Arabica Beans', 1, 'kg', 41.60, 15.00, 620.00, 'approved', NULL, 1, '2026-09-07 01:26:55'),
(2, 'ING-002', 'Robusta Beans', 1, 'kg', 8.00, 15.00, 480.00, 'approved', NULL, 1, '2026-09-07 01:26:55'),
(3, 'ING-003', 'Fresh Milk', 2, 'L', 48.00, 20.00, 90.00, 'approved', NULL, 1, '2026-09-07 01:26:55'),
(4, 'ING-004', 'Oat Milk', 2, 'L', 12.00, 10.00, 160.00, 'approved', NULL, 1, '2026-09-07 01:26:55'),
(5, 'ING-005', 'Caramel Syrup', 3, 'bottle', 4.49, 6.00, 320.00, 'approved', NULL, 1, '2026-09-07 01:26:55'),
(6, 'ING-006', 'Paper Cups 12oz', 4, 'pcs', 320.00, 200.00, 12.00, 'approved', NULL, 1, '2026-09-07 01:26:55');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(120) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip`, `success`, `created_at`) VALUES
(1, 'admin@brewco.ph', '::1', 1, '2026-09-07 06:33:01'),
(2, 'jayr@brewco.ph', '::1', 0, '2026-09-07 08:13:28'),
(3, 'jenwin@brewco.ph', '::1', 1, '2026-09-09 11:30:38'),
(4, 'jayr@brewco.ph', '::1', 1, '2026-09-09 11:36:28'),
(5, 'rica@brewco.ph', '::1', 1, '2026-09-09 11:43:27'),
(6, 'lavish@brewco.ph', '::1', 1, '2026-09-14 02:36:48'),
(7, 'lavish@brewco.ph', '::1', 1, '2026-09-14 03:50:48'),
(8, 'jayr@brewco.ph', '::1', 1, '2026-09-14 03:51:00'),
(9, 'jayr@brewco.ph', '::1', 1, '2026-09-14 15:09:14'),
(10, 'jayr@brewco.ph', '::1', 1, '2026-09-14 15:10:02'),
(11, 'lavish@brewco.ph', '::1', 1, '2026-09-14 15:10:13'),
(12, 'jenwin@brewco.ph', '::1', 1, '2026-09-14 15:10:27'),
(13, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 03:09:03'),
(14, 'jayr@brewco.ph', '::1', 1, '2026-09-15 03:09:26'),
(15, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 03:10:54'),
(16, 'jayr@brewco.ph', '::1', 1, '2026-09-15 03:11:01'),
(17, 'lavish@brewco.ph', '::1', 1, '2026-09-15 03:25:15'),
(18, 'jayr@brewco.ph', '::1', 1, '2026-09-15 03:45:33'),
(19, 'jayr@brewco.ph', '::1', 1, '2026-09-15 06:45:13'),
(20, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 06:46:45'),
(21, 'jayr@brewco.ph', '::1', 1, '2026-09-15 06:48:44'),
(22, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 06:49:13'),
(23, 'jayr@brewco.ph', '::1', 1, '2026-09-15 06:49:47'),
(24, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 06:50:13'),
(25, 'jayr@brewco.ph', '::1', 1, '2026-09-15 06:50:24'),
(26, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 06:50:45'),
(27, 'jayr@brewco.ph', '::1', 1, '2026-09-15 06:51:46'),
(28, 'lavish@brewco.ph', '::1', 1, '2026-09-15 08:02:27'),
(29, 'jenwin@brewco.ph', '::1', 1, '2026-09-15 08:03:08'),
(30, 'lavish@brewco.ph', '::1', 1, '2026-09-15 09:13:15'),
(31, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:11:22'),
(32, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:12:23'),
(33, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:15:04'),
(34, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:24:32'),
(35, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:28:03'),
(36, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:29:32'),
(37, 'lavish@brewco.ph', '::1', 0, '2026-09-17 01:29:32'),
(38, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:29:38'),
(39, 'lavish@brewco.ph', '::1', 1, '2026-09-17 01:56:20');

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `emoji` varchar(10) NOT NULL DEFAULT '?️',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`id`, `name`, `sort_order`, `is_active`, `is_default`, `emoji`, `deleted_at`) VALUES
(1, 'Hot drinks', 1, 1, 1, '☕', NULL),
(2, 'Cold drinks', 2, 1, 1, '🧋', NULL),
(3, 'Pastries', 3, 1, 1, '🥐', NULL),
(4, 'Desserts', 4, 1, 1, '🧁', NULL),
(5, 'Meals', 5, 1, 1, '🍱', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `menu_ingredients`
--

CREATE TABLE `menu_ingredients` (
  `id` int(10) UNSIGNED NOT NULL,
  `menu_item_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_ingredients`
--

INSERT INTO `menu_ingredients` (`id`, `menu_item_id`, `item_id`, `qty`) VALUES
(1, 1, 1, 0.020),
(2, 1, 6, 1.000),
(3, 2, 1, 0.020),
(4, 2, 3, 0.200),
(5, 2, 5, 0.030),
(6, 2, 6, 1.000),
(7, 3, 1, 0.020),
(8, 3, 3, 0.200),
(9, 3, 6, 1.000),
(10, 4, 4, 0.200),
(11, 4, 6, 1.000),
(12, 5, 1, 0.020),
(13, 5, 3, 0.150),
(14, 5, 5, 0.040),
(15, 5, 6, 1.000),
(16, 6, 1, 0.020),
(17, 6, 3, 0.200),
(18, 6, 5, 0.030),
(19, 6, 6, 1.000),
(20, 7, 1, 0.020),
(21, 7, 3, 0.200),
(22, 7, 5, 0.050),
(23, 7, 6, 1.000),
(24, 8, 6, 1.000),
(25, 9, 6, 1.000),
(26, 10, 6, 1.000);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `cost` decimal(12,2) NOT NULL,
  `sold` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `emoji` varchar(10) NOT NULL DEFAULT '☕',
  `image` varchar(255) NOT NULL DEFAULT '',
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `category_id`, `name`, `price`, `cost`, `sold`, `is_active`, `emoji`, `image`, `featured`, `stock`, `description`, `deleted_at`) VALUES
(1, 1, 'Espresso', 100.00, 22.00, 150, 1, '☕', 'uploads/items/espresso.png', 1, 320, '', NULL),
(2, 1, 'Caramel Latte', 120.00, 52.00, 217, 1, '☕', 'uploads/items/caramel-latte.png', 0, 149, NULL, NULL),
(3, 1, 'Cafe Latte', 140.00, 52.00, 323, 1, '☕', 'uploads/items/cafe-latte.png', 0, 240, NULL, NULL),
(4, 1, 'Matcha Latte', 130.00, 70.00, 180, 1, '☕', 'uploads/items/matcha-latte.png', 1, 60, '', NULL),
(5, 1, 'Caramel Macchiato', 165.00, 74.00, 210, 1, '☕', 'uploads/items/caramel-macchiato.png', 0, 112, NULL, NULL),
(6, 2, 'Iced Caramel Latte', 135.00, 55.00, 165, 1, '☕', 'uploads/items/iced-caramel-latte.png', 1, 149, '', NULL),
(7, 2, 'Mocha Frappe', 150.00, 68.00, 140, 1, '☕', 'uploads/items/mocha-frappe.png', 0, 89, NULL, NULL),
(8, 3, 'Butter Croissant', 80.00, 25.00, 90, 1, '☕', 'uploads/items/butter-croissant.png', 0, 320, NULL, NULL),
(9, 4, 'Cupcake', 85.00, 30.00, 75, 1, '☕', 'uploads/items/cupcake.png', 0, 320, NULL, NULL),
(10, 5, 'Meal Set', 160.00, 90.00, 60, 1, '☕', 'uploads/items/meal-set.png', 1, 320, '', NULL),
(11, 2, 'test', 25.00, 0.00, 76, 1, '☕', 'uploads/items/test.png', 0, 65, 'asdf', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_categories`
--

CREATE TABLE `pos_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `emoji` varchar(10) NOT NULL DEFAULT '?️',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_categories`
--

INSERT INTO `pos_categories` (`id`, `name`, `emoji`, `is_default`, `deleted_at`, `created_at`) VALUES
(1, 'Hot drinks', '☕', 1, NULL, '2026-09-07 01:26:56'),
(2, 'Cold drinks', '🧋', 1, NULL, '2026-09-07 01:26:56'),
(3, 'Pastries', '🥐', 1, NULL, '2026-09-07 01:26:56'),
(4, 'Desserts', '🧁', 1, NULL, '2026-09-07 01:26:56'),
(5, 'Meals', '🍱', 1, NULL, '2026-09-07 01:26:56');

-- --------------------------------------------------------

--
-- Table structure for table `pos_items`
--

CREATE TABLE `pos_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `category` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `emoji` varchar(10) NOT NULL DEFAULT '',
  `image` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_items`
--

INSERT INTO `pos_items` (`id`, `name`, `category`, `price`, `emoji`, `image`, `featured`, `stock`, `description`, `deleted_at`, `created_at`) VALUES
(1, 'test', 'Cold drinks', 25.00, '☕', '', 0, 25, 'asdf', NULL, '2026-09-07 18:01:26');

-- --------------------------------------------------------

--
-- Table structure for table `pos_orders`
--

CREATE TABLE `pos_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_no` varchar(20) NOT NULL,
  `client_name` varchar(80) DEFAULT NULL,
  `status` enum('placed','paid','cancelled') NOT NULL DEFAULT 'placed',
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT NULL,
  `change_due` decimal(12,2) DEFAULT NULL,
  `cashier_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pos_orders`
--

INSERT INTO `pos_orders` (`id`, `order_no`, `client_name`, `status`, `total`, `amount_paid`, `change_due`, `cashier_id`, `created_at`, `paid_at`) VALUES
(1, 'POS-1001', 'Walk-in', 'paid', 660.00, 700.00, 40.00, 5, '2026-09-09 11:43:44', '2026-09-09 19:44:16');

-- --------------------------------------------------------

--
-- Table structure for table `pos_order_items`
--

CREATE TABLE `pos_order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `menu_item_id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `qty` int(10) UNSIGNED NOT NULL,
  `price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pos_order_items`
--

INSERT INTO `pos_order_items` (`id`, `order_id`, `menu_item_id`, `item_name`, `qty`, `price`) VALUES
(1, 1, 2, 'Caramel Latte', 2, 120.00),
(2, 1, 3, 'Cafe Latte', 3, 140.00);

-- --------------------------------------------------------

--
-- Table structure for table `pos_sales`
--

CREATE TABLE `pos_sales` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cash_received` decimal(10,2) DEFAULT NULL,
  `change_given` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `order_type` varchar(20) NOT NULL DEFAULT 'Dine In',
  `payment` varchar(30) NOT NULL DEFAULT 'Pay at Cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `sold_counted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_sales`
--

INSERT INTO `pos_sales` (`id`, `order_code`, `total`, `cash_received`, `change_given`, `status`, `order_type`, `payment`, `created_at`, `paid_at`, `sold_counted`) VALUES
(1, 'ORD-001', 100.00, NULL, NULL, 'Paid', 'Take Out', 'Pay at Cashier', '2026-09-07 18:15:09', '2026-09-08 02:15:09', 0),
(2, 'ORD-002', 100.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 18:15:17', '2026-09-08 02:15:17', 0),
(3, 'ORD-003', 150.00, NULL, NULL, 'Voided', 'Dine In', 'Pay at Cashier', '2026-09-07 18:19:29', NULL, 0),
(4, 'ORD-004', 150.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 18:19:34', '2026-09-08 02:19:34', 0),
(5, 'ORD-005', 175.00, NULL, NULL, 'Voided', 'Dine In', 'Pay at Cashier', '2026-09-07 18:19:48', NULL, 0),
(6, 'ORD-006', 125.00, NULL, NULL, 'Paid', 'Take Out', 'Pay at Cashier', '2026-09-07 18:27:49', '2026-09-08 02:27:49', 0),
(7, 'ORD-007', 50.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 18:33:45', '2026-09-08 02:33:45', 0),
(8, 'ORD-008', 50.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 18:37:34', '2026-09-08 02:37:34', 1),
(9, 'ORD-009', 125.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 19:26:05', '2026-09-08 03:26:05', 1),
(10, 'ORD-010', 150.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 19:27:15', '2026-09-08 03:27:15', 1),
(11, 'ORD-011', 125.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 19:34:24', '2026-09-08 03:34:24', 1),
(12, 'ORD-012', 125.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 19:35:03', '2026-09-08 03:35:03', 1),
(13, 'ORD-013', 125.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-07 19:38:22', '2026-09-08 03:38:22', 1),
(14, 'ORD-014', 125.00, NULL, NULL, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-08 05:41:51', '2026-09-08 13:41:51', 1),
(15, 'ORD-015', 50.00, 500.00, 450.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-08 07:25:42', '2026-09-08 15:25:42', 1),
(16, 'ORD-016', 50.00, 500.00, 450.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-08 09:22:26', '2026-09-08 17:22:26', 1),
(17, 'ORD-017', 75.00, 100.00, 25.00, 'Paid', 'Take Out', 'Pay at Cashier', '2026-09-10 08:54:15', '2026-09-10 16:54:15', 1),
(18, 'ORD-018', 50.00, 100.00, 50.00, 'Paid', 'Take Out', 'Pay at Cashier', '2026-09-10 08:57:17', '2026-09-10 16:57:17', 1),
(19, 'ORD-019', 50.00, 100.00, 50.00, 'Paid', 'Take Out', 'Pay at Cashier', '2026-09-10 09:03:54', '2026-09-10 17:03:54', 1),
(20, 'ORD-020', 50.00, NULL, NULL, 'Pending', 'Dine In', 'Pay at Cashier', '2026-09-10 09:08:19', NULL, 0),
(21, 'ORD-021', 25.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-10 09:08:31', NULL, 0),
(22, 'ORD-022', 25.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-10 09:13:46', NULL, 0),
(23, 'ORD-023', 50.00, 5555.00, 5505.00, 'Paid', 'Take Out', 'Cash', '2026-09-10 10:48:08', '2026-09-10 18:48:08', 1),
(24, 'ORD-024', 25.00, 500.00, 475.00, 'Paid', 'Take Out', 'Cash', '2026-09-10 11:19:03', '2026-09-10 19:19:03', 1),
(25, 'ORD-025', 25.00, 500.00, 475.00, 'Paid', 'Dine In', 'Cash', '2026-09-10 11:19:37', '2026-09-10 19:19:37', 1),
(26, 'ORD-026', 50.00, 5000.00, 4950.00, 'Paid', 'Dine In', 'Cash', '2026-09-10 11:22:12', '2026-09-10 19:22:12', 1),
(27, 'ORD-027', 75.00, 500.00, 425.00, 'Paid', 'Dine In', 'Cash', '2026-09-10 11:22:24', '2026-09-10 19:22:24', 1),
(28, 'ORD-028', 50.00, NULL, NULL, 'Pending', 'Take Out', 'Cash', '2026-09-12 04:28:46', NULL, 0),
(29, 'ORD-029', 425.00, NULL, NULL, 'Pending', 'Take Out', 'Cash', '2026-09-14 03:22:21', NULL, 0),
(30, 'ORD-030', 25.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-14 03:42:06', NULL, 0),
(31, 'ORD-031', 275.00, NULL, NULL, 'Pending', 'Dine In', 'Pay at Cashier', '2026-09-14 03:42:31', NULL, 0),
(32, 'ORD-032', 150.00, NULL, NULL, 'Pending', 'Dine In', 'Pay at Cashier', '2026-09-14 03:42:58', NULL, 0),
(33, 'ORD-033', 250.00, NULL, NULL, 'Pending', 'Dine In', 'Pay at Cashier', '2026-09-14 04:21:21', NULL, 0),
(34, 'ORD-034', 550.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-14 04:35:23', NULL, 0),
(35, 'ORD-035', 175.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-14 04:37:48', NULL, 0),
(36, 'ORD-036', 175.00, NULL, NULL, 'Pending', 'Dine In', 'Pay at Cashier', '2026-09-14 04:39:07', NULL, 0),
(37, 'ORD-037', 175.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-14 04:39:38', NULL, 0),
(38, 'ORD-038', 196.00, NULL, NULL, 'Pending', 'Take Out', 'Pay at Cashier', '2026-09-14 04:42:42', NULL, 0),
(39, 'ORD-039', 196.00, 200.00, 4.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-14 04:43:21', '2026-09-14 12:43:21', 1),
(40, 'ORD-040', 196.00, 200.00, 4.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-14 04:46:15', '2026-09-14 12:46:15', 1),
(41, 'ORD-041', 196.00, 200.00, 4.00, 'Paid', 'Take Out', 'Pay at Cashier', '2026-09-14 04:50:25', '2026-09-14 12:50:25', 1),
(42, 'ORD-042', 168.00, 800.00, 632.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-14 05:15:59', '2026-09-14 13:15:59', 1),
(43, 'ORD-043', 140.00, 150.00, 10.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-14 05:17:32', '2026-09-14 13:17:32', 1),
(44, 'ORD-044', 112.00, 5500.00, 5388.00, 'Paid', 'Dine In', 'Pay at Cashier', '2026-09-14 06:27:53', '2026-09-14 14:27:53', 1),
(45, 'ORD-045', 756.00, 1000.00, 244.00, 'Voided', 'Take Out', 'Pay at Cashier', '2026-09-15 05:26:36', '2026-09-15 13:27:06', 1),
(46, 'ORD-046', 672.00, 700.00, 28.00, 'Paid', 'Dine In', 'Cash', '2026-09-15 09:15:05', '2026-09-15 11:15:05', 1),
(47, 'ORD-047', 1344.00, 1400.00, 56.00, 'Paid', 'Take Out', 'Cash', '2026-09-15 09:15:47', '2026-09-15 11:15:47', 1);

-- --------------------------------------------------------

--
-- Table structure for table `pos_sale_items`
--

CREATE TABLE `pos_sale_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_sale_items`
--

INSERT INTO `pos_sale_items` (`id`, `order_id`, `item_id`, `qty`, `unit_price`) VALUES
(1, 1, 11, 4, 25.00),
(2, 2, 11, 4, 25.00),
(3, 3, 11, 6, 25.00),
(4, 4, 11, 6, 25.00),
(5, 5, 11, 7, 25.00),
(6, 6, 11, 5, 25.00),
(7, 7, 11, 2, 25.00),
(8, 8, 11, 2, 25.00),
(9, 9, 11, 5, 25.00),
(10, 10, 11, 6, 25.00),
(11, 11, 11, 5, 25.00),
(12, 12, 11, 5, 25.00),
(13, 13, 11, 5, 25.00),
(14, 14, 11, 5, 25.00),
(15, 15, 11, 2, 25.00),
(16, 16, 11, 2, 25.00),
(17, 17, 11, 3, 25.00),
(18, 18, 11, 2, 25.00),
(19, 19, 11, 2, 25.00),
(20, 20, 11, 2, 25.00),
(21, 21, 11, 1, 25.00),
(22, 22, 11, 1, 25.00),
(23, 23, 11, 2, 25.00),
(24, 24, 11, 1, 25.00),
(25, 25, 11, 1, 25.00),
(26, 26, 11, 2, 25.00),
(27, 27, 11, 3, 25.00),
(28, 28, 11, 2, 25.00),
(29, 29, 11, 17, 25.00),
(30, 30, 11, 1, 25.00),
(31, 31, 11, 11, 25.00),
(32, 32, 11, 6, 25.00),
(33, 33, 11, 10, 25.00),
(34, 34, 11, 22, 25.00),
(35, 35, 11, 7, 25.00),
(36, 36, 11, 7, 25.00),
(37, 37, 11, 7, 25.00),
(38, 38, 11, 7, 25.00),
(39, 39, 11, 7, 25.00),
(40, 40, 11, 7, 25.00),
(41, 41, 11, 7, 25.00),
(42, 42, 11, 6, 25.00),
(43, 43, 11, 5, 25.00),
(44, 44, 11, 4, 25.00),
(45, 45, 6, 5, 135.00),
(46, 46, 2, 5, 120.00),
(47, 47, 2, 10, 120.00);

-- --------------------------------------------------------

--
-- Table structure for table `pos_users`
--

CREATE TABLE `pos_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'Staff',
  `source` varchar(20) NOT NULL DEFAULT 'admin_created',
  `admin_pin` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_users`
--

INSERT INTO `pos_users` (`id`, `full_name`, `email`, `password`, `role`, `source`, `admin_pin`, `created_at`) VALUES
(1, 'POS Admin', 'posadmin@brewco.ph', '$2y$12$rhOGPMCHdEePSdidLISKUeUlCUzqLLp8.6o0oTgQITA2twVLK1L8q', 'Admin', 'admin_created', NULL, '2026-09-07 01:26:56'),
(2, 'POS Cashier', 'cashier@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'Cashier', 'admin_created', NULL, '2026-09-07 01:26:56');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `pr_code` varchar(20) NOT NULL,
  `item_id` int(10) UNSIGNED DEFAULT NULL,
  `item_desc` varchar(120) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `est_cost` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `requested_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `letter_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `selfie_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `pr_code`, `item_id`, `item_desc`, `qty`, `unit`, `seller_id`, `est_cost`, `status`, `requested_by`, `approved_by`, `decided_at`, `created_at`, `letter_path`, `signature_path`, `selfie_path`) VALUES
(1, 'PR-0042', 1, 'Arabica Beans', 50.00, 'kg', 1, 18500.00, 'pending', 2, NULL, NULL, '2026-07-29 02:00:00', NULL, NULL, NULL),
(2, 'PR-0043', 6, 'Paper Cups 12oz', 10000.00, 'pcs', 4, 12000.00, 'pending', 2, NULL, NULL, '2026-07-30 01:00:00', NULL, NULL, NULL),
(3, 'PR-0044', 1, 'Arabica Beans', 5.00, 'kg', 1, 3100.00, 'pending', 2, NULL, NULL, '2026-09-15 03:11:16', 'uploads/purchase_letters/PR-0044.docx', NULL, NULL),
(4, 'PR-0045', 1, 'Arabica Beans', 5.00, 'kg', 2, 3100.00, 'approved', 2, 3, '2026-09-15 11:45:05', '2026-09-15 03:24:58', 'uploads/purchase_letters/PR-0045.docx', 'uploads/purchase_letters/PR-0045_sig.png', 'uploads/purchase_letters/PR-0045_selfie.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `quality_checks`
--

CREATE TABLE `quality_checks` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `received` date DEFAULT NULL,
  `status` enum('pending','passed','failed') NOT NULL DEFAULT 'pending',
  `checked_by` int(10) UNSIGNED DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quality_checks`
--

INSERT INTO `quality_checks` (`id`, `item_id`, `seller_id`, `received`, `status`, `checked_by`, `checked_at`, `note`, `created_at`) VALUES
(1, 1, 1, '2026-07-29', 'pending', NULL, NULL, NULL, '2026-09-07 01:26:56'),
(2, 3, 2, '2026-07-28', 'passed', NULL, NULL, NULL, '2026-09-07 01:26:56'),
(3, 4, 2, '2026-07-28', 'failed', NULL, NULL, NULL, '2026-09-07 01:26:56');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(20) NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','returned') NOT NULL DEFAULT 'pending',
  `returned_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sellers`
--

CREATE TABLE `sellers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `contact` varchar(60) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `goods` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sellers`
--

INSERT INTO `sellers` (`id`, `name`, `contact`, `phone`, `goods`, `rating`, `is_active`, `created_at`) VALUES
(1, 'Kalinga Coffee Co.', 'Ms. Rowena', '0917-555-2013', 'Arabica, Robusta beans', 4.8, 1, '2026-09-07 01:26:55'),
(2, 'Dairy Fresh PH', 'Mr. Luis', '0918-221-4400', 'Fresh milk, cream', 4.5, 1, '2026-09-07 01:26:55'),
(3, 'SweetLine Supply', 'Ms. Ana', '0920-778-9910', 'Syrups, sauces', 4.1, 1, '2026-09-07 01:26:55'),
(4, 'PackRight Trading', 'Mr. Dan', '0915-330-1200', 'Cups, lids, napkins', 4.6, 1, '2026-09-07 01:26:55');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(20) NOT NULL,
  `item_id` int(10) UNSIGNED DEFAULT NULL,
  `item_desc` varchar(120) NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `received_qty` decimal(12,2) DEFAULT NULL,
  `shortage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shortage_status` enum('none','pending','backordered','credited') NOT NULL DEFAULT 'none',
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `eta` date DEFAULT NULL,
  `status` enum('In transit','Out for delivery','Delivered','Received') NOT NULL DEFAULT 'In transit',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pr_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `ref`, `item_id`, `item_desc`, `qty`, `received_qty`, `shortage_qty`, `shortage_status`, `seller_id`, `eta`, `status`, `created_at`, `pr_id`) VALUES
(1, 'SHP-771', 1, 'Arabica Beans (50kg)', 50.00, NULL, 0.00, 'none', 1, '2026-08-01', 'In transit', '2026-09-07 01:26:56', NULL),
(2, 'SHP-770', 6, 'Paper Cups (10k pcs)', 10000.00, NULL, 0.00, 'none', 4, '2026-07-31', 'Out for delivery', '2026-09-07 01:26:56', NULL),
(3, 'SHP-769', 3, 'Fresh Milk (80L)', 80.00, 80.00, 0.00, 'none', 2, '2026-07-30', 'Received', '2026-09-07 01:26:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stocktakes`
--

CREATE TABLE `stocktakes` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(20) NOT NULL,
  `status` enum('draft','finalized') NOT NULL DEFAULT 'draft',
  `performed_by` int(10) UNSIGNED NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalized_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocktake_items`
--

CREATE TABLE `stocktake_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `stocktake_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `system_qty` decimal(12,2) NOT NULL,
  `counted_qty` decimal(12,2) DEFAULT NULL,
  `variance` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `action` enum('in','out','reduce','remove','adjust') NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `performed_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `item_id`, `action`, `qty`, `unit`, `note`, `performed_by`, `created_at`) VALUES
(1, 1, 'in', 20.00, 'kg', 'Delivery received', 1, '2026-07-28 01:14:00'),
(2, 3, 'out', 6.00, 'L', 'Used for AM shift', 1, '2026-07-28 03:02:00'),
(3, 5, 'reduce', 1.00, 'bottle', 'Spillage / wastage', 2, '2026-07-27 08:45:00'),
(4, 6, 'in', 500.00, 'pcs', 'Restock', 1, '2026-07-27 00:30:00'),
(5, 2, 'remove', 2.00, 'kg', 'Expired / disposed', 3, '2026-07-26 06:20:00'),
(6, 4, 'out', 4.00, 'L', 'PM shift usage', 1, '2026-07-26 02:05:00'),
(7, 1, 'out', 0.04, 'kg', 'POS sale POS-1001 — 2x Arabica Beans', 5, '2026-09-09 11:44:16'),
(8, 3, 'out', 0.40, 'L', 'POS sale POS-1001 — 2x Fresh Milk', 5, '2026-09-09 11:44:16'),
(9, 5, 'out', 0.06, 'bottle', 'POS sale POS-1001 — 2x Caramel Syrup', 5, '2026-09-09 11:44:16'),
(10, 6, 'out', 2.00, 'pcs', 'POS sale POS-1001 — 2x Paper Cups 12oz', 5, '2026-09-09 11:44:16'),
(11, 1, 'out', 0.06, 'kg', 'POS sale POS-1001 — 3x Arabica Beans', 5, '2026-09-09 11:44:16'),
(12, 3, 'out', 0.60, 'L', 'POS sale POS-1001 — 3x Fresh Milk', 5, '2026-09-09 11:44:16'),
(13, 6, 'out', 3.00, 'pcs', 'POS sale POS-1001 — 3x Paper Cups 12oz', 5, '2026-09-09 11:44:16'),
(14, 3, 'in', 22.00, 'L', 'Approved request REQ-0001 (from warehouse)', 2, '2026-09-15 06:50:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('clerk','manager','finance','superadmin','cashier','barista','cleaner','admin') NOT NULL DEFAULT 'clerk',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `registered_signature_path` varchar(255) DEFAULT NULL,
  `registered_signature_updated_at` datetime DEFAULT NULL,
  `remember_selector` varchar(64) DEFAULT NULL,
  `remember_hash` varchar(255) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `registered_signature_path`, `registered_signature_updated_at`, `remember_selector`, `remember_hash`, `remember_expires`) VALUES
(1, 'Jenwin', 'Docabo', 'jenwin@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'clerk', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL),
(2, 'Jayr', 'Fabon', 'jayr@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'manager', 1, '2026-09-07 01:26:55', 'uploads/signatures/inv_reg_2.png', '2026-09-15 11:24:27', NULL, NULL, NULL),
(3, 'Lavish Herzicm', 'Ancero', 'lavish@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'finance', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL),
(4, 'System', 'Admin', 'admin@brewco.ph', '$2y$12$rhOGPMCHdEePSdidLISKUeUlCUzqLLp8.6o0oTgQITA2twVLK1L8q', 'superadmin', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL),
(5, 'Rica', 'Diaz', 'rica@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'cashier', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL),
(6, 'Marco', 'Lim', 'marco@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'barista', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL),
(7, 'Nina', 'Reyes', 'nina@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'cleaner', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL),
(8, 'POS', 'Admin', 'posadmin@brewco.ph', '$2y$12$9YNs2Qw7XVMMhHQ8ytRWju/cOIEtx9lvtgUkhD0y75B3qHVPjFy72', 'admin', 1, '2026-09-07 01:26:55', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_receipts`
--

CREATE TABLE `warehouse_receipts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(20) NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `received_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_receipts`
--

INSERT INTO `warehouse_receipts` (`id`, `ref`, `item_id`, `qty`, `unit`, `seller_id`, `note`, `received_by`, `created_at`) VALUES
(1, 'WHR-0001', 3, 80.00, 'L', 2, 'From shipment SHP-769 (QC passed)', 2, '2026-09-15 06:48:52');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_requests`
--

CREATE TABLE `warehouse_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(20) NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `fulfilled_qty` decimal(12,2) DEFAULT NULL,
  `decided_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_status` varchar(12) NOT NULL DEFAULT 'pending',
  `admin_decided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_requests`
--

INSERT INTO `warehouse_requests` (`id`, `ref`, `item_id`, `qty`, `unit`, `note`, `requested_by`, `status`, `fulfilled_qty`, `decided_by`, `decided_at`, `created_at`, `admin_status`, `admin_decided_at`) VALUES
(1, 'REQ-0001', 3, 22.00, 'L', 'ccv', 1, 'approved', 22.00, 2, '2026-09-15 14:50:29', '2026-09-15 06:49:30', 'approved', '2026-09-15 14:49:55');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_stock`
--

CREATE TABLE `warehouse_stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_stock`
--

INSERT INTO `warehouse_stock` (`id`, `item_id`, `qty`) VALUES
(1, 1, 0.00),
(2, 2, 0.00),
(3, 3, 58.00),
(4, 4, 0.00),
(5, 5, 0.00),
(6, 6, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_transfers`
--

CREATE TABLE `warehouse_transfers` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(20) NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `transferred_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_transfers`
--

INSERT INTO `warehouse_transfers` (`id`, `ref`, `item_id`, `qty`, `unit`, `note`, `transferred_by`, `created_at`) VALUES
(1, 'WHT-0001', 3, 22.00, 'L', 'Fulfilled REQ-0001', 2, '2026-09-15 06:50:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_date` (`created_at`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batches_item` (`item_id`),
  ADD KEY `idx_batches_expiry` (`expiry_date`);

--
-- Indexes for table `budget`
--
ALTER TABLE `budget`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_budget_month` (`month`);

--
-- Indexes for table `budget_log`
--
ALTER TABLE `budget_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `decided_by` (`decided_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `hrms_applicants`
--
ALTER TABLE `hrms_applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_hrms_app_stage` (`stage`);

--
-- Indexes for table `hrms_applicant_accounts`
--
ALTER TABLE `hrms_applicant_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `hrms_approval_history`
--
ALTER TABLE `hrms_approval_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hrms_approval_requests`
--
ALTER TABLE `hrms_approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `hrms_archived_items`
--
ALTER TABLE `hrms_archived_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hrms_attendance_records`
--
ALTER TABLE `hrms_attendance_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hrms_audit_logs`
--
ALTER TABLE `hrms_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hrms_budget`
--
ALTER TABLE `hrms_budget`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `month` (`month`);

--
-- Indexes for table `hrms_employees`
--
ALTER TABLE `hrms_employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `hrms_interview_scores`
--
ALTER TABLE `hrms_interview_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `hrms_leave_requests`
--
ALTER TABLE `hrms_leave_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hrms_notifications`
--
ALTER TABLE `hrms_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hrms_payroll`
--
ALTER TABLE `hrms_payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `hrms_positions`
--
ALTER TABLE `hrms_positions`
  ADD UNIQUE KEY `title` (`title`);

--
-- Indexes for table `hrms_schedules`
--
ALTER TABLE `hrms_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `hrms_users`
--
ALTER TABLE `hrms_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_hrms_users_email` (`email`),
  ADD KEY `idx_hrms_users_role` (`role`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login` (`email`,`created_at`),
  ADD KEY `idx_login_ip` (`ip`,`created_at`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_menu_ing` (`menu_item_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pos_categories`
--
ALTER TABLE `pos_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_pos_cats_name` (`name`);

--
-- Indexes for table `pos_items`
--
ALTER TABLE `pos_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pos_items_cat` (`category`),
  ADD KEY `idx_pos_items_featured` (`featured`);

--
-- Indexes for table `pos_orders`
--
ALTER TABLE `pos_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `pos_order_items`
--
ALTER TABLE `pos_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `pos_sales`
--
ALTER TABLE `pos_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `idx_pos_sales_code` (`order_code`),
  ADD KEY `idx_pos_sales_status` (`status`);

--
-- Indexes for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `pos_users`
--
ALTER TABLE `pos_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_pos_users_email` (`email`),
  ADD KEY `idx_pos_users_role` (`role`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pr_code` (`pr_code`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `quality_checks`
--
ALTER TABLE `quality_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `checked_by` (`checked_by`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref` (`ref`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `returned_by` (`returned_by`);

--
-- Indexes for table `sellers`
--
ALTER TABLE `sellers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref` (`ref`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `stocktakes`
--
ALTER TABLE `stocktakes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref` (`ref`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `stocktake_items`
--
ALTER TABLE `stocktake_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stocktake_id` (`stocktake_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `warehouse_receipts`
--
ALTER TABLE `warehouse_receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `warehouse_requests`
--
ALTER TABLE `warehouse_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `decided_by` (`decided_by`);

--
-- Indexes for table `warehouse_stock`
--
ALTER TABLE `warehouse_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_wh_item` (`item_id`);

--
-- Indexes for table `warehouse_transfers`
--
ALTER TABLE `warehouse_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `transferred_by` (`transferred_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget`
--
ALTER TABLE `budget`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `budget_log`
--
ALTER TABLE `budget_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hrms_applicants`
--
ALTER TABLE `hrms_applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `hrms_applicant_accounts`
--
ALTER TABLE `hrms_applicant_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hrms_approval_history`
--
ALTER TABLE `hrms_approval_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `hrms_approval_requests`
--
ALTER TABLE `hrms_approval_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `hrms_archived_items`
--
ALTER TABLE `hrms_archived_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hrms_attendance_records`
--
ALTER TABLE `hrms_attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `hrms_audit_logs`
--
ALTER TABLE `hrms_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `hrms_budget`
--
ALTER TABLE `hrms_budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hrms_employees`
--
ALTER TABLE `hrms_employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `hrms_interview_scores`
--
ALTER TABLE `hrms_interview_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hrms_leave_requests`
--
ALTER TABLE `hrms_leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `hrms_notifications`
--
ALTER TABLE `hrms_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `hrms_payroll`
--
ALTER TABLE `hrms_payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `hrms_schedules`
--
ALTER TABLE `hrms_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `hrms_users`
--
ALTER TABLE `hrms_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_categories`
--
ALTER TABLE `pos_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=641;

--
-- AUTO_INCREMENT for table `pos_items`
--
ALTER TABLE `pos_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pos_orders`
--
ALTER TABLE `pos_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pos_order_items`
--
ALTER TABLE `pos_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pos_sales`
--
ALTER TABLE `pos_sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `pos_users`
--
ALTER TABLE `pos_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quality_checks`
--
ALTER TABLE `quality_checks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sellers`
--
ALTER TABLE `sellers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stocktakes`
--
ALTER TABLE `stocktakes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stocktake_items`
--
ALTER TABLE `stocktake_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `warehouse_receipts`
--
ALTER TABLE `warehouse_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `warehouse_requests`
--
ALTER TABLE `warehouse_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `warehouse_stock`
--
ALTER TABLE `warehouse_stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `warehouse_transfers`
--
ALTER TABLE `warehouse_transfers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `budget_log`
--
ALTER TABLE `budget_log`
  ADD CONSTRAINT `budget_log_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`),
  ADD CONSTRAINT `budget_log_ibfk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `hrms_applicant_accounts`
--
ALTER TABLE `hrms_applicant_accounts`
  ADD CONSTRAINT `hrms_applicant_accounts_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `hrms_applicants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `hrms_approval_requests`
--
ALTER TABLE `hrms_approval_requests`
  ADD CONSTRAINT `hrms_approval_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hrms_employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hrms_interview_scores`
--
ALTER TABLE `hrms_interview_scores`
  ADD CONSTRAINT `hrms_interview_scores_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `hrms_applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hrms_payroll`
--
ALTER TABLE `hrms_payroll`
  ADD CONSTRAINT `hrms_payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hrms_employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hrms_schedules`
--
ALTER TABLE `hrms_schedules`
  ADD CONSTRAINT `hrms_schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hrms_employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  ADD CONSTRAINT `menu_ingredients_ibfk_1` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`),
  ADD CONSTRAINT `menu_ingredients_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_orders`
--
ALTER TABLE `pos_orders`
  ADD CONSTRAINT `pos_orders_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pos_order_items`
--
ALTER TABLE `pos_order_items`
  ADD CONSTRAINT `pos_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`id`),
  ADD CONSTRAINT `pos_order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  ADD CONSTRAINT `fk_pos_sale_items_menu_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pos_sale_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `quality_checks`
--
ALTER TABLE `quality_checks`
  ADD CONSTRAINT `quality_checks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `quality_checks_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  ADD CONSTRAINT `quality_checks_ibfk_3` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  ADD CONSTRAINT `returns_ibfk_3` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`);

--
-- Constraints for table `stocktakes`
--
ALTER TABLE `stocktakes`
  ADD CONSTRAINT `stocktakes_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stocktake_items`
--
ALTER TABLE `stocktake_items`
  ADD CONSTRAINT `stocktake_items_ibfk_1` FOREIGN KEY (`stocktake_id`) REFERENCES `stocktakes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stocktake_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `warehouse_receipts`
--
ALTER TABLE `warehouse_receipts`
  ADD CONSTRAINT `warehouse_receipts_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `warehouse_receipts_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`),
  ADD CONSTRAINT `warehouse_receipts_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `warehouse_requests`
--
ALTER TABLE `warehouse_requests`
  ADD CONSTRAINT `warehouse_requests_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `warehouse_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `warehouse_requests_ibfk_3` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `warehouse_stock`
--
ALTER TABLE `warehouse_stock`
  ADD CONSTRAINT `warehouse_stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `warehouse_transfers`
--
ALTER TABLE `warehouse_transfers`
  ADD CONSTRAINT `warehouse_transfers_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `warehouse_transfers_ibfk_2` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
