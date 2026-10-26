<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Настройки для локального сервера (OpenServer / XAMPP)
// При переносе на Beget замените на данные из панели управления хостингом
$host = '127.0.0.1';
$db   = 'slippers_shop';
$user = 'root';
$pass = ''; // На OpenServer и XAMPP по умолчанию пустой
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Выброс исключений при ошибках
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Возврат ассоциативных массивов
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Истинные подготовленные выражения
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . htmlspecialchars($e->getMessage()));
}
?>