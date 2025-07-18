<?php
// Отключение кэширования для браузера
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Флаг доступа к QwartzDataBaseControl 1
$qwartz_open = 1; // Измените на 0, чтобы полностью закрыть страницу

if ($qwartz_open == 0) {
    header("Location: /index.php"); // Абсолютный путь, указывающий на корень веб-сервера
    exit();
}

session_start();

// Обработка AJAX-запросов
if (isset($_REQUEST['act'])) {
    // Если пользователь не авторизован — возвращаем ошибку
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
        http_response_code(403);
        exit('Нет доступа');
    }
    
    // Параметры подключения к базе данных
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'edge'; // Замените на название вашей базы данных

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("Ошибка подключения (" . $conn->connect_errno . "): " . $conn->connect_error);
    }

    $act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

    if ($act == 'listTables') {
        // Вывод списка таблиц базы данных
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_array()) {
                echo '<li>' . htmlspecialchars($row[0]) . '</li>';
            }
        } else {
            echo "Ошибка (" . $conn->errno . "): " . $conn->error;
        }
    } elseif ($act == 'showTable') {
        // Вывод данных из выбранной таблицы
        $table = $conn->real_escape_string($_GET['table']);
        $result = $conn->query("SELECT * FROM `$table` LIMIT 100");
        if ($result) {
            echo "<table>";
            // Заголовок таблицы
            echo "<tr>";
            while ($field = $result->fetch_field()) {
                echo "<th>" . htmlspecialchars($field->name) . "</th>";
            }
            echo "<th>Действия</th>";
            echo "</tr>";
            // Вывод строк таблицы
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $col => $value) {
                    echo '<td contenteditable="true" class="editable" data-table="' . htmlspecialchars($table) . '" data-id="' . htmlspecialchars($row[array_keys($row)[0]]) . '" data-column="' . htmlspecialchars($col) . '">' . htmlspecialchars($value) . '</td>';
                }
                $primaryKey = array_keys($row)[0];
                echo '<td><button class="deleteRow" data-table="' . htmlspecialchars($table) . '" data-id="' . htmlspecialchars($row[$primaryKey]) . '">Удалить</button></td>';
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Ошибка (" . $conn->errno . "): " . $conn->error;
        }
    } elseif ($act == 'executeSQL') {
        // Выполнение произвольного SQL‑запроса
        $query = $_POST['query'];
        $result = $conn->query($query);
        if ($result === true) {
            echo "Запрос выполнен успешно.";
        } elseif ($result === false) {
            echo "Ошибка (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($query);
        } else {
            // Если запрос вернул набор данных, выводим его в виде таблицы
            echo "<table>";
            echo "<tr>";
            while ($field = $result->fetch_field()) {
                echo "<th>" . htmlspecialchars($field->name) . "</th>";
            }
            echo "</tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } elseif ($act == 'deleteRow') {
        // Удаление строки из таблицы с обработкой зависимостей
        $table = $conn->real_escape_string($_POST['table']);
        $id = $conn->real_escape_string($_POST['id']);
        // Определяем имя первичного ключа (предполагаем, что это первый столбец)
        $primaryKey = 'id';
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row) {
                $primaryKey = $row['Field'];
            }
        }
        $sql = "DELETE FROM `$table` WHERE `$primaryKey` = '$id' LIMIT 1";
        if ($conn->query($sql)) {
            echo "Запись удалена.";
        } else {
            // Если ошибка из-за ограничений внешнего ключа (код 1451)
            if ($conn->errno == 1451) {
                // Поиск зависимых таблиц
                $fkQuery = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                            WHERE REFERENCED_TABLE_SCHEMA = '$dbName' 
                              AND REFERENCED_TABLE_NAME = '$table' 
                              AND REFERENCED_COLUMN_NAME = '$primaryKey'";
                $resFK = $conn->query($fkQuery);
                if ($resFK) {
                    while ($fkRow = $resFK->fetch_assoc()) {
                        $depTable = $fkRow['TABLE_NAME'];
                        $depColumn = $fkRow['COLUMN_NAME'];
                        $delDep = "DELETE FROM `$depTable` WHERE `$depColumn` = '$id'";
                        $conn->query($delDep);
                    }
                    // Повторная попытка удаления основной записи
                    if ($conn->query($sql)) {
                        echo "Запись и зависимые строки удалены.";
                    } else {
                        echo "Ошибка удаления записи (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($sql);
                    }
                } else {
                    echo "Ошибка определения зависимых таблиц (" . $conn->errno . "): " . $conn->error;
                }
            } else {
                echo "Ошибка удаления записи (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($sql);
            }
        }
    } elseif ($act == 'updateCell') {
        // Обновление конкретной ячейки таблицы
        $table  = $conn->real_escape_string($_POST['table']);
        $id     = $conn->real_escape_string($_POST['id']);
        $column = $conn->real_escape_string($_POST['column']);
        $value  = $conn->real_escape_string($_POST['value']);
        $primaryKey = 'id';
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row) {
                $primaryKey = $row['Field'];
            }
        }
        $sql = "UPDATE `$table` SET `$column` = '$value' WHERE `$primaryKey` = '$id' LIMIT 1";
        if ($conn->query($sql)) {
            echo "Запись обновлена.";
        } else {
            echo "Ошибка обновления (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($sql);
        }
    } elseif ($act == 'exportDB') {
        // Экспорт базы данных в виде SQL дампа
        $tablesResult = $conn->query("SHOW TABLES");
        if (!$tablesResult) {
            echo "Ошибка (" . $conn->errno . "): " . $conn->error;
            $conn->close();
            exit;
        }
        $sqlDump = "";
        while ($tableRow = $tablesResult->fetch_array()) {
            $tableName = $tableRow[0];
            // Получаем структуру таблицы
            $createResult = $conn->query("SHOW CREATE TABLE `$tableName`");
            if ($createResult) {
                $createRow = $createResult->fetch_assoc();
                $sqlDump .= $createRow['Create Table'] . ";\n\n";
            }
            // Получаем данные таблицы
            $dataResult = $conn->query("SELECT * FROM `$tableName`");
            if ($dataResult) {
                while ($dataRow = $dataResult->fetch_assoc()) {
                    $cols = array_keys($dataRow);
                    $vals = array_values($dataRow);
                    $colsStr = implode("`, `", $cols);
                    $valsEscaped = array_map(function($val) use ($conn) {
                        return "'" . $conn->real_escape_string($val) . "'";
                    }, $vals);
                    $valsStr = implode(", ", $valsEscaped);
                    $sqlDump .= "INSERT INTO `$tableName` (`$colsStr`) VALUES ($valsStr);\n";
                }
                $sqlDump .= "\n\n";
            }
        }
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="'.$dbName.'_backup_'.date("Y-m-d").'.sql"');
        echo $sqlDump;
        $conn->close();
        exit;
    } elseif ($act == 'importDB') {
        // Импорт базы данных из загруженного SQL‑файла
        if (isset($_FILES['sqlFile']) && $_FILES['sqlFile']['error'] == UPLOAD_ERR_OK) {
            $sqlContent = file_get_contents($_FILES['sqlFile']['tmp_name']);
            if ($conn->multi_query($sqlContent)) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
                echo "Импорт базы данных выполнен.";
            } else {
                echo "Ошибка импорта (" . $conn->errno . "): " . $conn->error;
            }
        } else {
            echo "Ошибка загрузки файла.";
        }
    } elseif ($act == 'insertRow') {
        // Вставка новой строки в таблицу
        $table = $conn->real_escape_string($_POST['table']);
        // Ожидается, что данные приходят в виде JSON-строки в поле 'fields'
        $fields = json_decode($_POST['fields'], true);
        if (!$fields || !is_array($fields)) {
            echo "Некорректные данные для вставки.";
            exit;
        }
        $columns = array();
        $values = array();
        foreach ($fields as $column => $value) {
            $columns[] = "`" . $conn->real_escape_string($column) . "`";
            $values[] = "'" . $conn->real_escape_string($value) . "'";
        }
        $columnsStr = implode(", ", $columns);
        $valuesStr = implode(", ", $values);
        $sql = "INSERT INTO `$table` ($columnsStr) VALUES ($valuesStr)";
        if ($conn->query($sql)) {
            echo "Запись добавлена.";
        } else {
            echo "Ошибка вставки (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($sql);
        }
    } elseif ($act == 'getColumns') {
        // Получение описания столбцов выбранной таблицы (для формы вставки)
        $table = $conn->real_escape_string($_GET['table']);
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($result) {
            $columns = array();
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row;
            }
            header('Content-Type: application/json');
            echo json_encode($columns);
        } else {
            echo json_encode(array("error" => $conn->errno . " " . $conn->error));
        }
    } elseif ($act == 'createTable') {
        // Создание новой таблицы через графический интерфейс
        $table = $conn->real_escape_string($_POST['table']);
        $columns = json_decode($_POST['columns'], true);
        if (!$columns || !is_array($columns)) {
            echo "Некорректные данные для создания таблицы.";
            exit;
        }
        $colsArr = array();
        $primaryKeys = array();
        foreach ($columns as $col) {
            $colName = $conn->real_escape_string($col['name']);
            $colType = $conn->real_escape_string($col['type']);
            $attributes = isset($col['attributes']) ? $conn->real_escape_string($col['attributes']) : "";
            $colsArr[] = "`$colName` $colType $attributes";
            if (isset($col['primary']) && $col['primary']) {
                $primaryKeys[] = "`$colName`";
            }
        }
        if (!empty($primaryKeys)) {
            $colsArr[] = "PRIMARY KEY (" . implode(", ", $primaryKeys) . ")";
        }
        $colsSql = implode(", ", $colsArr);
        $sql = "CREATE TABLE `$table` ($colsSql)";
        if ($conn->query($sql)) {
            echo "Таблица создана.";
        } else {
            echo "Ошибка создания таблицы (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($sql);
        }
    } elseif ($act == 'deleteTable') {
        // Удаление таблицы
        $table = $conn->real_escape_string($_POST['table']);
        $sql = "DROP TABLE `$table`";
        if ($conn->query($sql)) {
            echo "Таблица удалена.";
        } else {
            echo "Ошибка удаления таблицы (" . $conn->errno . "): " . $conn->error . "<br>Запрос: " . htmlspecialchars($sql);
        }
    } else {
        echo "Неизвестное действие.";
    }
    $conn->close();
    exit;
}

