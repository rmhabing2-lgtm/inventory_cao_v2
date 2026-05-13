<?php
// Assumes:
// - session already started in account_settings.php
// - DB connection ($conn) exists
// - $user_id is defined

/* ================== LOAD PROFILE DATA ================== */
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, department, position, username, email, role, status
    FROM user
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

/* ================== HANDLE UPDATE ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {

  $first = trim($_POST['first_name']);
  $last  = trim($_POST['last_name']);
  $dept  = trim($_POST['department']);
  $pos   = trim($_POST['position']);
  $email = trim($_POST['email']);

  $stmt2 = $conn->prepare("
        UPDATE user
        SET first_name=?, last_name=?, department=?, position=?, email=?, updated_at=NOW()
        WHERE id=?
    ");
  $stmt2->bind_param("sssssi", $first, $last, $dept, $pos, $email, $user_id);

  if ($stmt2->execute()) {
    $success = "Account successfully updated.";
  } else {
    $error = "Failed to update account.";
  }
}

/* ================== HANDLE PASSWORD CHANGE ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $current = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  // fetch current password hash
  $stmtp = $conn->prepare("SELECT password_hash FROM user WHERE id = ?");
  $stmtp->bind_param("i", $user_id);
  $stmtp->execute();
  $res = $stmtp->get_result();
  $row = $res->fetch_assoc();
  $stored = $row['password_hash'] ?? '';

  if (empty($stored) || !password_verify($current, $stored)) {
    $error = "Current password is incorrect.";
  } elseif (strlen($new) < 8) {
    $error = "New password must be at least 8 characters long.";
  } elseif ($new !== $confirm) {
    $error = "New password and confirmation do not match.";
  } else {
    $newhash = password_hash($new, PASSWORD_DEFAULT);
    $stmtu = $conn->prepare("UPDATE user SET password_hash = ? WHERE id = ?");
    $stmtu->bind_param("si", $newhash, $user_id);
    if ($stmtu->execute()) {
      $success = "Password changed successfully.";
    } else {
      $error = "Failed to update password.";
    }
  }
}

/* ================== HANDLE AVATAR UPLOAD ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  /* UPLOAD PHOTO */
  if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    $file = $_FILES['avatar'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
      $error = "Invalid image format.";
    } elseif ($file['size'] > $maxSize) {
      $error = "Image size must be under 2MB.";
    } else {

      $newName = "avatar_" . $user_id . "_" . time() . "." . $ext;
      $path = "../uploads/avatars/" . $newName;

      if (move_uploaded_file($file['tmp_name'], $path)) {

        // Delete old avatar
        if (!empty($user['avatar']) && $user['avatar'] !== 'default.png') {
          @unlink("../uploads/avatars/" . $user['avatar']);
        }

        $stmt = $conn->prepare("UPDATE user SET avatar=? WHERE id=?");
        $stmt->bind_param("si", $newName, $user_id);

        if ($stmt->execute()) {
          $_SESSION['avatar'] = $newName; 
          $user['avatar'] = $newName;
          $success = "Profile photo updated.";
        }
      }
    }
  }

  /* RESET PHOTO */
  if (isset($_POST['reset_avatar'])) {

    if (!empty($user['avatar']) && $user['avatar'] !== 'default.png') {
      @unlink("../uploads/avatars/" . $user['avatar']);
    }

    $stmt = $conn->prepare("UPDATE user SET avatar='default.png' WHERE id=?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
      $_SESSION['avatar'] = 'default.png'; 
      $user['avatar'] = 'default.png';
      $success = "Profile photo reset.";
    }
  }
}
?>

<div class="row">
    <div class="col-md-12">
        <h4 class="fw-bold py-3 mb-4">
            <span class="text-muted fw-light">Account Settings /</span> Account
        </h4>

        <ul class="nav nav-pills flex-column flex-md-row mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="javascript:void(0);">
                    <i class="bx bx-user me-1"></i> Account
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="edit_employee.php">
                    <i class="bx bx-bell me-1"></i> Edit Employee Details
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages-account-settings-connections.html">
                    <i class="bx bx-link-alt me-1"></i> Connections
                </a>
            </li>
        </ul>

        <?php if (isset($user['role']) && $user['role'] === 'ADMIN'): ?>
        <div style="margin-top:8px; margin-bottom: 16px;">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">Add
                User</button>
            <button class="btn btn-sm btn-outline-secondary" style="margin-left:8px" data-bs-toggle="modal"
                data-bs-target="#manageUsersModal">Manage Users</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($user['role']) && $user['role'] === 'ADMIN') {
  include __DIR__ . '/add_user_modal.php';
  include __DIR__ . '/manage_users_modal.php';
} ?>

<?php if (!empty($success)):
  $__acct_suc = htmlspecialchars($success);
  unset($success);
