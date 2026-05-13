<?php
/**
 * Navbar V3 — Integrated, Enhanced & Real-Time
 *
 * What's new in this version:
 *  • Unread notifications are loaded from the `notifications` table on
 *    every page render and pre-populated into the dropdown — no extra
 *    round-trip needed after login.
 *  • A Socket.io listener adds brand-new notifications to the dropdown
 *    in real time (Facebook-style) without any page refresh.
 *  • The badge count reflects both the server-rendered count AND any
 *    notifications that arrive live during the session.
 *  • "Mark all as read" now calls the API endpoint so the DB is kept
 *    in sync.
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session.php';
}

// ── 1. SESSION & IDENTITY ────────────────────────────────────────────────────
$avatarWebPath  = '/inventory_cao_v2/uploads/avatars/';
$avatarDiskPath = $_SERVER['DOCUMENT_ROOT'] . $avatarWebPath;
$avatarFile     = $_SESSION['avatar'] ?? '';
$username       = $_SESSION['username'] ?? 'Guest';
$role           = strtoupper($_SESSION['role'] ?? 'USER');
$user_email     = $_SESSION['email'] ?? 'N/A';
$user_id        = $_SESSION['id'] ?? null;

$is_privileged = ($role === 'ADMIN' || $role === 'MANAGER');

if (!$avatarFile || !file_exists($avatarDiskPath . $avatarFile)) {
    $avatarFile = 'default-avatar.png';
}

// ── 2. GREETING ──────────────────────────────────────────────────────────────
$hour = (int)date('H');
if ($hour < 12) {
    $greeting     = 'Good Morning';
    $greetingIcon = 'bx-sun text-warning';
} elseif ($hour < 18) {
    $greeting     = 'Good Afternoon';
    $greetingIcon = 'bx-cloud-sun text-info';
} else {
    $greeting     = 'Good Evening';
    $greetingIcon = 'bx-moon text-primary';
}

// ── 3. PRE-LOAD UNREAD NOTIFICATIONS FROM DB ─────────────────────────────────
// $conn is expected to be defined by the page that includes this navbar.
// If not available, notifications simply start empty until Socket.io fires.
$preloadedNotifications = [];
$unreadCount            = 0;

if ($user_id && !empty($conn)) {
    $stmt = $conn->prepare(
        "SELECT id, type, payload, created_at
           FROM notifications
          WHERE user_id = ? AND is_read = 0
          ORDER BY created_at DESC
          LIMIT 30"
    );
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $decoded  = json_decode($row['payload'] ?? '{}', true);
            $row['_decoded'] = is_array($decoded) ? $decoded : [];
            $preloadedNotifications[] = $row;
        }
        $stmt->close();
        $unreadCount = count($preloadedNotifications);
    }
}

/**
 * Renders a single notification <li> for the dropdown.
 * Used both by the PHP pre-load and echoed as a JS template string.
 *
 * $item = ['id', 'type', '_decoded' => ['title','body','notification_type'], 'created_at']
 */
