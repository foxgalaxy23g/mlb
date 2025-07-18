<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .menu-button{
            background-color: rgba(0, 0, 0, 0); 
            border-radius: 10px;
        }
        .hyperlink{
            color: black; 
            text-decoration: none;
        }
        .icon{
            width: 40px;  /* Уменьшил для компактности */
            height: 40px; /* Уменьшил для компактности */
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <h1>Настройки</h1>
    <div class="menu">
        <div class="menu-button">
            <a href="acc.php" class="hyperlink">Учётная запись</a>
        </div>
        <p></p>
        <div class="menu-button">
            <a href="pers.php" class="hyperlink"><img src="" alt="">Персонализация</a>
        </div>
        <p></p>
        <div class="menu-button">
            <a href="developer.php" class="hyperlink"><img src="" alt="">Для разработчиков</a>
        </div>
    </div>    
</body>
</html>