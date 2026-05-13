<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/db_connect.php';

$me = $_SESSION['id'];
$rows = [];

// Query with unread message count per contact
$sql = "SELECT u.id, u.username, u.status, u.last_active,
        (SELECT COUNT(*) FROM messenger_messages m 
         WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count
        FROM messenger_users u 
        WHERE u.id != ? 
        ORDER BY u.username ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ii', $me, $me);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
}

echo json_encode($rows);

?>
