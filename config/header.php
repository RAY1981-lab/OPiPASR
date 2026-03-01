<?php
require_once __DIR__ . '/bootstrap.php';

$role = $_SESSION['user']['role'] ?? null;
$can_cabinet = is_logged_in() && in_array($role, ['ADMIN','OPERATOR','RTP'], true);
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
<body>
<a class="skip-link" href="#content">Перейти к содержимому</a>

<header class="site-header" role="banner">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="На главную">
      <img src="/assets/logo.png" alt="Логотип ОПиПАСР" class="logo" width="28" height="28" />
      <span class="brand-text">
        <span class="brand-title">ОПиПАСР</span>
        <span class="brand-subtitle">НТД • методики • кабинет РТП</span>
      </span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
      <span class="nav-toggle__bar" aria-hidden="true"></span>
      <span class="sr-only">Открыть меню</span>
    </button>

    <nav id="site-nav" class="site-nav" role="navigation" aria-label="Основное меню">
      <a class="nav-link" href="/normative/">Нормативные документы</a>
      <a class="nav-link" href="/methods/">Методики и модели</a>
      <a class="nav-link" href="/about/">О&nbsp;системе</a>
      <a class="nav-link" href="/contacts/">Контакты</a>

      <div class="nav-actions">
        <?php if (is_logged_in()): ?>

          <?php if ($can_cabinet): ?>
            <a class="btn btn-ghost" href="/app/">Кабинет</a>
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

<main id="content" class="main" role="main">
  <?php $page_wrap_container = $page_wrap_container ?? true; ?>
  <?php if ($page_wrap_container): ?>
    <div class="container">
  <?php endif; ?>
