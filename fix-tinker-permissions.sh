#!/bin/bash

# Исправление прав доступа для Tinker
# Использование: sudo bash fix-tinker-permissions.sh

echo "🔧 Исправление прав доступа для Tinker..."

# Создаем директорию для конфигурации psysh
PSYSH_DIR="/var/www/.config/psysh"

# Создаем директорию, если её нет
sudo mkdir -p "$PSYSH_DIR"

# Устанавливаем владельца
sudo chown -R www-data:www-data "$PSYSH_DIR"

# Устанавливаем права
sudo chmod -R 755 "$PSYSH_DIR"

echo "✅ Права доступа для Tinker установлены"
echo ""
echo "📋 Проверка:"
ls -la "$PSYSH_DIR"

echo ""
echo "✅ Теперь можно запускать: sudo -u www-data php artisan tinker"








