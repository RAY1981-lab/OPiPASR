<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

// Public endpoint, protected by key. Use once and then delete/disable.
$key = (string)($_GET['key'] ?? '');
if (!defined('OPIPASR_INGEST_KEY') || $key === '' || !hash_equals((string)OPIPASR_INGEST_KEY, $key)) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Forbidden\n");
}

$force = (string)($_GET['force'] ?? '') === '1';
$reset = (string)($_GET['reset'] ?? '') === '1';

$pdo = db();

$admin = $pdo->query("SELECT id, username, role, status FROM users WHERE role='ADMIN' LIMIT 1")->fetch();
if ($admin && !$reset) {
  header('Content-Type: text/plain; charset=utf-8');
  exit("OK: ADMIN already exists (username={$admin['username']})\n");
}

$username = 'Admin';
$password = 'Admin-01';
$hash = password_hash($password, PASSWORD_DEFAULT);

$admin_id = $admin ? (int)($admin['id'] ?? 0) : 0;
if ($admin_id > 0 && $reset) {
  $pdo->prepare("UPDATE users SET pass_hash=:p, status='ACTIVE', approved_at=NOW(), approved_by=NULL WHERE id=:id")
      ->execute([':p' => $hash, ':id' => $admin_id]);
  header('Content-Type: text/plain; charset=utf-8');
  exit("OK: ADMIN password reset. Login: {$admin['username']} / {$password}\n");
}

$existing = $pdo->prepare("SELECT id, username, role, status FROM users WHERE username=:u LIMIT 1");
$existing->execute([':u' => $username]);
$row = $existing->fetch();

if ($row && !$force) {
  header('Content-Type: text/plain; charset=utf-8');
  exit(
    "Blocked: user '{$username}' already exists (role={$row['role']}, status={$row['status']}).\n" .
    "To overwrite this user into ADMIN, call with force=1.\n"
  );
}

if ($row && $force) {
  $pdo->prepare("
    UPDATE users
    SET pass_hash=:p, role='ADMIN', status='ACTIVE', approved_at=NOW(), approved_by=NULL
    WHERE id=:id
  ")->execute([':p' => $hash, ':id' => (int)$row['id']]);

  header('Content-Type: text/plain; charset=utf-8');
  exit("OK: existing user '{$username}' promoted to ADMIN. Password set to '{$password}'.\n");
}

$pdo->prepare("
  INSERT INTO users (username, pass_hash, role, status, approved_at, approved_by)
  VALUES (:u, :p, 'ADMIN', 'ACTIVE', NOW(), NULL)
")->execute([':u' => $username, ':p' => $hash]);

header('Content-Type: text/plain; charset=utf-8');
exit("OK: ADMIN created. Login: {$username} / {$password}\n");
