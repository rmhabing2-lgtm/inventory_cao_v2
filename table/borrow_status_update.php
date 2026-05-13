<?php
/**
 * borrow_status_update.php — Upgraded
 * =====================================
 * Handles the staff-initiated RETURN_PENDING flow only.
 * All ADMIN approve/deny decisions are in borrow_approvals.php.
 *
 * Changes:
 *  1. Writes to system_logs on every state change.
 *  2. Notifications sent to ADMIN + MANAGER (not just ADMIN).
 *  3. Duplicate session_start() and dead code blocks removed.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/notify_push.php';
require_once __DIR__ . '/../includes/system_log_helper.php'; // ← NEW

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['borrow_id'], $input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$borrow_id  = (int)$input['borrow_id'];
$status     = strtoupper(trim($input['status']));
$csrf       = $input['csrf_token'] ?? '';
$remarks    = trim($input['remarks'] ?? '');
$return_qty = isset($input['return_qty']) ? (int)$input['return_qty'] : 0;
$actorId    = (int)$_SESSION['id'];
$userRole   = strtoupper($_SESSION['role'] ?? 'STAFF');

// CSRF check
if (!isset($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

// Only staff-initiated RETURN_PENDING is allowed here; everything else requires ADMIN or MANAGER via borrow_approvals.php
if ($status !== 'RETURN_PENDING') {
    if ($userRole !== 'ADMIN' && $userRole !== 'MANAGER') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Use borrow_approvals.php for admin/manager decisions.']);
        exit;
    }
}

try {
    $conn->begin_transaction();

    // Lock the borrow row
    $sel = $conn->prepare(
        "SELECT b.*, ii.item_name
         FROM borrowed_items b
         JOIN inventory_items ii ON ii.id = b.inventory_item_id
         WHERE b.borrow_id = ? FOR UPDATE"
    );
    $sel->bind_param('i', $borrow_id);
    $sel->execute();
    $borrow = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$borrow) throw new Exception('Borrow record not found.');

    // ----------------------------------------------------------------
    // RETURN_PENDING  — staff flags item for return, notifies admins + managers
    // ----------------------------------------------------------------
    if ($status === 'RETURN_PENDING') {
        if ($borrow['status'] !== 'APPROVED') {
            throw new Exception('Only approved borrows can be flagged for return.');
        }

        $upd = $conn->prepare(
            "UPDATE borrowed_items
             SET status = 'RETURN_PENDING', return_requested_by = ?, return_requested_at = NOW()
             WHERE borrow_id = ?"
        );
        $upd->bind_param('ii', $actorId, $borrow_id);
        $upd->execute();
        $upd->close();

        $payloadArr = [
            'message'   => "Return request for Borrow #{$borrow_id}",
            'quantity'  => $return_qty,
            'remarks'   => $remarks,
            'reference' => $borrow['reference_no'] ?? null,
            'item'      => $borrow['item_name'],
            'borrow_id' => $borrow_id,
        ];

        // Notify ADMIN + MANAGER
        notify_roles($conn, ['ADMIN', 'MANAGER'], $actorId, 'return_request', $borrow_id, $payloadArr);

        // ★ system_logs
        log_system($conn, $actorId, 'RETURN_REQUEST',
            "Staff #{$actorId} submitted return request for Borrow #{$borrow_id} — {$borrow['item_name']} x{$return_qty} (ref: {$borrow['reference_no']})",
            ['borrow_id' => $borrow_id, 'qty' => $return_qty]
        );

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Return request submitted']);
        exit;
    }

    // ----------------------------------------------------------------
    // ADMIN/MANAGER flows that somehow land here — redirect gracefully
    // ----------------------------------------------------------------
    // (All admin/manager decisions should go through borrow_approvals.php, but
    //  if they arrive here we handle APPROVED / DENIED as a fallback.)

    $adminId    = $actorId;
    $actorLabel = ucfirst(strtolower($userRole)); // "Admin" or "Manager"

    if ($borrow['status'] === $status) {
        $conn->rollback();
        echo json_encode(['success' => true, 'message' => 'Status is already ' . $status]);
        exit;
    }

    if ($borrow['status'] === 'PENDING' && $status === 'APPROVED') {
        $acc = $conn->prepare("SELECT assigned_quantity FROM accountable_items WHERE id = ? FOR UPDATE");
        $acc->bind_param('i', $borrow['accountable_id']);
        $acc->execute();
        $acc_row = $acc->get_result()->fetch_assoc();
        $acc->close();

        if ((int)$acc_row['assigned_quantity'] < (int)$borrow['quantity']) {
            throw new Exception('Insufficient stock to approve this request.');
        }

        $new_qty = (int)$acc_row['assigned_quantity'] - (int)$borrow['quantity'];
        $upd_acc = $conn->prepare("UPDATE accountable_items SET assigned_quantity = ? WHERE id = ?");
        $upd_acc->bind_param('ii', $new_qty, $borrow['accountable_id']);
        $upd_acc->execute();
        $upd_acc->close();

        $trans = $conn->prepare(
            "INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
             VALUES (?, 'OUT', ?, ?, NOW())"
        );
        $trans->bind_param('iis', $borrow['inventory_item_id'], $borrow['quantity'], $borrow['reference_no']);
        $trans->execute();
        $trans->close();
    }

    $upd = $conn->prepare("UPDATE borrowed_items SET status = ?, approved_by = ?, approved_at = NOW() WHERE borrow_id = ?");
    $upd->bind_param('sii', $status, $adminId, $borrow_id);
    $upd->execute();
    $upd->close();

    $type    = ($status === 'APPROVED') ? 'borrow_approved' : 'borrow_denied';
    $payload = json_encode(['borrow_id' => $borrow_id, 'decision' => $status, 'reference' => $borrow['reference_no'], 'item' => $borrow['item_name']]);
    $notif   = $conn->prepare(
        "INSERT INTO notifications (user_id, actor_user_id, type, related_id, payload, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    if ($notif) {
        $notif->bind_param('iiiss', $borrow['requested_by'], $adminId, $type, $borrow_id, $payload);
        $notif->execute();
        $notif->close();
    }

    // Cross-notify: MANAGER action notifies ADMIN; ADMIN action notifies MANAGER
    $crossRoles = ($userRole === 'MANAGER') ? ['ADMIN'] : ['MANAGER'];
    notify_roles($conn, $crossRoles, $adminId, $type . '_info', $borrow_id, [
        'borrow_id' => $borrow_id, 'item' => $borrow['item_name'], 'decision' => $status,
        'message'   => "Borrow #{$borrow_id} {$status} by {$actorLabel} #{$adminId}",
    ], $adminId);

    try {
        push_notification_ws((int)$borrow['requested_by'], 'borrow_decision', $borrow_id, ['status' => $status]);
    } catch (Throwable $_) {}

    // ★ system_logs
    log_system($conn, $adminId, 'BORROW_' . $status,
        "{$actorLabel} #{$adminId} set Borrow #{$borrow_id} to {$status} — {$borrow['item_name']} (ref: {$borrow['reference_no']})",
        ['borrow_id' => $borrow_id, 'actor_role' => $userRole]
    );
    log_audit($conn, $adminId, 'borrowed_items', 'UPDATE', $borrow_id,
        ['status' => $borrow['status']],
        ['status' => $status, 'approved_by' => $adminId, 'actor_role' => $userRole]
    );

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Status successfully updated to $status"]);

} catch (Exception $e) {
    try { $conn->rollback(); } catch (Throwable $_) {}
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}