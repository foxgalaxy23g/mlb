<?php
// register.php - Веб-приложение для создания аккаунтов

session_start(); // Начинаем сессию для сообщений

// --- КОНФИГУРАЦИЯ БАЗЫ ДАННЫХ ---
// ВАЖНО: Замени эти данные на свои!
$host = 'localhost';
$db   = 'edge'; // Например, 'mydatabase'
$user = 'root';     // Например, 'root'
$pass = '';          // Например, '' (пусто для root без пароля)
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
    // В реальном приложении здесь лучше не показывать ошибку напрямую,
    // а логировать ее и показывать пользователю дружелюбное сообщение.
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// --- ОБРАБОТКА ФОРМЫ РЕГИСТРАЦИИ ---
$message = ''; // Переменная для сообщений пользователю (успех/ошибка)
$message_type = ''; // Тип сообщения (success/error)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? ''); // Получаем и очищаем имя пользователя
    $password = $_POST['password'] ?? '';       // Получаем пароль
    $confirm_password = $_POST['confirm_password'] ?? ''; // Получаем подтверждение пароля

    // Простые проверки на стороне сервера
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $message = "Все поля должны быть заполнены.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Пароли не совпадают.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Пароль должен быть не менее 6 символов.";
        $message_type = "error";
    } else {
        // Проверяем, существует ли пользователь с таким именем
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $message = "Пользователь с таким именем уже существует.";
            $message_type = "error";
        } else {
            // Хешируем пароль перед сохранением!
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Вставляем нового пользователя в базу данных
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
                $stmt->execute([
                    'username' => $username,
                    'password' => $hashed_password
                ]);
                $message = "Аккаунт успешно создан! Теперь вы можете <a href='login.php' class='text-blue-700 hover:underline'>войти</a>.";
                $message_type = "success";
                // Очищаем поля формы после успешной регистрации
                $username = '';
                $password = '';
                $confirm_password = '';

            } catch (\PDOException $e) {
                $message = "Ошибка при создании аккаунта: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание нового аккаунта</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8; /* Мягкий серый фон */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .message-box {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
            text-align: left;
        }
        .message-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container">
        <h1 class="text-4xl font-extrabold mb-8 text-gray-800">Создать аккаунт</h1>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">
            <div>
                <label for="username" class="sr-only">Имя пользователя</label>
                <input type="text" id="username" name="username" placeholder="Имя пользователя"
                       value="<?php echo htmlspecialchars($username ?? ''); ?>"
                       class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label for="password" class="sr-only">Пароль</label>
                <input type="password" id="password" name="password" placeholder="Пароль"
                       class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label for="confirm_password" class="sr-only">Подтвердите пароль</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Подтвердите пароль"
                       class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-lg">
                Зарегистрировать
            </button>
        </form>
    </div>
</body>
</html>