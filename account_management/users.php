<?php
// session_start();
require_once __DIR__ . '/../login/config.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
  header('Location: account_settings.php');
  exit;
}

$me = (int) $_SESSION['id'];

$q = $conn->prepare("SELECT id, username, first_name, last_name, email, role, status FROM user ORDER BY id DESC");
$q->execute();
$res = $q->get_result();

// Use app layout similar to account_settings.php
?>
<?php include 'account_settings.php'; ?>

<?php ob_start(); ?>
  <div class="container-xxl grow container-p-y">
    <h4 class="fw-bold py-3 mb-4">User Management</h4>

    <?php if (!empty($_SESSION['success'])):
      $__users_suc = htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
      <div id="usersSuccessToast" class="bs-toast toast fade bg-success" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" style="position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:1080;">
        <div class="toast-header bg-transparent">
          <i class="bx bx-bell me-2"></i>
          <div class="me-auto fw-semibold">Success</div>
          <small class="text-muted">now</small>
          <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"><?= $__users_suc ?></div>
      </div>
      <script>document.addEventListener('DOMContentLoaded',function(){var e=document.getElementById('usersSuccessToast');if(e&&typeof bootstrap!=='undefined'&&bootstrap.Toast){new bootstrap.Toast(e,{delay:3000}).show();}else if(e){e.classList.add('show');setTimeout(function(){e.classList.remove('show');},3000);}});</script>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])):
      $__users_err = htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
      <div id="usersErrorToast" class="bs-toast toast fade bg-danger" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000" style="position:fixed;top:4rem;left:50%;transform:translateX(-50%);z-index:1080;">
        <div class="toast-header bg-transparent">
          <i class="bx bx-error me-2"></i>
          <div class="me-auto fw-semibold">Error</div>
          <small class="text-muted">now</small>
          <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"><?= $__users_err ?></div>
      </div>
      <script>document.addEventListener('DOMContentLoaded',function(){var e=document.getElementById('usersErrorToast');if(e&&typeof bootstrap!=='undefined'&&bootstrap.Toast){new bootstrap.Toast(e,{delay:5000}).show();}else if(e){e.classList.add('show');setTimeout(function(){e.classList.remove('show');},5000);}});</script>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="mb-3 d-flex justify-content-between">
          <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
          </div>
          <div>
            <a href="account_settings.php" class="btn btn-outline-secondary">Back</a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = $res->fetch_assoc()): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td>
                  <form method="post" action="user_actions.php" class="d-inline">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="target_id" value="<?= (int)$row['id'] ?>">
                    <select name="role" class="form-select form-select-sm d-inline" style="width:auto;display:inline-block">
                      <option value="USER" <?= $row['role']=='USER' ? 'selected' : '' ?>>USER</option>
                      <option value="ADMIN" <?= $row['role']=='ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Set</button>
                  </form>
                </td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td>
                  <?php if ((int)$row['id'] !== $me): ?>
                    <form method="post" action="user_actions.php" style="display:inline" onsubmit="return confirm('Deactivate this account?')">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="target_id" value="<?= (int)$row['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted">Your account</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php include __DIR__ . '/add_user_modal.php'; ?>
  </div>

<?php
$content = ob_get_clean();
// Render inside the main layout: include header and then echo $content where account_settings_content.php would appear.
// account_settings.php already includes layout and calls account_settings_content.php; to reuse the same layout we will output the content directly.
echo $content;
?>
