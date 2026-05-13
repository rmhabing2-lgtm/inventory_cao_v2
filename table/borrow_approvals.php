<?php
/**
 * borrow_approvals.php — Revised
 * =================================
 * Single source of truth for all borrow/return approval decisions.
 *
 * Changes from previous version:
 *  1. Every decision writes to system_logs, audit_logs, AND notification_logs
 *     (3-layer audit trail: operational / field-diff / notification-action).
 *  2. Notifications fan out to ADMIN + MANAGER (not just ADMIN).
 *  3. The staff requester always gets a follow-up notification on approve OR deny.
 */

require_once __DIR__ . '/../includes/session.php';
require_login();

// ADMIN and MANAGER may approve/deny
$_actorRole = strtoupper($_SESSION['role'] ?? '');
if (!isset($_SESSION['role']) || !in_array($_actorRole, ['ADMIN', 'MANAGER'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'msg' => 'forbidden']);
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/notify_push.php';
require_once __DIR__ . '/../includes/system_log_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: borrow_items.php');
    exit;
}

$action    = $_POST['action']   ?? '';
$borrow_id = (int)($_POST['borrow_id'] ?? 0);
$remarks   = trim($_POST['remarks'] ?? '');
$adminId   = (int)$_SESSION['id'];
$actorRole = strtoupper($_SESSION['role'] ?? 'ADMIN'); // ADMIN or MANAGER
$actorLabel = ucfirst(strtolower($actorRole)); // "Admin" or "Manager"

// CSRF
if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'msg' => 'csrf_mismatch']);
    exit;
}

if (!$borrow_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'missing borrow_id']);
    exit;
}

