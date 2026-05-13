<?php
require_once __DIR__ . '/config.php';
// insert test row
$payload = ['message' => 'db smoke test', 'page' => '/table/notifications.php'];
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$stmt = $conn->prepare("INSERT INTO notification_logs (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$user_id = null; $ip = '127.0.0.1'; $ua = 'cli-test'; $page = $payload['page']; $filename = 'notifications.php'; $lineno = 1; $colno = 1; $message = $payload['message']; $stack = null;
$stmt->bind_param('issssiisss', $user_id, $ip, $ua, $page, $filename, $lineno, $colno, $message, $stack, $payloadJson);
$res = $stmt->execute();
if (!$res) { echo "Insert failed: " . $stmt->error . PHP_EOL; exit(1); }
$lastId = $conn->insert_id;
$stmt->close();
$sel = $conn->prepare('SELECT id, created_at, user_id, ip, user_agent, page, filename, lineno, colno, message, payload FROM notification_logs WHERE id = ?');
$sel->bind_param('i', $lastId);
$sel->execute();
$r = $sel->get_result()->fetch_assoc();
print_r($r);
$sel->close();
echo "OK\n";
