<?php
/**
 * borrow_items.php — Major Revision
 * ====================================
 * Changes over previous version:
 *
 *  1. SERIAL NUMBER PICKER in the Borrow modal
 *     — fetched live via AJAX from borrow_items_api.php
 *     — displayed as a scrollable checklist (one checkbox per serial)
 *     — quantity auto-locks to the number of selected serials
 *     — selected serials stored in borrowed_items.serial_number as " / "-joined string
 *     — server-side validates each selected serial against the accountable item's list
 *
 *  2. RICH RETURN MODAL for STAFF
 *     — shows: item, borrowed qty, borrowed serials, from_person, accountable holder
 *     — ADMIN/MANAGER are never shown this modal; they use the inline approve/deny buttons
 *     — remarks field added (stored in notification payload + system_log)
 *     — confirmation panel clearly states where the item is being returned to
 *
 *  3. AJAX LIVE SEARCH (debounced 300 ms) on both tables
 *     — Available Items:      calls borrow_items_api.php → re-renders tbody + pagination
 *     — Active Transactions:  calls borrow_items_api.php → re-renders tbody (no hard reload)
 *     — Both render functions support full action buttons (Approve/Deny/Return) client-side
 *
 *  All pre-existing security rules (CSRF, role enforcement, self-only STAFF, tamper-detect),
 *  audit_logs, system_logs, inventory_transactions, and push notifications are retained.
 */
ob_start();

require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';
require_once __DIR__ . '/../includes/notify_push.php';
require_once __DIR__ . '/../includes/system_log_helper.php';

