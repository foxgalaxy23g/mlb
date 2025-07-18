<?php
// dashboard.php - Страница для авторизованных пользователей

session_start(); // Обязательно начинаем сессию

include 'db.php';

// --- ПРОВЕРКА АВТОРИЗАЦИИ ---
// Если user_id нет в сессии, значит пользователь не авторизован, перенаправляем на index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Перенаправляем на твою текущую страницу входа (index.php)
    exit(); // Важно завершить выполнение скрипта
}

// --- ЗАПРОС ДАННЫХ ПОЛЬЗОВАТЕЛЯ ИЗ БАЗЫ ДАННЫХ НАПРЯМУЮ ---
$current_user_id = $_SESSION['user_id'];
$current_username = ''; // Инициализируем, так как будем брать из БД
$current_avatar = 'assets/icons/user.png'; // Дефолтная аватарка на всякий случай
$user_wallpaper = 'https://wallpaperaccess.com/full/317501.jpg'; // Дефолтные обои на всякий случай

try {
    $stmt = $pdo->prepare("SELECT username, avatar, wallpaper FROM users WHERE id = :id");
    $stmt->execute(['id' => $current_user_id]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        $current_username = htmlspecialchars($user_data['username']);
        // Если avatar из БД не пустой, используем его, иначе дефолтный
        $current_avatar = !empty($user_data['avatar']) ? htmlspecialchars($user_data['avatar']) : 'assets/icons/user.png';
        // Если wallpaper из БД не пустой, используем его, иначе дефолтный
        $user_wallpaper = !empty($user_data['wallpaper']) ? htmlspecialchars($user_data['wallpaper']) : 'https://wallpaperaccess.com/full/317501.jpg';
    } else {
        // Если по какой-то причине данные пользователя не найдены по ID, сбрасываем сессию и перенаправляем
        session_destroy();
        header("Location: index.php");
        exit();
    }
} catch (\PDOException $e) {
    die("Ошибка при получении данных пользователя: " . $e->getMessage());
}

?>