#!/bin/bash

# Скрипт для исправления прав доступа и настройки PostgreSQL
# Использование: sudo bash fix-permissions.sh

echo "🔧 Исправление прав доступа..."

# Определяем путь к проекту
PROJECT_PATH="/var/www/html/NotifyBus"

# Проверяем, существует ли путь
if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Директория $PROJECT_PATH не найдена!"
    echo "Укажите правильный путь к проекту."
    exit 1
fi

cd "$PROJECT_PATH"

# Создаем необходимые директории, если их нет
mkdir -p storage/logs
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public
mkdir -p bootstrap/cache

echo "✅ Директории созданы"

# Устанавливаем владельца (замените www-data на вашего пользователя веб-сервера)
WEB_USER="www-data"

# Проверяем, существует ли пользователь
if ! id "$WEB_USER" &>/dev/null; then
    echo "⚠️  Пользователь $WEB_USER не найден. Используем текущего пользователя."
    WEB_USER=$(whoami)
fi

# Устанавливаем права
echo "📝 Устанавливаем права доступа для $WEB_USER..."

# Владелец для всех файлов
sudo chown -R "$WEB_USER:$WEB_USER" "$PROJECT_PATH"

# Базовые права для всего проекта
sudo chmod -R 755 "$PROJECT_PATH"

# Права на запись для storage и bootstrap/cache
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# Убеждаемся, что файлы логов созданы и доступны
sudo touch storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log
sudo chown "$WEB_USER:$WEB_USER" storage/logs/laravel.log

echo "✅ Права доступа установлены"
echo ""
echo "📋 Проверка прав:"
ls -la storage/logs/ | head -5
ls -la bootstrap/cache/ | head -5

echo ""
echo "✅ Готово! Теперь попробуйте запустить миграции снова."
echo ""
echo "Если проблема сохраняется, проверьте:"
echo "  1. Пользователь веб-сервера (обычно www-data или nginx)"
echo "  2. Права на директорию проекта"
echo "  3. SELinux (если используется): sudo setsebool -P httpd_unified 1"

