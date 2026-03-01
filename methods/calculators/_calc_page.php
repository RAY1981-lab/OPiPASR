<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$calc_id = basename((string)($calc_id ?? ''));
$defs = require __DIR__ . '/_defs.php';

if (!is_string($calc_id) || $calc_id === '' || !isset($defs[$calc_id])) {
  http_response_code(404);
  $page_title = 'Калькулятор не найден — ОПиПАСР';
  require_once __DIR__ . '/../../config/header.php';
  ?>
  <section class="section">
    <h1>Калькулятор не найден</h1>
    <p class="lead">Такой страницы нет.</p>
    <a class="link" href="/methods/#calculators">← Вернуться к перечню</a>
  </section>
  <?php
  require_once __DIR__ . '/../../config/footer.php';
  exit;
}

$calc = $defs[$calc_id];
$page_title = $calc['title'] . ' — ОПиПАСР';

require_once __DIR__ . '/../../config/header.php';

$fields = $calc['fields'] ?? [];
$outputs = $calc['outputs'] ?? [];
?>

<section class="section">
  <p class="kicker">Калькулятор</p>
  <h1><?= h($calc['title']) ?></h1>
  <p class="lead"><?= h($calc['lead']) ?></p>
  <div class="note" style="margin-top:12px">
    <div><strong>Источник:</strong> <?= h($calc['ref'] ?? '—') ?></div>
    <div style="margin-top:8px"><a class="link" href="/methods/#calculators">← Все калькуляторы</a></div>
  </div>
</section>

<section class="section section-alt">
  <div class="calc-wrap">
    <form class="calc-form" data-calc="<?= h($calc_id) ?>" onsubmit="return false;">
      <div class="form-grid">
        <?php foreach ($fields as $f): ?>
          <?php
          $type = $f['type'] ?? 'number';
          $name = $f['name'] ?? '';
          $label = $f['label'] ?? $name;
          $unit = $f['unit'] ?? '';
          $value = $f['value'] ?? '';
          $step = $f['step'] ?? 'any';
          $min = $f['min'] ?? null;
          $max = $f['max'] ?? null;
          ?>
          <?php if ($type === 'checkbox'): ?>
            <div class="field" style="grid-column: 1 / -1">
              <label style="display:flex;gap:10px;align-items:flex-start">
                <input type="checkbox" name="<?= h($name) ?>" <?= !empty($f['checked']) ? 'checked' : '' ?> style="margin-top:2px" />
                <span><?= h($label) ?></span>
              </label>
            </div>
          <?php elseif ($type === 'select'): ?>
            <div class="field">
              <label for="<?= h($name) ?>"><?= h($label) ?></label>
              <select id="<?= h($name) ?>" name="<?= h($name) ?>">
                <?php foreach (($f['options'] ?? []) as $opt): ?>
                  <?php
                  $ov = (string)($opt['value'] ?? '');
                  $ol = (string)($opt['label'] ?? $ov);
                  $selected = ((string)($value) !== '' && (string)($value) === $ov) ? 'selected' : '';
                  ?>
                  <option value="<?= h($ov) ?>" <?= $selected ?>><?= h($ol) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($unit !== ''): ?><div class="help"><?= h($unit) ?></div><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="field">
              <label for="<?= h($name) ?>"><?= h($label) ?></label>
              <input
                id="<?= h($name) ?>"
                name="<?= h($name) ?>"
                type="number"
                inputmode="decimal"
                step="<?= h((string)$step) ?>"
                value="<?= h((string)$value) ?>"
                <?= $min !== null ? 'min="' . h((string)$min) . '"' : '' ?>
                <?= $max !== null ? 'max="' . h((string)$max) . '"' : '' ?>
              />
              <?php if ($unit !== ''): ?><div class="help"><?= h($unit) ?></div><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($outputs)): ?>
        <div class="calc-out" style="margin-top:12px">
          <?php foreach ($outputs as $o): ?>
            <div class="kv">
              <div class="kv-k"><?= h($o['label'] ?? ($o['key'] ?? '')) ?></div>
              <div class="kv-v" data-out="<?= h($o['key'] ?? '') ?>">—</div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/../../config/footer.php'; ?>

