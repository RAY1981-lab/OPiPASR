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
$ageSec = null;

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

$telemetry_live = ($stateClass === 'ok');
?>

<h1 style="margin:0 0 12px;">Телеметрия ИБАС</h1>

<div class="map-embed" style="margin: 0 auto 16px;max-width: 1120px;">
  <iframe
    src="https://yandex.ru/map-widget/v1/?ll=30.3351%2C59.9342&z=12&pt=30.3351,59.9342,pm2rdm"
    allowfullscreen="true"
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    title="Карта (Яндекс)"
  ></iframe>
</div>

<div class="features">
  <div class="feature">
    <div class="feature-title">Погода</div>
    <div class="kv-grid kv-grid--weather" data-live-tile="weather" style="margin-top:12px">
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Температура</div>
        <div class="kv-v" id="wx_temp">—</div>
        <div class="kv-hint">Источник: OpenWeatherMap</div>
      </div>
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Влажность</div>
        <div class="kv-v" id="wx_humidity">—</div>
        <div class="kv-hint">Источник: OpenWeatherMap</div>
      </div>
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Давление</div>
        <div class="kv-v" id="wx_pressure">—</div>
        <div class="kv-hint">Источник: OpenWeatherMap</div>
      </div>
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Ветер и направление</div>
        <div class="kv-v" id="wx_wind">—</div>
        <div class="kv-hint">Скорость/порывы/азимут</div>
      </div>
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Видимость</div>
        <div class="kv-v" id="wx_visibility">—</div>
        <div class="kv-hint">Источник: OpenWeatherMap</div>
      </div>
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Облачность</div>
        <div class="kv-v" id="wx_clouds">—</div>
        <div class="kv-hint">Источник: OpenWeatherMap</div>
      </div>
      <div class="kv" data-live-source="weather">
        <div class="kv-k">Осадки</div>
        <div class="kv-v" id="wx_precip">—</div>
        <div class="kv-hint">За последний час</div>
      </div>
    </div>
  </div>

  <div class="feature">
    <div class="feature-title">Сенсоры (ИБАС)</div>
    <div class="kv-grid" style="margin-top:12px">
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Связь с дроном</div>
        <div class="kv-v" id="connection_status"><?= h($connection_status) ?></div>
        <div class="kv-hint">
          Последний пакет: <span id="last_seen"><?= h((string)($last_seen ?? '—')) ?></span>
          (<span id="age_sec"><?= $ageSec !== null ? (int)$ageSec : '—' ?></span> сек назад)
        </div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">CO (MQ‑2)</div>
        <div class="kv-v" id="co_concentration"><?= h((string)$co_concentration) ?>%</div>
        <div class="kv-hint">Концентрация в процентах (1% = 10 000 ppm)</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">CH₄ (MQ‑2)</div>
        <div class="kv-v" id="ch4_concentration"><?= h((string)$ch4_concentration) ?>%</div>
        <div class="kv-hint">Концентрация в процентах (1% = 10 000 ppm)</div>
      </div>

      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">LPG (MQ‑2)</div>
        <div class="kv-v" id="lpg_concentration"><?= h((string)$lpg_concentration) ?>%</div>
        <div class="kv-hint">Пропан / бутан</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">H₂ (MQ‑2)</div>
        <div class="kv-v" id="h2_concentration"><?= h((string)$h2_concentration) ?>%</div>
        <div class="kv-hint">Водород</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Температура / дым</div>
        <div class="kv-v" id="smoke_temp">Tвозд 2,3 °C · видимость 1 800 м</div>
        <div class="kv-hint">Плотность дыма (оценка): 0,32 усл. ед.</div>
      </div>
    </div>
  </div>

  <div class="feature">
    <div class="feature-title">ИБАС (платформа)</div>
    <div class="kv-grid" style="margin-top:12px">
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Время пакета</div>
        <div class="kv-v" id="pkt_time"><?= h((string)($last['ts'] ?? '—')) ?></div>
        <div class="kv-hint">unix timestamp (будет заменено на МСК/UTC)</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Координаты ИБАС</div>
        <div class="kv-v" id="gps_pos">59,9342° N; 30,3351° E</div>
        <div class="kv-hint">Точность: ±1,8 м (HDOP 0,9)</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Высота / скорость / курс</div>
        <div class="kv-v" id="flight_vec">55 м AGL · 4,0 м/с · 124°</div>
        <div class="kv-hint">Vground; вертикальная скорость: +0,2 м/с</div>
      </div>

      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">АКБ / питание</div>
        <div class="kv-v" id="power_pack">76% · 22,8 В · 14,2 А</div>
        <div class="kv-hint">Оценка до возврата: ~18 мин</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Навигация</div>
        <div class="kv-v" id="nav_state">GNSS: FIX · спутники: 17</div>
        <div class="kv-hint">Компас: 1,2°; ИНС: OK</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Связь (uplink/downlink)</div>
        <div class="kv-v" id="link_rate">12,4 / 18,6 Мбит/с</div>
        <div class="kv-hint">SNR 18 дБ; RTT 180 мс; потери 0,8%</div>
      </div>

      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Двигатели (RPM)</div>
        <div class="kv-v" id="motors_rpm">M1 6 120 · M2 6 080 · M3 6 140 · M4 6 095</div>
        <div class="kv-hint">Среднее: 6 109 rpm</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Температура корпуса</div>
        <div class="kv-v" id="body_temp">+34 °C</div>
        <div class="kv-hint">Внутри: +29 °C · контроллер: +41 °C</div>
      </div>
      <div class="kv<?= $telemetry_live ? ' is-live' : '' ?>" data-live-source="telemetry">
        <div class="kv-k">Устройство / миссия</div>
        <div class="kv-v" id="device"><?= h($last['device'] ?? '—') ?></div>
        <div class="kv-hint">UAV: <span id="uav_id"><?= h($last['uav_id'] ?? '—') ?></span> · статус миссии: ПАТРУЛЬ</div>
      </div>
    </div>
  </div>
