<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/helpers.php';

require_permission('incidents', 'view');

$can_edit = can_permission('incidents', 'edit');

$page_title = 'Инциденты — Кабинет';
require_once __DIR__ . '/../config/header.php';
?>

<h1 style="margin:0 0 12px;">Инциденты</h1>

<div class="note" style="margin-bottom:14px">
  <strong>Черновик структуры.</strong> Здесь будет ведение карточек пожара/ЧС: журнал решений РТП/РЛЧС,
  задачи разведки ИБАС, контроль зон риска, отчётность и обмен с ЦУКС/ЕДДС.
</div>

<?php if ($can_edit): ?>
  <div class="calc-wrap" style="margin-bottom:16px">
    <div style="font-weight:900;font-size:16px;margin-bottom:10px">Создать инцидент (заготовка)</div>
    <form class="calc-form" method="post" onsubmit="return false;">
      <div class="form-grid">
        <div class="field">
          <label>Тип</label>
          <select>
            <option>Пожар (город)</option>
            <option>Пожар (природный)</option>
            <option>Аварийно-спасательные работы</option>
            <option>Другое</option>
          </select>
        </div>
        <div class="field">
          <label>Статус</label>
          <select>
            <option>Оперативная стадия</option>
            <option>Локализация</option>
            <option>Ликвидация</option>
            <option>Закрыт</option>
          </select>
        </div>
        <div class="field">
          <label>Адрес/район</label>
          <input placeholder="например: Санкт‑Петербург, Невский пр., 1" />
        </div>
        <div class="field">
          <label>Координаты</label>
          <input placeholder="59.9342, 30.3351" />
        </div>
        <div class="field">
          <label>Краткое описание</label>
          <input placeholder="что произошло / что требуется" />
        </div>
        <div class="field">
          <label>Приоритет</label>
          <select>
            <option>Высокий</option>
            <option>Средний</option>
            <option>Низкий</option>
          </select>
        </div>
      </div>

      <div class="alert" style="margin-top:6px">
        Это макет формы. Сохранение в БД и журналирование решений добавим после согласования полей.
      </div>

      <button class="btn btn-primary" type="submit" style="margin-top:6px">Создать</button>
    </form>
  </div>
<?php endif; ?>

<div class="features">
  <div class="feature">
    <div class="feature-title">Что будет в карточке инцидента (предложение)</div>
    <ul class="feature-list">
      <li>паспорт инцидента: адрес, координаты, тип, стадия, ответственные (РТП/штаб/ЦУКС);</li>
      <li>задачи ИБАС: маршрут, высоты, зоны запрета/коридоры, точки наблюдения;</li>
      <li>зоны риска и ОФП: токсичность/дым/температура, прогноз распространения;</li>
      <li>журнал решений: «принято/отклонено», основание, время, кто принял;</li>
      <li>обмен: сводка для ЦУКС/ЕДДС, экспорт PDF/Docx, отметки на карте.</li>
    </ul>
  </div>

  <div class="feature">
    <div class="feature-title">Шаблоны задач (РТП/РЛЧС)</div>
    <ul class="feature-list">
      <li>воздушная разведка очага/кромки, оценка площади/темпа роста;</li>
      <li>контроль тепловых аномалий, поиск пострадавших, маршруты эвакуации;</li>
      <li>контроль устойчивости связи и качества доставки данных.</li>
    </ul>
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

