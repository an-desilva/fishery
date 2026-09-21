-- Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System Schema
-- Compatible with MySQL 5.7+ / MySQL 8.0+

CREATE DATABASE IF NOT EXISTS `fishing_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fishing_db`;

-- 1. Users Table (Authentication & RBAC)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Trips Table
CREATE TABLE IF NOT EXISTS `trips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `boat_name` VARCHAR(100) NOT NULL,
    `reg_number` VARCHAR(50) NOT NULL,
    `skipper_name` VARCHAR(100) NOT NULL,
    `crew_count` INT NOT NULL DEFAULT 1,
    `crew_members` TEXT NULL,
    `departure_date` DATE NOT NULL,
    `arrival_date` DATE NULL,
    `status` ENUM('DEPARTED', 'LANDED', 'DISPATCHED', 'COMPLETED') NOT NULL DEFAULT 'DEPARTED',
    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Departure Expenses Table (Admin Restricted)
CREATE TABLE IF NOT EXISTS `trip_expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `trip_id` INT NOT NULL,
    `diesel_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `ice_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `ration_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `gas_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `maintenance_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `bait_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `other_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_expenses` DECIMAL(12, 2) GENERATED ALWAYS AS (diesel_cost + ice_cost + ration_cost + gas_cost + maintenance_cost + bait_cost + other_cost) STORED,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Harbour Unloading Labour Table
CREATE TABLE IF NOT EXISTS `harbour_unloadings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `trip_id` INT NOT NULL,
    `rate_type` ENUM('FIXED', 'PER_BOX') NOT NULL DEFAULT 'FIXED',
    `rate_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_unloaders` INT NOT NULL DEFAULT 1,
    `total_boxes` INT NOT NULL DEFAULT 0,
    `total_labour_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Catch Dispatches Table (Lorry Logistics)
CREATE TABLE IF NOT EXISTS `dispatches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `trip_id` INT NOT NULL,
    `dispatch_no` VARCHAR(30) NOT NULL UNIQUE,
    `lorry_number` VARCHAR(30) NOT NULL,
    `driver_name` VARCHAR(100) NOT NULL,
    `driver_phone` VARCHAR(30) NULL,
    `helper_name` VARCHAR(100) NULL,
    `lorry_hire_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `helper_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `transit_allowance` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_transport_cost` DECIMAL(10, 2) GENERATED ALWAYS AS (lorry_hire_fee + helper_fee + transit_allowance) STORED,
    `dispatch_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Dispatch Catch Box Items Table
CREATE TABLE IF NOT EXISTS `dispatch_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dispatch_id` INT NOT NULL,
    `trip_id` INT NOT NULL,
    `quality_grade` VARCHAR(20) NOT NULL, -- Grade 1 (①), Grade 2 (②), Grade 3 (③), Reject
    `fish_species` VARCHAR(50) NOT NULL, -- Kelawalla, Balaya, Thalapatha, Koppara, Alagoduwa, Hurulla, Mixed
    `size_category` ENUM('L', 'P') NOT NULL DEFAULT 'L', -- L (Large), P (Small)
    `box_count` INT NOT NULL DEFAULT 1,
    `net_weight_kg` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Market Final Bills Table (Bill Vault)
CREATE TABLE IF NOT EXISTS `trip_bills` (
    `bill_id` INT AUTO_INCREMENT PRIMARY KEY,
    `trip_id` INT NOT NULL,
    `bill_title` VARCHAR(150) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(20) NOT NULL,
    `file_size_kb` INT NOT NULL DEFAULT 0,
    `uploaded_by` INT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Direct & Secondary Fish Buyers Table (අමතර ගැනුම්කරුවන්)
CREATE TABLE IF NOT EXISTS `direct_buyers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `trip_id` INT NOT NULL,
    `buyer_name` VARCHAR(100) NOT NULL,
    `contact_number` VARCHAR(30) NULL,
    `vehicle_no` VARCHAR(30) NULL,
    `payment_type` VARCHAR(30) NOT NULL DEFAULT 'CASH',
    `total_weight_kg` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_boxes` INT NOT NULL DEFAULT 0,
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Direct Buyer Itemized Fish Table
CREATE TABLE IF NOT EXISTS `direct_buyer_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `buyer_id` INT NOT NULL,
    `trip_id` INT NOT NULL,
    `quality_grade` VARCHAR(20) NOT NULL,
    `fish_species` VARCHAR(50) NOT NULL,
    `size_category` VARCHAR(10) NOT NULL DEFAULT 'L',
    `net_weight_kg` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `box_count` INT NOT NULL DEFAULT 1,
    `rate_per_kg` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`buyer_id`) REFERENCES `direct_buyers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Seed Default Accounts
-- Default Admin: admin / AdminPassword123 ($2y$10$wH603G5y3gQ3e5X.8Y.Oze.1pW596LpB3Z1oM74vP61qC9L0v71eS)
-- Default Staff: staff / StaffPassword123 ($2y$10$Q7eY4H8.z7gJ.a89Xb1q1u5/M8aN34267L8Z.k9J.07aL6Z5.908u)

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`) VALUES
(1, 'admin', 'admin@sealogix.com', '$2y$10$qi4O1gqP98hQ.vEbClsUwutQVJrHbd/JDftc4ecUkzJXjTVM/qm7G', 'Admin', 1),
(2, 'staff', 'staff@sealogix.com', '$2y$10$ubqL77CJ9g38/pURHQejGO3El35ZjOBmnDy5nK7Xuxd8FyAJQxN4q', 'Staff', 1)
ON DUPLICATE KEY UPDATE `password_hash`=VALUES(`password_hash`), `is_active`=1;

