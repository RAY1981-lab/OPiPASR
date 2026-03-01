<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/bootstrap.php';
require_permission('telemetry', 'view');

$pdo = db();

function get_enabled_fields(PDO $pdo): array {
  $rows = $pdo->query("SELECT field_key FROM telemetry_fields WHERE enabled=1")->fetchAll();
  $out = [];
  foreach ($rows as $r) $out[(string)$r['field_key']] = true;
  return $out;
}

function filter_payload(array $payload, array $enabled, array $map): array {
  $out = [];
  foreach ($map as $field_key => $payload_key) {
    if (!empty($enabled[$field_key])) {
      $out[$payload_key] = $payload[$payload_key] ?? null;
    }
  }
  return $out;
}

function log_payload(PDO $pdo, string $source, array $payload, int $interval_sec, string $last_key): void {
  $now = time();
  $last = (int)setting_get($last_key, '0');
  if ($interval_sec > 0 && ($now - $last) < $interval_sec) return;

  $stmt = $pdo->prepare("INSERT INTO telemetry_log (source, payload) VALUES (:s, :p)");
  $stmt->execute([
    ':s' => $source,
    ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
  ]);
  setting_set($last_key, (string)$now);
}

function fetch_openweather(PDO $pdo, ?string &$err = null): ?array {
  $enabled = setting_get('weather_enabled', '0') === '1';
  $key = trim((string)setting_get('weather_api_key', ''));
  if (!$enabled) { $err = 'weather_disabled'; return null; }
  if ($key === '') { $err = 'missing_api_key'; return null; }

  $interval = (int)setting_get('weather_interval_sec', '300');
  $now = time();
  $last = (int)setting_get('weather_last_fetch_ts', '0');
  if ($interval > 0 && ($now - $last) < $interval) {
    // return cached
    $row = $pdo->query("SELECT payload FROM weather_cache ORDER BY id DESC LIMIT 1")->fetch();
    return $row ? json_decode((string)$row['payload'], true) : null;
  }

  $units = (string)setting_get('weather_units', 'metric');
  $lang  = (string)setting_get('weather_lang', 'ru');
  $lat   = trim((string)setting_get('weather_lat', '59.9342'));
  $lon   = trim((string)setting_get('weather_lon', '30.3351'));
  $city  = trim((string)setting_get('weather_city', ''));

  if ($city !== '') {
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . rawurlencode($city) .
      "&appid=" . rawurlencode($key) . "&units=" . rawurlencode($units) . "&lang=" . rawurlencode($lang);
  } else {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat=" . rawurlencode($lat) . "&lon=" . rawurlencode($lon) .
      "&appid=" . rawurlencode($key) . "&units=" . rawurlencode($units) . "&lang=" . rawurlencode($lang);
  }

  $json = null;
  if (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create(['http' => ['timeout' => 6]]);
    $json = @file_get_contents($url, false, $ctx);
  }
  if (!$json && function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $json = curl_exec($ch);
    curl_close($ch);
  }
  if (!$json) { $err = 'fetch_failed'; return null; }
  $data = json_decode($json, true);
  if (!is_array($data)) { $err = 'bad_json'; return null; }
  if (!empty($data['cod']) && (int)$data['cod'] !== 200) { $err = 'api_error'; return null; }

  $payload = [
    'ts' => $data['dt'] ?? time(),
    'temp' => $data['main']['temp'] ?? null,
    'humidity' => $data['main']['humidity'] ?? null,
    'pressure' => $data['main']['pressure'] ?? null,
    'wind_speed' => $data['wind']['speed'] ?? null,
    'wind_gust' => $data['wind']['gust'] ?? null,
    'wind_deg' => $data['wind']['deg'] ?? null,
    'visibility' => $data['visibility'] ?? null,
    'clouds' => $data['clouds']['all'] ?? null,
    'precip' => $data['rain']['1h'] ?? ($data['snow']['1h'] ?? null),
    'source' => 'openweathermap',
    'units' => $units,
  ];

  $pdo->prepare("INSERT INTO weather_cache (payload) VALUES (:p)")
      ->execute([':p' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
  setting_set('weather_last_fetch_ts', (string)$now);
  return $payload;
}

try {
  // Берём последнюю запись
  $stmt = $pdo->query("
    SELECT id, uav_id, device, ts, mq2_adc, created_at
    FROM sensor_readings
    ORDER BY id DESC
    LIMIT 1
  ");
  $last = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

  if (!$last) {
    $response = [
      'ok' => true,
      'connection_status' => 'НЕТ ДАННЫХ',
      'state_class' => 'bad',
      'last_seen' => null,
      'age_sec' => null,
      'co' => 0,
      'ch4' => 0,
      'lpg' => 0,
      'h2' => 0,
      'uav_id' => null,
      'device' => null,
      'ts' => null,
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
  }

  $mq2_adc = (int)($last['mq2_adc'] ?? 0);

  // Газовые расчёты (как у вас в helpers.php)
  $co = round(calculate_gas_concentration($mq2_adc, 'CO'), 2);
  $ch4 = round(calculate_gas_concentration($mq2_adc, 'CH4'), 2);
  $lpg = round(calculate_gas_concentration($mq2_adc, 'LPG'), 2);
  $h2  = round(calculate_gas_concentration($mq2_adc, 'H2'), 2);

  // ---- КРИТЕРИЙ "ДРОН НА СВЯЗИ" ----
  // Используем created_at как время поступления последнего пакета на сервер.
  $lastSeenStr = (string)($last['created_at'] ?? '');
  $lastSeenTs = $lastSeenStr !== '' ? strtotime($lastSeenStr) : false;

  $ageSec = ($lastSeenTs !== false) ? (time() - $lastSeenTs) : null;

  // Порог связи (подберите под вашу частоту отправки данных)
  $THRESHOLD_SEC = 30;

  if ($ageSec === null) {
    $connectionStatus = 'НЕТ ДАННЫХ';
    $stateClass = 'bad';
  } elseif ($ageSec <= $THRESHOLD_SEC) {
    $connectionStatus = 'OK';
    $stateClass = 'ok';
  } else {
    $connectionStatus = 'НЕТ СВЯЗИ';
    $stateClass = 'bad';
  }

  $response = [
    'ok' => true,

    // статус связи с дроном (по свежести последней телеметрии)
    'connection_status' => $connectionStatus,
    'state_class' => $stateClass,
    'last_seen' => $lastSeenStr,
    'age_sec' => $ageSec,

    // данные
    'co' => $co,
    'ch4' => $ch4,
    'lpg' => $lpg,
    'h2' => $h2,
    'uav_id' => $last['uav_id'] ?? null,
    'device' => $last['device'] ?? null,
    'ts' => $last['ts'] ?? null,
  ];

  // ---- Weather fetch (always try if enabled) ----
  $weather_err = null;
  $weather = fetch_openweather($pdo, $weather_err);

  // ---- Logging (telemetry + weather) ----
  $log_enabled = setting_get('telemetry_log_enabled', '1') === '1';
  if ($log_enabled) {
    $interval = (int)setting_get('telemetry_log_interval_sec', '30');
    $enabled = get_enabled_fields($pdo);

    $payload = [
      'connection_status' => $connectionStatus,
      'last_seen' => $lastSeenStr,
      'age_sec' => $ageSec,
      'uav_id' => $last['uav_id'] ?? null,
      'device' => $last['device'] ?? null,
      'ts' => $last['ts'] ?? null,
      'co' => $co,
      'ch4' => $ch4,
      'lpg' => $lpg,
      'h2' => $h2,
    ];

    $map = [
      'link_status' => 'connection_status',
      'last_seen' => 'last_seen',
      'age_sec' => 'age_sec',
      'uav_id' => 'uav_id',
      'device' => 'device',
      'ts' => 'ts',
      'gas_co' => 'co',
      'gas_ch4' => 'ch4',
      'gas_lpg' => 'lpg',
      'gas_h2' => 'h2',
    ];

    $filtered = filter_payload($payload, $enabled, $map);
    log_payload($pdo, 'ibas', $filtered, $interval, 'telemetry_last_log_ts');

    // weather
    if (is_array($weather)) {
      $wmap = [
        'weather_temp' => 'temp',
        'weather_humidity' => 'humidity',
        'weather_pressure' => 'pressure',
        'weather_wind_speed' => 'wind_speed',
        'weather_wind_gust' => 'wind_gust',
        'weather_wind_dir' => 'wind_deg',
        'weather_visibility' => 'visibility',
        'weather_clouds' => 'clouds',
        'weather_precip' => 'precip',
      ];
      $wfiltered = filter_payload($weather, $enabled, $wmap);
      log_payload($pdo, 'weather', $wfiltered, (int)setting_get('weather_interval_sec', '300'), 'weather_last_log_ts');
    }
  }

  // attach weather (if available) to response for UI
  if (isset($weather) && is_array($weather)) {
    $response['weather'] = $weather;
    $response['weather_ok'] = true;
  } else {
    $response['weather'] = null;
    $response['weather_ok'] = false;
    if ($weather_err) $response['weather_error'] = $weather_err;
  }

  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}