?>
<div id="acctSuccessToast" class="bs-toast toast fade bg-success" role="alert" aria-live="assertive" aria-atomic="true"
    data-bs-delay="3000" style="position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:1080;">
    <div class="toast-header bg-transparent">
        <i class="bx bx-bell me-2"></i>
        <div class="me-auto fw-semibold">Success</div>
        <small class="text-muted">now</small>
        <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast"
            aria-label="Close"></button>
    </div>
    <div class="toast-body"><?= $__acct_suc ?></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var e = document.getElementById('acctSuccessToast');
    if (e && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        new bootstrap.Toast(e, {
            delay: 3000
        }).show();
    } else if (e) {
        e.classList.add('show');
        setTimeout(function() {
            e.classList.remove('show');
        }, 3000);
    }
});
</script>
<?php endif; ?>

<?php if (!empty($error)):
  $__acct_err = htmlspecialchars($error);
  unset($error);
?>
<div id="acctErrorToast" class="bs-toast toast fade bg-danger" role="alert" aria-live="assertive" aria-atomic="true"
    data-bs-delay="5000" style="position:fixed;top:4rem;left:50%;transform:translateX(-50%);z-index:1080;">
    <div class="toast-header bg-transparent">
        <i class="bx bx-error me-2"></i>
        <div class="me-auto fw-semibold">Error</div>
        <small class="text-muted">now</small>
        <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast"
            aria-label="Close"></button>
    </div>
    <div class="toast-body"><?= $__acct_err ?></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var e = document.getElementById('acctErrorToast');
    if (e && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        new bootstrap.Toast(e, {
            delay: 5000
        }).show();
    } else if (e) {
        e.classList.add('show');
        setTimeout(function() {
            e.classList.remove('show');
        }, 5000);
    }
});
</script>
<?php endif; ?>

<script>
document.getElementById('avatarInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('avatarPreview').src = URL.createObjectURL(file);
    }
});
</script>

<div class="card mb-4">
    <h5 class="card-header">Profile Details</h5>
    <div class="card-body">
        <div class="d-flex align-items-start align-items-sm-center gap-4">
            <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" class="rounded"
                width="100" height="100" style="object-fit:cover" id="avatarPreview">

            <div>
                <form method="post" enctype="multipart/form-data" class="d-inline">
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" hidden
                        onchange="this.form.submit()">
                    <button type="button" class="btn btn-primary me-2"
                        onclick="document.getElementById('avatarInput').click()">
                        Upload new photo
                    </button>
                    <input type="hidden" name="upload_avatar">
                </form>

                <form method="post" class="d-inline">
                    <button type="submit" name="reset_avatar" class="btn btn-outline-secondary">
                        Reset
                    </button>
                </form>

                <p class="text-muted mb-0 mt-2">Allowed JPG, PNG, GIF. Max size 2MB</p>
            </div>
        </div>
    </div>

    <hr class="my-0">

    <div class="card-body">
        <form method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name</label>
                    <input name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Department</label>
                    <input name="department" class="form-control" value="<?= htmlspecialchars($user['department']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Position</label>
                    <input name="position" class="form-control" value="<?= htmlspecialchars($user['position']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" disabled>
                        <option value="ADMIN" <?= $user['role'] === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
                        <option value="MANAGER" <?= $user['role'] === 'MANAGER' ? 'selected' : '' ?>>Manager</option>
                        <option value="STAFF" <?= $user['role'] === 'STAFF' ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" disabled>
                        <option value="ACTIVE" <?= $user['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                        <option value="INACTIVE" <?= $user['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive
                        </option>
                        <option value="SUSPENDED" <?= $user['status'] === 'SUSPENDED' ? 'selected' : '' ?>>Suspended
                        </option>
                    </select>
                </div>

            </div>

            <button class="btn btn-primary" name="update_account">Save changes</button>
            <button type="reset" class="btn btn-outline-secondary">Cancel</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Change Password</h5>
    <div class="card-body">
        <form method="post">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Current Password</label>
                    <input name="current_password" type="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">New Password</label>
                    <input name="new_password" type="password" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input name="confirm_password" type="password" class="form-control" minlength="8" required>
                </div>
            </div>
            <button class="btn btn-primary" name="change_password">Change Password</button>
        </form>
    </div>
</div>

<?php if (isset($user['role']) && $user['role'] === 'ADMIN'): ?>
<div class="card">
    <h5 class="card-header">Delete Account</h5>
    <div class="card-body">
        <div class="alert alert-warning">
            Once you delete your account, there is no going back.
        </div>
        <form method="post" action="deactivate_account.php">
            <input type="hidden" name="target_id" value="<?= htmlspecialchars($user_id) ?>">
            <button class="btn btn-danger"
                onclick="return confirm('Are you sure you want to deactivate this account?')">Deactivate
                Account</button>
        </form>
    </div>
</div>
<?php endif; ?>