-- Seed Sample Trip & Dispatch Data
INSERT INTO `trips` (`id`, `boat_name`, `reg_number`, `skipper_name`, `crew_count`, `crew_members`, `departure_date`, `arrival_date`, `status`, `is_locked`, `created_by`) VALUES
(1, 'Ocean Queen', 'IMUL-A-0482-TRINCO', 'Sunil Perera', 5, 'K. Silva, M. Fernando, P. Kumara, S. Bandara, R. Gamage', '2026-08-01', '2026-08-12', 'DISPATCHED', 0, 1),
(2, 'Blue Dolphin', 'IMUL-A-0911-GALLE', 'Kamal Samarawickrama', 4, 'N. Perera, A. Mendis, W. Dasun, C. Ruwan', '2026-08-08', NULL, 'DEPARTED', 0, 1)
ON DUPLICATE KEY UPDATE `boat_name`=`boat_name`;

INSERT INTO `trip_expenses` (`id`, `trip_id`, `diesel_cost`, `ice_cost`, `ration_cost`, `gas_cost`, `maintenance_cost`, `bait_cost`, `other_cost`, `notes`) VALUES
(1, 1, 450000.00, 120000.00, 85000.00, 18000.00, 25000.00, 30000.00, 12000.00, '12-Day Deep Sea Trip Provisions')
ON DUPLICATE KEY UPDATE `diesel_cost`=`diesel_cost`;

INSERT INTO `harbour_unloadings` (`id`, `trip_id`, `rate_type`, `rate_amount`, `total_unloaders`, `total_boxes`, `total_labour_fee`, `notes`) VALUES
(1, 1, 'PER_BOX', 250.00, 6, 85, 21250.00, 'Unloaded at Dikowita Pier #4')
ON DUPLICATE KEY UPDATE `total_boxes`=`total_boxes`;

INSERT INTO `dispatches` (`id`, `trip_id`, `dispatch_no`, `lorry_number`, `driver_name`, `driver_phone`, `helper_name`, `lorry_hire_fee`, `helper_fee`, `transit_allowance`, `dispatch_date`, `notes`) VALUES
(1, 1, 'DISP-20260812-01', 'WP LE-4892', 'Dhammika Bandara', '0771234567', 'Saman Kumara', 35000.00, 5000.00, 2500.00, '2026-08-12 14:30:00', 'Logistics Dispatch Waybill')
ON DUPLICATE KEY UPDATE `dispatch_no`=`dispatch_no`;

INSERT INTO `dispatch_items` (`id`, `dispatch_id`, `trip_id`, `quality_grade`, `fish_species`, `size_category`, `box_count`, `net_weight_kg`) VALUES
(1, 1, 1, 'Grade 1 (①)', 'Yellowfin Tuna (Kelawalla)', 'L', 35, 1250.50),
(2, 1, 1, 'Grade 2 (②)', 'Skipjack (Balaya)', 'P', 25, 820.00),
(3, 1, 1, 'Grade 1 (①)', 'Sailfish (Thalapatha)', 'L', 15, 480.00),
(4, 1, 1, 'Grade 3 (③)', 'Marlin (Koppara)', 'L', 10, 310.00)
ON DUPLICATE KEY UPDATE `net_weight_kg`=`net_weight_kg`;
