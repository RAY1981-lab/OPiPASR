<?php declare(strict_types=1);
function is_logged_in(): bool { return !empty($_SESSION['user']); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if(!is_logged_in()) redirect('/login/'); }
function require_role(string $role): void { require_login(); $u=current_user(); if(!$u || strtoupper((string)$u['role'])!==strtoupper($role)){ http_response_code(403); exit('Forbidden'); } }
function login_user(array $user): void { session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$user['id'],'username'=>(string)$user['username'],'role'=>(string)$user['role'],'status'=>(string)$user['status']]; }
function logout_user(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',
time()-42000,$p['path'],$p['domain'],(bool)$p['secure'],(bool)$p['httponly']); } session_destroy(); }
function role_badge(string $role): string { $r=strtoupper($role); if($r==='ADMIN') return '<span class="badge warn">ADMIN</span>'; if($r==='RTP') return '<span class="badge warn">RTP</span>'; if($r==='OPERATOR') return '<span class="badge">OPERATOR</span>'; return '<span class="badge">VIEWER</span>'; }
function status_badge(string $status): string { $s=strtoupper($status); if($s==='ACTIVE') return '<span class="badge ok">ACTIVE</span>'; if($s==='PENDING') return '<span class="badge warn">PENDING</span>'; return '<span class="badge bad">DISABLED</span>'; }
?>