(() => {
  const cfg = window.SmokeThreatConfig || null;
  if (!cfg) return;

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
  const rad = (deg) => (deg * Math.PI) / 180;
  const fmt = (v, digits = 2) => {
    if (v === null || v === undefined || Number.isNaN(v)) return 'вЂ”';
    return Number(v).toFixed(digits);
  };
  const fmtNum = (v, digits = 2) => {
    if (v === null || v === undefined || Number.isNaN(v)) return 'вЂ”';
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(Number(v));
  };

  const toXY = (lat, lon, lat0, lon0) => {
    const R = 6371000;
    const lat0r = rad(lat0);
    const dx = rad(lon - lon0) * Math.cos(lat0r) * R;
    const dy = rad(lat - lat0) * R;
    return [dx, dy];
  };

  const fromXY = (x, y, lat0, lon0) => {
    const R = 6371000;
    const lat0r = rad(lat0);
    const lat = lat0 + (y / R) * (180 / Math.PI);
    const lon = lon0 + (x / (R * Math.cos(lat0r))) * (180 / Math.PI);
    return [lat, lon];
  };

  const rotate = (dx, dy, theta) => {
    const c = Math.cos(theta);
    const s = Math.sin(theta);
    const x = dx * c + dy * s;
    const y = -dx * s + dy * c;
    return [x, y];
  };

  const windToTheta = (dirFrom) => {
    const windTo = ((Number(dirFrom) || 0) + 180) % 360;
    let theta = 90 - windTo;
    theta %= 360;
    if (theta < 0) theta += 360;
    return rad(theta);
  };

  const refs = cfg.refs || {
    ppm_ref: 300,
    raw_ref: 1200,
    nap07_ref: 300,
    voc_range: 500,
  };
  const weights = cfg.weights || { smoke: 0.55, co: 0.35, voc: 0.1 };
  const confCfg = cfg.confidenceCfg || {
    base: 0.5,
    meteo_bonus: 0.2,
    ppm_bonus: 0.2,
    low_wind_penalty: 0.2,
    few_points_penalty: 0.2,
    few_points_min: 10,
  };
  const levels = cfg.levels || { green: 0.3, yellow: 0.6, red: 0.85 };
  const gridCfg = cfg.grid || { cell_m: 150, margin_m: 900 };

  const levelColor = (lvl) => {
    if (lvl === 'darkred') return '#ef4444';
    if (lvl === 'red') return '#f87171';
    if (lvl === 'yellow') return '#facc15';
    return '#34d399';
  };

  const calcLevel = (tox) => {
    if (tox > (levels.red ?? 0.85)) return 'darkred';
    if (tox > (levels.yellow ?? 0.6)) return 'red';
    if (tox > (levels.green ?? 0.3)) return 'yellow';
    return 'green';
  };

  const smokeColor = (value) => {
    const v = clamp(Number(value) || 0, 0, 1);
    const intensity = clamp(state.smokeIntensity ?? 1, 0.6, 1.6);
    const alpha = clamp(Math.pow(v, 0.75) * intensity, 0, 1);
    return `rgba(0,0,0,${alpha.toFixed(3)})`;
  };

  const calcIndexes = (sample) => {
    const hasPpm = sample.mq7_ppm !== null && Number.isFinite(sample.mq7_ppm);
    const coVal = hasPpm ? sample.mq7_ppm : (sample.mq7_raw ?? null);
    const ref = hasPpm ? refs.ppm_ref : refs.raw_ref;
    let coIdx = 0;
    if (coVal && ref) coIdx = clamp(Math.log(1 + coVal) / Math.log(1 + ref), 0, 1);
    let smokeIdx = 0;
    if (Number.isFinite(sample.nap07_raw)) {
      smokeIdx = clamp((sample.nap07_raw) / (refs.nap07_ref || 1), 0, 1);
    }
    let vocIdx = 0;
    if (Number.isFinite(sample.mq135_raw)) {
      vocIdx = clamp(sample.mq135_raw / (refs.voc_range || 1), 0, 1);
    }
    const toxIdx = (weights.smoke ?? 0.55) * smokeIdx + (weights.co ?? 0.35) * coIdx + (weights.voc ?? 0.1) * vocIdx;
    return { coIdx, smokeIdx, vocIdx, toxIdx };
  };

  const calcConfidence = (hasMeteo, hasPpm, windSpeed, pointCount) => {
    let c = confCfg.base ?? 0.5;
    if (hasMeteo) c += confCfg.meteo_bonus ?? 0.2;
    if (hasPpm) c += confCfg.ppm_bonus ?? 0.2;
    if (windSpeed < (cfg.windLowMs ?? 1)) c -= confCfg.low_wind_penalty ?? 0.2;
    if (pointCount < (confCfg.few_points_min ?? 10)) c -= confCfg.few_points_penalty ?? 0.2;
    return clamp(c, 0, 1);
  };

  const sField = (x, y, windSpeed, rh, pressure) => {
    const U = clamp(Number(windSpeed) || 0, 0, 15);
    const sigmaX = 600 + 140 * U;
    const sigmaY = 260 + 45 * U;
    const sxDown = sigmaX;
    const sxUp = Math.max(60, sigmaX * 0.55);
    const syUp = Math.max(60, sigmaY * 1.1);
    const down = Math.exp(-(x * x) / (2 * sxDown * sxDown) - (y * y) / (2 * sigmaY * sigmaY));
    const up = 0.2 * Math.exp(-(x * x) / (2 * sxUp * sxUp) - (y * y) / (2 * syUp * syUp));
    const bias = 1 / (1 + Math.exp(-x / Math.max(1, sigmaX * 0.35)));
    const plume = down * bias + up * (1 - bias);
    const originSigma = 220;
    const originBlob = 0.30 * Math.exp(-(x * x + y * y) / (2 * originSigma * originSigma));
    let S = plume + originBlob;
    const M = clamp(1 + 0.004 * ((Number(rh) || 0) - 50) - 0.002 * ((Number(pressure) || 1013) - 1013), 0.7, 1.4);
    S = clamp(S * M * 0.72, 0, 1);
    return S;
  };

  const getWheelStep = (input) => {
    const stepAttr = input.getAttribute('step');
    if (stepAttr && stepAttr !== 'any') {
      const s = Number(stepAttr);
      if (Number.isFinite(s) && s > 0) return s;
    }
    const min = Number(input.getAttribute('min'));
    const max = Number(input.getAttribute('max'));
    const range = Number.isFinite(min) && Number.isFinite(max) ? Math.abs(max - min) : 100;
    if (range <= 1) return 0.01;
    if (range <= 10) return 0.1;
    if (range <= 100) return 1;
    if (range <= 500) return 5;
    if (range <= 2000) return 10;
    if (range <= 10000) return 50;
    return 100;
  };

  const bindNumberWheel = () => {
    document.addEventListener('wheel', (e) => {
      const target = e.target;
      if (!(target instanceof HTMLInputElement)) return;
      if (target.type !== 'number') return;
      if (target.disabled || target.readOnly) return;
      if (!target.matches(':hover')) return;
      e.preventDefault();
      const step = getWheelStep(target);
      const dir = e.deltaY < 0 ? 1 : -1;
      const minAttr = target.getAttribute('min');
      const maxAttr = target.getAttribute('max');
      const min = minAttr !== null && minAttr !== '' ? Number(minAttr) : -Infinity;
      const max = maxAttr !== null && maxAttr !== '' ? Number(maxAttr) : Infinity;
      const cur = Number(target.value || 0);
      let next = cur + dir * step;
      if (Number.isFinite(min)) next = Math.max(min, next);
      if (Number.isFinite(max)) next = Math.min(max, next);
      target.value = String(next);
      target.dispatchEvent(new Event('input', { bubbles: true }));
      target.dispatchEvent(new Event('change', { bubbles: true }));
    }, { passive: false });
  };

  const xorshift32 = (seed) => {
    let x = seed >>> 0;
    x ^= x << 13; x >>>= 0;
    x ^= x >>> 17; x >>>= 0;
    x ^= x << 5; x >>>= 0;
    return x >>> 0;
  };

  const deterministicNoise = (incidentId, index, tsSec, stepSec) => {
    const seed = (incidentId * 1000003 + index * 9176 + Math.floor(tsSec / stepSec)) >>> 0;
    const u = xorshift32(seed) / 4294967296;
    return u * 2 - 1;
  };

  const generateSensors = (point, index, tsSec, opts) => {
    const lat0 = cfg.center[0];
    const lon0 = cfg.center[1];
    const windFrom = Number(opts.windDirFrom) || 0;
    const theta = windToTheta(windFrom);
    const [dx, dy] = toXY(point.lat, point.lon, lat0, lon0);
    const [x, y] = rotate(dx, dy, theta);
    const S = sField(x, y, opts.windSpeed, opts.rh, opts.pressure);
    const noise = opts.noise ? deterministicNoise(cfg.incidentId, index, tsSec, opts.stepSec) : 0;
    const co = clamp(15 + 340 * Math.pow(S, 1.05) + 25 * noise, 0, 600);
    const nap = clamp(40 + 520 * Math.pow(S, 0.95) + 35 * noise, 0, 700);
    const mq135 = clamp(180 + 1050 * Math.pow(S, 1.1) + 80 * noise, 0, 2000);
    const mq2 = clamp(120 + 1150 * Math.pow(S, 1.0) + 90 * noise, 0, 2000);
    const mq5 = clamp(100 + 750 * Math.pow(S, 0.9) + 70 * noise, 0, 2000);
    const radVal = clamp(0.12 + 0.02 * noise, 0.05, 0.30);
    const temp = clamp((Number(opts.temp) || 10) + 0.5 * noise, -30, 60);
    const rh = clamp((Number(opts.rh) || 65) + 2 * noise, 0, 100);
    return {
      mq7_ppm: co,
      nap07_raw: nap,
      mq135_raw: mq135,
      mq2_raw: mq2,
      mq5_raw: mq5,
      radiation: radVal,
      temp_c: temp,
      rh_pct: rh,
    };
  };

  const storageKey = 'calc01_data_mode';
  let initialMode = cfg.defaultMode || 'SIM';
  if (!cfg.ibasEnabled) initialMode = 'SIM';
  if (cfg.ibasEnabled) {
    const saved = localStorage.getItem(storageKey);
    if (saved === 'SIM' || saved === 'IBAS') initialMode = saved;
  }

  const state = {
    dataMode: initialMode,
    emuEnabled: initialMode === 'SIM',
    isDirty: false,
    emuSamples: [],
    emuDrawPoints: [],
    emuDrawMarkers: [],
    emuDrawLine: null,
    emuDrawActive: false,
    emuMarker: null,
    emuStreamTimer: null,
    emuStreamIndex: 0,
    lastToxGeojson: null,
    settlements: [],
    toxOpacity: 0.45,
    toxThreshold: 0,
    gridCell: (cfg.grid?.cell_m ?? 100),
    gridMargin: (cfg.grid?.margin_m ?? 1200),
    smokeIntensity: 1,
    meta: null,
    selectedRow: null,
  };

  const incidentsById = new Map();
  if (Array.isArray(cfg.incidents)) {
    cfg.incidents.forEach((row) => {
      if (row && row.id !== undefined) incidentsById.set(Number(row.id), row);
    });
  }

  const mapEl = $('#smokeMap');
  if (!mapEl) return;

  let map = null;
  let originLayer = null;
  let trackLayer = null;
  let trackLabelLayer = null;
  let basLayer = null;
  let pointsLayer = null;
  let toxLayer = null;
  let settlementsLayer = null;
  let windLayer = null;
  let plumeAxisLayer = null;
  let hydrantsLayer = null;
  let hydrantsFitted = false;
  let hydrantFallbackIcon = null;
  let hydrantIcon = null;
  let layerMap = {};
  let basIcon = null;
  let originIcon = null;

  const ensureLeaflet = () => {
    if (window.L) return Promise.resolve(true);
    return new Promise((resolve) => {
      const existing = document.querySelector('script[data-leaflet-fallback]');
      if (existing) {
        existing.addEventListener('load', () => resolve(!!window.L));
        existing.addEventListener('error', () => resolve(false));
        return;
      }
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = '/assets/vendor/leaflet/leaflet.css';
      document.head.appendChild(css);
      const script = document.createElement('script');
      script.src = '/assets/vendor/leaflet/leaflet.js';
      script.dataset.leafletFallback = '1';
      script.onload = () => resolve(!!window.L);
      script.onerror = () => {
        const cdnCss = document.createElement('link');
        cdnCss.rel = 'stylesheet';
        cdnCss.href = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
        cdnCss.crossOrigin = '';
        document.head.appendChild(cdnCss);
        const cdnScript = document.createElement('script');
        cdnScript.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
        cdnScript.crossOrigin = '';
        cdnScript.onload = () => resolve(!!window.L);
        cdnScript.onerror = () => resolve(false);
        document.body.appendChild(cdnScript);
      };
      document.body.appendChild(script);
    });
  };

  const initMap = () => {
    if (!window.L || map) return !!map;
    map = L.map(mapEl, {
      zoomControl: true,
      attributionControl: false,
      zoomSnap: 1,
      zoomDelta: 1,
    }).setView(cfg.center, 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '',
      tileSize: 256,
      updateWhenZooming: false,
      updateWhenIdle: true,
      keepBuffer: 4,
      className: 'osm-tiles',
    }).addTo(map);

    originLayer = L.layerGroup();
    trackLayer = L.geoJSON(null, { style: { color: '#a78bfa', weight: 2 } });
    trackLabelLayer = L.layerGroup();
    basLayer = L.layerGroup();
    pointsLayer = L.geoJSON(null, {
      pointToLayer: (feature, latlng) => L.circleMarker(latlng, {
        radius: 5,
        color: levelColor(feature.properties?.level),
        fillColor: levelColor(feature.properties?.level),
        fillOpacity: 0.75,
        weight: 1,
      }),
      onEachFeature: (feature, layer) => {
        layer.on('click', () => {
          const idx = feature.properties?._idx;
          if (idx !== undefined) highlightRow(idx);
        });
      },
    });
    toxLayer = L.geoJSON(null, {
      style: (feature) => ({
        color: levelColor(feature.properties?.level),
        fillColor: levelColor(feature.properties?.level),
        weight: 1,
        fillOpacity: state.toxOpacity,
      }),
    });
    settlementsLayer = L.layerGroup();
    windLayer = L.layerGroup();
    plumeAxisLayer = L.layerGroup();
    hydrantsLayer = L.layerGroup();

    layerMap = {
      origin: originLayer,
      track: trackLayer,
      points: pointsLayer,
      tox: toxLayer,
      settlements: settlementsLayer,
      wind: windLayer,
      plume_axis: plumeAxisLayer,
      hydrants: hydrantsLayer,
    };

    basIcon = L.icon({
      iconUrl: '/assets/Логотип БАС.png',
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    });
    originIcon = L.icon({
      iconUrl: '/assets/РћС‡Р°Рі РїРѕР¶Р°СЂР°.png',
      iconSize: [26, 26],
      iconAnchor: [13, 26],
    });
    const hydrantSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"><circle cx="6" cy="6" r="4" fill="#007BFF" stroke="#FFFFFF" stroke-width="1"/></svg>';
    hydrantIcon = L.icon({
      iconUrl: `data:image/svg+xml;utf8,${encodeURIComponent(hydrantSvg)}`,
      iconSize: [14, 14],
      iconAnchor: [7, 7],
      popupAnchor: [0, -7],
      className: 'hydrant-icon',
    });
    hydrantFallbackIcon = L.divIcon({
      className: 'hydrant-dot',
      html: '<span></span>',
      iconSize: [14, 14],
      iconAnchor: [7, 7],
    });

    return true;
  };

  const updateOriginMarker = () => {
    if (!originLayer || !originIcon) return;
    originLayer.clearLayers();
    const marker = L.marker(cfg.center, { icon: originIcon }).bindPopup('РћС‡Р°Рі');
    originLayer.addLayer(marker);
  };

  const setTrackVisibility = (on) => {
    if (!map || !trackLayer) return;
    if (on) {
      trackLayer.addTo(map);
      trackLabelLayer.addTo(map);
      if (!state.emuEnabled) basLayer.addTo(map);
    } else {
      trackLayer.removeFrom(map);
      trackLabelLayer.removeFrom(map);
      basLayer.removeFrom(map);
    }
  };

  const syncLayersFromUI = () => {
    if (!map) return;
    updateOriginMarker();
    Object.keys(layerMap).forEach((key) => {
      const cb = document.querySelector(`[data-layer="${key}"]`);
      if (cb && cb.checked) {
        if (key === 'track') setTrackVisibility(true);
        else layerMap[key].addTo(map);
        if (key === 'settlements') renderSettlementsLayer();
        if (key === 'hydrants') loadHydrants();
      }
    });
  };

  const fetchJson = async (url) => {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  };

  const setHydrantsStatus = (text, ok = null) => {
    if (!hydrantsStatus) return;
    hydrantsStatus.textContent = text;
    hydrantsStatus.classList.toggle('ok', ok === true);
    hydrantsStatus.classList.toggle('bad', ok === false);
  };

  const updateChips = (meta) => {
    const wind = meta && meta.wind_dir_deg !== null ? `${fmtNum(meta.wind_dir_deg, 0)}В° / ${fmtNum(meta.wind_speed_ms, 1)} Рј/СЃ` : 'вЂ”';
    const points = meta ? meta.points_count : 'вЂ”';
    const conf = meta ? fmtNum(meta.confidence, 2) : 'вЂ”';
    const elWind = $('#chipWind');
    const elPoints = $('#chipPoints');
    const elConf = $('#chipConfidence');
    if (elWind) elWind.textContent = `Р’РµС‚РµСЂ: ${wind}`;
    if (elPoints) elPoints.textContent = `РўРѕС‡РµРє: ${points}`;
    if (elConf) elConf.textContent = `Р”РѕСЃС‚РѕРІРµСЂРЅРѕСЃС‚СЊ: ${conf}`;
  };

  const dataModeToggle = $('#dataModeToggle');
  const hydrantsStatus = $("#hydrantsStatus");
  const dataModeHint = $('#dataModeHint');
  const simBanner = $('#simBanner');
  const simWatermark = $('#simWatermark');
  const simBadgeMetrics = $('#simBadgeMetrics');
  const tableModeBadge = $('#tableModeBadge');
  const dirtyIndicator = $('#dirtyIndicator');
  const recalcBtn = $('#recalcBtn');
  const tabMeteo = $('#tabMeteo');
  const tabOpts = $('#tabOpts');
  const tabMetrics = $('#tabMetrics');
  const tabRecord = $('#tabRecord');
  const panelMeteo = $('#panelMeteo');
  const panelOpts = $('#panelOpts');
  const panelMetrics = $('#panelMetrics');
  const panelRecord = $('#panelRecord');

  const setDirty = (dirty) => {
    state.isDirty = dirty;
    if (!dirtyIndicator) return;
    dirtyIndicator.textContent = dirty ? 'Р Р°СЃС‡С‘С‚ СѓСЃС‚Р°СЂРµР»' : 'Р Р°СЃС‡С‘С‚ Р°РєС‚СѓР°Р»РµРЅ';
    dirtyIndicator.classList.toggle('is-dirty', dirty);
  };

  const updateModeUI = () => {
    const sim = state.dataMode === 'SIM';
    if (dataModeToggle) dataModeToggle.checked = sim;
    const label = $('#dataModeLabel');
    if (label) label.textContent = sim ? 'Р РµР¶РёРј СЌРјСѓР»СЏС†РёРё' : 'Р РµР¶РёРј РР‘РђРЎ';
    if (dataModeHint) {
      dataModeHint.textContent = sim ? 'Р РµР¶РёРј СЌРјСѓР»СЏС†РёРё' : 'Р РµР¶РёРј РР‘РђРЎ';
    }
    if (simBanner) simBanner.hidden = !sim;
    if (simWatermark) simWatermark.hidden = !sim;
    if (simBadgeMetrics) simBadgeMetrics.hidden = false;
    if (tableModeBadge) {
      tableModeBadge.textContent = sim ? 'Р­РјСѓР»СЏС†РёСЏ' : 'Р”Р°РЅРЅС‹Рµ РР‘РђРЎ';
      tableModeBadge.classList.toggle('is-ibas', !sim);
    }
    if (simBadgeMetrics) {
      simBadgeMetrics.textContent = tableModeBadge ? tableModeBadge.textContent : simBadgeMetrics.textContent;
      simBadgeMetrics.classList.toggle('is-ibas', !sim);
    }
  };

  const updateMetaFromEmu = () => {
    const meteo = getEmuMeteo();
    const meta = {
      wind_dir_deg: meteo.windDirFrom,
      wind_speed_ms: meteo.windSpeed,
      points_count: state.emuSamples.length || state.emuDrawPoints.length,
      confidence: calcConfidence(true, true, meteo.windSpeed, state.emuSamples.length || state.emuDrawPoints.length),
    };
    state.meta = meta;
    window.__SMOKE_META = meta;
    updateChips(meta);
    setWindArrow(meta.wind_dir_deg);
    if (document.querySelector('[data-layer="wind"]')?.checked) {
      updateWindLayer(meta.wind_dir_deg, meta.wind_speed_ms);
    }
  };

  const windArrowImg = document.getElementById('windArrowImg');
  const setWindArrow = (dirFrom) => {
    if (!windArrowImg) return;
    const toggle = document.querySelector('[data-layer="wind"]');
    const enabled = toggle ? toggle.checked : true;
    if (!enabled) {
      windArrowImg.style.opacity = '0';
      windArrowImg.style.visibility = 'hidden';
      windArrowImg.style.display = 'none';
      windArrowImg.style.transform = 'rotate(0deg)';
      return;
    }
    windArrowImg.style.display = 'block';
    windArrowImg.style.visibility = 'visible';
    windArrowImg.style.transformOrigin = '50% 50%';
    const dirNum = Number(dirFrom);
    if (!Number.isFinite(dirNum)) {
      windArrowImg.style.opacity = '0.4';
      return;
    }
    windArrowImg.style.opacity = '1';
    const dirTo = (dirNum + 180) % 360;
    windArrowImg.style.transform = `rotate(${dirTo}deg)`;
  };

  const updateWindLayer = (dirFrom, speed) => {
    if (windLayer) windLayer.clearLayers();
    setWindArrow(dirFrom);
    return;
  };

  const updatePlumeAxis = (dirFrom) => {
    plumeAxisLayer.clearLayers();
    if (!Number.isFinite(dirFrom)) return;
    const dirTo = (dirFrom + 180) % 360;
    const theta = rad(dirTo);
    const len = 2500;
    const dx = len * Math.sin(theta);
    const dy = len * Math.cos(theta);
    const [lat1, lon1] = fromXY(dx, dy, cfg.center[0], cfg.center[1]);
    plumeAxisLayer.addLayer(L.polyline([cfg.center, [lat1, lon1]], { color: '#facc15', weight: 2, dashArray: '4,6' }));
  };

  const updateToxStyle = () => {
    toxLayer.setStyle((feature) => {
      const value = feature.properties?.value ?? 0;
      const color = '#000';
      const alpha = clamp(Math.pow(value, 0.75) * (state.smokeIntensity ?? 1), 0, 1);
      const opacity = clamp(alpha * state.toxOpacity, 0, 0.95);
      return {
        color,
        fillColor: color,
        weight: 0,
        opacity: 0,
        stroke: false,
        fillOpacity: opacity,
      };
    });
  };

  const loadTrack = async () => {
    if (!map || !trackLayer) return;
    if (!document.querySelector('[data-layer="track"]')?.checked) return;
    const data = await fetchJson(`/api/analysis/track.php?incident_id=${cfg.incidentId}&window_min=${cfg.windowMin}`);
    trackLayer.clearLayers();
    trackLayer.addData(data);
    const line = (data.features || []).find((f) => f.geometry?.type === 'LineString');
    const coords = line?.geometry?.coordinates || (data.features || [])
      .filter((f) => f.geometry?.type === 'Point')
      .map((f) => f.geometry.coordinates);
    renderTrackLabels(coords);
    updateBasMarker(coords);
  };

  const loadPoints = async () => {
    if (!map || !pointsLayer) return;
    const data = await fetchJson(`/api/analysis/points.php?incident_id=${cfg.incidentId}&window_min=${cfg.windowMin}`);
    const features = data.features || [];
    features.forEach((f, idx) => { f.properties._idx = idx; });
    if (document.querySelector('[data-layer="points"]')?.checked) {
      pointsLayer.clearLayers();
      pointsLayer.addData(data);
    }
    renderTelemetryTable(features.map((f) => ({ ...f.properties, lat: f.geometry.coordinates[1], lon: f.geometry.coordinates[0] })), false);
  };

  const loadTox = async () => {
    if (state.emuEnabled) {
      buildEmuToxLayer();
      return;
    }
    if (!map || !toxLayer) return;
    if (!document.querySelector('[data-layer="tox"]')?.checked) return;
    const data = await fetchJson(`/api/analysis/layer.php?incident_id=${cfg.incidentId}&window_min=${cfg.windowMin}&type=tox&cell_m=${encodeURIComponent(state.gridCell)}&margin_m=${encodeURIComponent(state.gridMargin)}`);
    state.lastToxGeojson = data;
    toxLayer.clearLayers();
    const filtered = {
      type: 'FeatureCollection',
      features: (data.features || []).filter((f) => (f.properties?.value ?? 0) >= state.toxThreshold),
    };
    toxLayer.addData(filtered);
    updateToxStyle();
  };

  const loadSettlements = async () => {
    if (!map) return;
    const sort = $('#npSortSelect')?.value === 'level' ? 'level_desc' : ($('#npSortSelect')?.value === 'tox' ? 'tox_desc' : 'eta_asc');
    const minLevel = $('#npMinLevelSelect')?.value || 'all';
    const limit = $('#npLimitSelect')?.value || '20';
    const url = `/api/analysis/settlements.php?incident_id=${cfg.incidentId}&window_min=${cfg.windowMin}&radius_km=${cfg.radiusKm}&limit=${limit}&sort=${sort}&min_level=${minLevel}`;
    const data = await fetchJson(url);
    state.settlements = Array.isArray(data) ? data : [];
    renderSettlements();
    renderSettlementsLayer();
  };

  const loadMeta = async () => {
    const data = await fetchJson(`/api/analysis/meta.php?incident_id=${cfg.incidentId}&window_min=${cfg.windowMin}`);
    state.meta = data;
    window.__SMOKE_META = data;
    updateChips(data);
    setWindArrow(data.wind_dir_deg);
    if (map && document.querySelector('[data-layer="wind"]')?.checked) updateWindLayer(data.wind_dir_deg, data.wind_speed_ms);
    if (map && document.querySelector('[data-layer="plume_axis"]')?.checked) updatePlumeAxis(data.wind_dir_deg);
  };

  const renderSettlements = () => {
    const tbody = $('#npListTbody');
    if (!tbody) return;
    const q = ($('#npSearchInput')?.value || '').trim().toLowerCase();
    let items = state.settlements.slice();
    if (q.length > 0) items = items.filter((s) => (s.name || '').toLowerCase().includes(q));
    tbody.innerHTML = '';
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="settlements-empty">РќРµС‚ РґР°РЅРЅС‹С…</td></tr>';
      return;
    }
    for (const item of items) {
      const tr = document.createElement('tr');
      tr.dataset.id = item.id;
      tr.innerHTML = `
        <td class="settlement-name">${item.name}<button class="pin-btn" type="button" data-lat="${item.lat}" data-lon="${item.lon}" title="РќР° РєР°СЂС‚Рµ">рџ“Ќ</button></td>
        <td>${item.eta_min !== null ? fmtNum(item.eta_min, 1) : 'вЂ”'}</td>
        <td><span class="level-badge level-${item.level}">${item.level}</span></td>
        <td>${fmtNum(item.tox_idx, 3)}</td>
        <td>${fmtNum(item.confidence, 2)}</td>
      `;
      tr.addEventListener('click', (e) => {
        const target = e.target;
        if (target instanceof HTMLButtonElement) return;
        selectSettlement(item);
      });
      tbody.appendChild(tr);
    }
    tbody.querySelectorAll('.pin-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const lat = Number(btn.getAttribute('data-lat'));
        const lon = Number(btn.getAttribute('data-lon'));
        if (Number.isFinite(lat) && Number.isFinite(lon)) {
          map.setView([lat, lon], 13);
        }
        const item = items.find((s) => Number(s.lat) === lat && Number(s.lon) === lon);
        if (item) selectSettlement(item);
      });
    });
  };

  const renderSettlementsLayer = () => {
    settlementsLayer.clearLayers();
    if (!document.querySelector('[data-layer="settlements"]')?.checked) return;
    for (const s of state.settlements) {
      if (!Number.isFinite(s.lat) || !Number.isFinite(s.lon)) continue;
      const marker = L.circleMarker([s.lat, s.lon], {
        radius: 5,
        color: levelColor(s.level),
        fillColor: levelColor(s.level),
        fillOpacity: 0.8,
        weight: 1,
      }).bindPopup(`${s.name}<br>ETA: ${s.eta_min ?? 'вЂ”'} РјРёРЅ<br>РРЅРґРµРєСЃ: ${fmtNum(s.tox_idx, 2)}<br>Р”РѕСЃС‚РѕРІРµСЂРЅРѕСЃС‚СЊ: ${fmtNum(s.confidence, 2)}`);
      marker.on('click', () => selectSettlement(s));
      settlementsLayer.addLayer(marker);
    }
  };

  const selectSettlement = (item) => {
    const name = $('#npCardName');
    const eta = $('#npCardEta');
    const level = $('#npCardLevel');
    const index = $('#npCardIndex');
    const conf = $('#npCardConf');
    if (name) name.textContent = item.name || 'вЂ”';
    if (eta) eta.textContent = item.eta_min !== null ? `${fmtNum(item.eta_min, 1)} РјРёРЅ` : 'вЂ”';
    if (level) level.innerHTML = `<span class="level-badge level-${item.level}">${item.level}</span>`;
    if (index) index.textContent = fmtNum(item.tox_idx, 3);
    if (conf) conf.textContent = fmtNum(item.confidence, 2);
  };

  const renderTrackLabels = (coords) => {
    trackLabelLayer.clearLayers();
    if (!coords || coords.length === 0) return;
    const centroid = coords.reduce((acc, c) => ({ lat: acc.lat + c[1], lon: acc.lon + c[0] }), { lat: 0, lon: 0 });
    centroid.lat /= coords.length;
    centroid.lon /= coords.length;
    const groups = new Map();
    coords.forEach((c, idx) => {
      const key = `${c[1].toFixed(6)}|${c[0].toFixed(6)}`;
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push({ idx, lat: c[1], lon: c[0] });
    });

    groups.forEach((items) => {
      const n = items.length;
      if (n > 1) {
        items.forEach((item, j) => {
          const offset = 16 + j * 18;
          const [latOff, lonOff] = fromXY(offset, 0, item.lat, item.lon);
          const icon = L.divIcon({
            className: 'track-label',
            html: `<div class="track-label__inner">${item.idx + 1}</div>`,
            iconSize: [22, 22],
            iconAnchor: [11, 11],
          });
          trackLabelLayer.addLayer(L.marker([latOff, lonOff], { icon }));
        });
        return;
      }
      items.forEach((item) => {
        const lat = item.lat;
        const lon = item.lon;
        const [dx, dy] = toXY(lat, lon, centroid.lat, centroid.lon);
        const len = Math.hypot(dx, dy) || 1;
        const off = 18;
        const nx = dx / len;
        const ny = dy / len;
        const [latOff, lonOff] = fromXY(dx + nx * off, dy + ny * off, centroid.lat, centroid.lon);
        const icon = L.divIcon({
          className: 'track-label',
          html: `<div class="track-label__inner">${item.idx + 1}</div>`,
          iconSize: [22, 22],
          iconAnchor: [11, 11],
        });
        trackLabelLayer.addLayer(L.marker([latOff, lonOff], { icon }));
      });
    });
  };

  const loadHydrants = async () => {
    if (!map || !hydrantsLayer) {
      setHydrantsStatus('Гидранты: карта не готова', false);
      return;
    }
    if (!map.hasLayer(hydrantsLayer)) hydrantsLayer.addTo(map);
    setHydrantsStatus('Гидранты: загрузка…');
    try {
      const data = await fetchJson('/api/analysis/hydrants.php');
      hydrantsLayer.clearLayers();
      const features = Array.isArray(data?.features) ? data.features : [];
      let added = 0;
      const bounds = [];
      features.forEach((f) => {
        const coords = f.geometry?.coordinates || [];
        const lat = coords[1];
        const lon = coords[0];
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
        const props = f.properties || {};
        const marker = L.marker([lat, lon], { icon: hydrantIcon || hydrantFallbackIcon || undefined });
        const lines = [
          props.name ? `<strong>${props.name}</strong>` : '<strong>Гидрант</strong>',
          props.discharge ? `Водоотдача: ${props.discharge}` : '',
          props.status ? `Состояние: ${props.status}` : '',
          props.address ? `Адрес: ${props.address}` : '',
          `Координаты: ${lat.toFixed(6)}, ${lon.toFixed(6)}`
        ].filter(Boolean);
        marker.bindPopup(lines.join('<br>'), { closeButton: true, autoPan: true });
        hydrantsLayer.addLayer(marker);
        bounds.push([lat, lon]);
        added += 1;
      });
      setHydrantsStatus(`Гидранты: ${added} / ${features.length}`, added > 0);
      if (bounds.length && !hydrantsFitted) {
        const b = L.latLngBounds(bounds);
        map.fitBounds(b.pad(0.12));
        hydrantsFitted = true;
      }
    } catch (e) {
      setHydrantsStatus('Гидранты: ошибка загрузки', false);
    }
  };

  const updateBasMarker = (coords) => {
    basLayer.clearLayers();
    if (!coords || coords.length === 0 || state.emuEnabled) return;
    const last = coords[coords.length - 1];
    basLayer.addLayer(L.marker([last[1], last[0]], { icon: basIcon }));
  };

  const renderTelemetryTable = (rows, editable) => {
    const tbody = $('#telemetryTbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) return;
    rows.forEach((r, idx) => {
      const tr = document.createElement('tr');
      tr.dataset.idx = String(idx);
      const ts = r.ts ? new Date(r.ts * 1000) : null;
      const timeStr = ts ? ts.toLocaleString('ru-RU') : 'вЂ”';
      const rangeCfg = {
        alt: { min: 0, max: 500, step: 1 },
        mq2_raw: { min: 0, max: 2000, step: 10 },
        mq5_raw: { min: 0, max: 2000, step: 10 },
        mq7_ppm: { min: 0, max: 600, step: 1 },
        mq135_raw: { min: 0, max: 2000, step: 10 },
        nap07_raw: { min: 0, max: 700, step: 1 },
        radiation: { min: 0.05, max: 5.0, step: 0.01 },
        temp_c: { min: -30, max: 60, step: 0.5 },
        rh_pct: { min: 0, max: 100, step: 1 },
      };
      const cell = (key, digits = 2) => Number.isFinite(r[key]) ? fmtNum(r[key], digits) : 'вЂ”';
      const editableCell = (key) => {
        const cfgRange = rangeCfg[key] || { min: 0, max: 100, step: 1 };
        const v = Number.isFinite(r[key]) ? r[key] : '';
        const rv = Number.isFinite(r[key]) ? r[key] : cfgRange.min;
        return `
          <div class="cell-stack">
            <input class="input input-sm" data-kind="num" data-key="${key}" data-idx="${idx}" type="number" min="${cfgRange.min}" max="${cfgRange.max}" step="${cfgRange.step}" value="${v}">
            <input class="table-range" data-kind="range" data-key="${key}" data-idx="${idx}" type="range" min="${cfgRange.min}" max="${cfgRange.max}" step="${cfgRange.step}" value="${rv}">
          </div>
        `;
      };
      const readonlyCell = (key, digits = 2) => {
        const cfgRange = rangeCfg[key] || { min: 0, max: 100, step: 1 };
        const rv = Number.isFinite(r[key]) ? r[key] : cfgRange.min;
        return `
          <div class="cell-stack">
            <div class="cell-value">${cell(key, digits)}</div>
            <input class="table-range" type="range" min="${cfgRange.min}" max="${cfgRange.max}" step="${cfgRange.step}" value="${rv}" disabled>
          </div>
        `;
      };
      tr.innerHTML = editable ? `
        <td>${idx + 1}</td>
        <td>${timeStr}</td>
        <td>${fmtNum(r.lat, 6)}</td>
        <td>${fmtNum(r.lon, 6)}</td>
        <td>${editableCell('alt')}</td>
        <td>${editableCell('mq2_raw')}</td>
        <td>${editableCell('mq5_raw')}</td>
        <td>${editableCell('mq7_ppm')}</td>
        <td>${editableCell('mq135_raw')}</td>
        <td>${editableCell('nap07_raw')}</td>
        <td>${editableCell('radiation')}</td>
        <td>${editableCell('temp_c')}</td>
        <td>${editableCell('rh_pct')}</td>
      ` : `
        <td>${idx + 1}</td>
        <td>${timeStr}</td>
        <td>${fmtNum(r.lat, 6)}</td>
        <td>${fmtNum(r.lon, 6)}</td>
        <td>${readonlyCell('alt', 1)}</td>
        <td>${readonlyCell('mq2_raw', 1)}</td>
        <td>${readonlyCell('mq5_raw', 1)}</td>
        <td>${readonlyCell('mq7_ppm', 1)}</td>
        <td>${readonlyCell('mq135_raw', 1)}</td>
        <td>${readonlyCell('nap07_raw', 1)}</td>
        <td>${readonlyCell('radiation', 2)}</td>
        <td>${readonlyCell('temp_c', 1)}</td>
        <td>${readonlyCell('rh_pct', 1)}</td>
      `;
      tr.addEventListener('click', () => highlightRow(idx));
      tbody.appendChild(tr);
    });

    const attachWheel = (input) => {
      input.addEventListener('wheel', (e) => {
        e.preventDefault();
        const step = Number(input.step || 1);
        const dir = e.deltaY < 0 ? 1 : -1;
        const min = input.min !== '' ? Number(input.min) : -Infinity;
        const max = input.max !== '' ? Number(input.max) : Infinity;
        let val = Number(input.value || 0) + step * dir;
        val = Math.min(max, Math.max(min, val));
        input.value = String(val);
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }, { passive: false });
    };

    if (editable) {
      const numInputs = tbody.querySelectorAll('input[data-kind="num"]');
      const rangeInputs = tbody.querySelectorAll('input[data-kind="range"]');

      numInputs.forEach((input) => {
        attachWheel(input);
        input.addEventListener('input', () => {
          const key = input.getAttribute('data-key');
          const idx = Number(input.getAttribute('data-idx'));
          if (!Number.isFinite(idx) || !key) return;
          const val = Number(input.value);
          state.emuSamples[idx][key] = Number.isFinite(val) ? val : null;
          const range = tbody.querySelector(`input[data-kind="range"][data-key="${key}"][data-idx="${idx}"]`);
          if (range) range.value = Number.isFinite(val) ? String(val) : range.value;
          updateMiniMetrics(state.emuSamples[idx]);
          setDirty(true);
        });
      });

      rangeInputs.forEach((range) => {
        range.addEventListener('input', () => {
          const key = range.getAttribute('data-key');
          const idx = Number(range.getAttribute('data-idx'));
          if (!Number.isFinite(idx) || !key) return;
          const val = Number(range.value);
          state.emuSamples[idx][key] = Number.isFinite(val) ? val : null;
          const input = tbody.querySelector(`input[data-kind="num"][data-key="${key}"][data-idx="${idx}"]`);
          if (input) input.value = Number.isFinite(val) ? String(val) : '';
          updateMiniMetrics(state.emuSamples[idx]);
          setDirty(true);
        });
      });
    } else {
      tbody.querySelectorAll('input[data-kind="num"]').forEach(attachWheel);
    }
  };

  const highlightRow = (idx) => {
    state.selectedRow = idx;
    const tbody = $('#telemetryTbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach((tr) => {
      tr.classList.toggle('row-highlight', tr.dataset.idx === String(idx));
    });
  };

  const updateMiniMetrics = (sample) => {
    if (!sample) return;
    const { coIdx, smokeIdx, vocIdx, toxIdx } = calcIndexes(sample);
    const hasPpm = sample.mq7_ppm !== null && Number.isFinite(sample.mq7_ppm);
    const conf = calcConfidence(true, hasPpm, Number($('#emuWindSpeed')?.value || 0), state.emuSamples.length);
    $('#mCoIdx').textContent = fmt(coIdx, 2);
    $('#mSmokeIdx').textContent = fmt(smokeIdx, 2);
    $('#mVocIdx').textContent = fmt(vocIdx, 2);
    $('#mToxIdx').textContent = fmt(toxIdx, 2);
    $('#mConfEst').textContent = fmt(conf, 2);
  };

  const getSensorValuesFromSliders = () => ({
    mq7_ppm: 35,
    nap07_raw: 80,
    mq135_raw: 250,
    mq2_raw: 150,
    mq5_raw: 120,
    radiation: 0.12,
    temp_c: Number($('#emuTemp')?.value || 10),
    rh_pct: Number($('#emuRH')?.value || 65),
  });

  const getEmuMeteo = () => ({
    windDirFrom: Number($('#emuWindDirFrom')?.value || 0),
    windSpeed: Number($('#emuWindSpeed')?.value || 0),
    pressure: Number($('#emuPressure')?.value || 1013),
    temp: Number($('#emuTemp')?.value || 10),
    rh: Number($('#emuRH')?.value || 65),
  });

  const ensureEmuMarker = () => {
    if (state.emuMarker) return state.emuMarker;
    state.emuMarker = L.marker(cfg.center, { draggable: true });
    return state.emuMarker;
  };

  const updateDrawButtonLabel = () => {
    const btn = $('#emuDrawEnableBtn');
    if (!btn) return;
    if (state.emuDrawPoints.length > 0) {
      btn.textContent = 'РЎРѕС…СЂР°РЅРёС‚СЊ РјР°СЂС€СЂСѓС‚';
    } else {
      btn.textContent = 'Р РёСЃРѕРІР°С‚СЊ РјР°СЂС€СЂСѓС‚';
    }
  };

  const setDrawActive = (active) => {
    state.emuDrawActive = active;
    updateDrawButtonLabel();
  };

  const clearDraw = () => {
    state.emuDrawPoints = [];
    state.emuDrawMarkers.forEach((m) => m.remove());
    state.emuDrawMarkers = [];
    if (state.emuDrawLine) {
      state.emuDrawLine.remove();
      state.emuDrawLine = null;
    }
    state.emuSamples = [];
    renderTelemetryTable([], true);
    updateActionStates();
    updateDrawButtonLabel();
  };

  const redrawDrawLine = () => {
    if (state.emuDrawLine) state.emuDrawLine.remove();
    if (state.emuDrawPoints.length < 2) return;
    state.emuDrawLine = L.polyline(state.emuDrawPoints.map((p) => [p.lat, p.lon]), { color: '#7dd3fc', weight: 2 });
    state.emuDrawLine.addTo(map);
  };

  map.on('click', (e) => {
    if (!state.emuEnabled) return;
    if (state.emuDrawActive) {
      const alt = Number($('#emuDefaultAlt')?.value || 30);
      const point = { lat: e.latlng.lat, lon: e.latlng.lng, alt };
      state.emuDrawPoints.push(point);
      const marker = L.circleMarker([point.lat, point.lon], { radius: 5, color: '#7dd3fc', fillColor: '#7dd3fc', fillOpacity: 0.8 });
      marker.addTo(map);
      state.emuDrawMarkers.push(marker);
      redrawDrawLine();
      updateActionStates();
      setDirty(true);
      return;
    }
    if ($('#emuCoordMode')?.value === 'marker') {
      const marker = ensureEmuMarker();
      marker.setLatLng(e.latlng);
      if (!map.hasLayer(marker)) marker.addTo(map);
    }
  });

  const buildSamplesFromDraw = () => {
    const meteo = getEmuMeteo();
    const stepSec = Number($('#emuOrbitStep')?.value || 5);
    const noise = $('#emuNoiseEnable')?.checked;
    state.emuSamples = state.emuDrawPoints.map((p, idx) => {
      const tsSec = Math.floor(Date.now() / 1000) + idx * stepSec;
      const sensors = generateSensors(p, idx, tsSec, { ...meteo, stepSec, noise });
      return { ...p, ts: tsSec, ...sensors };
    });
    renderTelemetryTable(state.emuSamples, true);
    refreshEmuLayers();
  };

  const refreshEmuLayers = () => {
    const points = state.emuSamples.length ? state.emuSamples : state.emuDrawPoints;
    if (document.querySelector('[data-layer="points"]')?.checked) {
      pointsLayer.clearLayers();
      const features = points.map((p, idx) => ({
        type: 'Feature',
        properties: { level: calcLevel(calcIndexes(p).toxIdx), _idx: idx },
        geometry: { type: 'Point', coordinates: [p.lon, p.lat] },
      }));
      pointsLayer.addData({ type: 'FeatureCollection', features });
    }

    if (document.querySelector('[data-layer="track"]')?.checked) {
      trackLayer.clearLayers();
      if (points.length >= 2) {
        trackLayer.addData({
          type: 'FeatureCollection',
          features: [{
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: points.map((p) => [p.lon, p.lat]) },
            properties: {},
          }],
        });
      }
      renderTrackLabels(points.map((p) => [p.lon, p.lat]));
      updateBasMarker([]);
    }

    if (document.querySelector('[data-layer="tox"]')?.checked) {
      buildEmuToxLayer();
    }
  };

  const buildEmuToxLayer = () => {
    if (!state.emuEnabled) return;
    const meteo = getEmuMeteo();
    let points = state.emuSamples.length ? state.emuSamples : state.emuDrawPoints;
    if (!points.length) {
      points = [{ lat: cfg.center[0], lon: cfg.center[1] }];
    }
    const lat0 = cfg.center[0];
    const lon0 = cfg.center[1];
    const theta = windToTheta(meteo.windDirFrom);
    const xy = points.map((p) => {
      const [dx, dy] = toXY(p.lat, p.lon, lat0, lon0);
      return { x: dx, y: dy };
    });
    const xs = xy.map((p) => p.x);
    const ys = xy.map((p) => p.y);
    const cell = Number(state.gridCell) || gridCfg.cell_m || 150;
    const orbitRadius = Number($('#emuOrbitRadius')?.value || 0);
    const marginBase = Number(state.gridMargin) || gridCfg.margin_m || 900;
    const margin = Math.max(marginBase, orbitRadius + cell * 2);
    const minX = Math.min(...xs) - margin;
    const maxX = Math.max(...xs) + margin;
    const minY = Math.min(...ys) - margin;
    const maxY = Math.max(...ys) + margin;
    const features = [];
    for (let x = Math.floor(minX / cell) * cell; x <= maxX; x += cell) {
      for (let y = Math.floor(minY / cell) * cell; y <= maxY; y += cell) {
        const cx = x + cell / 2;
        const cy = y + cell / 2;
        const [xw, yw] = rotate(cx, cy, theta);
        const tox = sField(xw, yw, meteo.windSpeed, meteo.rh, meteo.pressure);
        if (tox < state.toxThreshold) continue;
        const lvl = calcLevel(tox);
        const [lat1, lon1] = fromXY(x, y, lat0, lon0);
        const [lat2, lon2] = fromXY(x + cell, y, lat0, lon0);
        const [lat3, lon3] = fromXY(x + cell, y + cell, lat0, lon0);
        const [lat4, lon4] = fromXY(x, y + cell, lat0, lon0);
        features.push({
          type: 'Feature',
          properties: { value: tox, level: lvl },
          geometry: {
            type: 'Polygon',
            coordinates: [[
              [lon1, lat1],
              [lon2, lat2],
              [lon3, lat3],
              [lon4, lat4],
              [lon1, lat1],
            ]],
          },
        });
      }
    }
    const geo = { type: 'FeatureCollection', features };
    state.lastToxGeojson = geo;
    toxLayer.clearLayers();
    toxLayer.addData(geo);
    updateToxStyle();
  };

  const pushSample = async (sample) => {
    const form = new FormData();
    form.append('csrf', cfg.csrf || '');
    form.append('incident_id', String(cfg.incidentId));
    form.append('lat', String(sample.lat));
    form.append('lon', String(sample.lon));
    form.append('alt', String(sample.alt ?? 0));
    form.append('mq7_ppm', String(sample.mq7_ppm ?? ''));
    form.append('mq7_raw', String(sample.mq7_raw ?? ''));
    form.append('mq2_raw', String(sample.mq2_raw ?? ''));
    form.append('mq5_raw', String(sample.mq5_raw ?? ''));
    form.append('mq135_raw', String(sample.mq135_raw ?? ''));
    form.append('nap07_raw', String(sample.nap07_raw ?? ''));
    form.append('radiation', String(sample.radiation ?? ''));
    form.append('temp_c', String(sample.temp_c ?? ''));
    form.append('rh_pct', String(sample.rh_pct ?? ''));
    if (sample.ts) form.append('ts', new Date(sample.ts * 1000).toISOString().slice(0, 19).replace('T', ' '));
    await fetch('/api/dev/push_sample.php', { method: 'POST', body: form, credentials: 'same-origin' });
  };

  const pushMeteo = async () => {
    const meteo = getEmuMeteo();
    const form = new FormData();
    form.append('csrf', cfg.csrf || '');
    form.append('incident_id', String(cfg.incidentId));
    form.append('wind_dir_deg', String(meteo.windDirFrom));
    form.append('wind_speed_ms', String(meteo.windSpeed));
    form.append('pressure_hpa', String(meteo.pressure));
    form.append('temp_c', String(meteo.temp));
    form.append('rh_pct', String(meteo.rh));
    await fetch('/api/dev/push_meteo.php', { method: 'POST', body: form, credentials: 'same-origin' });
    setWindArrow(meteo.windDirFrom);
    if (document.querySelector('[data-layer="wind"]')?.checked) updateWindLayer(meteo.windDirFrom, meteo.windSpeed);
    if (document.querySelector('[data-layer="plume_axis"]')?.checked) updatePlumeAxis(meteo.windDirFrom);
  };

  const getSampleFromMode = () => {
    const mode = $('#emuCoordMode')?.value || 'origin';
    const alt = Number($('#emuDefaultAlt')?.value || 30);
    let lat = cfg.center[0];
    let lon = cfg.center[1];
    if (mode === 'marker') {
      const marker = ensureEmuMarker();
      const ll = marker.getLatLng();
      lat = ll.lat; lon = ll.lng;
    } else if (mode === 'orbit') {
      const radius = Number($('#emuOrbitRadius')?.value || 150);
      const speed = Number($('#emuOrbitSpeed')?.value || 8);
      const step = Number($('#emuOrbitStep')?.value || 5);
      state.emuStreamIndex += 1;
      const phi = (speed / Math.max(1, radius)) * step * state.emuStreamIndex;
      const x = radius * Math.cos(phi);
      const y = radius * Math.sin(phi);
      const ll = fromXY(x, y, cfg.center[0], cfg.center[1]);
      lat = ll[0];
      lon = ll[1];
    } else if (mode === 'draw' && state.emuDrawPoints.length) {
      const idx = state.emuStreamIndex % state.emuDrawPoints.length;
      lat = state.emuDrawPoints[idx].lat;
      lon = state.emuDrawPoints[idx].lon;
      state.emuStreamIndex += 1;
    }
    const stepSec = Number($('#emuOrbitStep')?.value || 5);
    const noise = $('#emuNoiseEnable')?.checked;
    const ts = Math.floor(Date.now() / 1000);
    const meteo = getEmuMeteo();
    const sensors = generateSensors({ lat, lon }, state.emuStreamIndex, ts, { ...meteo, stepSec, noise });
    return { lat, lon, alt, ...sensors, ts };
  };

  const startStream = () => {
    const step = Number($('#emuOrbitStep')?.value || 5);
    if (state.emuStreamTimer) clearInterval(state.emuStreamTimer);
    state.emuStreamTimer = setInterval(async () => {
      const sample = getSampleFromMode();
      state.emuSamples.push(sample);
      renderTelemetryTable(state.emuSamples, true);
      await pushSample(sample);
      refreshEmuLayers();
    }, Math.max(1, step) * 1000);
    updateActionStates();
  };

  const stopStream = () => {
    if (state.emuStreamTimer) clearInterval(state.emuStreamTimer);
    state.emuStreamTimer = null;
    updateActionStates();
  };

  const updateActionStates = () => {
    const hasTrack = state.emuDrawPoints.length > 0;
    const hasSamples = state.emuSamples.length > 0;
    const streamActive = !!state.emuStreamTimer;
    $('#emuDrawClearBtn')?.toggleAttribute('disabled', !hasTrack);
    updateDrawButtonLabel();
    const streamBtn = $('#emuStreamToggle');
    if (streamBtn) {
      streamBtn.textContent = streamActive ? 'РЎС‚РѕРї' : 'РЎС‚Р°СЂС‚ РїРѕС‚РѕРє';
      streamBtn.classList.toggle('btn-danger', streamActive);
    }
  };

  const setEmuEnabled = (enabled, opts = {}) => {
    state.emuEnabled = enabled;
    const panels = [panelOpts, panelMeteo, panelRecord].filter(Boolean);
    panels.forEach((panel) => {
      panel.querySelectorAll('input,select,button,textarea').forEach((el) => {
        el.disabled = !enabled || !cfg.canAdjust;
      });
    });
    if (!enabled) {
      stopStream();
      state.emuSamples = [];
      state.emuDrawPoints = [];
      state.emuDrawMarkers.forEach((m) => m.remove());
      state.emuDrawMarkers = [];
      if (state.emuDrawLine) { state.emuDrawLine.remove(); state.emuDrawLine = null; }
      renderTelemetryTable([], false);
      if (map) {
        loadPoints();
        loadTrack();
        loadTox();
        loadMeta();
      }
    } else {
      if (basLayer) basLayer.clearLayers();
      renderTelemetryTable(state.emuSamples, true);
      if (map) {
        refreshEmuLayers();
        updateMetaFromEmu();
        const m = getEmuMeteo();
        setWindArrow(m.windDirFrom);
        if (document.querySelector('[data-layer="wind"]')?.checked) {
          updateWindLayer(m.windDirFrom, m.windSpeed);
        }
        if (document.querySelector('[data-layer="plume_axis"]')?.checked) {
          updatePlumeAxis(m.windDirFrom);
        }
      }
    }
    updateActionStates();
    if (opts.clearDirty) setDirty(false);
  };

  const setDataMode = (mode) => {
    const needsConfirm = state.emuStreamTimer || state.emuSamples.length || state.emuDrawPoints.length;
    if (needsConfirm) {
      const ok = confirm('РџРµСЂРµРєР»СЋС‡РµРЅРёРµ РѕСЃС‚Р°РЅРѕРІРёС‚ РїРѕС‚РѕРє Рё РѕС‡РёСЃС‚РёС‚ Р·Р°РјРµСЂС‹. РџСЂРѕРґРѕР»Р¶РёС‚СЊ?');
      if (!ok) {
        if (dataModeToggle) dataModeToggle.checked = state.dataMode === 'SIM';
        return;
      }
    }
    state.dataMode = mode;
    state.emuEnabled = mode === 'SIM';
    if (cfg.ibasEnabled) localStorage.setItem(storageKey, mode);
    setEmuEnabled(mode === 'SIM', { clearDirty: true });
    updateModeUI();
    setDirty(true);
  };

  const updateSensorUI = () => {
    const sensors = [
      { id: 'MQ7' },
      { id: 'NAP07' },
      { id: 'MQ135' },
      { id: 'MQ2' },
      { id: 'MQ5' },
      { id: 'Rad' },
    ];
    sensors.forEach((s) => {
      const sl = $(`#sl${s.id}`);
      const min = $(`#min${s.id}`);
      const max = $(`#max${s.id}`);
      const val = $(`#val${s.id}`);
      if (sl && min && max && val) {
        val.textContent = sl.value;
        sl.addEventListener('input', () => { val.textContent = sl.value; updateMiniMetrics(getSensorValuesFromSliders()); });
        min.addEventListener('change', () => { sl.min = min.value; });
        max.addEventListener('change', () => { sl.max = max.value; });
      }
    });
  };

  const initControls = () => {
    const activateTab = (tab) => {
      const tabs = [tabMeteo, tabOpts, tabMetrics, tabRecord].filter(Boolean);
      const panels = [panelMeteo, panelOpts, panelMetrics, panelRecord].filter(Boolean);
      tabs.forEach((t) => t.classList.toggle('is-active', t === tab));
      panels.forEach((p) => {
        const target = tab?.getAttribute('data-tab');
        p.classList.toggle('is-active', p.getAttribute('data-panel') === target);
      });
    };
    tabMeteo?.addEventListener('click', () => activateTab(tabMeteo));
    tabOpts?.addEventListener('click', () => activateTab(tabOpts));
    tabMetrics?.addEventListener('click', () => activateTab(tabMetrics));
    tabRecord?.addEventListener('click', () => activateTab(tabRecord));

    const opacity = $('#toxOpacitySlider');
    const threshold = $('#toxThresholdSlider');
    if (opacity) {
      opacity.addEventListener('input', () => {
        state.toxOpacity = Number(opacity.value);
        $('#toxOpacityVal').textContent = `${Math.round(state.toxOpacity * 100)}%`;
        updateToxStyle();
        setDirty(true);
      });
    }
    if (threshold) {
      threshold.addEventListener('input', () => {
        state.toxThreshold = Number(threshold.value);
        $('#toxThresholdVal').textContent = fmt(state.toxThreshold, 2);
        state.emuEnabled ? buildEmuToxLayer() : loadTox();
        setDirty(true);
      });
    }

    const gridCell = $('#gridCellSlider');
    const gridMargin = $('#gridMarginSlider');
    const smokeIntensity = $('#smokeIntensitySlider');
    if (gridCell) {
      gridCell.addEventListener('input', () => {
        if (!cfg.canAdjust) return;
        state.gridCell = Number(gridCell.value);
        const el = $('#gridCellVal');
        if (el) el.textContent = `${Math.round(state.gridCell)} Рј`;
        state.emuEnabled ? buildEmuToxLayer() : loadTox();
        setDirty(true);
      });
    }
    if (gridMargin) {
      gridMargin.addEventListener('input', () => {
        if (!cfg.canAdjust) return;
        state.gridMargin = Number(gridMargin.value);
        const el = $('#gridMarginVal');
        if (el) el.textContent = `${Math.round(state.gridMargin)} Рј`;
        state.emuEnabled ? buildEmuToxLayer() : loadTox();
        setDirty(true);
      });
    }
    if (smokeIntensity) {
      smokeIntensity.addEventListener('input', () => {
        if (!cfg.canAdjust) return;
        state.smokeIntensity = Number(smokeIntensity.value);
        const el = $('#smokeIntensityVal');
        if (el) el.textContent = `${Math.round(state.smokeIntensity * 100)}%`;
        updateToxStyle();
        setDirty(true);
      });
    }

    $('#emuNoiseEnable')?.addEventListener('change', () => setDirty(true));

    const getWindForUi = () => {
      const meta = state.meta || null;
      const emu = getEmuMeteo();
      const dir = meta && Number.isFinite(Number(meta.wind_dir_deg))
        ? Number(meta.wind_dir_deg)
        : Number(emu.windDirFrom);
      const spd = meta && Number.isFinite(Number(meta.wind_speed_ms))
        ? Number(meta.wind_speed_ms)
        : Number(emu.windSpeed);
      return { dir, spd };
    };

    $$('[data-layer]').forEach((cb) => {
      cb.addEventListener('change', () => {
        const key = cb.getAttribute('data-layer');
        if (!key) return;
        if (key === 'wind') {
          const w = getWindForUi();
          if (cb.checked) {
            updateWindLayer(w.dir, w.spd);
          } else {
            if (windLayer) windLayer.clearLayers();
            setWindArrow(NaN);
          }
        }
        if (!map || !layerMap[key]) return;
        if (cb.checked) {
          if (key === 'track') {
            setTrackVisibility(true);
            state.emuEnabled ? refreshEmuLayers() : loadTrack();
          } else {
            layerMap[key].addTo(map);
          }
          if (key === 'points') state.emuEnabled ? refreshEmuLayers() : loadPoints();
          if (key === 'tox') state.emuEnabled ? buildEmuToxLayer() : loadTox();
          if (key === 'settlements') renderSettlementsLayer();
          if (key === 'hydrants') loadHydrants();
          if (key === 'plume_axis') {
            const meta = state.meta;
            if (meta) updatePlumeAxis(meta.wind_dir_deg);
          }
        } else {
          if (key === 'track') setTrackVisibility(false);
          else layerMap[key].removeFrom(map);
          if (key === 'plume_axis') plumeAxisLayer.clearLayers();
          if (key === 'hydrants') {
            hydrantsLayer.clearLayers();
            setHydrantsStatus('Гидранты: выключено');
            hydrantsFitted = false;
          }
        }
      });
    });

    $('#npSortSelect')?.addEventListener('change', loadSettlements);
    $('#npMinLevelSelect')?.addEventListener('change', loadSettlements);
    $('#npLimitSelect')?.addEventListener('change', loadSettlements);
    $('#npSearchInput')?.addEventListener('input', renderSettlements);
    $('#npExportCsvBtn')?.addEventListener('click', () => exportSettlementsCsv());
    $('#npExportGeoJsonBtn')?.addEventListener('click', () => exportToxGeoJson());

    dataModeToggle?.addEventListener('change', (e) => {
      setDataMode(e.target.checked ? 'SIM' : 'IBAS');
    });

    const toolbarSnapshot = {
      incidentId: cfg.incidentId,
      windowMin: cfg.windowMin,
      radiusKm: cfg.radiusKm,
      refreshSec: cfg.refreshSec,
    };
    const getToolbarValues = () => ({
      incidentId: Number($('#incidentSelect')?.value || cfg.incidentId),
      windowMin: Number($('#windowMinInput')?.value || cfg.windowMin),
      radiusKm: Number($('#radiusKmSelect')?.value || cfg.radiusKm),
      refreshSec: Number($('#refreshSecSelect')?.value || cfg.refreshSec),
    });
    ['incidentSelect','windowMinInput','radiusKmSelect','refreshSecSelect'].forEach((id) => {
      document.getElementById(id)?.addEventListener('input', () => setDirty(true));
    });

    $('#calcToolbar')?.addEventListener('submit', (e) => {
      e.preventDefault();
      const v = getToolbarValues();
      const changed = v.incidentId !== toolbarSnapshot.incidentId
        || v.windowMin !== toolbarSnapshot.windowMin
        || v.radiusKm !== toolbarSnapshot.radiusKm
        || v.refreshSec !== toolbarSnapshot.refreshSec;
      if (changed) {
        const url = new URL(window.location.href);
        url.searchParams.set('incident_id', String(v.incidentId));
        url.searchParams.set('window_min', String(v.windowMin));
        url.searchParams.set('radius_km', String(v.radiusKm));
        url.searchParams.set('refresh_sec', String(v.refreshSec));
        window.location.href = url.toString();
        return;
      }
      if (state.emuEnabled) {
        refreshEmuLayers();
        buildEmuToxLayer();
        updateMetaFromEmu();
      } else {
        loadMeta();
        loadTrack();
        loadPoints();
        loadTox();
        loadSettlements();
      }
      setDirty(false);
    });

    $('#emuCoordMode')?.addEventListener('change', (e) => {
      const mode = e.target.value;
      const orbitFields = $('#emuOrbitFields');
      if (orbitFields) orbitFields.style.display = mode === 'orbit' ? 'grid' : 'none';
      if (mode === 'marker') {
        const marker = ensureEmuMarker();
        if (!map.hasLayer(marker)) marker.addTo(map);
      }
    });
    $('#emuDrawEnableBtn')?.addEventListener('click', async () => {
      if (state.emuDrawPoints.length > 0) {
        if (state.emuDrawActive) setDrawActive(false);
        if (!state.emuSamples.length) buildSamplesFromDraw();
        for (const sample of state.emuSamples) await pushSample(sample);
        setDirty(true);
        return;
      }
      setDrawActive(!state.emuDrawActive);
      setDirty(true);
    });
    $('#emuDrawClearBtn')?.addEventListener('click', () => {
      if (state.emuDrawPoints.length && !confirm('РћС‡РёСЃС‚РёС‚СЊ РјР°СЂС€СЂСѓС‚?')) return;
      clearDraw();
      setDirty(true);
    });
    $('#emuDrawGenSamplesBtn')?.addEventListener('click', () => { buildSamplesFromDraw(); setDirty(true); updateActionStates(); });
    ['emuWindDirFrom','emuWindSpeed','emuPressure','emuTemp','emuRH'].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      const onMeteoChange = () => {
        const m = getEmuMeteo();
        setWindArrow(m.windDirFrom);
        if (document.querySelector('[data-layer="wind"]')?.checked) {
          updateWindLayer(m.windDirFrom, m.windSpeed);
        }
        if (document.querySelector('[data-layer="plume_axis"]')?.checked) {
          updatePlumeAxis(m.windDirFrom);
        }
        if (!state.emuEnabled) return;
        if (document.querySelector('[data-layer="tox"]')?.checked) {
          buildEmuToxLayer();
        }
        updateMetaFromEmu();
        setDirty(true);
      };
      el.addEventListener('input', onMeteoChange);
      el.addEventListener('change', onMeteoChange);
      el.addEventListener('keyup', onMeteoChange);
    });
    $('#emuPushOnceBtn')?.addEventListener('click', async () => {
      const idx = state.selectedRow ?? (state.emuSamples.length - 1);
      const sample = state.emuSamples[idx] || getSampleFromMode();
      await pushSample(sample);
      setDirty(true);
    });
    $('#emuStreamToggle')?.addEventListener('click', () => {
      if (state.emuStreamTimer) {
        if (!confirm('РћСЃС‚Р°РЅРѕРІРёС‚СЊ РїРѕС‚РѕРє?')) return;
        stopStream();
      } else {
        startStream();
      }
      updateActionStates();
      setDirty(true);
    });

    $('#toggleDisplay')?.addEventListener('click', () => {
      const left = $('#leftParamsPanel');
      const right = $('#rightParamsPanel');
      if (left) left.classList.toggle('is-collapsed');
      if (right) right.classList.toggle('is-collapsed');
      if (typeof resizeTelemetryTable === 'function') resizeTelemetryTable();
    });

    updateActionStates();
  };

  const exportSettlementsCsv = () => {
    if (!state.settlements.length) return;
    const rows = [
      ['РќР°СЃРµР»С‘РЅРЅС‹Р№ РїСѓРЅРєС‚', 'ETA (РјРёРЅ)', 'РЈСЂРѕРІРµРЅСЊ', 'РРЅРґРµРєСЃ', 'Р”РѕСЃС‚РѕРІРµСЂРЅРѕСЃС‚СЊ'],
      ...state.settlements.map((s) => [
        s.name,
        s.eta_min ?? '',
        s.level,
        s.tox_idx ?? '',
        s.confidence ?? '',
      ]),
    ];
    const csv = rows.map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'settlements.csv';
    link.click();
  };

  const exportToxGeoJson = () => {
    if (!state.lastToxGeojson) return;
    const blob = new Blob([JSON.stringify(state.lastToxGeojson)], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'tox_layer.geojson';
    link.click();
  };

  const initModal = () => {
    const openModal = (id) => {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden', 'false');
    };
    const closeModal = (id) => {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
    };
    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
      btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
    });
    const openIncidentModal = (mode) => {
      openModal('incidentModal');
      const form = $('#incidentCreateForm');
      const title = $('#incidentModalTitle');
      const idField = $('#incidentIdField');
      if (!form) return;
      if (mode === 'edit') {
        form.action = '/api/incidents/update.php';
        if (title) title.textContent = 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РёРЅС†РёРґРµРЅС‚';
        const id = Number($('#incidentSelect')?.value || 0);
        if (idField) idField.value = String(id);
        const inc = incidentsById.get(id);
        if (inc) {
          form.querySelector('[name="type"]')?.value = inc.type || 'urban';
          form.querySelector('[name="status"]')?.value = inc.status || 'operational';
          form.querySelector('[name="priority"]')?.value = inc.priority || 'medium';
          form.querySelector('[name="started_at"]')?.value = inc.started_at ? inc.started_at.replace(' ', 'T') : '';
          form.querySelector('[name="summary"]')?.value = inc.summary || '';
          form.querySelector('[name="lat0"]')?.value = inc.lat0 ?? '';
          form.querySelector('[name="lon0"]')?.value = inc.lon0 ?? '';
          form.querySelector('[name="address"]')?.value = inc.address || '';
          form.querySelector('[name="calc_mode"]')?.value = inc.calc_mode || 'index';
          form.querySelector('[name="default_radius_km"]')?.value = inc.default_radius_km || cfg.radiusKm;
          form.querySelector('[name="default_window_min"]')?.value = inc.default_window_min || cfg.windowMin;
          form.querySelector('[name="uav_id"]')?.value = inc.uav_id || 'BAS-01';
          form.querySelector('[name="meteo_source"]')?.value = 'rhm';
        }
      } else {
        form.action = '/api/incidents/create.php';
        if (title) title.textContent = 'РЎРѕР·РґР°С‚СЊ РёРЅС†РёРґРµРЅС‚';
        if (idField) idField.value = '';
        form.reset();
        const dt = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const v = `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
        form.querySelector('[name="started_at"]')?.value = v;
        form.querySelector('[name="meteo_source"]')?.value = 'rhm';
      }
    };

    $('#addIncidentBtn')?.addEventListener('click', () => openIncidentModal('create'));
    $('#editIncidentBtn')?.addEventListener('click', () => openIncidentModal('edit'));

    const form = $('#incidentCreateForm');
    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const res = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json().catch(() => ({}));
        if (data.ok && data.id) {
          const url = new URL(window.location.href);
          url.searchParams.set('incident_id', data.id);
          url.searchParams.set('window_min', String(cfg.windowMin));
          url.searchParams.set('radius_km', String(cfg.radiusKm));
          url.searchParams.set('refresh_sec', String(cfg.refreshSec));
          window.location.href = url.toString();
        } else {
          const err = $('#incidentError');
          if (err) { err.style.display = 'block'; err.textContent = data.error || 'РћС€РёР±РєР° СЃРѕС…СЂР°РЅРµРЅРёСЏ'; }
        }
      });
    }

    // meteo source UI removed from modal

    let pickMap = null;
    let pickMarker = null;
    let pickLatLng = null;
    const openPick = () => {
      openModal('pickMapModal');
      setTimeout(() => {
        if (!window.L) {
          $('#pickMapHint') && ($('#pickMapHint').textContent = 'РљР°СЂС‚Р° РЅРµРґРѕСЃС‚СѓРїРЅР°');
          return;
        }
        if (!pickMap) {
          pickMap = L.map('pickMapCanvas', { attributionControl: false, zoomSnap: 1, zoomDelta: 1 }).setView(cfg.center, 12);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '',
            tileSize: 256,
            updateWhenZooming: false,
            updateWhenIdle: true,
            keepBuffer: 4,
            className: 'osm-tiles',
          }).addTo(pickMap);
          pickMap.on('click', (e) => {
            pickLatLng = e.latlng;
            if (!pickMarker) {
              const icon = L.divIcon({ className: 'pick-marker', html: '<div class="pick-dot"></div>', iconSize: [18, 18] });
              pickMarker = L.marker(e.latlng, { icon }).addTo(pickMap);
            } else {
              pickMarker.setLatLng(e.latlng);
            }
            const latEl = $('#incidentLat');
            const lonEl = $('#incidentLon');
            if (latEl) latEl.value = pickLatLng.lat.toFixed(6);
            if (lonEl) lonEl.value = pickLatLng.lng.toFixed(6);
            const btn = $('#pickMapConfirm');
            if (btn) btn.disabled = false;
          });
        } else {
          pickMap.invalidateSize();
        }
      }, 50);
    };

    $('#pickOnMapBtn')?.addEventListener('click', () => openPick());
    $('#pickMapConfirm')?.addEventListener('click', () => {
      if (!pickLatLng) return;
      const latEl = $('#incidentLat');
      const lonEl = $('#incidentLon');
      if (latEl) latEl.value = pickLatLng.lat.toFixed(6);
      if (lonEl) lonEl.value = pickLatLng.lng.toFixed(6);
      closeModal('pickMapModal');
    });
  };

  const resizeTelemetryTable = () => {
    const wrap = document.querySelector('.table-wrap--full');
    if (!wrap) return;
    const footer = document.querySelector('footer');
    const rect = wrap.getBoundingClientRect();
    const footerHeight = footer ? footer.getBoundingClientRect().height : 0;
    const available = window.innerHeight - rect.top - footerHeight - 16;
    if (Number.isFinite(available) && available > 240) {
      wrap.style.height = `${Math.floor(available)}px`;
    }
    scheduleMapInvalidate();
  };

  const scheduleMapInvalidate = () => {
    if (!map) return;
    requestAnimationFrame(() => map.invalidateSize(true));
    setTimeout(() => map.invalidateSize(true), 120);
    setTimeout(() => map.invalidateSize(true), 600);
    setTimeout(() => map.invalidateSize(true), 1200);
  };

  const ensureMapSize = () => {
    if (!map) return;
    let tries = 0;
    const timer = setInterval(() => {
      map.invalidateSize(true);
      tries += 1;
      if (tries >= 12) clearInterval(timer);
    }, 300);
  };

  const init = async () => {
    bindNumberWheel();
    initControls();
    initModal();
    updateSensorUI();
    resizeTelemetryTable();
    updateModeUI();
    setEmuEnabled(state.dataMode === 'SIM', { clearDirty: true });
    const syncWindUi = () => {
      const windCb = document.querySelector('[data-layer="wind"]');
      const dirEl = document.getElementById('emuWindDirFrom');
      const spdEl = document.getElementById('emuWindSpeed');
      const dir = dirEl ? Number(dirEl.value) : Number(state.meta?.wind_dir_deg);
      const spd = spdEl ? Number(spdEl.value) : Number(state.meta?.wind_speed_ms);
      if (windCb && !windCb.checked) {
        if (windLayer) windLayer.clearLayers();
        setWindArrow(NaN);
        return;
      }
      setWindArrow(dir);
      updateWindLayer(dir, spd);
    };
    document.addEventListener('input', (e) => {
      if (!e.target) return;
      if (e.target.id === 'emuWindDirFrom' || e.target.id === 'emuWindSpeed') syncWindUi();
    }, true);
    document.addEventListener('change', (e) => {
      if (!e.target) return;
      if (e.target.matches && e.target.matches('[data-layer="wind"]')) syncWindUi();
    }, true);
    window.addEventListener('resize', resizeTelemetryTable);
    window.addEventListener('load', scheduleMapInvalidate);
    const hasLeaflet = await ensureLeaflet();
    if (hasLeaflet && initMap()) {
      syncLayersFromUI();
      map.whenReady(() => scheduleMapInvalidate());
      map.on('load', () => scheduleMapInvalidate());
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => scheduleMapInvalidate());
      }
      const sideToggle = document.querySelector('.sidebar-toggle');
      if (sideToggle) {
        sideToggle.addEventListener('click', () => setTimeout(scheduleMapInvalidate, 80));
      }
      if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(() => scheduleMapInvalidate());
        ro.observe(mapEl);
      }
      ensureMapSize();
      if (!state.emuEnabled) {
        await loadMeta();
        await loadSettlements();
        await loadTrack();
        await loadPoints();
        await loadTox();
        if (cfg.refreshSec > 0) {
          setInterval(() => {
            if (!state.emuEnabled) {
              loadMeta();
              loadTrack();
              loadPoints();
              loadTox();
              loadSettlements();
            }
          }, cfg.refreshSec * 1000);
        }
      } else {
        updateMetaFromEmu();
        refreshEmuLayers();
        buildEmuToxLayer();
      }
    } else {
      mapEl.classList.add('map-error');
      if (!mapEl.querySelector('.map-error-msg')) {
        const msg = document.createElement('div');
        msg.className = 'map-error-msg';
        msg.textContent = 'РљР°СЂС‚Р° РЅРµРґРѕСЃС‚СѓРїРЅР°. РџСЂРѕРІРµСЂСЊС‚Рµ Р·Р°РіСЂСѓР·РєСѓ Leaflet.';
        mapEl.appendChild(msg);
      }
    }
  };

  init();
  window.__SMOKE_THREAT_BOOTSTRAPPED = true;
})();




