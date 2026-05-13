-- Migration: Add Escalation Level State Machine
-- Date: 2026-02-10
-- Purpose: Replace is_overdue_notified flag with explicit escalation level tracking

-- Add escalation_level column to borrowed_items table
ALTER TABLE `borrowed_items` 
ADD COLUMN `escalation_level` ENUM('None', 'Warning', 'Escalated', 'Incident') DEFAULT 'None' AFTER `status`;

-- Create index for efficient queries on overdue items
CREATE INDEX idx_borrowed_escalation_level ON borrowed_items(status, escalation_level, expected_return_date);

-- Update existing records to 'None' (default applied automatically, but being explicit)
UPDATE borrowed_items SET escalation_level = 'None' WHERE escalation_level IS NULL;

-- Migration log
INSERT INTO schema_migrations (version, name, batch, executed_at) 
VALUES ('20260210_add_escalation_level', 'Add Escalation Level State Machine', 1, NOW());
