<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_permission('calculators', 'view');
$can_adjust = can_permission('calculators', 'edit');
$ibas_enabled = setting_get('ibas_enabled', '1') === '1';

$cfg = is_file(__DIR__ . '/../config/calculator01.php') ? require __DIR__ . '/../config/calculator01.php' : [];

$pdo = db();
$incident_id = (int)($_GET['incident_id'] ?? 0);
if ($incident_id <= 0) {
  $incident_id = (int)($pdo->query("SELECT id FROM incidents ORDER BY started_at DESC, id DESC LIMIT 1")->fetchColumn() ?: 0);
}

$incidents = [];
try {
  $stmt = $pdo->query("SELECT id, started_at, status, lat0, lon0, title, address, default_window_min, default_radius_km, type, priority, summary, calc_mode, meteo_source, uav_id FROM incidents ORDER BY started_at DESC, id DESC LIMIT 50");
  $incidents = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) {
  $incidents = [];
}

$incident = null;
foreach ($incidents as $row) {
  if ((int)$row['id'] === $incident_id) { $incident = $row; break; }
}

$lat0 = (float)($incident['lat0'] ?? 59.9342);
$lon0 = (float)($incident['lon0'] ?? 30.3351);

$window_min = (int)($_GET['window_min'] ?? 0);
$window_default = (int)($incident['default_window_min'] ?? ($cfg['window_min'] ?? 15));
if ($window_min <= 0) $window_min = max(1, $window_default);

$radius_km = (int)($_GET['radius_km'] ?? ($incident['default_radius_km'] ?? 25));
$radius_km = in_array($radius_km, [5,10,25,50], true) ? $radius_km : 25;

$refresh_sec = (int)($_GET['refresh_sec'] ?? 15);
$refresh_allowed = [0,10,15,30,60];
if (!in_array($refresh_sec, $refresh_allowed, true)) $refresh_sec = 15;

$extra_head = <<<HTML
  <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
HTML;

$page_title = 'РЈРіСЂРѕР·Р° Р·Р°РґС‹РјР»РµРЅРёСЏ вЂ” РћРџРёРџРђРЎР ';
require_once __DIR__ . '/../config/header.php';
?>

<div class="calc-head" style="margin-bottom:12px">
  <div>
    <h1 style="margin:0 0 6px">РЈРіСЂРѕР·Р° Р·Р°РґС‹РјР»РµРЅРёСЏ / РїСЂРѕРґСѓРєС‚С‹ РіРѕСЂРµРЅРёСЏ</h1>
    <div class="section-lead">РЎР»РѕР№ С‚РѕРєСЃРёС‡РЅРѕСЃС‚Рё РїРѕ РґР°РЅРЅС‹Рј РР‘РђРЎ Рё РјРµС‚РµРѕ + СЃРїРёСЃРѕРє РЅР°СЃРµР»С‘РЅРЅС‹С… РїСѓРЅРєС‚РѕРІ СЃ ETA Рё СѓСЂРѕРІРЅРµРј&nbsp;СѓРіСЂРѕР·С‹</div>
  </div>
  <div class="calc-mode" id="dataModeBox">
    <div class="calc-mode__label" id="dataModeLabel">Р РµР¶РёРј СЌРјСѓР»СЏС†РёРё</div>
    <label class="switch switch--mode">
      <input type="checkbox" id="dataModeToggle" <?= !$ibas_enabled ? 'checked' : '' ?>>
      <span class="switch-ui"><span class="switch-dot" aria-hidden="true"></span></span>
    </label></div>
</div>

<div class="sim-banner" id="simBanner" hidden>Р Р•Р–РРњ Р­РњРЈР›РЇР¦РР вЂ” РґР°РЅРЅС‹Рµ РЅРµ СЂРµР°Р»СЊРЅС‹Рµ</div>

