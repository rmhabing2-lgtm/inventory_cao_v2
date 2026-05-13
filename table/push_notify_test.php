<?php
require_once __DIR__ . '/../includes/notify_push.php';
// call push_notification_ws to test DB logging (WS may not be reachable, but DB insert should run)
$res = push_notification_ws(1, 'test_event', 123, ['message' => 'push notify test', 'extra' => 'cli']);
echo "push res: ".(is_string($res)?$res:'(non-string)') . PHP_EOL;
// show last log inserted
require_once __DIR__ . '/config.php';
$r = $conn->query('SELECT id, created_at, user_id, filename, message, payload FROM notification_logs ORDER BY id DESC LIMIT 1')->fetch_assoc();
print_r($r);
