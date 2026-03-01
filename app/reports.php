<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/helpers.php';

require_permission('reports', 'view');

$can_edit = can_permission('reports', 'edit');

$page_title = 'Отчёты — Кабинет';
require_once __DIR__ . '/../config/header.php';
?>

<h1 style="margin:0 0 12px;">Отчёты и сводки</h1>

<div class="note" style="margin-bottom:14px">
  <strong>Черновик структуры.</strong> Здесь будут формироваться оперативные сводки для РТП/РЛЧС и ЦУКС/ЕДДС:
  параметры ИБАС, расчёты, рекомендации, принятые решения и таймлайн.
</div>

<?php if ($can_edit): ?>
  <div class="calc-wrap" style="margin-bottom:16px">
    <div style="font-weight:900;font-size:16px;margin-bottom:10px">Сформировать отчёт (заготовка)</div>
    <form class="calc-form" method="post" onsubmit="return false;">
      <div class="form-grid">
        <div class="field">
          <label>Тип отчёта</label>
          <select>
            <option>Оперативная сводка (15 мин)</option>
            <option>Сводка по миссии ИБАС</option>
            <option>Журнал решений РТП/РЛЧС</option>
            <option>Отчёт по связи/доставке данных</option>
          </select>
        </div>
        <div class="field">
          <label>Период</label>
          <select>
            <option>Последние 15 минут</option>
            <option>Последний час</option>
            <option>С начала инцидента</option>
          </select>
        </div>
        <div class="field">
          <label>Получатели</label>
          <input placeholder="например: ЦУКС, Штаб, РТП" />
        </div>
        <div class="field">
          <label>Формат</label>
          <select>
            <option>PDF</option>
            <option>DOCX</option>
            <option>HTML</option>
          </select>
        </div>
      </div>

      <div class="alert" style="margin-top:6px">
        Это макет. Генерацию PDF/DOCX и связь с инцидентами/телеметрией добавим после утверждения структуры.
      </div>

      <button class="btn btn-primary" type="submit" style="margin-top:6px">Сформировать</button>
    </form>
  </div>
<?php endif; ?>

<div class="features">
  <div class="feature">
    <div class="feature-title">Состав оперативной сводки (предложение)</div>
    <ul class="feature-list">
      <li>кратко: обстановка, цели миссии, ограничения;</li>
      <li>ИБАС: координаты/высота/скорость, АКБ, связь, камера/тепловизор;</li>
      <li>ОФП/газовая среда: CO/CH₄/иные датчики, тренды, пороги;</li>
      <li>погода: температура, влажность, давление, ветер/порывы, осадки;</li>
      <li>расчёты: выбранные калькуляторы, входные данные, результаты;</li>
      <li>решения: что рекомендовано, что принято/отклонено, кем и когда.</li>
    </ul>
  </div>

  <div class="feature">
    <div class="feature-title">Экспорт и аудит</div>
    <ul class="feature-list">
      <li>подпись времени (МСК/UTC), идентификаторы пакетов;</li>
      <li>версионирование отчётов и журнал изменений;</li>
      <li>источники данных: телеметрия/датчики/Росгидрометцентр.</li>
    </ul>
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

