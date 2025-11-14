#!/bin/bash

# Быстрое исправление прав доступа к storage и logs
# Использование: sudo bash fix-storage-permissions.sh

echo "🔧 Исправление прав доступа к storage и logs..."

PROJECT_PATH="/var/www/html/NotifyBus"

if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Директория $PROJECT_PATH не найдена!"
    echo "Укажите правильный путь к проекту."
    exit 1
fi

cd "$PROJECT_PATH"

# Определяем пользователя веб-сервера
WEB_USER="www-data"

if ! id "$WEB_USER" &>/dev/null; then
    echo "⚠️  Пользователь $WEB_USER не найден. Используем текущего пользователя."
    WEB_USER=$(whoami)
fi

echo "📝 Устанавливаем права для пользователя: $WEB_USER"

# Создаем необходимые директории
mkdir -p storage/logs
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public
mkdir -p bootstrap/cache

# Устанавливаем владельца
sudo chown -R "$WEB_USER:$WEB_USER" storage
sudo chown -R "$WEB_USER:$WEB_USER" bootstrap/cache

# Устанавливаем права
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# Создаем файл лога, если его нет
sudo touch storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log
sudo chown "$WEB_USER:$WEB_USER" storage/logs/laravel.log

echo "✅ Права доступа установлены"
echo ""
echo "📋 Проверка:"
ls -la storage/logs/laravel.log
ls -la storage/ | head -10

echo ""
echo "✅ Готово! Теперь можно запускать команды Laravel."













