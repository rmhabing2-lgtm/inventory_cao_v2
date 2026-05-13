<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/db_connect.php';

$me = $_SESSION['id'];
$friend = isset($_GET['friend_id']) ? intval($_GET['friend_id']) : 0;

if ($friend <= 0) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

// 1. Fetch Messages using bind_result for maximum compatibility
$sql = "SELECT id, sender_id, receiver_id, message_text, timestamp, is_read 
        FROM messenger_messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY timestamp ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // This helps debug if the table is missing
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('iiii', $me, $friend, $friend, $me);
$stmt->execute();
$stmt->bind_result($id, $s_id, $r_id, $text, $time, $read);

$messages = [];
while ($stmt->fetch()) {
    $messages[] = [
        'id' => $id,
        'sender_id' => $s_id,
        'receiver_id' => $r_id,
        'message_text' => $text,
        'timestamp' => $time,
        'is_read' => $read
    ];
}
$stmt->close();

// 2. Mark messages as read
$upd = $conn->prepare("UPDATE messenger_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
if ($upd) { 
    $upd->bind_param('ii', $me, $friend); 
    $upd->execute(); 
    $upd->close(); 
}

// 3. Fetch friend details and typing status
$typing = 0; $status = 'offline'; $last_active = '';
$t = $conn->prepare("SELECT is_typing, status, last_active FROM messenger_users WHERE id = ? LIMIT 1");
if ($t) { 
    $t->bind_param('i', $friend); 
    $t->execute(); 
    $t->bind_result($is_typing, $u_status, $u_active); 
    if ($t->fetch()) { 
        $typing = (int)$is_typing;
        $status = $u_status;
        $last_active = $u_active;
    } 
    $t->close(); 
}

echo json_encode([
    'success' => true, 
    'messages' => $messages, 
    'friend_typing' => $typing,
    'friend_status' => $status
]);
?>

