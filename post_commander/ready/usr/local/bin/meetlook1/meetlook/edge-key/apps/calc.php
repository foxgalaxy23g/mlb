<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Простой Калькулятор</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Убираем min-height, чтобы body не создавал лишнюю прокрутку при росте контента на мобильных */
            height: 100vh; /* Высота ровно на весь видимый экран */
            width: 100vw;  /* Ширина ровно на весь видимый экран */
            background-color: #f0f0f0;
            margin: 0;
            padding: 0; /* Убираем все отступы у body */
            box-sizing: border-box;
            overflow: hidden; /* Важно: скрываем прокрутку, если калькулятор вдруг вылезет за границы */
        }
        
        .calculator {
            border: none; /* Убираем рамку */
            border-radius: 0; /* Убираем скругление углов */
            width: 100%; /* Калькулятор занимает всю ширину */
            height: 100%; /* Калькулятор занимает всю высоту */
            max-width: none; /* Убираем ограничение по максимальной ширине */
            max-height: none; /* Убираем ограничение по максимальной высоте */
            box-shadow: none; /* Убираем тень */
            background-color: #fff;
            display: flex; /* Делаем калькулятор flex-контейнером */
            flex-direction: column; /* Элементы внутри будут располагаться вертикально */
        }
        
        .calculator-screen {
            width: 100%;
            /* Высота теперь будет адаптивной, благодаря flex-grow */
            min-height: 20vh; /* Минимальная высота экрана, чтобы он был виден */
            border: none;
            background-color: #252525;
            color: #fff;
            text-align: right;
            padding: 20px; /* Увеличим отступы, чтобы текст не прилипал к краям */
            font-size: 4em; /* Увеличим размер шрифта, чтобы было хорошо видно на мобильных */
            box-sizing: border-box;
            border-radius: 0; /* Убираем скругление углов */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex; /* Сделаем flex-контейнером для центрирования текста по вертикали */
            align-items: flex-end; /* Выравниваем текст по нижнему краю */
            justify-content: flex-end; /* Выравниваем текст по правому краю */
            flex-grow: 1; /* Позволяет экрану занимать все доступное место */
        }
        
        .calculator-keys {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            /* grid-gap: 10px; */ /* Отступы между кнопками */
            padding: 0; /* Убираем padding у контейнера кнопок */
            flex-grow: 2; /* Позволяет кнопкам занимать больше места, чем экран */
            /* Устанавливаем минимальную высоту для кнопок, чтобы они не были слишком маленькими */
            min-height: 50vh;
        }
        
        .calculator-keys button {
            height: auto; /* Высота кнопок будет автоматически распределяться */
            width: 100%; /* Кнопки занимают всю доступную ширину в своей ячейке */
            font-size: 2.2em; /* Увеличим размер шрифта на кнопках */
            border: 1px solid rgba(0, 0, 0, 0.1); /* Добавим тонкие разделители между кнопками */
            border-radius: 0; /* Убираем скругление кнопок */
            cursor: pointer;
            background-color: #e0e0e0;
            transition: background-color 0.2s, transform 0.1s;
            outline: none;
            padding: 10px; /* Добавим внутренние отступы для текста на кнопках */
            box-sizing: border-box;
            display: flex; /* Для центрирования текста на кнопках */
            justify-content: center;
            align-items: center;
        }
        
        .calculator-keys button:hover {
            background-color: #d0d0d0;
        }
        
        .calculator-keys button:active {
            transform: translateY(1px);
        }
        
        .operator {
            background-color: #f2a60c;
            color: #fff;
        }
        
        .operator:hover {
            background-color: #d9920b;
        }
        
        .equal-sign {
            background-color: #28a745;
            color: #fff;
            grid-column: span 2;
        }
        
        .equal-sign:hover {
            background-color: #218838;
        }
        
        .clear {
            background-color: #dc3545;
            color: #fff;
        }
        
        .clear:hover {
            background-color: #c82333;
        }
        
        /* Удаляем медиа-запросы, так как теперь все адаптивно благодаря flexbox и viewport units */
        /* Если все же понадобятся очень специфические изменения, можно добавить */
    </style>
</head>
<body>
    <div class="calculator">
        <input type="text" class="calculator-screen" value="" disabled />
        <div class="calculator-keys">
            <button type="button" class="operator" value="+">+</button>
            <button type="button" class="operator" value="-">-</button>
            <button type="button" class="operator" value="*">&times;</button>
            <button type="button" class="operator" value="/">÷</button>

            <button type="button" value="7">7</button>
            <button type="button" value="8">8</button>
            <button type="button" value="9">9</button>

            <button type="button" value="4">4</button>
            <button type="button" value="5">5</button>
            <button type="button" value="6">6</button>

            <button type="button" value="1">1</button>
            <button type="button" value="2">2</button>
            <button type="button" value="3">3</button>

            <button type="button" value="0">0</button>
            <button type="button" class="decimal" value=".">.</button>
            <button type="button" class="clear" value="clear">C</button>

            <button type="button" class="equal-sign operator" value="=">=</button>
        </div>
    </div>
    <script>
        const calculator = document.querySelector('.calculator');
        const display = document.querySelector('.calculator-screen');
        const keys = document.querySelector('.calculator-keys');

        let firstValue = '';
        let operator = '';
        let secondValue = '';
        let result = '';
        let waitingForSecondValue = false; // Флаг для отслеживания ввода второго числа

        keys.addEventListener('click', e => {
            const target = e.target;
            const value = target.value;
        
            if (!target.matches('button')) {
                return;
            }
        
            // Если это цифра или десятичная точка
            if (target.classList.contains('number') || (!isNaN(value) || value === '.')) {
                if (waitingForSecondValue === true) {
                    secondValue += value;
                    display.value = secondValue;
                } else {
                    firstValue += value;
                    display.value = firstValue;
                }
            }
        
            // Если это оператор
            if (target.classList.contains('operator')) {
                if (value === '=') {
                    if (firstValue && operator && (secondValue || secondValue === '0')) {
                        result = calculate(firstValue, operator, secondValue);
                        display.value = result;
                        firstValue = result; // Результат становится первым числом для следующей операции
                        secondValue = '';
                        operator = '';
                        waitingForSecondValue = false;
                    }
                } else {
                    if (firstValue && !waitingForSecondValue) {
                        operator = value;
                        waitingForSecondValue = true;
                        secondValue = ''; // Сбросить второе значение, если оператор меняется
                    } else if (firstValue && waitingForSecondValue && secondValue === '') {
                        // Если оператор меняется до ввода второго числа
                        operator = value;
                    }
                }
            }
        
            // Если это кнопка очистки
            if (target.classList.contains('clear')) {
                firstValue = '';
                operator = '';
                secondValue = '';
                result = '';
                waitingForSecondValue = false;
                display.value = '';
            }
        });

        function calculate(n1, op, n2) {
            let num1 = parseFloat(n1);
            let num2 = parseFloat(n2);
        
            if (op === '+') {
                return num1 + num2;
            }
            if (op === '-') {
                return num1 - num2;
            }
            if (op === '*') {
                return num1 * num2;
            }
            if (op === '/') {
                if (num2 === 0) {
                    return 'Ошибка: Деление на 0';
                }
                return num1 / num2;
            }
        }
    </script>
</body>
</html>