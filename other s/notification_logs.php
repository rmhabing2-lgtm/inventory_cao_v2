<?php
// Simple logging endpoint for client-side notification errors
require_once __DIR__ . '/../includes/session.php';
// include DB config if available
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// support CLI test mode: pass JSON as first arg
if (PHP_SAPI === 'cli' && !empty($argv[1])) {
    $raw = implode(' ', array_slice($argv, 1));
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'cli';
} else {
    // allow both JSON and form POSTs
    $raw = file_get_contents('php://input');
}

$data = [];
if ($raw) {
    $parsed = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
        $data = $parsed;
    }
}
// fallback to POST form fields
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}
// minimal structure
$entry = [
    'ts' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    'user_id' => $_SESSION['id'] ?? null,
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'data' => $data,
];
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/notification_logs.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($line) {
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
// also insert into DB table `notification_logs` if $conn is available
try {
    if (!empty($conn) && ($stmt = $conn->prepare("INSERT INTO notification_logs (user_id, ip, user_agent, page, filename, lineno, colno, message, stack, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"))) {
        $user_id = $_SESSION['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $page = isset($data['page']) ? substr($data['page'],0,2083) : (isset($data['page']) ? $data['page'] : null);
        $filename = isset($data['filename']) ? substr($data['filename'],0,255) : null;
        $lineno = isset($data['lineno']) ? intval($data['lineno']) : null;
        $colno = isset($data['colno']) ? intval($data['colno']) : null;
        $message = isset($data['message']) ? $data['message'] : (isset($data['msg']) ? $data['msg'] : null);
        $stack = isset($data['stack']) ? $data['stack'] : null;
        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // types: i - int, s - string. Use s for JSON payload.
        $stmt->bind_param('issssiiiss', $user_id, $ip, $ua, $page, $filename, $lineno, $colno, $message, $stack, $payloadJson);
        // mysqli bind_param requires types matching variables; adjust nulls
        $stmt->execute();
        $stmt->close();
    }
} catch (Exception $e) {
    // ignore DB errors for now
}
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
