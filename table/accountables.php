<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

// ========================================
// SECURITY: CSRF PROTECTION
// ========================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf()
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Security token validation failed. Please try again.";
        header("Location: accountables.php");
        exit();
    }
}

// ========================================
// RESOLVE LOGGED-IN USER (EDITOR)
// ========================================
$admin_id = (int)(
    $_SESSION['user_id']        // most common
    ?? $_SESSION['id']          // some setups use 'id'
    ?? $_SESSION['user']['id']  // some setups nest under 'user'
    ?? 0
);

$admin_name = 'System';
$admin_role = '';

if ($admin_id > 0) {
    $stmt_admin = $conn->prepare("
        SELECT first_name, last_name, role
        FROM user
        WHERE id = ? AND status = 'ACTIVE'
        LIMIT 1
    ");
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result();
    if ($admin_data = $res_admin->fetch_assoc()) {
        $admin_name = trim($admin_data['first_name'] . ' ' . $admin_data['last_name']);
        $admin_role = strtoupper(trim($admin_data['role'] ?? ''));
    }
    $stmt_admin->close();
} else {
    error_log('[accountables.php] WARNING: Could not resolve admin_id from session. '
        . 'Session keys present: ' . implode(', ', array_keys($_SESSION)));
}

// ========================================
// HELPER: Write to system_logs
// ========================================
function log_system_action($conn, int $user_id, string $user_name, string $action_type, string $description): void
{
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, user_name, action_type, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $user_name, $action_type, $description);
    $stmt->execute();
    $stmt->close();
}

