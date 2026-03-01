<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect('/');
}

// Обрабатываем POST запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Получаем данные из формы
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Проверяем username с использованием регулярных выражений
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        flash_set('bad', 'Неверный username или пароль.');
        redirect('/login/');
    }

    try {
        $pdo = db();

        // Подготовка запроса для получения пользователя по username
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        // Если пользователь не найден или пароль неверный
        if (!$user || !password_verify($password, (string)$user['pass_hash'])) {
            flash_set('bad', 'Неверный username или пароль.');
            redirect('/login/');
        }

        // Проверка статуса пользователя
        if (strtoupper((string)$user['status']) !== 'ACTIVE') {
            flash_set('bad', 'Учётная запись не активирована. Статус: ' . strtoupper((string)$user['status']) . '.');
            redirect('/login/');
        }

        // Обновление времени последнего входа
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $user['id']]);

        // Логиним пользователя
        login_user($user);

        // Перенаправление в закрытую версию (главная)
        redirect('/');
    } catch (Throwable $e) {
        flash_set('bad', 'Ошибка сервера при входе.');
        redirect('/login/');
    }
}

$page_title = 'Вход — ОПиПАСР';
$header_variant = 'public';
$body_class = 'page-auth';
$page_wrap_container = false;
require_once __DIR__ . '/../config/header.php'; // Подключаем header

// Вывод flash-сообщений
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

        <div class="auth-title">Вход</div>
        <div class="auth-subtitle">Введите данные учётной записи для доступа к закрытому контуру.</div>

        <?php if ($f): ?>
            <div class="alert <?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>"><strong><?= h($f['msg']) ?></strong></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
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
                        inputmode="latin"
                        pattern="[A-Za-z0-9_]{3,32}"
                        placeholder="Username"
                    />
                </div>

                <div class="auth-input">
                    <div class="auth-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M7 10h10a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </div>
                    <input
                        name="password"
                        type="password"
                        required
                        placeholder="Password"
                    />
                </div>
            </div>

            <div class="auth-meta">
                <label style="display:flex;gap:8px;align-items:center">
                    <input type="checkbox" name="remember" value="1" />
                    <span>Запомнить меня</span>
                </label>
                <a href="#" onclick="return false;">Забыли пароль?</a>
            </div>

            <button class="btn btn-primary auth-submit" type="submit">LOGIN</button>
        </form>

        <div class="auth-alt">Нет аккаунта? <a href="/register/">Регистрация</a></div>
        <div class="auth-alt"><a href="/admin/login.php">Админ-вход</a></div>
    </div>
</section>

<?php require_once __DIR__ . '/../config/footer.php'; // Подключаем footer ?>
