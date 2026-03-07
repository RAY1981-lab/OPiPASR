<?php
declare(strict_types=1);

require_once __DIR__ . '/_lib.php';
require_permission('calculators', 'view');

$pdo = db();
$table_key = 'practice_hydrants';

$default_headers = ['№ п/п', 'Имя гидранта', 'Водоотдача', 'lat', 'lon', 'Состояние', 'Улица (наименование объекта)'];
$headers = $default_headers;

try {
  $stmt = $pdo->prepare("SELECT header_json FROM reference_generic_headers WHERE table_key=:k LIMIT 1");
  $stmt->execute([':k' => $table_key]);
  $header_json = $stmt->fetchColumn();
  if ($header_json) {
    $decoded = json_decode((string)$header_json, true);
    if (is_array($decoded) && count($decoded) > 0) {
      $clean = [];
      foreach ($decoded as $h) {
        $h = trim((string)$h);
        if ($h !== '') $clean[] = $h;
      }
      if ($clean) $headers = $clean;
    }
  }
} catch (Throwable $e) {}

$rows = [];
try {
  $stmt = $pdo->prepare("
    SELECT row_index, payload_json
    FROM reference_generic_rows
    WHERE table_key=:k
    ORDER BY row_index ASC
  ");
  $stmt->execute([':k' => $table_key]);
  $rows = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) {
  $rows = [];
}

$features = [];
$hmap = [];
foreach ($headers as $i => $h) {
  $key = mb_strtolower(trim((string)$h), 'UTF-8');
  $hmap[$key] = $i;
}

$lat_idx = $hmap['lat'] ?? ($hmap['широта'] ?? null);
$lon_idx = $hmap['lon'] ?? ($hmap['долгота'] ?? null);
$name_idx = $hmap['имя гидранта'] ?? null;
$dis_idx = $hmap['водоотдача'] ?? null;
$status_idx = $hmap['состояние'] ?? null;
$addr_idx = $hmap['улица (наименование объекта)'] ?? ($hmap['адрес'] ?? null);

// Fallback to known CSV positions if headers are missing or mangled.
if ($lat_idx === null) $lat_idx = 3;
if ($lon_idx === null) $lon_idx = 4;
if ($name_idx === null) $name_idx = 1;
if ($dis_idx === null) $dis_idx = 2;
if ($status_idx === null) $status_idx = 5;
if ($addr_idx === null) $addr_idx = 6;

foreach ($rows as $r) {
  $payload = json_decode((string)($r['payload_json'] ?? ''), true);
  if (!is_array($payload)) $payload = [];
  $lat = $lat_idx !== null ? (float)str_replace(',', '.', (string)($payload[$lat_idx] ?? '')) : 0.0;
  $lon = $lon_idx !== null ? (float)str_replace(',', '.', (string)($payload[$lon_idx] ?? '')) : 0.0;
  if (!$lat || !$lon) continue;
  $name = (string)($payload[$name_idx] ?? '');
  $dis = $dis_idx !== null ? (string)($payload[$dis_idx] ?? '') : '';
  $status = $status_idx !== null ? (string)($payload[$status_idx] ?? '') : '';
  $addr = $addr_idx !== null ? (string)($payload[$addr_idx] ?? '') : '';
  $features[] = [
    'type' => 'Feature',
    'properties' => [
      'row_index' => (int)($r['row_index'] ?? 0),
      'name' => $name,
      'discharge' => $dis,
      'status' => $status,
      'address' => $addr,
      'lat' => $lat,
      'lon' => $lon,
    ],
    'geometry' => [
      'type' => 'Point',
      'coordinates' => [$lon, $lat],
    ],
  ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(
  ['type' => 'FeatureCollection', 'features' => $features],
  JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
