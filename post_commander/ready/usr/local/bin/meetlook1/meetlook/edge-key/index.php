<?php
// 1. Настройки подключения к базе данных MySQL
$db_host = 'localhost';      // Хост вашей БД (обычно 'localhost')
$db_user = 'root'; // Имя пользователя БД
$db_pass = '';   // Пароль пользователя БД
$db_name = 'edge'; // Название вашей БД

// Попытка подключения к БД
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$applications_data = [];
$db_connection_error = null;

// Проверка соединения
if ($conn->connect_error) {
    $db_connection_error = "Ошибка подключения: " . $conn->connect_error;
} else {
    // Установка кодировки UTF-8 для правильного отображения русских имен
    $conn->set_charset("utf8");

    $sql = "SELECT name, path, icon FROM applications ORDER BY name ASC";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $applications_data[] = $row;
        }
        $result->free();
    } else {
        $db_connection_error = "Ошибка выполнения запроса: " . $conn->error;
    }
    $conn->close();
}

// Конвертируем данные приложений в JSON для JavaScript
$apps_json = json_encode($applications_data);
$db_error_json = json_encode($db_connection_error); // Также передаем ошибку, если она есть
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>PHP Launcher</title>
    <style>
html, body {
     margin: 0;
     padding: 0;
     height: 100%;
     overflow: hidden;
     font-family: sans-serif;
}
body {
     background: url('https://wallpaperaccess.com/full/317501.jpg') center/cover no-repeat;
}
.window {
     position: absolute;
     width: 400px;
     height: 300px;
     border: 1px solid #666666a1;
     background: #ffffff;
     box-shadow: 0 0 15px #00000086;
     resize: both;
     overflow: hidden;
     border-top-left-radius: 7px;
     border-top-right-radius: 7px;
}
.titlebar {
     background: #4444446b;
     padding: 5px;
     cursor: move;
     display: flex;
     justify-content: space-between;
     align-items: center;
     height: 20px; /* Явно зададим высоту, чтобы calc работал точнее */
}
.titlebar span {
    margin-left: 5px;
    color: #333;
    font-weight: bold;
}
.titlebar button {
     margin-left: 5px;
     background: none;
     border: none;
     color: #333;
     font-size: 16px;
     cursor: pointer;
}
.titlebar button:hover {
     color: #000;
}
iframe {
     width: 100%;
     height: calc(100% - 30px); /* 30px = высота titlebar (20px) + padding (5px*2) */
     border: none;
}
.taskbar {
     position: absolute;
     bottom: 0;
     left: 0;
     right: 0;
     height: 40px;
     background: rgba(20,20,20,0.9);
     display: flex;
     align-items: center;
     padding: 0 10px;
     border-top-left-radius: 15px;
     border-top-right-radius: 15px;
}
#taskbarClock {
     color: white;
     font-size: 14px;
    margin-left: auto;
}
.start-button {
     background: #2d2d2d00;
     color: white;
     border: none;
     border-radius: 5px;
     padding: 5px 10px;
     cursor: pointer;
     margin-right: 10px;
     font-size: 20px;
     line-height: 1;
}
.taskbar-apps {
     display: flex;
     gap: 5px;
     flex: 1;
     justify-content: center;
}
.taskbar-apps button { /* Стиль для кнопок-иконок, если они будут кнопками */
     background: #44444400;
     border: none;
     padding: 5px;
     color: white;
     cursor: pointer;
}
.start-menu {
     position: absolute;
     bottom: 50px;
     left: 10px;
     width: 260px;
     max-height: 360px;
     background: #2c2c2c;
     border: 1px solid #555;
     display: none; /* Изначально скрыто */
     flex-wrap: wrap; /* Чтобы кнопки переносились */
     padding: 10px;
     box-shadow: 0 0 10px #000;
     border-radius: 15px;
     overflow-y: auto; /* Если приложений много */
}
.start-menu button {
     width: 70px; /* Немного увеличил для лучшего вида */
     height: 70px; /* Немного увеличил */
     margin: 5px;
     background: #55555500;
     border-radius: 4px;
     border: none;
     color: white;
     font-size: 10px;
     text-align: center;
     display: flex;
     flex-direction: column;
     justify-content: center;
     align-items: center;
     cursor: pointer;
     padding: 5px; /* Внутренний отступ */
}
.start-menu button:hover {
    background: #ffffff22;
}
.start-menu img {
     width: 32px;  /* Немного увеличил иконку */
     height: 32px; /* Немного увеличил иконку */
     margin-bottom: 4px; /* Отступ под иконкой */
     border-radius: 50%; /* Оставим круглыми */
}
.start-menu span {
    display: block; /* Чтобы текст был под иконкой */
    word-wrap: break-word; /* Перенос длинных слов */
    line-height: 1.2;
}
.taskbar-icon {
     position: relative;
     width: 36px;  /* Уменьшил для компактности */
     height: 36px; /* Уменьшил для компактности */
     border-radius: 4px;
     overflow: hidden;
     display: flex;
     align-items: center;
     justify-content: center;
     background: #55555533; /* Небольшой фон для видимости */
     cursor: pointer;
     margin: 2px; /* Небольшой отступ между иконками */
}
.taskbar-icon:hover {
    background: #55555588;
}
.taskbar-icon img {
     width: 24px;
     height: 24px;
     /* border-radius: 50%;  Убрал, т.к. иконки могут быть не круглыми */
     pointer-events: none;
}
.indicator-dot {
     position: absolute;
     bottom: 3px; /* Скорректировал положение */
     width: 6px;
     height: 6px;
     border-radius: 50%;
     background: white;
     opacity: 0.8;
}
.taskbar-icon.minimized .indicator-dot {
     opacity: 0.4; /* Менее заметная точка для свернутых */
}
     </style>
