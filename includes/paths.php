<?php
// Central fallback helper functions for the app.
// These are defined only if not already present elsewhere in the project.

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..'));
}

if (!function_exists('base_url')) {
    function base_url($path = '') {
        if (function_exists('site_url')) return site_url($path);
        $base = '/inventory_cao_v2';
        $p = trim((string)$path, '/');
        return rtrim($base, '/') . ($p === '' ? '/' : '/' . $p);
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path = '') {
        $p = trim((string)$path, '/');
        return rtrim(base_url('assets'), '/') . ($p === '' ? '/' : '/' . $p);
    }
}

if (!function_exists('app_url')) {
    function app_url($path = '') {
        return base_url($path);
    }
}

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($s) {
        echo h($s);
    }
}

if (!function_exists('old')) {
    function old($key, $default = '') {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key])) return $_GET[$key];
        return $default;
    }
}

if (!function_exists('flash')) {
    function flash($key, $value = null) {
        if ($value === null) {
            if (!empty($_SESSION['flash'][$key])) {
                $val = $_SESSION['flash'][$key];
                unset($_SESSION['flash'][$key]);
                return $val;
            }
            return null;
        }
        if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];
        $_SESSION['flash'][$key] = $value;
        return true;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return $_SESSION['csrf_token'] ?? '';
    }
}

if (!function_exists('validate_csrf')) {
    function validate_csrf($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

?>