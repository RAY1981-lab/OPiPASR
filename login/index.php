<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/auth.php'; 
require_once __DIR__ . '/../config/db.php';  // Подключаем db.php для подключения к базе данных
require_once __DIR__ . '/../config/helpers.php';  // Подключаем helpers.php для функции

// Обрабатываем POST запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Проверяем username с использованием регулярных выражений
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        flash_set('bad', 'Неверный username или пароль.');
        redirect('/login/');
    }

    try {
        // Подключение к базе данных (используем ранее полученный $pdo)
        global $pdo; // Если объект pdo определен в db.php, используем его

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

        // Перенаправление на кабинет
        redirect('/app/');
    } catch (PDOException $e) {
        flash_set('bad', 'Ошибка сервера при входе.');
        redirect('/login/');
    }
}

$page_title = 'Вход — ОПиПАСР';
require_once __DIR__ . '/../config/header.php'; // Подключаем header

// Вывод flash-сообщений
$f = flash_get();
if ($f) {
    $cls = $f['type'] === 'ok' ? 'alert ok' : 'alert bad';
    echo '<div class="' . $cls . '"><strong>' . h($f['msg']) . '</strong></div>';
}
?>

<div class="panel">
    <div class="h1">Вход</div>
    <p class="p">Вход доступен только после одобрения администратором.</p>

    <!-- Форма входа -->
    <form class="form" method="post" autocomplete="off">
        <!-- Убрали CSRF защиту -->

        <div class="field">
            <label class="label" for="username">Username</label>
            <input class="input" id="username" name="username" required inputmode="latin"
                   pattern="[A-Za-z0-9_]{3,32}" />
        </div>

        <div class="field">
            <label class="label" for="password">Пароль</label>
            <input class="input" id="password" name="password" type="password" required />
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
            <button class="btn btn-primary" type="submit">Войти</button>
            <a class="btn btn-ghost" href="/register/">Регистрация</a>
            <a class="btn btn-ghost" href="/admin/login.php">Админ-вход</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; // Подключаем footer ?>
