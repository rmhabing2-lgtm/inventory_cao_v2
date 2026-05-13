<?php
// Single modal used to change a user's password. Requires session and $conn available.
?>

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password for <span id="cp-username"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="user_actions.php">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="target_id" id="cp-target-id" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">New password</label>
            <input name="new_password" id="cp-new" type="password" class="form-control" required minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm new password</label>
            <input name="confirm_password" id="cp-confirm" type="password" class="form-control" required minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Change Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var changeBtns = document.querySelectorAll('.change-pass-btn');
  changeBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = this.getAttribute('data-id');
      var username = this.getAttribute('data-username');
      document.getElementById('cp-target-id').value = id;
      document.getElementById('cp-username').textContent = username;
      // clear inputs
      document.getElementById('cp-new').value = '';
      document.getElementById('cp-confirm').value = '';
    });
  });
});
</script>
