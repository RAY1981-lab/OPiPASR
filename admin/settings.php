<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role('ADMIN');

$pdo = db();

$fields = [
  'weather' => [
    'weather_temp' => 'Температура',
    'weather_humidity' => 'Влажность',
    'weather_pressure' => 'Давление',
    'weather_wind_speed' => 'Скорость ветра',
    'weather_wind_gust' => 'Порывы ветра',
    'weather_wind_dir' => 'Направление ветра',
    'weather_visibility' => 'Видимость',
    'weather_clouds' => 'Облачность',
    'weather_precip' => 'Осадки',
  ],
  'sensors' => [
    'gas_co' => 'CO (MQ‑2)',
    'gas_ch4' => 'CH₄ (MQ‑2)',
    'gas_lpg' => 'LPG (MQ‑2)',
    'gas_h2' => 'H₂ (MQ‑2)',
    'smoke_temp' => 'Температура/дым',
  ],
  'platform' => [
    'link_status' => 'Статус связи',
    'last_seen' => 'Последний пакет',
    'age_sec' => 'Возраст пакета',
    'uav_id' => 'UAV ID',
    'device' => 'Устройство',
    'ts' => 'Время (ts)',
    'coords' => 'Координаты',
    'flight_vector' => 'Высота/скорость/курс',
    'battery' => 'АКБ/питание',
    'nav_state' => 'GNSS/ИНС',
    'link_rate' => 'Скорость канала',
    'motors_rpm' => 'Обороты двигателей',
    'body_temp' => 'Температура корпуса',
  ],
];

$enabled = [];
try {
  $rows = $pdo->query("SELECT field_key, enabled FROM telemetry_fields")->fetchAll();
  foreach ($rows as $r) {
    $enabled[(string)$r['field_key']] = (bool)$r['enabled'];
  }
} catch (Throwable $e) {
  $enabled = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  require_csrf();

  setting_set('telemetry_log_enabled', !empty($_POST['telemetry_log_enabled']) ? '1' : '0');
  setting_set('telemetry_log_interval_sec', (string)max(5, (int)($_POST['telemetry_log_interval_sec'] ?? 30)));

  setting_set('weather_enabled', !empty($_POST['weather_enabled']) ? '1' : '0');
  setting_set('weather_api_key', trim((string)($_POST['weather_api_key'] ?? '')));
  setting_set('weather_lat', trim((string)($_POST['weather_lat'] ?? '59.9342')));
  setting_set('weather_lon', trim((string)($_POST['weather_lon'] ?? '30.3351')));
  setting_set('weather_city', trim((string)($_POST['weather_city'] ?? '')));
  setting_set('weather_units', (string)($_POST['weather_units'] ?? 'metric'));
  setting_set('weather_lang', (string)($_POST['weather_lang'] ?? 'ru'));
  setting_set('weather_interval_sec', (string)max(60, (int)($_POST['weather_interval_sec'] ?? 300)));

  $posted = $_POST['field'] ?? [];
  foreach ($fields as $cat => $list) {
    foreach ($list as $key => $_label) {
      $val = !empty($posted[$key]) ? 1 : 0;
      $pdo->prepare("
        INSERT INTO telemetry_fields (field_key, category, enabled)
        VALUES (:k, :c, :e)
        ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), category=VALUES(category)
      ")->execute([':k' => $key, ':c' => $cat, ':e' => $val]);
      $enabled[$key] = (bool)$val;
    }
  }

  flash_set('ok', 'Настройки сохранены.');
  redirect('/admin/settings.php');
}

$page_title = 'Настройки телеметрии — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';

$f = flash_get();
$telemetry_log_enabled = setting_get('telemetry_log_enabled', '1') === '1';
$telemetry_log_interval = (int)setting_get('telemetry_log_interval_sec', '30');

$weather_enabled = setting_get('weather_enabled', '0') === '1';
$weather_api_key = (string)setting_get('weather_api_key', '');
$weather_lat = (string)setting_get('weather_lat', '59.9342');
$weather_lon = (string)setting_get('weather_lon', '30.3351');
$weather_city = (string)setting_get('weather_city', '');
$weather_units = (string)setting_get('weather_units', 'metric');
$weather_lang = (string)setting_get('weather_lang', 'ru');
$weather_interval = (int)setting_get('weather_interval_sec', '300');
?>

