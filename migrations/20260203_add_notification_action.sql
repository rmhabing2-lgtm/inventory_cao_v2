-- Migration: Add action metadata to notifications and notification_logs
-- Run on your MySQL/MariaDB database (inventory_cao)

ALTER TABLE `notifications`
  ADD COLUMN `action` ENUM('NONE','APPROVED','DENIED') NOT NULL DEFAULT 'NONE',
  ADD COLUMN `action_by` INT(11) DEFAULT NULL,
  ADD COLUMN `action_at` DATETIME DEFAULT NULL;

ALTER TABLE `notification_logs`
  ADD COLUMN `notification_id` INT(11) DEFAULT NULL,
  ADD COLUMN `event` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN `action` ENUM('NONE','APPROVED','DENIED','MARK_READ') NOT NULL DEFAULT 'NONE',
  ADD COLUMN `action_by` INT(11) DEFAULT NULL,
  ADD COLUMN `action_at` DATETIME DEFAULT NULL;

-- Optional: add indexes to speed lookups
ALTER TABLE `notifications`
  ADD INDEX `idx_notifications_action` (`action`),
  ADD INDEX `idx_notifications_action_by` (`action_by`);

ALTER TABLE `notification_logs`
  ADD INDEX `idx_notification_logs_notification_id` (`notification_id`),
  ADD INDEX `idx_notification_logs_action` (`action`);

-- End of migration
