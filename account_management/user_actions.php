<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../login/config.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
  $_SESSION['error'] = 'Unauthorized.';
  header('Location: users.php');
  exit;
}

$action = $_POST['action'] ?? '';
switch ($action) {
  case 'deactivate':
    $target = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
    if ($target <= 0) {
      $_SESSION['error'] = 'Invalid user.';
      break;
    }
    if ($target === (int)$_SESSION['id']) {
      $_SESSION['error'] = 'You cannot deactivate your own account.';
      break;
    }
    // check target role and ensure we don't remove the last ADMIN
    $rstmt = $conn->prepare("SELECT role, status FROM user WHERE id = ? LIMIT 1");
    $rstmt->bind_param('i', $target);
    $rstmt->execute();
    $rres = $rstmt->get_result()->fetch_assoc();
    $targetRole = $rres['role'] ?? '';
    $targetStatus = $rres['status'] ?? '';
    if ($targetRole === 'ADMIN') {
      $cstmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user WHERE role = 'ADMIN' AND status = 'ACTIVE'");
      $cstmt->execute();
      $cnt = (int) $cstmt->get_result()->fetch_assoc()['cnt'];
      if ($cnt <= 1) {
        $_SESSION['error'] = 'Cannot deactivate the last remaining ADMIN account.';
        break;
      }
    }
    $stmt = $conn->prepare("UPDATE user SET status = 'DEACTIVATED', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $target);
    if ($stmt->execute()) $_SESSION['success'] = 'Account deactivated.';
    else $_SESSION['error'] = 'Failed to deactivate.';
    break;

  case 'update_role':
    $target = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    if ($target <= 0 || !in_array($role, ['USER','ADMIN'])) {
      $_SESSION['error'] = 'Invalid parameters.';
      break;
    }
    // prevent removing own admin role
    if ($target === (int)$_SESSION['id'] && $role !== 'ADMIN') {
      $_SESSION['error'] = 'You cannot remove your own ADMIN role.';
      break;
    }
    // if changing someone from ADMIN -> USER, ensure at least one other active ADMIN remains
    $curStmt = $conn->prepare("SELECT role, status FROM user WHERE id = ? LIMIT 1");
    $curStmt->bind_param('i', $target);
    $curStmt->execute();
    $cur = $curStmt->get_result()->fetch_assoc();
    $curRole = $cur['role'] ?? '';
    if ($curRole === 'ADMIN' && $role !== 'ADMIN') {
      $cstmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user WHERE role = 'ADMIN' AND status = 'ACTIVE'");
      $cstmt->execute();
      $cnt = (int) $cstmt->get_result()->fetch_assoc()['cnt'];
      if ($cnt <= 1) {
        $_SESSION['error'] = 'Cannot remove ADMIN role: at least one ADMIN must exist.';
        break;
      }
    }
    $stmt = $conn->prepare("UPDATE user SET role = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $role, $target);
    if ($stmt->execute()) $_SESSION['success'] = 'Role updated.';
    else $_SESSION['error'] = 'Failed to update role.';
    break;

  case 'add':
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'USER';

    if ($username === '' || strlen($password) < 8) {
      $_SESSION['error'] = 'Username and password (min 8 chars) required.';
      break;
    }
    if (!in_array($role, ['USER','ADMIN'])) $role = 'USER';

    // check username unique
    $chk = $conn->prepare("SELECT id FROM user WHERE username = ? LIMIT 1");
    $chk->bind_param('s', $username);
    $chk->execute();
    $r = $chk->get_result();
    if ($r->fetch_assoc()) {
      $_SESSION['error'] = 'Username already taken.';
      break;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO user (username, password_hash, first_name, last_name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())");
    $ins->bind_param('ssssss', $username, $hash, $first, $last, $email, $role);
    if ($ins->execute()) $_SESSION['success'] = 'User created.';
    else $_SESSION['error'] = 'Failed to create user.';
    break;

  case 'change_password':
    $target = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($target <= 0) {
      $_SESSION['error'] = 'Invalid user.';
      break;
    }
    if (strlen($new) < 8) {
      $_SESSION['error'] = 'Password must be at least 8 characters.';
      break;
    }
    if ($new !== $confirm) {
      $_SESSION['error'] = 'Password confirmation does not match.';
      break;
    }
    // ensure target exists
    $tstmt = $conn->prepare("SELECT id FROM user WHERE id = ? LIMIT 1");
    $tstmt->bind_param('i', $target);
    $tstmt->execute();
    $tres = $tstmt->get_result();
    if (!$tres->fetch_assoc()) {
      $_SESSION['error'] = 'User not found.';
      break;
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $ustmt = $conn->prepare("UPDATE user SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $ustmt->bind_param('si', $hash, $target);
    if ($ustmt->execute()) {
      $_SESSION['success'] = 'Password changed.';
    } else {
      $_SESSION['error'] = 'Failed to change password.';
    }
    break;

  default:
    $_SESSION['error'] = 'Unknown action.';
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'users.php';
header('Location: ' . $redirect);
exit;