<form method="get" class="calc-toolbar" id="calcToolbar">
  <div class="toolbar-row">
    <label class="toolbar-field">
      <span>РРЅС†РёРґРµРЅС‚</span>
      <select class="select" name="incident_id" id="incidentSelect">
        <?php foreach ($incidents as $row): ?>
          <?php
            $id = (int)$row['id'];
            $status = (string)($row['status'] ?? '');
            $started = (string)($row['started_at'] ?? '');
            $address = (string)($row['address'] ?? '');
            $title = (string)($row['title'] ?? '');
            $extra = $address ?: $title;
            $label = '#' . $id . ($status ? " В· {$status}" : '') . ($started ? " В· {$started}" : '') . ($extra ? " В· {$extra}" : '');
          ?>
          <option value="<?= $id ?>"<?= $id === $incident_id ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="toolbar-field">
      <span>РћРєРЅРѕ (РјРёРЅ)</span>
      <input class="input" type="number" name="window_min" id="windowMinInput" min="1" max="120" value="<?= (int)$window_min ?>">
    </label>
    <label class="toolbar-field">
      <span>Р Р°РґРёСѓСЃ РќРџ (РєРј)</span>
      <select class="select" name="radius_km" id="radiusKmSelect">
        <?php foreach ([5,10,25,50] as $r): ?>
          <option value="<?= $r ?>"<?= $r === $radius_km ? ' selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="toolbar-field">
      <span>РђРІС‚РѕРѕР±РЅРѕРІР»РµРЅРёРµ</span>
      <select class="select" name="refresh_sec" id="refreshSecSelect">
        <option value="0"<?= $refresh_sec === 0 ? ' selected' : '' ?>>Р’С‹РєР»</option>
        <option value="10"<?= $refresh_sec === 10 ? ' selected' : '' ?>>10СЃ</option>
        <option value="15"<?= $refresh_sec === 15 ? ' selected' : '' ?>>15СЃ</option>
        <option value="30"<?= $refresh_sec === 30 ? ' selected' : '' ?>>30СЃ</option>
        <option value="60"<?= $refresh_sec === 60 ? ' selected' : '' ?>>60СЃ</option>
      </select>
    </label>
    <button class="btn btn-primary" type="submit" id="recalcBtn">РџРµСЂРµСЃС‡РёС‚Р°С‚СЊ</button>
    <button class="btn btn-ghost btn-icon" type="button" id="editIncidentBtn" title="Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РёРЅС†РёРґРµРЅС‚">вљ™</button>
    <button class="btn btn-ghost" type="button" id="addIncidentBtn">Р”РѕР±Р°РІРёС‚СЊ</button>
    <button class="btn btn-ghost" type="button" id="toggleDisplay">вљ™ РџР°СЂР°РјРµС‚СЂС‹</button>
    <div class="dirty-indicator" id="dirtyIndicator">Р Р°СЃС‡С‘С‚ Р°РєС‚СѓР°Р»РµРЅ</div>
  </div>
