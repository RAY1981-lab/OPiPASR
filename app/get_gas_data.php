<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/db.php'; // должно создать $pdo (PDO)

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'DB connection not available',
  ], JSON_UNESCAPED_UNICODE);
  exit;
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
    echo json_encode([
      'ok' => true,
      'connection_status' => 'НЕТ ДАННЫХ',
      'state_class' => 'bad',
      'last_seen' => null,
      'age_sec' => null,
      'co' => 0,
      'ch4' => 0,
      'uav_id' => null,
      'device' => null,
      'ts' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $mq2_adc = (int)($last['mq2_adc'] ?? 0);

  // Газовые расчёты (как у вас в helpers.php)
  $co = round(calculate_gas_concentration($mq2_adc, 'CO'), 2);
  $ch4 = round(calculate_gas_concentration($mq2_adc, 'CH4'), 2);

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

  echo json_encode([
    'ok' => true,

    // статус связи с дроном (по свежести последней телеметрии)
    'connection_status' => $connectionStatus,
    'state_class' => $stateClass,
    'last_seen' => $lastSeenStr,
    'age_sec' => $ageSec,

    // данные
    'co' => $co,
    'ch4' => $ch4,
    'uav_id' => $last['uav_id'] ?? null,
    'device' => $last['device'] ?? null,
    'ts' => $last['ts'] ?? null,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}
