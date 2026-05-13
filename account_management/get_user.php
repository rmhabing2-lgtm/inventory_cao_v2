<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/../login/config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

$stmt = $conn->prepare('SELECT id, username, first_name, last_name, email, department, position, role, status FROM user WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode(['user' => $user]);
exit;

?>
