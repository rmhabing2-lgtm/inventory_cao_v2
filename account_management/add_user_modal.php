<!-- Add User Modal (reusable partial) -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="user_actions.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">First name</label>
            <input name="first_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Last name</label>
            <input name="last_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="USER">USER</option>
              <option value="ADMIN">ADMIN</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>