</head>
<body>

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

let z = 1; // z-index manager
let openWindows = {}; // Объект для отслеживания открытых окон и их иконок

function createWindow(title, url, iconSrc) {
     const windowId = 'window_' + Date.now() + Math.random().toString(16).slice(2); // Уникальный ID для окна

     // Если окно с таким URL уже открыто и не свернуто, просто поднимем его и выйдем
     for (const id in openWindows) {
        if (openWindows[id].url === url && openWindows[id].win.style.display !== 'none') {
            openWindows[id].win.style.zIndex = z++;
            return; // Выходим, чтобы не создавать дубликат
        }
     }

     const win = document.createElement('div');
     win.className = 'window';
     win.style.top = Math.random() * 50 + 30 + 'px';
     win.style.left = Math.random() * 150 + 30 + 'px';
     win.style.zIndex = z++;
     win.dataset.id = windowId;
     win.dataset.url = url; // Сохраняем URL для возможной проверки дубликатов

     const titlebar = document.createElement('div');
     titlebar.className = 'titlebar';

     const titleEl = document.createElement('span');
     titleEl.innerText = title;

     const btnsDiv = document.createElement('div'); // Контейнер для кнопок

     const minBtn = document.createElement('button');
     minBtn.innerHTML = '−';
     minBtn.title = 'Свернуть';
     minBtn.onclick = (e) => {
         e.stopPropagation();
         win.style.display = 'none';
         if (openWindows[windowId]) {
             openWindows[windowId].icon.classList.add('minimized');
         }
     };

     const extBtn = document.createElement('button');
     extBtn.innerHTML = '↗';
     extBtn.title = 'Открыть в новой вкладке';
     extBtn.onclick = (e) => {
         e.stopPropagation();
         window.open(url, '_blank');
     };

     const closeBtn = document.createElement('button');
     closeBtn.innerHTML = '×';
     closeBtn.title = 'Закрыть';
     closeBtn.onclick = (e) => {
         e.stopPropagation();
         win.remove();
         if (openWindows[windowId]) {
             openWindows[windowId].icon.remove();
             delete openWindows[windowId]; // Удаляем из списка открытых окон
         }
     };

     btnsDiv.appendChild(minBtn);
     btnsDiv.appendChild(extBtn);
     btnsDiv.appendChild(closeBtn);

     titlebar.appendChild(titleEl);
     titlebar.appendChild(btnsDiv);
     win.appendChild(titlebar);

     const iframe = document.createElement('iframe');
     iframe.src = url;
     win.appendChild(iframe);
     document.body.appendChild(win);

     win.addEventListener('mousedown', () => {
         win.style.zIndex = z++;
     });

     let offsetX, offsetY, dragging = false;
     titlebar.onmousedown = e => {
         if (e.target.tagName === 'BUTTON' || e.target.parentElement.tagName === 'BUTTON') return;
         dragging = true;
         offsetX = e.clientX - win.offsetLeft;
         offsetY = e.clientY - win.offsetTop;
         win.style.zIndex = z++;
     };
     document.onmouseup = () => dragging = false;
     document.onmousemove = e => {
         if (dragging) {
             win.style.left = Math.max(0, (e.clientX - offsetX)) + 'px'; // Ограничение по левому краю
             win.style.top = Math.max(0, (e.clientY - offsetY)) + 'px';   // Ограничение по верхнему краю
         }
     };

     // Создаем иконку на панели задач только если ее еще нет для этого URL
     let appIcon;
     let existingIcon = null;
     for (const id in openWindows) {
        if (openWindows[id].url === url) {
            existingIcon = openWindows[id].icon;
            break;
        }
     }

     if (existingIcon) {
        appIcon = existingIcon;
        // Если окно было свернуто и мы его открываем заново через меню "Пуск"
        if (win.style.display !== 'none') {
            appIcon.classList.remove('minimized');
        }
     } else {
         appIcon = document.createElement('div');
         appIcon.className = 'taskbar-icon';
         appIcon.title = title;

         const iconImg = document.createElement('img');
         iconImg.src = iconSrc;
         iconImg.alt = title;
         iconImg.onerror = () => {
            // iconImg.style.display = 'none'; // Скрываем если ошибка
            // Можно добавить текст вместо иконки
            const textFallback = document.createElement('span');
            textFallback.innerText = title.substring(0,1).toUpperCase();
            textFallback.style.color = 'white';
            textFallback.style.fontSize = '16px';
            appIcon.insertBefore(textFallback, iconImg); // Вставляем перед сломанной картинкой
            iconImg.remove(); // Удаляем сломанную картинку
         };
         appIcon.appendChild(iconImg);

         const dot = document.createElement('div');
         dot.className = 'indicator-dot';
         appIcon.appendChild(dot);

         document.getElementById('taskbarApps').appendChild(appIcon);
     }


    openWindows[windowId] = { win: win, icon: appIcon, url: url };

    appIcon.onclick = () => {
        // Найти все окна, связанные с этой иконкой (по URL)
        let relatedWindowsVisible = false;
        let topRelatedWindow = null;

        for (const id in openWindows) {
            if (openWindows[id].icon === appIcon) { // Проверяем, что это та самая иконка
                const currentWin = openWindows[id].win;
                if (currentWin.style.display !== 'none') {
                    relatedWindowsVisible = true;
                    if (!topRelatedWindow || parseInt(currentWin.style.zIndex) > parseInt(topRelatedWindow.style.zIndex)) {
                        topRelatedWindow = currentWin;
                    }
                }
            }
        }

        if (relatedWindowsVisible) {
            // Если есть видимые окна и кликнутое не наверху, поднять его.
            // Если кликнутое уже наверху, свернуть все окна этого приложения.
            let allShouldBeMinimized = true;
            for (const id in openWindows) {
                if (openWindows[id].icon === appIcon) {
                    const currentWin = openWindows[id].win;
                     // Если окно не самое верхнее из этой группы, его не трогаем при решении о минимизации всех
                    if (currentWin !== topRelatedWindow && currentWin.style.display !== 'none') {
                         allShouldBeMinimized = false; // Есть другое видимое окно этого приложения
                    }
                }
            }

            if (topRelatedWindow && parseInt(topRelatedWindow.style.zIndex) !== z - 1 && topRelatedWindow.style.display !== 'none'){
                 topRelatedWindow.style.zIndex = z++;
                 appIcon.classList.remove('minimized'); // Убедимся что иконка не "свернута"
            } else { // Либо самое верхнее, либо единственное видимое - сворачиваем все
                 for (const id in openWindows) {
                    if (openWindows[id].icon === appIcon) {
                        openWindows[id].win.style.display = 'none';
                    }
                }
                appIcon.classList.add('minimized');
            }

        } else { // Все окна этого приложения свернуты, разворачиваем последнее активное (или первое найденное)
            let lastActiveWin = null;
             for (const id in openWindows) {
                 if (openWindows[id].icon === appIcon) {
                    lastActiveWin = openWindows[id].win; // Просто берем последнее (или единственное)
                 }
             }
            if(lastActiveWin) {
                lastActiveWin.style.display = 'block';
                lastActiveWin.style.zIndex = z++;
                appIcon.classList.remove('minimized');
            }
        }
    };
    toggleStartMenu(true); // Закрыть меню "Пуск" после выбора приложения
}


