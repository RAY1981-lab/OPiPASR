<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$page_title = 'О системе — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';
?>

<div class="panel">
  <div class="h1">О системе</div>

  <p class="p">
    ОПиПАСР — информационно-аналитическая платформа поддержки решений при пожарах и АСР.
    Публичный контур содержит систематизированные нормативные документы, литературу, методики и модели.
    Закрытый контур (после входа) — телеметрия БАС, индикаторы обстановки, журнал событий и инструменты поддержки РТП.
  </p>

  <h2 style="margin:18px 0 8px;font-size:18px">Назначение</h2>
  <ul class="list">
    <li>быстрый поиск и корректное цитирование НТД;</li>
    <li>поддержка расчётных оценок и сценарного анализа;</li>
    <li>интеграция телеметрии/наблюдений с отображением состояния и предупреждений.</li>
  </ul>

  <h2 style="margin:18px 0 8px;font-size:18px">Структура контуров</h2>
  <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
    <div class="card" style="padding:14px;border:1px solid rgba(255,255,255,.08);border-radius:16px">
      <strong>Публичный контур</strong>
      <div class="help" style="margin-top:6px;opacity:.9">
        Нормативные документы, литература, методики и модели.
      </div>
    </div>
    <div class="card" style="padding:14px;border:1px solid rgba(255,255,255,.08);border-radius:16px">
      <strong>Закрытый контур</strong>
      <div class="help" style="margin-top:6px;opacity:.9">
        Кабинет, телеметрия БАС, индикаторы, журнал, калькуляторы, протоколирование решений.
      </div>
    </div>
  </div>

  <div class="alert" style="margin-top:14px">
    <strong>Статус:</strong> OK
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
