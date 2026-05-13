<?php
if (session_status() === PHP_SESSION_NONE) {
  require_once __DIR__ . '/session.php';
}

$__app_config = [
  'csrf' => $_SESSION['csrf_token'] ?? '',
  'userId' => intval($_SESSION['id'] ?? 0),
  'baseUrl' => '/inventory_cao_v2',
  'realtimeDisabled' => !empty($_SESSION['realtime_disabled']) ? 1 : 0,
  'role' => strtoupper($_SESSION['role'] ?? 'STAFF'),
  'notifWsHost' => getenv('NOTIF_WS_HOST') ?: 'http://localhost:3000'
];
?>
<script id="app-config" type="application/json">
<?= json_encode($__app_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
