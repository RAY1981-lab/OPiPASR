<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';  // Подключаем bootstrap

// Обрабатываем POST запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Убираем проверку CSRF
    // require_csrf();  // Эта строка удалена

    // Получаем данные из формы
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Проверяем username с использованием регулярных выражений
    if (!preg_match(USERNAME_REGEX, $username)) {
        flash_set('bad', 'Неверный логин или пароль.');
        redirect('/admin/login.php');
    }

    try {
        $pdo = db();

        // Подготовка запроса для получения пользователя по username
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        // Если пользователь не найден или пароль неверный
        if (!$user || !password_verify($password, (string)$user['pass_hash'])) {
            flash_set('bad', 'Неверный логин или пароль.');
            redirect('/admin/login.php');
        }

        // Проверка статуса пользователя и роли
        if (strtoupper((string)$user['status']) !== 'ACTIVE' || strtoupper((string)$user['role']) !== 'ADMIN') {
            flash_set('bad', 'Нет прав администратора или аккаунт не активен.');
            redirect('/admin/login.php');
        }

        // Обновление времени последнего входа
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => (int)$user['id']]);

        // Логиним пользователя
        login_user($user);

        // Перенаправление на кабинет администратора
        redirect('/admin/approvals.php');

    } catch (Throwable $e) {
        flash_set('bad', 'Ошибка сервера при входе.');
        redirect('/admin/login.php');
    }
}

$page_title = 'Админ-вход — ОПиПАСР';
require_once __DIR__ . '/../config/header.php'; // Подключаем header

// Вывод flash-сообщений
$f = flash_get();
if ($f) {
    $cls = $f['type'] === 'ok' ? 'alert ok' : 'alert bad';
    echo '<div class="' . $cls . '"><strong>' . h($f['msg']) . '</strong></div>';
}
?>

<div class="panel">
    <div class="h1">Админ-вход</div>
    <p class="p">Вход только для роли <strong>ADMIN</strong> и статуса <strong>ACTIVE</strong>.</p>

    <div class="alert">
        <strong>Первый запуск:</strong> создайте администратора через
        <code style="color:rgba(255,255,255,.9)">/admin/bootstrap_admin.php</code> и удалите этот файл.
    </div>

    <form class="form" method="post" autocomplete="off">
        <!-- Убрали csrf_field() -->
        <div class="field">
            <label class="label" for="username">Admin username</label>
            <input class="input" id="username" name="username" required inputmode="latin"
                   pattern="[A-Za-z0-9_]{3,32}" />
        </div>

        <div class="field">
            <label class="label" for="password">Пароль</label>
            <input class="input" id="password" name="password" type="password" required />
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
            <button class="btn btn-primary" type="submit">Войти</button>
            <a class="btn btn-ghost" href="/login/">Пользовательский вход</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; // Подключаем footer ?>
