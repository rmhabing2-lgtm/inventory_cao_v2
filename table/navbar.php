<!-- <?php
if (session_status() === PHP_SESSION_NONE) {
  require_once __DIR__ . '/../includes/session.php';
}

require_once "../login/config.php"; // DB connection

// Session values
$user_id  = $_SESSION['id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest';
$role     = $_SESSION['role'] ?? 'STAFF';

// Avatar paths and fetch
$avatar = '';
$avatarWebPath = '/inventory_cao_v2/uploads/avatars/'; // Matches your folder location
$avatarDiskPath = rtrim($_SERVER['DOCUMENT_ROOT'], "\\/") . $avatarWebPath;

// Fetch avatar and additional user info
if ($user_id) {
  $stmt = $conn->prepare("SELECT avatar, email FROM user WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {
    $maybe = trim($row['avatar'] ?? '');
    // Verify file exists on disk
    if ($maybe && file_exists($avatarDiskPath . $maybe)) {
      $avatar = $maybe;
    }
    $user_email = $row['email'] ?? 'N/A';
  }
}

// Escape output
$avatarSafe   = htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8');
$usernameSafe = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
$roleSafe     = htmlspecialchars(ucfirst(strtolower($role)), ENT_QUOTES, 'UTF-8');
$emailSafe    = htmlspecialchars($user_email ?? 'N/A', ENT_QUOTES, 'UTF-8');

// Default avatar logic: Use uploaded file if it exists, otherwise use a default placeholder
$displayAvatar = !empty($avatarSafe) ? ($avatarWebPath . $avatarSafe) : '../assets/img/avatars/1.png';

// Get current time for greeting
$hour = date('H');
if ($hour < 12) {
  $greeting = 'Good Morning'; $greetingIcon = 'bx-sun';
} elseif ($hour < 18) {
  $greeting = 'Good Afternoon'; $greetingIcon = 'bx-cloud-sun';
} else {
  $greeting = 'Good Evening'; $greetingIcon = 'bx-moon';
}
?>

<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
  <style>
    .navbar-enhance {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .layout-navbar .bx { color: #fff !important; }
    .layout-navbar .greeting-text { color: #fff !important; font-size: 13px; font-weight: 500; }
    
    .user-profile-btn {
      display: flex; align-items: center; gap: 10px; padding: 8px 12px;
      border-radius: 50px; background: rgba(255, 255, 255, 0.1);
      border: 2px solid rgba(255, 255, 255, 0.2); color: white; text-decoration: none;
    }

    .avatar-badge { position: relative; display: inline-block; }
    .avatar-badge img { border: 2px solid rgba(255,255,255,0.5); object-fit: cover; }
    
    .online-indicator {
      position: absolute; bottom: 0; right: 0; width: 10px; height: 10px;
      background: #28a745; border: 2px solid white; border-radius: 50%;
    }

    .dropdown-menu-enhanced {
      border: none; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15); min-width: 280px;
    }
    .dropdown-header-user {
      padding: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 12px 12px 0 0; color: white;
    }
    .dropdown-item-enhanced { padding: 10px 16px; transition: all 0.2s; }
    .dropdown-item-enhanced:hover { transform: translateX(5px); background: #f8f9fa; }
  </style>

  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center ms-auto" id="navbar-collapse">
    <ul class="navbar-nav flex-row align-items-center ms-auto">
      <li class="nav-item me-3">
        <span class="greeting-text">
          <i class="bx <?= $greetingIcon ?>"></i> <?= $greeting ?>, <?= $usernameSafe ?>!
        </span>
      </li>

      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="user-profile-btn dropdown-toggle" href="javascript:void(0);" data-bs-toggle="dropdown">
          <div class="avatar-badge">
            <img src="<?= $displayAvatar ?>" alt="User Avatar" class="rounded-circle" style="width: 32px; height: 32px;">
            <span class="online-indicator"></span>
          </div>
          <div class="d-none d-sm-flex flex-column text-start">
            <span style="font-size: 12px; font-weight: 600; line-height: 1.2;"><?= $usernameSafe ?></span>
            <span style="font-size: 10px; opacity: 0.8;"><?= $roleSafe ?></span>
          </div>
        </a>

        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-enhanced">
          <li class="dropdown-header-user">
            <div class="d-flex align-items-center gap-3">
              <div class="avatar-badge">
                <img src="<?= $displayAvatar ?>" alt="User Avatar" class="rounded-circle" style="width: 45px; height: 45px;">
              </div>
              <div class="user-details">
                <p class="mb-0 fw-bold"><?= $usernameSafe ?></p>
                <p class="mb-0 small opacity-75"><?= $emailSafe ?></p>
                <span class="badge bg-success mt-1" style="font-size: 9px;">● Online</span>
              </div>
            </div>
          </li>

          <li><hr class="dropdown-divider"></li>

          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="../account_management/account_settings.php">
              <i class="bx bx-user me-2 text-primary"></i><span>My Profile</span>
            </a>
          </li>

          <?php if (strtoupper($role) === 'ADMIN'): ?>
          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="system_monitor.php">
              <i class="bx bx-shield-quarter me-2 text-info"></i><span class="fw-bold">System Activity</span>
            </a>
          </li>
          <?php endif; ?>

          <li>
            <a class="dropdown-item dropdown-item-enhanced" href="settings.php">
              <i class="bx bx-cog me-2 text-secondary"></i><span>Settings</span>
            </a>
          </li>

          <li><hr class="dropdown-divider"></li>

          <li>
            <a class="dropdown-item dropdown-item-enhanced text-danger" href="../login/logout.php">
              <i class="bx bx-power-off me-2"></i><span>Log Out</span>
            </a>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.getElementById('layout-navbar');
    navbar.classList.add('navbar-enhance');
  });
</script> -->
<?php
// Centralized sidebar include for table pages
require_once __DIR__ . '/../includes/navbar.php';
?>
