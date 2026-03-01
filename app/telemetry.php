<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/helpers.php';

require_permission('telemetry', 'view');

$page_title = 'Телеметрия — Кабинет';
require_once __DIR__ . '/../config/header.php';

// Для первичного рендера страницы берём последние данные из БД (если есть).
$pdo = null;
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
  $pdo = $GLOBALS['pdo'];
} elseif (function_exists('db')) {
  try {
    $tmp = db();
    if ($tmp instanceof PDO) $pdo = $tmp;
  } catch (Throwable $e) {
    $pdo = null;
  }
}

$last = null;
if ($pdo instanceof PDO) {
  try {
    $stmt = $pdo->query("SELECT * FROM sensor_readings ORDER BY id DESC LIMIT 1");
    $last = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
  } catch (Throwable $e) {
    $last = null;
  }
}

$mq2adc = (int)($last['mq2_adc'] ?? 0);
$co_concentration   = round(calculate_gas_concentration($mq2adc, 'CO'), 4);
$ch4_concentration  = round(calculate_gas_concentration($mq2adc, 'CH4'), 4);
$lpg_concentration  = round(calculate_gas_concentration($mq2adc, 'LPG'), 4);
$h2_concentration   = round(calculate_gas_concentration($mq2adc, 'H2'), 4);

// Первичный статус связи (на сервере) — по created_at
$connection_status = 'НЕТ ДАННЫХ';
$stateClass = 'bad';
$last_seen = $last['created_at'] ?? null;

if ($last_seen) {
  $ageSec = time() - strtotime((string)$last_seen);
  if ($ageSec <= 30) {
    $connection_status = 'OK';
    $stateClass = 'ok';
  } else {
    $connection_status = 'НЕТ СВЯЗИ';
    $stateClass = 'bad';
  }
}
?>

<h1 style="margin:0 0 12px;">Телеметрия ИБАС</h1>

<div class="note" style="margin-bottom:14px">
  <strong>Черновик структуры.</strong> Здесь будут собраны все параметры ИБАС: газоанализ, GNSS/ИНС, АКБ, связь, камера/тепловизор,
  погодные данные, статус миссии и журнал событий.
</div>

<div class="panel-card" style="margin-bottom:16px;">
  <div class="panel-header">
    <span class="panel-badge">ИБАС</span>
    <span class="panel-title">Состояние связи и газовая среда</span>
  </div>

  <div class="panel-grid">
    <div class="tile">
      <div class="tile-label">Связь с дроном</div>
      <div class="tile-value" id="connection_status" data-class="<?= h($stateClass) ?>"><?= h($connection_status) ?></div>
      <div class="tile-note">
        Последний пакет: <span id="last_seen"><?= h((string)($last_seen ?? '—')) ?></span>
        (<span id="age_sec"><?= $last_seen ? (int)(time() - strtotime((string)$last_seen)) : '—' ?></span> сек назад)
      </div>
    </div>

    <div class="tile">
      <div class="tile-label">UAV</div>
      <div class="tile-value" id="uav_id"><?= h($last['uav_id'] ?? '—') ?></div>
      <div class="tile-note">источник данных</div>
    </div>

    <div class="tile">
      <div class="tile-label">Концентрация CO</div>
      <div class="tile-value" id="co_concentration"><?= h((string)$co_concentration) ?>%</div>
      <div class="tile-note">угарный газ</div>
    </div>

    <div class="tile">
      <div class="tile-label">Концентрация CH₄</div>
      <div class="tile-value" id="ch4_concentration"><?= h((string)$ch4_concentration) ?>%</div>
      <div class="tile-note">метан</div>
    </div>

    <div class="tile">
      <div class="tile-label">Концентрация LPG</div>
      <div class="tile-value" id="lpg_concentration"><?= h((string)$lpg_concentration) ?>%</div>
      <div class="tile-note">пропан / бутан</div>
    </div>

    <div class="tile">
      <div class="tile-label">Концентрация H₂</div>
      <div class="tile-value" id="h2_concentration"><?= h((string)$h2_concentration) ?>%</div>
      <div class="tile-note">водород</div>
    </div>

    <div class="tile">
      <div class="tile-label">Устройство</div>
      <div class="tile-value" id="device"><?= h($last['device'] ?? '—') ?></div>
      <div class="tile-note">метка отправителя</div>
    </div>

    <div class="tile">
      <div class="tile-label">Время (ts)</div>
      <div class="tile-value" id="ts"><?= h((string)($last['ts'] ?? '—')) ?></div>
      <div class="tile-note">unix timestamp</div>
    </div>
  </div>

  <div class="panel-footer">
    <span class="panel-footnote">Связь определяется по свежести последней телеметрии в sensor_readings.</span>
  </div>
</div>

<div class="features">
  <div class="feature">
    <div class="feature-title">Навигация и позиционирование (план)</div>
    <ul class="feature-list">
      <li>координаты: широта/долгота (WGS‑84), точность, HDOP;</li>
      <li>высота: AGL/AMSL, вертикальная скорость;</li>
      <li>курс/скорость, режим GNSS, число спутников;</li>
      <li>ИНС/компас: статус, расхождение, флаги деградации.</li>
    </ul>
  </div>
  <div class="feature">
    <div class="feature-title">Связь и канал передачи (план)</div>
    <ul class="feature-list">
      <li>uplink/downlink (Мбит/с), SNR, RTT, потери;</li>
      <li>режим деградации и приоритет потоков;</li>
      <li>оценка качества канала и прогноз устойчивости.</li>
    </ul>
  </div>
  <div class="feature">
    <div class="feature-title">Погода (план)</div>
    <ul class="feature-list">
      <li>температура, влажность, давление (гПа), ветер (м/с и направление), порывы;</li>
      <li>осадки (мм/ч), видимость (м), облачность;</li>
      <li>источник: Росгидрометцентр / локальный датчик.</li>
    </ul>
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function setStatusClass(el, stateClass) {
  el.classList.remove('ok', 'bad');
  if (stateClass) el.classList.add(stateClass);
}

async function fetchData() {
  try {
    const res = await fetch('/app/get_gas_data.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data || data.ok === false) return;

    document.getElementById('co_concentration').innerText  = (data.co ?? '0') + '%';
    document.getElementById('ch4_concentration').innerText = (data.ch4 ?? '0') + '%';
    document.getElementById('lpg_concentration').innerText = (data.lpg ?? '0') + '%';
    document.getElementById('h2_concentration').innerText  = (data.h2 ?? '0') + '%';

    if (data.uav_id !== undefined) document.getElementById('uav_id').innerText = data.uav_id ?? '—';
    if (data.device !== undefined) document.getElementById('device').innerText = data.device ?? '—';
    if (data.ts !== undefined) document.getElementById('ts').innerText = data.ts ?? '—';

    const st = document.getElementById('connection_status');
    if (data.connection_status !== undefined) st.innerText = data.connection_status ?? '—';
    if (data.state_class !== undefined) setStatusClass(st, data.state_class);

    if (data.last_seen !== undefined) document.getElementById('last_seen').innerText = data.last_seen ?? '—';
    if (data.age_sec !== undefined) document.getElementById('age_sec').innerText = (data.age_sec ?? '—');
  } catch (e) {
    console.error('Ошибка получения данных:', e);
  }
}

setInterval(fetchData, 10000);
fetchData();
</script>

