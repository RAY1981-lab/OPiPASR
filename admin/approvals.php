<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role('ADMIN');

$pdo = db();

function try_log_action(PDO $pdo, int $admin_id, int $target_id, string $action, ?string $new_role = null): void {
  // Пишем в approvals_log, но не падаем, если таблицы нет/ошибка прав/схемы.
  try {
    if ($new_role !== null) {
      $pdo->prepare("INSERT INTO approvals_log (admin_user_id, target_user_id, action, new_role)
                     VALUES (:a,:t,:ac,:r)")
          ->execute([':a'=>$admin_id, ':t'=>$target_id, ':ac'=>$action, ':r'=>$new_role]);
    } else {
      $pdo->prepare("INSERT INTO approvals_log (admin_user_id, target_user_id, action)
                     VALUES (:a,:t,:ac)")
          ->execute([':a'=>$admin_id, ':t'=>$target_id, ':ac'=>$action]);
    }
  } catch (Throwable $e) {
    // игнорируем
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();

  $action    = (string)($_POST['action'] ?? '');
  $target_id = (int)($_POST['user_id'] ?? 0);

  $admin = current_user();
  $admin_id = (int)($admin['id'] ?? 0);

  if (!$target_id) {
    flash_set('bad', 'Некорректные параметры (user_id).');
    redirect('/admin/approvals.php');
  }

  // Запрещаем удалять/менять самого себя опасными операциями
  if ($target_id === $admin_id && ($action === 'delete_user' || $action === 'disable')) {
    flash_set('bad', 'Нельзя выполнить это действие над самим собой.');
    redirect('/admin/approvals.php');
  }

  // Получим целевого пользователя (нужно для проверок роли/статуса)
  $stmtU = $pdo->prepare("SELECT id, username, role, status FROM users WHERE id=:id LIMIT 1");
  $stmtU->execute([':id' => $target_id]);
  $target = $stmtU->fetch();

  if (!$target) {
    flash_set('bad', 'Пользователь не найден.');
    redirect('/admin/approvals.php');
  }

  $target_role = strtoupper((string)($target['role'] ?? ''));
  $target_name = (string)($target['username'] ?? '');

  // Нельзя удалять/править админов через эту страницу
  if ($target_role === 'ADMIN' && ($action === 'delete_user' || $action === 'change_role' || $action === 'disable')) {
    flash_set('bad', 'Нельзя изменять/удалять пользователя с ролью ADMIN.');
    redirect('/admin/approvals.php');
  }

  if ($action === 'approve') {
    $new_role = strtoupper(trim((string)($_POST['role'] ?? 'VIEWER')));

    // роли для назначения при одобрении (ADMIN запрещён)
    $allowed_roles = ['VIEWER','OPERATOR','RTP'];
    if (!in_array($new_role, $allowed_roles, true)) {
      flash_set('bad', 'Некорректная роль для одобрения.');
      redirect('/admin/approvals.php');
    }

    $pdo->prepare("UPDATE users
                   SET status='ACTIVE', role=:r, approved_at=NOW(), approved_by=:ab
                   WHERE id=:id AND status='PENDING'")
        ->execute([':r'=>$new_role, ':ab'=>$admin_id, ':id'=>$target_id]);

    try_log_action($pdo, $admin_id, $target_id, 'APPROVE', $new_role);

    flash_set('ok', 'Заявка одобрена: роль ' . $new_role . '.');
    redirect('/admin/approvals.php');
  }

  if ($action === 'disable') {
    $pdo->prepare("UPDATE users SET status='DISABLED' WHERE id=:id")->execute([':id'=>$target_id]);
    try_log_action($pdo, $admin_id, $target_id, 'DISABLE', null);

    flash_set('ok', 'Пользователь отключён.');
    redirect('/admin/approvals.php');
  }

  if ($action === 'change_role') {
    $new_role = strtoupper(trim((string)($_POST['role'] ?? 'VIEWER')));
    $allowed_roles = ['VIEWER','OPERATOR','RTP']; // ADMIN запрещаем

    if (!in_array($new_role, $allowed_roles, true)) {
      flash_set('bad', 'Некорректная роль.');
      redirect('/admin/approvals.php');
    }

    $pdo->prepare("UPDATE users SET role=:r WHERE id=:id AND role<>'ADMIN'")
        ->execute([':r'=>$new_role, ':id'=>$target_id]);

    try_log_action($pdo, $admin_id, $target_id, 'CHANGE_ROLE', $new_role);

    flash_set('ok', 'Роль пользователя ' . h($target_name) . ' изменена на ' . $new_role . '.');
    redirect('/admin/approvals.php');
  }

  if ($action === 'delete_user') {
    // Жёсткое удаление (необратимо)
    try {
      $pdo->beginTransaction();

      // чистим логи, если таблица есть
      try {
        $pdo->prepare("DELETE FROM approvals_log WHERE admin_user_id=:id OR target_user_id=:id")
            ->execute([':id'=>$target_id]);
      } catch (Throwable $e) {
        // игнорируем
      }

      // удаляем самого пользователя (ADMIN уже отфильтрован выше)
      $stmt = $pdo->prepare("DELETE FROM users WHERE id=:id AND role<>'ADMIN'");
      $stmt->execute([':id'=>$target_id]);

      $pdo->commit();

      if ($stmt->rowCount() < 1) {
        flash_set('bad', 'Не удалось удалить пользователя (возможно, запрещено или уже удалён).');
        redirect('/admin/approvals.php');
      }

      flash_set('ok', 'Пользователь удалён: ' . h($target_name));
      redirect('/admin/approvals.php');

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();

      // На случай внешних связей/ограничений
      flash_set('bad', 'Не удалось удалить пользователя. Если есть связи в БД — используйте "Отключить" (DISABLED).');
      redirect('/admin/approvals.php');
    }
  }

  flash_set('bad', 'Неизвестное действие.');
  redirect('/admin/approvals.php');
}

$page_title = 'Одобрение заявок — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';

$f = flash_get();
if ($f) {
  $cls = $f['type'] === 'ok' ? 'alert ok' : 'alert bad';
  echo '<div class="' . $cls . '"><strong>' . h($f['msg']) . '</strong></div>';
}

$pending = $pdo->query("
  SELECT id, username, created_at
  FROM users
  WHERE status='PENDING'
  ORDER BY created_at ASC, id ASC
")->fetchAll();

$active = $pdo->query("
  SELECT id, username, role, status, approved_at, last_login_at
  FROM users
  WHERE status='ACTIVE'
  ORDER BY approved_at DESC, id DESC
  LIMIT 50
")->fetchAll();

$me = current_user();
$my_id = (int)($me['id'] ?? 0);
?>

<div class="panel">
  <div class="h1">Одобрение заявок</div>
  <p class="p">Заявки со статусом <?= status_badge('PENDING') ?>. Назначьте роль при одобрении.</p>

  <h2 style="margin:18px 0 8px;font-size:18px">Ожидают (PENDING): <?= (int)count($pending) ?></h2>

  <?php if (!$pending): ?>
    <div class="alert ok">Нет заявок на одобрение.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Username</th>
          <th>Создано</th>
          <th>Роль</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $u): ?>
          <tr>
            <td><strong style="color:rgba(255,255,255,.88)"><?= h($u['username']) ?></strong></td>
            <td><?= h((string)($u['created_at'] ?? '')) ?></td>
            <td>
              <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <select class="select" name="role">
                  <option value="VIEWER">VIEWER</option>
                  <option value="OPERATOR">OPERATOR</option>
                  <option value="RTP">RTP</option>
                </select>
            </td>
            <td>
                <button class="btn btn-primary" type="submit" name="action" value="approve">Одобрить</button>
                <button class="btn" type="submit" name="action" value="disable"
                        onclick="return confirm('Отклонить/отключить пользователя?')">Отклонить</button>
                <button class="btn" type="submit" name="action" value="delete_user"
                        onclick="return confirm('Удалить пользователя НАВСЕГДА? Это необратимо.')">Удалить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2 style="margin:22px 0 8px;font-size:18px">Активные (последние 50)</h2>

  <?php if (!$active): ?>
    <div class="alert">Пока нет активных пользователей.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Username</th>
          <th>Роль</th>
          <th>Статус</th>
          <th>Одобрено</th>
          <th>Последний вход</th>
          <th>Управление</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($active as $u):
          $uid = (int)$u['id'];
          $role = strtoupper((string)($u['role'] ?? ''));
          $is_admin_row = ($role === 'ADMIN');
          $is_me = ($uid === $my_id);
        ?>
          <tr>
            <td><strong style="color:rgba(255,255,255,.88)"><?= h($u['username']) ?></strong></td>
            <td><?= role_badge((string)$u['role']) ?></td>
            <td><?= status_badge((string)$u['status']) ?></td>
            <td><?= h((string)($u['approved_at'] ?? '')) ?></td>
            <td><?= h((string)($u['last_login_at'] ?? '—')) ?></td>

            <td style="white-space:nowrap">
              <?php if ($is_admin_row): ?>
                <span style="opacity:.8">ADMIN</span>
              <?php elseif ($is_me): ?>
                <span style="opacity:.8">нельзя менять себя</span>
              <?php else: ?>
                <form method="post" style="display:inline-flex;gap:10px;align-items:center;flex-wrap:wrap">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= $uid ?>">
                  <select class="select" name="role">
                    <option value="VIEWER"   <?= $role==='VIEWER'?'selected':'' ?>>VIEWER</option>
                    <option value="OPERATOR" <?= $role==='OPERATOR'?'selected':'' ?>>OPERATOR</option>
                    <option value="RTP"      <?= $role==='RTP'?'selected':'' ?>>RTP</option>
                  </select>
                  <button class="btn btn-primary" type="submit" name="action" value="change_role">Сменить</button>
                  <button class="btn" type="submit" name="action" value="delete_user"
                          onclick="return confirm('Удалить пользователя НАВСЕГДА? Это необратимо.')">Удалить</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="/admin/logout.php">Выход</a>
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
