<?php
// ОЧЕНЬ ВАЖНО: Прочитайте предупреждение ниже, прежде чем использовать этот код!
// Данный код позволяет выполнять команды на сервере. Убедитесь, что вы понимаете риски безопасности.

// Включить отображение ошибок для отладки (в продакшене лучше выключить)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Настройки подключения к базе данных MySQL
$db_host = 'localhost';      // Хост вашей БД (обычно 'localhost')
$db_user = 'root';           // Имя пользователя БД
$db_pass = '';               // Пароль пользователя БД. МИХАИЛ, ЭТО ОЧЕНЬ ОПАСНО! УСТАНОВИТЕ ПАРОЛЬ!
$db_name = 'edge';           // Название вашей БД

$applications_data = [];
$pinned_applications_data = []; // Массив для закрепленных приложений
$db_connection_error = null;

// Путь к Unix-сокету ретранслятора C. Убедитесь, что ретранслятор запущен и доступен по этому пути.
$socket_path = '/tmp/command_executor.sock';

// Функция для отправки команды C-ретранслятору через Unix-сокет
// Эта функция позволяет запускать программы и получать информацию о окнах.
function send_command_to_relay($command, $sync = false, $env_vars = []) {
    global $socket_path;

    // Создаем Unix-сокет
    $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
    if ($socket === false) {
        error_log('Не удалось создать сокет: ' . socket_strerror(socket_last_error()));
        return ['status' => 'error', 'message' => 'Не удалось создать сокет: ' . socket_strerror(socket_last_error())];
    }

    // Устанавливаем таймауты для приема и отправки данных
    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 10, 'usec' => 0));
    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 10, 'usec' => 0));

    // Подключаемся к ретранслятору. Используем @ для подавления предупреждений, ошибку обрабатываем ниже.
    $result = @socket_connect($socket, $socket_path);
    if ($result === false) {
        $error_message = 'Не удалось подключиться к ретранслятору: ' . socket_strerror(socket_last_error($socket)) . '. Убедитесь, что C-ретранслятор запущен.';
        error_log($error_message);
        return ['status' => 'error', 'message' => $error_message];
    }

    // Готовим данные для отправки в формате JSON
    $request_payload = [
        'command' => $command,
        'sync' => $sync,
        'env' => $env_vars
    ];

    $json_request = json_encode($request_payload);
    
    // Отправляем JSON-запрос через сокет
    socket_write($socket, $json_request, strlen($json_request));

    $response_data = '';
    // Читаем ответ от ретранслятора до конца или до таймаута
    while (($buf = socket_read($socket, 4096, PHP_BINARY_READ)) !== false) {
        $response_data .= $buf;
        // Простая эвристика для завершения JSON: ищем последнюю '}'.
        // Может быть ненадежной для сложных JSON, но для wm-controller должно работать.
        if (strrpos($response_data, '}') !== false) {
            break;
        }
    }

    // Закрываем сокет
    socket_close($socket);
    // Декодируем JSON-ответ и возвращаем массив
    return json_decode($response_data, true);
}