$userId   = (int)($_SESSION['id'] ?? 0);
$userRole = strtoupper($_SESSION['role'] ?? 'STAFF');

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validate_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function jsonResponse(bool $success, string $message, array $data = []): never
{
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ============================================================
// Pre-fetch the logged-in staff's full name from `user` table
// ============================================================
$currentUserFullName = '';
$currentUserStmt = $conn->prepare(
    "SELECT CONCAT_WS(' ', first_name, last_name) AS full_name FROM `user` WHERE id = ?"
);
$currentUserStmt->bind_param('i', $userId);
$currentUserStmt->execute();
$currentUserRow = $currentUserStmt->get_result()->fetch_assoc();
$currentUserStmt->close();
$currentUserFullName = $currentUserRow['full_name'] ?? 'Unknown';

// ============================================================
// AJAX / POST handlers
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Security validation failed.');
    }

    // ----------------------------------------------------------
    // ACTION: borrow
    // ----------------------------------------------------------
    if ($action === 'borrow') {
        $accountable_id = filter_input(INPUT_POST, 'accountable_id', FILTER_VALIDATE_INT);
        $qty            = filter_input(INPUT_POST, 'borrow_quantity', FILTER_VALIDATE_INT);
        $remarks        = trim($_POST['remarks'] ?? '');

        // Gather selected serials (may be empty if item has no serial numbers)
        $selected_serials_raw = $_POST['selected_serials'] ?? [];
        $selected_serials_raw = is_array($selected_serials_raw) ? $selected_serials_raw : [];

        if (!$accountable_id) {
            jsonResponse(false, 'Missing required fields.');
        }

        $conn->begin_transaction();
        try {
            // ---- Borrower identity ----
            if ($userRole === 'STAFF') {
                $borrower_id   = $userId;
                $borrower_name = $currentUserFullName;

                $submitted_id = filter_input(INPUT_POST, 'borrower_employee_id', FILTER_VALIDATE_INT);
                if ($submitted_id && $submitted_id !== $userId) {
                    log_notif_action($conn, $userId, 'borrower_field_tamper_attempt', [
                        'submitted_borrower_id' => $submitted_id,
                        'enforced_to'           => $userId,
                        'note'                  => 'STAFF submitted a different borrower_employee_id; overridden server-side',
                    ], 'borrow_items');
                }
            } else {
                $borrower_id = filter_input(INPUT_POST, 'borrower_employee_id', FILTER_VALIDATE_INT);
                if (!$borrower_id) {
                    $conn->rollback();
                    jsonResponse(false, 'Please select a borrower.');
                }

                $stmt = $conn->prepare(
                    "SELECT CONCAT_WS(' ', FIRSTNAME, LASTNAME) AS full_name
                     FROM cao_employee WHERE ID = ? AND DELETED = 0"
                );
                $stmt->bind_param('i', $borrower_id);
                $stmt->execute();
                $empRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$empRow) {
                    $conn->rollback();
                    jsonResponse(false, 'Selected employee not found.');
                }
                $borrower_name = $empRow['full_name'];
            }

            // ---- Lock + fetch accountable item ----
            $stmt = $conn->prepare(
                "SELECT ai.*, ii.item_name
                 FROM accountable_items ai
                 JOIN inventory_items ii ON ii.id = ai.inventory_item_id
                 WHERE ai.id = ? AND ai.is_deleted = 0 FOR UPDATE"
            );
            $stmt->bind_param('i', $accountable_id);
            $stmt->execute();
            $acc = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$acc) throw new Exception('Item not found.');

            // ---- Resolve serial numbers ----
            $available_serials = [];
            if (!empty(trim((string)$acc['serial_number']))) {
                $available_serials = array_values(
                    array_filter(array_map('trim', explode('/', (string)$acc['serial_number'])))
                );
            }

            if (count($selected_serials_raw) > 0) {
                // Validate each submitted serial against what's actually on the accountable item
                $selected_serials_clean = array_values(
                    array_filter(array_map('trim', $selected_serials_raw))
                );
                foreach ($selected_serials_clean as $sn) {
                    if (!in_array($sn, $available_serials, true)) {
                        throw new Exception("Serial number '{$sn}' is not available on this item.");
                    }
                }
                // Quantity is driven by the number of selected serials
                $qty = count($selected_serials_clean);
                $serial_str = implode(' / ', $selected_serials_clean);
            } else {
                // No serial picker used — fall back to free-entry quantity
                $serial_str = $acc['serial_number'] ?? '';
                if (!$qty || $qty <= 0) {
                    throw new Exception('Missing required fields or invalid quantity.');
                }
            }

            if ($qty > $acc['assigned_quantity']) {
                throw new Exception("Only {$acc['assigned_quantity']} unit(s) available.");
            }

            $ref_no = 'BORROW-' . date('YmdHis') . '-' . rand(1000, 9999);
            $status = in_array($userRole, ['ADMIN', 'MANAGER'], true) ? 'APPROVED' : 'PENDING';

            $sql = "INSERT INTO borrowed_items (
                        accountable_id, inventory_item_id, from_person, to_person, borrower_employee_id,
                        quantity, are_mr_ics_num, property_number, serial_number, po_number,
                        account_code, old_account_code, reference_no, remarks, status,
                        requested_by, requested_at, borrow_date"
                 . ($status === 'APPROVED' ? ', approved_by, approved_at' : '')
                 . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()"
                 . ($status === 'APPROVED' ? ', ?, NOW()' : '')
                 . ')';

            $types  = 'iissiisssssssssi' . ($status === 'APPROVED' ? 'i' : '');
            $params = [
                $accountable_id, $acc['inventory_item_id'],
                $acc['person_name'], $borrower_name, $borrower_id,
                $qty,
                $acc['are_mr_ics_num'], $acc['property_number'],
                $serial_str,
                $acc['po_number'], $acc['account_code'], $acc['old_account_code'],
                $ref_no, $remarks, $status, $userId,
            ];
            if ($status === 'APPROVED') $params[] = $userId;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $newBorrowId = (int)$conn->insert_id;
            $stmt->close();

            if ($status === 'APPROVED') {
                // Decrement quantity
                $conn->query(
                    "UPDATE accountable_items
                     SET assigned_quantity = assigned_quantity - {$qty}
                     WHERE id = {$accountable_id}"
                );

                // Remove borrowed serials from accountable_items.serial_number
                if (!empty($selected_serials_clean)) {
                    $rem_acc = array_values(array_filter($available_serials, function ($s) use ($selected_serials_clean) {
                        return !in_array($s, $selected_serials_clean, true);
                    }));
                    $new_acc_ser = count($rem_acc) ? implode(' / ', $rem_acc) . ' /' : '';
                    $sUpd = $conn->prepare('UPDATE accountable_items SET serial_number = ? WHERE id = ?');
                    $sUpd->bind_param('si', $new_acc_ser, $accountable_id);
                    $sUpd->execute();
                    $sUpd->close();
                }

                $log = $conn->prepare(
                    "INSERT INTO inventory_transactions
                         (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
                     VALUES (?, 'OUT', ?, ?, NOW())"
                );
                $log->bind_param('iis', $acc['inventory_item_id'], $qty, $ref_no);
                $log->execute();
                $log->close();

                $actorLabelDirect = ucfirst(strtolower($userRole));
                log_system($conn, $userId, 'BORROW_APPROVED_DIRECT',
                    "{$actorLabelDirect} #{$userId} directly approved borrow #{$newBorrowId} — {$acc['item_name']} x{$qty} to {$borrower_name} (ref: {$ref_no})",
                    ['borrow_id' => $newBorrowId, 'ref' => $ref_no, 'qty' => $qty, 'serials' => $serial_str, 'actor_role' => $userRole]
                );
                log_audit($conn, $userId, 'borrowed_items', 'CREATE', $newBorrowId, null, [
                    'status'       => 'APPROVED', 'reference_no' => $ref_no,
                    'item'         => $acc['item_name'], 'qty' => $qty,
                    'serials'      => $serial_str,
                    'borrower'     => $borrower_name, 'actor_role' => $userRole,
                ]);
                log_notif_action($conn, $userId, 'borrow_approved_direct', [
                    'borrow_id' => $newBorrowId, 'ref' => $ref_no,
                    'item'      => $acc['item_name'], 'qty' => $qty,
                    'serials'   => $serial_str, 'borrower' => $borrower_name,
                    'actor_role' => $userRole,
                ], 'borrow_items');

                $notifyRoles = ($userRole === 'MANAGER') ? ['ADMIN'] : ['MANAGER'];
                notify_roles($conn, $notifyRoles, $userId, 'borrow_approved_direct_info', $newBorrowId, [
                    'reference' => $ref_no, 'borrow_id' => $newBorrowId,
                    'item'      => $acc['item_name'], 'qty' => $qty,
                    'serials'   => $serial_str, 'borrower' => $borrower_name,
                    'message'   => "{$actorLabelDirect} directly borrowed {$acc['item_name']} x{$qty} for {$borrower_name} (ref: {$ref_no})",
                ], $userId);

            } else {
                $notifPayload = [
                    'reference'            => $ref_no,
                    'borrow_id'            => $newBorrowId,
                    'item'                 => $acc['item_name'],
                    'qty'                  => $qty,
                    'serials'              => $serial_str,
                    'borrower'             => $borrower_name,
                    'requested_by_user_id' => $userId,
                    'message'              => "Borrow request {$ref_no} — {$acc['item_name']} x{$qty} by {$borrower_name}",
                ];
                notify_roles($conn, ['ADMIN', 'MANAGER'], $userId, 'borrow_request', $newBorrowId, $notifPayload);
                @push_borrow_update_ws($newBorrowId, 'PENDING', $notifPayload);

                log_system($conn, $userId, 'BORROW_REQUEST',
                    "Staff #{$userId} ({$borrower_name}) submitted borrow request #{$newBorrowId} — {$acc['item_name']} x{$qty} serials:[{$serial_str}] (ref: {$ref_no})",
                    ['borrow_id' => $newBorrowId, 'ref' => $ref_no, 'serials' => $serial_str]
                );
                log_audit($conn, $userId, 'borrowed_items', 'CREATE', $newBorrowId, null, [
                    'status'      => 'PENDING', 'reference_no' => $ref_no,
                    'item'        => $acc['item_name'], 'qty' => $qty,
                    'serials'     => $serial_str, 'borrower' => $borrower_name,
                ]);
                log_notif_action($conn, $userId, 'borrow_request_submitted', [
                    'borrow_id' => $newBorrowId, 'ref' => $ref_no,
                    'item'      => $acc['item_name'], 'qty' => $qty, 'serials' => $serial_str,
                ], 'borrow_items');
            }

            $conn->commit();
            jsonResponse(true, $status === 'APPROVED'
                ? 'Item borrowed successfully!'
                : 'Request submitted for approval.');
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // ACTION: return
    // ----------------------------------------------------------
    if ($action === 'return') {
        $borrow_id     = filter_input(INPUT_POST, 'return_id', FILTER_VALIDATE_INT);
        $qty           = filter_input(INPUT_POST, 'return_quantity', FILTER_VALIDATE_INT);
        $return_remarks = trim($_POST['return_remarks'] ?? '');

        if (!$borrow_id || !$qty || $qty <= 0) jsonResponse(false, 'Invalid quantity.');

        if (!in_array($userRole, ['ADMIN', 'MANAGER'], true)) {
            // STAFF: flag as RETURN_PENDING and notify ADMIN + MANAGER
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    "SELECT b.*, ii.item_name FROM borrowed_items b
                     JOIN inventory_items ii ON ii.id = b.inventory_item_id
                     WHERE b.borrow_id = ? FOR UPDATE"
                );
                $stmt->bind_param('i', $borrow_id);
                $stmt->execute();
                $b = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$b)                         throw new Exception('Borrow record not found.');
                if ($b['status'] !== 'APPROVED') throw new Exception('Only approved borrows can be returned.');
                if ((int)$b['requested_by'] !== $userId) {
                    log_notif_action($conn, $userId, 'return_unauthorized_attempt', [
                        'borrow_id' => $borrow_id, 'owner_id' => $b['requested_by'], 'actor_id' => $userId,
                    ], 'borrow_items');
                    throw new Exception('You can only return items you have borrowed.');
                }

                $stmt = $conn->prepare(
                    "UPDATE borrowed_items
                     SET status = 'RETURN_PENDING',
                         return_requested_by = ?,
                         return_requested_at = NOW()
                     WHERE borrow_id = ?"
                );
                $stmt->bind_param('ii', $userId, $borrow_id);
                $stmt->execute();
                $stmt->close();

                $notifPayload = [
                    'message'              => "Return request for Borrow #{$borrow_id}",
                    'quantity'             => $qty,
                    'borrow_id'            => $borrow_id,
                    'item'                 => $b['item_name'],
                    'reference'            => $b['reference_no'] ?? null,
                    'serial_number'        => $b['serial_number'] ?? '',
                    'from_person'          => $b['from_person'],
                    'requested_by_user_id' => $userId,
                    'requester_name'       => $currentUserFullName,
                    'return_remarks'       => $return_remarks,
                ];
                notify_roles($conn, ['ADMIN', 'MANAGER'], $userId, 'return_request', $borrow_id, $notifPayload);
                @push_borrow_update_ws($borrow_id, 'RETURN_PENDING', $notifPayload);

                log_system($conn, $userId, 'RETURN_REQUEST',
                    "Staff #{$userId} ({$currentUserFullName}) submitted return request for Borrow #{$borrow_id} — {$b['item_name']} x{$qty} serials:[{$b['serial_number']}] returning to {$b['from_person']} (ref: {$b['reference_no']})",
                    ['borrow_id' => $borrow_id, 'qty' => $qty, 'from_person' => $b['from_person'], 'return_remarks' => $return_remarks]
                );
                log_audit($conn, $userId, 'borrowed_items', 'UPDATE', $borrow_id,
                    ['status' => 'APPROVED'],
                    ['status' => 'RETURN_PENDING', 'return_requested_by' => $userId, 'return_remarks' => $return_remarks]
                );
                log_notif_action($conn, $userId, 'return_request_submitted', [
                    'borrow_id' => $borrow_id, 'qty' => $qty,
                    'item'      => $b['item_name'], 'ref' => $b['reference_no'],
                    'serials'   => $b['serial_number'] ?? '',
                    'from_person'    => $b['from_person'],
                    'return_remarks' => $return_remarks,
                ], 'borrow_items');

                $conn->commit();
                jsonResponse(true, 'Return request submitted for admin approval.');
            } catch (Exception $e) {
                $conn->rollback();
                jsonResponse(false, $e->getMessage());
            }
        }

        // ADMIN / MANAGER immediate return
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT b.*, ii.item_name FROM borrowed_items b
                 JOIN inventory_items ii ON ii.id = b.inventory_item_id
                 WHERE b.borrow_id = ? FOR UPDATE"
            );
            $stmt->bind_param('i', $borrow_id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$b)             throw new Exception('Borrow record not found.');
            if ($qty > $b['quantity']) throw new Exception('Cannot return more than borrowed.');

            $conn->query(
                "UPDATE accountable_items
                 SET assigned_quantity = assigned_quantity + {$qty}
                 WHERE id = {$b['accountable_id']}"
            );
            $is_full   = ($qty === (int)$b['quantity']);
            $newStatus = $is_full ? 'RETURNED' : 'APPROVED';
            $is_ret    = $is_full ? 1 : 0;

            $stmt = $conn->prepare(
                "UPDATE borrowed_items
                 SET quantity = quantity - ?, is_returned = ?, return_date = NOW(), status = ?
                 WHERE borrow_id = ?"
            );
            $stmt->bind_param('iisi', $qty, $is_ret, $newStatus, $borrow_id);
            $stmt->execute();
            $stmt->close();

            $ref = 'RETURN-' . date('YmdHis') . '-' . rand(1000, 9999);
            $stmt = $conn->prepare(
                "INSERT INTO inventory_transactions
                     (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
                 VALUES (?, 'IN', ?, ?, NOW())"
            );
            $stmt->bind_param('iis', $b['inventory_item_id'], $qty, $ref);
            $stmt->execute();
            $stmt->close();

            if (!empty($b['requested_by'])) {
                $reqId      = (int)$b['requested_by'];
                $retPayload = ['borrow_id' => $borrow_id, 'qty' => $qty, 'reference' => $ref, 'item' => $b['item_name']];
                $ins = $conn->prepare(
                    "INSERT INTO notifications
                         (user_id, actor_user_id, type, related_id, payload, is_read, action, action_by, action_at, created_at)
                     VALUES (?, ?, 'return_approved', ?, ?, 0, 'APPROVED', ?, NOW(), NOW())"
                );
                if ($ins) {
                    $pj = json_encode($retPayload);
                    $ins->bind_param('iiisi', $reqId, $userId, $borrow_id, $pj, $userId);
                    $ins->execute();
                    $ins->close();
                }
                @push_notification_ws($reqId, 'return_approved', $borrow_id, $retPayload);
            }

            $retNotifyRoles = ($userRole === 'MANAGER') ? ['ADMIN'] : ['MANAGER'];
            notify_roles($conn, $retNotifyRoles, $userId, 'return_processed_info', $borrow_id, [
                'borrow_id' => $borrow_id, 'item' => $b['item_name'],
                'qty' => $qty, 'ref' => $ref,
                'message' => ucfirst(strtolower($userRole)) . " directly processed return for Borrow #{$borrow_id}",
            ], $userId);

            log_system($conn, $userId, 'RETURN_PROCESSED',
                ucfirst(strtolower($userRole)) . " #{$userId} directly processed return for Borrow #{$borrow_id} — {$b['item_name']} x{$qty} (ref: {$ref})",
                ['borrow_id' => $borrow_id, 'qty' => $qty, 'status' => $newStatus, 'actor_role' => $userRole]
            );
            log_audit($conn, $userId, 'borrowed_items', 'UPDATE', $borrow_id,
                ['status' => $b['status'], 'quantity' => $b['quantity'], 'is_returned' => $b['is_returned']],
                ['status' => $newStatus, 'quantity' => $b['quantity'] - $qty, 'is_returned' => $is_ret]
            );
            log_notif_action($conn, $userId, 'return_processed_direct', [
                'borrow_id' => $borrow_id, 'qty' => $qty,
                'item' => $b['item_name'], 'ref' => $ref, 'status' => $newStatus,
            ], 'borrow_items');

            $conn->commit();
            jsonResponse(true, 'Return processed successfully.');
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // ACTION: admin_approve
    // ----------------------------------------------------------
    if ($action === 'admin_approve') {
        $borrow_id = filter_input(INPUT_POST, 'borrow_id', FILTER_VALIDATE_INT);
        if (!$borrow_id || !in_array($userRole, ['ADMIN', 'MANAGER'], true)) jsonResponse(false, 'Unauthorized.');

        $actorLabel = ucfirst(strtolower($userRole));
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT b.*, ii.item_name FROM borrowed_items b
                 JOIN inventory_items ii ON ii.id = b.inventory_item_id
                 WHERE b.borrow_id = ? AND b.status = 'PENDING' FOR UPDATE"
            );
            $stmt->bind_param('i', $borrow_id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$b) throw new Exception('Record not found or already processed.');

            $stmt = $conn->prepare(
                "SELECT assigned_quantity FROM accountable_items WHERE id = ? FOR UPDATE"
            );
            $stmt->bind_param('i', $b['accountable_id']);
            $stmt->execute();
            $accRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$accRow || (int)$accRow['assigned_quantity'] < (int)$b['quantity']) {
                throw new Exception('Insufficient stock to approve this request.');
            }

            $newQty = (int)$accRow['assigned_quantity'] - (int)$b['quantity'];
            $conn->query("UPDATE accountable_items SET assigned_quantity = {$newQty} WHERE id = {$b['accountable_id']}");

            $stmt = $conn->prepare(
                "UPDATE borrowed_items
                 SET status = 'APPROVED', approved_by = ?, approved_at = NOW(), decision_remarks = ''
                 WHERE borrow_id = ?"
            );
            $stmt->bind_param('ii', $userId, $borrow_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO inventory_transactions
                     (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
                 VALUES (?, 'OUT', ?, ?, NOW())"
            );
            $stmt->bind_param('iis', $b['inventory_item_id'], $b['quantity'], $b['reference_no']);
            $stmt->execute();
            $stmt->close();

            if (!empty($b['requested_by'])) {
                $reqId        = (int)$b['requested_by'];
                $notifPayload = ['borrow_id' => $borrow_id, 'reference' => $b['reference_no'], 'item' => $b['item_name']];
                $pj           = json_encode($notifPayload);
                $ins = $conn->prepare(
                    "INSERT INTO notifications
                         (user_id, actor_user_id, type, related_id, payload, is_read, action, action_by, action_at, created_at)
                     VALUES (?, ?, 'borrow_approved', ?, ?, 0, 'APPROVED', ?, NOW(), NOW())"
                );
                if ($ins) { $ins->bind_param('iiisi', $reqId, $userId, $borrow_id, $pj, $userId); $ins->execute(); $ins->close(); }
                @push_notification_ws($reqId, 'borrow_approved', $borrow_id, $notifPayload);
                @push_borrow_update_ws($borrow_id, 'APPROVED', $notifPayload);
            }

            $crossRoles = ($userRole === 'MANAGER') ? ['ADMIN'] : ['MANAGER'];
            notify_roles($conn, $crossRoles, $userId, 'borrow_approved_info', $borrow_id, [
                'reference' => $b['reference_no'], 'borrow_id' => $borrow_id,
                'item'      => $b['item_name'], 'qty' => $b['quantity'],
                'message'   => "Borrow #{$borrow_id} approved by {$actorLabel} #{$userId}",
            ], $userId);

            $upd = $conn->prepare(
                "UPDATE notifications SET action = 'APPROVED', action_by = ?, action_at = NOW(), is_read = 1
                 WHERE type = 'borrow_request' AND related_id = ?"
            );
            if ($upd) { $upd->bind_param('ii', $userId, $borrow_id); $upd->execute(); $upd->close(); }

            log_system($conn, $userId, 'BORROW_APPROVED',
                "{$actorLabel} #{$userId} approved Borrow #{$borrow_id} — {$b['item_name']} x{$b['quantity']} (ref: {$b['reference_no']})",
                ['borrow_id' => $borrow_id, 'actor_role' => $userRole]
            );
            log_audit($conn, $userId, 'borrowed_items', 'UPDATE', $borrow_id,
                ['status' => 'PENDING'],
                ['status' => 'APPROVED', 'approved_by' => $userId, 'actor_role' => $userRole]
            );
            log_notif_action($conn, $userId, 'borrow_approved', [
                'borrow_id' => $borrow_id, 'item' => $b['item_name'],
                'qty' => $b['quantity'], 'ref' => $b['reference_no'], 'actor_role' => $userRole,
            ], 'borrow_items');

            $conn->commit();
            jsonResponse(true, 'Borrow request approved.');
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // ACTION: admin_deny
    // ----------------------------------------------------------
    if ($action === 'admin_deny') {
        $borrow_id = filter_input(INPUT_POST, 'borrow_id', FILTER_VALIDATE_INT);
        $remarks   = trim($_POST['remarks'] ?? '');
        if (!$borrow_id || !in_array($userRole, ['ADMIN', 'MANAGER'], true)) jsonResponse(false, 'Unauthorized.');

        $actorLabel = ucfirst(strtolower($userRole));
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT b.*, ii.item_name FROM borrowed_items b
                 JOIN inventory_items ii ON ii.id = b.inventory_item_id
                 WHERE b.borrow_id = ? AND b.status = 'PENDING' FOR UPDATE"
            );
            $stmt->bind_param('i', $borrow_id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$b) throw new Exception('Record not found or already processed.');

            $stmt = $conn->prepare(
                "UPDATE borrowed_items
                 SET status = 'DENIED', approved_by = ?, approved_at = NOW(), decision_remarks = ?
                 WHERE borrow_id = ?"
            );
            $stmt->bind_param('isi', $userId, $remarks, $borrow_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($b['requested_by'])) {
                $reqId        = (int)$b['requested_by'];
                $notifPayload = ['borrow_id' => $borrow_id, 'reason' => $remarks, 'item' => $b['item_name']];
                $pj           = json_encode($notifPayload);
                $ins = $conn->prepare(
                    "INSERT INTO notifications
                         (user_id, actor_user_id, type, related_id, payload, is_read, action, action_by, action_at, created_at)
                     VALUES (?, ?, 'borrow_denied', ?, ?, 0, 'DENIED', ?, NOW(), NOW())"
                );
                if ($ins) { $ins->bind_param('iiisi', $reqId, $userId, $borrow_id, $pj, $userId); $ins->execute(); $ins->close(); }
                @push_notification_ws($reqId, 'borrow_denied', $borrow_id, $notifPayload);
                @push_borrow_update_ws($borrow_id, 'DENIED', $notifPayload);
            }

            $crossRoles = ($userRole === 'MANAGER') ? ['ADMIN'] : ['MANAGER'];
            notify_roles($conn, $crossRoles, $userId, 'borrow_denied_info', $borrow_id, [
                'borrow_id' => $borrow_id, 'item' => $b['item_name'], 'reason' => $remarks,
                'message'   => "Borrow #{$borrow_id} denied by {$actorLabel} #{$userId}",
            ], $userId);

            $upd = $conn->prepare(
                "UPDATE notifications SET action = 'DENIED', action_by = ?, action_at = NOW(), is_read = 1
                 WHERE type = 'borrow_request' AND related_id = ?"
            );
            if ($upd) { $upd->bind_param('ii', $userId, $borrow_id); $upd->execute(); $upd->close(); }

            log_system($conn, $userId, 'BORROW_DENIED',
                "{$actorLabel} #{$userId} denied Borrow #{$borrow_id} — {$b['item_name']}. Reason: {$remarks}",
                ['borrow_id' => $borrow_id, 'actor_role' => $userRole]
            );
            log_audit($conn, $userId, 'borrowed_items', 'UPDATE', $borrow_id,
                ['status' => 'PENDING'],
                ['status' => 'DENIED', 'decision_remarks' => $remarks, 'actor_role' => $userRole]
            );
            log_notif_action($conn, $userId, 'borrow_denied', [
                'borrow_id' => $borrow_id, 'item' => $b['item_name'], 'reason' => $remarks, 'actor_role' => $userRole,
            ], 'borrow_items');

            $conn->commit();
            jsonResponse(true, 'Borrow request denied.');
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // ACTION: admin_approve_return
    // ----------------------------------------------------------
    if ($action === 'admin_approve_return') {
        $borrow_id      = filter_input(INPUT_POST, 'borrow_id', FILTER_VALIDATE_INT);
        $ret_remarks    = trim($_POST['return_decision_remarks'] ?? '');
        if (!$borrow_id || !in_array($userRole, ['ADMIN', 'MANAGER'], true)) jsonResponse(false, 'Unauthorized.');

        $actorLabel = ucfirst(strtolower($userRole));
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT b.*, ii.item_name FROM borrowed_items b
                 JOIN inventory_items ii ON ii.id = b.inventory_item_id
                 WHERE b.borrow_id = ? AND b.status = 'RETURN_PENDING' FOR UPDATE"
            );
            $stmt->bind_param('i', $borrow_id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$b) throw new Exception('Record not found or not pending return.');

            $qty = (int)$b['quantity'];

            // ── Restore serial numbers back to accountable_items ──────────────────
            $borrowed_serials = [];
            if (!empty(trim((string)$b['serial_number']))) {
                $borrowed_serials = array_values(array_filter(
                    array_map('trim', explode('/', (string)$b['serial_number']))
                ));
            }
            if (count($borrowed_serials) > 0) {
                $acc_s = $conn->prepare('SELECT serial_number FROM accountable_items WHERE id = ? FOR UPDATE');
                $acc_s->bind_param('i', $b['accountable_id']);
                $acc_s->execute();
                $acc_row = $acc_s->get_result()->fetch_assoc();
                $acc_s->close();
                $cur_serials = [];
                if (!empty(trim((string)($acc_row['serial_number'] ?? '')))) {
                    $cur_serials = array_values(array_filter(
                        array_map('trim', explode('/', (string)$acc_row['serial_number']))
                    ));
                }
                $merged_serials = array_unique(array_merge($cur_serials, $borrowed_serials));
                $restored_ser   = implode(' / ', $merged_serials) . ' /';
                $sRest = $conn->prepare('UPDATE accountable_items SET serial_number = ?, assigned_quantity = assigned_quantity + ? WHERE id = ?');
                $sRest->bind_param('sii', $restored_ser, $qty, $b['accountable_id']);
                $sRest->execute();
                $sRest->close();
            } else {
                // No serials — just restore quantity
                $conn->query("UPDATE accountable_items SET assigned_quantity = assigned_quantity + {$qty} WHERE id = {$b['accountable_id']}");
            }

            $stmt = $conn->prepare(
                "UPDATE borrowed_items
                 SET is_returned = 1, return_date = NOW(), status = 'RETURNED',
                     return_approved_by = ?, return_approved_at = NOW(),
                     return_decision_remarks = ?
                 WHERE borrow_id = ?"
            );
            $stmt->bind_param('isi', $userId, $ret_remarks, $borrow_id);
            $stmt->execute();
            $stmt->close();

            $ref = 'RETURN-' . date('YmdHis') . '-' . rand(1000, 9999);
            $stmt = $conn->prepare(
                "INSERT INTO inventory_transactions
                     (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
                 VALUES (?, 'IN', ?, ?, NOW())"
            );
            $stmt->bind_param('iis', $b['inventory_item_id'], $qty, $ref);
            $stmt->execute();
            $stmt->close();

            $reqId = (int)($b['return_requested_by'] ?? $b['requested_by'] ?? 0);
            if ($reqId) {
                $retPayload = ['borrow_id' => $borrow_id, 'qty' => $qty, 'reference' => $ref, 'item' => $b['item_name']];
                $pj = json_encode($retPayload);
                $ins = $conn->prepare(
                    "INSERT INTO notifications
                         (user_id, actor_user_id, type, related_id, payload, is_read, action, action_by, action_at, created_at)
                     VALUES (?, ?, 'return_approved', ?, ?, 0, 'APPROVED', ?, NOW(), NOW())"
                );
                if ($ins) { $ins->bind_param('iiisi', $reqId, $userId, $borrow_id, $pj, $userId); $ins->execute(); $ins->close(); }
                @push_notification_ws($reqId, 'return_approved', $borrow_id, $retPayload);
                @push_borrow_update_ws($borrow_id, 'RETURN_APPROVED', $retPayload);
            }

            $crossRoles = ($userRole === 'MANAGER') ? ['ADMIN'] : ['MANAGER'];
            notify_roles($conn, $crossRoles, $userId, 'return_approved_info', $borrow_id, [
                'borrow_id' => $borrow_id, 'item' => $b['item_name'], 'qty' => $qty, 'ref' => $ref,
                'message'   => "Return for Borrow #{$borrow_id} approved by {$actorLabel} #{$userId}; returned to {$b['from_person']}",
                'from_person' => $b['from_person'], 'serials' => $b['serial_number'] ?? '',
            ], $userId);

            $upd = $conn->prepare(
                "UPDATE notifications SET action = 'APPROVED', action_by = ?, action_at = NOW(), is_read = 1
                 WHERE type = 'return_request' AND related_id = ?"
            );
            if ($upd) { $upd->bind_param('ii', $userId, $borrow_id); $upd->execute(); $upd->close(); }

            log_system($conn, $userId, 'RETURN_APPROVED',
                "{$actorLabel} #{$userId} approved return for Borrow #{$borrow_id} — {$b['item_name']} x{$qty} returned to {$b['from_person']} serials:[{$b['serial_number']}] (ref: {$ref})",
                ['borrow_id' => $borrow_id, 'qty' => $qty, 'actor_role' => $userRole, 'from_person' => $b['from_person']]
            );
            log_audit($conn, $userId, 'borrowed_items', 'UPDATE', $borrow_id,
                ['status' => 'RETURN_PENDING', 'is_returned' => 0],
                ['status' => 'RETURNED', 'is_returned' => 1, 'return_approved_by' => $userId, 'actor_role' => $userRole]
            );
            log_notif_action($conn, $userId, 'return_approved', [
                'borrow_id' => $borrow_id, 'qty' => $qty, 'item' => $b['item_name'],
                'ref' => $ref, 'actor_role' => $userRole,
                'from_person' => $b['from_person'], 'serials' => $b['serial_number'] ?? '',
            ], 'borrow_items');

            $conn->commit();
            jsonResponse(true, 'Return approved and processed.');
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // ACTION: admin_deny_return — reject a staff return request
    // ----------------------------------------------------------
    if ($action === 'admin_deny_return') {
        $borrow_id   = filter_input(INPUT_POST, 'borrow_id', FILTER_VALIDATE_INT);
        $deny_remarks = trim($_POST['remarks'] ?? '');
        if (!$borrow_id || !in_array($userRole, ['ADMIN', 'MANAGER'], true)) jsonResponse(false, 'Unauthorized.');

        $actorLabel = ucfirst(strtolower($userRole));
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT b.*, ii.item_name FROM borrowed_items b
                 JOIN inventory_items ii ON ii.id = b.inventory_item_id
                 WHERE b.borrow_id = ? AND b.status = 'RETURN_PENDING' FOR UPDATE"
            );
            $stmt->bind_param('i', $borrow_id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$b) throw new Exception('Record not found or not pending return.');

            // Revert status to APPROVED so staff can re-submit or keep using item
            $stmt = $conn->prepare(
                "UPDATE borrowed_items
                 SET status = 'APPROVED',
                     return_decision_remarks = ?,
                     return_approved_by = ?, return_approved_at = NOW()
                 WHERE borrow_id = ?"
            );
            $stmt->bind_param('sii', $deny_remarks, $userId, $borrow_id);
            $stmt->execute();
            $stmt->close();

            // Notify the requester
            $reqId = (int)($b['return_requested_by'] ?? $b['requested_by'] ?? 0);
            if ($reqId) {
                $pj = json_encode(['borrow_id' => $borrow_id, 'item' => $b['item_name'], 'reason' => $deny_remarks]);
                $ins = $conn->prepare(
                    "INSERT INTO notifications (user_id, actor_user_id, type, related_id, payload, is_read, created_at)
                     VALUES (?, ?, 'return_denied', ?, ?, 0, NOW())"
                );
                if ($ins) { $ins->bind_param('iiis', $reqId, $userId, $borrow_id, $pj); $ins->execute(); $ins->close(); }
            }

            log_system($conn, $userId, 'RETURN_DENIED',
                "{$actorLabel} #{$userId} denied return for Borrow #{$borrow_id} — {$b['item_name']}. Status reverted to APPROVED. Reason: {$deny_remarks}",
                ['borrow_id' => $borrow_id, 'actor_role' => $userRole]
            );
            log_audit($conn, $userId, 'borrowed_items', 'UPDATE', $borrow_id,
                ['status' => 'RETURN_PENDING'],
                ['status' => 'APPROVED', 'return_decision_remarks' => $deny_remarks, 'actor_role' => $userRole]
            );

            $conn->commit();
            jsonResponse(true, 'Return request denied. Item status reverted to Approved.');
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, $e->getMessage());
        }
    }
}


