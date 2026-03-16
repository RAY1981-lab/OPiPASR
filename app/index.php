<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/helpers.php';

require_permission('cabinet', 'view');

$u = current_user();
$uid = (int)($u['id'] ?? 0);
$role = strtoupper((string)($u['role'] ?? 'GUEST'));
$status = strtoupper((string)($u['status'] ?? ''));

// Optional DB info
$last_login = null;
try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT last_login_at FROM users WHERE id=:id LIMIT 1");
  $stmt->execute([':id' => $uid]);
  $last_login = $stmt->fetchColumn();
} catch (Throwable $e) {
  $last_login = null;
}

$page_title = 'Кабинет — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';
?>

<h1 style="margin:0 0 12px;">Кабинет</h1>

<div class="features">
  <div class="feature">
    <div class="feature-title">Профиль</div>
    <div class="kv-grid" style="margin-top:12px">
      <div class="kv">
        <div class="kv-k">Пользователь</div>
        <div class="kv-v"><?= h((string)($u['username'] ?? '—')) ?></div>
        <div class="kv-hint">ID: <?= (int)$uid ?></div>
      </div>
      <div class="kv">
        <div class="kv-k">Роль</div>
        <div class="kv-v"><?= role_label((string)$role) ?></div>
        <div class="kv-hint">Права можно уточнить у администратора</div>
      </div>
      <div class="kv">
        <div class="kv-k">Статус</div>
        <div class="kv-v"><?= h($status !== '' ? $status : '—') ?></div>
        <div class="kv-hint">Последний вход: <?= h((string)($last_login ?: '—')) ?></div>
      </div>
    </div>
  </div>

  <div class="feature">
    <div class="feature-title">Быстрые действия (черновик)</div>
    <ul class="feature-list">
      <?php if (can_permission('telemetry', 'view')): ?>
        <li><a class="calc-link" href="/app/telemetry.php"><strong>Телеметрия ИБАС</strong> — связь, датчики, GNSS, погода, видео/тепловизор.</a></li>
      <?php endif; ?>
      <?php if (can_permission('incidents', 'view')): ?>
        <li><a class="calc-link" href="/app/incidents.php"><strong>Инциденты</strong> — карточки пожара/ЧС, задачи, журнал решений.</a></li>
      <?php endif; ?>
      <?php if (can_permission('reports', 'view')): ?>
        <li><a class="calc-link" href="/app/reports.php"><strong>Отчёты</strong> — оперативные сводки, экспорт, аудит.</a></li>
      <?php endif; ?>
      <?php if (can_permission('calculators', 'view')): ?>
        <li><a class="calc-link" href="/methods/#calculators"><strong>Калькуляторы</strong> — быстрый доступ к расчётам и методикам.</a></li>
      <?php endif; ?>
      <?php if ($role === 'GUEST'): ?>
        <li><strong>Гость:</strong> в кабинете можно добавить «заявку на повышение роли» (РТП/ЦУКС) и центр уведомлений — скажите, какие поля нужны.</li>
      <?php endif; ?>
      <?php if ($role === 'ADMIN'): ?>
        <li><a class="calc-link" href="/admin/approvals.php"><strong>Администрирование</strong> — заявки на регистрацию, пользователи, права.</a></li>
      <?php endif; ?>
    </ul>
  </div>
  <div class="feature">
    <div class="feature-title">Что добавим под роли (предложение)</div>
    <ul class="feature-list">
      <li><strong>Гость:</strong> профиль, уведомления, доступы, запрос роли, доступ к нормативной базе/методикам.</li>
      <li><strong>РТП/РЛЧС:</strong> оперативная панель ИБАС, калькуляторы «по сценарию», журнал решений, шаблоны задач разведки, экспорт сводки.</li>
      <li><strong>ЦУКС:</strong> обзор нескольких инцидентов/ИБАС, очередь сообщений, приоритизация участков, контроль связи и доставки данных, отчёты для ЕДДС.</li>
      <li><strong>Админ:</strong> заявки/пользователи/права, аудит действий, мониторинг ingest, статусы БД/каналов.</li>
    </ul>
  </div>

  <div class="feature feature-wide lk-camera-card">
    <div class="feature-title">Камера</div>
    <div class="lk-camera-status-row">Статус: <strong id="lkCamStatus">init</strong></div>
    <video id="lkCamVideo" autoplay playsinline controls muted></video>
    <div class="lk-camera-actions">
      <button type="button" class="btn btn-primary" id="lkCamConnect">Подключить</button>
      <button type="button" class="btn btn-ghost" id="lkCamDisconnect">Отключить</button>
      <button type="button" class="btn btn-ghost" id="lkCamReconnect">Переподключить</button>
    </div>
    <div class="kv-hint" id="lkCamHint">Если кабинет открыт по HTTPS, нужен WSS прокси. Сейчас используйте LAN или настройте /janusws.</div>
  </div>
</div>

<script src="/js/webrtc/adapter.js"></script>
<script src="/js/webrtc/janus.js"></script>
<script src="/js/webrtc/lk-camera.js"></script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