</div>

<div class="telemetry-video">
  <div class="iv-embed" style="margin:0 auto;padding:0;border:0;width:482px;"><div class="iv-v" style="display:block;margin:0;padding:1px;border:0;background:#000;"><iframe class="iv-i" style="display:block;margin:0;padding:0;border:0;" src="https://open.ivideon.com/embed/v3/100-ZN0HiLHuEOvUnYwp6unIHI:0/" width="480" height="270" frameborder="0" allow="autoplay; fullscreen; clipboard-write; picture-in-picture"></iframe></div><div class="iv-b" style="display:block;margin:0;padding:0;border:0;"><div style="float:right;text-align:right;padding:0 0 10px;line-height:10px;"><a class="iv-a" style="font:10px Verdana,sans-serif;color:inherit;opacity:.6;" href="https://go.ivideon.com/site" target="_blank">Powered by Ivideon</a></div><div style="clear:both;height:0;overflow:hidden;">&nbsp;</div><script src="https://open.ivideon.com/embed/v3/embedded.js"></script></div></div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function setLive(source, live) {
  document.querySelectorAll(`[data-live-source="${source}"]`).forEach((el) => {
    el.classList.toggle('is-live', !!live);
  });
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
    if (data.ts !== undefined) document.getElementById('pkt_time').innerText = data.ts ?? '—';

    const st = document.getElementById('connection_status');
    if (data.connection_status !== undefined) st.innerText = data.connection_status ?? '—';

    if (data.last_seen !== undefined) document.getElementById('last_seen').innerText = data.last_seen ?? '—';
    if (data.age_sec !== undefined) document.getElementById('age_sec').innerText = (data.age_sec ?? '—');

    const live = (data.state_class ?? '') === 'ok';
    setLive('telemetry', live);

    const weatherTile = document.querySelector('[data-live-tile="weather"]');
    const weatherErrorText = (code) => {
      if (code === 'missing_api_key' || code === 'api_error') return 'Введите корректный API';
      if (code === 'fetch_failed' || code === 'bad_json') return 'Нет связи с сервером';
      if (code === 'weather_disabled') return 'Погода выключена';
      return 'Нет данных';
    };

    if (data.weather_ok && data.weather) {
      const w = data.weather;
      const temp = (w.temp ?? '—');
      const rh = (w.humidity ?? '—');
      const p = (w.pressure ?? '—');
      const ws = (w.wind_speed ?? '—');
      const wg = (w.wind_gust ?? '—');
      const wd = (w.wind_deg ?? '—');
      const vis = (w.visibility ?? '—');
      const cl = (w.clouds ?? '—');
      const pr = (w.precip ?? '—');

      document.getElementById('wx_temp').innerText = `${temp} °C`;
      document.getElementById('wx_humidity').innerText = `${rh} %`;
      document.getElementById('wx_pressure').innerText = `${p} гПа`;
      document.getElementById('wx_wind').innerText = `${ws} м/с · порывы ${wg} м/с · ${wd}°`;
      document.getElementById('wx_visibility').innerText = `${vis} м`;
      document.getElementById('wx_clouds').innerText = `${cl} %`;
      document.getElementById('wx_precip').innerText = `${pr} мм/ч`;
      setLive('weather', true);
      if (weatherTile) weatherTile.classList.add('is-live');
    } else {
      setLive('weather', false);
      if (weatherTile) weatherTile.classList.remove('is-live');
      const err = weatherErrorText(data.weather_error);
      document.getElementById('wx_temp').innerText = err;
      document.getElementById('wx_humidity').innerText = err;
      document.getElementById('wx_pressure').innerText = err;
      document.getElementById('wx_wind').innerText = err;
      document.getElementById('wx_visibility').innerText = err;
      document.getElementById('wx_clouds').innerText = err;
      document.getElementById('wx_precip').innerText = err;
    }
  } catch (e) {
    console.error('Ошибка получения данных:', e);
  }
}

setInterval(fetchData, 10000);
fetchData();
</script>
