<?php
/**
 * system_log_helper.php
 * ----------------------
 * Centralised helpers for:
 *   - system_logs   → log_system()
 *   - audit_logs    → log_audit()
 *   - notification_logs → log_notif_action()   ← NEW
 *   - notifications (fan-out) → notify_roles()
 *
 * Include after $conn is available:
 *   require_once __DIR__ . '/../includes/system_log_helper.php';
 */

if (!function_exists('log_system')) {
    /**
     * Insert a row into system_logs.
     *
     * @param mysqli  $conn
     * @param int     $userId      Acting user's system `user`.id  (0 = system)
     * @param string  $actionType  Short slug, e.g. 'BORROW_REQUEST', 'BORROW_APPROVED'
     * @param string  $description Human-readable sentence
     * @param array   $extra       Optional JSON payload appended to description
     */
    function log_system(mysqli $conn, int $userId, string $actionType, string $description, array $extra = []): void
    {
        try {
            if (!empty($extra)) {
                $description .= ' | ' . json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $description = mb_substr($description, 0, 65535);
            $stmt = $conn->prepare(
                "INSERT INTO system_logs (user_id, action_type, description, created_at)
                 VALUES (?, ?, ?, NOW())"
            );
            if ($stmt) {
                $stmt->bind_param('iss', $userId, $actionType, $description);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[system_log_helper:log_system] ' . $e->getMessage());
        }
    }
}

if (!function_exists('log_audit')) {
    /**
     * Insert a row into audit_logs for field-level change tracking.
     *
     * @param mysqli       $conn
     * @param int          $userId
     * @param string       $module   Table name, e.g. 'borrowed_items'
     * @param string       $action   'CREATE' | 'UPDATE' | 'DELETE'
     * @param int          $recordId Primary key of the affected row
     * @param array|null   $oldData  Snapshot before change (null for CREATE)
     * @param array|null   $newData  Snapshot after change  (null for DELETE)
     */
    function log_audit(mysqli $conn, int $userId, string $module, string $action, int $recordId, ?array $oldData, ?array $newData): void
    {
        try {
            $old = $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $new = $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt = $conn->prepare(
                "INSERT INTO audit_logs (user_id, module, action, record_id, old_data, new_data, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            if ($stmt) {
                $stmt->bind_param('ississ', $userId, $module, $action, $recordId, $old, $new);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[system_log_helper:log_audit] ' . $e->getMessage());
        }
    }
}

if (!function_exists('log_notif_action')) {
    /**
     * Insert a row into notification_logs to record server-side borrow/return actions.
     *
     * This mirrors the schema used by notification_logs.php and notifications_api.php
     * so all notification-adjacent events appear in one table for audit purposes.
     *
     * @param mysqli      $conn
     * @param int         $userId    Acting user
     * @param string      $message   Short action slug, e.g. 'borrow_request', 'borrow_approved'
     * @param array       $payload   Structured data (borrow_id, item, qty, etc.)
     * @param string|null $page      Originating page slug
     */
    function log_notif_action(mysqli $conn, int $userId, string $message, array $payload = [], ?string $page = null): void
    {
        try {
            $ip       = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $filename = $page ? ($page . '.php') : null;
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt = $conn->prepare(
                "INSERT INTO notification_logs
                    (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload)
                 VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, NULL, ?)"
            );
            if ($stmt) {
                $stmt->bind_param('issssss', $userId, $ip, $ua, $page, $filename, $message, $payloadJson);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[system_log_helper:log_notif_action] ' . $e->getMessage());
        }
    }
}

if (!function_exists('notify_roles')) {
    /**
     * Insert a notification row for every user that has one of the given roles,
     * then fire a best-effort WebSocket push.
     *
     * @param mysqli   $conn
     * @param array    $roles        e.g. ['ADMIN', 'MANAGER']
     * @param int      $actorUserId  Who triggered the event
     * @param string   $type         Notification type slug
     * @param int      $relatedId    borrow_id or other FK
     * @param array    $payload      Extra data stored as JSON
     * @param int|null $excludeUser  User to skip (e.g. skip the actor themselves)
     */
    function notify_roles(mysqli $conn, array $roles, int $actorUserId, string $type, int $relatedId, array $payload, ?int $excludeUser = null): void
    {
        if (empty($roles)) return;

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $typesStr     = str_repeat('s', count($roles));
        $sql          = "SELECT id FROM `user` WHERE role IN ($placeholders) AND status = 'ACTIVE'";
        if ($excludeUser) {
            $sql     .= ' AND id != ?';
            $typesStr .= 'i';
            $roles[]   = $excludeUser;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) return;

        $refs = [&$typesStr];
        foreach ($roles as $k => $v) { $refs[] = &$roles[$k]; }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        while ($row = $result->fetch_assoc()) {
            $uid = (int)$row['id'];
            $ins = $conn->prepare(
                "INSERT INTO notifications (user_id, actor_user_id, type, related_id, payload, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())"
            );
            if ($ins) {
                $ins->bind_param('iiiss', $uid, $actorUserId, $type, $relatedId, $payloadJson);
                $ins->execute();
                $ins->close();
            }
            if (function_exists('push_notification_ws')) {
                try { push_notification_ws($uid, $type, $relatedId, $payload); } catch (Throwable $_) {}
            }
        }
    }
}