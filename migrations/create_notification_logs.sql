-- Migration: create_notification_logs.sql
-- Run this against your MySQL/MariaDB database used by the app

CREATE TABLE IF NOT EXISTS `notification_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` INT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `page` VARCHAR(2083) NULL,
  `filename` VARCHAR(255) NULL,
  `lineno` INT NULL,
  `colno` INT NULL,
  `message` TEXT NULL,
  `stack` LONGTEXT NULL,
  `payload` JSON NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_logs_user` (`user_id`),
  KEY `idx_notification_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