// --- ОБРАБОТКА AJAX-ЗАПРОСОВ ---
// Эта часть кода обрабатывает запросы от JavaScript
if (isset($_POST['action'])) {
    header('Content-Type: application/json'); // Указываем, что ответ будет в формате JSON

    // Переменные окружения для запуска GUI-приложений (например, DISPLAY для X-сервера)
    // DISPLAY:0 обычно означает основной экран. XAUTHORITY может понадобиться для прав доступа.
    $env_vars = [
        'DISPLAY' => ':0', // Пример: основной дисплей
        // 'XAUTHORITY' => '/home/ваш_пользователь/.Xauthority' // Раскомментируйте, если нужно
    ];

    switch ($_POST['action']) {
        case 'execute_command_via_relay':
            // МИХАИЛ, ЭТО КРИТИЧЕСКИЙ МОМЕНТ БЕЗОПАСНОСТИ!
            // Команда берется напрямую из POST-запроса. Это ОЧЕНЬ ОПАСНО!
            // Злоумышленник может отправить любую команду (например, 'rm -rf /').
            // НИКОГДА НЕ ДЕЛАЙТЕ ТАК В ПРОДАКШЕНЕ БЕЗ СТРОГОЙ ПРОВЕРКИ И БЕЛОГО СПИСКА!
            $command_to_execute = $_POST['command'] ?? ''; // Получаем команду от клиента
            
            // Здесь должна быть СТРОГАЯ ВАЛИДАЦИЯ и БЕЛЫЙ СПИСОК РАЗРЕШЕННЫХ КОМАНД
            // Пример (НЕ БЕЗОПАСНО, но показывает идею):
            // if (!in_array($command_to_execute, ['firefox', 'xterm', '/usr/local/bin/edge/wm'])) {
            //     echo json_encode(['status' => 'error', 'message' => 'Неразрешенная команда.']);
            //     exit();
            // }

            $response = send_command_to_relay($command_to_execute, false, $env_vars); // Запускаем асинхронно
            echo json_encode($response);
            break;

        case 'list_windows':
            // Запрашиваем список окон у wm-controller, который теперь выводит JSON
            $wm_command = '/usr/local/bin/edge/wm-controller list'; // Команда для получения списка окон
            $response = send_command_to_relay($wm_command, true, $env_vars); // Запрашиваем синхронно

            if ($response && $response['status'] === 'success') {
                // Вывод wm-controller list теперь должен быть JSON-строкой
                $windows_json_str = trim($response['stdout']);
                $windows_data = json_decode($windows_json_str, true);

                // Проверяем, удалось ли распарсить JSON
                if (json_last_error() === JSON_ERROR_NONE && is_array($windows_data)) {
                    echo json_encode(['status' => 'success', 'windows' => $windows_data]);
                } else {
                    $error_msg = 'Не удалось распарсить JSON от wm-controller: ' . json_last_error_msg();
                    error_log($error_msg . ' Raw output: ' . $windows_json_str);
                    echo json_encode(['status' => 'error', 'message' => $error_msg, 'raw_output' => $windows_json_str]);
                }
            } else {
                $error_msg = 'Не удалось получить список окон от ретранслятора: ' . ($response['stderr'] ?? 'Неизвестная ошибка');
                error_log($error_msg);
                echo json_encode(['status' => 'error', 'message' => $error_msg]);
            }
            break;

        case 'control_window':
            // Управление окнами (фокус, свернуть, развернуть, закрыть)
            $window_id = $_POST['window_id'] ?? '';
            $action_type = $_POST['action_type'] ?? ''; // focus, minimize, maximize, close

            if (empty($window_id) || empty($action_type)) {
                echo json_encode(['status' => 'error', 'message' => 'Не указан ID окна или тип действия.']);
                break;
            }

            // МИХАИЛ, ЗДЕСЬ ТАКЖЕ ДОЛЖНА БЫТЬ ВАЛИДАЦИЯ $action_type и $window_id
            // Например, проверка, что $action_type - это одно из разрешенных значений.
            $wm_command = "/usr/local/bin/edge/wm-controller {$action_type} {$window_id}";
            $response = send_command_to_relay($wm_command, false, $env_vars); // Отправляем асинхронно

            if ($response && $response['status'] === 'success') {
                echo json_encode(['status' => 'success', 'message' => $response['message'] ?? "Команда {$action_type} для окна {$window_id} отправлена."]);
            } else {
                $error_msg = 'Ошибка управления окном: ' . ($response['stderr'] ?? 'Неизвестная ошибка');
                error_log($error_msg);
                echo json_encode(['status' => 'error', 'message' => $error_msg]);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Неизвестное действие.']);
            break;
    }
    exit(); // Завершаем выполнение PHP после обработки AJAX-запроса
}

// --- ЗАГРУЗКА ДАННЫХ ИЗ БД ДЛЯ ПЕРВОНАЧАЛЬНОЙ ЗАГРУЗКИ СТРАНИЦЫ ---
// Эта часть кода выполняется при первом открытии страницы для получения данных из БД
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    $db_connection_error = "Ошибка подключения: " . $conn->connect_error;
} else {
    $conn->set_charset("utf8");

    // Запрос для всех приложений (для меню "Пуск")
    // МИХАИЛ, В ЭТОМ МЕСТЕ ЛУЧШЕ ИСПОЛЬЗОВАТЬ ПОДГОТОВЛЕННЫЕ ВЫРАЖЕНИЯ,
    // ЕСЛИ В БУДУЩЕМ БУДЕТ ФИЛЬТРАЦИЯ ПОЛЬЗОВАТЕЛЯ.
    $sql_all_apps = "SELECT name, path AS command, icon FROM applications ORDER BY name ASC";
    $result_all_apps = $conn->query($sql_all_apps);
    if ($result_all_apps) {
        while ($row = $result_all_apps->fetch_assoc()) {
            $applications_data[] = $row;
        }
        $result_all_apps->free();
    } else {
        $db_connection_error = "Ошибка выполнения запроса для всех приложений: " . $conn->error;
        error_log("Ошибка выполнения запроса для всех приложений: " . $conn->error);
    }

    // Запрос для закрепленных приложений (для панели задач)
    // МИХАИЛ, В ЭТОМ МЕСТЕ ЛУЧШЕ ИСПОЛЬЗОВАТЬ ПОДГОТОВЛЕННЫЕ ВЫРАЖЕНИЯ.
    $sql_pinned_apps = "
        SELECT
            a.name,
            a.path AS command,
            a.icon
        FROM
            pinned_apps pa
        LEFT JOIN
            applications a ON pa.app_id = a.id
        ORDER BY
            a.name ASC
    ";
    $result_pinned_apps = $conn->query($sql_pinned_apps);
    if ($result_pinned_apps) {
        while ($row = $result_pinned_apps->fetch_assoc()) {
            if ($row['name'] !== null) { // Проверяем, что приложение существует (LEFT JOIN)
                $pinned_applications_data[] = $row;
            }
        }
        $result_pinned_apps->free();
    } else {
        error_log("Ошибка выполнения запроса для закрепленных приложений: " . $conn->error);
        if ($db_connection_error === null) {
            $db_connection_error = "Ошибка загрузки закрепленных приложений: " . $conn->error;
        } else {
            $db_connection_error .= "; Ошибка загрузки закрепленных приложений: " . $conn->error;
        }
    }
    $conn->close();
}

