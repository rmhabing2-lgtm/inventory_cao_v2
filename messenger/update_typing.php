<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/db_connect.php';

$me = $_SESSION['id'];
$status = isset($_REQUEST['status']) ? intval($_REQUEST['status']) : 0;

$stmt = $conn->prepare("UPDATE messenger_users SET is_typing = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('ii', $status, $me);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => (bool)$ok]);
} else {
    echo json_encode(['success' => false]);
}

?>
