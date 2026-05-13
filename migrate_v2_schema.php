<?php
require 'config.php';

$migrations = [
    // Update Borrow Statuses
    "ALTER TABLE `borrowed_items` 
     MODIFY COLUMN `status` ENUM('DRAFT', 'PENDING', 'APPROVED', 'DENIED', 'RELEASED', 'EXPIRED', 'RETURN_PENDING', 'CLOSED') 
     NOT NULL DEFAULT 'PENDING'",

    // Add missing tracking columns
    "ALTER TABLE `borrowed_items` 
     ADD COLUMN `expected_return_date` DATETIME NULL AFTER `borrow_date`",
    
    "ALTER TABLE `borrowed_items` 
     ADD COLUMN `purpose` TEXT NULL AFTER `expected_return_date`",
    
    "ALTER TABLE `borrowed_items` 
     ADD COLUMN `release_condition` TEXT NULL AFTER `purpose`",
    
    "ALTER TABLE `borrowed_items` 
     ADD COLUMN `release_timestamp` DATETIME NULL AFTER `release_condition`",

    // Update Inventory Item Statuses
    "ALTER TABLE `inventory_items` 
     ADD COLUMN `item_status` ENUM('Available', 'Reserved', 'Borrowed', 'For Inspection', 'Unserviceable') 
     NOT NULL DEFAULT 'Available'",

    // Create incident_reports table
    "CREATE TABLE IF NOT EXISTS `incident_reports` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `borrow_id` INT(11) NOT NULL,
        `return_log_id` INT(11) DEFAULT NULL,
        `reported_by` INT(11) NOT NULL,
        `incident_type` ENUM('Damaged', 'Lost', 'Total Loss') NOT NULL,
        `severity` ENUM('Minor', 'Major', 'Irreparable') DEFAULT 'Minor',
        `description` TEXT NOT NULL,
        `estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
        `action_taken` ENUM('Repair', 'Replacement', 'Salary Deduction', 'Pending Investigation') DEFAULT 'Pending Investigation',
        `status` ENUM('Open', 'Under Review', 'Resolved', 'Closed') DEFAULT 'Open',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`borrow_id`) REFERENCES `borrowed_items` (`borrow_id`),
        INDEX `idx_incident_status` (`status`),
        INDEX `idx_borrow_id` (`borrow_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "Starting database migrations...\n\n";
$success = 0;
$errors = 0;

foreach ($migrations as $i => $sql) {
    try {
        $conn->query($sql);
        echo "[✓] Migration " . ($i + 1) . " completed\n";
        $success++;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "[~] Migration " . ($i + 1) . " already exists (skipped)\n";
            $success++;
        } else {
            echo "[✗] Migration " . ($i + 1) . " failed: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n=== SUMMARY ===\n";
echo "Completed: $success\n";
echo "Errors: $errors\n";
if ($errors === 0) {
    echo "\n✅ All migrations applied successfully!\n";
}
?>
