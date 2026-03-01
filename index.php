<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$page_title = 'ОПиПАСР — Пожаротушение, АСР, БАС';
$page_wrap_container = false;

require_once __DIR__ . '/config/header.php';

$index_html = @file_get_contents(__DIR__ . '/index.html');
if ($index_html !== false && preg_match('~<main\\b[^>]*>(.*?)</main>~si', $index_html, $m)) {
  echo $m[1];
} else {
  ?>
  <div class="panel">
    <div class="h1">ОПиПАСР</div>
    <p class="p">Не удалось загрузить содержимое главной страницы.</p>
  </div>
  <?php
}

require_once __DIR__ . '/config/footer.php';

