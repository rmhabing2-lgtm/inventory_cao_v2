<?php
// Lightweight helper to POST notification events to a local socket server
// Usage: require_once __DIR__ . '/notify_push.php'; push_notification_ws($user_id, $type, $related_id, $payloadArray);

function push_notification_ws($user_id, $type, $related_id, $payload)
{
    // Only run if configured; default to localhost:3000
    $host = getenv('NOTIF_WS_HOST') ?: 'http://localhost:3000';
    $url = rtrim($host, '/') . '/emit';

    $data = [
        'user_id' => $user_id,
        'event' => 'notification',
        'payload' => [
            'type' => $type,
            'related_id' => $related_id,
            'payload' => $payload,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $res = curl_exec($ch);
    // curl_close($ch);
    // Also write a server-side notification log (best-effort)
    try {
        $dbConf = __DIR__ . '/../table/config.php';
        if (file_exists($dbConf)) {
            // include quietly; may define $conn
            @include_once $dbConf;
            if (!empty($conn)) {
                $payloadJson = json_encode($data['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $msg = '';
                if (is_array($data['payload']) && isset($data['payload']['payload']) && is_array($data['payload']['payload']) && isset($data['payload']['payload']['message'])) {
                    $msg = $data['payload']['payload']['message'];
                } elseif (is_array($data['payload']) && isset($data['payload']['message'])) {
                    $msg = $data['payload']['message'];
                }
                $stmt = $conn->prepare("INSERT INTO notification_logs (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $uid = $user_id;
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    $page = null; $filename = $type; $lineno = null; $colno = null; $stack = null;
                    $stmt->bind_param('issssiisss', $uid, $ip, $ua, $page, $filename, $lineno, $colno, $msg, $stack, $payloadJson);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Exception $e) {}
    return $res;
}

// Broadcast a borrow/return update to all connected clients (best-effort)
function push_borrow_update_ws($borrow_id, $status, $payload = [])
{
    $host = getenv('NOTIF_WS_HOST') ?: 'http://localhost:3000';
    $url = rtrim($host, '/') . '/emit';

    $data = [
        'user_id' => 0, // 0 indicates broadcast to server-side implementation
        'event' => 'borrow_update',
        'payload' => [
            'type' => 'borrow_update',
            'related_id' => $borrow_id,
            'status' => $status,
            'payload' => $payload,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $res = curl_exec($ch);
    // curl_close($ch);
    return $res;
}
