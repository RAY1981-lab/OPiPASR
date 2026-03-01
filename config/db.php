<?php
declare(strict_types=1);

$pdo = null;

function db(): PDO {
    global $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = defined('DB_HOST') ? (string)DB_HOST : 'localhost';
    $dbname = defined('DB_NAME') ? (string)DB_NAME : 'u3052693_default';
    $charset = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';
    $user = defined('DB_USER') ? (string)DB_USER : 'u3052693_default';
    $password = defined('DB_PASS') ? (string)DB_PASS : '28RwFk3TptuOFpo5';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log('DB connect error: ' . $e->getMessage());
        http_response_code(500);
        exit('DB connection failed');
    }
}

// Legacy compatibility: many files expect $pdo to exist.
db();
?>
