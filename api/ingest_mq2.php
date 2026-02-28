<?php
// public_html/api/ingest_mq2.php
header('Content-Type: application/json; charset=utf-8');

$API_KEY = 'HpQpk3A6bbeUYRrOawC04FlE5eXNq+PsEtPmg9L/1ywWZsROE64bjU/JXq1vsd7v';

$hdr = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals($API_KEY, $hdr)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// MySQL
$DB_HOST = 'localhost';
$DB_NAME = 'u3052693_default';
$DB_USER = 'u3052693_default';
$DB_PASS = '28RwFk3TptuOFpo5';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_json']);
    exit;
}

$uav_id  = $data['uav_id'] ?? 'UAV-01';
$ts      = (int)($data['ts'] ?? time());

$seq     = array_key_exists('seq', $data) ? (int)$data['seq'] : null;
$ms      = array_key_exists('ms',  $data) ? (int)$data['ms']  : null;

$mq2_adc = array_key_exists('mq2_adc', $data) ? (int)$data['mq2_adc'] : null;
$mq2_do  = array_key_exists('mq2_do',  $data) ? (int)$data['mq2_do']  : null;

$status  = array_key_exists('status', $data) ? (string)$data['status'] : null;
$device  = array_key_exists('device', $data) ? (string)$data['device'] : null;

// raw_json: если не строка — сохраним весь payload как JSON
$raw_json = $data['raw_json'] ?? null;
if (!is_string($raw_json)) {
    $raw_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $sql = "
      INSERT INTO sensor_readings
        (uav_id, ts, ms, seq, mq2_adc, mq2_do, status, device, raw_json)
      VALUES
        (:uav_id, :ts, :ms, :seq, :mq2_adc, :mq2_do, :status, :device, :raw_json)
      ON DUPLICATE KEY UPDATE
        ms      = VALUES(ms),
        mq2_adc = VALUES(mq2_adc),
        mq2_do  = VALUES(mq2_do),
        status  = VALUES(status),
        device  = VALUES(device),
        raw_json= VALUES(raw_json)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uav_id'  => $uav_id,
        ':ts'      => $ts,
        ':ms'      => $ms,
        ':seq'     => $seq,
        ':mq2_adc' => $mq2_adc,
        ':mq2_do'  => $mq2_do,
        ':status'  => $status,
        ':device'  => $device,
        ':raw_json'=> $raw_json,
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}
