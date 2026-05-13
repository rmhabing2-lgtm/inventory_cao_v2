-- Migration: add notifications table and borrow status/approval fields
-- Run this SQL against your `inventory_cao` database (e.g., via phpMyAdmin or mysql CLI)

ALTER TABLE `borrowed_items`
  ADD COLUMN `status` ENUM('PENDING','APPROVED','DENIED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  ADD COLUMN `requested_by` INT NULL,
  ADD COLUMN `requested_at` DATETIME NULL,
  ADD COLUMN `approved_by` INT NULL,
  ADD COLUMN `approved_at` DATETIME NULL,
  ADD COLUMN `decision_remarks` TEXT NULL,
  ADD COLUMN `return_request_status` ENUM('NONE','PENDING','APPROVED','DENIED') NOT NULL DEFAULT 'NONE',
  ADD COLUMN `return_requested_by` INT NULL,
  ADD COLUMN `return_requested_at` DATETIME NULL,
  ADD COLUMN `return_approved_by` INT NULL,
  ADD COLUMN `return_approved_at` DATETIME NULL,
  ADD COLUMN `return_decision_remarks` TEXT NULL;

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `actor_user_id` INT NULL,
  `type` VARCHAR(50) NOT NULL,
  `related_id` INT NULL,
  `payload` JSON NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`user_id`),
  INDEX (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Note: add appropriate foreign keys if desired.
