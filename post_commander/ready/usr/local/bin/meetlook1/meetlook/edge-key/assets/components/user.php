<?php
// dashboard.php - Страница для авторизованных пользователей

session_start(); // Обязательно начинаем сессию

// --- КОНФИГУРАЦИЯ БАЗЫ ДАННЫХ ---
// ВАЖНО: Замените эти данные на свои!
$host = '127.0.0.1'; // Из users.sql
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

// --- ПРОВЕРКА АВТОРИЗАЦИИ ---
// Если user_id нет в сессии, значит пользователь не авторизован, перенаправляем на index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Перенаправляем на твою текущую страницу входа (index.php)
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

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: url('<?php echo $user_wallpaper; ?>') center/cover no-repeat; /* Обои пользователя */
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
    </style>
</head>
<body class="bg-blue-50">
    <div class="container">
        <img src="<?php echo $current_avatar; ?>" alt="Аватар пользователя" class="profile-avatar">
        <h1 class="text-3xl font-bold mb-4 text-green-700">Привет, <?php echo $current_username; ?>!</h1>
        <p class="text-lg text-gray-700 mb-8">Это ваш личный кабинет. Ваши обои загружены.</p>
        <a href="logout.php" class="logout-button">
            Выйти
        </a>
        <img src="<?php echo $user_wallpaper; ?>" alt="">
    </div>
</body>
</html>