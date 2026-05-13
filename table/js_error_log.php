<?php
require_once __DIR__ . '/../includes/session.php';
require_login();

// Simple endpoint to log client-side JS errors for troubleshooting
header('Content-Type: application/json');

$payload = [];
$raw = file_get_contents('php://input');
if ($raw) {
    $data = json_decode($raw, true);
    if (is_array($data)) $payload = $data;
}
// fallback to POST form
if (empty($payload)) {
    $payload = $_POST;
}

$entry = [
    'time' => date('Y-m-d H:i:s'),
    'user_id' => $_SESSION['id'] ?? 0,
    'url' => $_SERVER['HTTP_REFERER'] ?? '',
    'payload' => $payload
];

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/client_js_errors.log';
file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true]);
exit;
