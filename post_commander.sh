#!/bin/bash
#
# Скрипт для компиляции кастомного ПО и его перемещения 
# в сборочную директорию live-build для последующей интеграции в дистрибутив.
#

# Прекращаем выполнение скрипта при возникновении любой ошибки
set -e

# --- ОСНОВНЫЕ ПЕРЕМЕННЫЕ ---
# Определяем базовую директорию, где находится этот скрипт, для построения абсолютных путей.
BASE_DIR=$(pwd)

# Директория с исходным кодом для компиляции
CODE_DIR="$BASE_DIR/post_commander/code"

# Директория для "готовых" файлов перед их переносом в live-build
READY_DIR="$BASE_DIR/post_commander/ready"

# Целевая директория для скомпилированных бинарных файлов
TARGET_INCLUDE_DIR="$READY_DIR/usr/local/bin/edge"

# Директория в live-build для включения файлов в корневую систему chroot
CHROOT_INCLUDES_DIR="$BASE_DIR/live-build/config/includes.chroot"

PACKAGES="$BASE_DIR/live-build/config/package-lists"

echo "🚀 Начало сборки и подготовки кастомного ПО..."

# 1. Переход в директорию с исходным кодом и запуск компиляции
echo "📂 Переход в директорию: $CODE_DIR"
cd "$CODE_DIR"

# Проверяем, существует ли скрипт сборки
if [ ! -f "build_software.sh" ]; then
    echo "❌ Ошибка: Скрипт 'build_software.sh' не найден в директории $CODE_DIR"
    exit 1
fi

echo "🛠️  Запуск скрипта сборки 'build_software.sh'..."
# Предоставляем права на исполнение для скрипта сборки
chmod +x build_software.sh
# Запускаем скрипт
./build_software.sh
echo "✅ Сборка успешно завершена."

# 2. Перемещение скомпилированных файлов в "готовую" директорию
echo "📦 Перемещение скомпилированных файлов..."

# Создаем целевую директорию для бинарников, если она не существует
mkdir -p "$TARGET_INCLUDE_DIR"

# Ищем файлы без расширения в текущей директории (CODE_DIR) и перемещаем их.
# -maxdepth 1: искать только в текущей папке, не заходя в подпапки.
# -type f: искать только файлы.
# ! -name "*.*": искать файлы, в имени которых нет точки (т.е. без расширения).
# -exec mv -v {} ...: выполнить команду mv для каждого найденного файла.
find . -maxdepth 1 -type f ! -name "*.*" -exec mv -v {} "$TARGET_INCLUDE_DIR/" \;

echo "✅ Скомпилированные файлы перемещены в $TARGET_INCLUDE_DIR"

# Возвращаемся в исходную директорию, откуда был запущен скрипт
cd "$BASE_DIR"

# 3. Перемещение всех подготовленных файлов и папок в директорию live-build
echo "🚚 Перемещение подготовленной структуры в live-build..."

# Создаем директорию для chroot в live-build, если она не существует
mkdir -p "$CHROOT_INCLUDES_DIR"

# Проверяем, есть ли что-либо в директории READY_DIR для перемещения
if [ -z "$(ls -A "$READY_DIR")" ]; then
   echo "⚠️  Директория $READY_DIR пуста. Пропускаем шаг перемещения."
else
   # Копируем все содержимое из READY_DIR в CHROOT_INCLUDES_DIR.
   # Использование 'cp -a' сохраняет права и структуру.
   cp -a "$READY_DIR"/* "$CHROOT_INCLUDES_DIR/"
   
   # Очищаем директорию 'ready' после успешного копирования
   sudo rm -r "$TARGET_INCLUDE_DIR"/*
   #echo "✅ Файлы и папки успешно перемещены в $CHROOT_INCLUDES_DIR"
fi

sudo cp -r post_commander/debian-apps.list.chroot live-build/config/package-lists

echo "🎉 Все операции успешно завершены!"
