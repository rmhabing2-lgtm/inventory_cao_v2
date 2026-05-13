-- Create notification_audit_log table for COA compliance audit trail
-- Run this as needed if it doesn't exist

CREATE TABLE IF NOT EXISTS notification_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NULL,
    action VARCHAR(50) NOT NULL,
    actor_user_id INT NOT NULL,
    actor_role VARCHAR(50) NOT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_id (notification_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Also ensure the notifications table has the required columns for enterprise system
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high','critical') DEFAULT 'normal';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS sender_role ENUM('STAFF','ADMIN','SYSTEM') DEFAULT 'SYSTEM';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS delivery_channels JSON;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS read_at DATETIME NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS message_title VARCHAR(255);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS related_entity_type VARCHAR(50);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS acknowledged_at DATETIME NULL;

-- Add missing indexes
CREATE INDEX IF NOT EXISTS idx_user_read ON notifications(user_id, is_read, created_at);
CREATE INDEX IF NOT EXISTS idx_type_created ON notifications(type, created_at);
CREATE INDEX IF NOT EXISTS idx_priority ON notifications(priority);
CREATE INDEX IF NOT EXISTS idx_created_at ON notifications(created_at);

COMMIT;