// Конвертируем данные приложений и закрепленных приложений в JSON для JavaScript
$apps_json = json_encode($applications_data);
$pinned_apps_json = json_encode($pinned_applications_data);
$db_error_json = json_encode($db_connection_error);
?>

<?php
    // Включаем дополнительные компоненты PHP, если они нужны (например, для обоев или кликов)
    include 'assets/components/info.php';
    include 'assets/components/left-click.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Edge Dash</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: url('<?php echo $user_wallpaper; ?>') center/cover no-repeat;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Чтобы не было скролла от окон */
        }
    </style>
</head>
<body>
    <div class="watermark">
        <a href="logout.php"><img src="icons/exit.png" alt="Выход" class="deskico"></a>
        <p>Пароль от MySQL Панели : root</p>
        <p>Данная ОС в Альфа разработке</p>
        <p>By FoxGalaxy23 2025</p>
    </div>
<div class="taskbar">
     <button class="start-button" onclick="toggleStartMenu()">☰</button>
    <div class="taskbar-apps" id="taskbarApps"></div>
    <div id="taskbarClock"></div>
</div>

<div class="start-menu" id="startMenu">
</div>

<script>
// Данные о приложениях и ошибка подключения из PHP
const applicationsFromDB = <?php echo $apps_json; ?>;
const dbError = <?php echo $db_error_json; ?>;
const pinnedApplicationsFromDB = <?php echo $pinned_apps_json; ?>;

// Объект для отслеживания реальных запущенных окон
// Ключ: windowId (из wm-controller), Значение: { name: string, state: number, command: string, iconSrc: string, taskbarIconElement: HTMLElement }
let liveWindows = {};

// Объект для отслеживания иконок на панели задач, сгруппированных по команде
// Ключ: command (из БД), Значение: { icon: HTMLElement, windowIds: Set<string> }
let taskbarIconsByCommand = {};

// Константы состояний окна из TinyWM-Plus API
const WM_STATE_MINIMIZED = 1; // Бит 0 (1 << 0)
const WM_STATE_MAXIMIZED = 2; // Бит 1 (1 << 1)
const WM_STATE_FULLSCREEN = 4; // Бит 2 (1 << 2)

/**
 * Создает или обновляет иконку на панели задач.
 * @param {string} title - Название приложения.
 * @param {string} command - Команда, связанная с приложением.
 * @param {string} iconSrc - URL источника изображения иконки.
 * @param {boolean} [isPinned=false] - True, если иконка представляет закрепленное приложение.
 * @returns {HTMLElement} Созданный или найденный элемент иконки панели задач.
 */
