<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

$user_id = $_SESSION['id'] ?? 0;

// Generate CSRF if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Handle Simple Mark Read (Query Param Fallback)
if (!empty($_GET['mark_read']) && intval($_GET['mark_read']) > 0) {
    $nid = intval($_GET['mark_read']);
    $u = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $u->bind_param('ii', $nid, $user_id);
    $u->execute();
    $u->close();
    header('Location: notifications.php');
    exit;
}

// 2. Fetch Notifications
$q = $conn->prepare(
    "SELECT n.*, ii.item_name, b.from_person, b.to_person, CONCAT_WS(' ', u.first_name, u.last_name) AS admin_name
     FROM notifications n
     LEFT JOIN borrowed_items b ON n.related_id = b.borrow_id
     LEFT JOIN inventory_items ii ON b.inventory_item_id = ii.id
     LEFT JOIN `user` u ON n.actor_user_id = u.id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 200"
);
$q->bind_param('i', $user_id);
$q->execute();
$res = $q->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default">
<?php include __DIR__ . '/head.php'; ?>
<body>
    <script type="application/json" id="app-config">
    {
        "csrf": "<?= $_SESSION['csrf_token'] ?>",
        "userId": <?= $user_id ?>,
        "baseUrl": "/inventory_cao_v2",
        "realtimeDisabled": <?= !empty($_SESSION['realtime_disabled']) ? 'true' : 'false' ?>,
        "notifWsHost": "http://localhost:3000"
    }
    </script>

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include 'sidebar.php'; ?>
            
            <!-- <div class="layout-page"> -->
                <?php include __DIR__ . '/../includes/navbar.php'; ?>

                <div class="content-wrapper">
                    <div class="container-xxl grow container-p-y">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="m-0">Notifications</h4>
                            <div>
                                <button id="markAllReadBtn" class="btn btn-sm btn-outline-secondary">Mark all read</button>
                                <div class="form-check form-switch d-inline-block ms-2">
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
                                        <div class="list-group-item text-center py-4">No notifications found.</div>
                                    <?php endif; ?>

                                    <?php while ($n = $res->fetch_assoc()):
                                        $payload = json_decode($n['payload'] ?? 'null', true) ?: [];
                                        $related = intval($n['related_id'] ?? 0);
                                        $remarks = $payload['remarks'] ?? $payload['decision_remarks'] ?? ($n['action'] ?? '') ;
                                    ?>
                                        <div class="list-group-item list-group-item-action notif-row <?= $n['is_read'] ? '' : 'bg-light' ?>" data-id="<?= $n['id'] ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1 fw-bold text-primary">
                                                    <?php
                                                        if (strpos($n['type'], 'approved') !== false) echo '<span class="text-success">Request Approved</span>';
                                                        elseif (strpos($n['type'], 'denied') !== false) echo '<span class="text-danger">Request Denied</span>';
                                                        elseif (strpos($n['type'], 'return') !== false) echo 'Return Status Updated';
                                                        else echo 'System Notification';
                                                    ?>
                                                </h6>
                                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></small>
                                            </div>
                                            
                                            <div class="notif-content small">
                                                <p class="mb-1">
                                                    <i class='bx bx-package me-1'></i> <b>Item:</b> <?= htmlspecialchars($n['item_name'] ?? 'N/A') ?><br>
                                                    <i class='bx bx-transfer me-1'></i> <b>Flow:</b> <?= htmlspecialchars($n['from_person'] ?? '') ?> <i class='bx bx-right-arrow-alt'></i> <?= htmlspecialchars($n['to_person'] ?? '') ?><br>
                                                    <i class='bx bx-user-check me-1'></i> <b>Processed by:</b> <?= htmlspecialchars($n['admin_name'] ?? 'System') ?>
                                                </p>

                                                <?php if (!empty($remarks)): ?>
                                                    <div class="alert alert-secondary p-2 mt-2 mb-0">
                                                        <small><b>Admin Remarks:</b> <?= htmlspecialchars($remarks) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-2">
                                                <a href="#" class="mark-read-link mark-read-btn small text-muted" data-id="<?= $n['id'] ?>">Mark as read</a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card shadow-none border">
                                    <div class="card-body">
                                        <h6 class="card-title">Summary</h6>
                                        <p class="small text-muted">Unread notifications are highlighted in yellow. Admin users can process borrow and return requests directly.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include __DIR__ . '/footer.php'; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="decisionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="decisionMessage" class="fw-bold"></p>
                    <div class="mb-3">
                        <label class="form-label">Remarks (Optional)</label>
                        <textarea id="decisionRemarks" class="form-control" rows="3" placeholder="Enter reason for approval or denial..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="decisionConfirmBtn" class="btn btn-primary">Confirm Action</button>
                </div>
            </div>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
        <div id="notifToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script>
        // jQuery-based event delegation for mark-read links (navbar and main page)
        $(document).ready(function() {
            const CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";
            const baseUrl = '/inventory_cao_v2';

            // --- 1. Functional "Mark All Read" (Button ID: markAllReadBtn) ---
            $('#markAllReadBtn').on('click', function(e) {
                e.preventDefault();
                const btn = $(this);

                $.post(baseUrl + '/table/notifications_api.php', {
                    action: 'mark_all',
                    csrf_token: CSRF_TOKEN
                }, function(res) {
                    if (res.success) {
                        // Remove highlight from all items
                        $('.notif-item, .notif-row').removeClass('fw-bold bg-light list-group-item-warning');
                        $('.mark-read-link, .mark-read-btn').fadeOut();
                        $('#notif-count-badge').addClass('d-none').text('0');
                        // Show toast and reload after short delay
                        if (window.showNotifToast) {
                            window.showNotifToast('All notifications marked as read');
                        }
                        setTimeout(() => location.reload(), 1000);
                    }
                }, 'json');
            });

            // --- 2. Functional "Mark Single Read" (Class: mark-read-link, mark-read-btn) ---
            // Using delegation so it works for items in Navbar AND the main page
            $(document).on('click', '.mark-read-link, .mark-read-btn', function(e) {
                e.preventDefault();
                const link = $(this);
                const notifId = link.data('id');
                const container = link.closest('.notif-item, .notif-row');

                $.post(baseUrl + '/table/notifications_api.php', {
                    action: 'mark_read',
                    id: notifId,
                    csrf_token: CSRF_TOKEN
                }, function(res) {
                    if (res.success) {
                        // Visually update the UI
                        container.removeClass('fw-bold bg-light list-group-item-warning');
                        link.fadeOut();

                        // Update badge count if it exists
                        let badge = $('#notif-count-badge');
                        if (badge.length > 0) {
                            let count = parseInt(badge.text()) || 0;
                            if (count > 0) {
                                count--;
                                badge.text(count);
                                if (count === 0) badge.addClass('d-none');
                            }
                        }

                        // Show toast
                        if (window.showNotifToast) {
                            window.showNotifToast('Notification marked as read');
                        }
                    }
                }, 'json');
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const config = JSON.parse(document.getElementById('app-config').textContent);
            const modalEl = document.getElementById('decisionModal');
            const modal = new bootstrap.Modal(modalEl);
            const toastEl = document.getElementById('notifToast');
            const toast = new bootstrap.Toast(toastEl);

            let pending = { action: null, borrowId: null, notifId: null, type: null, qty: 0 };

            function showToast(msg) {
                toastEl.querySelector('.toast-body').textContent = msg;
                toast.show();
            }

            // Expose toast function globally for jQuery code
            window.showNotifToast = showToast;

            // 1. Mark Read (Individual) - Fetch API version (fallback if jQuery fails)
            const notifListEl = document.getElementById('notifList');
            if (notifListEl) {
                notifListEl.addEventListener('click', async (e) => {
                    const btn = e.target.closest('.mark-read-btn');
                    if (!btn) return;

                    const fd = new FormData();
                    fd.append('action', 'mark_read');
                    fd.append('id', btn.dataset.id);
                    fd.append('csrf_token', config.csrf);

                    try {
                        const r = await fetch(config.baseUrl + '/table/notifications_api.php', { method: 'POST', body: fd });
                        const res = await r.json();
                        if (res.success) {
                            btn.closest('.notif-row').classList.remove('list-group-item-warning');
                            btn.remove();
                            showToast("Marked as read");
                        }
                    } catch (err) { showToast("Error connecting to server"); }
                });
            }

            // 2. Mark All Read (Fetch API version - fallback)
            const markAllBtn = document.getElementById('markAllReadBtn');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', async () => {
                    const fd = new FormData();
                    fd.append('action', 'mark_all');
                    fd.append('csrf_token', config.csrf);
                    try {
                        const r = await fetch(config.baseUrl + '/table/notifications_api.php', { method: 'POST', body: fd });
                        const res = await r.json();
                        if (res.success) location.reload();
                    } catch (err) { showToast("Error processing request"); }
                });
            }

            // 3. Realtime Toggle
            document.getElementById('realtimeToggle').addEventListener('change', async function() {
                const fd = new FormData();
                fd.append('action', 'toggle_realtime');
                fd.append('enabled', this.checked ? 1 : 0);
                fd.append('csrf_token', config.csrf);
                await fetch(config.baseUrl + '/table/notifications_api.php', { method: 'POST', body: fd });
                location.reload();
            });

            // 4. Approve/Deny Button Trigger
            document.getElementById('notifList').addEventListener('click', (e) => {
                const btn = e.target.closest('.approve-btn, .deny-btn');
                if (!btn) return;

                pending = {
                    action: btn.classList.contains('approve-btn') ? 'approve' : 'deny',
                    borrowId: btn.dataset.borrow,
                    notifId: btn.dataset.notif,
                    type: btn.dataset.type,
                    qty: btn.dataset.qty || 0
                };

                document.getElementById('decisionMessage').textContent = 
                    `Confirm ${pending.action} for ${pending.type.replace('_', ' ')} #${pending.borrowId}?`;
                modal.show();
            });

            // 5. Submit Modal Decision (The backend logic)
            document.getElementById('decisionConfirmBtn').addEventListener('click', async function() {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
                
                const fd = new FormData();
                // Logic to handle Return Request actions correctly
                let actionPath = pending.action;
                if (pending.type === 'return_request') {
                    actionPath = (pending.action === 'approve') ? 'approve_return' : 'deny_return';
                    fd.append('return_quantity', pending.qty);
                }

                fd.append('action', actionPath);
                fd.append('borrow_id', pending.borrowId);
                fd.append('remarks', document.getElementById('decisionRemarks').value);
                fd.append('csrf_token', config.csrf);

                try {
                    const r = await fetch(config.baseUrl + '/table/borrow_approvals.php', { method: 'POST', body: fd });
                    const res = await r.json();
                    
                    if (res.success) {
                        // Mark notif read automatically on success
                        const fdRead = new FormData();
                        fdRead.append('action', 'mark_read');
                        fdRead.append('id', pending.notifId);
                        fdRead.append('csrf_token', config.csrf);
                        await fetch(config.baseUrl + '/table/notifications_api.php', { method: 'POST', body: fdRead });
                        
                        showToast("Action processed successfully");
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(res.msg || "Action failed");
                    }
                } catch (err) {
                    showToast("Network error");
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    modal.hide();
                }
            });

            // 6. Socket.io Realtime Sync
            if (!config.realtimeDisabled) {
                const loadSocket = () => {
                    const s = document.createElement('script');
                    s.src = config.notifWsHost.replace(/\/+$/, '') + '/socket.io/socket.io.js';
                    s.onload = initSocket;
                    s.onerror = () => {
                        const s2 = document.createElement('script');
                        s2.src = 'https://cdn.socket.io/4.10.1/socket.io.min.js';
                        s2.onload = initSocket;
                        document.head.appendChild(s2);
                    };
                    document.head.appendChild(s);
                };

                const initSocket = () => {
                    if (typeof io !== 'function') return;
                    const socket = io(config.notifWsHost, { transports: ['websocket', 'polling'] });
                    socket.on('connect', () => socket.emit('identify', { userId: config.userId }));
                    socket.on('notification', () => {
                        showToast("New notification received");
                        setTimeout(() => location.reload(), 1500);
                    });
                };
                loadSocket();
            }

            // 7. Polling Fallback (Every 30s)
            setInterval(async () => {
                try {
                    const r = await fetch(config.baseUrl + '/table/notifications_api.php?action=poll');
                    const res = await r.json();
                    if (res.success && res.unread > 0) {
                        const badge = document.getElementById('notif-count-badge');
                        if (badge) {
                            badge.textContent = res.unread;
                            badge.classList.remove('d-none');
                        }
                    }
                } catch (e) {}
            }, 30000);
        });
    </script>
</body>
</html>
