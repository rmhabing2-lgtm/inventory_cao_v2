<?php
/**
 * components/notification_logs.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Receives POST payloads from the front-end sendErrorReport() / notification
 * action functions and writes them to the notification_logs table.
 *
 * Accepted POST fields (all optional except at least one of message/event):
 *   message        – JS error message string
 *   stack          – JS error stack trace
 *   filename       – source filename (window.onerror)
 *   lineno         – line number (window.onerror)
 *   colno          – column number (window.onerror)
 *   page           – page identifier / URL (sent by client)
 *   payload        – arbitrary JSON string
 *   notification_id– related notification row id
 *   event          – event name (e.g. "borrow_approved")
 *   action         – NONE | APPROVED | DENIED | MARK_READ
 *
 * Always returns JSON: {"ok": true} or {"ok": false, "error": "..."}.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/* ── Only accept POST ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Bootstrap DB connection ────────────────────────────────────────────── */
$config_path = __DIR__ . '/../login/config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Config not found']);
    exit;
}
require_once $config_path;   // provides $conn

/* ── Collect fields ─────────────────────────────────────────────────────── */
$user_id         = isset($_SESSION['id'])   ? (int)$_SESSION['id']   : null;
$ip              = $_SERVER['REMOTE_ADDR']  ?? null;
$user_agent      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$page            = substr(trim($_POST['page']    ?? ''), 0, 2083) ?: null;
$filename        = substr(trim($_POST['filename'] ?? ''), 0, 255) ?: null;
$lineno          = isset($_POST['lineno'])  && is_numeric($_POST['lineno'])  ? (int)$_POST['lineno']  : null;
$colno           = isset($_POST['colno'])   && is_numeric($_POST['colno'])   ? (int)$_POST['colno']   : null;
$message         = trim($_POST['message']   ?? '') ?: null;
$stack           = trim($_POST['stack']     ?? '') ?: null;
$notification_id = isset($_POST['notification_id']) && is_numeric($_POST['notification_id'])
                    ? (int)$_POST['notification_id'] : null;
$event           = substr(trim($_POST['event']   ?? ''), 0, 50) ?: null;

/* ── Validate / sanitise action ─────────────────────────────────────────── */
$allowed_actions = ['NONE', 'APPROVED', 'DENIED', 'MARK_READ'];
$action_raw      = strtoupper(trim($_POST['action'] ?? 'NONE'));
$action          = in_array($action_raw, $allowed_actions, true) ? $action_raw : 'NONE';

/* ── Validate payload JSON (must be valid JSON if supplied) ─────────────── */
$payload_raw = trim($_POST['payload'] ?? '');
$payload     = null;
if ($payload_raw !== '') {
    $decoded = json_decode($payload_raw);
    $payload = ($decoded !== null) ? $payload_raw : null;
}

/* ── Nothing meaningful to store? ──────────────────────────────────────── */
if ($message === null && $event === null && $payload === null) {
    echo json_encode(['ok' => true, 'note' => 'nothing to log']);
    exit;
}

/* ── Insert ─────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare("
    INSERT INTO notification_logs
        (user_id, ip, user_agent, page, filename, lineno, colno,
         message, stack, payload, notification_id, event, action)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'issssiisssiis',
    $user_id,         // i  (nullable int)
    $ip,              // s
    $user_agent,      // s
    $page,            // s
    $filename,        // s
    $lineno,          // i  (nullable int)
    $colno,           // i  (nullable int)
    $message,         // s
    $stack,           // s
    $payload,         // s
    $notification_id, // i  (nullable int)
    $event,           // s
    $action           // s  (enum)
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB execute failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$stmt->close();
echo json_encode(['ok' => true]);