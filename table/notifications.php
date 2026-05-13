<?php
/**
 * notifications.php — Upgraded
 * ==============================
 * Changes:
 *  1. Notifications fetched for ADMIN/MANAGER include borrow_request and return_request
 *     items with inline Approve / Deny buttons.
 *  2. Staff users see only their own decision notifications (approved/denied).
 *  3. Actionable items show the item name, borrower, quantity, and reference.
 *  4. Decision actions write to system_logs via borrow_approvals.php (existing flow).
 */

require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

$user_id  = (int)($_SESSION['id'] ?? 0);
$userRole = strtoupper($_SESSION['role'] ?? 'STAFF');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simple mark-read via GET fallback
if (!empty($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $u = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $u->bind_param('ii', $nid, $user_id);
    $u->execute();
    $u->close();
    header('Location: notifications.php');
    exit;
}

// Fetch notifications with full context
$q = $conn->prepare(
    "SELECT n.*,
            ii.item_name,
            b.from_person,
            b.to_person,
            b.quantity       AS borrow_quantity,
            b.reference_no,
            b.status         AS borrow_status,
            CONCAT_WS(' ', u.first_name, u.last_name) AS actor_name
     FROM notifications n
     LEFT JOIN borrowed_items b  ON n.related_id = b.borrow_id
     LEFT JOIN inventory_items ii ON b.inventory_item_id = ii.id
     LEFT JOIN `user` u           ON n.actor_user_id = u.id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 200"
);
$q->bind_param('i', $user_id);
$q->execute();
$res = $q->get_result();
$q->close();

// Notification type → display config
function notif_meta(string $type): array
{
    return match ($type) {
        'borrow_request'       => ['label' => 'Borrow Pending',           'icon' => 'bx-package',          'color' => 'warning',  'actionable' => true],
        'return_request'       => ['label' => 'Return Pending',           'icon' => 'bx-revision',         'color' => 'info',     'actionable' => true],
        'borrow_approved'      => ['label' => 'Borrow Approved',          'icon' => 'bx-check-circle',     'color' => 'success',  'actionable' => false],
        'borrow_denied'        => ['label' => 'Borrow Denied',            'icon' => 'bx-x-circle',         'color' => 'danger',   'actionable' => false],
        'return_approved'      => ['label' => 'Return Approved',          'icon' => 'bx-check-double',     'color' => 'success',  'actionable' => false],
        'return_denied'        => ['label' => 'Return Denied',            'icon' => 'bx-x-circle',         'color' => 'danger',   'actionable' => false],
        'borrow_approved_info' => ['label' => 'Borrow Approved (Info)',   'icon' => 'bx-info-circle',      'color' => 'secondary','actionable' => false],
        'borrow_denied_info'   => ['label' => 'Borrow Denied (Info)',     'icon' => 'bx-info-circle',      'color' => 'secondary','actionable' => false],
        'return_approved_info' => ['label' => 'Return Approved (Info)',   'icon' => 'bx-info-circle',      'color' => 'secondary','actionable' => false],
        'return_denied_info'   => ['label' => 'Return Denied (Info)',     'icon' => 'bx-info-circle',      'color' => 'secondary','actionable' => false],
        default                => ['label' => 'System Notification',      'icon' => 'bx-bell',             'color' => 'secondary','actionable' => false],
    };
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default">
<?php include __DIR__ . '/head.php'; ?>
<body>
<script type="application/json" id="app-config">
{
    "csrf":             "<?= $_SESSION['csrf_token'] ?>",
    "userId":           <?= $user_id ?>,
    "userRole":         "<?= $userRole ?>",
    "baseUrl":          "/inventory_cao_v2",
    "realtimeDisabled": <?= !empty($_SESSION['realtime_disabled']) ? 'true' : 'false' ?>,
    "notifWsHost":      "http://localhost:3000"
}
</script>

<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <?php include 'sidebar.php'; ?>
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="content-wrapper">
            <div class="container-xxl grow container-p-y">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0">Notifications</h4>
                    <div class="d-flex align-items-center gap-2">
                        <button id="markAllReadBtn" class="btn btn-sm btn-outline-secondary">Mark all read</button>
                        <div class="form-check form-switch mb-0">
                            <?php $rd = !empty($_SESSION['realtime_disabled']); ?>
                            <input class="form-check-input" type="checkbox" id="realtimeToggle" <?= $rd ? '' : 'checked' ?>>
                            <label class="form-check-label small" for="realtimeToggle">Realtime Sync</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div id="notifList" class="list-group">

                            <?php if ($res->num_rows === 0): ?>
                                <div class="list-group-item text-center py-5 text-muted">
                                    <i class="bx bx-bell-off fs-1 d-block mb-2"></i>No notifications yet.
                                </div>
                            <?php endif; ?>

                            <?php while ($n = $res->fetch_assoc()):
                                $payload  = json_decode($n['payload'] ?? 'null', true) ?: [];
                                $relatedId = (int)($n['related_id'] ?? 0);
                                $meta     = notif_meta($n['type']);
                                if ($n['type'] === 'borrow_approved' && strtoupper($n['borrow_status'] ?? '') !== 'APPROVED') {
                                    $meta['label'] = 'Borrow Pending';
                                    $meta['icon']  = 'bx-package';
                                    $meta['color'] = 'warning';
                                }
                                $alreadyActioned = !empty($n['action']) && $n['action'] !== 'NONE';
                                $isUnread = !(bool)$n['is_read'];

                                // Context data
                                $itemName  = $n['item_name'] ?? ($payload['item'] ?? 'N/A');
                                $refNo     = $n['reference_no'] ?? ($payload['reference'] ?? '—');
                                $qty       = $n['borrow_quantity'] ?? ($payload['quantity'] ?? ($payload['qty'] ?? '?'));
                                $fromPerson = $n['from_person'] ?? '—';
                                $toPerson   = $n['to_person']   ?? '—';
                                $actorName  = $n['actor_name']  ?? 'System';
                                $remarks    = $payload['reason'] ?? $payload['remarks'] ?? $payload['decision_remarks'] ?? '';
                            ?>
                            <div class="list-group-item list-group-item-action notif-row <?= $isUnread ? 'bg-light border-start border-4 border-warning' : '' ?>"
                                 data-id="<?= $n['id'] ?>"
                                 data-type="<?= htmlspecialchars($n['type']) ?>">

                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-label-<?= $meta['color'] ?> p-2">
                                            <i class="bx <?= $meta['icon'] ?>"></i>
                                        </span>
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($meta['label']) ?></h6>
                                        <?php if ($isUnread): ?>
                                            <span class="badge bg-warning text-dark" style="font-size:.65rem;">NEW</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted text-nowrap ms-2">
                                        <?= date('M d, Y H:i', strtotime($n['created_at'])) ?>
                                    </small>
                                </div>

                                <div class="mt-2 small">
                                    <div class="row g-1">
                                        <div class="col-sm-6">
                                            <i class='bx bx-package me-1 text-muted'></i>
                                            <b>Item:</b> <?= htmlspecialchars($itemName) ?>
                                        </div>
                                        <?php if ($qty !== '?'): ?>
                                        <div class="col-sm-3">
                                            <i class='bx bx-hash me-1 text-muted'></i>
                                            <b>Qty:</b> <?= htmlspecialchars((string)$qty) ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($refNo && $refNo !== '—'): ?>
                                        <div class="col-sm-3">
                                            <i class='bx bx-barcode me-1 text-muted'></i>
                                            <b>Ref:</b> <code><?= htmlspecialchars($refNo) ?></code>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($fromPerson !== '—'): ?>
                                        <div class="col-sm-6">
                                            <i class='bx bx-transfer me-1 text-muted'></i>
                                            <b>Flow:</b> <?= htmlspecialchars($fromPerson) ?>
                                            <i class='bx bx-right-arrow-alt'></i>
                                            <?= htmlspecialchars($toPerson) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-sm-6">
                                            <i class='bx bx-user me-1 text-muted'></i>
                                            <b><?= in_array($n['type'], ['borrow_request','return_request']) ? 'Requested by' : 'Actioned by' ?>:</b>
                                            <?= htmlspecialchars($actorName) ?>
                                        </div>
                                    </div>

                                    <?php if ($remarks): ?>
                                    <div class="alert alert-secondary py-1 px-2 mt-2 mb-0">
                                        <i class='bx bx-comment-detail me-1'></i>
                                        <b>Remarks:</b> <?= htmlspecialchars($remarks) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons: admin/manager actionable requests -->
                                <?php if ($meta['actionable'] && in_array($userRole, ['ADMIN', 'MANAGER']) && !$alreadyActioned): ?>
                                <div class="mt-2 d-flex gap-2 align-items-center">
                                    <button class="btn btn-sm btn-success approve-btn"
                                            data-borrow="<?= $relatedId ?>"
                                            data-notif="<?= $n['id'] ?>"
                                            data-type="<?= htmlspecialchars($n['type']) ?>"
                                            data-qty="<?= htmlspecialchars((string)$qty) ?>">
                                        <i class="bx bx-check me-1"></i>Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger deny-btn"
                                            data-borrow="<?= $relatedId ?>"
                                            data-notif="<?= $n['id'] ?>"
                                            data-type="<?= htmlspecialchars($n['type']) ?>"
                                            data-qty="<?= htmlspecialchars((string)$qty) ?>">
                                        <i class="bx bx-x me-1"></i>Deny
                                    </button>
                                    <span class="text-muted small ms-1">Borrow #<?= $relatedId ?></span>
                                </div>
                                <?php elseif ($meta['actionable'] && in_array($userRole, ['ADMIN','MANAGER']) && $alreadyActioned): ?>
                                <div class="mt-2">
                                    <span class="badge bg-label-<?= $n['action'] === 'APPROVED' ? 'success' : 'danger' ?>">
                                        <i class="bx bx-<?= $n['action'] === 'APPROVED' ? 'check' : 'x' ?> me-1"></i>
                                        <?= ucfirst(strtolower($n['action'])) ?> by you
                                    </span>
                                </div>
                                <?php endif; ?>

                                <!-- Mark read -->
                                <div class="mt-2">
                                    <?php if ($isUnread): ?>
                                    <a href="#" class="mark-read-btn small text-muted" data-id="<?= $n['id'] ?>">
                                        <i class="bx bx-check-double me-1"></i>Mark as read
                                    </a>
                                    <?php else: ?>
                                    <span class="small text-muted"><i class="bx bx-check me-1"></i>Read</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>

                        </div><!-- #notifList -->
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-none border">
                            <div class="card-body">
                                <h6 class="card-title">Summary</h6>
                                <p class="small text-muted mb-2">
                                    Unread notifications are highlighted.
                                    <?php if (in_array($userRole, ['ADMIN','MANAGER'])): ?>
                                    As <strong><?= $userRole ?></strong>, you can approve or deny
                                    borrow and return requests directly from this page.
                                    <?php endif; ?>
                                </p>
                                <?php
                                $unreadCount = $conn->query(
                                    "SELECT COUNT(*) AS c FROM notifications WHERE user_id = $user_id AND is_read = 0"
                                )->fetch_assoc()['c'];
                                ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning text-dark fs-6"><?= $unreadCount ?></span>
                                    <span class="small text-muted">unread notification<?= $unreadCount !== '1' ? 's' : '' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</div>

<!-- Decision Modal -->
<div class="modal fade" id="decisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="decisionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="decisionMessage" class="fw-bold mb-3"></p>
                <div id="returnQtyGroup" class="mb-3 d-none">
                    <label class="form-label fw-semibold">Return Quantity</label>
                    <input type="number" id="returnQtyInput" class="form-control" min="1" placeholder="Enter quantity to return">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Remarks <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="decisionRemarks" class="form-control" rows="3" placeholder="Enter reason or notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="decisionConfirmBtn" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100">
    <div id="notifToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const config     = JSON.parse(document.getElementById('app-config').textContent);
    const modalEl    = document.getElementById('decisionModal');
    const modal      = new bootstrap.Modal(modalEl);
    const toastEl    = document.getElementById('notifToast');
    const bsToast    = new bootstrap.Toast(toastEl);

    let pending = { action: null, borrowId: null, notifId: null, type: null, qty: 0 };

    function showToast(msg, bg = 'dark') {
        toastEl.className = `toast align-items-center text-white bg-${bg} border-0`;
        toastEl.querySelector('.toast-body').textContent = msg;
        bsToast.show();
    }
    window.showNotifToast = showToast;

    // ----------------------------------------------------------------
    // Mark single read
    // ----------------------------------------------------------------
    $(document).on('click', '.mark-read-btn', function (e) {
        e.preventDefault();
        const btn = $(this);
        const nid = btn.data('id');
        const row = btn.closest('.notif-row');

        $.post(config.baseUrl + '/table/notifications_api.php', {
            action: 'mark_read', id: nid, csrf_token: config.csrf
        }, function (res) {
            if (res.success) {
                row.removeClass('bg-light border-start border-4 border-warning');
                row.find('.badge.bg-warning').remove();
                btn.replaceWith('<span class="small text-muted"><i class="bx bx-check me-1"></i>Read</span>');
                const badge = $('#notif-count-badge');
                if (badge.length) {
                    const c = Math.max(0, (parseInt(badge.text()) || 0) - 1);
                    badge.text(c).toggleClass('d-none', c === 0);
                }
                showToast('Marked as read', 'secondary');
            }
        }, 'json');
    });

    // ----------------------------------------------------------------
    // Mark all read
    // ----------------------------------------------------------------
    $('#markAllReadBtn').on('click', function () {
        $.post(config.baseUrl + '/table/notifications_api.php', {
            action: 'mark_all', csrf_token: config.csrf
        }, function (res) {
            if (res.success) {
                showToast('All notifications marked as read', 'secondary');
                setTimeout(() => location.reload(), 700);
            }
        }, 'json');
    });

    // ----------------------------------------------------------------
    // Realtime toggle
    // ----------------------------------------------------------------
    document.getElementById('realtimeToggle').addEventListener('change', async function () {
        const fd = new FormData();
        fd.append('action', 'toggle_realtime');
        fd.append('enabled', this.checked ? 1 : 0);
        fd.append('csrf_token', config.csrf);
        await fetch(config.baseUrl + '/table/notifications_api.php', { method: 'POST', body: fd });
        location.reload();
    });

    // ----------------------------------------------------------------
    // Approve / Deny button clicked → open modal
    // ----------------------------------------------------------------
    document.getElementById('notifList').addEventListener('click', function (e) {
        const btn = e.target.closest('.approve-btn, .deny-btn');
        if (!btn) return;

        pending = {
            action:   btn.classList.contains('approve-btn') ? 'approve' : 'deny',
            borrowId: btn.dataset.borrow,
            notifId:  btn.dataset.notif,
            type:     btn.dataset.type,
            qty:      parseInt(btn.dataset.qty) || 0,
        };

        const isReturn = pending.type === 'return_request';
        const label    = pending.action === 'approve' ? 'Approve' : 'Deny';
        const subject  = isReturn ? 'return request' : 'borrow request';

        document.getElementById('decisionModalTitle').textContent = `${label} ${subject}`;
        document.getElementById('decisionMessage').textContent =
            `${label} ${subject} for Borrow #${pending.borrowId}?`;
        document.getElementById('decisionConfirmBtn').className =
            'btn ' + (pending.action === 'approve' ? 'btn-success' : 'btn-danger');
        document.getElementById('decisionConfirmBtn').textContent = label;

        // Show return-qty field when approving a return
        const qtyGroup = document.getElementById('returnQtyGroup');
        const qtyInput = document.getElementById('returnQtyInput');
        if (isReturn && pending.action === 'approve') {
            qtyGroup.classList.remove('d-none');
            qtyInput.max   = pending.qty || '';
            qtyInput.value = pending.qty || '';
        } else {
            qtyGroup.classList.add('d-none');
            qtyInput.value = '';
        }

        document.getElementById('decisionRemarks').value = '';
        modal.show();
    });

    // ----------------------------------------------------------------
    // Confirm modal decision → POST to borrow_approvals.php
    // ----------------------------------------------------------------
    document.getElementById('decisionConfirmBtn').addEventListener('click', async function () {
        const btn       = this;
        const origText  = btn.textContent;
        btn.disabled    = true;
        btn.innerHTML   = '<span class="spinner-border spinner-border-sm"></span>';

        let apiAction = pending.action;  // 'approve' | 'deny'
        if (pending.type === 'return_request') {
            apiAction = (pending.action === 'approve') ? 'approve_return' : 'deny_return';
        }

        const fd = new FormData();
        fd.append('action',      apiAction);
        fd.append('borrow_id',   pending.borrowId);
        fd.append('remarks',     document.getElementById('decisionRemarks').value);
        fd.append('csrf_token',  config.csrf);

        // Return qty only for approve_return
        if (apiAction === 'approve_return') {
            const inputVal = parseInt(document.getElementById('returnQtyInput').value) || pending.qty;
            fd.append('return_quantity', inputVal);
        }

        try {
            const r   = await fetch(config.baseUrl + '/table/borrow_approvals.php', { method: 'POST', body: fd });
            const res = await r.json();

            if (res.success) {
                // Mark the notification as read
                const fdRead = new FormData();
                fdRead.append('action',     'mark_read');
                fdRead.append('id',         pending.notifId);
                fdRead.append('csrf_token', config.csrf);
                await fetch(config.baseUrl + '/table/notifications_api.php', { method: 'POST', body: fdRead });

                showToast('Action completed successfully', 'success');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(res.msg || 'Action failed', 'danger');
            }
        } catch (err) {
            showToast('Network error', 'danger');
        } finally {
            btn.disabled  = false;
            btn.textContent = origText;
            modal.hide();
        }
    });

    // ----------------------------------------------------------------
    // Polling fallback (30 s)
    // ----------------------------------------------------------------
    setInterval(async () => {
        try {
            const r   = await fetch(config.baseUrl + '/table/notifications_api.php?action=poll');
            const res = await r.json();
            if (res.success && res.unread > 0) {
                const badge = document.getElementById('notif-count-badge');
                if (badge) { badge.textContent = res.unread; badge.classList.remove('d-none'); }
            }
        } catch (_) {}
    }, 30000);

    // ----------------------------------------------------------------
    // Socket.io realtime sync
    // ----------------------------------------------------------------
    if (!config.realtimeDisabled) {
        const loadSocket = () => {
            const s   = document.createElement('script');
            s.src     = config.notifWsHost.replace(/\/+$/, '') + '/socket.io/socket.io.js';
            s.onload  = initSocket;
            s.onerror = () => {
                const s2   = document.createElement('script');
                s2.src     = 'https://cdn.socket.io/4.10.1/socket.io.min.js';
                s2.onload  = initSocket;
                document.head.appendChild(s2);
            };
            document.head.appendChild(s);
        };
        const initSocket = () => {
            if (typeof io !== 'function') return;
            const socket = io(config.notifWsHost, { transports: ['websocket', 'polling'] });
            socket.on('connect',      () => socket.emit('identify', { userId: config.userId }));
            socket.on('notification', () => { showToast('New notification received', 'info'); setTimeout(() => location.reload(), 1500); });
        };
        loadSocket();
    }
});
</script>
</body>
</html>