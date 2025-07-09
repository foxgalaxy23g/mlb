<?php
// Корневая директория, с которой начинается проводник
$baseDir = realpath(__DIR__ . '/files');

// Получаем путь, который хотим показать, из параметра URL
// Например: ?path=documents/subfolder
$path = isset($_GET['path']) ? $_GET['path'] : '';

// Формируем полный путь к текущей папке
$currentDir = realpath($baseDir . DIRECTORY_SEPARATOR . $path);

// Безопасность: запрещаем выходить за пределы базовой папки
if ($currentDir === false || strpos($currentDir, $baseDir) !== 0) {
    die('Ошибка: доступ запрещён.');
}

// Получаем список файлов и папок
$items = scandir($currentDir);

// Для построения ссылки к родительской папке
function getParentPath($path) {
    $parts = explode('/', trim($path, '/'));
    array_pop($parts);
    return implode('/', $parts);
}

// HTML вывод
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Файловый проводник</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        ul { list-style: none; padding-left: 0; }
        li { margin: 5px 0; }
        a.folder { font-weight: bold; color: #007bff; text-decoration: none; }
        a.folder:hover { text-decoration: underline; }
        a.file { color: #333; text-decoration: none; }
        a.file:hover { text-decoration: underline; }
    </style>
</head>
<body>
<h2>Файловый проводник</h2>

<?php if ($path !== ''): ?>
    <p><a href="?path=<?= urlencode(getParentPath($path)) ?>">⬅ Назад</a></p>
<?php endif; ?>

<ul>
<?php
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;

    $itemPath = trim($path . '/' . $item, '/');
    $fullPath = $currentDir . DIRECTORY_SEPARATOR . $item;

    if (is_dir($fullPath)) {
        // Папка — ссылка ведёт внутрь неё
        echo "<li>📁 <a class='folder' href='?path=" . urlencode($itemPath) . "'>$item</a></li>";
    } else {
        // Файл — ссылка ведёт на скачивание/просмотр
        $fileUrl = 'files/' . str_replace('\\', '/', $itemPath);
        echo "<li>📄 <a class='file' href='$fileUrl' target='_blank'>$item</a></li>";
    }
}
?>
</ul>
</body>
</html>
