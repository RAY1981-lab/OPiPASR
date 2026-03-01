(() => {
  const fmt = (value, digits = 2) => {
    if (!Number.isFinite(value)) return '—';
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(value);
  };

  const num = (form, name) => {
    const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
    if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement)) return NaN;
    const v = (el instanceof HTMLSelectElement) ? Number(el.value) : Number(el.value);
    return Number.isFinite(v) ? v : NaN;
  };

  const bool = (form, name) => {
    const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
    return el instanceof HTMLInputElement ? Boolean(el.checked) : false;
  };

  const setText = (form, key, text) => {
    const out = form.querySelector(`[data-out="${CSS.escape(key)}"]`);
    if (out) out.textContent = String(text);
  };

  const g = 9.81;

  const calculators = {
    'urban-h-work': (form) => {
      const h0 = num(form, 'h0_m');
      const hSmoke = num(form, 'h_smoke_m');
      const dH = num(form, 'dh_smoke_m');
      const hWork = Math.max(h0, hSmoke + dH);
      setText(form, 'h_work_m', `${fmt(hWork, 1)} м`);
    },

    'urban-omega-dop': (form) => {
      const inForbidden = bool(form, 'in_forbidden');
      const inOfp = bool(form, 'in_ofp');
      const inCollapse = bool(form, 'in_collapse');
      const allowed = !(inForbidden || inOfp || inCollapse);
      setText(form, 'allowed', allowed ? 'ДА' : 'НЕТ');
      const reasons = [];
      if (inForbidden) reasons.push('Ωзапр');
      if (inOfp) reasons.push('ΩОФП');
      if (inCollapse) reasons.push('Ωобр');
      setText(form, 'reason', allowed ? 'Точка находится в Ωдоп' : `Исключено: ${reasons.join(', ')}`);
    },

    'urban-route-score': (form) => {
      const lambda = num(form, 'lambda');
      let j = 0;
      for (let i = 1; i <= 3; i++) {
        const l = num(form, `l${i}_m`);
        const r = num(form, `r${i}`);
        if (!Number.isFinite(l) || !Number.isFinite(r)) continue;
        j += l * (1 + lambda * r);
      }
      setText(form, 'j', `${fmt(j, 0)} усл.ед.`);
    },

    'urban-risk-aggregate': (form) => {
      const rt = num(form, 'rt');
      const rs = num(form, 'rs');
      const rc = num(form, 'rc');
      const rg = num(form, 'rg');
      const wt = num(form, 'wt');
      const wS = num(form, 'wS');
      const wC = num(form, 'wC');
      const wG = num(form, 'wG');
      const sumW = wt + wS + wC + wG;
      const r = (wt * rt + wS * rs + wC * rc + wG * rg) / (sumW || 1);
      setText(form, 'r', fmt(r, 3));
      setText(form, 'sum_w', fmt(sumW, 2));
    },

    'urban-turn-radius': (form) => {
      const v = num(form, 'v_ms');
      const phi = num(form, 'phi_deg');
      const denom = g * Math.tan((phi * Math.PI) / 180);
      const rmin = v * v / denom;
      setText(form, 'rmin_m', `${fmt(rmin, 1)} м`);
    },

    'urban-corridor-width': (form) => {
      const v = num(form, 'v_ms');
      const phi = num(form, 'phi_deg');
      const dSide = num(form, 'd_side_m');
      const denom = g * Math.tan((phi * Math.PI) / 180);
      const rmin = v * v / denom;
      const b = rmin + dSide;
      setText(form, 'rmin_m', `${fmt(rmin, 1)} м`);
      setText(form, 'b_m', `${fmt(b, 1)} м`);
    },

    'urban-mission-profile': (form) => {
      const goal = String(form.querySelector('[name="goal"]')?.value || 'overview');
      const navOk = bool(form, 'nav_ok');
      const linkOk = bool(form, 'link_ok');
      const visOk = bool(form, 'vis_ok');

      let mode = 'A';
      let note = 'Обзорный профиль: поддержание общей картины.';
      if (!(navOk && linkOk && visOk)) {
        mode = 'C';
        note = 'Режим деградации: безопасная высота, снижение скорости, упрощение профиля.';
      } else if (goal === 'focus') {
        mode = 'B';
        note = 'Фокусный профиль: висение/медленное перемещение, детальная оценка зон интереса.';
      }

      setText(form, 'mode', mode);
      setText(form, 'note', note);
    },

    'wildfire-scan-strip': (form) => {
      const h = num(form, 'h_m');
      const alpha = num(form, 'alpha_deg');
      const eta = num(form, 'eta');
      const w = 2 * h * Math.tan((alpha * Math.PI) / 180 / 2);
      const wEff = w * (1 - eta);
      setText(form, 'w_m', `${fmt(w, 2)} м`);
      setText(form, 'w_eff_m', `${fmt(wEff, 2)} м`);
    },

    'wildfire-coverage-rate': (form) => {
      const v = num(form, 'v_ms');
      const wEff = num(form, 'w_eff_m');
      const q = v * wEff;
      setText(form, 'q_m2s', `${fmt(q, 0)} м²/с`);
    },

    'wildfire-coverage-time': (form) => {
      const a = num(form, 'a_m2');
      const q = num(form, 'q_m2s');
      const tS = a / q;
      setText(form, 't_min', `${fmt(tS / 60, 1)} мин`);
      setText(form, 't_s', `${fmt(tS, 0)} с`);
    },

    'wildfire-params-tmax': (form) => {
      const a = num(form, 'a_m2');
      const tMaxMin = num(form, 'tmax_min');
      const h = num(form, 'h_m');
      const v = num(form, 'v_ms');
      const alpha = num(form, 'alpha_deg');
      const eta = num(form, 'eta');

      const tMaxS = tMaxMin * 60;
      const qReq = a / tMaxS;
      const w = 2 * h * Math.tan((alpha * Math.PI) / 180 / 2);
      const wEff = w * (1 - eta);
      const qNow = v * wEff;
      const tNow = a / qNow;

      const wEffReq = qReq / v;
      const hReq = wEffReq / (2 * Math.tan((alpha * Math.PI) / 180 / 2) * (1 - eta));
      const vReq = qReq / wEff;
      const nReq = Math.ceil(tNow / tMaxS);

      setText(form, 'q_req', `${fmt(qReq, 0)} м²/с`);
      setText(form, 't_now', `${fmt(tNow / 60, 1)} мин`);
      setText(form, 'h_req', `${fmt(hReq, 1)} м`);
      setText(form, 'v_req', `${fmt(vReq, 1)} м/с`);
      setText(form, 'n_req', String(Number.isFinite(nReq) ? nReq : '—'));
    },

    'wildfire-monitoring-mode': (form) => {
      const objective = String(form.querySelector('[name="objective"]')?.value || 'detect');
      const hotSmoke = bool(form, 'hot_smoke');
      let mode = 'Обзорный';
      let product = 'Точки очагов, первичный контур, лента событий';
      if (objective === 'edge') {
        mode = 'Уточнение кромки';
        product = 'Периметр/кромка, «горячие точки», динамика';
      } else if (objective === 'protect') {
        mode = 'Контроль/защита';
        product = 'Предупреждения, контроль рубежей, зоны угрозы';
      }
      if (hotSmoke) product += ' (приоритет ИК, работа над «горячим дымом»)';
      setText(form, 'mode', mode);
      setText(form, 'product', product);
    },

    'wildfire-patrol-mode': (form) => {
      const pattern = String(form.querySelector('[name="pattern"]')?.value || 'lawnmower');
      const mapping = {
        lawnmower: 'Галсирование (lawnmower): равномерное покрытие площади.',
        perimeter: 'Обход периметра: актуализация кромки/периметра.',
        sector: 'Секторный режим: приоритетные направления/участки.'
      };
      setText(form, 'pattern_text', mapping[pattern] || '—');
    },

    'link-budget': (form) => {
      const rVideo = num(form, 'rv_mbps');
      const rIr = num(form, 'rir_mbps');
      const rTel = num(form, 'rt_mbps');
      const eta = num(form, 'eta');
      const k = num(form, 'k_res');
      const sum = rVideo + rIr + rTel;
      const rReq = (sum / eta) * k;
      setText(form, 'sum', `${fmt(sum, 2)} Мбит/с`);
      setText(form, 'rreq', `${fmt(rReq, 2)} Мбит/с`);
    },

    'link-sufficiency': (form) => {
      const rAvail = num(form, 'ravail_mbps');
      const rReq = num(form, 'rreq_mbps');
      const k = rAvail / rReq;
      setText(form, 'kkan', fmt(k, 2));
      let mode = 'Штатный режим';
      if (k < 0.6) mode = 'Приоритет телеметрии/событий';
      else if (k < 1) mode = 'Режим деградации';
      setText(form, 'mode', mode);
    },

    'link-delivery-prob': (form) => {
      const p = num(form, 'p');
      const n = num(form, 'n');
      const ps = 1 - Math.pow(1 - p, n);
      setText(form, 'ps', fmt(ps, 4));
    },

    'link-degradation': (form) => {
      const k = num(form, 'kkan');
      let text = 'Штатный режим: видео/ИК/телеметрия по плану.';
      if (k < 0.6) {
        text = 'Kкан < 0,6: приоритет телеметрии и событий; видео — только эпизодически; передача контуров/меток (вектор) вместо «второго видео».';
      } else if (k < 1) {
        text = '0,6 ≤ Kкан < 1: режим деградации — снижение битрейта/разрешения, пониженный FPS, буферизация, QoS (события/телеметрия выше видео).';
      }
      setText(form, 'recommend', text);
    },

    'metrics-air': (form) => {
      const a1 = num(form, 'a1_m2');
      const a2 = num(form, 'a2_m2');
      const dtMin = num(form, 'dt_min');
      const da = a2 - a1;
      const rate = da / (dtMin * 60);
      setText(form, 'da', `${fmt(da, 0)} м²`);
      setText(form, 'rate', `${fmt(rate, 2)} м²/с`);
    },

    'metrics-ofp': (form) => {
      const co = num(form, 'co_ppm');
      const o2 = num(form, 'o2_pct');
      const temp = num(form, 't_c');
      let status = 'Низкий риск';
      if (co >= 100 || o2 <= 19.5 || temp >= 60) status = 'Повышенный риск';
      if (co >= 200 || o2 <= 18.0 || temp >= 80) status = 'Высокий риск';
      setText(form, 'status', status);
    },

    'metrics-sar': (form) => {
      const dist = num(form, 'dist_m');
      const speed = num(form, 'speed_ms');
      const t = dist / speed;
      setText(form, 't_min', `${fmt(t / 60, 1)} мин`);
    },

    'metrics-decision': (form) => {
      const areaHa = num(form, 'area_ha');
      const wind = num(form, 'wind_ms');
      const people = num(form, 'people');
      const score = areaHa * 0.6 + wind * 1.2 + Math.log10(people + 1) * 4;
      const rank = Math.min(5, Math.max(1, Math.round(score / 10)));
      setText(form, 'score', fmt(score, 1));
      setText(form, 'rank', String(rank));
    }
  };

  const bindCalc = (form) => {
    const calcId = String(form.getAttribute('data-calc') || '');
    const fn = calculators[calcId];
    if (!fn) return;

    const run = () => fn(form);
    run();
    form.addEventListener('input', run);
    form.addEventListener('change', run);
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.calc-form[data-calc]').forEach((el) => {
      if (el instanceof HTMLFormElement) bindCalc(el);
    });
  });
})();

