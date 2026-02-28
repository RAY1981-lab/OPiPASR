<?php
declare(strict_types=1);

/**
 * Секреты и параметры БД.
 * Этот файл НЕ должен быть доступен из web (он в /config/).
 */

define('OPIPASR_INGEST_KEY', 'HpQpk3A6bbeUYRrOawC04FlE5eXNq+PsEtPmg9L/1ywWZsROE64bjU/JXq1vsd7v'); // уже сгенерированный

// DB параметры (как ждёт config/db.php)
define('DB_HOST', 'localhost');
define('DB_NAME', 'u3052693_default');   // это имя базы на скрине
define('DB_CHARSET', 'utf8mb4');

// ВАЖНО: DB_USER — это ИМЯ ПОЛЬЗОВАТЕЛЯ MySQL (из ISPmanager -> "Пользователи"), НЕ имя базы
define('DB_USER', 'u3052693_default');      // <-- подставьте реального пользователя
define('DB_PASS', '28RwFk3TptuOFpo5');       // <-- подставьте пароль, который задали
