<?php
$host = 'localhost'; // Из users.sql
$db   = 'edge'; // Из users.sql
$user = 'root';     // Ваш пользователь БД (например, 'root')
$pass = '';          // Ваш пароль БД (например, '' для root без пароля)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>