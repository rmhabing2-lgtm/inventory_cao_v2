<?php
require_once __DIR__ . '/../includes/session.php';
include 'config.php';

$error = ''; // Initialize error variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']); // username OR email
    $password = $_POST['password'];

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare(
        "SELECT * FROM user WHERE username = ? OR email = ? LIMIT 1"
    );
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['status'] = $user['status'];
            $_SESSION['avatar'] = !empty($row['avatar']) ? $row['avatar'] : 'default.png';


            // Update last login
            $stmtUpdate = $conn->prepare("UPDATE user SET last_login = NOW() WHERE id = ?");
            $stmtUpdate->bind_param("i", $user['id']);
            $stmtUpdate->execute();

            header("Location: ../index.php");
            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
    } else {
        $error = "Invalid username/email or password.";
    }
}
?>

<!-- HTML Login Form -->
<!DOCTYPE html>
<html
    lang="en"
    class="light-style customizer-hide"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Inventory Cao - Login</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/pages/page-auth.css" />

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>
    <!-- Config -->
    <script src="../assets/js/config.js"></script>
</head>

<body>
    <!-- Content -->
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <!-- Login Card -->
                <div class="card">
                    <div class="card-body">
                        <!-- Logo -->
                        <div class="app-brand justify-content-center mb-4">
                            <a href="index.html" class="app-brand-link gap-2">
                                <span class="app-brand-logo demo">
                                    <!-- SVG Logo here -->
                                    <!-- (Your existing SVG code) -->
                                </span>
                                <span class="app-brand-text demo text-body fw-bolder">Sneat</span>
                            </a>
                        </div>
                        <h4 class="mb-2">Welcome to Cao! 👋</h4>
                        <p class="mb-4">Please sign-in to your account and start the adventure</p>

                        <form class="mb-3" action="login.php" method="POST" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Email or Username</label>
                                <input type="text" class="form-control" name="login" required />
                            </div>
                            <div class="mb-3 form-password-toggle">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required />
                            </div>
                            <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
                        </form>

                        <?php if (!empty($error)):
                            $__login_err = htmlspecialchars($error); ?>
                            <div id="loginErrorToast" class="bs-toast toast fade bg-danger" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000" style="position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:1080;">
                                <div class="toast-header bg-transparent">
                                    <i class="bx bx-error me-2"></i>
                                    <div class="me-auto fw-semibold">Error</div>
                                    <small class="text-muted">now</small>
                                    <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                                <div class="toast-body"><?= $__login_err ?></div>
                            </div>
                            <script>document.addEventListener('DOMContentLoaded',function(){var e=document.getElementById('loginErrorToast');if(e&&typeof bootstrap!=='undefined'&&bootstrap.Toast){new bootstrap.Toast(e,{delay:5000}).show();}else if(e){e.classList.add('show');setTimeout(function(){e.classList.remove('show');},5000);}});</script>
                        <?php endif; ?>

                        <p class="text-center">
                            <span>New on our platform?</span>
                            <a href="register.php"><span>Create an account</span></a>
                        </p>
                    </div>
                </div>
                <!-- End Login Card -->
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>