<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$page_title = 'Контакты — ОПиПАСР';
require_once __DIR__ . '/../config/header.php';
?>

<div class="panel">
  <div class="h1">Контакты</div>

  <p class="p">
    Если нужна поддержка по доступу/работе кабинета или замечены ошибки в материалах (НТД, литература, методики),
    используйте контактные каналы ниже.
  </p>

  <h2 style="margin:18px 0 8px;font-size:18px">Обратная связь</h2>

  <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
    <div style="padding:14px;border:1px solid rgba(255,255,255,.08);border-radius:16px">
      <div style="font-weight:700;margin-bottom:6px">Техническая поддержка</div>
      <div class="help" style="opacity:.9">
        По вопросам регистрации, входа, ролей, телеметрии, ошибок страниц.
      </div>
      <div style="margin-top:10px">
        <span style="opacity:.75">Email:</span> <strong>support@opipasr.ru</strong><br>
        <span style="opacity:.75">Время ответа:</span> <strong>24–72 ч</strong>
      </div>
    </div>

    <div style="padding:14px;border:1px solid rgba(255,255,255,.08);border-radius:16px">
      <div style="font-weight:700;margin-bottom:6px">Контент/материалы</div>
      <div class="help" style="opacity:.9">
        Замечания по нормативной базе, литературе, методикам, корректности ссылок/цитирования.
      </div>
      <div style="margin-top:10px">
        <span style="opacity:.75">Email:</span> <strong>content@opipasr.ru</strong>
      </div>
    </div>
  </div>

  <h2 style="margin:18px 0 8px;font-size:18px">Что прикладывать к сообщению</h2>
  <ul class="list">
    <li>адрес страницы (URL);</li>
    <li>время (по местному времени) и что именно делали;</li>
    <li>скриншот/текст ошибки;</li>
    <li><strong>request_id</strong> из сообщения об ошибке (если показывается).</li>
  </ul>

  <div class="alert" style="margin-top:14px">
    <strong>Примечание:</strong> адреса email можно заменить на ваши реальные контакты.
  </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
