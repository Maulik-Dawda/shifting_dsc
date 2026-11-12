-- DSC Office Shifting Inventory Database Schema
-- Database: shifting_dsc
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ / SQLite

CREATE DATABASE IF NOT EXISTS `shifting_dsc` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shifting_dsc`;

-- --------------------------------------------------------
-- Table structure for items
-- --------------------------------------------------------
DROP TABLE IF EXISTS `item_images`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `items`;

CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `serial_number` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `old_location` varchar(150) DEFAULT '',
  `new_location` varchar(150) DEFAULT '',
  `status` varchar(50) NOT NULL DEFAULT 'packed',
  `old_condition` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reached_at` datetime DEFAULT NULL,
  `reached_by` varchar(100) DEFAULT NULL,
  `reached_condition` varchar(50) DEFAULT NULL,
  `reached_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_serial_number` (`serial_number`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for item_images
-- --------------------------------------------------------
CREATE TABLE `item_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `stage` varchar(50) NOT NULL DEFAULT 'old_office',
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_stage` (`item_id`, `stage`),
  CONSTRAINT `fk_item_images_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for activity_logs
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Initial Demo Data
-- --------------------------------------------------------
INSERT INTO `items` (`id`, `brand`, `model`, `serial_number`, `category`, `old_location`, `new_location`, `status`, `old_condition`, `created_at`, `reached_at`, `reached_by`, `reached_condition`, `reached_notes`) VALUES
(1, 'Dell', 'OptiPlex 7090 Desktop', 'DSC-DT-7090-001', 'Workstation', 'Old Office - IT Dept Desk 04', 'New Office - 3rd Floor IT Bay', 'reached', 'Clean condition, power cord and HDMI cable attached in box.', NOW(), NOW(), 'Maulik Dawda', 'perfect', 'Received intact and booted up successfully at new office.'),
(2, 'HP', 'LaserJet Pro MFP M428fdw', 'DSC-PR-428-8821', 'Printers', 'Old Office - Admin Printing Station', 'New Office - Admin Print Hub', 'packed', 'Toner removed and packed in separate anti-static pouch. Unit secured with bubble wrap.', NOW(), NULL, NULL, NULL, NULL),
(3, 'Apple', 'MacBook Pro 16" M2', 'DSC-MBP-16-9923', 'Laptops', 'Old Office - Design Studio Desk 01', 'New Office - Creative Wing Desk 12', 'packed', 'Supplied with original 140W MagSafe charger and sleeve.', NOW(), NULL, NULL, NULL, NULL),
(4, 'Cisco', 'Catalyst 2960-X 48-Port Switch', 'DSC-SW-2960-5541', 'Networking', 'Old Office - Main Server Rack 1U-12', 'New Office - Server Room Rack A-04', 'reached', 'Rack ears attached, console cable included.', NOW(), NOW(), 'IT Relocation Team', 'perfect', 'Mounted into Rack A at New Office.'),
(5, 'Samsung', '27" Curved LED Monitor S27E510CS', 'DSC-MON-27-3312', 'Monitors', 'Old Office - Finance Desk 02', 'New Office - Finance Hub Desk 08', 'packed', 'Packed with foam corner protectors and power adapter.', NOW(), NULL, NULL, NULL, NULL);

INSERT INTO `activity_logs` (`item_id`, `action`, `details`) VALUES
(NULL, 'System Initialized', 'Office shifting inventory system database initialized with default configuration.'),
(1, 'Marked Reached', 'Item DSC-DT-7090-001 verified and received at New Office.'),
(4, 'Marked Reached', 'Item DSC-SW-2960-5541 verified and received at New Office.');
