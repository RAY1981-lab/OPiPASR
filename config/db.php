<?php
declare(strict_types=1);

// Параметры подключения
$host = 'localhost';
$dbname = 'u3052693_default';
$user = 'u3052693_default';
$password = '28RwFk3TptuOFpo5';

try {
    // Инициализация PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Логируем ошибку в файл error_log.txt
    error_log('Ошибка подключения: ' . $e->getMessage(), 3, __DIR__ . '/www/opipasr.ru/config/error_log.txt');  // Путь к файлу логов

    // Выводим ошибку на экран для быстрого анализа
    echo 'Подключение не удалось: ' . $e->getMessage(); 

    // Завершаем выполнение скрипта
    exit;
}
?>