function getOrCreateTaskbarIcon(title, command, iconSrc, isPinned = false) {
    let appIcon = taskbarIconsByCommand[command] ? taskbarIconsByCommand[command].icon : null;

    if (!appIcon) {
        const taskbarApps = document.getElementById('taskbarApps');
        appIcon = document.createElement('div');
        appIcon.className = 'taskbar-icon';
        appIcon.title = title;
        appIcon.dataset.command = command;

        const iconImg = document.createElement('img');
        iconImg.src = iconSrc;
        iconImg.alt = title;
        iconImg.onerror = () => {
            const textFallback = document.createElement('span');
            textFallback.innerText = title.substring(0,1).toUpperCase();
            textFallback.style.color = 'white';
            textFallback.style.fontSize = '16px';
            appIcon.insertBefore(textFallback, iconImg);
            iconImg.remove();
        };
        appIcon.appendChild(iconImg);

        const dot = document.createElement('div');
        dot.className = 'indicator-dot';
        dot.style.display = 'none'; // По умолчанию скрыто
        appIcon.appendChild(dot);

        taskbarApps.appendChild(appIcon);

        // Инициализируем запись для этой команды
        taskbarIconsByCommand[command] = { icon: appIcon, windowIds: new Set() };

        // Обработчик клика для иконки панели задач
        appIcon.onclick = async () => {
            const currentWindowIds = Array.from(taskbarIconsByCommand[command].windowIds);
            if (currentWindowIds.length === 0) {
                // Если нет открытых окон для этой команды (может быть для закрепленных)
                launchNativeApp(title, command, iconSrc);
                return;
            }

            // Ищем активное (не свернутое) окно
            let activeWindowId = null;
            for (const winId of currentWindowIds) {
                if (liveWindows[winId] && (liveWindows[winId].state & WM_STATE_MINIMIZED) === 0) { // Проверяем, что не свернуто
                    activeWindowId = winId;
                    break;
                }
            }

            if (activeWindowId) {
                // Если есть активное окно, сворачиваем его
                await controlWindow(activeWindowId, 'minimize');
            } else {
                // Если все окна свернуты, разворачиваем последнее свернутое
                let minimizedWindowId = null;
                // Ищем любое свернутое окно для этой команды
                for (const winId of currentWindowIds) {
                    if (liveWindows[winId] && (liveWindows[winId].state & WM_STATE_MINIMIZED) !== 0) {
                        minimizedWindowId = winId;
                        break;
                    }
                }
                if (minimizedWindowId) {
                    await controlWindow(minimizedWindowId, 'focus'); // Разворачиваем и фокусируемся
                } else {
                    // Если нет ни активных, ни свернутых (странно, но на всякий случай)
                    launchNativeApp(title, command, iconSrc);
                }
            }
            // После действия, обновим состояние панели задач
            setTimeout(updateTaskbar, 100); // Небольшая задержка для WM на обработку
        };
    }
    return appIcon;
}

/**
 * Обновляет индикатор активности на иконке панели задач.
 * @param {string} command - Команда приложения.
 * @param {boolean} hasActiveWindows - Есть ли видимые (не свернутые) окна для этой команды.
 * @param {boolean} hasMinimizedWindows - Есть ли свернутые окна для этой команды.
 */
function updateTaskbarIconIndicator(command, hasActiveWindows, hasMinimizedWindows) {
    const iconEntry = taskbarIconsByCommand[command];
    if (iconEntry && iconEntry.icon) {
        const dot = iconEntry.icon.querySelector('.indicator-dot');
        if (dot) {
            if (hasActiveWindows) {
                dot.style.display = 'block';
                dot.style.backgroundColor = '#00ff00'; // Зеленый: активно
                iconEntry.icon.classList.remove('minimized');
            } else if (hasMinimizedWindows) {
                dot.style.display = 'block';
                dot.style.backgroundColor = '#ffa500'; // Оранжевый: свернуто
                iconEntry.icon.classList.add('minimized');
            } else {
                // Нет открытых окон, скрываем точку, если это не закрепленное приложение
                let isPinned = pinnedApplicationsFromDB.some(app => app.command === command);
                if (!isPinned) {
                    dot.style.display = 'none';
                    iconEntry.icon.remove(); // Удаляем иконку, если не закреплена и нет окон
                    delete taskbarIconsByCommand[command];
                } else {
                    dot.style.display = 'none'; // Скрываем, если закреплено, но нет активных окон
                    iconEntry.icon.classList.remove('minimized'); // Снимаем статус свернутого
                }
            }
        }
    }
}


