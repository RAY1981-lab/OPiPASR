<?php declare(strict_types=1);
function is_logged_in(): bool { return !empty($_SESSION['user']); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if(!is_logged_in()) redirect('/login/'); }
function require_role(string $role): void { require_login(); $u=current_user(); if(!$u || strtoupper((string)$u['role'])!==strtoupper($role)){ http_response_code(403); exit('Forbidden'); } }
function require_any_role(array $roles): void {
  require_login();
  $u = current_user();
  $r = strtoupper((string)($u['role'] ?? ''));
  $allowed = array_map(static fn($v) => strtoupper((string)$v), $roles);
  if ($r === '' || !in_array($r, $allowed, true)) {
    http_response_code(403);
    exit('Forbidden');
  }
}
function role_label(string $role): string {
  $r = strtoupper(trim($role));
  if ($r === 'ADMIN') return 'Админ';
  if ($r === 'RTP') return 'РТП/РЛЧС';
  if ($r === 'CUKS') return 'ЦУКС';
  if ($r === 'GUEST') return 'Гость';
  return $r !== '' ? $r : '—';
}
function role_badge(string $role): string {
  $r = strtoupper($role);
  if ($r==='ADMIN') return '<span class="badge warn">АДМИН</span>';
  if ($r==='RTP') return '<span class="badge warn">РТП/РЛЧС</span>';
  if ($r==='CUKS') return '<span class="badge">ЦУКС</span>';
  return '<span class="badge">ГОСТЬ</span>';
}
function status_badge(string $status): string {
  $s=strtoupper($status);
  if($s==='ACTIVE') return '<span class="badge ok">ACTIVE</span>';
  if($s==='PENDING') return '<span class="badge warn">PENDING</span>';
  if($s==='REJECTED') return '<span class="badge bad">REJECTED</span>';
  return '<span class="badge bad">DISABLED</span>';
}
function default_permissions_for_role(string $role): array {
  $r = strtoupper(trim($role));
  $base = [
    'normative' => ['view' => true,  'edit' => false],
    'methods'   => ['view' => true,  'edit' => false],
    'about'     => ['view' => true,  'edit' => false],
    'contacts'  => ['view' => true,  'edit' => false],
    'cabinet'   => ['view' => false, 'edit' => false],
    'admin'     => ['view' => false, 'edit' => false],
  ];

  if ($r === 'RTP' || $r === 'CUKS') {
    $base['cabinet']['view'] = true;
  }
  if ($r === 'ADMIN') {
    foreach ($base as $k => $_) {
      $base[$k]['view'] = true;
      $base[$k]['edit'] = true;
    }
  }
  return $base;
}
function permission_overrides_for_user(int $user_id): array {
  static $cache = [];
  if (isset($cache[$user_id])) return $cache[$user_id];

  $rows = [];
  try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT perm_key, can_view, can_edit FROM user_permissions WHERE user_id = :id");
    $stmt->execute([':id' => $user_id]);
    $rows = $stmt->fetchAll();
  } catch (Throwable $e) {
    $cache[$user_id] = [];
    return $cache[$user_id];
  }

  $map = [];
  foreach ($rows as $r) {
    $key = strtolower((string)($r['perm_key'] ?? ''));
    if ($key === '') continue;
    $map[$key] = [
      'view' => (bool)($r['can_view'] ?? false),
      'edit' => (bool)($r['can_edit'] ?? false),
    ];
  }

  $cache[$user_id] = $map;
  return $cache[$user_id];
}
function can_permission(string $perm, string $action = 'view', ?array $user = null): bool {
  $u = $user ?? current_user();
  if (!$u) return false;

  $perm = strtolower(trim($perm));
  $action = strtolower(trim($action)) === 'edit' ? 'edit' : 'view';

  $role = strtoupper((string)($u['role'] ?? 'GUEST'));
  $defaults = default_permissions_for_role($role);
  $value = (bool)($defaults[$perm][$action] ?? false);

  $uid = (int)($u['id'] ?? 0);
  if ($uid > 0) {
    $ov = permission_overrides_for_user($uid);
    if (isset($ov[$perm][$action])) {
      $value = (bool)$ov[$perm][$action];
    }
  }
  return $value;
}
function require_permission(string $perm, string $action = 'view'): void {
  require_login();
  if (!can_permission($perm, $action)) {
    http_response_code(403);
    exit('Forbidden');
  }
}
function login_user(array $user): void { session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$user['id'],'username'=>(string)$user['username'],'role'=>(string)$user['role'],'status'=>(string)$user['status']]; }
function logout_user(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',
time()-42000,$p['path'],$p['domain'],(bool)$p['secure'],(bool)$p['httponly']); } session_destroy(); }
?>
