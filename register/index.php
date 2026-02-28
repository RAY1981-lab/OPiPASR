<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

/**
 * ===== Регистрационный лог =====
 * На вашем хостинге структура обычно такая:
 *   .../data/www/opipasr.ru/register/index.php
 *   .../data/logs/ingest.log
 * Значит register.log нужно писать в:
 *   .../data/logs/register.log
 */
$REQUEST_ID = bin2hex(random_bytes(8));

$LOG_DIR = null;

// 1) Самый надёжный вариант: через DOCUMENT_ROOT (если задан)
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
  // DOCUMENT_ROOT: .../data/www/opipasr.ru
  // dirname(..., 2) -> .../data
  $cand = dirname((string)$_SERVER['DOCUMENT_ROOT'], 2) . '/logs';
  if (is_dir($cand)) $LOG_DIR = $cand;
}

// 2) Резерв: от текущего файла (register -> opipasr.ru -> www -> data)
if ($LOG_DIR === null) {
  $cand = __DIR__ . '/../../../logs';
  if (is_dir($cand)) $LOG_DIR = $cand;
}

// 3) Резерв: лог рядом с проектом (если вдруг есть папка logs в домене)
if ($LOG_DIR === null) {
  $cand = dirname(__DIR__) . '/logs'; // .../opipasr.ru/logs
  if (is_dir($cand)) $LOG_DIR = $cand;
}

$LOG_FILE = ($LOG_DIR !== null)
  ? rtrim($LOG_DIR, '/\\') . '/register.log'
  : (sys_get_temp_dir() . '/register.log'); // последний фолбэк (в ISPmanager не увидите)

function reg_log(string $level, string $event, array $ctx = []): void {
  global $LOG_FILE, $REQUEST_ID;

  $row = array_merge([
    'ts_utc'     => gmdate('c'),
    'level'      => $level,
    'event'      => $event,
    'request_id' => $REQUEST_ID,
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
    'uri'        => $_SERVER['REQUEST_URI'] ?? null,
    'method'     => $_SERVER['REQUEST_METHOD'] ?? null,
    'ua'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'log_file'   => $LOG_FILE, // чтобы вы точно видели, куда пишется
  ], $ctx);

  @file_put_contents(
    $LOG_FILE,
    json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
  );
}

// Логируем любой заход на страницу, чтобы файл гарантированно создавался
reg_log('info', 'register_hit', []);

// Логируем необработанные исключения аккуратно
set_exception_handler(function (Throwable $e) {
  reg_log('error', 'unhandled_exception', [
    'type' => get_class($e),
    'code' => (string)$e->getCode(),
    'msg'  => $e->getMessage(),
  ]);

  flash_set('bad', 'Ошибка сервера при регистрации. Код: ' . ($GLOBALS['REQUEST_ID'] ?? 'n/a'));
  redirect('/register/');
});

ini_set('log_errors', '1');
error_reporting(E_ALL);


/**
 * ===== Обработка регистрации =====
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  reg_log('info', 'register_post', [
    'post_keys' => array_keys($_POST),
  ]);

  require_csrf();

  $username = trim((string)($_POST['username'] ?? ''));
  $pass1 = (string)($_POST['password'] ?? '');
  $pass2 = (string)($_POST['password2'] ?? '');

  if (!preg_match(USERNAME_REGEX, $username)) {
    reg_log('warn', 'validation_failed', ['reason' => 'bad_username', 'username' => $username]);
    flash_set('bad', 'Некорректный username: используйте латиницу/цифры/подчёркивание, 3–32 символа.');
    redirect('/register/');
  }

  if (strlen($pass1) < MIN_PASSWORD_LEN) {
    reg_log('warn', 'validation_failed', ['reason' => 'short_password', 'username' => $username]);
    flash_set('bad', 'Слишком короткий пароль. Минимум: ' . MIN_PASSWORD_LEN . ' символов.');
    redirect('/register/');
  }

  if ($pass1 !== $pass2) {
    reg_log('warn', 'validation_failed', ['reason' => 'password_mismatch', 'username' => $username]);
    flash_set('bad', 'Пароли не совпадают.');
    redirect('/register/');
  }

  $hash = password_hash($pass1, PASSWORD_DEFAULT);

  try {
    $pdo = db();

    $stmt = $pdo->prepare('
      INSERT INTO users (username, pass_hash, role, status)
      VALUES (:u, :p, :r, :s)
    ');

    $stmt->execute([
      ':u' => $username,
      ':p' => $hash,
      ':r' => 'VIEWER',
      ':s' => 'PENDING',
    ]);

    reg_log('info', 'register_ok', ['username' => $username]);

    flash_set('ok', 'Заявка принята. Доступ будет открыт после одобрения администратора.');
    redirect('/login/');

  } catch (PDOException $e) {
    $sqlstate = (string)$e->getCode();

    reg_log('error', 'register_fail', [
      'username' => $username,
      'sqlstate' => $sqlstate,
      'msg'      => $e->getMessage(),
    ]);

    if ($sqlstate === '23000') {
      flash_set('bad', 'Этот username уже занят.');
      redirect('/register/');
    }

    flash_set('bad', 'Ошибка сервера при регистрации. Код: ' . $REQUEST_ID);
    redirect('/register/');
  }
}


/**
 * ===== Отрисовка страницы =====
 */
$page_title = 'Регистрация — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';

$f = flash_get();
if ($f) {
  $cls = $f['type'] === 'ok' ? 'alert ok' : 'alert bad';
  echo '<div class="' . $cls . '"><strong>' . h($f['msg']) . '</strong></div>';
}
?>

<div class="panel">
  <div class="h1">Регистрация</div>
  <p class="p">Создайте заявку на доступ. Учётная запись активируется только после одобрения администратора.</p>

  <form method="post" action="/register/">
    <?= csrf_field() ?>

    <div class="field">
      <label class="label" for="username">Username</label>
      <input class="input" id="username" name="username" required
             placeholder="например: rtp_operator_1"
             inputmode="latin"
             pattern="[A-Za-z0-9_]{3,32}"
             title="Латиница/цифры/подчёркивание, 3–32 символа" />
      <div class="help">Допустимы: латинские буквы, цифры, подчёркивание. Длина 3–32.</div>
    </div>

    <div class="field">
      <label class="label" for="password">Пароль</label>
      <input class="input" id="password" name="password" type="password" required minlength="<?= (int)MIN_PASSWORD_LEN ?>" />
      <div class="help">Минимум <?= (int)MIN_PASSWORD_LEN ?> символов.</div>
    </div>

    <div class="field">
      <label class="label" for="password2">Повтор пароля</label>
      <input class="input" id="password2" name="password2" type="password" required minlength="<?= (int)MIN_PASSWORD_LEN ?>" />
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
      <button class="btn btn-primary" type="submit">Отправить заявку</button>
      <a class="btn btn-ghost" href="/login/">Уже есть доступ? Войти</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
