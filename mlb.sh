#!/bin/bash

if [ "$EUID" -ne 0 ]; then
  echo "Meetlook Linux Builder должен быть запущен с правами root. Используйте sudo" >&2
  exit 1
fi

# Основной код скрипта ниже
#echo "Meetlook Linux Builder by foxgalaxy23"

# Проверка и установка зависимостей
REQUIRED_PKGS=(live-build squashfs-tools xorriso rsync syslinux isolinux)

echo "🔍 Проверка зависимостей..."

for pkg in "${REQUIRED_PKGS[@]}"; do
  if ! dpkg -s "$pkg" &> /dev/null; then
    echo "📦 Пакет '$pkg' не установлен. Устанавливаю..."
    sudo apt-get update
    sudo apt-get install -y "$pkg"
  else
    echo "✅ $pkg найден"
  fi
done

# Проверка наличия lb
if ! command -v lb &> /dev/null; then
  echo "❌ Команда 'lb' не найдена. Проверь, установлен ли live-build"
  exit 1
fi

set -e

# === НАСТРОЙКИ ===
DISTRO="bookworm"
BUILD_DIR="$PWD/live-build"
OUTPUT_ISO="$BUILD_DIR/live-image-amd64.hybrid.iso"

WORKDIR="$PWD/liveiso"
EXTRACT="$WORKDIR/extract"
FS="$WORKDIR/filesystem"
FINAL_ISO="$WORKDIR/custom-live.iso"

COMMANDS_FILE="$PWD/commands.txt"

# === 1. Компиляция Live OS ===
echo "🛠️ 1. Сборка live-build дистрибутива..."

mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

echo "🧼 Очистка старой сборки..."
sudo lb clean --all || true

echo "⚙️ Конфигурация Live Build..."
sudo lb config --distribution "$DISTRO" --binary-images iso-hybrid || {
  echo "❌ Ошибка конфигурации live-build"
  exit 1
}

echo "📦 Сборка ISO. Это может занять несколько минут..."
sudo lb build

if [ ! -f "$OUTPUT_ISO" ]; then
  echo "❌ ISO не найдено после сборки: $OUTPUT_ISO"
  exit 1
fi

cd "$PWD"

# === 2. Распаковка ISO и подготовка chroot ===
echo "🗃️ 2. Создание рабочих директорий..."
mkdir -p "$EXTRACT" "$FS"

echo "📦 3. Монтирование и копирование ISO..."
sudo mount -o loop "$OUTPUT_ISO" /mnt
rsync -a /mnt/ "$EXTRACT/"
sudo umount /mnt

echo "📤 4. Распаковка squashfs..."
sudo unsquashfs -d "$FS" "$EXTRACT/live/filesystem.squashfs"

echo "🔧 5. Подготовка chroot окружения..."
sudo mount --bind /dev  "$FS/dev"
sudo mount --bind /dev/pts "$FS/dev/pts"
sudo mount -t sysfs sys "$FS/sys"
sudo mount -t proc proc "$FS/proc"
sudo cp /etc/resolv.conf "$FS/etc/resolv.conf"

# === 6. Выполнение команд из commands.txt ===
if [ -f "$COMMANDS_FILE" ]; then
  echo "📜 Выполнение команд из commands.txt внутри chroot..."
  # Копируем файл внутрь
  sudo cp "$COMMANDS_FILE" "$FS/root/commands.txt"
  sudo chroot "$FS" /bin/bash -c "bash /root/commands.txt && rm /root/commands.txt"
else
  echo "⚠️ Файл commands.txt не найден, пропускаю автоматические команды."
fi

# === 7. Ручной chroot ===
echo "🚪 7. Вход в chroot. Выйдите через 'exit' когда закончите."
sudo chroot "$FS" /bin/bash

# === 8. Очистка окружения ===
echo "🧹 8. Очистка chroot окружения..."
sudo umount "$FS/dev/pts"
sudo umount "$FS/dev"
sudo umount "$FS/proc"
sudo umount "$FS/sys"

echo "📦 9. Пересборка squashfs..."
sudo mksquashfs "$FS" "$EXTRACT/live/filesystem.squashfs" -noappend

echo "🧽 Удаление временной rootfs..."
sudo rm -rf "$FS"

# === 10. Сборка ISO обратно ===
MBR_BIN="/usr/lib/ISOLINUX/isohdpfx.bin"
[ ! -f "$MBR_BIN" ] && MBR_BIN="/usr/lib/syslinux/isohdpfx.bin"

echo "💿 10. Сборка итогового ISO..."
cd "$EXTRACT"

sudo xorriso -as mkisofs \
  -iso-level 3 \
  -o "$FINAL_ISO" \
  -full-iso9660-filenames \
  -volid "Custom Live" \
  -isohybrid-mbr "$MBR_BIN" \
  -c isolinux/boot.cat \
  -b isolinux/isolinux.bin \
  -no-emul-boot -boot-load-size 4 -boot-info-table \
  -eltorito-alt-boot \
  -e boot/grub/efi.img \
  -no-emul-boot \
  .

cd "$PWD"

echo "✅ Готово! Новый ISO: $FINAL_ISO"