// ============================================================
// Data Fetching — initial page load (PHP-rendered, first paint)
// ============================================================
$items_search = trim($_GET['items_search'] ?? '');
$page         = max(1, (int)($_GET['items_page'] ?? 1));
$limit        = 10;
$offset       = ($page - 1) * $limit;

$where  = 'ai.is_deleted = 0 AND ai.assigned_quantity > 0';
$params = [];
$types  = '';

if ($items_search) {
    $where   .= ' AND (ii.item_name LIKE ? OR ai.person_name LIKE ?)';
    $sVal     = "%$items_search%";
    $params   = [$sVal, $sVal];
    $types    = 'ss';
}

$countSql = "SELECT COUNT(*) AS cnt FROM accountable_items ai
             JOIN inventory_items ii ON ii.id = ai.inventory_item_id WHERE $where";
$stmt = $conn->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_items = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = (int)ceil($total_items / $limit);
$stmt->close();

// Include serial_number so the Borrow modal can show it via JS without an extra round-trip
$dataSql = "SELECT ai.id, ii.item_name, ai.person_name, ai.assigned_quantity, ai.serial_number
            FROM accountable_items ai
            JOIN inventory_items ii ON ii.id = ai.inventory_item_id
            WHERE $where ORDER BY ii.item_name ASC LIMIT ?, ?";
