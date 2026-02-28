<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/helpers.php';

$page_title = 'Кабинет';
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
$rows = [];

if ($pdo instanceof PDO) {
  $stmt = $pdo->query("SELECT * FROM sensor_readings ORDER BY id DESC LIMIT 1");
  $last = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

  $stmt2 = $pdo->query("
    SELECT id, uav_id, ts, ms, seq, mq2_adc, mq2_do, status, device, created_at
    FROM sensor_readings
    ORDER BY id DESC
    LIMIT 25
  ");
  $rows = $stmt2 ? $stmt2->fetchAll(PDO::FETCH_ASSOC) : [];
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

<h1 style="margin:0 0 12px;">Кабинет</h1>

<div class="panel-card" style="margin-bottom:16px;">
  <div class="panel-header">
    <span class="panel-badge">Кабинет</span>
    <span class="panel-title">Оперативная панель</span>
  </div>

  <div class="panel-grid">
    <!-- СТАТУС СВЯЗИ С ДРОНОМ (по свежести телеметрии) -->
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
      <div class="tile-label">Концентрация CH4</div>
      <div class="tile-value" id="ch4_concentration"><?= h((string)$ch4_concentration) ?>%</div>
      <div class="tile-note">метан</div>
    </div>
    
    <div class="tile">
        <div class="tile-label">Концентрация LPG</div>
        <div class="tile-value"><?= h((string)$lpg_concentration) ?>%</div>
        <div class="tile-note">пропан / бутан</div>
    </div>
    
    <div class="tile">
        <div class="tile-label">Концентрация H₂</div>
        <div class="tile-value"><?= h((string)$h2_concentration) ?>%</div>
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




<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function setStatusClass(el, stateClass) {
  // если у вас есть CSS классы .ok / .bad — используем их
  el.classList.remove('ok', 'bad');
  if (stateClass) el.classList.add(stateClass);
}

async function fetchData() {
  try {
    const res = await fetch('/app/get_gas_data.php', { cache: 'no-store' });
    const data = await res.json();

    if (!data || data.ok === false) return;

    // газы
    document.getElementById('co_concentration').innerText  = (data.co ?? '0') + '%';
    document.getElementById('ch4_concentration').innerText = (data.ch4 ?? '0') + '%';

    // метаданные
    if (data.uav_id !== undefined) document.getElementById('uav_id').innerText = data.uav_id ?? '—';
    if (data.device !== undefined) document.getElementById('device').innerText = data.device ?? '—';
    if (data.ts !== undefined) document.getElementById('ts').innerText = data.ts ?? '—';

    // статус связи с дроном
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
