<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$page_title = 'Методики и модели — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';
?>

<section class="section">
  <p class="kicker">По диссертации (2026)</p>
  <h1>Методики и модели</h1>
  <p class="lead">
    Ниже — перечень «серверных калькуляторов» и расчётных методик, которые прямо упоминаются в диссертации
    (формулы, алгоритмы, таблицы и блок-схемы). Это каркас для дальнейшей реализации на платформе.
  </p>
</section>

<section class="section" id="calculators">
  <h2>Перечень калькуляторов</h2>
  <p class="section-lead">
    Список сгруппирован по типовым задачам РТП/ОШ, РЛЧС, ЦУКС/ЕДДС и операторов БАС.
    Нумерация формул и рисунков приведена как в тексте диссертации.
  </p>

  <div class="features">
    <div class="feature feature-wide">
      <div class="feature-title">Параметры, полученные от ИБАС (пример)</div>
      <div class="section-lead" style="margin:8px 0 0">
        Демонстрационные значения в системе СИ. Реальные данные будут подставлены из телеметрии/датчиков и погоды (Росгидрометцентр) позже.
      </div>

      <div class="kv-grid" style="margin-top:12px">
        <div class="kv">
          <div class="kv-k">Время пакета</div>
          <div class="kv-v">2026‑03‑01 14:32:10 (МСК, UTC+3)</div>
          <div class="kv-hint">Синхронизация по GNSS/НПУ</div>
        </div>
        <div class="kv">
          <div class="kv-k">Координаты ИБАС</div>
          <div class="kv-v">59,9342° N; 30,3351° E</div>
          <div class="kv-hint">Точность: ±1,8 м (HDOP 0,9)</div>
        </div>
        <div class="kv">
          <div class="kv-k">Высота / скорость / курс</div>
          <div class="kv-v">55 м AGL · 4,0 м/с · 124°</div>
          <div class="kv-hint">Vground; вертикальная скорость: +0,2 м/с</div>
        </div>

        <div class="kv">
          <div class="kv-k">АКБ / питание</div>
          <div class="kv-v">76% · 22,8 В · 14,2 А</div>
          <div class="kv-hint">Оценка до возврата: ~18 мин</div>
        </div>
        <div class="kv">
          <div class="kv-k">Навигация</div>
          <div class="kv-v">GNSS: FIX · спутники: 17</div>
          <div class="kv-hint">Компас: 1,2°; ИНС: OK</div>
        </div>
        <div class="kv">
          <div class="kv-k">Связь (uplink/downlink)</div>
          <div class="kv-v">12,4 / 18,6 Мбит/с</div>
          <div class="kv-hint">SNR 18 дБ; RTT 180 мс; потери 0,8%</div>
        </div>

        <div class="kv">
          <div class="kv-k">Газовая среда</div>
          <div class="kv-v">CO 35 ppm · CO₂ 860 ppm · O₂ 20,4%</div>
          <div class="kv-hint">HCN 2,1 ppm · H₂S 0,0 ppm · CH₄ 0% LEL</div>
        </div>
        <div class="kv">
          <div class="kv-k">Температура / дым</div>
          <div class="kv-v">Tвозд 2,3 °C · видимость 1 800 м</div>
          <div class="kv-hint">Плотность дыма (оценка): 0,32 усл. ед.</div>
        </div>
        <div class="kv">
          <div class="kv-k">Тепловизор</div>
          <div class="kv-v">Tmax 96 °C · Tmin 18 °C</div>
          <div class="kv-hint">Термоконтраст: 12,4 K; «горячие точки»: 3</div>
        </div>

        <div class="kv">
          <div class="kv-k">Погода (Росгидрометцентр, пример)</div>
          <div class="kv-v">T 1,6 °C · RH 78% · P 1012 гПа</div>
          <div class="kv-hint">Ветер 5,2 м/с (СЗ, 315°) · осадки 0,0 мм/ч</div>
        </div>
        <div class="kv">
          <div class="kv-k">Ветер на высоте (оценка)</div>
          <div class="kv-v">6,4 м/с · порывы до 9,0 м/с</div>
          <div class="kv-hint">Сдвиг ветра: +0,8 м/с на 50 м</div>
        </div>
        <div class="kv">
          <div class="kv-k">Камера (видимый диапазон)</div>
          <div class="kv-v">1920×1080 · 25 fps · H.265</div>
          <div class="kv-hint">Битрейт: 4,2 Мбит/с · экспозиция авто</div>
        </div>
      </div>
    </div>

    <div class="feature">
      <div class="feature-title">Городской пожар / задымление</div>
      <ul class="feature-list">
        <li><a class="calc-link" href="/methods/calculators/urban-h-work/"><strong>Рабочая высота над дымом</strong>: Hраб ≥ max(H0; Hдым + ΔHдым) (формула (37)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/urban-omega-dop/"><strong>Допустимое пространство полёта</strong>: Ωдоп = Ω \ (Ωзапр ∪ ΩОФП ∪ Ωобр) (формула (38)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/urban-route-score/"><strong>Оценка предпочтительности маршрута</strong> J по путевым точкам (формула (39)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/urban-risk-aggregate/"><strong>Агрегированный риск коридора</strong> R = wt·Rt + wS·RS + wC·RC + wG·RG (формула (40)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/urban-turn-radius/"><strong>Манёвренность</strong>: минимальный радиус разворота Rmin = V² / (g·tan φmax) (формула (41)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/urban-corridor-width/"><strong>Полуширина коридора безопасности</strong>: b = Rmin + dбок (формула (42)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/urban-mission-profile/"><strong>Выбор профиля миссии</strong> (режимы A/B/C) по блок-схеме (Рисунок 30) + параметры из Таблицы 24.</a></li>
      </ul>
    </div>

    <div class="feature">
      <div class="feature-title">Природные пожары / патрулирование</div>
      <ul class="feature-list">
        <li><a class="calc-link" href="/methods/calculators/wildfire-scan-strip/"><strong>Ширина полосы обзора</strong>: Wэф = 2·H·tan(α/2)·(1 − η) (формула (43)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/wildfire-coverage-rate/"><strong>Темп покрытия площади</strong>: Q = V·Wэф (пример расчёта в 3.3.2).</a></li>
        <li><a class="calc-link" href="/methods/calculators/wildfire-coverage-time/"><strong>Время покрытия</strong>: Tпокр ≈ A / Q (пример расчёта в 3.3.2).</a></li>
        <li><a class="calc-link" href="/methods/calculators/wildfire-params-tmax/"><strong>Подбор параметров под Tмакс</strong>: варианты коррекции высоты/скорости/числа БВС (разбор после формулы (43), Таблица 28).</a></li>
        <li><a class="calc-link" href="/methods/calculators/wildfire-monitoring-mode/"><strong>Выбор режима мониторинга</strong> (обзор/уточнение кромки/контроль) и продукта на карте (Таблица 29).</a></li>
        <li><a class="calc-link" href="/methods/calculators/wildfire-patrol-mode/"><strong>Схемы и режим патрулирования</strong> по Рисункам 34–35.</a></li>
      </ul>
    </div>

    <div class="feature">
      <div class="feature-title">Канал связи / надёжность доставки</div>
      <ul class="feature-list">
        <li><a class="calc-link" href="/methods/calculators/link-budget/"><strong>Бюджет пропускной способности</strong>: расчёт требуемого uplink по сумме потоков (видео/ИК/телеметрия) с учётом η и kрез (фрагмент перед Рисунком 26).</a></li>
        <li><a class="calc-link" href="/methods/calculators/link-sufficiency/"><strong>Метрика достаточности канала</strong>: Kкан = Ravail / Rreq (формула (31)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/link-delivery-prob/"><strong>Вероятность доставки при повторах</strong>: Psucc = 1 − (1 − p)ⁿ (формула (32)).</a></li>
        <li><a class="calc-link" href="/methods/calculators/link-degradation/"><strong>Режим деградации</strong>: правила снижения битрейта/частоты/перехода к «векторным объектам» вместо второго видео (описание после Рисунка 26).</a></li>
      </ul>
    </div>

    <div class="feature">
      <div class="feature-title">Показатели по данным ИБАС</div>
      <ul class="feature-list">
        <li><a class="calc-link" href="/methods/calculators/metrics-air/"><strong>Воздушная разведка</strong>: площадь пожара, скорость изменения площади, глубина зоны задымления, оценка видимости (Таблица 6).</a></li>
        <li><a class="calc-link" href="/methods/calculators/metrics-ofp/"><strong>ОФП и газовая среда</strong>: радиусы/площади зон поражения, уровни риска, допустимое время пребывания, безопасные/опасные маршруты (Таблица 6).</a></li>
        <li><a class="calc-link" href="/methods/calculators/metrics-sar/"><strong>ПСР</strong>: оценка времени доставки помощи, приоритетность участков поиска, показатели вероятности обнаружения по зонам (Таблица 6).</a></li>
        <li><a class="calc-link" href="/methods/calculators/metrics-decision/"><strong>Поддержка решений</strong>: ранг пожара, требуемое количество сил и средств, ожидаемое время локализации/ликвидации, оценка эффективности вариантов тактических решений (продолжение Таблицы 6).</a></li>
      </ul>
    </div>
  </div>
</section>

<section class="section section-alt" id="methods">
  <h2>Перечень методик и алгоритмов</h2>
  <p class="section-lead">
    Это «как применять и проверять расчёты» — то, что в диссертации оформлено в виде блок-схем, таблиц режимов и
    процедур верификации.
  </p>

  <div class="features">
    <div class="feature">
      <div class="feature-title">Алгоритм выбора миссии (Рисунок 30)</div>
      <ul class="feature-list">
        <li>Ввод исходных данных: сценарий, объём миссии, цель «обзор/фокус», ограничения.</li>
        <li>Координация с НПУ/ЦУКС/штабом: адресаты данных, роли и разрешения.</li>
        <li>Проверка ограничений ИВП/запретных зон/времени/порядка взаимодействия.</li>
        <li>Контроль входных данных об обстановке (метео, тепловые признаки, телеметрия, сообщения РТП).</li>
        <li>Оценка навигации и качества связи; при ухудшении — переход в режим деградации.</li>
        <li>Формирование маршрута (коридоры, обход препятствий, резервные точки) и циклическая переоценка условий.</li>
      </ul>
    </div>

    <div class="feature">
      <div class="feature-title">Ситуационная карта и слои (Рисунок 32)</div>
      <ul class="feature-list">
        <li>Алгоритм формирования, верификации и доведения рекомендаций ИИ до РТП, ЦУКС/ЕДДС и служб (Рисунок 32).</li>
        <li>Подбор слоёв карты «под задачи тушения»: очаги/задымление/тактические отметки/риски/рекомендации (описание рядом с Рисунком 32).</li>
        <li>Матрица «потребитель → решение → слой карты → обратная связь» (Таблица 25).</li>
      </ul>
    </div>

    <div class="feature">
      <div class="feature-title">Патрулирование природных пожаров</div>
      <ul class="feature-list">
        <li>Сценарии применения по этапам тушения (Рисунок 27) и схемы патрулирования (Рисунок 34).</li>
        <li>Выбор режима патрулирования (Рисунок 35) и рекомендуемые режимы мониторинга (Таблица 29).</li>
        <li>Комбинация «обзорный проход + детальный проход по секторам» как методически корректный вариант (3.3.2).</li>
      </ul>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
