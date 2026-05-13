<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/RateLimiter.php';

class AuthController {

    public static function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Invalid session token.';
            return;
        }

        if (!RateLimiter::check($_SERVER['REMOTE_ADDR'])) {
            $_SESSION['error'] = 'Too many login attempts. Try again later.';
            return;
        }

        $login = trim($_POST['login']);
        $password = $_POST['password'];

        $user = User::findByLogin($login);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['error'] = 'Invalid credentials.';
            return;
        }

        if ($user['status'] === 'banned') {
            $_SESSION['error'] = 'Your account has been banned.';
            return;
        }

        // success
        session_regenerate_id(true);

        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE user SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();

        header("Location: /inventory_cao_v2/public/index.php");
        exit;
    }
}
