<?php declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/ingest_secret.php'; // <-- ВАЖНО: СЕКРЕТЫ СНАЧАЛА
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path === '/index.php') $path = '/';

$public_exact = [
  '/',
  '/login/',
  '/register/',
  '/contacts/',
  '/logout/',
  '/admin/login.php',
  '/admin/bootstrap_admin.php',
];
$public_prefixes = [
  '/assets/',
  '/api/',
];

if (!is_logged_in()) {
  $is_public = in_array($path, $public_exact, true);
  if (!$is_public) {
    foreach ($public_prefixes as $pfx) {
      if (strncmp($path, $pfx, strlen($pfx)) === 0) {
        $is_public = true;
        break;
      }
    }
  }

  if (!$is_public) {
    redirect('/');
  }
}
