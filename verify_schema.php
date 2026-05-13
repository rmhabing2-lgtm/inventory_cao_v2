<?php
// Quick schema verification script
require_once 'config.php';

echo "=== BORROW SYSTEM SCHEMA VERIFICATION ===\n\n";

// 1. Check tables
$tables = ['borrowed_items', 'incident_reports', 'inventory_items'];
foreach ($tables as $table) {
    $r = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'");
    $status = $r->num_rows > 0 ? '✓' : '✗';
    echo "$status Table: $table\n";
}

echo "\n--- Column Verification ---\n";

// 2. Check borrowed_items columns
$columns_to_check = [
    'borrowed_items' => ['expected_return_date', 'purpose', 'release_condition', 'release_timestamp', 'is_overdue_notified'],
    'inventory_items' => ['item_status'],
    'incident_reports' => ['borrow_id', 'incident_type', 'severity', 'estimated_cost']
];

foreach ($columns_to_check as $table => $cols) {
    echo "\n$table:\n";
    $desc = $conn->query("DESCRIBE $table");
    $existing = [];
    while ($c = $desc->fetch_assoc()) {
        if (in_array($c['Field'], $cols)) {
            $existing[] = $c['Field'];
            echo "  ✓ {$c['Field']}\n";
        }
    }
    $missing = array_diff($cols, $existing);
    if ($missing) {
        foreach ($missing as $m) {
            echo "  ✗ $m (MISSING)\n";
        }
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";
?>
