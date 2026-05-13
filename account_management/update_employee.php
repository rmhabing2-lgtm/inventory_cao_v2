<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/../login/config.php';

// 1. Security Check: Only Admins can access this script
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    $_SESSION['error'] = 'Unauthorized access.';
    header('Location: edit_employee.php');
    exit;
}

// 2. Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: edit_employee.php');
    exit;
}

// 3. ID Validation
$target = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
if ($target <= 0) {
    $_SESSION['error'] = 'Invalid target user.';
    header('Location: edit_employee.php');
    exit;
}

// 4. Input Sanitization
$first      = trim($_POST['first_name'] ?? '');
$last       = trim($_POST['last_name'] ?? '');
$username   = trim($_POST['username'] ?? '');
$email      = trim($_POST['email'] ?? '');
$department = trim($_POST['department'] ?? '');
$position   = trim($_POST['position'] ?? '');

// Synced with inventory_cao.sql ENUMs
$allowed_roles    = ['ADMIN', 'MANAGER', 'STAFF'];
$allowed_statuses = ['ACTIVE', 'INACTIVE', 'SUSPENDED'];

$role   = in_array($_POST['role'] ?? '', $allowed_roles) ? $_POST['role'] : 'STAFF';
$status = in_array($_POST['status'] ?? '', $allowed_statuses) ? $_POST['status'] : 'ACTIVE';

// 5. Basic Validation
if ($username === '' || $first === '' || $last === '') {
    $_SESSION['error'] = 'Username, first name, and last name are required.';
    header('Location: edit_employee.php');
    exit;
}

// 6. Self-Protection: Prevent changing own role or deactivating own account
if ($target === (int)$_SESSION['id']) {
    if ($role !== 'ADMIN') {
        $_SESSION['error'] = 'You cannot remove your own ADMIN role.';
        header('Location: edit_employee.php');
        exit;
    }
    if ($status !== 'ACTIVE') {
        $_SESSION['error'] = 'You cannot deactivate your own account.';
        header('Location: edit_employee.php');
        exit;
    }
}

// 7. Username Uniqueness Check (Exclude the user being edited)
$chk = $conn->prepare('SELECT id FROM user WHERE username = ? AND id <> ? LIMIT 1');
$chk->bind_param('si', $username, $target);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    $_SESSION['error'] = 'The username "' . htmlspecialchars($username) . '" is already taken.';
    header('Location: edit_employee.php');
    exit;
}

// 8. Execute Database Update
$stmt = $conn->prepare('
    UPDATE user 
    SET username = ?, 
        first_name = ?, 
        last_name = ?, 
        email = ?, 
        department = ?, 
        position = ?, 
        role = ?, 
        status = ?, 
        updated_at = NOW() 
    WHERE id = ?
');

$stmt->bind_param('ssssssssi', $username, $first, $last, $email, $department, $position, $role, $status, $target);

if ($stmt->execute()) {
    $_SESSION['success'] = 'User details for "' . htmlspecialchars($username) . '" updated successfully.';
} else {
    $_SESSION['error'] = 'Database error: Failed to update user.';
}

header('Location: edit_employee.php');
exit;