</form>
<div class="smoke-grid">
  <div class="left-col">
    <div class="smoke-panel params-panel" id="leftParamsPanel">
      <div class="collapsed-title">РЎР»РѕРё РґР°РЅРЅС‹С…<br>РћС‚РѕР±СЂР°Р¶РµРЅРёРµ</div>
      <div class="layer-block">
        <div class="feature-title" style="margin-bottom:8px">РЎР»РѕРё РґР°РЅРЅС‹С…</div>
        <div class="layer-list">
          <label class="switch layer-item">
            <input type="checkbox" data-layer="origin" checked>
            <span class="switch-ui"></span>
            <span>РћС‡Р°Рі</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="track" checked>
            <span class="switch-ui"></span>
            <span>РўСЂРµРє Р‘РђРЎ</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="points" checked>
            <span class="switch-ui"></span>
            <span>РўРѕС‡РєРё Р·Р°РјРµСЂРѕРІ</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="tox" checked>
            <span class="switch-ui"></span>
            <span>РўРѕРєСЃРёС‡.</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="settlements">
            <span class="switch-ui"></span>
            <span>РќРџ РЅР° РєР°СЂС‚Рµ</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="wind" checked>
            <span class="switch-ui"></span>
            <span>Р’РµС‚РµСЂ</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="plume_axis">
            <span class="switch-ui"></span>
            <span>РћСЃСЊ С€Р»РµР№С„Р°</span>
          </label>
          <label class="switch layer-item">
            <input type="checkbox" data-layer="hydrants">
            <span class="switch-ui"></span>
            <span>Р“РёРґСЂР°РЅС‚С‹</span>
          </label>
        </div>
        <div class="layer-status" id="hydrantsStatus">Гидранты: —</div>
      </div>
      <div class="layer-block" id="displayBlock">
        <div class="feature-title" style="margin-bottom:8px">РћС‚РѕР±СЂР°Р¶РµРЅРёРµ</div>
        <div class="layer-controls">
          <label class="field">
            <span>РџСЂРѕР·СЂР°С‡РЅРѕСЃС‚СЊ Tox</span>
            <input class="input" type="range" id="toxOpacitySlider" min="0.1" max="0.7" step="0.05" value="0.45">
            <div class="kv-hint" id="toxOpacityVal">45%</div>
          </label>
          <label class="field">
            <span>РџРѕСЂРѕРі Tox</span>
            <input class="input" type="range" id="toxThresholdSlider" min="0" max="1" step="0.05" value="0">
            <div class="kv-hint" id="toxThresholdVal">0.00</div>
          </label>
          <label class="field">
            <span>Р Р°Р·СЂРµС€РµРЅРёРµ СЃРµС‚РєРё (Рј)</span>
            <input class="input" type="range" id="gridCellSlider" min="5" max="300" step="5" value="<?= (int)($cfg['grid']['cell_m'] ?? 10) ?>" <?= $can_adjust ? '' : 'disabled' ?>>
            <div class="kv-hint" id="gridCellVal"><?= (int)($cfg['grid']['cell_m'] ?? 10) ?> Рј</div>
          </label>
          <label class="field">
            <span>РћС…РІР°С‚ СЃРµС‚РєРё (Рј)</span>
            <input class="input" type="range" id="gridMarginSlider" min="300" max="5000" step="100" value="<?= (int)($cfg['grid']['margin_m'] ?? 1200) ?>" <?= $can_adjust ? '' : 'disabled' ?>>
            <div class="kv-hint" id="gridMarginVal"><?= (int)($cfg['grid']['margin_m'] ?? 1200) ?> Рј</div>
          </label>
          <label class="field">
            <span>РРЅС‚РµРЅСЃРёРІРЅРѕСЃС‚СЊ РґС‹РјР°</span>
            <input class="input" type="range" id="smokeIntensitySlider" min="0.6" max="1.6" step="0.05" value="1" <?= $can_adjust ? '' : 'disabled' ?>>
            <div class="kv-hint" id="smokeIntensityVal">100%</div>
          </label>
          <label class="switch" style="align-items:flex-end">
            <input type="checkbox" id="emuNoiseEnable" <?= $can_adjust ? '' : 'disabled' ?>>
            <span class="switch-ui"></span>
            <span>РЁСѓРј В±3%</span>
          </label>
        </div>
        <div class="tox-legend">
          <?php
            $lvl = $cfg['levels'] ?? ['green' => 0.3, 'yellow' => 0.6, 'red' => 0.85];
          ?>
          <div><span class="legend-dot level-green"></span> green в‰¤ <?= h(number_format((float)$lvl['green'], 2, '.', '')) ?></div>
          <div><span class="legend-dot level-yellow"></span> yellow в‰¤ <?= h(number_format((float)$lvl['yellow'], 2, '.', '')) ?></div>
          <div><span class="legend-dot level-red"></span> red в‰¤ <?= h(number_format((float)$lvl['red'], 2, '.', '')) ?></div>
          <div><span class="legend-dot level-darkred"></span> darkred &gt; <?= h(number_format((float)$lvl['red'], 2, '.', '')) ?></div>
          <div class="kv-hint">РўРµРјРЅРµРµ = РІС‹С€Рµ Р·Р°РґС‹РјР»С‘РЅРЅРѕСЃС‚СЊ</div>
        </div>
      </div>
    </div>

  </div>

  <div class="right-col">
  <div class="smoke-panel params-panel" id="rightParamsPanel">
      <div class="tabs">
        <button class="tab-btn" data-tab="meteo" id="tabMeteo">РњРµС‚РµРѕ</button>
        <button class="tab-btn is-active" data-tab="opts" id="tabOpts">РќР°РґСЃС‚СЂРѕР№РєРё</button>
        <button class="tab-btn" data-tab="metrics" id="tabMetrics">РњРёРЅРёвЂ‘РјРµС‚СЂРёРєРё</button>
        <button class="tab-btn" data-tab="record" id="tabRecord">РЈРїСЂР°РІР»РµРЅРёРµ Р·Р°РїРёСЃСЊСЋ</button>
      </div>
      <div class="tab-panel" data-panel="meteo" id="panelMeteo">
        <div class="emu-block">
          <div class="emu-section">
            <div class="emu-title">РњРµС‚РµРѕ</div>
            <div class="emu-grid">
              <label class="field">
                <label>Р’РµС‚РµСЂ (РѕС‚РєСѓРґР°, В°)</label>
                <input class="input" type="number" id="emuWindDirFrom" value="250" min="0" max="359" step="1">
              </label>
              <label class="field">
                <label>РЎРєРѕСЂРѕСЃС‚СЊ (Рј/СЃ)</label>
                <input class="input" type="number" id="emuWindSpeed" value="5" min="0" max="15" step="0.1">
              </label>
              <label class="field">
                <label>Р”Р°РІР»РµРЅРёРµ (hPa)</label>
                <input class="input" type="number" id="emuPressure" value="1013" min="960" max="1040" step="1">
              </label>
              <label class="field">
                <label>РўРµРјРїРµСЂР°С‚СѓСЂР° (В°C)</label>
                <input class="input" type="number" id="emuTemp" value="10" min="-30" max="60" step="0.5">
              </label>
              <label class="field">
                <label>Р’Р»Р°Р¶РЅРѕСЃС‚СЊ (%)</label>
                <input class="input" type="number" id="emuRH" value="65" min="0" max="100" step="1">
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-panel is-active" data-panel="opts" id="panelOpts">
      <div class="emu-block">

        <div class="emu-section">
          <div class="emu-title">РљРѕРѕСЂРґРёРЅР°С‚С‹ С‚РѕС‡РєРё</div>
          <div class="emu-grid">
            <label class="field">
              <label>РСЃС‚РѕС‡РЅРёРє</label>
              <select class="select" id="emuCoordMode">
                <option value="origin">РћС‡Р°Рі</option>
                <option value="marker">РњР°СЂРєРµСЂ</option>
                <option value="draw">Р РёСЃРѕРІР°РЅРёРµ</option>
                <option value="orbit">РђРІС‚РѕвЂ‘РѕСЂР±РёС‚Р°</option>
              </select>
            </label>
            <label class="field">
              <label>Р’С‹СЃРѕС‚Р° РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ (Рј)</label>
              <input class="input" type="number" id="emuDefaultAlt" value="30" min="5" max="150" step="1">
            </label>
          </div>
          <div class="emu-actions">
            <button class="btn btn-ghost" type="button" id="emuDrawEnableBtn">Р РёСЃРѕРІР°С‚СЊ РјР°СЂС€СЂСѓС‚</button>
            <button class="btn btn-ghost" type="button" id="emuDrawClearBtn">РћС‡РёСЃС‚РёС‚СЊ РјР°СЂС€СЂСѓС‚</button>
            <button class="btn btn-ghost" type="button" id="emuDrawGenSamplesBtn">РЎРіРµРЅРµСЂРёСЂРѕРІР°С‚СЊ Р·Р°РјРµСЂС‹</button>
          </div>
          <div class="emu-grid emu-orbit" id="emuOrbitFields">
            <label class="field">
              <label>Р Р°РґРёСѓСЃ (Рј)</label>
              <input class="input" type="number" id="emuOrbitRadius" value="150" min="10" max="2000" step="10">
            </label>
            <label class="field">
              <label>Р’С‹СЃРѕС‚Р° (Рј)</label>
              <input class="input" type="number" id="emuOrbitAlt" value="60" min="0" max="500" step="1">
            </label>
            <label class="field">
              <label>РЎРєРѕСЂРѕСЃС‚СЊ (Рј/СЃ)</label>
              <input class="input" type="number" id="emuOrbitSpeed" value="8" min="1" max="30" step="0.5">
            </label>
            <label class="field">
              <label>РЁР°Рі (СЃРµРє)</label>
              <input class="input" type="number" id="emuOrbitStep" value="5" min="1" max="60" step="1">
            </label>
          </div>
        </div>

      </div>
      </div>
      <div class="tab-panel" data-panel="metrics" id="panelMetrics">
        <div class="emu-block">
          <div class="emu-section">
            <div class="emu-title">РњРёРЅРёвЂ‘РјРµС‚СЂРёРєРё <span class="sim-badge" id="simBadgeMetrics">РЎРёРјСѓР»СЏС†РёСЏ</span></div>
            <div class="emu-grid">
              <div class="kv"><div class="kv-k">co_idx</div><div class="kv-v" id="mCoIdx">0.00</div></div>
              <div class="kv"><div class="kv-k">smoke_idx</div><div class="kv-v" id="mSmokeIdx">0.00</div></div>
              <div class="kv"><div class="kv-k">voc_idx</div><div class="kv-v" id="mVocIdx">0.00</div></div>
              <div class="kv"><div class="kv-k">tox_idx</div><div class="kv-v" id="mToxIdx">0.00</div></div>
              <div class="kv"><div class="kv-k">confidence</div><div class="kv-v" id="mConfEst">0.00</div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-panel" data-panel="record" id="panelRecord">
        <div class="emu-block">
          <div class="emu-section">
            <div class="emu-title">РЈРїСЂР°РІР»РµРЅРёРµ Р·Р°РїРёСЃСЊСЋ</div>
            <div class="emu-actions">
              <button class="btn btn-ghost" type="button" id="emuPushOnceBtn">РћС‚РїСЂР°РІРёС‚СЊ 1 С‚РѕС‡РєСѓ</button>
              <button class="btn btn-ghost" type="button" id="emuStreamToggle">РЎС‚Р°СЂС‚ РїРѕС‚РѕРє</button>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>

