<?php
require_once __DIR__ . '/config.php';

define('MYSQL_HOST', 'sql308.hstn.me');
define('MYSQL_DB',   'mseet_41930214_chargepoint');
define('MYSQL_USER', 'mseet_41930214');
define('MYSQL_PASS', '1214161820Ben');
define('MYSQL_PORT', 3306);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            MYSQL_HOST, MYSQL_PORT, MYSQL_DB
        );
        $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    }
    return $pdo;
}

function getSetting(string $key, string $default = ''): string {
    $stmt = getDB()->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}