<?php if ($f): ?>
  <div class="alert <?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>"><strong><?= h($f['msg']) ?></strong></div>
<?php endif; ?>

<div class="panel">
  <div class="h1">Настройки телеметрии</div>
  <p class="p">Здесь задаются частота записи телеметрии и список параметров, которые будут сохраняться в БД.</p>

  <form method="post">
    <?= csrf_field() ?>

    <div class="feature" style="margin-top:12px">
      <div class="feature-title">Запись телеметрии</div>
      <div class="kv-grid" style="margin-top:12px">
        <div class="kv">
          <div class="kv-k">Включить запись</div>
          <label class="switch">
            <input type="checkbox" name="telemetry_log_enabled" value="1" <?= $telemetry_log_enabled ? 'checked' : '' ?> />
            <span class="switch-ui" aria-hidden="true"></span>
          </label>
        </div>
        <div class="kv">
          <div class="kv-k">Период записи (сек.)</div>
          <input class="input" name="telemetry_log_interval_sec" type="number" min="5" value="<?= (int)$telemetry_log_interval ?>" />
          <div class="kv-hint">Минимум 5 секунд</div>
        </div>
      </div>
    </div>

    <div class="feature" style="margin-top:16px">
      <div class="feature-title">OpenWeatherMap</div>
      <div class="kv-grid" style="margin-top:12px">
        <div class="kv">
          <div class="kv-k">Включить погоду</div>
          <label class="switch">
            <input type="checkbox" name="weather_enabled" value="1" <?= $weather_enabled ? 'checked' : '' ?> />
            <span class="switch-ui" aria-hidden="true"></span>
          </label>
        </div>
        <div class="kv">
          <div class="kv-k">API ключ</div>
          <input class="input" name="weather_api_key" value="<?= h($weather_api_key) ?>" placeholder="OpenWeatherMap API key" />
        </div>
        <div class="kv">
          <div class="kv-k">Широта</div>
          <input class="input" name="weather_lat" value="<?= h($weather_lat) ?>" placeholder="59.9342" />
        </div>
        <div class="kv">
          <div class="kv-k">Долгота</div>
          <input class="input" name="weather_lon" value="<?= h($weather_lon) ?>" placeholder="30.3351" />
        </div>
        <div class="kv">
          <div class="kv-k">Город (опц.)</div>
          <input class="input" name="weather_city" value="<?= h($weather_city) ?>" placeholder="Saint Petersburg" />
        </div>
        <div class="kv">
          <div class="kv-k">Единицы</div>
          <select class="select" name="weather_units">
            <option value="metric" <?= $weather_units==='metric'?'selected':'' ?>>metric</option>
            <option value="imperial" <?= $weather_units==='imperial'?'selected':'' ?>>imperial</option>
            <option value="standard" <?= $weather_units==='standard'?'selected':'' ?>>standard</option>
          </select>
        </div>
        <div class="kv">
          <div class="kv-k">Язык</div>
          <input class="input" name="weather_lang" value="<?= h($weather_lang) ?>" placeholder="ru" />
        </div>
        <div class="kv">
          <div class="kv-k">Период обновления (сек.)</div>
          <input class="input" name="weather_interval_sec" type="number" min="60" value="<?= (int)$weather_interval ?>" />
        </div>
      </div>
    </div>

    <div class="feature" style="margin-top:16px">
      <div class="feature-title">Параметры для записи</div>

      <?php foreach ($fields as $cat => $list): ?>
        <div class="side-head" style="margin-top:12px"><?= $cat === 'weather' ? 'Погода' : ($cat === 'sensors' ? 'Сенсоры (ИБАС)' : 'ИБАС (платформа)') ?></div>
        <div class="kv-grid" style="margin-top:10px">
          <?php foreach ($list as $key => $label): ?>
            <div class="kv">
              <div class="kv-k"><?= h($label) ?></div>
              <label class="switch">
                <input type="checkbox" name="field[<?= h($key) ?>]" value="1" <?= !empty($enabled[$key]) ? 'checked' : '' ?> />
                <span class="switch-ui" aria-hidden="true"></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">Сохранить</button>
      <a class="btn btn-ghost" href="/admin/approvals.php">Назад</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

