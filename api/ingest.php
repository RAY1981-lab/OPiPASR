<?php
declare(strict_types=1);

// 1) Секреты и DB-параметры
require_once __DIR__ . '/../config/ingest_secret.php';

// 2) Настройки логирования
$LOG_FILE = dirname(__DIR__, 3) . '/logs/ingest.log'; // .../data/logs/ingest.log
$LOG_MAX_BYTES = 10 * 1024 * 1024; // 10 MB, простая ротация

function log_event(string $logFile, int $maxBytes, string $level, string $event, array $ctx = []): void {
    // UTC ISO8601
    $row = [
        'ts_utc' => gmdate('c'),
        'level'  => $level,
        'event'  => $event,
    ] + $ctx;

    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    // простая ротация
    if (is_file($logFile) && filesize($logFile) !== false && filesize($logFile) > $maxBytes) {
        @rename($logFile, $logFile . '.1');
    }

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function respond(int $code, string $body): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
    exit;
}

$request_id = bin2hex(random_bytes(8));
$remote_ip  = $_SERVER['REMOTE_ADDR'] ?? '-';
$method     = $_SERVER['REQUEST_METHOD'] ?? '-';
$uri        = $_SERVER['REQUEST_URI'] ?? '-';

// 3) Только POST
if ($method !== 'POST') {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'warn', 'method_not_allowed', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'method' => $method,
        'uri' => $uri,
    ]);
    header('Allow: POST');
    respond(405, 'Method Not Allowed');
}

// 4) Проверка ключа (не логируем сам ключ)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!defined('OPIPASR_INGEST_KEY') || OPIPASR_INGEST_KEY === '' || !hash_equals(OPIPASR_INGEST_KEY, $apiKey)) {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'warn', 'unauthorized', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'uri' => $uri,
    ]);
    respond(401, 'Unauthorized');
}

// 5) Тело запроса и JSON
$raw = file_get_contents('php://input') ?: '';
$raw_len = strlen($raw);

$data = json_decode($raw, true);
if (!is_array($data)) {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'warn', 'bad_json', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'bytes' => $raw_len,
    ]);
    respond(400, 'Bad JSON');
}

// 6) Валидация полей (минимально необходимая)
$device = (string)($data['device'] ?? '');
$reading_uuid = (string)($data['reading_uuid'] ?? '');
$ts_in = (string)($data['ts_utc'] ?? '');

if ($device === '' || mb_strlen($device) > 64) {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'warn', 'bad_device', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
    ]);
    respond(400, 'Bad device');
}

if ($reading_uuid === '' || mb_strlen($reading_uuid) > 36) {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'warn', 'bad_reading_uuid', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'device' => $device,
    ]);
    respond(400, 'Bad reading_uuid');
}

// ts_utc: если не пришёл — ставим текущее UTC; если пришёл — пытаемся распарсить
try {
    $dt = $ts_in !== ''
        ? new DateTimeImmutable($ts_in)
        : new DateTimeImmutable('now', new DateTimeZone('UTC'));
} catch (Throwable $e) {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'warn', 'bad_ts_utc', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'device' => $device,
        'ts_utc' => $ts_in,
    ]);
    respond(400, 'Bad ts_utc');
}

// MySQL DATETIME(6)
$ts_mysql = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

$temp  = array_key_exists('temp',  $data) ? (is_numeric($data['temp'])  ? (float)$data['temp']  : null) : null;
$hum   = array_key_exists('hum',   $data) ? (is_numeric($data['hum'])   ? (float)$data['hum']   : null) : null;
$smoke = array_key_exists('smoke', $data) ? (is_numeric($data['smoke']) ? (float)$data['smoke'] : null) : null;

$raw_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($raw_json === false) $raw_json = '{}';

// 7) Запись в БД
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Идемпотентность: если (device, reading_uuid) уже был — обновим запись
    $sql = "
        INSERT INTO sensor_readings (device, ts_utc, reading_uuid, temp, hum, smoke, raw_json)
        VALUES (:device, :ts_utc, :reading_uuid, :temp, :hum, :smoke, :raw_json)
        ON DUPLICATE KEY UPDATE
            ts_utc   = VALUES(ts_utc),
            temp     = VALUES(temp),
            hum      = VALUES(hum),
            smoke    = VALUES(smoke),
            raw_json = VALUES(raw_json)
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':device' => $device,
        ':ts_utc' => $ts_mysql,
        ':reading_uuid' => $reading_uuid,
        ':temp' => $temp,
        ':hum' => $hum,
        ':smoke' => $smoke,
        ':raw_json' => $raw_json,
    ]);

    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'info', 'ingest_ok', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'device' => $device,
        'reading_uuid' => $reading_uuid,
        'bytes' => $raw_len,
    ]);

    // 201 оставляем как и раньше (клиенту так проще)
    http_response_code(201);
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;

} catch (Throwable $e) {
    log_event($GLOBALS['LOG_FILE'], $GLOBALS['LOG_MAX_BYTES'], 'error', 'db_error', [
        'request_id' => $request_id,
        'ip' => $remote_ip,
        'device' => $device,
        'reading_uuid' => $reading_uuid,
        'err' => $e->getMessage(),
    ]);
    respond(500, 'DB error');
}