<div class="smoke-panel smoke-panel--wide" style="margin-top:12px">
  <div class="map-wrap">
    <div id="smokeMap" class="map-canvas" aria-label="РљР°СЂС‚Р° СѓРіСЂРѕР·С‹ Р·Р°РґС‹РјР»РµРЅРёСЏ"></div>
    <div class="map-overlay map-overlay--wind">
      <img id="windArrowImg" src="/assets/РЎС‚СЂРµР»РєР° РЅР°РїСЂР°РІР»РµРЅРёСЏ РІРµС‚СЂР°.png" alt="РќР°РїСЂР°РІР»РµРЅРёРµ РІРµС‚СЂР°">
    </div>
    <div class="map-watermark" id="simWatermark" hidden>Р­РњРЈР›РЇР¦РРЇ</div>
  </div>
</div>

<div class="smoke-panel smoke-panel--wide" style="margin-top:12px">
  <div class="feature-title">РўР°Р±Р»РёС†Р° РґР°РЅРЅС‹С… <span class="sim-badge" id="tableModeBadge">РЎРёРјСѓР»СЏС†РёСЏ</span></div>
  <div class="table-wrap table-wrap--telemetry table-wrap--full">
    <table class="table table-compact table-sticky" id="telemetryTable">
      <thead>
        <tr>
          <th>в„–</th>
          <th>Р’СЂРµРјСЏ</th>
          <th>Lat</th>
          <th>Lon</th>
          <th>Р’С‹СЃРѕС‚Р° (Рј)</th>
          <th>MQ-2</th>
          <th>MQ-5</th>
          <th>MQ-7 (ppm)</th>
          <th>MQ-135</th>
          <th>NAP07</th>
          <th>Radiation</th>
          <th>Temp</th>
          <th>RH</th>
        </tr>
      </thead>
      <tbody id="telemetryTbody"></tbody>
    </table>
  </div>
