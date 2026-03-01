<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
  redirect('/');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  require_csrf();

  $username = trim((string)($_POST['username'] ?? ''));
  $pass1 = (string)($_POST['password'] ?? '');
  $pass2 = (string)($_POST['password2'] ?? '');

  if (!preg_match(USERNAME_REGEX, $username)) {
    flash_set('bad', 'Некорректный логин: используйте латиницу/цифры/подчёркивание, 3–32 символа.');
    redirect('/register/');
  }

  if (strlen($pass1) < MIN_PASSWORD_LEN) {
    flash_set('bad', 'Слишком короткий пароль. Минимум: ' . MIN_PASSWORD_LEN . ' символов.');
    redirect('/register/');
  }

  if ($pass1 !== $pass2) {
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
      ':r' => 'GUEST',
      ':s' => 'PENDING',
    ]);

    flash_set('ok', 'Заявка на регистрацию отправлена. Дождитесь подтверждения администратора, затем выполните вход.');
    redirect('/login/');

  } catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
      flash_set('bad', 'Этот логин уже занят.');
      redirect('/register/');
    }

    flash_set('bad', 'Ошибка сервера при регистрации.');
    redirect('/register/');
  }
}


/**
 * ===== Отрисовка страницы =====
 */
$page_title = 'Регистрация — ОПиПАСР';
$header_variant = 'public';
$body_class = 'page-auth';
$page_wrap_container = false;
require_once __DIR__ . '/../config/header.php';

$f = flash_get();
?>

<section class="auth-shell">
  <div class="auth-card">
    <div class="auth-avatar" aria-hidden="true">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5Z" stroke="currentColor" stroke-width="1.6"/>
        <path d="M4 21c0-4.418 3.582-8 8-8s8 3.582 8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
    </div>

    <div class="auth-title">Регистрация</div>
    <div class="auth-subtitle">Создайте учётную запись для доступа к закрытому контуру.</div>

    <?php if ($f): ?>
      <div class="alert <?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>"><strong><?= h($f['msg']) ?></strong></div>
    <?php endif; ?>

    <form method="post" action="/register/" autocomplete="off">
      <?= csrf_field() ?>

      <div class="auth-fields">
        <div class="auth-input">
          <div class="auth-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5Z" stroke="currentColor" stroke-width="1.8"/>
              <path d="M4 21c0-4.418 3.582-8 8-8s8 3.582 8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <input
            name="username"
            required
            placeholder="Логин"
            inputmode="latin"
            pattern="[A-Za-z0-9_]{3,32}"
          />
        </div>

        <div class="auth-input">
          <div class="auth-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M7 10h10a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </div>
          <input name="password" type="password" required minlength="<?= (int)MIN_PASSWORD_LEN ?>" placeholder="Пароль" />
        </div>

        <div class="auth-input">
          <div class="auth-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M7 10h10a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
              <path d="M12 14v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <input name="password2" type="password" required minlength="<?= (int)MIN_PASSWORD_LEN ?>" placeholder="Повтор пароля" />
        </div>
      </div>

      <button class="btn btn-primary auth-submit" type="submit">ЗАРЕГИСТРИРОВАТЬСЯ</button>
    </form>

    <div class="auth-alt">Уже есть аккаунт? <a href="/login/">Войти</a></div>
  </div>
</section>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