/**
 * Отправляет команду на запуск нативного приложения через PHP-ретранслятор.
 * @param {string} title - Заголовок приложения.
 * @param {string} command - Команда для выполнения.
 * @param {string} iconSrc - Источник иконки.
 */
async function launchNativeApp(title, command, iconSrc) {
    console.log(`Попытка запустить: ${title} с командой: ${command}`);

    // Получаем или создаем иконку на панели задач
    const appIcon = getOrCreateTaskbarIcon(title, command, iconSrc);
    const dot = appIcon.querySelector('.indicator-dot');

    if (dot) {
        dot.style.display = 'block';
        dot.style.backgroundColor = '#00ff00'; // Временно показываем зеленый, пока не получим подтверждение
    }

    try {
        const formData = new FormData();
        formData.append('action', 'execute_command_via_relay');
        formData.append('command', command);

        const response = await fetch('dash.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            console.log(`Успешно отправлено: ${title}. Сообщение: ${result.message}`);
            // После успешной отправки, запускаем опрос состояния окон
            setTimeout(updateTaskbar, 1000); // Даем время приложению запуститься
        } else {
            console.error(`Ошибка запуска ${title}: ${result.message}`);
            if (dot) {
                dot.style.backgroundColor = '#ff0000'; // Красный для ошибки
                setTimeout(() => {
                    dot.style.display = 'none';
                    // Если это была новая иконка для незакрепленного приложения, удалим ее
                    let isPinned = pinnedApplicationsFromDB.some(app => app.command === command);
                    if (!isPinned && taskbarIconsByCommand[command] && taskbarIconsByCommand[command].windowIds.size === 0) {
                        taskbarIconsByCommand[command].icon.remove();
                        delete taskbarIconsByCommand[command];
                    }
                }, 3000);
            }
            // Используем alert() только для отладки, в продакшене лучше использовать кастомное модальное окно.
            alert(`Ошибка запуска ${title}: ${result.message}`);
        }
    } catch (error) {
        console.error(`Сетевая ошибка при запуске ${title}:`, error);
        if (dot) {
            dot.style.backgroundColor = '#ff0000';
            setTimeout(() => {
                dot.style.display = 'none';
                let isPinned = pinnedApplicationsFromDB.some(app => app.command === command);
                if (!isPinned && taskbarIconsByCommand[command] && taskbarIconsByCommand[command].windowIds.size === 0) {
                    taskbarIconsByCommand[command].icon.remove();
                    delete taskbarIconsByCommand[command];
                }
            }, 3000);
        }
        // Используем alert() только для отладки, в продакшене лучше использовать кастомное модальное окно.
        alert(`Сетевая ошибка при запуске ${title}. Проверьте консоль.`);
    }

    toggleStartMenu(true);
}

/**
 * Отправляет команду управления окном WM-контроллеру.
 * @param {string} windowId - ID окна.
 * @param {string} actionType - Тип действия (focus, minimize, maximize, close).
 */
async function controlWindow(windowId, actionType) {
    console.log(`Отправка команды ${actionType} для окна ${windowId}`);
    try {
        const formData = new FormData();
        formData.append('action', 'control_window');
        formData.append('window_id', windowId);
        formData.append('action_type', actionType);

        const response = await fetch('dash.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            console.log(`Успешно: ${result.message}`);
            setTimeout(updateTaskbar, 100); // Небольшая задержка для WM на обработку
        } else {
            console.error(`Ошибка управления окном: ${result.message}`);
            // Используем alert() только для отладки, в продакшене лучше использовать кастомное модальное окно.
            alert(`Ошибка управления окном: ${result.message}`);
        }
    } catch (error) {
        console.error(`Сетевая ошибка при управлении окном:`, error);
        // Используем alert() только для отладки, в продакшене лучше использовать кастомное модальное окно.
        alert(`Сетевая ошибка при управлении окном. Проверьте консоль.`);
    }
}

/**
 * Получает список запущенных окон от WM-контроллера и обновляет панель задач.
 */
