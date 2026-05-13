<?php
/**
 * Quick diagnostic to verify bind_param types are correct
 * Run this to see parameter mapping
 */

// From borrow_items.php create_borrow section:
$params = [
    0 => 'accountable_id (int)',        // → i
    1 => 'inventory_item_id (int)',     // → i
    2 => 'person_name (string)',        // → s
    3 => 'borrower_name (string)',      // → s
    4 => 'borrower_id (int)',           // → i
    5 => 'qty (int)',                   // → i
    6 => 'ref_no (string)',             // → s
    7 => 'purpose (string)',            // → s
    8 => 'status (string)',             // → s
    9 => 'userId (int)',                // → i  **IMPORTANT: Position 10 (0-indexed: 9)**
    10 => 'expected_return (string)',   // → s **Position 11 (0-indexed: 10)**
];

$current_types = 'iissiisssss';  // Current code
$correct_types = 'iissiiisssis'; // What it should be

echo "Parameter count: " . count($params) . "\n";
echo "Current type string length: " . strlen($current_types) . "\n";
echo "Correct type string length: " . strlen($correct_types) . "\n\n";

echo "Position | Param Name                | Expected Type | Current Type | Match?\n";
echo str_repeat("-", 90) . "\n";

for ($i = 0; $i < count($params); $i++) {
    $expected = ['i','i','s','s','i','i','s','s','s','i','s'][$i];
    $current = $current_types[$i];
    $match = ($expected === $current) ? '✓' : '✗ MISMATCH!';
    
    printf("%d (1-idx) | %-25s | %s         | %s           | %s\n",
        $i + 1, $params[$i], $expected, $current, $match);
}

echo "\nCONCLUSION:\n";
echo "If Position 10 shows a mismatch, that's the bug causing the borrow creation to fail.\n";
echo "Position 10 should be 'i' for \$userId, not 's'.\n";
?>
