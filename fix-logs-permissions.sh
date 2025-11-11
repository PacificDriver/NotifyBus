#!/bin/bash

# Быстрое исправление прав доступа к логам
# Использование: sudo bash fix-logs-permissions.sh

echo "🔧 Исправление прав доступа к логам..."

PROJECT_PATH="/var/www/html/NotifyBus"

if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Директория $PROJECT_PATH не найдена!"
    echo "Укажите правильный путь к проекту."
    exit 1
fi

cd "$PROJECT_PATH"

# Создаем директорию, если её нет
mkdir -p storage/logs

# Устанавливаем владельца
WEB_USER="www-data"

if ! id "$WEB_USER" &>/dev/null; then
    echo "⚠️  Пользователь $WEB_USER не найден. Используем текущего пользователя."
    WEB_USER=$(whoami)
fi

# Создаем файл лога, если его нет
touch storage/logs/laravel.log

# Устанавливаем права
sudo chown -R "$WEB_USER:$WEB_USER" storage/logs
sudo chmod -R 775 storage/logs
sudo chmod 664 storage/logs/laravel.log

echo "✅ Права доступа к логам исправлены"
echo ""
echo "📋 Проверка:"
ls -la storage/logs/laravel.log

echo ""
echo "✅ Готово! Теперь можно запускать команды Laravel."







