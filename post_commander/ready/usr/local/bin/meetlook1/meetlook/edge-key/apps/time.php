<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мировое время</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
        }

        .time-zones {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .time-card {
            background-color: #e9e9e9;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            min-width: 200px;
        }

        h2 {
            color: #555;
            font-size: 1.2em;
            margin-top: 0;
        }

        p {
            font-size: 1.8em;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Мировое время</h1>
        <div class="time-zones">
            <div class="time-card">
                <h2>Ваше местное время</h2>
                <p id="local-time"></p>
            </div>
            <div class="time-card">
                <h2>Лондон (GMT)</h2>
                <p id="london-time"></p>
            </div>
            <div class="time-card">
                <h2>Нью-Йорк (EST)</h2>
                <p id="newyork-time"></p>
            </div>
            <div class="time-card">
                <h2>Токио (JST)</h2>
                <p id="tokyo-time"></p>
            </div>
        </div>
    </div>
    <script>
        function updateTime() {
            // Получаем местное время
            const now = new Date();
            document.getElementById('local-time').textContent = now.toLocaleTimeString('ru-RU');
                
            // Время в Лондоне
            const londonTime = new Date(now.toLocaleString('en-US', { timeZone: 'Europe/London' }));
            document.getElementById('london-time').textContent = londonTime.toLocaleTimeString('ru-RU');
                
            // Время в Нью-Йорке
            const newyorkTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/New_York' }));
            document.getElementById('newyork-time').textContent = newyorkTime.toLocaleTimeString('ru-RU');
                
            // Время в Токио
            const tokyoTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
            document.getElementById('tokyo-time').textContent = tokyoTime.toLocaleTimeString('ru-RU');
        }
        
        // Обновляем время сразу, как только страница загрузится
        updateTime();
        
        // Обновляем время каждую секунду
        setInterval(updateTime, 1000);
    </script>
</body>
</html>