<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$_reqRole = strtoupper($_SESSION['role'] ?? '');
if (!in_array($_reqRole, ['ADMIN', 'MANAGER'], true)) { header('Location: borrow_items.php'); exit; }
require_once 'config.php';

$q = $conn->prepare("SELECT b.borrow_id, b.accountable_id, b.inventory_item_id, b.from_person, b.to_person, b.borrower_employee_id, b.quantity, b.reference_no, b.remarks, b.requested_by, b.requested_at, ii.item_name FROM borrowed_items b LEFT JOIN inventory_items ii ON ii.id = b.inventory_item_id WHERE b.status = 'PENDING' ORDER BY b.requested_at ASC");
$q->execute();
$res = $q->get_result();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Borrow Requests</title>
<link rel="stylesheet" href="../assets/vendor/css/core.css">
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
</head><body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); $CSRF = $_SESSION['csrf_token']; ?>
<div style="margin-left:250px;padding:20px;">
  <h4>Pending Borrow Requests</h4>
  <table class="table" id="pendingRequestsTable">
    <thead><tr><th>ID</th><th>Item</th><th>From</th><th>To</th><th>Qty</th><th>Requested At</th><th>Actions</th></tr></thead>
    <tbody>
    <?php while ($r = $res->fetch_assoc()): ?>
      <tr id="req-row-<?= (int)$r['borrow_id'] ?>">
        <td><?= (int)$r['borrow_id'] ?></td>
        <td><?= htmlspecialchars($r['item_name']) ?></td>
        <td><?= htmlspecialchars($r['from_person']) ?></td>
        <td><?= htmlspecialchars($r['to_person']) ?></td>
        <td><?= (int)$r['quantity'] ?></td>
        <td><?= htmlspecialchars($r['requested_at']) ?></td>
        <td>
          <button class="btn btn-sm btn-success btn-approve" data-id="<?= (int)$r['borrow_id'] ?>">Approve</button>
          <button class="btn btn-sm btn-danger btn-deny" data-id="<?= (int)$r['borrow_id'] ?>">Deny</button>
          <input type="text" class="deny-remarks" placeholder="Reason (optional)" data-id="<?= (int)$r['borrow_id'] ?>">
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script>
$(function(){
  function showToast(msg, type='success'){
    alert(msg);
  }

  function sendAction(borrowId, action, remarks, btn){
    btn.prop('disabled', true);
    $.post('borrow_approvals.php', { borrow_id: borrowId, action: action, remarks: remarks, csrf_token: '<?= $CSRF ?>' }, function(resp){
      if (resp && resp.success) {
        $('#req-row-' + borrowId).fadeOut(300, function(){ $(this).remove(); });
        showToast('Request ' + action + 'ed');
      } else {
        showToast('Error: ' + (resp.msg || 'unknown'));
        btn.prop('disabled', false);
      }
    }, 'json').fail(function(xhr){
      showToast('Request failed: ' + xhr.statusText);
      btn.prop('disabled', false);
    });
  }

  $('#pendingRequestsTable').on('click', '.btn-approve', function(){
    var id = $(this).data('id');
    if (!confirm('Approve borrow #' + id + '?')) return;
    sendAction(id, 'approve', '', $(this));
  });

  $('#pendingRequestsTable').on('click', '.btn-deny', function(){
    var id = $(this).data('id');
    var remarks = $(this).closest('tr').find('.deny-remarks[data-id="'+id+'"]').val() || '';
    if (!confirm('Deny borrow #' + id + '?')) return;
    sendAction(id, 'deny', remarks, $(this));
  });
});
</script>

</body></html>