<?php
require_once 'config.php';

// Check if column already exists
$result = $conn->query("SHOW COLUMNS FROM borrowed_items LIKE 'is_overdue_notified'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE borrowed_items ADD COLUMN is_overdue_notified BOOLEAN DEFAULT 0 AFTER decision_remarks");
    echo "✓ Column is_overdue_notified added\n";
} else {
    echo "✓ Column is_overdue_notified already exists\n";
}
?>
