-- =====================================================
--  BARANGAYGO - WAMP DATABASE SETUP
--  Database: webdev2
--  Compatible sa WAMP MySQL & MariaDB
-- =====================================================

CREATE DATABASE IF NOT EXISTS `webdev2`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `webdev2`;

-- 1. RESIDENTS TABLE
CREATE TABLE IF NOT EXISTS `residents` (
    `resident_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `salutation`    VARCHAR(20) NOT NULL DEFAULT '',
    `first_name`    VARCHAR(100) NOT NULL,
    `middle_name`   VARCHAR(100) NOT NULL DEFAULT '',
    `last_name`     VARCHAR(100) NOT NULL,
    `full_name`     VARCHAR(320) NOT NULL,
    `birthday`      DATE NOT NULL,
    `age`           INT NOT NULL DEFAULT 0,
    `sex`           VARCHAR(20) NOT NULL DEFAULT '',
    `civil_status`  VARCHAR(50) NOT NULL DEFAULT '',
    `mobile`        VARCHAR(20) NOT NULL DEFAULT '',
    `street`        VARCHAR(255) NOT NULL,
    `region`        VARCHAR(100) NOT NULL,
    `province`      VARCHAR(100) NOT NULL,
    `city`          VARCHAR(100) NOT NULL,
    `barangay`      VARCHAR(100) NOT NULL,
    `email`         VARCHAR(255) NOT NULL UNIQUE,
    `password`      VARCHAR(255) NOT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. REQUESTS TABLE (Barangay Documents)
CREATE TABLE IF NOT EXISTS `requests` (
    `request_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `resident_id`         INT NOT NULL,
    `service`             VARCHAR(100) NOT NULL,
    `purpose`             TEXT NOT NULL,
    `business_name`       VARCHAR(255) DEFAULT NULL,
    `business_address`    TEXT DEFAULT NULL,
    `business_type`       VARCHAR(100) DEFAULT NULL,
    `years_of_residency`  INT DEFAULT NULL,
    `id_type`             VARCHAR(100) DEFAULT NULL,
    `id_front`            VARCHAR(255) DEFAULT NULL,
    `id_back`             VARCHAR(255) DEFAULT NULL,
    `selfie`              VARCHAR(255) DEFAULT NULL,
    `status`              ENUM('Pending','Approved','Rejected','Ready','Released') NOT NULL DEFAULT 'Pending',
    `remarks`             TEXT DEFAULT NULL,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_req_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
    `notif_id`      INT AUTO_INCREMENT PRIMARY KEY,
    `resident_id`   INT NOT NULL,
    `request_id`    INT DEFAULT NULL,
    `message`       TEXT NOT NULL,
    `is_read`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_res` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ADMINS TABLE
CREATE TABLE IF NOT EXISTS `admins` (
    `admin_id`    INT AUTO_INCREMENT PRIMARY KEY,
    `username`    VARCHAR(100) NOT NULL UNIQUE,
    `email`       VARCHAR(255) DEFAULT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `full_name`   VARCHAR(255) NOT NULL DEFAULT 'Administrator',
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin account (Username: admin | Password: adminpassword)
-- Password hash for 'adminpassword'
INSERT IGNORE INTO `admins` (`admin_id`, `username`, `email`, `password`, `full_name`)
VALUES (
    1,
    'admin',
    'admin@barangaygo.local',
    '$2y$10$wEkgmC/g9yF4wZ5Y2HqLKe8k1K8vS6Yk.7Z1YtYj1bVv6lS8jUqWe',
    'Barangay Administrator'
);