$stmt = $conn->prepare($dataSql);
$params[] = $offset;
$params[] = $limit;
$types   .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$available_items = $stmt->get_result();

// Active Transactions — STAFF sees only their own borrows
if ($userRole === 'STAFF') {
    $bSql = "SELECT b.*, ii.item_name,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS b_name
             FROM borrowed_items b
             JOIN inventory_items ii ON ii.id = b.inventory_item_id
             LEFT JOIN `user` u ON u.id = b.borrower_employee_id
             WHERE b.requested_by = ?
             ORDER BY b.borrow_date DESC LIMIT 50";
    $bStmt = $conn->prepare($bSql);
    $bStmt->bind_param('i', $userId);
    $bStmt->execute();
    $bRes = $bStmt->get_result();
} else {
    $bSql = "SELECT b.*, ii.item_name,
                    CONCAT(e.FIRSTNAME,' ',e.LASTNAME) AS b_name
             FROM borrowed_items b
             JOIN inventory_items ii ON ii.id = b.inventory_item_id
             LEFT JOIN cao_employee e ON e.ID = b.borrower_employee_id
             ORDER BY b.borrow_date DESC LIMIT 50";
    $bRes = $conn->query($bSql);
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
      data-assets-path="/inventory_cao_v2/assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Borrow Items | Inventory CAO</title>

    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
        /* ---- layout ---- */
        .card-header { padding: 1.5rem 1.5rem 0.5rem 1.5rem; }
        .table th { text-transform: uppercase; font-size: .75rem; letter-spacing: .5px; }

        /* ---- borrower locked display ---- */
        .borrower-locked {
            background: #f8f8f8; border: 1px solid #ddd; border-radius: .375rem;
            padding: .5rem .75rem; display: flex; align-items: center; gap: .5rem;
        }
        .borrower-locked .badge { font-size: .7rem; }

        /* ---- serial number picker ---- */
        .serial-picker-box {
            border: 1.5px solid #e0e0e0; border-radius: .45rem;
            padding: .6rem .85rem; max-height: 188px; overflow-y: auto;
            background: #fafbff; transition: border-color .2s;
        }
        .serial-picker-box:focus-within {
            border-color: #696cff; box-shadow: 0 0 0 3px rgba(105,108,255,.1);
        }
        .serial-picker-box .form-check {
            padding: .3rem 0 .3rem 1.85rem;
            border-bottom: 1px solid #f0f1f7;
        }
        .serial-picker-box .form-check:last-child { border-bottom: none; }
        .serial-picker-box .form-check-label {
            font-size: .875rem; cursor: pointer;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            letter-spacing: .03em; color: #4a5568;
        }
        .serial-picker-box .form-check-input:checked + label { font-weight: 700; color: #696cff; }
        .serial-picker-box .form-check-input:checked { background-color: #696cff; border-color: #696cff; }

        /* ---- serial counter / mismatch bar ---- */
        .serial-counter-bar {
            display: flex; align-items: center; justify-content: space-between;
            gap: .5rem; padding: .35rem .6rem; border-radius: .35rem;
            background: #f4f5ff; margin-top: .45rem; font-size: .8rem;
        }
        .serial-counter-bar .sc-text { color: #566a7f; }
        .sc-badge { font-weight: 700; padding: .18rem .55rem; border-radius: 99px; font-size: .76rem; }
        .sc-badge.match    { background: #e6f4ea; color: #1a7f3c; }
        .sc-badge.mismatch { background: #fff0f0; color: #d32f2f; }
        .sc-badge.none     { background: #ebebeb; color: #888; }
        .serial-mismatch-msg {
            font-size: .78rem; color: #d32f2f; margin-top: .3rem;
            display: none; align-items: center; gap: .3rem;
        }
        .serial-mismatch-msg.visible { display: flex; }

        /* ---- return modal serials ---- */
        .return-serial-badge { font-size: .75rem; }

        /* ---- AJAX search spinner ---- */
        .search-loading { display: none; }
        .search-loading.visible { display: inline-block; }

        /* ---- quantity locked indicator ---- */
        .qty-locked-note { font-size: .78rem; color: #696cff; margin-top: .25rem; display: none; }
        .qty-locked-note.visible { display: block; }
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'sidebar.php'; ?>
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="content-wrapper">
            <div class="container-xxl grow container-p-y">

                <h4 class="fw-bold py-3 mb-4">
                    <span class="text-muted fw-light">Inventory /</span> Borrow Items
                </h4>

                <!-- Toast container -->
                <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100;"></div>

                <!-- ============================================================ -->
                <!-- AVAILABLE ITEMS                                               -->
                <!-- ============================================================ -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Available Items</h5>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-loader bx-spin text-primary search-loading" id="availSearchSpinner"></i>
                            <input type="text"
                                   id="availItemsSearch"
                                   class="form-control form-control-sm"
                                   placeholder="Search item or person…"
                                   value="<?= htmlspecialchars($items_search) ?>"
                                   autocomplete="off"
                                   style="width:240px;">
                        </div>
                    </div>

                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Name</th>
                                    <th>Held By</th>
                                    <th>Qty</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="availItemsTbody">
                                <?php if ($available_items->num_rows > 0): ?>
                                    <?php while ($row = $available_items->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['person_name']) ?></td>
                                            <td><span class="badge bg-label-info"><?= $row['assigned_quantity'] ?></span></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-icon btn-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#borrowModal"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-item="<?= htmlspecialchars($row['item_name'], ENT_QUOTES) ?>"
                                                        data-person="<?= htmlspecialchars($row['person_name'], ENT_QUOTES) ?>"
                                                        data-max="<?= $row['assigned_quantity'] ?>"
                                                        data-serials="<?= htmlspecialchars($row['serial_number'] ?? '', ENT_QUOTES) ?>">
                                                    <i class="bx bx-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No items found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (AJAX-replaceable) -->
                    <div id="availItemsPagination">
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer py-2">
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                            <a class="page-link avail-page-link" href="#"
                                               data-page="<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div><!-- /Available Items card -->


                <!-- ============================================================ -->
                <!-- ACTIVE TRANSACTIONS                                           -->
                <!-- ============================================================ -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">
                            Active Transactions
                            <?php if ($userRole === 'STAFF'): ?>
                                <span class="badge bg-label-secondary ms-2"
                                      title="You can only see your own transactions">My Borrows</span>
                            <?php endif; ?>
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-loader bx-spin text-primary search-loading" id="txSearchSpinner"></i>
                            <input type="text"
                                   id="clientSearch"
                                   class="form-control form-control-sm"
                                   placeholder="Search transactions…"
                                   autocomplete="off"
                                   style="width:240px;">
                        </div>
                    </div>

                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover" id="borrowedTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Item Details</th>
                                    <th>Borrower</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="borrowedTbody">
                                <?php
                                if ($bRes && $bRes->num_rows > 0):
                                    while ($b = $bRes->fetch_assoc()):
                                        $statusBadge = match($b['status']) {
                                            'PENDING'        => 'bg-label-warning',
                                            'APPROVED'       => 'bg-label-success',
                                            'RETURN_PENDING' => 'bg-label-info',
                                            'RETURNED'       => 'bg-label-secondary',
                                            'DENIED'         => 'bg-label-danger',
                                            default          => 'bg-label-secondary',
                                        };
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary">#<?= htmlspecialchars($b['reference_no']) ?></span><br>
                                        <small class="text-muted"><?= $b['borrow_date'] ? date('M d, Y', strtotime($b['borrow_date'])) : '' ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['item_name']) ?></strong><br>
                                        <small>Qty: <?= $b['quantity'] ?></small>
                                        | <small>From: <?= htmlspecialchars($b['from_person']) ?></small>
                                        <?php if (!empty($b['serial_number'])): ?>
                                        <br><small class="text-muted"><i class="bx bx-barcode-reader"></i>
                                            <?= htmlspecialchars($b['serial_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($b['b_name'] ?? $b['to_person'] ?? '') ?></td>
                                    <td>
                                        <?php if ($b['is_returned']): ?>
                                            <span class="badge bg-label-secondary">Returned</span>
                                        <?php else: ?>
                                            <span class="badge <?= $statusBadge ?>"><?= $b['status'] ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($b['decision_remarks'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($b['decision_remarks']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['status'] === 'PENDING' && in_array($userRole, ['ADMIN','MANAGER'])): ?>
                                            <button class="btn btn-sm btn-success"
                                                    onclick="approveRequest(<?= $b['borrow_id'] ?>)">Approve</button>
                                            <button class="btn btn-sm btn-danger ms-1"
                                                    onclick="denyRequest(<?= $b['borrow_id'] ?>)">Deny</button>
                                        <?php elseif ($b['status'] === 'RETURN_PENDING' && in_array($userRole, ['ADMIN','MANAGER'])): ?>
                                            <button class="btn btn-sm btn-success"
                                                    onclick="approveReturn(<?= $b['borrow_id'] ?>)">Approve Return</button>
                                        <?php elseif (!$b['is_returned'] && $b['status'] === 'APPROVED'): ?>
                                            <?php if ($userRole === 'STAFF'): ?>
                                            <button class="btn btn-sm btn-outline-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#returnConfirmModal"
                                                    data-bid="<?= $b['borrow_id'] ?>"
                                                    data-item="<?= htmlspecialchars($b['item_name'], ENT_QUOTES) ?>"
                                                    data-qty="<?= $b['quantity'] ?>"
                                                    data-from-person="<?= htmlspecialchars($b['from_person'], ENT_QUOTES) ?>"
                                                    data-serials="<?= htmlspecialchars($b['serial_number'] ?? '', ENT_QUOTES) ?>"
                                                    data-acc-id="<?= $b['accountable_id'] ?>"
                                                    data-ref="<?= htmlspecialchars($b['reference_no'], ENT_QUOTES) ?>">
                                                <i class="bx bx-send me-1"></i>Request Return
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="openAdminReturnModal(
                                                        <?= (int)$b['borrow_id'] ?>,
                                                        '<?= htmlspecialchars($b['item_name'], ENT_QUOTES) ?>',
                                                        <?= (int)$b['quantity'] ?>,
                                                        '<?= htmlspecialchars($b['from_person'], ENT_QUOTES) ?>',
                                                        '<?= htmlspecialchars($b['serial_number'] ?? '', ENT_QUOTES) ?>',
                                                        <?= (int)$b['accountable_id'] ?>,
                                                        '<?= htmlspecialchars($b['reference_no'], ENT_QUOTES) ?>'
                                                    )">
                                                <i class="bx bx-check-circle me-1"></i>Return
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No active transactions.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div><!-- /Active Transactions card -->

            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>
</div>


<!-- ============================================================ -->
<!-- BORROW MODAL (with Serial Number Picker)                     -->
<!-- ============================================================ -->
<div class="modal fade" id="borrowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="borrowForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-package me-2 text-primary"></i>Borrow Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action"         value="borrow">
                    <input type="hidden" name="csrf_token"     value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="accountable_id" id="m_acc_id">

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Item</label>
                            <input type="text" class="form-control" id="m_item_name" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Held By (From)</label>
                            <input type="text" class="form-control" id="m_from" readonly>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Available Qty</label>
                            <input type="text" class="form-control" id="m_max_qty" readonly>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- SERIAL NUMBER PICKER                        -->
                    <!-- ========================================== -->
                    <div class="mb-3" id="serialPickerWrapper">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0">
                                <i class="bx bx-barcode-reader me-1 text-primary"></i>Serial Number(s)
                            </label>
                            <span id="snOptionalBadge" class="badge bg-label-secondary" style="font-size:.7rem;">
                                loading…
                            </span>
                        </div>

                        <!-- Filled by JS after AJAX -->
                        <div id="serialPickerContainer">
                            <div class="text-muted small py-1">
                                <i class="bx bx-loader bx-spin"></i> Loading serial numbers…
                            </div>
                        </div>

                        <!-- Live counter bar (hidden until serials load) -->
                        <div class="serial-counter-bar d-none" id="serialCounterBar">
                            <span class="sc-text">
                                <i class="bx bx-check-square me-1"></i>
                                Selected <strong id="snCheckedCount">0</strong>
                                &nbsp;/&nbsp; Qty <strong id="snQtyMirror">1</strong>
                            </span>
                            <span class="sc-badge none" id="snMatchBadge">—</span>
                        </div>

                        <!-- Mismatch message -->
                        <div class="serial-mismatch-msg" id="serialMismatchMsg">
                            <i class="bx bx-error-circle"></i>
                            <span id="serialMismatchText">Serials checked must equal quantity.</span>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- BORROWER FIELD — role-based                -->
                    <!-- ========================================== -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Borrower</label>
                        <?php if ($userRole === 'STAFF'): ?>
                            <input type="hidden" name="borrower_employee_id" value="<?= $userId ?>">
                            <div class="borrower-locked">
                                <i class="bx bx-user-check text-primary fs-5"></i>
                                <span class="fw-semibold"><?= htmlspecialchars($currentUserFullName) ?></span>
                                <span class="badge bg-label-primary ms-auto">You</span>
                            </div>
                            <div class="form-text text-muted mt-1">
                                <i class="bx bx-lock-alt"></i>
                                As Staff, borrow requests are always registered under your own name.
                            </div>
                        <?php else: ?>
                            <select name="borrower_employee_id" class="form-select" required>
                                <option value="">Select Employee…</option>
                                <?php
                                $emps = $conn->query(
                                    "SELECT ID, CONCAT(FIRSTNAME,' ',LASTNAME) AS name
                                     FROM cao_employee WHERE DELETED = 0
                                     ORDER BY LASTNAME, FIRSTNAME"
                                );
                                while ($e = $emps->fetch_assoc()) {
                                    echo '<option value="' . (int)$e['ID'] . '">'
                                       . htmlspecialchars($e['name'])
                                       . '</option>';
                                }
                                ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Quantity</label>
                            <input type="number" name="borrow_quantity" id="m_qty_input"
                                   class="form-control" min="1" value="1" required>
                            <div class="qty-locked-note" id="qtyLockedNote">
                                <i class="bx bx-lock-alt"></i> Locked — matches selected serial(s)
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"
                                  placeholder="Purpose, notes…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-send me-1"></i>
                        <?= $userRole === 'STAFF' ? 'Submit Request' : 'Confirm Borrow' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================ -->
<!-- RETURN CONFIRMATION MODAL (Staff-initiated)                  -->
<!-- Admin/Manager confirm inline via approveReturn()             -->
<!-- ============================================================ -->
<div class="modal fade" id="returnConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="returnForm" method="POST">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title text-dark">
                        <i class="bx bx-arrow-back me-2"></i>
                        <?= $userRole === 'STAFF' ? 'Request Item Return' : 'Process Return' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action"      value="return">
                    <input type="hidden" name="csrf_token"  value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="return_id"   id="r_borrow_id">

                    <!-- ── Item Being Returned ── -->
                    <div class="card border mb-3">
                        <div class="card-body py-3">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bx bx-package me-1"></i>Item Being Returned
                            </h6>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <small class="text-muted text-uppercase d-block" style="font-size:.7rem;letter-spacing:.5px;">Item Name</small>
                                    <strong id="r_item_name" class="fs-6"></strong>
                                </div>
                                <div class="col-sm-3">
                                    <small class="text-muted text-uppercase d-block" style="font-size:.7rem;letter-spacing:.5px;">Borrowed Qty</small>
                                    <span id="r_max_qty" class="fw-semibold fs-6"></span>
                                </div>
                                <div class="col-sm-3">
                                    <small class="text-muted text-uppercase d-block" style="font-size:.7rem;letter-spacing:.5px;">Reference</small>
                                    <span id="r_ref_no" class="text-primary fw-semibold" style="font-size:.85rem;"></span>
                                </div>
                            </div>
                            <!-- Serial numbers on the borrowed record -->
                            <div id="r_serials_section" class="mt-3 d-none">
                                <small class="text-muted text-uppercase d-block mb-1" style="font-size:.7rem;letter-spacing:.5px;">
                                    <i class="bx bx-barcode-reader me-1"></i>Serial Number(s) to Return
                                </small>
                                <div id="r_serials_list" class="d-flex flex-wrap gap-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Returning To ── -->
                    <div class="card border border-info mb-3">
                        <div class="card-body py-3">
                            <h6 class="fw-bold mb-3 text-info">
                                <i class="bx bx-user-check me-1"></i>Returning To
                            </h6>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <small class="text-muted text-uppercase d-block" style="font-size:.7rem;letter-spacing:.5px;">Account Holder</small>
                                    <strong id="r_from_person" class="fs-6"></strong>
                                </div>
                                <div class="col-sm-6" id="r_acc_holder_col">
                                    <small class="text-muted text-uppercase d-block" style="font-size:.7rem;letter-spacing:.5px;">Accountable Record</small>
                                    <div id="r_acc_details">
                                        <span class="text-muted small">
                                            <i class="bx bx-loader bx-spin"></i> Loading…
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Quantity & Remarks ── -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Return Quantity</label>
                            <input type="number" name="return_quantity" id="r_qty_input"
                                   class="form-control" min="1" required>
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label fw-semibold">
                                Remarks <small class="text-muted fw-normal">(optional)</small>
                            </label>
                            <textarea name="return_remarks" class="form-control" rows="2"
                                      placeholder="Item condition, notes…"></textarea>
                        </div>
                    </div>

                    <!-- Notice for STAFF -->
                    <?php if ($userRole === 'STAFF'): ?>
                    <div class="alert alert-warning d-flex gap-2 mb-0 py-2">
                        <i class="bx bx-info-circle fs-5 flex-shrink-0 mt-1"></i>
                        <div class="small">
                            Your return request will be reviewed by <strong>Admin / Manager</strong> before
                            it is finalised. The item will be marked as <em>Return Pending</em> until approved.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold text-dark">
                        <i class="bx bx-check me-1"></i>
                        <?= $userRole === 'STAFF' ? 'Submit Return Request' : 'Process Return' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================ -->
<!-- SCRIPTS                                                       -->
<!-- ============================================================ -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/main.js"></script>

<script>
/* ================================================================
   GLOBALS injected from PHP
   ================================================================ */
const USER_ROLE = '<?= $userRole ?>';
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

/* ================================================================
   UTILITIES
   ================================================================ */
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str ?? '')));
    return d.innerHTML;
}
function escAttr(str) {
    return String(str ?? '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white bg-${type} border-0`;
    el.innerHTML = `<div class="d-flex">
        <div class="toast-body">${escHtml(msg)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
    document.querySelector('.toast-container').appendChild(el);
    new bootstrap.Toast(el, { delay: 4000 }).show();
}

/* ================================================================
   GENERIC AJAX FORM SUBMITTER
   ================================================================ */
function setupAjaxForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn  = form.querySelector('button[type="submit"]');
        const orig = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<i class="bx bx-loader bx-spin me-1"></i>Processing…';
        fetch('borrow_items.php', {
            method:  'POST',
            body:    new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async r => {
            const text = await r.text();
            try { return JSON.parse(text); }
            catch { throw new Error('Server returned an invalid format.'); }
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                // Close open modal then reload
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    bootstrap.Modal.getInstance(openModal)?.hide();
                    openModal.addEventListener('hidden.bs.modal', () => location.reload(), { once: true });
                } else {
                    setTimeout(() => location.reload(), 700);
                }
            } else {
                showToast(data.message || 'An error occurred.', 'danger');
                btn.disabled  = false;
                btn.innerHTML = orig;
            }
        })
        .catch(err => {
            showToast(err.message, 'danger');
            btn.disabled  = false;
            btn.innerHTML = orig;
        });
    });
}

function postAction(actionName, borrowId, extra = {}) {
    const fd = new FormData();
    fd.append('action',     actionName);
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('borrow_id',  borrowId);
    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    return fetch('borrow_items.php', {
        method:  'POST',
        body:    fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json());
}

/* ================================================================
   ADMIN / MANAGER INLINE ACTIONS
   ================================================================ */
window.approveRequest = function (id) {
    if (!confirm('Approve this borrow request?')) return;
    postAction('admin_approve', id)
        .then(d => { showToast(d.message, d.success ? 'success' : 'danger'); if (d.success) setTimeout(() => location.reload(), 700); })
        .catch(err => showToast('Error: ' + err.message, 'danger'));
};

window.denyRequest = function (id) {
    const reason = prompt('Reason for denial (optional):');
    if (reason === null) return;
    postAction('admin_deny', id, { remarks: reason })
        .then(d => { showToast(d.message, d.success ? 'success' : 'danger'); if (d.success) setTimeout(() => location.reload(), 700); })
        .catch(err => showToast('Error: ' + err.message, 'danger'));
};

/* ================================================================
   ADMIN / MANAGER — Return Confirmation Modal
   ================================================================ */
window.openAdminReturnModal = function (borrowId, itemName, qty, fromPerson, serialStr, accId, refNo) {
    document.getElementById('ar_borrow_id').value       = borrowId;
    document.getElementById('ar_item_name').textContent  = itemName;
    document.getElementById('ar_qty').textContent        = qty;
    document.getElementById('ar_ref').textContent        = refNo;
    document.getElementById('ar_from_person').textContent = fromPerson;

    // Serial badges
    const secEl   = document.getElementById('ar_serials_section');
    const listEl  = document.getElementById('ar_serials_list');
    listEl.innerHTML = '';
    if (serialStr && serialStr.trim()) {
        const parts = serialStr.split('/').map(s => s.trim()).filter(Boolean);
        if (parts.length) {
            secEl.classList.remove('d-none');
            parts.forEach(sn => {
                const span = document.createElement('span');
                span.className = 'badge bg-label-info return-serial-badge';
                span.textContent = sn;
                listEl.appendChild(span);
            });
        } else { secEl.classList.add('d-none'); }
    } else { secEl.classList.add('d-none'); }

    // Fetch accountable holder details
    const accEl = document.getElementById('ar_acc_details');
    accEl.innerHTML = '<span class="text-muted small"><i class="bx bx-loader bx-spin"></i> Loading…</span>';
    if (accId) {
        fetch(`borrow_items_api.php?fetch_accountable_serials=1&accountable_id=${encodeURIComponent(accId)}`)
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    let html = '';
                    if (d.person_name)    html += `<span class="fw-semibold">${escHtml(d.person_name)}</span><br>`;
                    if (d.are_mr_ics_num) html += `<small class="text-muted">ARE/MR/ICS: ${escHtml(d.are_mr_ics_num)}</small><br>`;
                    if (d.property_number) html += `<small class="text-muted">Property#: ${escHtml(d.property_number)}</small>`;
                    if (d.serials && d.serials.length) {
                        html += `<br><small class="text-muted">Current serials on record: ${d.serials.map(s => escHtml(s)).join(' / ')}</small>`;
                    }
                    accEl.innerHTML = html || '<span class="text-muted small">—</span>';
                } else { accEl.innerHTML = '<span class="text-muted small">—</span>'; }
            })
            .catch(() => { accEl.innerHTML = '<span class="text-muted small text-danger">Could not load.</span>'; });
    } else { accEl.innerHTML = '<span class="text-muted small">—</span>'; }

    new bootstrap.Modal(document.getElementById('adminReturnModal')).show();
};

// Legacy approveReturn() — now opens the modal instead of confirm()
window.approveReturn = function (id) {
    // Try to get data from the row; fall back to just opening modal with borrow_id only
    // (Full data is fetched inside openAdminReturnModal via AJAX)
    const btn = document.querySelector(`button[onclick*='openAdminReturnModal'][onclick*='${id}']`);
    if (btn) { btn.click(); return; }
    // fallback if called from old PHP-rendered row without onclick
    document.getElementById('ar_borrow_id').value = id;
    document.getElementById('ar_item_name').textContent   = '—';
    document.getElementById('ar_qty').textContent         = '—';
    document.getElementById('ar_ref').textContent         = '—';
    document.getElementById('ar_from_person').textContent = '—';
    document.getElementById('ar_serials_section').classList.add('d-none');
    document.getElementById('ar_acc_details').innerHTML   = '<span class="text-muted small">—</span>';
    new bootstrap.Modal(document.getElementById('adminReturnModal')).show();
};

window.denyReturnFromModal = function () {
    const id = document.getElementById('ar_borrow_id').value;
    if (!id) return;
    const reason = prompt('Reason for denying this return request (optional):');
    if (reason === null) return;  // user cancelled
    postAction('admin_deny_return', id, { remarks: reason })
        .then(d => {
            showToast(d.message, d.success ? 'success' : 'danger');
            if (d.success) {
                const m = bootstrap.Modal.getInstance(document.getElementById('adminReturnModal'));
                if (m) m.hide();
                setTimeout(() => location.reload(), 700);
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'danger'));
};

// Wire up admin return form (AJAX submit)
setupAjaxForm('adminReturnForm');

/* ================================================================
   BORROW MODAL — Serial Number Picker
   ================================================================ */
/* ================================================================
   BORROW MODAL — Serial Number Picker
   RULE: checked-serials count MUST equal quantity before submit.
   ================================================================ */
let _borrowHasSerials = false;   // true when this item has serial numbers on record

document.getElementById('borrowModal').addEventListener('show.bs.modal', function (e) {
    const btn   = e.relatedTarget;
    const accId = btn.dataset.id;
    const max   = parseInt(btn.dataset.max, 10);

    // ── Populate header fields ────────────────────────────────────────────
    document.getElementById('m_acc_id').value    = accId;
    document.getElementById('m_item_name').value = btn.dataset.item;
    document.getElementById('m_from').value      = btn.dataset.person;
    document.getElementById('m_max_qty').value   = max;

    const qInput = document.getElementById('m_qty_input');
    qInput.max      = max;
    qInput.value    = 1;
    qInput.readOnly = false;
    document.getElementById('qtyLockedNote').classList.remove('visible');

    // ── Reset serial UI ───────────────────────────────────────────────────
    _borrowHasSerials = false;
    const container = document.getElementById('serialPickerContainer');
    container.innerHTML = '<div class="text-muted small py-1">'
        + '<i class="bx bx-loader bx-spin me-1"></i>Loading serial numbers…</div>';

    const badge = document.getElementById('snOptionalBadge');
    badge.textContent = 'loading…';
    badge.className   = 'badge bg-label-secondary';
    document.getElementById('serialCounterBar').classList.add('d-none');
    document.getElementById('serialMismatchMsg').classList.remove('visible');
    _syncBorrowSubmit();   // disable submit while loading

    // ── Parse serials inline from data-serials (no AJAX needed) ──────────
    const rawSerials = btn.dataset.serials || '';
    const serials    = rawSerials.split('/').map(s => s.trim()).filter(Boolean);

    if (serials.length > 0) {
        // ── Item HAS serial numbers ───────────────────────────────────────
        _borrowHasSerials = true;
        badge.textContent = 'required — select one per unit';
        badge.className   = 'badge bg-label-warning';

        // Build the checklist
        let html = '<div class="d-flex justify-content-between align-items-center mb-2">'
            + '<small class="text-muted fst-italic">Check one serial per unit being borrowed.</small>'
            + '<label class="form-check form-check-inline mb-0 me-0" style="font-size:.8rem;cursor:pointer;">'
            + '<input type="checkbox" class="form-check-input" id="serialSelectAll">'
            + '<span class="form-check-label fw-semibold">Select All</span></label>'
            + '</div>'
            + '<div class="serial-picker-box">';

        serials.forEach(function (sn, i) {
            const cbId = 'sn_borrow_' + i;
            html += '<div class="form-check">'
                + '<input class="form-check-input serial-checkbox" type="checkbox"'
                + ' name="selected_serials[]" value="' + escAttr(sn) + '" id="' + cbId + '">'
                + '<label class="form-check-label" for="' + cbId + '">'
                + escHtml(sn) + '</label></div>';
        });
        html += '</div>';
        container.innerHTML = html;

        // Wire checkboxes
        container.querySelectorAll('.serial-checkbox').forEach(function (cb) {
            cb.addEventListener('change', updateQtyFromSerials);
        });

        // Wire Select-All
        const selAll = document.getElementById('serialSelectAll');
        selAll.addEventListener('change', function () {
            container.querySelectorAll('.serial-checkbox').forEach(function (cb) {
                cb.checked = selAll.checked;
            });
            updateQtyFromSerials();
        });

        document.getElementById('serialCounterBar').classList.remove('d-none');
        updateQtyFromSerials();   // set initial counter state

    } else {
        // ── Item has NO serial numbers ────────────────────────────────────
        _borrowHasSerials = false;
        badge.textContent = 'not applicable';
        badge.className   = 'badge bg-label-secondary';
        container.innerHTML = '<div class="alert alert-secondary py-2 mb-0" style="font-size:.83rem;">'
            + '<i class="bx bx-info-circle me-1"></i>'
            + 'No serial numbers recorded for this item — enter quantity manually.</div>';
        document.getElementById('serialCounterBar').classList.add('d-none');
        _syncBorrowSubmit();
    }
});

// Keep counter in sync when user manually edits the qty field
document.getElementById('m_qty_input').addEventListener('input', function () {
    if (_borrowHasSerials) updateQtyFromSerials();
    else _syncBorrowSubmit();
});

/* ── Main sync function called on every checkbox change ─────────────────── */
function updateQtyFromSerials() {
    var allCbs   = document.querySelectorAll('#serialPickerContainer .serial-checkbox');
    var checked  = document.querySelectorAll('#serialPickerContainer .serial-checkbox:checked');
    var qInput   = document.getElementById('m_qty_input');
    var note     = document.getElementById('qtyLockedNote');

    // Lock qty to checked count (one-way: checking drives qty)
    if (checked.length > 0) {
        qInput.value    = checked.length;
        qInput.readOnly = true;
        note.classList.add('visible');
    } else {
        qInput.readOnly = false;
        note.classList.remove('visible');
    }

    // Select-All indeterminate state
    var selAll = document.getElementById('serialSelectAll');
    if (selAll) {
        selAll.indeterminate = checked.length > 0 && checked.length < allCbs.length;
        selAll.checked       = allCbs.length > 0 && checked.length === allCbs.length;
    }

    // ── Counter bar ───────────────────────────────────────────────────────
    var qty      = parseInt(qInput.value, 10) || 0;
    var nChecked = checked.length;
    var isMatch  = nChecked > 0 && nChecked === qty;

    document.getElementById('snCheckedCount').textContent = nChecked;
    document.getElementById('snQtyMirror').textContent    = qty;

    var matchBadge = document.getElementById('snMatchBadge');
    if (nChecked === 0) {
        matchBadge.textContent = 'none selected';
        matchBadge.className   = 'sc-badge none';
    } else if (isMatch) {
        matchBadge.textContent = '✓ match';
        matchBadge.className   = 'sc-badge match';
    } else {
        matchBadge.textContent = nChecked + ' ≠ ' + qty;
        matchBadge.className   = 'sc-badge mismatch';
    }

    // ── Mismatch warning ──────────────────────────────────────────────────
    var mismatchEl = document.getElementById('serialMismatchMsg');
    var mismatchTx = document.getElementById('serialMismatchText');
    if (_borrowHasSerials && nChecked > 0 && !isMatch) {
        var need = qty - nChecked;
        mismatchTx.textContent = need > 0
            ? 'Select ' + need + ' more serial(s) to match the quantity (' + qty + ').'
            : 'Uncheck ' + Math.abs(need) + ' serial(s) to match the quantity (' + qty + ').';
        mismatchEl.classList.add('visible');
    } else {
        mismatchEl.classList.remove('visible');
    }

    _syncBorrowSubmit();
}

/* ── Enable / disable the submit button based on serial-qty match ────────── */
function _syncBorrowSubmit() {
    var submitBtn = document.querySelector('#borrowForm button[type="submit"]');
    if (!submitBtn) return;

    if (!_borrowHasSerials) {
        // No serials for this item — qty ≥ 1 is sufficient
        var qty = parseInt(document.getElementById('m_qty_input').value, 10);
        submitBtn.disabled = !(qty >= 1);
        submitBtn.title    = '';
        return;
    }

    var checked = document.querySelectorAll('#serialPickerContainer .serial-checkbox:checked');
    var qty     = parseInt(document.getElementById('m_qty_input').value, 10);
    var ok      = checked.length > 0 && checked.length === qty;

    submitBtn.disabled = !ok;
    submitBtn.title    = ok ? '' : 'You must select exactly ' + qty + ' serial number(s) before submitting.';
}

/* ================================================================
   RETURN MODAL — populate + fetch accountable holder details
   ================================================================ */
document.getElementById('returnConfirmModal').addEventListener('show.bs.modal', function (e) {
    const btn       = e.relatedTarget;
    const max       = parseInt(btn.dataset.qty, 10);
    const accId     = btn.dataset.accId;
    const serialStr = btn.dataset.serials || '';

    document.getElementById('r_borrow_id').value      = btn.dataset.bid;
    document.getElementById('r_item_name').textContent = btn.dataset.item;
    document.getElementById('r_max_qty').textContent    = max;
    document.getElementById('r_ref_no').textContent     = btn.dataset.ref || '';
    document.getElementById('r_from_person').textContent = btn.dataset.fromPerson || '';

    const qInput = document.getElementById('r_qty_input');
    qInput.max   = max;
    qInput.value = max;

    // Show serial numbers from the borrowed record
    const serialsSection = document.getElementById('r_serials_section');
    const serialsList    = document.getElementById('r_serials_list');
    serialsList.innerHTML = '';

    if (serialStr.trim()) {
        const parts = serialStr.split('/').map(s => s.trim()).filter(Boolean);
        if (parts.length) {
            serialsSection.classList.remove('d-none');
            parts.forEach(sn => {
                const span = document.createElement('span');
                span.className = 'badge bg-label-secondary return-serial-badge';
                span.textContent = sn;
                serialsList.appendChild(span);
            });
        } else {
            serialsSection.classList.add('d-none');
        }
    } else {
        serialsSection.classList.add('d-none');
    }

    // Fetch the accountable item's holder info for the "Returning To" panel
    const accDetails = document.getElementById('r_acc_details');
    accDetails.innerHTML = '<span class="text-muted small"><i class="bx bx-loader bx-spin"></i> Loading…</span>';

    if (accId) {
        fetch(`borrow_items_api.php?fetch_accountable_serials=1&accountable_id=${encodeURIComponent(accId)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.person_name) {
                        html += `<span class="fw-semibold">${escHtml(data.person_name)}</span><br>`;
                    }
                    if (data.are_mr_ics_num) {
                        html += `<small class="text-muted">ARE/MR/ICS: ${escHtml(data.are_mr_ics_num)}</small><br>`;
                    }
                    if (data.property_number) {
                        html += `<small class="text-muted">Property#: ${escHtml(data.property_number)}</small>`;
                    }
                    accDetails.innerHTML = html || '<span class="text-muted small">—</span>';
                } else {
                    accDetails.innerHTML = '<span class="text-muted small">—</span>';
                }
            })
            .catch(() => {
                accDetails.innerHTML = '<span class="text-muted small text-danger">Could not load details.</span>';
            });
    } else {
        accDetails.innerHTML = '<span class="text-muted small">—</span>';
    }
});

/* ================================================================
   AJAX LIVE SEARCH — Available Items
   ================================================================ */
let availSearchTimer;
let availCurrentPage = <?= $page ?>;
let availCurrentSearch = '';

document.getElementById('availItemsSearch').addEventListener('input', function () {
    clearTimeout(availSearchTimer);
    const val = this.value.trim();
    document.getElementById('availSearchSpinner').classList.add('visible');
    availSearchTimer = setTimeout(() => {
        availCurrentSearch = val;
        availCurrentPage   = 1;
        fetchAvailItems(val, 1);
    }, 300);
});

// Pagination links (event delegation — works for both PHP-rendered and AJAX-rendered links)
document.getElementById('availItemsPagination').addEventListener('click', function (e) {
    const link = e.target.closest('.avail-page-link');
    if (!link) return;
    e.preventDefault();
    const pg = parseInt(link.dataset.page, 10);
    fetchAvailItems(availCurrentSearch, pg);
});

function fetchAvailItems(search, page) {
    document.getElementById('availSearchSpinner').classList.add('visible');
    const url = `borrow_items_api.php?account_search=${encodeURIComponent(search)}&account_page=${page}&page_size=10`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            document.getElementById('availSearchSpinner').classList.remove('visible');
            if (!data.success) return;
            renderAvailItems(data.accountables);
            availCurrentPage = data.accountables.page;
        })
        .catch(() => {
            document.getElementById('availSearchSpinner').classList.remove('visible');
        });
}