</div>

<div class="modal-backdrop" id="incidentModal" aria-hidden="true">
  <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="incidentModalTitle">
    <div class="modal-head">
      <div class="modal-title" id="incidentModalTitle">РЎРѕР·РґР°С‚СЊ РёРЅС†РёРґРµРЅС‚</div>
      <button class="modal-close" type="button" data-close-modal="incidentModal">вњ•</button>
    </div>
    <form id="incidentCreateForm" method="post" action="/api/incidents/create.php" style="display:grid;gap:12px" onsubmit="return false;" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="incident_id" id="incidentIdField" value="">
      <div class="alert bad" id="incidentError" style="display:none"></div>
      <div class="modal-section">
        <div class="modal-section-title">РЎРІРµРґРµРЅРёСЏ</div>
        <div class="form-grid">
          <div class="field">
            <label>РўРёРї РёРЅС†РёРґРµРЅС‚Р°</label>
            <select class="select" name="type" required>
              <option value="urban">РџРѕР¶Р°СЂ (РіРѕСЂРѕРґ)</option>
              <option value="wild">РџРѕР¶Р°СЂ (РїСЂРёСЂРѕРґРЅС‹Р№)</option>
              <option value="chem">РҐРёРј. Р·Р°РіСЂСЏР·РЅРµРЅРёРµ</option>
              <option value="other">Р”СЂСѓРіРѕРµ</option>
            </select>
          </div>
          <div class="field">
            <label>РЎС‚Р°С‚СѓСЃ / СЃС‚Р°РґРёСЏ</label>
            <select class="select" name="status" required>
              <option value="operational">РћРїРµСЂР°С‚РёРІРЅР°СЏ</option>
              <option value="localization">Р›РѕРєР°Р»РёР·Р°С†РёСЏ</option>
              <option value="liquidation">Р›РёРєРІРёРґР°С†РёСЏ</option>
              <option value="closed">Р—Р°РєСЂС‹С‚</option>
            </select>
          </div>
          <div class="field">
            <label>РџСЂРёРѕСЂРёС‚РµС‚</label>
            <select class="select" name="priority" required>
              <option value="low">РќРёР·РєРёР№</option>
              <option value="medium" selected>РЎСЂРµРґРЅРёР№</option>
              <option value="high">Р’С‹СЃРѕРєРёР№</option>
            </select>
          </div>
          <div class="field">
            <label>Р’СЂРµРјСЏ РЅР°С‡Р°Р»Р° (t0)</label>
            <input class="input" type="datetime-local" name="started_at" value="<?= h(date('Y-m-d\\TH:i')) ?>" required>
          </div>
        </div>
        <div class="field">
          <label>РљСЂР°С‚РєРѕРµ РѕРїРёСЃР°РЅРёРµ</label>
          <textarea class="input textarea" name="summary" rows="3" placeholder="С‡С‚Рѕ РіРѕСЂРёС‚ / РјР°СЃС€С‚Р°Р±С‹ / С‡С‚Рѕ С‚СЂРµР±СѓРµС‚СЃСЏ"></textarea>
        </div>
      </div>

      <div class="modal-section">
        <div class="modal-section-title">Р›РѕРєР°С†РёСЏ</div>
        <div class="form-grid">
          <div class="field">
            <label>РљРѕРѕСЂРґРёРЅР°С‚С‹ РѕС‡Р°РіР° (lat)</label>
            <input class="input" type="text" name="lat0" id="incidentLat" value="<?= number_format($lat0, 6, '.', '') ?>" required>
          </div>
          <div class="field">
            <label>РљРѕРѕСЂРґРёРЅР°С‚С‹ РѕС‡Р°РіР° (lon)</label>
            <input class="input" type="text" name="lon0" id="incidentLon" value="<?= number_format($lon0, 6, '.', '') ?>" required>
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label>РђРґСЂРµСЃ / СЂР°Р№РѕРЅ</label>
            <input class="input" type="text" name="address" placeholder="РІСЂСѓС‡РЅСѓСЋ РёР»Рё Р°РІС‚Рѕ">
          </div>
          <div class="field" style="display:flex;align-items:flex-end">
            <button class="btn btn-ghost" type="button" id="pickOnMapBtn">РљР»РёРєРЅРёС‚Рµ РїРѕ РєР°СЂС‚Рµ</button>
          </div>
        </div>
      </div>

      <div class="modal-section">
      <div class="modal-section-title">РџР°СЂР°РјРµС‚СЂС‹ СЂР°СЃС‡С‘С‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ)</div>
        <div class="form-grid">
          <div class="field">
            <label>Р РµР¶РёРј СЂР°СЃС‡С‘С‚Р°</label>
            <select class="select" name="calc_mode">
              <option value="index" selected>РРЅРґРµРєСЃРЅС‹Р№ (MQ + NAP07)</option>
              <option value="co">РќРѕСЂРјР°С‚РёРІРЅС‹Р№ CO (РїРѕР·Р¶Рµ)</option>
            </select>
          </div>
          <div class="field">
            <label>Р Р°РґРёСѓСЃ РќРџ (РєРј)</label>
            <select class="select" name="default_radius_km">
              <?php foreach ([5,10,25,50] as $r): ?>
                <option value="<?= $r ?>"<?= $r === $radius_km ? ' selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>РћРєРЅРѕ РґР°РЅРЅС‹С… (РјРёРЅ)</label>
            <input class="input" type="number" name="default_window_min" min="1" max="120" value="<?= (int)$window_min ?>">
          </div>
          <div class="field">
            <label>РќР°Р·РЅР°С‡РµРЅРЅР°СЏ РР‘РђРЎ/Р±РѕСЂС‚</label>
            <select class="select" name="uav_id">
              <option value="BAS-01">Р‘РђРЎ-01</option>
              <option value="BAS-02">Р‘РђРЎ-02</option>
              <option value="BAS-03">Р‘РђРЎ-03</option>
            </select>
          </div>
          <input type="hidden" name="meteo_source" value="rhm">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button class="btn btn-ghost" type="button" data-close-modal="incidentModal">РћС‚РјРµРЅР°</button>
        <button class="btn btn-primary" type="button" id="incidentSaveBtn">РЎРѕС…СЂР°РЅРёС‚СЊ</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="pickMapModal" aria-hidden="true">
  <div class="modal-window modal-window--map" role="dialog" aria-modal="true" aria-labelledby="pickMapModalTitle">
    <div class="modal-head">
      <div class="modal-title" id="pickMapModalTitle">Р’С‹Р±РѕСЂ РєРѕРѕСЂРґРёРЅР°С‚ РѕС‡Р°РіР°</div>
      <button class="modal-close" type="button" data-close-modal="pickMapModal">вњ•</button>
    </div>
    <div id="pickMapCanvas" class="map-pick-canvas" aria-label="РљР°СЂС‚Р° РІС‹Р±РѕСЂР° РєРѕРѕСЂРґРёРЅР°С‚"></div>
    <div class="modal-actions">
      <div class="kv-hint" id="pickMapHint">РљР»РёРєРЅРёС‚Рµ РїРѕ РєР°СЂС‚Рµ, С‡С‚РѕР±С‹ РІС‹Р±СЂР°С‚СЊ С‚РѕС‡РєСѓ</div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-ghost" type="button" data-close-modal="pickMapModal">РћС‚РјРµРЅР°</button>
        <button class="btn btn-primary" type="button" id="pickMapConfirm" disabled>РџРѕРґС‚РІРµСЂРґРёС‚СЊ</button>
      </div>
    </div>
  </div>
