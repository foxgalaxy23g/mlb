#!/bin/bash

# Скрипт для сборки ТОЛЬКО оконного менеджера tinywm-plus

# Явно перечисляем исходные файлы для WM
SOURCES="main.c client.c events.c actions.c api.c"

# Имя выходного файла
OUTPUT="mywm"

# Флаги компилятора
# -o: задает имя выходного файла
# -lX11: подключает библиотеку X11
# -Wall: включает все предупреждения компилятора
CFLAGS="-lX11 -Wall"

echo "--- Compiling Window Manager ---"
echo "Sources: $SOURCES"

# Команда компиляции
gcc $SOURCES -o $OUTPUT $CFLAGS

# Проверяем, прошла ли компиляция успешно
if [ $? -eq 0 ]; then
    echo "Compilation successful! Executable: $OUTPUT"
else
    echo "Compilation failed."
fi