function renderAvailItems(data) {
    const tbody = document.getElementById('availItemsTbody');
    const pag   = document.getElementById('availItemsPagination');

    if (!data.items || data.items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No items found.</td></tr>';
        pag.innerHTML   = '';
        return;
    }

    tbody.innerHTML = data.items.map(row => `
        <tr>
            <td><strong>${escHtml(row.item_name)}</strong></td>
            <td>${escHtml(row.person_name)}</td>
            <td><span class="badge bg-label-info">${row.assigned_quantity}</span></td>
            <td class="text-center">
                <button class="btn btn-sm btn-icon btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#borrowModal"
                        data-id="${row.id}"
                        data-item="${escAttr(row.item_name)}"
                        data-person="${escAttr(row.person_name)}"
                        data-max="${row.assigned_quantity}"
                        data-serials="${escAttr(row.serial_number || '')}">
                    <i class="bx bx-plus"></i>
                </button>
            </td>
        </tr>
    `).join('');

    // Rebuild pagination
    const total      = data.total;
    const page_size  = data.page_size;
    const total_pgs  = Math.ceil(total / page_size);
    const cur        = data.page;

    if (total_pgs <= 1) { pag.innerHTML = ''; return; }

    let links = '';
    for (let i = 1; i <= total_pgs; i++) {
        links += `<li class="page-item ${i === cur ? 'active' : ''}">
            <a class="page-link avail-page-link" href="#" data-page="${i}">${i}</a>
        </li>`;
    }
    pag.innerHTML = `
        <div class="card-footer py-2">
            <nav><ul class="pagination justify-content-center mb-0">${links}</ul></nav>
        </div>`;
}

