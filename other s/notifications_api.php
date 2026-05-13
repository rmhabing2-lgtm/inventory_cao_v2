<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

// Simple AJAX API for notifications
header('Content-Type: application/json');

// allow GET poll for lightweight polling without CSRF
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'poll') {
    $action = 'poll';
} else {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'msg' => 'method']);
        exit;
    }
    $action = $_POST['action'] ?? '';
}
$user_id = $_SESSION['id'] ?? 0;
// CSRF required for state-changing POST actions
if (in_array($action, ['mark_read', 'mark_all', 'approve', 'deny', 'toggle_realtime'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'msg' => 'csrf']);
        exit;
    }
}

try {
    if ($action === 'mark_read') {
        $nid = intval($_POST['id'] ?? 0);
        if (!$nid) throw new Exception('id');
        $u = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $u->bind_param('ii', $nid, $user_id);
        $u->execute();
        $u->close();
        // return new count
        $c = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0');
        $c->bind_param('i', $user_id); $c->execute(); $rc = $c->get_result()->fetch_assoc(); $c->close();
        // log the mark-read action for audit / reflection (fallback to common notification_logs schema)
        try {
            if (!empty($conn) && ($stmt = $conn->prepare("INSERT INTO notification_logs (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)") )) {
                $msg = 'mark_read';
                $payloadJson = json_encode(['notification_id' => $nid, 'action' => 'mark_read', 'by' => $user_id]);
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $page = 'notifications_api';
                $filename = 'notifications_api.php';
                $lineno = null; $colno = null; $stack = null;
                $stmt->bind_param('issssiiiss', $user_id, $ip, $ua, $page, $filename, $lineno, $colno, $msg, $stack, $payloadJson);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            // ignore logging failures
        }
        echo json_encode(['success' => true, 'unread' => intval($rc['cnt'] ?? 0)]);
        exit;
    }
    if ($action === 'mark_all') {
        $u = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $u->bind_param('i', $user_id); $u->execute(); $u->close();
        // log mark-all as a single action (fallback to common notification_logs schema)
        try {
            if (!empty($conn) && ($stmt = $conn->prepare("INSERT INTO notification_logs (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"))) {
                $msg = 'mark_all_read';
                $payloadJson = json_encode(['action' => 'mark_all', 'by' => $user_id]);
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $page = 'notifications_api';
                $filename = 'notifications_api.php';
                $lineno = null; $colno = null; $stack = null;
                $stmt->bind_param('issssiiiss', $user_id, $ip, $ua, $page, $filename, $lineno, $colno, $msg, $stack, $payloadJson);
                @$stmt->execute(); @$stmt->close();
            }
        } catch (Exception $e) {
        }
        echo json_encode(['success' => true, 'unread' => 0]);
        exit;
    }
    if ($action === 'toggle_realtime') {
        // toggle realtime notifications for this user (stored in session)
        // expects 'enabled' => '0' or '1'
        $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;
        $_SESSION['realtime_disabled'] = $enabled ? 0 : 1;
        echo json_encode(['success' => true, 'realtime_disabled' => intval($_SESSION['realtime_disabled'] ?? 0)]);
        exit;
    }
    if ($action === 'approve' || $action === 'deny') {
        // only admins can approve/deny
        $role = $_SESSION['role'] ?? 'STAFF';
        if (strtoupper($role) !== 'ADMIN') {
            echo json_encode(['success' => false, 'msg' => 'unauthorized']);
            exit;
        }
        $nid = intval($_POST['id'] ?? 0);
        if (!$nid) { echo json_encode(['success' => false, 'msg' => 'id']); exit; }

        // fetch notification
        $q = $conn->prepare('SELECT id, user_id, actor_user_id, type, related_id, payload FROM notifications WHERE id = ? LIMIT 1');
        $q->bind_param('i', $nid);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        if (!$row) { echo json_encode(['success' => false, 'msg' => 'notfound']); exit; }

        // mark original notification as read and record action (so it no longer appears in unread lists)
        $actVal = ($action === 'approve') ? 'APPROVED' : 'DENIED';
        $u = $conn->prepare('UPDATE notifications SET is_read = 1, action = ?, action_by = ?, action_at = NOW() WHERE id = ?');
        $u->bind_param('sii', $actVal, $admin_id, $nid); $u->execute(); $u->close();

        // create a follow-up notification to the actor_user_id informing them of the decision
        $actor = intval($row['actor_user_id'] ?? 0);
        $admin_id = intval($user_id);
        $decision = ($action === 'approve') ? 'approved' : 'denied';
        $new_type = $row['type'] . '_' . $decision;
        $payload = json_encode(array_merge(is_array(json_decode($row['payload'] ?? 'null', true)) ? json_decode($row['payload'], true) : [], ['decision' => $decision, 'admin_id' => $admin_id]));
        if ($actor) {
            $ins = $conn->prepare('INSERT INTO notifications (user_id, actor_user_id, type, related_id, payload, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
            $ins->bind_param('iisis', $actor, $admin_id, $new_type, $row['related_id'], $payload);
            @$ins->execute(); @$ins->close();
        }

        // log the action (fallback to common notification_logs schema)
        try {
            if (!empty($conn) && ($stmt = $conn->prepare("INSERT INTO notification_logs (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)") )) {
                $msg = 'notification_' . $decision;
                $pl = json_encode(['notification_id' => $nid, 'decision' => $decision, 'admin_id' => $admin_id]);
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $page = 'notifications_api';
                $filename = 'notifications_api.php';
                $lineno = null; $colno = null; $stack = null;
                $stmt->bind_param('issssiiiss', $user_id, $ip, $ua, $page, $filename, $lineno, $colno, $msg, $stack, $pl);
                @$stmt->execute(); @$stmt->close();
            }
        } catch (Exception $e) {}

        // return updated unread count for admin
        $c = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0');
        $c->bind_param('i', $user_id); $c->execute(); $rc = $c->get_result()->fetch_assoc(); $c->close();
        echo json_encode(['success' => true, 'unread' => intval($rc['cnt'] ?? 0)]);
        exit;
    }

    if ($action === 'poll') {
        // return unread count and latest 6 UNREAD notifications for the user
        $c = $conn->prepare('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0');
        $c->bind_param('i', $user_id); $c->execute(); $rc = $c->get_result()->fetch_assoc(); $c->close();
        $unread = intval($rc['cnt'] ?? 0);
        $items = [];
        $qn = $conn->prepare(
            "SELECT n.*, ii.item_name, b.from_person, b.to_person, CONCAT_WS(' ', u.first_name, u.last_name) AS admin_name
             FROM notifications n
             LEFT JOIN borrowed_items b ON n.related_id = b.borrow_id
             LEFT JOIN inventory_items ii ON b.inventory_item_id = ii.id
             LEFT JOIN `user` u ON n.actor_user_id = u.id
             WHERE n.user_id = ? AND n.is_read = 0
             ORDER BY n.created_at DESC LIMIT 6"
        );
        if ($qn) {
            $qn->bind_param('i', $user_id);
            $qn->execute();
            $items = $qn->get_result()->fetch_all(MYSQLI_ASSOC);
            $qn->close();
        }
        echo json_encode(['success' => true, 'unread' => $unread, 'items' => $items]);
        exit;
    }
    throw new Exception('unknown');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
    exit;
}

?>