async function updateTaskbar() {
    console.log("Обновление панели задач...");
    try {
        const formData = new FormData();
        formData.append('action', 'list_windows');

        const response = await fetch('dash.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success' && result.windows) {
            const currentLiveWindowIds = new Set();
            const newLiveWindows = {};

            result.windows.forEach(win => {
                if (win.id && win.name && typeof win.state !== 'undefined') {
                    currentLiveWindowIds.add(win.id);
                    newLiveWindows[win.id] = {
                        name: win.name,
                        state: win.state,
                        command: null, // Будет заполнено ниже
                        iconSrc: null // Будет заполнено ниже
                    };

                    // Пытаемся найти соответствующее приложение из нашей БД по имени или части имени
                    let matchedApp = applicationsFromDB.find(app =>
                        win.name.toLowerCase().includes(app.name.toLowerCase()) ||
                        app.name.toLowerCase().includes(win.name.toLowerCase()) || // Обратное включение
                        app.command.toLowerCase().includes(win.name.toLowerCase()) // Если имя окна содержит часть команды
                    );

                    // Если не нашли точное совпадение, попробуем по известным командам/именам
                    if (!matchedApp) {
                        if (win.name.toLowerCase().includes('terminal') || win.name.toLowerCase().includes('xterm')) {
                            matchedApp = applicationsFromDB.find(app => app.name === 'Терминал' || app.command === 'xterm');
                        } else if (win.name.toLowerCase().includes('firefox') || win.name.toLowerCase().includes('mozilla firefox')) {
                            matchedApp = applicationsFromDB.find(app => app.name === 'Веб-браузер' || app.command === 'firefox');
                        } else if (win.name.toLowerCase().includes('nautilus') || win.name.toLowerCase().includes('files')) {
                            matchedApp = applicationsFromDB.find(app => app.name === 'Файлы' || app.command === 'nautilus');
                        }
                        // Добавь больше таких правил для других приложений, если нужно
                    }

                    const appName = matchedApp ? matchedApp.name : win.name;
                    const appCommand = matchedApp ? matchedApp.command : win.name; // Используем имя окна как команду, если не нашли
                    const appIcon = matchedApp ? matchedApp.icon : 'icons/default.png'; // Запасная иконка

                    newLiveWindows[win.id].command = appCommand;
                    newLiveWindows[win.id].iconSrc = appIcon;

                    // Создаем/получаем иконку на панели задач для этой команды
                    getOrCreateTaskbarIcon(appName, appCommand, appIcon);

                    // Добавляем ID окна к списку ID, связанных с этой командой
                    taskbarIconsByCommand[appCommand].windowIds.add(win.id);
                }
            });

            // Обновляем liveWindows
            liveWindows = newLiveWindows;

            // Проходим по всем командам на панели задач и обновляем их состояние
            for (const command in taskbarIconsByCommand) {
                let hasActive = false;
                let hasMinimized = false;
                const windowIdsForCommand = Array.from(taskbarIconsByCommand[command].windowIds);

                for (const winId of windowIdsForCommand) {
                    if (!currentLiveWindowIds.has(winId)) {
                        // Окно больше не существует, удаляем его из списка
                        taskbarIconsByCommand[command].windowIds.delete(winId);
                    } else {
                        // Окно все еще существует, проверяем его состояние
                        const windowState = liveWindows[winId].state;
                        // Проверяем биты состояния
                        const isMinimized = (windowState & WM_STATE_MINIMIZED) !== 0;
                        // const isMaximized = (windowState & WM_STATE_MAXIMIZED) !== 0;
                        // const isFullscreen = (windowState & WM_STATE_FULLSCREEN) !== 0;

                        if (!isMinimized) { // Если окно не свернуто (нормальное, развернутое, полноэкранное)
                            hasActive = true;
                        } else { // Если окно свернуто
                            hasMinimized = true;
                        }
                    }
                }
                updateTaskbarIconIndicator(command, hasActive, hasMinimized);
            }

        } else {
            console.error(`Ошибка получения списка окон: ${result.message}`);
            // Если WM-контроллер не работает, скрываем все индикаторы
            for (const command in taskbarIconsByCommand) {
                const dot = taskbarIconsByCommand[command].icon.querySelector('.indicator-dot');
                if (dot) dot.style.display = 'none';
            }
        }
    } catch (error) {
        console.error(`Сетевая ошибка при получении списка окон:`, error);
        // При сетевой ошибке также скрываем все индикаторы
        for (const command in taskbarIconsByCommand) {
            const dot = taskbarIconsByCommand[command].icon.querySelector('.indicator-dot');
            if (dot) dot.style.display = 'none';
        }
    }
}

