import subprocess
import os
import time

# Определяем директорию, в которой находится сам скрипт
script_dir = os.path.dirname(os.path.abspath(__file__))

# Строим полные пути к файлам относительно директории скрипта
ui_path = os.path.join(script_dir, "edge-key")
sql_path = os.path.join(script_dir, "edge.sql")

# Запуск MariaDB - ЭТОТ БЛОК УДАЛЕН ИЛИ ЗАКОММЕНТИРОВАН!
# print("[+] Запуск MariaDB...")
# subprocess.run(["service", "mysql", "start"])

# Инициализация БД (только если не существует)
print("[+] Проверка базы данных...")
# Убедись, что MySQL/MariaDB уже запущен systemd до выполнения этого скрипта
check_db = subprocess.run(
    ['mysql', '-u', 'root', '-e', "SHOW DATABASES LIKE 'edge';"],
    capture_output=True, text=True
)

if "edge" not in check_db.stdout:
    print("[+] Инициализация базы edge из SQL дампа...")
    subprocess.run(["mysql", "-u", "root", "-e", f"source {sql_path}"])
else:
    print("[*] База данных уже существует. Пропускаем инициализацию.")

# Запуск PHP-сервера
print("[+] Запуск PHP UI...")
php_server_process = subprocess.Popen(["php", "-S", "127.0.0.1:7777", "-t", ui_path])


# --- НОВЫЙ БЛОК КОДА ---
# Этот блок не даст скрипту завершиться и позволит остановить его через Ctrl+C

print("\n[*] Сервер запущен на http://127.0.0.1:7777") # Изменил порт на 7777, как в твоем скрипте
print("[*] Нажми CTRL+C для остановки.")

try:
    # Бесконечный цикл, чтобы основной скрипт не завершался
    while True:
        time.sleep(1)
except KeyboardInterrupt:
    print("\n[+] Остановка сервера...")
    php_server_process.terminate()  # Останавливаем дочерний процесс PHP
    print("[*] Сервер остановлен.")
# --- КОНЕЦ НОВОГО БЛОКА ---
