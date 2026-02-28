<?php declare(strict_types=1);




require_once __DIR__ . '/ingest_secret.php'; // <-- ВАЖНО: СЕКРЕТЫ СНАЧАЛА
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
