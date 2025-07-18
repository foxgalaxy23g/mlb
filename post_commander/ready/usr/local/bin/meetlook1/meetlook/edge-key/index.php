<?php
// index.php - Единый файл для списка пользователей и аутентификации

session_start(); // Начинаем сессию для управления состоянием

include "assets/components/db.php";

// --- ОБРАБОТКА AJAX-ЗАПРОСОВ (ПРОВЕРКА ПАРОЛЯ) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => 'Неизвестная ошибка.'];

    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Ищем пользователя в базе данных, теперь запрашиваем и аватарку
        $stmt = $pdo->prepare("SELECT id, username, password, avatar FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            // Проверяем, пустой ли пароль у пользователя в базе данных
            if (empty($user['password'])) {
                // Если пароль пустой, авторизуем без ввода пароля
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['avatar'] = $user['avatar']; // Сохраняем путь к аватарке
                $response['success'] = true;
                $response['redirect'] = 'dash.php';
            } elseif (password_verify($password, $user['password'])) {
                // Если пароль не пустой, проверяем его
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['avatar'] = $user['avatar']; // Сохраняем путь к аватарке
                $response['success'] = true;
                $response['redirect'] = 'dash.php';
            } else {
                $response['message'] = "Неверный пароль.";
            }
        } else {
            $response['message'] = "Пользователь не найден.";
        }
    } else {
        $response['message'] = "Отсутствуют имя пользователя или пароль.";
    }

    echo json_encode($response);
    exit();
}

// --- ПРОВЕРКА АВТОРИЗАЦИИ ДЛЯ ОТОБРАЖЕНИЯ КОНТЕНТА ---
$is_logged_in = isset($_SESSION['user_id']);
$current_username = $is_logged_in ? htmlspecialchars($_SESSION['username']) : '';
$current_avatar = $is_logged_in && !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'default_avatar.png'; // Указываем дефолтную аватарку

// --- ПОЛУЧЕНИЕ СПИСКА ПОЛЬЗОВАТЕЛЕЙ ДЛЯ ОТОБРАЖЕНИЯ (если пользователь не залогинен) ---
$users = [];
if (!$is_logged_in) {
    try {
        // Теперь получаем username, password (для проверки, пустой ли он), и avatar
        $stmt = $pdo->query("SELECT id, username, password, avatar FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll();
    } catch (\PDOException $e) {
        die("Ошибка при получении списка пользователей: " . $e->getMessage());
    }
}

// --- HTML-СТРУКТУРА С ФОРМОЙ И СПИСКОМ ПОЛЬЗОВАТЕЛЕЙ ---
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_logged_in ? 'Личный кабинет' : 'Список пользователей и вход'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body class="bg-blue-50">
    <div class="container">
        <?php if ($is_logged_in): ?>
            <img src="<?php echo $current_avatar; ?>" alt="Аватар пользователя" class="profile-avatar">
            <h1 class="text-3xl font-bold mb-6 text-green-700">Добро пожаловать, <?php echo $current_username; ?>!</h1>
            <p class="text-lg text-gray-700 mb-8">Вы успешно авторизованы.</p>
            <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-md transition duration-300 ease-in-out transform hover:scale-105">
                Выйти
            </a>
        <?php else: ?>

            <div id="userList" class="mb-8">
                <?php if (empty($users)): ?>
                    <p class="text-gray-600">Пользователи не найдены. Добавьте их в базу данных.</p>
                    <p class="text-sm text-gray-500 mt-2">
                        Пример добавления пользователя (пароль 'password123', аватар 'user_avatar.png'):<br>
                        `INSERT INTO users (username, password, avatar) VALUES ('testuser', '<?php echo password_hash('password123', PASSWORD_DEFAULT); ?>', 'user_avatar.png');`<br><br>
                        Для пользователя без пароля (логин без ввода):<br>
                        `INSERT INTO users (username, password, avatar) VALUES ('guest', '', 'guest_avatar.png');`
                    </p>
                <?php else: ?>
                    <?php foreach ($users as $user):
                        // Определяем, нужен ли пароль для этого пользователя
                        $needs_password = !empty($user['password']);
                        $display_avatar = !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'default_avatar.png'; // Путь к аватарке
                    ?>
                        <div class="user-list-item"
                             data-username="<?php echo htmlspecialchars($user['username']); ?>"
                             data-needs-password="<?php echo $needs_password ? 'true' : 'false'; ?>">
                            <img src="<?php echo $display_avatar; ?>" alt="Аватар" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                            <?php if (!$needs_password): ?>
                                <span class="ml-auto text-sm text-gray-500">(без пароля)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="passwordModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" id="closeModalBtn">&times;</span>
                    <h3 class="text-2xl font-semibold mb-4 text-gray-700">Введите пароль для <span id="modalUsername" class="text-blue-600"></span></h3>
                    <input type="password" id="passwordInput" placeholder="Пароль" class="w-full p-3 mb-4 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <button id="submitPasswordBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md transition duration-300 ease-in-out transform hover:scale-105">
                        Войти
                    </button>
                    <div id="messageBox" class="message-box mt-4 hidden"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if (!$is_logged_in): ?>
        const userList = document.getElementById('userList');
        const passwordModal = document.getElementById('passwordModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const modalUsernameSpan = document.getElementById('modalUsername');
        const passwordInput = document.getElementById('passwordInput');
        const submitPasswordBtn = document.getElementById('submitPasswordBtn');
        const messageBox = document.getElementById('messageBox');

        let currentUsername = '';
        let currentNeedsPassword = false; // Добавляем переменную для отслеживания, нужен ли пароль

        userList.addEventListener('click', (event) => {
            const target = event.target.closest('.user-list-item'); // Ищем ближайший родитель с классом user-list-item
            if (target) {
                currentUsername = target.dataset.username;
                currentNeedsPassword = target.dataset.needsPassword === 'true'; // Получаем значение из data-атрибута

                if (!currentNeedsPassword) {
                    // Если пароль не нужен, сразу отправляем AJAX-запрос на авторизацию
                    // (отправляем пустой пароль, PHP-скрипт поймёт)
                    authenticateUser(currentUsername, '');
                } else {
                    // Если пароль нужен, показываем модальное окно
                    modalUsernameSpan.textContent = currentUsername;
                    passwordInput.value = '';
                    messageBox.classList.add('hidden');
                    passwordModal.style.display = 'flex';
                    passwordInput.focus();
                }
            }
        });

        closeModalBtn.addEventListener('click', () => {
            passwordModal.style.display = 'none';
        });

        submitPasswordBtn.addEventListener('click', () => {
            const password = passwordInput.value;
            authenticateUser(currentUsername, password);
        });

        // Новая функция для отправки AJAX-запроса на аутентификацию
        async function authenticateUser(username, password) {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect; // Перенаправляем
                    } else {
                        displayMessage('Успешный вход! Перенаправление...', 'success');
                        setTimeout(() => {
                            window.location.href = 'dash.php';
                        }, 500);
                    }
                } else {
                    displayMessage(data.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка при отправке запроса:', error);
                displayMessage('Произошла ошибка при проверке данных.', 'error');
            }
        }

        function displayMessage(message, type) {
            messageBox.textContent = message;
            messageBox.className = `message-box mt-4 ${type}`;
            messageBox.classList.remove('hidden');
        }

        window.addEventListener('click', (event) => {
            if (event.target === passwordModal) {
                passwordModal.style.display = 'none';
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && passwordModal.style.display === 'flex') {
                passwordModal.style.display = 'none';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>