<?php
/**
 * encoder_guard.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Drop this require_once AFTER session.php on every non-encoder page.
 * If the logged-in user is ENCODER, they get redirected to encoder_module.php
 * so they cannot access any other part of the system.
 *
 * Usage (add to every page that should be off-limits to ENCODERs):
 *
 *   require_once __DIR__ . '/includes/session.php';
 *   require_once __DIR__ . '/includes/encoder_guard.php'; // ← add this
 *   require_login();
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['role']) && strtoupper(trim($_SESSION['role'])) === 'ENCODER') {
    $enc_url = function_exists('site_url')
        ? site_url('encoder_module.php')
        : '/inventory_cao_v2/encoder_module.php';
    header('Location: ' . $enc_url);
    exit;
}