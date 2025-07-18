<?php
include 'assets/components/info.php';
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