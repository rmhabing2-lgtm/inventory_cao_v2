<?php
/**
 * CENTRALIZED SESSION & AUTHENTICATION ENGINE
 */
require 'encoder_guard.php'; 


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROOT_DIR = realpath(__DIR__ . '/..');

/**
 * 1. DYNAMIC URL HELPER
 */
if (!function_exists('site_url')) {
    $detected_base = '';
    try {
        $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $root = str_replace('\\', '/', realpath($ROOT_DIR));
        if ($docroot && $root && strpos($root, $docroot) === 0) {
            $detected_base = substr($root, strlen($docroot));
            $detected_base = '/' . trim(str_replace('\\', '/', $detected_base), '/');
        }
    } catch (Exception $e) {
        $detected_base = '';
    }

    if (empty($detected_base)) {
        $detected_base = '/inventory_cao_v2';
    }

    function site_url($path = '') {
        global $detected_base;
        $p = trim((string)$path, '/');
        return rtrim($detected_base, '/') . '/' . $p;
    }
}

/**
 * 2. SECURITY: CSRF PROTECTION
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * 3. PATH CONSTANTS
 */
if (!defined('AVATAR_PATH')) {
    define('AVATAR_PATH', site_url('uploads/avatars/'));
}

/**
 * 4. USER DATA RETRIEVAL (Avatar & Audit Log Identity)
 */
if (file_exists($ROOT_DIR . '/login/config.php')) {
    require_once $ROOT_DIR . '/login/config.php';

    if (isset($_SESSION['id'])) {
        // Fetch Avatar and Full Name if not already in session
        if (!isset($_SESSION['avatar']) || !isset($_SESSION['full_name'])) {
            $stmt = $conn->prepare("SELECT first_name, last_name, avatar FROM user WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $_SESSION['id']);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($user_data = $res->fetch_assoc()) {
                    $_SESSION['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
                    $_SESSION['avatar']    = $user_data['avatar'] ?: 'default.png';
                } else {
                    $_SESSION['full_name'] = 'System';
                    $_SESSION['avatar']    = 'default.png';
                }
                $stmt->close();
            }
        }
    }
}

// Variables for easy access in other files (e.g., for Audit Logging)
$admin_id   = $_SESSION['id'] ?? 0;
$admin_name = $_SESSION['full_name'] ?? 'System';

/**
 * 5. AUTHENTICATION HELPERS
 */
function require_login() {
    $allowed = ['ADMIN', 'STAFF', 'MANAGER'];
    if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), $allowed, true)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, 
                    $params['path'], $params['domain'], 
                    $params['secure'], $params['httponly']
                );
            }
            session_destroy();
        }
        header("Location: " . site_url('login/login.php'));
        exit;
    }
}

function require_role(array $roles) {
    if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), array_map('strtoupper', $roles), true)) {
        header("Location: " . site_url('login/login.php'));
        exit;
    }
}

