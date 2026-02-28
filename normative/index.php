<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$page_title = 'Нормативные документы — ОПиПАСР';

$PDF_DIR   = __DIR__ . '/pdf';
$WEB_BASE  = '/normative/pdf';
$META_FILE = $PDF_DIR . '/_titles.json'; // {"file.pdf":"Красивое название", ...}

if (!is_dir($PDF_DIR)) {
  @mkdir($PDF_DIR, 0775, true);
}

/** страница может быть публичной */
$cu = function_exists('current_user') ? current_user() : null;
$is_admin = is_array($cu) && strtoupper((string)($cu['role'] ?? '')) === 'ADMIN';

/** ===== утилиты ===== */
function load_titles(string $path): array {
  if (!is_file($path)) return [];
  $raw = @file_get_contents($path);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function save_titles(string $path, array $titles): void {
  foreach ($titles as $k => $v) {
    if (!is_string($k) || $k === '' || !is_string($v) || trim($v) === '') unset($titles[$k]);
  }
  @file_put_contents(
    $path,
    json_encode($titles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL,
    LOCK_EX
  );
}

function safe_pdf_name(string $name): string {
  $name = basename($name);
  $name = preg_replace('~[^A-Za-z0-9._-]+~', '_', $name);
  if ($name === '' || $name === '_' || $name === '.' || $name === '..') {
    $name = 'doc_' . gmdate('Ymd_His') . '.pdf';
  }
  if (!preg_match('~\.pdf$~i', $name)) $name .= '.pdf';
  return $name;
}

function is_pdf_file_tmp(string $tmpPath): bool {
  $fh = @fopen($tmpPath, 'rb');
  if (!$fh) return false;
  $head = @fread($fh, 4);
  @fclose($fh);
  return $head === "%PDF";
}

/** ===== загрузим текущие названия ===== */
$titles = load_titles($META_FILE);

/** Если у вас раньше был ручной массив $TITLES — не ломаем */
$TITLES = $TITLES ?? [];
if (is_array($TITLES) && $TITLES) {
  $titles = $titles + $TITLES; // ручные названия приоритетнее json
}

/** ===== обработка действий админа (upload/delete/title) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_role('ADMIN');
  require_csrf();

  $action = (string)($_POST['action'] ?? '');

  if ($action === 'upload') {
    $displayTitle = trim((string)($_POST['title'] ?? ''));
    $file = $_FILES['pdf'] ?? null;

    if (!$file || !isset($file['tmp_name'], $file['error'])) {
      flash_set('bad', 'Файл не получен.');
      redirect('/normative/');
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
      flash_set('bad', 'Ошибка загрузки файла (код ' . (int)$file['error'] . ').');
      redirect('/normative/');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
      flash_set('bad', 'Загрузка не подтверждена как uploaded_file.');
      redirect('/normative/');
    }
    if (!is_pdf_file_tmp($file['tmp_name'])) {
      flash_set('bad', 'Это не PDF (нет сигнатуры %PDF).');
      redirect('/normative/');
    }
    if ($displayTitle === '') {
      flash_set('bad', 'Укажите название документа (как показывать на сайте).');
      redirect('/normative/');
    }

    $orig = safe_pdf_name((string)($file['name'] ?? 'document.pdf'));
    $target = $PDF_DIR . '/' . $orig;

    if (is_file($target)) {
      $base = preg_replace('~\.pdf$~i', '', $orig);
      $orig = $base . '_' . gmdate('Ymd_His') . '.pdf';
      $target = $PDF_DIR . '/' . $orig;
    }

    if (!@move_uploaded_file($file['tmp_name'], $target)) {
      flash_set('bad', 'Не удалось сохранить PDF в каталог /normative/pdf/. Проверьте права на запись.');
      redirect('/normative/');
    }

    $titles[$orig] = $displayTitle;
    save_titles($META_FILE, $titles);

    flash_set('ok', 'Документ загружен: ' . $displayTitle);
    redirect('/normative/');
  }

  if ($action === 'delete') {
    $fname = safe_pdf_name((string)($_POST['file'] ?? ''));
    $full  = $PDF_DIR . '/' . $fname;

    $realDir  = realpath($PDF_DIR);
    $realFile = realpath($full);
    if (!$realDir || !$realFile || strpos($realFile, $realDir) !== 0) {
      flash_set('bad', 'Некорректный путь файла.');
      redirect('/normative/');
    }

    if (is_file($realFile)) {
      @unlink($realFile);
    }
    unset($titles[$fname]);
    save_titles($META_FILE, $titles);

    flash_set('ok', 'Документ удалён.');
    redirect('/normative/');
  }

  if ($action === 'set_title') {
    $fname    = safe_pdf_name((string)($_POST['file'] ?? ''));
    $newTitle = trim((string)($_POST['title'] ?? ''));

    if ($newTitle === '') {
      unset($titles[$fname]);
    } else {
      $titles[$fname] = $newTitle;
    }
    save_titles($META_FILE, $titles);

    flash_set('ok', 'Название обновлено.');
    redirect('/normative/');
  }

  flash_set('bad', 'Неизвестное действие.');
  redirect('/normative/');
}

/** ===== список PDF ===== */
$files = [];
foreach (glob($PDF_DIR . '/*.pdf') ?: [] as $path) {
  $bn = basename($path);
  $files[] = [
    'file'  => $bn,
    'title' => $titles[$bn] ?? $bn,
    'size'  => (int)@filesize($path),
    'mtime' => (int)@filemtime($path),
  ];
}
usort($files, fn($a,$b) => ($b['mtime'] <=> $a['mtime']) ?: strcmp($a['file'], $b['file']));

require_once __DIR__ . '/../config/header.php';

$f = flash_get();
if ($f) {
  $cls = $f['type'] === 'ok' ? 'alert ok' : 'alert bad';
  echo '<div class="' . $cls . '"><strong>' . h($f['msg']) . '</strong></div>';
}
?>

<div class="panel">
  <div class="h1">Нормативные документы</div>

  <?php if ($is_admin): ?>
    <div class="alert" style="margin-top:10px">
      <strong>Режим администратора:</strong> можно загружать, удалять и менять отображаемые названия.
    </div>
  <?php endif; ?>

  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:22px 0 8px">
    <h2 style="margin:0;font-size:18px">Документы: <?= (int)count($files) ?></h2>

    <?php if ($is_admin): ?>
      <button class="btn btn-primary" type="button" data-open-modal="uploadModal">+ Добавить</button>
    <?php endif; ?>
  </div>

  <?php if (!$files): ?>
    <div class="alert">Пока нет PDF в <code>/normative/pdf/</code>.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Документ</th>
          <th style="white-space:nowrap">Размер</th>
          <th style="white-space:nowrap">Обновлён</th>
          <th style="white-space:nowrap">Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($files as $d):
        $url = $WEB_BASE . '/' . rawurlencode($d['file']);
        $sizeKb = $d['size'] ? round($d['size'] / 1024, 2) : 0;
        $dt = $d['mtime'] ? date('Y-m-d H:i', $d['mtime']) : '';
      ?>
        <tr>
          <td>
            <div style="font-weight:700;color:rgba(255,255,255,.92)"><?= h($d['title']) ?></div>
            <div style="opacity:.7;font-size:12px"><?= h($d['file']) ?></div>

            <?php if ($is_admin): ?>
              <form method="post" action="/normative/" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_title">
                <input type="hidden" name="file" value="<?= h($d['file']) ?>">
                <input class="input" name="title" style="max-width:420px"
                       value="<?= h($titles[$d['file']] ?? '') ?>"
                       placeholder="Задать/изменить название…" />
                <button class="btn" type="submit">Сохранить</button>
              </form>
            <?php endif; ?>
          </td>

          <td style="white-space:nowrap"><?= h(number_format($sizeKb, 2, '.', ' ')) ?> КБ</td>
          <td style="white-space:nowrap"><?= h($dt) ?></td>

          <td style="white-space:nowrap">
            <a class="btn btn-primary" href="<?= h($url) ?>" target="_blank" rel="noopener">Открыть</a>
            <a class="btn btn-ghost" href="<?= h($url) ?>" download>Скачать</a>

            <?php if ($is_admin): ?>
              <form method="post" action="/normative/" style="display:inline-block;margin-left:8px"
                    onsubmit="return confirm('Удалить документ: <?= h(addslashes($d['title'])) ?> ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="file" value="<?= h($d['file']) ?>">
                <button class="btn" type="submit">Удалить</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($is_admin): ?>
  <style>
    .modal-backdrop{
      position:fixed; inset:0;
      background:rgba(0,0,0,.55);
      display:none;
      align-items:center;
      justify-content:center;
      z-index:9999;
      padding:18px;
    }
    .modal-window{
      width:min(720px, 100%);
      background:rgba(10,18,30,.96);
      border:1px solid rgba(255,255,255,.12);
      border-radius:16px;
      box-shadow:0 20px 60px rgba(0,0,0,.6);
      padding:16px;
    }
    .modal-head{
      display:flex;align-items:center;justify-content:space-between;
      gap:12px;margin-bottom:10px;
    }
    .modal-title{font-size:18px;font-weight:800}
    .modal-close{
      cursor:pointer;
      border:1px solid rgba(255,255,255,.14);
      background:rgba(255,255,255,.06);
      color:rgba(255,255,255,.9);
      border-radius:10px;
      padding:8px 10px;
      line-height:1;
    }
  </style>

  <div class="modal-backdrop" id="uploadModal" aria-hidden="true">
    <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
      <div class="modal-head">
        <div class="modal-title" id="uploadModalTitle">Добавить документ (PDF)</div>
        <button class="modal-close" type="button" data-close-modal="uploadModal">✕</button>
      </div>

      <form method="post" enctype="multipart/form-data" action="/normative/" style="display:grid;gap:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">

        <div class="field">
          <label class="label">Название (как на сайте)</label>
          <input class="input" name="title" required placeholder="Например: О пожарной безопасности (ФЗ №69)" />
        </div>

        <div class="field">
          <label class="label">PDF-файл</label>
          <input class="input" type="file" name="pdf" accept="application/pdf,.pdf" required />
          <div class="help">При совпадении имени будет добавлен суффикс с датой/временем.</div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
          <button class="btn btn-primary" type="submit">Загрузить</button>
          <button class="btn btn-ghost" type="button" data-close-modal="uploadModal">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      function openModal(id){
        const el = document.getElementById(id);
        if(!el) return;
        el.style.display = 'flex';
        el.setAttribute('aria-hidden','false');
        const firstInput = el.querySelector('input,button,select,textarea');
        if(firstInput) firstInput.focus();
      }
      function closeModal(id){
        const el = document.getElementById(id);
        if(!el) return;
        el.style.display = 'none';
        el.setAttribute('aria-hidden','true');
      }

      document.addEventListener('click', function(e){
        const openBtn = e.target.closest('[data-open-modal]');
        if(openBtn){
          e.preventDefault();
          openModal(openBtn.getAttribute('data-open-modal'));
          return;
        }
        const closeBtn = e.target.closest('[data-close-modal]');
        if(closeBtn){
          e.preventDefault();
          closeModal(closeBtn.getAttribute('data-close-modal'));
          return;
        }
        const backdrop = (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) ? e.target : null;
        if(backdrop){
          closeModal(backdrop.id);
        }
      });

      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
          const opened = document.querySelector('.modal-backdrop[aria-hidden="false"]');
          if(opened) closeModal(opened.id);
        }
      });
    })();
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
