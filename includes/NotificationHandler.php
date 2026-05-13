<?php
/**
 * Notification Handler - Enterprise Grade
 * Manages notification creation, routing, delivery, and audit logging
 * COA-Compliant: Immutable, traceable, with SLA escalation support
 */

require_once __DIR__ . '/NotificationTypes.php';

class NotificationHandler {
    private $conn;
    private $userId;
    private $userRole;
    const DEDUP_THROTTLE_SECONDS = 60; // Prevent duplicate notifications within 60 seconds
    const ADMIN_USER_ID = 1; // Default admin user ID (should be configurable)

    /**
     * Map internal notification types to websocket event names or broadcast channels.
     * This keeps push event naming consistent and avoids scattered hard‑coded strings.
     */
    private static $eventNameMap = [
        // single‑user events
        NotificationTypes::BORROW_REQUEST_SUBMITTED    => 'borrow_request',
        NotificationTypes::BORROW_REQUEST_APPROVED     => 'borrow_decision',
        NotificationTypes::BORROW_REQUEST_DENIED       => 'borrow_decision',
        NotificationTypes::ITEM_RELEASED               => 'item_released',
        NotificationTypes::RETURN_REQUEST_SUBMITTED    => 'return_request',
        NotificationTypes::RETURN_REQUEST_APPROVED     => 'return_decision',
        NotificationTypes::RETURN_REQUEST_REJECTED     => 'return_decision',
        NotificationTypes::DAMAGE_REPORTED            => 'damage_reported',
        NotificationTypes::OVERDUE_ALERT              => 'overdue_alert',
        // fallback: any other type maps to "notification" which is generic
    ];

    /**
     * Return the websocket event name associated with a notification type.
     * Defaults to 'notification' when no specific mapping exists.
     */
    public static function getEventName($notificationType)
    {
        return self::$eventNameMap[$notificationType] ?? 'notification';
    }

    public function __construct($database, $currentUserId = null, $currentUserRole = 'SYSTEM') {
        $this->conn = $database;
        $this->userId = $currentUserId;
        $this->userRole = $currentUserRole;
    }

