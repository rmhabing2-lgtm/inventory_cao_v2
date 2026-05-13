<?php
if (session_status() === PHP_SESSION_NONE) {
  require_once __DIR__ . '/../includes/session.php';
}

// Base avatar directory
$avatarWebPath = '/inventory_cao_v2/uploads/avatars/';
$avatarDiskPath = $_SERVER['DOCUMENT_ROOT'] . $avatarWebPath;

// Get avatar from session
$avatarFile = $_SESSION['avatar'] ?? '';
$username = $_SESSION['username'] ?? 'Guest';
$role = $_SESSION['role'] ?? 'STAFF';
$user_email = $_SESSION['email'] ?? 'N/A';
$user_id = $_SESSION['id'] ?? null;

// Validate avatar file
if (!$avatarFile || !file_exists($avatarDiskPath . $avatarFile)) {
  $avatarFile = '';
}

// Get current time for greeting
$hour = date('H');
if ($hour < 12) {
  $greeting = 'Good Morning';
  $greetingIcon = 'bx-sun';
} elseif ($hour < 18) {
  $greeting = 'Good Afternoon';
  $greetingIcon = 'bx-cloud-sun';
} else {
  $greeting = 'Good Evening';
  $greetingIcon = 'bx-moon';
}
?>

<!-- NAVBAR -->
<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
  <style>
    :root {
      --navbar-height: 70px;
      --navbar-height-compact: 60px;
    }

    .layout-navbar {
      height: var(--navbar-height);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      z-index: 99;
    }

    .layout-navbar.navbar-compact {
      height: var(--navbar-height-compact);
      padding: 8px 0;
    }

    .navbar-enhance {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Improve contrast and readability for navbar icons and text */
    .layout-navbar { color: #fff; }
    .layout-navbar .nav-link, .layout-navbar .navbar-nav .nav-link { color: #fff !important; }
    .layout-navbar .bx { color: #fff !important; font-size: 18px; }
    .layout-navbar .greeting-text, .layout-navbar .user-info-text span { color: #fff !important; }
    .layout-navbar .user-info-text { display: flex; flex-direction: column; max-width: 160px; overflow: hidden; }
    .layout-navbar .user-info-text span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .layout-navbar .avatar-badge img { border-color: rgba(255,255,255,0.45); }
    .layout-navbar .dropdown-menu-enhanced { color: #333; background: #fff; }

    .navbar-enhance:hover {
      box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
    }

    .navbar-enhance.navbar-scrolled {
      box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
      padding: 8px 0;
    }

    .user-profile-btn {
      position: relative;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      border-radius: 50px;
      background: rgba(255, 255, 255, 0.1);
      border: 2px solid rgba(255, 255, 255, 0.2);
      color: white;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none;
      white-space: nowrap;
    }

    .user-profile-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      border-color: rgba(255, 255, 255, 0.4);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .layout-navbar.navbar-compact .user-profile-btn {
      padding: 6px 10px;
      gap: 6px;
    }

    .avatar-badge {
      position: relative;
      display: inline-block;
      min-width: 40px;
      flex-shrink: 0;
    }

    .avatar-badge img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
    }

    .avatar-initial {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 16px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .layout-navbar.navbar-compact .avatar-badge img,
    .layout-navbar.navbar-compact .avatar-initial {
      width: 32px;
      height: 32px;
      font-size: 14px;
    }

    .avatar-badge .online-indicator {
      position: absolute;
      bottom: 0;
      right: 0;
      width: 12px;
      height: 12px;
      background: #28a745;
      border: 2px solid white;
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
      }
      50% {
        box-shadow: 0 0 0 6px rgba(40, 167, 69, 0);
      }
    }

    .dropdown-menu-enhanced {
      border: none;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
      animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      min-width: 320px;
      max-height: 80vh;
      overflow-y: auto;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .dropdown-header-user {
      padding: 16px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 12px 12px 0 0;
      color: white;
      transition: all 0.3s ease;
    }

    .user-info-display {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-details {
      flex: 1;
      min-width: 0;
    }

    .user-details .username {
      font-weight: 600;
      font-size: 14px;
      margin: 0;
      word-break: break-word;
    }

    .user-details .user-role {
      font-size: 12px;
      opacity: 0.9;
      margin: 4px 0 0 0;
      word-break: break-word;
    }

    .user-details .user-email {
      font-size: 11px;
      opacity: 0.8;
      margin: 2px 0 0 0;
      word-break: break-word;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .dropdown-item-enhanced {
      padding: 12px 16px;
      border-radius: 6px;
      margin: 0 8px;
      transition: all 0.2s ease;
      color: #495057;
      border: none;
      white-space: nowrap;
    }

    .dropdown-item-enhanced:hover {
      background: #f0f2f5;
      color: #667eea;
      transform: translateX(4px);
    }

    .dropdown-item-enhanced i {
      width: 20px;
      text-align: center;
      color: #667eea;
      margin-right: 8px;
    }

    .dropdown-divider-enhanced {
      margin: 8px 0;
      background-color: #e9ecef;
    }

    .dropdown-item-danger {
      color: #dc3545 !important;
    }

    .dropdown-item-danger:hover {
      background: #ffe5e5 !important;
      color: #dc3545 !important;
    }

    .dropdown-item-danger i {
      color: #dc3545 !important;
    }

    .greeting-text {
      font-size: 13px;
      opacity: 0.95;
      font-weight: 500;
      transition: all 0.3s ease;
      white-space: nowrap;
      color: white;
    }

    .layout-navbar.navbar-compact .greeting-text {
      font-size: 0;
      width: 0;
      overflow: hidden;
      opacity: 0;
    }

    .status-badge {
      display: inline-block;
      padding: 3px 8px;
      background: rgba(40, 167, 69, 0.2);
      color: #28a745;
      border-radius: 20px;
      font-size: 10px;
      font-weight: 600;
      margin-top: 4px;
    }

    .navbar-enhance .navbar-nav-right {
      gap: 20px;
      transition: all 0.3s ease;
    }

    .layout-navbar.navbar-compact .navbar-nav-right {
      gap: 10px;
    }

    .user-info-text {
      display: flex;
      flex-direction: column;
      gap: 2px;
      text-align: left;
      transition: all 0.3s ease;
    }

    .layout-navbar.navbar-compact .user-info-text {
      display: none;
    }

    .user-info-text span:first-child {
      font-size: 13px;
      font-weight: 600;
      transition: font-size 0.3s ease;
      color: white;
    }

    .layout-navbar.navbar-compact .user-info-text span:first-child {
      font-size: 0;
    }

    .user-info-text span:last-child {
      font-size: 11px;
      opacity: 0.9;
      color: white;
    }

    @media (max-width: 992px) {
      :root {
        --navbar-height: 65px;
        --navbar-height-compact: 55px;
      }

      .greeting-text {
        display: none !important;
      }

      .dropdown-menu-enhanced {
        min-width: 280px;
      }
    }

    @media (max-width: 768px) {
      :root {
        --navbar-height: 60px;
        --navbar-height-compact: 50px;
      }

      .user-profile-btn {
        padding: 6px 10px;
        gap: 6px;
      }

      .avatar-badge img,
      .avatar-initial {
        width: 36px;
        height: 36px;
      }

      .user-info-text {
        display: none !important;
      }

      .dropdown-menu-enhanced {
        min-width: 260px;
      }
    }

    @media (max-width: 576px) {
      :root {
        --navbar-height: 56px;
        --navbar-height-compact: 48px;
      }

      .user-profile-btn {
        padding: 4px 8px;
      }

      .avatar-badge img,
      .avatar-initial {
        width: 32px;
        height: 32px;
        font-size: 12px;
      }

      .navbar-nav-right {
        gap: 8px !important;
      }

      .dropdown-menu-enhanced {
        min-width: 240px;
        position: fixed !important;
        left: 10px !important;
        right: 10px !important;
        width: auto !important;
      }

      .bx-chevron-down {
        margin-left: 4px !important;
      }
    }

    .dropdown-menu-enhanced::-webkit-scrollbar {
      width: 6px;
    }

    .dropdown-menu-enhanced::-webkit-scrollbar-track {
      background: transparent;
    }

    .dropdown-menu-enhanced::-webkit-scrollbar-thumb {
      background: #ccc;
      border-radius: 3px;
    }

    .dropdown-menu-enhanced::-webkit-scrollbar-thumb:hover {
      background: #999;
    }
  </style>

  <!-- Mobile menu toggle -->
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center ms-auto" id="navbar-collapse">
    <ul class="navbar-nav flex-row align-items-center ms-auto">
      <!-- Notifications Bell -->
      <?php
        $notif_count = 0;
        if ($user_id) {
            $qnc = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
            if ($qnc) {
                $qnc->bind_param('i', $user_id);
                $qnc->execute();
                $rc = $qnc->get_result()->fetch_assoc();
                $notif_count = intval($rc['cnt'] ?? 0);
                $qnc->close();
            }
        }
      ?>
      <li class="nav-item dropdown me-3">
        <a class="nav-link dropdown-toggle" href="#" id="adminNotifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bx bx-bell" style="font-size:20px;color:white"></i>
          <?php if ($notif_count > 0): ?>
            <span id="admin-notif-count-badge" class="badge rounded-pill bg-danger" style="position:relative;top:-10px;left:-8px;font-size:11px"><?= $notif_count ?></span>
          <?php else: ?>
            <span id="admin-notif-count-badge" class="d-none"></span>
          <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:320px;">
          <?php
            $notifList = [];
            if ($user_id) {
                $qn = $conn->prepare('SELECT id, actor_user_id, type, related_id, payload, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
                if ($qn) {
                    $qn->bind_param('i', $user_id);
                    $qn->execute();
                    $notifList = $qn->get_result()->fetch_all(MYSQLI_ASSOC);
                    $qn->close();
                }
            }
          ?>
          <li><div class="d-flex justify-content-between align-items-center px-2"><strong>Notifications</strong><a href="#" id="adminMarkAllRead" class="small">Mark all read</a></div></li>
          <li><div class="dropdown-divider"></div></li>
          <?php if (empty($notifList)): ?>
            <li class="px-2">No notifications</li>
          <?php else: ?>
            <?php foreach ($notifList as $n):
                $pay = json_decode($n['payload'] ?? '{}', true);
                $cls = $n['is_read'] ? '' : 'fw-bold';
            ?>
              <li class="px-2 py-2 notif-item <?= $cls ?>" data-id="<?= $n['id'] ?>">
                <div><small class="text-muted"><?= htmlspecialchars($n['type']) ?> • <?= htmlspecialchars($n['created_at']) ?></small></div>
                <div><?= htmlspecialchars(is_array($pay) ? json_encode($pay) : ($n['payload'] ?? '')) ?></div>
                <div class="mt-1"><a href="#" class="admin-mark-read-link small" data-id="<?= $n['id'] ?>">Mark read</a></div>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
          <li><div class="dropdown-divider"></div></li>
          <li class="px-2"><a href="../table/notifications.php">View all</a></li>
        </ul>
      </li>
      <!-- Greeting with Icon -->
      <li class="nav-item me-3">
        <span class="greeting-text">
          <i class="bx <?= $greetingIcon ?>"></i> <?= $greeting ?>, <?= htmlspecialchars($username) ?>!
        </span>
      </li>

      <!-- User Dropdown -->
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="user-profile-btn dropdown-toggle" href="javascript:void(0);" data-bs-toggle="dropdown" style="text-decoration: none; color: inherit;">
          <div class="avatar-badge">
            <?php $avatarFileLocal = $avatarFile; $usernameLocal = $username; $avatarFile = $avatarFileLocal; $username = $usernameLocal; $size='sm'; $status='online'; include __DIR__ . '/../includes/avatar.php'; ?>
            <span class="online-indicator"></span>
          </div>
          <div class="user-info-text">
            <span><?= htmlspecialchars($username) ?></span>
            <span><?= ucfirst(htmlspecialchars($role)) ?></span>
          </div>
          <i class="bx bx-chevron-down" style="transition: transform 0.3s; color: white;"></i>
        </a>

        <!-- Dropdown Menu -->
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-enhanced">
          <!-- User Header Section -->
          <li class="dropdown-header-user">
            <div class="user-info-display">
              <div class="avatar-badge">
                <?php $avatarFileLocal = $avatarFile; $usernameLocal = $username; $avatarFile = $avatarFileLocal; $username = $usernameLocal; $size='md'; $status='online'; include __DIR__ . '/../includes/avatar.php'; ?>
                <span class="online-indicator"></span>
              </div>
              <div class="user-details">
                <p class="username"><?= htmlspecialchars($username) ?></p>
                <p class="user-role"><?= ucfirst(htmlspecialchars($role)) ?></p>
                <p class="user-email"><?= htmlspecialchars($user_email) ?></p>
                <div class="status-badge">● Online</div>
              </div>
            </div>
          </li>

          <li>
            <div class="dropdown-divider-enhanced"></div>
          </li>

          <!-- Profile Option -->
          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="account_settings.php">
              <i class="bx bx-user"></i>
              <span class="align-middle">My Profile</span>
            </a>
          </li>

          <!-- Settings Option -->
          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="settings.php">
              <i class="bx bx-cog"></i>
              <span class="align-middle">Settings</span>
            </a>
          </li>

          <!-- Activity Option -->
          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="activity.php">
              <i class="bx bx-history"></i>
              <span class="align-middle">Activity Log</span>
            </a>
          </li>

          <!-- Help Option -->
          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="help.php">
              <i class="bx bx-help-circle"></i>
              <span class="align-middle">Help & Support</span>
            </a>
          </li>

          <li>
            <div class="dropdown-divider-enhanced"></div>
          </li>

          <!-- Logout Option -->
          <li>
            <a class="dropdown-item dropdown-item-enhanced dropdown-item-danger" href="../login/logout.php">
              <i class="bx bx-power-off"></i>
              <span class="align-middle">Log Out</span>
            </a>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<!-- Safe back/forward cache refresh (NO flicker) -->
<script>
  window.addEventListener("pageshow", function(event) {
    if (event.persisted) {
      window.location.reload();
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.getElementById('layout-navbar');
    const chevronIcon = document.querySelector('.bx-chevron-down');
    let lastScrollTop = 0;

    // Add navbar class on load
    navbar.classList.add('navbar-enhance');

    // Animate chevron on dropdown toggle
    document.querySelector('.dropdown-toggle').addEventListener('show.bs.dropdown', function() {
      chevronIcon.style.transform = 'rotate(180deg)';
    });

    // Reset chevron on dropdown hide
    document.querySelector('.dropdown-toggle').addEventListener('hide.bs.dropdown', function() {
      chevronIcon.style.transform = 'rotate(0deg)';
    });

    // Auto-resize navbar on scroll
    window.addEventListener('scroll', function() {
      let currentScroll = window.pageYOffset || document.documentElement.scrollTop;

      // Add scrolled class for shadow effect
      if (currentScroll > 10) {
        navbar.classList.add('navbar-scrolled');
      } else {
        navbar.classList.remove('navbar-scrolled');
      }

      // Compact navbar when scrolling down
      if (currentScroll > lastScrollTop && currentScroll > 100) {
        navbar.classList.add('navbar-compact');
      } else {
        navbar.classList.remove('navbar-compact');
      }

      lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    }, { passive: true });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        if (window.innerWidth <= 576) {
          navbar.style.paddingLeft = '8px';
          navbar.style.paddingRight = '8px';
        } else if (window.innerWidth <= 768) {
          navbar.style.paddingLeft = '12px';
          navbar.style.paddingRight = '12px';
        } else {
          navbar.style.paddingLeft = '16px';
          navbar.style.paddingRight = '16px';
        }
      }, 250);

      // Admin notifications AJAX handlers
      document.querySelectorAll('.admin-mark-read-link').forEach(function(el){
        el.addEventListener('click', function(e){
          e.preventDefault();
          var id = this.dataset.id;
          fetch('/inventory_cao_v2/table/notifications_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=mark_read&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>')
          }).then(r=>r.json()).then(function(res){
            if (res.success) {
              var badge = document.getElementById('admin-notif-count-badge');
              if (badge) {
                badge.textContent = res.unread || '';
                if (res.unread == 0) badge.classList.add('d-none');
              }
              var el = document.querySelector('.notif-item[data-id="'+id+'"]'); if (el) el.classList.remove('fw-bold');
            }
          }).catch(()=>{});
        });
      });

      document.getElementById('adminMarkAllRead')?.addEventListener('click', function(e){
        e.preventDefault();
        fetch('/inventory_cao_v2/table/notifications_api.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: 'action=mark_all&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>')
        }).then(r=>r.json()).then(function(res){
          if (res.success) {
            var badge = document.getElementById('admin-notif-count-badge'); if (badge) { badge.textContent = ''; badge.classList.add('d-none'); }
            document.querySelectorAll('.notif-item').forEach(function(x){ x.classList.remove('fw-bold'); });
          }
        }).catch(()=>{});
      });
    });

    window.dispatchEvent(new Event('resize'));
  });
</script>
<script>
  // Admin navbar: polling + optional socket.io realtime
  (function(){
    const notifAnchor = document.getElementById('adminNotifDropdown');
    if (!notifAnchor) return;
    const badge = document.getElementById('admin-notif-count-badge');
    const dropdown = notifAnchor.nextElementSibling;
    let currentTopId = document.querySelector('.notif-item')?.dataset.id || null;

    function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function renderNotifItem(n){
      const li = document.createElement('li');
      li.className = 'px-2 py-2 notif-item fw-bold';
      li.dataset.id = n.id;
      let payloadText = '';
      try { payloadText = typeof n.payload === 'string' ? n.payload : JSON.stringify(n.payload); } catch(e){ payloadText = n.payload || ''; }
      li.innerHTML = '<div><small class="text-muted">'+(n.type?escapeHtml(n.type):'')+' • '+(n.created_at||'')+'</small></div>'+
                     '<div>'+escapeHtml(payloadText)+'</div>'+
                     '<div class="mt-1"><a href="#" class="admin-mark-read-link small" data-id="'+n.id+'">Mark read</a></div>';
      return li;
    }

    function poll(){
      fetch('/inventory_cao_v2/table/notifications_api.php?action=poll').then(r=>r.json()).then(function(res){
        if (!res || !res.success) return;
        const unread = parseInt(res.unread || 0,10);
        if (badge) {
          if (unread > 0) { badge.textContent = unread; badge.classList.remove('d-none'); }
          else { badge.textContent = ''; badge.classList.add('d-none'); }
        }
        const items = res.items || [];
        if (items.length && String(items[0].id) !== String(currentTopId)) {
          items.reverse().forEach(function(it){
            if (!dropdown.querySelector('.notif-item[data-id="'+it.id+'"]')) {
              const newLi = renderNotifItem(it);
              const refs = dropdown.querySelectorAll('li');
              if (refs.length >= 3) dropdown.insertBefore(newLi, refs[2]); else dropdown.appendChild(newLi);
            }
          });
          currentTopId = items[0].id;
          // bind handlers
          dropdown.querySelectorAll('.admin-mark-read-link').forEach(function(el){ if (el.dataset.bound) return; el.dataset.bound = 1; el.addEventListener('click', function(e){ e.preventDefault(); var id = this.dataset.id; fetch('/inventory_cao_v2/table/notifications_api.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=mark_read&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>') }).then(r=>r.json()).then(function(res){ if (res.success) { if (badge) { badge.textContent = res.unread || ''; if (res.unread == 0) badge.classList.add('d-none'); } var el2 = dropdown.querySelector('.notif-item[data-id="'+id+'"]'); if (el2) el2.classList.remove('fw-bold'); } }).catch(()=>{}); }); });
        }
      }).catch(()=>{});
    }

    poll();
    setInterval(poll, 10000);

    // socket.io realtime
    (function(){
      var userId = <?= intval($user_id ?? 0) ?>;
      if (!userId) return;
      if (typeof io === 'undefined') {
        var s = document.createElement('script'); s.src = 'https://cdn.socket.io/4.7.1/socket.io.min.js'; s.async = true; s.onload = initSocket; document.head.appendChild(s);
      } else initSocket();
      function initSocket(){
        try {
          var socket = io('http://localhost:3000', { transports: ['websocket'], reconnectionAttempts: 5 });
          socket.on('connect', function(){ socket.emit('join', { room: 'user:' + userId }); });
          socket.on('notification', function(data){ try { var unread = parseInt((badge && badge.textContent) || 0, 10) || 0; unread = unread + 1; if (badge) { badge.textContent = unread; badge.classList.remove('d-none'); } var payload = data.payload || {}; var li = renderNotifItem({ id: data.related_id || ('n' + Date.now()), type: payload.type || '', payload: payload.payload || payload, created_at: payload.created_at || '' }); var refs = dropdown.querySelectorAll('li'); if (refs.length >= 3) dropdown.insertBefore(li, refs[2]); else dropdown.appendChild(li); li.querySelector('.admin-mark-read-link').addEventListener('click', function(e){ e.preventDefault(); var id = this.dataset.id; fetch('/inventory_cao_v2/table/notifications_api.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=mark_read&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>') }).then(r=>r.json()).then(function(res){ if (res.success) { if (badge) { badge.textContent = res.unread || ''; if (res.unread == 0) badge.classList.add('d-none'); } if (li) li.classList.remove('fw-bold'); } }).catch(()=>{}); }); } catch(e){} });
        } catch(e){}
      }
    })();
  })();
</script>
<!-- /NAVBAR -->