/**
 * Заполняет меню "Пуск" приложениями из базы данных.
 */
function populateStartMenu() {
    const menu = document.getElementById('startMenu');
    menu.innerHTML = ''; // Очищаем предыдущие элементы

    if (dbError) {
        const errorMsg = document.createElement('div');
        errorMsg.textContent = 'Ошибка загрузки приложений: ' + dbError;
        errorMsg.style.color = 'red';
        errorMsg.style.padding = '10px';
        menu.appendChild(errorMsg);
        return;
    }

    if (applicationsFromDB.length === 0) {
        const noAppsMsg = document.createElement('div');
        noAppsMsg.textContent = 'Нет доступных приложений.';
        noAppsMsg.style.color = 'white';
        noAppsMsg.style.padding = '10px';
        noAppsMsg.style.textAlign = 'center';
        menu.appendChild(noAppsMsg);
        return;
    }

    applicationsFromDB.forEach(app => {
        const button = document.createElement('button');
        // При клике отправляем команду на сервер
        button.onclick = () => {
            launchNativeApp(app.name, app.command, app.icon);
            toggleStartMenu(true); // Закрываем меню "Пуск"
        };

        const img = document.createElement('img');
        img.src = app.icon;
        img.alt = app.name;

        const span = document.createElement('span');
        span.textContent = app.name;

        button.appendChild(img);
        button.appendChild(span);
        menu.appendChild(button);
    });
}

/**
 * Переключает видимость меню "Пуск".
 * @param {boolean} [forceClose=false] - Если true, принудительно закрывает меню.
 */
function toggleStartMenu(forceClose = false) {
     const menu = document.getElementById('startMenu');
     if (forceClose) {
        menu.style.display = 'none';
        return;
     }
     if (menu.style.display === 'flex') {
         menu.style.display = 'none';
     } else {
         populateStartMenu(); // Заполняем меню при каждом открытии
         menu.style.display = 'flex';
     }
}

/**
 * Обновляет цифровые часы на панели задач.
 */
function updateClock() {
     const clockElement = document.getElementById('taskbarClock');
     if (clockElement) {
         const now = new Date();
         const hours = now.getHours().toString().padStart(2, '0');
         const minutes = now.getMinutes().toString().padStart(2, '0');
         clockElement.innerText = `${hours}:${minutes}`;
     }
}

/**
 * Загружает закрепленные приложения на панель задач при загрузке страницы.
 */
function loadPinnedApps() {
    pinnedApplicationsFromDB.forEach(app => {
        // Создаем иконку панели задач для каждого закрепленного приложения, помечая ее как закрепленную
        getOrCreateTaskbarIcon(app.name, app.command, app.icon, true);
    });
}


// Обновляем часы каждую секунду и запускаем сразу при загрузке
setInterval(updateClock, 1000);
// Обновляем панель задач каждые 2 секунды, чтобы отслеживать состояние окон
setInterval(updateTaskbar, 2000);

document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    loadPinnedApps(); // Загружаем закрепленные приложения, когда DOM готов
    updateTaskbar(); // Первоначальное обновление панели задач
    if (dbError) {
        console.error("Ошибка подключения к БД:", dbError);
        // Здесь можно добавить визуальное уведомление, если необходимо
    }
});

// Закрываем меню "Пуск" при клике вне его области
document.addEventListener('click', function(event) {
    const startMenu = document.getElementById('startMenu');
    const startButton = document.querySelector('.start-button');
    // Проверяем, что клик был не внутри меню и не по кнопке "Пуск"
    if (!startMenu.contains(event.target) && !startButton.contains(event.target)) {
        if (startMenu.style.display === 'flex') {
            startMenu.style.display = 'none';
        }
    }
});

document.addEventListener('keydown', (event) => {
    // Проверяем, была ли нажата клавиша "Meta" (обычно это кнопка Win/Super)
    if (event.key === 'Meta') {
        event.preventDefault(); // Останавливаем стандартное поведение браузера (например, открытие меню ОС)
        toggleStartMenu(); // Вызываем функцию для переключения меню "Пуск"
    }
});
</script>