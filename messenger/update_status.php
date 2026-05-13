<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/db_connect.php';

$me = $_SESSION['id'];
$status = isset($_REQUEST['status']) && in_array($_REQUEST['status'], ['online','offline']) ? $_REQUEST['status'] : 'online';

$stmt = $conn->prepare("UPDATE messenger_users SET status = ?, last_active = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('si', $status, $me);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => (bool)$ok]);
} else {
    echo json_encode(['success' => false]);
}

?>