function renderNotifItem(array $item): string
{
    $type    = htmlspecialchars($item['type'] ?? 'NOTIFICATION');
    $title   = htmlspecialchars($item['_decoded']['title'] ?? 'System Notification');
    $body    = $item['_decoded']['body'] ?? '';
    $time    = $item['created_at'] ?? '';
    $notifId = (int)($item['id'] ?? 0);

    // Choose an icon and colour based on type.
    $iconMap = [
        'USER_DATA_UPDATED'         => ['bx-user-check',   'bg-label-info'],
        'BORROW_REQUEST_SUBMITTED'  => ['bx-package',      'bg-label-primary'],
        'BORROW_REQUEST_APPROVED'   => ['bx-check-circle', 'bg-label-success'],
        'BORROW_REQUEST_DENIED'     => ['bx-x-circle',     'bg-label-danger'],
        'RETURN_REQUEST_SUBMITTED'  => ['bx-undo',         'bg-label-primary'],
        'RETURN_REQUEST_APPROVED'   => ['bx-check-double', 'bg-label-success'],
        'RETURN_REQUEST_REJECTED'   => ['bx-error',        'bg-label-warning'],
        'OVERDUE_ALERT'             => ['bx-alarm-exclamation', 'bg-label-danger'],
        'OVERDUE_CRITICAL'          => ['bx-alarm-exclamation', 'bg-label-danger'],
        'INCIDENT_CREATED'          => ['bx-error-circle', 'bg-label-warning'],
        'INCIDENT_RESOLVED'         => ['bx-badge-check',  'bg-label-success'],
        'DAMAGE_REPORTED'           => ['bx-wrench',       'bg-label-warning'],
        'SYSTEM_ESCALATION'         => ['bx-shield-x',     'bg-label-danger'],
    ];

    [$icon, $colour] = $iconMap[$type] ?? ['bx-bell', 'bg-label-secondary'];

    // Format time
    $timeLabel = $time ? date('M d, g:i A', strtotime($time)) : 'Just now';

    return <<<HTML
<li class="list-group-item list-group-item-action dropdown-notifications-item"
    data-notif-id="{$notifId}" data-notif-type="{$type}">
    <div class="d-flex">
        <div class="flex-shrink-0 me-3">
            <div class="avatar">
                <span class="avatar-initial rounded-circle {$colour}">
                    <i class="bx {$icon}"></i>
                </span>
            </div>
        </div>
        <div class="flex-grow-1">
            <h6 class="mb-1 notif-item-title">{$title}</h6>
            <p class="mb-0 text-body notif-item-body small">{$body}</p>
            <small class="text-muted">{$timeLabel}</small>
        </div>
        <div class="flex-shrink-0 ms-2">
            <span class="badge rounded-pill bg-label-primary notif-unread-dot"
                  style="width:10px;height:10px;padding:0;"></span>
        </div>
    </div>
</li>
HTML;
}
?>
<!-– ═══════════════════ STYLES ═══════════════════ -->
<style>
    /* ── Navbar V3 Aesthetics ─────────────────────────────────────────── */
    .navbar-v3 {
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        background: rgba(255, 255, 255, 0.85) !important;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 0.5rem;
        margin: 1rem auto;
        width: calc(100% - 2rem);
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
        z-index: 1050;
    }

    .navbar-v3.navbar-compact {
        margin: 0 auto;
        width: 100%;
        border-radius: 0;
        background: rgba(255, 255, 255, 0.98) !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .search-wrapper-sleek .form-control {
        background-color: #f4f5fa;
        border: 1px solid transparent;
        border-radius: 0.5rem;
        padding-left: 2.5rem;
        transition: all 0.2s ease;
    }

    .search-wrapper-sleek .form-control:focus {
        background-color: #fff;
        border-color: #696cff;
        box-shadow: 0 0 0 0.25rem rgba(105, 108, 255, 0.1);
    }

    .search-wrapper-sleek .bx-search {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #a1acb8;
    }

    /* ── Notification dropdown ────────────────────────────────────────── */
    .dropdown-notifications-list {
        max-height: 380px;
        overflow-y: auto;
    }

    /* New-notification pop-in animation */
    @keyframes notifSlideIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .notif-new-item {
        animation: notifSlideIn 0.28s ease forwards;
        background-color: rgba(105, 108, 255, 0.06) !important;
    }

    .dropdown-notifications-item:hover { background-color: rgba(105, 108, 255, 0.04); }

    /* Badge pulse when a new notification lands */
    @keyframes badgePulse {
        0%   { box-shadow: 0 0 0 0 rgba(234, 84, 85, 0.7); }
        70%  { box-shadow: 0 0 0 8px rgba(234, 84, 85, 0);  }
        100% { box-shadow: 0 0 0 0 rgba(234, 84, 85, 0);   }
    }
    .badge-pulse { animation: badgePulse 0.9s ease-out 2; }
</style>

<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme navbar-v3"
     id="layout-navbar">

    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
            <i class="bx bx-menu bx-sm"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

        <!-- Search -->
        <div class="navbar-nav align-items-center flex-grow-1">
            <div class="nav-item d-flex align-items-center search-wrapper-sleek position-relative w-50">
                <i class="bx bx-search fs-4"></i>
                <input type="text" class="form-control border-0 shadow-none" id="global-search"
                       placeholder="Search inventory…" aria-label="Search…">
            </div>
        </div>

        <ul class="navbar-nav flex-row align-items-center ms-auto">

            <!-- Greeting -->
            <li class="nav-item me-3 d-none d-md-flex align-items-center">
                <i class="bx <?= $greetingIcon ?> fs-4 me-2"></i>
                <span class="text-muted fw-medium">
                    <?= $greeting ?>, <span class="text-body fw-bold"><?= htmlspecialchars($username) ?></span>
                </span>
            </li>

            <!-- ── Notification Bell ──────────────────────────────────── -->
            <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                   data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                   id="notif-bell-btn">
                    <i class="bx bx-bell bx-sm"></i>
                    <span class="badge bg-danger rounded-pill badge-notifications<?= $unreadCount === 0 ? ' d-none' : '' ?>"
                          id="notif-count-badge"><?= $unreadCount > 0 ? $unreadCount : '' ?></span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end py-0">
                    <li class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h5 class="text-body mb-0 me-auto">Notifications</h5>
                            <a href="javascript:void(0)" id="mark-all-read-btn"
                               class="dropdown-notifications-all text-body"
                               data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Mark all as read">
                                <i class="bx fs-4 bx-envelope-open"></i>
                            </a>
                        </div>
                    </li>

                    <!-- Notification list (pre-populated server-side) -->
                    <li class="dropdown-notifications-list scrollable-container">
                        <ul class="list-group list-group-flush" id="notif-list-container">
                            <?php if (empty($preloadedNotifications)): ?>
                            <li class="list-group-item text-center text-muted py-4" id="notif-empty-state">
                                <i class="bx bx-bell-off fs-3 d-block mb-1 opacity-50"></i>
                                No new notifications
                            </li>
                            <?php else: ?>
                                <?php foreach ($preloadedNotifications as $notif): ?>
                                    <?= renderNotifItem($notif) ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="dropdown-menu-footer border-top">
                        <a href="notifications.php"
                           class="dropdown-item d-flex justify-content-center p-3">
                            View all notifications
                        </a>
                    </li>
                </ul>
            </li>
            <!-- ── /Notification Bell ─────────────────────────────────── -->

            <!-- User Avatar Dropdown -->
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                   data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <img src="<?= htmlspecialchars($avatarWebPath . $avatarFile) ?>"
                             alt class="w-px-40 h-auto rounded-circle" />
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="account_settings.php">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($avatarWebPath . $avatarFile) ?>"
                                             alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold d-block"><?= htmlspecialchars($username) ?></span>
                                    <small class="text-muted"><?= ucfirst(strtolower($role)) ?></small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li><div class="dropdown-divider"></div></li>
                    <li>
                        <a class="dropdown-item" href="account_settings.php">
                            <i class="bx bx-user me-2"></i>
                            <span class="align-middle">My Profile</span>
                        </a>
                    </li>
                    <?php if ($role === 'ADMIN'): ?>
                    <li>
                        <a class="dropdown-item text-primary" href="system_monitor.php">
                            <i class="bx bx-shield-quarter me-2"></i>
                            <span class="align-middle">System Activity</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($is_privileged): ?>
                    <li>
                        <a class="dropdown-item" href="settings.php">
                            <i class="bx bx-cog me-2"></i>
                            <span class="align-middle">System Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><div class="dropdown-divider"></div></li>
                    <li>
                        <a class="dropdown-item text-danger" href="login/logout.php">
                            <i class="bx bx-power-off me-2"></i>
                            <span class="align-middle">Log Out</span>
                        </a>
                    </li>
                </ul>
            </li>

        </ul>
    </div><!-- /navbar-nav-right -->
</nav>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SCRIPTS
     ─────────────────────────────────────────────────────────────────────── -->
<!--
    Socket.io client — served by the local Node notification server.
    If the WS server is unavailable the script tag itself fails silently;
    the rest of the page is unaffected.
-->
<script src="http://localhost:3000/socket.io/socket.io.js"
        onerror="window._socketIoUnavailable=true;"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Constants ────────────────────────────────────────────────────────────
    const CURRENT_USER_ID  = <?= json_encode((int)($user_id ?? 0)) ?>;
    const MARK_READ_URL    = '/inventory_cao_v2/components/notifications_api.php';

    // ── DOM refs ─────────────────────────────────────────────────────────────
    const navbar           = document.getElementById('layout-navbar');
    const badge            = document.getElementById('notif-count-badge');
    const listContainer    = document.getElementById('notif-list-container');
    const markAllReadBtn   = document.getElementById('mark-all-read-btn');

    // ── Badge helpers ────────────────────────────────────────────────────────
    function getBadgeCount() {
        return parseInt(badge.dataset.count || badge.innerText || '0', 10) || 0;
    }

    function setBadgeCount(n) {
        badge.dataset.count = n;
        if (n > 0) {
            badge.innerText = n > 99 ? '99+' : n;
            badge.classList.remove('d-none');
        } else {
            badge.innerText = '';
            badge.classList.add('d-none');
        }
    }

    function incrementBadge() {
        setBadgeCount(getBadgeCount() + 1);
        badge.classList.add('badge-pulse');
        setTimeout(() => badge.classList.remove('badge-pulse'), 1900);
    }

    // Initialise badge from the server-rendered count
    setBadgeCount(<?= $unreadCount ?>);

    // ── Empty-state helper ───────────────────────────────────────────────────
    function removeEmptyState() {
        const empty = document.getElementById('notif-empty-state');
        if (empty) empty.remove();
    }

    // ── Icon / colour map (mirrors PHP renderNotifItem) ──────────────────────
    const ICON_MAP = {
        'USER_DATA_UPDATED':        ['bx-user-check',        'bg-label-info'],
        'BORROW_REQUEST_SUBMITTED': ['bx-package',           'bg-label-primary'],
        'BORROW_REQUEST_APPROVED':  ['bx-check-circle',      'bg-label-success'],
        'BORROW_REQUEST_DENIED':    ['bx-x-circle',          'bg-label-danger'],
        'RETURN_REQUEST_SUBMITTED': ['bx-undo',              'bg-label-primary'],
        'RETURN_REQUEST_APPROVED':  ['bx-check-double',      'bg-label-success'],
        'RETURN_REQUEST_REJECTED':  ['bx-error',             'bg-label-warning'],
        'OVERDUE_ALERT':            ['bx-alarm-exclamation', 'bg-label-danger'],
        'OVERDUE_CRITICAL':         ['bx-alarm-exclamation', 'bg-label-danger'],
        'INCIDENT_CREATED':         ['bx-error-circle',      'bg-label-warning'],
        'INCIDENT_RESOLVED':        ['bx-badge-check',       'bg-label-success'],
        'DAMAGE_REPORTED':          ['bx-wrench',            'bg-label-warning'],
        'SYSTEM_ESCALATION':        ['bx-shield-x',          'bg-label-danger'],
    };

    function buildNotifHTML(data) {
        /*
         * `data` is the payload object emitted by the Node server, which looks like:
         * {
         *   type:       'notification',           // WS event name
         *   payload: {
         *     type:       'USER_DATA_UPDATED',    // notification type slug
         *     related_id: 42,
         *     payload: { title: '…', body: '…' },
         *     created_at: '2025-…'
         *   }
         * }
         *
         * NotificationHandler also emits simpler shapes when called via
         * push_notification_ws(); normalise both forms here.
         */
        const inner      = data.payload || data;         // unwrap one level if needed
        const typeSlug   = inner.type || inner.payload?.notification_type || '';
        const titleText  = inner.payload?.title  || inner.title  || 'System Notification';
        const bodyText   = inner.payload?.body   || inner.body   || '';
        const createdAt  = inner.created_at || 'Just now';

        const [icon, colour] = ICON_MAP[typeSlug] || ['bx-bell', 'bg-label-secondary'];

        // Format timestamp
        let timeLabel = 'Just now';
        if (createdAt && createdAt !== 'Just now') {
            try {
                timeLabel = new Date(createdAt).toLocaleString('en-PH', {
                    month: 'short', day: 'numeric',
                    hour: 'numeric', minute: '2-digit'
                });
            } catch (_) {}
        }

        return `
<li class="list-group-item list-group-item-action dropdown-notifications-item notif-new-item"
    data-notif-type="${escHtml(typeSlug)}">
  <div class="d-flex">
    <div class="flex-shrink-0 me-3">
      <div class="avatar">
        <span class="avatar-initial rounded-circle ${escHtml(colour)}">
          <i class="bx ${escHtml(icon)}"></i>
        </span>
      </div>
    </div>
    <div class="flex-grow-1">
      <h6 class="mb-1 notif-item-title">${escHtml(titleText)}</h6>
      <p class="mb-0 text-body small notif-item-body">${escHtml(bodyText)}</p>
      <small class="text-muted">${escHtml(timeLabel)}</small>
    </div>
    <div class="flex-shrink-0 ms-2">
      <span class="badge rounded-pill bg-label-primary notif-unread-dot"
            style="width:10px;height:10px;padding:0;"></span>
    </div>
  </div>
</li>`;
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Socket.io real-time listener ─────────────────────────────────────────
    if (!window._socketIoUnavailable && typeof io !== 'undefined' && CURRENT_USER_ID) {
        try {
            const socket = io('http://localhost:3000', {
                transports: ['websocket', 'polling'],
                reconnectionAttempts: 5,
                reconnectionDelay: 2000,
            });

            // Join this user's private room so only their notifications arrive.
            socket.on('connect', function () {
                socket.emit('join', 'user_' + CURRENT_USER_ID);
            });

            // ── Core handler: incoming notification ──────────────────────────
            socket.on('notification', function (data) {
                removeEmptyState();
                listContainer.insertAdjacentHTML('afterbegin', buildNotifHTML(data));
                incrementBadge();

                // Optional subtle sound (uncomment and supply the file)
                // try { new Audio('/inventory_cao_v2/assets/sounds/pop.mp3').play(); } catch(_) {}
            });

            // Handle borrow/return broadcast updates (existing system event)
            socket.on('borrow_update', function (data) {
                // This event is a broadcast and doesn't add to the notification
                // badge, but you can hook into it here for live table refreshes.
                document.dispatchEvent(new CustomEvent('borrow_update', { detail: data }));
            });

        } catch (wsErr) {
            console.warn('[Navbar] WebSocket init failed:', wsErr.message);
        }
    }

    // ── Mark all as read ─────────────────────────────────────────────────────
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function () {
            if (getBadgeCount() === 0) return; // nothing to do

            // Optimistic UI: clear badge and remove unread dots immediately
            setBadgeCount(0);
            document.querySelectorAll('.notif-unread-dot').forEach(function (el) {
                el.remove();
            });
            document.querySelectorAll('.dropdown-notifications-item').forEach(function (el) {
                el.classList.remove('notif-new-item', 'bg-light');
            });

            // Persist to database via API
            fetch(MARK_READ_URL + '?action=mark_all_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ user_id: CURRENT_USER_ID })
            }).catch(function (e) {
                console.warn('[Navbar] mark-all-read failed:', e.message);
            });
        });
    }

    // ── Scroll-compact behaviour (unchanged from V2) ─────────────────────────
    window.addEventListener('scroll', function () {
        if (window.scrollY > 15) {
            navbar.classList.add('navbar-compact');
        } else {
            navbar.classList.remove('navbar-compact');
        }
    }, { passive: true });

    // Reload on bfcache restore to keep session fresh
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) window.location.reload();
    });

    // ── Telemetry: global JS error reporting (unchanged from V2) ─────────────
    (function () {
        const ERROR_ENDPOINT = '/inventory_cao_v2/components/notification_logs.php';
        let isReporting = false;

        function sendErrorReport(data) {
            if (isReporting) return;
            isReporting = true;
            data.page = window.location.href;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ERROR_ENDPOINT, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    setTimeout(function () { isReporting = false; }, 2000);
                }
            };
            try { xhr.send(JSON.stringify(data)); } catch (e) { isReporting = false; }
        }

        window.addEventListener('error', function (evt) {
            try {
                sendErrorReport({
                    type:      'error',
                    message:   evt.message   || null,
                    filename:  evt.filename  || null,
                    lineno:    evt.lineno    || null,
                    colno:     evt.colno     || null,
                    stack:     (evt.error && evt.error.stack) ? evt.error.stack : null,
                    userAgent: navigator.userAgent || null
                });
            } catch (e) {}
        });
    })();

});// end DOMContentLoaded
</script>