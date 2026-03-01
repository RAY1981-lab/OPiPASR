<?php
require_once __DIR__ . '/bootstrap.php';

$role = $_SESSION['user']['role'] ?? null;
$can_cabinet = is_logged_in() && can_permission('cabinet', 'view');
$can_admin = is_logged_in() && strtoupper((string)$role) === 'ADMIN';
$header_variant = $header_variant ?? (is_logged_in() ? 'private' : 'public');
$body_class = trim((string)($body_class ?? ''));
$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = $path === '/index.php' ? '/' : $path;
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="opipasr.ru — нормативные документы, методики и закрытый кабинет для телеметрии БАС и поддержки принятия решений РТП." />
  <title><?= h($page_title ?? 'ОПиПАСР') ?></title>
  <link rel="icon" href="/assets/logo.png" />
  <link rel="stylesheet" href="/assets/styles.css?v=<?= @filemtime(__DIR__ . '/../assets/styles.css') ?: time() ?>" />
</head>
<body<?= $body_class !== '' ? ' class="' . h($body_class) . '"' : '' ?>>
<a class="skip-link" href="#content">Перейти к содержимому</a>

<header class="site-header" role="banner">
  <div class="container header-inner">
    <div style="display:flex;align-items:center;gap:12px;min-width:0">
      <?php if ($header_variant !== 'public'): ?>
        <button class="sidebar-toggle" type="button" aria-expanded="false" aria-controls="side-nav">
          <span class="sidebar-toggle__bar" aria-hidden="true"></span>
          <span class="sr-only">Открыть навигацию</span>
        </button>
      <?php endif; ?>

      <a class="brand" href="/" aria-label="ОПиПАСР — на главную">
        <img src="/assets/logo.png" alt="Логотип ОПиПАСР" class="logo" />
        <span class="brand-text">
          <span class="brand-title">ОПиПАСР</span>
        </span>
      </a>
    </div>

    <nav id="site-nav" class="site-nav<?= $header_variant === 'public' ? ' site-nav--public' : '' ?>" role="navigation" aria-label="Верхнее меню">
      <?php if ($header_variant === 'public' || can_permission('contacts', 'view')): ?>
        <a class="nav-link" href="/contacts/">Контакты</a>
      <?php endif; ?>

      <div class="nav-actions">
        <?php if (is_logged_in()): ?>
          <?php if ($can_admin): ?>
            <a class="btn btn-ghost" href="/admin/approvals.php">Админ</a>
          <?php endif; ?>
          <a class="btn btn-primary" href="/logout/">Выход</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="/login/">Вход</a>
          <a class="btn btn-primary" href="/register/">Регистрация</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

<?php if ($header_variant !== 'public'): ?>
  <div class="app-shell">
    <aside id="side-nav" class="side-nav" role="navigation" aria-label="Навигация по сайту">
      <div class="side-head">Разделы</div>

      <a class="side-link<?= $path === '/' ? ' is-active' : '' ?>" href="/">Главная</a>

      <?php if (can_permission('normative', 'view')): ?>
        <a class="side-link<?= strncmp($path, '/normative/', 11) === 0 ? ' is-active' : '' ?>" href="/normative/">Нормативные документы</a>
      <?php endif; ?>
      <?php if (can_permission('methods', 'view')): ?>
        <a class="side-link<?= strncmp($path, '/methods/', 9) === 0 ? ' is-active' : '' ?>" href="/methods/">Методики и модели</a>
      <?php endif; ?>
      <?php if (can_permission('about', 'view')): ?>
        <a class="side-link<?= strncmp($path, '/about/', 7) === 0 ? ' is-active' : '' ?>" href="/about/">О системе</a>
      <?php endif; ?>
      <?php if (can_permission('contacts', 'view')): ?>
        <a class="side-link<?= strncmp($path, '/contacts/', 10) === 0 ? ' is-active' : '' ?>" href="/contacts/">Контакты</a>
      <?php endif; ?>

      <?php if (can_permission('cabinet', 'view')): ?>
        <div class="side-divider" role="separator" aria-hidden="true"></div>
        <div class="side-head">Закрытый контур</div>
        <a class="side-link<?= $path === '/app/' || $path === '/app/index.php' ? ' is-active' : '' ?>" href="/app/">Кабинет (обзор)</a>
        <?php if (can_permission('telemetry', 'view')): ?>
          <a class="side-link<?= $path === '/app/telemetry.php' ? ' is-active' : '' ?>" href="/app/telemetry.php">Телеметрия</a>
        <?php endif; ?>
        <?php if (can_permission('incidents', 'view')): ?>
          <a class="side-link<?= $path === '/app/incidents.php' ? ' is-active' : '' ?>" href="/app/incidents.php">Инциденты</a>
        <?php endif; ?>
        <?php if (can_permission('reports', 'view')): ?>
          <a class="side-link<?= $path === '/app/reports.php' ? ' is-active' : '' ?>" href="/app/reports.php">Отчёты</a>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($can_admin): ?>
        <div class="side-divider" role="separator" aria-hidden="true"></div>
        <div class="side-head">Администрирование</div>
        <a class="side-link<?= $path === '/admin/approvals.php' ? ' is-active' : '' ?>" href="/admin/approvals.php">Заявки и пользователи</a>
      <?php endif; ?>
    </aside>

    <main id="content" class="main main--app" role="main">
<?php else: ?>
  <main id="content" class="main" role="main">
<?php endif; ?>
  <?php $page_wrap_container = $page_wrap_container ?? true; ?>
  <?php if ($page_wrap_container): ?>
    <div class="container">
  <?php endif; ?>