// ========================================
// HELPER: Write to audit_logs
// ========================================
function log_audit(
    $conn,
    int    $user_id,
    string $user_name,
    string $module,
    string $action,
    int    $record_id,
    ?string $old_data,
    ?string $new_data
): void {
    $stmt = $conn->prepare("
        INSERT INTO audit_logs
            (user_id, user_name, module, action, record_id, old_data, new_data)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssiss", $user_id, $user_name, $module, $action, $record_id, $old_data, $new_data);
    $stmt->execute();
    $stmt->close();
}

// ========================================
// CONTROLLER LOGIC (POST REQUESTS)
// ========================================

// ── 1. ASSIGN ITEM ──────────────────────────────────────────────────────────
if (isset($_POST['assign'])) {
    verify_csrf();

    $inventory_item_id = filter_input(INPUT_POST, 'inventory_item_id', FILTER_VALIDATE_INT);
    $employee_id       = filter_input(INPUT_POST, 'employee_id',       FILTER_VALIDATE_INT);
    $assign_quantity   = filter_input(INPUT_POST, 'assign_quantity',   FILTER_VALIDATE_INT);

    $are_mr_ics_num   = $_POST['are_mr_ics_num']   ?? '';
    $property_number  = $_POST['property_number']  ?? '';
    $serial_number    = $_POST['serial_number']    ?? '';
    $po_number        = $_POST['po_number']        ?? '';
    $account_code     = $_POST['account_code']     ?? '';
    $old_account_code = $_POST['old_account_code'] ?? '';
    $condition_status = $_POST['condition_status'] ?? 'Serviceable';
    $remarks          = $_POST['remarks']          ?? '';

    // Fetch the item's custodian name
    $emp_stmt = $conn->prepare("
        SELECT CONCAT_WS(' ', FIRSTNAME, MIDDLENAME, LASTNAME, SUFFIX) AS full_name
        FROM cao_employee WHERE ID = ?
    ");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_data = $emp_stmt->get_result()->fetch_assoc();
    $employee_name = trim($emp_data['full_name'] ?? 'Unknown Employee');
    $emp_stmt->close();

    // Fetch stock before change
    $stmt_pre = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmt_pre->bind_param("i", $inventory_item_id);
    $stmt_pre->execute();
    $old_inventory_data = $stmt_pre->get_result()->fetch_assoc();
    $stmt_pre->close();

    if ($old_inventory_data['quantity'] < $assign_quantity) {
        $_SESSION['error'] = "Insufficient stock! Only {$old_inventory_data['quantity']} left.";
    } else {
        $conn->begin_transaction();
        try {
            // A. CREATE ACCOUNTABLE RECORD (synced from inventory_items)
            $stmt = $conn->prepare("
                INSERT INTO accountable_items
                    (inventory_item_id, employee_id, person_name, assigned_quantity,
                     are_mr_ics_num, property_number, serial_number, po_number,
                     account_code, old_account_code, condition_status, remarks,
                     created_by_id, created_by_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iisissssssssss",
                $inventory_item_id,
                $employee_id,
                $employee_name,
                $assign_quantity,
                $are_mr_ics_num,
                $property_number,
                $serial_number,
                $po_number,
                $account_code,
                $old_account_code,
                $condition_status,
                $remarks,
                $admin_id,
                $admin_name
            );
            $stmt->execute();
            $new_record_id = $conn->insert_id;
            $stmt->close();

            // B. AUDIT LOG
            $new_accountable_data = json_encode([
                'inventory_item_id' => $inventory_item_id,
                'custodian'         => $employee_name,
                'quantity'          => $assign_quantity,
                'par_ics'           => $are_mr_ics_num,
                'property_no'       => $property_number,
                'serial_no'         => $serial_number,
                'assigned_by_id'    => $admin_id,
                'assigned_by_name'  => $admin_name,
            ]);
            log_audit($conn, $admin_id, $admin_name, 'accountable_items', 'CREATE', $new_record_id, null, $new_accountable_data);

            // C. UPDATE INVENTORY — MOVE (not copy) the selected tokens out of inventory_items.
            //    Only the tokens the user actually checked go to accountable_items;
            //    the rest stay in inventory_items so there is no duplication.
            $fn_remove_tokens = function (string $source_raw, string $selected_raw): string {
                if (trim($selected_raw) === '') return trim($source_raw); // nothing selected → keep all
                $source_tokens   = array_values(array_filter(array_map('trim', explode('/', $source_raw))));
                $selected_tokens = array_values(array_filter(array_map('trim', explode('/', $selected_raw))));
                $remaining = array_values(array_filter($source_tokens, function ($t) use ($selected_tokens) {
                    return !in_array($t, $selected_tokens, true);
                }));
                return implode(' / ', $remaining);
            };

            $new_inv_are    = $fn_remove_tokens($old_inventory_data['are_mr_ics_num'] ?? '', $are_mr_ics_num);
            $new_inv_serial = $fn_remove_tokens($old_inventory_data['serial_number']   ?? '', $serial_number);
            $new_inv_prop   = $fn_remove_tokens($old_inventory_data['property_number'] ?? '', $property_number);

            $update_stock = $conn->prepare("
                UPDATE inventory_items
                SET quantity        = quantity - ?,
                    are_mr_ics_num  = ?,
                    serial_number   = ?,
                    property_number = ?,
                    date_updated    = NOW()
                WHERE id = ?
            ");
            $update_stock->bind_param("isssi",
                $assign_quantity,
                $new_inv_are,
                $new_inv_serial,
                $new_inv_prop,
                $inventory_item_id
            );
            $update_stock->execute();
            $update_stock->close();

            // Fetch new stock for log
            $stmt_post = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
            $stmt_post->bind_param("i", $inventory_item_id);
            $stmt_post->execute();
            $new_inventory_data = $stmt_post->get_result()->fetch_assoc();
            $stmt_post->close();

            log_audit($conn, $admin_id, $admin_name, 'inventory_items', 'UPDATE', $inventory_item_id,
                json_encode($old_inventory_data), json_encode($new_inventory_data));

            // D. INVENTORY TRANSACTION
            $ref           = 'ASSIGN-' . date('YmdHis');
            $trans_remarks = "Assigned to $employee_name. Serial: $serial_number. By: $admin_name";
            $stmt_mv = $conn->prepare("
                INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no, transaction_date, remarks)
                VALUES (?, ?, ?, 'OUT', ?, ?, NOW(), ?)
            ");
            $stmt_mv->bind_param("iisiis", $inventory_item_id, $admin_id, $admin_name, $assign_quantity, $ref, $trans_remarks);
            $stmt_mv->execute();
            $stmt_mv->close();

            // E. SYSTEM LOG
            log_system_action($conn, $admin_id, $admin_name, 'ASSIGN_ITEM',
                "[$admin_name] Assigned {$assign_quantity} x item ID {$inventory_item_id} to {$employee_name} (accountable ID {$new_record_id})"
            );

            $conn->commit();
            $_SESSION['success'] = "Item successfully assigned and synchronized across all ledgers.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Critical Error: " . $e->getMessage();
        }
    }
    header("Location: accountables.php");
    exit();
}

// ── 2. DELETE ACCOUNTABLE ITEM ──────────────────────────────────────────────
if (isset($_POST['delete_id'])) {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);

    if ($id) {
        $conn->begin_transaction();
        try {
            // Lock accountable record
            $stmt = $conn->prepare("SELECT ai.*, ii.item_name FROM accountable_items ai JOIN inventory_items ii ON ii.id = ai.inventory_item_id WHERE ai.id = ? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $old_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$old_data) throw new Exception("Record not found.");

            // Fetch inventory BEFORE restore
            $stmt_inv_pre = $conn->prepare("SELECT * FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt_inv_pre->bind_param("i", $old_data['inventory_item_id']);
            $stmt_inv_pre->execute();
            $inv_before = $stmt_inv_pre->get_result()->fetch_assoc();
            $stmt_inv_pre->close();

            if (!$inv_before) throw new Exception("Inventory item not found.");

            // ── Merge helper: append accountable fields back to inventory ──
            $fn_merge = function(string $existing, string $from_acct): string {
                $existing  = trim($existing);
                $from_acct = trim($from_acct);
                if ($existing === '' && $from_acct === '') return '';
                if ($existing === '') return $from_acct;
                if ($from_acct === '') return $existing;
                return $existing . ' / ' . $from_acct;
            };

            $restore_are    = $fn_merge($inv_before['are_mr_ics_num']  ?? '', $old_data['are_mr_ics_num']  ?? '');
            $restore_serial = $fn_merge($inv_before['serial_number']    ?? '', $old_data['serial_number']    ?? '');
            $restore_prop   = $fn_merge($inv_before['property_number']  ?? '', $old_data['property_number']  ?? '');

            // ── Restore all data back to inventory_items ──
            $stmt_inv_upd = $conn->prepare("
                UPDATE inventory_items
                SET quantity        = quantity + ?,
                    are_mr_ics_num  = ?,
                    serial_number   = ?,
                    property_number = ?,
                    item_status     = 'Active',
                    date_updated    = NOW()
                WHERE id = ?
            ");
            $stmt_inv_upd->bind_param("isssi",
                $old_data['assigned_quantity'],
                $restore_are,
                $restore_serial,
                $restore_prop,
                $old_data['inventory_item_id']
            );
            $stmt_inv_upd->execute();
            $stmt_inv_upd->close();

            // Fetch inventory AFTER restore (for audit snapshot)
            $stmt_inv_post = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
            $stmt_inv_post->bind_param("i", $old_data['inventory_item_id']);
            $stmt_inv_post->execute();
            $inv_after = $stmt_inv_post->get_result()->fetch_assoc();
            $stmt_inv_post->close();

            // ── Soft-delete accountable record ──
            $stmt_del = $conn->prepare("
                UPDATE accountable_items
                SET is_deleted           = 1,
                    last_updated_by_id   = ?,
                    last_updated_by_name = ?,
                    last_updated_at      = NOW()
                WHERE id = ?
            ");
            $stmt_del->bind_param("isi", $admin_id, $admin_name, $id);
            $stmt_del->execute();
            $stmt_del->close();

            // ── audit_logs: accountable DELETE ──
            log_audit($conn, $admin_id, $admin_name, 'accountable_items', 'DELETE', $id,
                json_encode($old_data), null);

            // ── audit_logs: inventory_items UPDATE ──
            log_audit($conn, $admin_id, $admin_name, 'inventory_items', 'UPDATE',
                $old_data['inventory_item_id'],
                json_encode(['quantity' => $inv_before['quantity'], 'are_mr_ics_num' => $inv_before['are_mr_ics_num'], 'serial_number' => $inv_before['serial_number'], 'property_number' => $inv_before['property_number']]),
                json_encode($inv_after));

            // ── inventory_transactions: IN (stock returned) ──
            $ref_del = 'DEL-RESTORE-' . date('YmdHis');
            $trans_rmk_del = "Assignment deleted — {$old_data['assigned_quantity']} unit(s) of '{$old_data['item_name']}' returned to inventory stock from custodian: {$old_data['person_name']}. "
                . "Serial(s): " . ($old_data['serial_number'] ?? '—') . ". "
                . "PAR/ICS: " . ($old_data['are_mr_ics_num'] ?? '—') . ". "
                . "Prop#: " . ($old_data['property_number'] ?? '—') . ". "
                . "By: {$admin_name} ({$admin_role}). Accountable ID: {$id}.";
            $stmt_tx = $conn->prepare("
                INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no, transaction_date, remarks)
                VALUES (?, ?, ?, 'IN', ?, ?, NOW(), ?)
            ");
            $stmt_tx->bind_param("iisiis",
                $old_data['inventory_item_id'], $admin_id, $admin_name,
                $old_data['assigned_quantity'], $ref_del, $trans_rmk_del);
            $stmt_tx->execute();
            $stmt_tx->close();

            // ── system_logs ──
            log_system_action($conn, $admin_id, $admin_name, 'DELETE_ACCOUNTABLE',
                "[{$admin_name} / {$admin_role}] Deleted accountable record ID {$id} "
                . "('{$old_data['item_name']}', custodian: {$old_data['person_name']}, qty: {$old_data['assigned_quantity']}). "
                . "All data restored to inventory item ID {$old_data['inventory_item_id']}. "
                . "Ref: {$ref_del}."
            );

            $conn->commit();
            $_SESSION['success'] = "Accountable item deleted. All data ("
                . htmlspecialchars($old_data['assigned_quantity']) . " unit(s), serial numbers, PAR/ICS, property numbers) "
                . "have been returned to inventory stock.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    }
    header("Location: accountables.php");
    exit();
}

// ── 3. RETURN TO CGSO ────────────────────────────────────────────────────────
if (isset($_POST['return_to_cgso'])) {
    verify_csrf();

    if (!in_array($admin_role, ['ADMIN', 'MANAGER'], true)) {
        $_SESSION['error'] = "Access denied. Only Admin or Manager can perform Return to CGSO.";
        header("Location: accountables.php");
        exit();
    }

    $id              = filter_input(INPUT_POST, 'rtc_accountable_id', FILTER_VALIDATE_INT);
    $return_qty      = filter_input(INPUT_POST, 'rtc_quantity',       FILTER_VALIDATE_INT);
    $selected_serials_raw = trim($_POST['rtc_selected_serials'] ?? '');
    $return_condition     = trim($_POST['rtc_condition']        ?? 'Returned to CGSO');
    $return_remarks       = trim($_POST['rtc_remarks']          ?? '');

    if (!$id || !$return_qty || $return_qty < 1) {
        $_SESSION['error'] = "Invalid return data. Please provide a valid quantity.";
        header("Location: accountables.php");
        exit();
    }

    $selected_serials = [];
    if ($selected_serials_raw !== '') {
        $decoded = json_decode($selected_serials_raw, true);
        if (is_array($decoded)) {
            $selected_serials = array_values(array_filter(array_map('trim', $decoded)));
        }
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT ai.*, ii.item_name
            FROM accountable_items ai
            JOIN inventory_items ii ON ii.id = ai.inventory_item_id
            WHERE ai.id = ? AND ai.is_deleted = 0 FOR UPDATE");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rec = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$rec) throw new Exception("Accountable record not found.");
        if ($return_qty > $rec['assigned_quantity']) {
            throw new Exception("Cannot return {$return_qty} unit(s). Only {$rec['assigned_quantity']} assigned.");
        }

        $all_serials_raw = $rec['serial_number'] ?? '';
        $all_serials = array_values(array_filter(
            array_map('trim', preg_split('/\s*\/\s*/', $all_serials_raw))
        ));

        foreach ($selected_serials as $sel) {
            if (!in_array($sel, $all_serials, true)) {
                throw new Exception("Serial number '{$sel}' is not found in this record.");
            }
        }

        $remaining_serials = array_values(array_filter($all_serials, function ($s) use ($selected_serials) {
            return !in_array($s, $selected_serials, true);
        }));

        $remaining_serial_str = count($remaining_serials)
            ? implode(' / ', $remaining_serials) . ' /'
            : '';
        $returned_serial_str  = count($selected_serials)
            ? implode(' / ', $selected_serials) . ' /'
            : '';

        $emp_stmt = $conn->prepare("
            SELECT CONCAT_WS(' ', TRIM(FIRSTNAME),
                IF(MIDDLENAME IS NOT NULL AND MIDDLENAME <> '', TRIM(MIDDLENAME), NULL),
                TRIM(LASTNAME),
                IF(SUFFIX IS NOT NULL AND SUFFIX <> '', TRIM(SUFFIX), NULL)
            ) AS full_name, end_user_id_number
            FROM cao_employee WHERE ID = ?
        ");
        $emp_id = (int)$rec['employee_id'];
        $emp_stmt->bind_param("i", $emp_id);
        $emp_stmt->execute();
        $emp_data  = $emp_stmt->get_result()->fetch_assoc();
        $emp_stmt->close();
        $emp_fullname = $emp_data ? trim($emp_data['full_name']) : $rec['person_name'];
        $emp_id_num   = $emp_data ? $emp_data['end_user_id_number'] : null;

        $remaining_qty = $rec['assigned_quantity'] - $return_qty;
        $ref_no        = 'RTC-' . date('YmdHis') . '-' . mt_rand(1000, 9999);

        $stmt_ins = $conn->prepare("
            INSERT INTO returned_to_cgso
                (accountable_id, inventory_item_id, employee_id, employee_id_number,
                 employee_name, item_name,
                 are_mr_ics_num, property_number, returned_serial_numbers,
                 po_number, account_code, condition_status,
                 returned_quantity, remaining_quantity,
                 return_reference_no, remarks,
                 returned_by_id, returned_by_name, returned_by_role)
            VALUES (?,?,?,?, ?,?, ?,?,?, ?,?,?, ?,?, ?,?, ?,?,?)
        ");
        $stmt_ins->bind_param(
            "iiisssssssssiississ",
            $id, $rec['inventory_item_id'], $rec['employee_id'], $emp_id_num,
            $emp_fullname, $rec['item_name'],
            $rec['are_mr_ics_num'], $rec['property_number'], $returned_serial_str,
            $rec['po_number'], $rec['account_code'], $return_condition,
            $return_qty, $remaining_qty,
            $ref_no, $return_remarks,
            $admin_id, $admin_name, $admin_role
        );
        $stmt_ins->execute();
        $rtc_id = (int)$conn->insert_id;
        $stmt_ins->close();

        if ($remaining_qty === 0) {
            $stmt_upd = $conn->prepare("
                UPDATE accountable_items
                SET assigned_quantity    = 0,
                    serial_number        = '',
                    condition_status     = 'Returned to CGSO',
                    remarks              = CONCAT(COALESCE(remarks,''), ' [Returned to CGSO: ', ?, ' unit(s), Ref: ', ?, ']'),
                    is_deleted           = 1,
                    last_updated_by_id   = ?,
                    last_updated_by_name = ?,
                    last_updated_at      = NOW()
                WHERE id = ?
            ");
            $stmt_upd->bind_param("ssisi", $return_qty, $ref_no, $admin_id, $admin_name, $id);
        } else {
            $stmt_upd = $conn->prepare("
                UPDATE accountable_items
                SET assigned_quantity    = ?,
                    serial_number        = ?,
                    remarks              = CONCAT(COALESCE(remarks,''), ' [Partial return to CGSO: ', ?, ' unit(s), Ref: ', ?, ']'),
                    last_updated_by_id   = ?,
                    last_updated_by_name = ?,
                    last_updated_at      = NOW()
                WHERE id = ?
            ");
            $stmt_upd->bind_param("issisii", $remaining_qty, $remaining_serial_str, $return_qty, $ref_no, $admin_id, $admin_name, $id);
        }
        $stmt_upd->execute();
        $stmt_upd->close();

        if ($return_condition === 'Return to Property Custodian') {
            $stmt_stk = $conn->prepare("
                UPDATE inventory_items
                SET are_mr_ics_num  = ?,
                    serial_number   = ?,
                    property_number = ?,
                    quantity        = quantity + ?,
                    item_status     = 'Active',
                    date_updated    = NOW()
                WHERE id = ?
            ");
            $restore_are    = $rec['are_mr_ics_num']  ?? '';
            $restore_serial = $rec['serial_number']    ?? '';
            $restore_prop   = $rec['property_number']  ?? '';
            $stmt_stk->bind_param("sssii", $restore_are, $restore_serial, $restore_prop, $return_qty, $rec['inventory_item_id']);
        } else {
            $stmt_stk = $conn->prepare("UPDATE inventory_items SET quantity = quantity + ?, date_updated = NOW() WHERE id = ?");
            $stmt_stk->bind_param("ii", $return_qty, $rec['inventory_item_id']);
        }
        $stmt_stk->execute();
        $stmt_stk->close();

        $stmt_inv_snap = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
        $stmt_inv_snap->bind_param("i", $rec['inventory_item_id']);
        $stmt_inv_snap->execute();
        $new_inv_snap = $stmt_inv_snap->get_result()->fetch_assoc();
        $stmt_inv_snap->close();

        $trans_remarks_str = "Returned to CGSO from {$emp_fullname}. Serial(s): {$returned_serial_str}. By: {$admin_name} ({$admin_role}). Ref: {$ref_no}";
        $stmt_tx = $conn->prepare("
            INSERT INTO inventory_transactions
                (inventory_item_id, performed_by_id, performed_by_name,
                 transaction_type, quantity, reference_no, transaction_date, remarks)
            VALUES (?, ?, ?, 'IN', ?, ?, NOW(), ?)
        ");
        $stmt_tx->bind_param("iisiis", $rec['inventory_item_id'], $admin_id, $admin_name, $return_qty, $ref_no, $trans_remarks_str);
        $stmt_tx->execute();
        $stmt_tx->close();

        $old_snap = $rec;
        $stmt_new_snap = $conn->prepare("SELECT * FROM accountable_items WHERE id = ?");
        $stmt_new_snap->bind_param("i", $id);
        $stmt_new_snap->execute();
        $new_acct_snap = $stmt_new_snap->get_result()->fetch_assoc();
        $stmt_new_snap->close();

        log_audit($conn, $admin_id, $admin_name, 'accountable_items',
            $remaining_qty === 0 ? 'DELETE' : 'UPDATE', $id,
            json_encode($old_snap), json_encode($new_acct_snap));
        log_audit($conn, $admin_id, $admin_name, 'returned_to_cgso', 'CREATE', $rtc_id, null,
            json_encode(['accountable_id' => $id, 'inventory_item_id' => $rec['inventory_item_id'],
                'item_name' => $rec['item_name'], 'employee' => $emp_fullname,
                'returned_qty' => $return_qty, 'remaining_qty' => $remaining_qty,
                'returned_serials' => $returned_serial_str, 'ref_no' => $ref_no,
                'returned_by' => $admin_name, 'role' => $admin_role]));
        log_audit($conn, $admin_id, $admin_name, 'inventory_items', 'UPDATE',
            $rec['inventory_item_id'],
            json_encode(['quantity' => $rec['assigned_quantity']]),
            json_encode($new_inv_snap));

        $return_type_label = ($remaining_qty === 0) ? 'FULL' : 'PARTIAL';
        log_system_action($conn, $admin_id, $admin_name, 'RETURN_TO_CGSO',
            "[{$admin_name} / {$admin_role}] {$return_type_label} return to CGSO — "
            . "{$return_qty} unit(s) of '{$rec['item_name']}' from {$emp_fullname}. "
            . "Ref: {$ref_no}. Serials returned: {$returned_serial_str}");

        $conn->commit();
        $_SESSION['success'] = "{$return_qty} unit(s) of <strong>" . htmlspecialchars($rec['item_name'])
            . "</strong> returned to CGSO (Ref: {$ref_no}). Stock restored.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Return to CGSO failed: " . $e->getMessage();
    }
    header("Location: accountables.php");
    exit();
}

// ── 4. UPDATE ACCOUNTABLE ITEM ───────────────────────────────────────────────
if (isset($_POST['update_accountable'])) {
    verify_csrf();
    $id       = filter_input(INPUT_POST, 'edit_id',       FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'edit_quantity', FILTER_VALIDATE_INT);

    if ($quantity <= 0 || !$id) {
        $_SESSION['error'] = "Invalid quantity or ID.";
        header("Location: accountables.php");
        exit();
    }

    // ── BORROW LOCK: block edits while any active borrow exists ──────────────
    // "Active" = not yet returned AND not denied/cancelled.
    $stmt_bchk = $conn->prepare("
        SELECT COUNT(*) AS cnt,
               GROUP_CONCAT(reference_no ORDER BY borrow_id SEPARATOR ', ') AS refs
        FROM borrowed_items
        WHERE accountable_id = ?
          AND is_returned    = 0
          AND status NOT IN ('DENIED','CANCELLED','RETURNED')
    ");
    $stmt_bchk->bind_param("i", $id);
    $stmt_bchk->execute();
    $bchk = $stmt_bchk->get_result()->fetch_assoc();
    $stmt_bchk->close();

    if ((int)$bchk['cnt'] > 0) {
        $_SESSION['error'] = "Edit blocked: this item is currently borrowed "
            . "(active borrow ref: " . htmlspecialchars($bchk['refs']) . "). "
            . "It can only be edited after the borrow is returned or cancelled.";
        header("Location: accountables.php");
        exit();
    }
    // ── END BORROW LOCK ───────────────────────────────────────────────────────

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM accountable_items WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old_accountable_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$old_accountable_data) throw new Exception("Record not found.");

        $inventory_item_id = $old_accountable_data['inventory_item_id'];
        $diff = $quantity - $old_accountable_data['assigned_quantity'];

        if ($diff != 0) {
            $stmt_inv = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
            $stmt_inv->bind_param("i", $inventory_item_id);
            $stmt_inv->execute();
            $old_inv_data = $stmt_inv->get_result()->fetch_assoc();
            $stmt_inv->close();

            $type  = $diff > 0 ? 'OUT' : 'IN';
            $adj   = abs($diff);
            $query = ($type === 'OUT')
                ? "UPDATE inventory_items SET quantity = quantity - ?, date_updated = NOW() WHERE id = ?"
                : "UPDATE inventory_items SET quantity = quantity + ?, date_updated = NOW() WHERE id = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $adj, $inventory_item_id);
            $stmt->execute();
            $stmt->close();

            $ref = 'ADJUST-' . date('YmdHis');
            $stmt = $conn->prepare("
                INSERT INTO inventory_transactions
                    (inventory_item_id, performed_by_id, performed_by_name,
                     transaction_type, quantity, reference_no)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissis", $inventory_item_id, $admin_id, $admin_name, $type, $adj, $ref);
            $stmt->execute();
            $stmt->close();

            $stmt_inv_new = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
            $stmt_inv_new->bind_param("i", $inventory_item_id);
            $stmt_inv_new->execute();
            $new_inv_data = $stmt_inv_new->get_result()->fetch_assoc();
            $stmt_inv_new->close();

            log_audit($conn, $admin_id, $admin_name, 'inventory_items', 'UPDATE', $inventory_item_id,
                json_encode($old_inv_data), json_encode($new_inv_data));
        }

        $edit_remarks          = $_POST['edit_remarks']          ?? '';
        $edit_are_mr_ics_num   = $_POST['edit_are_mr_ics_num']   ?? '';
        $edit_property_number  = $_POST['edit_property_number']  ?? '';
        $edit_serial_number    = $_POST['edit_serial_number']    ?? '';
        $edit_po_number        = $_POST['edit_po_number']        ?? '';
        $edit_account_code     = $_POST['edit_account_code']     ?? '';
        $edit_old_account_code = $_POST['edit_old_account_code'] ?? '';
        $edit_condition_status = $_POST['edit_condition_status'] ?? 'Serviceable';

        // ── IDENTIFIER MOVE (not copy) ────────────────────────────────────────
        // Rule: a token must live in EXACTLY ONE place — either accountable_items
        // or inventory_items, never both.
        //
        // Tokens ADDED to accountable (new \ old) → remove from inventory_items.
        // Tokens REMOVED from accountable (old \ new) → merge back into inventory_items.
        //
        // This runs even when the quantity hasn't changed, because the admin may have
        // manually corrected the identifier fields.

        $fn_parse_tokens = function(string $raw): array {
            return array_values(array_filter(
                array_map('trim', explode('/', $raw))
            ));
        };

        // Fetch current inventory snapshot (may differ from the $old_inv_data fetched
        // above when the quantity didn't change, so we always fetch fresh here).
        $stmt_inv_snap = $conn->prepare("SELECT are_mr_ics_num, serial_number, property_number FROM inventory_items WHERE id = ? FOR UPDATE");
        $stmt_inv_snap->bind_param("i", $inventory_item_id);
        $stmt_inv_snap->execute();
        $inv_snap = $stmt_inv_snap->get_result()->fetch_assoc();
        $stmt_inv_snap->close();

        foreach (['are_mr_ics_num' => ['old_acct' => $old_accountable_data['are_mr_ics_num'] ?? '', 'new_acct' => $edit_are_mr_ics_num],
                  'property_number' => ['old_acct' => $old_accountable_data['property_number'] ?? '', 'new_acct' => $edit_property_number],
                  'serial_number'  => ['old_acct' => $old_accountable_data['serial_number']   ?? '', 'new_acct' => $edit_serial_number],
                 ] as $field => $vals) {

            $old_acct_tokens = $fn_parse_tokens($vals['old_acct']);
            $new_acct_tokens = $fn_parse_tokens($vals['new_acct']);
            $inv_tokens      = $fn_parse_tokens($inv_snap[$field] ?? '');

            // Tokens being ADDED to accountable → must not stay in inventory
            $added = array_values(array_filter($new_acct_tokens,
                fn($t) => !in_array($t, $old_acct_tokens, true)));

            // Tokens being REMOVED from accountable → return to inventory (avoid duplicates)
            $removed = array_values(array_filter($old_acct_tokens,
                fn($t) => !in_array($t, $new_acct_tokens, true)));

            // Build updated inventory token list
            $upd_inv = array_values(array_filter($inv_tokens,
                fn($t) => !in_array($t, $added, true)));  // remove tokens claimed by accountable
            foreach ($removed as $rt) {
                if (!in_array($rt, $upd_inv, true)) {
                    $upd_inv[] = $rt;                      // return orphaned tokens to inventory
                }
            }

            $inv_snap[$field] = implode(' / ', $upd_inv); // update our working snapshot
        }

        // Persist the reconciled inventory identifiers
        $stmt_inv_upd = $conn->prepare("
            UPDATE inventory_items
            SET are_mr_ics_num  = ?,
                serial_number   = ?,
                property_number = ?,
                date_updated    = NOW()
            WHERE id = ?
        ");
        $stmt_inv_upd->bind_param("sssi",
            $inv_snap['are_mr_ics_num'],
            $inv_snap['serial_number'],
            $inv_snap['property_number'],
            $inventory_item_id
        );
        $stmt_inv_upd->execute();
        $stmt_inv_upd->close();
        // ── END IDENTIFIER MOVE ───────────────────────────────────────────────

        $stmt = $conn->prepare("
            UPDATE accountable_items
            SET assigned_quantity    = ?,
                remarks              = ?,
                are_mr_ics_num       = ?,
                property_number      = ?,
                serial_number        = ?,
                po_number            = ?,
                account_code         = ?,
                old_account_code     = ?,
                condition_status     = ?,
                last_updated_by_id   = ?,
                last_updated_by_name = ?,
                last_updated_at      = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            "issssssssisi",
            $quantity, $edit_remarks, $edit_are_mr_ics_num, $edit_property_number,
            $edit_serial_number, $edit_po_number, $edit_account_code, $edit_old_account_code,
            $edit_condition_status, $admin_id, $admin_name, $id
        );
        $stmt->execute();
        $stmt->close();

        $stmt_new = $conn->prepare("SELECT * FROM accountable_items WHERE id = ?");
        $stmt_new->bind_param("i", $id);
        $stmt_new->execute();
        $new_accountable_data = $stmt_new->get_result()->fetch_assoc();
        $stmt_new->close();

        log_audit($conn, $admin_id, $admin_name, 'accountable_items', 'UPDATE', $id,
            json_encode($old_accountable_data), json_encode($new_accountable_data));
        log_system_action($conn, $admin_id, $admin_name, 'UPDATE_ACCOUNTABLE',
            "[$admin_name] Updated accountable ID {$id} (custodian: {$old_accountable_data['person_name']})");

        $conn->commit();
        $_SESSION['success'] = "Accountable item updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: accountables.php");
    exit();
}

// ── 5. TRANSFER ITEM ─────────────────────────────────────────────────────────
if (isset($_POST['transfer_item'])) {
    verify_csrf();

    $id              = filter_input(INPUT_POST, 'transfer_id',       FILTER_VALIDATE_INT);
    $new_employee_id = filter_input(INPUT_POST, 'new_employee_id',   FILTER_VALIDATE_INT);
    $transfer_qty    = filter_input(INPUT_POST, 'transfer_quantity', FILTER_VALIDATE_INT);

    // Selected identifiers for the transferred units
    $transfer_serial_raw   = trim($_POST['transfer_selected_serial']   ?? '');
    $transfer_prop_raw     = trim($_POST['transfer_selected_property'] ?? '');
    $transfer_are_raw      = trim($_POST['transfer_selected_are']      ?? '');
    $transfer_remarks_val  = trim($_POST['transfer_remarks']           ?? '');

    if (!$id || !$new_employee_id || !$transfer_qty || $transfer_qty < 1) {
        $_SESSION['error'] = "Invalid transfer data. Please check all fields.";
        header("Location: accountables.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt_old = $conn->prepare("SELECT ai.*, ii.item_name FROM accountable_items ai JOIN inventory_items ii ON ii.id = ai.inventory_item_id WHERE ai.id = ? AND ai.is_deleted = 0 FOR UPDATE");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();

        if (!$old_data) throw new Exception("Record not found.");
        if ($transfer_qty > $old_data['assigned_quantity']) {
            throw new Exception("Insufficient quantity! Only {$old_data['assigned_quantity']} unit(s) assigned.");
        }

        // Validate that checked counts match transfer_qty
        $fn_count_tokens = function(string $raw): int {
            if (trim($raw) === '') return 0;
            return count(array_filter(array_map('trim', explode('/', $raw))));
        };

        $all_tokens = array_filter(array_map('trim', explode('/', $old_data['serial_number'] ?? '')));
        $all_prop   = array_filter(array_map('trim', explode('/', $old_data['property_number'] ?? '')));
        $all_are    = array_filter(array_map('trim', explode('/', $old_data['are_mr_ics_num'] ?? '')));

        $sel_serial_tokens = array_values(array_filter(array_map('trim', explode('/', $transfer_serial_raw))));
        $sel_prop_tokens   = array_values(array_filter(array_map('trim', explode('/', $transfer_prop_raw))));
        $sel_are_tokens    = array_values(array_filter(array_map('trim', explode('/', $transfer_are_raw))));

        // If the source has tokens, checked count must match transfer_qty
        if (count($all_tokens) > 0 && count($sel_serial_tokens) !== $transfer_qty) {
            throw new Exception("Number of checked serial numbers (" . count($sel_serial_tokens) . ") must equal transfer quantity ({$transfer_qty}).");
        }
        if (count($all_prop) > 0 && count($sel_prop_tokens) !== $transfer_qty) {
            throw new Exception("Number of checked property numbers (" . count($sel_prop_tokens) . ") must equal transfer quantity ({$transfer_qty}).");
        }
        if (count($all_are) > 0 && count($sel_are_tokens) !== $transfer_qty) {
            throw new Exception("Number of checked PAR/ICS/ARE numbers (" . count($sel_are_tokens) . ") must equal transfer quantity ({$transfer_qty}).");
        }

        $stmt_emp = $conn->prepare("
            SELECT CONCAT_WS(' ', FIRSTNAME, MIDDLENAME, LASTNAME, SUFFIX) AS full_name
            FROM cao_employee WHERE ID = ?
        ");
        $stmt_emp->bind_param("i", $new_employee_id);
        $stmt_emp->execute();
        $emp_res = $stmt_emp->get_result()->fetch_assoc();
        $stmt_emp->close();
        if (!$emp_res) throw new Exception("New employee not found.");
        $new_person_name = trim($emp_res['full_name']);

        $remaining_qty = $old_data['assigned_quantity'] - $transfer_qty;
        $new_record_id = null;

        // Build remaining tokens (those NOT selected for transfer)
        $remaining_serial_str = '';
        $remaining_prop_str   = '';
        $remaining_are_str    = '';

        if ($remaining_qty > 0) {
            $rem_serial = array_values(array_filter($all_tokens, function($t) use ($sel_serial_tokens) { return !in_array($t, $sel_serial_tokens, true); }));
            $rem_prop   = array_values(array_filter($all_prop,   function($t) use ($sel_prop_tokens)   { return !in_array($t, $sel_prop_tokens,   true); }));
            $rem_are    = array_values(array_filter($all_are,    function($t) use ($sel_are_tokens)    { return !in_array($t, $sel_are_tokens,    true); }));
            $remaining_serial_str = implode(' / ', $rem_serial);
            $remaining_prop_str   = implode(' / ', $rem_prop);
            $remaining_are_str    = implode(' / ', $rem_are);
        }

        // Build transfer identifiers strings
        $xfer_serial_str = $transfer_serial_raw !== '' ? $transfer_serial_raw : $old_data['serial_number'];
        $xfer_prop_str   = $transfer_prop_raw   !== '' ? $transfer_prop_raw   : $old_data['property_number'];
        $xfer_are_str    = $transfer_are_raw    !== '' ? $transfer_are_raw    : $old_data['are_mr_ics_num'];

        $ref_no_xfer = 'XFER-' . date('YmdHis') . '-' . mt_rand(1000, 9999);

        if ($remaining_qty === 0) {
            // Full transfer — update the existing record
            $stmt = $conn->prepare("
                UPDATE accountable_items
                SET employee_id          = ?,
                    person_name          = ?,
                    are_mr_ics_num       = ?,
                    property_number      = ?,
                    serial_number        = ?,
                    remarks              = CONCAT(COALESCE(remarks,''), ' [Transferred from: ', ?, ' by: ', ?, ' Ref: ', ?, ']'),
                    last_updated_by_id   = ?,
                    last_updated_by_name = ?,
                    last_updated_at      = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("isssssssisi",
                $new_employee_id, $new_person_name,
                $xfer_are_str, $xfer_prop_str, $xfer_serial_str,
                $old_data['person_name'], $admin_name, $ref_no_xfer,
                $admin_id, $admin_name, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Partial transfer — reduce original record
            $stmt_reduce = $conn->prepare("
                UPDATE accountable_items
                SET assigned_quantity    = ?,
                    are_mr_ics_num       = ?,
                    property_number      = ?,
                    serial_number        = ?,
                    remarks              = CONCAT(COALESCE(remarks,''), ' [Partial transfer of ', ?, ' unit(s) to: ', ?, ' by: ', ?, ' Ref: ', ?, ']'),
                    last_updated_by_id   = ?,
                    last_updated_by_name = ?,
                    last_updated_at      = NOW()
                WHERE id = ?
            ");
            $stmt_reduce->bind_param("isssisssisi",
                $remaining_qty,
                $remaining_are_str, $remaining_prop_str, $remaining_serial_str,
                $transfer_qty, $new_person_name, $admin_name, $ref_no_xfer,
                $admin_id, $admin_name, $id);
            $stmt_reduce->execute();
            $stmt_reduce->close();

            // Create new accountable record for the new custodian
            $split_remarks = "Partial transfer of {$transfer_qty} unit(s) from: {$old_data['person_name']}. By: {$admin_name}. Ref: {$ref_no_xfer}."
                . ($transfer_remarks_val !== '' ? " Remarks: {$transfer_remarks_val}" : '');
            $stmt_new_rec = $conn->prepare("
                INSERT INTO accountable_items
                    (inventory_item_id, employee_id, person_name, assigned_quantity,
                     are_mr_ics_num, property_number, serial_number, po_number,
                     account_code, old_account_code, condition_status, remarks,
                     created_by_id, created_by_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_new_rec->bind_param("iisissssssssss",
                $old_data['inventory_item_id'], $new_employee_id, $new_person_name, $transfer_qty,
                $xfer_are_str, $xfer_prop_str, $xfer_serial_str,
                $old_data['po_number'], $old_data['account_code'], $old_data['old_account_code'],
                $old_data['condition_status'], $split_remarks, $admin_id, $admin_name);
            $stmt_new_rec->execute();
            $new_record_id = $conn->insert_id;
            $stmt_new_rec->close();
        }

        // Fetch updated records for audit snapshots
        $stmt_after = $conn->prepare("SELECT * FROM accountable_items WHERE id = ?");
        $stmt_after->bind_param("i", $id);
        $stmt_after->execute();
        $new_data = $stmt_after->get_result()->fetch_assoc();
        $stmt_after->close();

        // ── audit_logs: accountable UPDATE (source record) ──
        log_audit($conn, $admin_id, $admin_name, 'accountable_items', 'UPDATE', $id,
            json_encode($old_data), json_encode($new_data));

        // ── audit_logs: new accountable CREATE (partial transfer only) ──
        if ($new_record_id) {
            $stmt_new_snap = $conn->prepare("SELECT * FROM accountable_items WHERE id = ?");
            $stmt_new_snap->bind_param("i", $new_record_id);
            $stmt_new_snap->execute();
            $new_acct_snap = $stmt_new_snap->get_result()->fetch_assoc();
            $stmt_new_snap->close();
            log_audit($conn, $admin_id, $admin_name, 'accountable_items', 'CREATE',
                $new_record_id, null, json_encode($new_acct_snap));
        }

        // ── inventory_transactions: ADJUSTMENT (transfer does not change total stock) ──
        $transfer_type = ($remaining_qty === 0) ? 'FULL' : 'PARTIAL';
        $trans_rmk_xfer = "{$transfer_type} transfer of {$transfer_qty} unit(s) of '{$old_data['item_name']}' "
            . "from '{$old_data['person_name']}' to '{$new_person_name}'. "
            . "Serial(s): {$xfer_serial_str}. PAR/ICS: {$xfer_are_str}. Prop#: {$xfer_prop_str}. "
            . "By: {$admin_name} ({$admin_role}). Ref: {$ref_no_xfer}."
            . ($transfer_remarks_val !== '' ? " Remarks: {$transfer_remarks_val}" : '');
        $stmt_tx = $conn->prepare("
            INSERT INTO inventory_transactions
                (inventory_item_id, performed_by_id, performed_by_name,
                 transaction_type, quantity, reference_no, transaction_date, remarks)
            VALUES (?, ?, ?, 'ADJUSTMENT', ?, ?, NOW(), ?)
        ");
        $stmt_tx->bind_param("iisiis",
            $old_data['inventory_item_id'], $admin_id, $admin_name,
            $transfer_qty, $ref_no_xfer, $trans_rmk_xfer);
        $stmt_tx->execute();
        $stmt_tx->close();

        // ── system_logs ──
        log_system_action($conn, $admin_id, $admin_name, 'TRANSFER_ITEM',
            "[{$admin_name} / {$admin_role}] {$transfer_type} transfer of {$transfer_qty} unit(s) "
            . "of '{$old_data['item_name']}' from accountable ID {$id} ({$old_data['person_name']}) "
            . "to '{$new_person_name}'. Serial(s): {$xfer_serial_str}. PAR/ICS: {$xfer_are_str}. "
            . "Prop#: {$xfer_prop_str}. Ref: {$ref_no_xfer}."
            . ($new_record_id ? " New accountable record ID: {$new_record_id}." : '')
        );

        $conn->commit();
        $type_label = ($remaining_qty === 0) ? "fully transferred" : "partially transferred ({$transfer_qty} unit(s))";
        $_SESSION['success'] = "Item {$type_label} to " . htmlspecialchars($new_person_name)
            . ". Ref: {$ref_no_xfer}. Recorded by: {$admin_name}.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: accountables.php");
    exit();
}

// ========================================
// FETCH ACCOUNTABLE ITEMS (SERVER-SIDE PAGINATION & SEARCH)
// ========================================
$perPage = 10;
$page    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['search'] ?? '');

$whereClause = "WHERE ai.is_deleted = 0";
if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $whereClause .= " AND (ii.item_name LIKE '%$safeSearch%'
                      OR ai.person_name LIKE '%$safeSearch%'
                      OR ai.remarks LIKE '%$safeSearch%'
                      OR ai.property_number LIKE '%$safeSearch%'
                      OR ai.are_mr_ics_num LIKE '%$safeSearch%'
                      OR ai.serial_number LIKE '%$safeSearch%')";
}

$countQuery = "SELECT COUNT(*) AS cnt
               FROM accountable_items ai
               INNER JOIN inventory_items ii ON ii.id = ai.inventory_item_id
               $whereClause";
$countRes  = $conn->query($countQuery);
$total     = $countRes ? intval($countRes->fetch_assoc()['cnt'] ?? 0) : 0;
$totalPages = max(1, (int)ceil($total / $perPage));

// ── Main SELECT: includes all fields needed by action modals ─────────────────
$query = "
    SELECT
        ai.id,
        ai.inventory_item_id,
        ai.employee_id,
        ii.item_name,
        ii.particulars,
        ii.value_amount,
        ii.total_amount,
        ii.date_delivered,
        ii.quantity            AS stock_remaining,
        ai.person_name,
        ai.assigned_quantity,
        ai.remarks,
        ai.date_assigned,
        ai.condition_status,
        COALESCE(ai.are_mr_ics_num,   '') AS are_mr_ics_num,
        COALESCE(ai.property_number,  '') AS property_number,
        COALESCE(ai.serial_number,    '') AS serial_number,
        COALESCE(ai.po_number,        '') AS po_number,
        COALESCE(ai.account_code,     '') AS account_code,
        COALESCE(ai.old_account_code, '') AS old_account_code,
        ai.created_by_name,
        ai.last_updated_by_name,
        ai.last_updated_at,
        it.reference_no,
        /* ── Inventory-side identifiers for conflict detection ── */
        COALESCE(ii.are_mr_ics_num,  '') AS inv_are_mr_ics_num,
        COALESCE(ii.serial_number,   '') AS inv_serial_number,
        COALESCE(ii.property_number, '') AS inv_property_number,
        /* ── Active borrow count: > 0 means editing is locked ── */
        (SELECT COUNT(*)
         FROM borrowed_items bi
         WHERE bi.accountable_id = ai.id
           AND bi.is_returned    = 0
           AND bi.status NOT IN ('DENIED','CANCELLED','RETURNED')
        ) AS active_borrows,
        /* ── First active borrow reference (for the lock message) ── */
        (SELECT bi2.reference_no
         FROM borrowed_items bi2
         WHERE bi2.accountable_id = ai.id
           AND bi2.is_returned    = 0
           AND bi2.status NOT IN ('DENIED','CANCELLED','RETURNED')
         ORDER BY bi2.borrow_id ASC
         LIMIT 1
        ) AS active_borrow_ref,
        (SELECT bi3.to_person
         FROM borrowed_items bi3
         WHERE bi3.accountable_id = ai.id
           AND bi3.is_returned    = 0
           AND bi3.status NOT IN ('DENIED','CANCELLED','RETURNED')
         ORDER BY bi3.borrow_id ASC
         LIMIT 1
        ) AS active_borrow_borrower
    FROM accountable_items ai
    INNER JOIN inventory_items ii ON ii.id = ai.inventory_item_id
    LEFT JOIN (
        SELECT inventory_item_id, reference_no
        FROM inventory_transactions t1
        WHERE transaction_type = 'OUT'
          AND transaction_id = (
            SELECT MAX(transaction_id)
            FROM inventory_transactions t2
            WHERE t2.inventory_item_id = t1.inventory_item_id
              AND t2.transaction_type = 'OUT'
          )
    ) it ON it.inventory_item_id = ai.inventory_item_id
    $whereClause
    ORDER BY ai.date_assigned DESC
    LIMIT $offset, $perPage
";
$accountables = $conn->query($query);

// ── Employee list (used in Assign form + Transfer modal) ─────────────────────
$emp = $conn->query("SELECT ID, CONCAT_WS(' ', FIRSTNAME, MIDDLENAME, LASTNAME, SUFFIX) AS full_name
                     FROM cao_employee WHERE DELETED = 0 ORDER BY LASTNAME, FIRSTNAME");

// ── Inventory items with available stock ─────────────────────────────────────
$items = $conn->query("SELECT id, item_name, particulars, are_mr_ics_num, serial_number,
                               property_number, quantity, value_amount, total_amount, date_delivered
                        FROM inventory_items
                        WHERE quantity > 0 AND item_status = 'Active'
                        ORDER BY item_name");
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="/inventory_cao_v2/assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Accountables</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        .item-info-panel { background: #f0f4ff; border: 1px solid #c7d2fe; border-radius: 8px; }
        .item-info-panel .info-label { font-size: 0.72rem; text-transform: uppercase; color: #6c757d; font-weight: 600; }
        .item-info-panel .info-value { font-size: 0.9rem; font-weight: 500; word-break: break-word; }

        /* ── Checklist (PAR / ICS / Property / Serial) ───────────────── */
        .serial-checklist-wrap {
            background: #f8f9ff;
            border: 1.5px solid #d5d9e2;
            border-radius: .45rem;
            padding: .5rem .75rem;
            min-height: 2.6rem;
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .55rem;
            align-items: center;
        }
        .serial-checklist-wrap .serial-placeholder,
        .serial-checklist-wrap .serial-no-data {
            font-size: .82rem;
            color: #a1b0be;
            font-style: italic;
        }
        .serial-check-item {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: #fff;
            border: 1.5px solid #c5c7ff;
            border-radius: .35rem;
            padding: .28rem .6rem;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
            font-size: .83rem;
            color: #566a7f;
            user-select: none;
        }
        .serial-check-item:hover {
            background: #ededff;
            border-color: #696cff;
        }
        .serial-check-item input[type="checkbox"] {
            accent-color: #696cff;
            width: 1em;
            height: 1em;
            cursor: pointer;
            flex-shrink: 0;
        }
        .serial-check-item.sn-checked {
            background: #ededff;
            border-color: #696cff;
            color: #696cff;
            font-weight: 600;
        }
        .serial-checklist-wrap.sn-valid {
            border-color: #28c76f;
            background: rgba(40, 199, 111, .04);
        }
        .serial-checklist-wrap.sn-invalid {
            border-color: #ea5455;
            background: rgba(234, 84, 85, .04);
        }
        .sn-match-hint {
            font-size: .76rem;
            margin-top: .3rem;
            font-weight: 600;
            display: none;
        }
        .sn-match-hint.ok    { color: #28c76f; }
        .sn-match-hint.warn  { color: #ff9f43; }
        .sn-match-hint.error { color: #ea5455; }

        /* Transfer modal — per-row mismatch indicator */
        .xfer-mismatch-badge {
            font-size: .70rem;
            padding: .18em .45em;
            border-radius: .3rem;
            white-space: nowrap;
            cursor: help;
            animation: xfer-pulse 1.6s ease-in-out infinite;
        }
        @keyframes xfer-pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .55; }
        }
        .serial-check-item.xfer-mismatch-item {
            border: 1px solid #ff9f43 !important;
            background: rgba(255, 159, 67, .08) !important;
            border-radius: .35rem;
        }
        .serial-check-item.xfer-mismatch-item input[type="text"] {
            border-color: #ff9f43 !important;
            box-shadow: 0 0 0 .15rem rgba(255,159,67,.25);
        }
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'sidebar.php'; ?>
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="content-wrapper">
            <div class="container-xxl grow container-p-y">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0"><i class="bx bx-list-check me-2"></i>Accountables Management</h4>
                </div>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════
                     ASSIGN NEW ITEM FORM
                     ══════════════════════════════════════════════════════ -->
                <form id="assignForm" method="POST" class="card p-4 mb-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <h5 class="mb-3"><i class="bx bx-plus-circle me-1"></i>Assign New Item</h5>

                    <div class="row">
                        <!-- Inventory Item Select -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Inventory Item <span class="text-danger">*</span></label>
                            <select name="inventory_item_id" class="form-select" required id="itemSelect">
                                <option value="">-- Select Item --</option>
                                <?php while ($row = $items->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"
                                        data-name="<?= htmlspecialchars($row['item_name'], ENT_QUOTES) ?>"
                                        data-particulars="<?= htmlspecialchars($row['particulars'], ENT_QUOTES) ?>"
                                        data-qty="<?= (int)$row['quantity'] ?>"
                                        data-ics="<?= htmlspecialchars($row['are_mr_ics_num'], ENT_QUOTES) ?>"
                                        data-serial="<?= htmlspecialchars($row['serial_number'], ENT_QUOTES) ?>"
                                        data-property="<?= htmlspecialchars($row['property_number'], ENT_QUOTES) ?>"
                                        data-value="<?= number_format($row['value_amount'], 2) ?>"
                                        data-total="<?= number_format($row['total_amount'], 2) ?>"
                                        data-delivered="<?= htmlspecialchars($row['date_delivered'], ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($row['item_name']) ?>
                                        — <?= htmlspecialchars($row['particulars']) ?>
                                        (Available: <?= (int)$row['quantity'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Employee Select -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Assign To (Employee) <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required id="assignEmpSelect">
                                <option value="">-- Select Employee --</option>
                                <?php
                                $emp->data_seek(0);
                                while ($e = $emp->fetch_assoc()):
                                ?>
                                    <option value="<?= $e['ID'] ?>"><?= htmlspecialchars($e['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <!-- ── Item Info Panel (shown after selecting an item) ── -->
                    <div id="inventoryInfoPanel" class="item-info-panel p-3 mb-3 d-none">
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <div class="info-label">Particulars</div>
                                <div class="info-value" id="info_particulars">—</div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="info-label">Available Qty</div>
                                <div class="info-value text-success fw-bold" id="info_qty">—</div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="info-label">Unit Value</div>
                                <div class="info-value" id="info_value">—</div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="info-label">Total Amount</div>
                                <div class="info-value" id="info_total">—</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="info-label">Date Delivered</div>
                                <div class="info-value" id="info_delivered">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Synced Fields (pre-filled from inventory_items) ── -->
                    <div id="inventoryDetails" class="d-none">
                        <div class="row bg-light border rounded p-3 mb-3">
                            <div class="col-12 mb-2">
                                <small class="text-muted fw-semibold">
                                    <i class="bx bx-sync me-1"></i>
                                    The fields below are pre-filled from the selected inventory record.
                                    You may edit them before assigning.
                                </small>
                            </div>
                            <!-- PAR / ICS / ARE — one checkbox per /-delimited token -->
                            <div class="col-12 mb-2">
                                <label class="form-label">
                                    PAR / ICS / ARE Number
                                    <span id="assign_are_req_note" class="text-muted fw-normal" style="font-size:.72rem;display:none;">
                                        — <strong id="assign_are_checked_count">0</strong> checked
                                        (must equal quantity)
                                    </span>
                                </label>
                                <!-- Hidden input carries the joined checked values to PHP -->
                                <input type="hidden" name="are_mr_ics_num" id="are_mr_ics_num_hidden">
                                <div id="assign_are_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load its PAR / ICS / ARE numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="assign_are_match_hint"></span>
                            </div>

                            <!-- Property Number — one checkbox per /-delimited token -->
                            <div class="col-12 mb-2">
                                <label class="form-label">
                                    Property Number
                                    <span id="assign_prop_req_note" class="text-muted fw-normal" style="font-size:.72rem;display:none;">
                                        — <strong id="assign_prop_checked_count">0</strong> checked
                                        (must equal quantity)
                                    </span>
                                </label>
                                <input type="hidden" name="property_number" id="property_number_hidden">
                                <div id="assign_prop_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load its property numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="assign_prop_match_hint"></span>
                            </div>

                            <!-- Serial Number(s) — one checkbox per /-delimited token -->
                            <div class="col-12 mb-2">
                                <label class="form-label">
                                    Serial Number(s)
                                    <span id="assign_sn_req_note" class="text-muted fw-normal" style="font-size:.72rem;display:none;">
                                        — select exactly <strong id="assign_sn_req_count">0</strong> to match quantity
                                    </span>
                                </label>
                                <input type="hidden" name="serial_number" id="serial_number_hidden">
                                <div id="assign_serial_checklist" class="serial-checklist-wrap">
                                    <span class="serial-placeholder">Select an inventory item to load its serial numbers.</span>
                                </div>
                                <span class="sn-match-hint" id="assign_sn_match_hint"></span>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">PO Number</label>
                                <input type="text" name="po_number" id="po_number" class="form-control" placeholder="Purchase Order #">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Account Code</label>
                                <input type="text" name="account_code" id="account_code" class="form-control" placeholder="e.g. 10605020-00">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Old Account Code</label>
                                <input type="text" name="old_account_code" id="old_account_code" class="form-control" placeholder="Previous account code">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold">Quantity to Assign <span class="text-danger">*</span></label>
                                <input type="number" name="assign_quantity" id="assign_quantity" class="form-control" min="1" required placeholder="Enter qty">
                                <div class="invalid-feedback" id="assign_qty_error"></div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-semibold">Condition / Status</label>
                                <select name="condition_status" class="form-select">
                                    <option value="Serviceable">Serviceable</option>
                                    <option value="For Repair">For Repair</option>
                                    <option value="Unserviceable">Unserviceable</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Optional notes">
                            </div>
                        </div>

                        <div>
                            <button type="submit" name="assign" class="btn btn-primary" id="assignSubmitBtn">
                                <i class="bx bx-check me-1"></i> Assign Item
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ══════════════════════════════════════════════════════
                     ASSIGNED ITEMS TABLE
                     ══════════════════════════════════════════════════════ -->
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0"><i class="bx bx-table me-1"></i>Assigned Items
                            <span class="badge bg-primary ms-1"><?= $total ?></span>
                        </h5>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control"
                                placeholder="Search item, person, PAR, serial..." style="min-width:260px;"
                                value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bx bx-search"></i>
                            </button>
                            <?php if ($search): ?>
                                <a href="accountables.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:24%">Item Details</th>
                                    <th>Employee / Custodian</th>
                                    <th class="text-center">Qty</th>
                                    <th>Status</th>
                                    <th>Identifiers</th>
                                    <th style="width:130px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($accountables && $accountables->num_rows > 0): ?>
                                    <?php while ($row = $accountables->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['item_name']) ?></strong>
                                                <?php if (!empty($row['particulars'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($row['particulars']) ?></small>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    Assigned: <?= date('M d, Y', strtotime($row['date_assigned'])) ?>
                                                </small>
                                                <?php if (!empty($row['last_updated_at'])): ?>
                                                    <br><small class="text-info">
                                                        <i class="bx bx-edit-alt"></i>
                                                        Updated: <?= date('M d, Y', strtotime($row['last_updated_at'])) ?>
                                                        by <?= htmlspecialchars($row['last_updated_by_name'] ?? '—') ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['person_name']) ?></strong>
                                                <?php if (!empty($row['created_by_name'])): ?>
                                                    <br><small class="text-muted">Assigned by: <?= htmlspecialchars($row['created_by_name']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary fs-6"><?= (int)$row['assigned_quantity'] ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'Serviceable'   => 'success',
                                                    'For Repair'    => 'warning',
                                                    'Unserviceable' => 'danger',
                                                ];
                                                $badge = $statusMap[$row['condition_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $badge ?>">
                                                    <?= htmlspecialchars($row['condition_status'] ?? 'Serviceable') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <b>PAR/ICS:</b> <?= htmlspecialchars($row['are_mr_ics_num']) ?: '<span class="text-muted">—</span>' ?><br>
                                                    <b>Prop #:</b> <?= htmlspecialchars($row['property_number']) ?: '<span class="text-muted">—</span>' ?><br>
                                                    <b>Serial:</b> <?= htmlspecialchars($row['serial_number']) ?: '<span class="text-muted">—</span>' ?>
                                                    <?php if (!empty($row['po_number'])): ?>
                                                        <br><b>PO:</b> <?= htmlspecialchars($row['po_number']) ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['account_code'])): ?>
                                                        <br><b>Acc:</b> <?= htmlspecialchars($row['account_code']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                // Build JSON for Edit Modal (all fields needed)
                                           
                                                /* Safe JSON for the edit modal — no JS string literal needed.
                                                   JSON_HEX_* flags encode ", ', <, >, & as \uXXXX so
                                                   multiline serial numbers / special chars cannot break
                                                   the HTML attribute or be misread by JSON.parse. */
                                                $rowJson = htmlspecialchars(
                                                    json_encode($row,
                                                        JSON_HEX_TAG | JSON_HEX_APOS |
                                                        JSON_HEX_QUOT | JSON_HEX_AMP),
                                                    ENT_QUOTES, 'UTF-8'
                                                );
                                                ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-secondary dropdown-toggle"
                                                        type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <!-- Edit -->
                                                        <li>
                                                            <a class="dropdown-item" href="javascript:void(0);"
                                                                data-acct-row="<?= $rowJson ?>"
                                                                onclick="openEditModal(this)">
                                                                <i class="bx bx-edit-alt me-1"></i> Edit
                                                            </a>
                                                        </li>

                                                        <!-- Transfer -->
                                                        <li>
                                                            <a class="dropdown-item" href="javascript:void(0);"
                                                                data-id="<?= (int)$row['id'] ?>"
                                                                data-item-name="<?= htmlspecialchars($row['item_name']) ?>"
                                                                data-person-name="<?= htmlspecialchars($row['person_name']) ?>"
                                                                data-max-qty="<?= (int)$row['assigned_quantity'] ?>"
                                                                data-serial="<?= htmlspecialchars($row['serial_number']   ?? '') ?>"
                                                                data-prop="<?=   htmlspecialchars($row['property_number'] ?? '') ?>"
                                                                data-are="<?=    htmlspecialchars($row['are_mr_ics_num']  ?? '') ?>"
                                                                onclick="openTransferModal(this)">
                                                                <i class="bx bx-transfer me-1"></i> Transfer
                                                            </a>
                                                        </li>

                                                        <!-- Print PAR/ICS -->
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="print_form.php?id=<?= (int)$row['id'] ?>"
                                                                target="_blank">
                                                                <i class="bx bx-printer me-1"></i> Print PAR/ICS
                                                            </a>
                                                        </li>

                                                        <li><hr class="dropdown-divider"></li>

                                                        <!-- Return to CGSO (Admin / Manager only) -->
                                                        <?php if (in_array($admin_role, ['ADMIN', 'MANAGER'], true)): ?>
                                                        <li>
                                                            <a class="dropdown-item text-warning" href="javascript:void(0);"
                                                                data-id="<?= (int)$row['id'] ?>"
                                                                data-item-name="<?= htmlspecialchars($row['item_name']) ?>"
                                                                data-person-name="<?= htmlspecialchars($row['person_name']) ?>"
                                                                data-max-qty="<?= (int)$row['assigned_quantity'] ?>"
                                                                data-serial="<?= htmlspecialchars($row['serial_number'] ?? '') ?>"
                                                                onclick="openReturnToCgsoModal(this)">
                                                                <i class="bx bx-undo me-1"></i> Return to CGSO
                                                            </a>
                                                        </li>
                                                        <?php else: ?>
                                                        <li>
                                                            <span class="dropdown-item text-muted" style="cursor:default;" title="Admin or Manager role required">
                                                                <i class="bx bx-lock me-1"></i> Return to CGSO
                                                            </span>
                                                        </li>
                                                        <?php endif; ?>

                                                        <!-- Delete (Admin / Manager only) -->
                                                        <?php if (in_array($admin_role, ['ADMIN', 'MANAGER'], true)): ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="javascript:void(0);"
                                                                data-id="<?= (int)$row['id'] ?>"
                                                                data-item-name="<?= htmlspecialchars($row['item_name']) ?>"
                                                                data-person-name="<?= htmlspecialchars($row['person_name']) ?>"
                                                                data-qty="<?= (int)$row['assigned_quantity'] ?>"
                                                                onclick="openDeleteModal(this)">
                                                                <i class="bx bx-trash me-1"></i> Delete
                                                            </a>
                                                        </li>
                                                        <?php else: ?>
                                                        <li>
                                                            <span class="dropdown-item text-muted" style="cursor:default;" title="Admin or Manager role required">
                                                                <i class="bx bx-lock me-1"></i> Delete
                                                            </span>
                                                        </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bx bx-inbox bx-lg d-block mb-2"></i>
                                            No records found<?= $search ? " for \"" . htmlspecialchars($search) . "\"" : '' ?>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <div class="small text-muted">
                            Showing page <?= $page ?> of <?= $totalPages ?>
                            (<?= $total ?> total record<?= $total !== 1 ? 's' : '' ?>)
                        </div>
                        <ul class="pagination mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="bx bx-chevron-left"></i> Prev
                                </a>
                            </li>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                    Next <i class="bx bx-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

            </div><!-- /container-xxl -->
            <?php include 'footer.php'; ?>
        </div><!-- /content-wrapper -->
    </div><!-- /layout-container -->
</div><!-- /layout-wrapper -->


<!-- ══════════════════════════════════════════════════════════════
     EDIT MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">
                    <i class="bx bx-edit-alt me-1"></i> Edit Accountable Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" id="edit_id">

                <!-- Read-only info banner -->
                <div class="alert alert-info py-2 mb-3">
                    <strong>Item:</strong> <span id="edit_item_name_display">—</span>
                    &nbsp;&mdash;&nbsp;
                    <strong>Custodian:</strong> <span id="edit_person_display">—</span>
                </div>

                <!-- ══ BORROW LOCK PANEL (hidden by default; shown when item is actively borrowed) ══ -->
                <div id="edit_borrow_lock_panel" class="d-none">
                    <div class="alert alert-danger mb-3" role="alert">
                        <h6 class="alert-heading mb-1">
                            <i class="bx bx-lock me-1"></i>
                            <strong>Editing Locked — Item is Currently Borrowed</strong>
                        </h6>
                        <p class="mb-1">
                            This accountable item has an <strong>active borrow record</strong>.
                            You cannot edit it until the borrow is returned, denied, or cancelled.
                        </p>
                        <hr class="my-2">
                        <small>
                            <strong>Active Borrow:</strong>
                            <span id="edit_lock_borrow_ref" class="font-monospace"></span><br>
                            <strong>Borrowed by:</strong>
                            <span id="edit_lock_borrow_borrower"></span><br>
                            <strong>Active Count:</strong>
                            <span id="edit_lock_borrow_count"></span> borrow record(s) blocking edit
                        </small>
                    </div>
                    <!-- Blurred/disabled fields preview so admin can still see the data -->
                    <div id="edit_locked_preview" class="p-3 rounded border bg-light" style="opacity:.55;pointer-events:none;">
                        <div class="row">
                            <div class="col-6 mb-2"><label class="form-label small fw-semibold">PAR / ICS / ARE #</label><div class="form-control form-control-sm" id="lp_are"></div></div>
                            <div class="col-6 mb-2"><label class="form-label small fw-semibold">Property #</label><div class="form-control form-control-sm" id="lp_prop"></div></div>
                            <div class="col-12 mb-2"><label class="form-label small fw-semibold">Serial #</label><div class="form-control form-control-sm" id="lp_serial" style="white-space:pre-wrap;height:auto;min-height:36px;"></div></div>
                            <div class="col-4 mb-2"><label class="form-label small fw-semibold">Qty</label><div class="form-control form-control-sm" id="lp_qty"></div></div>
                            <div class="col-4 mb-2"><label class="form-label small fw-semibold">Condition</label><div class="form-control form-control-sm" id="lp_cond"></div></div>
                            <div class="col-4 mb-2"><label class="form-label small fw-semibold">PO #</label><div class="form-control form-control-sm" id="lp_po"></div></div>
                        </div>
                    </div>
                </div>

                <!-- ══ IDENTIFIER CONFLICT WARNING (shown when same token exists in both tables) ══ -->
                <div id="edit_conflict_panel" class="alert alert-warning py-2 mb-3 d-none" role="alert">
                    <strong><i class="bx bx-error-circle me-1"></i> Identifier Conflict Detected</strong>
                    <p class="mb-1 mt-1 small">
                        The following tokens exist in <strong>both</strong>
                        <code>accountable_items</code> and <code>inventory_items</code>
                        for this record. Saving will <strong>move</strong> them to
                        accountable_items and <strong>remove</strong> them from inventory_items.
                    </p>
                    <ul id="edit_conflict_list" class="mb-0 small ps-3"></ul>
                </div>

                <!-- ══ EDITABLE FIELDS (hidden when borrow-locked) ══ -->
                <div id="edit_form_fields">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PAR / ICS / ARE Number</label>
                            <input type="text" name="edit_are_mr_ics_num" id="edit_are_mr_ics_num" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Property Number</label>
                            <input type="text" name="edit_property_number" id="edit_property_number" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Serial Number(s)</label>
                            <input type="text" name="edit_serial_number" id="edit_serial_number" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PO Number</label>
                            <input type="text" name="edit_po_number" id="edit_po_number" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Account Code</label>
                            <input type="text" name="edit_account_code" id="edit_account_code" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Old Account Code</label>
                            <input type="text" name="edit_old_account_code" id="edit_old_account_code" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Condition / Status</label>
                            <select name="edit_condition_status" id="edit_condition_status" class="form-select">
                                <option value="Serviceable">Serviceable</option>
                                <option value="For Repair">For Repair</option>
                                <option value="Unserviceable">Unserviceable</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Assigned Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="edit_quantity" id="edit_quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="edit_remarks" id="edit_remarks" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_accountable" class="btn btn-primary" id="edit_save_btn">
                    <i class="bx bx-save me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     TRANSFER MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="transferForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="transferModalLabel">
                    <i class="bx bx-transfer me-1"></i> Transfer Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="transfer_id"               id="transfer_id">
                <input type="hidden" name="transfer_max_qty"          id="transfer_max_qty">
                <input type="hidden" name="transfer_selected_serial"  id="transfer_selected_serial">
                <input type="hidden" name="transfer_selected_property" id="transfer_selected_property">
                <input type="hidden" name="transfer_selected_are"     id="transfer_selected_are">

                <div class="alert alert-light border py-2 mb-3">
                    <strong>Item:</strong> <span id="transfer_item_name" class="fw-bold"></span><br>
                    <strong>Current Custodian:</strong> <span id="transfer_current_emp"></span>
                </div>

                <!-- Quantity -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Quantity to Transfer
                        <span class="text-muted fw-normal" id="transfer_qty_hint"></span>
                    </label>
                    <input type="number" name="transfer_quantity" id="transfer_quantity"
                        class="form-control" min="1" required placeholder="Enter quantity">
                    <div class="invalid-feedback" id="transfer_qty_error"></div>
                    <div class="mt-2">
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-warning" id="transfer_qty_bar" style="width:0%"></div>
                        </div>
                        <small class="text-muted" id="transfer_qty_label">0 of 0 units selected</small>
                    </div>
                </div>

                <div id="transfer_type_badge" class="alert py-2 mb-3 d-none"></div>

                <!-- ── Mismatch warning (shown when edited values ≠ custodian record) ── -->
                <div id="xfer_mismatch_alert" class="alert alert-warning py-2 mb-3 d-none" role="alert">
                    <i class="bx bx-error me-1"></i>
                    <strong>Mismatch Warning:</strong>
                    <span id="xfer_mismatch_msg"></span>
                </div>

                <!-- ── PAR / ICS / ARE checklist (editable labels) ── -->
                <div class="mb-3" id="xfer_are_section">
                    <label class="form-label fw-semibold">
                        PAR / ICS / ARE Number
                        <small class="text-muted fw-normal">(check items being transferred; labels are editable)</small>
                        <span class="sn-match-hint ms-2" id="xfer_are_hint"></span>
                    </label>
                    <div id="xfer_are_list" class="serial-checklist-wrap">
                        <span class="serial-placeholder">Loading…</span>
                    </div>
                </div>

                <!-- ── Property Number checklist (editable labels) ── -->
                <div class="mb-3" id="xfer_prop_section">
                    <label class="form-label fw-semibold">
                        Property Number
                        <small class="text-muted fw-normal">(check items being transferred; labels are editable)</small>
                        <span class="sn-match-hint ms-2" id="xfer_prop_hint"></span>
                    </label>
                    <div id="xfer_prop_list" class="serial-checklist-wrap">
                        <span class="serial-placeholder">Loading…</span>
                    </div>
                </div>

                <!-- ── Serial Number checklist ── -->
                <div class="mb-3" id="xfer_serial_section">
                    <label class="form-label fw-semibold">
                        Serial Number(s)
                        <small class="text-muted fw-normal">(check serials being transferred)</small>
                        <span class="sn-match-hint ms-2" id="xfer_sn_hint"></span>
                    </label>
                    <div id="xfer_serial_list" class="serial-checklist-wrap">
                        <span class="serial-placeholder">Loading…</span>
                    </div>
                </div>

                <!-- ── New Custodian ── -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Custodian <span class="text-danger">*</span></label>
                    <select name="new_employee_id" class="form-select" required id="transferEmpSelect">
                        <option value="">-- Select New Employee --</option>
                        <?php
                        $emp->data_seek(0);
                        while ($e = $emp->fetch_assoc()):
                        ?>
                            <option value="<?= $e['ID'] ?>"><?= htmlspecialchars($e['full_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- ── Remarks ── -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Remarks</label>
                    <input type="text" name="transfer_remarks" id="transfer_remarks"
                           class="form-control" placeholder="Optional transfer remarks" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="transfer_item" class="btn btn-warning" id="transferSubmitBtn">
                    <i class="bx bx-transfer me-1"></i> Process Transfer
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="delete_id" id="delete_id">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bx bx-trash me-1"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bx bx-error-circle text-danger" style="font-size:3rem;"></i>
                <p class="mt-2 mb-1">You are about to delete the assignment for:</p>
                <p class="fw-bold mb-0" id="delete_item_display">—</p>
                <p class="text-muted small" id="delete_person_display">—</p>
                <div class="alert alert-warning py-2 mt-2 text-start small">
                    <i class="bx bx-info-circle me-1"></i>
                    The assigned quantity (<strong id="delete_qty_display">0</strong> unit(s))
                    will be <strong>returned to inventory stock</strong>.
                    This action is <strong>irreversible</strong>.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="bx bx-trash me-1"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     RETURN TO CGSO MODAL
     ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="returnToCgsoModal" tabindex="-1" aria-labelledby="rtcModalLabel">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="rtcForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="return_to_cgso" value="1">
            <input type="hidden" name="rtc_accountable_id" id="rtc_accountable_id">
            <input type="hidden" name="rtc_selected_serials" id="rtc_selected_serials">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rtcModalLabel">
                    <i class="bx bx-undo me-1"></i> Return Item to CGSO
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-warning py-2 mb-3">
                    <strong><i class="bx bx-info-circle me-1"></i>Item:</strong>
                    <span id="rtc_item_name_display" class="fw-bold"></span>
                    &mdash; Former User: <span id="rtc_person_display"></span>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">
                            Quantity to Return <span class="text-danger">*</span>
                            <span class="text-muted fw-normal" id="rtc_max_label"></span>
                        </label>
                        <input type="number" name="rtc_quantity" id="rtc_quantity"
                               class="form-control" min="1" required placeholder="Enter qty">
                        <div class="invalid-feedback" id="rtc_qty_error"></div>
                        <div class="progress mt-2" style="height:5px;">
                            <div class="progress-bar bg-danger" id="rtc_qty_bar" style="width:0%"></div>
                        </div>
                        <small class="text-muted" id="rtc_qty_label">0 of 0 units selected</small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Condition upon Return</label>
                        <select name="rtc_condition" id="rtcConditionSelect" class="form-select">
                            <option value="Returned to CGSO">Returned to CGSO</option>
                            <option value="Serviceable">Serviceable</option>
                            <option value="For Repair">For Repair</option>
                            <option value="Unserviceable">Unserviceable</option>
                            <option value="For Disposal">For Disposal</option>
                            <option value="Return to Property Custodian">Return to Property Custodian</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Remarks</label>
                        <input type="text" name="rtc_remarks" class="form-control"
                               placeholder="Optional remarks" maxlength="255">
                    </div>
                </div>

                <div id="rtc_property_custodian_panel" class="d-none mb-3">
                    <div class="alert alert-success py-2 mb-2">
                        <i class="bx bx-transfer-alt me-1"></i>
                        <strong>Return to Property Custodian</strong> — original fields
                        (PAR/ICS, Serial, Property Number, item_status) will be
                        <strong>restored</strong> to the Inventory record.
                    </div>
                    <button type="button" id="rtcPropertyCustodianBtn" class="btn btn-success w-100">
                        <i class="bx bx-transfer-alt me-1"></i> Return to Property Custodian
                    </button>
                </div>

                <div id="rtc_serial_section">
                    <label class="form-label fw-semibold">
                        Serial Numbers
                        <small class="text-muted fw-normal">(check each serial being returned)</small>
                    </label>
                    <div id="rtc_serial_list" class="row g-2 mb-2"></div>
                    <div id="rtc_serial_note" class="text-muted small d-none">
                        No serial numbers recorded for this item.
                    </div>
                    <div class="alert alert-info py-2 mt-2 d-none" id="rtc_serial_sync_info">
                        <i class="bx bx-sync me-1"></i>
                        Selected serial numbers will be <strong>removed</strong> from the accountable record
                        and recorded in returned_to_cgso. Remaining serials stay on the original record.
                    </div>
                </div>

                <div id="rtc_preview" class="alert alert-secondary py-2 mt-3 d-none">
                    <strong>Preview:</strong> <span id="rtc_preview_text"></span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" id="rtcSubmitBtn" disabled>
                    <i class="bx bx-undo me-1"></i> Confirm Return to CGSO
                </button>
            </div>
        </form>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ============================================================
   HELPERS
   ============================================================ */
function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escAttr(s) {
    return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function parseTokens(raw) {
    return (raw || '').split('/').map(function (s) { return s.trim(); }).filter(Boolean);
}

/* ============================================================
   CHECKLIST BUILDER — shared factory used by ARE, PROP, SERIAL
   ============================================================ */
function buildChecklist(opts) {
    /*
     * opts = {
     *   wrapEl      : DOM element for the checklist container
     *   hiddenEl    : DOM element <input type="hidden">
     *   tokens      : string[]     – individual values
     *   placeholder : string       – text shown when no item selected
     *   noDataText  : string       – text shown when item has no tokens
     *   reqNoteEl   : DOM element  – the "(N checked)" span in the label
     *   checkedCountEl : DOM element – the <strong> inside reqNoteEl
     *   matchHintEl : DOM element  – .sn-match-hint span
     *   autoQty     : number       – pre-check this many from the top
     *   onSync      : function     – called after every checkbox change
     * }
     */
    var wrap    = opts.wrapEl;
    var hidden  = opts.hiddenEl;
    if (!wrap) return;

    wrap.innerHTML  = '';
    wrap.className  = 'serial-checklist-wrap';
    if (hidden) hidden.value = '';

    if (opts.matchHintEl) {
        opts.matchHintEl.style.display = 'none';
        opts.matchHintEl.textContent   = '';
    }

    if (!opts.tokens || opts.tokens.length === 0) {
        wrap.innerHTML = opts.tokens === null
            ? '<span class="serial-placeholder">' + escHtml(opts.placeholder) + '</span>'
            : '<span class="serial-no-data">' + escHtml(opts.noDataText) + '</span>';
        if (opts.reqNoteEl) opts.reqNoteEl.style.display = 'none';
        return;
    }

    if (opts.reqNoteEl) opts.reqNoteEl.style.display = 'inline';

    var autoCheck = Math.min(opts.autoQty || 0, opts.tokens.length);

    opts.tokens.forEach(function (token, idx) {
        var lbl = document.createElement('label');
        lbl.className = 'serial-check-item';
        lbl.title     = token;

        var cb      = document.createElement('input');
        cb.type     = 'checkbox';
        cb.value    = token;

        if (idx < autoCheck) {
            cb.checked = true;
            lbl.classList.add('sn-checked');
        }

        cb.addEventListener('change', function () {
            lbl.classList.toggle('sn-checked', cb.checked);
            if (opts.onSync) opts.onSync();
        });

        lbl.appendChild(cb);
        lbl.appendChild(document.createTextNode(token));
        wrap.appendChild(lbl);
    });

    if (opts.onSync) opts.onSync();
}

/* ============================================================
   ASSIGN FORM — state
   ============================================================ */
var _assignMaxQty = 0;

var assignAreWrap    = document.getElementById('assign_are_checklist');
var assignAreHidden  = document.getElementById('are_mr_ics_num_hidden');
var assignAreNote    = document.getElementById('assign_are_req_note');
var assignAreCnt     = document.getElementById('assign_are_checked_count');
var assignAreHint    = document.getElementById('assign_are_match_hint');

var assignPropWrap   = document.getElementById('assign_prop_checklist');
var assignPropHidden = document.getElementById('property_number_hidden');
var assignPropNote   = document.getElementById('assign_prop_req_note');
var assignPropCnt    = document.getElementById('assign_prop_checked_count');
var assignPropHint   = document.getElementById('assign_prop_match_hint');

var assignSnWrap     = document.getElementById('assign_serial_checklist');
var assignSnHidden   = document.getElementById('serial_number_hidden');
var assignSnNote     = document.getElementById('assign_sn_req_note');
var assignSnCnt      = document.getElementById('assign_sn_req_count');
var assignSnHint     = document.getElementById('assign_sn_match_hint');

var assignQtyInput   = document.getElementById('assign_quantity');
var assignSubmitBtn  = document.getElementById('assignSubmitBtn');
var assignQtyErr     = document.getElementById('assign_qty_error');

/* ── Sync helpers ─────────────────────────────────────────── */
function getChecked(wrapEl) {
    if (!wrapEl) return [];
    return Array.from(wrapEl.querySelectorAll('input[type="checkbox"]:checked'))
        .map(function (cb) { return cb.value; });
}

function applyWrapState(wrapEl, got, need) {
    if (!wrapEl) return;
    wrapEl.classList.remove('sn-valid', 'sn-invalid');
    if (got > 0) wrapEl.classList.add(got === need ? 'sn-valid' : 'sn-invalid');
}

function renderMatchHint(hintEl, label, got, need) {
    if (!hintEl) return;
    hintEl.style.display = 'block';
    if (need === 0) {
        hintEl.className = 'sn-match-hint warn';
        hintEl.textContent = '⚠ Enter a quantity first to know how many ' + label + ' to select.';
    } else if (got === 0) {
        hintEl.className = 'sn-match-hint warn';
        hintEl.textContent = 'Select ' + need + ' ' + label + '(s) — none checked yet.';
    } else if (got === need) {
        hintEl.className = 'sn-match-hint ok';
        hintEl.textContent = '✓ ' + got + ' ' + label + '(s) checked — matches quantity (' + need + ').';
    } else if (got < need) {
        hintEl.className = 'sn-match-hint error';
        hintEl.textContent = '✗ ' + got + '/' + need + ' ' + label + '(s) checked — need ' + (need - got) + ' more.';
    } else {
        hintEl.className = 'sn-match-hint error';
        hintEl.textContent = '✗ ' + got + '/' + need + ' ' + label + '(s) checked — ' + (got - need) + ' too many, uncheck some.';
    }
}

function syncAssignAll() {
    var need = parseInt(assignQtyInput ? assignQtyInput.value : '0', 10) || 0;

    // ARE
    var areChecked = getChecked(assignAreWrap);
    if (assignAreHidden) assignAreHidden.value = areChecked.join(' / ');
    if (assignAreCnt) assignAreCnt.textContent = areChecked.length;
    var hasAreBoxes = assignAreWrap ? assignAreWrap.querySelectorAll('input[type="checkbox"]').length > 0 : false;
    if (hasAreBoxes) {
        applyWrapState(assignAreWrap, areChecked.length, need);
        renderMatchHint(assignAreHint, 'PAR/ICS/ARE number', areChecked.length, need);
    } else if (assignAreHint) assignAreHint.style.display = 'none';

    // PROP
    var propChecked = getChecked(assignPropWrap);
    if (assignPropHidden) assignPropHidden.value = propChecked.join(' / ');
    if (assignPropCnt) assignPropCnt.textContent = propChecked.length;
    var hasPropBoxes = assignPropWrap ? assignPropWrap.querySelectorAll('input[type="checkbox"]').length > 0 : false;
    if (hasPropBoxes) {
        applyWrapState(assignPropWrap, propChecked.length, need);
        renderMatchHint(assignPropHint, 'property number', propChecked.length, need);
    } else if (assignPropHint) assignPropHint.style.display = 'none';

    // SERIAL
    var snChecked = getChecked(assignSnWrap);
    if (assignSnHidden) assignSnHidden.value = snChecked.join(' / ');
    if (assignSnCnt) assignSnCnt.textContent = need || '…';
    var hasSnBoxes = assignSnWrap ? assignSnWrap.querySelectorAll('input[type="checkbox"]').length > 0 : false;
    if (hasSnBoxes) {
        applyWrapState(assignSnWrap, snChecked.length, need);
        renderMatchHint(assignSnHint, 'serial number', snChecked.length, need);
    } else if (assignSnHint) assignSnHint.style.display = 'none';

    // Validate quantity vs. stock
    var max = _assignMaxQty;
    var qtyValid = need >= 1 && need <= max;

    if (assignQtyInput && max > 0) {
        if (!qtyValid && need > 0) {
            assignQtyInput.classList.remove('is-valid');
            assignQtyInput.classList.add('is-invalid');
            if (assignQtyErr) assignQtyErr.textContent = 'Only ' + max + ' unit(s) available. Cannot assign more than stock.';
        } else if (qtyValid) {
            assignQtyInput.classList.remove('is-invalid');
            assignQtyInput.classList.add('is-valid');
            if (assignQtyErr) assignQtyErr.textContent = '';
        }
    }

    // Block submit if qty exceeds stock, or if any visible checklist has wrong count
    var areOk  = !hasAreBoxes  || areChecked.length === need;
    var propOk = !hasPropBoxes || propChecked.length === need;
    var snOk   = !hasSnBoxes   || snChecked.length   === need;
    var valid  = qtyValid && areOk && propOk && snOk;

    if (assignSubmitBtn) assignSubmitBtn.disabled = !valid && need > 0;
}

/* ── Auto-check first N boxes across all three lists ───────── */
function autoCheckLists(n) {
    [assignAreWrap, assignPropWrap, assignSnWrap].forEach(function (wrap) {
        if (!wrap) return;
        var boxes = Array.from(wrap.querySelectorAll('input[type="checkbox"]'));
        boxes.forEach(function (cb, idx) {
            var shouldCheck = idx < n;
            cb.checked = shouldCheck;
            if (cb.parentElement) cb.parentElement.classList.toggle('sn-checked', shouldCheck);
        });
    });
    syncAssignAll();
}

/* ── Build all three checklists from the selected item ──────── */
function buildAssignChecklists(opt) {
    /*
     * opt = {
     *   icsRaw      : string   – raw are_mr_ics_num from inventory_items
     *   propRaw     : string   – raw property_number
     *   serialRaw   : string   – raw serial_number
     *   autoQty     : number   – how many to pre-check
     *   maxQty      : number   – available stock cap
     * }
     */
    _assignMaxQty = opt.maxQty || 0;

    var icsTokens    = opt.icsRaw    ? parseTokens(opt.icsRaw)    : null;
    var propTokens   = opt.propRaw   ? parseTokens(opt.propRaw)   : null;
    var serialTokens = opt.serialRaw ? parseTokens(opt.serialRaw) : null;

    // When no tokens at all in the source: pass null to show placeholder vs []
    // to show "No data" — we pass [] when tokens field exists but is empty.
    buildChecklist({
        wrapEl        : assignAreWrap,
        hiddenEl      : assignAreHidden,
        tokens        : icsTokens === null ? null : (icsTokens.length ? icsTokens : []),
        placeholder   : 'Select an inventory item to load its PAR / ICS / ARE numbers.',
        noDataText    : 'No PAR / ICS / ARE numbers on record for this item.',
        reqNoteEl     : assignAreNote,
        checkedCountEl: assignAreCnt,
        matchHintEl   : assignAreHint,
        autoQty       : opt.autoQty,
        onSync        : syncAssignAll,
    });

    buildChecklist({
        wrapEl        : assignPropWrap,
        hiddenEl      : assignPropHidden,
        tokens        : propTokens === null ? null : (propTokens.length ? propTokens : []),
        placeholder   : 'Select an inventory item to load its property numbers.',
        noDataText    : 'No property numbers on record for this item.',
        reqNoteEl     : assignPropNote,
        checkedCountEl: assignPropCnt,
        matchHintEl   : assignPropHint,
        autoQty       : opt.autoQty,
        onSync        : syncAssignAll,
    });

    buildChecklist({
        wrapEl        : assignSnWrap,
        hiddenEl      : assignSnHidden,
        tokens        : serialTokens === null ? null : (serialTokens.length ? serialTokens : []),
        placeholder   : 'Select an inventory item to load its serial numbers.',
        noDataText    : 'No serial numbers on record for this item.',
        reqNoteEl     : assignSnNote,
        checkedCountEl: assignSnCnt,
        matchHintEl   : assignSnHint,
        autoQty       : opt.autoQty,
        onSync        : syncAssignAll,
    });
}

/* ── Reset all checklists to placeholder state ──────────────── */
function resetAssignChecklists() {
    [
        {w: assignAreWrap,  h: assignAreHidden,  n: assignAreNote,  hint: assignAreHint,  ph: 'Select an inventory item to load its PAR / ICS / ARE numbers.'},
        {w: assignPropWrap, h: assignPropHidden, n: assignPropNote, hint: assignPropHint, ph: 'Select an inventory item to load its property numbers.'},
        {w: assignSnWrap,   h: assignSnHidden,   n: assignSnNote,   hint: assignSnHint,   ph: 'Select an inventory item to load its serial numbers.'},
    ].forEach(function (x) {
        if (x.w) { x.w.innerHTML = '<span class="serial-placeholder">' + escHtml(x.ph) + '</span>'; x.w.className = 'serial-checklist-wrap'; }
        if (x.h) x.h.value = '';
        if (x.n) x.n.style.display = 'none';
        if (x.hint) { x.hint.style.display = 'none'; x.hint.textContent = ''; }
    });
    _assignMaxQty = 0;
}

/* ── Inventory item select change ───────────────────────────── */
document.getElementById('itemSelect').addEventListener('change', function () {
    var opt       = this.options[this.selectedIndex];
    var infoPanel = document.getElementById('inventoryInfoPanel');
    var detailDiv = document.getElementById('inventoryDetails');

    if (!this.value) {
        infoPanel.classList.add('d-none');
        detailDiv.classList.add('d-none');
        resetAssignChecklists();
        if (assignQtyInput) { assignQtyInput.value = ''; assignQtyInput.max = ''; assignQtyInput.classList.remove('is-valid', 'is-invalid'); }
        if (assignSubmitBtn) assignSubmitBtn.disabled = false;
        return;
    }

    // Populate info panel
    document.getElementById('info_particulars').textContent = opt.dataset.particulars || '—';
    document.getElementById('info_qty').textContent         = opt.dataset.qty         || '0';
    document.getElementById('info_value').textContent       = '₱' + (opt.dataset.value || '0.00');
    document.getElementById('info_total').textContent       = '₱' + (opt.dataset.total || '0.00');
    document.getElementById('info_delivered').textContent   = opt.dataset.delivered   || '—';
    infoPanel.classList.remove('d-none');

    var maxQty = parseInt(opt.dataset.qty, 10) || 0;

    // Set quantity field constraints
    if (assignQtyInput) {
        assignQtyInput.max   = maxQty;
        assignQtyInput.value = maxQty === 1 ? 1 : '';
        assignQtyInput.classList.remove('is-valid', 'is-invalid');
    }

    var autoQty = maxQty === 1 ? 1 : (parseInt(assignQtyInput ? assignQtyInput.value : '0', 10) || 0);

    // Build checklists from this item's data
    buildAssignChecklists({
        icsRaw    : opt.dataset.ics      || '',
        propRaw   : opt.dataset.property || '',
        serialRaw : opt.dataset.serial   || '',
        autoQty   : autoQty,
        maxQty    : maxQty,
    });

    detailDiv.classList.remove('d-none');
});

/* ── Quantity input live validation + auto-check sync ──────── */
document.getElementById('assign_quantity').addEventListener('input', function () {
    var val = parseInt(this.value, 10);
    var max = _assignMaxQty;

    if (!isNaN(val) && val >= 1 && val <= max) {
        // Auto-check first val checkboxes in each list to match new qty
        autoCheckLists(val);
    } else {
        syncAssignAll();
    }
});

/* ── Block submit if checklists mismatch (extra safety guard) ─ */
document.getElementById('assignForm').addEventListener('submit', function (e) {
    var need = parseInt(assignQtyInput ? assignQtyInput.value : '0', 10) || 0;
    var max  = _assignMaxQty;

    if (need < 1 || need > max) {
        e.preventDefault();
        if (assignQtyErr) assignQtyErr.textContent = 'Please enter a valid quantity (1 – ' + max + ').';
        if (assignQtyInput) assignQtyInput.classList.add('is-invalid');
        return;
    }

    var hasAreBoxes  = assignAreWrap  ? assignAreWrap.querySelectorAll('input[type="checkbox"]').length > 0  : false;
    var hasPropBoxes = assignPropWrap ? assignPropWrap.querySelectorAll('input[type="checkbox"]').length > 0 : false;
    var hasSnBoxes   = assignSnWrap   ? assignSnWrap.querySelectorAll('input[type="checkbox"]').length > 0   : false;

    var areOk  = !hasAreBoxes  || getChecked(assignAreWrap).length  === need;
    var propOk = !hasPropBoxes || getChecked(assignPropWrap).length === need;
    var snOk   = !hasSnBoxes   || getChecked(assignSnWrap).length   === need;

    if (!areOk || !propOk || !snOk) {
        e.preventDefault();
        var msgs = [];
        if (!areOk)  msgs.push('PAR/ICS/ARE numbers');
        if (!propOk) msgs.push('property numbers');
        if (!snOk)   msgs.push('serial numbers');
        alert('The number of checked ' + msgs.join(', ') + ' must equal the assigned quantity (' + need + ').');
        syncAssignAll();
    }
});

/* ============================================================
   EDIT MODAL
   ============================================================ */
function openEditModal(el) {
    /* Read the row JSON from the data-* attribute.
       The browser HTML-decodes the attribute value before we see it here,
       so json_encode's \n / \r escape sequences arrive intact for JSON.parse. */
    var row = JSON.parse(el.dataset.acctRow);

    // ── Populate basic info banner ────────────────────────────
    document.getElementById('edit_id').value                     = row.id;
    document.getElementById('edit_item_name_display').textContent = row.item_name   || '—';
    document.getElementById('edit_person_display').textContent    = row.person_name || '—';

    var borrowLockPanel = document.getElementById('edit_borrow_lock_panel');
    var conflictPanel   = document.getElementById('edit_conflict_panel');
    var formFields      = document.getElementById('edit_form_fields');
    var saveBtn         = document.getElementById('edit_save_btn');

    var activeBorrows = parseInt(row.active_borrows, 10) || 0;

    // ── BORROW LOCK ───────────────────────────────────────────
    if (activeBorrows > 0) {
        // Show lock panel, hide editable fields + save button
        borrowLockPanel.classList.remove('d-none');
        formFields.classList.add('d-none');
        conflictPanel.classList.add('d-none');
        saveBtn.disabled    = true;
        saveBtn.style.display = 'none';

        // Populate lock details
        document.getElementById('edit_lock_borrow_ref').textContent
            = row.active_borrow_ref || '(ref unavailable)';
        document.getElementById('edit_lock_borrow_borrower').textContent
            = row.active_borrow_borrower || '—';
        document.getElementById('edit_lock_borrow_count').textContent
            = activeBorrows;

        // Populate read-only preview (blurred)
        document.getElementById('lp_are').textContent    = row.are_mr_ics_num  || '—';
        document.getElementById('lp_prop').textContent   = row.property_number || '—';
        document.getElementById('lp_serial').textContent = row.serial_number   || '—';
        document.getElementById('lp_qty').textContent    = row.assigned_quantity || '—';
        document.getElementById('lp_cond').textContent   = row.condition_status || '—';
        document.getElementById('lp_po').textContent     = row.po_number        || '—';

    } else {
        // ── No active borrows — allow editing ─────────────────
        borrowLockPanel.classList.add('d-none');
        formFields.classList.remove('d-none');
        saveBtn.disabled      = false;
        saveBtn.style.display = '';

        // Populate editable fields
        document.getElementById('edit_are_mr_ics_num').value   = row.are_mr_ics_num   || '';
        document.getElementById('edit_property_number').value  = row.property_number  || '';
        document.getElementById('edit_serial_number').value    = row.serial_number    || '';
        document.getElementById('edit_po_number').value        = row.po_number        || '';
        document.getElementById('edit_account_code').value     = row.account_code     || '';
        document.getElementById('edit_old_account_code').value = row.old_account_code || '';
        document.getElementById('edit_condition_status').value = row.condition_status || 'Serviceable';
        document.getElementById('edit_quantity').value         = row.assigned_quantity || 1;
        document.getElementById('edit_remarks').value          = row.remarks           || '';

        // ── IDENTIFIER CONFLICT DETECTION ────────────────────
        // Check if the same token appears in BOTH accountable_items (this row)
        // AND inventory_items for the same inventory_item_id.
        // If so, warn the admin — saving will MOVE the token (remove it from inventory).
        var conflicts = [];

        var fieldLabels = {
            are_mr_ics_num  : 'PAR / ICS / ARE #',
            property_number : 'Property #',
            serial_number   : 'Serial #',
        };

        ['are_mr_ics_num', 'property_number', 'serial_number'].forEach(function(field) {
            var acctTokens = parseTokens(row[field]             || '');
            var invTokens  = parseTokens(row['inv_' + field]   || '');

            var dupes = acctTokens.filter(function(t) {
                return invTokens.indexOf(t) !== -1;
            });

            if (dupes.length > 0) {
                conflicts.push({
                    label  : fieldLabels[field],
                    tokens : dupes,
                });
            }
        });

        if (conflicts.length > 0) {
            conflictPanel.classList.remove('d-none');
            var list = document.getElementById('edit_conflict_list');
            list.innerHTML = '';
            conflicts.forEach(function(c) {
                var li = document.createElement('li');
                li.innerHTML = '<strong>' + escHtml(c.label) + ':</strong> '
                    + c.tokens.map(function(t) {
                        return '<code class="bg-warning px-1 rounded">' + escHtml(t) + '</code>';
                      }).join(', ')
                    + ' <span class="text-muted">(will be moved to accountable on save)</span>';
                list.appendChild(li);
            });
        } else {
            conflictPanel.classList.add('d-none');
        }
    }

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

/* ============================================================
   TRANSFER MODAL
   ============================================================ */

/* Internal state for transfer modal */
var _xferMaxQty    = 0;
var _xferSerials   = [];  // token arrays from the source record
var _xferProps     = [];
var _xferAres      = [];

/* Build an editable checklist for transfer (ARE / Property).
   `tokens`  - the original token array from the current custodian's record.
   When an editable value is changed to something NOT in `tokens`, a
   per-row warning badge is shown and syncXferAll() surfaces a consolidated alert. */
function buildXferEditableChecklist(wrapEl, tokens, autoQty) {
    wrapEl.innerHTML = '';
    wrapEl.className = 'serial-checklist-wrap';

    if (!tokens || tokens.length === 0) {
        wrapEl.innerHTML = '<span class="serial-no-data">No values on record for this item.</span>';
        return;
    }

    tokens.forEach(function(token, idx) {
        var lbl = document.createElement('label');
        lbl.className = 'serial-check-item' + (idx < autoQty ? ' sn-checked' : '');
        lbl.style.flexDirection = 'column';
        lbl.style.alignItems    = 'flex-start';
        lbl.style.gap           = '0.2rem';

        var row = document.createElement('div');
        row.style.display    = 'flex';
        row.style.alignItems = 'center';
        row.style.gap        = '0.35rem';
        row.style.width      = '100%';

        var cb = document.createElement('input');
        cb.type    = 'checkbox';
        cb.value   = token;
        cb.checked = idx < autoQty;

        var editInput = document.createElement('input');
        editInput.type           = 'text';
        editInput.value          = token;
        editInput.className      = 'form-control form-control-sm';
        editInput.style.width    = '100%';
        editInput.style.minWidth = '180px';
        editInput.style.fontSize = '0.82rem';
        editInput.placeholder    = 'Edit value…';

        /* Mismatch badge — hidden by default, shown when the edited value
           no longer exists in the original custodian tokens. */
        var warnBadge = document.createElement('span');
        warnBadge.className     = 'badge bg-warning text-dark xfer-mismatch-badge';
        warnBadge.style.display = 'none';
        warnBadge.innerHTML     = '<i class="bx bx-error-circle me-1"></i>Not on record';
        warnBadge.title         = 'This value does not match any identifier on record for the current custodian. Verify before transferring.';

        /* Evaluate mismatch for this row and update its visual state. */
        function evalMismatch() {
            var val      = editInput.value.trim();
            var mismatch = cb.checked && val !== '' && tokens.indexOf(val) === -1;
            warnBadge.style.display = mismatch ? 'inline-block' : 'none';
            lbl.classList.toggle('xfer-mismatch-item', mismatch);
        }

        /* When the text is edited → update cb value + re-evaluate mismatch. */
        editInput.addEventListener('input', function() {
            cb.value = editInput.value.trim();
            evalMismatch();
            syncXferAll();
        });

        /* When the checkbox is toggled → re-evaluate mismatch
           (an unchecked mismatched row is no longer a blocking problem). */
        cb.addEventListener('change', function() {
            lbl.classList.toggle('sn-checked', cb.checked);
            evalMismatch();
            syncXferAll();
        });

        /* Run once on build so pre-checked items are evaluated immediately. */
        evalMismatch();

        row.appendChild(cb);
        row.appendChild(editInput);
        row.appendChild(warnBadge);
        lbl.appendChild(row);
        wrapEl.appendChild(lbl);
    });
}

/* Build a read-only checklist for transfer (Serial) */
function buildXferSerialChecklist(wrapEl, tokens, autoQty) {
    wrapEl.innerHTML = '';
    wrapEl.className = 'serial-checklist-wrap';

    if (!tokens || tokens.length === 0) {
        wrapEl.innerHTML = '<span class="serial-no-data">No serial numbers on record for this item.</span>';
        return;
    }

    tokens.forEach(function(token, idx) {
        var lbl = document.createElement('label');
        lbl.className = 'serial-check-item' + (idx < autoQty ? ' sn-checked' : '');
        lbl.title = token;

        var cb = document.createElement('input');
        cb.type    = 'checkbox';
        cb.value   = token;
        cb.checked = idx < autoQty;

        cb.addEventListener('change', function() {
            lbl.classList.toggle('sn-checked', cb.checked);
            syncXferAll();
        });

        lbl.appendChild(cb);
        lbl.appendChild(document.createTextNode(token));
        wrapEl.appendChild(lbl);
    });
}

function getXferCheckedValues(wrapEl) {
    if (!wrapEl) return [];
    return Array.from(wrapEl.querySelectorAll('input[type="checkbox"]:checked'))
        .map(function(cb) { return cb.value.trim(); }).filter(Boolean);
}

/* Returns true if any CHECKED editable row in `wrapEl` has a value that is
   not present in `originalTokens` (i.e. the user edited it to something
   that doesn't belong to the current custodian's record). */
function hasXferMismatch(wrapEl, originalTokens) {
    if (!wrapEl || !originalTokens || originalTokens.length === 0) return false;
    var checkboxes = Array.from(wrapEl.querySelectorAll('input[type="checkbox"]'));
    var textInputs  = Array.from(wrapEl.querySelectorAll('input[type="text"]'));
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked && textInputs[i]) {
            var val = textInputs[i].value.trim();
            if (val !== '' && originalTokens.indexOf(val) === -1) {
                return true;
            }
        }
    }
    return false;
}

function renderXferHint(hintEl, label, got, need) {
    if (!hintEl) return;
    if (need === 0) {
        hintEl.className = 'sn-match-hint ms-2 warn';
        hintEl.style.display = 'inline';
        hintEl.textContent = '— enter quantity first';
        return;
    }
    hintEl.style.display = 'inline';
    if (got === need) {
        hintEl.className = 'sn-match-hint ms-2 ok';
        hintEl.textContent = '✓ ' + got + ' checked';
    } else if (got < need) {
        hintEl.className = 'sn-match-hint ms-2 error';
        hintEl.textContent = '✗ ' + got + '/' + need + ' — need ' + (need - got) + ' more';
    } else {
        hintEl.className = 'sn-match-hint ms-2 error';
        hintEl.textContent = '✗ ' + got + '/' + need + ' — ' + (got - need) + ' too many';
    }
}

function syncXferAll() {
    var qty    = parseInt(document.getElementById('transfer_quantity').value, 10) || 0;
    var max    = _xferMaxQty;
    var badge  = document.getElementById('transfer_type_badge');
    var bar    = document.getElementById('transfer_qty_bar');
    var label  = document.getElementById('transfer_qty_label');
    var errMsg = document.getElementById('transfer_qty_error');
    var btn    = document.getElementById('transferSubmitBtn');

    // Qty progress bar
    document.getElementById('transfer_quantity').classList.remove('is-invalid', 'is-valid');
    badge.className = 'alert py-2 mb-3 d-none';

    if (qty >= 1 && qty <= max) {
        var pct = Math.round((qty / max) * 100);
        bar.style.width = pct + '%';
        document.getElementById('transfer_quantity').classList.add('is-valid');
        if (qty === max) {
            bar.className   = 'progress-bar bg-warning';
            label.innerText = qty + ' of ' + max + ' — full transfer';
            badge.className = 'alert alert-warning py-2 mb-3';
            badge.innerHTML = '<i class="bx bx-transfer me-1"></i> <strong>Full Transfer:</strong> Record fully re-assigned to new custodian.';
        } else {
            bar.className   = 'progress-bar bg-info';
            label.innerText = qty + ' of ' + max + ' — partial (' + (max - qty) + ' remain)';
            badge.className = 'alert alert-info py-2 mb-3';
            badge.innerHTML = '<i class="bx bx-git-branch me-1"></i> <strong>Partial Transfer:</strong> ' + qty + ' unit(s) go to new custodian. ' + (max - qty) + ' remain with current custodian.';
        }
    } else if (qty > max) {
        document.getElementById('transfer_quantity').classList.add('is-invalid');
        errMsg.innerText = '❌ Only ' + max + ' unit(s) available.';
        bar.style.width = '100%';
        bar.className   = 'progress-bar bg-danger';
        label.innerText = qty + ' of ' + max + ' — exceeds available!';
        badge.className = 'alert alert-danger py-2 mb-3';
        badge.innerHTML = '<i class="bx bx-error-circle me-1"></i> <strong>Insufficient quantity!</strong> Maximum: <strong>' + max + '</strong>.';
        btn.disabled = true;
        return;
    } else {
        // qty is 0, negative, or blank — warn and block submission
        bar.style.width = '0%';
        bar.className   = 'progress-bar bg-danger';
        label.innerText = '0 of ' + max + ' units selected';
        document.getElementById('transfer_quantity').classList.add('is-invalid');
        errMsg.innerText = '⚠️ Quantity must be at least 1 to process a transfer.';
        badge.className = 'alert alert-danger py-2 mb-3';
        badge.innerHTML = '<i class="bx bx-error-circle me-1"></i> <strong>Invalid Quantity:</strong> Enter a value between <strong>1</strong> and <strong>' + max + '</strong> to proceed.';
        btn.disabled = true;
        return;
    }

    // Collect checked values
    var areWrap    = document.getElementById('xfer_are_list');
    var propWrap   = document.getElementById('xfer_prop_list');
    var serialWrap = document.getElementById('xfer_serial_list');

    var areChecked    = getXferCheckedValues(areWrap);
    var propChecked   = getXferCheckedValues(propWrap);
    var serialChecked = getXferCheckedValues(serialWrap);

    // Update hidden fields
    document.getElementById('transfer_selected_are').value      = areChecked.join(' / ');
    document.getElementById('transfer_selected_property').value  = propChecked.join(' / ');
    document.getElementById('transfer_selected_serial').value    = serialChecked.join(' / ');

    // Render hints
    var hasAre    = _xferAres.length    > 0;
    var hasProp   = _xferProps.length   > 0;
    var hasSerial = _xferSerials.length > 0;

    if (hasAre)    renderXferHint(document.getElementById('xfer_are_hint'),    'PAR/ICS/ARE', areChecked.length,    qty);
    if (hasProp)   renderXferHint(document.getElementById('xfer_prop_hint'),   'property #',  propChecked.length,   qty);
    if (hasSerial) renderXferHint(document.getElementById('xfer_sn_hint'),     'serial #',    serialChecked.length, qty);

    // Apply wrap state
    [
        {w: areWrap,    got: areChecked.length,    has: hasAre},
        {w: propWrap,   got: propChecked.length,   has: hasProp},
        {w: serialWrap, got: serialChecked.length, has: hasSerial},
    ].forEach(function(x) {
        if (!x.w || !x.has) return;
        x.w.classList.remove('sn-valid', 'sn-invalid');
        if (x.got > 0) x.w.classList.add(x.got === qty ? 'sn-valid' : 'sn-invalid');
    });

    // ── Mismatch detection ──────────────────────────────────────────────────
    // Check whether any checked, editable row has a value that was edited to
    // something not present in the current custodian's on-record tokens.
    var areMismatch  = hasAre  && hasXferMismatch(areWrap,  _xferAres);
    var propMismatch = hasProp && hasXferMismatch(propWrap, _xferProps);
    var anyMismatch  = areMismatch || propMismatch;

    var mismatchAlert = document.getElementById('xfer_mismatch_alert');
    var mismatchMsg   = document.getElementById('xfer_mismatch_msg');
    if (mismatchAlert && mismatchMsg) {
        if (anyMismatch) {
            var mismatchFields = [];
            if (areMismatch)  mismatchFields.push('<strong>PAR / ICS / ARE Number</strong>');
            if (propMismatch) mismatchFields.push('<strong>Property Number</strong>');
            mismatchMsg.innerHTML = ' One or more checked values in ' + mismatchFields.join(' and ')
                + ' do not match what the current custodian has on record.'
                + ' Please correct the highlighted rows (marked <span class="badge bg-warning text-dark" style="font-size:.70rem">Not on record</span>) before continuing.';
            mismatchAlert.className = 'alert alert-warning py-2 mb-3';
        } else {
            mismatchAlert.className = 'alert py-2 mb-3 d-none';
            mismatchMsg.innerHTML   = '';
        }
    }
    // ────────────────────────────────────────────────────────────────────────

    // Enable submit only when qty valid, all checked counts match, and no mismatches
    var areOk    = !hasAre    || areChecked.length    === qty;
    var propOk   = !hasProp   || propChecked.length   === qty;
    var serialOk = !hasSerial || serialChecked.length === qty;
    btn.disabled = !(qty >= 1 && qty <= max && areOk && propOk && serialOk && !anyMismatch);
}

function openTransferModal(el) {
    var d        = el.dataset;
    var id       = parseInt(d.id,     10);
    var itemName = d.itemName;      // dataset converts data-item-name → itemName
    var currentEmp = d.personName;  // data-person-name → personName
    var maxQty   = parseInt(d.maxQty, 10);
    var rawSerial = d.serial;
    var rawProp   = d.prop;
    var rawAre    = d.are;

    _xferMaxQty  = maxQty;
    _xferSerials = rawSerial ? rawSerial.split('/').map(function(s){return s.trim();}).filter(Boolean) : [];
    _xferProps   = rawProp   ? rawProp.split('/').map(function(s){return s.trim();}).filter(Boolean)   : [];
    _xferAres    = rawAre    ? rawAre.split('/').map(function(s){return s.trim();}).filter(Boolean)    : [];

    document.getElementById('transfer_id').value              = id;
    document.getElementById('transfer_max_qty').value         = maxQty;
    document.getElementById('transfer_item_name').innerText   = itemName;
    document.getElementById('transfer_current_emp').innerText = currentEmp;
    document.getElementById('transfer_qty_hint').innerText    = '(max: ' + maxQty + ')';

    var qtyInput = document.getElementById('transfer_quantity');
    qtyInput.max   = maxQty;
    qtyInput.value = maxQty;   // pre-fill with full assigned quantity
    qtyInput.classList.remove('is-invalid', 'is-valid');

    document.getElementById('transfer_type_badge').className = 'alert py-2 mb-3 d-none';
    document.getElementById('transfer_qty_bar').style.width  = '0%';
    document.getElementById('transfer_qty_bar').className    = 'progress-bar bg-warning';
    document.getElementById('transfer_qty_label').innerText  = '0 of ' + maxQty + ' units selected';
    document.getElementById('transferSubmitBtn').disabled    = true;
    document.getElementById('transferEmpSelect').value       = '';
    document.getElementById('transfer_remarks').value        = '';
    document.getElementById('transfer_selected_serial').value   = '';
    document.getElementById('transfer_selected_property').value = '';
    document.getElementById('transfer_selected_are').value      = '';

    // Reset hints
    ['xfer_are_hint','xfer_prop_hint','xfer_sn_hint'].forEach(function(hid){
        var h = document.getElementById(hid);
        if (h) { h.style.display = 'none'; h.textContent = ''; }
    });

    // Always pre-check ALL tokens for the current custodian.
    // Passing maxQty as autoQ is safe: builders clamp to tokens.length,
    // so every assigned identifier is checked when the modal opens.
    buildXferEditableChecklist(document.getElementById('xfer_are_list'),    _xferAres,    maxQty);
    buildXferEditableChecklist(document.getElementById('xfer_prop_list'),   _xferProps,   maxQty);
    buildXferSerialChecklist(  document.getElementById('xfer_serial_list'), _xferSerials, maxQty);

    // Sync immediately so the progress bar, hints, and submit button
    // all reflect the pre-filled quantity + pre-checked state.
    syncXferAll();

    new bootstrap.Modal(document.getElementById('transferModal')).show();
}

document.getElementById('transfer_quantity').addEventListener('input', function() {
    var val = parseInt(this.value, 10);
    var max = _xferMaxQty;

    // Auto-check first val items in each list
    if (!isNaN(val) && val >= 1 && val <= max) {
        [
            {wrapId: 'xfer_are_list',    editable: true},
            {wrapId: 'xfer_prop_list',   editable: true},
            {wrapId: 'xfer_serial_list', editable: false},
        ].forEach(function(cfg) {
            var wrap = document.getElementById(cfg.wrapId);
            if (!wrap) return;
            var items = Array.from(wrap.querySelectorAll('input[type="checkbox"]'));
            items.forEach(function(cb, idx) {
                cb.checked = idx < val;
                if (cb.parentElement) cb.parentElement.classList.toggle('sn-checked', cb.checked);
            });
        });
    } else {
        // qty is 0, blank, or out-of-range — uncheck everything
        ['xfer_are_list', 'xfer_prop_list', 'xfer_serial_list'].forEach(function(wrapId) {
            var wrap = document.getElementById(wrapId);
            if (!wrap) return;
            wrap.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                cb.checked = false;
                if (cb.parentElement) cb.parentElement.classList.remove('sn-checked');
            });
        });
    }
    syncXferAll();
});

/* Block submit if validation fails */
document.getElementById('transferForm').addEventListener('submit', function(e) {
    var qty = parseInt(document.getElementById('transfer_quantity').value, 10) || 0;
    var max = _xferMaxQty;

    if (qty < 1 || qty > max) {
        e.preventDefault();
        var errEl = document.getElementById('transfer_qty_error');
        if (errEl) errEl.textContent = 'Please enter a valid quantity (1 – ' + max + ').';
        document.getElementById('transfer_quantity').classList.add('is-invalid');
        return;
    }

    var areChecked    = getXferCheckedValues(document.getElementById('xfer_are_list'));
    var propChecked   = getXferCheckedValues(document.getElementById('xfer_prop_list'));
    var serialChecked = getXferCheckedValues(document.getElementById('xfer_serial_list'));

    var hasAre    = _xferAres.length > 0;
    var hasProp   = _xferProps.length > 0;
    var hasSerial = _xferSerials.length > 0;

    var areOk    = !hasAre    || areChecked.length    === qty;
    var propOk   = !hasProp   || propChecked.length   === qty;
    var serialOk = !hasSerial || serialChecked.length === qty;

    if (!areOk || !propOk || !serialOk) {
        e.preventDefault();
        var msgs = [];
        if (!areOk)    msgs.push('PAR/ICS/ARE numbers');
        if (!propOk)   msgs.push('property numbers');
        if (!serialOk) msgs.push('serial numbers');
        alert('The number of checked ' + msgs.join(', ') + ' must equal the transfer quantity (' + qty + ').');
        syncXferAll();
    }
});

/* ============================================================
   DELETE MODAL
   ============================================================ */
function openDeleteModal(el) {
    var d = el.dataset;
    document.getElementById('delete_id').value                = d.id;
    document.getElementById('delete_item_display').textContent   = d.itemName;
    document.getElementById('delete_person_display').textContent = 'Custodian: ' + d.personName;
    document.getElementById('delete_qty_display').textContent    = d.qty;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

/* ============================================================
   RETURN TO CGSO MODAL
   ============================================================ */
var _rtcMaxQty     = 0;
var _rtcAllSerials = [];

function parseSerials(raw) {
    if (!raw || raw.trim() === '') return [];
    return raw.split('/').map(function (s) { return s.trim(); }).filter(Boolean);
}

function openReturnToCgsoModal(el) {
    var d          = el.dataset;
    var id         = parseInt(d.id,     10);
    var itemName   = d.itemName;
    var personName = d.personName;
    var maxQty     = parseInt(d.maxQty, 10);
    var rawSerials = d.serial;

    _rtcMaxQty     = maxQty;
    _rtcAllSerials = parseSerials(rawSerials);

    document.getElementById('rtc_accountable_id').value        = id;
    document.getElementById('rtc_selected_serials').value      = '';
    document.getElementById('rtc_item_name_display').textContent = itemName;
    document.getElementById('rtc_person_display').textContent    = personName;
    document.getElementById('rtc_max_label').textContent         = '(max: ' + maxQty + ')';

    var qtyInput = document.getElementById('rtc_quantity');
    qtyInput.max   = maxQty;
    qtyInput.value = '';
    qtyInput.classList.remove('is-invalid', 'is-valid');
    document.getElementById('rtc_qty_bar').style.width   = '0%';
    document.getElementById('rtc_qty_label').textContent = '0 of ' + maxQty + ' units selected';
    document.getElementById('rtcSubmitBtn').disabled     = true;
    document.getElementById('rtc_preview').classList.add('d-none');

    var listEl = document.getElementById('rtc_serial_list');
    var noteEl = document.getElementById('rtc_serial_note');
    var syncEl = document.getElementById('rtc_serial_sync_info');
    listEl.innerHTML = '';

    if (_rtcAllSerials.length === 0) {
        noteEl.classList.remove('d-none');
        syncEl.classList.add('d-none');
    } else {
        noteEl.classList.add('d-none');
        syncEl.classList.remove('d-none');
        _rtcAllSerials.forEach(function (sn, idx) {
            var col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4';
            col.innerHTML =
                '<div class="form-check border rounded px-3 py-2 bg-light">' +
                    '<input class="form-check-input rtc-serial-cb" type="checkbox" ' +
                           'value="' + escAttr(sn) + '" id="rtc_sn_' + idx + '">' +
                    '<label class="form-check-label font-monospace small" for="rtc_sn_' + idx + '">' +
                        escHtml(sn) +
                    '</label>' +
                '</div>';
            listEl.appendChild(col);
            col.querySelector('.rtc-serial-cb').addEventListener('change', syncRtcState);
        });
    }

    syncRtcState();
    new bootstrap.Modal(document.getElementById('returnToCgsoModal')).show();
}

function getCheckedSerials() {
    return Array.from(document.querySelectorAll('.rtc-serial-cb:checked')).map(function (cb) { return cb.value; });
}

function syncRtcState() {
    var qty     = parseInt(document.getElementById('rtc_quantity').value, 10) || 0;
    var max     = _rtcMaxQty;
    var checked = getCheckedSerials();
    var bar     = document.getElementById('rtc_qty_bar');
    var lbl     = document.getElementById('rtc_qty_label');
    var btn     = document.getElementById('rtcSubmitBtn');
    var prev    = document.getElementById('rtc_preview');
    var prevTx  = document.getElementById('rtc_preview_text');

    if (max > 0 && qty > 0) {
        var pct = Math.min(100, Math.round((qty / max) * 100));
        bar.style.width = pct + '%';
        lbl.textContent = qty + ' of ' + max + ' units selected';
    } else {
        bar.style.width = '0%';
        lbl.textContent = '0 of ' + max + ' units selected';
    }

    var qtyValid    = qty >= 1 && qty <= max;
    var hasSerials  = _rtcAllSerials.length > 0;
    var serialValid = !hasSerials || (checked.length === qty);
    btn.disabled    = !(qtyValid && serialValid);

    document.getElementById('rtc_selected_serials').value = JSON.stringify(checked);

    if (qtyValid) {
        var remaining = max - qty;
        var retSer    = checked.length > 0 ? checked.join(' / ') + ' /' : '(none recorded)';
        var html = qty + ' unit(s) will be returned to CGSO.';
        if (hasSerials) html += ' Serials: <code>' + escHtml(retSer) + '</code>.';
        html += ' Remaining: <strong>' + remaining + '</strong> unit(s) with custodian.';
        if (hasSerials && !serialValid) {
            html += ' <span class="text-danger">⚠ Select exactly ' + qty + ' serial(s).</span>';
        }
        prevTx.innerHTML = html;
        prev.classList.remove('d-none');
    } else {
        prev.classList.add('d-none');
    }
}

document.getElementById('rtc_quantity').addEventListener('input', syncRtcState);

/* Return to Property Custodian condition toggle */
(function () {
    var condSel   = document.getElementById('rtcConditionSelect');
    var panel     = document.getElementById('rtc_property_custodian_panel');
    var propBtn   = document.getElementById('rtcPropertyCustodianBtn');
    var submitBtn = document.getElementById('rtcSubmitBtn');

    function handleConditionChange() {
        var isProp = condSel.value === 'Return to Property Custodian';
        panel.classList.toggle('d-none', !isProp);
        submitBtn.classList.toggle('d-none', isProp);
    }

    condSel.addEventListener('change',   handleConditionChange);
    condSel.addEventListener('focusout', handleConditionChange);

    propBtn.addEventListener('click', function () {
        var qty = parseInt(document.getElementById('rtc_quantity').value, 10) || 0;
        var max = _rtcMaxQty;
        if (qty < 1 || qty > max) {
            document.getElementById('rtc_quantity').classList.add('is-invalid');
            document.getElementById('rtc_qty_error').textContent =
                'Please enter a valid quantity (1 – ' + max + ').';
            return;
        }
        var hasSerials = _rtcAllSerials.length > 0;
        var checked    = getCheckedSerials();
        if (hasSerials && checked.length !== qty) {
            alert('Please select exactly ' + qty + ' serial number(s) matching the quantity.');
            return;
        }
        document.getElementById('rtcForm').submit();
    });

    document.getElementById('returnToCgsoModal').addEventListener('show.bs.modal', function () {
        panel.classList.add('d-none');
        submitBtn.classList.remove('d-none');
        condSel.value = 'Returned to CGSO';
    });
}());
</script>
</body>
</html>