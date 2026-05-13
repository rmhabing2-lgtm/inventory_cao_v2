<?php
// Simple DB wrapper for messenger endpoints - reuses project's config
$root = realpath(__DIR__ . '/..');
if (file_exists($root . '/login/config.php')) {
    require_once $root . '/login/config.php'; // provides $conn
} else {
    // fallback (local defaults)
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "inventory_cao";
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'DB connection failed']);
        exit;
    }
}

// Ensure utf8
$conn->set_charset('utf8mb4');

?>