/* ================================================================
   AJAX LIVE SEARCH — Active Transactions
   ================================================================ */
let txSearchTimer;
let txCurrentPage = 1;
let txCurrentSearch = '';

document.getElementById('clientSearch').addEventListener('input', function () {
    clearTimeout(txSearchTimer);
    const val = this.value.trim();
    document.getElementById('txSearchSpinner').classList.add('visible');
    txSearchTimer = setTimeout(() => {
        txCurrentSearch = val;
        txCurrentPage   = 1;
        fetchTransactions(val, 1);
    }, 300);
});

function fetchTransactions(search, page) {
    document.getElementById('txSearchSpinner').classList.add('visible');
    const url = `borrow_items_api.php?borrow_search=${encodeURIComponent(search)}&borrow_page=${page}&page_size=50`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            document.getElementById('txSearchSpinner').classList.remove('visible');
            if (!data.success) return;
            renderTransactions(data.borrowed.items);
            txCurrentPage = data.borrowed.page;
        })
        .catch(() => {
            document.getElementById('txSearchSpinner').classList.remove('visible');
        });
}

function renderTransactions(items) {
    const tbody = document.getElementById('borrowedTbody');

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No active transactions.</td></tr>';
        return;
    }

    const statusBadge = {
        'PENDING':        'bg-label-warning',
        'APPROVED':       'bg-label-success',
        'RETURN_PENDING': 'bg-label-info',
        'RETURNED':       'bg-label-secondary',
        'DENIED':         'bg-label-danger',
    };

    tbody.innerHTML = items.map(b => {
        const badge = statusBadge[b.status] || 'bg-label-secondary';

        let statusHtml = b.is_returned
            ? '<span class="badge bg-label-secondary">Returned</span>'
            : `<span class="badge ${badge}">${escHtml(b.status)}</span>`;
        if (b.decision_remarks) {
            statusHtml += `<br><small class="text-muted">${escHtml(b.decision_remarks)}</small>`;
        }

        let serialHtml = b.serial_number
            ? `<br><small class="text-muted"><i class="bx bx-barcode-reader"></i> ${escHtml(b.serial_number)}</small>`
            : '';

        let actionHtml = '';
        if (b.status === 'PENDING' && (USER_ROLE === 'ADMIN' || USER_ROLE === 'MANAGER')) {
            actionHtml = `
                <button class="btn btn-sm btn-success" onclick="approveRequest(${b.borrow_id})">Approve</button>
                <button class="btn btn-sm btn-danger ms-1" onclick="denyRequest(${b.borrow_id})">Deny</button>`;
        } else if (b.status === 'RETURN_PENDING' && (USER_ROLE === 'ADMIN' || USER_ROLE === 'MANAGER')) {
            actionHtml = `
                <button class="btn btn-sm btn-success"
                        onclick="openAdminReturnModal(
                            ${b.borrow_id},
                            '${escAttr(b.item_name)}',
                            ${b.quantity},
                            '${escAttr(b.from_person)}',
                            '${escAttr(b.serial_number || '')}',
                            ${b.accountable_id},
                            '${escAttr(b.reference_no)}'
                        )">
                    <i class="bx bx-check-circle me-1"></i>Confirm Return
                </button>`;
        } else if (!b.is_returned && b.status === 'APPROVED') {
            if (USER_ROLE === 'STAFF') {
                actionHtml = `
                <button class="btn btn-sm btn-outline-warning"
                    data-bs-toggle="modal"
                    data-bs-target="#returnConfirmModal"
                    data-bid="${b.borrow_id}"
                    data-item="${escAttr(b.item_name)}"
                    data-qty="${b.quantity}"
                    data-from-person="${escAttr(b.from_person)}"
                    data-serials="${escAttr(b.serial_number || '')}"
                    data-acc-id="${b.accountable_id}"
                    data-ref="${escAttr(b.reference_no)}">
                    <i class="bx bx-send me-1"></i>Request Return
                </button>`;
            } else {
                actionHtml = `
                <button class="btn btn-sm btn-outline-danger"
                        onclick="openAdminReturnModal(
                            ${b.borrow_id},
                            '${escAttr(b.item_name)}',
                            ${b.quantity},
                            '${escAttr(b.from_person)}',
                            '${escAttr(b.serial_number || '')}',
                            ${b.accountable_id},
                            '${escAttr(b.reference_no)}'
                        )">
                    <i class="bx bx-check-circle me-1"></i>Confirm Return
                </button>`;
            }
        }

        return `<tr>
            <td>
                <span class="fw-bold text-primary">#${escHtml(b.reference_no)}</span><br>
                <small class="text-muted">${escHtml(b.borrow_date || '')}</small>
            </td>
            <td>
                <strong>${escHtml(b.item_name)}</strong><br>
                <small>Qty: ${b.quantity}</small> | <small>From: ${escHtml(b.from_person)}</small>
                ${serialHtml}
            </td>
            <td>${escHtml(b.to_person || '')}</td>
            <td>${statusHtml}</td>
            <td>${actionHtml}</td>
        </tr>`;
    }).join('');
}

