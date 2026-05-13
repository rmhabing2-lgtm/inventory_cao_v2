<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/db_connect.php';

$sender_id = $_SESSION['id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

if ($receiver_id <= 0 || $message_text === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$sql = "INSERT INTO messenger_messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed']);
    exit;
}
$stmt->bind_param('iis', $sender_id, $receiver_id, $message_text);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Execute failed']);
}
$stmt->close();

// Mark both users as active
$u = $conn->prepare("UPDATE messenger_users SET status='online', last_active = NOW() WHERE id = ?");
if ($u) { $u->bind_param('i', $sender_id); $u->execute(); $u->close(); }

?>
