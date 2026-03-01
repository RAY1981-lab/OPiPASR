<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role('ADMIN');

$pdo = db();

$user_id = (int)($_GET['user_id'] ?? 0);
if ($user_id <= 0) {
  redirect('/admin/approvals.php');
}

$stmt = $pdo->prepare("SELECT id, username, role, status FROM users WHERE id=:id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$target = $stmt->fetch();

if (!$target) {
  flash_set('bad', 'Пользователь не найден.');
  redirect('/admin/approvals.php');
}

$target_role = strtoupper((string)($target['role'] ?? 'GUEST'));
$perms = [
  'normative' => 'Нормативные документы',
  'methods'   => 'Методики и модели',
  'about'     => 'О системе',
  'contacts'  => 'Контакты',
  'cabinet'   => 'Кабинет',
  'telemetry' => 'Телеметрия',
  'incidents' => 'Инциденты',
  'reports'   => 'Отчёты',
  'calculators' => 'Калькуляторы',
  'settings'  => 'Настройки телеметрии',
  'admin'     => 'Админ-панель',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  require_csrf();

  $action = (string)($_POST['action'] ?? 'save');
  if ($action === 'reset') {
    $pdo->prepare("DELETE FROM user_permissions WHERE user_id=:id")->execute([':id' => $user_id]);
    flash_set('ok', 'Права сброшены к значениям по роли.');
    redirect('/admin/permissions.php?user_id=' . $user_id);
  }

  $defaults = default_permissions_for_role($target_role);
  foreach ($perms as $key => $_label) {
    $v = !empty($_POST['perm'][$key]['view']);
    $e = !empty($_POST['perm'][$key]['edit']);

    $dv = (bool)($defaults[$key]['view'] ?? false);
    $de = (bool)($defaults[$key]['edit'] ?? false);

    if ($v === $dv && $e === $de) {
      $pdo->prepare("DELETE FROM user_permissions WHERE user_id=:id AND perm_key=:k")
          ->execute([':id' => $user_id, ':k' => $key]);
      continue;
    }

    $pdo->prepare("
      INSERT INTO user_permissions (user_id, perm_key, can_view, can_edit)
      VALUES (:id, :k, :v, :e)
      ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_edit=VALUES(can_edit)
    ")->execute([
      ':id' => $user_id,
      ':k'  => $key,
      ':v'  => $v ? 1 : 0,
      ':e'  => $e ? 1 : 0,
    ]);
  }

  flash_set('ok', 'Права сохранены.');
  redirect('/admin/permissions.php?user_id=' . $user_id);
}

$defaults = default_permissions_for_role($target_role);
$overrides = permission_overrides_for_user($user_id);
$f = flash_get();

$page_title = 'Права доступа — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';
?>

<?php if ($f): ?>
  <div class="alert <?= $f['type'] === 'ok' ? 'ok' : 'bad' ?>"><strong><?= h($f['msg']) ?></strong></div>
<?php endif; ?>

<div class="panel">
  <div class="h1">Права доступа</div>
  <p class="p" style="margin-top:6px">
    Пользователь: <strong><?= h((string)$target['username']) ?></strong>
    <span style="opacity:.65">•</span>
    Роль: <?= role_badge((string)($target['role'] ?? '')) ?>
    <span style="opacity:.65">•</span>
    Статус: <?= status_badge((string)($target['status'] ?? '')) ?>
  </p>

  <div class="note" style="margin-top:12px">
    <strong>Важно:</strong> доступ к админ-панели остаётся привязан к роли <strong>ADMIN</strong>.
    Разрешение <em>«Админ-панель»</em> используется для будущей детализации (например, «только просмотр»).
  </div>

  <form method="post" style="margin-top:14px">
    <?= csrf_field() ?>

    <table class="table">
      <thead>
        <tr>
          <th>Раздел</th>
          <th>Просмотр</th>
          <th>Изменение</th>
          <th>По роли</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($perms as $key => $label):
          $def_v = (bool)($defaults[$key]['view'] ?? false);
          $def_e = (bool)($defaults[$key]['edit'] ?? false);
          $cur_v = isset($overrides[$key]) ? (bool)$overrides[$key]['view'] : $def_v;
          $cur_e = isset($overrides[$key]) ? (bool)$overrides[$key]['edit'] : $def_e;
        ?>
          <tr>
            <td><strong style="color:rgba(255,255,255,.88)"><?= h($label) ?></strong></td>
            <td>
              <label class="switch" title="Разрешить просмотр">
                <input type="checkbox" name="perm[<?= h($key) ?>][view]" value="1" <?= $cur_v ? 'checked' : '' ?> />
                <span class="switch-ui" aria-hidden="true"></span>
              </label>
            </td>
            <td>
              <label class="switch" title="Разрешить изменение">
                <input type="checkbox" name="perm[<?= h($key) ?>][edit]" value="1" <?= $cur_e ? 'checked' : '' ?> />
                <span class="switch-ui" aria-hidden="true"></span>
              </label>
            </td>
            <td style="color:rgba(255,255,255,.62);font-size:13px">
              <?= $def_v ? 'просмотр' : '—' ?><?= $def_e ? ' / изменение' : '' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit" name="action" value="save">Сохранить</button>
      <button class="btn btn-ghost" type="submit" name="action" value="reset"
              onclick="return confirm('Сбросить все индивидуальные права к значениям по роли?')">Сбросить к роли</button>
      <a class="btn btn-ghost" href="/admin/approvals.php">Назад</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
