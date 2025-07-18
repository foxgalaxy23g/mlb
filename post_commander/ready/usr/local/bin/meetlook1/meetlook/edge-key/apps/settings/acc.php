<?php
include '../../info.php';
?>
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
    <a href="settings.php" class="hyperlink"><h1>Учётные записи</h1></a>
    <div class="menu">
        <div class="menu-button">
            <a href="make_profile.php" class="hyperlink">Создать новую учётную запись</a>
        </div>
    </div>   
    <p>Текущая учётная запись : <?php echo $current_username; ?></p> 
</body>
</html>