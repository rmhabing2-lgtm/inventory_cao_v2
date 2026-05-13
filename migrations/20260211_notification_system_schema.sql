-- Notification System Schema Migration
-- Adds enterprise-grade notification columns for priority routing, audit logging, and multi-channel delivery
-- COA-Compliant: Immutable notifications with full audit trail

-- Step 1: Alter notifications table to add new columns
-- Note: Execute these individually if adding to existing table

ALTER TABLE `notifications` 
ADD COLUMN `priority` ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal' AFTER `payload`,
ADD COLUMN `sender_role` ENUM('STAFF', 'ADMIN', 'SYSTEM') DEFAULT 'SYSTEM' AFTER `priority`,
ADD COLUMN `delivery_channels` JSON AFTER `sender_role`,
ADD COLUMN `read_at` DATETIME DEFAULT NULL AFTER `is_read`,
ADD COLUMN `message_title` VARCHAR(255) AFTER `read_at`,
ADD COLUMN `related_entity_type` VARCHAR(50) AFTER `message_title`,
ADD COLUMN `acknowledged_at` DATETIME DEFAULT NULL COMMENT 'For critical notifications requiring explicit acknowledgment' AFTER `related_entity_type`;

-- Step 2: Add indexes for performance
ALTER TABLE `notifications` 
ADD INDEX `idx_user_read` (`user_id`, `is_read`),
ADD INDEX `idx_type_created` (`type`, `created_at`),
ADD INDEX `idx_priority` (`priority`),
ADD INDEX `idx_created_at` (`created_at`);

-- Step 3: Create notification audit log table for COA compliance
CREATE TABLE IF NOT EXISTS `notification_audit_log` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `notification_id` INT DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL COMMENT 'CREATED, SENT, READ, MARKED_READ, DUPLICATE_SUPPRESSED, ACKNOWLEDGED, etc',
  `actor_user_id` INT DEFAULT NULL,
  `actor_role` VARCHAR(50) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notification_id` (`notification_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`),
  FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 4: Create notification preferences table (optional - for user channel preferences)
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `enabled` BOOLEAN DEFAULT TRUE,
  `channels` JSON COMMENT 'User can override default channels per type',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_type` (`user_id`, `notification_type`),
  FOREIGN KEY (`user_id`) REFERENCES `cao_employee` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