// Обработка обычных (не AJAX) запросов — форма авторизации и основной интерфейс

// Обработка входа (жёстко заданный пароль)
if (isset($_POST['password'])) {
    $password = 'root'; // Измените на нужный вам пароль
    if ($_POST['password'] === $password) {
        $_SESSION['logged'] = true;
    } else {
        $error = 'Неверный пароль';
    }
}

// Если не авторизованы — выводим форму входа с логотипом
if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход - QwartzDataBaseControl 1</title>
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <meta name="robots" content="noindex, nofollow">
        <style>
            :root {
                --bg-color: #ffffff;
                --text-color: #000000;
                --border-color: #cccccc;
                --button-bg: #f0f0f0;
                --button-text: #000000;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg-color: #121212;
                    --text-color: #e0e0e0;
                    --border-color: #444444;
                    --button-bg: #1e1e1e;
                    --button-text: #e0e0e0;
                }
            }
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0;
                padding: 20px;
                background-color: var(--bg-color);
                color: var(--text-color);
                font-family: Arial, sans-serif;
                line-height: 1.6;
            }
            .login-container {
                max-width: 400px;
                margin: 100px auto;
                padding: 20px;
                border: 1px solid var(--border-color);
                border-radius: 4px;
                text-align: center;
                background-color: var(--bg-color);
            }
            input[type="password"] {
                width: 100%;
                padding: 10px;
                margin: 10px 0;
                border: 1px solid var(--border-color);
                border-radius: 4px;
                background-color: var(--bg-color);
                color: var(--text-color);
            }
            button {
                padding: 10px 20px;
                background-color: var(--button-bg);
                color: var(--button-text);
                border: 1px solid var(--border-color);
                border-radius: 4px;
                cursor: pointer;
            }
            button:hover {
                opacity: 0.8;
            }

            .logo {
                filter: invert(0%);
              }
          
              @media (prefers-color-scheme: dark) {
                .logo {
                  filter: invert(100%);
                }
              }
        </style>
        <script>
            document.addEventListener('keydown', function(e) {
              if (e.key === 'F12' || (e.ctrlKey && (e.key === 'u' || e.key === 'U')) || (e.ctrlKey && e.shiftKey && ['I', 'J'].includes(e.key))) {
                e.preventDefault();
              }
            });
        
            // Предотвращаем открытие контекстного меню
            window.addEventListener('contextmenu', function(e) {
              e.preventDefault();
            });
        
            // Перехватываем изменения DOM
            const originalDefineProperty = Object.defineProperty;
            Object.defineProperty = function(obj, prop, descriptor) {
              if (['innerHTML', 'outerHTML', 'textContent'].includes(prop)) {
                window.location.href = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
              }
              return originalDefineProperty.apply(this, arguments);
            };
        
            // Перехватываем изменения через setAttribute
            const originalSetAttribute = Element.prototype.setAttribute;
            Element.prototype.setAttribute = function(name, value) {
              if (['innerHTML', 'outerHTML'].includes(name)) {
                window.location.href = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
              }
              return originalSetAttribute.apply(this, arguments);
            };
        </script>
    </head>
    <body>
        <div class="login-container">
            <img class="logo" src="logo.png" alt="QwartzDataBaseControl 1" style="height:80px; margin-bottom:20px;">
            <h1>QwartzDataBaseControl 1</h1>
            <?php if (isset($error)) echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>'; ?>
            <form method="post">
                <label>Пароль: <input type="password" name="password" /></label>
                <br><br>
                <button type="submit">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>QwartzDataBaseControl 1</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <meta name="robots" content="noindex, nofollow">
    <!-- Подключаем jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --border-color: #cccccc;
            --button-bg: #f0f0f0;
            --button-text: #000000;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #121212;
                --text-color: #e0e0e0;
                --border-color: #444444;
                --button-bg: #1e1e1e;
                --button-text: #e0e0e0;
            }
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 200px;
            padding: 20px;
            border-right: 1px solid var(--border-color);
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        h3 {
            margin-top: 0;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        ul li {
            margin-bottom: 10px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
        }
        ul li:hover {
            background-color: var(--border-color);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid var(--border-color);
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        button {
            padding: 10px 20px;
            background-color: var(--button-bg);
            color: var(--button-text);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            opacity: 0.8;
        }
        .hidden {
            display: none;
        }

        .logo {
            filter: invert(0%);
          }
      
          @media (prefers-color-scheme: dark) {
            .logo {
              filter: invert(100%);
            }
          }
    </style>
    <script>
        // Отключаем F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
        document.addEventListener('keydown', function(e) {
          if (e.key === 'F12' || (e.ctrlKey && (e.key === 'u' || e.key === 'U')) || (e.ctrlKey && e.shiftKey && ['I', 'J'].includes(e.key))) {
            e.preventDefault();
            window.location.href = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
          }
        });

        // Предотвращаем открытие контекстного меню
        window.addEventListener('contextmenu', function(e) {
          e.preventDefault();
        });

        // Перехватываем изменения DOM
        const originalDefineProperty = Object.defineProperty;
        Object.defineProperty = function(obj, prop, descriptor) {
          if (['innerHTML', 'outerHTML', 'textContent'].includes(prop)) {
            window.location.href = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
          }
          return originalDefineProperty.apply(this, arguments);
        };

        // Перехватываем изменения через setAttribute
        const originalSetAttribute = Element.prototype.setAttribute;
        Element.prototype.setAttribute = function(name, value) {
          if (['innerHTML', 'outerHTML'].includes(name)) {
            window.location.href = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
          }
          return originalSetAttribute.apply(this, arguments);
        };
    </script>
</head>
<body>
    <div style="text-align:center; padding: 20px;">
        <img class="logo" src="logo.png" alt="QwartzDataBaseControl 1" style="height:80px; margin-bottom:10px;">
    </div>
    <div class="container">
        <div class="sidebar">
            <h3>Таблицы</h3>
            <ul id="tableList"></ul>
        </div>
        <div class="main-content">
            <h3>Данные таблицы</h3>
            <div id="tableData"></div>
            <hr>
            <h3>SQL запрос</h3>
            <textarea id="sqlQuery" rows="5"></textarea><br>
            <button id="executeQuery">Выполнить</button>
            <div id="queryResult"></div>
            
            <br>
            <button id="toggleExtraMenu">Показать дополнительные функции</button>
            <div id="extraMenu" class="hidden">
                <h3>Дополнительные функции</h3>
                <h4>Экспорт / Импорт базы данных</h4>
                <button id="exportDB">Экспорт базы данных</button>
                <br><br>
                <form id="importForm" enctype="multipart/form-data">
                    <input type="file" name="sqlFile" accept=".sql" required />
                    <button type="submit">Импорт базы данных</button>
                </form>
                <hr>
                <h4>Работа с таблицами</h4>
                <button id="showInsertRow">Добавить строку</button>
                <button id="showCreateTable">Создать таблицу</button>
                <button id="deleteCurrentTable">Удалить таблицу</button>
                
                <div id="insertRowForm" class="hidden" style="margin-top:10px;">
                    <h5>Добавить строку в таблицу: <span id="insertRowTableName"></span></h5>
                    <form id="insertRowDataForm">
                        <div id="insertRowFields"></div>
                        <button type="submit">Добавить строку</button>
                    </form>
                </div>
                
                <div id="createTableForm" class="hidden" style="margin-top:10px;">
                    <h5>Создать таблицу</h5>
                    <form id="createTableDataForm">
                        <label>Имя таблицы: <input type="text" name="table" required></label><br>
                        <div id="columnsContainer">
                            <div class="columnDef" style="margin-bottom:5px;">
                                <label>Имя колонки: <input type="text" name="col_name[]" required></label>
                                <label>Тип: <input type="text" name="col_type[]" placeholder="VARCHAR(255)" required></label>
                                <label>Атрибуты: <input type="text" name="col_attr[]" placeholder="NOT NULL"></label>
                                <label>Первичный: <input type="checkbox" name="col_primary[]" value="1"></label>
                                <button type="button" class="removeColumn">Удалить колонку</button>
                            </div>
                        </div>
                        <button type="button" id="addColumn">Добавить колонку</button><br><br>
                        <button type="submit">Создать таблицу</button>
                    </form>
                </div>
                <div id="menuResult" style="margin-top:10px; color:blue;"></div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function(){
        var currentTable = '';

        // Функция загрузки списка таблиц
        function loadTables() {
            $.ajax({
                url: '?act=listTables',
                method: 'GET',
                success: function(response) {
                    $('#tableList').html(response);
                },
                error: function(){
                    alert('Ошибка загрузки таблиц');
                }
            });
        }
        loadTables();

        // При клике по названию таблицы загружаем её данные и запоминаем выбранную таблицу
        $('#tableList').on('click', 'li', function(){
            currentTable = $(this).text();
            loadTableData(currentTable);
        });

        function loadTableData(table) {
            $.ajax({
                url: '?act=showTable&table=' + encodeURIComponent(table),
                method: 'GET',
                success: function(response) {
                    $('#tableData').html(response);
                },
                error: function(){
                    alert('Ошибка загрузки данных таблицы');
                }
            });
        }

        // Выполнение произвольного SQL‑запроса
        $('#executeQuery').click(function(){
            var query = $('#sqlQuery').val();
            $.ajax({
                url: '?act=executeSQL',
                method: 'POST',
                data: { query: query },
                success: function(response) {
                    $('#queryResult').html(response);
                    loadTables(); // обновляем список таблиц, если изменена схема
                },
                error: function(){
                    alert('Ошибка выполнения запроса');
                }
            });
        });

        // Удаление строки
        $('#tableData').on('click', '.deleteRow', function(){
            if (!confirm("Удалить эту запись?")) return;
            var id = $(this).data('id');
            var table = $(this).data('table');
            $.ajax({
                url: '?act=deleteRow',
                method: 'POST',
                data: { table: table, id: id },
                success: function(response) {
                    alert(response);
                    loadTableData(table);
                },
                error: function(){
                    alert('Ошибка удаления записи');
                }
            });
        });

        // Редактирование ячеек
        $('#tableData').on('blur', '.editable', function(){
            var newValue = $(this).text();
            var table = $(this).data('table');
            var column = $(this).data('column');
            var id = $(this).data('id');
            $.ajax({
                url: '?act=updateCell',
                method: 'POST',
                data: { table: table, id: id, column: column, value: newValue },
                success: function(response) {
                    console.log(response);
                },
                error: function(){
                    alert('Ошибка обновления данных');
                }
            });
        });

        // Переключение меню дополнительных функций
        $('#toggleExtraMenu').click(function(){
            $('#extraMenu').toggleClass('hidden');
        });

        // Экспорт базы данных
        $('#exportDB').click(function(){
            window.location.href = '?act=exportDB';
        });

        // Импорт базы данных
        $('#importForm').submit(function(e){
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('act', 'importDB');
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    $('#menuResult').html(response);
                    loadTables();
                },
                error: function(){
                    alert('Ошибка импорта базы данных');
                }
            });
        });

        // Показ формы добавления строки
        $('#showInsertRow').click(function(){
            if (!currentTable) {
                alert("Выберите таблицу из списка слева.");
                return;
            }
            $('#insertRowTableName').text(currentTable);
            // Получаем список столбцов для выбранной таблицы
            $.ajax({
                url: '?act=getColumns&table=' + encodeURIComponent(currentTable),
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if(data.error){
                        alert("Ошибка: " + data.error);
                        return;
                    }
                    var html = '';
                    $.each(data, function(index, col){
                        html += '<label>' + col.Field + ': <input type="text" name="' + col.Field + '"></label><br>';
                    });
                    $('#insertRowFields').html(html);
                    $('#insertRowForm').removeClass('hidden');
                },
                error: function(){
                    alert('Ошибка получения столбцов');
                }
            });
        });

        // Отправка формы добавления строки
        $('#insertRowDataForm').submit(function(e){
            e.preventDefault();
            var fields = {};
            $('#insertRowDataForm').find('input').each(function(){
                var fieldName = $(this).attr('name');
                fields[fieldName] = $(this).val();
            });
            $.ajax({
                url: '?act=insertRow',
                method: 'POST',
                data: { table: currentTable, fields: JSON.stringify(fields) },
                success: function(response) {
                    $('#menuResult').html(response);
                    $('#insertRowForm').addClass('hidden');
                    loadTableData(currentTable);
                },
                error: function(){
                    alert('Ошибка добавления строки');
                }
            });
        });

        // Показ формы создания таблицы
        $('#showCreateTable').click(function(){
            $('#createTableForm').toggleClass('hidden');
        });

        // Добавление новой строки для описания колонки
        $('#addColumn').click(function(){
            var newColumn = '<div class="columnDef" style="margin-bottom:5px;">'+
                '<label>Имя колонки: <input type="text" name="col_name[]" required></label> '+
                '<label>Тип: <input type="text" name="col_type[]" placeholder="VARCHAR(255)" required></label> '+
                '<label>Атрибуты: <input type="text" name="col_attr[]" placeholder="NOT NULL"></label> '+
                '<label>Первичный: <input type="checkbox" name="col_primary[]" value="1"></label> '+
                '<button type="button" class="removeColumn">Удалить колонку</button>'+
                '</div>';
            $('#columnsContainer').append(newColumn);
        });

        // Удаление строки описания колонки
        $(document).on('click', '.removeColumn', function(){
            $(this).closest('.columnDef').remove();
        });

        // Отправка формы создания таблицы
        $('#createTableDataForm').submit(function(e){
            e.preventDefault();
            var tableName = $(this).find('input[name="table"]').val();
            var columns = [];
            $('#columnsContainer .columnDef').each(function(){
                var colName = $(this).find('input[name="col_name[]"]').val();
                var colType = $(this).find('input[name="col_type[]"]').val();
                var colAttr = $(this).find('input[name="col_attr[]"]').val();
                var colPrimary = $(this).find('input[name="col_primary[]"]').is(':checked');
                columns.push({
                    name: colName,
                    type: colType,
                    attributes: colAttr,
                    primary: colPrimary
                });
            });
            $.ajax({
                url: '?act=createTable',
                method: 'POST',
                data: { table: tableName, columns: JSON.stringify(columns) },
                success: function(response) {
                    $('#menuResult').html(response);
                    $('#createTableForm').addClass('hidden');
                    loadTables();
                },
                error: function(){
                    alert('Ошибка создания таблицы');
                }
            });
        });

        // Удаление текущей таблицы
        $('#deleteCurrentTable').click(function(){
            if (!currentTable) {
                alert("Выберите таблицу для удаления.");
                return;
            }
            if (!confirm("Вы уверены, что хотите удалить таблицу " + currentTable + "? Это действие нельзя отменить!")) return;
            $.ajax({
                url: '?act=deleteTable',
                method: 'POST',
                data: { table: currentTable },
                success: function(response) {
                    $('#menuResult').html(response);
                    currentTable = '';
                    $('#tableData').html('');
                    loadTables();
                },
                error: function(){
                    alert('Ошибка удаления таблицы');
                }
            });
        });
    });
    </script>
</body>
</html>
