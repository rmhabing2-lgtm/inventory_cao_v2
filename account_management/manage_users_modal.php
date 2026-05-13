<?php
// Manage Users modal partial — expects $conn to be available and session started
// Fetch users
$q = $conn->prepare("SELECT id, username, first_name, last_name, email, role, status FROM user ORDER BY id DESC");
$q->execute();
$res = $q->get_result();
$me = (int) ($_SESSION['id'] ?? 0);
?>

<div class="modal fade" id="manageUsersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Manage Users</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 d-flex justify-content-between">
          <div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
          </div>
          <div>
            <small class="text-muted">Total: <?= $res->num_rows ?></small>
          </div>
        </div>

        <div class="mb-3">
          <input id="manageUserSearch" type="search" class="form-control" placeholder="Search users by username, name, email or role" />
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
                      <option value="ADMIN" <?= $row['role'] == 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                      <option value="MANAGER" <?= $row['role'] == 'MANAGER' ? 'selected' : '' ?>>MANAGER</option>
                      <option value="STAFF" <?= $row['role'] == 'STAFF' ? 'selected' : '' ?>>STAFF</option>
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
                    <button class="btn btn-sm btn-secondary change-pass-btn" data-id="<?= (int)$row['id'] ?>" data-username="<?= htmlspecialchars($row['username']) ?>" data-bs-toggle="modal" data-bs-target="#changePasswordModal" style="margin-left:6px">Change Password</button>
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
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php // include add user modal so it can be opened from here ?>
<?php include __DIR__ . '/add_user_modal.php'; ?>
<?php include __DIR__ . '/change_password_modal.php'; ?>

<script>
// Client-side search/filter for Manage Users modal
(function(){
  var input = document.getElementById('manageUserSearch');
  if (!input) return;
  input.addEventListener('input', function(){
    var q = (this.value || '').trim().toLowerCase();
    var rows = document.querySelectorAll('#manageUsersModal table tbody tr');
    rows.forEach(function(row){
      var text = (row.textContent || '').toLowerCase();
      row.style.display = q === '' || text.indexOf(q) !== -1 ? '' : 'none';
    });
  });
})();
</script>