function populateStartMenu() {
    const menu = document.getElementById('startMenu');
    menu.innerHTML = ''; // Очищаем предыдущие пункты

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
        button.onclick = () => createWindow(app.name, app.path, app.icon);

        const img = document.createElement('img');
        img.src = app.icon;
        img.alt = app.name;
        // img.style.borderRadius = '50%'; // Уже есть в CSS для .start-menu img

        const span = document.createElement('span');
        span.textContent = app.name;

        button.appendChild(img);
        button.appendChild(span);
        menu.appendChild(button);
    });
}

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

// Функция для обновления часов
function updateClock() {
     const clockElement = document.getElementById('taskbarClock');
     if (clockElement) {
         const now = new Date();
         const hours = now.getHours().toString().padStart(2, '0');
         const minutes = now.getMinutes().toString().padStart(2, '0');
         clockElement.innerText = `${hours}:${minutes}`;
     }
}

// Обновлять часы каждую секунду и запустить сразу
setInterval(updateClock, 1000);
document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    if (dbError) {
        console.error("Ошибка подключения к БД:", dbError);
        // Можно вывести уведомление пользователю в каком-то месте на странице, если нужно
        // Например, в taskbar
        const taskbar = document.getElementById('taskbarApps');
        const errorDiv = document.createElement('div');
        errorDiv.textContent = "DB Error!";
        errorDiv.style.color = "red";
        errorDiv.style.marginLeft = "10px";
        errorDiv.title = dbError; // Полная ошибка во всплывающей подсказке
        // taskbar.parentNode.insertBefore(errorDiv, taskbar.nextSibling); // Вставить после taskbarApps
    }
});

// Закрывать меню "Пуск" при клике вне его области
document.addEventListener('click', function(event) {
    const startMenu = document.getElementById('startMenu');
    const startButton = document.querySelector('.start-button');
    // Проверяем, что клик был не по меню и не по кнопке "Пуск"
    if (!startMenu.contains(event.target) && !startButton.contains(event.target)) {
        if (startMenu.style.display === 'flex') {
            startMenu.style.display = 'none';
        }
    }
});

</script>
</body>
</html>