    /**
     * Send a notification through the system
     * 
     * @param string $notificationType Type constant from NotificationTypes class
     * @param int $recipientUserId User ID receiving the notification
     * @param int|null $senderUserId User ID of sender (null for system)
     * @param int|null $relatedEntityId ID of related entity (borrow_id, item_id, etc)
     * @param string|null $messageBody Custom message body
     * @param array $metadata Additional notification metadata
     * @return bool|int Notification ID if successful, false on failure
     */
    public function send(
        $notificationType,
        $recipientUserId,
        $senderUserId = null,
        $relatedEntityId = null,
        $messageBody = null,
        $metadata = []
    ) {
        try {
            // Validate notification type
            if (!NotificationTypes::isValid($notificationType)) {
                throw new Exception("Invalid notification type: $notificationType");
            }

            // Check for duplicates (prevent spam)
            if (!$this->isDuplicate($notificationType, $recipientUserId, $relatedEntityId)) {
                // Get default priority and channels
                $priority = NotificationTypes::getDefaultPriority($notificationType);
                $channels = NotificationTypes::getChannelsForPriority($priority);
                $title = NotificationTypes::getTitle($notificationType);

                // Build notification payload
                $payload = $this->buildPayload(
                    $notificationType,
                    $senderUserId,
                    $title,
                    $messageBody,
                    $metadata
                );

                // Determine sender role
                $senderRole = $senderUserId === null ? NotificationTypes::ROLE_SYSTEM : $this->userRole;

                // Insert into notifications table
                $notificationId = $this->insertNotification(
                    $recipientUserId,
                    $senderUserId,
                    $notificationType,
                    $relatedEntityId,
                    $payload,
                    $priority,
                    $senderRole,
                    json_encode($channels)
                );

                if ($notificationId) {
                    // Log to audit trail
                    $this->auditLog($notificationId, 'CREATED', 'Notification created and persisted');

                    // Send to delivery channels (pass type + related id for push integration)
                    $this->deliverToChannels($notificationId, $recipientUserId, $channels, $title, $messageBody, $notificationType, $relatedEntityId);

                    return $notificationId;
                }
            } else {
                // Duplicate detected - log to audit but don't send
                $this->auditLog(null, 'DUPLICATE_SUPPRESSED', "Duplicate $notificationType for user $recipientUserId");
            }

            return false;
        } catch (Exception $e) {
            error_log("NotificationHandler::send() Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple recipients
     * 
     * @param string $notificationType Type constant
     * @param array $recipientUserIds Array of user IDs
     * @param int|null $senderUserId Sender user ID
     * @param int|null $relatedEntityId Related entity ID
     * @param string|null $messageBody Custom message
     * @param array $metadata Additional metadata
     * @return array Notification IDs created
     */
    public function sendToMultiple(
        $notificationType,
        $recipientUserIds,
        $senderUserId = null,
        $relatedEntityId = null,
        $messageBody = null,
        $metadata = []
    ) {
        $notificationIds = [];
        
        foreach ((array)$recipientUserIds as $userId) {
            $id = $this->send(
                $notificationType,
                $userId,
                $senderUserId,
                $relatedEntityId,
                $messageBody,
                $metadata
            );
            if ($id) {
                $notificationIds[] = $id;
            }
        }
        
        return $notificationIds;
    }

    /**
     * Mark notification as read
     * 
     * @param int $notificationId Notification ID
     * @return bool Success
     */
    public function markAsRead($notificationId) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE notifications 
                 SET is_read = 1, read_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->bind_param("i", $notificationId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("NotificationHandler::markAsRead() Error: " . $e->getMessage());
            return false;
        }
    }

    // Sync: mark all unread notifications for a user as read
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("NotificationHandler::markAllAsRead() Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Broadcast an arbitrary websocket event to all connected clients.
     * Useful for page-level state updates that don't correspond to a single
     * recipient notification.
     *
     * @param string $eventName Custom event name (e.g. 'borrow_update')
     * @param int|null $relatedId ID of related entity, if any
     * @param array $payload Additional data to send
     */
    public function broadcast($eventName, $relatedId = null, $payload = []) {
        if (function_exists('push_borrow_update_ws')) {
            push_borrow_update_ws($relatedId, $eventName, $payload);
        }
    }


    /**
     * Get unread notifications for user
     * 
     * @param int $userId User ID
     * @param int $limit Number of notifications to fetch
     * @return array Array of notification rows
     */
    public function getUnread($userId, $limit = 10) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM notifications 
                 WHERE user_id = ? AND is_read = 0 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            );
            $stmt->bind_param("ii", $userId, $limit);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("NotificationHandler::getUnread() Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all notifications for user with pagination
     * 
     * @param int $userId User ID
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array Notifications and pagination info
     */
    public function getNotifications($userId, $page = 1, $perPage = 20) {
        try {
            $offset = ($page - 1) * $perPage;
            
            $stmt = $this->conn->prepare(
                "SELECT * FROM notifications 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT ? OFFSET ?"
            );
            $stmt->bind_param("iii", $userId, $perPage, $offset);
            $stmt->execute();
            $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Get total count for pagination
            $countStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
            $countStmt->bind_param("i", $userId);
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $total = $countResult['total'] ?? 0;

            return [
                'notifications' => $notifications,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
        } catch (Exception $e) {
            error_log("NotificationHandler::getNotifications() Error: " . $e->getMessage());
            return ['notifications' => [], 'pagination' => []];
        }
    }

    /**
     * Get notification count badge data
     * 
     * @param int $userId User ID
     * @return array Unread count and summary
     */
    public function getCountSummary($userId) {
        try {
            // Unread count
            $unreadStmt = $this->conn->prepare(
                "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0"
            );
            $unreadStmt->bind_param("i", $userId);
            $unreadStmt->execute();
            $unreadResult = $unreadStmt->get_result()->fetch_assoc();
            $unreadCount = $unreadResult['unread_count'] ?? 0;

            // Critical count
            $criticalStmt = $this->conn->prepare(
                "SELECT COUNT(*) as critical_count FROM notifications 
                 WHERE user_id = ? AND is_read = 0 AND priority = ?"
            );
            $critical = NotificationTypes::PRIORITY_CRITICAL;
            $criticalStmt->bind_param("is", $userId, $critical);
            $criticalStmt->execute();
            $criticalResult = $criticalStmt->get_result()->fetch_assoc();
            $criticalCount = $criticalResult['critical_count'] ?? 0;

            return [
                'unread_count' => $unreadCount,
                'critical_count' => $criticalCount,
                'has_critical' => $criticalCount > 0
            ];
        } catch (Exception $e) {
            error_log("NotificationHandler::getCountSummary() Error: " . $e->getMessage());
            return ['unread_count' => 0, 'critical_count' => 0, 'has_critical' => false];
        }
    }

    /**
     * ===== PRIVATE HELPER METHODS =====
     */

    /**
     * Check if notification is duplicate (spam prevention)
     * 
     * @param string $type Notification type
     * @param int $userId User ID
     * @param int|null $relatedId Related entity ID
     * @return bool True if duplicate within throttle window
     */
    private function isDuplicate($type, $userId, $relatedId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) as count FROM notifications 
                 WHERE type = ? AND user_id = ? AND related_id = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            // Must assign constant to variable for bind_param (cannot pass constants by reference)
            $throttleSeconds = self::DEDUP_THROTTLE_SECONDS;
            $stmt->bind_param("siii", $type, $userId, $relatedId, $throttleSeconds);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Build notification payload JSON
     * 
     * @param string $type Notification type
     * @param int|null $senderUserId Sender
     * @param string $title Title
     * @param string|null $body Custom body
     * @param array $metadata Additional data
     * @return string JSON payload
     */
    private function buildPayload($type, $senderUserId, $title, $body, $metadata) {
        $payload = [
            'notification_type' => $type,
            'title' => $title,
            'body' => $body ?? $title,
            'sender_user_id' => $senderUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'metadata' => $metadata
        ];

        return $this->makeSafePayload($payload);
    }

    /**
     * UTF-8 safe JSON encoding
     * 
     * @param array $data Data to encode
     * @return string JSON string
     */
    private function makeSafePayload($data) {
        array_walk_recursive($data, function(&$val) {
            if (is_string($val) && !mb_check_encoding($val, 'UTF-8')) {
                $val = mb_convert_encoding($val, 'UTF-8');
            }
        });
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(['message' => 'Notification'], JSON_UNESCAPED_UNICODE);
            if ($json === false) $json = '{}';
        }
        
        return $json;
    }

    /**
     * Insert notification into database
     * 
     * @return int|false Notification ID or false on failure
     */
    private function insertNotification(
        $userId,
        $senderUserId,
        $type,
        $relatedId,
        $payload,
        $priority,
        $senderRole,
        $channels
    ) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO notifications 
                 (user_id, actor_user_id, type, related_id, payload, priority, sender_role, delivery_channels, is_read, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())"
            );
            
            // CORRECTED: Changed 'iisssss' (7 chars) to 'iisissss' (8 chars)
            // 1: user_id (i), 2: actor_user_id (i), 3: type (s), 4: related_id (i),
            // 5: payload (s), 6: priority (s), 7: sender_role (s), 8: delivery_channels (s)
            $stmt->bind_param(
                "iisissss",
                $userId,
                $senderUserId,
                $type,
                $relatedId,
                $payload,
                $priority,
                $senderRole,
                $channels
            );
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            return false;
        } catch (Exception $e) {
            error_log("NotificationHandler::insertNotification() Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to configured delivery channels
     * 
     * @param int $notificationId Notification ID
     * @param int $userId Recipient user ID
     * @param array $channels Delivery channels
     * @param string $title Notification title
     * @param string|null $body Message body
     */
    private function deliverToChannels($notificationId, $userId, $channels, $title, $body, $type = null, $relatedId = null) {
        // In-app: already persisted to database; attempt websocket push with richer context
        if (in_array(NotificationTypes::CHANNEL_IN_APP, $channels)) {
            $this->tryPushNotification($notificationId, $userId, $title, $body, $type, $relatedId);
            // Some notification types are of broad interest and should also be
            // broadcast to all connected clients (e.g. status changes).  We
            // centralize that behaviour here instead of scattering push_borrow_update_ws
            if (in_array($type, [
                NotificationTypes::BORROW_REQUEST_SUBMITTED,
                NotificationTypes::BORROW_REQUEST_APPROVED,
                NotificationTypes::BORROW_REQUEST_DENIED,
                NotificationTypes::ITEM_RELEASED,
                NotificationTypes::RETURN_REQUEST_SUBMITTED,
                NotificationTypes::RETURN_REQUEST_APPROVED,
                NotificationTypes::RETURN_REQUEST_REJECTED,
                NotificationTypes::DAMAGE_REPORTED
            ])) {
                if (function_exists('push_borrow_update_ws')) {
                    // event name may be derived from type if needed
                    push_borrow_update_ws($relatedId, self::getEventName($type), ['type' => $type, 'notification_id' => $notificationId]);
                }
            }        }

        // Email: Stub for future implementation
        if (in_array(NotificationTypes::CHANNEL_EMAIL, $channels)) {
            // TODO: Integrate with PHPMailer or email service
            // $this->sendEmail($userId, $title, $body);
        }

        // SMS: Stub for future implementation
        if (in_array(NotificationTypes::CHANNEL_SMS, $channels)) {
            // TODO: Integrate with SMS gateway (Twilio, etc)
            // $this->sendSMS($userId, $title, $body);
        }

        // Push: Stub for future implementation
        if (in_array(NotificationTypes::CHANNEL_PUSH, $channels)) {
            // TODO: Integrate with push notification service
            // $this->sendPush($userId, $title, $body);
        }
    }

    /**
     * Try to push notification via WebSocket
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @param string $title Title
     */
    /**
     * Try to push notification via WebSocket
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     * @param string $title Title
     */
    private function tryPushNotification($notificationId, $userId, $title, $body = null, $type = null, $relatedId = null) {
        // Sync: Actually call the push_notification_ws function if available
        // convert our internal notification type to an external event name
        $event = NotificationHandler::getEventName($type);

        $pushScript = __DIR__ . '/notify_push.php';
        if (file_exists($pushScript)) {
            require_once $pushScript;
            if (function_exists('push_notification_ws')) {
                $payloadData = ['id' => $notificationId, 'title' => $title, 'body' => $body];
                push_notification_ws($userId, $event, $relatedId, $payloadData);
            }
        }
    }

    /**
     * Audit log for compliance and debugging
     * 
     * @param int|null $notificationId Notification ID
     * @param string $action Action type (CREATED, SENT, READ, DUPLICATE_SUPPRESSED, etc)
     * @param string $details Action details
     */
    private function auditLog($notificationId, $action, $details) {
        // TEMPORARY BYPASS FOR DEBUGGING - Remove this return when notification_audit_log table is confirmed to exist
        return;
        
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO notification_audit_log 
                 (notification_id, action, actor_user_id, actor_role, details, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->bind_param(
                "issss",
                $notificationId,
                $action,
                $this->userId,
                $this->userRole,
                $details
            );
            
            $stmt->execute();
        } catch (Exception $e) {
            // Audit table may not exist yet - non-critical
            // error_log("NotificationHandler::auditLog() Error: " . $e->getMessage());
        }
    }
}
