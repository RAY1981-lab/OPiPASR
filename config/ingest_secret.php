<?php
declare(strict_types=1);

/**
 * Секреты и параметры БД.
 * Этот файл НЕ должен быть доступен из web (он в /config/).
 */

define('OPIPASR_INGEST_KEY', 'GgbliWcdCWpqDCQZ7nt6a1N3RucP+RjYQY4PHfrDBg+V9bxgREKQH71YdcfpMCdhcKi70q/ofwf0ZPPv9Qg+RA=='); // rotated: 2026-03-01

// DB параметры (как ждёт config/db.php)
define('DB_HOST', 'localhost');
define('DB_NAME', 'u3052693_default');   // это имя базы на скрине
define('DB_CHARSET', 'utf8mb4');

// ВАЖНО: DB_USER — это ИМЯ ПОЛЬЗОВАТЕЛЯ MySQL (из ISPmanager -> "Пользователи"), НЕ имя базы
define('DB_USER', 'u3052693_default');      // <-- подставьте реального пользователя
define('DB_PASS', '28RwFk3TptuOFpo5');       // <-- подставьте пароль, который задали
