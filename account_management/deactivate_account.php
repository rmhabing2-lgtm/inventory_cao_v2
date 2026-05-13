<?php
require_once __DIR__ . '/../includes/session.php';
require_login();

// require DB connection (same include pattern used by account_settings.php)
require_once __DIR__ . '/../login/config.php';

// ensure user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
  $_SESSION['error'] = 'Not authenticated.';
  header('Location: account_settings.php');
  exit;
}

// only admin may deactivate accounts
if ($_SESSION['role'] !== 'ADMIN') {
  $_SESSION['error'] = 'Unauthorized: only admins can deactivate accounts.';
  header('Location: account_settings.php');
  exit;
}

$target = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
if ($target <= 0) {
  $_SESSION['error'] = 'Invalid account specified.';
  header('Location: account_settings.php');
  exit;
}
// prevent self-deactivation
if ($target === (int)($_SESSION['id'] ?? 0)) {
  $_SESSION['error'] = 'You cannot deactivate your own account.';
  header('Location: account_settings.php');
  exit;
}

// prevent deactivating last ADMIN
$rstmt = $conn->prepare("SELECT role FROM user WHERE id = ? LIMIT 1");
$rstmt->bind_param('i', $target);
$rstmt->execute();
$r = $rstmt->get_result()->fetch_assoc();
if (($r['role'] ?? '') === 'ADMIN') {
  $cstmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user WHERE role = 'ADMIN' AND status = 'ACTIVE'");
  $cstmt->execute();
  $cnt = (int) $cstmt->get_result()->fetch_assoc()['cnt'];
  if ($cnt <= 1) {
    $_SESSION['error'] = 'Cannot deactivate the last remaining ADMIN account.';
    header('Location: account_settings.php');
    exit;
  }
}

// perform safe update: set status to DEACTIVATED
$stmt = $conn->prepare("UPDATE user SET status = 'DEACTIVATED', updated_at = NOW() WHERE id = ?");
if (!$stmt) {
  $_SESSION['error'] = 'Database error (prepare failed).';
  header('Location: account_settings.php');
  exit;
}
$stmt->bind_param('i', $target);
if ($stmt->execute()) {
  $_SESSION['success'] = 'Account deactivated.';
} else {
  $_SESSION['error'] = 'Failed to deactivate account.';
}

header('Location: account_settings.php');
exit;