</div>

<script>
window.SmokeThreatConfig = {
  incidentId: <?= (int)$incident_id ?>,
  windowMin: <?= (int)$window_min ?>,
  radiusKm: <?= (int)$radius_km ?>,
  center: [<?= number_format($lat0, 6, '.', '') ?>, <?= number_format($lon0, 6, '.', '') ?>],
  refreshSec: <?= (int)$refresh_sec ?>,
  levels: <?= json_encode($cfg['levels'] ?? ['green' => 0.3, 'yellow' => 0.6, 'red' => 0.85]) ?>,
  grid: <?= json_encode($cfg['grid'] ?? ['cell_m' => 150, 'margin_m' => 900, 'ax' => 600, 'ay' => 200, 'p' => 2, 'eps' => 0.001, 'max_radius_m' => 1500]) ?>,
  windDirFrom: <?= !empty($cfg['wind_dir_from']) ? 'true' : 'false' ?>,
  windLowMs: <?= json_encode($cfg['wind_low_ms'] ?? 1) ?>,
  refs: <?= json_encode([
    'ppm_ref' => $cfg['ppm_ref'] ?? 300,
    'raw_ref' => $cfg['raw_ref'] ?? 1200,
    'nap07_ref' => $cfg['nap07_ref'] ?? 300,
    'voc_range' => $cfg['voc_range'] ?? 500,
  ]) ?>,
  weights: <?= json_encode($cfg['tox_weights'] ?? ['smoke' => 0.55, 'co' => 0.35, 'voc' => 0.10]) ?>,
  confidenceCfg: <?= json_encode($cfg['confidence'] ?? []) ?>,
  canAdjust: <?= $can_adjust ? 'true' : 'false' ?>,
  ibasEnabled: <?= $ibas_enabled ? 'true' : 'false' ?>,
  defaultMode: <?= $ibas_enabled ? "'IBAS'" : "'SIM'" ?>,
  incidents: <?= json_encode($incidents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  csrf: <?= json_encode((string)($_SESSION['csrf'] ?? '')) ?>,
};
</script>
<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script src="/assets/smoke_threat.js?v=<?= @filemtime(__DIR__ . '/../assets/smoke_threat.js') ?: time() ?>"></script>
<script>
setTimeout(function () {
  if (window.__SMOKE_THREAT_BOOTSTRAPPED) return;
  if (!window.L) return;
  var el = document.getElementById('smokeMap');
  if (!el || el.classList.contains('leaflet-container')) return;
  try {
    var cfg = window.SmokeThreatConfig || { center: [59.9342, 30.3351] };
    var map = L.map(el, { zoomControl: true, attributionControl: false, zoomSnap: 1, zoomDelta: 1 }).setView(cfg.center || [59.9342, 30.3351], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '' }).addTo(map);
  } catch (e) {}
}, 600);
</script>
<script>
(function () {
  var toggle = document.getElementById('dataModeToggle');
  var label = document.getElementById('dataModeLabel');
  var hint = document.getElementById('dataModeHint');
  var badge = document.getElementById('tableModeBadge');
  var mini = document.getElementById('simBadgeMetrics');
  if (!toggle) return;
  var update = function () {
    var sim = !!toggle.checked;
    var text = sim ? 'Р РµР¶РёРј СЌРјСѓР»СЏС†РёРё' : 'Р РµР¶РёРј РР‘РђРЎ';
    if (label) label.textContent = text;
    if (hint) hint.textContent = text;
    if (badge) {
      badge.textContent = sim ? 'Р­РјСѓР»СЏС†РёСЏ' : 'Р”Р°РЅРЅС‹Рµ РР‘РђРЎ';
      badge.classList.toggle('is-ibas', !sim);
    }
    if (mini) {
      mini.textContent = sim ? 'Р­РјСѓР»СЏС†РёСЏ' : 'Р”Р°РЅРЅС‹Рµ РР‘РђРЎ';
      mini.classList.toggle('is-ibas', !sim);
    }
  };
  toggle.addEventListener('change', update);
  update();
})();
</script>
<script>
(function () {
  var arrow = document.getElementById('windArrowImg');
  var windCb = document.querySelector('[data-layer="wind"]');
  var dirInput = document.getElementById('emuWindDirFrom');
  var modeToggle = document.getElementById('dataModeToggle');
  if (!arrow) return;
  var updateArrow = function () {
    var windOn = !windCb || !!windCb.checked;
    if (!windOn) {
      arrow.style.display = 'none';
      arrow.style.visibility = 'hidden';
      arrow.style.opacity = '0';
      arrow.style.transform = 'rotate(0deg)';
      return;
    }
    arrow.style.display = 'block';
    arrow.style.visibility = 'visible';
    arrow.style.transformOrigin = '50% 50%';
  var isSim = modeToggle ? !!modeToggle.checked : true;
  var meta = window.__SMOKE_META || null;
  var v = isSim && dirInput ? Number(dirInput.value) : (meta ? Number(meta.wind_dir_deg) : NaN);
  if (!isFinite(v)) {
    arrow.style.opacity = '0.4';
    return;
  }
    var dirTo = (v + 180) % 360;
    arrow.style.opacity = '1';
    arrow.style.transform = 'rotate(' + dirTo + 'deg)';
  };
  if (windCb) windCb.addEventListener('change', updateArrow);
  if (dirInput) {
    dirInput.addEventListener('input', updateArrow);
    dirInput.addEventListener('change', updateArrow);
    dirInput.addEventListener('keyup', updateArrow);
  }
  if (modeToggle) modeToggle.addEventListener('change', updateArrow);
  setTimeout(updateArrow, 200);
  setInterval(updateArrow, 500);
})();
</script>
<script>
(function () {
  var addBtn = document.getElementById('addIncidentBtn');
  var editBtn = document.getElementById('editIncidentBtn');
  var modal = document.getElementById('incidentModal');
  var form = document.getElementById('incidentCreateForm');
  if (!modal || !form) return;
  var title = document.getElementById('incidentModalTitle');
  var idField = document.getElementById('incidentIdField');
  var select = document.getElementById('incidentSelect');
  var incidents = (window.SmokeThreatConfig && window.SmokeThreatConfig.incidents) || [];
  var findIncident = function (id) {
    for (var i = 0; i < incidents.length; i++) {
      if (Number(incidents[i].id) === Number(id)) return incidents[i];
    }
    return null;
  };
  var openModal = function (mode) {
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    var meteo = document.getElementById('meteoSource');
    var fields = document.getElementById('manualMeteoFields');
    if (mode === 'edit') {
      if (title) title.textContent = 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РёРЅС†РёРґРµРЅС‚';
      form.action = '/api/incidents/update.php';
      var id = select ? Number(select.value || 0) : 0;
      if (idField) idField.value = String(id || '');
      var inc = findIncident(id);
      if (inc) {
        form.querySelector('[name=\"type\"]').value = inc.type || 'urban';
        form.querySelector('[name=\"status\"]').value = inc.status || 'operational';
        form.querySelector('[name=\"priority\"]').value = inc.priority || 'medium';
        form.querySelector('[name=\"started_at\"]').value = inc.started_at ? String(inc.started_at).replace(' ', 'T') : '';
        form.querySelector('[name=\"summary\"]').value = inc.summary || '';
        form.querySelector('[name=\"lat0\"]').value = inc.lat0 ?? '';
        form.querySelector('[name=\"lon0\"]').value = inc.lon0 ?? '';
        form.querySelector('[name=\"address\"]').value = inc.address || '';
        form.querySelector('[name=\"calc_mode\"]').value = inc.calc_mode || 'index';
        form.querySelector('[name=\"default_radius_km\"]').value = inc.default_radius_km || 25;
        form.querySelector('[name=\"default_window_min\"]').value = inc.default_window_min || 15;
        form.querySelector('[name=\"uav_id\"]').value = inc.uav_id || 'BAS-01';
        form.querySelector('[name=\"meteo_source\"]').value = inc.meteo_source || 'rhm';
        var windDir = form.querySelector('[name=\"wind_dir_deg\"]');
        var windSpeed = form.querySelector('[name=\"wind_speed_ms\"]');
        if (windDir) windDir.value = inc.wind_dir_deg ?? '';
        if (windSpeed) windSpeed.value = inc.wind_speed_ms ?? '';
      }
    } else {
      if (title) title.textContent = 'РЎРѕР·РґР°С‚СЊ РёРЅС†РёРґРµРЅС‚';
      form.action = '/api/incidents/create.php';
      if (idField) idField.value = '';
      form.reset();
      var dt = new Date();
      var pad = function (n) { return String(n).padStart(2, '0'); };
      form.querySelector('[name=\"started_at\"]').value =
        dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    }
    if (meteo && fields) fields.style.display = meteo.value === 'manual' ? 'grid' : 'none';
  };
  addBtn && addBtn.addEventListener('click', function () { openModal('create'); });
  editBtn && editBtn.addEventListener('click', function () { openModal('edit'); });
  document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-close-modal');
      var target = id ? document.getElementById(id) : null;
      if (!target) return;
      target.style.display = 'none';
      target.setAttribute('aria-hidden', 'true');
    });
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form);
    fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.id) {
          var url = new URL(window.location.href);
          url.searchParams.set('incident_id', data.id);
          window.location.href = url.toString();
        } else {
          var err = document.getElementById('incidentError');
          if (err) { err.style.display = 'block'; err.textContent = (data && data.error) ? data.error : 'РћС€РёР±РєР° СЃРѕС…СЂР°РЅРµРЅРёСЏ'; }
        }
      })
      .catch(function () {
        var err = document.getElementById('incidentError');
        if (err) { err.style.display = 'block'; err.textContent = 'РћС€РёР±РєР° СЃРѕС…СЂР°РЅРµРЅРёСЏ'; }
      });
  });
  var saveBtn = document.getElementById('incidentSaveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });
  }
})();
</script>
<script>
(function () {
  var btn = document.getElementById('pickOnMapBtn');
  var modal = document.getElementById('pickMapModal');
  var canvas = document.getElementById('pickMapCanvas');
  var confirmBtn = document.getElementById('pickMapConfirm');
  var latEl = document.getElementById('incidentLat');
  var lonEl = document.getElementById('incidentLon');
  var hint = document.getElementById('pickMapHint');
  if (!btn || !modal || !canvas) return;
  var pickMap = null;
  var pickMarker = null;
  var center = (window.SmokeThreatConfig && window.SmokeThreatConfig.center) || [59.9342, 30.3351];
  var open = function () {
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    if (!window.L) {
      if (hint) hint.textContent = 'РљР°СЂС‚Р° РЅРµРґРѕСЃС‚СѓРїРЅР°';
      return;
    }
    setTimeout(function () {
      if (!pickMap) {
        pickMap = L.map(canvas, { attributionControl: false, zoomSnap: 1, zoomDelta: 1 }).setView(center, 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '' }).addTo(pickMap);
        pickMap.on('click', function (e) {
          if (!e || !e.latlng) return;
          if (latEl) latEl.value = e.latlng.lat.toFixed(6);
          if (lonEl) lonEl.value = e.latlng.lng.toFixed(6);
          if (!pickMarker) {
            var icon = L.divIcon({ className: 'pick-marker', html: '<div class="pick-dot"></div>', iconSize: [18, 18] });
            pickMarker = L.marker(e.latlng, { icon: icon }).addTo(pickMap);
          } else {
            pickMarker.setLatLng(e.latlng);
          }
          if (confirmBtn) confirmBtn.disabled = false;
        });
      } else {
        pickMap.invalidateSize();
      }
    }, 50);
  };
  btn.addEventListener('click', open);
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
    });
  }
})();
</script>
<script>
(function () {
  var btn = document.getElementById('toggleDisplay');
  var left = document.getElementById('leftParamsPanel');
  var right = document.getElementById('rightParamsPanel');
  if (!btn || !left || !right) return;
  btn.addEventListener('click', function () {
    left.classList.toggle('is-collapsed');
    right.classList.toggle('is-collapsed');
  });
})();
</script>
<script>
(function () {
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.tabs .tab-btn'));
  if (!tabs.length) return;
  var panels = Array.prototype.slice.call(document.querySelectorAll('.tab-panel'));
  var activate = function (tab) {
    tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
    var target = tab.getAttribute('data-tab');
    panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-panel') === target); });
  };
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { activate(t); });
  });
})();
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>









