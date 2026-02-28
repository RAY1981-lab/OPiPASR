<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$page_title = 'Литература — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';
?>

<div class="panel">
  <div class="h1">Литература</div>
  <p class="p">
    Раздел подключён. Дальше сюда можно добавить:
    карточки источников, тематические подборки, поиск, импорт BibTeX/ГОСТ и т.п.
  </p>

  <div class="alert">
    <strong>Статус:</strong> OK
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