$conn->begin_transaction();
try {
    // Lock the borrow row
    $s = $conn->prepare(
        "SELECT b.*, ii.item_name FROM borrowed_items b
         JOIN inventory_items ii ON ii.id = b.inventory_item_id
         WHERE b.borrow_id = ? FOR UPDATE"
    );
    $s->bind_param('i', $borrow_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row) throw new Exception('borrow not found');

    // ----------------------------------------------------------------
    // Helper: send a notification to a specific user + WS push
    // ----------------------------------------------------------------
    $sendNotif = function (int $uid, string $type, string $action_val, string $payloadJson) use ($conn, $adminId, $borrow_id): void {
        $n = $conn->prepare(
            "INSERT INTO notifications
                (user_id, actor_user_id, type, related_id, payload, is_read, action, action_by, action_at, created_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())"
        );
        if ($n) {
            $n->bind_param('iiisssi', $uid, $adminId, $type, $borrow_id, $payloadJson, $action_val, $adminId);
            $n->execute();
            $n->close();
        }
        if (function_exists('push_notification_ws')) {
            try { push_notification_ws($uid, $type, $borrow_id, json_decode($payloadJson, true) ?? []); } catch (Throwable $_) {}
        }
    };

    // ----------------------------------------------------------------
    // Helper: mark original pending notification as actioned
    // ----------------------------------------------------------------
    $markNotifActioned = function (string $type, string $action_val) use ($conn, $adminId, $borrow_id): void {
        try {
            $u = $conn->prepare(
                "UPDATE notifications SET action = ?, action_by = ?, action_at = NOW(), is_read = 1
                 WHERE type = ? AND related_id = ?"
            );
            if ($u) { $u->bind_param('siis', $action_val, $adminId, $type, $borrow_id); $u->execute(); $u->close(); }
        } catch (Throwable $_) {}
    };

    // ================================================================
    // BORROW APPROVE
    // ================================================================
    if ($action === 'approve') {
        if ($row['status'] !== 'PENDING') throw new Exception('borrow not pending');

        $accId = (int)$row['accountable_id'];
        $qty   = (int)$row['quantity'];

        $s2 = $conn->prepare("SELECT assigned_quantity FROM accountable_items WHERE id = ? FOR UPDATE");
        $s2->bind_param('i', $accId);
        $s2->execute();
        $accRow = $s2->get_result()->fetch_assoc();
        $s2->close();

        if (!$accRow) throw new Exception('accountable item not found');
        if ($qty > (int)$accRow['assigned_quantity']) throw new Exception('Insufficient assigned quantity to approve');

        $newAssigned = (int)$accRow['assigned_quantity'] - $qty;
        $u = $conn->prepare("UPDATE accountable_items SET assigned_quantity = ? WHERE id = ?");
        $u->bind_param('ii', $newAssigned, $accId);
        $u->execute();
        $u->close();

        $upd = $conn->prepare(
            "UPDATE borrowed_items SET status = 'APPROVED', approved_by = ?, approved_at = NOW(), decision_remarks = ? WHERE borrow_id = ?"
        );
        $upd->bind_param('isi', $adminId, $remarks, $borrow_id);
        $upd->execute();
        $upd->close();

        $ref = $row['reference_no'] ?: 'BORROW-' . date('YmdHis') . '-' . rand(1000, 9999);
        $t = $conn->prepare(
            "INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
             VALUES (?, 'OUT', ?, ?, NOW())"
        );
        $t->bind_param('iis', $row['inventory_item_id'], $qty, $ref);
        $t->execute();
        $t->close();

        // Notify requester (staff)
        $reqId = (int)($row['requested_by'] ?? 0);
        if ($reqId) {
            $p = json_encode(['reference' => $ref, 'borrow_id' => $borrow_id, 'item' => $row['item_name'], 'qty' => $qty]);
            $sendNotif($reqId, 'borrow_approved', 'APPROVED', $p);
            @push_borrow_update_ws($borrow_id, 'APPROVED', ['reference' => $ref, 'borrow_id' => $borrow_id]);
        }

        // Notify all MANAGERs (only if actor is ADMIN; skip if actor IS manager to avoid self-notify)
        if ($actorRole === 'ADMIN') {
            notify_roles($conn, ['MANAGER'], $adminId, 'borrow_approved_info', $borrow_id, [
                'reference' => $ref, 'borrow_id' => $borrow_id,
                'item'      => $row['item_name'], 'qty' => $qty,
                'message'   => "Borrow #{$borrow_id} approved by {$actorLabel} #{$adminId}",
            ], $adminId);
        }

        $markNotifActioned('borrow_request', 'APPROVED');

        log_system($conn, $adminId, 'BORROW_APPROVED',
            "{$actorLabel} #{$adminId} approved Borrow #{$borrow_id} — {$row['item_name']} x{$qty} (ref: {$ref}). Remarks: {$remarks}",
            ['borrow_id' => $borrow_id, 'ref' => $ref, 'qty' => $qty, 'actor_role' => $actorRole]
        );
        log_audit($conn, $adminId, 'borrowed_items', 'UPDATE', $borrow_id,
            ['status' => 'PENDING', 'assigned_quantity_before' => $accRow['assigned_quantity']],
            ['status' => 'APPROVED', 'approved_by' => $adminId, 'decision_remarks' => $remarks]
        );
        log_notif_action($conn, $adminId, 'borrow_approved', [
            'borrow_id' => $borrow_id, 'ref' => $ref, 'qty' => $qty,
            'item' => $row['item_name'], 'requester_id' => $reqId, 'remarks' => $remarks,
        ], 'borrow_approvals');

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => 'approved']);
        exit;
    }

    // ================================================================
    // BORROW DENY
    // ================================================================
    if ($action === 'deny') {
        if ($row['status'] !== 'PENDING') throw new Exception('borrow not pending');

        $upd = $conn->prepare(
            "UPDATE borrowed_items SET status = 'DENIED', approved_by = ?, approved_at = NOW(), decision_remarks = ? WHERE borrow_id = ?"
        );
        $upd->bind_param('isi', $adminId, $remarks, $borrow_id);
        $upd->execute();
        $upd->close();

        $reqId = (int)($row['requested_by'] ?? 0);
        if ($reqId) {
            $p = json_encode(['reason' => $remarks, 'borrow_id' => $borrow_id, 'item' => $row['item_name']]);
            $sendNotif($reqId, 'borrow_denied', 'DENIED', $p);
            @push_borrow_update_ws($borrow_id, 'DENIED', ['reason' => $remarks, 'borrow_id' => $borrow_id]);
        }

        // Notify MANAGERs (only if actor is ADMIN)
        if ($actorRole === 'ADMIN') {
            notify_roles($conn, ['MANAGER'], $adminId, 'borrow_denied_info', $borrow_id, [
                'borrow_id' => $borrow_id, 'item' => $row['item_name'], 'reason' => $remarks,
                'message'   => "Borrow #{$borrow_id} denied by {$actorLabel} #{$adminId}",
            ], $adminId);
        }

        $markNotifActioned('borrow_request', 'DENIED');

        log_system($conn, $adminId, 'BORROW_DENIED',
            "{$actorLabel} #{$adminId} denied Borrow #{$borrow_id} — {$row['item_name']}. Reason: {$remarks}",
            ['borrow_id' => $borrow_id, 'actor_role' => $actorRole]
        );
        log_audit($conn, $adminId, 'borrowed_items', 'UPDATE', $borrow_id,
            ['status' => 'PENDING'],
            ['status' => 'DENIED', 'approved_by' => $adminId, 'decision_remarks' => $remarks]
        );
        log_notif_action($conn, $adminId, 'borrow_denied', [
            'borrow_id' => $borrow_id, 'item' => $row['item_name'],
            'requester_id' => $reqId, 'reason' => $remarks,
        ], 'borrow_approvals');

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => 'denied']);
        exit;
    }

    // ================================================================
    // RETURN APPROVE
    // ================================================================
    if ($action === 'approve_return') {
        $return_qty = (int)($_POST['return_quantity'] ?? 0);
        if ($return_qty <= 0) throw new Exception('invalid return quantity');
        if ($row['is_returned'])                         throw new Exception('already returned');
        if ($return_qty > (int)$row['quantity'])         throw new Exception('return quantity exceeds borrowed quantity');

        $accId = (int)$row['accountable_id'];
        $s2 = $conn->prepare("SELECT assigned_quantity FROM accountable_items WHERE id = ? FOR UPDATE");
        $s2->bind_param('i', $accId);
        $s2->execute();
        $accRow = $s2->get_result()->fetch_assoc();
        $s2->close();
        if (!$accRow) throw new Exception('accountable item not found');

        $newAssigned = (int)$accRow['assigned_quantity'] + $return_qty;
        $u = $conn->prepare("UPDATE accountable_items SET assigned_quantity = ? WHERE id = ?");
        $u->bind_param('ii', $newAssigned, $accId);
        $u->execute();
        $u->close();

        $isFull = ($return_qty === (int)$row['quantity']);
        if ($isFull) {
            $upd = $conn->prepare(
                "UPDATE borrowed_items SET is_returned = 1, return_date = NOW(), quantity = 0, status = 'RETURNED',
                 decision_remarks = ?, return_approved_by = ?, return_approved_at = NOW() WHERE borrow_id = ?"
            );
            $upd->bind_param('sii', $remarks, $adminId, $borrow_id);
        } else {
            $upd = $conn->prepare(
                "UPDATE borrowed_items SET quantity = quantity - ?, decision_remarks = ?,
                 return_approved_by = ?, return_approved_at = NOW() WHERE borrow_id = ?"
            );
            $upd->bind_param('isii', $return_qty, $remarks, $adminId, $borrow_id);
        }
        $upd->execute();
        $upd->close();

        $ref = 'RETURN-' . date('YmdHis') . '-' . rand(1000, 9999);
        $t = $conn->prepare(
            "INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, transaction_date)
             VALUES (?, 'IN', ?, ?, NOW())"
        );
        $t->bind_param('iis', $row['inventory_item_id'], $return_qty, $ref);
        $t->execute();
        $t->close();

        // Find who made the return request
        $reqId = (int)($row['return_requested_by'] ?? 0);
        if (!$reqId) {
            $actQ = $conn->prepare(
                "SELECT actor_user_id FROM notifications WHERE type = 'return_request' AND related_id = ? ORDER BY created_at DESC LIMIT 1"
            );
            if ($actQ) {
                $actQ->bind_param('i', $borrow_id);
                $actQ->execute();
                $actRow = $actQ->get_result()->fetch_assoc();
                $actQ->close();
            }
            $reqId = (int)($actRow['actor_user_id'] ?? $row['requested_by'] ?? 0);
        }

        if ($reqId) {
            $p = json_encode(['reference' => $ref, 'borrow_id' => $borrow_id, 'quantity' => $return_qty, 'item' => $row['item_name']]);
            $sendNotif($reqId, 'return_approved', 'APPROVED', $p);
            @push_borrow_update_ws($borrow_id, 'RETURN_APPROVED', ['reference' => $ref, 'borrow_id' => $borrow_id, 'quantity' => $return_qty]);
        }

        // Notify MANAGERs (only if actor is ADMIN)
        if ($actorRole === 'ADMIN') {
            notify_roles($conn, ['MANAGER'], $adminId, 'return_approved_info', $borrow_id, [
                'reference' => $ref, 'borrow_id' => $borrow_id, 'item' => $row['item_name'], 'quantity' => $return_qty,
                'message'   => "Return for Borrow #{$borrow_id} approved by {$actorLabel} #{$adminId}",
            ], $adminId);
        }

        $markNotifActioned('return_request', 'APPROVED');

        log_system($conn, $adminId, 'RETURN_APPROVED',
            "{$actorLabel} #{$adminId} approved return for Borrow #{$borrow_id} — {$row['item_name']} x{$return_qty} (ref: {$ref}). Remarks: {$remarks}",
            ['borrow_id' => $borrow_id, 'return_qty' => $return_qty, 'actor_role' => $actorRole]
        );
        log_audit($conn, $adminId, 'borrowed_items', 'UPDATE', $borrow_id,
            ['status' => $row['status'], 'quantity' => $row['quantity'], 'is_returned' => 0, 'assigned_qty_before' => $accRow['assigned_quantity']],
            ['status' => $isFull ? 'RETURNED' : $row['status'], 'quantity' => $row['quantity'] - $return_qty,
             'is_returned' => (int)$isFull, 'return_approved_by' => $adminId]
        );
        log_notif_action($conn, $adminId, 'return_approved', [
            'borrow_id' => $borrow_id, 'return_qty' => $return_qty,
            'item' => $row['item_name'], 'ref' => $ref,
            'requester_id' => $reqId, 'full_return' => $isFull,
        ], 'borrow_approvals');

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => 'return_approved']);
        exit;
    }

    // ================================================================
    // RETURN DENY
    // ================================================================
    if ($action === 'deny_return') {
        $reqId = (int)($row['return_requested_by'] ?? 0);
        if (!$reqId) {
            $actQ = $conn->prepare(
                "SELECT actor_user_id FROM notifications WHERE type = 'return_request' AND related_id = ? ORDER BY created_at DESC LIMIT 1"
            );
            if ($actQ) {
                $actQ->bind_param('i', $borrow_id);
                $actQ->execute();
                $actRow = $actQ->get_result()->fetch_assoc();
                $actQ->close();
            }
            $reqId = (int)($actRow['actor_user_id'] ?? $row['requested_by'] ?? 0);
        }

        // Restore status back to APPROVED (item still borrowed)
        $upd = $conn->prepare(
            "UPDATE borrowed_items SET status = 'APPROVED', return_decision_remarks = ? WHERE borrow_id = ?"
        );
        $upd->bind_param('si', $remarks, $borrow_id);
        $upd->execute();
        $upd->close();

        if ($reqId) {
            $p = json_encode(['reason' => $remarks, 'borrow_id' => $borrow_id, 'item' => $row['item_name']]);
            $sendNotif($reqId, 'return_denied', 'DENIED', $p);
            @push_borrow_update_ws($borrow_id, 'RETURN_DENIED', ['reason' => $remarks, 'borrow_id' => $borrow_id]);
        }

        // Notify MANAGERs (only if actor is ADMIN)
        if ($actorRole === 'ADMIN') {
            notify_roles($conn, ['MANAGER'], $adminId, 'return_denied_info', $borrow_id, [
                'borrow_id' => $borrow_id, 'item' => $row['item_name'], 'reason' => $remarks,
                'message'   => "Return for Borrow #{$borrow_id} denied by {$actorLabel} #{$adminId}",
            ], $adminId);
        }

        $markNotifActioned('return_request', 'DENIED');

        log_system($conn, $adminId, 'RETURN_DENIED',
            "{$actorLabel} #{$adminId} denied return for Borrow #{$borrow_id} — {$row['item_name']}. Reason: {$remarks}",
            ['borrow_id' => $borrow_id, 'actor_role' => $actorRole]
        );
        log_audit($conn, $adminId, 'borrowed_items', 'UPDATE', $borrow_id,
            ['status' => $row['status']],
            ['status' => 'APPROVED', 'return_decision_remarks' => $remarks]
        );
        log_notif_action($conn, $adminId, 'return_denied', [
            'borrow_id' => $borrow_id, 'item' => $row['item_name'],
            'requester_id' => $reqId, 'reason' => $remarks,
        ], 'borrow_approvals');

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => 'return_denied']);
        exit;
    }

    throw new Exception('unknown action: ' . $action);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
    exit;
}