/* ================================================================
   FORM SETUP
   ================================================================ */
setupAjaxForm('borrowForm');
setupAjaxForm('returnForm');

/* ================================================================
   SESSION FLASH TOASTS
   ================================================================ */
<?php if (isset($_SESSION['success'])): ?>
    showToast("<?= addslashes($_SESSION['success']) ?>", 'success');
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    showToast("<?= addslashes($_SESSION['error']) ?>", 'danger');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

<!-- ============================================================ -->
<!-- ADMIN / MANAGER — CONFIRM RETURN MODAL                       -->
<!-- Staff-initiated RETURN_PENDING items land here for review    -->
<!-- ============================================================ -->
<div class="modal fade" id="adminReturnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="adminReturnForm" method="POST">
                <input type="hidden" name="action"                  value="admin_approve_return">
                <input type="hidden" name="csrf_token"              value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="borrow_id"               id="ar_borrow_id">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-check-circle me-2"></i>Confirm Item Return
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Item panel -->
                    <div class="card border border-primary mb-3">
                        <div class="card-body py-3">
                            <h6 class="fw-bold text-primary mb-3">
                                <i class="bx bx-package me-1"></i>Item Being Returned
                            </h6>
                            <div class="row g-2">
                                <div class="col-sm-5">
                                    <small class="text-uppercase text-muted d-block" style="font-size:.7rem;letter-spacing:.5px">Item Name</small>
                                    <strong id="ar_item_name" class="fs-6"></strong>
                                </div>
                                <div class="col-sm-3">
                                    <small class="text-uppercase text-muted d-block" style="font-size:.7rem;letter-spacing:.5px">Qty to Return</small>
                                    <span id="ar_qty" class="badge bg-label-primary fs-6"></span>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-uppercase text-muted d-block" style="font-size:.7rem;letter-spacing:.5px">Reference</small>
                                    <span id="ar_ref" class="fw-semibold text-primary" style="font-size:.85rem;"></span>
                                </div>
                            </div>
                            <!-- Serial numbers on the borrowed record -->
                            <div id="ar_serials_section" class="mt-3 d-none">
                                <small class="text-uppercase text-muted d-block mb-1" style="font-size:.7rem;letter-spacing:.5px">
                                    <i class="bx bx-barcode-reader me-1"></i>Serial Number(s) Being Returned
                                </small>
                                <div id="ar_serials_list" class="d-flex flex-wrap gap-1"></div>
                                <div class="alert alert-info py-2 mt-2" style="font-size:.82rem;">
                                    <i class="bx bx-sync me-1"></i>
                                    These serial(s) will be <strong>restored</strong> to the accountable record upon approval.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Returning to panel -->
                    <div class="card border border-info mb-3">
                        <div class="card-body py-3">
                            <h6 class="fw-bold text-info mb-3">
                                <i class="bx bx-user-check me-1"></i>Returning To
                            </h6>
                            <div class="row g-2">
                                <div class="col-sm-5">
                                    <small class="text-uppercase text-muted d-block" style="font-size:.7rem;letter-spacing:.5px">From Person (account holder)</small>
                                    <strong id="ar_from_person" class="fs-6"></strong>
                                </div>
                                <div class="col-sm-7" id="ar_acc_details_col">
                                    <small class="text-uppercase text-muted d-block" style="font-size:.7rem;letter-spacing:.5px">Accountable Record Details</small>
                                    <div id="ar_acc_details">
                                        <span class="text-muted small"><i class="bx bx-loader bx-spin"></i> Loading…</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="mb-1">
                        <label class="form-label fw-semibold">
                            Decision Remarks <small class="text-muted fw-normal">(optional)</small>
                        </label>
                        <textarea name="return_decision_remarks" class="form-control" rows="2"
                                  placeholder="Item condition, notes on the return…"></textarea>
                    </div>
                </div>

                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i>Cancel
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-danger" id="ar_deny_btn"
                                onclick="denyReturnFromModal()">
                            <i class="bx bx-x-circle me-1"></i>Deny Return
                        </button>
                        <button type="submit" class="btn btn-success fw-semibold">
                            <i class="bx bx-check me-1"></i>Confirm &amp; Process Return
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>