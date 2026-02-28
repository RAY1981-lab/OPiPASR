Установка:
1) Выполните /sql/schema.sql в phpMyAdmin (в штатной базе).
2) В /config/config.php заполните DB_PASS (DB_NAME/DB_USER уже проставлены по вашему скрину).
3) Откройте /admin/bootstrap_admin.php, создайте ADMIN, затем удалите этот файл.
4) Вход админа: /admin/login.php -> /admin/approvals.php
5) Пользователь: /register/ (PENDING) -> одобрение -> /login/ (ACTIVE)

Важно: DB_PASS не присылайте в чат — вставьте локально в config.php.
